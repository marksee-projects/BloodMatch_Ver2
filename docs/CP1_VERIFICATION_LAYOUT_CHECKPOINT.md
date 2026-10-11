# CP1 — Verification decision layout

**Later authorization update:** user approved proceeding through the remaining checkpoints while away. CP2–CP4 are now implemented in prepared code; follow [the current combined handoff](REMAINING_UI_CHECKPOINTS.md) for statuses/tests. References below to CP2 not started/new-tab inspection are historical CP1 snapshot behavior and superseded by the contained viewer. CP1 live visual evidence is still pending.

2026-10-11. Implemented in prepared code; offline checks passed. Live UI remains unverified because browser tooling has no connected browser. CP2 has not started.

## Changes

- Removed the redundant Decision dropdown and decorative shield beside the member identity.
- Centered and bolded Submitted National ID and Verification decision headings; removed their explanatory paragraphs. File count/metadata, identity, supporting documents and meaningful validation messages remain.
- Approve account submits approval explicitly. Reject verification only opens the required reason field, with keyboard focus directed to it; Submit rejection is the explicit final action. Cancel rejection clears the reason and optional donor-card acceptance without saving.
- Donor-card acceptance remains opt-in administrative provenance, with its medical-confirmation notice retained. Non-pending/loading/busy decisions are blocked; approval without an ID and rejection with whitespace-only reasons do not post. A synchronous guard prevents duplicate submission. Errors preserve the form for retry.
- Existing backend endpoints, payloads, role/privacy/self-review protections, audit/notification/email policies and schema are unchanged.

## Checks actually run

- `node tests/management_ui_offline.mjs` passed. Uses synthetic component fixtures and mock transport only: separate actions, rejection opening/cancellation without posting, whitespace refusal, trimmed reason payload, donor-card approval payload, duplicate pending submission, busy controls, error/retry, non-pending decisions, missing-ID approval blocking and existing management/aggregate privacy rendering. No network or database calls. Router server-render warnings are expected; DOM focus, actual navigation, native validation and visual layout are not proven by this harness.
- Frontend `npm.cmd run build` passed with the existing bundle-size warning.
- `git diff --check` passed. No DB/PowerShell suites, migration, real email or schedule run; no new dependency.

## Manual UI check only

No new database/PowerShell regression or migration command is required for CP1. Do not repeat CP0's completed 48 API checks or cleanup.

With the frontend/backend running and an authorized Admin/Officer session, open Account Verifications and an existing pending-member detail. Keep automation/email disabled. Do not approve/reject a real account merely to test presentation.

1. Confirm both requested headings are centered/bold, their explanatory paragraphs and detail shield are absent, and no Decision dropdown remains. Check desktop and narrow width; file metadata and supporting documents stay readable.
2. Click Reject verification: reason field appears and receives focus; nothing is submitted. Click Cancel rejection: returns to approval and clears the reason/optional donor-card choice.
3. Where a donor card and blood type exist, acceptance starts unchecked. Check it, open rejection, then cancel: acceptance is unchecked again. Document inspection still opens a new tab until CP2.
4. Tab through controls; confirm visible focus and readable buttons. Back to queue returns to the list.

Report layout/focus/cancellation results. Real decision authorization/persistence was covered by CP0's user-run API suite; actual new browser submission behavior remains unverified here. After the visual check, CP2 is the next checkpoint.

## Source verification

| File | What changed | Distinctive string to search for | Found? |
| --- | --- | --- | --- |
| frontend/src/pages/admin/views/VerificationsView.jsx | Separate actions and reason validation, preserved evidence option | Submit rejection | Yes |
| frontend/src/pages/admin/views/VerificationsView.module.css | Centered bold section headings | centeredSectionHeading | Yes |
| tests/management_ui_offline.mjs | Synthetic render and real handler checks | beforeMissingId | Yes |
| docs/UI_CHECKPOINT_PLAN.md / docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | CP1 implementation/evidence status | CP1 | Yes |
