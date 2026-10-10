<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\UserRepository;

final class NonResponseStandbyService
{
    public static function ready(): bool
    {
        static $ready=null;
        if ($ready===null) {
            $ready=(int)Database::pdo()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME IN ('donor_response_state','donor_opportunities')")->fetchColumn()===2;
            if ($ready) {
                $s=Database::pdo()->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');
                $s->execute(['022_non_response_opportunities.sql']);$ready=(int)$s->fetchColumn()===1;
            }
        }
        return $ready;
    }

    public static function consumed(string $dueAt,?string $through): bool
    {
        return $through!==null && $dueAt<=$through;
    }

    public static function state(int $donorId): array
    {
        if (!self::ready()) { return ['standby_started_at'=>null,'consumed_through'=>null]; }
        $s=Database::pdo()->prepare('SELECT standby_started_at,consumed_through FROM donor_response_state WHERE user_id=?');
        $s->execute([$donorId]);
        return $s->fetch(\PDO::FETCH_ASSOC)?:['standby_started_at'=>null,'consumed_through'=>null];
    }

    /** Reference-only history, same transaction as committed in-app opportunity. */
    public static function opportunity(int $requestId,int $donorId,int $generation,int $notificationId): void
    {
        if (!self::ready()) { return; } // Explicitly inactive until the user's 022 rollout.
        if (!Database::pdo()->inTransaction()) { throw new \LogicException('Opportunity requires its notification transaction.'); }
        $s=Database::pdo()->prepare('SELECT generation FROM donor_opportunities WHERE request_id=? AND donor_id=? ORDER BY generation DESC LIMIT 1 FOR UPDATE');
        $s->execute([$requestId,$donorId]);$latest=$s->fetchColumn();
        if ($latest!==false && (int)$latest>$generation) { return; }
        Database::pdo()->prepare("INSERT INTO donor_opportunities(request_id,donor_id,generation,notification_id,notified_at,due_at)
            VALUES (?,?,?,?,UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 42 HOUR))
            ON DUPLICATE KEY UPDATE id=id")->execute([$requestId,$donorId,$generation,$notificationId]);
        Database::pdo()->prepare("UPDATE donor_opportunities SET status='superseded' WHERE request_id=? AND donor_id=? AND generation<? AND status='pending'")
            ->execute([$requestId,$donorId,$generation]);
    }

    public static function resolve(int $requestId,?int $donorId,string $status): void
    {
        if (!self::ready()) { return; }
        if (!in_array($status,['answered','invalidated'],true)) { throw new \LogicException('Invalid opportunity resolution.'); }
        $s=Database::pdo()->prepare("UPDATE donor_opportunities SET status=? WHERE request_id=? AND status='pending'" . ($donorId===null?'':' AND donor_id=?'));
        $s->execute($donorId===null?[$status,$requestId]:[$status,$requestId,$donorId]);
    }

    /** Bounded selections, keyset drain; no task is installed or invoked. */
    public static function processDue(): int
    {
        if (!self::ready()) { throw new \RuntimeException('Non-response schema is not ready.',503); }
        $pdo=Database::pdo(); $cursor=0; $count=0;
        $cutoff=(string)$pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
        do {
            $s=$pdo->prepare("SELECT id,request_id,donor_id FROM donor_opportunities WHERE status='pending' AND due_at<=? AND id>? ORDER BY id LIMIT 100");
            $s->execute([$cutoff,$cursor]); $rows=$s->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cursor=(int)$row['id'];
                $count+=(int)WorkflowLockService::transaction(function () use ($row,$cutoff,$pdo) {
                    $request=WorkflowLockService::request((int)$row['request_id']);
                    $donor=WorkflowLockService::users([(int)$row['donor_id']])[(int)$row['donor_id']];
                    $s=$pdo->prepare('SELECT * FROM donor_opportunities WHERE id=? FOR UPDATE'); $s->execute([$row['id']]); $timer=$s->fetch(\PDO::FETCH_ASSOC);
                    $s=$pdo->prepare('SELECT status,generation FROM matches WHERE request_id=? AND donor_id=?'); $s->execute([$row['request_id'],$row['donor_id']]); $match=$s->fetch(\PDO::FETCH_ASSOC);
                    $state=self::state((int)$donor['id']);
                    if (!$timer || $timer['status']!=='pending' || $timer['due_at']>$cutoff
                        || self::consumed($timer['due_at'],$state['consumed_through'])) { return false; }
                    if ($request['status']!=='OPEN' || RequestService::hasDeadlinePassed($request) || !$match
                        || !in_array($match['status'],['POTENTIAL','NOTIFIED'],true) || (int)$match['generation']!==(int)$timer['generation']) { return false; }
                    if ($state['standby_started_at']!==null) { return false; }
                    if ($donor['role']!=='member' || $donor['account_status']!=='active' || empty($donor['donor_enrolled_at'])) { return false; }
                    $now=(string)$pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
                    $pdo->prepare('INSERT INTO donor_response_state(user_id,standby_started_at) VALUES (?,?) ON DUPLICATE KEY UPDATE standby_started_at=VALUES(standby_started_at)')->execute([$donor['id'],$now]);
                    NotificationService::notify((int)$donor['id'],'donor.non_response_standby','Please review your donor availability',
                        'An unanswered donation opportunity reached 42 hours. Acknowledge it in your profile before receiving new donor opportunities.',[
                            'related_type'=>'account','related_id'=>(int)$donor['id'],'dedup_key'=>'non-response:'.$timer['id'],
                        ]);
                    AuditLogger::log(null,'donor.non_response_standby','user',(string)$donor['id'],['opportunity_id'=>(int)$timer['id']]);
                    return true;
                });
            }
        } while ($rows!==[]);
        return $count;
    }

    public static function acknowledge(int $donorId): bool
    {
        if (!self::ready()) { throw new \RuntimeException('Non-response schema is not ready.',503); }
        return WorkflowLockService::transaction(function () use ($donorId) {
            $donor=WorkflowLockService::users([$donorId])[$donorId];
            WorkflowLockService::assertActiveActor($donor);
            if ($donor['role']!=='member') { throw new \RuntimeException('Only members acknowledge donor standby.',403); }
            $state=self::state($donorId);
            if ($state['standby_started_at']===null) { return false; }
            $pdo=Database::pdo(); $now=(string)$pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
            // Compare due_at, not notified_at: fresh timers remain pending.
            $pdo->prepare("UPDATE donor_opportunities SET status='acknowledged' WHERE donor_id=? AND status='pending' AND due_at<=?")->execute([$donorId,$now]);
            $pdo->prepare('UPDATE donor_response_state SET standby_started_at=NULL,consumed_through=? WHERE user_id=?')->execute([$now,$donorId]);
            NotificationService::notify($donorId,'donor.non_response_acknowledged','Donor standby acknowledged',
                'Your acknowledgment was recorded. Current availability and post-donation safety windows still apply.',[
                    'related_type'=>'account','related_id'=>$donorId,'dedup_key'=>'non-response-ack:'.$donorId.':'.$state['standby_started_at'],
                ]);
            AuditLogger::log($donorId,'donor.non_response_acknowledged','user',(string)$donorId,['consumed_through'=>$now]);
            return true;
        });
    }
}
