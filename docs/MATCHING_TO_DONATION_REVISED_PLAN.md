# Matching-to-donation revised plan

## Ratification and implementation checkpoint

The user subsequently approved all five recommendations and authorized implementation: one **confirmed** donor match is one tracking unit (offers/acceptance/chat count zero); existing valid consent survives withdrawal/closure, with cooldown/non-response standby/report holds alone never blocking report coordination; up to three optional JPG/PNG/WEBP photos of 5 MB each use protected encrypted storage, exact-participant/non-conflicted-reviewer access and approved retention; historical notes stay; new member blocking is out of scope; completed-record staff correction requests never automatically reverse credits, fulfillment or restrictions. Actual reversal policy remains separately proposed/unimplemented. The earlier open-choice section is retained as approval history, not outstanding questions. Latest timezone requirement restores a concise visible Manila hint.

Source and additive migration 031 are being prepared; this does not claim migration/live SQL/browser verification. No DB/PowerShell suite, migration apply, scheduler installation/start or real email is to be run by the agent. See [implementation handoff](OFFER_CHAT_IMPLEMENTATION_HANDOFF.md) for user-run commands and remaining verification.

Planning checkpoint: 2026-10-19. The user supplied the target flow below and requested this revised plan before implementation. No new workflow behavior, schema, application data, environment setting, scheduler or email operation is authorized by this checkpoint. Recommendations and open choices remain proposals. Earlier implemented functionality stays in place until a reviewed change replaces it.

## Evidence and current implementation

Reviewed project instructions/CONTEXT, requirements, the approved donation plan, current handoff/progress records, MatchResponseService, MatchRepository, ChatConsentService, ChatPolicy, ChatService/ChatRepository, RequestActionsController, DonationCompletionService/Policy, DonationFollowupService, DonationWorkflowRepository, DocumentStorageService/DocumentCipher and the related UI.

| Area | Existing behavior to reuse | Remaining change |
| --- | --- | --- |
| Requests/matching | Blood type, hospital, quantity, urgency and deadline; centralized eligibility, compatible substitutions and ranking; no credit from offering | Standardize donor action to **Offer to donate**; keep existing matching and notification policies |
| Offers | RESPONDED match, response chronology, owner notification, duplicate handling and guarded withdrawal | Separate requester acceptance from donor response; record acceptance/revocation and offer cycle |
| Chat | One direct conversation per pair, participant authorization, active/verified gates, message-request consent, suggestions and protected staff investigation | Accepted offer becomes an additional explicit consent basis; attach exact request/offer contexts and persistent cards |
| Completion | Donor-only submission; actual occurrence; exact-version requester choices; atomic mutual/admin completion, credits, holds, restrictions and fulfillment | Present this same service inside chat; requester can prompt donor without submitting a donation claim |
| Exceptions | Dispute/uncertainty/clarification, required admin basis/rejection reason, explicit reasoned hold release and private events | Route new coordination signals correctly without cancelling reports or lifting holds |
| Timers | 24h reminder/48h escalation from original donor submission; no silence decision; idempotent retry flags | Reuse processor, not a second chat timer; operational activation is separately verified/user-run |
| Closure | No-credit owner closure, expiry, late/excess donations, pending-report preservation | Stop new acceptances as well as offers; show terminal context while report handling remains accessible |
| Documents | Protected storage/streaming, MIME/size validation, encrypted envelope support, private previews | Report-specific photo metadata, endpoint authorization, version binding and retention; not chat attachments |
| UI freshness | Feed and owner request/history refresh, notification badge synchronization, separate date/time fields | Extend the same freshness/invalidation discipline to cards and acceptance; preserve completed fixes |

Recorded verification: migration 030 was applied by the user to test and application targets. The supplied 33 DB and seven API checks passed for their then-current suite contents. Later SQL/UI/freshness fixes have lightweight/build evidence, not blanket live verification. Safety expiry scheduling was user-verified; that is not evidence that the separate donation follow-up task is active. Do not repeat completed broad audits or treat old prepared-only document headings as current operational truth.

