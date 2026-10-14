# Chat, database, and notification plan

Prepared 14 October 2026; execution approved 15 October 2026. Working branch: `feat/chat-privacy-account-lifecycle`. The approved amendments now have prepared application code, migrations and user-run checks. Earlier "planning only" statements below describe the instruction at the time of each amendment; the later approval authorizes implementation. Database apply/live PowerShell checks still belong to the user. ERD generation is deferred and load testing remains cancelled. Use [the current implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md), which supersedes the older rollout sequence.

Current source covers registration after OTP, shared feedback/layout, consent-based full chat, request extension/renewal/closure, profile privacy/navigation, account deletion/retention and the admin Chat Reports page. These are source-prepared changes, not a claim of live database/browser verification. Privacy contact supplied by the user: DeMolay Bataan, demolaybataan@gmail.com, Bataan. Retention periods were approved as product policy with the plan, not supplied as NPC deadlines. Historical missing locations and unknown legacy verification dates require evidence/owner correction; no values were invented.

## Read-only findings

- Connected application database: `bloodmatch`, MariaDB 10.4.32, port 3306. README documents port 3307; the database connector has a fallback. All future scripts must assert the resolved database and port rather than rely on the documented port.
- 24 base tables; 115 users: 18 verified active admins, 60 verified active members, 34 pending active members, and 3 rejected active members.
- Chat code and UI exist, but live two-account operation has not been verified.
- `conversations` has 2 rows, `conversation_participants` 4, and `messages` 20. All three imported tables lack primary keys, automatic ID generation, indexes, and foreign keys present in the intended chat migration.
- Participant `last_read_at` is DATETIME without fractional precision; the intended schema uses DATETIME(3).
- Chat has 2 orphan participant rows and 1 orphan message row, measured against users and conversations. No duplicate IDs or participant pairs were found, and both conversations have two participant rows. Orphan causes still need investigation.
- The chat migration is absent from the migration ledger. Two migration filenames use prefix 021; do not run the entire migration runner to repair this import.
- 8 blood requests contain the invalid empty enum value for `review_status`; valid values are `not_required` and `pending_review`. Resolve their intended state from workflow evidence before changing data.
- A subsequent enum audit found one legacy admin with invalid blood-type provenance. The prepared repair retains its type as self-reported and removes the unsupported documentary-verification claim; the obsolete seeder is retired.
- Email outbox has no orphan notification references in this inspection.
- Frontend action feedback uses page-local messages and global alert CSS, shared confirmation dialogs, specialized dialogs, and a native browser alert in `ChatContext` when starting chat fails. There is no single shared action-result popup implementation in the inspected paths.
- No table or column is yet proven redundant. Empty operational tables are not evidence of redundancy. Generated names, location coordinates, blood verification flags, and ranking fields are referenced by current code.

## 1. Capture and audit the imported database

1. Export a complete local backup including schema, rows, triggers, and migration ledger. Verify that it can be restored into an isolated database.
2. Compare every actual table, column, default, index, constraint, and trigger with migrations and application SQL.
3. Trace each proposed removal through repositories, services, routes, scheduled workers, tests, and requirements. Classify fields as required, intentional snapshot/cache, derived, obsolete, or unresolved.
4. Check duplicates, orphan references, invalid enum values, timestamp precision, nullability, generated fields, normalization, and required indexes. Audit user deletion cascades and audit-log triggers explicitly.
5. Produce a table/column decision report with evidence and the exact migration required. Remove only proven obsolete objects; update their consumers in the same change.

## 2. Repair and verify chat

1. Investigate the orphan chat records and decide whether they can be restored from the import or must be archived before repair. Preserve existing message content in the backup/archive.
2. Prepare a new migration for existing tables, rather than rerunning CREATE TABLE. Restore ID keys and automatic generation, participant uniqueness, indexes, foreign keys, and millisecond read timestamps after integrity checks pass.
3. Reconcile the ledger and duplicate migration prefix with the actual migration runner behavior.
4. Verify conversation creation, permitted and denied contacts, sending, polling, unread counts, bounded read updates, reload persistence, inactive accounts, mobile tabs, and simultaneous starts using two accounts on an isolated copy first.
5. Report chat as verified only after live database and browser checks pass.

## 3. Inventory and unify notification feedback

Inventory the actual active routes and their handlers:

| Area | Actions and feedback to inspect |
| --- | --- |
| Authentication | Login/logout, registration, email verification/resend, password recovery/reset |
| Profile | Save details, photo upload, donor enrollment/availability, standby acknowledgment, ID and donor-card uploads |
| Requests | Create/edit/cancel, emergency creation, matching success/partial failure, retry/re-match |
| Donor coordination | Offer help, withdraw offer, donation report submission, My Responses actions |
| Staff | Verify/reject membership, confirm/reject donations, role/status/chapter updates |
| Chat | Start/load/send failures, permission errors, unread/read failures |
| Notifications/reports | Load/read actions, analytics/audit errors, export feedback |

