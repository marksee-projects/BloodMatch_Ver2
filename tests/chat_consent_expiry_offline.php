<?php
declare(strict_types=1);
// Pure boundary checks: no environment, database connection, files or network.
require dirname(__DIR__) . '/backend/src/autoload.php';
use BloodMatch\Services\ChatConsentService;

$now = strtotime('2026-10-15 12:00:00 UTC');
$cases = [
    ['pending', '2026-10-08 12:00:01', false, 'Pending request remains valid until seven days'],
    ['pending', '2026-10-08 12:00:00', true, 'Pending request expires at exactly seven days'],
    ['pending', '2026-10-07 12:00:00', true, 'Expired request is denied without worker execution'],
    ['accepted', '2026-10-01 12:00:00', false, 'Accepted consent does not expire as pending'],
    ['declined', '2026-10-01 12:00:00', false, 'Declined request retains its separate retry rule'],
    ['pending', 'invalid timestamp', true, 'Invalid timestamp fails closed'],
    ['pending', '', true, 'Missing timestamp fails closed'],
];
foreach ($cases as [$status, $created, $expected, $label]) {
    if (ChatConsentService::pendingExpired(['status'=>$status, 'created_at'=>$created], $now) !== $expected) {
        throw new RuntimeException('FAIL ' . $label);
    }
    echo 'PASS ' . $label . PHP_EOL;
}
echo count($cases) . ' consent-expiry checks passed.' . PHP_EOL;
