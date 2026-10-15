# Restore the application database into the test database

User-run only. Confirmed targets: `bloodmatch` and `bloodmatch_test`, 127.0.0.1:3306. The former contains the designated administrator (account 3); the current test database does not. This procedure replaces the existing test dataset and preserves the application database.

1. Stop website/backend servers and scheduled mail, expiry and retention workers. Leave MySQL running. Keep workers stopped while the test copy contains real imported contact data. Before importing events, record `SHOW VARIABLES LIKE 'event_scheduler';` in phpMyAdmin. If ON, disable it during this offline maintenance using `SET GLOBAL event_scheduler=OFF;` on the confirmed server; this pauses database events for the whole server. If you cannot disable it, stop before import. Do not re-enable it until imported event definitions and their database targets have been reviewed.
2. From the repository root, run each backup separately and stop if either fails:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch -Action Backup
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Backup
```

3. Each successful command prints `BACKUP` followed by its SQL path in `backend/storage/maintenance`. Keep both files. Confirm each exists and is nonempty; successful export alone does not prove restorability.
4. Open phpMyAdmin on the same 127.0.0.1:3306 server. Select **bloodmatch_test**, confirm the selected database name, and use its Operations tab to drop that test database. Recreate **bloodmatch_test** using utf8mb4. Do not drop or import over **bloodmatch**.
5. Select the newly empty **bloodmatch_test** and import the fresh **bloodmatch** SQL backup from step 2. These helper dumps are single-database dumps, without the `--databases` option. Before import, check that the actual SQL file has no `CREATE DATABASE bloodmatch` or `USE bloodmatch` directive directing writes to the application database; stop if it does. Preserve triggers/routines/events included in the dump. Do not enable any imported scheduled event until its target and side effects have been reviewed. A failed/partial import must be investigated before repair or cleanup.
6. After import succeeds, run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Review
```

7. Expect the restored snapshot to have 115 users, the active verified designated admin at ID 3, and the same table/trigger data as the application backup. Compare the private review's table row counts and fingerprints against the application snapshot before treating restore as verified. Matching user counts alone is insufficient. Investigate unexpected differences rather than proceeding with cleanup.
8. Once restore is verified, continue the handoff's **test-only** repair, fresh review, cleanup and migrations 025–027. Stop if `cleanup_allowed` is false. Keep the printed credential file private. The isolated test runner disables real SMTP; ordinary application startup must not accidentally send mail from the cloned data.

No step here has been executed by the agent. Account cleanup, schema repair and migrations are separate later operations. See [the implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md).
