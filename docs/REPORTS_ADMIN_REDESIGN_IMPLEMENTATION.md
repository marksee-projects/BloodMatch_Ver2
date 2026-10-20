# Reports and admin redesign implementation

October 20, 2026 — approved plan implemented in source.

## Dashboard and notification follow-up

The outcome and donor-availability cards now have equal heights on desktop. Search and the remaining User Management filters share one desktop row, with wrapping at smaller breakpoints. Name/email values are kept on one line each. The audit-log link is a shared button aligned with its header; donation-report view buttons are grouped inside a report panel.

The screenshot alert for James Russel Russ links to the account currently named Requestor Russ. Its October 19, 9:56:24 PM upload was approved at 9:56:45 PM. The notification previously retained its original ready-for-review text indefinitely. Notification reads now join current verification/name/account state and expose these fields only for admin verification-request notifications. Shared presentation shows Account already verified (or rejected/inactive/unavailable state), while keeping the stored event text, timestamp, read state, and authorized deep link unchanged. This addresses the misleading alert without returning an approved account to the pending queue.

Production build, management/report/notification regression checks, PHP syntax checks, and a read-only check of the notification repository passed. Browser visual verification remains unavailable. A compact chapter-demand summary is recommended for dashboard scanning; no additional map was introduced by this follow-up.

## Latest cleanup revision

The user's screenshot feedback supersedes the initial rollout choices listed below. Headings are centered; dashboard introduction, explanatory paragraphs, queue shortcuts, update labels, verification chapter filter/count, and User Management database diagnostics are removed. The latest dashboard revision stretches the outcome/availability cards to equal heights, distributing the outcome rows through the available space. User Management search and filters now occupy a separate card above the table. Search spans the top row; the four remaining filters use four columns at wide widths, two at medium widths, and one at narrow widths. The Accounts dropdown and obsolete role_group request/filter code are removed. A read-only schema check found no account, accounts, or role_group columns; existing role and account_status columns remain required. The unfiltered listing returns all 22 accounts, with member/admin permissions preserved. Management rendering tests, PHP syntax checks, the production build, and read-only listing checks passed; the account table scrolls when needed.

Feed cards again use one expandable box containing hospital, municipality, units, and deadline. Offer to donate is centered. Donation report dates and review/completion facts are visible inline. History has one toggle; submission, requester response, and acknowledgment show their timestamps directly without per-event buttons. The proof/correction disclosure and generic ReportDisclosure component are removed. Available photos are inline; Request correction and History share a compact action row. The chat link is labelled Open report.

A fresh read-only investigation at approximately 7:03 PM Philippine time found the latest National ID upload at October 19, 9:56:24 PM and its recorded approval at 9:56:45 PM by the admin account. No later stored upload or pending account without an ID was found in `bloodmatch:3306`. Both remaining pending rows are older resubmissions from October 6. The pending queue now sorts newest first. No account status or document was changed.

The User Management diagnostic came from `UserAdminController::index()` adding database/port metadata and `UsersView` rendering it after the account count. Both the API diagnostic field and its presentation were removed. Counts remain available internally for pagination.

Cleanup regression checks passed for report metadata/history/actions, absence of unwanted dashboard copy, compact feed expansion, verification/management controls, and donation/offer safeguards. Changed PHP files pass syntax checks, and a read-only repository check confirms newest-first queue ordering. Browser inventory is still empty; screenshots/mobile geometry remain unverified. The earlier file table describes the initial rollout; `ReportDisclosure.jsx` is now removed, and the additional changed files are `UserAdminController.php`, `UsersView.jsx`, `UsersView.module.css`, and `VerificationsView.module.css`.

## Result

