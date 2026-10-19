# Donation-completion handoff — 2026-10-19

**Latest UI follow-up:** required donation date and time are now separate labelled controls across initial report and correction forms, with Asia/Manila shown and unchanged UTC conversion. Opening the notification bell now marks current notifications read and clears/synchronizes the badge; read failures retain the count and report an error. This is the user's requested replacement for the earlier opening-keeps-unread behavior. Build, PHP lint, date/status and offline render/handler checks pass. No new migration or scheduler action is needed. User browser checks: refresh, reopen the report dialog, verify two separate fields; open bell, close/reopen/refresh and verify old count stays cleared; new incoming notifications should still increase the count.

**Application migration confirmed by user:** migration 030 SHA `73b2bfb6fcc27cd3aed0c727b0010885b4217d4590bf328b523ba468bcf7e5da` applied on `bloodmatch:3306`; subsequent read-only review reports `recorded:true`, `new_tables:3`, `new_columns:2`, `status:ready`. This supersedes earlier application-schema-not-applied statements below. Authenticated application browser/parallel-action checks and follow-up activation remain pending. Agent did not execute the migration or activate workers. MatchesPage missing occurrence picker is corrected; production build and 13 date/status helper checks passed. Close the test-server terminal before restarting the normal backend; the synthetic browser accounts created on bloodmatch_test are not application accounts.

Implementation approval and superseded rules: [approved plan](DONOR_REQUESTER_CONFIRMATION_PLAN.md).

**Source prepared, not deployed/DB-verified.** The agent did not apply migration 030, run a database/PowerShell suite, change the real .env, install/start schedules, or execute SMTP. Earlier notification/chat fixes and the existing verification-email worker were preserved. Real browser verification is outstanding.

**Latest user-run evidence:** migration 030 SHA `73b2bfb6fcc27cd3aed0c727b0010885b4217d4590bf328b523ba468bcf7e5da` successfully resumed on `bloodmatch_test:3306`; subsequent review reports `recorded:true`, three new tables, two tracked columns, `status:ready`. All 33 fixed-target DB checks passed with fixture rollback and SMTP false. All seven isolated API authorization checks passed, with test server stopped/environment restored. This supersedes the test-target unverified status above; application migration, authenticated browser/parallel-action checks and follow-up activation remain pending. Do not repeat the completed migration/suites without a new reason.

**User steering after browser testing:** user requested continuing browser tests on the main database instead of the test target. This supersedes the earlier requirement to finish all test-target browser checks before application rollout; it does not claim those checks passed. The observed missing occurrence picker was a separate legacy reporting dialog in MatchesPage, not demonstrated database corruption. That dialog now submits the required UTC occurrence from an explicitly labelled Asia/Manila picker, uses requester-acknowledgment wording, and styles its inputs with existing tokens. Application read-only review, recoverable backup and user-run migration still precede main-target testing. Do not use the test-only account creator against the application database or invent real donation events for existing members. Main-target synthetic test confirmations persist as credits/holds/restrictions; use designated synthetic accounts, with no automatic cleanup or history deletion. Migration/suite/scheduler execution remains user-run and no new email activation is authorized.

## What changed

- Separate actual occurrence, original submission, acknowledgment, completion recording and admin review timestamps.
- Required Asia/Manila picker; UTC storage; invalid/future/pre-response dates rejected. Legacy missing dates/response chronology require clarification.
- Version-bound requester acknowledgment; agreement records **mutually acknowledged donation**, never medical verification. Admin confirmations require a private documented basis.
- Request closure/expiry/fulfillment preserves reports. Late credit updates history/restrictions but cannot reopen/fulfill CANCELLED/EXPIRED requests. Excess genuine donations are retained.
- Separate pending-report holds; rejection leaves the hold. Explicit reasoned no-donation release affects only that report, is idempotent, and changes no availability or other eligibility state. Genuine donations with incorrect details must be clarified/corrected.
- Private version snapshots and decision events, actor references, active-hold retention guards, unique per-match credits, atomic locking/rollback and silent retries.
- In-app-only 24h reminder/48h escalation processor, disabled by default. Existing configured donor intervals and approved email coverage/budgets remain unchanged.

No file evidence upload/storage was added. Optional private references/supporting notes use report storage and authorized event history; no document URLs or storage paths are exposed. Existing encrypted identity/document handling is unchanged.

## User-run test-target migration

Run from the repository root. Keep MySQL running. Stop HTTP/task writers while applying schema changes; DDL auto-commits, so take a recoverable backup first. These commands target the project's currently configured fixed local port **3306**. Do not use the generic all-pending migration runner.

