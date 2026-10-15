# Approved plan implementation handoff

Prepared 15 October 2026 on `feat/chat-privacy-account-lifecycle`.

Application changes and user-run migrations are prepared. No migration, account cleanup, password reset, retention apply, or live API suite was run by the agent. ERD generation remains deferred and the load test remains cancelled. Do not run the old handoff's rollout sequence unchanged: the functional suite now requires migration 025.

## Latest follow-up and user-run source checks

The 15 October follow-up simplifies Chat Reports/My Responses headers and aligns plus/bell hover with reduced-motion support. Chat page workspaces now reset by account/eligibility/route and ignore old async completions; unread counts and profile-to-chat actions ignore a previous account's responses. Pending message requests expire at exactly seven days in the API, independently of the retention schedule. Accepted consent remains valid; explicit decline/cancel keeps the one-day retry rule. A new request can replace an expired pending pair without implicitly accepting it. Retention no longer converts that expiry into a cancellation that restarts the retry delay.

Run from the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File tests/check_chat_privacy_source.ps1
```

Requires existing frontend dependencies, Node/npm and PHP at `C:\xampp\php\php.exe` (override with `-PhpPath`). The script stops on failure, lints application/database PHP, runs an explicit allowlist of offline checks and builds the frontend. It does not apply SQL or exercise live APIs. These latest edits and the new runner have not been executed by the agent. Do not treat earlier passing results as verification of this working tree.

After source checks pass, follow the test-copy database sequence below. The isolated suite now ages only its owned pending request to verify denial after seven days, exclusion from pending lists, and a fresh request that reuses the pair without consent. This is a small functional test; the cancelled load test remains excluded.

## Source changes

- Compact bottom-right notices dismiss after five seconds, with hover/focus pause and placement above open floating chat. Logout has balanced adjacent buttons without its old paragraph. Registration upload and blocked availability use concise notice feedback and a reduced-motion-aware wiggle. Request-form header/field spacing and navigation hover/plus/profile-card access are updated.
- Registration stages a password hash, canonical location and optional private document in pending_registrations. A valid, unexpired OTP atomically creates one email-verified, membership-pending account. Missing/wrong/expired/reused codes create no extra account. Abandoned signup lasts 24 hours; codes last at most 10 minutes, with existing attempt/resend protections. Existing pre-OTP user rows are not silently removed or converted.
- Verified active pairs request and accept chat consent. Profiles, request cards and valid I can help offers use that flow. Imported messages remain preserved, but old conversations have no automatic consent. `/chat` provides requests, accepted conversations, direct thread links, older-message loading and reporting. Pending requests expire after seven days at API access even if maintenance is delayed; declined/cancelled pairs wait one day before retry; direct requests are limited to ten/hour. Requests in opposite directions reuse one pair.
- Chat listing, unread counts and message/read access require consent and eligible participants. Read markers use fetched message IDs, avoiding timestamp ties and stale unread-refresh races. Message sends recheck both locked accounts. The full page and floating tabs do not poll the same thread at the same time.
- Owner profile provides My requests and Expired posts actions. OPEN requests can extend their deadline; expired requests renew as linked new posts, with no old offers reactivated. Owner closure records Need met elsewhere/No longer needed without inventing donations. Expiry prompts link to the expired tab and remain available on the next visit/notification center.
- Pending donation reports survive closure. Staff may review timely reports after closure with documented evidence; existing no-self-review and invalid-offer checks remain. Verified units may fulfill an expired original without reopening matching or changing its linked renewal. Cancellation itself never creates a donation/window. Donor windows still start at staff confirmation and overlap.
- `/admin/chat-reports` lists active cases first, with status filters/pagination. Only the designated administrator reviews selected messages and up to two adjacent messages on either side of each selection, at most 50 unique evidence messages. Evidence access and review must be audited. Unrelated chat browsing and self-investigation are denied; a case involving the sole administrator needs independent review rather than an automatic privilege bypass.
- Delete account requires the current password and DELETE confirmation. It immediately revokes access, closes active requests/unresolved offers and schedules profile/contact erasure after 30 days. Closed accounts cannot be reactivated. Necessary pending reviews/history and restricted report evidence follow retention rules. The sole administrator cannot self-delete/demote/deactivate; transfer requires a separately reviewed change.
- Profile email is removed from presentation and the other-member API; authentication and authorized staff email use remain. Privacy notice uses DeMolay Bataan, demolaybataan@gmail.com, Bataan. Consent version/acknowledgement time are recorded on new signup audit events; no historic consent is fabricated.
- User management shows the actual database name/port, separate access and donation-window status/filter, and no role-promotion action. Application staff authorization is limited to markseejr@gmail.com; migration 027 enforces the designated administrator in database insert/update/delete paths too. Ordinary admin blood requests retain existing normal validation; Responses is retained.

## Database prerequisites and order

The read-only inspection found bloodmatch:3306 has 97 members and 18 admins. The reported 15-account database view has not been reconciled. Compare server/database/filter and User Management's new source label before any cleanup. No table/column has been proven obsolete, so no unsupported schema removal was made.

1. Stop website servers and mail/expiry workers for repair, demo cleanup and migrations.
2. Back up both the application database and the existing test database with the earlier maintenance helper. Restore the fresh application backup into bloodmatch_test only if you need a fresh rehearsal copy. Keep the test backup before replacing any test data.
3. Rehearse the import repair and fifteen-account cleanup **before** enabling these new workflows. The earlier cleanup review does not model retention/moderation evidence for an already-used new schema; do not repeat that cleanup after new reports/signup data exist without a fresh impact review.
4. Review and apply 025, 026 and 027, in order. The new runner refuses unrepaired chat keys, requires a reviewed SQL hash/offline acknowledgement, and makes a fresh complete SQL backup before each apply. 027 also requires exactly the designated active admin already remains.
5. Run the isolated functional suite and manual browser checks. Only after that passes should you repeat the reviewed preparation on bloodmatch and restart the website.

From the repository root, after backing up/restoring the chosen test copy:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Review
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Repair -Offline
powershell -ExecutionPolicy Bypass -File scripts/chat_demo_maintenance.ps1 -Target bloodmatch_test -Action Cleanup -Offline

$privacyPhp = 'C:\xampp\php\php.exe'
& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=025
# Read the review and replace REVIEWED_HASH with its sha256, then apply:
& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=025 --apply --offline-ack --sha256=REVIEWED_HASH

& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=026
& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=026 --apply --offline-ack --sha256=REVIEWED_HASH_FOR_026

& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=027
& $privacyPhp database/apply_privacy_migrations.php --target=bloodmatch_test --migration=027 --apply --offline-ack --sha256=REVIEWED_HASH_FOR_027

powershell -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite chat_api.ps1
```