## Target flow and example

Requester posts -> donor offers -> requester **Accept & chat** -> coordination -> donor **I donated** -> requester acknowledgment -> mutual completion or exceptional admin review.

An offer or acceptance reserves no quantity and awards no credit. Routine, Urgent and Emergency use the identical acceptance/consent checks. Preserve existing approved urgency-dependent matching/email rules without adding an Emergency consent bypass.

Example: Ana requests two units. Ben and Carlo offer. Ana accepts each independently and coordinates privately. Ben submits his actual donation time and optional proof photo; Ana acknowledges that exact version. One completed donation counts under the existing one-match/one-unit convention, so Ana's request remains open. Carlo's accepted offer still contributes zero. His separately acknowledged donation supplies the second count and fulfills the request. A genuine third donation can still be recorded later without reopening the request or discarding credit.

## Offer acceptance and chat permissions

- Show **Offer to donate**, **Offer sent**, **Accept & chat**, and **Coordination ongoing**. Only the exact request owner accepts another member's live offer.
- Add audited acceptance metadata to the offer, without repurposing RESPONDED to mean donation or acceptance. Re-offering after withdrawal advances an offer cycle; stale acceptance cannot accept the new cycle.
- Acceptance atomically rechecks the request is OPEN and before deadline, donor compatibility/current eligibility, account restrictions, verification and applicable consent restrictions. Persist acceptance and create/reuse the pair conversation plus exact request context together. UI navigation occurs only after success. Repeated clicks return the same context without repeated alerts.
- Stop calling the separate message-request flow merely because a donor offers. A donor offer plus requester acceptance supplies mutual consent for this workflow. Direct messaging outside it keeps its existing consent process.
- Extend authorization consistently across conversation creation/open, list, read, send, unread and mark-read; changing only the send endpoint would leave privacy/access inconsistencies. Acceptance must never override a declined/cancelled messaging choice silently: requester explicitly chooses the offer's Accept & chat action, subject to any actual blocking restriction.
- Reuse existing authorized pair conversations, retaining their existing messages and consent. Existing pending message requests are not auto-accepted just because a match exists; accepting an offer can explicitly satisfy that pair's pending consent with an auditable source, without a second dialog. No mass conversion of historical offers or conversations.
- Multiple donors have separate private conversations. Multiple requests for the same pair share their existing conversation with clearly selected request contexts; never infer the card's report from the latest pair-wide match role. Request cards identify request ID, offer/cycle and report/version internally.
- Withdrawal ends the live offer/acceptance context, retains history and requires fresh acceptance on a new offer. Existing report-related withdrawal guards remain; withdrawing cannot bypass an active hold or cancel a report.
- Request closure stops new offers/acceptances but retains authorized history and report actions. Continuing ordinary messages after closure/withdrawal is an open decision below. Report access remains independent of chat eligibility, under existing report authorization; losing chat does not erase a pending case or grant unverified chat access.
- Current reviewed code enforces active/membership-verified accounts and accepted conversation consent. No separate user-to-user block table/API was found in the reviewed backend/frontend paths. Do not claim that feature is implemented or silently add a new blocking system under this redesign; clarify the intended blocking scope below.

## Persistent card and action ownership

The card is a structured view of request context plus canonical report data, not an editable/deletable chat message. It remains accessible when scrolling; mobile may use a compact expandable context panel. Matches, chat and report/history screens deep-link to the same report ID/version.

