# Non-response Standby: 42-to-48-Hour Change Proposal

> **Implementation checkpoint 20 October 2026:** The user approved the prospective policy, full timeout eligibility/stale invalidation and no revival on recovery. Source and offline tests are implemented; legacy 42h deadlines/active state and independent donation/report clocks are retained. [Actual verification and handshake diagnosis](NON_RESPONSE_48_IMPLEMENTATION_RESULTS.md) supersede the prepared-only language below. SQL/concurrency testing is blocked before target verification; no app data/migrations/tasks changed. The remaining body is the pre-implementation proposal/alternative history, not pending permission to repeat already approved decisions.

Original proposal prepared 20 October 2026 (Asia/Manila); retained as decision history. The implementation checkpoint above records approval and current verification limits. Companion: [paper/checklist reconciliation](PAPER_CHECKLIST_RECONCILIATION.md). Use the [existing checklist](../features/BLOODMATCH_CURRENT_REQUIREMENTS_STATUS.md) and [flow audit](../BLOODMATCH_FLOW_SAFETY_AUDIT.md) for all unchanged functionality.

## 1. Precisely bounded change

The paper Scope supplied by the user explicitly requires standby after **48 hours**; its fifth objective says the specified period. At proposal time code and old requirements used 42h. The later user instruction approved the focused 48h implementation with preserved existing deadlines and active standby; current source/test status is in the checkpoint above. No historical-data adjustment is authorized.

Change the deadline for a **new unanswered donor opportunity** from 42h to 48h after its committed in-app notification/opportunity issuance. Preserve the current per-request/donor/generation identity, explicit acknowledgment recovery, safeguards against consumed/obsolete timers, and all unrelated clocks.

48 hours is the time allowed to respond, **not the duration of standby**. Once applied, non-response standby has no automatic end; acknowledgment clears only that restriction. SMTP delivery, reading a notification, login, accepting a chat request and a requester donation prompt are not the non-response clock's start.

| Clock/state | Current anchor and interval | Proposed handling |
| --- | --- | --- |
| Non-response deadline | donor_opportunities.notified_at; stored due_at is currently notified_at + 42h | New opportunities after cutover receive +48h; transition policy below governs old rows |
| Non-response standby | donor_response_state.standby_started_at records actual processor transition | Keep acknowledgment-based recovery; do not add a 48h auto-unblock timer |
| Post-donation standby | Latest preserved donation occurrence anchor; standby_hours setting, previously inspected as 42h locally | **Unchanged**; do not edit setting/seed or replace its 42h references |
| Post-donation cooldown | Same occurrence anchor; cooldown_days setting, previously inspected as 90d locally | **Unchanged**; windows run from the same anchor, not 42h plus 90d |
| Donation-report reminder | Original reported_at +24h without acknowledgment | **Unchanged** |
| Donation-report escalation | Original reported_at +48h without acknowledgment | **Unchanged**; same number does not mean same event/processor |
| Report-specific hold | Begins on donation claim; retained until authorized resolution | **Unchanged**; no elapsed-time release |
| Other communication/delivery timers | Chat consent expiry/retry, prompt rate limit, email retries/grouping | **Unchanged** |

Sources: [NonResponseStandbyService](../../backend/src/Services/NonResponseStandbyService.php), [DonorEligibilityService](../../backend/src/Services/DonorEligibilityService.php), [settings](../../backend/src/Services/SystemSettingsService.php), [seed](../../database/seeds/003_system_settings.sql), [DonationCompletionPolicy::dueActions](../../backend/src/Services/DonationCompletionPolicy.php), [DonationFollowupService](../../backend/src/Services/DonationFollowupService.php).

## 2. Current timer/state behavior that must survive