Proposed common popup:

- One shared provider and popup component accepting severity, title, message, optional action, deduplication key, and dismissal behavior.
- Same surface, spacing, typography, icon placement, close control, and action placement throughout the system; use existing design tokens and the installed icon library.
- Desktop: a compact bottom-right stack positioned to avoid floating chat controls. Mobile: a viewport-safe placement with readable text and 44px controls.
- Transient popups close after five seconds, including error/emergency feedback, paused during hover or keyboard interaction. Keep important details/actions available in the notification center or relevant page after dismissal. This latest user preference supersedes the earlier persistent-popup proposal.
- Emergency notifications use the same component with an emergency icon, red semantic accent, explicit urgency text, and a View request action. Do not style a successfully saved emergency request as a failed action.
- A saved request with failed matching shows a persistent warning and retry guidance, never a success-only notice or instruction to create a duplicate.
- Queue and deduplicate events; preserve notices across navigation; clear private notices on logout/account change.
- Announce routine updates through a polite live region and urgent/errors appropriately. Routine notices do not steal focus; support keyboard access, light/dark modes, and reduced motion.
- Keep validation next to the invalid field. Keep explicit destructive confirmations and verification/privacy workflows, using consistent shared styling. Persisted notification-center records and transient action feedback serve different purposes; avoid creating database records for every successful click.

## 4. Retain fifteen representative demo accounts

1. Keep `markseejr@gmail.com` as the sole Admin/Officer account. The user authorized selecting a representative count; fifteen accounts cover all eight blood groups, three chapters, pending/rejected verification and donation history.
2. Generate the retained-user manifest and deletion impact before cleanup; preserve existing enrollment, verification, and safety-window data.
3. Calculate the exact removal impact: requests, matches, reports, notifications/outbox, documents, password resets, verification decisions, chat records, audit references, and operational records lacking user foreign keys.
4. Rehearse cleanup on the restored copy; preserve audit history and handle chat conversations that would otherwise have insufficient participants. Prevent queued mail from being sent during the rehearsal and cleanup.
5. The user runs the reviewed cleanup after backup and integrity checks. Verify exactly fifteen users and one admin, no unintended orphan records, and functioning staff/member workflows.
6. Existing passwords are hashed and cannot be read back. Reset the retained accounts to new unique temporary passwords, store only secure hashes, invalidate old reset/verification tokens and sessions as applicable, and deliver the new login credentials privately. Do not put passwords in tracked documentation or frontend code.

## 5. Final verification; ERD deferred

- Latest user instruction: do not make an ERD. Diagram generation/export and updates to existing ERD artifacts are out of the current execution scope. Preserve existing artifacts; do not regenerate or delete them.
- Run affected database integrity checks, chat integration checks, request/matching and account workflow regression checks, frontend build, and browser checks across popup variants, navigation, narrow screens, and light/dark modes.
- Deliver the audit findings, repair/removal migrations, notification inventory and implementation, fifteen-account manifest and private credentials, and verification results. ERD delivery is deferred unless requested again.

## Execution order

Backup and schema audit -> test-copy import repairs -> registration and notification amendments -> request lifecycle and accepted-chat workflow with reviewed migrations -> single-admin enforcement -> final demo cleanup and credential resets -> regression verification. ERD generation is deferred. Database migrations and live PowerShell checks remain user-run. See [the checkpoint summary](CHAT_DATABASE_NOTIFICATION_CHECKPOINT.md) for prepared versus pending work.

Account data volume does not affect ERD structure. Account cleanup and schema normalization are separate tasks.

## Pending UI amendment: blocked availability action

User request, 14 October 2026: remove the long red post-donation error beneath Set available in the account dropdown. On a blocked attempt, give that menu row a brief horizontal wiggle instead. This is recorded for implementation; the UI has not been changed for this amendment.

- Animate only when the availability change is blocked; retain the current value and keep the dropdown open. Do not animate continuously or merely because the menu opens.
- Remove the dropdown's duplicate error paragraph. Keep a concise explanation in the shared notification popup so the cause and next eligible time remain available to users, including screen-reader users.
- Show the full eligible_again_at date/time in the user's local timezone. The end of the 42-hour standby alone is not the end of the overlapping 90-day cooldown.
- Respect reduced-motion preferences with static feedback and the popup explanation instead of the wiggle. Avoid repeated animation bursts from rapid clicks.
- Preserve all backend window checks. Neither the animation nor the availability toggle may bypass post-donation restrictions.

Current behavior: Admin/Officer confirmation records the current confirmation time as last_verified_donation_at and sets availability to standby. Both the 42-hour standby and 90-day cooldown start from that same timestamp. They overlap rather than running consecutively. An unconfirmed or rejected donation report does not start these windows.