- Donation reports have a left-aligned heading, safe in-app Back link, automatic refresh, compact primary facts, a next-step explanation for unresolved reports, and button disclosures for secondary evidence/history. The normal Refresh button is removed; Retry remains available for failed reads.
- Donation management keeps its existing accessible dialog. A real View donation details / Hide donation details button replaces the native summary. Closing/navigating from it preserves a useful report return destination.
- Request/report/donation identifiers are removed from donation-facing labels and selector options. Internal route/query identifiers, authorization, record keys, and version checks are preserved. Legacy donation notification text also hides numeric report IDs without changing destinations.
- Proof and correction content is grouped. Request correction reveals the reason field and Submit correction request action. History events use readable labels, protected evidence remains available, and malformed historical snapshots do not crash rendering.
- Feed cards show hospital, units, and deadline in their compact state, use a labelled detail toggle, and omit Request message. Request-detail actions sit on a consistent card surface; chat clearance adapts for one or multiple open tabs so controls can be reached by scrolling.
- The admin dashboard reads the actual API contract and rejects missing or malformed metrics rather than substituting false zeros. It shows the complete availability breakdown, meaningful rate denominators, and queue links. The unsupported verified-donor metric is removed; membership verification is distinguished from medical confirmation.
- Verification, dashboard, donation reports, and confirmation summaries refresh on focus and every ten seconds while visible. Relevant notification events and local actions invalidate affected queries. Query keys include account/filter/page context, and account changes already clear the shared query cache. Draft fields stay outside fetched data.
- The pending-verification badge shares the queue's active/member/pending/National-ID predicate. A National ID uploaded by an unverified member transitions that account to pending in the existing upload transaction and sends the existing review notification. Verified-account upload restrictions are preserved.
- Admin reports have Needs attention and Completed donations views. Completed history supports server-side name/facility search and pagination. Its scope requires admin authorization; the existing review/acknowledgment rules are unchanged.

## Diagnosis from read-only database checks

The resolved database was `bloodmatch:3306`. At inspection it had two active pending members with National IDs and two completed donation reports. The recent uploaded-ID notifications shown in the screenshots belonged to accounts already verified. Each recent account checked had a recorded approval decision after its upload notification. Those alerts are historical; re-adding the accounts to the pending queue would be incorrect. The review page now displays current status when an old alert is opened and disables an outdated decision.

Actual dashboard data at inspection was eight active requests, eight units needed, two fulfilled, two cancelled, and nineteen expired requests. The resolved denominator was 23: fulfillment 8.7%, cancellation 8.7%, and expiration 82.6%. These are inspection-time values, not fixed expectations in the UI. The screenshot's earlier 10/80/10 example remains a synthetic regression fixture.

All database inspection ran inside read-only transactions followed by rollback. No records were changed. No migrations, workers, schedules, real email, test seeds, or database-reset suites were run.

## Verification

| Check | Result |
| --- | --- |
| Frontend production build | Passed with existing Vite config loaded directly; 4,745 modules. Vite reports a bundle-size warning above 500 kB. |
| `tests/reports_admin_redesign_offline.mjs` | Passed: dashboard contract/zero/error handling and rendered values, readable labels, preserved notification destinations, hidden IDs, disclosure buttons, correction-form reveal, report scope and page changes. |
| `tests/donation_chat_modal_offline.mjs` | Passed: 22 mocked modal/reporting checks, including button disclosure and hidden request IDs. |
| `tests/donation_completion_ui_offline.mjs` | Passed: 13 date/status checks. |
| `tests/offer_chat_ui_offline.mjs` | Passed: 12 offer/proof checks. |
| `tests/management_ui_offline.mjs` | Passed: management, self-review restrictions, verification decision interactions, and aggregate-print privacy. Updated mocks for shared query fetching and current staff workflow. React emits expected server-rendering warnings for MemoryRouter. |
| `tests/home_ui.mjs` | Passed: card facts/disclosure, no feed messaging, response safeguards, filters and pagination. Expectations updated for approved compact facts. |
| `tests/notification_delivery_offline.mjs` and `tests/notification_feedback_offline.mjs` | Passed. |
| `tests/donation_completion_offline.php` | Passed: 32 pure policy checks, including stale versions, no self-review, quantity fulfillment, and closed-request preservation. |
| `tests/rejection_reason_offline.php` | Passed: 19 offline validation/migration-source checks; migration not executed. |
| PHP syntax checks | Passed for all five changed PHP files. |
| Read-only repository checks | Dashboard returns nonzero metrics; verification list/count match; completed-history pages 1 and 2 each return one of the two records; no-match search returns zero; member access to admin history is denied. |
| Git whitespace check | Passed. |

