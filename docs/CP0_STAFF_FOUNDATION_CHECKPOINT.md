# CP0 — Staff foundation checkpoint

2026-10-11. CP0 code and focused test-target checkpoint complete. User reports Combined staff APIs: 48 passed, 0 failed; SUITES REPORTING: 1, ASSERTIONS PASSED: 48, FAILED: 0. Target is bloodmatch_test at 127.0.0.1:3306 through the fixed isolated runner. Do not rerun this completed suite or cleanup. CP1 code preparation may proceed; live UI checks and exact application post-conversion JSON remain separate unverified evidence, not a reason to repeat completed test-target work.

The instructions and intermediate failures below are retained as checkpoint history, not new required runs. Application-only 024 conversion is user-reported successful (all goods); no exact final JSON was supplied. No further migration is currently requested. Browser tooling had no connected browser, so live UI behavior remains unverified and will be carried into the shared verification-layout/viewer checks.

Latest follow-up: application-only 024 conversion is user-reported successful; exact final JSON was not supplied. Corrected regression then reported 27 passed/1 failed at donor-card fixture setup: blood type was set without its required source. Setup now explicitly uses `A+`, `self_reported`, blood_type_verified=0; accepting the card through the API must change provenance to donor_card/verified. Reviewed remaining direct fixture writes against the relevant schema constraints; no schema change. PowerShell parsing passed; database suite not executed by agent. User screenshot confirms automatic fixture cleanup and server/environment restoration. Rerun only the corrected focused API suite, not migration or cleanup.

Latest user-run read-only target evidence: `bloodmatch_test` at `127.0.0.1:3306` is ready, 024 recorded, role enum `member/admin`, zero officers. Do not reapply test migration. `bloodmatch` at the same host/port is `review_required`, 024 not recorded, enum `member/officer/admin`, 12 remaining officers. Approved user-run application-only backup/review and conversion are next; application conversion and corrected API regression remain pending. Earlier target ambiguity is resolved.

Follow-up user result: one preview reports `review_required`, 12 remaining officers, but the supplied excerpt omits the database/target. Do not infer which database needs conversion. Focused test reported 25 passed/1 failed because its direct deactivation fixture update omitted the existing required `deactivated_at`; exact fixtures were removed and the server/environment restored. Corrected the fixture to set/clear that timestamp with account status, and added an enum/zero-officer preflight before fixtures. PowerShell syntax parsing passed; corrected database suite has not been run by agent. No production constraint/schema change is needed, and no cleanup repetition is required.

## Completed code

- Shared Admin/Officer verification detail includes National ID, parental-consent and donor-card metadata and authenticated inspection links. Donor-card acceptance is optional, initially unchecked, available only with a card and a recorded blood type, and resets when changing decisions/members. It records administrative provenance, not medical confirmation. Duplicate submissions are guarded; pending-state and backend no-self/active/document protections remain.
- Staff notifications open canonical `/admin/verifications` and `/admin/confirmations` destinations. Members do not receive staff destinations.
- Migration review reports ready only when the role enum is exactly member/admin, migration 024 is recorded and zero officer records remain. Inconsistent recorded states exit nonzero rather than claim readiness.
- The obsolete chapter-scoped full suite and known obsolete Officer suites are blocked before fixture creation through the shared runner. Their historical files are retained; they have not all been ported. Use the current focused suite below.
- Focused API regression now includes cross-chapter supplemental metadata, donor-card acceptance and duplicate decision rejection. Its document fixtures are metadata only; it creates/reads no document files. No real transport is used.

CP1 decision-layout cleanup, CP2 contained document preview and all later checkpoints remain untouched.

## Checks actually run

- PHP syntax: migration reviewer and staff migration service passed.
- `tests/staff_role_offline.php`: 32 passed, without database/SMTP/tasks.
- `tests/cp0_notification_links.mjs`: passed.
- `tests/management_ui_offline.mjs`: passed, including supplemental-document rendering and unchecked/conditional acceptance. Server rendering produces the existing router layout-effect warnings; this is not browser interaction evidence.
- PowerShell syntax parsing: `run_all.ps1`, `run_isolated.ps1`, `staff_role_api.ps1` passed; scripts/suites were not executed.
- Frontend production build passed, with the existing bundle-size warning.
- Browser inventory returned no connected browser. No live UI checks performed; no retry required.

## 1. Read-only target readiness

