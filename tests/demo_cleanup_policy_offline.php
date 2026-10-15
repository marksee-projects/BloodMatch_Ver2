<?php
declare(strict_types=1);
// No environment, database, backup or account changes.
require dirname(__DIR__) . '/backend/src/autoload.php';
use BloodMatch\Services\DemoCleanupPolicy;

DemoCleanupPolicy::assertLegacySchema(['users','conversations','messages']);
echo 'PASS legacy schema remains eligible for reviewed cleanup' . PHP_EOL;
foreach (['pending_registrations','chat_requests','chat_reports','chat_report_evidence','account_closures'] as $table) {
    $denied=false;
    try { DemoCleanupPolicy::assertLegacySchema(['users',$table]); }
    catch (LogicException) { $denied=true; }
    if (!$denied) throw new RuntimeException('FAIL privacy table must block legacy cleanup: ' . $table);
    echo 'PASS cleanup blocked when ' . $table . ' exists, even if empty' . PHP_EOL;
}
echo '6 demo-cleanup policy checks passed.' . PHP_EOL;