The ordinary npm build launcher intermittently failed while esbuild tried reading an inaccessible parent directory during config loading. Loading the same existing `vite.config.js` through Node and calling Vite with `configFile:false` bypassed that config-loader issue; the complete production build passed. No build configuration was changed.

## Remaining verification limits

Browser inventory returned no available browsers, and `createBrowserTab('iab', ...)` returned “Browser is not available: iab.” Screenshots of the new implementation, actual mobile/dark-mode layout, 130% zoom, chat overlap, modal focus/Escape/return behavior, and keyboard traversal could not be visually verified. The dialog continues to use the existing shared focus-management component.

New cross-account ID upload, mutation-driven completion, proof upload, and correction submission were exercised only through existing pure/mocked tests where listed, not through live HTTP or database mutations. The new unverified-to-pending submission transition needs an isolated end-to-end fixture check. The read-only history tests verify repository authorization and SQL behavior, not a full authenticated HTTP session.

## File verification

| File | Change / distinctive string | Found? |
| --- | --- | --- |
| `backend/src/Controllers/DocumentController.php` | unverified submission transitions to pending | Yes |
| `backend/src/Controllers/DonationCompletionController.php` | `scope` / `history` admin gate | Yes |
| `backend/src/Repositories/DonationWorkflowRepository.php` | `dr.confirmed_at DESC` paginated history | Yes |
| `backend/src/Repositories/UserRepository.php` | `reviewReadyWhere` | Yes |
| `backend/src/Repositories/AnalyticsRepository.php` | `UserRepository::reviewReadyWhere` | Yes |
| `frontend/src/App.jsx` | `admin-verification-detail` notification invalidation | Yes |
| `frontend/src/components/ConfirmedDonationSummary.jsx` | `confirmed-donation-summary` query | Yes |
| `frontend/src/components/DonationContextCard.jsx` | `View donation details` | Yes |
| `frontend/src/components/DonationContextCard.module.css` | `proofContent` layout | Yes |
| `frontend/src/components/DonationProofPanel.jsx` | `Submit correction request` | Yes |
| `frontend/src/components/DonationReportInbox.jsx` | `Completed donations` | Yes |
| `frontend/src/components/DonationReportInbox.module.css` | `disclosureBody` | Yes |
| `frontend/src/components/ReportDisclosure.jsx` | `aria-expanded` button | Yes |
| `frontend/src/components/FeedCard.jsx` | `previewFacts` and labelled toggle | Yes |
| `frontend/src/components/FeedCard.module.css` | `previewFacts` responsive styles | Yes |
| `frontend/src/pages/MatchesPage.jsx` | `data-chat-count` | Yes |
| `frontend/src/pages/MatchesPage.module.css` | chat clearance / request surface | Yes |
| `frontend/src/pages/admin/views/DashboardView.jsx` | `System overview` | Yes |
| `frontend/src/pages/admin/views/DashboardView.module.css` | `rateLabel` | Yes |
| `frontend/src/pages/admin/views/VerificationsView.jsx` | historical-notification current-status message | Yes |
| `frontend/src/services/adminDashboard.js` | `parseAdminDashboard` | Yes |
| `frontend/src/services/liveQueries.js` | `invalidateDonationViews` | Yes |
| `frontend/src/services/reportPresentation.js` | `reportSnapshot` | Yes |
| `frontend/src/services/notifications.js` | donation label ID presentation filter | Yes |
| `tests/reports_admin_redesign_offline.mjs` | synthetic redesign regression checks | Yes |
| `tests/donation_chat_modal_offline.mjs` | real details-button interaction | Yes |
| `tests/home_ui.mjs` | compact essential-facts expectation | Yes |
| `tests/management_ui_offline.mjs` | `offlineQueries` | Yes |
| `docs/REPORTS_REQUEST_CARDS_ADMIN_REPAIR_PLAN.md` | approved implementation status | Yes |
