# Accepted-offer chat and donation proof handoff

Source prepared. User reported migration 031 applied and ready on **bloodmatch_test:3306** on 2026-10-19, SHA `3a5e0589b7f5625472c5d19dd574dfc2c4243060cb2bdd8f7a7761a4d2d4f66a`: recorded, four new tables, two new columns, one new index, zero duplicate credit groups. Application-target rollout is not yet reported. Photo readiness remains blocked by `gd:false` and `encrypted_key_ready:false`; testing without optional photos can proceed. [Approved plan and ratification](MATCHING_TO_DONATION_REVISED_PLAN.md). All five choices were approved, including messaging during donor restrictions. Actual credit/restriction reversal rules remain separately proposed and unimplemented.

Verified lightweight results: all 16 changed/new PHP files lint; 100 counted offline checks (offer/proof 19, donor completion 32, workflow 17, date/status 13, offer/proof UI 12, consent expiry 7), plus existing fake-fetch document-preview checks pass. PowerShell suite syntax was parsed without executing it; frontend production build passes with the existing chunk-size advisory. None of these results replaces the user-run SQL/HTTP/photo/browser/concurrency gates below.

## Implemented source

- Offer to donate preserves eligibility/ranking/substitutions and existing matching notifications. After 031, offering does not create a second message request. Request-owner Accept & chat rechecks current eligibility and open deadline, creates/reuses the private pair conversation and grants existing chat consent atomically at all urgencies.
- Separate accepted contexts identify exact request/match/offer cycle and snapshot the real donor response time. Withdrawal/re-offering cannot reuse stale acceptance; accepted consent survives closure/withdrawal and donor holds/cooldown/non-response standby. Existing active/verified account and participant checks remain. No per-member blocking feature was added.
- Full and floating chats have a persistent context selector/card. Donor I donated submits the canonical report; requester Ask donor to submit details is a deduplicated in-app prompt with no report/hold/credit/timer. Still arranging changes coordination only. Donation didn't happen records an attributed statement before reporting; requester disputes and donor clarification signals retain pending reports/holds. Completed records link to private correction requests, not reversal.
- Same canonical report and version are used in chat, Matches, report/history screens. New reports bind to the exact accepted context when available; legacy unbound reports are not backfilled. Other offer cycles show a reference/link rather than providing the wrong report's acknowledgment actions. Message edit/delete code never mutates these records.
- One confirmed match is one platform tracking unit. Accepting/chatting/photos count zero. Existing per-match credit uniqueness remains; a current-read service guard and unique donor/occurrence index prevent the same donor/UTC occurrence being credited on another match. This does not medically identify donations or infer duplicates solely from similar photos or different claimed times; disputed physical-event identity still needs staff clarification. Existing historical unknown occurrence dates/credits are not fabricated or merged.
- New normal report forms have separate date/time controls with displayed Manila timezone and optional guided proof upload, without donor description. Seconds are supported to preserve occurrence precision. Existing validation/anchors/intervals remain; historical notes and clarification/review text stay.
- Up to three JPG/PNG/WEBP photos per current report version, 5 MB each. Server checks MIME/raster/dimensions (20 MP resource ceiling), re-encodes pixels to remove metadata, and reuses encrypted protected storage. No plaintext fallback for proof. Prior photos remain privately available when a newer set replaces them; exact report version binds the evidence manifest. Proof uploads do not erase existing disputes or restart the original timers.
- Proof file/list access is limited to exact report participants and canonical non-conflicted reviewers; private preview reuses authenticated no-store streaming. No normal chat attachment, public storage path, or new email channel. Participant correction requests and staff responses remain private and audited, with no automatic reversal of credit, fulfillment or restrictions. Open correction requests remain in the staff queue with age/reference time.
- Evidence/correction retention reuses the resolved-record year horizon and existing donor-window safeguards, keeps unresolved report holds/inquiries, guards linked request/audit pruning and coordinates protected-file/metadata cleanup. No historical cleanup is part of applying 031.

## Changes that require user-run verification

PHP lint, offline policy/authorization/date/preview/cipher checks and production build are lightweight checks only. They do not prove MariaDB execution, real parallel transactions, actual HTTP multipart/GD/encrypted-key readiness, browser layout or filesystem retention. The DB suite below is prepared, not executed by the agent. It uses synthetic metadata, not actual proof files; actual image upload/preview is a required browser gate. No migrations, database/PowerShell suite, schedule install/start, real email or actual .env edit was performed for this implementation.

