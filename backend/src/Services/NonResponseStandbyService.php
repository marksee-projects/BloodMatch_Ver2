<?php
declare(strict_types=1);
namespace BloodMatch\Services;

/**
 * Retired non-response standby. Legacy entry points do no database work.
 * Historical flags/timers cannot block donors or send new notifications.
 * Keep older scheduled callers harmless; donation safety windows are separate.
 */
final class NonResponseStandbyService
{
    public static function ready(): bool { return false; }
    public static function state(int $donorId): array
    {
        return ['standby_started_at'=>null, 'consumed_through'=>null];
    }
    public static function opportunity(int $requestId,int $donorId,int $generation,int $notificationId): void {}
    public static function resolve(int $requestId,?int $donorId,string $status): void {}
    public static function processDue(?array $onlyIds=null): int { return 0; }
    public static function acknowledge(int $donorId): bool { return false; }

    /** Historical timestamp comparison retained for older integrations. */
    public static function consumed(string $dueAt,?string $through): bool
    {
        return $through!==null && $dueAt<=$through;
    }
}
