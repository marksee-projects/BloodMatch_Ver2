# BloodMatch database improvement, table reduction, and relationship proposal

Prepared: **20 October 2026 (Asia/Manila)**.

**PROPOSAL ONLY.** Creating this document does not approve or implement any new database, backend, frontend, worker, configuration, or feature change. No migrations, database queries, automated suites, or schedulers were executed for this document. No ERD was generated or updated.

This consolidates the earlier database review, additional table-reduction candidates, floating-table discussion, and their effects on current features. It uses the earlier live inspection and rollout evidence, with a source review of relevant current services. The last verified main inventory is **34 base tables**; projected counts assume that inventory has not changed independently.

## 1. Approval and implementation status

| Scope | Status |
| --- | --- |
| Donation credit/hold consolidation and compatibility-pair normalization | Previously approved, tested, and applied to main through 032 |
| Donation uniqueness/relationship constraints and context identity protection | Previously approved and applied through 032/033 |
| Retirement of the three replaced source tables | Subsequently approved and completed through 035 |
| Additional reductions discussed in this document | **Not approved; not implemented** |
| New relationships for disconnected tables | **Not approved; not implemented** |
| Remaining improvements from the earlier design review | Review proposals unless explicitly identified below as complete |
| Preparation of this Markdown document | Authorized documentation work only |

The earlier approvals were scoped to those completed changes. They are not blanket approval to redesign other tables or change product rules.

## 2. Current baseline and completed improvements

The completed donation changes preserve distinct facts: current report state, confirmed credit, independent safety holds, multiple evidence photos, report history, accepted offer contexts, coordination activity, and correction-review requests.

| Earlier review item | Completed result |
| --- | --- |
| Repeated credit state in a separate table | Credit state now resides in `donation_reports`, with explicit nullable `credit_recorded_at` |
| Separate one-row-per-report hold state | Independent start/release/actor/basis/type fields now reside in `donation_reports` |
| Legacy confirmation without a known donation occurrence | Preserved without inventing a date or awarding credit |
| Duplicate reports and credits | Unique report per match; conditional credited donor/occurrence uniqueness; transactional retry handling |
| Independently valid but contradictory donor/match references | Composite match/donor FK and context identity safeguards |
| Unconstrained request renewal relationship | Nullable predecessor FK and one-successor uniqueness |
| Workflow/domain gaps | Positive request quantity, report version/state/response checks and credit prerequisites |
| Duplicate chat evidence within the same case | Unique case/message pair |
| Compatibility types stored as a repeating list | `compatibility_pairs` stores 27 ordered rows for eight recipient types, preserving existing compatibility and order |
| Retained obsolete source tables | `donation_credits`, `donation_report_holds`, and `compatibility_matrix` retired after backup, dependency, and preservation checks |

Main has six active donation tables: `donation_reports`, `donation_report_evidence`, `donation_report_events`, `donation_chat_contexts`, `donation_coordination_events`, and `donation_correction_requests`.

Evidence: [consolidation checkpoint](DONATION_CONSOLIDATION_032.md), [source retirement checkpoint](DONATION_SOURCE_RETIREMENT_035.md), migrations [032](../database/migrations/032_consolidate_donation_storage.sql), [033](../database/migrations/033_immutable_donation_context.sql), and [035](../database/migrations/035_retire_donation_sources.sql). Those checkpoints describe previous checks; this document does not claim a new test run or browser verification.

## 3. Additional table-reduction candidates

The intended outcome is fewer storage structures with the same approved features. Backend readers, writers, joins, locks, jobs, cleanup paths, and tests would change. No candidate is safe merely because its table is small or empty.

### R1. Move `donor_response_state` into `users`

**Potential reduction: one table. Priority: first review candidate.**

Current relationship: each response-state row belongs to one user, and a user has zero or one such row. Its facts are `standby_started_at` and `consumed_through`.

Proposed storage: explicitly named nullable non-response fields on `users`. Historical users without a state row must retain the equivalent absence of state; no historical timeout should be fabricated.

| Feature affected internally | Required preservation |
| --- | --- |
| Non-response opportunity processing | Existing issue time, stored deadline, generation and obsolete-opportunity rules remain unchanged |
| Standby eligibility | Non-response restriction remains separate from availability, post-donation standby/cooldown and report holds |
| Acknowledgment/recovery | Clear only non-response standby and retain the consumed-through watermark |
| Repeated processing and concurrent acknowledgment | No repeated sanction or resurrection of consumed/obsolete opportunities; preserve locking and atomic updates |
| Profile/eligibility DTOs | Maintain the current API response shape; do not expose new internal fields unnecessarily |