- `opportunity()` writes the timer in the same transaction as the in-app opportunity. It uses UTC microsecond timestamps and a unique request/donor/generation key. Repeated same-generation insertion does not restart the timer; a newly issued generation supersedes older pending generations.
- The timer writer currently contains `INTERVAL 42 HOUR`. The schema in [022](../../database/migrations/022_non_response_opportunities.sql) stores notified_at/due_at; it has no configurable response-window field or default duration to change. `system_settings.standby_hours` controls post-donation standby, not this timer.
- `processDue()` selects pending rows by stored due_at, then uses request → donor → opportunity locks and checks the current match/generation. It updates the separate response-state row and sends an in-app standby alert/audit event. It does not overwrite donor_availability or last_verified_donation_at. The triggering opportunity remains pending until subsequent resolution/acknowledgment.
- `acknowledge()` retires pending timers whose **due_at** is at or before acknowledgment, clears standby_started_at and sets consumed_through to acknowledgment time. `due_at <= consumed_through` remains excluded, including exact equality. Not-yet-due timers remain; they may qualify later. [ProfileController](../../backend/src/Controllers/ProfileController.php) reconciles opportunities after acknowledgment.
- [NotificationService::notifyMatchGeneration](../../backend/src/Services/NotificationService.php) can reuse an existing visible notification while issuing a newer opportunity generation. Do not infer a new timer's anchor from the visible notification's old created_at or read state.
- [Application safety runner](../../database/run_application_safety.php) processes expiry before non-response; it is activation/overlap guarded. Actual recurring execution is not established here. Changing a constant alone does not make scheduling operational.

No current donor/timer counts were collected for this proposal. Existing timers and active standby require a later approved, read-only impact inventory; do not infer zero affected users from absent evidence.

## 3. Eligibility exclusions: current checks versus proposal

Current `processDue()` excludes nonpending/not-yet-due/consumed opportunities; missing, terminal or overdue requests; missing/answered/completed/closed or different-generation matches; donors already on non-response standby; and users who are not active enrolled members. Answered, invalidated, superseded and acknowledged rows are not candidates.

It does **not** call the complete live request eligibility evaluator. Thus membership/email verification, current age/consent, report holds, post-donation restrictions, effective availability and current blood compatibility are not independently rechecked by this timeout path. Other reconciliation may have removed some opportunities earlier; that is not a complete timeout guarantee.

**Recommended policy amendment, requiring approval:** at the timeout decision, retain all current opportunity/request/generation checks and also require the donor to remain eligible to respond to that request under the shared live evaluator. Obtain current consent, hold and non-response projections through the locked repository path. Check existing active non-response state first so it is not treated as a newly invalid opportunity merely because the evaluator reports that same restriction.

| State at timeout | Recommended outcome |
| --- | --- |
| Pending/rejected/unverified membership or unverified email | Do not apply a new non-response sanction |
| Staff, deactivated/closed, no longer enrolled | Do not apply a new sanction; preserve existing account restrictions |
| Missing/invalid DOB, under 16, or age 16–17 without documented consent | Do not apply a new sanction; preserve age/consent prerequisites |
| Active report-specific hold or post-donation standby/cooldown | Do not apply a new sanction; never release/shorten those restrictions |
| Effectively unavailable or stored standby without a valid recovery anchor | Do not apply a new sanction |
| Expired stored post-donation standby with all windows elapsed | Use shared effective eligibility, rather than rejecting solely because the raw availability string is standby |
| Blood type missing/incompatible or own request | Do not apply a new sanction |
| Match answered/withdrawn after answering, terminal/overdue request, older generation or consumed deadline | Preserve existing resolution/exclusion; do not recreate a response obligation from withdrawal alone |
| Existing active non-response standby | No second transition, no duplicate alert, no automatic clearing; acknowledgment remains available subject to current active-member guard |
| All current gates pass and stored opportunity deadline has elapsed | Apply one standby transition and auditable in-app alert; leave donor availability/donation anchor untouched |

