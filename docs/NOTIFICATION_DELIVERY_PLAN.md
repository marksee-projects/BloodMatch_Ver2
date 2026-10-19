# Notification delivery proposal — 2026-10-19

Status: subsequently approved by the user's “proceed with that plan” instruction on 2026-10-19 and implemented in source. Historical proposal follows. Login/reconnect is silent even with zero unread; fresh allowlisted events use 60-second server-time freshness, a 30-second background cap with Emergency replacement, and Web Locks/account-scoped ID/time-only storage for cross-tab claims. Unavailable coordination falls back to bell-only. Action feedback/errors remain separate. Allowlisting prevents the observed cancellation duplicate; inbox generation and read behavior remain unchanged. No rows were deleted or bulk-marked read.

An explicit `offer.donation_prompt` in-app type distinguishes donor-report prompts from ordinary coordination, retaining recipients/deduplication/no-email policy. Accepted-offer notification destinations reuse conversation authorization. API additions are read-only server time and authorized conversation destination; no notification schema migration, new channel or worker. Focused checks/build pass; native multi-tab and visual browser verification remain user-run. See [main migration and source checkpoint](OFFER_CHAT_MAIN_ROLLOUT_031.md).

## Observed implementation

- ProfilePage/RequestsPage cancellation handlers publish an action success notice. RequestLifecycleService also persists the requester's cancellation notification and NotificationService notifies affected donor matches. App.jsx independently turns newly polled unread notifications into pop-ups, using notification-ID deduplication. The action notice uses a different key, so the same operation can show twice.
- First nonempty unread fetch suppresses most historical pop-ups, but explicitly allows unread expiry and Emergency opportunity alerts. Initialization happens only when unread count is positive; it is not a reliable session-start event boundary. Seen IDs are held in component memory and are not an account-scoped durable delivery cursor.
- NotificationProvider displays up to three pop-ups simultaneously and retains additional queued items. It deduplicates only while the same key remains in the queue, not across session/reconnect/tab activity.

## Proposed presentation and recipients

The bell remains the durable activity/action inbox. Existing authorized recipient selection, notification generation, donor opportunity tracking and email policies remain unchanged in this first implementation. Do not remove donor opportunity records merely to silence pop-ups: those records also support existing safety/non-response behavior. Audit any later recipient narrowing separately.

| Event | Intended authorized audience | Presentation |
|---|---|---|
| User finishes their own action: cancel, offer, accept, submit or acknowledge | Acting user | One short action confirmation; no second pop-up from its saved notification |
| Request cancelled, closed, expired or fulfilled | Owner and currently authorized affected donors under existing rules | Bell only; refresh related request/chat status silently |
| Routine/Urgent compatible opportunity | Existing eligible matched donors | Bell only and live feed refresh |
| Emergency compatible opportunity | Existing eligible matched donors | Bell; at most one fresh, still-open live pop-up, subject to caps; no login backlog replay |
| New donor offer or withdrawal | Requester | Bell only; update offer list |
| Accept & chat | Accepted donor | Bell plus one fresh live pop-up with Open chat, unless already viewing that context |
| Still arranging or no-donation coordination update | Other authorized participant | Bell only; update context card; no hold/credit changes |
| Donor details requested or report submitted for acknowledgment | Required participant | Bell plus one fresh live action pop-up unless already viewing the exact context/report |
| Donation acknowledged, confirmed, disputed, rejected, clarification or hold outcome | Authorized participants/reviewers under current workflow | Bell and updated report/status; clarification requests requiring a reply may use the same capped fresh action pop-up |
| 24-hour reminder / 48-hour escalation | Existing requester, participants and non-conflicted reviewers as already defined | Bell only; preserve one reminder/escalation and original report clock |
| Membership verification decision, new staff review items | Member / authorized staff respectively | Bell only; preserve approved emails independently |
| Chat messages | Existing authorized conversation participants | Chat unread badge and active thread update; no additional generic bell pop-up for each message |
| Failed user action | Acting user | Clear actionable form error or one error pop-up; preserve entered data |