| Action | Who | Exact proposed effect |
| --- | --- | --- |
| I donated | Matched donor only | Opens required date/time and optional proof form. Successful submission creates/reuses the canonical report and existing report-specific hold. No credit until mutual acknowledgment or supported admin confirmation. After submission label becomes View donation report. |
| Ask donor to submit details | Request owner only | Audited, rate-limited/idempotent in-app prompt scoped to this donor offer. Creates no submitted donation report, hold, credit or 24/48h report clock. Reuse an existing report instead of creating another prompt/report if one exists. |
| Still arranging | Either participant | Records that actor's coordination update. No change to request deadline/status, report state, restrictions, credit, original submission time or reminder/escalation clock. It cannot hide an unresolved report; terminal contexts cannot be reactivated through it. |
| Donation didn't happen, before a report | Either participant | Records an attributed coordination statement, not a verified outcome or medical-readiness finding. Notify the other participant in-app once per actual changed state. No credit/hold, automatic withdrawal, request closure or suppression of later genuine reporting. Outcome remains unknown/unconfirmed without a submitted report. Explicit withdrawal/closure remain separate actions. |
| Donation didn't happen, with pending report | Requester | Use the existing exact-version Did not donate acknowledgment and enter admin review. Never cancel/reject the report or release its hold automatically. |
| Donation didn't happen, with pending report | Donor | Submit an audited correction/dispute signal for staff clarification, preserving the report, occurrence history and hold. It is not the requester's acknowledgment. |
| Donation didn't happen, after completion | Either participant | Replace destructive action with Request correction; open a reasoned staff review path. No automatic reversal of credit, fulfillment or restrictions. Final-record correction policy is an open choice below. |

Normal report states remain **Awaiting requester acknowledgment**, **Mutually acknowledged donation**, **Disputed — admin review**, **Uncertain — admin review**, **Clarification requested**, **Admin-confirmed donation**, and **Rejected report**. Coordination state is displayed separately. Messages, arrangements, acceptance and photos never decide these states.

## Reporting, acknowledgment and evidence

- Preserve separate date/time controls and explicit timezone interpretation: default/display Asia/Manila, store UTC, never require typing timezone codes. Reject malformed/future times and times before the recorded donor response; require clarification for missing legacy chronology. Preserve existing intervals and latest-occurrence/historical-anchor rule so late reports never shorten restrictions. Do not add a requirement that occurrence must be after acceptance: that would change the approved anchor rule.
- A previous UI request hid the timezone caption. The latest explicit timezone requirement takes precedence: restore a concise timezone hint near these controls, not the removed redundant section heading.
- Replace only the optional normal donor-description input with **Upload donation proof (optional)**. Text: "Donation acknowledgment, certificate, or donor-card entry showing this donation. Crop or cover unrelated personal information. A photo supports review; it does not confirm donation." Admin decision reasons and clarification text remain available/required where applicable; historical donor descriptions remain readable and unchanged.
- Proposed photos: JPG/PNG/WEBP, at most three per report version, 5 MB each, subject to approval below. Validate actual file content/dimensions and sanitize/re-encode images to remove metadata; do not trust extension/client MIME. Preserve original historical evidence; no retroactive conversion/backfill. No new external file service.
- Reuse existing storage/encryption services and authenticated private preview. New proof files must be encrypted; fail closed if keys/encryption are unavailable rather than using the existing legacy plaintext mode. Verify deployment readiness without exposing keys. Add report-specific metadata/authorization rather than reusing identity-document access broadly.
- Only the report's exact donor, request owner, and an authorized non-conflicted admin reviewing that report may fetch evidence. No other donor, unrelated member, general chat participant, self-reviewing admin, aggregate/export consumer, public URL or ordinary chat-attachment endpoint gets access. An admin who is a participant retains only participant permissions for that case.
- Audit accesses, use private/no-store responses, random protected storage references and bounded uploads. Evidence filenames, bytes, notes and rejection details stay out of generic alerts, email, ordinary chat messages and general audit metadata.
- Evidence changes on a pending submitted report create a new report content version and preserve prior evidence/events; old acknowledgment cannot approve it. Original submission/24h/48h timing stays fixed. Stage uploads against the exact offer/report with ownership checks; finalize only committed references and remove only newly orphaned uploads on failed submission. Do not require a photo to submit or mutually acknowledge.
- Both parties agree -> reuse the atomic existing credit/history/restriction/hold/fulfillment transaction. Disagreement/uncertainty -> admin review. Admin can request evidence/clarification; a missing photo cannot silently confirm or reject a report.

