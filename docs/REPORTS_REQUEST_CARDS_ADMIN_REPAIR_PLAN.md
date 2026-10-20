# Donation reports, request cards, and admin repair plan

Date: October 20, 2026 (Asia/Manila)

Status: Approved by the user and implemented in source on October 20, 2026. Production build, targeted offline checks, PHP syntax checks, and read-only database checks passed. Authenticated browser/mobile visual checks and fresh cross-account upload/completion exercises remain unverified. See [implementation and verification record](REPORTS_ADMIN_REDESIGN_IMPLEMENTATION.md).

Latest user revision, October 20: center page and section headings; remove decorative explanatory paragraphs, automatic-update labels, dashboard introduction/queue shortcuts, verification chapter filter/count, and database diagnostics. Restore the feed's original single expandable facts box with municipality alongside hospital and center Offer to donate. Report timestamps are inline; retain one History toggle with no per-event disclosures. Proof is inline when available; correction and History actions share a compact row. These instructions supersede conflicting initial design choices below.

## Scope and decisions

This plan addresses the supplied screenshots: `/donation-reports`, compatible-request feed cards, `/requests/:id/matches`, `/admin/dashboard`, and `/admin/verifications`. Screenshot text is evidence of displayed behavior, not an instruction source.

Use the current amendments at the top of `docs/ai_context/CONTEXT.md`, the requirements checklist in `.agents/requirements/`, and the visual rules in `.agents/plan/DESIGN.md`. The current completion rules permit version-bound donor/requester acknowledgment; routine admin approval is no longer required for every completed donation. Admin review remains for exceptions, clarification, holds, and correction requests.

Recommended detail interaction: keep and improve the existing inline card accordion. A visible **View details / Hide details** label with the chevron is easier to discover and supports comparing several posts. Keep essential facts visible in the compact card; expand secondary details inside it. Use existing dialogs for decisions, proof inspection, and donation reporting. Do not introduce a second full-post popup that duplicates the accordion and request route.

Interpret “move that to the left” as left-aligning the Donation reports title and subtitle with the report cards. Add Back above the heading and remove the normal Refresh control. Remove **Request message** from compatible-request feed cards; retain authorized chat coordination within the accepted-offer workflow. The screenshot does not clearly request removing messaging from every profile or existing conversation.

## Findings and evidence

### Confirmed in source

| Priority | Finding | Evidence and consequence |
| --- | --- | --- |
| P1 | Dashboard API and UI use different field names | `AnalyticsRepository::getAdminDashboard()` returns `active_open_requests`, `open_units_needed`, `fulfillment_rate_percent`, `cancellation_rate_percent`, and `expiration_rate_percent`. `admin/views/DashboardView.jsx` reads `open_requests`, `total_units_needed`, `fulfillment_rate`, `cancellation_rate`, and `expiry_rate`. Missing values become misleading zeros. |
| P1 | Verified-donor metric has no API field | Dashboard reads `donor_pool.verified_donors`; `evaluateDonorPoolStatus()` does not return it. The donor query already restricts the pool to active, membership-verified, enrolled members. The displayed zero is unsupported. Define what verification means before replacing this metric; membership verification and blood-type evidence are different. |
| P2 | Admin lists do not stay current | Dashboard fetches once on mount. Verification queue fetches on mount/filter/page changes and after local decisions, with no focus refresh, polling, or notification-driven refresh. Report inbox loads on mount/local changes/manual refresh. Another account's activity can leave these pages stale. |
| P2 | Verification dashboard count and actionable queue have different predicates | Dashboard counts active pending members without requiring a National ID. `UserRepository::pendingVerificationMembers()` also requires an existing `national_id` document. Reuse one queue predicate or explicitly distinguish pending accounts from review-ready submissions. |
| P2 | Admin donation queue is not completed-donation history | `DonationWorkflowRepository::listFor(..., true)` selects pending reports, unresolved holds, and unresolved correction requests. A successfully completed mutual report normally leaves this queue. Explain this and provide a separate authorized history view/filter rather than changing completion rules. |
| P2 | Report page hierarchy is unnecessarily large | `DonationReportInbox` centers its heading, positions Refresh separately, and exposes many history/version fields in each card. Primary progress and required action compete with secondary metadata. |
| P2 | Feed includes the unwanted action | `FeedCard.jsx` renders Request message beside Offer to donate. The existing detail toggle is an icon-only accordion control. |
| P2 | Some donor states disappear from the dashboard breakdown | API includes `donor_pool.unavailable`; dashboard only renders available, standby, and cooldown. Those displayed categories may not sum to total enrolled. |

