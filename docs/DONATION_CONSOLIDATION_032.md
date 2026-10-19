# Donation storage consolidation checkpoint - 19 October 2026

**Latest checkpoint:** source retirement was subsequently approved and completed by migration 035. Main now has 34 tables; see [retirement evidence](DONATION_SOURCE_RETIREMENT_035.md). Retained-source/reconstruction statements below describe the earlier rollout checkpoint. No ERD update was performed for retirement, per user instruction.

## Status and boundaries

**Application rollout approved and migrations 032/033 applied to `bloodmatch:3306` on 19 October 2026. Source-table retirement remains unapproved and unexecuted.**

The application now uses consolidated reports and ordered compatibility pairs. The earlier test-only checkpoint below is retained as test evidence; it was superseded by the approved application rollout. Unrelated edits were preserved. `.env` was not edited, no synthetic main-database fixtures were created, and no source tables were retired. Browser and actual photo handling remain unverified.

## Approved application rollout evidence

- Exact target: `127.0.0.1:3306/bloodmatch`; all 11 preflight checks zero; reviewed 032/033 hashes matched the tested files.
- Enabled the existing HTTP maintenance gate and paused only the two owned, previously enabled application tasks (MembershipEmail and Safety). No scheduler installed or manually started; no SMTP or notification action invoked by migration/read checks.
- External pre-032 backup: `C:\BloodMatchBackups\bloodmatch-before-032-20261019-212427.sql`; SHA256 `6266f183697097ef93db24533702aebb622eefe2f198b0aa55bdab859cb01804`. Target header, complete dump footer and SHA verified before DDL.
- Separate full pre-033 dump and backend code archive are in the same external backup directory. Paths/checksums and task state are recorded in ignored/private `backend/storage/maintenance/application-consolidation-state.json`.
- Both migrations applied successfully. Original-column hashes matched across **all 35 original business tables**, excluding the intentionally changed migration ledger. Credit, hold and ordered compatibility reconciliation each reported zero mismatches. The existing legacy confirmation retained its original NULL occurrence and received no invented credit.
- Read-only runtime checks passed for migration-selected storage, ordered compatibility service, administrative queue, active hold reads, proof retention queries, and immutable context trigger. No application fixture or fulfillment action was executed.
- Current main schema: **37 physical tables**; six active donation tables plus three retained source tables. Separately approved source retirement would leave **34** main tables. The test schema has 38 because unrelated password-reset removal 034 was applied only to main.
- Detailed migration evidence: ignored/private `application-consolidation032-result.json`, `application-consolidation033-result.json`, and `application-consolidation-before.json` in the maintenance directory. Full dump restore remains unrehearsed; the prior test recovery reconstruction rehearsal passed.
- Completion: original MembershipEmail/Safety task enablement restored and both verified Ready; no task manually triggered. Owned maintenance lock removed; `.env` hash unchanged. Rollout state is `completed_sources_retained`.

### Login follow-up

The user's restarted PHP terminal retained test-database environment overrides: recent failed login audits appeared on `bloodmatch_test`, not main, despite `.env` naming main. No password/account change was made. Only the verified local PHP listener on port 8000 was replaced. `scripts/start_main_backend.ps1` now explicitly selects `bloodmatch:3306`, verifies the actual connection before serving, and restores its own process overrides on exit. The backend was started with this launcher; main-target preflight, HTTP health/database connectivity, and CSRF checks passed. Account login itself remains for the user to retry; no password was requested or tested.

**Concurrent unrelated change at the earlier test checkpoint:** migration `034_remove_password_resets.sql` was applied independently to the application and related password-recovery removal edits appeared in the workspace. Those changes were preserved, not executed or reverted by this task. Before the approved rollout, main had 36 tables and recorded 034 but neither 032 nor 033. Current main rollout status is documented above. The test snapshot still includes `password_resets`; 034 was not applied to test as part of this consolidation.

- 032 SHA256: `00ed99175af9a12121276df88daac5ac45aa4454fb493f65117e1b27b986219b`
- 033 SHA256: `145ee96d22091473f16d60529cda524ed2a1d07f073c06c1da0c04a82010985f`
- Test before-032 backup: ignored/private `backend/storage/maintenance/bloodmatch_test-before-032.sql`; SHA256 `8221dd1376f178d2c5a54a76e33280a5a80a500d0deab0ba15e85a9e24711482`.
- A separate before-033 test backup is in the same ignored directory. Dumps contain database data: do not publish or commit them.
- Test fixture manifests: `consolidation032-fixtures.json` and `consolidation032-race-fixtures.json` in that directory. The multi-process tests deliberately retain synthetic committed history on the disposable test database. Other workflow fixtures roll back.

