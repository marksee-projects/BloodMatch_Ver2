<?php
declare(strict_types=1);
namespace BloodMatch\Repositories;

use BloodMatch\Config\Database;

final class EmailOutboxRepository
{
    public function claim(array $ids, string $now): ?array
    {
        if ($ids === []) { return null; }
        return \BloodMatch\Services\WorkflowLockService::transaction(function () use ($ids, $now) {
            $pdo = Database::pdo();
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT * FROM email_outbox WHERE id IN ($marks) AND
                ((status='pending' AND next_attempt_at<=?) OR (status='leased' AND lease_expires_at<=?))
                ORDER BY (priority='emergency') DESC,next_attempt_at,id LIMIT 1 FOR UPDATE");
            $s->execute(array_merge($ids, [$now, $now])); $job = $s->fetch(\PDO::FETCH_ASSOC);
            if ($job === false) { return null; }
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE email_outbox SET status='leased',lease_token=?,lease_expires_at=DATE_ADD(?,INTERVAL 120 SECOND),updated_at=? WHERE id=?")
                ->execute([$token, $now, $now, $job['id']]);
            $job['lease_token'] = $token; $job['status'] = 'leased';
            return $job;
        });
    }

    public function beginAttempt(array $job, string $now): bool
    {
        $s = Database::pdo()->prepare("UPDATE email_outbox SET attempts=attempts+1,updated_at=?
            WHERE id=? AND status='leased' AND lease_token=? AND lease_expires_at>? AND attempts<6");
        $s->execute([$now,$job['id'],$job['lease_token'],$now]);
        return $s->rowCount() === 1;
    }

    /** Only explicitly scoped jobs for the same recipient and five-minute window. */
    public function claimDigest(array $first, array $ids, int $limit, string $now): array
    {
        if ($first['priority'] !== 'emergency' || $limit <= 1) { return [$first]; }
        return \BloodMatch\Services\WorkflowLockService::transaction(function () use ($first,$ids,$limit,$now) {
            $pdo=Database::pdo(); $marks=implode(',',array_fill(0,count($ids),'?'));
            $s=$pdo->prepare("SELECT e.* FROM email_outbox e JOIN notifications n ON n.id=e.notification_id
                WHERE e.id IN ($marks) AND e.id<>? AND e.priority='emergency'
                AND n.user_id=(SELECT user_id FROM notifications WHERE id=?)
                AND e.created_at>=? AND e.created_at<DATE_ADD(?,INTERVAL 5 MINUTE)
                AND ((e.status='pending' AND e.next_attempt_at<=?) OR (e.status='leased' AND e.lease_expires_at<=?))
                ORDER BY e.id LIMIT " . ($limit-1) . ' FOR UPDATE');
            $s->execute(array_merge($ids,[$first['id'],$first['notification_id'],$first['created_at'],$first['created_at'],$now,$now]));
            $jobs=[$first];
            foreach ($s->fetchAll(\PDO::FETCH_ASSOC) as $job) {
                $token=bin2hex(random_bytes(16));
                $pdo->prepare("UPDATE email_outbox SET status='leased',lease_token=?,lease_expires_at=DATE_ADD(?,INTERVAL 120 SECOND),updated_at=? WHERE id=?")
                    ->execute([$token,$now,$now,$job['id']]);
                $job['lease_token']=$token; $job['status']='leased'; $jobs[]=$job;
            }
            return $jobs;
        });
    }

    public function beginDigestAttempt(array $jobs, string $now): bool
    {
        try {
            return \BloodMatch\Services\WorkflowLockService::transaction(function () use ($jobs,$now) {
                foreach ($jobs as $job) { if (!$this->beginAttempt($job,$now)) { throw new \LogicException('Digest lease lost.'); } }
                return true;
            });
        } catch (\LogicException $e) { return false; }
    }

    public function finishDigest(array $jobs, string $status, string $now, ?string $code = null, ?string $next = null): bool
    {
        try {
            return \BloodMatch\Services\WorkflowLockService::transaction(function () use ($jobs,$status,$now,$code,$next) {
                foreach ($jobs as $job) {
                    if (!$this->finish($job,$status,$now,$code,$next)) { throw new \LogicException('Digest lease lost.'); }
                }
                return true;
            });
        } catch (\LogicException $e) { return false; }
    }

    public function finish(array $job, string $status, string $now, ?string $code = null, ?string $next = null): bool
    {
        return \BloodMatch\Services\WorkflowLockService::transaction(function () use ($job,$status,$now,$code,$next) {
            $pdo = Database::pdo();
            $s = $pdo->prepare("UPDATE email_outbox SET status=?,last_error_code=?,cancel_reason=?,next_attempt_at=?,
                sent_at=?,lease_token=NULL,lease_expires_at=NULL,updated_at=?
                WHERE id=? AND status='leased' AND lease_token=? AND lease_expires_at>?");
            $s->execute([$status, $status === 'cancelled' ? null : $code, $status === 'cancelled' ? $code : null,
                $next ?? $now, $status === 'sent' ? $now : null, $now, $job['id'],$job['lease_token'],$now]);
            if ($s->rowCount() !== 1) { return false; }
            if ($status === 'sent') {
                $pdo->prepare('UPDATE notifications SET emailed_at=? WHERE id=?')->execute([$now,$job['notification_id']]);
            }
            return true;
        });
    }

    public function insert(int $notificationId, string $priority, string $expiresAt): void
    {
        $pdo = Database::pdo();
        if (!$pdo->inTransaction()) { throw new \LogicException('Outbox insertion requires its notification transaction.'); }
        // No IGNORE/upsert: failures must abort the event. Only a newly inserted
        // notification reaches here, so duplicates never backfill historical mail.
        $pdo->prepare("INSERT INTO email_outbox
            (notification_id,delivery_key,priority,next_attempt_at,expires_at,created_at,updated_at)
            VALUES (?,?,?,UTC_TIMESTAMP(6),?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")
            ->execute([$notificationId, 'notification:' . $notificationId . ':email', $priority, $expiresAt]);
    }
}
