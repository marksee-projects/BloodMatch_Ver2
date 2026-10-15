# Database and chat rehearsal results

Completed 15 October 2026 after the user explicitly authorized the agent to perform the rehearsal and confirmed both databases were backed up. This authorization supersedes the earlier user-run-only restriction for this test rehearsal; it does not represent a production rollout.

## Targets and preservation

- Source application: `127.0.0.1:3306/bloodmatch`, 115 accounts. No source rows/schema were changed by the rehearsal.
- Destination: `127.0.0.1:3306/bloodmatch_test`, originally 281 unrelated accounts; replaced with a fresh source backup after backing up both databases again.
- Database event scheduler was OFF and no SQL events existed in either target. No test-database clients were connected at restore preflight. SMTP was forced to loopback port 1 during live API tests.
- Source table schema/data SHA-256 before restore and after rehearsal: `6abb531f012395af234fac22358c2cfbfacd18106496ae1c0b58d6b1234b6df6`.
- Restore matched that fingerprint before repair. The private restore record is `backend/storage/maintenance/rehearsal-restore-20261015-015407-04c2497c.json`.
- Fresh source backup: `backend/storage/maintenance/bloodmatch-20261015-015403-1ff07cba.sql`; previous test dataset backup: `backend/storage/maintenance/bloodmatch_test-20261015-015404-f943c9c6.sql`. Additional pre-operation backups were taken by each maintenance/migration runner.

## Applied on the test copy

1. Restored source schema/data and verified all table fingerprints.
2. Repaired imported chat keys, auto-increment, participant uniqueness, references and timestamp precision. The import contained two conversations and four participant rows with ID zero; these cannot be reliably paired. They were verified in a private JSON quarantine archive before removal from the test operational tables. No ID-zero messages existed. Other invalid/orphan imported chat records remain recoverable from the full SQL backups. No conversation pairing or historical approval was invented.
3. Corrected invalid imported enum/provenance values using the prepared conservative rules and audit events.
4. Rehearsed cleanup to 15 accounts: 14 members plus the sole active verified administrator, `markseejr@gmail.com` at ID 3. Test passwords were reset only on the test copy.
5. Applied and recorded migrations 025, 026 and 027, with fresh backups before each.
6. Ran isolated live API tests. Fixture users/threads/reports were cleaned up; audit history was retained. Final test users: 15; fixture user count: zero. The isolated server was stopped and its environment restored.
7. Applied the lifecycle worker: one overdue request was processed, no reminder offers were due. Applied retention after review: 53 old throttle records removed; no identity files/accounts were due for erasure.

Private test-only credentials: `backend/storage/maintenance/bloodmatch_test-20261015-015559-a3f00cbf-credentials.json`. These do not change the login credentials of the website's application database. Keep the file and quarantine/backup records private; do not commit them. The quarantine JSON is a maintenance evidence artifact, not automatically covered by SQL-backup aging.

## Verified checks

| Check | Result |
| --- | --- |
| Live registration/chat/account closure/moderation | 66 passed |
| Live schema, declared FK integrity and rollback-only trigger probes | 45 passed |
| Cleanup policy guards | 6 passed |
| Seven-day consent expiry boundaries | 7 passed |
| Migration ledger | 025–027 present on test only |
| Moderation audit events | Evidence access, under-review and resolution events recorded |
| Application preservation | Source table/schema fingerprint unchanged; still 115 users |

The live API suite covers no user before OTP, wrong/correct code, canonical location persistence, membership pending state, verified-only chat, consent gating, seven-day expiry without cleanup, send/reply/cursor polling/read/unread, session-derived sender identity, CSRF enforcement, recipient-verification revocation, password-confirmed account closure and old-session denial. When a matching private test-cleanup credential is available, it also checks designated-admin report listing, selected evidence and adjacent context, report decisions, duplicate-decision denial, no unreported private browsing and no self-investigation.

Schema probes verified 15 users/one designated admin, repaired chat shape, zero orphans across declared foreign keys, and database denial of other-admin promotion, admin demotion/deactivation/deletion and ordinary audit deletion. Trigger probes were rolled back.

## Problems found and corrected during rehearsal

- The import's duplicate/nonpositive chat IDs needed a verified private archive and quarantine before key repair could proceed. The maintenance review now reports those invalid-ID counts.
- The isolated PHP server could not persist sessions in XAMPP's external temporary directory under the agent's filesystem permissions. The test runner now creates a unique workspace-local session directory and removes only that checked directory on shutdown.
- Windows PowerShell retained a prior CSRF header on its WebRequestSession; the missing-token test therefore was not actually omitting the token. The suite now clears that header before each request. Production CSRF enforcement was not weakened.
- Live test output now includes a summary recognized by the isolated runner. Earlier failing attempts were corrected and the complete expanded suite rerun successfully.

## Not established by this rehearsal

- Production repair, account cleanup, passwords and migrations remain unapplied. No production worker scheduling/deployment was performed.
- Browser interaction, layout/mobile/theme/accessibility and delayed-network account-switch behavior remain manual checks.
- Request extension/renewal/late-donation-review concurrency and notification transport were not exercised by this chat suite. The lifecycle worker result confirms its run on the due data, not every lifecycle path.
- No account or document was due for physical retention erasure. Thirty-day erasure, filesystem deletion, scoped investigation holds, historical location correction, external backups and restore-time enforcement still need dedicated acceptance checks. The test copy shares the application's private document storage, so seeded destructive file-erasure testing must first isolate that storage.
- Independent reviewer/administrator transfer remains a separate reviewed operation. ERD remains deferred; the 100/500-user load test remains cancelled.

Reproduce the live checks only against the prepared test copy:

```powershell
powershell -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite chat_api.ps1
& 'C:\xampp\php\php.exe' tests/privacy_schema_live.php
```

Do not run legacy demo cleanup again after migration 025. See [the implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md).