These are user-run commands, not executed results. Stop on any nonzero exit. DDL is not transactional: a partial migration requires inspecting/restoring the backup, not blindly rerunning CREATE statements. Do not use the generic all-migrations runner for this imported database or these compound triggers.

The isolated suite uses member fixtures only, preserves the one-admin policy, forces SMTP to a closed local port, supplies an OTP only to its owned test registration, and cleans up its owned users/threads/reports. It covers OTP creation/location, consent, legacy unaccepted history, send/reply/poll/read/unread, verification revocation, reporting, account closure, CSRF and participant privacy. It does not test load, production transport, retention filesystem deletion or administrator evidence review.

When the rehearsal passes, run fresh repair/cleanup reviews and the same migration sequence against `--target=bloodmatch`, with application servers/workers stopped. Keep the private credentials file printed by Cleanup; credentials are generated only by that user-run operation. No passwords are committed to source. Restart and sign in again.

## Workers and retention

Expiry is a status transition, not immediate deletion. Run the lifecycle worker each minute; reminders begin after an unresolved offer is one day old, at most one notice/day per recipient and offer, with bounded batches that exclude already-reminded rows. It does not assume an unreported donation occurred.

```powershell
& $privacyPhp database/run_privacy_lifecycle.php --target=bloodmatch_test
& $privacyPhp database/run_privacy_lifecycle.php --target=bloodmatch_test --apply
& $privacyPhp database/run_retention.php --target=bloodmatch_test
# Review counts, stop test servers/workers, then rehearse erasure:
& $privacyPhp database/run_retention.php --target=bloodmatch_test --apply --offline-ack
```

Review without --apply is read-only. Retention apply takes a fresh SQL backup. Configure daily retention in an actual maintenance window: --offline-ack is an acknowledgement that servers/workers are stopped, not a command that stops them. Schedule the same reviewed runners for bloodmatch after rehearsal. No scheduled task was installed by the agent; retention promises depend on these workers actually running successfully.

- Profile/contact data and private files are erased after the 30-day closure grace; an inaccessible tombstone may preserve necessary linked history. This is not a claim that all remaining health/evidence records are already fully anonymous.
- National ID files uploaded by the final review are erased after 30 days. Imported verified documents with no decision timestamp are reported for manual review, not assigned an invented final-review date. Newly uploaded/unreviewed documents are not erased using an older decision.
- Chat/message-request records expire after one year of inactivity/final decision. Closed reports/evidence expire one year after resolution; open cases retain scoped evidence. Closed requests/reports retain necessary pending reviews and a year after final resolution/donor restriction. Audit deletion requires the retention-only session flag and respects active case/consent holds; ordinary append-only protections remain.
- Only the current target's dated SQL dumps in backend/storage/maintenance are aged out after 90 days. External copies, cloud version histories, restored backups, logs and document backups need the same operational policy. Restores must reapply recorded closure/erasure decisions before reopening access.
- Before public release, verify retention against all storage destinations and review any exceptional legal hold. These are approved product periods, not NPC-mandated deadlines.

## Manual acceptance checks

- Two verified users: request from profile/card, decline/cancel, accept, reply, older history, refresh/deep link, unread badge, minimize/hidden tab, account switch and permission revocation. Unaccepted imported chats stay outside normal lists/counts. Check a real report as the nonparticipant administrator, unrelated evidence denial, conflict denial, status changes and access logs.
- In browser network throttling, switch threads during polling, sending and older-history loading; old replies must not populate the new thread. Switch accounts with a pending unread/profile-message request; old tabs, badges and late popup results must not appear for the new account. Revoke recipient eligibility and refresh the selected thread; it must clear when absent from the authorized list. Pending-request expiry must not depend on a cleanup run.
- Registration: invalid/oversized ID then empty optional upload, valid replacement, keyboard upload, step back/forward, labels, location selected in JSON/multipart, OTP missing/wrong/expired/resend/reuse, and private-file cleanup. Existing null locations need owner correction/verified source data, not guesses from chapter.
- Requests: deadline expiry/next-visit prompt, future extension, linked renewal without old offers, ignored prompt stays expired, external closure without donation/window, partial and late pending-report review. Verify workers are scheduled and failing runs are visible.
- Deletion: wrong password, missing DELETE, valid closure, all old sessions blocked, no reactivation, active-work closure, pending reviews preserved, thirty-day erasure/file cleanup, report holds and backup aging on expendable test data.
- Layout: bottom-right five-second notices, hover/focus pause, chat overlap, dark/mobile/reduced-motion, logout button row, form spacing, plain plus icon, avatar hover, profile-card link/disclosure and hidden profile email.

Browser tooling has no enabled browser; actual appearance and live database behavior remain user-verification checkpoints. No deployment, ERD export or load test is included.
