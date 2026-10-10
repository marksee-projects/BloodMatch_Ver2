# Approved remaining fixes checkpoint — 2026-10-10

Application target confirmed by user: **bloodmatch, 127.0.0.1:3306**. Disposable tests: **bloodmatch_test, 127.0.0.1:3306**, isolated HTTP port **8001**. No agent-run database suite, migration, schedule, real email or historical conversion. Previously completed checkpoints are retained; only changed paths need the new checks below.

Agent read-only metadata review at 2026-10-10T12:22:32Z confirmed application 021 tables ready; 020 rejection_reason and both 022 tables missing. 023 missing/unrecorded, SHA-256 `546182bf9fb7b1187c0d4a9591e65c5ba3806f45c854214986b32582b2504937`. User subsequently approved and reported the [020-only checkpoint](APPLICATION_020_CHECKPOINT.md) passed, existing reports preserved/no backfill; its SHA-256 is `d2669a60ee8826f1db0a06eeafbc16cbae772c125ead1e538e2332e65b21c9d0`. No agent DDL was executed. Do not repeat 020 or infer application 022/023 readiness. Next: section 2's test-target donor-cap checkpoint; later synthetic storage/API checks are independent and remain unrun.

Subsequent result: user supplied **65 database fake-worker checks passed** and exact fixtures/synthetic attempts cleaned, audit retained. Accepted on the fixed test target; mandatory 023 shape/ledger checks support its test application. No numeric exit/full transcript separately supplied. Do not repeat test 023 application, fake suite or cleanup. Next commands are only section 2's document_encryption_checkpoint.php and remaining_ui_api.ps1 isolated run; application 022/023 and real delivery remain separate.

