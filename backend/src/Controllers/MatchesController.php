<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\MatchRepository;
use BloodMatch\Repositories\DonationReportRepository;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Utils\Response;

final class MatchesController
{
    public function respond(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('matches.respond');

        if (empty($actor['email_verified_at'])) {
            Response::error('Please verify your email before responding to matches.', 403, [
                'code' => 'EMAIL_UNVERIFIED',
            ]);
            return;
        }

        $matchId = (int) $params['matchId'];

        $repo = new MatchRepository();
        $match = $repo->findByIdDetailed($matchId);

        if ($match === null) {
            Response::error('Match not found.', 404);
            return;
        }

        if ((int) $match['donor_id'] !== (int) $actor['id']) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', null, null, [
                'endpoint' => 'matches.respond',
                'reason' => 'not_match_owner',
            ]);
            Response::error('Forbidden.', 403);
            return;
        }

        if ((string) $match['request_status'] !== 'OPEN') {
            Response::error('This request is no longer active.', 409);
            return;
        }

        $status = (string) $match['status'];
        if ($status === 'RESPONDED') {
            Response::success(['message' => 'Already responded.', 'status' => 'RESPONDED']);
            return;
        }
        if (!in_array($status, ['POTENTIAL', 'NOTIFIED'], true)) {
            Response::error("Cannot respond to a match in state {$status}.", 409);
            return;
        }

        $repo->setStatus($matchId, 'RESPONDED');
        AuditLogger::log((int) $actor['id'], 'match.responded', 'blood_request', (string) $match['request_id'], [
            'match_id' => $matchId,
        ]);

        Response::success(['message' => 'Response recorded.', 'status' => 'RESPONDED']);
    }

    public function withdraw(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('matches.withdraw');
        $matchId = (int) $params['matchId'];
        $repo = new MatchRepository();
        $match = $repo->findByIdDetailed($matchId);

        if ($match === null) {
            Response::error('Match not found.', 404);
            return;
        }

        if ((int) $match['donor_id'] !== (int) $actor['id']) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', null, null, [
                'endpoint' => 'matches.withdraw',
                'reason' => 'not_match_owner',
            ]);
            Response::error('Forbidden.', 403);
            return;
        }

        if ((string) $match['request_status'] !== 'OPEN') {
            Response::error('This request is no longer active.', 409);
            return;
        }

        if ((string) $match['status'] !== 'RESPONDED') {
            Response::error('Only an active offer to help can be cancelled.', 409);
            return;
        }

        if ((new DonationReportRepository())->pendingExistsForMatch($matchId)) {
            Response::error('This offer cannot be cancelled while its donation report is awaiting review.', 409);
            return;
        }

        $repo->setStatus($matchId, 'NOTIFIED');
        AuditLogger::log((int) $actor['id'], 'match.response_withdrawn', 'blood_request', (string) $match['request_id'], [
            'match_id' => $matchId,
        ]);

        Response::success([
            'message' => 'Your offer to help has been cancelled.',
            'status' => 'NOTIFIED',
        ]);
    }
}