**Recommended stale-obligation policy:** invalidate the particular unanswered opportunity when it becomes ineligible, rather than leave an elapsed pending obligation that can punish the member immediately after later recovery. Align unanswered-match reconciliation with the timer resolution, under the established lock order, without touching responded/completed matches or reports. Do not invalidate unrelated opportunities merely because one request changes.

This amendment requires a re-issuance decision: the existing unique key cannot create a second timer for an invalidated same-generation row, and donor refresh preserves CLOSED matches. Recommended rule: only a genuinely newly issued opportunity/generation may start another response obligation; an ordinary eligibility recovery or refresh must not silently reactivate the old timer. This preserves current no-silent-reset behavior but may leave that request without a new timed alert until another genuine issuance. If same-request recovery must generate a fresh timed invitation automatically, design an explicit issuance/re-invitation identity and notification policy separately; do not reset notified_at/due_at on the existing history row or globally bump every donor's generation as a shortcut.

Temporary ineligibility between issuance and due time is not fully reconstructible from current state alone. Enforce invalidation on known relevant transitions as well as at due-time recheck; do not promise a historical pause/resume clock without recording such periods. Exclusions never grant clinical clearance or alter permitted pending-member request creation.

## 4. Recommended transition policy

**Recommendation: prospective issuance, with stored existing deadlines and active standby preserved.** Set one documented cutover instant T in UTC and Asia/Manila during a later coordinated rollout. No actual T is selected by this document.

| Existing state at T | Recommended transition |
| --- | --- |
| New opportunity first issued after cutover by the new writer | Persist due_at = notified_at +48h |
| Pending opportunity issued before cutover, including already due 42h rows | Retain its stored 42h due_at. It may still transition at the next tick under the retained deadline and approved current eligibility checks |
| Retry of an existing same-generation opportunity after cutover | Retain original row/deadline; not a new +48h timer |
| Newly issued generation after cutover | Its own +48h deadline; older pending generation superseded as before |
| Already answered, invalidated, acknowledged or superseded opportunity | No update, reopening, backfill or notification replay |
| Due time at/before consumed_through | Preserve watermark and exclusion; never move a consumed deadline into the future |
| Active non-response standby | Preserve started_at and acknowledgment requirement, even if the originating notification is only 42–48h old. Changing the policy is not proof the old transition was erroneous |
| Multiple simultaneous legacy/new opportunities for one donor | Apply at most one active standby. Acknowledgment retires all due pending opportunities at acknowledgment time; genuinely future deadlines survive |
| Deadline later than request needed-by | Do not extend the request to accommodate 48h. Expiry excludes/closes the opportunity; no non-response sanction after request expiry |
| Missing or inconsistent historical dates | Flag for review; do not fabricate timestamps or silently infer a new obligation |

Why this policy: deadlines are already persisted per opportunity; prospective issuance requires no historical timer rewrite or schema change, preserves acknowledgment watermarks and past alerts, and has a bounded rollback path. However, retaining legacy 42h deadlines is a temporary exception to the supplied paper's 48h scope. Explicitly approve and disclose that exception; if every unresolved obligation must align with the paper immediately, use the reviewed pending-timer extension alternative below instead. It is a recommendation, not an assertion that legacy 42h satisfies the paper.

**Alternative requiring explicit approval:** extend only still-pending, unconsumed legacy opportunities to notified_at +48h. Do not use cutover time +48h, reset notified_at, alter consumed/resolved rows or clear active standby. Extending due_at of a consumed-but-still-pending row could otherwise move it beyond consumed_through and resurrect a retired obligation. Legacy rows already older than 48h would still be due. Active-standby donors need separate handling because acknowledgment of their 42–48h pending rows could miss newly moved deadlines and trigger later sanctions; never mix retiming and acknowledgment without a reviewed retirement strategy. Require pre-change snapshots, scope/identity checks, coordinated writers/workers, audit of old/new due_at and explicit recovery review. A future reviewed data adjustment would be needed; none is supplied or executed here.

