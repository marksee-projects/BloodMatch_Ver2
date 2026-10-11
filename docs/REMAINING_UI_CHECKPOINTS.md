# Remaining UI checkpoints — current handoff

**Readability follow-up:** Confirmed donation summary heading has no dash; compact grouped filters, separate download row, prominent count/period/chapter, correct report singular/plural and readable dates replace the earlier dense paragraphs. Pending-queue empty state uses a simple card. CSV/API values and print metadata remain unchanged; offline UI/build checks passed. No additional DB test is required solely for this presentation change; the already prepared new API suite remains pending at its recorded status.

2026-10-11. Agent finished authorized code preparation. No additional migration, dependency, operational schedule, live email or historical-data conversion was needed/performed. Live browser tooling had no connected browser; no browser passes claimed. Keep safety/email automation disabled throughout this checkpoint. Do not start live chat.

## Current status

| Checkpoint | Status | Evidence / remaining work |
| --- | --- | --- |
| CP0 staff foundation | Implemented; focused test target complete | User-run Combined staff APIs 48 passed/0 failed on bloodmatch_test:3306. Application 024 conversion user-reported successful. Do not repeat suite, migration or cleanup. |
| CP1 verification layout | Implemented in prepared code | Offline actual-handler checks passed: explicit decisions, required reason, no-save cancellation, opt-in evidence, duplicate/stale/error safeguards. Live layout/focus check pending. |
| CP2 private document viewer | Implemented in prepared code | Contained native dialog, image zoom, supported native PDF iframe and explicit separate-view fallback, loading/error/retry, MIME/authentication guard and URL cleanup prepared. Fake-fetch checks passed. Actual browser image/PDF/focus lifecycle pending. |
| CP3 confirmed summary/downloads | Implemented in prepared code | Dedicated aggregates by confirmation date; fixed-field CSV/print report, explicit UTC/request chapter, no personal data. Offline dates/export/render/privacy passed. New API/source-count regression and actual CSV/PDF pending. |
| CP4 analytics cleanup/chart | Implemented in prepared code | Requested heading/paragraph changes, requests/units binding and keyboard labels. Nonzero/empty offline rendering passed. Live chart/filter layout pending. |
| CP5 municipality follow-up | Partially implemented / actual account unverified | Existing reference/API/UI path and empty states reviewed. New test-target API checks and application saved-reference diagnostic prepared. Cannot infer missing stored values; no backfill or migration. |
| CP6 verification/rollout | Unverified pending user results | Only new focused test plus the manual checks below remain. Exact final application role-readiness output not supplied. |

## Checks actually run

- `node tests/management_ui_offline.mjs`: passed. Synthetic rendering plus actual decision handlers using mock API, aggregate summary/print privacy, nonzero/empty blood-group chart and existing management protections. Router server-render warnings remain; this does not prove actual browser focus/native validation/navigation.
- `node tests/document_preview_offline.mjs`: passed. Fake fetch only: same-origin credentials, no-store, redirects refused, validated route IDs/MIME, denied/invalid-format outcomes. No actual private documents fetched.
- PHP `tests/remaining_checkpoints_offline.php`: passed. Exact UTC start/exclusive end, calendar/reversed-range errors, CSV field allowlist, totals and formula escaping. No DB/SMTP.
- PHP syntax passed for new summary utility, analytics repository/controller/routes, SecurityHeaders and profile-location review command. PowerShell parser passed for the new suite; it was not executed.
- Production frontend build and `git diff --check` passed. Existing bundle-size warning remains.

## Ordered user-run commands

Run from `C:\Users\PC\OneDrive\Documents\BloodMatch`. XAMPP MySQL must be running. Keep `SAFETY_AUTOMATION_ENABLED=0` and `EMAIL_WORKER_ENABLED=0`; keep installed safety/email tasks and other test/worker writers stopped. No new backup/migration is required merely for these reviewed disposable tests. Retain your existing pre-024 application backup.

### 1. Read-only application role readiness

