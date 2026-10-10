# Email outbox and lifecycle notification proposal

Marker: BM-SAFETY-PHASE5-PROPOSAL. Historical proposal; current chat approvals and the current alignment in BLOODMATCH_SAFETY_FIX_PROGRESS.md supersede pending gates and conflicting channel/timer suggestions below. Outbox/accounting/enqueue and a manual fake worker are now prepared; 020/021 have user-supplied test-target evidence. This document is not current completion evidence.

## Source-verified current behavior

- NotificationService inserts in-app records, then sends eligible email synchronously. NotificationRepository returns NULL on a duplicate (dedup_key + generation), so repeating matching does not retry a failed email. Unconfigured SMTP and ordinary rate-limit deferrals leave no durable delivery job.
- MatchService calls notifyMatchGeneration inside its request-locked transaction. Request creation has already inserted its request before invoking matching; matching failure is logged and creation still succeeds. Removing SMTP resolves network blocking, but does not itself repair matching-generation failures caused by SQL/reference data errors.
- Emergency donor email occurs on request_created only; Routine/Urgent, edit/rematch and donor-refresh opportunity email are currently disabled. Notifications are capped at 500 candidates per generation. Chapter/distance affect ranking; compatibility/current donor safety still control eligibility.
- MatchResponseService commits its response/requester notification, then attempts Emergency requester email. Its requester-response dedup key suppresses subsequent withdrawal/re-response alerts for the same donor/request pair.
- DonationService submits a report with audit only; decisions commit then notify the donor, including normal email. Fulfillment has no requester/affected-donor notification. Cancellation/expiry currently notify the requester in-app only, within the closure transaction.
- Verification upload/resubmission alerts active admins only. Verification decision and account deactivation/reactivation attempt normal email. Role/chapter changes lack equivalent alerts. Several verification/account/donation notification calls occur after their business writes, so an email outbox alone cannot make all events durable without moving notification creation into the business transaction.
- Normal email cap is five per recipient/hour, Emergency bypasses it. Current counter uses notification.created_at rather than emailed_at, which is unsuitable for delayed delivery. Mailer exposes a boolean result and logs raw exception messages; SMTP acceptance is not proof of inbox delivery. No real mail or database was used to verify these facts.

## Recommended small phases

| Phase | Deliverable / likely affected files | Verification and gate |
|---|---|---|
| 5A | Additive outbox migration (proposed 021); EmailOutboxRepository/service/CLI; NotificationService/Repository and Mailer; MatchResponseService, DonationService, VerificationService and account-status transaction wiring | First preserve existing event recipients/channel coverage and 500 cap. No SMTP from request/response/status handlers. Offline mocked transport, user-run test migration/API/worker/failure/retry tests. Approval A below first |
| 5B | Approved new recipient/channel coverage in request lifecycle, donation submit/fulfill/withdraw, verification upload and role/chapter actions; notification fan-out in bounded DB batches without truncating eligible recipients | Approval B/C first; atomic event and recipient/delivery insertion, repeat/concurrency/scope/privacy assertions. No permissions or donor/request eligibility change |
| Later, separate | Non-response standby/recovery and post-donation-window-ended events | These await their own timer/state migration and operational approval; approving the matrix does not implement them now |

5A includes only the transaction changes needed to commit a status/response with its notification and email job, preserving existing authorization/state guards. Successful audit writes remain in that transaction; audit failure durability is still separate. Move donation decision notification creation inside its existing transaction; wrap verification/account status changes and relevant notification writes in transactions with current-actor/target state checks. Do not replay business transitions or SMTP automatically during transaction retries. Initial request creation remains independent of subsequent matching, as currently implemented. Durable recovery of an entirely failed matching-generation transaction is a separate follow-up, not a claim made by this outbox.

## Approval A: outbox design and operating policy

Use existing PHP/MySQL/PHPMailer, with no Redis or new package. Proposed single email_outbox table:

