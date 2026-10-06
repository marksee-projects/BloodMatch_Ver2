<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Repositories\MatchRepository;

final class ProfileViewPolicy
{
    public function relation(array $viewer, array $target): ?string
    {
        $viewerId = (int) $viewer['id'];
        $targetId = (int) $target['id'];

        if ($viewerId === $targetId) {
            return 'self';
        }

        if ((string) $target['role'] !== 'member' || (string) $target['account_status'] !== 'active') {
            return null;
        }

        if ((string) $viewer['role'] === 'admin') {
            return 'admin';
        }

        if ((string) $viewer['role'] === 'officer'
            && $viewer['chapter_id'] !== null
            && $target['chapter_id'] !== null
            && (int) $viewer['chapter_id'] === (int) $target['chapter_id']) {
            return 'chapter_officer';
        }

        return (new MatchRepository())->profileViewRelation($viewerId, $targetId);
    }
}