## Resulting design

032 copies credit and independent hold state into `donation_reports`, preserving original values rather than recreating donations. An explicit nullable `credit_recorded_at` distinguishes awarded credit from a legacy confirmation. Nullable generated donor/occurrence keys enforce uniqueness only for credited reports. Hold start/release/actor/reason/type remain independent of report status. Rejection does not release a hold; reopening clears release fields, retaining the original start and event history.

Reports are unique per match. A composite FK enforces match/donor agreement. Renewals gain a nullable self-FK and one-successor uniqueness. Positive request quantity, report versions, workflow/response domains, credit prerequisites, chat-case message uniqueness, and an unresolved-correction index are added. Context validation preserves the original nullable SET NULL FK. 033 makes accepted context match identity immutable, closing a possible concurrent insert/reassignment race without changing an application writer: no application code reassigns accepted contexts.

`compatibility_pairs` replaces the repeating list with 27 ordered pairs. Both donor preference order and inverse recipient order are preserved, including the original `utf8mb4_unicode_ci` ordering. Seed 006 contains the same approved pairs; the seed runner selects the correct old/new reference according to the 032 marker. No matching, eligibility, waiting-period, clinical, or role policy is changed.

All readers/writers select storage through the 032 migration marker. Before application rollout, the same backend continues using original application tables. After rollout, reports are authoritative, including queue, proof retention, and request retention queries. Restart persistent PHP workers when switching markers; do not carry a request across DDL.

**Six active donation tables:** reports, evidence, report events, accepted chat contexts, coordination events, and correction requests. Photos stay as individual child rows with version intervals and protected file storage. Corrections remain review requests and do not silently reverse completed donations.

The test schema currently has **38 physical tables** because `donation_credits`, `donation_report_holds`, and `compatibility_matrix` are retained cutover sources. They are not dual-written after 032. After separately approved retirement, this tested design has **35 active base tables**. Main now has **37**, including those sources; retiring them would leave **34** there because unrelated 034 removed password resets only on main. Retained sources can age through existing FK cascades; the verified backup and cutover digests are the durable pre-migration evidence.

## Verification actually completed

| Check | Result |
|---|---|
| Exact isolation | Direct `127.0.0.1:3306/bloodmatch_test`; zero other test connections before migration; no port fallback |
| Pre-032 donation baseline | 35 passed, rollback fixtures |
| Migration preservation | Original field/table digests matched; five reports, one source credit, three source holds preserved; all three copy/order reconciliation checks zero |
| Legacy migration cases | Existing and synthetic confirmed reports without credit received no invented occurrence or credit |
| `donation_completion_db.php`, post-032 | 35 passed |
| `offer_chat_db.php` | 65 passed |
| `donation_consolidation_db.php` | 21 passed |
| `donation_consolidation_constraints.php`, final 033 design | 21 passed, including all eight inverse compatibility orders, context immutability/SET NULL, seed equivalence, isolation refusal, and overdue OPEN behavior |
| `donation_consolidation_concurrency.php` | 14 passed using independent PHP processes and committed synthetic cases |
| Isolated PowerShell `donation_completion_api.ps1` | 7 passed |
| Isolated PowerShell `offer_chat_api.ps1` | 13 passed |
| `home_compatibility_model.php` | 3 passed |
| Recovery rehearsal | Source reconstruction after post-cutover writes reconciled with all 14 checks zero, then rolled back |
| Backup guards and artifact checks | Full test dump hash/target/footer accepted; same dump rejected for application target; 19 changed/new PHP files linted; both SVG files parsed as XML |

Concurrency covers identical submissions, repeated acknowledgment, confirmation versus rejection, and different claims for the same donor/occurrence. Injected fulfillment failure proved rollback of report/acknowledgment/credit/hold/match/restriction state, followed by successful retry. Other checks cover excess donations, CANCELLED/EXPIRED requests, effectively overdue OPEN requests, hold release/reopening, versioned history, correction privacy, and email-outbox absence for donation actions.

Test processes use `tests/helpers/consolidation_isolation.php`; application and alternate-port overrides fail before connection. `Mailer::send` uses a no-socket fake only in explicitly isolated test processes. HTTP tests used a separate local server on port 8003; both servers were stopped and environment overrides restored. No worker/scheduler was started. Notifications generated by tests exist only in the disposable database.

Observed and resolved during implementation: a digest pre-read initially assumed every table had an `id` column and stopped before DDL; it now orders credit/hold tables by `report_id`. Running two rollback-based suites together caused a fixture-lock deadlock; suites were subsequently run serially and dedicated multi-process service races passed. An inverse-order assertion caught CAST using connection collation; explicit original collation fixed it. Earlier interrupted/failed runs are not counted as passes.

