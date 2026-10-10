<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Services\Exceptions\EmailEnqueueException;

final class EmailEnqueueService
{
    /** Only the two approved notification channels. OTP/reset never enter here. */
    public static function priority(string $type, string $requested): ?string
    {
        if ($type === 'match.new' && $requested === NotificationService::EMAIL_EMERGENCY) { return 'emergency'; }
        if ($type === 'verification.decision' && $requested === NotificationService::EMAIL_NORMAL) { return 'normal'; }
        return null;
    }

    /** A savepoint makes caught nested enqueue failures safe without committing
     * the owning business transaction or silently leaving an orphan notification.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = Database::pdo();
        $owner = !$pdo->inTransaction();
        $started = false;
        try {
            if ($owner) { $pdo->beginTransaction(); }
            else { $pdo->exec('SAVEPOINT bm_email_enqueue'); }
            $started = true;
            $result = $work();
            if ($owner) { $pdo->commit(); }
            else { $pdo->exec('RELEASE SAVEPOINT bm_email_enqueue'); }
            return $result;
        } catch (\Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                if ($owner) { $pdo->rollBack(); }
                else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT bm_email_enqueue');
                    $pdo->exec('RELEASE SAVEPOINT bm_email_enqueue');
                }
            }
            throw new EmailEnqueueException($e);
        }
    }
}
