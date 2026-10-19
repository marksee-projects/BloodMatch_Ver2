<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\DonationReportRepository;
use BloodMatch\Repositories\MatchRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\Exceptions\ValidationException;
use RuntimeException;
use Throwable;

final class DonationService
{
    public static function offerInvalid(array $match,array $request,array $donor): bool
    {
        if (!in_array((string)$donor['blood_type'],BloodCompatibilityService::getCompatibleDonorTypes((string)$request['required_blood_type']),true)) { return true; }
        $s=\BloodMatch\Config\Database::pdo()->prepare('SELECT 1 FROM notifications WHERE dedup_key=? LIMIT 1');
        $s->execute(["offer-invalid:{$match['id']}:{$match['generation']}:{$donor['id']}"]);
        return $s->fetchColumn()!==false && !DonorEligibilityService::evaluateForRequest($donor,$request)['eligible'];
    }
    public function submit(int $donorId,int $matchId,?string $note,mixed $occurredAt=null): array
    {
        $actor=(new UserRepository())->findById($donorId);
        if (!$actor) throw new RuntimeException('Donor not found.',404);
        return (new DonationCompletionService())->submit($actor,$matchId,$occurredAt,$note);
    }

    public static function normalizeRejectionReason(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new ValidationException(['rejection_reason' => ['Enter a rejection reason as text.']]);
        }
        $reason = preg_replace('/^[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+$/u', '', $value) ?? '';
        if (!preg_match('/[^\s\p{Z}\p{Cf}]/u', $reason)) {
            throw new ValidationException(['rejection_reason' => ['Enter a rejection reason.']]);
        }
        if (mb_strlen($reason, 'UTF-8') > 500) {
            throw new ValidationException(['rejection_reason' => ['Rejection reason must be at most 500 characters.']]);
        }
        return $reason;
    }

    public function decide(array $actor,int $reportId,bool $confirm,mixed $rejectionReason=null,mixed $evidenceNote=null,?int $expectedVersion=null): array
    {
        if ($expectedVersion===null) throw new RuntimeException('The exact report version is required. Reload the report.',400);
        return (new DonationCompletionService())->review($actor,$reportId,$expectedVersion,$confirm?'confirm':'reject',$confirm?$evidenceNote:$rejectionReason);
    }
}
