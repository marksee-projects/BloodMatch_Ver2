<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\BloodCompatibilityService;
use BloodMatch\Services\DonorEligibilityService;
use BloodMatch\Services\RequestService;
use BloodMatch\Services\Exceptions\ValidationException;
use BloodMatch\Utils\Request;
use BloodMatch\Utils\Response;
use RuntimeException;

final class RequestsController
{
    public function create(): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.create');

        if (empty($actor['email_verified_at'])) {
            Response::error('Please verify your email before creating blood requests.', 403, [
                'code' => 'EMAIL_UNVERIFIED',
            ]);
            return;
        }

        $throttleRepo = new \BloodMatch\Repositories\AuthThrottleRepository();
        $throttleKey = 'mutation:req_create:' . (int) $actor['id'];
        $nowUtc = \BloodMatch\Services\AuthService::nowUtc();

        if ($throttleRepo->isLocked($throttleKey, $nowUtc)) {
            Response::error('Too many requests created. Please wait before creating more blood requests.', 429);
            return;
        }

        try {
            RequestService::assertCanCreate($actor);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 403);
            return;
        }

        try {
            $fields = RequestService::validatePayload(Request::json(), partial: false);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 400, $e->errors());
            return;
        }

        if (!$throttleRepo->hitAndCheckRateLimit($throttleKey, 10, 10, $nowUtc)) {
            Response::error('Too many requests created. Please wait before creating more blood requests.', 429);
            return;
        }

        $repo = new BloodRequestRepository();
        $id = $repo->create([
            'requester_id' => (int) $actor['id'],
            'request_chapter_id' => RequestService::chapterIdOf($actor),
            'required_blood_type' => $fields['required_blood_type'],
            'quantity_units' => $fields['quantity_units'],
            'facility_name' => $fields['facility_name'],
            'hospital_id' => $fields['hospital_id'] ?? null,
            'location_id' => $fields['location_id'],
            'latitude' => $fields['latitude'],
            'longitude' => $fields['longitude'],
            'urgency' => $fields['urgency'],
            'needed_datetime' => $fields['needed_datetime'],
            'review_status' => RequestService::reviewStatusFor($actor),
        ]);

        AuditLogger::log((int) $actor['id'], 'request.created', 'blood_request', (string) $id, [
            'review_status' => RequestService::reviewStatusFor($actor),
            'urgency' => $fields['urgency'],
        ]);

        $matchSummary = null;
        try {
            $matchSummary = (new \BloodMatch\Services\MatchService())
                ->generateForRequest($id, true, 'request_created', (int) $actor['id']);
        } catch (\Throwable $e) {
            error_log('[matches] auto-generation failed for request ' . $id . ': ' . $e->getMessage());
        }

        $fresh = $repo->findById($id);
        $requester = (new UserRepository())->findById((int) $actor['id']);
        Response::success([
            'request' => RequestService::publicView($fresh, $requester),
            'matching' => $matchSummary,
        ], 201);
    }

    public function mine(): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.mine');
        $rows = (new BloodRequestRepository())->listByRequester((int) $actor['id']);
        Response::success(['requests' => array_map(
            static fn (array $row): array => RequestService::publicView($row, null),
            $rows
        )]);
    }

    public function homeFeed(): void
    {
        $actor = AuthMiddleware::requireAuthenticatedUser('requests.home_feed');
        try {
            $filters = RequestService::validateHomeFeedQuery($_GET);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->errors());
            return;
        }

        if ((string) $actor['account_status'] !== 'active') {
            $this->emptyHomeFeed($actor, $filters, 'not_active', 'This account is not active.');
            return;
        }
        if ((string) $actor['verification_status'] !== 'verified') {
            $this->emptyHomeFeed(
                $actor,
                $filters,
                'not_verified',
                'Only verified accounts can view donor request suggestions.'
            );
            return;
        }
        if ((string) $actor['role'] !== 'member' && empty($actor['blood_type'])) {
            $this->emptyHomeFeed(
                $actor,
                $filters,
                'staff_no_blood_type',
                'Staff accounts need a blood type to view read-only request suggestions.'
            );
            return;
        }
        if (empty($actor['blood_type'])) {
            $this->emptyHomeFeed(
                $actor,
                $filters,
                'blood_type_missing',
                'Add your blood type to view compatible requests.'
            );
            return;
        }

        $recipientTypes = BloodCompatibilityService::getCompatibleRecipientTypesForDonor((string) $actor['blood_type']);
        $nowUtc = DonorEligibilityService::nowUtc();
        $window = DonorEligibilityService::evaluateWindows(
            $actor['last_verified_donation_at'] !== null
                ? (string) $actor['last_verified_donation_at']
                : null,
            $nowUtc
        );
        $result = (new BloodRequestRepository())->pageCompatibleOpenForViewer(
            (int) $actor['id'],
            $recipientTypes,
            $filters,
            (int) $filters['page'],
            (int) $filters['page_size'],
            $nowUtc
        );
        Response::success([
            'blood_type' => (string) $actor['blood_type'],
            'compatible_recipient_types' => $recipientTypes,
            'reason_code' => null,
            'reason_text' => null,
            'requests' => array_map(
                static fn (array $row): array => RequestService::homeFeedView(
                    $row,
                    DonorEligibilityService::evaluateForRequest($actor, $row, $nowUtc, $window)
                ),
                $result['rows']
            ),
            'page' => (int) $filters['page'],
            'page_size' => (int) $filters['page_size'],
            'has_more' => (bool) $result['has_more'],
            'total_matching' => (int) $result['total_matching'],
            'total_unfiltered' => (int) $result['total_unfiltered'],
        ]);
    }

    public function show(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.show');
        $row = $this->loadForAccess($actor, (int) $params['id'], 'requests.show');
        if ($row === null) {
            return;
        }
        $requester = (new UserRepository())->findById((int) $row['requester_id']);
        Response::success(['request' => RequestService::publicView($row, $requester)]);
    }

    public function update(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.update');
        $repo = new BloodRequestRepository();
        $id = (int) $params['id'];

        $row = $repo->findById($id);
        if ($row === null || (int) $row['requester_id'] !== (int) $actor['id']) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', 'blood_request', (string) $id, [
                'endpoint' => 'requests.update',
                'reason' => $row === null ? 'not_found' : 'not_owner',
            ]);
            Response::error('Request not found.', $row === null ? 404 : 403);
            return;
        }

        if ((string) $row['status'] !== 'OPEN') {
            Response::error('Only OPEN requests can be edited.', 409);
            return;
        }

        try {
            $fields = RequestService::validatePayload(Request::json(), partial: true);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 400, $e->errors());
            return;
        }

        if ($fields === []) {
            Response::error('No editable fields supplied.', 400);
            return;
        }

        $materialGroups = RequestService::materialChangedFields($row, $fields);
        $repo->updateFields($id, $fields);

        if ($materialGroups !== []) {
            AuditLogger::log((int) $actor['id'], 'request.material_change', 'blood_request', (string) $id, [
                'groups' => $materialGroups,
            ]);
            try {
                (new \BloodMatch\Services\MatchService())
                    ->generateForRequest($id, true, 'material_change', (int) $actor['id']);
            } catch (\Throwable $e) {
                error_log('[matches] regeneration failed for request ' . $id . ': ' . $e->getMessage());
            }
        } else {
            AuditLogger::log((int) $actor['id'], 'request.updated', 'blood_request', (string) $id, [
                'material' => false,
            ]);
        }

        $fresh = $repo->findById($id);
        Response::success(['request' => RequestService::publicView($fresh, $actor)]);
    }

    public function matches(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.matches');
        $requestId = (int) $params['id'];
        $repo = new BloodRequestRepository();
        $row = $repo->findById($requestId);

        if ($row === null) {
            Response::error('Request not found.', 404);
            return;
        }

        $isOwner = (int) $row['requester_id'] === (int) $actor['id'];
        $isAdmin = (string) $actor['role'] === 'admin';
        $isSameChapterOfficer = (string) $actor['role'] === 'officer'
            && $actor['chapter_id'] !== null
            && (int) $actor['chapter_id'] === (int) $row['request_chapter_id'];
        $context = $repo->findRequestContext($requestId, (int) $actor['id']);
        $nowUtc = DonorEligibilityService::nowUtc();
        $window = DonorEligibilityService::evaluateWindows(
            $actor['last_verified_donation_at'] !== null
                ? (string) $actor['last_verified_donation_at']
                : null,
            $nowUtc
        );
        $requestView = $context !== null
            ? RequestService::homeFeedView(
                $context,
                DonorEligibilityService::evaluateForRequest($actor, $context, $nowUtc, $window)
            )
            : null;

        if ($isOwner || $isAdmin || $isSameChapterOfficer) {
            Response::success([
                'viewer_mode' => 'requester',
                'request' => $requestView,
                'matches' => (new \BloodMatch\Services\MatchService())->privacySafeMatches($requestId, null, $actor),
            ]);
            return;
        }

        // Matched donors may view ONLY their own entry for this request.
        $ownMatch = (new \BloodMatch\Repositories\MatchRepository())
            ->findByRequestAndDonor($requestId, (int) $actor['id']);

        if ($ownMatch !== null) {
            Response::success([
                'viewer_mode' => 'donor',
                'request' => $requestView,
                'matches' => (new \BloodMatch\Services\MatchService())
                    ->privacySafeMatches($requestId, (int) $actor['id'], $actor),
            ]);
            return;
        }

        // Home is a compatibility browse view. A verified viewer may inspect the same
        // privacy-safe request information shown on its card even when the
        // account is not currently eligible to respond as a donor.
        if ((string) $actor['verification_status'] === 'verified' && !empty($actor['blood_type'])) {
            $recipientTypes = BloodCompatibilityService::getCompatibleRecipientTypesForDonor(
                (string) $actor['blood_type']
            );
            $browseContext = $repo->findCompatibleOpenForViewer(
                $requestId,
                (int) $actor['id'],
                $recipientTypes,
                $nowUtc
            );
        } else {
            $browseContext = null;
        }

        if ($browseContext !== null) {
            Response::success([
                'viewer_mode' => 'browser',
                'request' => RequestService::homeFeedView(
                    $browseContext,
                    DonorEligibilityService::evaluateForRequest($actor, $browseContext, $nowUtc, $window)
                ),
                'matches' => [],
            ]);
            return;
        }

        AuditLogger::log((int) $actor['id'], 'authz.denied', 'blood_request', (string) $requestId, [
            'endpoint' => 'requests.matches',
            'reason' => 'not_owner_officer_or_matched_donor',
        ]);
        Response::error('Forbidden.', 403);
    }

    private function emptyHomeFeed(
        array $actor,
        array $filters,
        string $reasonCode,
        string $reasonText
    ): void {
        Response::success([
            'blood_type' => $actor['blood_type'] ?? null,
            'compatible_recipient_types' => [],
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'requests' => [],
            'page' => (int) $filters['page'],
            'page_size' => (int) $filters['page_size'],
            'has_more' => false,
            'total_matching' => 0,
            'total_unfiltered' => 0,
        ]);
    }

    public function rematch(array $params): void
    {
        $actor = AuthMiddleware::requireRoles(['officer', 'admin'], 'requests.rematch');
        $repo = new BloodRequestRepository();
        $row = $repo->findById((int) $params['id']);

        if ($row === null) {
            Response::error('Request not found.', 404);
            return;
        }

        AuthMiddleware::requireChapterScope(
            $actor,
            $row['request_chapter_id'] !== null ? (int) $row['request_chapter_id'] : -1,
            'requests.rematch'
        );

        try {
            $summary = (new \BloodMatch\Services\MatchService())
                ->generateForRequest((int) $row['id'], false, 'manual_rematch', (int) $actor['id']);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            Response::error($e->getMessage(), $code >= 400 && $code <= 499 ? $code : 500);
            return;
        }

        Response::success(['matching' => $summary]);
    }

    public function cancel(array $params): void
    {
        $actor = AuthMiddleware::requireActiveUser('requests.cancel');
        $repo = new BloodRequestRepository();
        $id = (int) $params['id'];

        $row = $repo->findById($id);
        if ($row === null) {
            Response::error('Request not found.', 404);
            return;
        }

        $isOwner = (int) $row['requester_id'] === (int) $actor['id'];
        $isAdmin = (string) $actor['role'] === 'admin';
        $isChapterOfficer = (string) $actor['role'] === 'officer'
            && $actor['chapter_id'] !== null
            && (int) $actor['chapter_id'] === (int) $row['request_chapter_id'];

        if (!$isOwner && !$isAdmin && !$isChapterOfficer) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', 'blood_request', (string) $id, [
                'endpoint' => 'requests.cancel',
                'reason' => 'not_owner_or_out_of_chapter',
                'actor_role' => (string) $actor['role'],
            ]);
            Response::error('Forbidden.', 403);
            return;
        }

        if ((string) $row['status'] !== 'OPEN') {
            Response::error("Only OPEN requests can be cancelled (current: {$row['status']}).", 409);
            return;
        }

        $repo->setStatus($id, 'CANCELLED');
        AuditLogger::log((int) $actor['id'], 'request.cancelled', 'blood_request', (string) $id, [
            'via' => $isOwner ? 'owner' : ($isAdmin ? 'admin' : 'officer'),
        ]);

        \BloodMatch\Services\NotificationService::notify(
            (int) $row['requester_id'],
            'request.cancelled',
            'Blood request cancelled',
            sprintf('Your blood request #%d has been cancelled.', $id),
            [
                'related_type' => 'blood_request',
                'related_id' => $id,
                'dedup_key' => "request:{$id}:cancelled",
            ]
        );

        Response::success(['request' => RequestService::publicView($repo->findById($id), null)]);
    }

    private function loadForAccess(array $actor, int $id, string $endpoint): ?array
    {
        $repo = new BloodRequestRepository();
        $row = $repo->findById($id);

        if ($row === null) {
            Response::error('Request not found.', 404);
            return null;
        }

        $isOwner = (int) $row['requester_id'] === (int) $actor['id'];
        $isAdmin = (string) $actor['role'] === 'admin';
        $isSameChapterOfficer = (string) $actor['role'] === 'officer'
            && $actor['chapter_id'] !== null
            && (int) $actor['chapter_id'] === (int) $row['request_chapter_id'];

        if (!$isOwner && !$isAdmin && !$isSameChapterOfficer) {
            AuditLogger::log((int) $actor['id'], 'authz.denied', 'blood_request', (string) $id, [
                'endpoint' => $endpoint,
                'reason' => 'not_owner_or_out_of_chapter',
                'actor_role' => (string) $actor['role'],
            ]);
            Response::error('Forbidden.', 403);
            return null;
        }

        return $row;
    }
}