Auto-clearing active standby or retrospectively rewriting all opportunities is **not recommended**. The current state table stores a start time but no direct triggering-opportunity FK; a blanket recalculation cannot safely reconstruct causation from the state row alone.

## 5. Affected implementation and tests — future work only

| Area | Future change/check |
| --- | --- |
| [NonResponseStandbyService](../../backend/src/Services/NonResponseStandbyService.php) | Use a separately named non-response duration/policy, default 48h for new issuance; persist due_at once; retain consumed comparisons. Implement approved eligibility/invalidation rules and audit policy/actual deadline without confusing post-donation settings |
| [ProfilePage](../../frontend/src/pages/ProfilePage.jsx) and service notification text | Remove hardcoded universal “reached 42 hours” wording. Prefer “An unanswered opportunity passed its response deadline. Acknowledge standby to resume matching when other requirements permit.” Existing stored 42h alerts remain historical records; do not bulk rewrite them as 48h events |
| [NotificationService](../../backend/src/Services/NotificationService.php), [MatchService](../../backend/src/Services/MatchService.php), [MatchResponseService](../../backend/src/Services/MatchResponseService.php) | Preserve transactional issuance, deduplication, response resolution and generation rules; assess invalidation/re-issuance hooks. No new email class or notification replay |
| [UserRepository](../../backend/src/Repositories/UserRepository.php), [DonorEligibilityService](../../backend/src/Services/DonorEligibilityService.php), [OfferChatService](../../backend/src/Services/OfferChatService.php) | Verify locked consent/hold/state hydration and reuse of live eligibility; preserve offer acceptance restrictions and authorized existing chat/report access |
| [ProfileController](../../backend/src/Controllers/ProfileController.php) | Keep explicit acknowledgment/reconciliation and independent restriction gates. Existing duplicate unlocked availability write is a related race to resolve only if separately included in the implementation scope |
| [Application safety runner](../../database/run_application_safety.php), [test runner](../../database/run_test_non_response.php), [readiness helper](../../database/non_response_checkpoint_support.php), task launcher/logs | Stored due_at must remain authoritative; align previews with new eligibility policy or explicitly label them broad candidates. Preserve expiry-first/overlap behavior; verify mixed-policy batches and logs. No task activation proposed here |
| [022 migration](../../database/migrations/022_non_response_opportunities.sql), system settings/seed | Reference only: no alteration required for prospective policy; do not edit applied 022, standby_hours, cooldown_days or historical data. A configurable policy/version schema, if desired, is a separate choice |
| [non_response_offline.php](../../tests/non_response_offline.php) | Keep four watermark boundary assertions; add independent duration/cutover policy tests if extracted as pure logic. Current four tests alone do not prove 48h behavior |
| [safety_followup.php](../../tests/safety_followup.php) and [wrapper](../../tests/safety_followup.ps1) | Current fixtures model 43h-old notification/one-hour-overdue 42h timers. Retain explicit legacy cases and add 48h issuance/boundaries/exclusions, mixed cohorts, no restart/replay and concurrent actions |
| [donor_age_offline.php](../../tests/donor_age_offline.php), [offer_chat_offline.php](../../tests/offer_chat_offline.php) | Reuse age/consent and existing-chat-during-restrictions regression expectations; extend only where the new timeout policy creates a meaningful case |
| [donation_completion_offline.php](../../tests/donation_completion_offline.php), [DB suite](../../tests/donation_completion_db.php) | Regression-only: preserve report reminder at 24h/escalation at 48h, claim holds, occurrence anchors and no-silence decisions |
| [phase9.ps1](../../tests/phase9.ps1) | Historical post-donation suite: retain its 42h/default and configurable 24/48h window tests. Its old non-response-negative wording must be qualified: no post-donation-field mutation does not prove no separate response-state standby. Review legacy fixtures before any run; do not global-replace 42 |
| Checklist/requirements/research/worker documentation | On approval/implementation, distinguish current code, prospective policy, legacy obligations and preserved donation/report clocks. Update only relevant statements; no ERD/data-dictionary edits in this task |