```powershell
$php = 'C:\xampp\php\php.exe'
$dump = 'C:\xampp\mysql\bin\mysqldump.exe'
$backupFolder = 'C:\BloodMatchBackups'
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null
$testBackup = Join-Path $backupFolder ('bloodmatch_test-before-030-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$testBackup" bloodmatch_test
if ($LASTEXITCODE -ne 0) { throw 'Test backup failed.' }
& $php database/review_donation_completion_migration.php --target=test
$migrationSha = (Get-FileHash database/migrations/030_donation_completion_workflow.sql -Algorithm SHA256).Hash.ToLowerInvariant()
& $php database/review_donation_completion_migration.php --target=test --apply "--reviewed-sha=$migrationSha" --backup-confirmed
if ($LASTEXITCODE -ne 0) { throw 'Migration stopped; review partial DDL and the backup before retrying.' }
& $php database/review_donation_completion_migration.php --target=test
```

If your MySQL account requires a password, use the normal interactive `-p` option on the dump command. Confirm the backup file exists and is usable. The helper refuses already-recorded/partial migrations; do not remove its ledger entry or rerun raw DDL to hide a partial failure.

Migration 030 preserves existing report statuses, adds no historical donation occurrence/credit, recovers response timestamps only from real audit events, and puts existing PENDING claims on distinct holds. Its ready check is a migration/table marker check; the regression suite exercises the actual queries and transitions.

### Diagnosed test-target collation failure

The user-run first attempt added `matches.responded_at` and then failed in the audit backfill with error 1267 (`utf8mb4_unicode_ci` versus `utf8mb4_general_ci`). The first read-only fix probe then reported error 1253 because MariaDB returned the JSON value as `utf8`. Migration 030 and the corresponding historical-response query now explicitly convert JSON ID text to `utf8mb4` before applying `utf8mb4_unicode_ci`; no table-wide collation change is needed. The migration SHA changed. Recovery has not yet been run or database-verified by the agent.

Run the read-only diagnosis first:

```powershell
& 'C:\xampp\php\php.exe' database/review_donation_completion_migration.php --target=test --diagnose
```

Only if `backfill_probe.status` is `passed` and `response_column_resume_eligible` is `true`, retain the successful pre-030 backup, stop HTTP/task writers, and resume with the newly reviewed SHA:

```powershell
$migrationSha = (Get-FileHash database/migrations/030_donation_completion_workflow.sql -Algorithm SHA256).Hash.ToLowerInvariant()
& 'C:\xampp\php\php.exe' database/review_donation_completion_migration.php --target=test --apply --resume-response-column "--reviewed-sha=$migrationSha" --backup-confirmed
if ($LASTEXITCODE -ne 0) { throw 'Resume stopped. Save the full database error; do not retry.' }
& 'C:\xampp\php\php.exe' database/review_donation_completion_migration.php --target=test
```

Resume is limited to the test target with no migration ledger entry, no new workflow tables/columns on donation_reports, and only the expected nullable DATETIME response column. It skips exactly the already committed first ALTER, reruns the failed backfill, and applies the remaining statements. Other partial states are refused; it never drops existing schema or manually fabricates a ledger entry. If the backup is missing/unusable, stop before applying. A new backup of a partial schema does not replace the pre-migration backup.

## User-run focused regressions

```powershell
& 'C:\xampp\php\php.exe' tests/donation_completion_db.php
if ($LASTEXITCODE -ne 0) { throw 'Donation DB regression failed. Save the complete output.' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite donation_completion_api.ps1
if ($LASTEXITCODE -ne 0) { throw 'Donation API guard regression failed. Save the complete output.' }
```

The PHP suite fixes its target to bloodmatch_test:3306, requires the canonical active test administrator and migration 030, uses synthetic example.test accounts/references, and rolls back all fixture writes. It tests real service/SQL agreement, duplicates/conflicting final actions, excess/late donations, privacy, version corrections, holds, legacy missing dates and follow-up retries. No SMTP transport is called. Auto-increment counters may advance on rolled-back fixtures. The PowerShell suite runs the new anonymous API authorization guards through the existing isolated server wrapper; it does not claim authenticated UI or true parallel-race proof.

Also check with two signed-in sessions on the test target:

1. Donor submits; requester sees all three actions and the exact reported time/version. A normal agreement completes without admin action. Check donor hold → restriction and requester fulfillment.
2. Two donors report to one request: confirm both, including the second after fulfillment. Exactly one credit per donor match; no discarded second report.
3. Race requester acknowledgment against admin rejection on the same version. One final outcome wins; the other operation conflicts or becomes a silent idempotent retry. Check one credit at most, no duplicate alerts, and matching hold/history.
4. Dispute/uncertainty, clarification and corrected version: older acknowledgment must fail. Check all prior events, required admin bases, and active hold/age visibility.
5. Reject and explicitly release one no-donation hold; another report's hold, standby/cooldown and donor preference stay intact. A genuine but incorrect report remains held until correction.
6. Close/cancel/expire a request before deciding a pending report. Reports stay available, late recording does not change closed status or reopen offers. An overdue OPEN request expires rather than fulfilling on late review.
7. Verify donor/requester/admin access and self-action denials, mobile layout, light/dark mode, keyboard access and refresh. Notes/rejection details must not appear in public feeds, generic alerts, aggregate downloads or unrelated accounts.

