# Feed order and expired-request renewal — 2026-10-19

The user reported a newly posted Routine request below old Emergency/Urgent posts and a generic renewal error.

- Home now orders eligible compatible open requests by `created_at DESC, id DESC` before pagination. This explicitly replaces Home's previous urgency-first ordering for this user request. Urgency labels/filters, donor ranking, eligibility, access checks and notification policies are unchanged. Editing a deadline does not change the original publication time.
- Expired renewal called `MatchService::generateForRequest` with only one argument despite three required arguments. It now passes the new post ID, generation bump, `request_created` trigger and owner ID, matching normal creation. The existing transaction, linked successor, original expired record and duplicate/ownership/terminal protections remain in place.
- No schema, data, environment, scheduler or email operations were performed.

Verification: 8 new offline renewal/feed checks, 22 existing offline lifecycle checks, existing Home UI checks, PHP syntax checks and diff whitespace checks passed. The renewal harness executes the real controller with isolated collaborators; it does not verify database locking or delivery. The broader `request_ui_static.mjs` suite stops at its unchanged Matches donor-profile assertion (`href="/profile/9"`); no Matches UI code was changed here. Updated the user-run Home database suite's H18 expectation to newest-first; did not execute that suite.

Browser acceptance: a newly published compatible Routine post should appear above older Emergency/Urgent posts within the normal refresh interval. Renew an expired post with a future deadline; expect a new linked open post and matching, with the old post still expired. Retrying renewal must not create a second successor. Existing filters and pagination should continue to work.

Lightweight rerun commands (from repository root):

```powershell
& 'C:\xampp\php\php.exe' tests/request_renewal_offline.php
& 'C:\xampp\php\php.exe' tests/request_lifecycle_offline.php
node tests/home_ui.mjs
```
