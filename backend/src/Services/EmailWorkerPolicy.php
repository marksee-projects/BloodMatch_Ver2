<?php
declare(strict_types=1);
namespace BloodMatch\Services;

final class EmailWorkerPolicy
{
    public static function mailClass(string $type, string $priority): ?string
    {
        if ($type === 'match.new' && $priority === 'emergency') { return EmailAttemptBudget::EMERGENCY; }
        if ($type === 'verification.decision' && $priority === 'normal') { return EmailAttemptBudget::ACCOUNT; }
        return null;
    }

    public static function retry(int $attempts, string $now): array
    {
        $delays = [1 => 60, 2 => 300, 3 => 900, 4 => 3600, 5 => 14400];
        if ($attempts >= 6) { return ['status'=>'failed','next'=>$now]; }
        if (!isset($delays[$attempts])) { throw new \LogicException('Invalid attempt count.'); }
        return ['status'=>'pending','next'=>gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') + $delays[$attempts])];
    }

    public static function expired(string $expires, string $now): bool
    {
        return strtotime($expires . ' UTC') < strtotime($now . ' UTC');
    }

    public static function digestReadyAt(string $created): string
    {
        return (new \DateTimeImmutable($created,new \DateTimeZone('UTC')))->modify('+5 minutes')
            ->format(str_contains($created,'.')?'Y-m-d H:i:s.u':'Y-m-d H:i:s');
    }
}