## Closure, holds, timers and notification preservation

Donor submission alone starts the existing report-specific hold. A requester prompt, acceptance or coordination statement creates no hold. Pending/disputed/uncertain/clarification holds persist, and rejection alone never releases them. Explicit admin release requires the documented finding no donation occurred, affects only that hold, is idempotent, never sets availability, and bypasses no other restriction. A genuine donation with incorrect details remains held for correction.

Keep one reminder after 24h and escalation after 48h from the original donor submission, with retry deduplication and visible unresolved age. Neither corrections nor Still arranging restart them. Silence decides nothing. No-report cases stay unknown; no invented occurrence, automatic donation, blanket medical restriction or automatic 24/48h report escalation is added for them.

Reuse Help no longer needed/closure with explicit reason and no credit. OPEN/past-deadline requests expire; closure/expiry/fulfillment preserve all pending reports/evidence. No new acceptance after terminal status, but valid historical/late reports remain possible under existing protections even if the offer was not migrated into a new acceptance state. CANCELLED/EXPIRED never become FULFILLED and no terminal request reopens. Genuine excess donations are retained.

Notifications for offer acceptance, context updates, prompts, report/review actions and evidence requests remain in-app only with dedup keys scoped to actual state changes/versions/events. Preserve existing matching notifications, Emergency email eligibility/grouping, membership emails, budgets, cutovers and recipient protections. Do not add email for the new actions or reset any outbox history. Do not reinstall or alter the already verified Safety schedule.

## Proposed schema/API/UI impact — changes only

**Schema:** next additive migration, not edits/reruns of applied 030. Add audited offer acceptance/cycle/coordination metadata or a child table; unique offer-context links to existing conversations; actor-attributed coordination/prompt events; protected report evidence/version references. Keep reports, credit uniqueness, occurrence/submission/review fields and holds. Add targeted indexes/FKs and retention guards for unresolved evidence. Check legacy report multiplicity before adding any new canonical-report uniqueness constraint; never silently merge/delete old rows. No historical acceptance, occurrence, proof or credit backfill.

**API:** retain donor offer endpoint/service with standardized wording; add requester accept/get-context endpoints with expected offer cycle/version; context-scoped coordination/prompt operations; reuse existing donor submit, requester acknowledge, admin review and report history endpoints. Add report evidence upload/private-stream endpoints. Return explicit allowed actions/state so UI does not infer permission from names or urgency. Do not turn existing generic request follow-up (which alerts staff) into a donor prompt silently; add a distinct scoped operation. Message edits/deletes have no report mutation permission.

**Concurrency/security:** preserve request -> sorted users -> match -> report lock order and add conversation/context/evidence locks in one documented order across accept, withdrawal and chat operations. Fresh server-side authorization/CSRF and participant identity at every boundary. Unique context/report-credit constraints, exact-version decisions, idempotency keys and transactional alerts must prevent double-clicks, donor/requester simultaneous initiation, stale acceptance, upload retries, acceptance-vs-expiry and acknowledgment-vs-admin conflicts. Preserve request material-change safeguards and ranking; never let a pending-report hold invalidate its own already responded offer and force routine admin review.

**UI:** standardize Offer to donate and Accept & chat across feed/Matches/responses; show offer versus acceptance separately; add request selector/persistent context card in full chat and chat box; reuse shared report controls/screens; replace normal description with guided private upload; keep admin reasons/history and old notes. Preserve current spacing, hover, multiline compose, presence, notification and list freshness fixes. Do not expose implementation-only versions/storage paths as primary product copy.

