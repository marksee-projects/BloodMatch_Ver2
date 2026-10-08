<?php

declare(strict_types=1);

// Pure validation/migration review checks. No environment/DB/HTTP/email operations.
require dirname(__DIR__) . '/backend/src/autoload.php';

use BloodMatch\Services\DonationService;
use BloodMatch\Services\Exceptions\ValidationException;

$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/rejection_reason_cases.json'), true, 512, JSON_THROW_ON_ERROR);
$passed = 0;
foreach ($cases as $case) {
    $input = array_key_exists('input', $case) ? $case['input'] : str_repeat($case['repeat'], $case['count']);
    try {
        $reason = DonationService::normalizeRejectionReason($input);
        if (!$case['valid'] || $reason !== ($case['expected'] ?? $input)) {
            throw new RuntimeException('FAIL ' . $case['label']);
        }
    } catch (ValidationException $e) {
        if ($case['valid'] || $e->getCode() !== 400 || !isset($e->errors()['rejection_reason'])) {
            throw new RuntimeException('FAIL ' . $case['label']);
        }
    }
    $passed++; echo 'PASS ' . $case['label'] . PHP_EOL;
}
try {
    DonationService::normalizeRejectionReason("\xFF");
    throw new RuntimeException('FAIL invalid UTF-8');
} catch (ValidationException $e) { $passed++; echo 'PASS invalid UTF-8' . PHP_EOL; }
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/020_donation_report_rejection_reason.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
if (!preg_match('/^\s*ALTER TABLE donation_reports\s+ADD COLUMN rejection_reason VARCHAR\(500\) NULL AFTER report_note;\s*$/i', $sql)) {
    throw new RuntimeException('FAIL migration must contain only the approved additive ALTER.');
}
$passed++; echo 'PASS migration contains only approved additive ALTER; no backfill/status writes' . PHP_EOL;
echo "{$passed} offline rejection validation/migration checks passed. Migration not executed." . PHP_EOL;
