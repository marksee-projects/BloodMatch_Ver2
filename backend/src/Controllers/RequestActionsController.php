<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Config\Database;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\{WorkflowLockService,RequestService,RequestLifecycleService,AuthService,AuditLogger,MatchService,NotificationService};
use BloodMatch\Utils\{Request,Response};
use RuntimeException;
final class RequestActionsController
{
    public function renew(array $params): void {
        $a=AuthMiddleware::requireActiveUser('requests.renew');$id=(int)$params['id'];
        try { $r=WorkflowLockService::transaction(function() use($a,$id){
            $old=WorkflowLockService::request($id);$users=WorkflowLockService::users([(int)$a['id']]);$actor=$users[(int)$a['id']];RequestLifecycleService::assertCanEdit($actor,$old);RequestService::assertCanCreate($actor);
            $fields=RequestService::validatePayload(['needed_datetime'=>Request::str('needed_datetime')],partial:true);
            if (RequestService::hasDeadlinePassed(array_replace($old,$fields))) throw new RuntimeException('Choose a future deadline.',400);
            if ($old['status']==='OPEN' && !RequestService::hasDeadlinePassed($old)) return (new RequestLifecycleService())->update($actor,$id,$fields);
            if ($old['status']==='OPEN') (new RequestLifecycleService())->expireOne($id,AuthService::nowUtc());
            elseif ($old['status']!=='EXPIRED') throw new RuntimeException('Only expired requests can be renewed.',409);
            $p=Database::pdo();$s=$p->prepare('SELECT id FROM blood_requests WHERE renewed_from_id=? LIMIT 1');$s->execute([$id]);if ($s->fetchColumn()) throw new RuntimeException('This request has already been renewed. Open its new post.',409);
            $new=array_replace($old,$fields,['review_status'=>RequestService::reviewStatusFor($actor)]);
            $newId=(new BloodRequestRepository())->create($new);$s=$p->prepare('UPDATE blood_requests SET renewed_from_id=? WHERE id=?');$s->execute([$id,$newId]);
            (new MatchService())->generateForRequest($newId);
            AuditLogger::log((int)$a['id'],'request.renewed','blood_request',(string)$newId,['previous_request_id'=>$id]);return ['id'=>$newId,'renewed_from_id'=>$id];
        });Response::success(['request'=>$r]); } catch(\Throwable $e) { $c=(int)$e->getCode();Response::error($e instanceof RuntimeException?$e->getMessage():'Request renewal is temporarily unavailable.',$c>=400&&$c<=499?$c:503); }
    }
    public function close(array $params): void {
        $a=AuthMiddleware::requireActiveUser('requests.close');$id=(int)$params['id'];$reason=Request::str('reason')??'';
        if (!in_array($reason,['need_met_elsewhere','no_longer_needed'],true)) {Response::error('Choose a closure reason.',400);return;}
        try {WorkflowLockService::transaction(function() use($a,$id,$reason){
            $r=WorkflowLockService::request($id);RequestLifecycleService::assertCanEdit($a,$r);
            (new RequestLifecycleService())->cancel($a,$id);$s=Database::pdo()->prepare('UPDATE blood_requests SET closure_reason=? WHERE id=?');$s->execute([$reason,$id]);
            AuditLogger::log((int)$a['id'],'request.closed','blood_request',(string)$id,['reason'=>$reason,'verified_donation'=>false]);
        });Response::success(['closed'=>true]);}catch(\Throwable $e){Response::error($e instanceof RuntimeException?$e->getMessage():'Request closure is temporarily unavailable.',409);}
    }
    public function followup(array $params): void {
        $a=AuthMiddleware::requireActiveUser('requests.followup');$id=(int)$params['id'];
        try { $r=(new BloodRequestRepository())->findById($id);if (!$r) throw new RuntimeException('Request not found.',404);RequestLifecycleService::assertCanEdit($a,$r);
            $staff=Database::pdo()->query("SELECT id FROM users WHERE email='markseejr@gmail.com' AND role='admin' AND account_status='active'")->fetchColumn();
            if (!$staff) throw new RuntimeException('Administrator unavailable.',503);
            NotificationService::notify((int)$staff,'donation.followup','Donation follow-up requested','Review the request and verify evidence before recording a donation.',['related_type'=>'blood_request','related_id'=>$id,'dedup_key'=>'donation-followup:'.$id]);
            Response::success(['sent'=>true]);
        }catch(\Throwable $e){Response::error('Could not request donation follow-up.',403);}
    }
}
