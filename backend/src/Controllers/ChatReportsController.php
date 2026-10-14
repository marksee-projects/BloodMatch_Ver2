<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Config\Database;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\{ChatRepository,AuthThrottleRepository};
use BloodMatch\Services\{ChatPolicy,AuditLogger,AuthService,WorkflowLockService};
use BloodMatch\Utils\{Request,Response};
use RuntimeException;
use Throwable;
final class ChatReportsController
{
    private function staff(): array {
        $a=AuthMiddleware::requireRoles(['admin'],'chat.reports');
        if (strtolower($a['email'])!=='markseejr@gmail.com') { Response::error('Forbidden.',403);exit; }
        return $a;
    }
    private function run(callable $fn): void {
        try { Response::success($fn()); } catch(Throwable $e) { $code=(int)$e->getCode();Response::error($e instanceof \PDOException?'Chat reporting is temporarily unavailable.':$e->getMessage(),$code>=400&&$code<=499?$code:503); }
    }
    public function create(array $params): void {
        $a=AuthMiddleware::requireActiveUser('chat.report');$id=(int)$params['id'];
        $this->run(function() use($a,$id){
            if (!(new ChatPolicy())->canAccessConversation($a,$id)) throw new RuntimeException('Forbidden.',403);
            $body=Request::json();if (!is_string($body['reason']??null)) throw new RuntimeException('Enter a report reason.',400);$reason=trim($body['reason']);$ids=$body['message_ids']??[];
            if ($reason==='' || mb_strlen($reason)>500 || !is_array($ids) || count($ids)<1 || count($ids)>10) throw new RuntimeException('Select 1–10 messages and enter a reason up to 500 characters.',400);
            foreach($ids as $n) if (!is_int($n) || $n<1) throw new RuntimeException('Invalid message selection.',400);
            $ids=array_values(array_unique($ids));
            if (!(new AuthThrottleRepository())->hitAndCheckRateLimitSeconds('chat_report:'.$a['id'],5,3600,AuthService::nowUtc())) throw new RuntimeException('Please wait before submitting another report.',429);
            return WorkflowLockService::transaction(function() use($a,$id,$reason,$ids){
                $locked=WorkflowLockService::users([(int)$a['id']])[(int)$a['id']];if (!ChatPolicy::isEligible($locked)) throw new RuntimeException('Verification required.',403);
                $p=Database::pdo();$marks=implode(',',array_fill(0,count($ids),'?'));
                $s=$p->prepare("SELECT * FROM messages WHERE conversation_id=? AND id IN ($marks) ORDER BY id");$s->execute([$id,...$ids]);$chosen=$s->fetchAll();
                if (count($chosen)!==count($ids)) throw new RuntimeException('Message selection does not belong to this conversation.',403);
                // A bounded snapshot: selected messages and up to two adjacent messages each side.
                $evidence=[];
                foreach($chosen as $m) {
                    $evidence[$m['id']]=$m;
                    foreach(['< DESC','> ASC'] as $direction) {
                        [$op,$order]=explode(' ',$direction);
                        $s=$p->prepare("SELECT * FROM messages WHERE conversation_id=? AND id $op ? ORDER BY id $order LIMIT 2");$s->execute([$id,$m['id']]);
                        foreach($s->fetchAll() as $context) $evidence[$context['id']]=$context;
                    }
                }
                $s=$p->prepare('INSERT INTO chat_reports(reporter_id,conversation_id,reason) VALUES(?,?,?)');$s->execute([$a['id'],$id,$reason]);$reportId=(int)$p->lastInsertId();
                $s=$p->prepare('INSERT INTO chat_report_evidence(report_id,message_id,sender_id,body,message_created_at,is_selected) VALUES(?,?,?,?,?,?)');
                ksort($evidence);foreach($evidence as $m) $s->execute([$reportId,$m['id'],$m['sender_id'],$m['body'],$m['created_at'],in_array((int)$m['id'],$ids,true)?1:0]);
                AuditLogger::log((int)$a['id'],'chat.report.created','chat_report',(string)$reportId);
                $staff=$p->query("SELECT id FROM users WHERE email='markseejr@gmail.com' AND role='admin' AND account_status='active'")->fetchColumn();
                if ($staff) \BloodMatch\Services\NotificationService::notify((int)$staff,'chat.reported','Chat report received','Review the reported conversation evidence.',['related_type'=>'chat_report','related_id'=>$reportId,'dedup_key'=>'chat-report:'.$reportId]);
                return ['report_id'=>$reportId];
            });
        });
    }
    public function index(): void {
        $this->staff();$this->run(function(){
            $filter=Request::str('status')??'active';$page=max(1,Request::int('page')??1);
            if (!in_array($filter,['active','all','open','under_review','resolved','dismissed'],true)) throw new RuntimeException('Invalid report filter.',400);
            $where=$filter==='active'?"r.status IN ('open','under_review')":($filter==='all'?'1=1':'r.status=?');$params=in_array($filter,['active','all'],true)?[]:[$filter];
            $p=Database::pdo();$s=$p->prepare("SELECT COUNT(*) FROM chat_reports r WHERE $where");$s->execute($params);$total=(int)$s->fetchColumn();$offset=($page-1)*25;
            $s=$p->prepare("SELECT r.id,r.reason,r.status,r.created_at,u.full_name AS reporter_name FROM chat_reports r LEFT JOIN users u ON u.id=r.reporter_id WHERE $where ORDER BY r.created_at ASC LIMIT 25 OFFSET $offset");$s->execute($params);
            return ['reports'=>$s->fetchAll(),'total'=>$total,'page'=>$page];
        });
    }
    public function detail(array $params): void {
        $a=$this->staff();$id=(int)$params['id'];
        $this->run(function() use($a,$id){
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM chat_reports WHERE id=?');$s->execute([$id]);$r=$s->fetch();if (!$r) throw new RuntimeException('Report not found.',404);
            if ((int)$r['reporter_id']===(int)$a['id'] || ($r['conversation_id'] && (new ChatRepository())->isParticipant((int)$r['conversation_id'],(int)$a['id']))) throw new RuntimeException('Independent review is required for a report involving your account.',403);
            if (AuditLogger::log((int)$a['id'],'chat.report.evidence_accessed','chat_report',(string)$id,['purpose'=>'reported-conversation investigation'])===null) throw new RuntimeException('Evidence access could not be recorded. Try again later.',503);
            $s=$p->prepare('SELECT message_id,sender_id,body,message_created_at,is_selected FROM chat_report_evidence WHERE report_id=? ORDER BY message_id');$s->execute([$id]);return ['report'=>$r,'evidence'=>$s->fetchAll()];
        });
    }
    public function decide(array $params): void {
        $a=$this->staff();$id=(int)$params['id'];
        $this->run(function() use($a,$id){return WorkflowLockService::transaction(function() use($a,$id){
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM chat_reports WHERE id=? FOR UPDATE');$s->execute([$id]);$r=$s->fetch();if (!$r) throw new RuntimeException('Report not found.',404);
            if ((int)$r['reporter_id']===(int)$a['id'] || ($r['conversation_id'] && (new ChatRepository())->isParticipant((int)$r['conversation_id'],(int)$a['id']))) throw new RuntimeException('Independent review required.',403);
            $status=Request::str('status')??'';$note=trim(Request::str('findings')??'');
            if (!in_array($status,['under_review','resolved','dismissed'],true) || $note==='' || mb_strlen($note)>2000) throw new RuntimeException('Choose a status and enter findings up to 2000 characters.',400);
            if (in_array($r['status'],['resolved','dismissed'],true)) throw new RuntimeException('This report has already been closed.',409);
            $s=$p->prepare('UPDATE chat_reports SET status=?,findings=?,resolved_at=? WHERE id=?');$s->execute([$status,$note,in_array($status,['resolved','dismissed'],true)?AuthService::nowUtc():null,$id]);
            if (AuditLogger::log((int)$a['id'],'chat.report.'.$status,'chat_report',(string)$id)===null) throw new RuntimeException('Review action could not be recorded.',503);return ['status'=>$status];
        });});
    }
}