| Column/group | Proposed shape/purpose |
|---|---|
| id | BIGINT UNSIGNED primary key |
| notification_id | BIGINT UNSIGNED NOT NULL, FK to notifications, UNIQUE: one email job per notification |
| delivery_key | VARCHAR(190) NOT NULL UNIQUE; stable identifier from notification ID/channel, also used to derive stable Message-ID |
| priority | ENUM normal/emergency NOT NULL |
| status | ENUM pending/leased/sent/failed/cancelled NOT NULL |
| attempts, next_attempt_at | SMALLINT UNSIGNED default 0; DATETIME UTC indexed for due claims |
| lease_token, lease_expires_at | CHAR(32) nullable claim nonce; DATETIME nullable. Conditional updates require current ownership |
| expires_at | DATETIME nullable; opportunity deadline or proposed normal-message lifetime |
| last_error_code, cancel_reason | VARCHAR(60) nullable allowlisted codes only; no raw SMTP/SQL errors |
| created_at, updated_at, sent_at | UTC timestamps, nullable sent_at denotes SMTP acceptance |
| Indexes | (status, priority, next_attempt_at, id), (status, lease_expires_at), notification/delivery uniqueness |

Body/event details remain in the existing notification row, recipient address comes from its user's current verified email at send time. No additional copies of email addresses, passwords, OTPs, reset tokens, documents, DOB, phone or coordinates. Existing notification text is escaped for email. Internal links still require current authorization; notification access does not grant access to history/documents. Proposed FK deletion policy: cascade if its notification is deleted, consistent with existing user-notification removal; no new retention/deletion job.

The migration creates structure only; no backfill, status changes, deletion or changes to migration 020. Rollback is not automatically executed and would discard queued jobs; review it separately. Missing schema must fail clearly rather than silently reverting to synchronous SMTP. Read-only default review and user-only apply helper must verify actual bloodmatch_test and port 3306, rejecting connector fallback before DDL/writes. No migration is applied by the agent.

Proposed defaults requiring approval:

- Enqueue email atomically with its in-app notification/business event; SMTP outages never block the request handler. A SQL enqueue failure rolls back that event transaction rather than falsely claiming durable delivery. Existing request creation remains committed if its separate matching transaction fails; return/log a sanitized matching-pending result rather than claiming donors were notified.
- One-minute test-worker schedule, signed-in Windows account, hidden, IgnoreNew plus file lock. Prepare user-controlled Preview/Setup/Verify/Remove only after approval; never install/start here. Claim one due job at a time in a short transaction, commit before SMTP; no request/donor locks across SMTP. Avoid reliance on MySQL-8-only SKIP LOCKED; use ordered conditional claims compatible with the existing XAMPP engine.
- Maximum 20 attempts per run and a 45-second soft budget checked between jobs, Emergency first. Transport timeouts proposed: 10-second connect and 20-second SMTP command limit; these need measured validation and do not guarantee a hard whole-send deadline. Lease 120 seconds, ownership-checked completion, expired lease recovery; account for elapsed transport time before reclaiming a send. Task/file overlap protection complements DB leases, without guaranteeing exactly-once external delivery.
- Six transport attempts total: immediate first opportunity, then retry after 1, 5, 15, 60 and 240 minutes; delays are measured from each failed attempt and rounded to the next worker tick. SMTP-unconfigured and normal-rate-limit delays do not consume transport attempts; configuration pause retries after five minutes. Exhaustion -> failed. Malformed/unverified/deleted recipients -> cancelled with a safe code; status email can still reach a deactivated but email-verified account. No donor outreach to inactive/unverified accounts.
- Normal messages expire after 72 hours to avoid delivering an old status update indefinitely. Emergency opportunity lifetime ends at request deadline; response/withdraw alerts additionally stop if the underlying response is no longer active or request closes. Closure/verification/account/history messages use their own normal lifetime, not OPEN-request eligibility. Newer conflicting account/verification/role/chapter transitions supersede unsent older jobs.
- Retain five normal SMTP acceptances per user/hour, counted by actual sent_at/emailed_at; rate-limit deferrals reschedule instead of disappearing. Emergency keeps existing bypass. A single worker initially serializes this accounting; any later concurrent/multi-host delivery needs atomic per-recipient limit reservations, separately reviewed.
- Permanent SMTP/configuration failures are reported by safe codes, with no passwords, recipients, message body or raw provider error in logs. Worker summaries expose counts/job IDs/status and UTC only. Existing worker/notification logs must be sanitized where used by these paths. sent means SMTP accepted; bounce/inbox evidence requires a separately approved real delivery test. A crash after acceptance but before marking sent can duplicate a retry even with stable Message-ID; recipients' providers are not required to deduplicate it.
- Recheck current request/deadline, generation, live donor age/consent/availability/account/compatibility and unresolved offer immediately before Emergency opportunity delivery. Cancel stale jobs on closure/generation changes/ineligibility. Chapter/distance remain ranking factors. Never hold workflow locks across network I/O: closure can still win after the last check while SMTP is underway; report that small race honestly, rather than promising recall of already sent mail.
- First rollout queues new events only. Existing failed/no-emailed_at records are ambiguous (not requested/unconfigured/rate-limited/failed); do not bulk resend. Failed-job requeue is a future explicitly authorized CLI action, not a new management UI in this phase. OTP/password-reset remain outside this outbox pending separate secret/TTL design.

