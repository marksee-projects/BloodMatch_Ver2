<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AuthThrottleRepository;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\AuthService;
use BloodMatch\Services\ProfilePictureStorageService;
use BloodMatch\Services\ProfileViewPolicy;
use BloodMatch\Services\RequestService;
use BloodMatch\Utils\Response;

final class MemberProfileController
{
    public function presence(array $params): void
    {
        $viewer = AuthMiddleware::requireActiveUser('profile.presence');
        $targetId = (int) ($params['id'] ?? 0);
        $target = (new UserRepository())->findProfileDisplayById($targetId);
        if ($target === null || (new ProfileViewPolicy())->relation($viewer, $target) === null) {
            Response::error("This profile isn't available.", 404);
            return;
        }
        Response::success(['is_online' => \BloodMatch\Services\PresenceService::isOnline($targetId)]);
    }

    public function show(array $params): void
    {
        $viewer = AuthMiddleware::requireActiveUser('profile.member');
        $targetId = (int) ($params['id'] ?? 0);
        $target = (new UserRepository())->findProfileDisplayById($targetId);
        $relation = $target === null ? null : (new ProfileViewPolicy())->relation($viewer, $target);

        if ($target === null || $relation === null) {
            Response::error("This profile isn't available.", 404);
            return;
        }

        $throttleKey = 'read:profile_view:' . (int) $viewer['id'];
        if (!(new AuthThrottleRepository())->hitAndCheckRateLimit($throttleKey, 61, 60, AuthService::nowUtc())) {
            Response::error('Too many profile views. Please try again later.', 429);
            return;
        }

        if ((int) $viewer['id'] !== $targetId) {
            AuditLogger::log((int) $viewer['id'], 'profile.viewed', 'user', (string) $targetId);
        }

        try {
            iterator_to_array((new \BloodMatch\Services\RequestLifecycleService())->expireDueRequests(AuthService::nowUtc()));
        } catch (\Throwable) {
        }

        $targetRequests = (new BloodRequestRepository())->listByRequester($targetId);
        $publicRequests = array_map(
            static fn (array $row): array => RequestService::publicView($row, $target),
            array_values(array_filter($targetRequests, static fn (array $row): bool => in_array($row['status'], ['OPEN', 'FULFILLED'], true)))
        );

        $pair = [min((int) $viewer['id'], $targetId), max((int) $viewer['id'], $targetId)];
        $crStmt = \BloodMatch\Config\Database::pdo()->prepare(
            "SELECT 1 FROM chat_requests WHERE user_low = ? AND user_high = ? AND status = 'accepted' LIMIT 1"
        );
        $crStmt->execute($pair);
        $isConnected = (bool) $crStmt->fetchColumn();

        if (!$isConnected) {
            $cpStmt = \BloodMatch\Config\Database::pdo()->prepare(
                "SELECT 1 FROM conversation_participants cp1
                 JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                 WHERE cp1.user_id = ? AND cp2.user_id = ? LIMIT 1"
            );
            $cpStmt->execute([(int) $viewer['id'], $targetId]);
            $isConnected = (bool) $cpStmt->fetchColumn();
        }

        Response::success([
            'profile' => [
                'id' => $targetId,
                'is_online' => \BloodMatch\Services\PresenceService::isOnline($targetId),
                'full_name' => (string) $target['full_name'],
                'profile_picture_url' => ProfilePictureStorageService::urlFor($target['profile_picture'], $targetId),
                'chapter_name' => $target['chapter_name'] !== null ? (string) $target['chapter_name'] : null,
                'municipality_name' => $target['municipality_name'] !== null ? (string) $target['municipality_name'] : null,
                'role_label' => $target['role'] === 'admin' ? 'Admin' : ucfirst((string) $target['role']),
                'member_since' => substr((string) $target['created_at'], 0, 10),
                'verification_status' => (string) $target['verification_status'],
                'blood_type' => $target['blood_type'] !== null ? (string) $target['blood_type'] : null,
                'is_connected' => $isConnected,
            ],
            'requests' => $publicRequests,
        ]);
    }
}
