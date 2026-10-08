<?php

declare(strict_types=1);

// Pure request policy/deadline checks. No environment, PDO, HTTP, or email.
require dirname(__DIR__) . '/backend/src/autoload.php';

use BloodMatch\Services\RequestLifecycleService;
use BloodMatch\Services\RequestService;
use BloodMatch\Services\WorkflowLockService;

$passed = 0;
function check(string $name, bool $condition): void {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL ' . $name); }
    $passed++; echo 'PASS ' . $name . PHP_EOL;
}
function blocked(callable $work, int $code): bool {
    try { $work(); } catch (RuntimeException $e) { return $e->getCode() === $code; }
    return false;
}
$now = '2026-10-09 00:00:00';
foreach (['2026-10-08 23:59:59' => true, '2026-10-09 00:00:00' => false, '2026-10-09 00:00:01' => false] as $needed => $past) {
    check('Deadline ' . $needed, RequestService::hasDeadlinePassed(['needed_datetime' => $needed], $now) === $past);
}
check('Missing deadline fails closed', RequestService::hasDeadlinePassed([], $now));
check('Invalid deadline fails closed', RequestService::hasDeadlinePassed(['needed_datetime' => 'invalid'], $now));
check('Invalid clock fails closed', RequestService::hasDeadlinePassed(['needed_datetime' => $now], 'invalid'));
$request = ['id' => 99, 'requester_id' => 1, 'request_chapter_id' => 2, 'status' => 'OPEN', 'needed_datetime' => $now];
WorkflowLockService::assertOpenBeforeDeadline($request, $now);
check('Exact deadline keeps existing equality boundary', true);
check('After waiting past deadline, active action denied', blocked(fn () => WorkflowLockService::assertOpenBeforeDeadline($request, '2026-10-09 00:00:01'), 409));
foreach (['CANCELLED', 'EXPIRED', 'FULFILLED'] as $status) {
    check($status . ' blocks mutation regardless of future deadline', blocked(fn () => WorkflowLockService::assertOpenBeforeDeadline(array_replace($request, ['status' => $status]), $now), 409));
}
$owner = ['id' => 1, 'role' => 'member', 'chapter_id' => 3, 'account_status' => 'active'];
$officer = ['id' => 2, 'role' => 'officer', 'chapter_id' => 2, 'account_status' => 'active'];
$admin = ['id' => 3, 'role' => 'admin', 'chapter_id' => null, 'account_status' => 'active'];
RequestLifecycleService::assertCanEdit($owner, $request);
check('Active owner retains edit permission', true);
check('Same-chapter officer cannot edit another owner request', blocked(fn () => RequestLifecycleService::assertCanEdit($officer, $request), 403));
check('Admin does not gain edit permission', blocked(fn () => RequestLifecycleService::assertCanEdit($admin, $request), 403));
check('Inactive owner edit blocked', blocked(fn () => RequestLifecycleService::assertCanEdit(array_replace($owner, ['account_status' => 'deactivated']), $request), 403));
check('Owner cancellation retains priority', RequestLifecycleService::cancellationMode($owner, $request) === 'owner');
check('Same-chapter officer retains cancellation', RequestLifecycleService::cancellationMode($officer, $request) === 'officer');
check('Admin retains global cancellation', RequestLifecycleService::cancellationMode($admin, $request) === 'admin');
check('Other member cancellation denied', blocked(fn () => RequestLifecycleService::cancellationMode(array_replace($officer, ['role' => 'member']), $request), 403));
check('Cross-chapter officer cancellation denied', blocked(fn () => RequestLifecycleService::cancellationMode(array_replace($officer, ['chapter_id' => 1]), $request), 403));
check('Unassigned officer cancellation denied', blocked(fn () => RequestLifecycleService::cancellationMode(array_replace($officer, ['chapter_id' => null]), $request), 403));
check('Inactive admin cancellation denied', blocked(fn () => RequestLifecycleService::cancellationMode(array_replace($admin, ['account_status' => 'deactivated']), $request), 403));
echo "{$passed} offline lifecycle checks passed. SQL locking/closure/draining not executed." . PHP_EOL;