## Approval B: event/recipient/channel table

All email below is queued to verified addresses. N = normal priority subject to five/hour; E = Emergency priority. Approval is for these recipients/channels, not additional medical rules or staff permissions. 5A preserves existing coverage; expansion below is 5B.

| Event | Proposed recipient | In-app | Email | Current delta / timing |
|---|---|---|---|---|
| Routine/Urgent compatible opportunity | All eligible compatible donors, ranked across chapters | Yes | None | Preserve channel; 5B remove recipient truncation using DB batches |
| Emergency compatible opportunity | All eligible compatible donors, ranked across chapters | Yes | E at initial creation only | Queue existing creation email; 5B remove 500-recipient total cap |
| Material edit/rematch/newly eligible donor refresh | Current eligible affected donors | Existing generation rules | None | Preserve current edit/rematch email policy; stale initial email cancelled |
| ID upload/resubmission | Active assigned-chapter officers plus active admins; resubmitting member receives acknowledgment | Yes | None | Add officers/member acknowledgment; never embed ID/consent contents |
| Verification decision | Reviewed member | Yes | N | Queue current decision coverage |
| Deactivation/reactivation; role/chapter change | Affected account | Yes | N | Queue current status; add role/chapter events, no wider member disclosure |
| Donor response | Requester | Yes | E for Emergency only | Queue current channel; cycle behavior approval C |
| Donor withdrawal | Requester plus withdrawing donor | Yes | E to requester for Emergency only; none to donor | New event, after actual transition only |
| Offer invalidated after material edit | Affected donor; requester if an accepted offer is affected | Yes | None | Event wiring deferred until separate offer-treatment rule is approved/implemented |
| Donation report submitted | Active request-chapter officers; active admins only if no active assigned officer exists | Yes | None | New reviewer alert; chapter scope, no broad staff donor contact |
| Donation confirmed | Reporting donor plus requester | Yes | N to donor; requester email only when request is fulfilled | Keep existing donor message; add requester progress; avoid duplicate fulfillment email |
| Donation rejected, including closed requests | Reporting donor | Yes | N, generic message | Keep rejection reason in authorized history/audit only |
| Request fulfilled/cancelled/expired | Requester plus donors with unresolved offers or pending reports at closure | Yes | N | Snapshot affected IDs before cleanup; exclude completed-only donors, deduplicate same recipient |
| Non-response standby/recovery | Donor | Yes, later | N, later | Pending standby implementation/migration, no timer added here |
| Post-donation windows end | Donor | Proposed later | None | Separate automatic-transition/event approval; no lazy-read notification side effects |

For closure, snapshot both unfinished offers and pending-report donors under existing request/ordered-user locks before closing offers. Notification rows/outbox insertion share that closure transaction. A pending report remains available for explicit reasoned rejection and cannot confirm; no donation/cooldown is created by a notification. Completed donors receive their existing donation message; completed-only donors are not included merely because a request later expires.

## Approval C: event identity / repeated response policy