## Conflicts and unresolved decisions for review

The latest user flow resolves the earlier requester-initiated hold/timer question: requester prompts have no hold or report clock; donor submission does. Accepted-offer consent supersedes the second message-request approval only for this workflow. Optional photos replace new normal donor descriptions, not old data or required review reasons. There are no new urgency exceptions or medical interval rules.

1. **Unit accounting:** current completion counts one COMPLETED match as one unit; credits have no donated-unit amount. Recommendation: explicitly retain this convention for this release. If confirmed actual unit amounts are required, approve donor-entered/requester-acknowledged quantities and separate additive validation/accounting changes; never count acceptance or infer units from photos/request quantity.
2. **Messaging after withdrawal/closure:** recommendation: retain existing accepted pair consent and allow messaging while both accounts remain eligible; mark request coordination inactive/terminal. Pending-report actions always survive. Alternative is history-only messaging for contexts without another valid consent basis; that requires explicit revocation policy.
3. **Proof policy:** approve/revise three JPG/PNG/WEBP photos at 5 MB each, accessible to both participants and the non-conflicted reviewer. Recommendation: retain proof while reports/holds are unresolved, then use the existing report-record retention horizon for resolved proof instead of inventing a new period. Exact implementation must check the current report/account-erasure policy and document file/metadata cleanup together; no automatic historical deletion is part of rollout.
4. **Blocking scope:** reviewed code has account/access restrictions but no distinct member-to-member block feature. Does "preserve blocking" mean those existing restrictions, or is per-member blocking required? Recommendation: preserve existing restrictions now; treat adding a new block system as explicitly scoped separate work. Accepted-offer consent must never be described as overriding an actual block.

Final completed-record correction/reversal is not defined by the current report service. Recommendation: this release's Request correction records a private staff issue/clarification request without automatic reversal; design any credit/restriction reversal policy separately before implementing it. This does not weaken review of pending reports.

## Ordered implementation and acceptance checks

1. Resolve decisions above and ratify this replacement plan. Record that current completed work remains intact; do not treat planning as authorization to deploy.
2. Prepare additive acceptance/context schema and APIs; integrate every chat authorization/list/unread path, preserving legacy consent and account checks.
3. Add persistent context card/actions and reuse canonical report operations; implement requester prompts with no hold/credit/clock.
4. Add private encrypted proof uploads and versioned evidence lifecycle; remove only new normal-description controls.
5. Prepare focused regressions and user-run migration handoff; validate lightweight code/UI locally only when implementation is approved. User-run DB/PowerShell, task and real-email operations remain separate.

Acceptance checks: same consent flow at all three urgencies; offer/acceptance never reserves/credits/fulfills; eligibility/ranking/substitution regression; existing chat reuse, pending/declined consent and account restrictions; two donors/two requests never share the wrong card/report/evidence; withdrawal/re-offer and acceptance-vs-expiry races; requester prompt no hold/credit/timer; donor submission exact UTC/date rules and one hold; stale evidence/report acknowledgment fails; one credit/fulfillment update and no duplicated alert under concurrent decisions; disagreement/uncertainty/admin self-review denial; rejection/explicit release isolation; reminders once at 24h and escalation at 48h without clock reset; terminal/late/excess and no-report unknown cases; message edit/delete independence; unauthorized/guessed evidence IDs, wrong MIME/oversized uploads, key failure, access logging, uncommitted-upload cleanup and unresolved retention; responsive chat/card/report screens, keyboard use, refresh and repeated-action behavior. Existing email channel/budget tests only need focused coverage proving new actions do not enqueue mail; no real SMTP test belongs to this checkpoint.

## Checkpoint

Documentation only saved for review. No new code/schema/data/env mutation, migration application, database/PowerShell suite, schedule operation or email send was performed. Approval of the remaining choices and implementation plan is pending. Existing membership email experiment (job 27 last seen pending) is separate and not advanced by this planning task.
