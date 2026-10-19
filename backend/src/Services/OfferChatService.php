<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use BloodMatch\Repositories\{MatchRepository,ChatRepository,DonationReportRepository,DonationWorkflowRepository};
use RuntimeException;

final class OfferChatService
{
    public static function ready(): bool {
        $s=Database::pdo()->prepare('SELECT 1 FROM schema_migrations WHERE name=?');
        $s->execute(['031_offer_chat_donation_proof.sql']);return $s->fetchColumn()!==false;
    }
    public static function requireReady(): void { if (!self::ready()) throw new RuntimeException('Offer chat requires migration 031.',503); }
    public static function accept(array $actor,int $matchId,int $cycle): array {
        self::requireReady();$pre=(new MatchRepository())->findByIdDetailed($matchId);
        if (!$pre) throw new RuntimeException('Offer not found.',404);
        return WorkflowLockService::transaction(function() use($actor,$pre,$matchId,$cycle) {
            $r=WorkflowLockService::request((int)$pre['request_id']);
            $users=WorkflowLockService::users([(int)$actor['id'],(int)$r['requester_id'],(int)$pre['donor_id']]);
            $a=$users[(int)$actor['id']];$donor=$users[(int)$pre['donor_id']];
            if ((int)$a['id']!==(int)$r['requester_id'] || (int)$a['id']===(int)$donor['id']) throw new RuntimeException('Only the request owner can accept this donor.',403);
            if (!(new ChatPolicy())->canMessage($a,$donor)) throw new RuntimeException('Both accounts must be active and verified.',403);
            $m=(new MatchRepository())->findByIdForUpdate($matchId);
            if (!$m || (int)$m['request_id']!==(int)$r['id'] || (int)$m['donor_id']!==(int)$donor['id'] || (int)$m['offer_cycle']!==$cycle) throw new RuntimeException('This offer changed. Reload before accepting.',409);
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM donation_chat_contexts WHERE match_id=? AND offer_cycle=? FOR UPDATE');$s->execute([$matchId,$cycle]);$old=$s->fetch();
            // An already accepted consent remains usable despite later donor cooldown/holds.
            if ($old && $old['conversation_id']) return ['context_id'=>(int)$old['id'],'conversation_id'=>(int)$old['conversation_id'],'changed'=>false];
            if ($old) throw new RuntimeException('This previous chat is no longer available. Its report history remains accessible.',410);
            WorkflowLockService::assertOpenBeforeDeadline($r);
            if ($m['status']!=='RESPONDED') throw new RuntimeException('This offer is no longer active.',409);
            $donor['pending_report_hold']=\BloodMatch\Repositories\DonationWorkflowRepository::hasHold((int)$donor['id']);
            $donor['non_response_standby']=NonResponseStandbyService::state((int)$donor['id'])['standby_started_at'];
            $eligibility=DonorEligibilityService::evaluateForRequest($donor,$r);
            if (!$eligibility['eligible']) throw new RuntimeException($eligibility['reason'],409);
            $repo=new ChatRepository();$conversation=$repo->findDirectConversationId((int)$a['id'],(int)$donor['id'])??$repo->createDirectConversation((int)$a['id'],(int)$donor['id']);
            $p->prepare('UPDATE conversations SET updated_at=UTC_TIMESTAMP(3) WHERE id=?')->execute([$conversation]);
            // Use the existing consent gate for every list/read/send/unread endpoint.
            // Acceptance explicitly consents to this pair; no extra message request is sent.
            $pair=[min((int)$a['id'],(int)$donor['id']),max((int)$a['id'],(int)$donor['id'])];
            $s=$p->prepare('SELECT id FROM chat_requests WHERE user_low=? AND user_high=? FOR UPDATE');$s->execute($pair);$consent=$s->fetchColumn();
            if ($consent) $p->prepare("UPDATE chat_requests SET status='accepted',conversation_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$conversation,$consent]);
            else $p->prepare("INSERT INTO chat_requests(user_low,user_high,sender_id,recipient_id,status,conversation_id) VALUES(?,?,?,?,'accepted',?)")->execute([...$pair,$donor['id'],$a['id'],$conversation]);
            WorkflowLockService::assertOpenBeforeDeadline($r);
            $p->prepare('INSERT INTO donation_chat_contexts(match_id,offer_cycle,conversation_id,accepted_by,accepted_at,responded_at) VALUES(?,?,?,?,?,?)')->execute([$matchId,$cycle,$conversation,$a['id'],AuthService::nowUtc(),DonationCompletionService::responseAt($m)]);$id=(int)$p->lastInsertId();
            if (AuditLogger::log((int)$a['id'],'offer.accepted','blood_request',(string)$r['id'],['match_id'=>$matchId,'offer_cycle'=>$cycle,'context_id'=>$id])===null) throw new RuntimeException('Acceptance audit unavailable.',503);
            NotificationService::notify((int)$donor['id'],'offer.accepted','Your offer was accepted','Open the request-linked chat to coordinate. Acceptance does not confirm a donation.',['related_type'=>'blood_request','related_id'=>(int)$r['id'],'dedup_key'=>'offer-accepted:'.$id,'email'=>NotificationService::EMAIL_NONE]);
            return ['context_id'=>$id,'conversation_id'=>$conversation,'changed'=>true];
        });
    }
    public static function offers(array $actor,int $requestId): array {
        self::requireReady();$r=(new \BloodMatch\Repositories\BloodRequestRepository())->findById($requestId);
        if (!$r || (int)$r['requester_id']!==(int)$actor['id']) throw new RuntimeException('Request not found.',404);
        if (!ChatPolicy::isEligible($actor)) throw new RuntimeException('Active verified membership is required to review chat acceptance.',403);
        $s=Database::pdo()->prepare("SELECT m.id AS match_id,m.offer_cycle,m.status,u.full_name AS donor_name,c.conversation_id,c.id AS context_id FROM matches m JOIN users u ON u.id=m.donor_id LEFT JOIN donation_chat_contexts c ON c.match_id=m.id AND c.offer_cycle=m.offer_cycle WHERE m.request_id=? AND (m.status IN ('RESPONDED','COMPLETED') OR c.id IS NOT NULL) ORDER BY m.id");$s->execute([$requestId]);
        return ['offers'=>$s->fetchAll(),'can_accept'=>$r['status']==='OPEN'&&!RequestService::hasDeadlinePassed($r)];
    }
    public static function ownOffer(array $actor,int $matchId): array {
        self::requireReady();$m=(new MatchRepository())->findByIdDetailed($matchId);
        if (!$m || (int)$m['donor_id']!==(int)$actor['id']) throw new RuntimeException('Offer not found.',404);
        $s=Database::pdo()->prepare('SELECT id,conversation_id FROM donation_chat_contexts WHERE match_id=? AND offer_cycle=?');$s->execute([$matchId,$m['offer_cycle']]);$c=$s->fetch();
        $report=Database::pdo()->prepare('SELECT id FROM donation_reports WHERE match_id=? ORDER BY id DESC LIMIT 1');$report->execute([$matchId]);
        $conversation=$c&&$c['conversation_id']&&(new ChatPolicy())->canAccessConversation($actor,(int)$c['conversation_id'])?(int)$c['conversation_id']:null;
        return ['conversation_id'=>$conversation,'request_id'=>(int)$m['request_id'],'report_id'=>$report->fetchColumn()?:null,'status'=>$c?'accepted':($m['request_status']!=='OPEN'||RequestService::hasDeadlinePassed($m)?'inactive':'awaiting_acceptance')];
    }
    public static function contexts(array $actor,int $conversation): array {
        if (!self::ready()) return [];
        if (!(new ChatPolicy())->canAccessConversation($actor,$conversation)) throw new RuntimeException('Conversation not found.',404);
        $s=Database::pdo()->prepare("SELECT c.id,c.match_id,c.offer_cycle,c.accepted_at,m.offer_cycle AS current_cycle,m.status AS offer_status,m.donor_id,r.id AS request_id,r.requester_id,r.status AS request_status,r.needed_datetime,r.facility_name,r.required_blood_type,r.quantity_units,
            (SELECT id FROM donation_reports d WHERE d.match_id=m.id ORDER BY id DESC LIMIT 1) AS report_id
            FROM donation_chat_contexts c JOIN matches m ON m.id=c.match_id JOIN blood_requests r ON r.id=m.request_id WHERE c.conversation_id=? AND (m.donor_id=? OR r.requester_id=?) ORDER BY c.id DESC");$s->execute([$conversation,$actor['id'],$actor['id']]);$rows=$s->fetchAll();
        foreach($rows as &$row) {
            $row['active']=$row['request_status']==='OPEN'&&!RequestService::hasDeadlinePassed($row)&&$row['offer_status']==='RESPONDED'&&(int)$row['offer_cycle']===(int)$row['current_cycle'];
            $row['report']=$row['report_id']?(new DonationReportRepository())->findById((int)$row['report_id']):null;
            $row['report_in_context']=!$row['report'] || empty($row['report']['offer_context_id']) || (int)$row['report']['offer_context_id']===(int)$row['id'];
            $q=Database::pdo()->prepare('SELECT actor_reference,action,created_at FROM donation_coordination_events WHERE context_id=? ORDER BY id DESC LIMIT 10');$q->execute([$row['id']]);$row['coordination']=$q->fetchAll();
        }unset($row);return $rows;
    }
    public static function signal(array $actor,int $contextId,string $action,int $version): array {
        self::requireReady();if (!in_array($action,['still_arranging','did_not_happen','prompt_donor'],true)) throw new RuntimeException('Invalid coordination action.',400);
        $p=Database::pdo();$s=$p->prepare('SELECT c.*,m.request_id,m.donor_id FROM donation_chat_contexts c JOIN matches m ON m.id=c.match_id WHERE c.id=?');$s->execute([$contextId]);$pre=$s->fetch();if (!$pre) throw new RuntimeException('Context not found.',404);
        return WorkflowLockService::transaction(function() use($actor,$pre,$contextId,$action,$version,$p) {
            $r=WorkflowLockService::request((int)$pre['request_id']);$users=WorkflowLockService::users([(int)$actor['id'],(int)$r['requester_id'],(int)$pre['donor_id']]);$a=$users[(int)$actor['id']];
            if (!in_array((int)$a['id'],[(int)$r['requester_id'],(int)$pre['donor_id']],true) || !(new ChatPolicy())->canAccessConversation($a,(int)$pre['conversation_id'])) throw new RuntimeException('Context not found.',404);
            $m=(new MatchRepository())->findByIdForUpdate((int)$pre['match_id']);
            if (!$m || (int)$m['request_id']!==(int)$r['id'] || (int)$m['donor_id']!==(int)$pre['donor_id']) throw new RuntimeException('Context changed. Reload before acting.',409);
            $s=$p->prepare('SELECT * FROM donation_reports WHERE match_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$s->execute([$m['id']]);$report=$s->fetch();
            if ($report) {
                DonationCompletionPolicy::assertVersion($report,$version);
                if ($action==='prompt_donor') return ['changed'=>false,'report_id'=>(int)$report['id']];
                if ($action==='did_not_happen') {
                    if (!empty($report['offer_context_id']) && (int)$report['offer_context_id']!==$contextId) throw new RuntimeException('Open the report’s original offer context to dispute it.',409);
                    if ($report['status']!=='PENDING') throw new RuntimeException('Use Request correction for a completed record.',409);
                    if ((int)$a['id']===(int)$r['requester_id']) return (new DonationCompletionService())->acknowledge($a,(int)$report['id'],$version,'did_not_donate',null);
                    $s=$p->prepare("SELECT 1 FROM donation_report_events WHERE report_id=? AND content_version=? AND event_type='donor_disputed' LIMIT 1");$s->execute([$report['id'],$version]);if ($s->fetchColumn()) return ['changed'=>false];
                    $p->prepare("UPDATE donation_reports SET workflow_state='clarification' WHERE id=?")->execute([$report['id']]);$report['workflow_state']='clarification';
                    DonationWorkflowRepository::event($report,(int)$a['id'],'donor_disputed','Donor states this donation did not happen. Review required; hold retained.',AuthService::nowUtc());
                    DonationCompletionService::notifyAdmins($report,$r,'review_needed','Donor requested clarification of a donation claim','donor-dispute:'.$version);
                }
            } else if ($version!==0) throw new RuntimeException('This context changed. Reload it.',409);
            if ($action==='prompt_donor' && (int)$a['id']!==(int)$r['requester_id']) throw new RuntimeException('Only the requester can prompt the donor.',403);
            if ($action==='still_arranging' && !($r['status']==='OPEN'&&!RequestService::hasDeadlinePassed($r)&&$m['status']==='RESPONDED'&&(int)$m['offer_cycle']===(int)$pre['offer_cycle'])) throw new RuntimeException('This coordination is inactive. Existing reports remain available.',409);
            $s=$p->prepare('SELECT action,report_version FROM donation_coordination_events WHERE context_id=? AND actor_reference=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$s->execute([$contextId,$a['id']]);$last=$s->fetch();
            if ($last && $last['action']===$action && (int)$last['report_version']===$version) return ['changed'=>false];
            if ($action==='prompt_donor') { $s=$p->prepare("SELECT 1 FROM donation_coordination_events WHERE context_id=? AND action='prompt_donor' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)");$s->execute([$contextId]);if($s->fetchColumn()) return ['changed'=>false]; }
            $p->prepare('INSERT INTO donation_coordination_events(context_id,actor_id,actor_reference,action,report_version,created_at) VALUES(?,?,?,?,?,?)')->execute([$contextId,$a['id'],$a['id'],$action,$version,AuthService::nowUtc()]);$event=(int)$p->lastInsertId();
            $p->prepare('UPDATE conversations SET updated_at=UTC_TIMESTAMP(3) WHERE id=?')->execute([$pre['conversation_id']]);
            if (AuditLogger::log((int)$a['id'],'offer.coordination','blood_request',(string)$r['id'],['context_id'=>$contextId,'action'=>$action,'event_id'=>$event])===null) throw new RuntimeException('Coordination audit unavailable.',503);
            $other=(int)$a['id']===(int)$r['requester_id']?(int)$m['donor_id']:(int)$r['requester_id'];
            NotificationService::notify($other,'offer.coordination',$action==='prompt_donor'?'Please submit donation details':'Donation coordination updated','Open your request-linked chat. This update does not confirm a donation.',['related_type'=>'blood_request','related_id'=>(int)$r['id'],'dedup_key'=>'coordination:'.$event,'email'=>NotificationService::EMAIL_NONE]);
            return ['changed'=>true];
        });
    }
}
