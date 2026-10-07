<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\MatchRepository;
use BloodMatch\Services\Exceptions\DonorIneligibleException;
use RuntimeException;
use Throwable;

final class MatchResponseService
{
    public function respond(array $actor, int $requestId, string $source): array
    {
        $pdo = Database::pdo();
        $requestRepo = new BloodRequestRepository();
        $matchRepo = new MatchRepository();
        $nowUtc = AuthService::nowUtc();
        $notificationId = null;
        $email = null;

        $pdo->beginTransaction();
        try {
            // One request lock serializes both first-response insertion and repeats.
            $request = $requestRepo->findByIdForUpdate($requestId);
            if ($request === null) {
                throw new RuntimeException('Request not found.', 404);
            }

            $match = $matchRepo->findByRequestAndDonorForUpdate($requestId, (int) $actor['id']);
            $eligibility = DonorEligibilityService::evaluateForRequest($actor, $request, $nowUtc);
            if (!$eligibility['eligible']) {
                throw new DonorIneligibleException($eligibility);
            }

            if ($match !== null && (string) $match['status'] === 'COMPLETED') {
                throw new RuntimeException('A completed donation match cannot be changed.', 409);
            }

            $wasResponded = $match !== null && (string) $match['status'] === 'RESPONDED';
            $created = false;
            if ($match === null) {
                $generation = max(1, $matchRepo->maxGenerationForRequest($requestId));
                $matchId = $matchRepo->insertResponded($requestId, (int) $actor['id'], $generation);
                $created = true;
            } else {
                $matchId = (int) $match['id'];
                if (!$wasResponded) {
                    // Eligibility was freshly checked above, so CLOSED may respond again.
                    $matchRepo->setStatus($matchId, 'RESPONDED');
                }
            }

            $title = sprintf('A donor responded to request #%d', $requestId);
            $body = 'A donor has offered to help with your blood request. Open BloodMatch to review the response.';
            $notificationId = NotificationService::notify(
                (int) $request['requester_id'],
                'match.responded',
                $title,
                $body,
                [
                    'related_type' => 'blood_request',
                    'related_id' => $requestId,
                    'dedup_key' => NotificationService::dedupMatchResponse($requestId, (int) $actor['id']),
                    'generation' => 0,
                    'email' => NotificationService::EMAIL_NONE,
                ]
            );

            if (!$wasResponded) {
                AuditLogger::log(
                    (int) $actor['id'],
                    'match.responded',
                    'blood_request',
                    (string) $requestId,
                    ['match_id' => $matchId, 'source' => $source]
                );
            }

            if ((string) $request['urgency'] === 'emergency' && $notificationId !== null) {
                $email = [
                    'user_id' => (int) $request['requester_id'],
                    'notification_id' => $notificationId,
                    'subject' => $title,
                    'body' => $body,
                ];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof DonorIneligibleException) {
                throw $e;
            }
            if ($e instanceof RuntimeException && $e->getCode() >= 400 && $e->getCode() <= 499) {
                throw $e;
            }
            error_log('[matches] response failed for request ' . $requestId . ': ' . $e->getMessage());
            throw new RuntimeException('Could not record the response.', 500);
        }

        // Email is deliberately outside the transaction and is best-effort.
        if ($email !== null) {
            NotificationService::attemptExistingEmail(
                $email['user_id'],
                $email['notification_id'],
                $email['subject'],
                $email['body'],
                NotificationService::EMAIL_EMERGENCY
            );
        }

        return [
            'message' => $wasResponded ? 'Already responded.' : 'Response recorded.',
            'match_id' => $matchId,
            'status' => 'RESPONDED',
            'created' => $created,
            'already_responded' => $wasResponded,
        ];
    }
}
