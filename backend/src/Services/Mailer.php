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

    /** Shared reservation gateway. Test workers supply a transport with no sockets.
     * A deferred result consumes no transport attempt; failures after reservation do.
     */
    public static function attemptWithTransport(string $mailClass, callable $transport, ?int $donorId = null): array
    {
        try { $reservation = EmailAttemptBudget::reserve($mailClass, null, $donorId); }
        catch (\Throwable $e) { return ['status' => 'deferred', 'code' => 'accounting_unavailable']; }
        if (!$reservation['allowed']) { return ['status' => 'deferred', 'code' => $reservation['reason']==='emergency_recipient_limit' ? $reservation['reason'] : 'shared_budget']; }
        try { $outcome = self::transportOutcome($transport()); }
        catch (\Throwable $e) { $outcome = 'uncertain'; }
        if ($donorId !== null) {
            try { EmailAttemptBudget::recordDonorOutcome((int)$reservation['attempt_id'],$outcome==='rejected'?'released':$outcome); }
            catch (\Throwable $e) { /* Outcome-write failure leaves outstanding capacity counted. */ }
        }
        return ['status' => $outcome==='accepted' ? 'sent' : 'failed', 'code' => $outcome==='accepted' ? null : 'transport_failure', 'outcome'=>$outcome];
    }

    public static function transportOutcome(mixed $result): string
    {
        if ($result === true) { return 'accepted'; }
        if (is_array($result) && in_array($result['outcome'] ?? null,['accepted','rejected','uncertain'],true)) { return $result['outcome']; }
        return 'uncertain';
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
                return self::attemptWithTransport($mailClass, static fn () => true)['status'] === 'sent';
            }
            $mail->isSMTP();
            $mail->Host = $host;
            $port = (int) (\BloodMatch\Config\Env::get('MAIL_PORT', '25'));
            $mail->Port = $port;
            if ($port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
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
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
            $mail->send();
            error_log('[mailer] successfully sent email to ' . $toEmail . ' (subject: ' . $subject . ')');
            return true;
        } catch (\Throwable $e) {
            error_log('[mailer] send failed to ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }
}