Migration 030/test evidence and the existing verified Safety schedule are not repeated. Do not run generic all-pending migrations. New actions are in-app only. Existing email jobs/settings/budgets and the separate membership-email experiment are unchanged.

## 1. Review and back up test target

From repository root in a fresh PowerShell terminal, keep MySQL running. Pause HTTP and scheduled writers and let active jobs finish before DDL; do not kill a transaction. 031 uses auto-committing DDL, so partial failure requires inspecting the retained backup, not blindly retrying.

```powershell
$php = 'C:\xampp\php\php.exe'
$dump = 'C:\xampp\mysql\bin\mysqldump.exe'
$review = & $php database/review_offer_chat_migration.php --target=test | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '031 review failed.' }
$review | ConvertTo-Json -Depth 5
```

Expect `prepared_not_applied`, no new schema markers, `duplicate_credit_groups:0`. If already `ready`, skip the apply block. If partial schema or duplicates exist, save output and stop; no automatic data repair or rollback is included. The helper requires recorded 030 and reports photo readiness without revealing key material.

```powershell
if ($review.status -ne 'prepared_not_applied' -or $review.duplicate_credit_groups -ne 0) { throw 'Stop and review the schema/duplicate state before applying.' }
$backupFolder = 'C:\BloodMatchBackups'
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null
$testBackup = Join-Path $backupFolder ('bloodmatch_test-before-031-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$testBackup" bloodmatch_test
if ($LASTEXITCODE -ne 0) { throw 'Test backup failed.' }
if (!(Test-Path $testBackup) -or (Get-Item $testBackup).Length -eq 0) { throw 'Backup missing or empty.' }
& $php database/review_offer_chat_migration.php --target=test --apply "--reviewed-sha=$($review.sha256)" --backup-confirmed
if ($LASTEXITCODE -ne 0) { throw '031 stopped. Save the complete database error and do not retry partial DDL.' }
& $php database/review_offer_chat_migration.php --target=test
```

Use the normal interactive `-p` on mysqldump if required. Retain and verify the recoverable backup. Ready means recorded 031, four new tables, two tracked new columns and the donor/occurrence unique index. No historical acceptance, descriptions, files, occurrence dates or credits are modified/backfilled. Deploy source/schema together; new coordination/proof endpoints report migration-required until 031 is ready. Ordinary legacy matching/consent stays compatible before activation.

## 2. User-run focused suites

```powershell
& $php tests/offer_chat_db.php
if ($LASTEXITCODE -ne 0) { throw 'Offer/chat DB regression failed. Save the full output.' }
& $php tests/donation_completion_db.php
if ($LASTEXITCODE -ne 0) { throw 'Donation completion regression failed. Save the full output.' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite offer_chat_api.ps1
if ($LASTEXITCODE -ne 0) { throw 'Offer/chat API guards failed. Save the full output.' }
```

The two PHP suites fix their target to bloodmatch_test:3306, require the existing canonical active test admin, and roll back synthetic fixture writes. Auto-increment values may advance. The new suite covers actual offer/withdraw/reoffer, all urgency acceptances, legacy gate reuse, exact contexts, independent donors, prompts without holds/reports, hold/cooldown messaging, proof metadata/privacy/versioning, credit and duplicate occurrence prevention, correction requests/responses and staff queue visibility. The existing completion suite keeps timer/late/excess/hold/idempotency checks; occurrence fixtures now use distinct seconds so they do not accidentally claim the same donation. The 13-guard PowerShell suite uses the existing isolated server wrapper with SMTP blocked; it does not claim authenticated multipart or race proof.

## 3. Proof storage and browser gate

The review output reports `photo_processing.gd`, `encrypted_key_ready`, `upload_max_filesize`, and `post_max_size`. New proof uploads need GD and the existing external DocumentCipher key configuration. If absent, configure the already approved protected-document setup before testing files; do not generate/commit keys inside the repo or bulk-convert historical documents to make this feature work. `dual` mode can write encrypted files while reading historical legacy files; `strict` requires the existing conversion policy. See the existing document encryption handoff/converter; do not run a historical conversion implicitly. No change to the real .env was made by this implementation.

For three 5 MB photos, configure the PHP runtime that actually serves HTTP with `upload_max_filesize` at least 5M, `post_max_size` at least 16M plus any deployment overhead, and `max_file_uploads` at least 3. Restart the user-managed PHP/Apache process after a runtime configuration change. The file chooser's accept list alone is not server validation. Without GD/key readiness, reports can still be submitted without optional photos and photo attempts fail clearly.

Use the test backend setup in [the existing browser handoff](DONATION_COMPLETION_HANDOFF.md). Test with separate profiles and designated test accounts:

