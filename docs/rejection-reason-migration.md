# Donation Report Rejection Reason Migration

Marker: `BM-REJECTION-REASON-MIGRATION-020`. Prepared 2026-10-08, Asia/Manila.

User approved preparing the migration and related API/UI changes. **The agent has not applied this migration or run database/PowerShell suites.** This document provides the review and exact test-database commands for the user.

## SQL for review

File: [020_donation_report_rejection_reason.sql](../database/migrations/020_donation_report_rejection_reason.sql).

```sql
ALTER TABLE donation_reports
    ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER report_note;
```

It adds one nullable column. Existing values become NULL; no reason is invented and no report status/decision/timestamp is updated. There is no UPDATE, DELETE, seed, reset, constraint backfill or storage migration. Existing approved/rejected/pending history stays in place. This does not apply the non-response standby/outbox migrations, which are still separate pending work.

New rejections require a string containing visible text, up to 500 Unicode characters after trimming outer whitespace/format marks. Invalid/missing/blank/overlength values receive 400 with rejection_reason validation details. Whitespace/format-only values are blank; internal line breaks/text are preserved. Staff must explicitly reject pending reports; no automatic rejection or donation is recorded when a request closes.

The reason is stored with the existing reviewer ID and decision timestamp, added to the protected donation.rejected audit event, and returned only in the donor's own report history. Other member profiles do not gain report/reason access. Current officer chapter/admin scope/self-review restrictions remain. Existing notification recipients/channels/body remain unchanged; freeform rejection text is not newly copied into email or in-app alerts.

## Exact commands: review, apply, verify

From repository root, use an existing disposable **bloodmatch_test** database on **3306**, with its existing migration ledger/reference data. Stop test API writers before applying; leave port 8001 free for subsequent isolated suites. Do not reset/seed production or share credentials/document contents.

Review only (read metadata/print SQL; no schema or report writes):

```powershell
& 'C:\xampp\php\php.exe' database/apply_rejection_reason_migration.php --port 3306
```

Expected: target bloodmatch_test:3306, pending column/ledger (or correctly ready/recorded if already applied), exact additive SQL, and “Review only; no writes.” The CLI-only helper fixes DB_HOST/DB_NAME for its own process, verifies actual database name and server port, and refuses an unexpected column definition or inconsistent ledger. It does not modify .env, print credentials, apply other pending migrations or create a missing baseline ledger.

After reviewing the SQL, **you** apply it:

```powershell
& 'C:\xampp\php\php.exe' database/apply_rejection_reason_migration.php --port 3306 --apply
```

Expected: column verified VARCHAR(500) NULL, existing report IDs/statuses unchanged, and only 020 recorded in schema_migrations. For an initially absent column, all preexisting reasons must be NULL. The helper applies only this file rather than running the generic all-pending migration runner. If already correctly applied, it performs no repeat ALTER; an existing correct column with a missing ledger can be recorded without rewriting any report. MySQL DDL auto-commits: if ledger recording fails after column creation, inspect/review and retry this helper; never drop/rebuild a database as recovery for this additive migration. If writers changed report IDs/statuses during apply, it stops before recording the ledger; stop writers and inspect first.

Read-only metadata verification:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root bloodmatch_test -e "SELECT COLUMN_NAME,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' AND COLUMN_NAME='rejection_reason'; SELECT name FROM schema_migrations WHERE name='020_donation_report_rejection_reason.sql';"
```

Expected: rejection_reason | varchar | 500 | YES, and one ledger entry for 020. This verifies schema readiness, not inbox delivery or complete UI interaction. Use your configured local credential handling if root requires authentication; do not paste secrets into chat. These commands do not authorize a production migration. Coordinate schema-before-write rollout for any later deployment separately.

## Exact test commands (user-run only)

Same disposable bloodmatch_test setup and port 3306; XAMPP PHP/MySQL paths must exist, proc_open must be available for the previously prepared concurrency suite, and TCP port 8001 must be free. Migration 020 must be ready before these runs. The isolated runner points its separate API to the test DB, disables real SMTP via a closed local port, restores process environment and stops its server afterward.

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite rejection_reason.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite workflow_atomicity.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase8.ps1
```

Expected: zero failed assertions. The new rejection suite has 29 checks: invalid reason rejection without mutation; reviewer/chapter/self authorization; normalized reason/reviewer/time persistence; own history and scoped audit; member/other-chapter audit privacy; donor_id history manipulation; other-profile privacy; repeat decision; pending closed-report confirmation block and explicit admin rejection without cooldown; legacy NULL/status preservation; 500 Unicode-character boundary. Fixtures are token-marked reserved .test accounts and removed from the test DB; ordinary test requests and the isolated SMTP guard prevent real emails. The database suite has only been parsed by the agent, not executed.

Existing phase8 now supplies a reason for its intentional rejection. Workflow atomicity remains the previously prepared 17-check parallel/direct-service regression; its prior user-reported pass does not certify the new service changes. Return full summaries/errors after applying/testing; no unrun suite is marked passed.

Manual UI checks, using local test accounts after migration:

1. Officer Confirmations: Reject opens a required reason dialog; blank/overlength input cannot submit; Keep report leaves it pending. Confirm/reject buttons respect server capabilities, including closed and self-review cases.
2. Keyboard: focus reaches the textarea, Tab/Shift+Tab stays in the dialog, Escape cancels when idle, busy state blocks repeated submission, and errors retain typed reason for correction. Check narrow viewport and long reasons; no Home/card redesign occurred.
3. Own Profile -> Donation History: rejected report shows stored reason as text with line breaks; legacy NULL shows “No reason recorded.” A markup-looking reason must remain escaped text. Viewing another member must not show their report/rejection history.
4. Assigned officer/admin audit view: donation.rejected context includes rejection_reason; unauthorized member/cross-chapter access remains blocked/scoped.

Browser tool startup failed in this session (sandbox helper setup error). Offline markup/callback checks passed but do not prove actual keyboard/layout behavior; these manual checks remain pending.

## Pre-migration compatibility and remaining scope

Own history remains readable if the new column is absent: the repository projects NULL, preserving the legacy fallback. New rejections are blocked with 503 when the exact nullable VARCHAR(500) storage is unavailable; the transaction rolls back and the report stays pending, avoiding a rejected report with a lost reason. This behavior is source-reviewed; live missing-column/DDL/rollout behavior was not executed by the agent. Normal confirmation does not require a reason.

This step is Phase 3B1 (approved reason storage/API/UI). Request edit/cancel locking and terminal-offer cleanup are Phase 3B2; expiry scheduling/draining/deadline consistency and non-response standby/queued mail remain later phases. The already existing confirmation status guard blocks stored CANCELLED/EXPIRED/FULFILLED requests, but this migration is not a claim that automatic expiry/closure cleanup is complete. No historical request/report states were backfilled.

## Checks actually run

| Check | Result |
|---|---|
| tests/rejection_reason_offline.php | 19 passed; pure validation + SQL review only |
| tests/rejection_ui_offline.mjs | 25 passed; shared fixtures and actual mocked UI callbacks, no network |
| tests/request_ui_static.mjs | 19 passed; includes own-history escaping/legacy fallback and other-profile privacy |
| PHP syntax / PowerShell parser | Passed on changed PHP and deferred scripts; no database execution |
| Frontend build | Passed; existing >500 kB bundle warning remains |
| Migration/helper review/apply against a DB | NOT RUN by agent |
| PowerShell/database suites | NOT RUN by agent |
| Browser/manual interaction | UNVERIFIED; browser tool could not initialize |
