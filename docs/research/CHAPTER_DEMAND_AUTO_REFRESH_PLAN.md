# Chapter demand automatic-refresh plan

Prepared 2026-10-21. **Implementation authorized by the user and applied 2026-10-21.** See [implementation evidence and remaining acceptance](CHAPTER_DEMAND_AUTO_REFRESH_RESULTS.md). The starting-point table below records pre-change behavior.

The remaining FR-14 gap is automatic freshness of the existing privacy-safe chapter demand visualization. Chapter and urgency filters have offline verification; live aggregate/API acceptance remains unverified. This plan does not mark either gap complete.

## Verified starting point

| Evidence | Current behavior |
|---|---|
| [DemandMapWidget.jsx](../../frontend/src/components/DemandMapWidget.jsx) | Fetches on mount and full-view blood-type/urgency/window changes. Full view has manual Refresh. Home mini-view filters fetched aggregates by chapter and urgency locally. No automatic polling; loading/error currently replaces the chart. |
| [HomeFeedPage.jsx](../../frontend/src/pages/HomeFeedPage.jsx), [DemandMapView.jsx](../../frontend/src/pages/admin/views/DemandMapView.jsx) | Home uses mini-mode; Admin Officer demand page uses the same widget in full-mode. |
| [DemandMapController.php](../../backend/src/Controllers/Analytics/DemandMapController.php), [AnalyticsRepository.php](../../backend/src/Repositories/AnalyticsRepository.php) | Existing authenticated GET returns chapter aggregates. Counts include only OPEN requests with `needed_datetime >=` current UTC time. Chapter attribution uses `request_chapter_id`. No scheduler transition is necessary to exclude an overdue OPEN request from the next aggregate query. |
| [liveQueries.js](../../frontend/src/services/liveQueries.js), [main.jsx](../../frontend/src/main.jsx) | React Query is already installed. Shared operational options use 10-second visible-tab polling; global defaults instead use five-minute freshness and disable focus refresh. Demand therefore needs explicit local options. |
| [apiClient.js](../../frontend/src/services/apiClient.js) | GET supports request options, including an abort signal, and uses `cache: 'no-store'`. No new API client or transport dependency is needed. |

This completes the freshness portion of the approved chapter/urgency demand replacement. Donor search and ranking remain separate and unchanged. Reference: [requirements checklist](../features/BLOODMATCH_CURRENT_REQUIREMENTS_STATUS.md), [paper reconciliation](PAPER_CHECKLIST_RECONCILIATION.md).

## Recommended behavior

The cadence below is a proposed product policy, not an already approved requirement or measured service guarantee.

| Situation | Planned behavior |
|---|---|
| First visit | Fetch immediately in both modes; show the initial loading state. |
| Visible, online view | Poll every **30 seconds**, with at most one active request per query key. This proposes a lower request rate than the existing 10-second operational policy because demand is aggregate monitoring. |
| Hidden tab, offline browser, or unmounted view | Suspend interval fetching; resume with a refresh when visible/online again. Remove observers/listeners on unmount. |
| Window focus or reconnect | Refresh immediately, deduplicating concurrent triggers for the same query. |
| Full-view filters | Fetch immediately under a distinct query key. Never render a previous filter's response as the selected filter's result. |
| Home chapter/urgency selection | Continue local filtering; preserve selections through refresh and recompute counts/bars from the new aggregates. Reset remains an explicit user action. |
| Manual refresh | Preserve full-view Refresh; add a compact Refresh action to Home for recovery. Disable/coalesce it during an active fetch. |
| Successful background refresh | Replace counts/units atomically without replacing the chart with skeletons or moving focus. Show “Last updated” based on the successful fetch time; this is not the time the requests were posted or a server snapshot timestamp. |
| Background failure | Keep the last successful aggregates, show “Could not refresh. Showing data from [time].” and retain manual retry. Do not emit repeated toast notifications on each polling failure. |
| Initial failure | Show an inline error and Retry; do not present a failed fetch as zero demand. |
| Authentication/access failure | Stop demand polling for 401/403 until session/access revalidation permits it. Hide protected cached data when access is lost; backend checks remain authoritative. |
| Selected chapter no longer returned | Retain its selected identity and explain that the chapter is unavailable, rather than silently switching to All. A returned chapter with zero demand remains a valid zero state. |

Describe the result as **periodically refreshed / near-real-time**, not instantaneous live updates. With a healthy service and foreground tab, a change should appear on the next scheduled successful fetch, nominally within 30 seconds plus request latency. Hidden tabs, outages, browser throttling and slow responses can extend this; the timestamp/error state must make that visible.

## Implementation sequence