The supplied dashboard screenshot shows 2 fulfilled, 16 expired, and 2 cancelled requests. With the repository's resolved denominator, those values imply 10%, 80%, and 10%, respectively, rather than three zeros. This is an illustration from the screenshot, not a live database measurement. Zero active requests alone does not establish a bug: fulfilled requests are no longer open.

### Requires live verification

The exact reason the teammate's account is absent remains unconfirmed. A historical notification is not proof that an account is still pending now. The current upload path stores the document and notification inside a transaction for pending members and supports rejected-member resubmission. The queue is database-backed, not a hardcoded two-bot array.

For each reported missing account, inspect only the necessary authorized data:

1. Confirm frontend and backend are using the intended database and port. `Database::pdo()` can fall back between 3306 and 3307; this can mask an unexpected target. Report the resolved target to developers without exposing credentials.
2. Match notification `related_id` to the account; inspect role, account status, verification status, National ID document type/count, and latest verification decision timestamp.
3. Compare the unfiltered queue API with the rendered list, active chapter filter, pagination, and notification deep link `/admin/verifications?user=<id>`.
4. Determine whether the notification is historical, the page is stale, the account was approved/rejected/deactivated, or a document/status inconsistency exists.
5. Verify that a new eligible upload appears in the queue and opens the same member as its notification. Preserve valid historical notifications; show the current review state when opening an already reviewed member.

An additional source edge case merits a targeted reproduction: uploading a National ID for an `unverified` member does not transition that status to `pending` in `DocumentController`. Registration normally creates pending members, so this is not yet the explanation for the reported notification mismatch. If reachable, define and implement the appropriate submission transition without resetting already verified accounts.

No database records, dummy accounts, IDs, or notifications should be deleted to hide these discrepancies.

## Implementation sequence

### 1. Repair admin data correctness first

- Align dashboard reads with the existing API contract; use the same values for metric text and bars. Keep valid zero values distinct from missing/malformed data. Show a recoverable error when required metrics cannot be read.
- Clarify the resolved-request denominator with plain copy, such as “2 of 20 resolved requests fulfilled.” A zero denominator should say “No resolved requests yet.”
- Replace the unsupported verified-donor metric with an accurately defined existing metric or add an explicitly defined API field. Do not imply membership approval medically verifies blood type.
- Show unavailable donors so the mutually exclusive pool categories reconcile to total enrolled.
- Compare the admin analytics contract too; that view already reads percentage fields with `_percent` names and should remain consistent.
- Add focused API-to-UI contract coverage, including nonzero rates and missing required fields, so silently defaulting to zero cannot hide future contract changes.

Primary files: `frontend/src/pages/admin/views/DashboardView.jsx`, `backend/src/Repositories/AnalyticsRepository.php`, and relevant tests.

### 2. Reconcile verification and refresh behavior

- Complete the live account-to-notification trace above before any data repair.
- Centralize review-ready queue eligibility between list and count, preserving active/member/pending/document requirements and global admin authorization.
- Reuse the existing React Query setup for admin dashboard, verification lists, and report lists where practical. Include account identity, filters, and page in query keys.
- Invalidate affected lists/counts after local actions and relevant notifications; refresh on window focus and use bounded visible-tab polling as a fallback for activity from other accounts. Preserve filters, detail state, scroll, and unsaved form input.
- Keep previous data visible during background refresh, show a quiet update indicator, and provide Retry on errors. Avoid full-page loading flicker or repeated error toasts.
- Prevent old requests from overwriting newer filter/page results. Clear or scope cached data across logout/account changes.
- For a decision made by another reviewer, show current status and disable stale decision controls; retain backend conflict/version checks.

Primary files: `VerificationsView.jsx`, `DashboardView.jsx`, `DonationReportInbox.jsx`, `UserRepository.php`, `AnalyticsRepository.php`, and the existing notification/query integration points.

### 3. Redesign donation reports

Proposed hierarchy:

```text
← Back
Donation reports
Track your donation progress and history.

[Donor → Requester]                    [Status]
Facility · Donation date
Request status · Report submitted

[What happens next / action needed]
[Primary action, only when applicable]

View supporting details ▾     View report history ▾
```