Private evidence, rejection reasons and sensitive review details never appear in generic pop-up previews. Permission checks still apply to destinations.

## Login, freshness and duplicate rules

1. At login, refresh and reconnect, fetch current unread state as a silent baseline. Display the bell badge, never replay individual historical items. Suppressing a pop-up does not mark an item read. Keep the user's approved bell-opening behavior that marks current notifications read.
2. Establish the baseline even with zero unread notifications. Notifications encountered after baseline must also have a server-created timestamp within 60 seconds of delivery to qualify for a live pop-up. Older/missed items stay in the bell. This intentionally permits bell-only delivery around a reconnect rather than a backlog burst.
3. Clear account-specific delivery/queue state on logout/account switch. Coordinate visible tabs so the same remote event does not pop up in multiple tabs. Scope deduplication by account, notification/event and version; avoid sensitive content in browser storage.
4. Use semantic event correlation for the action response and its persisted notification. Keep one action confirmation for the actor while leaving any existing inbox entry intact. Do not deduplicate by text: two different requests may have identical titles. Repeated idempotent actions must not create extra alerts.
5. Show at most one background-event pop-up at a time and at most one per 30 seconds. Coalesce eligible arrivals from the same request/report; different events that exceed the cap remain in the bell without a delayed replay queue. Do not cap away action errors. An eligible fresh Emergency event may replace a routine background pop-up, but does not bypass consent, eligibility or safety.
6. While viewing the exact thread/report, update its card and action state instead of showing a redundant background pop-up. Merely viewing a card must not acknowledge a donation or change bell read state beyond existing explicit rules.

## Impact and preservation

- Frontend: central presentation classifier shared by notification polling; explicit baseline/cursor lifecycle; account/tab deduplication; capped background delivery distinct from local action feedback; exact-context visibility suppression. Retain established popup styling/accessibility and pause-on-focus/hover behavior.
- API: inspect existing notification ID, created timestamp, recipient/type, related entity and action-response fields. Add only missing presentation/correlation metadata if needed; never expose evidence/reasons or change authorization. A stable event correlation is preferable to timing guesses for actor suppression.
- Schema: none planned unless inspection proves existing fields cannot support reliable correlation; propose any needed additive change before applying. No historical deletion or bulk mark-read.
- Preserve all matching notifications, email coverage/budgets/cutovers, request expiry, report reminders/escalation, chat consent, evidence privacy and donor safety rules. This is a presentation proposal, not a new notification channel or recipient expansion.
- Approval would supersede the current special replay of unread expiry/Emergency pop-ups at login. Fresh eligible Emergency pop-ups remain; Emergency email is unchanged. Previously approved opening-the-bell read behavior remains.

## Ordered implementation and acceptance

1. Inventory notification types and correlate actor feedback to stored events; implement the allowlist/classifier and silent baseline.
2. Add account/tab-aware deduplication, fresh-event caps/coalescing and exact-context suppression; keep request/feed/chat invalidation independent of popup decisions.
3. Focused offline classifier/cursor tests and frontend build; user-run browser checks on the test database. No migrations, database/PowerShell suites, task changes or emails are required for this proposal.
4. Acceptance: login with 50 old unread items yields zero pop-ups and correct badge; cancellation gives actor one success message and donor bell-only update; two distinct requests remain distinct; zero-unread login followed by a fresh eligible event works; stale Emergency is silent while a fresh open eligible Emergency can alert once; refresh/reconnect/multiple tabs/account switch produce no duplicate or cross-account alerts; active chat is quiet while status updates; failures remain clear; reminder/escalation and approved emails are unaffected.

Recommended approval: adopt the table, silent login including old Emergency alerts, 60-second freshness and one-background-popup-per-30-second limits. No behavior has been implemented at this planning checkpoint.