## Pending registration amendment: create the account after OTP verification

User request: inspect whether submitting registration inserts a user account before the email OTP has been entered and validated. If it does, prepare a change so the users row is created only after successful OTP verification. Planning only; no application or database changes are authorized by this amendment yet.

- Trace registration, OTP issuance/resend/verification, any ID upload, and their database writes. Document the current timing before choosing the implementation.
- Hold an expiring pending registration separately from actual user accounts. Preserve only the necessary registration data securely, including a password hash rather than plaintext; protect any pending uploaded document.
- Create the account and its required associated records atomically after a valid, unexpired, single-use OTP. Email verification does not grant membership verification: new members must still follow the existing membership review process.
- Preserve validation, consent, CSRF protection, OTP expiry, attempt/resend limits, email uniqueness, and the ability to retry email delivery. Existing-account email verification must continue to work independently.
- Handle duplicate submissions and simultaneous verification attempts without creating duplicate accounts. Remove abandoned/expired pending registrations and temporary uploads under a defined retention rule.
- Plan how existing accounts created before OTP verification will be handled; do not delete or convert them implicitly.
- Verify that missing, incorrect, expired or reused OTPs create no user account; a correct OTP creates exactly one account. Include abandoned registration, resend, duplicate email, document handling, and transaction-failure cases.
- Any required database migration and live PowerShell checks remain user-run, consistent with the existing execution boundary.

## Pending registration UI amendment: National ID upload feedback

User request: on the registration page, replace the red inline upload error shown beneath the National ID upload box with the shared notification popup and a brief wiggle of the upload box. Planning only; do not change application code yet.

- Apply this to rejected National ID selections, including oversized files and unsupported formats. For the pictured case, show a concise popup such as: "Your ID file is too large. Choose a file up to 5 MB."
- Remove the duplicate red error paragraph beneath the upload box. Keep the existing accepted-format and maximum-size guidance visible.
- Wiggle the upload box briefly when a selection fails validation. Do not animate continuously, on page load, or after a valid selection; avoid repeated bursts from rapid attempts.
- Preserve client and server validation, the 5 MB limit and accepted JPG/PNG/WEBP/PDF formats. A rejected file must not become a valid attachment or bypass the form's checks; keep other entered registration details intact.
- Retain an accessible invalid state and associate a screen-reader explanation with the file input without displaying the red paragraph. Announce the shared popup; respect reduced-motion preferences with static feedback instead of animation.
- Verify oversized/unsupported files, correction with a valid file, keyboard selection, drag-and-drop, repeated attempts, mobile/dark mode, and reduced motion. Coordinate this change with the pending OTP-before-account-creation workflow.

## Pending registration fix: step 2 remains blocked after a rejected file

User-reported reproduction: choose an invalid National ID file on step 2, leave the upload empty, then try to move to step 3. Navigation remains blocked.

Read-only source finding: processFile sets idError and returns when a file is rejected. validateStep(2) then treats that stored error as a current blocking error even when no accepted file is attached. Cancelling the picker does not clear it; the existing remove action clears idError but is associated with the accepted-file preview.

- Separate feedback about a rejected selection from validation of the file currently attached. A historical upload error must not permanently block an empty optional upload.
- Make clearing/removing a rejected selection possible, including when no valid preview exists. Reset the native file input, rejected-file/error state and any obsolete file-specific step/server errors; release preview URLs as appropriate. Keep other form entries intact.
- Permit step 2 -> step 3 without an ID attachment when the upload is optional under the registration requirements. Keep validation for all required fields and any actual attached file. Later membership verification still requires its existing document/review workflow.
- Retain the planned shared popup and brief wiggle for the rejected attempt; clearing the upload must not submit the invalid file.
- Cover oversized and unsupported selections followed by clear/remove, picker cancellation, valid replacement, and back/forward navigation. Check both the step button and the step-navigation header.
- Remove only the Next: prefix from registration navigation buttons. Preserve the destination wording: Personal Details & ID and Review & Consent, their arrows, and their existing navigation/validation behavior.

## Pending review: old request retention and expiry

Read-only source finding: expiry changes an overdue OPEN request to EXPIRED, closes its active offers and preserves history. It does not delete the post/database row. Expiry processing and active-feed deadline checks serve different roles; an old record can remain in request history while being excluded from active matching/feed results.

- Inspect the expiry worker/schedule and feed filtering if an overdue request is still shown as actionable. Check status, needed-by time and timezone; do not infer worker execution from the presence of its script.
- Clarify expired/cancelled/fulfilled history versus active posts in the UI. Preserve completed donations and audit/history data; no automatic hard-delete policy is authorized by this amendment.

## Pending chat investigation: demo participants and profile Message action

