<?php

declare(strict_types=1);

/**
 * Sends ONE test email through the production Mailer (backend/src/Services/Mailer.php)
 * and prints success or the exact error Mailer reported.
 *
 * Usage:
 *   C:\xampp\php\php.exe tests\helpers\send_test_email.php recipient@example.com
 *
 * Exit codes: 0 = sent, 1 = send failed, 2 = bad usage / preflight failure.
 * Never prints MAIL_PASS; MAIL_USER is masked.
 */

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Env;
use BloodMatch\Services\Mailer;

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function maskEmail(string $email): string
{
    if (!str_contains($email, '@')) {
        return $email === '' ? '<empty>' : '<set, not an email>';
    }
    [$local, $domain] = explode('@', $email, 2);
    return substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
}

$to = $argv[1] ?? '';
if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    out('Usage: php tests/helpers/send_test_email.php recipient@example.com');
    exit(2);
}

Env::load(BASE_PATH . '/.env');

$host = (string) Env::get('MAIL_HOST', '');
$port = (int) Env::get('MAIL_PORT', '25');
$user = (string) Env::get('MAIL_USER', '');
$pass = (string) Env::get('MAIL_PASS', '');
$from = (string) Env::get('MAIL_FROM', 'bloodmatch@localhost');

out('--- SMTP config (from .env) ---');
out('MAIL_HOST : ' . ($host === '' ? '<empty>' : $host));
out('MAIL_PORT : ' . $port);
out('MAIL_USER : ' . maskEmail($user));
out('MAIL_PASS : ' . ($pass === '' ? '<empty>' : '<set>'));
out('MAIL_FROM : ' . maskEmail($from));
out('openssl   : ' . (extension_loaded('openssl') ? 'loaded' : 'MISSING (STARTTLS impossible)'));

$warnings = [];
if (strcasecmp($host, 'smtp.gmail.com') === 0) {
    if ($port !== 587) {
        $warnings[] = 'Gmail STARTTLS expects MAIL_PORT=587.';
    }
    if ($pass !== '' && strlen(str_replace(' ', '', $pass)) !== 16) {
        $warnings[] = 'MAIL_PASS is not 16 characters; Gmail App Passwords are 16 characters.';
    }
    if (str_contains($pass, ' ')) {
        $warnings[] = 'MAIL_PASS contains spaces; remove them.';
    }
    if ($user !== '' && strcasecmp($user, $from) !== 0) {
        $warnings[] = 'MAIL_FROM differs from MAIL_USER; Gmail rewrites From unless it is a verified alias.';
    }
}
foreach ($warnings as $w) {
    out('WARN      : ' . $w);
}

if ($host === '') {
    out('RESULT    : FAIL - MAIL_HOST is empty, Mailer skips delivery.');
    exit(2);
}
if (!extension_loaded('openssl')) {
    out('RESULT    : FAIL - enable extension=openssl in php.ini.');
    exit(2);
}

// Preflight: fail fast (10s) instead of PHPMailer's 300s default timeout if the port is blocked.
$errno = 0;
$errstr = '';
$sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
if ($sock === false) {
    out("RESULT    : FAIL - cannot open TCP {$host}:{$port} ({$errno}: {$errstr}). Check firewall/antivirus/ISP blocking.");
    exit(2);
}
fclose($sock);
out("tcp       : {$host}:{$port} reachable");

// Mailer swallows exceptions and reports them via error_log(); capture that output.
$logFile = tempnam(sys_get_temp_dir(), 'bm_mail_');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$subject = '[BloodMatch] SMTP test ' . gmdate('Y-m-d H:i:s') . ' UTC';
$body = '<p>This is a BloodMatch SMTP test message. If you received it, email delivery works.</p>';

out('--- Sending via BloodMatch\\Services\\Mailer::send() ---');
$started = microtime(true);
$ok = Mailer::send($to, $subject, $body);
$elapsed = round(microtime(true) - $started, 2);

$logged = is_file($logFile) ? (string) file_get_contents($logFile) : '';
@unlink($logFile);

$mailerLines = array_values(array_filter(
    preg_split('/\R/', $logged) ?: [],
    static fn (string $l): bool => str_contains($l, '[mailer]')
));

if ($ok) {
    out("RESULT    : SUCCESS - sent to {$to} in {$elapsed}s (subject: {$subject})");
    exit(0);
}

out("RESULT    : FAIL after {$elapsed}s");
if ($mailerLines === []) {
    out('ERROR     : (Mailer returned false without logging a message)');
} else {
    foreach ($mailerLines as $l) {
        // Strip the error_log timestamp prefix, keep the exact Mailer message.
        out('ERROR     : ' . preg_replace('/^\[[^\]]*\]\s*/', '', $l));
    }
}

$joined = implode(' ', $mailerLines);
if (stripos($joined, 'authenticate') !== false) {
    out('HINT      : Gmail rejected the login. Use a 16-char App Password (2-Step Verification must be on), not your normal password.');
} elseif (stripos($joined, 'connect') !== false) {
    out('HINT      : Connection/TLS failed. Confirm smtp.gmail.com:587 is not blocked and openssl is enabled.');
}
exit(1);