API follow-up: first admin CSV assertion failed (4 passes; the two-failure tally double-counted that one assertion). User reported exact cleanup and server/environment restoration. Added extensionless export URL for the implicit PHP front controller, preserving the .csv filename/scoped alias; failure diagnostics now include status/content-type and count once. Original HTTP response was not supplied, so source finding is not runtime proof. Rerun only remaining_ui_api.ps1 through run_isolated.ps1. Do not repeat 020/023/fake worker or cleanup. Document result remains unverified unless its existing output is supplied. [PHP built-in server dotted-path report](https://bugs.php.net/bug.php?id=74061).

Corrected API result now supplied: **20 passed, 0 failed**, wrapper 20 assertions/0 failed. Accept this checkpoint; the previous rerun instruction is superseded. Do not repeat API/fake-worker/020/023 test application or cleanup. Document-storage final JSON remains requested if already run; no repeat solely for evidence. Browser controls, application 022/023, operational activation, real email and conversion remain separate.

Document-storage result now supplied: **8 PASS checks**, JSON bloodmatch_test:3306, exit_code=0, remaining_owned_files=[], smtp=false, historical_conversion=false. Transaction fixture rolled back and exact synthetic files/key cleaned. This supersedes the prior “not run yet” state. All three new test checkpoints (fake worker 65, APIs 20, storage 8) are accepted; do not repeat them/cleanup. Next application step is [022-only review/backup/apply/verify](APPLICATION_022_CHECKPOINT.md). External application keys, actual authorized HTTP reads, historical conversion, application 023 and schedules remain separate.

## Prepared changes and limits

User confirmed membership job 1 was **received in Inbox**. Combined with scoped SMTP/outbox success, membership-decision delivery checkpoint complete; no repeat/resend/reset. General queue remains disabled. Next Emergency grouping review needs a controlled donor email and two controlled requester emails; requested those identities before preparing any data-changing experiment. No Emergency proof/general activation is inferred.

Membership experiment result supplied: exact job 1, verification.decision, claimed=1/sent=1, failed/cancelled/deferred/lost=0, exit_code=0 at 2026-10-10T15:28:42Z on bloodmatch:3306. General queue enabled=false. SMTP acceptance/outbox completion confirmed; recipient Inbox/Spam receipt still requested. Do not rerun/reset/clean this job. Current experiment commands below are completed records. No Emergency donor/grouping/automatic delivery proof is inferred.

Current operational result: safety task now has three successful scheduled logs, including consecutive completions 61 seconds apart; checkpoint complete, no repeat/polling. Email review found officeradmin24@gmail.com job 7 stale (unchanged), and current membership job 1 for controlled fireshorts395@gmail.com/account137. User explicitly confirmed that exact job/recipient. [Single membership experiment](MEMBERSHIP_EMAIL_EXPERIMENT.md) uses --experiment with EMAIL_WORKER_ENABLED=0, max one claim, explicit recipient and no other jobs. Membership delivery/inbox result pending; Emergency grouping/donor delivery still needs a separate controlled experiment. General email scheduling remains disabled.

Safety result now supplied: manual completion expired 24/standby 0 at 2026-10-10T15:06:39Z, exit 0; one scheduled completion at 15:07:47Z with zero counts/exit 0. Task Ready/PT1M/Interactive/Limited/IgnoreNew, LastTaskResult 0, last local run 23:07:44 and next 23:08:44. Accept manual expiry and one automatic dispatch; second scheduled log still pending. Do not repeat manual run/install/migrations/tests/cleanup. Capture only the next natural scheduled log and task verification. No due-donor transition is established by standby=0; email/key/conversion/browser checks remain separate.

Application 023 follow-up now passed by supplied apply/read-only ready/recorded output, matching target/SHA and terminal pass; backup path supplied, contents not agent-inspected. Agent SELECT-only readiness reviews confirm all required shapes/ledger on bloodmatch:3306. Effect preview at 2026-10-10T14:44:57Z: safety enabled=false, due_open_requests=24, opportunity_timer_candidates=0; email cutover_configured=false, smtp=false. No processing/SMTP/task action executed. Next user-only step is section 4's **Safety** activation/manual run/preview/install/verify, keeping email off. Use invocation=scheduled log entries for scheduled evidence. Do not repeat completed migrations/tests/cleanup.

Application 022 follow-up: supplied apply and final read-only verification confirm donor_response_state/donor_opportunities ready, recorded=true, bloodmatch:3306, matching SHA, historical_backfill=false and terminal pass. Retained backup path supplied; contents not independently inspected. No repeat. Next: [application 023-only checkpoint](APPLICATION_023_CHECKPOINT.md), retaining all completed test results. Operational/key/conversion/browser checks remain gated and separate.

| Fix | Prepared code | Still pending |
|---|---|---|
| 3 Email | Additive 023 donor reservations, atomic shared/donor budget admission, outstanding/accepted/uncertain outcomes, proven-rejection-only donor release; changed fake worker now user-passed; shared worker and TLS SMTP adapter prepared | Application readiness; one exact real-job experiment; scheduling activation |
| 4/5 Expiry/standby | Fixed-target runner expires requests before standby processing; one-minute signed-in-user task, locks and sanitized logs; application 020 user-passed and 022 shape/ledger confirmed | User-run application processing/schedule checks and installation; no deployed rollout |
| 7 Documents | Existing OpenSSL AES-256-GCM, external versioned 32-byte keys, authenticated file/metadata binding, dual/strict staged reading; synthetic storage DB test user-passed; per-document conversion and journal recovery prepared | External keys/backup/ACL verification, authenticated HTTP checks, separately reviewed conversion; deployed storage/root/TLS checks |
| 9 Accuracy | “Donor matches” instead of claiming every retained offer is eligible; raw availability labeled “Donor preference” | Browser presentation observation |
| 10 Admin/export | Existing admin account APIs with review dialog/self-change disabled; existing donation queue/capabilities reused for admin; same-scope aggregate-only CSV and formula protection; focused APIs user-passed | Browser interactions |

Donor cap compares **reserved_at** to DB UTC now minus 30 minutes. A reservation commits immediately before transport with the shared attempt. Outcome changes do not restart the window. Outstanding/accepted/uncertain rows count; released rows do not. Accepted outcomes survive failed outbox completion; unknown replies/false/exception remain uncertain. SMTP rejection before DATA or an explicit 4xx/5xx DATA-END reply proves non-acceptance. Stable Message-ID does not guarantee exactly-once inbox delivery.

023 contains IDs/outcome/timestamps only, references the committed shared attempt, and changes no historical rows. Shared attempts are never refunded. Fake-suite cleanup removes only its synthetic reservations before its synthetic attempts; never reset an existing ledger. Direct OTP/reset retain the shared gateway and no queued secrets. Real worker rejects reserved test/local/invalid addresses before reservation, rechecks current events/eligibility, and preserves five-minute grouping, retries, bounded processing and protected budgets. Automatic delivery requires an explicit cutover timestamp and ignores earlier queued jobs; no historical bulk resend/backfill. A 30-minute quiet cutover after 023 is required before real delivery.

## 1. Read-only reviews (run first)

From the repository root in PowerShell, with XAMPP/MySQL running:

```powershell
$php = 'C:\xampp\php\php.exe'
& $php database\review_operational_schema.php --target=test --migration=023
if ($LASTEXITCODE -ne 0) { throw 'Test schema review failed; stop.' }
& $php database\run_application_safety.php
if ($LASTEXITCODE -ne 0) { throw 'Application safety review failed; stop.' }
& $php database\run_application_email.php
if ($LASTEXITCODE -ne 0) { throw 'Application email review failed; stop.' }
& $php database\review_operational_schema.php --target=application --migration=022
if ($LASTEXITCODE -ne 0) { throw 'Application 022 review failed; stop.' }
& $php database\review_operational_schema.php --target=application --migration=023
if ($LASTEXITCODE -ne 0) { throw 'Application 023 review failed; stop.' }
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Preview
if ($LASTEXITCODE -ne 0) { throw 'Schedule preview failed; stop.' }
```

Expected: fixed target/port, review mode/no delivery, explicit ready/missing shapes and ledger entries. Runner review exits zero even when readiness=false: inspect JSON. Application 020 is now user-reported passed and 021 was previously confirmed. Application 022/023 remain unconfirmed; application activation is blocked on their scoped checkpoints. Do not rerun completed 020 evidence or use the generic all-migrations runner, drop/reset partial objects, or infer applied state from printed SQL.

## 2. Test-target 023 application and changed fake checks

Stop other test DB/mail writers. Backup is outside the repository and retained:

```powershell
$testBackup = Join-Path $env:TEMP ('bloodmatch-test-before-023-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& 'C:\xampp\mysql\bin\mysqldump.exe' -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers bloodmatch_test "--result-file=$testBackup"
if ($LASTEXITCODE -ne 0) { throw 'Backup failed; stop.' }
$review = & $php database\review_operational_schema.php --target=test --migration=023 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '023 review failed; stop.' }
& $php database\review_operational_schema.php --target=test --migration=023 --apply "--sha256=$($review.sql_sha256)"
if ($LASTEXITCODE -ne 0) { throw '023 apply failed; stop and inspect partial objects.' }
& $php database\review_operational_schema.php --target=test --migration=023
if ($LASTEXITCODE -ne 0) { throw '023 verification failed; stop.' }
powershell -ExecutionPolicy Bypass -File tests\email_worker.ps1 -PhpPath $php
if ($LASTEXITCODE -ne 0) { throw 'Changed fake-worker checkpoint failed; stop.' }
& $php tests\document_encryption_checkpoint.php
if ($LASTEXITCODE -ne 0) { throw 'Synthetic encrypted-storage checkpoint failed; stop.' }
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -Suite remaining_ui_api.ps1 -PhpPath $php -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -DbPort 3306 -Port 8001
if ($LASTEXITCODE -ne 0) { throw 'Focused API checkpoint failed; stop.' }
```

Expected: 023 ready/recorded; **65 changed fake-worker checks** (not yet user-run); **8 synthetic document checks** with zero remaining_owned_files/exit 0; focused API zero failures, exact disposable users removed, server stopped/environment restored. Fake worker requires the test attempt ledger already empty; if not, stop—never erase it to make the suite run. The synthetic document test uses an ephemeral external fake key and one new synthetic document; rolls back DB rows and removes exact files/key. No historical document conversion or real SMTP. API fixtures use existing roles/APIs only; audit and attempt history retained. Keep backup files. On fixture cleanup failure preserve output and report the exact owned paths/IDs; do not rerun setup or bulk-delete.

**Report these results before enabling real queued delivery or installing application tasks.** There is no reason to repeat prior enqueue/safety/legacy fixture cleanup suites merely for this checkpoint.

## 3. Separate application migration checkpoints

020 is user-passed; 021, 022 and 023 shapes/ledger are confirmed by supplied/read-only output. Do not repeat them or use a combined all-pending runner. 022 records future committed opportunity events without historical timer backfill; 023 adds only reservation structure. Operational execution/key setup/conversion/browser evidence in sections 4/5 remains separate.

## 4. User-only operational activation (gated)

After new results/readiness are accepted, set `SAFETY_AUTOMATION_ENABLED=1` in .env and restart the PHP server. Leave `EMAIL_WORKER_ENABLED=0` until the reviewed real-job check is ready. Review the effect: existing overdue OPEN requests expire, unfinished offers close, pending reports remain for explicit rejection; latest valid overdue opportunities may create standby. These are application writes.

```powershell
& $php database\run_application_safety.php --run
if ($LASTEXITCODE -ne 0) { throw 'Application safety run failed; inspect sanitized log.' }
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Install -Kind Safety
if ($LASTEXITCODE -ne 0) { throw 'Safety task install failed; stop.' }
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Verify -Kind Safety
Get-Content logs\application-safety.jsonl -Tail 5
```

Observe two actual scheduled completions approximately one minute apart while signed in/awake. Zero counts prove dispatch only. `IgnoreNew` plus the runner file lock protects overlaps; each lifecycle event uses existing DB locks/transactions. The wrapper is hidden, uses no task credentials and does not manually start a task. This is local-only; no signed-out/deployed availability claim.

The **current confirmed experiment** is membership-decision job 1 only, using [MEMBERSHIP_EMAIL_EXPERIMENT.md](MEMBERSHIP_EMAIL_EXPERIMENT.md). Keep EMAIL_WORKER_ENABLED=0. Do not use the earlier broad-enable instructions. A separate initial Emergency donor experiment must be reviewed before claiming grouping/donor delivery or activating general queued sending. For the current confirmed job, use:

```powershell
$delivery = & $php database\run_application_email.php --experiment --deliver --job-id=1 --recipient=fireshorts395@gmail.com | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Real-job check failed; inspect sanitized log.' }
$delivery
if ($delivery.worker.sent -ne 1) { throw 'No accepted delivery; inspect grouping, budgets, leases and outcome before scheduling.' }
```

Accepted means SMTP acceptance, not Inbox proof; check the confirmed recipient's Inbox/Spam separately. Do not automatically install/enable the general email task after membership success. The following task commands are **future gated instructions**, only after the separate Emergency experiment and explicit activation settings/current cutover have been reviewed:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Install -Kind Email
if ($LASTEXITCODE -ne 0) { throw 'Email task install failed; stop.' }
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Verify -Kind Email
Get-Content logs\application-email.jsonl -Tail 5
```

Email scheduling ticks once a minute but **keeps the five-minute grouping window**. Automatic jobs created before cutover are ignored; no bulk historical resend. Never reset attempts/reservations. Review/cancel/resend of older jobs needs separately scoped instructions.

To stop/remove owned application tasks, set the corresponding enabled flags to 0, wait for any running task to finish, then:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Remove -Kind All
powershell -ExecutionPolicy Bypass -File scripts\manage_application_tasks.ps1 -Action Verify -Kind All
```

Removal retains logs/data and refuses to kill running transactions. Keep application backups and audit/accounting history.

## 5. Document keys, backup and staged conversion

Use the existing PHP/OpenSSL implementation: AES-256-GCM, fresh 12-byte nonce, fixed 16-byte tag, key ID in the authenticated envelope, and authenticated binding to stored name/MIME/plaintext size. [PHP OpenSSL encryption](https://www.php.net/manual/en/function.openssl-encrypt.php), [tag validation](https://www.php.net/manual/en/function.openssl-decrypt.php). No new library/service has been installed. Keys are raw random 32-byte files, never .env key contents, DB rows, repository or public files. Default `legacy` leaves rollout off; `dual` encrypts new uploads and reads old/plain + encrypted files; `strict` refuses unconverted plaintext.

Create keys only after the synthetic storage check passes. Choose private local storage outside OneDrive/web roots:

```powershell
$keyDir = Join-Path $env:LOCALAPPDATA 'BloodMatch\document-keys'
New-Item -ItemType Directory -Path $keyDir -Force | Out-Null
$windowsUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
icacls $keyDir /inheritance:r /grant:r "${windowsUser}:(OI)(CI)F" 'SYSTEM:(OI)(CI)F'
if ($LASTEXITCODE -ne 0) { throw 'Key ACL setup failed; stop.' }
& $php database\document_keys.php "--directory=$keyDir" --review-directory
if ($LASTEXITCODE -ne 0) { throw 'Key directory review failed; stop.' }
$newKey = & $php database\document_keys.php "--directory=$keyDir" --create | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Key creation failed; stop.' }
$newKey
```

Only metadata is printed. Set `DOCUMENT_KEY_DIRECTORY` to the returned external directory, `DOCUMENT_ACTIVE_KEY_ID` to the generated ID and `DOCUMENT_ENCRYPTION_MODE=dual` in .env. Ensure the actual PHP service identity can read the private key directory; never grant Everyone/web-public access. Restart PHP. Back up keys to an existing protected/encrypted external folder; verify location **before copying**:

```powershell
$keyBackupDir = (Read-Host 'Full path to an existing protected external key backup folder').Trim()
& $php database\document_keys.php "--directory=$keyBackupDir" --review-directory
if ($LASTEXITCODE -ne 0) { throw 'Backup location unsafe; stop.' }
Copy-Item -LiteralPath (Join-Path $keyDir ($newKey.active_key_id + '.key')) -Destination $keyBackupDir
& $php database\document_keys.php "--verify-backup=$keyBackupDir"
if ($LASTEXITCODE -ne 0) { throw 'Key backup verification failed; stop.' }
```

Expected backup_matches=true. Protect the backup's ACL/encrypted storage, separately from document backups. Retain **all referenced old key IDs** when rotating; losing a key makes its documents unrecoverable. Recovery: restore the matching `.key` files to the external directory with the same IDs/ACLs, run key readiness/backup verification, then test authenticated owner/staff retrieval. Never reconstruct keys from the DB or replace a missing old key with a new one.

Before any historical conversion, upload/retrieve a disposable document in dual mode and verify original bytes, Content-Length, MIME, no-store headers and existing owner/officer chapter/admin restrictions. No new grants. Then stop document writers and review **one exact existing document**:

```powershell
$documentId = [long](Read-Host 'Enter one document ID for read-only review')
$docReview = & $php database\convert_document_encryption.php "--id=$documentId" | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Document review failed; stop.' }
$docReview
```

This is not conversion approval evidence. Separately review identity/SHA and an external protected encrypted-document backup folder. The prepared write command (user only, after that review) is:

```powershell
$encryptedBackupDir = (Read-Host 'Full path to existing protected external encrypted-document backup folder').Trim()
& $php database\convert_document_encryption.php "--id=$documentId" --convert "--sha256=$($docReview.file_sha256)" "--backup-directory=$encryptedBackupDir" --maintenance-confirmed
if ($LASTEXITCODE -ne 0) { throw 'Conversion stopped; preserve encrypted backup/recovery journal.' }
& $php database\convert_document_encryption.php "--id=$documentId"
```

Conversion validates the owned reference/hash/MIME/size, saves only an **encrypted** backup and recovery journal, verifies roundtrip, atomically replaces that one file, and audits conversion; DB document metadata is unchanged. It never mass-converts or plaintext-backfills. On failure after replacement, preserve the journal/key and use the separately guarded recovery command only after review:

```powershell
& $php database\convert_document_encryption.php "--id=$documentId" --recover "--backup-directory=$encryptedBackupDir" --maintenance-confirmed
```

Recovery authenticates the current envelope and hashes against the journal, records a missing conversion audit once, and completes the journal; it does not decrypt files in place. Do not delete keys/backups/journals on error. Enable `strict` only after all intended conversions and authenticated reads are verified. Deployed web-root isolation/ACL/TLS, full disaster recovery and conversion failure experiments remain unverified by this preparation.

## Remaining manual UI checks

Admin Users: search/paging, review/cancel/apply role/chapter/status changes on disposable accounts, disabled own-account actions, officer/member denial. Admin Confirmations: queue, server-driven disabled self/invalid confirmation, reasoned rejection. Analytics: CSV matches displayed date/chapter scope, officer stays in chapter, no personal fields. Donor list: truthful matches/preference labels, centered description, filters preserve existing ordering. Prior browser evidence remains only the specific user-reported Emergency invalidation-notification scenario.