### Browser test server (user-run, test database only)

The isolated API wrapper has already stopped its server. The frontend currently proxies `/api` to port 8000. Stop the existing backend on that port, then use a **fresh PowerShell terminal** at the repository root for this test backend. These overrides affect only this terminal and its PHP child, not the real `.env`. No task is started. Use known test accounts; the regression fixtures were rolled back and are not login accounts.

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_NAME = 'bloodmatch_test'
$env:MAIL_HOST = '127.0.0.1'
$env:MAIL_PORT = '1'
$env:EMAIL_WORKER_ENABLED = '0'
$env:MEMBERSHIP_EMAIL_ENABLED = '0'
$env:SAFETY_AUTOMATION_ENABLED = '0'
$env:DONATION_FOLLOWUP_ENABLED = '0'
$env:APP_SECURE_COOKIES = 'false'
$browserTestSessions = Join-Path $env:TEMP ('bloodmatch-browser-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $browserTestSessions | Out-Null
& 'C:\xampp\php\php.exe' -d variables_order=EGPCS -d "session.save_path=$browserTestSessions" -S 127.0.0.1:8000 -t backend/public
```

Use the existing frontend at `http://localhost:5173`, or start `npm.cmd run dev` in a separate terminal from `frontend`. Use separate browser profiles for donor/requester/admin. Confirm that the test accounts appear before creating test requests. After testing, stop PHP with Ctrl+C and close its dedicated terminal to discard the process-only overrides. Restart the normal backend through the existing process; do not reuse this test terminal for application operations. No email delivery will succeed against the closed local SMTP port.

If two known member logins are needed, the user can run `& 'C:\xampp\php\php.exe' database/create_donation_browser_accounts.php --create`. This prepared helper is fixed to `bloodmatch_test:3306`, creates only two synthetic adult, email/membership-verified active members, enrolls the donor with self-reported O+, audits creation and sends no email. Requester: `bm-donation-requester@example.test`; donor: `bm-donation-donor@example.test`; shared local test password: `BloodMatchTest2026!`. Both use the same existing test chapter. It refuses existing addresses rather than resetting passwords or restrictions. Fixtures persist for browser testing. Agent checked PHP syntax/default no-write mode only; creation and login remain user-run/unverified. Create an O+ request for compatibility. No admin account is created or changed.

## Conditional application rollout — only after test results pass

Stop application HTTP/task writers for the schema apply. Retain the existing .env and existing task settings; no other worker is activated by this feature.

```powershell
$appBackup = Join-Path $backupFolder ('bloodmatch-before-030-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$appBackup" bloodmatch
if ($LASTEXITCODE -ne 0) { throw 'Application backup failed.' }
& $php database/review_donation_completion_migration.php --target=application
& $php database/review_donation_completion_migration.php --target=application --apply "--reviewed-sha=$migrationSha" --backup-confirmed
if ($LASTEXITCODE -ne 0) { throw 'Application migration stopped; inspect before retrying.' }
& $php database/review_donation_completion_migration.php --target=application
& $php database/run_application_donationfollowup.php
```

Restart/rebuild the application using its existing development/deployment process and repeat the relevant participant checks. Source and schema must be deployed together: the new completion endpoints intentionally return a clear unavailable/migration message before 030 is recorded. No endpoint falls back to the superseded blind-confirmation flow.

## Optional user-run reminder/escalation activation

After reviewing the application follow-up candidate count, set **only** `DONATION_FOLLOWUP_ENABLED=1` in .env. No new email flag is needed. Existing pending legacy claims may immediately qualify for reminder/escalation from their original reported_at; this never awards/rejects credit.

```powershell
& $php database/run_application_donationfollowup.php --run
if ($LASTEXITCODE -ne 0) { throw 'Follow-up run failed.' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action Preview -Kind DonationFollowup
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action Install -Kind DonationFollowup
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action Verify -Kind DonationFollowup
Get-Content logs/donation-followup-application.jsonl -Tail 2
```

The prepared task uses the installed windowless php-win.exe, every minute, without altering other tasks. It runs while the Windows user is signed in and the computer/MySQL are available. The processor uses a separate non-overlap lock and bounded batches; flags plus transactional/deduplicated events make retries safe. Do not reinstall an existing task; Verify it, or use the task manager's ownership-checked Upgrade action if appropriate. No real-email experiment is needed for these in-app-only workflow alerts.

## Checkpoint

All requested product choices were approved in chat. Agent-side lightweight checks and build results are recorded in the progress log. Database migration, real SQL/API/parallel-race/browser verification and scheduling remain unrun by the agent. Supply the user-run outputs before treating the workflow as deployed and verified; do not repeat unrelated previously completed fixes/tests or real-email experiments.