Run from the repository root with XAMPP MySQL running. Keep safety/email automation disabled. These commands do not apply migrations or send email.

```powershell
& {
    $ErrorActionPreference = 'Stop'
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Preview -Target test
    if ($LASTEXITCODE -ne 0) { throw 'Test role review failed; stop.' }
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Preview -Target application
    if ($LASTEXITCODE -ne 0) { throw 'Application role review failed; stop.' }
}
```

Targets: `bloodmatch_test` and `bloodmatch`, both `127.0.0.1:3306`. Expected for each: `writes:false`, `ready:true`, `status:ready`, `recorded:true`, `remaining_officers:0`, `role_shape:enum('member','admin')`. Do not reapply 024 if ready. An inconsistent result is a blocker; retain backups and send the output, without automatic repair.

## Conditional migration step — only if genuinely still pending

No new migration is introduced. Reuse an already completed verified 024 conversion. If Preview instead reports `review_required`, stop PHP writers and workers/tasks, keep MySQL running, and keep `SAFETY_AUTOMATION_ENABLED=0` / `EMAIL_WORKER_ENABLED=0`. Do not serve strict admin code against unconverted officer records. Create a fresh external backup and inspect affected accounts/new permissions before applying only 024. Choose exactly the target reported as pending:

```powershell
$cp0Target = 'test' # Use 'application' only for the pending application target.
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action BackupReview -Target $cp0Target
if ($LASTEXITCODE -ne 0) { throw 'Backup/review failed; stop before Apply.' }
```

Review the impact output and retain the printed checkpoint JSON path and backup hash. Then run separately:

```powershell
$cp0ReviewFile = Read-Host 'Paste the complete checkpoint.json path for this same target'
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Apply -Target $cp0Target -ReviewFile $cp0ReviewFile -MaintenanceConfirmed
if ($LASTEXITCODE -ne 0) { throw '024 conversion/verification failed; retain backups and stop.' }
```

Apply performs a read-only post-review too. Recheck step 1 before reopening PHP and refresh staff sessions. Never use a test checkpoint for application. On partial failure, keep PHP/tasks off and retain every backup: MariaDB DDL commits separately. Do not drop/recreate databases or copy table folders. Follow the paired code/database recovery procedure in [the frozen checkpoint](STAFF_ROLE_FROZEN_CHECKPOINT.md); restoring old officer records requires restoring compatible old code as well. Take another backup before any recovery write.

## 2. Focused API regression — writes disposable test fixtures

Prerequisites: step 1 ready; test schema/references available (including at least two chapters and one active municipality); port 8001 free; PHP/MySQL executables at the paths below; test MySQL root access as expected by the existing runner. No live application database is targeted by this suite.

```powershell
& {
    $ErrorActionPreference = 'Stop'
    powershell -NoProfile -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -PhpPath 'C:\xampp\php\php.exe' -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -DbPort '3306' -Suite staff_role_api.ps1
    if ($LASTEXITCODE -ne 0) { throw 'CP0 API checkpoint failed; stop before CP1.' }
}
```

Target: `bloodmatch_test` on `127.0.0.1:3306`, isolated API port 8001. Expected: Combined staff APIs reports zero failures; new supplemental/donor-card/duplicate checks pass; SMTP attempt ledger unchanged. Runner forces SMTP to a closed loopback port and restores the environment/stops its server.

Cleanup is automatic and ownership-checked: exact fixture document metadata, requests and users are removed; audit/email-attempt history is retained. No document file is created/deleted. If cleanup reports incomplete, retain the output and fixtures; do not manually bulk-delete or rerun blindly. Do not repeat the completed recovery/worker/enqueue/encryption suites.

## 3. Short manual UI check

Use an authorized staff account and existing pending-member detail; inspecting does not require making a real decision.

- Supporting documents appear when submitted, with correct labels/metadata and authenticated inspection links. New-tab inspection is expected until CP2.
- With donor card and blood type present, the acceptance checkbox starts unchecked. Switching to rejection hides it; returning to approval resets it. Changing the member resets it too.
- Non-pending details cannot submit a new decision. Staff verification/report notifications open the appropriate admin view.

Actual decision persistence and duplicate prevention are covered by the disposable API suite. Do not approve/reject real members merely for a layout check. Send readiness JSON, API final summary/cleanup output and manual observations; only then clear CP0 and move to CP1.
