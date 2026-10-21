# Facility address and donor proximity — 21 October 2026

## Completed scope

- Compatibility and eligibility remain prerequisites. Candidate generation and donor-result queries now order by known approximate distance ascending, same request chapter only for equal distances, then donor ID. Unknown distances follow known distances. Generation and the retained legacy score column do not control order.
- Distance still uses the request's saved facility coordinates and the donor's saved profile coordinates. It is approximate straight-line distance between stored reference points, not road distance or travel time. Seeded barangays share municipal reference coordinates, so barangay selection does not imply barangay-level precision.
- Hospital directory API adds address completeness and coordinate-free location labels. A coherent active barangay reference with coordinates and an active parent supplies a complete saved address. The schema has no independent address-verification flag; no addresses were inferred or newly certified.
- Complete saved hospital addresses display read-only and are resolved again on the backend, overriding conflicting submitted location IDs. Directory name lookup prevents bypass by omitting hospital ID.
- Two small native radio options, Hospital and Other donation venue, appear under Donation location. Other donation venue shows only the municipality/city and optional barangay selectors: there is no meeting-place name textbox. New saves use the generic label `Other donation venue` in existing `facility_name`, with no hospital link; editing also recognizes the previous `Other location` label without bulk rewriting it. UI mode is not submitted or stored in a new column. Municipality/city remains required and barangay optional in both modes. Title/card, bottom padding, and spacing between location fields remain increased using existing spacing tokens.
- Municipality-only or unusable directory references permit manual entry. A coherent known hospital municipality is enforced for manually selected locations. Unlisted facilities remain supported. Switching facilities clears stale selections; a backend facility-only switch without a new address clears copied address values. Explanatory paragraphs were removed from the form at the user's request.
- Facility edits use the existing transaction/rematching workflow. Completed matches retain their stored history; existing offer/report protections remain in place. Unrelated partial edits do not repair or rewrite existing hospital addresses.

## Storage and application audit

No schema change or migration is needed. Existing `hospitals.location_id`, `bataan_locations`, request coordinate/location fields, and match distance fields suffice. No application records were written, no bulk rematch ran, and no mail transport or worker ran.

Read-only application audit: `bloodmatch`, port 3306. All 14 hospitals have municipality-only directory references; none currently has a complete barangay address. They prefill the known municipality; users can proceed without knowing or selecting a barangay. Full-address automatic/read-only behavior was verified with synthetic complete-directory fixtures in the disposable test database only.

Active, unexpired linked requests requiring targeted municipality review:

| Request | Hospital ID | Request location ID | Hospital reference ID | Finding |
|---|---|---|---|---|
| 52 | 9 | 12 | 26 | Municipality mismatch |
| 53 | 9 | 12 | 26 | Municipality mismatch |
| 56 | 9 | 12 | 26 | Municipality mismatch |
| 58 | 9 | 12 | 26 | Municipality mismatch |
| 69 | 11 | 216 | 26 | Municipality mismatch |
| 73 | 9 | 162 | 26 | Municipality mismatch |
| 91 | 12 | 193 | 52 | Municipality mismatch |

These findings do not establish which real hospital address is correct. Confirm it with the requester/directory source before targeted edits. Re-run `C:\xampp\php\php.exe scripts/audit_facility_locations.php` for a fresh read-only report; the script uses the configured application port without fallback and a read-only transaction.

## Verification

Actual automated checks:

- `C:\xampp\php\php.exe tests/facility_proximity_db.php`: 25 checks passed on verified `bloodmatch_test:3306`; one outer transaction rolled back all fixture data; fake mail class installed, zero transport calls. Covers distance/chapter/ID/null ordering, obsolete sort keys, requester home independence, hospital authority/conflicting payloads, optional barangay, other-location storage in existing fields, incomplete/unmapped/manual entries, stale selection clearing, facility edit recalculation, completed history, pending report/offer retention, and owner/compatibility/availability/cooldown/verification/age gates.
- `C:\xampp\php\php.exe -d auto_prepend_file=tests/helpers/consolidation_isolation.php tests/offer_chat_db.php`: 65 existing offer/chat checks passed; fixture rollback; no files or SMTP.
- `node tests/facility_form_component.mjs`: component-handler checks passed with mocked hooks and API. Covers two native venue radios under Donation location, Hospital / Other donation venue labels and selected state, no meeting-place textbox, read-only saved address, actual save payloads, optional barangay, required municipality, manual entry, and clearing stale values when switching modes/facilities. This is not a browser test.
- `node tests/facility_address_offline.mjs`: 6 selection/state checks passed.
- Selected existing `request_ui_static.mjs` checks (`REQUEST_UI_CHECK=Existing form fields|Save and discard|Request modal regression|Donor panel`): 4 passed.
- `node tests/match_filters_offline.mjs`: 13 checks passed.
- `C:\xampp\php\php.exe tests/request_lifecycle_offline.php`: 22 checks passed.
- Frontend production build passed with its bundle-size warning. PHP syntax and diff whitespace checks passed.

The unrestricted existing `request_ui_static.mjs` stops at its donor-profile-link assertion (`href="/profile/9"`). The same failure was reproduced while supplying the original HEAD version of the only edited frontend file to its loader. It was left unchanged to preserve unrelated work.

No browser interaction or visual layout was verified: browser inventory was empty and the in-app browser reported unavailable. No HTTP endpoint integration test was run; backend payload and authorization tests exercised their service paths.

## Minimum rollout / defense preparation (not executed)

1. Deploy backend and rebuilt frontend together through the normal process; no migration, data repair job, or bulk rematch is required. Existing match distances remain stored snapshots; result queries immediately use the new ordering when the code is active.
2. Confirm the hospital and intended destination for the seven flagged requests. Any targeted application edits require separate authorization; normal owner facility edits then recalculate eligible unfinished matches through the existing workflow. Do not alter completed-match history.
3. In a browser, smoke-test selecting a listed municipality-only hospital without a barangay, checking the increased title/bottom spacing, and reviewing nearest-first results. Use disposable/demo records. Distance remains an approximate municipal-reference calculation.

Optional later directory work: obtain independently checked hospital barangay addresses before updating individual directory references. No directory enrichment, ERD, data dictionary, manuscript, separate donation-event workflow, application migration, or bulk correction is included here.
