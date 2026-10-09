# Focused scheduled-expiry fixture check

User-run only, **127.0.0.1 / bloodmatch_test / 3306**, current Windows account signed in and machine awake. No migration application, resets/seeds, real emails, application/deployed scheduling or API-server startup. Use existing C:\xampp\php\php.exe with pdo_mysql and mbstring; credentials come from existing .env and are never printed. Adjust only the PHP executable path if needed (also pass -PhpPath to scheduler commands).

The fixture helper independently pins/verifies the actual database and port before any fixture write, refuses the connector's 3307 fallback, disables SMTP and uses random reserved .test accounts. Its `check` action executes SELECTs only; it does not write files/fixtures/migration records or invoke expiry. Setup requires both the nullable VARCHAR(500) rejection_reason column and migration 020's schema_migrations ledger entry. A column without its ledger entry is not called an applied migration.

Run each numbered step separately and stop on failure. Do not run other expiry workers, API mutations or suites during this focused check. The scheduled worker drains **all** overdue OPEN requests in the test database; setup refuses if any already exist rather than changing unrelated data. It seeds one overdue request with five donor offers: POTENTIAL, NOTIFIED, RESPONDED, RESPONDED with PENDING report, and COMPLETED with a historical CONFIRMED report. Completed donor already has a historical safety anchor; all other anchors are NULL. The historical completion is a fixture, not a new donation workflow.

## 1. Read-only migration 020 verification

From repository root:

```powershell
& 'C:\xampp\php\php.exe' tests\scheduled_expiry_fixture.php check
if ($LASTEXITCODE -ne 0) { throw 'Target/schema prerequisite failed. Do not set up fixtures or install the task.' }
```

Expected JSON: database bloodmatch_test, port 3306, column_ready true, migration_020_recorded true, exit 0. This is the requested read-only confirmation of test migration presence. If false, return the output; no migration is applied by this command. Application/deployed migration state is untouched.

## 2. Ensure previous test task is removed; create disposable fixtures

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Remove
if ($LASTEXITCODE -ne 0) { throw 'Task may still be running; let it finish and repeat Remove before continuing.' }
& 'C:\xampp\php\php.exe' tests\scheduled_expiry_fixture.php setup
if ($LASTEXITCODE -ne 0) { throw 'Fixture setup failed; do not install the task. See cleanup below.' }
```

Expected PASS fixture setup and one request ID. Setup is one transaction. Exact IDs, synthetic baseline snapshots and the current scheduler-log byte offset are saved to logs/scheduled-expiry-fixture.json before commit. No password hashes, credentials or real member details are saved. Do not edit/delete the manifest before cleanup. An existing manifest requires cleanup first; preexisting overdue test requests cause a safe prerequisite refusal. Report that refusal rather than resetting data or running a broad cleanup.

## 3. Install only the approved test task, then wait

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Setup
if ($LASTEXITCODE -ne 0) { throw 'Task setup failed. Run cleanup below.' }
```

**Wait at least three minutes awake and signed in.** Do not invoke run_expiry.php, run_test_expiry.php without --verify, a lifecycle service or Start-ScheduledTask manually. The fixture helper never invokes expiry: closure must come from the normal scheduled action. IgnoreNew and the existing file lock still protect worker overlap. The fixture helper takes the same worker lock during setup/assert/cleanup to avoid racing the worker.

## 4. Capture evidence; remove the task before assertions

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Verify
Get-Content -LiteralPath logs\test-expiry.jsonl -Tail 8
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Remove
if ($LASTEXITCODE -ne 0) { throw 'Allow current run to finish, then repeat Remove before assertions/cleanup.' }
```

Expected: recent LastRunTime, LastTaskResult 0, PT1M/Interactive/Limited/IgnoreNew. At least one **new** scheduled completed log after setup must have expired_count >= 1 and exit_code 0. Subsequent runs normally have expired_count 0. The assertion command ignores old log entries using the saved offset and correlates the request's expiry cutoff with the scheduled run timestamp. Manual expiry during this experiment invalidates its scheduling evidence.

## 5. Run the focused assertions once

```powershell
& 'C:\xampp\php\php.exe' tests\scheduled_expiry_fixture.php assert
if ($LASTEXITCODE -ne 0) { throw 'Scheduled fixture check failed; retain output and then run cleanup.' }
```

Expected: **16 assertions passed, 0 failed**, exit 0:

- New scheduled run expired at least one request; the fixture changed OPEN -> EXPIRED with a corresponding expiry timestamp.
- Each of the four unfinished offers becomes CLOSED; the PENDING report row is unchanged by expiry.
- The historical COMPLETED match and CONFIRMED report remain byte-for-byte equivalent at the row-value level; all users' donation anchors/availability remain unchanged.
- The same-chapter staff pending queue still contains the report on EXPIRED/CLOSED. The authorized officer's confirmation returns 409; blank-reason rejection fails and leaves PENDING.
- Explicit valid rejection succeeds, persists reviewer/reason, and exposes the reason in own donor history and the protected audit record. It creates no donation/cooldown, changes no completed donation and leaves the request EXPIRED.

Assertions deliberately reject the disposable pending report. This is not a read-only assertion phase and cannot be repeated successfully on the same fixture; cleanup and setup again if rerunning. No real email is sent: SMTP is disabled and all recipients are reserved .test addresses. These service/queue checks do not prove browser layout or HTTP rendering.

## 6. Cleanup on success or failure

Run in a fresh command block even if an earlier command threw:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manage_test_expiry_task.ps1 -Action Remove
if ($LASTEXITCODE -ne 0) { throw 'Wait for the disabled task to finish, then repeat Remove before cleanup.' }
& 'C:\xampp\php\php.exe' tests\scheduled_expiry_fixture.php cleanup
if ($LASTEXITCODE -ne 0) { throw 'Fixture cleanup failed; retain manifest and report the safe error.' }
Get-ScheduledTask -TaskName 'BloodMatch-Test-Expiry' -TaskPath '\' -ErrorAction SilentlyContinue | Select-Object TaskName, State
Test-Path -LiteralPath logs\scheduled-expiry-fixture.json
```

Expected: PASS exact fixture rows removed, PASS fixture audit history retained with cleared actor references, PASS cleanup complete, no task row, manifest False. Cleanup validates random-token account names and request ownership, deletes only that request and its dependent offers/reports and the seven fixture accounts/notifications. It never deletes by a broad email prefix or resets a table. Missing fixture rows are handled safely for retry after interrupted setup/cleanup. All audit records and scheduler logs remain. The existing audit actor foreign key uses ON DELETE SET NULL when removing fixture accounts; cleanup checks that action/target/context/timestamps are preserved. No audit UPDATE/DELETE or trigger disabling is performed. Persistent lock files are harmless and retained.

Cleanup-only retry after the reported SQLSTATE 45000: the original cleanup attempted an audit DELETE, which the existing append-only trigger blocked; the transaction rolled back and the manifest remains. Use the corrected helper and the step 6 block above. Do not recreate fixtures, rerun assertions, apply a migration or reinstall the scheduler. Cleanup success remains pending until that retry output is returned.

Return migration-check JSON, scheduler metadata/new log lines, assertion summary/errors and cleanup output. The agent will report the checkpoint status after those results. Forced contention, browser rendering and any application/deployed scheduling remain outside this experiment.
