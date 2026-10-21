# MAIN database preparation and scoped recovery

Run from the repository root in PowerShell. Target is **bloodmatch / 127.0.0.1:3306**, batch **DEMO_MAIN_20261022_B42E**. These commands never drop or recreate the main database.

## Review / preparation / verification

The transfer has already been applied. Rerunning apply detects the batch and creates no duplicates.

```powershell
C:/xampp/php/php.exe database/prepare_main_demo.php review
C:/xampp/php/php.exe database/prepare_main_demo.php apply DEMO_MAIN_20261022_B42E
C:/xampp/php/php.exe database/finalize_main_demo.php
node scripts/check-main-demo.mjs
node scripts/write-main-demo-docs.mjs
```

Finalization verifies imported IDs/references and prepares private access files. It never resets original passwords. HTTP verification signs into only approved examples/Admin and reads only the listed demo evidence and authorized analytics. The normal application’s audit/presence may continue changing concurrently.

## Local capture interface to MAIN

Servers were left running. UI http://127.0.0.1:5174 ; inbox http://127.0.0.1:8026 . Unlike the original version of these scripts, the starter now selects **bloodmatch**. It applies SMTP/key overrides only to its child process; .env and the normal application's configuration are unchanged.

```powershell
powershell -ExecutionPolicy Bypass -File scripts/stop-system-demo.ps1
powershell -ExecutionPolicy Bypass -File scripts/start-system-demo.ps1
C:/xampp/php/php.exe database/check_main_demo_mail.php
```

The last command is an idempotent local transport smoke check, not a global queued worker or workflow proof. Keep the demo key and existing dummy files; their location is recorded privately. Real document keys/files are not changed.

## Scoped request creation/edit helper

Broad normal request creation/material edits/renewal/rematching can select genuine donors in the shared main database. The local capture helper refuses these broad routes; product feature code and normal application routes remain unchanged. The helper below uses the normal payload validator, fixture repository and per-donor scoped matching, only for requester 85 and confirmed demo donors. It does not prove UI creation/edit works.

For a bounded edit, create a private payload file and run:

```powershell
@{ quantity_units = 3 } | ConvertTo-Json | Set-Content -Encoding ASCII backend/storage/maintenance/DEMO_MAIN_20261022_B42E/edit.json
C:/xampp/php/php.exe database/main_demo_request.php edit 116 backend/storage/maintenance/DEMO_MAIN_20261022_B42E/edit.json
```

For creation, save a complete normal request payload (required_blood_type, quantity_units, facility_name, hospital_id, location_id, urgency, future needed_datetime) as create.json in that same private directory, then use:

```powershell
C:/xampp/php/php.exe database/main_demo_request.php create 0 backend/storage/maintenance/DEMO_MAIN_20261022_B42E/create.json
```

Do not use a global worker or general seeder. Successful bounded live actions are logged in scoped-request-actions.jsonl; they may require reviewed cleanup beyond the initial transfer inventory.

## Scoped cleanup — PREPARED, NOT EXECUTED

Stop the demo interface first. This removes only imported business rows listed in the main batch's created-records.json and restores the backed-up changed fields on example 85. It refuses modified fixtures, intervening changes to those example fields or unrecorded FK-linked business records. It preserves immutable audit/accounting records, dummy files and backups. A later live action may make cleanup require review; the command will stop instead of cascading into unknown history.

```powershell
C:/xampp/php/php.exe database/prepare_main_demo.php cleanup DEMO_MAIN_20261022_B42E
```

## Restore original example fields only — PREPARED, NOT EXECUTED

This restores only the backed-up original profile fields changed on example 85. It leaves imported fixtures in place and refuses intervening edits. It does not reverse donation credit or overwrite any password/Admin row.

```powershell
C:/xampp/php/php.exe database/prepare_main_demo.php restore-examples DEMO_MAIN_20261022_B42E
```

The full private main before.json is an emergency backup, not permission for a global restore over genuine activity. Resetting main fixtures after completed live donations requires reviewed scoped changes; no whole-main-database reset command is provided. The isolated database's old DROP/restore command applies only to that old isolated database and must not be repurposed for main.

The previous nine working-email releases remain independently backed up. Their optional guarded restore is still database/release_demo_emails.php restore DEMO_20261022_7C91; it refuses reused addresses. It has not been executed.
