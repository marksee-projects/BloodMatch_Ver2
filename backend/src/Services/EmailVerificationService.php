<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Repositories\UserRepository;
use Throwable;

final class EmailVerificationService
{
    public const CODE_TTL_SECONDS = 600; // 10 minutes

    public static function sendVerificationCode(int $userId, string $email, bool $isResend = false): void
    {
        $code = sprintf('%06d', random_int(0, 999999));
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS);

        (new UserRepository())->setEmailCode($userId, $codeHash, $expiresAt);

        if (Mailer::isConfigured()) {
            try {
                $subject = $isResend
                    ? '[BloodMatch] Your new verification code'
                    : '[BloodMatch] Verify your email address';
                $template = EmailTemplate::otp($code, self::CODE_TTL_SECONDS, $isResend);
                Mailer::send($email, $subject, $template['html'], EmailAttemptBudget::ACCOUNT, $template['text']);
            } catch (Throwable $e) {
                error_log('[mailer] verification email unavailable');
            }
        }
    }
}