### Minimum acceptance cases before a later rollout

1. New generation due_at is exactly notified_at +48h; one microsecond before is not due, equality is due; later worker ticks process once. Use controlled clocks rather than sleeps.
2. Legacy42h and new 48h rows coexist; reusing notification/same generation does not reset a timer; a true newer generation supersedes old pending obligations.
3. At T+processing, a legacy 42h overdue row remains due under the recommended policy; a new 43h-old row is not due. Labels explain which obligation elapsed.
4. Answer, expiry, terminal cleanup, supersession, invalidation and acknowledgment race with timeout without stale sanctions or duplicate notifications; retain existing request/user lock discipline.
5. Test every proposed eligibility exclusion separately; restoring eligibility never revives an invalidated elapsed row. Verify deliberately approved re-issuance behavior.
6. Active standby at cutover remains until acknowledgment; no duplicate transition. Acknowledge retires due legacy/new rows and preserves future rows; watermark equality excludes, +1 microsecond can qualify later.
7. Independent requests/generations and more than 100 due rows drain in bounded batches. Multiple opportunities for one donor produce one active state/alert transition.
8. Non-response changes never update donor_availability, last_verified_donation_at, report holds or donation credit; default post-donation 42h/90d and report 24h/48h boundaries remain intact.
9. Losing donor readiness still permits existing chat/report coordination where account/verification/participant rules allow. Acknowledgment does not bypass another restriction.
10. Generic Profile/notification copy is accurate for both cohorts; no historical notification rewrite or added SMTP delivery occurs.

No tests are claimed run or passed for this proposal; no tests or production files were changed.

## 6. Rollout, recovery and decisions still needed

A later approved rollout should first produce a SELECT-only cohort inventory: legacy pending before/after 42h and48h, current-generation validity, consumed pending rows, active standby, proposed exclusions and inconsistent timestamps. Counts must distinguish opportunities from distinct donors. Do not process due rows while collecting that inventory. Retain a reproducible cutover record and backup before any authorized writes.

Coordinate opportunity writers and safety workers to avoid mixed old/new writers around T; no new schedule is inherently required. Persisted deadlines remain authoritative after rollback as well: reverting the writer must not shorten existing48h rows, undo acknowledgments, clear active standby or restore old whole-database state over subsequent user actions. Revert code only with a compatible mixed-deadline reader/copy and explicit policy for subsequent issuance. Data edits, if the alternative is chosen, require their own scoped recovery record.

| Decision | Recommendation / current status |
| --- | --- |
| Paper48h requirement versus current 42h code | The supplied Scope already specifies 48h; implementation alignment is needed, but execution remains unauthorized |
| Existing timer transition? | Prospective new issuance; retain stored legacy deadlines. Alternative extension needs explicit data scope/recovery approval |
| Active standby? | Preserve acknowledgment-based state; individual erroneous sanctions reviewed separately, no blanket reset |
| Additional eligibility exclusions? | Require complete live request eligibility at timeout and invalidate stale obligations; approval needed because current processor checks less |
| Fresh issuance after recovery? | No same-row timer reset; require genuine new issuance. Automatic same-request re-invitation requires separate identity/notification design |
| Fixed duration versus configurable? | Prefer one separately named non-response policy/constant for this narrow change; do not reuse post-donation standby_hours. Configuration UI/schema/versioning is additional scope |
| Cutover and operations? | Select T, inspect real cohorts, confirm responsible worker and acceptance evidence only after implementation authorization |
| Paper wording? | State 48h as the paper target and 42h as current implementation; describe any approved legacy 42h transition exception. The companion now maps all nine user-supplied objectives |

Only this proposal and the reconciliation supplement were written. No code, tests, migrations, data, flags, schedules, ERD or data dictionary were edited; no live SQL or workers were executed for this request.