Read-only source finding: the chat list comes from existing conversations and their participant accounts. It does not currently require a message-request acceptance. Starting a conversation can insert its participant records before any message is sent. Retained demo accounts can therefore appear as participants; seeing them does not by itself prove random users were added to chat.

- Trace the exact dummy conversations/participant IDs and their source: imported/seeded history, prior profile Message clicks, or an unintended query/UI behavior. Check activation/verification filtering and current cleanup/migration state without assuming the prepared scripts have been applied.
- Reproduce the non-working Message button on another member's profile. Inspect recipient ID, visibility/disabled conditions, authenticated verification/activation, POST response, database shape/ledger, shared-popup error handling and floating-chat rendering. Report the actual cause before fixing it.
- Preserve participant privacy checks and membership verification. Do not make pending/rejected users eligible merely to make the button respond.

## Pending chat amendment: recipient accepts a message request first

User requirement: add a message-request action to another user's profile and to blood-request post cards. Only after the recipient accepts may that user appear in the normal chat list and the pair communicate. Planning only; do not implement it or alter database records yet.

- A profile request targets that profile's account. A request-card action targets the post's requester. A successful I can help offer should also initiate or reuse a pending message request to the requester, as approved below. Offering help and accepting chat remain separate decisions; an offer must not silently grant chat consent.
- Require active, membership-verified accounts at both ends, deny self-requests, and preserve existing profile/request access permissions. Matching and emergency urgency do not bypass recipient acceptance.
- Before acceptance, show a pending request in a separate message-requests area, with clear Request sent feedback for the sender and Accept/Decline actions for the recipient. Pending requests must not appear as accepted conversations or enable ordinary message sending.
- On acceptance, atomically activate/create one direct conversation for the pair, add it to their normal chat lists and enable communication. Enforce the accepted relationship in backend conversation creation, sending and applicable listing/read endpoints; hiding controls alone is insufficient.
- Define pending/accepted/declined/cancelled states and reasonable expiry, retry and rate-limit rules. Prevent duplicate requests and duplicate conversations, including simultaneous requests in opposite directions and repeated acceptance.
- Use the common popup for request sent, accepted, declined and failed actions; preserve chat unread/read behavior separately from message-request notifications.
- Plan explicit treatment of existing/imported/demo conversations that lack acceptance records. Do not silently treat them as approved or delete history. Any transition/backfill must be reviewed and user-run.
- Verify the profile and card entry points, pending list separation, rejection/cancellation, acceptance, direct API bypass attempts, inactive/unverified accounts, concurrency and existing-history transition. User-run database migrations and live checks remain the execution boundary; the previously cancelled 100/500-user load test remains cancelled.

## Approved planning amendment: deadlines and expired posts

User approval, 14 October 2026. Record these changes for later implementation; this approval does not override the current documentation-only instruction.

- A post expires at its requester-selected needed-by date/time, not a fixed number of hours or days after posting. Overdue OPEN requests become EXPIRED; active feed/matching must reject them even if the expiry worker has not yet run. Preserve history rather than hard-delete posts.
- Let the owner extend an OPEN request's deadline before it expires. Validate a future deadline, permissions and concurrent status changes; audit the change and refresh matching/notifications where appropriate. Display local dates consistently while retaining the backend time convention.
- Offer an explicit renewal action for an expired request when the need remains. Do not silently revive offers or refresh old posts. Decide whether renewal reopens the record or creates a linked new request after tracing reports, matches and audit references; preserve the old deadline and history either way.
- Add an Expired posts tab to the owner's profile. Show their expired requests with status, needed-by date, details and an eligible renewal action. Keep this separate from donation history, preserve other-profile privacy rules, and provide an empty state plus mobile/dark-mode support.
- Verify the expiry schedule as well as feed filtering. Test deadline boundaries, extension/expiry races, renewal, preserved completed donations and prevention of new offers on expired posts.

## Approved planning amendment: offers, chat consent and request closure

- After a valid I can help offer succeeds, create or reuse a pending message request addressed to the requester. Only recipient acceptance enables ordinary chat and adds the pair to the normal chat list. Reuse an already accepted relationship; avoid duplicate requests for repeated offers or multiple posts. A failed or ineligible offer creates no message request.
- Accepting chat does not confirm a donation, fulfill a request, or start donor windows. Declining chat is distinct from withdrawing/rejecting an offer; communicate each outcome clearly.
- A donor reports the donation and staff confirm valid evidence. Confirmed donations count toward the required units; reaching the requirement fulfills the request. Preserve staff permissions, no self-review, concurrency safeguards and audit history.
- If an offered donation is never reported, it contributes no confirmed units. Other confirmed donations may still fulfill the request; otherwise the request can expire at its deadline or be explicitly closed. Do not infer donation completion from silence, chat acceptance, or message text.
- Add reminders and a route for the requester to flag an unreported donation for staff follow-up. Define reminder timing and evidence requirements during implementation; neither a timeout nor an unsupported requester claim automatically confirms a donation.
- Add an owner Close request action with reasons such as Need met elsewhere and No longer needed. Distinguish this closure from fulfillment by verified donations in status/history and analytics. Do not create a donation, completed donor match, or cooldown merely because a requester closes the post.
- Permit staff review of legitimate donation reports after request expiry, with reliable donation-time evidence and an explicit late-report policy. Current confirmation requires an OPEN request before its deadline, so this needs a backend change. Preserve pending reports through expiry; do not reopen active matching, double-count units, or alter donor-window timestamps without a documented rule.
- Verify unreported, rejected, late and confirmed reports; partial donations; external fulfillment; manual closure; and accepted/pending/declined chat independently.

