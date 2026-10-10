# Approved remaining fixes checkpoint — 2026-10-10

Application target confirmed by user: **bloodmatch, 127.0.0.1:3306**. Disposable tests: **bloodmatch_test, 127.0.0.1:3306**, isolated HTTP port **8001**. No agent-run database suite, migration, schedule, real email or historical conversion. Previously completed checkpoints are retained; only changed paths need the new checks below.

Agent read-only metadata review at 2026-10-10T12:22:32Z confirmed application 021 tables ready; 020 rejection_reason and both 022 tables missing. 023 missing/unrecorded, SHA-256 `546182bf9fb7b1187c0d4a9591e65c5ba3806f45c854214986b32582b2504937`. User subsequently approved application 020, structure only/no backfill; SHA-256 `d2669a60ee8826f1db0a06eeafbc16cbae772c125ead1e538e2332e65b21c9d0`. [Current 020-only checkpoint](APPLICATION_020_CHECKPOINT.md); no application DDL was executed. Do not bundle 020 with other pending application migrations.

## Prepared changes and limits

| Fix | Prepared code | Still pending |
|---|---|---|
| 3 Email | Additive 023 donor reservations, atomic shared/donor budget admission, outstanding/accepted/uncertain outcomes, proven-rejection-only donor release; shared worker and TLS SMTP adapter | User-run changed fake worker; application readiness; one exact real-job experiment; scheduling activation |
| 4/5 Expiry/standby | Fixed-target runner expires requests before standby processing; one-minute signed-in-user task, locks and sanitized logs | Application 020/022 readiness, user-run schema/application checks and installation; no deployed rollout |
| 7 Documents | Existing OpenSSL AES-256-GCM, external versioned 32-byte keys, authenticated file/metadata binding, dual/strict staged reading; per-document conversion and journal recovery | Synthetic storage DB test, external keys/backup/ACL verification, authenticated HTTP checks, separately reviewed conversion; deployed storage/root/TLS checks |
| 9 Accuracy | “Donor matches” instead of claiming every retained offer is eligible; raw availability labeled “Donor preference” | Browser presentation observation |
| 10 Admin/export | Existing admin account APIs with review dialog/self-change disabled; existing donation queue/capabilities reused for admin; same-scope aggregate-only CSV and formula protection | Focused API tests and browser interactions |

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

Expected: fixed target/port, review mode/no delivery, explicit ready/missing shapes and ledger entries. Runner review exits zero even when readiness=false: inspect JSON. Application 021 was previously confirmed; **020 and 022 must not be inferred**. Current metadata confirms 020/022 missing: application activation is blocked until scoped DDL and readiness checks succeed; 020 application is now approved but not yet confirmed applied. Do not run the generic all-migrations runner, drop/reset partial objects, or infer applied state from printed SQL.

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

Run **only 020** using [APPLICATION_020_CHECKPOINT.md](APPLICATION_020_CHECKPOINT.md) now. Its approval is resolved; actual application/row-preservation verification is pending user output. Application 022/023 remain prepared and will have separate commands after their prerequisite checks. No combined loop/all-pending runner. Applying 022 records future committed opportunity events without historical timer backfill; 023 adds only reservation structure. Neither is confirmed applied by preparation. Sections 4/5 below remain gated future instructions, not part of the current 020 checkpoint.

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

For real email, wait the 30-minute quiet cutover after 023, keep all other queued-mail writers stopped, and choose one **new, explicitly reviewed** membership-decision or initial Emergency job. Verify its notification/recipient/deadline in read-only SQL; no secrets are queued. Set `EMAIL_WORKER_ENABLED=1` and `EMAIL_WORKER_START_AT_UTC` to the exact `server_time_utc` from the read-only email runner, then restart PHP. Record the numeric job ID below, not a placeholder string:

```powershell
$jobId = [long](Read-Host 'Enter the reviewed new outbox job ID')
if ($jobId -lt 1) { throw 'Invalid job ID.' }
$delivery = & $php database\run_application_email.php --deliver "--job-id=$jobId" | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Real-job check failed; inspect sanitized log.' }
$delivery
if ($delivery.worker.sent -ne 1) { throw 'No accepted delivery; inspect grouping, budgets, leases and outcome before scheduling.' }
```

Grouping may defer an Emergency job until five minutes after creation; a deferred result is not delivery. Accepted means SMTP acceptance, not Inbox proof; check the approved recipient's Inbox/Spam separately. After the single-job result is accepted, automatic email-task commands are:

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