- Back returns to the known in-app origin when available, with a safe fallback to the member home/request context. A direct or notification link must not send users to an unrelated external history entry.
- Left-align title/subtitle, reduce excess vertical whitespace, and align them with card edges.
- Remove the persistent Refresh button after automatic refresh is in place; retain Retry for failed loading.
- Put status, donor/requester, facility, occurrence date, and next action first. Move version numbers, review/completion timestamps, historical notes, and detailed event snapshots behind disclosure controls.
- Preserve legacy notes and private history. Removing Request message from the feed must not remove evidence or historical supporting notes.
- Distinguish legacy missing dates from genuinely pending actions. Do not fabricate occurrence dates or reopen completed reports.
- Keep member report history and the embedded admin review workspace context-specific; avoid adding a second page heading/back control inside AdminLayout.
- Reuse existing Button, Badge, Card, date/time fields, proof panel, and tokens. Keep Asia/Manila date labels with UTC storage.

### 3a. Donation-details popup, proof, and history (added October 20)

The additional screenshots explicitly extend the redesign to the existing **Donation details** popup opened from chat and the report's proof/correction/history area. Keep this donation-management popup; the recommendation against a new full-post popup concerns request browsing, not this existing workflow.

- Replace the plain **Donation actions and details** disclosure in `DonationContextCard.jsx` with a shared, clearly styled button labelled **View donation details**, changing to **Hide donation details** when expanded. Use a real `button` with `type="button"`, `aria-expanded`, and `aria-controls`; support keyboard activation and visible focus. Expand the existing content inside the popup without opening another nested popup.
- Organize the popup as: title, selected request summary, donation status, details/action button, expanded details and applicable actions, then Close. Use consistent spacing and clear status copy; keep actions visible and allow internal scrolling on small screens. Preserve modal focus management, Escape behavior, focus return, and usable layering above chat.
- Remove visible database identifiers from this donation experience: **Request #85** in the request selector and selected-context label, **Donation report #2** in report cards, and equivalent request/report/donation ID labels in the related donation details/history presentation. Keep internal IDs in API calls, routes, deep-link query parameters, React keys, and accessibility associations so linking and data integrity continue to work. This is a presentation change, not identifier deletion.
- Identify requests with blood type, facility, needed date, and requester name where already authorized and available. When several requests look alike, add meaningful date/status context rather than exposing a numeric database key. Retain the existing Previous offer distinction.
- Redesign **Proof and correction requests** into clearly spaced supporting-photo, correction-request, and staff-response sections. Keep the correction form collapsed behind a secondary **Request correction** button until the user chooses it; reveal the labelled reason field and a distinct **Submit correction request** action. Preserve validation, busy/error feedback, and the notice that a correction does not automatically reverse donation credit, fulfillment, or restrictions.
- Present report history as a readable timeline with human labels such as **Report submitted**, **Requester acknowledged**, and **Donation mutually acknowledged**. Replace raw event identifiers/underscores with explicit display mappings. Show relevant dates and authorized actor names; keep technical content-version information secondary and preserve the version binding internally.
- Replace the browser-default **Recorded version details** summary with a consistent secondary **View event details / Hide event details** button and controlled disclosure. Retain authorized evidence and event snapshots. Where a historical actor's name is unavailable, use a privacy-safe label such as **Former member**, without exposing an internal account reference.
- Use neutral card borders, token-based spacing, and a restrained notification-target highlight rather than relying on a large persistent outline as the main visual structure. Keep the targeted report discoverable when opened from a notification.

Additional primary files: `DonationContextCard.jsx`, `DonationContextCard.module.css`, `DonationProofPanel.jsx`, `DonationReportInbox.jsx`, `DonationReportInbox.module.css`, and the existing modal wrapper only where required for layout/focus behavior.

Primary files for the report page: `DonationReportsPage.jsx`, its CSS module, `DonationReportInbox.jsx`, and its CSS module.

### 4. Clean request cards and request detail actions

- Remove Request message from `FeedCard` and remove its unused chat bindings/imports if no longer needed.
- Show requester, urgency, blood type, facility, units, and deadline in a compact summary; put additional authorized post details in the existing accordion.
- Add visible View details / Hide details text while preserving `aria-expanded`, `aria-controls`, and keyboard operation. Do not make nested profile/action links part of one large clickable card.
- Keep Offer to donate as the primary eligible-user action. Preserve response, unavailable, and enrollment explanations and their backend eligibility rules.
- On `/requests/:id/matches`, group identity/profile links separately from donation actions; reduce duplicate navigation and clarify “View offer and chat status.” Preserve accepted-offer consent and existing conversation access.
- In the screenshot, the chat window covers request actions. Verify this at desktop and narrow widths, then adjust chat docking/page clearance or use the existing dedicated chat route where appropriate so donation and cancellation actions remain reachable.
- Do not add a full-post modal unless later testing demonstrates a need that the accordion and existing route cannot cover.

