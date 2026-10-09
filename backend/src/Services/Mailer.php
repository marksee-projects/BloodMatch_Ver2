<?php

declare(strict_types=1);

namespace BloodMatch\Services;

use BloodMatch\Config\AppConfig;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function isConfigured(): bool
    {
        return \BloodMatch\Config\Env::get('MAIL_HOST', '') !== '';
    }

    public static function send(string $toEmail, string $subject, string $htmlBody, string $mailClass = EmailAttemptBudget::NORMAL): bool
    {
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) { return false; }
        $domain = strtolower((string) substr(strrchr($toEmail, '@') ?: '', 1));
        if (preg_match('/(^|\.)(local|test|invalid)$/', $domain) === 1) {
            error_log('[mailer] skipped: reserved domain (' . $domain . ')');
            return false;
        }

        if (!self::isConfigured()) {
            error_log('[mailer] skipped: SMTP not configured');
            return false;
        }

        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            require_once \BloodMatch\Config\AppConfig::basePath() . '/backend/lib/phpmailer/Exception.php';
            require_once \BloodMatch\Config\AppConfig::basePath() . '/backend/lib/phpmailer/SMTP.php';
            require_once \BloodMatch\Config\AppConfig::basePath() . '/backend/lib/phpmailer/PHPMailer.php';
        }

        $mail = new PHPMailer(true);
        try {
            $host = (string) \BloodMatch\Config\Env::get('MAIL_HOST');
            if ($host === '' || $host === 'mock') {
                return EmailAttemptBudget::reserve($mailClass)['allowed'];
            }
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) (\BloodMatch\Config\Env::get('MAIL_PORT', '25'));
            $user = \BloodMatch\Config\Env::get('MAIL_USER', '');
            $pass = \BloodMatch\Config\Env::get('MAIL_PASS', '');
            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
            } else {
                $mail->SMTPAuth = false;
            }
            $mail->CharSet = 'UTF-8';
            $from = \BloodMatch\Config\Env::get('MAIL_FROM', 'bloodmatch@localhost');
            $mail->setFrom($from, 'BloodMatch');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            if (!EmailAttemptBudget::reserve($mailClass)['allowed']) {
                error_log('[mailer] deferred: shared attempt budget');
                return false;
            }
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[mailer] send unavailable: transport or accounting failure');
            return false;
        }
    }
}
