# Demand map refresh implementation — 2026-10-21

User authorized implementation of the existing refresh plan on 2026-10-21.

## Applied behavior

- Home mini-map and admin full map use the same account/filter-scoped React Query options in `frontend/src/services/chapterDemand.js`.
- Fetch on mount and server-filter changes; refresh every 30 seconds while visible and online, on visibility/focus and reconnect. Duplicate observers and manual refresh share in-flight requests. Unobserved requests receive an abort signal.
- Home chapter/urgency selections remain local and survive refreshes. New server-filter keys do not display the previous filter's response. Missing selected chapters remain selected with an unavailable option; returned zero-demand chapters remain valid.
- Background refresh preserves the successful chart and its last-success timestamp. Errors are inline without repeated notification toasts. Initial failure offers Retry instead of displaying an empty aggregate. Both modes have manual Refresh. Offline controls are disabled.
- 401/403 stops polling/focus/reconnect retries and hides protected charts. A fresh auth result can reset the denied query; remounting with the same denied session does not bypass this. Account/status/role are part of query identity; logout/account change also uses existing AuthProvider cache clearing. No verification-status access restriction was introduced.

## Executed verification

- `node tests/demand_map_refresh_offline.mjs`: PASS. Actual QueryObserver subscriptions with fake time/promises cover initial/30-second fetching, duplicate observers, manual coalescing, retained chart on pending/error, hidden/offline pause, visibility/reconnect recovery, 403 stop/revalidation reset, server-filter key/encoding, previous request abort and late response isolation, account switching, and unsubscribe timer cleanup. API responses are synthetic.
- `node tests/home_ui.mjs`: PASS. Existing rendered/callback checks retain chapter/urgency/reset behavior; added selected-chapter removal and zero-demand return checks. Demand auth is an explicit test fixture, aggregates are seeded in query cache. Server rendering does not exercise effects.
- `node tests/home_filter_controls_offline.mjs`: PASS. Existing Home filter callback/parameter checks remain intact.
- Frontend production build using existing Vite config: PASS; existing bundle-size advisory remains.

## Remaining acceptance

Query subscriptions and rendered callbacks are not mounted React/StrictMode or browser evidence. No DOM test harness is installed; no new dependency was added. Mounted React StrictMode, real browser visibility/focus integration, visual/mobile review and a live two-session request create/change/expiry comparison remain outstanding. This report does not claim those checks passed or full FR-14 runtime sign-off. No API fixture mutations, schema changes, notification deliveries or worker/schedule changes were performed.

The shared widget automatically applies this implementation in both existing locations. Backend aggregate semantics and privacy policy are unchanged.