Existing child-table digests at migration time included empty event/evidence/correction tables; this does not establish nonempty historical-file preservation. Subsequent service tests exercised versioned history and proof metadata. Actual encrypted photo upload/serving, physical file reconciliation, authenticated browser flows, production contention, and full dump restore remain unverified. No permanent audit-retention or substantive completed-credit correction policy was introduced.

## Review artifacts

- [Tested ERD](erd.md), [tested SVG](erd.svg), [tested data dictionary](data_dictionary.md).
- [Actual application ERD](erd_application.md), [application SVG](erd_application.svg), [application data dictionary](data_dictionary_application.md).
- `database/export_erd.php` now includes every actual table and composite keys in the dictionary. Exports contain metadata only.

## Application rollout - prepared, DO NOT execute without approval

Use a separate application maintenance window. Keep a deployable copy of the current backend before replacing it; preserve unrelated workspace changes. Do not use `git reset`, broad checkout, or the generic all-pending migration runner.

1. Enable the existing HTTP maintenance gate. Pause only existing BloodMatch application schedules; record which were enabled. Do not install or manually run any task. Let in-flight work finish, stop standalone application CLI writers and persistent PHP servers, and confirm no target connections remain. If anything cannot be fenced, stop.
2. Take and verify a full external backup before each DDL checkpoint. Do not proceed on a nonzero dump exit, missing completion marker, target mismatch, partial migration, or nonzero preflight result.
3. Review the preflight and exact migration hashes. Apply only 032, reconcile original fields and copied values before its marker is recorded, then apply 033 with a fresh backup. Keep maintenance enabled if either step stops.
4. Restart PHP with the reviewed backend, export the application metadata, and review isolated/deployed behavior before reopening writes. Restore only the previously enabled schedules after approval to resume application automation; do not manually trigger them.
5. Keep the source tables during the initial rollout. Retirement requires a separate approval and a current full backup; it is not part of either migration.

Example maintenance preparation, **only after application approval**, in a fresh PowerShell terminal at the repository root:

```powershell
$php = 'C:\xampp\php\php.exe'
$dump = 'C:\xampp\mysql\bin\mysqldump.exe'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$backupDir = 'C:\BloodMatchBackups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
New-Item -ItemType File -Path backend/storage/maintenance/website.lock -Force | Out-Null

# Inventory existing application tasks; do not install or start anything.
$applicationTasks = @(Get-ScheduledTask | Where-Object { $_.TaskName -like 'BloodMatch-Application-*' })
$previouslyEnabled = @($applicationTasks | Where-Object { $_.State -ne 'Disabled' } | Select-Object TaskName,TaskPath)
$previouslyEnabled | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $backupDir 'before-consolidation-enabled-tasks.json')
foreach ($task in $previouslyEnabled) {
  Disable-ScheduledTask -TaskName $task.TaskName -TaskPath $task.TaskPath | Out-Null
}
# Do not kill in-flight transactions. Wait for completion and stop standalone writers.
if (@(Get-ScheduledTask | Where-Object { $_.TaskName -like 'BloodMatch-Application-*' -and $_.State -eq 'Running' }).Count) {
  throw 'Application task still running; leave maintenance enabled and stop here.'
}
& $mysql -h 127.0.0.1 -P 3306 -u root bloodmatch -e "SELECT COUNT(*) AS other_connections FROM information_schema.PROCESSLIST WHERE DB='bloodmatch' AND ID<>CONNECTION_ID();"
if ($LASTEXITCODE -ne 0) { throw 'Connection review failed.' }
# The displayed count must be zero; the apply helper also enforces this.
& $php database/review_donation_consolidation.php --target=application
if ($LASTEXITCODE -ne 0) { throw 'Preflight stopped.' }
```

032, after reviewing all preflight values are zero:

```powershell
$backup032 = Join-Path $backupDir ('bloodmatch-before-032-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$backup032" bloodmatch
if ($LASTEXITCODE -ne 0) { throw 'Backup failed; do not migrate.' }
$backupSha032 = (Get-FileHash -LiteralPath $backup032 -Algorithm SHA256).Hash.ToLowerInvariant()
$sha032 = (Get-FileHash -LiteralPath database/migrations/032_consolidate_donation_storage.sql -Algorithm SHA256).Hash.ToLowerInvariant()
# Compare sha032 with the reviewed checkpoint SHA above before executing.
& $php database/review_donation_consolidation.php --target=application --apply --application-approved --writers-stopped "--reviewed-sha=$sha032" "--backup-file=$backup032" "--backup-sha=$backupSha032" |
  Tee-Object -FilePath (Join-Path $backupDir 'consolidation032-result.json')
if ($LASTEXITCODE -ne 0) { throw '032 stopped; leave maintenance and schedules paused.' }
```