The conversion was reported successful; obtain its current final readiness only. This does not repeat/apply 024:

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\staff_role_checkpoint.ps1 -Action Preview -Target application
    if ($LASTEXITCODE -ne 0) { throw 'Application readiness review failed; retain backups and stop.' }
}
```

Target **bloodmatch, 127.0.0.1:3306**. Expected writes=false, ready=true, recorded=true, role_shape member/admin, zero remaining officers. If not ready, do not serve strict admin code against Officer records or reapply blindly; send output. Do not replay earlier migrations, worker/email experiments or recovery checks.

### 2. New focused API suite — writes disposable fixtures

Prerequisites: **bloodmatch_test on 127.0.0.1:3306**, canonical member/admin role schema, existing schema/reference data from completed CP0, at least two chapters and one active municipality, port **8001 free**, PHP/MySQL at the paths below, existing runner's root test access. Do not run another suite concurrently.

```powershell
& {
    powershell -NoProfile -ExecutionPolicy Bypass -File tests\run_isolated.ps1 `
        -PhpPath 'C:\xampp\php\php.exe' `
        -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
        -DbPort '3306' `
        -Suite ui_remaining_api.ps1
    if ($LASTEXITCODE -ne 0) { throw 'Remaining checkpoint API test failed; retain the output and stop.' }
}
```

Only **bloodmatch_test:3306**, isolated HTTP 8001. Runner forces SMTP to a closed loopback port. Suite creates two token-owned users, one request/match and synthetic report metadata (including UTC boundary/status cases). It never approves real donations or opens actual document files. Checks staff/member summary/CSV access, source-count reconciliation, confirmation versus request/submission date, full UTC end day, other-chapter exclusion, invalid ranges, private-field absence and saved municipality in own/authorized-other profile APIs.

Expected final `Remaining checkpoints APIs` summary: **zero failed**, exact remaining-UI fixtures removed, email-attempt ledger unchanged, test server stopped/environment restored. No count is claimed in advance of execution. Ownership-checked automatic cleanup removes only its users/request/match/report rows; audits and SMTP attempt history retained. Login/profile reads may leave ordinary synthetic audit/rate-limit history. If cleanup is incomplete, retain output/fixtures and stop; no bulk cleanup or blind rerun.

### 3. Read-only affected profile location diagnostic

Use the affected account's numeric ID from its profile URL, the authorized user-management view, or the signed-in `/api/me` response. This reviews **bloodmatch:3306 only**, with no writes/SMTP and no names/contacts/barangay/coordinates in output:

```powershell
& {
    $affectedProfileId = Read-Host 'Enter the affected account numeric ID'
    & 'C:\xampp\php\php.exe' tests\profile_location_review.php "--user-id=$affectedProfileId"
    if ($LASTEXITCODE -ne 0) { throw 'Read-only location review failed; stop.' }
}
```

Expected interpretation:

- `display_value_available`: stored reference has a municipality label. Compare `/api/profile` -> `profile.location.municipality_name` for self, and authorized `/api/profile/ID` -> `profile.municipality_name` for others, with the About card. Do not paste a full profile response containing private fields.
- `no_saved_location`: reference was not stored on that account; UI correctly uses Municipality not provided. Any correction should be an explicit account edit/review, not automatic population from unrelated records.
- `missing_reference` / `missing_reference_label`: preserve evidence for reference-data review; no inferred municipality, silent backfill or automatic schema repair.

No cleanup needed for either read-only step.

## Manual browser checklist — no need for real decisions/emails

Restart application PHP only after canonical application readiness. Sign in again as Admin/Officer. Use existing documents you are authorized to inspect; do not create a new real account/request or approve/reject an actual member solely for visual testing.

1. **Verification layout:** no dropdown/detail shield/explanatory paragraphs, centered bold requested headings; Reject opens/focuses required reason, Cancel rejection saves nothing and resets donor-card opt-in. Check keyboard and narrow width.
2. **Private preview:** Inspect opens inside the page for ID/consent/donor card. Image fits and zoom/scroll work. Close/Escape returns focus to Inspect; switch members or close/reopen clears previous preview. Actual PDF rendering depends on browser support; a clear separate-view fallback is available. Check loading/error behavior and that unauthorized membership/document URLs still deny access. No public/external viewer is used.
3. **Confirmed summary:** visible separately from pending queue even when queue is empty. Date/chapter changes update counts; empty interval shows a truthful empty state. Confirmed records leave pending queue by status, not deletion. This is a count summary, not a personal donation history list.
4. **Downloads:** CSV totals/daily counts agree with the selected summary. Print / Save PDF includes only the aggregate report, scope/dates and counts, with sidebar/page actions absent. Confirm neither download includes names, contact details, report/match IDs, notes, documents or inferred donated units.
5. **Analytics:** trend heading centered/bold with paragraph removed; blood-group bars show nonzero requested units when the selected interval has demand and zero/empty values otherwise. Keyboard focus exposes request/unit labels. Chart measures requested demand, not confirmed donations or supply.
6. **Municipality:** compare affected account diagnostic, own/authorized-other API municipality value and About display. Empty values use sensible empty state; barangay/coordinates remain absent for other viewers. Record only scenarios actually checked.

Send the new suite final summary/cleanup, application readiness JSON, minimal location diagnostic and manual observations. No unresolved business-rule decision blocks this prepared code. Staff-filter removal and chart removal remain unchosen and unchanged. Historical location corrections, operational schedules/transport rollout and Emergency real-email grouping remain separate work.

## Source verification

| File | What changed | Distinctive string to search for | Found? |
| --- | --- | --- | --- |
| DocumentPreview.jsx / documentPreview.js / related styles | Contained uncached private preview | loadDocumentPreview | Yes |
| VerificationsView.jsx / related styles | Inspect buttons invoke contained preview | previewDocument | Yes |
| SecurityHeaders.php | Local blob image/frame support; no external viewer | frame-src blob: | Yes |
| AnalyticsController.php / AnalyticsRepository.php / routes/api.php | Aggregate confirmed summary and export | confirmedDonationSummary | Yes |
| ConfirmedDonationSummary.php | Strict UTC range and fixed-field escaped CSV | end_exclusive | Yes |
| ConfirmedDonationSummary.jsx / related styles / ConfirmationsView.jsx | Separate summary and printable report; refresh after decisions | total_confirmed_reports | Yes |
| AnalyticsView.jsx / related styles / BloodGroupDemandChart.jsx | Heading cleanup and real API chart binding | data.units | Yes |
| New offline/API/location-review test files | Mock checks, disposable test-target suite and read-only diagnosis | ui_remaining_api.ps1 | Yes |

## Recovery

For this code-only checkpoint, retain/revert just these UI/reporting changes if a regression is found; no database rollback is introduced. Preserve the canonical staff foundation and completed migrations. If the separate application role readiness is inconsistent, keep PHP/tasks off, retain pre-024 backups and follow the paired code/database recovery plan from the staff checkpoint. Never restore old Officer records while serving strict admin-only code.