Affected source includes [NonResponseStandbyService](../backend/src/Services/NonResponseStandbyService.php), donor eligibility projections, matching/offer services, and associated worker/tests. Review lock order carefully because more state updates would target the user row.

This consolidation does **not** approve changing the current 42-hour non-response deadline. The separate [42-to-48-hour proposal](research/NON_RESPONSE_48_HOUR_CHANGE_PROPOSAL.md) remains a separate product decision. Neither response deadline controls how long acknowledgment-based non-response standby lasts.

### R2. Move `account_closures` into `users`

**Potential reduction: one table. Priority: second review candidate.**

Current relationship: a user has zero or one closure row containing `closed_at`, `erase_after`, and `profile_erased_at`. Proposed storage keeps those distinct nullable facts on `users`.

| Feature affected internally | Required preservation |
| --- | --- |
| Permanent closure | Password/confirmation checks, immediate deactivation and session invalidation remain |
| Administrative safeguards | Preserve the existing administrator and no-reactivation rules |
| Request, offer, consent and queued-mail cleanup | Maintain current closure behavior and transaction ordering |
| Personal-data erasure | Retain the original erasure deadline and completed-erasure marker; never restart a deadline during migration |
| Donation investigations and history | Preserve pending reports, safety restrictions, evidence/case retention and applicable audit guards |
| Erasure retries | Already completed erasure remains idempotent; absence of a closure must not be mistaken for an expired closure |

Do not collapse these facts into `account_status` or `deactivated_at`: an ordinary deactivation and a permanent closure with scheduled erasure have different meanings.

Affected source includes [AccountDeletionController](../backend/src/Controllers/AccountDeletionController.php), [AccountLifecycleService](../backend/src/Services/AccountLifecycleService.php), [RetentionService](../backend/src/Services/RetentionService.php), and closure-aware audit protection. Migration 026's audit-retention trigger also references `account_closures`; database dependencies must be updated before retirement.

### R3. Move `email_donor_reservations` into `email_attempts`

**Potential reduction: one table. Priority: investigate after R1/R2.**

Current relationship: an email attempt has zero or one donor reservation. All transport attempts must remain represented, including attempts without a donor reservation or an existing account.

Proposed storage would retain optional donor reference, reservation timestamp, outcome, and outcome-update timestamp on the attempt. Historical absence of a reservation must remain distinguishable from an outstanding reservation.

Preserve independent accounting commits, shared hourly/daily budgets, protected account-mail capacity, per-donor recipient limits, and outstanding/accepted/uncertain/released outcomes. Workflow rollback, transport failure, or a crash must not incorrectly refund consumed capacity. Do not infer a successful send from an attempt or reservation.

Affected source includes [EmailAttemptBudget](../backend/src/Services/EmailAttemptBudget.php), workers, accounting checks, and cleanup routines. This is the most sensitive reduction because its transaction boundary is intentionally independent of business workflows. It does not approve expanding email coverage or sending test email to real recipients.

### Count scenarios

| Selected changes | Projected main table count |
| --- | ---: |
| Current baseline | 34 |
| R1 only | 33 |
| R1 and R2 | 32 |
| R1, R2 and R3 | 31 |
| Add a `blood_types` reference table only | 35 |
| R1/R2 plus `blood_types` | 33 |
| All three reductions plus `blood_types` | 32 |

These are alternative review scenarios, not promised final counts. R1 and R2 already have user FKs; consolidating them reduces table count but does not itself solve the disconnected-table concern.

## 4. Floating-table relationship proposals

The earlier FK inspection found the following groups outside the main connected component. A table can be actively used without having a user FK. Lines must represent actual facts; operational lookup use is not automatically an enforced FK.

