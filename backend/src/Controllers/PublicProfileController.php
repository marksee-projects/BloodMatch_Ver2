<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Repositories\UserRepository;
use BloodMatch\Utils\Response;
use BloodMatch\Middleware\AuthMiddleware;

final class PublicProfileController
{
    public function show(array $params): void
    {
        AuthMiddleware::requireActiveUser('profile.public');
        
        $id = (int) $params['id'];
        $user = (new UserRepository())->findById($id);

        if ($user === null) {
            Response::error('User not found.', 404);
            return;
        }
        $chapterName = null;
        if ($user['chapter_id'] !== null) {
            $stmt = \BloodMatch\Config\Database::pdo()->prepare('SELECT name FROM chapters WHERE id = ?');
            $stmt->execute([(int) $user['chapter_id']]);
            $cName = $stmt->fetchColumn();
            if ($cName !== false) {
                $chapterName = $cName;
            }
        }

        Response::success([
            'profile' => [
                'id' => (int) $user['id'],
                'full_name' => (string) $user['full_name'],
                'email' => (string) $user['email'],
                'role' => (string) $user['role'],
                'chapter_id' => $user['chapter_id'] !== null ? (int) $user['chapter_id'] : null,
                'chapter_name' => $chapterName,
                'blood_type' => $user['blood_type'],
                'verification_status' => (string) $user['verification_status'],
                'donor_availability' => $user['donor_availability'],
                'profile_picture_url' => \BloodMatch\Services\ProfilePictureStorageService::urlFor($user['profile_picture'] ?? null),
            ]
        ]);
    }
}
