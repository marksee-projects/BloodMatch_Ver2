> FROZEN: See [the final checkpoint](STAFF_ROLE_FROZEN_CHECKPOINT.md) for unfinished workflow items, rollout holds, commands and recovery. It supersedes earlier full-completion wording below. Product edits are stopped; migration remains unapplied by the agent.

# Combined staff role checkpoint — 2026-10-11

The user's approval supersedes earlier chapter-scoped Officer requirements: `admin` is the only staff backend role, displayed as **Admin/Officer**, with global access. `member` remains separate. Membership verification and activation statuses are not roles and are unchanged.

## Current checkpoint

| Work | Status | Evidence / limit |
| --- | --- | --- |
| Global staff authorization, one portal, management/review/analytics workflows | Implemented in prepared code | Canonical admin gates; legacy Officer URLs/imports delegate to the same workflows. Migration not applied by agent. |
| Member-only donor mutations; no self-verification/report review; active-account checks | Implemented in prepared code | Offline policy checks passed; new user-run API regression prepared. Document files remain authenticated, private, member-owner restricted and audited. |
| 024 conversion and impact preview | Prepared, not applied | Review lists actual affected IDs, display names, emails, chapter associations, activation and membership status, plus new permissions. No actual account query by agent. |
| Member-first User Management, verification column, Staff filter, automatic search, compact card/footer | Implemented in prepared code | Offline rendering and build checks; live interaction remains user-manual. Access-role promotion explicitly identifies global access. |
| Profile municipality | Implemented API/UI | Existing profile permissions retained, except approved staff-role global expansion. Only municipality is added to other-profile responses; no barangay, coordinates or document fields. `/profile` keeps its own full location. No historical location backfill. |
| Confirmation heading, analytics chart controls/print report, simplified audit filters/full-day end date | Implemented in prepared code | CSV retained. Printable report uses an aggregate whitelist and current scoped summary. Print/PDF and live browser layout checks remain manual. UTC reporting boundaries unchanged. |

## Migration impact and boundaries

- Convert every existing `officer` account to `admin`, including deactivated accounts. Deactivated accounts remain deactivated; any later reactivation enables global staff privileges.
- Preserve IDs, chapters, email/password/OTP fields, verification status, timestamps and all other user fields. Do not rematch, reset timers, generate lifecycle alerts, enqueue email, or change historical documents/requests/reports/attempts.
- Append one migration audit event per converted account; historical audit rows and `verification_decisions.officer_id` references remain intact. Historical column names are identifiers, not live role distinctions.
- Retire the `officer` enum value: the role column becomes `ENUM('member','admin')`. APIs reject attempts to assign `officer`.
- New access includes global member management, review of member documents and verification, donation-report review, request/match inspection/cancellation/rematching, dashboard/demand map/analytics/aggregate exports, and redacted audit logs. Request edits remain owner-only; staff donor responses/withdrawals/reports remain read-only.
- Runtime does **not** silently elevate an unconverted `officer`. That account has no staff privileges until user-run conversion. Coordinate migration and runtime rollout during maintenance.
- The data update/audit transaction and role-column DDL cannot be one atomic MariaDB transaction. If interrupted, retain backups and review actual state; do not drop the database or blindly retry. A new reviewed snapshot may be needed to finish the enum/ledger stage. No automatic reverse migration: restoring old authorization semantics is a separate reviewed decision.
- The general migration runner refuses pending 024. Use the reviewed, target-specific helper below; it applies **only 024**.

## 1. Read-only impact previews — run first

From the repository root, MySQL must be running normally on 127.0.0.1:3306. These commands make no persistent writes or SMTP calls.

```powershell
& {
    & 'C:\xampp\php\php.exe' database\review_staff_role_migration.php --target=test
    if ($LASTEXITCODE -ne 0) { throw 'Test impact review failed; stop.' }
    & 'C:\xampp\php\php.exe' database\review_staff_role_migration.php --target=application
    if ($LASTEXITCODE -ne 0) { throw 'Application impact review failed; stop.' }
}
```

Expected targets: **bloodmatch_test:3306**, then **bloodmatch:3306**. Each JSON has `writes:false`, `affected_accounts`, `affected_count`, `newly_granted_permissions`, `sql_sha256` and `review_sha256`. Review all accounts/permissions before applying. Do not infer the affected count from source code or previous backups.

## 2. Backup and apply only 024 to the test database

Prerequisites: impact reviewed; application/PHP test-server writers stopped; background workers/tasks disabled; `.env` retains `SAFETY_AUTOMATION_ENABLED=0` and `EMAIL_WORKER_ENABLED=0`. **Keep MySQL running.** Port 8001 must be free before the later isolated suite. This updates existing test officer roles/enum and appends conversion audits; it is not a disposable-fixture test.

Run the backup/review command first (press Enter if the MySQL root password is blank):

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action BackupReview -Target test
    if ($LASTEXITCODE -ne 0) { throw 'Test backup/review failed; stop.' }
}
```

Expected: completed SQL backup with SHA256, refreshed affected-account impact, and an external **Saved checkpoint JSON** path. Read the refreshed impact before applying. The JSON and SQL backup are saved under your Local AppData BloodMatch\Backups folder, outside the repository/public directory. Existing files are not overwritten.

Then run this separate command; it prompts for the actual saved checkpoint.json path, so no placeholder token/path needs editing:

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Apply -Target test -MaintenanceConfirmed
    if ($LASTEXITCODE -ne 0) { throw '024-only test application failed; retain backups and review partial state.' }
}
```

