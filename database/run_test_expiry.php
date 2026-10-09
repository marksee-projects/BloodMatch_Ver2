<?php

declare(strict_types=1);

// BM-TEST-EXPIRY: deliberately has no application/production target option.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0'); // Never persist SQL, credentials or raw exception text.
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\AuthService;
use BloodMatch\Services\RequestLifecycleService;

$started = microtime(true);
$verify = in_array('--verify', $argv, true);
$record = [
    'utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'database' => 'bloodmatch_test', 'port' => 3306,
    'invocation' => $verify ? 'verify' : (in_array('--scheduled', $argv, true) ? 'scheduled' : 'manual'),
    'status' => 'storage_failed', 'expired_count' => 0, 'exit_code' => 13,
];
$lock = null;
$logFile = BASE_PATH . '/logs/test-expiry.jsonl';
try {
    if (!is_dir(dirname($logFile)) && !@mkdir(dirname($logFile), 0700, true) && !is_dir(dirname($logFile))) {
        throw new RuntimeException('storage');
    }
    // Fail before database writes if the result log cannot be opened.
    $probe = @fopen($logFile, 'ab');
    if ($probe === false) { throw new RuntimeException('storage'); }
    fclose($probe);
    $lock = @fopen(BASE_PATH . '/logs/test-expiry.lock', 'c');
    if ($lock === false) { throw new RuntimeException('storage'); }
    $wouldBlock = 0;
    if (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
        if (!$wouldBlock) { throw new RuntimeException('storage'); }
        $record['status'] = 'overlap_skipped'; $record['exit_code'] = 0;
    } else {
        // Existing process overrides win over .env; set both Env lookup sources.
        foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_NAME' => 'bloodmatch_test',
            'MAIL_HOST' => '', 'SMTP_HOST' => ''] as $key => $value) {
            putenv($key . '=' . $value); $_ENV[$key] = $value;
        }
        Env::load(BASE_PATH . '/.env');
        $record['status'] = 'connection_failed'; $record['exit_code'] = 11;
        $pdo = Database::pdo();
        $record['status'] = 'target_refused'; $record['exit_code'] = 10;
        $actual = $pdo->query('SELECT DATABASE() AS db, @@port AS port')->fetch();
        // Same cached PDO is subsequently used by the lifecycle service.
        if ($actual['db'] !== 'bloodmatch_test' || (int) $actual['port'] !== 3306) {
            throw new RuntimeException('target');
        }
        $cutoff = AuthService::nowUtc();
        if ($verify) {
            $query = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE status = 'OPEN' AND needed_datetime < ?");
            $query->execute([$cutoff]);
            $record['due_count'] = (int) $query->fetchColumn();
            $record['status'] = 'verified'; $record['exit_code'] = 0;
        } else {
            $record['status'] = 'expiry_failed'; $record['exit_code'] = 12;
            $complete = false;
            try {
                foreach ((new RequestLifecycleService())->expireDueRequests($cutoff, 500) as $row) {
                    $record['expired_count']++;
                }
                $complete = true;
            } finally {
                if ($record['expired_count'] > 0) {
                    AuditLogger::log(null, 'request.expired_batch', 'blood_request', null, [
                        'count' => $record['expired_count'], 'cutoff_utc' => $cutoff,
                        'incomplete' => !$complete,
                    ]);
                }
            }
            $record['status'] = 'completed'; $record['exit_code'] = 0;
        }
    }
} catch (Throwable $e) {
    // Only the stage code is emitted. Exception content is intentionally discarded.
}
$record['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
$json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (@file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
    $record['status'] = 'log_failed'; $record['exit_code'] = 13;
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
echo $json . PHP_EOL;
if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
exit($record['exit_code']);
