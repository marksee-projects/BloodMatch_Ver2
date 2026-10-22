<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use RuntimeException;

final class DonationCompletionPolicy
{
    // Explicit user approval recorded 2026-10-19; these are product rules.
    public const DATE_RULES_APPROVED = true;
    public const HOLD_RELEASE_APPROVED = true;

    public static function occurrence(mixed $value,string $responseAt,string $submittedAt): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D',$value)) {
            throw new RuntimeException('Choose the actual donation date and time with its displayed timezone.',400);
        }
        if ((int)substr($value,0,4)<1000 || (substr($value,-1)!=='Z' && ((int)substr($value,-5,2)>14 || (int)substr($value,-2)>59 || ((int)substr($value,-5,2)===14 && (int)substr($value,-2)!==0)))) throw new RuntimeException('Invalid donation timezone or year.',400);
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',str_ends_with($value,'Z')?substr($value,0,-1).'+00:00':$value);
        $errors=\DateTimeImmutable::getLastErrors();
        if (!$date || ($errors!==false && ($errors['warning_count'] || $errors['error_count']))) throw new RuntimeException('Invalid donation date or time.',400);
        $utc=$date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if ($utc>$submittedAt) throw new RuntimeException('The donation time cannot be in the future.',400);
        if ($utc<$responseAt) {
            $earliest=(new \DateTimeImmutable($responseAt,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Manila'))->format('M j, Y g:i:s A');
            throw new RuntimeException('The donation time cannot precede the recorded donor response. Choose the actual donation time on or after '.$earliest.' (Philippine time). Check AM/PM.',400);
        }
        return $utc;
    }

    public static function restrictionAnchor(?string $existing,string $occurred): string
    {
        return $existing!==null && $existing>$occurred ? $existing : $occurred;
    }

    public static function requireDateApproval(): void
    {
        if (!self::DATE_RULES_APPROVED) {
            throw new RuntimeException('Donation date and restriction rules await approval. No donation was recorded.', 503);
        }
    }

    public static function dueActions(array $report, string $nowUtc): array
    {
        if (($report['status'] ?? '') !== 'PENDING'
            || !empty($report['acknowledged_at'])
            || !in_array($report['workflow_state'] ?? '', ['awaiting_requester','needs_review','clarification'], true)) return [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$report['reported_at']??'')) return [];
        $submitted = strtotime($report['reported_at'].' UTC');
        $now = strtotime($nowUtc.' UTC');
        if ($submitted === false || $now === false) return [];
        $age = $now-$submitted;
        $due=[];
        if ($age >= 24*3600 && empty($report['reminder_sent_at'])) $due[]='remind';
        if ($age >= 48*3600 && empty($report['escalated_at'])) $due[]='escalate';
        return $due;
    }

    public static function assertVersion(array $report, int $expectedVersion): void
    {
        if ($expectedVersion < 1 || (int)$report['content_version'] !== $expectedVersion) {
            throw new RuntimeException('This report changed. Reload its current version before responding.', 409);
        }
    }

    public static function assertRequester(array $actor, array $request, array $report): void
    {
        WorkflowLockService::assertActiveActor($actor);
        if ((int)$actor['id'] !== (int)$request['requester_id'] || (int)$actor['id'] === (int)$report['donor_id']) {
            throw new RuntimeException('Only the request owner can acknowledge another donor\'s report.',403);
        }
    }

    public static function assertUnresolved(array $report): void
    {
        if ($report['status'] !== 'PENDING') throw new RuntimeException('This report already has a final decision.',409);
    }

    public static function basis(mixed $value): string
    {
        return DonationService::normalizeRejectionReason($value);
    }

    public static function canFulfill(array $request, int $completed, string $nowUtc): bool
    {
        return $request['status'] === 'OPEN' && !RequestService::hasDeadlinePassed($request,$nowUtc)
            && $completed >= (int)$request['quantity_units'];
    }
}
