<?php
declare(strict_types=1);
namespace BloodMatch\Repositories;

use BloodMatch\Config\Database;

final class EmailOutboxRepository
{
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