Recommended: every actual withdrawal/re-response transition is a new event, but duplicate clicks/retries/rematches without a new transition produce no duplicate. Generate a per-transition identifier inside its existing locked transaction and persist it in notification dedup keys (one per event + recipient, generation NOT NULL); all recipients for that transition share the identifier. An identical second Respond while RESPONDED generates nothing. Withdrawal after report submission remains forbidden. This changes notification repetition only, not multiple-active-response permissions.

Alternative: preserve one requester-response alert per donor/request pair for its lifetime, even after withdrawal/re-response; new withdrawal alerts still need their own real-transition identity. Approval C must explicitly choose one. Material-generation opportunity alerts keep one per donor/request/generation; no compatibility-tier reorder. Initial-only Emergency email is the recommended table default; allowing fresh Emergency email after a material edit is a separate channel choice, not silently enabled by queueing.

## Planned user-run validation after approval and implementation

No new test command is runnable yet: there is no outbox schema/helper/worker/test in this proposal. Do not run invented CLI paths or a generic all-pending migration command. After implementation, provide exact default-review/apply commands and focused suites with:

1. Read-only actual target and migration shape/ledger check, user-only additive apply to bloodmatch_test:3306; existing notifications/statuses/020 remain unchanged.
2. Forced SMTP unavailable/delayed mock: Emergency request/response returns without SMTP; event and one delivery job persist atomically; notification duplicate creates no extra job; failed SQL transaction creates neither notification nor job.
3. Mock transport outcomes: configured failure/recovery, six-attempt schedule, normal cap deferral, Emergency bypass, lease crash/expiry/ownership, overlap, stable Message-ID, terminal failure and TTL/supersession. Mock acceptance never claims genuine inbox delivery.
4. Close/edit/response-withdraw/revoke eligibility before sending: stale outreach cancelled; closure/status notifications remain deliverable according to their own rules. Test distant/cross-chapter compatible donors remain eligible; >500 recipients receive persisted jobs in bounded batches after 5B approval.
5. Approved event matrix: scope and recipients, current-role access, repeated transition/idempotency, report reason privacy, closed-report review, no donation/cooldown from messaging.
6. User-only one-minute test schedule with fake transport, fresh logs/fixtures and cleanup retaining append-only audit history. No task installation/start, database suite or real email by agent. No fixture suite concurrent with any installed expiry task.

Lightweight future checks: PHP syntax, pure fake-transport/clock tests and PowerShell parse-only once after edits. No frontend build unless that phase actually changes UI.

## Historical requested decisions ? resolved items must not be asked again

A. Approve 5A implementation/preparation of this additive outbox schema, operating defaults, test-only worker/task commands and no-backfill policy; migration application/task execution stay user-only. Alternatives: revise worker/retry/lifetime settings, or defer infrastructure.

B. Approve the table for later 5B coverage, including all compatible recipients without the existing total cap; alternatives: approve queueing existing coverage only, or specify recipient/channel edits. Deferred standby/window/offer-treatment rows stay deferred either way.

C. Choose actual-transition alerts for withdrawal/re-response (recommended), or retain lifetime response suppression. Keep material-edit/rematch opportunity email off unless explicitly changed.

These choices are required by the user's original infrastructure/schema and notification-coverage approval gate. No user permission is inferred from this document. After answers, implement 5A alone and report its user-test checkpoint before starting 5B.

## Current amendment - 2026-10-10

Actual-transition withdrawal/re-response alerts were already approved and reaffirmed: each genuine transition gets one in-app event; repeated clicks/retries stay silent. Multiple-response permissions do not change. The approved email allowlist is direct OTP/password reset, queued initial Emergency donor opportunities and membership verification decisions only. Response, withdrawal, account, donation, closure and standby updates are in-app; material-edit/rematch email stays off. Quiet visible opportunity reuse is separate from persistent per-generation timeout history. Additive 022 preparation and donor acknowledgment are approved; application/backfill/database tests remain user-only. Only older-generation timeout supersession and removal of the retained 500-email-job cap are currently awaiting answers. Worker validation, operational targets and new infrastructure/data changes remain execution gates.