1. At Routine/Urgent/Emergency, offer -> no new message request or donated unit -> requester Accept & chat -> both can read/send without another consent approval. Existing authorized conversation/messages are reused. Test inactive/unverified/restricted account denials and outsider IDs.
2. Accept two donors, and two requests for one donor pair. Switch contexts in full/floating chat. Verify exact request/cycle/report, private photo access and distinct histories; previous-cycle cards cannot acknowledge another cycle's report. Withdraw/reoffer and try an old Accept click.
3. Requester prompts donor; verify no report/hold/credit/timer. Still arranging and Donation didn't happen before reporting leave outcome unconfirmed and other restrictions untouched. Terminal contexts cannot be reactivated. No-report silence awards nothing.
4. Donor submits actual time with zero photos, then repeat with valid photos on another fixture. Inspect actual stored-file encryption through the existing protected-document tooling; verify preview, EXIF removal, wrong MIME/oversized/four-photo rejection and failed-upload cleanup. No photo appears as a chat attachment or in public/aggregate/email output.
5. Requester acknowledges exact report version. One match counts one unit; request requiring two stays open after one. Add/replace proof before acknowledgment and reject stale acknowledgment. Existing disputes remain in review; original report timestamp and 24h/48h flags remain unchanged.
6. Test disagreement/uncertainty, donor dispute, staff clarification/photo request, rejection with hold, explicit reasoned release isolation, and completed-record correction request plus staff response. No automatic credit/fulfillment/restriction reversal. No admin self-review.
7. With a pending hold or confirmed cooldown/non-response standby, eligible existing conversation participants can still send/read/report. Account deactivation/verification revocation must still block chat. Report handling remains independently authorized.
8. Close/expire/fulfill before completing a pending donation; preserve conversation/report/evidence and late/excess credit without reopening terminal requests. Race acceptance against withdrawal/expiry and proof update against acknowledgment/admin decision using separate sessions; exactly one coherent outcome, no repeated credit/alerts. Check lost-response retry behavior.
9. Verify mobile layout, keyboard/focus, context selection while a form is open, report/history freshness and that editing/deleting ordinary messages cannot modify donation data. Retention needs a separately scoped test rehearsal using the existing read-only retention preview first; never run unreviewed cleanup on application data.

Do not simulate a genuine donation on real member accounts just to test controls; application confirmations persist as history/holds/restrictions. Designated synthetic accounts and retained test backups isolate these experiments.

## 4. Conditional application rollout

Only after supplied test/browser results pass, pause writers and apply the identical reviewed migration to the application target with a separate recoverable backup. Keep existing schedules/email scope/cutovers unchanged.

```powershell
$appReview = & $php database/review_offer_chat_migration.php --target=application | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Application review failed.' }
$appReview | ConvertTo-Json -Depth 5
if ($appReview.status -ne 'prepared_not_applied' -or $appReview.duplicate_credit_groups -ne 0 -or $appReview.sha256 -ne $review.sha256) { throw 'Review application schema/duplicates/SHA; do not apply.' }
$appBackup = Join-Path $backupFolder ('bloodmatch-before-031-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& $dump -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers "--result-file=$appBackup" bloodmatch
if ($LASTEXITCODE -ne 0) { throw 'Application backup failed.' }
if (!(Test-Path $appBackup) -or (Get-Item $appBackup).Length -eq 0) { throw 'Application backup missing or empty.' }
& $php database/review_offer_chat_migration.php --target=application --apply "--reviewed-sha=$($appReview.sha256)" --backup-confirmed
if ($LASTEXITCODE -ne 0) { throw 'Application DDL stopped. Retain full output and inspect partial schema before retrying.' }
& $php database/review_offer_chat_migration.php --target=application
```

Restart normal HTTP runtime and resume only the existing approved writers after ready checks, then repeat participant checks on designated application fixtures. Do not reinstall the already verified Safety task. The separate DonationFollowup processor must be reviewed/activated by the user under the prior handoff if it is not already running; code and offline 24/48h checks do not prove scheduled delivery. No new scheduler or real-email experiment is required for accepted-offer/proof actions themselves.

## Checkpoint

All product choices needed for this implementation are approved. Actual reversal remains deliberately outside implementation. Remaining gates are user-run migration, MariaDB/API results, encrypted photo/runtime/browser/race verification and any separately reviewed retention rehearsal. Save complete failures; no blind partial-schema retry, historical cleanup, credit merge, new email channel or repeated broad audit. Continue fixes from the returned focused results.