033, with writers still fenced:

```powershell
$backup033 = Join-Path $backupDir ('bloodmatch-before-033-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$backup033" bloodmatch
if ($LASTEXITCODE -ne 0) { throw 'Backup failed; do not harden.' }
$backupSha033 = (Get-FileHash -LiteralPath $backup033 -Algorithm SHA256).Hash.ToLowerInvariant()
$sha033 = (Get-FileHash -LiteralPath database/migrations/033_immutable_donation_context.sql -Algorithm SHA256).Hash.ToLowerInvariant()
& $php database/review_donation_consolidation.php --target=application --context-identity --apply --application-approved --writers-stopped "--reviewed-sha=$sha033" "--backup-file=$backup033" "--backup-sha=$backupSha033"
if ($LASTEXITCODE -ne 0) { throw '033 stopped; leave maintenance and schedules paused.' }
& $php database/review_donation_consolidation.php --target=application
& $php database/review_donation_consolidation.php --target=application --context-identity
& $php database/export_erd.php --target=bloodmatch
```

For a password-protected MySQL CLI account, add interactive `-p`; never put the password into a command or publish dump contents. PHP tools read existing credentials privately from `.env`. They connect to the named database and port directly, without fallback.

## Recovery

DDL auto-commits. A partial 032 is refused rather than retried blindly. Before reopening writes, the pre-032 dump is the full restoration point. Keep the failed-state dump too. After identifying the correct backup and receiving restoration approval, the exact local MySQL client restore form is:

```powershell
# ONLY for a verified pre-032 backup, while writers remain stopped.
# Do not restore an old dump over any post-cutover activity.
$restorePath = $backup032.Replace('\','/')
& $mysql -h 127.0.0.1 -P 3306 -u root bloodmatch --execute="source $restorePath"
if ($LASTEXITCODE -ne 0) { throw 'Restore stopped; keep maintenance enabled.' }
```

If normal writes have resumed, restoring a pre-cutover dump would discard new activity. While the old source tables remain, use the prepared **forward-preserving recovery** instead: take a current backup, fence writers, rebuild sources from current authoritative reports/pairs, reconcile, then remove only the 032 storage-selection marker in the same transaction. Report/event/history rows are retained. Added constraints and the 033 identity rule remain. No original migration file is edited. Re-enabling 032 after this downgrade requires a new reviewed reconciliation checkpoint; do not rerun its DDL.

```powershell
$recoveryBackup = Join-Path $backupDir ('bloodmatch-before-storage-recovery-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$recoveryBackup" bloodmatch
if ($LASTEXITCODE -ne 0) { throw 'Current-state recovery backup failed.' }
$recoverySha = (Get-FileHash -LiteralPath $recoveryBackup -Algorithm SHA256).Hash.ToLowerInvariant()
& $php database/recover_donation_sources.php --target=application
& $php database/recover_donation_sources.php --target=application --apply --application-approved --writers-stopped "--backup-file=$recoveryBackup" "--backup-sha=$recoverySha"
if ($LASTEXITCODE -ne 0) { throw 'Recovery failed; no source switch should be assumed.' }
# Restart PHP so migration-gated storage detection is fresh.
```

## Retirement gate - separate approval required

Retirement is intentionally not automated. Require successful rollout/cutover preservation evidence, current-state backup, current report/credit/hold invariants, compatibility equivalence, confirmed absence of active old writers, and acceptance of losing the direct source-reconstruction recovery path. Reconcile current authority separately from original cutover snapshots: they intentionally diverge after new writes. Keep backups/history according to existing privacy rules.

Only then may a separately approved maintenance step retire `donation_credits`, `donation_report_holds`, and `compatibility_matrix`. No source table has been dropped on either database in this checkpoint. Do not submit a diagram claiming 35 physical tables until retirement has actually happened; the current tested diagram labels all 38.

## Reproduction commands (do not rerun passed checks without a reason)

All database suites below require the existing migrated test target and isolation bootstrap. The prepare fixture command is one-time and must not be repeated after 032. PHP race workers inherit the same exact target and fake transport. No application scheduler is involved.

```powershell
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/donation_completion_db.php
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/offer_chat_db.php
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/donation_consolidation_db.php
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/donation_consolidation_constraints.php
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/donation_consolidation_concurrency.php
& $php -d auto_prepend_file=tests/helpers/consolidation_isolation.php database/recover_donation_sources.php --target=test --rehearse
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite donation_completion_api.ps1 -Port 8003
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite offer_chat_api.ps1 -Port 8003
```
