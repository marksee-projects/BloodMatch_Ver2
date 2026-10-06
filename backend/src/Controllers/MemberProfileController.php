<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AuthThrottleRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\AuthService;
use BloodMatch\Services\ProfilePictureStorageService;
use BloodMatch\Services\ProfileViewPolicy;
use BloodMatch\Utils\Response;

final class MemberProfileController
{
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

        Response::success([
            'profile' => [
                'full_name' => (string) $target['full_name'],
                'profile_picture_url' => ProfilePictureStorageService::urlFor($target['profile_picture'], $targetId),
                'chapter_name' => $target['chapter_name'] !== null ? (string) $target['chapter_name'] : null,
                'role_label' => ucfirst((string) $target['role']),
                'member_since' => substr((string) $target['created_at'], 0, 10),
                'verification_status' => (string) $target['verification_status'],
                'blood_type' => $target['blood_type'] !== null ? (string) $target['blood_type'] : null,
                'email' => (string) $target['email'],
            ],
        ]);
    }
}
