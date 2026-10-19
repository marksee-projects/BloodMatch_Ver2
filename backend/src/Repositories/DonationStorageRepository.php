<?php
declare(strict_types=1);
namespace BloodMatch\Repositories;

use BloodMatch\Config\Database;

/** Migration-gated storage adapter. Pre-032 application continues using its original tables. */
final class DonationStorageRepository
{
    public const MIGRATION = '032_consolidate_donation_storage.sql';

    public static function consolidated(): bool
    {
        static $ready = null;
        if ($ready === null) {
            $s = Database::pdo()->prepare('SELECT 1 FROM schema_migrations WHERE name=?');
            $s->execute([self::MIGRATION]);
            $ready = $s->fetchColumn() !== false;
        }
        return $ready;
    }

    /** Read-only relational projection, retaining legacy aliases for retention/queue queries. */
    public static function sql(string $sql): string
    {
        if (!self::consolidated()) return $sql;
        return str_replace('donation_report_holds', '(SELECT id AS report_id,donor_id,hold_started_at AS started_at,hold_released_at AS released_at,hold_released_by AS released_by,hold_release_basis AS release_basis,hold_release_type AS release_type FROM donation_reports WHERE hold_started_at IS NOT NULL)', $sql);
    }

    public static function startHold(int $report, int $donor, string $now): void
    {
        $s = Database::pdo()->prepare(self::consolidated()
            ? 'UPDATE donation_reports SET hold_started_at=? WHERE id=? AND donor_id=?'
            : 'INSERT INTO donation_report_holds(started_at,report_id,donor_id) VALUES(?,?,?)');
        $s->execute([$now,$report,$donor]);
    }

    public static function duplicateCredit(int $donor, string $occurred): bool
    {
        $s = Database::pdo()->prepare(self::consolidated()
            ? 'SELECT 1 FROM donation_reports WHERE donor_id=? AND occurred_at=? AND credit_recorded_at IS NOT NULL LIMIT 1 FOR UPDATE'
            : 'SELECT 1 FROM donation_credits WHERE donor_id=? AND occurred_at=? LIMIT 1 FOR UPDATE');
        $s->execute([$donor,$occurred]);
        return $s->fetchColumn() !== false;
    }

    public static function releaseHold(int $report, string $now, ?int $actor, string $type, ?string $basis): void
    {
        $s = Database::pdo()->prepare(self::consolidated()
            ? 'UPDATE donation_reports SET hold_released_at=?,hold_released_by=?,hold_release_type=?,hold_release_basis=? WHERE id=? AND hold_started_at IS NOT NULL AND hold_released_at IS NULL'
            : 'UPDATE donation_report_holds SET released_at=?,released_by=?,release_type=?,release_basis=? WHERE report_id=? AND released_at IS NULL');
        $s->execute([$now,$actor,$type,$basis,$report]);
    }

    public static function reopenHold(int $report): void
    {
        Database::pdo()->prepare(self::consolidated()
            ? 'UPDATE donation_reports SET hold_released_at=NULL,hold_released_by=NULL,hold_release_basis=NULL,hold_release_type=NULL WHERE id=? AND hold_started_at IS NOT NULL'
            : 'UPDATE donation_report_holds SET released_at=NULL,released_by=NULL,release_basis=NULL,release_type=NULL WHERE report_id=?')->execute([$report]);
    }

    public static function hold(int $report): array|false
    {
        // The caller already holds the report row lock.
        $s = Database::pdo()->prepare(self::sql('SELECT * FROM donation_report_holds h WHERE report_id=?'));
        $s->execute([$report]);
        return $s->fetch();
    }
}