## Approved planning amendment: one administrator and account status

- Keep markseejr@gmail.com (currently user ID 3) as the sole administrator. The latest read-only check still found 18 active admins and 97 active members: the prepared cleanup has not been applied.
- Interpret removal of Admin/Officer from the role control as removing ordinary role-assignment/promotion options and the flow that creates additional administrators. Keep the designated account's internal admin role and required staff authorization. Use a single consistent administrator label in the interface.
- Enforce the one-admin rule in backend create/update/import paths as well as the UI. Reject direct API promotion and concurrent attempts, and protect the designated administrator from accidental demotion/deactivation/deletion. Review maintenance and test fixtures against this policy before using them.
- Reconcile extra imported/demo administrators through the already planned reviewed cleanup. Do not silently convert accounts outside its reviewed scope. Retain fifteen representative accounts, including the sole administrator, and deliver new temporary credentials privately after the user runs cleanup.
- Deactivated means an administratively disabled account. Standby/cooldown means an active account temporarily restricted from donating or changing availability. Keep account activation, membership verification and donor availability/window indicators separate in user management.
- Current donor windows are 42 hours of standby and 90 days of cooldown, both beginning at staff confirmation and overlapping. Do not deactivate a donor merely because either window is active, or reset these windows during account cleanup.
- Verify administrator access and lockout protection, member denial of staff endpoints, disabled-account restrictions, and continued eligible chat/access for active verified donors during cooldown.

## Planning checkpoint

The consolidated scope, dependencies, evidence and acceptance checkpoints are recorded in [CHAT_DATABASE_NOTIFICATION_CHECKPOINT.md](CHAT_DATABASE_NOTIFICATION_CHECKPOINT.md). Existing maintenance scripts describe the earlier prepared baseline; review/update them for any new schema and singleton-admin policy before the final rehearsal. No new application behavior or database changes were made for these amendments.

## Latest planning amendment: expiry prompt, status, compact notices and investigations

User request, 14 October 2026. Documentation only; no application code/database change or ERD generation in this amendment.

- At the needed-by deadline, expire the post and notify its owner with a concise Still need blood? prompt and an Extend deadline action. If offline, show the prompt on their next visit and retain it in their notification center/Expired posts tab. Deduplicate per expiry event. Dismissing or ignoring the prompt leaves the request expired; acceptance requires a valid future date and the reviewed renewal flow, not automatic reopening. Preserve offers/reports/history according to the lifecycle policy above.
- Add a Cooldown indicator and filter in the user-management status area, alongside the account's Active/Deactivated access state. Derive cooldown from the authoritative donor-window logic and show its local end time in details. It is not a new editable account_status value: an active account can also be on cooldown. Keep standby distinguishable and combine filters/counts correctly; deactivation/reactivation must not clear donor restrictions.
- Move shared transient notifications from upper-left to lower-right and use a five-second dismissal timer. Keep a short title, at most one short explanatory sentence, and an optional brief action; move paragraphs, detailed dates/policies and technical errors to the relevant detail view. Avoid chat overlap on desktop and narrow screens. Pause the timer while interacting, preserve screen-reader access and reduced-motion support, and retain actionable emergency/expiry information outside the transient popup. Explicit confirmation dialogs are unaffected.
- Investigate the reported persistent 1 on the navigation chat icon. Source shows this is an unread-message count, not the shared popup: opening MessengerFlyout loads conversation summaries but does not mark messages read. FloatingChatTab posts the read cursor after loading a visible conversation and refreshes the total. Verify whether the badge clears after opening the actual unread thread; inspect failed read requests, cursor precision/import integrity, polling races and account-switch state if it does not. Do not mark unseen messages read merely by opening the list. Keep pending message-request counts separate from unread messages in the planned consent workflow.
- Investigate existing chats on the other regular account. The current listing uses conversation_participants without recipient-consent records; the planned acceptance gate is not implemented yet. Trace those actual participant records and imported/prior-start sources; exclude unaccepted relationships from the normal list after the reviewed transition. Preserve messages/audit history; do not silently grandfather consent or delete chats.
- Reconcile the reported 15 users in the database view with 97 in user management before any further cleanup. Read-only check using the current application configuration found bloodmatch on port 3306 with 97 active members and 18 active admins (115 total); bloodmatch_test on port 3306 has 206 members and 75 admins (281 total). Port 3307 was unavailable using the configured connection. These observations do not identify the user's 15-row view.
- User management is database-backed: UsersView fetches /api/admin/users with role_group=member by default; UserAdminController calls UserRepository.listAll, which counts/reads users with those filters. The UI's 97 matches the configured database's member count; it is not a hardcoded/demo total. Compare phpMyAdmin's server/port/database, users table total versus displayed page/filter, and the running API target/environment before assuming cleanup failed or repeating deletion. Runtime/browser target matching remains unverified.
- Acceptance checkpoints: one expiry prompt per expiry, ignored prompt stays expired, future-date renewal works; cooldown and activation filters remain independent; notices appear bottom-right and dismiss after five seconds; read-thread badge clears without erasing other unread threads; accepted-only normal chat list; user-management/API/database counts agree on the same target.

