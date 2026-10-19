# Donation source retirement - 19 October 2026

User confirmed export completion and authorized retirement. ERD generation/updates were explicitly declined; none were performed for this checkpoint.

Migration `035_retire_donation_sources.sql` retired exactly `donation_credits`, `donation_report_holds`, and `compatibility_matrix`. Main `bloodmatch:3306` now has **34 physical base tables**. The disposable test database has 35 because its unrelated password-reset table remains.

Before dropping:

- Rechecked application migration 032/033 markers, source-to-current-state preservation, current credit validity, context identity, and exact compatibility ordering. All five checks reported zero mismatches.
- Found zero inbound foreign keys, view, trigger, routine, or database-event dependencies. Application reads/writes use the migration-gated consolidated path; old hold SQL is translated to a report projection. The seed runner uses normalized seed 006.
- Compared all three main source-table digests with the recorded 032 cutover evidence: unchanged. Retention did not silently remove historical source rows before this retirement.
- Created an additional full main backup including schema/data/triggers: `C:\BloodMatchBackups\bloodmatch-before-035-retirement-20261019-213955.sql`. Verified exact target header, completion footer, and SHA256 `a1950122b5604650263e897c5cd7537e9466d57d6d2d0fd9de45edb985e4f7ee`. This is independently verified backup evidence; the user's separate export path was not inspected.

Retirement was rehearsed on `bloodmatch_test` first, preserving hashes of all 34 remaining business tables. With old tables absent, 35 donation, 65 offer/chat, and 21 consolidation checks passed using fake transport and rollback fixtures. One initial test run encountered a fixture-time collision; the fixture now uses a fixed occurrence base so advancing wall-clock seconds cannot duplicate a synthetic time. No production rule was changed.

Main retirement preserved hashes across **all 33 remaining business tables**, excluding only the migration ledger. The remaining reports, histories, evidence, users, matches, requests and reference data were unchanged. Read-only runtime checks passed for consolidated storage, all eight compatibility types/27 ordered pairs, staff queue, and retention queries. No main fixture or donation action was executed.

An additional 21 compatibility/constraint/isolation checks passed after adapting their reference comparison to the saved pre-normalization fixture rather than the retired table: **142 post-retirement checks passed in total**. Main HTTP health/database connectivity passed after reopening. The two previously enabled application tasks were restored and verified Ready without manually starting a worker; maintenance ended and the `.env` hash was unchanged.

Evidence and backup/task state are in ignored/private `backend/storage/maintenance/application-retirement035-result.json` and `application-retirement035-state.json`. Only the three named tables were dropped. No personal record/message/document contents were printed. No ERD files were generated or changed.

Recovery boundary: direct reconstruction through `recover_donation_sources.php` is now deliberately refused because the old sources no longer exist. Recovery requires the verified full backup and an explicit maintenance/reconciliation plan; never restore the pre-retirement dump over later activity without accounting for new writes. Do not remove the 032 storage-selection marker or rerun old migration/seed files directly.

Earlier ERD/data-dictionary exports describe their generation-time snapshots and are now stale as to the three retired tables; they were intentionally left untouched at the user's request.
