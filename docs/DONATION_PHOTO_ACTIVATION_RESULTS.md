# Donation-photo activation and submission results — 2026-10-19

The user explicitly authorized local `.env` activation, GD enablement in the running backend's PHP configuration, a targeted server restart, and focused bloodmatch_test HTTP/database tests with fake recipients and no real email. This supersedes the earlier prepared-only and user-run-only statuses for this scoped work. No schema changes, historical conversions, scheduling or real-email experiments were authorized or performed.

## Activated and verified

- Reused the single prepared LocalAppData key and verified matching protected backup; **no key generated or replaced**.
- Updated only `DOCUMENT_ENCRYPTION_MODE=dual`, `DOCUMENT_KEY_DIRECTORY`, and `DOCUMENT_ACTIVE_KEY_ID` in local `.env`.
- A temporary loopback-only HTTP probe identified `C:\xampp\php\php.ini` as the configuration actually loaded by BloodMatch on port 8000. Enabled its existing GD extension, then restarted only the dedicated `php.exe -S 127.0.0.1:8000 -t backend/public` process, hidden. Apache on 80/443 remained the same process.
- The restarted **HTTP runtime**, not merely CLI, reported `gd=true`, `mode=dual`, `key_ready=true`, and application database `bloodmatch`. The temporary probe was removed. Final application HTTP health/database connectivity passed.
- Private configuration backups: `%LOCALAPPDATA%\BloodMatch\config-backups\photo-20261019-231953` (contains credentials; do not commit/share). Keys/backup ACLs remain current user + SYSTEM. Existing PHP configuration and unrelated `.env` settings preserved.
- Final file-header review: **99 legacy, 0 encrypted files, no required/missing old key IDs** after test-file cleanup. Dual mode preserves legacy reads; no historical upload was converted/deleted.

## Submission fixes and preserved behavior

- Both browser routes (`/api/donation-reports` and `/api/donation-completion`) use the same multipart controller, validation, image normalization, encrypted storage, report transaction, hold and in-app requester notification.
- Identical initial submissions retried with photos return the existing pending report. Comparison uses hashes of normalized private photo bytes, never randomized ciphertext. Exact request/offer context, original occurrence, initial report version and note must match. Different photos/changed versions go through existing correction controls; no silent evidence replacement.
- Unused retry uploads are removed. Validation, authorization and transaction failures remove their staged files; ambiguous cleanup still preserves any committed evidence. One report/hold/notification survives repeat submissions.
- Matches has an immediate in-flight guard and readable errors inside its modal, retaining failed date/photo drafts. Earlier requested minute-only time input and concise label remain.
- Actual occurrence validation still rejects future/pre-response dates. Photos are optional and never confirm donation. Success stays pending requester acknowledgment; disputes/uncertainty/admin review and the original 24h/48h reminder/escalation rules remain.

## Checks actually passed

- **28 isolated HTTP checks** against `bloodmatch_test:3306` through a hidden port-8001 server: real login/CSRF and multipart parsing; photo and photo-free submission; PNG/JPG/WEBP; 3-photo and 5MB boundaries; private encrypted storage/retrieval; requester notification persistence and API visibility; unauthorized access/submission; identical retry without duplicate alert or orphan; changed-proof conflict; future/spoofed/partial-upload failure cleanup; notification persistence failure rolling back report/hold/evidence; successful retry after rollback; legacy owner read without conversion and outsider denial; zero workflow email jobs.
- **35 donation database checks** and **65 offer/chat database checks**, fixture transactions rolled back. These cover acknowledgment/credit/hold atomicity, no self-review, restriction anchors, report versions, late/excess donations, consent and reminder/escalation behavior.
- **45 focused offline UI/form/preview checks** (20 chat-modal + 13 date/status + 12 proof-form/preview), plus the existing Home UI check suite.
- PHP lint and whitespace checks pass. Production build passes (4,740 modules; existing chunk-size advisory).
- HTTP suite fixture users/records and exact owned files cleaned; final read-only checks found **zero photo-test fixture accounts/requests in the application database** and zero remaining HTTP test accounts in the test database. No real emails sent; isolated SMTP forced to closed localhost port 1, and donation workflows queued no email. No test server left on port 8001. Test audit history is retained normally.

## Re-run commands

From the repository root, under the Windows account with access to the existing private key:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite donation_photo_http.ps1
if ($LASTEXITCODE -ne 0) { throw 'Photo HTTP suite failed; preserve output.' }
& 'C:\xampp\php\php.exe' tests/donation_completion_db.php
if ($LASTEXITCODE -ne 0) { throw 'Donation DB suite failed; preserve output.' }
& 'C:\xampp\php\php.exe' tests/offer_chat_db.php
if ($LASTEXITCODE -ne 0) { throw 'Offer/chat DB suite failed; preserve output.' }
```

The DB helpers force the fixed test database and use in-app-only workflow notifications. No migration/apply commands are required for these fixes.

## Remaining manual browser step

Refresh BloodMatch, open the donor's existing request/chat donation action, choose when donation **actually happened** (not a future time), optionally attach suitable proof, and send. Expect pending requester acknowledgment; in the requester account, open the bell/report and review the private proof. Native file/time pickers and visual layout still require this browser check; no artificial donation was created in the application database to perform it.