## Latest UI planning amendment: dialog, request form, chat and navigation

User screenshots and request, 14 October 2026. Continue documentation-only; no application changes yet.

- Logout confirmation: remove the explanatory paragraph (You can sign in again...) and its reserved space. Keep the concise question and put Stay signed in / Log out in an equal-width, side-by-side action row with consistent spacing. The screenshot already has adjacent buttons; improve their balanced sizing/alignment. Preserve dialog focus trapping, Escape/cancel, busy/error behavior and focus restoration. Do not auto-dismiss a confirmation using the transient popup timer, or remove essential consequence text from unrelated destructive confirmations.
- Shared transient notices: remove paragraph-style body copy; prefer a concise title and optional short action. Put multiple actions side by side with consistent spacing when they fit; preserve readable controls on narrow screens. Keep the previously requested bottom-right placement and five-second dismissal. Full explanations remain in the relevant page/details, not large popup cards.
- Create blood request modal: reduce the oversized title/header area and the excess space before the stepper. Add consistent vertical space between each complete label/input group so the quantity label does not touch the blood-type dropdown. Apply the same spacing across all steps, using existing typography/spacing tokens; preserve a reachable close control, validation, scroll behavior, footer actions and mobile layout.
- Chat search and message composer: inspect the reported black hover line and its CSS source. Remove the unintended hover underline/border treatment from both controls; use a restrained surface highlight. Keep a distinct accessible focus-visible indicator for keyboard users and preserve actual validation states. Verify empty/filled, hover/focus, light/dark and narrow-screen behavior; do not remove all borders/focus styles globally.
- Navigation create action: remove the visible circular background around the plus icon while keeping an accessible hit area, Create blood request label, hover/pressed feedback and current action.
- Navigation profile: remove the unintended internal blue line/underline on hover. Highlight the avatar image itself and keep the current animation, with reduced-motion support and visible keyboard focus. Preserve menu, status indicator and active/open state behavior; scope styles to this control rather than all navigation items.
- Acceptance checkpoint: compare both supplied dialogs after layout changes; verify all form-step spacing, chat search/composer hover versus keyboard focus, plus visibility/hit area and avatar-only highlight. Regression-check tab order, Escape, cancellation, submission and narrow-screen/dark-mode rendering.

## Recommendations awaiting a policy decision: administrator requests and Responses

- Recommend allowing the designated administrator to create blood requests using the ordinary request workflow and validation, without extra matching/urgency privileges. Requester identity must remain explicit. Do not add posting on another person's behalf without a separately defined authorization/attribution flow. Staff actions still require audit and existing no-self-review safeguards; a sole administrator's donation report cannot bypass independent review simply because no second staff account exists.
- Inspect RequestsController.create and RequestService.assertCanCreate for the actual admin eligibility requirements, and verify the resulting API behavior before claiming end-to-end support. A visible form alone does not prove creation works.
- Recommend keeping My Responses: it records offers, cancellation/withdrawal, donation reporting and completion status. Chat is coordination and does not replace those workflow records. Update its Message action to request/accepted-chat behavior and its expired-report behavior consistently with the planned lifecycle changes. A future clearer label such as My offers may be considered; removal/renaming is not yet approved.

## Latest planning amendment: privacy, full chat page and profile access

User request, 14 October 2026. Documentation-only additions; implementation and database changes remain pending.

### Privacy notice and email visibility

