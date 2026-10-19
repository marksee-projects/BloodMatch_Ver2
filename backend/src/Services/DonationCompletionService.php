<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\{DonationReportRepository,DonationWorkflowRepository,MatchRepository,UserRepository};
use RuntimeException;

final class DonationCompletionService
{
    public static function responseAt(array $match, ?string $reportedAt=null): string
    {
        if (!empty($match['responded_at']) && ($reportedAt===null || $match['responded_at']<=$reportedAt)) return $match['responded_at'];
        $s=Database::pdo()->prepare("SELECT MAX(created_at) FROM audit_log WHERE action='match.responded' AND actor_id=?
            AND target_type='blood_request' AND target_id=? AND JSON_UNQUOTE(JSON_EXTRACT(context,'$.match_id'))=? AND created_at<=?");
        $s->execute([$match['donor_id'],(string)$match['request_id'],(string)$match['id'],$reportedAt??AuthService::nowUtc()]);
        $at=$s->fetchColumn();
        if (!$at) throw new RuntimeException('The recorded donor response time is missing. Administrative clarification is required; no date will be invented.',409);
        return $at;
    }

    public function submit(array $actor,int $matchId,mixed $occurredAt,?string $note): array
    {
        DonationWorkflowRepository::requireReady();DonationCompletionPolicy::requireDateApproval();
        $note=$note===null || trim($note)===''?null:DonationCompletionPolicy::basis($note);
        $pre=(new MatchRepository())->findByIdDetailed($matchId);
        if (!$pre || (int)$pre['donor_id']!==(int)$actor['id']) throw new RuntimeException('Only the matched donor can report this donation.',403);
        return WorkflowLockService::transaction(function() use($actor,$pre,$matchId,$occurredAt,$note) {
            $request=WorkflowLockService::request((int)$pre['request_id']);
            $users=WorkflowLockService::users([(int)$actor['id'],(int)$request['requester_id']]);$donor=$users[(int)$actor['id']];
            WorkflowLockService::assertDonorActor($donor);
            if ($donor['verification_status']!=='verified') throw new RuntimeException('Verified membership is required to submit a donation report.',403);
            if ((int)$donor['id']===(int)$request['requester_id']) throw new RuntimeException('You cannot report a donation to your own request.',403);
            $match=(new MatchRepository())->findByIdForUpdate($matchId);
            if (!$match || (int)$match['donor_id']!==(int)$donor['id'] || (int)$match['request_id']!==(int)$request['id']) throw new RuntimeException('Match not found.',404);
            if (!in_array($match['status'],['RESPONDED','CLOSED'],true)) throw new RuntimeException('This match has no reportable donor response.',409);
            $now=AuthService::nowUtc();$occurred=DonationCompletionPolicy::occurrence($occurredAt,self::responseAt($match),$now);
            $s=Database::pdo()->prepare('SELECT * FROM donation_reports WHERE match_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$s->execute([$matchId]);$prior=$s->fetch();
            if ($prior) {
                if ($prior['status']==='PENDING' && $prior['occurred_at']===$occurred && $prior['report_note']===$note) return ['id'=>(int)$prior['id'],'changed'=>false];
                throw new RuntimeException('This match already has a report. Open it for review or correction.',409);
            }
            $s=Database::pdo()->prepare("INSERT INTO donation_reports(match_id,donor_id,report_note,status,reported_at,occurred_at,workflow_state) VALUES(?,?,?,'PENDING',?,?,'awaiting_requester')");
            $s->execute([$matchId,$donor['id'],$note,$now,$occurred]);$id=(int)Database::pdo()->lastInsertId();
            Database::pdo()->prepare('INSERT INTO donation_report_holds(report_id,donor_id,started_at) VALUES(?,?,?)')->execute([$id,$donor['id'],$now]);
            $row=(new DonationReportRepository())->findByIdForUpdate($id);
            DonationWorkflowRepository::event($row,(int)$donor['id'],'submitted',null,$now);
            self::notify($row,(int)$request['requester_id'],'awaiting_acknowledgment','Please respond to a donation report','A donor reported donating for your request. Open the report and choose Donation received, Did not donate, or Not sure.','submitted');
            return ['id'=>$id,'changed'=>true,'workflow_state'=>'awaiting_requester'];
        });
    }

    private static function requiresAdmin(array $row,array $match): bool
    {
        if ($row['workflow_state']==='clarification' || empty($row['occurred_at'])) return true;
        $s=Database::pdo()->prepare('SELECT 1 FROM notifications WHERE dedup_key=? LIMIT 1');
        $s->execute(["offer-invalid:{$match['id']}:{$match['generation']}:{$row['donor_id']}"]);
        return $s->fetchColumn()!==false;
    }

    private static function complete(array $row,array $request,array $match,array $users,?array $reviewer,string $basis,?string $reason): array
    {
        $now=AuthService::nowUtc();
        if (empty($row['occurred_at'])) throw new RuntimeException('Clarify the actual donation date before recording completion.',409);
        DonationCompletionPolicy::occurrence(str_replace(' ','T',$row['occurred_at']).'Z',self::responseAt($match,$row['reported_at']),$now);
        if (!in_array($match['status'],['RESPONDED','CLOSED'],true)) throw new RuntimeException('This match cannot receive another donation credit.',409);
        $s=Database::pdo()->prepare("SELECT 1 FROM donation_reports WHERE match_id=? AND status='CONFIRMED' LIMIT 1 FOR UPDATE");$s->execute([$match['id']]);
        if ($s->fetchColumn()!==false) throw new RuntimeException('This match already has a recorded donation.',409);
        $s=Database::pdo()->prepare('INSERT INTO donation_credits(report_id,match_id,donor_id,occurred_at,recorded_at,completion_basis) VALUES(?,?,?,?,?,?)');
        $s->execute([$row['id'],$match['id'],$row['donor_id'],$row['occurred_at'],$now,$basis]);
        Database::pdo()->prepare("UPDATE donation_reports SET status='CONFIRMED',workflow_state='completed',completion_basis=?,confirmed_by=?,confirmed_at=?,reviewed_at=COALESCE(?,reviewed_at),review_evidence=? WHERE id=?")
            ->execute([$basis,$reviewer['id']??null,$now,$reviewer!==null?$now:null,$reason,$row['id']]);
        $donor=$users[(int)$row['donor_id']];
        // Historical field name retained for compatibility: it is a platform restriction anchor, not a medical verification claim.
        (new UserRepository())->setLastVerifiedDonation((int)$row['donor_id'],DonationCompletionPolicy::restrictionAnchor($donor['last_verified_donation_at'],$row['occurred_at']));
        (new UserRepository())->setAvailability((int)$row['donor_id'],'standby');
        Database::pdo()->prepare("UPDATE donation_report_holds SET released_at=?,released_by=?,release_type='donation_recorded',release_basis=? WHERE report_id=? AND released_at IS NULL")
            ->execute([$now,$reviewer['id']??null,$basis==='mutual'?'Mutually acknowledged donation recorded.':$reason,$row['id']]);
        (new MatchRepository())->setStatus((int)$match['id'],'COMPLETED');
        $row=array_replace($row,['status'=>'CONFIRMED','workflow_state'=>'completed','completion_basis'=>$basis,'confirmed_at'=>$now,'reviewed_at'=>$reviewer!==null?$now:$row['reviewed_at']]);
        DonationWorkflowRepository::event($row,$reviewer!==null?(int)$reviewer['id']:(int)$request['requester_id'],$basis==='mutual'?'mutually_acknowledged':'admin_confirmed',$reason,$now);
        // A request past its deadline must expire, not become fulfilled on late review.
        if ($request['status']==='OPEN' && RequestService::hasDeadlinePassed($request,$now)) {
            (new RequestLifecycleService())->expireOne((int)$request['id'],$now);$request['status']='EXPIRED';
        }
        $fulfilled=DonationCompletionPolicy::canFulfill($request,MatchRepository::countCompleted((int)$request['id']),$now);
        if ($fulfilled) {
            MatchRepository::fulfillAndCloseUnresolved((int)$request['id']);
            if (AuditLogger::log($reviewer['id']??(int)$request['requester_id'],'request.fulfilled','blood_request',(string)$request['id'],['via'=>'donation_completion'])===null) throw new RuntimeException('Fulfillment audit could not be recorded.',503);
            NotificationService::notify((int)$request['requester_id'],'request.fulfilled','Blood request fulfilled','The requested donation count has been reached.',[
                'related_type'=>'blood_request','related_id'=>(int)$request['id'],'dedup_key'=>"request:{$request['id']}:fulfilled",'email'=>NotificationService::EMAIL_NONE]);
        }
        $title=$basis==='mutual'?'Donation mutually acknowledged':'Donation confirmed by admin';
        foreach(array_unique([(int)$row['donor_id'],(int)$request['requester_id']]) as $recipient) self::notify($row,$recipient,$basis==='mutual'?'mutually_acknowledged':'admin_confirmed',$title,'The donation is recorded in platform history. This is not medical verification.','completed');
        return ['changed'=>true,'workflow_state'=>'completed','completion_basis'=>$basis,'request_fulfilled_now'=>$fulfilled];
    }
    public static function locked(int $id, ?int $actorId, callable $work): mixed
    {
        DonationWorkflowRepository::requireReady();
        $pre=(new DonationReportRepository())->findById($id);
        if (!$pre) throw new RuntimeException('Report not found.',404);
        return WorkflowLockService::transaction(function() use($pre,$id,$actorId,$work) {
            $request=WorkflowLockService::request((int)$pre['request_id']);
            $ids=[(int)$pre['donor_id'],(int)$request['requester_id']];
            if ($actorId!==null) $ids[]=$actorId;
            $users=WorkflowLockService::users($ids);
            $match=(new MatchRepository())->findByIdForUpdate((int)$pre['match_id']);
            $row=(new DonationReportRepository())->findByIdForUpdate($id);
            if (!$row || !$match || (int)$row['match_id']!==(int)$match['id'] || (int)$row['donor_id']!==(int)$match['donor_id']
                || (int)$match['request_id']!==(int)$request['id']) throw new RuntimeException('Report not found.',404);
            $actor=$actorId===null?null:$users[$actorId];
            if ($actor!==null) WorkflowLockService::assertActiveActor($actor);
            return $work($row,$request,$match,$actor,$users);
        });
    }

    public static function notify(array $row,int $recipient,string $event,string $title,string $body,string $key): void
    {
        NotificationService::notify($recipient,'donation.'.$event,$title,$body,[
            'related_type'=>'donation_report','related_id'=>(int)$row['id'],
            'dedup_key'=>'donation-workflow:'.$row['id'].':'.$key.':'.$recipient,
            'email'=>NotificationService::EMAIL_NONE,
        ]);
    }

    public static function notifyAdmins(array $row,array $request,string $event,string $title,string $key): void
    {
        foreach ((new UserRepository())->activeIdsByRole('admin') as $id) {
            if (in_array((int)$id,[(int)$row['donor_id'],(int)$request['requester_id']],true)) continue;
            self::notify($row,(int)$id,$event,$title,'A donation report needs review. Open its private details.',$key);
        }
    }

    public function acknowledge(array $actor,int $id,int $version,string $response,?string $note): array
    {
        if (!in_array($response,['received','did_not_donate','not_sure'],true)) throw new RuntimeException('Choose a requester response.',400);
        $note=$note===null || trim($note)===''?null:DonationCompletionPolicy::basis($note);
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor,$users) use($version,$response,$note) {
            DonationCompletionPolicy::assertRequester($actor,$request,$row);
            DonationCompletionPolicy::assertVersion($row,$version);
            if ($row['requester_response']===$response && (int)$row['acknowledged_version']===$version) return ['changed'=>false];
            DonationCompletionPolicy::assertUnresolved($row);
            if ($row['requester_response']!==null) throw new RuntimeException('This version already has a response. Request clarification before changing it.',409);
            if ($row['workflow_state']==='clarification') throw new RuntimeException('Respond to the requested clarification before acknowledging this report.',409);
            $now=AuthService::nowUtc();
            Database::pdo()->prepare("UPDATE donation_reports SET requester_response=?,acknowledged_by=?,acknowledged_at=?,acknowledged_version=?,workflow_state='needs_review' WHERE id=?")
                ->execute([$response,$actor['id'],$now,$version,$row['id']]);
            $row=array_replace($row,['requester_response'=>$response,'acknowledged_by'=>$actor['id'],'acknowledged_at'=>$now,'acknowledged_version'=>$version,'workflow_state'=>'needs_review']);
            DonationWorkflowRepository::event($row,(int)$actor['id'],'requester_responded',$note,$now);
            if ($response==='received' && !self::requiresAdmin($row,$match)) return self::complete($row,$request,$match,$users,null,'mutual',null);
            self::notify($row,(int)$row['donor_id'],$response==='received'?'review_needed':'disputed','Donation report needs review','This report needs administrative review. It has not been rejected.','response:'.$version);
            self::notifyAdmins($row,$request,'review_needed','Donation report needs review','response:'.$version);
            return ['changed'=>true,'workflow_state'=>'needs_review'];
        });
    }