Primary files: `FeedCard.jsx`, `FeedCard.module.css`, `MatchesPage.jsx`, its CSS module, and existing chat layout components only if overlap is reproduced.

### 5. Make admin work queues and history understandable

- Explain the donation queue as reports needing attention, including pending acknowledgment, escalations, disputed/clarification reports, unresolved holds, and correction requests as supported by current workflow fields.
- Add authorized completed-history access with meaningful filters and pagination; verify available endpoints first. An extension must enforce admin scope and no unrestricted chat/ID exposure server-side.
- Link dashboard pending counts to their queues; distinguish completed requests, completed donor donations, and tracking units.
- Preserve one credited unit per confirmed donor match, version-bound acknowledgment, transactional completion, pending-report holds, cooldown anchors, audit history, and no self-review.
- Validate that confirming one donation does not automatically fulfill a request requiring more units. Full fulfillment follows credited quantity and existing closure rules.

## Acceptance checks

| Area | Required result |
| --- | --- |
| Dashboard contract | Nonzero fixture values populate counts, rates, and bars; screenshot example renders 10/80/10; categories reconcile; unavailable data is not reported as a real zero. |
| Cross-account verification | Member uploads ID; admin receives a notification; the correct eligible account appears without a full reload; opening the alert targets that account. |
| Reviewed notification | An old alert for an approved/rejected member shows current status rather than incorrectly re-adding the member to pending review. |
| Filters and races | Chapter/search/page changes and out-of-order responses do not hide or replace the current result set. |
| Verification rules | Rejection/resubmission works; inactive/nonmember accounts are not review-ready; protected previews and no self-verification remain enforced. |
| Mutual completion | Donor submits; requester acknowledges the same version; completion records once; request progress and dashboard update; completed report leaves the attention queue and remains in authorized history. |
| Exceptions | Dispute, missing occurrence date, report revision, stale acknowledgment, late/closed request, correction request, and unresolved hold remain correctly reviewable. |
| Report UI | Safe Back, left-aligned heading, no normal Refresh, disclosure of secondary details, useful empty/loading/error states. |
| Donation popup | Donation details uses a visible shared button; expanding it stays in the same popup; Close, Escape, focus return, keyboard access, and scrolling work with chat open. |
| Visible identifiers | No raw request/report/donation IDs appear in donation card labels, selector options, popup summaries, or user-facing history. Existing deep links and backend identifiers still resolve the correct records; similar requests remain distinguishable through meaningful context. |
| Proof, correction, and history | Photos, corrections, and staff responses have clear sections; Request correction reveals the form; submission validation and feedback work; timeline and event-detail buttons use readable labels and preserve private evidence/version rules. |
| Feed/detail UI | No Request message on feed cards; inline details work with keyboard; primary donation action stays clear and accessible with chat open. |
| Responsive design | Check 375px mobile and supplied desktop sizes, 130% text, long facility names, dark mode, visible focus, 44px targets, and reduced motion. |

Run the frontend production build and focused meaningful regression tests during implementation. Use synthetic fixtures for contract checks. Reuse existing donation/offer/privacy tests where relevant. Run database/API mutation suites only in the designated isolated environment under the applicable execution authorization; do not seed or reset the main database as part of this UI repair.

## Review limits

The original planning review inspected implementation source and the supplied screenshots; PHP was initially unavailable. During approved implementation, `C:/xampp/php/php.exe` became accessible and read-only checks succeeded against `bloodmatch:3306`. Recent verification-upload alerts correspond to accounts with subsequent recorded approval decisions; the remaining two pending accounts match the queue and dashboard count. Two completed donation reports exist and are returned by the new admin history query. No data repair, migration, account deletion, or database mutation was performed.

The browser inventory returned no browsers, and opening the in-app browser returned “Browser is not available: iab.” Authenticated browser appearance, mobile layout, focus behavior, and fresh cross-account workflow checks remain unverified. The optional `.agents/skills/DBskill/` directory referenced by the folder guide was absent; the available `db-skills` instructions were used instead. See the implementation record for exact checks and remaining limits.

Recommended order: dashboard contract repair → verification diagnosis and shared refresh behavior → report/card redesign → admin history clarity → targeted integration and browser checks.
