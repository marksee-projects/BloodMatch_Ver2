# Application database rollout results

Completed 15 October 2026 after the user approved applying the rehearsed changes to the actual application database. This supersedes earlier user-run-only/pending-production statements in the historical plan and rehearsal handoff.

## Applied target and protection

- Target: `127.0.0.1:3306/bloodmatch`.
- Fresh pre-rollout backup: `backend/storage/maintenance/bloodmatch-20261015-021204-edcb77ac.sql`. Each subsequent maintenance operation and migration also created a backup.
- Preflight: no PHP server on ports 8000/8001, the existing BloodMatch application-safety scheduled task was disabled, MySQL event scheduler OFF, no other database clients on the application/test targets at inspection.
- Apache and the Vite frontend were running. Added an API maintenance-file gate to `backend/public/index.php`, preventing HTTP requests during schema/account changes without stopping unrelated Apache applications.
- The gate returned HTTP 503 when tested, then was removed after database/credential verification. The application PHP server is running hidden on `127.0.0.1:8000`, PID 26476 at startup; Vite continues on port 5173. No external hosting/deployment was performed.

## Completed changes

1. Repaired imported chat keys, auto-increment, participant uniqueness, foreign keys and timestamp precision.
2. Preserved the two ambiguous ID-zero conversations and four participant rows in `backend/storage/maintenance/bloodmatch-invalid-chat-20261015-021211-1a531cdd.json` before quarantine. No ID-zero messages existed. Other invalid imported conversation content remains in full SQL backups. No unsupported pairing/history was reconstructed.
3. Applied the conservative imported enum/provenance repairs with audit events.
4. Reduced 115 accounts to **15**: 14 members and one active verified administrator, **markseejr@gmail.com**, ID **3**. Passwords were reset for all retained accounts, old reset/code tokens cleared, and old sessions are invalidated by password fingerprints. Outstanding email jobs were cancelled by cleanup.
5. Applied and recorded migrations **025, 026 and 027**.
6. Ran the lifecycle worker: one overdue request was processed; no overdue OPEN request remained afterward. No reminder offers were due.
7. Reviewed and ran retention while HTTP was gated: 53 old throttle records removed; no account/document was due for physical erasure.
8. Live smoke review found the other-member profile response still exposed email despite the UI hiding it. Removed that response field and verified member-to-member API access no longer includes it. The first profile probe targeted an admin profile, which the existing member-profile policy correctly treats as unavailable; the final smoke suite uses another retained member.

## Credentials

All 15 private manifest passwords were verified against their stored hashes, without printing them to tool logs.

- Private readable account list: `backend/storage/maintenance/BLOODMATCH_DEMO_ACCOUNTS.md`.
- Original committed credential manifest: `backend/storage/maintenance/bloodmatch-20261015-021228-06825781-credentials.json`.

These are the current application credentials, distinct from rehearsal credentials. Keep them private. The maintenance directory is ignored by Git; credentials were not added to tracked documentation. Existing passwords no longer work. Backup/quarantine artifacts contain private data and require controlled retention; JSON quarantine artifacts are not covered by the SQL-dump aging rule.

## Verification

| Check | Result |
| --- | --- |
| Application schema/FK/count/migration checks | 40 passed, SELECT-only |
| Retained credential checks | 15 matched current database hashes |
| Application HTTP smoke checks | 18 passed |
| PHP syntax for changed API/maintenance helpers | Passed for checked files |
| Prior isolated rehearsal | 66 live API and 45 schema/trigger checks passed |

Application smoke covered designated-admin and verified-member login, correct database/port label, fifteen-account count/one admin, profile, own requests/responses, chat conversations/requests/unread, admin reports and absence of email in another member's profile. Smoke sessions were logged out. No test accounts, messages or requests were created on the application database by this smoke check.

To repeat the application checks without destructive workflow tests:

```powershell
& 'C:\xampp\php\php.exe' tests/privacy_schema_live.php --target=bloodmatch --read-only
& 'C:\xampp\php\php.exe' database/verify_demo_credentials.php --target=bloodmatch --file=backend/storage/maintenance/bloodmatch-20261015-021228-06825781-credentials.json
powershell -ExecutionPolicy Bypass -File scripts/check_application_rollout.ps1 -CredentialFile backend/storage/maintenance/bloodmatch-20261015-021228-06825781-credentials.json
```

Do not rerun demo cleanup: migration 025 is now installed, and that legacy cleanup is intentionally blocked.

## Remaining acceptance and operations

- Browser layout, mobile/dark/reduced-motion, delayed-network account switches and real end-to-end interactions remain manual acceptance work.
- Automatic lifecycle/reminder and daily retention scheduling were not installed/enabled by this rollout. The pre-existing safety task remains disabled; runners were executed once manually. The disabled task's existing behavior is not a replacement for all new lifecycle/reminder processing. Retention/expiry promises require actual recurring operations.
- Physical thirty-day erasure and scoped holds were not exercised because nothing was due. External backups, JSON maintenance artifacts and restore-time deletion enforcement require operational coverage.
- Historical missing locations still require a verified source or owner correction; no invented address data was backfilled.
- Administrator transfer/independent review remains separate. ERD is excluded and load testing remains cancelled.

These remaining items do not undo the verified database rollout, but the entire approved plan must not be described as fully accepted or operationally complete yet.
