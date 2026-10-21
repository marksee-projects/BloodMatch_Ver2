# Local MariaDB recovery checkpoint — 2026-10-21

Status: **database recovered; HTTP services responsive**. User subsequently requested inspection and post-restoration grant comparison, authorizing the focused recovery. Successful login with the user's credentials has not been tested.

## Confirmed cause and evidence

The frontend responds at `http://localhost:5173` (IPv6 loopback listener). MariaDB originally accepted TCP on 3306 without providing its protocol greeting. The bundled client failed before authentication with ERROR 2013, while PHP requests queued on the development server at 8000 and phpMyAdmin timed out.

After the user explicitly approved pausing workers, stopping stalled processes, taking a cold backup and diagnostic startup, MariaDB's console reported:

```text
Table '.\mysql\db' is marked as crashed and last (automatic?) repair failed
Fatal error: Can't open and lock privilege tables
Aborting
```

Inspection of the cold `mysql/db.MAD` found MariaDB startup log text dated October 18 in the data file, including its record page. This establishes damaged privilege-table contents; the process or event that wrote that text has not been established. Do not infer application-code involvement or reset passwords.

## Completed safeguards and checks

- Preserved configuration and a preliminary full file snapshot under `C:\xampp\mysql\recovery-backups\20261021_101409`.
- Exported the two scheduled task definitions to the same directory, disabled both and stopped their running workers: `BloodMatch-Application-Safety`, `BloodMatch-Application-MembershipEmail`.
- Stopped the verified stalled PHP backend and MariaDB processes; preserved a second full `cold-data` copy while MariaDB was stopped.
- Diagnostic MariaDB startup exited on the privilege-table error. Its console output is retained as `diagnostic-stderr.log` in the recovery directory.
- Tested normal, safe and extended Aria recovery on three separate copies. Each discarded all three damaged rows. **None of those results was installed.**
- Found an intact historical `mysql.db` in `C:\xampp\mysql\data_before_recovery_20261011-124623\mysql`. Copied its three files into `validated-db-restore` under the recovery directory. Offline read-only Aria validation passed with three records and both indexes checked.
- Offline checks also reported checksum damage in `mysql.proxies_priv`; this is a separate internal table requiring assessment before any claim that restoring `mysql.db` alone is sufficient. Other transaction-ID warnings arose with the control file deliberately ignored and are not, by themselves, evidence of corruption.
- Compared application schema files against the cold backup: `bloodmatch` 81 files, `bloodmatch_test` 83 files, `bloodmatch_dev` 36 files; **zero changed files** in those directories. This is file-preservation evidence, not a fresh SQL integrity test. Normal diagnostic startup can update shared engine/log metadata.

## Earlier approval checkpoint (superseded by subsequent authorization)

MariaDB and the PHP development backend are stopped. Both scheduled tasks remain disabled. The frontend and Apache were not stopped. No live privilege table was replaced, no application migration ran, and no real email experiment was performed.

Automatic approval review rejected replacing the live `mysql.db` with the historical backup: older database-level grants could omit newer grants or restore stale access. Prior approval covered service recovery/diagnosis, not that security-sensitive restoration. Obtain explicit approval for the exact restoration before proceeding. `mysql.global_priv` (database accounts/passwords) and all application tables are outside the proposed replacement.

After approved table recovery: validate remaining privilege tables, start MariaDB with authentication enabled, verify normal readiness and database targets with read-only SQL, restart PHP with the existing application launcher, and check bounded HTTP responses for the auth/CSRF endpoints. Do not claim successful authenticated login without testing it. Keep scheduled tasks paused during acceptance; report their final state explicitly and do not silently enable processing/email delivery.