Expected apply status `applied` (or `already_applied` if previously confirmed), `role_shape:enum('member','admin')`, `recorded:true`, no officers. Final read-only review has `affected_count:0`, `writes:false`, `recorded:true`. Full user-row preservation is checked by the PHP helper; no other migrations are applied. The wrapper validates the fixed target, backup hash, reviewed SQL hash and account-snapshot hash; account changes invalidate the apply step. Review mode itself does not apply a migration.

## 3. Disposable test regression — user only

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -PhpPath 'C:\xampp\php\php.exe' -DbPort '3306' -Suite staff_role_api.ps1
    if ($LASTEXITCODE -ne 0) { throw 'Combined staff regression failed; stop before application migration.' }
}
```

Target: **bloodmatch_test:3306**, isolated PHP **127.0.0.1:8001**. Requires 020–024 and seeded chapters/location references. Creates three synthetic users, one request, one legacy-offer match and one pending report; exercises cross-chapter/global access, obsolete-role denial, member/staff filters, coarse-profile privacy, inactive/self restrictions and reasoned report rejection. Staff self-review API assertions are not substitutes for real donations. No real email; test endpoints must leave the SMTP attempt ledger unchanged.

Expected `Combined staff APIs: ... passed, 0 failed`, wrapper exit 0, and exact fixture-cleanup/server-restoration lines. The suite deletes only its validated users/request and cascade children; audit and attempt history remain. If ownership differs, cleanup stops and retains fixtures. Do not delete unrelated users or reset ledgers. If interrupted, inspect retained fixture identities before cleanup; no blanket cleanup command is authorized. Prior verified worker/enqueue/encryption/recovery suites need no repeat. Older phase scripts/logs with chapter-scoped Officer expectations describe the superseded model; they are not this checkpoint's regression commands. The legacy `remaining_ui_api.ps1` suite name delegates to this canonical staff suite.

## 4. Application migration ? after the test result passes

Target **bloodmatch, 127.0.0.1:3306** only. Review its separate affected-account list before application. Keep PHP/application writers stopped, MySQL running, and safety/email flags/tasks off.

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action BackupReview -Target application
    if ($LASTEXITCODE -ne 0) { throw 'Application backup/review failed; stop.' }
}
```

Review the displayed accounts/permissions and retain the SQL backup/hash and application checkpoint.json path. Then run this separate command, entering **that application checkpoint.json path**, not the test path:

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Apply -Target application -MaintenanceConfirmed
    if ($LASTEXITCODE -ne 0) { throw '024-only application failed; retain backups and investigate.' }
}
```

Expected the same ready enum/ledger/zero-officer result as the test target. The helper verifies that all existing user fields except officer-to-admin role values are unchanged. It does not modify historical documents/requests/reports/SMTP attempts or send role-change emails. Conversion audit entries are new; existing audit records remain.

No application disposable fixtures, new schedules, real email, historical file conversion or other migrations at this checkpoint. Do not delete backups/recovery folders or reverse roles as cleanup. Restart the normal application PHP server only after verification, then refresh/sign in for the manual checks below.

## 5. Manual browser checks after application migration

Refresh/sign in as a converted staff account. Verify one Admin/Officer portal; an old Officer bookmark redirects there; chapter filters can select other chapters but do not restrict staff authority. User Management defaults to Members, shows membership status separately, searches while typing, and has centered bottom pagination. Staff filter shows Admin/Officer; no self-management buttons work.

Check another permitted member profile: municipality appears if saved; response omits barangay, coordinates, DOB, phone and documents. Own profile retains its location selector/display. If the value remains empty, inspect the saved location through the normal profile API or select it through own-profile editing; no automatic backfill is authorized.

Check confirmation controls, basic/Advanced audit filters (end date includes late-day events), and analytics chart controls. Print / Save PDF opens the browser dialog: choose Save as PDF; check selected UTC dates/chapter scope, full multi-page tables, and absence of contacts/documents. CSV remains available. Do not create real donations, send emails or reactivate schedules solely for these UI checks.

## Source verification map

| File(s) | Change | Distinctive string | Found? |
| --- | --- | --- | --- |
| AuthMiddleware / WorkflowLockService / verification and donation services | Canonical staff, global scope, protected self/active/donor rules | `Staff accounts can only view donor responses` | Yes |
| MembershipVerificationController / api.php / App.jsx | One staff workflow; legacy redirects/delegates | `MembershipVerificationController`, `Legacy bookmarks` | Yes |
| 024 SQL / review_staff_role_migration.php / StaffRoleMigration | Impact, checked conversion, preservation, enum retirement | `admin.user.role_consolidated`, `review_sha256` | Yes |
| UsersView / ProfilePage / MemberProfileController | Member-first management, live search, coarse municipality | `role_group: 'member'`, `municipality_name` | Yes |
| AnalyticsView / AnalyticsPrintReport / AuditFilterToolbar / AuditDateRange | Chart controls, aggregate PDF, simpler filters, full-day bounds | `Print / Save PDF`, `Advanced filters` | Yes |
| staff_role_offline.php / staff_role_api.ps1 / management_ui_offline.mjs | Offline and user-run focused regressions | `Combined staff APIs`, `assertPreserved` | Yes |
