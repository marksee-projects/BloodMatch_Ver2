# Home filter check — October 21, 2026

Result: the three visible request-feed filters behave correctly in source, rendered/callback tests, and read-only repository checks. Authenticated browser/HTTP interaction was not verified: the browser inventory returned no apps or browsers. Application source and database records were not changed.

## Follow-up: retired backend text search

User requested removal on October 21. The Home query validator, SQL text-search branch, unused LIKE escaping helper, and obsolete caller defaults are removed. Unknown legacy text-query parameters are ignored; municipality, blood type, urgency, compatibility and pagination remain active. Regression coverage now checks that the retired parameter has no effect rather than expecting facility/municipality text search. No database changes were needed. The original audit evidence below describes the pre-removal check.

## Verified behavior

| Control or rule | Result |
| --- | --- |
| Municipality | Sends the selected PSGC municipality code and excludes other municipalities and unlocated requests. |
| Blood type | Filters requested blood type within the viewer's compatible recipient types. Selecting an incompatible type returns no requests; it does not widen compatibility. `+` is correctly encoded. |
| Urgency | Routine, Urgent and Emergency filter the actual request urgency. |
| Combination | Municipality, blood type and urgency intersect; changing one preserves the other selections. |
| Reset | Clears selections and page state. Focus-return code is present; actual browser focus was not verified. |
| Pagination | Load more preserves the filter key and advances the page; changing a filter returns to page 1. Repository page/count/has_more checks passed. Current data does not exercise a live second 20-record page; one-record boundaries were checked read-only. |
| Restored URLs | Selected values restore from URL state; comma-separated legacy selections remain represented. Actual browser Back/Forward and asynchronous page restoration were not exercised. |
| Input safety | Invalid blood/urgency/municipality/page values are rejected; SQL search wildcards are escaped. |
| Request scope | Only open, unexpired requests from active member requesters are included; own requests are excluded. At inspection, eight stored OPEN requests included one already past its deadline; that record was correctly excluded without requiring a status mutation. |
| Demand chart filters | Chapter and urgency combine, including Routine and zero-result/reset behavior, in existing rendered/callback checks. These are aggregate-chart controls, separate from feed filters. |

Feed visibility is compatibility-based. Enrollment, availability, and donation holds are separately evaluated for response eligibility; a disabled Offer to donate button is not evidence that the search filters failed. Counts reflect compatible requests rather than all requests in the database.

## Findings and scope clarification

1. **Outside the current visible filter scope: legacy text-search URLs.** `RequestService::validateHomeFeedQuery()` and `BloodRequestRepository::pageCompatibleOpenForViewer()` support `q` against facility/municipality text, but `HomeFeedPage` rebuilds its filter key from only `municipality_code`, `blood`, and `urgency`. A shared `/?q=Hospital` URL therefore makes an unfiltered feed request. There is currently no free-text input in this Home filter card. This is not a defect or missing requirement in the three visible Home dropdowns. The audit does not recommend adding a text-search field merely because the API retains this parameter. The earlier summary overstated it as a Home filter gap.
2. **Demand aggregates can become stale while left open.** Rechecked after the user asked whether last night's refresh work covered this widget: the shared 10-second policy is used by admin/report views, while DemandMapWidget does not import or use it. The separate CHAPTER_DEMAND_AUTO_REFRESH_PLAN.md is explicitly plan-only. Mini-mode chapter and urgency selections filter the last loaded aggregate response locally. There is no periodic refresh or push invalidation. Reopening the expanded widget remounts it and fetches again. The feed itself polls every five seconds, so feed and chart counts can reflect different refresh times.
3. **Old draft documentation is not the final behavior contract.** `docs/features/home.md` is explicitly a draft with unresolved decisions and historic profile/demand access rules. The current source/context and requirements-status reconciliation take precedence. The current feed has three dropdowns, no standalone chapter dropdown or free-text search box; chapter selection is in the demand widget.

## Evidence

- `tests/home_ui.mjs` — passed; rendered feed controls, existing card safeguards, demand chapter/urgency combinations, zero state and reset.
- `tests/match_filters_offline.mjs` — passed; adjacent matching-filter regression coverage, not proof of Home HTTP behavior.
- `tests/home_filter_controls_offline.mjs` — new; passed against the actual Home callbacks and request construction with synthetic transport. Includes combined parameters, plus encoding, clearing one filter, reset, pagination reset/query key, restored selections, legacy multiselect, abort-signal forwarding and reproduction of ignored `q`.
- `tests/home_filters_readonly.php` — new; 405 SELECT-only checks passed, including 64 nonempty cases across eight simulated donor blood types. Results, order and counts were compared with independent PHP evaluation of raw database rows. Pagination and invalid query values were checked. All work ran inside a read-only transaction followed by rollback; no accounts or blood types were modified.
- Maximum measured repository call was approximately 20 ms in this local run. This is not an end-to-end or load-performance certification.

No login attempts, fixture creation, schema changes, task scheduling, worker execution, expiry processing or email delivery were performed. The existing MariaDB recovery checkpoint file was left untouched.
