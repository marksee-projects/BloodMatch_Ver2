<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Services\AuditLogger;
use BloodMatch\Services\AuthService;
use BloodMatch\Services\RequestLifecycleService;

Env::load(BASE_PATH . '/.env');

try {
    Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, '[expiry] cannot connect: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$nowUtc = AuthService::nowUtc();
$expiredCount = 0;
$lifecycle = new RequestLifecycleService();
try {
    foreach ($lifecycle->expireDueRequests($nowUtc, 500) as $row) { $expiredCount++; }
} catch (Throwable $e) {
    if ($expiredCount > 0) {
        AuditLogger::log(null, 'request.expired_batch', 'blood_request', null,
            ['count' => $expiredCount, 'cutoff_utc' => $nowUtc, 'incomplete' => true]);
    }
    error_log('[expiry] batch failed: ' . $e->getMessage());
    fwrite(STDERR, "[expiry] stopped after {$expiredCount} committed expirations; rerun to retry the remaining due requests.\n");
    exit(1);
}

if ($expiredCount > 0) {
    AuditLogger::log(null, 'request.expired_batch', 'blood_request', null, [
        'count' => $expiredCount,
        'cutoff_utc' => $nowUtc,
    ]);
}

echo "[expiry] expired {$expiredCount} request(s) as of {$nowUtc} UTC" . PHP_EOL;