    public function review(array $actor,int $id,int $version,string $action,mixed $basis): array
    {
        if (!in_array($action,['confirm','reject','clarify'],true)) throw new RuntimeException('Choose a review action.',400);
        $basis=DonationCompletionPolicy::basis($basis);
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor,$users) use($version,$action,$basis) {
            WorkflowLockService::assertReportReviewer($actor,$request,(int)$row['donor_id']);
            if (strtolower((string)$actor['email'])!=='markseejr@gmail.com') throw new RuntimeException('Forbidden.',403);
            DonationCompletionPolicy::assertVersion($row,$version);
            if ($action==='reject' && $row['status']==='REJECTED' && $row['rejection_reason']===$basis) return ['changed'=>false];
            if ($action==='confirm' && $row['status']==='CONFIRMED' && $row['completion_basis']==='admin' && $row['review_evidence']===$basis) return ['changed'=>false];
            if ($action!=='clarify' || $row['status']!=='REJECTED') DonationCompletionPolicy::assertUnresolved($row);
            if ($action==='confirm') {
                return self::complete($row,$request,$match,$users,$actor,'admin',$basis);
            }
            $now=AuthService::nowUtc();
            if ($action==='reject') {
                Database::pdo()->prepare("UPDATE donation_reports SET status='REJECTED',workflow_state='rejected',confirmed_by=?,reviewed_at=?,rejection_reason=? WHERE id=?")
                    ->execute([$actor['id'],$now,$basis,$row['id']]);
                $row=array_replace($row,['status'=>'REJECTED','workflow_state'=>'rejected','reviewed_at'=>$now,'rejection_reason'=>$basis]);
                // No automatic hold release, availability update or medical readiness inference.
            } else {
                $s=Database::pdo()->prepare("SELECT event_type,basis FROM donation_report_events WHERE report_id=? AND event_type='clarification_requested' ORDER BY id DESC LIMIT 1");$s->execute([(int)$row['id']]);$last=$s->fetch();
                if ($row['workflow_state']==='clarification' && $last && $last['event_type']==='clarification_requested' && $last['basis']===$basis) return ['changed'=>false];
                if ($row['status']==='REJECTED') {
                    DonationWorkflowRepository::event($row,(int)$actor['id'],'reopened_for_clarification',$basis,$now);
                    Database::pdo()->prepare('UPDATE donation_reports SET content_version=content_version+1,requester_response=NULL,acknowledged_by=NULL,acknowledged_at=NULL,acknowledged_version=NULL WHERE id=?')->execute([$row['id']]);
                    $row=array_replace($row,['content_version'=>(int)$row['content_version']+1,'requester_response'=>null,'acknowledged_at'=>null,'acknowledged_version'=>null]);
                }
                Database::pdo()->prepare("UPDATE donation_reports SET status='PENDING',workflow_state='clarification',reviewed_at=? WHERE id=?")->execute([$now,$row['id']]);
                // If a rejected report is reopened for correction, restore only its own hold.
                Database::pdo()->prepare('UPDATE donation_report_holds SET released_at=NULL,released_by=NULL,release_basis=NULL,release_type=NULL WHERE report_id=?')->execute([$row['id']]);
                $row['workflow_state']='clarification';$row['status']='PENDING';$row['reviewed_at']=$now;
            }
            $type=$action==='reject'?'rejected':'clarification_requested';
            $event=DonationWorkflowRepository::event($row,(int)$actor['id'],$type,$basis,$now);
            foreach (array_unique([(int)$row['donor_id'],(int)$request['requester_id']]) as $recipient) {
                self::notify($row,$recipient,$type,$action==='reject'?'Donation report rejected':'Donation clarification requested',
                    $action==='reject'?'The report was rejected. A report hold may still apply; rejection does not establish donation readiness.':'An administrator requested clarification. Open the report to respond.',"event:$event");
            }
            return ['changed'=>true,'workflow_state'=>$row['workflow_state']];
        });
    }

    public function clarify(array $actor,int $id,int $version,string $note): array
    {
        $note=DonationCompletionPolicy::basis($note);
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor) use($version,$note) {
            if (!in_array((int)$actor['id'],[(int)$row['donor_id'],(int)$request['requester_id']],true)) throw new RuntimeException('Forbidden.',403);
            DonationCompletionPolicy::assertVersion($row,$version);DonationCompletionPolicy::assertUnresolved($row);
            if ($row['workflow_state']!=='clarification') throw new RuntimeException('No clarification is currently requested.',409);
            $now=AuthService::nowUtc();$row['workflow_state']='needs_review';
            Database::pdo()->prepare("UPDATE donation_reports SET workflow_state='needs_review' WHERE id=?")->execute([$row['id']]);
            $event=DonationWorkflowRepository::event($row,(int)$actor['id'],'clarification_replied',$note,$now);
            self::notifyAdmins($row,$request,'review_needed','Donation clarification received',"event:$event");
            return ['changed'=>true,'workflow_state'=>'needs_review'];
        });
    }

    public function events(array $actor,int $id): array
    {
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor) {
            $participant=in_array((int)$actor['id'],[(int)$row['donor_id'],(int)$request['requester_id']],true);
            $staff=$actor['role']==='admin' && strtolower((string)$actor['email'])==='markseejr@gmail.com';
            if (!$participant && !$staff) throw new RuntimeException('Report not found.',404);
            $s=Database::pdo()->prepare('SELECT e.id,e.content_version,e.actor_id,e.actor_reference,u.full_name AS actor_name,e.event_type,e.basis,e.snapshot_json,e.created_at FROM donation_report_events e LEFT JOIN users u ON u.id=e.actor_id WHERE e.report_id=? ORDER BY e.id ASC');$s->execute([$row['id']]);return $s->fetchAll();
        });
    }

    public function revise(array $actor,int $id,int $version,mixed $occurredAt,string $note): array
    {
        $note=DonationCompletionPolicy::basis($note);
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor) use($version,$occurredAt,$note) {
            if ((int)$actor['id']!==(int)$row['donor_id'] || $actor['role']!=='member') throw new RuntimeException('Only the donor can correct donation details.',403);
            DonationCompletionPolicy::assertVersion($row,$version);DonationCompletionPolicy::assertUnresolved($row);
            if ($row['workflow_state']!=='clarification') throw new RuntimeException('Ask for administrative clarification before changing reported details.',409);
            $now=AuthService::nowUtc();
            $occurred=DonationCompletionPolicy::occurrence($occurredAt,self::responseAt($match,$row['reported_at']),$now);
            DonationWorkflowRepository::event($row,(int)$actor['id'],'version_superseded','Donor corrected report details.',$now);
            Database::pdo()->prepare("UPDATE donation_reports SET occurred_at=?,report_note=?,content_version=content_version+1,requester_response=NULL,
                acknowledged_by=NULL,acknowledged_at=NULL,acknowledged_version=NULL,rejection_reason=NULL,workflow_state='awaiting_requester' WHERE id=?")
                ->execute([$occurred,$note,$row['id']]);
            $row=array_replace($row,['occurred_at'=>$occurred,'report_note'=>$note,'content_version'=>(int)$row['content_version']+1,'requester_response'=>null,'acknowledged_at'=>null,'acknowledged_version'=>null,'workflow_state'=>'awaiting_requester']);
            DonationWorkflowRepository::event($row,(int)$actor['id'],'revised',null,$now);
            self::notify($row,(int)$request['requester_id'],'awaiting_acknowledgment','Donation report corrected','Review the updated donation date and details. Your earlier response does not apply to this version.','version:'.$row['content_version']);
            return ['changed'=>true,'content_version'=>$row['content_version']];
        });
    }

    public function releaseHold(array $actor,int $id,int $version,mixed $basis,bool $noDonationOccurred): array
    {
        if (!DonationCompletionPolicy::HOLD_RELEASE_APPROVED) throw new RuntimeException('Hold release is not approved.',503);
        $basis=DonationCompletionPolicy::basis($basis);
        if (!$noDonationOccurred) throw new RuntimeException('Explicitly conclude that no donation occurred before releasing a rejected report hold.',400);
        return self::locked($id,(int)$actor['id'],function($row,$request,$match,$actor) use($version,$basis) {
            WorkflowLockService::assertReportReviewer($actor,$request,(int)$row['donor_id']);
            if (strtolower((string)$actor['email'])!=='markseejr@gmail.com') throw new RuntimeException('Forbidden.',403);
            DonationCompletionPolicy::assertVersion($row,$version);
            if ($row['status']!=='REJECTED') throw new RuntimeException('Reject the report before resolving its no-donation hold. Correct genuine donations instead.',409);
            $s=Database::pdo()->prepare('SELECT * FROM donation_report_holds WHERE report_id=? FOR UPDATE');$s->execute([$row['id']]);$hold=$s->fetch();
            if (!$hold || $hold['released_at']!==null) return ['changed'=>false];
            $now=AuthService::nowUtc();
            Database::pdo()->prepare("UPDATE donation_report_holds SET released_at=?,released_by=?,release_type='no_donation',release_basis=? WHERE report_id=? AND released_at IS NULL")
                ->execute([$now,$actor['id'],$basis,$row['id']]);
            $releaseEvent=DonationWorkflowRepository::event($row,(int)$actor['id'],'hold_released_no_donation',$basis,$now);
            foreach(array_unique([(int)$row['donor_id'],(int)$request['requester_id']]) as $recipient) self::notify($row,$recipient,'hold_released','Report-specific hold released','An administrator documented that no donation occurred for this report. Other holds, restrictions and eligibility checks still apply.',"hold-release:$releaseEvent");
            return ['changed'=>true];
        });
    }
}