| Table/group | Current reason for separation | Proposal and tradeoff |
| --- | --- | --- |
| `compatibility_pairs` | Reference rules relate blood types, not particular user accounts | **F1:** Introduce `blood_types` with eight unique type codes. Users, requests and both compatibility endpoints reference it. This connects the domain correctly and adds one table; preserve nullable user blood type, existing values, compatible substitutions and pair ordering. FK column types/collations and indexes need a migration review. |
| `pending_registrations` | Registration staging exists before successful OTP creates a user | **F2:** Move chapter/location references from the payload into explicit relational columns with FKs. Select one authoritative location for each fact rather than maintaining contradictory JSON and column copies. Preserve staged uploads, OTP expiry/resend, privacy acknowledgment and atomic account creation. Do not create user accounts early merely to obtain a connecting line. |
| `auth_throttle` | Identifiers include pre-account, invalid-login and other opaque rate-limit keys | **F3:** Consider an optional user FK only for limits associated with an already authenticated/identified account. Unknown/pre-account keys remain valid without a user. Preserve rate limits, expiry and closure cleanup; do not cascade away security state without a policy review. This does not make every throttle row account-owned. |
| `system_settings` | Global configuration is currently seeded and read/validated by services | **F4:** An optional `updated_by` user FK is meaningful if actual administrator updates are implemented and recorded. Existing system-seeded rows must not receive an invented actor. The settings editor is currently missing, so this option includes additional product/backend scope; a nullable unused column alone provides no substantive provenance. |
| `email_attempts` and `email_donor_reservations` | The two tables connect to each other; the donor identifier is intentionally not a user FK | **F5:** Review a user relationship only after resolving independent-transaction locking and deletion/retention behavior. Adding a user FK can block against an outer transaction that already locks that user. Preserve budget records when a business transaction rolls back or an account is erased. R3 alone does not connect this component to the core. |
| `schema_migrations` | Deployment ledger keyed by migration name | **F6:** Keep as infrastructure. No genuine donation/member owner exists. Do not attach a fabricated user or merge the ledger into retention-managed audit history. |

F1-F5 are potential models, not approved schema changes. In particular, F3 and F4 are justified only when real corresponding writers/actors exist.

### Academic presentation scope

Ask whether the grading rule concerns the **business ERD** or every table in the **physical schema**. A business ERD can focus on actual domain relationships while infrastructure tables are separately disclosed and explained. That does not mean hiding or deleting tables from the physical inventory.

If every physical table must be connected, confirm the professor's expected treatment of pre-account security, configuration and deployment bookkeeping. The current design cannot honestly guarantee a meaningful user/donation FK for every infrastructure record. Merely drawing a line does not create a constraint or a real dependency.

No diagram is included in this proposal, consistent with the user's instruction not to generate an ERD.

## 5. Other improvements carried forward from the earlier review

| Item | Current assessment | Proposed next action |
| --- | --- | --- |
| Facility name, hospital, location and copied coordinates | Multiple related values can disagree; FK existence alone does not prove agreement | Define which are request-time snapshots versus current catalog values; then review validation and historical behavior. Avoid replacing historical facility facts with today's catalog values. |
| Location municipality name/code repetition | Reference data includes repeated facts and a possible transitive dependency | Document deliberate reference/snapshot exceptions or propose a normalized municipality relationship. Do not change proximity or chapter-ranking policy as a side effect. |
| Donation event history protection | Services append events; protection is not identical to general audit-log triggers | Review ordinary database privileges and approved retention boundaries before adding update/delete guards. Keep authorized erasure and case retention functional. |
| Completed donation corrections | Existing workflow records participant requests and staff responses; it does not amend/reverse completed credit | Define any new amendment/reversal rules separately, including restriction anchors, fulfillment, duplicate prevention and history. It is a feature change, not merely table consolidation. |
| Legacy `blood_requests.description` | Earlier review found populated legacy values without a clear current migration/writer path | Investigate current provenance and use before any removal. The earlier count is not a fresh inventory or evidence the data is disposable. |
| Overlapping indexes and queue performance | Some indexes have overlapping prefixes; no performance failure was established | Inspect actual query plans/workload before adding/removing indexes. Preserve FK support and uniqueness indexes. |
| Audit lifetime versus original requirements | Current approved retention behavior differs from older permanent-audit wording | Reconcile documentation and product policy separately; do not silently change retention through this storage proposal. |
| Old schema exports | Earlier exports predate source retirement and were intentionally left untouched | Clearly label generation-time evidence when sharing it. Regeneration requires a separate request; none is performed here. |

These items are not all confirmed runtime defects. Some are design ambiguities, intentional exceptions or missing product decisions. Revalidate each before implementation; do not treat an old audit snapshot as current failure evidence.

## 6. User-facing feature impact

For R1-R3 and relationship-only changes, the target is the same UI, API behavior and approved rules. Internal storage changes still require backend changes and can affect features if incorrectly implemented.

Required invariants include:

