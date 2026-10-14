# Chat, database, notifications, and demo accounts

14 October 2026. Application code is prepared; database repair and account deletion are deliberately user-run. The 100/500-user load test remains cancelled.

## Current database findings

The connected database is `bloodmatch` on MariaDB port **3306**. It has 24 base tables and 115 users. The admin to keep is **markseejr@gmail.com (ID 3)**.

| Finding | Prepared handling |
| --- | --- |
| All three imported chat tables lack ID keys, automatic generation, indexes and foreign keys | Guarded repair in `database/demo_maintenance.php`; never rerun their CREATE statements against the import |
| Two orphan participant records and one orphan message | Back up all original records, remove affected invalid conversations as complete units, then restore constraints |
| Participant read timestamp has no millisecond precision | Restore DATETIME(3), matching message timestamps |
| Chat ledger entry missing | Record the original full migration filename only after schema verification succeeds |
| Two filenames begin with 021 | The runner keys by full filename, so their ledger entries are distinct. Its new guard stops CREATE against imported chat tables |
| Eight requests have the invalid empty enum value in review_status | Flag as pending_review and append a maintenance audit entry; do not infer historical approval. The requests are expired. Old seed scripts used the invalid value approved and are corrected |
| One user has invalid empty blood_type_source and an unsupported verification claim | Preserve the blood type as self-reported, clear its documentary-verification flag, and append a maintenance audit entry. The affected legacy admin (ID 2) is excluded from the retained set. Retire the old officer/provenance seeder |
| A leftover trg_force_fail donation-report trigger | Remove only if it exactly matches the known FORCE_FAIL test helper. Preserve both append-only audit triggers |
| No orphan email-outbox notification references | No email table removal required |

No table or column is proven obsolete, so none is scheduled for deletion. Removing empty tables or useful snapshot fields would break existing behavior.

The read-only enum audit checked every enum column, finding the two problems above. A separate scan of every currently enforced foreign key found no orphan references. Chat references are not enforced in the import and were checked separately.

## Table and column review decisions

All 24 tables have an identifiable purpose:

| Tables | Purpose and evidence |
| --- | --- |
| users, chapters | Accounts and chapter binding; UserRepository and Chapter controllers |
| bataan_locations, hospitals | Location/facility references; LocationService, HospitalController, request/profile forms |
| blood_requests, matches, donation_reports | Request lifecycle, donor coordination and confirmation; RequestService, MatchService and DonationService |
| conversations, conversation_participants, messages | Direct-chat identity, access membership/read state, and message history; ChatRepository and ChatService |
| member_documents, verification_decisions | Stored document metadata and membership decision history; DocumentRepository and VerificationService |
| notifications | Durable in-app event history and deduplication; NotificationService/Repository |
| email_outbox, email_attempts, email_donor_reservations | Delivery queue, global accounting and per-donor reservation windows; EmailWorker and accounting repositories/services. These are separate responsibilities |
| donor_opportunities, donor_response_state | Per-opportunity non-response timers and donor standby acknowledgment; NonResponseStandbyService. Empty rows do not make the tables redundant |
| password_resets, auth_throttle | Hashed single-use reset tokens and rate limiting; AuthService and password-reset/throttle repositories |
| audit_log | Append-only accountability; AuditLogger and audit repositories |
| compatibility_matrix, system_settings, schema_migrations | Fixed matching reference data, operational settings, and migration history |

Fields that can look redundant but are required:

- users.full_name is generated from atomic name fields and is used throughout display/search queries.
- Coordinates on users/requests are resolved snapshots consumed directly by proximity matching. location_id links to the reference location; it does not replace those consumers automatically.
- facility_name preserves a readable facility even when hospital_id is absent or its reference changes.
- blood_type_source and blood_type_verified record different provenance/verification facts. Membership verification and email verification also remain independent.
- matches.rank_score and distance_km are computed matching results used for ordering; generation handles materially changed events.
- notifications.read_at, emailed_at, dedup_key and generation track different aspects of the event lifecycle.
- conversations.type is used by direct-conversation lookup. officer_id in verification history is a historical field name; current staff authorization uses the canonical admin role.
- Operational lease, reservation, timer, token-expiry, and accounting columns are used by workers and safety windows.

