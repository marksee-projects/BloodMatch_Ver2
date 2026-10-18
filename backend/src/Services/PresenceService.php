<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;

final class PresenceService
{
    // Session-scoped records let logout clear one device without hiding another.
    public static function heartbeat(int $userId): void
    {
        $p = Database::pdo();
        $p->prepare('INSERT INTO user_presence (session_key,user_id,last_seen_at) VALUES (?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),last_seen_at=VALUES(last_seen_at)')
            ->execute([hash('sha256', session_id()), $userId]);
        $p->exec('DELETE FROM user_presence WHERE last_seen_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }

    public static function leave(): void
    {
        Database::pdo()->prepare('DELETE FROM user_presence WHERE session_key=?')->execute([hash('sha256', session_id())]);
    }

    public static function isOnline(int $userId): bool
    {
        $s = Database::pdo()->prepare("SELECT 1 FROM user_presence p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND p.last_seen_at>UTC_TIMESTAMP()-INTERVAL 90 SECOND AND u.account_status='active' AND u.verification_status='verified' LIMIT 1");
        $s->execute([$userId]);
        return $s->fetchColumn() !== false;
    }
}
