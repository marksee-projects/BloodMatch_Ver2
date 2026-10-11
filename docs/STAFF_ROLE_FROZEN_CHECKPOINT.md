# Staff role checkpoint - frozen 2026-10-11

This record supersedes any earlier claim of full workflow or rollout completion. Product edits stopped at the user's request. Migration 024 is prepared and **unapplied by the agent**. No database/PowerShell suites, SMTP, scheduling or rollout were run.

## Completed in prepared code

- Canonical staff role `admin`, label Admin/Officer, global authorization and one staff portal. Old Officer routes/imports delegate or redirect to the same authorization, rather than granting legacy officer privileges.
- Member-first management, independent membership-status column, Staff filter, debounced search, filter card and centered footer pagination.
- Municipality only on permitted other-profile API/UI; own full location retained. No barangay/coordinates exposed to other profile viewers, no historical backfill.
- Duplicate local headings hidden, shared analytics filters in the chart card, aggregate-only browser print/Save PDF alongside CSV, basic/Advanced audit filters and full-day UTC date-to bounds.
- Active-account/self-review safeguards retained; member-only legacy donor withdrawal/report guards and accurate staff donor capability added.
- Reviewed-hash migration/helper/wrapper prepared: officer-to-admin conversion, per-account audit, non-role field preservation, enum retirement and external backup checks. General migration runner refuses unreviewed 024.

## Unfinished / rollout blockers

1. **Shared verification UI feature parity:** backend donor-card acceptance remains available, but the shared Admin view submits `accept_donor_card:false` and displays National IDs only. Former Officer donor-card acceptance and supplemental-document review still need merging. Current legacy wrappers do not prove full feature parity.
2. **Notification destinations:** `frontend/src/services/notifications.js` still uses old officer branching, including donation-review links. Canonical staff links need correction.
3. **Old phase suites:** still contain superseded Officer/chapter assumptions and fixtures. Do not run run_all/default full isolated suites or old Officer fixtures after enum retirement. Use the specifically named staff_role_api suite only.
4. **Read-only readiness reporting:** tighten enum/ledger/officer consistency. For now, verify the explicit fields below; do not trust a generic `status:ready` alone.
5. Actual affected-account list, conversion, database/API tests and browser/PDF behavior remain unverified. Own-account saved location has not been inspected; a missing historical value was not invented or backfilled.
6. Minor formatting debt: diff whitespace checking flagged a trailing blank line in migrations/README.md. It was not corrected after the stop request.

No additional business-rule decision is pending. These are approved unfinished work and required user-run evidence. **Hold application migration/rollout until workflow parity is finished and the focused regression passes.**

## Checks actually run

| Check | Result / limit |
| --- | --- |
| PHP syntax | Passed for 33 changed/new paths plus final StaffRoleMigration/CapabilityMatrix changes; no migration script executed |
| staff_role_offline.php | 26 policy/preservation/date checks passed; no DB/SMTP/tasks |
| management_ui_offline.mjs | Management/self-action/confirmation/aggregate-print privacy rendering passed; expected MemoryRouter SSR warning, not live browser proof |
| PowerShell parser | Focused suites and checkpoint wrapper passed; scripts/suites not executed |
| npm.cmd run build | Final build passed; existing >500 kB bundle warning. Initial npm.ps1 command was blocked by execution policy |
| Diff whitespace | Minor README trailing-blank-line warning; no behavioral failure |

## Safe order: prevent pre-conversion Officer lockout

The prepared runtime accepts only admin for staff and has **no pre-conversion compatibility bridge**. If PHP is currently serving this edited workspace, edits may already be active and unconverted officers can lose staff access. Stop that PHP server, or keep a separately retained known pre-consolidation runtime serving until coordinated maintenance. Do not reset/discard the prepared working tree.

1. Finish the frozen feature/link/test gaps before application rollout. Read-only previews may run now.
2. Back up/review/apply only 024 to bloodmatch_test:3306, then run the focused isolated regression. No application conversion yet.
3. For application cutover, let writes finish and stop application PHP with Ctrl+C in its own terminal. Keep safety/email flags and tasks disabled. **Leave MySQL running.** Do not use the application during conversion.
4. Create a fresh external SQL backup and review the application impact. Apply only 024 while PHP remains stopped.
5. Verify member/admin enum, zero officers and exactly one ledger record. Only then serve the completed new runtime, refresh/sign in, and use /admin. IDs, passwords, activation/membership statuses and chapter associations are retained.
6. Keep automation/email off through browser checks. No schedule activation, real email or unrelated migrations at this checkpoint.

This is conversion-before-serving ordering, with coordinated maintenance, not an unattended zero-downtime upgrade. If uninterrupted old-code service is needed, preserve its runtime artifact separately; do not serve the strict new runtime against officer records.

## Exact commands - user only, repository root

