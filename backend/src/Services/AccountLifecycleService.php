<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Repositories\UserRepository;
use BloodMatch\Config\Database;

final class AccountLifecycleService
{
    /** Existing admin operations; no new role or chapter permissions. */
    public static function change(int $actorId, int $targetId, array $fields): array
    {
        return WorkflowLockService::transaction(function () use ($actorId,$targetId,$fields) {
            $users=WorkflowLockService::users([$actorId,$targetId]);
            $actor=$users[$actorId]; $target=$users[$targetId];
            WorkflowLockService::assertActiveActor($actor);
            if ($actor['role']!=='admin' || $actorId===$targetId) { throw new \RuntimeException('Forbidden.',403); }
            $repo=new UserRepository();
            if (strtolower((string)$actor['email'])!=='markseejr@gmail.com' || isset($fields['role']) || strtolower((string)$target['email'])==='markseejr@gmail.com') throw new \RuntimeException('Administrator role assignment is locked.',403);
            $closed=Database::pdo()->prepare('SELECT 1 FROM account_closures WHERE user_id=?');$closed->execute([$targetId]);
            if ($closed->fetchColumn()!==false) throw new \RuntimeException('This account is permanently closed.',409);
            $role=$fields['role']??$target['role'];
            $chapter=array_key_exists('chapter_id',$fields)?$fields['chapter_id']:$target['chapter_id'];
            if (!in_array($role, ['member', 'admin'], true)) { throw new \RuntimeException('Role must be member or admin.',422); }
            if ($chapter!==null && !$repo->chapterExists((int)$chapter)) { throw new \RuntimeException('Chapter does not exist.',400); }
            $changed=[];
            foreach ($fields as $field=>$value) {
                if (!in_array($field,['role','chapter_id','account_status'],true)) { throw new \LogicException('Unsupported account field.'); }
                if ((string)($target[$field]??'')!==(string)($value??'')) { $changed[$field]=$value; }
            }
            if ($changed===[]) { return $target; }
            if (isset($fields['role'])) { $repo->setRoleAndChapter($targetId,$role,$chapter===null?null:(int)$chapter); }
            elseif (array_key_exists('chapter_id',$fields)) { $repo->setChapter($targetId,$chapter===null?null:(int)$chapter); }
            if (isset($fields['account_status'])) {
                $repo->setStatus($targetId,$fields['account_status'],$fields['account_status']==='deactivated'?AuthService::nowUtc():null);
            }
            $action=isset($fields['account_status'])?($fields['account_status']==='deactivated'?'admin.user.deactivated':'admin.user.reactivated')
                :(isset($fields['role'])?'admin.user.role_changed':'admin.user.chapter_assigned');
            $context=['from_role'=>$target['role'],'to_role'=>$role,'chapter_id'=>$chapter,
                'from_chapter_id'=>$target['chapter_id'],'to_chapter_id'=>$chapter,'previous_status'=>$target['account_status']];
            foreach ($changed as $field=>$value) { $context[$field]=['from'=>$target[$field],'to'=>$value]; }
            AuditLogger::log($actorId,$action,'user',(string)$targetId,$context);
            $s=Database::pdo()->prepare("SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=? AND type='account.status_changed'");
            $s->execute([$targetId]); $previous=(int)$s->fetchColumn();
            NotificationService::notify($targetId,'account.status_changed','Account details updated',
                'Your BloodMatch account ' . implode(', ',array_map(fn($field)=>str_replace('_',' ',$field),array_keys($changed))) . ' changed. Open your profile to review your current details.',[
                    'related_type'=>'account','related_id'=>$targetId,'dedup_key'=>"account-change:{$targetId}:after:{$previous}",
                ]);
            return $repo->findById($targetId);
        });
    }
}