- Preserve donation occurrence, confirmation/credit distinction, explicit-version acknowledgment, independent holds and historical events.
- Keep fulfillment atomic; preserve late/excess donations without reopening or fulfilling terminal requests incorrectly.
- Preserve eligibility restrictions, existing response deadlines, consumed-through watermarks and donor preferences as distinct facts.
- Preserve closure/erasure deadlines, investigation guards, consent and access restrictions.
- Preserve independent email accounting, deduplication, retry/crash handling and existing recipient/budget limits.
- Keep evidence photos as individual child records. Do not introduce photo1/photo2/photo3 columns or operational lists of IDs/files.
- Do not infer unused tables from empty rows, or accidental duplicates from repeated FKs/history.

F4's actual settings editor and any substantive completed-credit amendment are exceptions: they add product capability and require explicit feature approval. Configurable timing, email coverage, clinical scope and role policy must not change under a structural approval.

## 7. Validation and rollout requirements for any approved phase

1. Agree on exact scope and unchanged behavior. Review affected constraints, triggers, source writers, jobs and API responses before preparing migrations.
2. Inventory affected data using read-only metadata/aggregate checks; report unknowns without exposing personal information or credentials.
3. Prepare an additive migration, explicit old-to-new field mapping and recovery plan. Preserve NULL/absent-row meanings and original timestamps. Do not invent actors, historical events or relationships.
4. Verify the exact disposable test target with no fallback; use fake transport and keep application schedulers and external sends out of test execution.
5. Test preservation and feature-specific retries/concurrency. R1 requires obsolete/consumed timer and acknowledgment races; R2 requires closure/erasure idempotency and investigation safeguards; R3 requires independent commits, limits, failure/crash outcomes and transport behavior.
6. For new FKs, test valid/invalid writes, optional references, update/delete semantics and independent-connection locking. Test compatibility order/equivalence and staged-registration success/expiry where relevant.
7. Present test results and remaining limitations for application approval. An implementation pass does not certify browser behavior, delivery, recurring jobs, performance or uptime without corresponding evidence.
8. During approved application maintenance, stop writers, take a verified full backup, apply only reviewed migrations, reconcile old/new facts and verify read paths. Retain old sources until retirement is separately authorized after preservation checks.
9. Once old sources are retired, document the changed recovery boundary. An old full backup must not overwrite later activity without a reconciliation plan.

No such execution is authorized by preparing this document.

## 8. Recommended decision order

1. Clarify the professor's business-versus-physical ERD requirement.
2. Review R1 and R2 as the first reduction phase, potentially **34 to 32 tables**, with no intended feature changes.
3. Decide whether F1/F2 provide sufficient integrity benefit to justify their migration scope. F1 increases the table count by one.
4. Investigate R3/F5 together before committing to email-accounting or user-FK changes.
5. Defer F3/F4 unless real actor/reference requirements justify the additional writers and feature scope. Keep F6 as disclosed infrastructure.
6. Prioritize necessary integrity and workflow decisions over cosmetic table-count or diagram changes.

## 9. Supporting sources

- [Current requirements and feature status](features/BLOODMATCH_CURRENT_REQUIREMENTS_STATUS.md)
- [Requirements baseline](../.agents/requirements/BloodMatch_System_Requirements_and_Features.md), interpreted alongside approved current workflow changes
- [Current flow/safety audit](BLOODMATCH_FLOW_SAFETY_AUDIT.md), including its historical-snapshot qualifications
- [Donation consolidation evidence](DONATION_CONSOLIDATION_032.md) and [retirement evidence](DONATION_SOURCE_RETIREMENT_035.md)
- [Separate non-response timing proposal](research/NON_RESPONSE_48_HOUR_CHANGE_PROPOSAL.md)
- [DonationStorageRepository](../backend/src/Repositories/DonationStorageRepository.php), [DonationCompletionService](../backend/src/Services/DonationCompletionService.php), [DonationCorrectionService](../backend/src/Services/DonationCorrectionService.php)
- [EmailAttemptBudget](../backend/src/Services/EmailAttemptBudget.php), [PendingRegistrationService](../backend/src/Services/PendingRegistrationService.php), [SystemSettingsService](../backend/src/Services/SystemSettingsService.php)

**Decision required before implementation:** select specific proposal IDs and authorize their preparation/testing scope. Application migration, historical-data changes, source retirement, scheduler activation and expanded features require their corresponding explicit approval.
