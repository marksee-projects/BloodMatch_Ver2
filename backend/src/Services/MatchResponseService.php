<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Repositories\DonationReportRepository;
use BloodMatch\Repositories\MatchRepository;
use BloodMatch\Services\Exceptions\DonorIneligibleException;
use RuntimeException;
use Throwable;

final class MatchResponseService
{
    public function respond(array $actor, int $requestId, string $source): array
    {
        $actorId = (int) $actor['id'];
        $email = null;
        try {
            $result = WorkflowLockService::transaction(function () use ($actorId, $requestId, $source, &$email): array {
                $request = WorkflowLockService::request($requestId);
                $users = WorkflowLockService::users([$actorId, (int) $request['requester_id']]);
                $donor = $users[$actorId];
                $repo = new MatchRepository();
                $match = $repo->findByRequestAndDonorForUpdate($requestId, $actorId);
                // Evaluate after all lock waits, using the current donor row/consent.
                $eligibility = DonorEligibilityService::evaluateForRequest($donor, $request, AuthService::nowUtc());
                if (!$eligibility['eligible']) {
                    throw new DonorIneligibleException($eligibility);
                }
                if ($match !== null && (string) $match['status'] === 'COMPLETED') {
                    throw new RuntimeException('A completed donation match cannot be changed.', 409);
                }
                $wasResponded = $match !== null && (string) $match['status'] === 'RESPONDED';
                $created = $match === null;
                if ($created) {
                    $matchId = $repo->insertResponded($requestId, $actorId, max(1, $repo->maxGenerationForRequest($requestId)));
                } else {
                    $matchId = (int) $match['id'];
                    if (!$wasResponded) {
                        $repo->setStatus($matchId, 'RESPONDED');
                    }
                }
                if (!$wasResponded && \BloodMatch\Repositories\DonationWorkflowRepository::ready()) {
                    \BloodMatch\Config\Database::pdo()->prepare('UPDATE matches SET responded_at=? WHERE id=?')->execute([AuthService::nowUtc(),$matchId]);
                }
                if (!$wasResponded && OfferChatService::ready()) {
                    \BloodMatch\Config\Database::pdo()->prepare('UPDATE matches SET offer_cycle=offer_cycle+1 WHERE id=?')->execute([$matchId]);
                }
                $title = 'A donor responded to your blood request';
                $body = NotificationService::displayName($actorId) . ' has offered to help with your blood request. Open BloodMatch to review the response.';
                $notificationId=null;
                if (!$wasResponded) {
                    $base=NotificationService::dedupMatchResponse($requestId,$actorId);
                    $previous=\BloodMatch\Config\Database::pdo()->prepare("SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=? AND type='match.responded' AND (dedup_key=? OR dedup_key LIKE CONCAT(?,':after:%'))");
                    $previous->execute([(int)$request['requester_id'],$base,$base]);
                    $previousId=(int)$previous->fetchColumn();
                    $notificationId=NotificationService::notify((int)$request['requester_id'],'match.responded',$title,$body,[
                        'related_type'=>'blood_request','related_id'=>$requestId,
                        'dedup_key'=>$previousId===0?$base:$base.':after:'.$previousId,'generation'=>0,'email'=>NotificationService::EMAIL_NONE,
                    ]);
                }
                if (!$wasResponded) {
                    AuditLogger::log($actorId, 'match.responded', 'blood_request', (string) $requestId, [
                        'match_id' => $matchId, 'source' => $source,
                    ]);
                }
                if ((string) $request['urgency'] === 'emergency' && $notificationId !== null) {
                    $email = [
                        'user_id' => (int) $request['requester_id'], 'notification_id' => $notificationId,
                        'subject' => $title, 'body' => $body,
                    ];
                }
                return [
                    'chat_request' => OfferChatService::ready() ? null : ChatConsentService::request($donor,(int)$request['requester_id']),
                    'message' => $wasResponded ? 'Already responded.' : 'Response recorded.',
                    'match_id' => $matchId, 'status' => 'RESPONDED',
                    'created' => $created, 'already_responded' => $wasResponded,
                ];
            });
        } catch (Throwable $e) {
            if ($e instanceof DonorIneligibleException
                || ($e instanceof RuntimeException && $e->getCode() >= 400 && $e->getCode() <= 499)) {
                throw $e;
            }
            error_log('[matches] response failed for request ' . $requestId . ': ' . $e->getMessage());
            throw new RuntimeException('Could not record the response.', 500);
        }
        if ($email !== null) {
            NotificationService::attemptExistingEmail(
                $email['user_id'], $email['notification_id'], $email['subject'], $email['body'],
                NotificationService::EMAIL_EMERGENCY
            );
        }
        return $result;
    }

    public function withdraw(array $actor, int $matchId): array
    {
        $repo = new MatchRepository();
        $pre = $repo->findByIdDetailed($matchId);
        if ($pre === null) {
            throw new RuntimeException('Match not found.', 404);
        }
        if ((int) $pre['donor_id'] !== (int) $actor['id']) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', null, null, [
                'endpoint' => 'matches.withdraw', 'reason' => 'not_match_owner',
            ]);
            throw new RuntimeException('Forbidden.', 403);
        }
        return WorkflowLockService::transaction(function () use ($actor, $matchId, $pre, $repo): array {
            $request = WorkflowLockService::request((int) $pre['request_id']);
            $users = WorkflowLockService::users([(int) $actor['id'], (int) $request['requester_id']]);
            $currentActor = $users[(int) $actor['id']];
            WorkflowLockService::assertDonorActor($currentActor);
            $match = $repo->findByIdForUpdate($matchId);
            if ($match === null || (int) $match['donor_id'] !== (int) $currentActor['id']
                || (int) $match['request_id'] !== (int) $request['id']) {
                throw new RuntimeException('Match not found.', 404);
            }
            WorkflowLockService::assertOpen($request);
            if ((string) $match['status'] !== 'RESPONDED') {
                throw new RuntimeException('Only an active offer to help can be cancelled.', 409);
            }
            if ((new DonationReportRepository())->pendingExistsForMatchForUpdate($matchId)) {
                throw new RuntimeException('This offer cannot be cancelled while its donation report is awaiting review.', 409);
            }
            $repo->setStatus($matchId, 'NOTIFIED');
            $event = \BloodMatch\Config\Database::pdo()->prepare("SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=? AND type='match.withdrawn' AND related_type='blood_request' AND related_id=?");
            $event->execute([(int)$currentActor['id'], (int)$request['id']]);
            $previousEvent = (int)$event->fetchColumn();
            foreach (array_unique([(int)$request['requester_id'], (int)$currentActor['id']]) as $recipientId) {
                NotificationService::notify($recipientId, 'match.withdrawn', 'Donor offer withdrawn',
                    NotificationService::displayName((int)$currentActor['id']) . " withdrew their offer to help with " . NotificationService::requestDescription((int)$request['requester_id']) . '.', [
                        'related_type'=>'blood_request', 'related_id'=>(int)$request['id'],
                        'dedup_key'=>"withdrawal:{$matchId}:after:{$previousEvent}:{$recipientId}",
                    ]);
            }
            AuditLogger::log((int) $currentActor['id'], 'match.response_withdrawn', 'blood_request', (string) $request['id'], [
                'match_id' => $matchId,
            ]);
            return ['message' => 'Your offer to help has been cancelled.', 'status' => 'NOTIFIED'];
        });
    }
}
