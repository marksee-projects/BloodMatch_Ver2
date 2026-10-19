# Donation-completion redesign ? approved 2026-10-19

Approved update: the user ratified all five recommendations for accepted-offer chat consent, persistent request-specific donation actions and optional protected encrypted proof photos, then authorized source implementation. See [ratified revised plan](MATCHING_TO_DONATION_REVISED_PLAN.md) and [031 implementation/user-run handoff](OFFER_CHAT_IMPLEMENTATION_HANDOFF.md). The normal new-report description is superseded by optional photos; old notes/review reasons stay. Existing valid consent supports messaging despite donor cooldown, non-response standby or report holds. One confirmed match counts one tracking unit; acceptance/chat count zero. Actual reversal and per-member blocking remain outside scope. Later user-run migration/test/Safety results take precedence over historical prepared-only statements below. Migration 031 and its DB/PowerShell/browser gates remain user-run and unverified.

Status: source prepared; migration 030, database/PowerShell regressions, visual checks and operational activation are user-run and not claimed complete. See [exact handoff commands](DONATION_COMPLETION_HANDOFF.md).

## Approved rules and superseded rules

The user's 2026-10-19 instruction supersedes the earlier admin-only routine-confirmation plan in this file. Donor/requester agreement records a **mutually acknowledged donation**, not a medically verified donation. Admin review is for disagreements, uncertainty, unanswered reports, clarification and supported exceptional review. Admin can confirm with a required documented basis, reject with a required reason, or request clarification. Nobody may acknowledge their own donation or administratively review their own donation/request.

Each donor submits **I donated** with actual occurrence time and an optional private supporting reference/note. Submission, occurrence, requester acknowledgment, completion recording and administrative review times are separate. Existing private report-note/review-note storage is reused; no file-upload or new evidence-file storage infrastructure was introduced. Event snapshots are restricted to the report's participants and authorized staff; general audit metadata and alerts do not contain private notes/reasons.

The requester chooses **Donation received**, **Did not donate**, or **Not sure** for each exact report version. Agreement credits the donation, updates history/restrictions and counts toward fulfillment in one transaction. The other choices send the report to review without rejecting it. Reports with missing occurrence data require clarification; material-invalidation markers remain visible for exceptional admin review rather than being silently auto-completed.

One requester reminder is due at 24 hours from original submission. At 48 hours without acknowledgment, the report escalates to review. Silence never decides it. A delayed processor catches up missing reminder/escalation once each. Corrections do not restart the original clock or send another original reminder. All new alerts are in-app only; approved email coverage, budgets and schedules are unchanged.

## Date and restriction approval

The user explicitly approved the proposed date/anchor rules and specified an Asia/Manila date/time picker with the timezone displayed; users never type timezone codes. The picker is labeled **Asia/Manila (UTC+08:00)** regardless of device timezone and submits an explicit UTC instant. Backend validation rejects malformed dates, absent/invalid offsets, future occurrences and occurrences preceding the recorded donor response.

Configured standby/cooldown intervals are unchanged. The restriction anchor advances to the latest of the existing historical anchor and the newly recorded occurrence; out-of-order/late reports cannot shorten an established restriction. The historical column name last_verified_donation_at is retained for compatibility as this administrative restriction anchor, not a medical-verification claim. Donation occurrence is separately recorded on the report and credit. Completion time remains confirmed_at for existing report/analytics compatibility; reviewed_at is separately populated for admin actions.

New response timestamps are stored explicitly. Migration 030 recovers legacy response times only from actual match.responded audit events, never match creation/update timestamps. No legacy donation occurrence date is invented. Missing response chronology remains visible for administrative clarification rather than fabricated credit.

## Hold approval

The user explicitly approved report-specific holds throughout pending, disputed, uncertain and clarification states. Rejection alone never releases a hold. An administrator may explicitly release only a rejected report's hold after recording a basis establishing that **no donation occurred**. Repeated release is idempotent. Release never sets availability, clears another report's hold, clears non-response standby, or overrides confirmed-donation restrictions or other eligibility gates.

If a donation happened but report details are wrong, request clarification, preserve the hold, and let the donor submit a corrected version. Prior versions/events remain available; previous acknowledgment cannot approve the new version. Reopening a rejected report for clarification restores only its own hold and advances the report version, invalidating old action retries, with its prior rejection/release events retained. Recording a genuine donation replaces only that report's hold with the applicable occurrence-based restriction.

## Requests and reports are independent

The pre-existing owner closure action stops offers with a reason and awards no donation credit. Fulfillment stops offers when the requested count is reached. Pending reports survive closure, cancellation, expiry and fulfillment. Late valid completion may update donor history/restrictions, but cannot reopen matching or convert CANCELLED/EXPIRED to FULFILLED. An OPEN request whose deadline has passed expires during completion instead of being fulfilled. Genuine additional donations are recorded even after another donor fulfilled the request. Existing one-completed-match/one-unit counting is retained; request quantities are not copied into donation records.

This explicitly supersedes historical blanket closed-request confirmation blocks and the old EXPIRED-to-FULFILLED confirmation branch. Current donor readiness gates govern new offers; historical donation recording is a separate authenticated, versioned report decision and does not make medical claims. Compatibility/ranking algorithms are unchanged. Material changes still preserve pending reports and route exceptional confirmation to documented admin review.

## Implementation and verification boundary

Additive migration 030 introduces report versions/timestamps, private report events, report-specific holds and unique per-match donation credits. Existing CONFIRMED/REJECTED states are preserved; prior pending claims receive holds, with no historical donation credit or occurrence backfill. Transactions retain request ? sorted users ? match ? report locking, protecting acknowledgment, credit, anchor, hold and fulfillment updates together. Unique credit constraints and final-state checks prevent duplicate/conflicting decisions.

Requester/donor history is available at /donation-reports; the admin queue remains /admin/confirmations with the aggregate summary retained. Unresolved reports/rejected holds show age. Retention excludes unresolved holds from request/audit/restriction-data pruning. Account permissions, privacy, document handling, compatibility/ranking and completed notification/chat hover fixes are preserved.

No new unresolved product choice remains. Only user-run migration/test/browser results and follow-up activation remain. Do not apply migration 030, run database/PowerShell suites, install/start tasks or run real-email experiments as the agent. The user is away; if a genuinely unresolved choice emerges, save a checkpoint and stop.
