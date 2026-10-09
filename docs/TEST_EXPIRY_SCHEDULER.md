# Windows test expiry schedule

Prepared for the approved **bloodmatch_test, 127.0.0.1:3306** target only. No migration. No agent installation, task start or database execution. Run commands from the repository root in Windows PowerShell as the Windows account that will remain signed in.

Prerequisites: Windows Task Scheduler/ScheduledTasks module, PHP CLI with pdo_mysql at C:\xampp\php\php.exe (adjust both commands' -PhpPath if different), MySQL running on 3306, existing disposable bloodmatch_test with current schema/reference data. The existing .env supplies DB_USER/DB_PASS; that account needs the same permissions as the existing expiry CLI on the test DB. Task arguments and logs contain no credentials. Do not paste credentials or .env contents into test reports. Keep the checkout available and the machine awake and signed in during verification.

The dedicated runner overrides host/name/port before loading .env, then checks SELECT DATABASE() and @@port on the same cached PDO used for expiry. A connector fallback to 3307 is refused before expiry writes. MAIL_HOST is disabled in this process; the existing expiry lifecycle emits in-app alerts only. The generic application expiry command and .env are unchanged.

## 1. Readiness and optional existing regression

The verification command below reads DB metadata/due count only; it does not expire anything. It appends a sanitized verification record. Stop on a nonzero exit code.

```powershell
& 'C:\xampp\php\php.exe' database\run_test_expiry.php --verify
if ($LASTEXITCODE -ne 0) { throw 'Test target verification failed; do not install the task.' }
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Preview -PhpPath 'C:\xampp\php\php.exe'
```

Expected: status verified, database bloodmatch_test, port 3306, exit_code 0. Preview reports PT1M, Interactive, Limited, IgnoreNew + file lock; it installs/starts nothing. If rerunning the earlier lifecycle regression, do so **before** installing the timer, to keep scheduled expiry from racing fixtures:

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite workflow_atomicity.ps1
```

Expected: zero failures. Existing tests cover overdue closure, pending-report retention/blocked confirmation and preservation of completed donations; this schedule does not change those rules. This regression is user-run only and is not required again merely to prove the timer fires.

## 2. Install (user action; starts future automatic triggers)

```powershell
$testScheduleStarted = (Get-Date).ToUniversalTime()
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Setup -PhpPath 'C:\xampp\php\php.exe'
if ($LASTEXITCODE -ne 0) { throw 'Task setup failed.' }
```

Registers BloodMatch-Test-Expiry for the current user, Limited/Interactive (signed-in only), starting approximately one minute later and repeating every minute indefinitely. It does not issue Start-ScheduledTask. An existing task is never overwritten. Only the matching checkout/owner/action task can be inspected or removed by the helper. If registration is denied by local Windows policy, report the error; do not change the account to SYSTEM or store a password.

Task Scheduler IgnoreNew prevents overlapping scheduled instances; the runner also takes a nonblocking checkout file lock, covering manual and scheduled executions of this dedicated runner. Direct calls to the generic run_expiry.php are not covered by that file lock; do not run another expiry worker during the test. Existing request-level transactions remain in use. A failed run retries remaining work at the next tick.

## 3. Verify actual automatic runs

Stay signed in and awake for at least two minutes, then run in the same shell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Verify -PhpPath 'C:\xampp\php\php.exe'
$testRuns = @(Get-Content -LiteralPath logs\test-expiry.jsonl | ForEach-Object { $_ | ConvertFrom-Json } | Where-Object {
    $_.invocation -eq 'scheduled' -and ([datetime]$_.utc).ToUniversalTime() -ge $testScheduleStarted
})
$testRuns | Select-Object utc, database, port, status, expired_count, elapsed_ms, exit_code
if (@($testRuns | Where-Object { $_.status -eq 'completed' -and $_.exit_code -eq 0 }).Count -lt 2) {
    throw 'Need at least two completed scheduled runs after setup.'
}
if (@($testRuns | Where-Object { $_.exit_code -ne 0 }).Count -gt 0) { throw 'A scheduled run failed; report its safe result code.' }
& 'C:\xampp\php\php.exe' database\run_test_expiry.php --verify
if ($LASTEXITCODE -ne 0) { throw 'Final target verification failed.' }
```

Expected metadata: Interval PT1M, Logon Interactive, RunLevel Limited, Overlap IgnoreNew, recent LastRunTime and LastTaskResult 0. Require at least two new scheduled completed records approximately a minute apart, every record targeting bloodmatch_test:3306. Each zero-count completed record proves the timer ran, **not** that a due fixture was closed. Final verify should show due_count 0 if no new overdue rows are being added. A task that has never run is not a pass, even if its initial LastTaskResult is 0.

For a data-effect check, create a disposable request through the existing test application/fixtures, allow its deadline to pass, record its ID, and observe its stored state/history after the next scheduled tick. Expected: EXPIRED, unfinished offers CLOSED; completed donations preserved; pending reports still PENDING and visible to authorized rejection, confirmation blocked, no new donor cooldown. Do not change system time or modify application data. The previous workflow_atomicity assertions cover these effects deterministically; timer verification is recorded separately.

Logs: logs/test-expiry.jsonl contains only UTC, fixed target, invocation/status, counts, elapsed_ms and exit_code. logs/test-expiry.lock is a persistent lock file; its existence alone does not mean a worker is running. No SQL, exception messages, recipients, credentials or environment dumps. Codes: 0 completed/verified/overlap_skipped; 10 target refused; 11 connection failure; 12 expiry failure (expired_count includes committed work); 13 storage/log failure; 14 wrapper/runtime setup failure (Task Scheduler result, possibly no log). Raw PHP output is discarded by the scheduled wrapper. Log storage failing after expiry does not roll back already committed closures; subsequent runs are idempotent.

## 4. Remove after testing

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Remove -PhpPath 'C:\xampp\php\php.exe'
Get-ScheduledTask -TaskName 'BloodMatch-Test-Expiry' -TaskPath '\' -ErrorAction SilentlyContinue | Select-Object TaskName, State
```

Expected: removed/absent message and no task row. Removal disables future ticks first. If an instance is running, it leaves the task disabled and asks you to repeat Remove after that run completes; it does not kill an active transaction. Logs remain for review. No data cleanup/reset is performed.

Send the safe metadata/result table and pass/fail summary. Application database/deployed scheduling remains unapproved; ask for a separate target/runtime approval after successful test verification.

Microsoft references: [IgnoreNew settings](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtasksettingsset?view=winserver2012r2-ps), [Interactive principal](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtaskprincipal?view=windowsserver2025-ps), [repeating trigger](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtasktrigger?view=windowsserver2025-ps).
