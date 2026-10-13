<?php

declare(strict_types=1);

namespace BloodMatch\Services;

final class NotificationService
{
    public const EMAIL_NONE = 'none';
    public const EMAIL_NORMAL = 'normal';
    public const EMAIL_EMERGENCY = 'emergency';

    private const MAX_EMAIL_RECIPIENTS_PER_BATCH = 500;

    public static function displayName(int $userId): string
    {
        $user = (new \BloodMatch\Repositories\UserRepository())->findProfileDisplayById($userId);
        return trim((string) ($user['full_name'] ?? '')) ?: 'A community member';
    }

    public static function requestDescription(int $requesterId): string
    {
        return self::displayName($requesterId) . "'s blood request";
    }

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

    public static function dedupDonationReportSubmitted(int $reportId, ?int $officerId = null): string
    {
        return $officerId !== null
            ? "donation_report:{$reportId}:submitted:officer:{$officerId}"
            : "donation_report:{$reportId}:submitted";
    }

    public static function notifyVerificationRequested(
        int $memberId,
        string $memberName,
        int $documentId,
        bool $resubmitted = false
    ): array {
        $users = new \BloodMatch\Repositories\UserRepository();
        $adminIds = $users->activeIdsByRole('admin');
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
                    'email' => self::EMAIL_NORMAL,
                ]
            );
            if ($notificationId !== null) {
                $notified[] = $adminId;
            }
        }

        return $notified;
    }

    /** Legacy helper; review recipients are now global staff. */
    public static function chapterOfficerIds(?int $chapterId): array
    {
        return (new \BloodMatch\Repositories\UserRepository())->activeIdsByRole('admin');
    }

    /** Called before unfinished offers are closed, inside the owning transaction. */
    public static function notifyRequestClosed(int $requestId, string $status): void
    {
        $s = \BloodMatch\Config\Database::pdo()->prepare("SELECT DISTINCT m.donor_id FROM matches m WHERE m.request_id=? AND
            (m.status IN ('POTENTIAL','NOTIFIED','RESPONDED') OR EXISTS
                (SELECT 1 FROM donation_reports dr WHERE dr.match_id=m.id AND dr.status='PENDING'))");
        $s->execute([$requestId]);
        $donorIds = $s->fetchAll(\PDO::FETCH_COLUMN);
        $request = (new \BloodMatch\Repositories\BloodRequestRepository())->findById($requestId);
        $description = self::requestDescription((int) $request['requester_id']);
        foreach ($donorIds as $donorId) {
            self::notify((int)$donorId, 'request.' . strtolower($status), 'Blood request ' . strtolower($status),
                $description . ' is ' . strtolower($status) . '. Unfinished offers are closed; pending donation reports remain available for review.', [
                    'related_type'=>'blood_request', 'related_id'=>$requestId,
                    'dedup_key'=>"request:{$requestId}:{$status}:donor:{$donorId}",
                ]);
        }
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

        $emailPriority = $options['email'] ?? self::EMAIL_NONE;
        if ($emailPriority !== self::EMAIL_NONE) {
            self::attemptEmail($userId, $id, $title, $body, $emailPriority, $type);
        }

        return $id;
    }

    public static function notifyMany(array $userIds, string $type, string $title, string $body, array $options = []): array
    {
        $notified = [];
        foreach (array_chunk(array_unique($userIds),self::MAX_EMAIL_RECIPIENTS_PER_BATCH) as $batch) {
        foreach ($batch as $uid) {
            $id = self::notify($uid, $type, $title, $body, $options);
            if ($id !== null) {
                $notified[$id] = $uid;
            }
        }
        }
        return $notified;
    }

    public static function attemptExistingEmail(
        int $userId,
        int $notificationId,
        string $subject,
        string $body,
        string $priority,
        string $type = ''
    ): void {
        try {
            self::attemptEmail($userId, $notificationId, $subject, $body, $priority, $type);
        } catch (\Throwable $e) {
            error_log('[notifications] best-effort email failed: ' . $e->getMessage());
        }
    }

    public static function notifyMatchGeneration(
        array $request,
        int $generation,
        array $eligibleDonorIds,
        bool $sendEmergencyEmail = false,
        bool $sendEmailOnMatch = false
    ): array {
        $pdo=\BloodMatch\Config\Database::pdo();
        if (!$pdo->inTransaction()) {
            return WorkflowLockService::transaction(fn()=>self::notifyMatchGeneration($request,$generation,$eligibleDonorIds,$sendEmergencyEmail));
        }
        $requestId = (int) $request['id'];
        $emergency = (string) ($request['urgency'] ?? '') === 'emergency';
        if ($emergency && ($sendEmergencyEmail || $sendEmailOnMatch)) {
            $emailPriority = self::EMAIL_EMERGENCY;
        } elseif ($sendEmailOnMatch) {
            $emailPriority = self::EMAIL_NORMAL;
        } else {
            $emailPriority = self::EMAIL_NONE;
        }

        $title = 'Compatible donation opportunity';
        $body = sprintf(
            '%s (%s) for blood type %s near %s needs donors like you. Open BloodMatch for details.',
            self::requestDescription((int) $request['requester_id']),
            ucfirst((string) $request['urgency']),
            (string) $request['required_blood_type'],
            (string) $request['facility_name']
        );

        $pdo->prepare("UPDATE notifications SET title=?,body=? WHERE type='match.new' AND related_type='blood_request' AND related_id=?")
            ->execute([$title,mb_substr($body,0,500),$requestId]);
        $notifiedDonorIds = [];
        foreach (array_chunk(array_values(array_unique($eligibleDonorIds)),self::MAX_EMAIL_RECIPIENTS_PER_BATCH) as $batch) {
        foreach ($batch as $donorId) {
            $existing=$pdo->prepare("SELECT id,generation FROM notifications WHERE user_id=? AND type='match.new' AND dedup_key=? ORDER BY generation DESC,id DESC LIMIT 1 FOR UPDATE");
            $existing->execute([$donorId,self::dedupMatch($requestId,(int)$donorId)]);
            $visible=$existing->fetch(\PDO::FETCH_ASSOC);
            $visibleId=$visible===false?false:$visible['id'];
            if ($visibleId!==false) {
                $pdo->prepare('UPDATE notifications SET title=?,body=?,generation=? WHERE id=?')->execute([$title,mb_substr($body,0,500),$generation,$visibleId]);
                $pdo->prepare("UPDATE email_outbox SET status='cancelled',cancel_reason='opportunity_refreshed',lease_token=NULL,lease_expires_at=NULL,updated_at=UTC_TIMESTAMP(6)
                    WHERE notification_id=? AND status IN ('pending','leased') AND ?=0")->execute([$visibleId,(int)((int)$visible['generation']===$generation)]);
                NonResponseStandbyService::opportunity($requestId,(int)$donorId,$generation,(int)$visibleId);
                $notifiedDonorIds[]=(int)$donorId;
                continue;
            }
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
                NonResponseStandbyService::opportunity($requestId,(int)$donorId,$generation,$id);
                $notifiedDonorIds[] = (int) $donorId;
            }
        }
        }

        return $notifiedDonorIds;
    }

    private static function attemptEmail(
        int $userId,
        int $notificationId,
        string $subject,
        string $body,
        string $priority,
        string $type = ''
    ): void {
        try {
            if (!Mailer::isConfigured()) {
                return;
            }

            $repo = new \BloodMatch\Repositories\NotificationRepository();

            if ($priority !== self::EMAIL_EMERGENCY) {
                if ($repo->emailCountInLastHour($userId, AuthService::nowUtc()) >= self::MAX_EMAILS_PER_USER_PER_HOUR) {
                    return;
                }
            }

            $user = (new \BloodMatch\Repositories\UserRepository())->findById($userId);
            if ($user === null || !is_string($user['email']) || filter_var($user['email'], FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            // Only mass donor match broadcasts require email_verified_at to prevent broadcasting to unverified donor addresses.
            // Direct transactional notifications (verification decisions, account status changes, match responses,
            // donation reports, verification requests, etc.) must always be delivered to the registered account email.
            if ($type === 'match.new' && empty($user['email_verified_at'])) {
                return;
            }

            $formattedSubject = str_starts_with($subject, '[BloodMatch] ') ? $subject : '[BloodMatch] ' . $subject;
            $htmlContent = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #111;">'
                . '<h2 style="margin-top: 0; color: #b91c1c;">BloodMatch</h2>'
                . '<h3 style="margin-top: 0; color: #1f2937;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<p style="font-size: 15px; line-height: 1.6; color: #374151;">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>'
                . '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />'
                . '<p style="color: #6b7280; font-size: 12px;">This is an automated notification from BloodMatch. Please do not reply directly to this email.</p>'
                . '</div>';

            $sent = Mailer::send(
                $user['email'],
                $formattedSubject,
                $htmlContent
            );

            if ($sent) {
                $repo->markEmailed($notificationId, AuthService::nowUtc());
            }
        } catch (\Throwable $e) {
            error_log('[notification] email delivery failed for user ' . $userId . ': ' . $e->getMessage());
        }
    }
}