Tool reference: MariaDB documents offline checks/recovery and cautions against using `aria_chk` against running tables in its [aria_chk documentation](https://mariadb.com/docs/server/clients-and-utilities/aria-clients-and-utilities/aria_chk).

## Completed recovery and grant comparison

The user subsequently asked to inspect current grants, finish restoration and check for missing grants. An isolated copy of the cold backup was started with TCP networking disabled and a unique local named pipe, initially bypassing grant loading solely to read the damaged system tables. The original server was never started with authentication bypassed. The inspection clone was shut down after use.

Readable pre-restoration rows were captured privately under the recovery directory; password/authentication material was not printed in the report. Empty grant tables produced no TSV file in the client, so their comparison uses successful zero-row query results rather than nonexistent file hashes.

| Grant category | Before | After on recovered main server | Comparison |
|---|---|---|---|
| `mysql.global_priv` | Four accounts: root at localhost, 127.0.0.1 and ::1; pma at localhost | Same four records, including authentication material | Sorted SQL export hashes match exactly |
| `mysql.tables_priv` | Two pma grants on phpmyadmin.pma__pdf_pages and pma__table_coords | Same two records | Sorted SQL export hashes match exactly |
| `mysql.columns_priv` | Zero readable rows | Zero rows | Counts unchanged |
| `mysql.procs_priv` | Zero readable rows | Zero rows | Counts unchanged |
| `mysql.roles_mapping` | Zero readable rows | Zero rows | Counts unchanged |
| `mysql.db` | Unreadable: crashed table; header recorded three rows but their contents were overwritten | Three validated historical rows: anonymous test/test\\_% grants and pma@localhost on phpmyadmin | Restored from October 11 backup; exact pre-corruption comparison is impossible |
| `mysql.proxies_priv` | Unreadable checksum error; offline index metadata recorded one live row | Offline repair recovered one root@localhost proxy row, retained after installation | Recovery checked on a copy first; unreadable original SQL contents cannot be compared directly |

The proxy repair recovered the existing table, rather than restoring the older proxy backup or installing a factory table. Only the prepared `mysql.db` and repaired `mysql.proxies_priv` files were installed. `mysql.global_priv`, application tables, application credentials and configuration were not replaced. No GRANT/REVOKE statements or application migrations were executed. The restored historical test grants are reported explicitly; no unrequested security hardening was performed.

Before touching the real server, the prepared recovery was tested in the isolated clone with authentication enabled. `CHECK TABLE` returned OK for db, proxies_priv and global_priv. All four accounts' SHOW GRANTS were saved privately. After installation on the real server, the same checks passed, readable account/table grant exports matched their original captures, and SHOW GRANTS matched the validated clone exactly.

**Conclusion:** no missing grants were found among the readable pre-restoration records. It is not possible to prove that the restored database-level grants match the already overwritten original rows. No application-specific database grant was present in the readable restored mysql.db backup; the existing local root accounts retain global privileges, and the application's configured connection succeeds.

## Final service verification and limitations

- MariaDB PID 25436 recorded **ready for connections** on port 3306 with normal authentication; root@localhost authenticated successfully.
- The existing `scripts/start_main_backend.ps1` verified `bloodmatch:3306` and started PHP on 127.0.0.1:8000 (observed PHP PID 22596).
- Backend `/api/csrf`: HTTP 200, approximately 0.057 seconds.
- Backend `/api/auth/me` without a login session: expected HTTP 401, approximately 0.069 seconds.
- Frontend-proxied `/api/csrf` at localhost:5173: HTTP 200, approximately 0.041 seconds.
- phpMyAdmin: HTTP 200; subsequent response-content check found no fatal error, execution timeout or MySQL-login failure text.
- Read-only counts found 22 application users and 24 test users. These counts establish access, not a full application integrity audit.
- Both BloodMatch scheduled tasks remain deliberately disabled during recovery acceptance. They have not been re-enabled; automated safety/membership-email processing remains paused.
- No successful application login was attempted with user credentials, no real email experiment was run, and no application code was changed. The next user action is to reload BloodMatch and sign in normally.

The console and data-file evidence establishes system-table corruption, but does not establish which process caused the log text to enter db.MAD. Keep the backups and diagnostic output for further investigation if this recurs.
