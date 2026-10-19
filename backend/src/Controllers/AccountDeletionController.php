<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Config\Database;
use BloodMatch\Http\Session;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\{UserRepository,AuthThrottleRepository};
use BloodMatch\Services\{AuthService,AuditLogger,WorkflowLockService,RequestLifecycleService,MatchResponseService};
use BloodMatch\Utils\{Request,Response};
use RuntimeException;
final class AccountDeletionController
{
    public function delete(): void {
        $a=AuthMiddleware::requireActiveUser('account.delete');
        if ($a['role']==='admin') { Response::error('Transfer administrator responsibility before deleting this account.',403);return; }
        if (!(new AuthThrottleRepository())->hitAndCheckRateLimitSeconds('account_delete:'.$a['id'],5,900,AuthService::nowUtc())) {Response::error('Too many attempts. Try again later.',429);return;}
        $input=Request::json();
        if (($input['confirmation']??'')!=='DELETE' || !is_string($input['password']??null) || !password_verify($input['password'],(string)$a['password_hash'])) {Response::error('Enter your current password and type DELETE.',400);return;}
        try {
            WorkflowLockService::transaction(function() use($a,$input){
                $p=Database::pdo();
                // Migration required before any closure changes.
                $p->query('SELECT user_id FROM account_closures LIMIT 0');
                // Lock requests before users, consistent with donation workflows.
                $s=$p->prepare("SELECT DISTINCT br.id,br.requester_id FROM blood_requests br LEFT JOIN matches m ON m.request_id=br.id WHERE br.status='OPEN' AND (br.requester_id=? OR (m.donor_id=? AND m.status='RESPONDED')) ORDER BY br.id FOR UPDATE");$s->execute([$a['id'],$a['id']]);$requests=$s->fetchAll();
                $locked=WorkflowLockService::users([(int)$a['id']])[(int)$a['id']];
                if ($locked['account_status']!=='active' || $locked['role']==='admin' || !password_verify($input['password'],(string)$locked['password_hash'])) throw new RuntimeException('Account state changed. Try again.',409);
                foreach($requests as $r) if ((int)$r['requester_id']===(int)$a['id']) (new RequestLifecycleService())->cancel($locked,(int)$r['id']);
                $s=$p->prepare("UPDATE matches m LEFT JOIN donation_reports dr ON dr.match_id=m.id AND dr.status='PENDING' SET m.status='CLOSED' WHERE m.donor_id=? AND m.status<>'COMPLETED' AND dr.id IS NULL");$s->execute([$a['id']]);
                $s=$p->prepare('INSERT INTO account_closures(user_id,closed_at,erase_after) VALUES(?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))');$s->execute([$a['id']]);
                $s=$p->prepare("UPDATE users SET account_status='deactivated',deactivated_at=UTC_TIMESTAMP(),password_hash=?,email_code_hash=NULL,email_code_expires_at=NULL WHERE id=?");$s->execute([password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT),$a['id']]);
                $s=$p->prepare("UPDATE chat_requests SET status='cancelled',updated_at=UTC_TIMESTAMP() WHERE status='pending' AND (sender_id=? OR recipient_id=?)");$s->execute([$a['id'],$a['id']]);
                $s=$p->prepare("UPDATE email_outbox e JOIN notifications n ON n.id=e.notification_id SET e.status='cancelled',e.cancel_reason='account_closed' WHERE n.user_id=? AND e.status IN ('pending','leased')");$s->execute([$a['id']]);
                AuditLogger::log((int)$a['id'],'account.closed','user',(string)$a['id']);
            });
        } catch(\Throwable $e) {Response::error($e instanceof RuntimeException?$e->getMessage():'Account closure is temporarily unavailable.', $e instanceof RuntimeException && $e->getCode()===409?409:503);return;}
        Session::destroy();Response::success(['closed'=>true,'message'=>'Account permanently closed.']);
    }
}