The ERD exporter records actual enforced FKs and documents polymorphic/application references separately. It also exposes the missing chat keys until the repair is actually run.

## Code changes

- Every chat endpoint requires an active, membership-verified member/admin. Both sender and recipient must be verified to initiate or send messages; there is no pending-user exception for contacting staff or a matched member.
- Frontend chat entry points follow the same eligibility rule. Hidden/minimized chats stop message polling. Overlapping poll requests are prevented, and read writes are avoided when the fetched cursor has not advanced.
- Failed chat sends use the common popup and restore typed message text.
- Sessions are bound to the current password hash through a one-way fingerprint. Resetting a password invalidates old sessions on their next protected request. Existing sessions created before this change must sign in again once.
- Shared NotificationProvider supplies success, information, warning, error, and emergency variants. Popup surfaces, icons, dismiss controls, typography and spacing use the existing design system. Routine results dismiss after six seconds, paused during pointer/keyboard interaction; warnings/errors/emergencies persist.
- The queue shows three notices at a time and deduplicates outstanding events. It survives route navigation and resets when the authenticated account changes.
- Incoming unread notifications are checked on the existing 45-second notification schedule when the page is visible. Initial historical routine notices are suppressed; open emergency donation opportunities receive the same persistent popup with View request. The existing notification center remains the durable history.
- A successfully saved emergency request shows success. Failed matching after a successful save shows a persistent warning with retry guidance, avoiding duplicate posting.

## Notification inventory

| Area | Common popup integration | Context kept in the screen/form |
| --- | --- | --- |
| Authentication | Login/registration failures; password-reset request/completion; email verification/resend results; shared logout confirmation | Password confirmation and verification-code feedback; privacy/verification forms |
| Profile and navbar | Profile/photo save, enrollment, availability, standby acknowledgment, ID submission, action failures | Field validation and destructive confirmations |
| Request create/edit/cancel | Shared saved-request notice for routine/urgent/emergency; cancellation results; matching-retry warning | Date/location validation, failed screen-load recovery and confirmations |
| Home, Matches, My Responses | Help offer, withdrawal, donation report results and action errors | Eligibility reasons, record status, report form and unavailable-screen recovery |
| Staff | Account changes, membership decisions, donation confirmation/rejection and failures | Decision reasons, document viewing, no-self-action checks and confirmations |
| Chat | Start/send failures and flyout load failures | Conversation history, loading/unavailable states and suggested messages |
| Notification center | Read failures and mark-all completion | Durable notification list and loading/recovery states |
| Reporting/maps | Dashboard, analytics, audit and map failures; CSV export results | Filters, charts, aggregate print layout and unavailable-screen recovery |

Inline field errors, eligibility explanations, and loading/recovery panels remain contextual UI rather than duplicate action-result popups. Older unmounted Officer views are not active routes; the combined Admin/Officer portal is the current implementation.

## Representative demo set

The proposed set is **15 existing accounts**, with **one admin**. Selection prioritizes existing donation history/responses, enrolled donors, identity documents and chapter coverage.

Latest reviewed retained IDs: **3, 14, 64, 16, 17, 18, 19, 137, 21, 89, 104, 69, 85, 15, 20**. This covers all eight blood types, three chapters, two pending accounts, and one rejected account. Current verification/availability/safety-window states are preserved.

The latest preview removes 100 users, 38 requests and 372 matches, while preserving the existing donation report. The retained requests include seven future OPEN requests (one routine, four urgent, two emergency), plus fulfilled/cancelled/expired history. The user-run script regenerates the manifest against the current state and prints the exact impact before applying.