### A. Read-only previews (safe now)

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Preview -Target test
    if ($LASTEXITCODE -ne 0) { throw 'Test preview failed; stop.' }
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Preview -Target application
    if ($LASTEXITCODE -ne 0) { throw 'Application preview failed; stop.' }
}
```

Expected bloodmatch_test then bloodmatch, both 127.0.0.1:3306, writes:false. Inspect affected_accounts and newly_granted_permissions. Actual names/counts are not inferred from code. Deactivated officers remain deactivated; reactivation later enables global staff access.

### B. Test backup/review, then separate 024-only application

Prerequisites: reviewed impact, application/test PHP writers stopped, worker/task flags 0, MySQL running normally. The backup wrapper prompts for root's MySQL password; press Enter if blank.

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action BackupReview -Target test
    if ($LASTEXITCODE -ne 0) { throw 'Test backup/review failed; stop.' }
}
```

Expected completed SQL backup, SHA256, refreshed impact and Saved checkpoint JSON path under Local AppData/BloodMatch/Backups, outside the repository. **Review that impact before the next separate command.** It prompts for the exact printed checkpoint.json path; no placeholder editing is needed.

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Apply -Target test -MaintenanceConfirmed
    if ($LASTEXITCODE -ne 0) { throw '024-only test application failed; retain backup and inspect partial state.' }
}
```

Expected applied/already_applied, role_shape member/admin, recorded:true, remaining_officers:0. Only 024 is applied; all non-role user fields are checked for preservation. Audit conversion events are appended; no notifications/email/history correction is performed.

### C. Independent read-only verification of the test target

```powershell
& {
    & 'C:\xampp\mysql\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=root --password --table --database=bloodmatch_test --execute="SELECT DATABASE() AS db,@@port AS port; SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'; SELECT COUNT(*) AS remaining_officers FROM users WHERE role='officer'; SELECT COUNT(*) AS migration_recorded FROM schema_migrations WHERE name='024_consolidate_staff_role.sql';"
    if ($LASTEXITCODE -ne 0) { throw 'Test verification failed; stop.' }
}
```

Expected bloodmatch_test/3306, enum('member','admin'), NOT NULL/default member, remaining_officers=0, migration_recorded=1. Do not infer readiness from the helper's status alone.

### D. Disposable regression - test database only

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -PhpPath 'C:\xampp\php\php.exe' -DbPort '3306' -Suite staff_role_api.ps1
    if ($LASTEXITCODE -ne 0) { throw 'Staff regression failed; stop before application conversion.' }
}
```

Requires 020-024, reference chapters/locations and port 8001 free. Targets bloodmatch_test:3306 with isolated PHP 127.0.0.1:8001. Writes three disposable users, one request/match/report and fixture notifications; exercises global/cross-chapter access, obsolete-role rejection, member/staff filtering, coarse-profile privacy, active/self restrictions and reasoned report rejection. SMTP attempts must remain unchanged. Expected zero failures and exact cleanup/server-restoration lines. Owned fixture rows/cascade children are removed; audit/attempt history is retained. If ownership checks fail or execution is interrupted, stop and review retained fixtures; no blanket cleanup is authorized. Do not repeat previous worker/encryption/recovery tests.

### E. Application backup/review/apply - HOLD until blockers and test result resolved

Keep PHP/worker writers stopped and MySQL running. These commands target **bloodmatch:3306**, not the test database.

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action BackupReview -Target application
    if ($LASTEXITCODE -ne 0) { throw 'Application backup/review failed; stop.' }
}
```

Review the separately displayed application accounts/permissions and retain its external backup/checkpoint path before this separate command:

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Apply -Target application -MaintenanceConfirmed
    if ($LASTEXITCODE -ne 0) { throw 'Application conversion failed; retain backup and keep PHP stopped.' }
}
```

```powershell
& {
    & 'C:\xampp\mysql\bin\mysql.exe' --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=root --password --table --database=bloodmatch --execute="SELECT DATABASE() AS db,@@port AS port; SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'; SELECT COUNT(*) AS remaining_officers FROM users WHERE role='officer'; SELECT COUNT(*) AS migration_recorded FROM schema_migrations WHERE name='024_consolidate_staff_role.sql';"
    if ($LASTEXITCODE -ne 0) { throw 'Application verification failed; keep PHP stopped.' }
}
```

Expected bloodmatch/3306 and the same explicit enum/zero-officer/one-ledger values. Only after these values and workflow parity pass should the completed runtime be served and staff refresh/sign in. Browser checks: portal/bookmark redirects, member/staff filters/live search, self/active restrictions, municipality privacy, date-to late-day events and printed PDF scope/multi-page layout. CSV stays available. Do not enable email/safety schedules at this step.

## Recovery if conversion or rollout fails