1. **Move the widget's remote data into React Query.** Keep filter state local. Use a key such as `['chapter-demand', user.id, mode, bloodType, urgency, days]`, containing normalized server-side filters only. Include the authenticated identity so cached data cannot carry across accounts. Enable only after auth has resolved and existing role/active-account rules allow access; do not invent a membership-verification gate. Inspect the actual authorization middleware during implementation to preserve its precise policy.
2. **Apply demand-specific query options.** Set the proposed 30-second interval, background polling off, focus/mount/reconnect refresh explicitly, and no immediate retry loop. Use the existing API client and pass the query abort signal. Share identical requests through the query cache; discard/abort superseded requests so slow old responses cannot overwrite a newer filter result. Keep cached data only for the same filter identity. Do not change global query defaults or unrelated operational polling.
3. **Handle refresh states in the widget.** Separate initial loading from background fetching; retain successful same-key data on transient failure. Add last-success time, quiet stale/error feedback, and manual retry in both modes. Stop recurring attempts on authorization errors and resume only after access revalidation. Preserve keyboard focus, labels, filter selections and zero states; avoid announcing every successful poll to screen readers. Clear/remove demand data on logout or loss of access using the existing session lifecycle.
4. **Keep this first change bounded.** Polling covers changes from other users and clock-based expiry, so it does not depend on notifications or local mutation invalidation. Immediate invalidation after request/report mutations can be considered later; it is not required for this first release. Do not alter notification production, request transitions, donation accounting, matching, standby, or recurring workers.
5. **Verify before changing completion labels.** Add lifecycle tests, run existing filter regressions and the frontend build, then verify authorized aggregate behavior against the disposable test database. Update FR-14/F61 and the paper reconciliation with exact evidence and limitations. Offline polling tests alone cannot establish live API correctness or deployment behavior.

## Affected files and boundaries

| File | Planned work |
|---|---|
| `frontend/src/components/DemandMapWidget.jsx` | Query lifecycle, explicit polling policy, preserved filters, loading/error/refresh feedback and timestamp. |
| `frontend/src/components/DemandMapWidget.module.css` | Only styles needed for compact refresh and status feedback, using existing tokens. |
| `tests/home_ui.mjs` | Adapt the demand fixture to query-backed data without losing chapter/urgency/reset/empty-state checks. Its current server-rendered callback checks do not exercise effects or timers. |
| Proposed `tests/demand_refresh_ui.mjs` | Mounted lifecycle checks with fake time, controlled API promises and isolated query clients. Use a suitable existing test harness if available; otherwise identify the smallest DOM/test-harness addition before implementation. Do not claim server rendering proves polling. |
| Focused aggregate API test, location to confirm | Disposable-database assertions for filters, expiry, authorization and aggregate privacy. Reuse existing test isolation utilities where suitable. |
| Checklist and reconciliation documents | Evidence-based status update after tests; retain remaining runtime limitations. |

No backend behavior change, migration, database setting, scheduler, WebSocket service, new email, ERD change or data-dictionary edit is planned. The existing aggregate repository/controller are verification targets. A full-view chapter selector is separate optional parity work and is not included.

## Acceptance checks

| Area | Required evidence |
|---|---|
| Timing | Initial fetch; no interval fetch before 30 seconds; next fetch at the boundary; both mini/full modes; no overlapping request when the server is slow. |
| Lifecycle | Hidden/offline pause, visible/focus/reconnect recovery, unmount cleanup, development Strict Mode, duplicate observer deduplication and account-switch isolation. Do not promise cross-tab deduplication. |
| Filters and races | Combined chapter + each urgency, reset, full blood/urgency/window parameters, preserved selections after refreshed counts, zero-demand chapter, removed chapter, out-of-order response and cancelled request. |
| Failure recovery | Initial failure, background failure with retained data/time, manual retry, 401/403 stop/revalidation, no repeated background toasts, successful recovery clears stale feedback. |
| Live aggregates | On `bloodmatch_test` only: new/edited/closed/cancelled/fulfilled/expired/renewed requests affect the next fetch correctly; elapsed deadlines disappear without running the expiry worker; exact deadline inclusion remains unchanged. Full-view date/blood/urgency filters retain existing semantics. |
| Access and privacy | Exercise existing authorized roles/account states and denied requests; aggregates contain no donor/requester identity, contact details, documents or request-specific location. Preserve approved chapter reference metadata. |
| Regression and load | Existing Home filter suite and frontend build pass. Verify on a visible deployed view and observe request rate: approximately two interval GETs/minute per active distinct query, plus mount/focus/filter/manual triggers. Check response latency at representative test volume before claiming a capacity target. |

Automated frontend checks must use fake API responses and controlled time. Any database fixture writes require a verified disposable `bloodmatch_test` connection, isolated storage and fake notification transport. Do not use application data, activate workers, send real email or restart MySQL. The last recorded DB greeting failure remains a testing limitation until a fresh read-only check proves recovery; this plan has not retested it.

## Decisions and completion criteria

Recommended defaults are 30-second visible-view polling, focus/reconnect recovery, manual refresh in both modes, and timestamped retained data on transient failures. The user has requested a plan; those choices remain proposals for implementation approval. No additional scope question blocks preparing this plan.

Mark automatic refresh **implemented and verified offline** only after lifecycle tests pass. Mark API/runtime behavior verified only after the disposable API tests and browser/deployed-view acceptance pass, recording the environment. Until then, retain **actual missing automatic freshness** in the checklist, with this document linked as the proposed remedy. This feature does not establish recurring notification/report/retention processing readiness.
