<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\ChatRepository;
use RuntimeException;

final class ChatConsentService
{
    public static function accepted(int $conversationId, int $userId): bool
    {
        $s=Database::pdo()->prepare("SELECT 1 FROM chat_requests WHERE conversation_id=? AND status='accepted' AND (user_low=? OR user_high=?)");
        $s->execute([$conversationId,$userId,$userId]);
        return $s->fetchColumn()!==false;
    }

    public static function request(array $actor, int $recipientId): array
    {
        if ($recipientId<1 || $recipientId===(int)$actor['id']) throw new RuntimeException('Choose another verified member.',400);
        return WorkflowLockService::transaction(function() use($actor,$recipientId): array {
            $users=WorkflowLockService::users([(int)$actor['id'],$recipientId]);
            if (!(new ChatPolicy())->canMessage($users[(int)$actor['id']],$users[$recipientId])) throw new RuntimeException('Both accounts must be active and verified.',403);
            $p=Database::pdo(); $pair=[min((int)$actor['id'],$recipientId),max((int)$actor['id'],$recipientId)];
            $s=$p->prepare('SELECT * FROM chat_requests WHERE user_low=? AND user_high=? FOR UPDATE'); $s->execute($pair);$row=$s->fetch();
            if ($row && in_array($row['status'],['pending','accepted'],true)) return $row;
            if ($row && strtotime($row['updated_at'].' UTC')>time()-86400) throw new RuntimeException('Wait one day before sending another request.',429);
            if ($row) {
                $s=$p->prepare("UPDATE chat_requests SET sender_id=?,recipient_id=?,status='pending',conversation_id=NULL,created_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?");$s->execute([(int)$actor['id'],$recipientId,$row['id']]);$id=(int)$row['id'];
            } else {
                $s=$p->prepare('INSERT INTO chat_requests(user_low,user_high,sender_id,recipient_id) VALUES(?,?,?,?)');$s->execute([...$pair,(int)$actor['id'],$recipientId]);$id=(int)$p->lastInsertId();
            }
            NotificationService::notify($recipientId,'chat.request','Message request','A verified member requested to chat.', ['related_type'=>'chat_request','related_id'=>$id,'dedup_key'=>'chat-request:'.$id.':'.AuthService::nowUtc()]);
            AuditLogger::log((int)$actor['id'],'chat.request.sent','chat_request',(string)$id,['recipient_id'=>$recipientId]);
            return ['id'=>$id,'status'=>'pending','recipient_id'=>$recipientId];
        });
    }

    public static function listing(int $userId): array
    {
        $s=Database::pdo()->prepare("SELECT r.id,r.status,r.sender_id,r.recipient_id,r.created_at,r.conversation_id,u.full_name AS sender_name,v.full_name AS recipient_name FROM chat_requests r JOIN users u ON u.id=r.sender_id JOIN users v ON v.id=r.recipient_id WHERE (r.sender_id=? OR r.recipient_id=?) AND r.status='pending' ORDER BY r.created_at DESC LIMIT 100");
        $s->execute([$userId,$userId]);return $s->fetchAll();
    }

    public static function decide(array $actor, int $id, string $action): array
    {
        if (!in_array($action,['accept','decline','cancel'],true)) throw new RuntimeException('Invalid action.',400);
        return WorkflowLockService::transaction(function() use($actor,$id,$action): array {
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM chat_requests WHERE id=?');$s->execute([$id]);$pre=$s->fetch();
            if (!$pre) throw new RuntimeException('Request not found.',404);
            $users=WorkflowLockService::users([(int)$pre['sender_id'],(int)$pre['recipient_id']]);
            $s=$p->prepare('SELECT * FROM chat_requests WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            $expected=$action==='cancel'?(int)$row['sender_id']:(int)$row['recipient_id'];
            if ((int)$actor['id']!==$expected) throw new RuntimeException('Forbidden.',403);
            if (!ChatPolicy::isEligible($users[$expected])) throw new RuntimeException('Verification required.',403);
            if ($row['status']!=='pending') throw new RuntimeException('This request has already been handled.',409);
            $conv=null;
            if ($action==='accept') {
                if (!(new ChatPolicy())->canMessage($users[(int)$row['sender_id']],$users[(int)$row['recipient_id']])) throw new RuntimeException('Both accounts must be active and verified.',403);
                $repo=new ChatRepository();$conv=$repo->findDirectConversationId((int)$row['sender_id'],(int)$row['recipient_id']) ?? $repo->createDirectConversation((int)$row['sender_id'],(int)$row['recipient_id']);
            }
            $status=['accept'=>'accepted','decline'=>'declined','cancel'=>'cancelled'][$action];
            $s=$p->prepare('UPDATE chat_requests SET status=?,conversation_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$status,$conv,$id]);
            AuditLogger::log((int)$actor['id'],'chat.request.'.$status,'chat_request',(string)$id);
            $otherId=$action==='cancel'?(int)$row['recipient_id']:(int)$row['sender_id'];
            NotificationService::notify($otherId,'chat.request.'.$status,'Message request '.$status,'Open chat to review this request.',['related_type'=>'chat_request','related_id'=>$id,'dedup_key'=>'chat-request:'.$id.':'.$status.':'.AuthService::nowUtc()]);
            return ['status'=>$status,'conversation_id'=>$conv];
        });
    }
}