- Keep application PHP/tasks off and all backups/reviews/logs retained. No manual blanket role update, database drop, generic migration runner or blind retry.
- Determine state using read-only previews and explicit verification. Data/audit conversion is transactional, but ALTER TABLE/ledger stages commit separately; a failure may leave converted accounts even with old enum/no ledger.
- Preserve the failed/current state before remediation. Validate the pre-024 SQL backup in a **separate recovery instance** and review a fresh role/account snapshot before any retry.
- Any rollback must pair a reviewed pre-024 database state with the matching pre-consolidation runtime. Do not mix old role-assignment code with the narrowed enum or new strict code with officer records. Retain conversion audit evidence; do not erase new history blindly.
- After live writes resume, do not overwrite them with an older whole-database dump without a reviewed recovery plan. No automatic reverse migration was prepared.
- Existing code checkpoints: pre-consolidation 8b62807; prepared checkpoint 28b42e8. Preserve any additional edits/ignored config/document files/external keys separately; a Git archive is not a backup of those files. No repository reset is necessary.

Detailed prior source map: [consolidation checkpoint](STAFF_ROLE_CONSOLIDATION_CHECKPOINT.md). The frozen gates above take precedence over its earlier implementation-completion wording.

## Complete changed-file grouping

Primary grouping is used below; UserRepository and the staff safeguards also span UI/role work. Product files reflect checkpoint 28b42e8. This freeze only updates documentation.

### Role consolidation

```text
backend/routes/api.php
backend/src/Controllers/Admin/MembershipVerificationController.php
backend/src/Controllers/Admin/UserAdminController.php
backend/src/Controllers/Analytics/AnalyticsController.php
backend/src/Controllers/Analytics/DemandMapController.php
backend/src/Controllers/CompatibilityController.php
backend/src/Controllers/DocumentController.php
backend/src/Controllers/DonationReportController.php
backend/src/Controllers/Officer/AuditLogOfficerController.php
backend/src/Controllers/Officer/ChapterUsersController.php
backend/src/Controllers/Officer/OfficerDashboardController.php
backend/src/Controllers/Officer/OfficerVerificationController.php
backend/src/Controllers/RequestsController.php
backend/src/Middleware/AuthMiddleware.php
backend/src/Repositories/AnalyticsRepository.php
backend/src/Repositories/UserRepository.php
backend/src/Services/AccountLifecycleService.php
backend/src/Services/MatchService.php
backend/src/Services/NotificationService.php
backend/src/Services/ProfileViewPolicy.php
backend/src/Services/RequestLifecycleService.php
backend/src/Services/StaffRoleMigration.php
backend/src/Services/VerificationService.php
database/migrations/024_consolidate_staff_role.sql
database/migrations/README.md
database/review_staff_role_migration.php
database/run_migrations.php
docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md
docs/STAFF_ROLE_CONSOLIDATION_CHECKPOINT.md
docs/ai_context/CONTEXT.md
frontend/src/App.jsx
frontend/src/components/Hero.jsx
frontend/src/components/PortalLayout.jsx
frontend/src/components/PrivacyConsentModal.jsx
frontend/src/pages/MatchesPage.jsx
frontend/src/pages/admin/AdminLayout.jsx
frontend/src/pages/admin/views/DashboardView.jsx
frontend/src/pages/officer/OfficerLayout.jsx
frontend/src/pages/officer/views/AuditLogsView.jsx
frontend/src/pages/officer/views/ConfirmationsView.jsx
frontend/src/pages/officer/views/DashboardView.jsx
frontend/src/pages/officer/views/VerificationView.jsx
scripts/staff_role_checkpoint.ps1
tests/remaining_ui_api.ps1
tests/staff_role_api.ps1
tests/staff_role_offline.php
```

### Prior approved UI work

```text
backend/src/Controllers/MemberProfileController.php
frontend/src/components/AnalyticsPrintReport.jsx
frontend/src/components/AnalyticsPrintReport.module.css
frontend/src/components/AuditFilterToolbar.jsx
frontend/src/components/AuditFilterToolbar.module.css
frontend/src/pages/ProfilePage.jsx
frontend/src/pages/admin/views/AnalyticsView.jsx
frontend/src/pages/admin/views/AnalyticsView.module.css
frontend/src/pages/admin/views/AuditLogsView.jsx
frontend/src/pages/admin/views/ConfirmationsView.jsx
frontend/src/pages/admin/views/ConfirmationsView.module.css
frontend/src/pages/admin/views/UsersView.jsx
frontend/src/pages/admin/views/UsersView.module.css
frontend/src/styles/global.css
tests/management_ui_offline.mjs
```

### Additional focused fixes

```text
backend/src/Controllers/MatchesController.php
backend/src/Repositories/AuditLogRepository.php
backend/src/Services/CapabilityMatrix.php
backend/src/Services/DonationService.php
backend/src/Services/MatchResponseService.php
backend/src/Services/WorkflowLockService.php
backend/src/Utils/AuditDateRange.php
```

Documentation-only freeze: docs/STAFF_ROLE_FROZEN_CHECKPOINT.md, docs/STAFF_ROLE_CONSOLIDATION_CHECKPOINT.md, docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md.
