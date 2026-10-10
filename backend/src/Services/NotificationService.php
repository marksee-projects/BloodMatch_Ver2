<?php

declare(strict_types=1);

namespace BloodMatch\Services;

final class NotificationService
{
    public const EMAIL_NONE = 'none';
    public const EMAIL_NORMAL = 'normal';
    public const EMAIL_EMERGENCY = 'emergency';

    private const MAX_EMAIL_RECIPIENTS_PER_BATCH = 500;

    public static function dedupMatch(int $requestId, int $donorId): string
    {
        return "match:{$requestId}:{$donorId}";
    }

    public static function dedupMatchResponse(int $requestId, int $donorId): string
    {
        return "match-response:{$requestId}:{$donorId}";
    }

    public static function dedupVerification(int $userId, string $decision, ?int $auditId): string
    {
        $suffix = $auditId !== null ? (string) $auditId : uniqid('', true);
        return "verification:{$userId}:{$decision}:{$suffix}";
    }

    public static function dedupAccount(int $userId, string $transition, ?int $auditId): string
    {
        $suffix = $auditId !== null ? (string) $auditId : uniqid('', true);
        return "account:{$userId}:{$transition}:{$suffix}";
    }

    public static function dedupRequestStatus(int $requestId, string $status): string
    {
        return "request:{$requestId}:{$status}";
    }

    public static function dedupDonation(int $reportId, string $decision): string
    {
        return "donation:{$reportId}:{$decision}";
    }

    public static function notifyVerificationRequested(
        int $memberId,
        string $memberName,
        int $documentId,
        bool $resubmitted = false
    ): array {
        $adminIds = (new \BloodMatch\Repositories\UserRepository())->activeIdsByRole('admin');
        $notified = [];
        $title = $resubmitted ? 'National ID resubmitted' : 'New account verification request';
        $body = $resubmitted
            ? "{$memberName} uploaded another National ID and is ready for review."
            : "{$memberName} uploaded a National ID and is ready for review.";

        foreach ($adminIds as $adminId) {
            $notificationId = self::notify(
                $adminId,
                'verification.requested',
                $title,
                $body,
                [
                    'related_type' => 'verification',
                    'related_id' => $memberId,
                    'dedup_key' => "verification-request:{$documentId}:admin:{$adminId}",
                    'email' => self::EMAIL_NONE,
                ]
            );
            if ($notificationId !== null) {
                $notified[] = $adminId;
            }
        }

        return $notified;
    }

    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $options = []
    ): ?int {
        $priority = EmailEnqueueService::priority($type, (string) ($options['email'] ?? self::EMAIL_NONE));
        if ($priority !== null) {
            return EmailEnqueueService::transaction(fn () => self::insertNotification($userId, $type, $title, $body, $options, $priority));
        }
        return self::insertNotification($userId, $type, $title, $body, $options, null);
    }

    private static function insertNotification(int $userId, string $type, string $title, string $body, array $options, ?string $priority): ?int
    {
        $repo = new \BloodMatch\Repositories\NotificationRepository();
        $id = $repo->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => mb_substr($body, 0, 500),
            'related_type' => $options['related_type'] ?? null,
            'related_id' => $options['related_id'] ?? null,
            'dedup_key' => $options['dedup_key'] ?? null,
            'generation' => $options['generation'] ?? 0,
        ]);

        if ($id === null) {
            return null;
        }

        if ($priority !== null) {
            $user = (new \BloodMatch\Repositories\UserRepository())->findById($userId);
            if ($user !== null && !empty($user['email_verified_at'])
                && filter_var($user['email'], FILTER_VALIDATE_EMAIL) !== false) {
                $expiresAt = $priority === 'emergency' ? ($options['email_expires_at'] ?? null)
                    : gmdate('Y-m-d H:i:s', time() + 72 * 3600);
                if (!is_string($expiresAt) || strtotime($expiresAt . ' UTC') === false) {
                    throw new \LogicException('Queued notification expiry is required.');
                }
                (new \BloodMatch\Repositories\EmailOutboxRepository())->insert($id, $priority, $expiresAt);
            }
        }

        return $id;
    }

    public static function notifyMany(array $userIds, string $type, string $title, string $body, array $options = []): array
    {
        $notified = [];
        foreach (array_slice(array_unique($userIds), 0, self::MAX_EMAIL_RECIPIENTS_PER_BATCH * 10) as $uid) {
            $id = self::notify($uid, $type, $title, $body, $options);
            if ($id !== null) {
                $notified[$id] = $uid;
            }
        }
        return $notified;
    }

    public static function attemptExistingEmail(
        int $userId,
        int $notificationId,
        string $subject,
        string $body,
        string $priority
    ): void {
        // Legacy response caller retained as an explicit no-op: response and
        // other lifecycle email are outside the approved queued coverage.
    }

    public static function notifyMatchGeneration(
        array $request,
        int $generation,
        array $eligibleDonorIds,
        bool $sendEmergencyEmail = false
    ): array {
        $requestId = (int) $request['id'];
        $emergency = (string) $request['urgency'] === 'emergency';
        $emailPriority = $emergency && $sendEmergencyEmail
            ? self::EMAIL_EMERGENCY
            : self::EMAIL_NONE;

        $title = sprintf('New compatible donation opportunity (#%d)', $requestId);
        $body = sprintf(
            'A blood request (%s) for blood type %s near %s needs donors like you. Open BloodMatch for details.',
            ucfirst((string) $request['urgency']),
            (string) $request['required_blood_type'],
            (string) $request['facility_name']
        );

        $notifiedDonorIds = [];
        foreach (array_slice($eligibleDonorIds, 0, self::MAX_EMAIL_RECIPIENTS_PER_BATCH) as $donorId) {
            $id = self::notify(
                (int) $donorId,
                'match.new',
                $title,
                $body,
                [
                    'related_type' => 'blood_request',
                    'related_id' => $requestId,
                    'dedup_key' => self::dedupMatch($requestId, (int) $donorId),
                    'generation' => $generation,
                    'email' => $emailPriority,
                    'email_expires_at' => $request['needed_datetime'],
                ]
            );
            if ($id !== null) {
                $notifiedDonorIds[] = (int) $donorId;
            }
        }

        return $notifiedDonorIds;
    }

}
