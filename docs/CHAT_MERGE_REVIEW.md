# Chat merge review — 13 October 2026

Compared merged commit 04a26cc with your pre-merge commit f29ec36 and teammate commit 20730f5. The incoming merge changed 64 files, including substantial work unrelated to chat. The working tree now contains a curated integration; no commit or push was made.

## Kept beside chat

| Addition | Decision | Reason |
| --- | --- | --- |
| My Responses page at /responses (with /my-responses alias) | Keep | Lets donors revisit requests they offered to help with; includes message and request links. Staff actions remain read-only. |
| Hand-heart navigation icon | Keep | Opens My Responses; a mobile menu entry is also retained. |
| Shield navigation icon | Keep, adapted | Opens your existing combined Admin/Officer portal, with canonical admin authorization. It does not revive the separate Officer portal. |
| Donor-enrollment prompt in Profile and request cards | Keep | Makes the existing enrollment flow easier to find. It uses your existing enrollment endpoint. |
| Message buttons in Matches and member Profile | Keep | Connects chat to your existing donor/request coordination screens. |
| Link back to My Responses from a responded request | Keep | Makes it easier to revisit donor commitments. |
| Local-date minimum in request form | Keep | Avoids using tomorrow's UTC date as the local date near midnight. |
| Automatic navigation to a request after a successful response | Keep | Leads directly to the request details and message action. |

## Chat retained and repaired

- Authenticated conversation/message/read/unread endpoints, server policy, repositories and React provider.
- Chat-bubble icon in the desktop and mobile headers, conversation flyout, unread badge, name search and floating tabs.
- Suggested starter messages for matched donors/requesters.
- Active conversation polling every three seconds; unread count polling every 30 seconds. This is polling, not WebSocket delivery.
- Recent 50 messages load initially in chronological order. Older-history pagination remains a future enhancement.
- Sending no longer advances the polling cursor past unseen incoming messages; send/poll duplicates are removed.
- Read updates stop at the last fetched message rather than marking a concurrent incoming message read.
- Existing conversations recheck recipient activation and messaging permission when sending.
- Legacy officer accounts do not gain staff chat privileges; canonical member/admin roles are used.
- Direct conversation creation locks the participant accounts before checking/creating the conversation to serialize competing starts. Real SQL concurrency is not verified yet.
- Chat errors are shown instead of displaying a failed load as an empty conversation.
- False “Active now” presence was removed because there is no presence implementation.
- Floating chats sit below existing dialogs; visible tab counts adapt to viewport width. Mobile flyouts fit the viewport, inputs can shrink, and motion respects reduced-motion settings.
- Staff profile links inside chats are omitted to preserve your existing member-profile privacy policy.

## Your implementation restored

The following areas were restored from f29ec36: combined staff routing and layout; officer compatibility aliases; verification controller and pages; analytics queries and pages; audit queries and pages; donation review/confirmation behavior; email verification and dialog behavior; mailer, notification delivery and link routing; matching notifications; cancellation/expiry notifications; registration behavior; public profile access policy; logout confirmation; donor-age/workflow test versions.

The incoming frontend eligibility override was removed. Both request cards and Matches still rely on backend can_respond. Profile queries again require the authenticated user and retain your original cache key. New response-page actions cannot mutate staff donor records and invalidate related request/feed caches.

The independent dateTime helper was removed because its remaining consumers were restored. The demo chat seed script was excluded: it targets hardcoded user IDs 2/3 and deletes their conversation messages before seeding. It remains recoverable from teammate commit 20730f5, but is not needed for chat.

## Extra teammate pages not adopted

Separate Officer Dashboard, Verification, Confirmations and Audit Logs pages overlap your existing combined portal and contradict your current staff-role consolidation. Keep your existing versions. If you want selected visual ideas later, port their presentation into the combined portal without replacing its authorization or workflow logic. The incoming Admin sidebar replacement and analytics reshuffle were also not adopted.

## Database handoff — pending

No database connection, migration, seed, or repair script was run for this task. .env was neither read nor changed. Pre-existing untracked repair_imported_email_schema.php and import_archive_guards_offline.php were left untouched.

Chat requires conversations, conversation_participants and messages from the bundled 021_create_live_chat.sql. Its pending definition now uses DATETIME(3) for last_read_at to match message timestamp precision. If that migration has already been applied to the database you send, use a separate reviewed ALTER migration instead of rerunning or editing its ledger entry.

There is already an unrelated 021_email_outbox_accounting.sql. Resolve the chat migration's numbering/ledger status against the supplied database before applying anything. Verify users.id foreign-key compatibility, existing tables, ledger, timestamp precision, and SQL transaction/concurrency behavior first. The schema and live chat cannot yet be verified end to end.

## Verification

- Production Vite build passed with the existing React plugin via the Vite API and configFile:false. Normal CLI config loading was blocked by Windows sandbox parent-directory access. No project build configuration was changed. Existing >500 kB bundle warning remains.
- All 110 tracked backend PHP files passed syntax checks.
- New tests/chat_offline.php: 18 checks passed using connectionless PDO fixtures, including participant access, inactive recipients, canonical roles, message validation, authenticated sender identity and bounded read updates.
- Existing offline suites: 18 of 21 passed. staff_role_offline.php passed all 32 checks; email, donor age, management, location and other passing suites retained their behavior.
- workflow_lock_offline.php and request_lifecycle_offline.php failed on old Officer-role expectations. Both test files and both related services are identical to f29ec36; these mismatches predate this integration.
- rejection_ui_offline.mjs was blocked by the sandbox while esbuild resolved its entry.
- No conflict markers found in application PHP/JSX/SQL; git diff --check passed after whitespace cleanup.
- Browser rendering and live two-account chat were not verified: browser tooling could not initialize, and the database is pending. The passing build/offline tests do not establish live database behavior.

All restored teammate work remains available in Git history. Review git diff against HEAD for the cleanup, or git diff f29ec36 for the final additions to your original implementation.