- Update the registration/privacy notice and its profile/document-review entry points to match the final implemented workflow: email for authentication/OTP and operational delivery, limited member-profile visibility, recipient-approved chat, message-request metadata/history, pending-registration retention, expired-request history and donation review/windows. Describe proposed behavior as pending until implemented; do not publish promises unsupported by storage/access/retention controls.
- Remove the email field/link from profile presentation, including the owner profile overview if present. Remove email from the other-member profile API allowlist, rather than merely hiding it with CSS. Retain the email internally for sign-in, OTP, recovery, email delivery and authorized staff account management; preserve necessary owner account-settings access separately. Do not delete the email column or break authentication.
- Inspect the notice's current claims about encryption, deletion/anonymization, sharing, cooldown and its contact address against actual behavior and an approved contact. Correct unsupported claims; avoid calling the system's administrative donation windows medically mandated. Keep municipality/barangay exposure consistent with existing limited-profile privacy rules; hiding email does not authorize revealing other private fields.
- Verify another member cannot retrieve the profile email through that endpoint, while authentication/OTP/recovery and authorized staff workflows continue to work. Confirm notice copy and acknowledgement/version handling reflect the final privacy policy without silently assuming old acknowledgements cover materially changed terms.

### Expand chat into a dedicated page

- Add a clearly labelled expand/Open full chat action in the navigation chat dropdown that routes to a dedicated chat page. Preserve the dropdown and floating chat for quick use.
- The full page provides an accepted-conversation list, message-request area and selected message thread, reusing existing API/context logic and the planned consent gate. Pending contacts remain outside the normal conversation list and cannot send ordinary messages. Require active verified accounts and participant authorization on page/API access.
- Preserve unread/read cursor semantics, existing message history, send/retry/loading/error states and account-switch cleanup. Coordinate page/floating polling to avoid duplicate overlapping fetch/read operations. Do not mark every conversation read simply by opening the full page.
- Use a responsive list/thread layout with a back-to-list control on narrow screens; support direct navigation/reload and unauthorized/deleted thread states. Keep navigation focus accessible and preserve the new chat-input hover styling and bottom-right notice placement without composer overlap.

### Investigate missing municipality and barangay

- Screenshot account: read-only inspection of the configured bloodmatch database found user ID 3 has location_id = NULL. Its owner-profile API composes location only when this reference is present, and the editor reads profile.location; thus this record has no canonical stored location to populate either selector. This explains the present empty display but does not establish how the reference became missing.
- Source tracing: registrationPayload includes location only when location.location_id is resolved; AuthService resolves a submitted selection and UserRepository stores location_id. Profile editing initializes from profile.location and saves location_id. A selected-looking label alone must not be mistaken for a resolved reference.
- Reproduce both JSON and multipart/ID-upload registration paths on the isolated database, including selection, typed-but-unselected labels, step navigation, OTP pending-registration handoff, municipality changes, async barangay loading and reload. Compare final payload, inserted reference, canonical location row and owner-profile response; establish the actual loss point before fixing code.
- Check imported/seeded users separately, invalid/orphan location references, option-code types and profile cache/editor initialization. Inspect whether owner overview should show barangay as well as municipality; retain other-profile privacy limits. Do not infer a user's address from their chapter or fill missing locations with invented defaults. Repair historical records only from verified source data or owner correction through the reviewed user-run process.
- Acceptance checkpoint: the selected canonical municipality/barangay survives registration, OTP, login, owner-profile display, edit/save and reload; switching municipality clears obsolete barangay values; genuinely missing locations remain truthfully labelled.

### Profile navigation

- Remove the separate center-navigation Profile icon/link, including its redundant mobile counterpart where applicable. Keep the avatar dropdown trigger: the user explicitly still wants access through that menu, and the previous avatar-highlight/animation plan applies to it.
- Make the page's account/profile summary card provide a clear keyboard-accessible route to /profile. Scope the clickable region so nested Account Details disclosure or other controls do not trigger unwanted navigation or create nested interactive elements. Keep a visible View profile action if making the whole card interactive conflicts with its controls.
- Add or retain an explicit View profile item in the avatar dropdown. The current menu already has See your profile; consolidate rather than adding duplicate links. Close the menu on navigation and retain the profile route/deep links and other menu actions.
- Verify desktop/mobile entry points, card activation by keyboard, disclosure behavior, active-route state and dropdown dismissal. No new public directory or email exposure is introduced.

### Checkpoint for this amendment

Privacy notice and profile API agree on hidden email; full-chat navigation preserves consent/unread state; location persistence is reproduced and fixed at its verified source; center Profile navigation is removed while page-card and dropdown access remain available. All items are pending implementation; no ERD generation is included.

## Proposed retention schedule for privacy-notice review

The account-deletion and reported-chat workflows below supplement this proposed schedule. Exact retention periods still require final policy approval and verified enforcement before publication.

Read-only finding: no scheduled general user-data deletion/anonymization policy was found. Deactivation records a disabled state and timestamp; it does not automatically erase the account. The current notice's assertion that permanently inactive accounts are purged/anonymized is not supported by the inspected lifecycle. Token expiry is not proof of record deletion, and one-off demo maintenance is not a production retention policy.

