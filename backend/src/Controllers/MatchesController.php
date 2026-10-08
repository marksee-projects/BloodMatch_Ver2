<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\MatchRepository;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\Exceptions\DonorIneligibleException;
use BloodMatch\Services\MatchResponseService;
use BloodMatch\Utils\Response;
use RuntimeException;

final class MatchesController
{
    public function respondToRequest(array $params): void
    {
        $actor = AuthMiddleware::requireAuthenticatedUser('requests.respond');
        $this->performResponse($actor, (int) $params['id'], 'request_id');
    }

    public function respond(array $params): void
    {
        $actor = AuthMiddleware::requireAuthenticatedUser('matches.respond');

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

        $this->performResponse($actor, (int) $match['request_id'], 'match_id');
    }

    private function performResponse(array $actor, int $requestId, string $source): void
    {
        try {
            $result = (new MatchResponseService())->respond($actor, $requestId, $source);
        } catch (DonorIneligibleException $e) {
            $eligibility = $e->eligibility();
            Response::json([
                'success' => false,
                'error' => [
                    'code' => (string) $eligibility['code'],
                    'message' => (string) $eligibility['reason'],
                    'eligible_again_at' => $eligibility['eligible_again_at'],
                ],
            ], 403);
            return;
        } catch (RuntimeException $e) {
            $status = $e->getCode();
            Response::error(
                $e->getMessage(),
                $status >= 400 && $status <= 499 ? $status : 500
            );
            return;
        }

        Response::success($result);
    }

    public function withdraw(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('matches.withdraw');
        try {
            $result = (new MatchResponseService())->withdraw($actor, (int) $params['matchId']);
        } catch (RuntimeException $e) {
            $status = $e->getCode();
            Response::error($e->getMessage(), $status >= 400 && $status <= 499 ? $status : 500);
            return;
        }
        Response::success($result);
    }
}