Cleanup is transactional, removes complete conversations whose participants are removed, handles the accounting table without a donor FK, retains audit history, cancels queued email, clears reset/code tokens, and assigns unique bcrypt-hashed temporary passwords. IDs are not renumbered. Original uploaded files remain private for recovery.

Passwords are not recoverable from stored hashes. After a successful cleanup, the script prints **PRIVATE_CREDENTIALS** with a local JSON path in the ignored backend/storage/maintenance directory. That file contains the new passwords and a COMMITTED status. Do not commit or publish it. Passwords are generated only when cleanup runs.

## User-run sequence

Run from the repository root. Stop application servers and scheduled mail/expiry workers while applying repair or cleanup. The maintenance script uses .env credentials, asserts the exact target/port, refuses stale review fingerprints, and makes a full mysqldump backup before every apply. MySQL DDL commits separately: if a repair stops, keep the backup and inspect before retrying.

A complete application SQL backup was generated without changing database rows:

`backend/storage/maintenance/bloodmatch-20261014-111302-353bf3e6.sql`

First back up your existing test database:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Backup
```

Restore the application backup into **bloodmatch_test** to rehearse against the imported schema. The following commands replace existing test data; the preceding backup preserves it. Use your configured local MySQL credentials if they differ from root.

```powershell
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$sourceBackup = (Resolve-Path 'backend/storage/maintenance/bloodmatch-20261014-111302-353bf3e6.sql').Path.Replace('\','/')
& $mysql -h 127.0.0.1 -P 3306 -u root -e "DROP DATABASE bloodmatch_test; CREATE DATABASE bloodmatch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw 'Test database preparation failed.' }
& $mysql -h 127.0.0.1 -P 3306 -u root bloodmatch_test -e "SOURCE $sourceBackup"
if ($LASTEXITCODE -ne 0) { throw 'Test restore failed.' }
```

Review, repair, and rehearse cleanup on the test copy:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Review
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Repair -Offline
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Cleanup -Offline
powershell -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite chat_api.ps1
```

The functional suite creates six temporary accounts only on bloodmatch_test, verifies sending/replies/poll cursors/unread state/CSRF/participant privacy/verification revocation, and cleans up its own fixtures. It is not a concurrency or load test.

After the rehearsal passes, apply the same operations to the application database with servers/workers stopped:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch -Action Repair -Offline
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch -Action Cleanup -Offline
& 'C:\xampp\php\php.exe' database/export_erd.php --target=bloodmatch
```

Each script invocation returns a nonzero exit on failure. Do not continue to the application step if the test copy or functional test fails. Keep backups and the printed private credential file. Restart the website and sign in with the new passwords.

## Verification status

- Read-only inspection and complete application SQL backup: completed. Restore verification is pending the user-run test-copy step.
- Chat policy: 27 offline checks passed.
- Password/session identity: 5 offline checks passed.
- Request-result popup semantics: 9 offline checks passed; registration/notification helpers: 16 checks passed.
- Existing Home rendered/callback checks passed; request/profile static suite passed 21 checks; management/verification/privacy rendering and callback checks passed.
- The older tests/profile_ui.mjs fails its first navigation assertion. The same failure was reproduced using the unchanged HEAD ProfilePage source; its fixtures use an obsolete owner-cache/auth setup. The current request_ui_static.mjs covers the authenticated profile/cache cases and passed.
- Production build passed. Changed/new PHP files passed syntax checks; both user-run PowerShell scripts passed parser checks without executing their actions. SVG XML and git diff checks passed. The existing large-bundle warning remains.
- Browser tooling reported no enabled browsers/tabs. Actual browser rendering, keyboard/timer behavior, mobile/dark-mode appearance, CSV downloads, live API tests, repair/cleanup transactions and restored-backup behavior await user execution.
- The current docs/erd.md and docs/erd.svg reflect the imported database accurately and mark its missing chat keys. Regenerate them after the actual repair for the submission ERD.
- No application database repair, account deletion, credential reset, or 100/500-user load test was executed by the agent.