The following periods are proposed product policy, not existing behavior, user-approved periods, or statutory minimums. Approve and implement a justified schedule before publishing these promises in the live notice.

| Data | Proposed retention and trigger |
| --- | --- |
| Necessary active-account details | While the account remains active and the data is necessary for its stated purpose; do not retain obsolete data merely because the account is active |
| Profile/contact data after permanent closure | Delete or anonymize within 30 days of confirmed permanent closure; temporary deactivation and donor cooldown do not start this timer |
| Abandoned pending registration and temporary uploads | Delete within 24 hours after registration expires or is cancelled; OTP validity remains its own shorter security limit |
| Uploaded identity documents and extracted copies | Delete within 30 days after the final verification decision or completed appeal; keep only necessary verification outcome/audit metadata |
| Chat messages and message-request records | Delete within 12 months after the last conversation activity or final request decision; account-closure erasure may occur earlier, with reviewed treatment of the other participant's records |
| Identifiable request/donation/audit records | Up to 12 months after final closure/decision or the end of an active donor restriction, whichever is later, only where their documented purpose justifies retention; then delete or genuinely anonymize |
| Backups containing deleted data | Age out within 90 days after deletion from live storage; access restricted and restoration must reapply recorded erasures |

Specific legal obligations or an actual dispute/investigation may require a documented, restricted retention hold for necessary records; periodically review and release it. Do not retain everything indefinitely for hypothetical future use. This proposal requires review against actual operational needs and any applicable obligations.

Privacy-notice draft for use only after approval and implementation: "We retain necessary account information while your account is active. Following confirmed permanent account closure, we delete or anonymize profile and contact information within 30 days. Identity documents are removed within 30 days after final verification or appeal. Chat records are retained for up to 12 months after their last activity, and necessary request, donation and audit records for up to 12 months after final resolution or the end of an active donor restriction. Deleted information may remain in restricted backups for up to 90 additional days. Limited records may be retained longer where required by law or necessary for a documented dispute. Temporary deactivation and donation cooldown do not constitute permanent account closure."

Implementation checkpoint: document category-specific purposes/triggers; approve periods; prepare user-run migrations/workers; cover files/OCR, chat free text, logs, outbox and backups rather than users rows alone; avoid FK cascades that destroy required evidence; verify anonymization cannot identify a user through retained linkage. Publish notice periods only when enforcement and exceptions are verified. Source: [NPC implementing rules, section 19 on retention](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/).

## Consolidated planning amendment: account deletion and reported-chat review

- Account settings -> Delete account -> review consequences/retained categories -> verify identity using current password or a secure purpose-bound email code -> explicit permanent-closure confirmation -> immediate sign-out. Backend verification, CSRF protection and a transaction must enforce this flow; a hidden button alone is insufficient.
- Immediately revoke sessions/tokens, hide the closed profile, prevent new chats/offers/matching, close active requests and withdraw unresolved offers. Preserve completed donations and legitimate pending reviews under the reviewed lifecycle/retention rules. Permanent closure differs from reversible deactivation and donor cooldown.
- Schedule profile/contact erasure or anonymization within the proposed 30-day period after confirmed closure. Cover private documents, extracted data, messages containing identifiers, notification/email payloads and linked records; do not equate replacing a name with true anonymization. Backups and lawful investigation holds follow the approved category-specific schedule.
- Protect the sole administrator from self-deletion until an authorized, reviewed transfer of responsibility is complete; do not silently leave the system without staff access. Account-deletion implementation and exact retention periods remain pending.
- Report conversation: a participant selects relevant messages and a reason; backend verifies participation/message ownership within that conversation and creates a restricted report for the designated administrator. Provide report feedback and preserve a scoped evidence snapshot where necessary.
- Permit the designated administrator to review reported messages and only the surrounding context needed for the case. Do not grant unrestricted chat browsing or ordinary message-send access as a side effect. Enforce report-scoped authorization in every evidence endpoint and log access, reason and action. Review conflicts where the administrator is a participant; no silent self-investigation privilege.
- A documented open investigation may hold necessary evidence beyond ordinary deletion while restricting access. Define review/release criteria and expiry; do not retain entire unrelated conversations indefinitely. Exceptional staff access without a participant report is not authorized by this amendment and needs a separately defined policy.
- Update notice wording to identify the designated administrator, reported-message/context access and logging, rather than generic Chapter Officer access to all chats. Resolve organization/contact placeholders before publication and align notice/consent wording with actual implemented controls.
- Verify unauthorized evidence access, unrelated-thread access, report submission/retries, audit logging, closure/session revocation, report holds and eventual erasure. Prepare required migrations and live checks for the user to run; no application or database changes are made in this planning amendment.
