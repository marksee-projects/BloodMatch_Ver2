<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use PDO;

final class EmailAttemptBudget
{
    public const ACCOUNT = 'account';
    public const NORMAL = 'normal';
    public const EMERGENCY = 'emergency';
    public const HOUR_TOTAL = 100;
    public const HOUR_PROTECTED = 10;
    public const DAY_TOTAL = 450;
    public const DAY_PROTECTED = 50;

    public static function denial(string $class, int $hourTotal, int $dayTotal, int $hourOther, int $dayOther): ?string
    {
        if (!in_array($class, [self::ACCOUNT, self::NORMAL, self::EMERGENCY], true)) {
            throw new \InvalidArgumentException('Invalid mail class.');
        }
        if ($hourTotal >= self::HOUR_TOTAL) { return 'total_hour'; }
        if ($dayTotal >= self::DAY_TOTAL) { return 'total_day'; }
        // All non-account traffic shares the unprotected capacity. Emergency
        // therefore cannot exceed 90/hour or 400/rolling 24h, even with retries.
        if ($class !== self::ACCOUNT) {
            if ($hourOther >= self::HOUR_TOTAL - self::HOUR_PROTECTED) { return 'protected_hour'; }
            if ($dayOther >= self::DAY_TOTAL - self::DAY_PROTECTED) { return 'protected_day'; }
        }
        return null;
    }

    /** Reserve immediately before transport; never refund a failure/crash.
     * An optional independent PDO is for focused tests, never a workflow PDO.
     * @return array{allowed: bool, reason: ?string, attempt_id: ?int}
     */
    public static function reserve(string $class, ?PDO $pdo = null): array
    {
        self::denial($class, 0, 0, 0, 0);
        $pdo ??= Database::independentPdo();
        if ($pdo->inTransaction()) {
            throw new \LogicException('Mail accounting requires an independent connection.');
        }
        $name = 'bm-mail-budget:' . substr(hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 3)');
        $lock->execute([$name]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new \RuntimeException('Mail accounting lock unavailable.');
        }
        try {
            // Snapshot starts AFTER acquiring the common cross-process lock.
            $pdo->beginTransaction();
            $now = (string) $pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
            $counts = $pdo->prepare("SELECT COUNT(*) AS day_total,
                COALESCE(SUM(attempted_at > DATE_SUB(?, INTERVAL 1 HOUR)), 0) AS hour_total,
                COALESCE(SUM(mail_class <> 'account'), 0) AS day_other,
                COALESCE(SUM(mail_class <> 'account' AND attempted_at > DATE_SUB(?, INTERVAL 1 HOUR)), 0) AS hour_other
                FROM email_attempts WHERE attempted_at > DATE_SUB(?, INTERVAL 24 HOUR)");
            $counts->execute([$now, $now, $now]);
            $row = $counts->fetch(PDO::FETCH_ASSOC);
            $reason = self::denial($class, (int) $row['hour_total'], (int) $row['day_total'], (int) $row['hour_other'], (int) $row['day_other']);
            $id = null;
            if ($reason === null) {
                $pdo->prepare('INSERT INTO email_attempts (mail_class, attempted_at) VALUES (?, ?)')->execute([$class, $now]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
            return ['allowed' => $reason === null, 'reason' => $reason, 'attempt_id' => $id];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        }
    }
}
