# Notification and chat fixes — 2026-10-18

Branch: `fix/notifications-chat-presence`. Donation acknowledgment/confirmation changes are deferred.

## Changes

- The bell refreshes every five seconds while visible. Opening it no longer marks all notifications read. Clicking a notification marks that item read. The open panel refreshes independently; stale filter requests cannot replace newer results.
- ID-submission alerts already existed. Local inspection found 14 alerts, all read, and both pending members with documents already had review alerts. Submission/resubmission and event deduplication remain intact.
- Hospital duplicates were consolidated to their oldest IDs, preserving request references. A unique name/municipality index and repeatable seed prevent recurrence.
- Notification dates, filters and hover surfaces use readable theme colors, with a white hover surface in light mode.
- Suggestions remain until the current participant sends a message, including after reload and incoming messages.
- Both chat composers wrap and grow to a bounded height. Enter sends; Shift+Enter inserts a line break. Message bubbles preserve line breaks and wrap long words/URLs.
- Online indicators appear in chat lists, chat headers and authorized member profiles. Authenticated visible clients heartbeat every 30 seconds; records expire for display after 90 seconds. Logout clears only the current session, preserving other device sessions. Inactive/unverified accounts do not appear online. Profile presence refreshes without repeatedly logging profile views.

## Database execution

Only `028_user_presence.sql` and `029_unique_hospitals.sql` were applied to the configured local `bloodmatch` database on port 3306. No other pending migrations ran. This port was verified from the active connection; historical documentation referring to 3307 does not describe this local target.

The exact migration helper is `database/apply_notification_chat_fixes.php` (read-only by default; `--apply` executes). A private recovery snapshot of hospital rows and request associations is retained under `backend/storage/maintenance/notification-chat-*.json`. Local duplicate hospital groups decreased from 14 to zero.

## Email finding and prepared activation

Approval/rejection events already enqueue membership emails correctly. The local SMTP configuration is present, but `EMAIL_WORKER_ENABLED=0` and no general delivery cutover is configured. Two membership jobs remain pending; one historical job is marked sent. No SMTP was performed during these fixes.

The earlier `REMAINING_FIXES_CHECKPOINT.md` deliberately held general email sending pending controlled delivery review. A separate membership-only runner is now prepared so future verification decisions can be activated independently without enabling Emergency delivery or releasing older jobs:

- `database/run_application_membershipemail.php` reviews readiness by default.
- Scheduled delivery requires `MEMBERSHIP_EMAIL_ENABLED=1` and a valid `MEMBERSHIP_EMAIL_START_AT_UTC` timestamp in `.env`.
- The selector includes only `verification.decision` jobs created at or after that timestamp. Existing expiration, supersession, verified-email, shared-budget and retry protections still apply.
- The dedicated scheduled task is prepared by `scripts/manage_application_tasks.ps1 -Action Preview -Kind MembershipEmail`; installation uses `-Action Install -Kind MembershipEmail` after activation approval. It runs every minute while the signed-in Windows user is awake/logged in.
- The wrapper refuses manual delivery/experiment scopes. General `EMAIL_WORKER_ENABLED` remains disabled. Pending historical jobs are excluded by the activation cutover.

The user approved activation in chat on 2026-10-18. MEMBERSHIP_EMAIL_ENABLED=1 and MEMBERSHIP_EMAIL_START_AT_UTC=2026-10-18 15:33:30 are now set locally. The dedicated BloodMatch-Application-MembershipEmail task is installed with a one-minute interval, Interactive logon and Limited privileges. Its first scheduled invocation completed at 15:34:01 UTC with exit code 0, zero claimed jobs and zero SMTP calls. Both older pending membership jobs remain pending. General EMAIL_WORKER_ENABLED remains 0. Future inbox delivery still needs confirmation from a new verification decision. The local computer must be awake, the Windows user signed in, and MySQL available.

## Verification

- Frontend production build.
- PHP syntax checks for changed controllers, services, routes and maintenance runners.
- `tests/chat_offline.php`: 35 checks, including receiver/sender suggestion behavior and authorization.
- `tests/chat_consent_expiry_offline.php`: 7 checks.
- `tests/registration_notification_offline.mjs`: 16 checks.
- `tests/notification_feedback_offline.mjs`: 9 checks.
- Email enqueue, worker and accounting offline suites: 15, 21 and 19 checks.
- `tests/notification_chat_integration.php`: 11 checks against the configured local database, with all fixture writes rolled back. Covers heartbeat expiry, multiple sessions, logout, deactivation, review alerts/deduplication, hospital seed repetition and request references.
- HTTP smoke check: unauthenticated presence POST denied with 401.
- Visual browser verification could not run: browser inventory was empty and the in-app browser was unavailable. Hover appearance, responsive composition, IME keyboard behavior and two visible user sessions still need visual verification.

Build retains the existing large-JavaScript-chunk advisory. No unrelated bundling refactor was included.
