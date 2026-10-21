# Demo preparation, cleanup and restore commands

Run from the BloodMatch repository root in PowerShell. The batch is `DEMO_20261022_7C91`; the isolated database is `bloodmatch_demo_20261022_7c91` on `127.0.0.1:3306`. No passwords or tokens are embedded in these commands. Database credentials are read from the existing local environment; demo process overrides do not edit `.env`.

## Existing prepared demo

The demo servers were left running. Open:

- UI: http://127.0.0.1:5174
- Local captured mail: http://127.0.0.1:8026

If stopped, start them with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-system-demo.ps1
```

Startup refuses occupied ports. It records the exact process IDs and start times; the stop command checks both before terminating a process. It does not stop the normal application or installed workers.

## Reusable preparation and verification

The initial seed is transactional; copied reference/schema creation is separate. A committed batch is detected and skipped on rerun. The initial failed encrypted-document attempt rolled back its data; unused dummy files are retained in the manifest. The seed never invokes the legacy general-purpose seed/cleanup scripts.

```powershell
C:/xampp/php/php.exe database/prepare_system_demo.php prepare
C:/xampp/php/php.exe database/demo_recover_existing_access.php
```

Start the demo servers if needed, then finish/check independent examples:

```powershell
node scripts/check-system-demo.mjs
C:/xampp/php/php.exe database/demo_independence.php
node scripts/check-demo-demand.mjs
C:/xampp/php/php.exe database/demo_request_examples.php
C:/xampp/php/php.exe database/demo_copy_timestamp_review.php
C:/xampp/php/php.exe database/finalize_system_demo.php
C:/xampp/php/php.exe database/verify_system_demo.php
node scripts/write-demo-docs.mjs
```

These commands skip recorded API creation/checks to avoid duplicates. Their saved checks describe the prepared snapshot; a skip is not a new retest after live actions. The finalizer saves the current isolated state as `prepared.json`; **do not rerun it after live actions if you want to preserve the original reset point**. To reset the demonstration, stop servers and restore the saved snapshot instead of reseeding from the changed application database. The walkthrough is a maintained document; the manifest and coverage checklist are regenerated from local evidence.

The source schema/reference data is imported unchanged into a new named database. Foreign-key checks are disabled only during that empty-database import and re-enabled afterwards; the final review checks every relationship for orphans. Schema comparison excludes only advancing AUTO_INCREMENT counters. Existing application schema/constraints/triggers are never changed.

## Stop demo servers

```powershell
powershell -ExecutionPolicy Bypass -File scripts/stop-system-demo.ps1
```

This only stops the recorded demo processes. It does not change data or erase captured emails.

## Scoped demo database cleanup — prepared, not executed

After stopping the demo servers, run this only when you want to remove the dedicated demo database:

```powershell
C:/xampp/php/php.exe database/prepare_system_demo.php cleanup DEMO_20261022_7C91
```

The command validates the exact batch/target and drops **only** `bloodmatch_demo_20261022_7c91`. It never deletes application accounts in `bloodmatch`. It retains backups, dummy documents, local mail, private access files and the separate demo key so restoration remains possible. No automatic shared-storage file deletion is provided; exact new/unused dummy file paths are recorded for later reviewed disposal.

## Restore the prepared demo — prepared, not executed

Stop the demo servers first. This replaces only the isolated demo database with the recorded prepared snapshot, discarding subsequent live-demo changes in that database:

```powershell
C:/xampp/php/php.exe database/prepare_system_demo.php restore DEMO_20261022_7C91
powershell -ExecutionPolicy Bypass -File scripts/start-system-demo.ps1
```

The snapshot contains table definitions, rows and original triggers. Generated columns are recomputed by the database. Keep the encrypted dummy document files and demo-only key; a database-only restore cannot reconstruct missing image bytes or encryption keys. The key is under the OS temporary directory, so move it through a separately reviewed secure backup process before clearing temporary files. Captured mail and PHP sessions are separate files and are not rolled back by database restore; log out/in after a reset.

## Nine working-email releases in the application database

The release has already been executed once after you identified the nine addresses. It deactivated the exact test accounts, assigned reserved demo addresses, canceled only their queued mail if pending, and retained passwords and linked history. Default review is read-only:

```powershell
C:/xampp/php/php.exe database/release_demo_emails.php review
```

The original apply command is recorded here for reproducibility; rerunning refuses to overwrite its backup:

```powershell
C:/xampp/php/php.exe database/release_demo_emails.php apply DEMO_20261022_7C91
```

Optional scoped restore, **not executed**:

```powershell
C:/xampp/php/php.exe database/release_demo_emails.php restore DEMO_20261022_7C91
```

Restore refuses if a working address has been reused, if an affected profile changed after release, or if its backed-up mail state changed. It restores only the saved email/status/timestamps and scoped job fields, never overwrites passwords, and appends audit records. Do not restore the release after registering a new owner for one of those addresses. This operation is separate from demo database cleanup/restore.

## Private local evidence

Everything under `backend/storage/maintenance/DEMO_20261022_7C91/` is gitignored. It includes sensitive account backups/access files and captured verification codes. Keep these private; the public deliverables contain IDs/labels/states, not secret contents. Detailed record/file inventories and syntax/API/service results are linked from the manifest. Neither cleanup nor restore has been run as a test.
