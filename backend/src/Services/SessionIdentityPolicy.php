<?php
declare(strict_types=1);
namespace BloodMatch\Services;

final class SessionIdentityPolicy
{
    public static function fingerprint(array $user): string
    {
        return hash('sha256', (string) ($user['password_hash'] ?? ''));
    }

    public static function matches(array $user, array $session): bool
    {
        return isset($session['password_fingerprint'])
            && is_string($session['password_fingerprint'])
            && hash_equals(self::fingerprint($user), $session['password_fingerprint']);
    }
}
