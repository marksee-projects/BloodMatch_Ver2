# BloodMatch Entity Relationship Model: factual description basis

Verified **22 October 2026 (Asia/Manila)** against the configured application database and current workspace code. This was a read-only inspection: metadata and migration names were queried in a read-only transaction. No application records, uploaded document contents, credentials or encryption keys were retrieved for this document. No database changes, migrations, workers, sample data or ERD edits were performed.

## 1. Verified database scope and counts

| Item | Current verified finding |
| --- | --- |
| Application target | `bloodmatch`, configured on `127.0.0.1:3306`; connection target independently checked with `DATABASE()` and `@@port` |
| Database server | **MariaDB 10.4.32**; `VERSION()` returned `10.4.32-MariaDB`, with version comment `mariadb.org binary distribution` |
| Table storage engine | **InnoDB for all 34 application base tables**; server default is also InnoDB |
| Current application inventory | **34 physical base tables; 0 views**. This is a fresh metadata count, not a count copied from an older audit. |
| Business and reference tables | **22**, marked **B** below: accounts, verification records, requests, compatibility, matches, donation workflow, chat, notifications and domain reference catalogs |
| Operational/support and retained-history tables | **12**, marked **S** below: registration staging, throttling, closure scheduling, audit, presence, email processing, settings, deployment bookkeeping and the two retired non-response tables |
| Previously edited presentation copy | `bloodmatcherd (13)` currently has **29 base tables**; its saved phpMyAdmin Designer page **2** contains the same 29 table names. It is a reduced academic copy, not the application schema. |
| Test database | `bloodmatch_test` currently has **35 base tables**, including `password_resets`, which is absent from the application database. Test status is not application status. |

The B/S split is a descriptive grouping for this explanation, not a database-enforced distinction or a recommendation to remove support tables. In particular, registration staging and email processing remain necessary implemented functions even though they are classified as support.

**Submitted ERD status:** no final submitted diagram or confirmed submission file was supplied in this task. The earlier presentation copy is an identifiable candidate, so the inventory records its current saved Designer membership as **Copy ERD: Yes/No**. These markers do not establish what was actually submitted. Comparison against the final submitted artifact remains **pending**. Existing repository diagrams are separate historical snapshots, compared in section 5. The BayaniMIS passage itself is not present in the available conversation/files inspected; the manuscript follows the requested concise paragraph structure without claiming a verbatim stylistic comparison.

## 2. Table inventory, grouped by feature

**Reading the relationships:** `?` marks a nullable FK. An unmarked FK requires exactly one referenced parent per child row; `?` permits zero or one. Unless an exception is stated, a parent can have **zero to many** child rows (`1:N`). A FK does not require a parent to have children. `0..1` on the child side is supported by a primary or unique key. Cardinalities below describe the physical constraints; code may impose narrower workflow rules. All references in this inventory are within `bloodmatch`.

### Accounts, registration, and member verification — 6 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `users` | B / Yes | Member and Admin accounts; profile, verification state, donor enrollment/availability, blood type, account status and donation timing. Admin means an authorized organization officer, not a separate officer entity. | `id` | `chapter_id? → chapters.id`; `location_id? → bataan_locations.id` | Each chapter/location has `0..N` users; each user has `0..1` of each. Donor is a participation state of a Member, not a separate donor table. |
| `pending_registrations` | S / No | Temporary registration payload and upload metadata pending email-code verification; code verification creates the user and removes the staging record. | `id` | None | Standalone; unique `email`. There is no retained registration-to-user FK or enforced 1:1 user relationship. |
| `auth_throttle` | S / No | Identifier-based attempt/rate-limit state used by login and registration-related paths. | `identifier` | None | Standalone. An identifier need not correspond to an existing account. |
| `member_documents` | B / Yes | Metadata for national ID, donor card and parental-consent uploads, supporting verification and donor eligibility. | `id` | `user_id → users.id` | User `1:N` documents. File bytes are not stored in these metadata columns. |
| `verification_decisions` | B / Yes | Verification decisions and resubmission/re-review history for a Member; Admin review. | `id` | `target_user_id → users.id`; `officer_id? → users.id` | Target user `1:N` decisions; reviewing user `1:N` decisions, with nullable reviewer. `officer_id` is the actual historical column name; the current role is Admin. |
| `account_closures` | S / Yes | Permanent-account-closure timestamp, scheduled erasure date and erasure-completion marker. | `user_id` | `user_id → users.id` | User has `0..1` closure record; each closure belongs to exactly one user. |

### Donor participation and eligibility — 2 retained tables, with active state in other groups

Active enrollment, availability and last verified donation timing are stored in `users`; document/age checks use `member_documents`; compatibility uses `compatibility_pairs`; unresolved donation holds reside in `donation_reports`. There is no additional active donor master table.

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `donor_opportunities` | S / Yes | Retained per-request, donor and generation history from the retired unanswered-opportunity timer workflow. | `id` | `request_id → blood_requests.id`; `donor_id → users.id`; `notification_id? → notifications.id` | Each request/user has `0..N` historical opportunities. Each notification can be referenced by `0..N` rows under the physical schema; its FK is not unique. Unique `(request_id, donor_id, generation)` prevents repeat rows for that combination. **Not an active non-response sanction mechanism.** |
| `donor_response_state` | S / Yes | Retained legacy non-response-standby timestamp and acknowledgment watermark. | `user_id` | `user_id → users.id` | User has `0..1` legacy response-state record. Current service entry points are inert; retained values do not activate the retired restriction. |

### Blood requests, compatibility, and matching — 3 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `blood_requests` | B / Yes | Member blood requests: required type, quantity, facility, urgency, needed time, status and request context; renewal linkage. | `id` | `requester_id → users.id`; `request_chapter_id? → chapters.id`; `hospital_id? → hospitals.id`; `location_id? → bataan_locations.id`; `renewed_from_id? → blood_requests.id` | User `1:N` requests; each chapter/hospital/location `1:N` requests, with optional reference per request. Renewal is an optional self-reference; unique `renewed_from_id` permits at most one immediate successor per predecessor. |
| `compatibility_pairs` | B / No | Ordered recipient/donor blood-type rules read by the compatibility and matching services. | `(recipient_type, donor_type)` | None | Standalone reference table. Blood types are ENUM values, not FKs to users or a blood-type entity. Unique `(recipient_type, preference_order)` preserves order per recipient. |
| `matches` | B / Yes | Persisted donor/request association with generation, offer cycle, status, distance and ranking values. | `id` | `request_id → blood_requests.id`; `donor_id → users.id` | Request `1:N` matches; donor user `1:N` matches. Unique `(request_id, donor_id)` represents the conceptual many-to-many relationship between requests and donors. |

### Donation reporting, confirmation, evidence, and history — 6 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `donation_reports` | B / Yes | Donation claim and occurrence time, requester response, review/confirmation state, credited donation marker and independent report-hold state. | `id` | `match_id → matches.id`; `donor_id → users.id`; `(match_id, donor_id) → matches.(id, donor_id)`; `confirmed_by? → users.id`; `acknowledged_by? → users.id`; `hold_released_by? → users.id`; `offer_context_id? → donation_chat_contexts.id` | Match has `0..1` report because `match_id` is unique. Donor/reviewer/acknowledger/releasing user each has `0..N` reports in that role. Offer context has `0..N` reports under the FK/uniqueness structure alone; code and context-identity triggers impose further same-match rules. Composite FK keeps the report donor consistent with its match. |
| `donation_report_evidence` | B / Yes | Versioned proof-file metadata, including uploader and retired-version markers. | `id` | `report_id → donation_reports.id`; `uploader_id? → users.id` | Report `1:N` evidence files; uploader user `1:N` files. Uploading proof does not itself confirm a donation. |
| `donation_report_events` | B / Yes | Versioned report-event history, event basis and snapshots. | `id` | `report_id → donation_reports.id`; `actor_id? → users.id` | Report `1:N` events; actor user `1:N` events. `actor_reference` is a retained reference, not another FK. |
| `donation_chat_contexts` | B / Yes | Accepted offer context preserving the match, offer cycle, response/acceptance times and optional coordination conversation. | `id` | `match_id → matches.id`; `conversation_id? → conversations.id`; `accepted_by? → users.id` | Match `1:N` contexts across offer cycles; unique `(match_id, offer_cycle)`. Conversation and accepting user each have `0..N` contexts; both references are nullable. |
| `donation_coordination_events` | B / Yes | Coordination action history for an accepted donation offer context. | `id` | `context_id → donation_chat_contexts.id`; `actor_id? → users.id` | Context `1:N` coordination events; actor user `1:N` events. `actor_reference` and `report_version` are not separate FKs. |
| `donation_correction_requests` | B / Yes | Participant requests to review a confirmed report and recorded Admin responses; does not itself reverse credited donation state. | `id` | `report_id → donation_reports.id`; `actor_id? → users.id`; `resolved_by? → users.id` | Report `1:N` requests; requesting/resolving user each `1:N` requests. Unique `(report_id, content_version, actor_reference)` limits duplicates for that version and participant reference. |

### Chat, consent, and message reports — 7 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `chat_requests` | B / Yes | Member chat-consent request and pending/accepted/declined/cancelled state. | `id` | `user_low → users.id`; `user_high → users.id`; `sender_id → users.id`; `recipient_id → users.id` | Each user can participate in `0..N` requests in each named role. Unique `(user_low, user_high)` stores one reusable request state per ordered account pair. `conversation_id` is nullable but **not an FK**. |
| `conversations` | B / Yes | Conversation container and timestamps for direct messaging/coordination. | `id` | None outgoing; referenced by participants, messages and donation contexts | Conversation `1:N` participants and messages. Not standalone: incoming FKs connect it to the core schema. Exactly two participants is an application direct-chat rule, not a FK cardinality constraint. |
| `conversation_participants` | B / Yes | Membership in a conversation and per-user read state. | `id` | `conversation_id → conversations.id`; `user_id → users.id` | Conversation `1:N` participant rows; user `1:N` participant rows, yielding users/conversations M:N. Unique `(conversation_id, user_id)`. `last_read_message_id` is not an FK. |
| `messages` | B / Yes | Message text, conversation and sender. | `id` | `conversation_id → conversations.id`; `sender_id → users.id` | Conversation `1:N` messages; sender user `1:N` messages. Sender participation/permission is checked by code, not by a composite FK to participants. |
| `chat_reports` | B / Yes | Message-report case, reason, findings, review status and resolution time for Admin review. | `id` | `reporter_id? → users.id` | Reporting user `1:N` cases, with nullable reporter. `conversation_id` is an application-only link, not an enforced FK. |
| `chat_report_evidence` | B / Yes | Selected and contextual message snapshots retained for a reported case. | `id` | `report_id → chat_reports.id` | Case `1:N` evidence snapshots; unique `(report_id, message_id)`. `message_id` and `sender_id` are **not FKs**, allowing evidence to remain independent of live messages. |
| `user_presence` | S / Yes | Session-scoped recent-presence/online indicators for chat. | `session_key` | `user_id → users.id` | User `1:N` presence rows, permitting multiple sessions; not one row per user. |

### Notifications and email processing — 4 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `notifications` | B / Yes | In-app notification events, recipient, read state and email marker. | `id` | `user_id → users.id` | User `1:N` notifications. Generic `related_type`/`related_id` links and deduplication keys are resolved by code, not FKs to every possible target. |
| `email_outbox` | S / Yes | Notification email queue: priority, delivery identity, lease, retries, expiry and delivery outcome. | `id` | `notification_id → notifications.id` | Notification has `0..1` outbox record because `notification_id` is unique; each outbox row belongs to exactly one notification. |
| `email_attempts` | S / Yes | Independently committed transport-attempt reservations for shared email budgets, including account mail. | `id` | None outgoing; referenced by donor reservations | Attempt has `0..1` donor reservation. It is connected to `email_donor_reservations`, but this pair is a separate FK component in the **application** database. No recipient/user or outbox FK exists here. |
| `email_donor_reservations` | S / Yes | Per-donor emergency-mail reservation and outstanding/accepted/uncertain/released outcome. | `attempt_id` | `attempt_id → email_attempts.id` | Each reservation belongs to exactly one attempt; each attempt has `0..1` reservation. `donor_id` denotes a user in application logic but has **no FK to users in `bloodmatch`**. The presentation copy adds that FK; do not transfer that claim to the application. |

### Analytics, audit, retention, and reference data — 6 tables

| Exact table | Class / Copy ERD | Purpose and related BloodMatch feature | Primary key | Enforced foreign keys | Cardinality and qualifications |
| --- | --- | --- | --- | --- | --- |
| `chapters` | B / Yes | Organization chapter catalog and geographic centroids used by accounts, requests and regional aggregates. | `id` | None outgoing; referenced by users and requests | Chapter `1:N` users and requests; optional chapter on each referencing row. |
| `bataan_locations` | B / Yes | Municipality/barangay reference records, PSGC identifiers and location coordinates. | `id` | None outgoing; referenced by users, requests and hospitals | Location `1:N` users, requests and hospitals; optional on each child. `municipality_code` is not a self-referencing FK. |
| `hospitals` | B / Yes | Facility catalog associated with optional reference location; used by requests. | `id` | `location_id? → bataan_locations.id` | Location `1:N` hospitals; hospital `1:N` requests. Request facility text also exists, so FK existence alone does not guarantee all copied facility facts agree. |
| `audit_log` | S / Yes | Recorded application actions and review/registration history; database update/delete guards and retention-aware handling. | `id` | `actor_id? → users.id` | Actor user `1:N` audit entries, with nullable actor. `target_type`/`target_id` are generic application links, not enforced references to every target table. |
| `system_settings` | S / No | Validated global settings used for post-donation standby and donation cooldown calculations. | `setting_key` | None | Standalone key-value configuration; no updater/user FK. These donation windows are separate from retired non-response standby. |
| `schema_migrations` | S / No | Deployment ledger; also used by runtime readiness/storage-compatibility checks. | `name` | None | Standalone infrastructure; no domain owner or user relationship. |

**Analytics and retention are functions, not additional table entities.** `AnalyticsRepository` aggregates requests, users, matches, reports and reference data; there is no dedicated analytics table in the current 34-table inventory. Account erasure scheduling uses `account_closures`; retention services operate on existing records and file metadata. The existence of code does not establish that scheduled workers have actually run.

## 3. Enforced links, application-only links, and standalone components

The application FK graph contains **27 tables in the component containing `users`**, a separate **2-table email-accounting component**, and **5 individually standalone tables**. This totals 34. A table with no outgoing FK is not necessarily standalone; `conversations`, `chapters` and `bataan_locations` have incoming relationships.

The five individually standalone application tables are exactly `auth_throttle`, `compatibility_pairs`, `pending_registrations`, `schema_migrations` and `system_settings`. Their operational/reference use is real, but does not create an enforced user relationship. The email-accounting pair links to each other through `attempt_id`; its connection to a donor user is application-only in the main database.

| Application-only association | Implemented interpretation and limit |
| --- | --- |
| `email_donor_reservations.donor_id` to `users.id` | Donor-specific email budgeting in `EmailAttemptBudget`; main schema does not enforce the user reference. Independent accounting commits are deliberate. |
| `chat_requests.conversation_id`, `chat_reports.conversation_id` to `conversations.id` | Chat consent/report code associates conversations without these FKs. Do not draw these as database-enforced links. |
| `chat_report_evidence.message_id` / `sender_id` | Message/sender identifiers accompany evidence snapshots, without FKs to `messages` or `users`. |
| `conversation_participants.last_read_message_id` | Code-maintained read marker without a FK to `messages`. |
| Notification `related_type` / `related_id`; audit `target_type` / `target_id` | Polymorphic target references interpreted by services. No universal target-table FK exists. |
| Pending registration payload to future user/chapter/location | Registration code validates/stages payload and creates a user on code verification. JSON values do not enforce FKs and staging rows are removed after account creation. |
| Blood-type values across users, requests and compatibility pairs | Compatibility service uses matching type values; no `blood_types` master table exists in the verified schema. |
| Donation `actor_reference`, `content_version` / `report_version` and generated credited fields | Historical identifiers, versions and conditional credit identity are not additional FKs. `credited_donor_id` / `credited_occurred_at` support a unique credited-occurrence key; the explicit donor FK is `donor_id`. |
| Roles and workflow responsibilities | `users.role` permits `member` and `admin`. FKs to `users` do not themselves enforce that a reviewer is Admin or that a donor is an eligible Member; services check these responsibilities. |

Additional enforced safeguards must also be distinguished from application-only logic: the report-to-match donor identity is a composite FK; unique match/report, request/donor, offer-cycle and participant-pair keys impose the limits noted above. Live report/context triggers additionally prevent inconsistent report offer contexts and changes to accepted context match identity. These do not establish a blanket claim of normalization or security.

## 4. Implemented status, retained history, and migration boundaries

| Topic | Current application finding | Manuscript implication |
| --- | --- | --- |
| Migration status | All **37 numbered SQL files currently present**, 001–036 with two distinct 021 files, are recorded by exact filename in the main migration ledger. One additional non-numbered maintenance marker exists. No currently present numbered SQL migration is pending by filename comparison. Ledger entries alone are not proof of every historical DDL detail; this document uses the live columns/keys/table inventory for its structural claims. | Do not repeat stale migration README claims that later migrations are only prepared or test-only. |
| Password-reset storage | `034_remove_password_resets.sql` is recorded; `password_resets` is absent in `bloodmatch`. It is present in the test database and the old test diagram. | Do not describe a current password-reset table or infer active reset functionality from migration 003. |
| Donation consolidation | 032/033/035 are recorded; `donation_reports` has credited/hold fields and the composite donor/match FK. `donation_credits`, `donation_report_holds` and `compatibility_matrix` are absent. | Describe current consolidated storage and `compatibility_pairs`. Legacy fallback references in code do not establish current physical tables. |
| Non-response standby | `NonResponseStandbyService::ready()` returns false; its mutating legacy methods are empty and due processing returns zero. Profile/auth return `non_response_standby = null`; safety runner no longer processes opportunity timers. Both historical tables remain physically present. | Describe **retired functionality with retained storage**, not table removal and not active 42/48-hour non-response enforcement. |
| Other donation restrictions | Eligibility still checks enrollment, availability, age/consent, verification, blood compatibility, post-donation windows and unresolved donation-report holds. | Non-response retirement does not remove post-donation standby/cooldown or report holds. Do not conflate a report's reminder/escalation timing with the retired opportunity timeout. |
| Temporary chat disciplinary restriction | 036 is recorded; live `users.disabled_until` exists. Chat report decision code can set a temporary account restriction; auth/middleware inspect its expiry. | This is implemented in the application, although the earlier presentation copy lacks this column. No separate disciplinary table was added. |
| Earlier copy-only email change and removals | Main retains the five standalone tables and has no donor-reservation-to-user FK. Those five removals and that FK exist only in the academic copy. | The reduced copy cannot establish current application table count or all application-enforced relationships. |
| Proposed improvements | Items in `DATABASE_IMPROVEMENT_PROPOSAL.md` are not evidence of deployment. | Do not imply proposed user-state consolidations, blood-type reference entities or new provenance FKs are already implemented. |

## 5. ERD differences and submission comparison limits

| Candidate artifact | Verified difference from the current application |
| --- | --- |
| Saved Designer page for `bloodmatcherd (13)` | Shows **29 tables**: all inventory rows marked Yes. It omits `auth_throttle`, `compatibility_pairs`, `pending_registrations`, `schema_migrations` and `system_settings`. It includes an extra FK `email_donor_reservations.donor_id → users.id`, absent from main. Its `users` table lacks main's `disabled_until` column. The two historical non-response tables remain visible despite inactive functionality. |
| `docs/erd_application.md` and associated application diagram snapshot | Markdown identifies a **19 October** application snapshot with **37 entity blocks**. It includes three now-retired tables: `donation_credits`, `donation_report_holds`, `compatibility_matrix`. Its user definition predates `disabled_until`. Its 37 count is not today's count. |
| `docs/erd.md` and associated test diagram snapshot | Markdown identifies a **19 October test** snapshot with **38 entity blocks**, including the same three retired tables plus `password_resets`. It must not be cited as the current application ERD or table count. |
| Final submitted ERD | **Unavailable; final comparison pending.** The earlier Designer copy is a candidate only. No exported final submission was identified, and this task did not alter or regenerate any ERD. |

Do not silently reconcile differences by inventing relationships or treating omitted tables as removed from production. If the 29-table copy is the submitted figure, its caption and explanation should disclose that it is a reduced presentation model; a claim that it depicts every current application table would be inaccurate.

## 6. Manuscript-ready explanation

The Entity Relationship Model shows the database design of BloodMatch, with `users` as the central table for Member and Admin accounts, where an Admin is an authorized organization officer. Member verification is supported by document records and verification decisions, while donor enrollment, availability and donation timing are maintained in the user profile. Blood requests are linked to their requesting Members, and `matches` associates each request with prospective donors. Chapter, location and hospital records provide reference information, while compatibility rules guide the application's selection of suitable donors. Each match may have a donation report that records the reported donation, requester response and confirmation or review status. Related evidence, event history, accepted offer contexts and correction requests support coordination and the review of donation records.

Conversation participants and messages organize Member communication, with chat requests recording consent and message-report records preserving evidence for Admin review. Notifications and supporting email tables manage application alerts and delivery processing. Other supporting records handle registration staging, account closure, activity auditing, configuration and deployment tracking, while analytics are derived from existing application records. The database also retains historical donor-opportunity and response-state records from the retired non-response standby feature; these records no longer impose unanswered-opportunity restrictions. Post-donation eligibility windows and unresolved donation-report holds remain separate active checks.

**Editorial scope:** these paragraphs describe the verified implemented application. The count and diagram limitations belong in the figure caption or surrounding discussion after the actual submitted ERD is confirmed. The manuscript deliberately does not claim that every logical association is an FK, that all supporting tables appear in the candidate figure, or that the database meets an unverified normal form/security guarantee.

## 7. Evidence, separate from the manuscript

### Live schema evidence

The inspection used a direct connection to the configured target and a read-only transaction. It queried server/schema metadata and migration **filenames only**, not personal records. The metadata snapshot was retained locally at `backend/storage/db-review/erd-basis-schema-20261022.json` (git-ignored), containing server identity, tables/engines, column definitions, key usage, indexes, migration names and copy-specific Designer table names. It contains no application row contents or credentials.

Read-only query forms used:

```sql
SELECT DATABASE(), @@port, VERSION(), @@version_comment, @@default_storage_engine;

SELECT TABLE_NAME, TABLE_TYPE, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'bloodmatch'
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'bloodmatch'
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION,
       REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'bloodmatch'
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'bloodmatch'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT name FROM bloodmatch.schema_migrations ORDER BY name;
```

Equivalent metadata queries inspected the copy and test schemas. The saved-copy diagram inventory came from a read-only join of `phpmyadmin.pma__pdf_pages` and `phpmyadmin.pma__table_coords`, filtered to `bloodmatcherd (13)`. Main trigger definitions were inspected through `information_schema.TRIGGERS`; no trigger was executed by this review.

### File and code evidence

| Finding | Relevant source |
| --- | --- |
| Configured connection and target distinction | [Database.php](../backend/src/Config/Database.php), [Env.php](../backend/src/Config/Env.php); only non-secret target fields were reported from configuration |
| Registration staging, email verification and document metadata transfer | [PendingRegistrationService.php](../backend/src/Services/PendingRegistrationService.php), [RegisterController.php](../backend/src/Controllers/Auth/RegisterController.php), [018 migration](../database/migrations/018_email_verification.sql) |
| Admin verification decisions and Member role rules | [VerificationService.php](../backend/src/Services/VerificationService.php), [024 migration](../database/migrations/024_consolidate_staff_role.sql); live `users.role` ENUM is `member/admin` |
| Donor eligibility, age/consent and donation windows | [DonorEligibilityService.php](../backend/src/Services/DonorEligibilityService.php), [AgeEligibilityService.php](../backend/src/Services/AgeEligibilityService.php), [SystemSettingsService.php](../backend/src/Services/SystemSettingsService.php) |
| Current compatibility source and consolidated-storage selection | [BloodCompatibilityService.php](../backend/src/Services/BloodCompatibilityService.php), [DonationStorageRepository.php](../backend/src/Repositories/DonationStorageRepository.php); 032 ledger entry selects consolidated storage |
| Donation claims, requester response, review, proof and correction | [DonationCompletionService.php](../backend/src/Services/DonationCompletionService.php), [DonationCompletionPolicy.php](../backend/src/Services/DonationCompletionPolicy.php), [DonationProofService.php](../backend/src/Services/DonationProofService.php), [DonationCorrectionService.php](../backend/src/Services/DonationCorrectionService.php) |
| Composite identity, credit consolidation and source-table retirement | [032 migration](../database/migrations/032_consolidate_donation_storage.sql), [033 migration](../database/migrations/033_immutable_donation_context.sql), [035 migration](../database/migrations/035_retire_donation_sources.sql), checked against current columns/keys/table absence |
| Chat consent, messaging, report evidence and session presence | [ChatConsentService.php](../backend/src/Services/ChatConsentService.php), [ChatService.php](../backend/src/Services/ChatService.php), [ChatReportsController.php](../backend/src/Controllers/ChatReportsController.php), [PresenceService.php](../backend/src/Services/PresenceService.php) |
| Temporary restriction field and enforcement | [036 migration](../database/migrations/036_chat_report_temporary_disable.sql), [AuthMiddleware.php](../backend/src/Middleware/AuthMiddleware.php), [AuthService.php](../backend/src/Services/AuthService.php), plus chat report decision code |
| Email accounting and application-only donor association | [EmailAttemptBudget.php](../backend/src/Services/EmailAttemptBudget.php), [EmailWorker.php](../backend/src/Services/EmailWorker.php), [021 email migration](../database/migrations/021_email_outbox_accounting.sql), [023 migration](../database/migrations/023_email_donor_reservations.sql) |
| Inactive non-response functionality and historical storage distinction | [NonResponseStandbyService.php](../backend/src/Services/NonResponseStandbyService.php), [ProfileController.php](../backend/src/Controllers/ProfileController.php), [AuthService.php](../backend/src/Services/AuthService.php), [application safety runner](../database/run_application_safety.php), [retirement note](research/NON_RESPONSE_STANDBY_RETIREMENT.md); live tables remain present |
| Analytics without a separate analytics entity | [AnalyticsRepository.php](../backend/src/Repositories/AnalyticsRepository.php), [AnalyticsController.php](../backend/src/Controllers/Analytics/AnalyticsController.php) |
| Closure and retention functions | [AccountDeletionController.php](../backend/src/Controllers/AccountDeletionController.php), [RetentionService.php](../backend/src/Services/RetentionService.php), [026 migration](../database/migrations/026_audit_retention.sql) |
| Removed password-reset storage | [034 migration](../database/migrations/034_remove_password_resets.sql), main migration ledger and live table absence; test-only presence separately checked |
| Historical diagrams and copy-specific changes | [application ERD snapshot](erd_application.md), [test ERD snapshot](erd.md), [copy-change record](ERD_COPY_EMAIL_RELATIONSHIP.md), and current copy schema/Designer metadata |

File definitions establish intended behavior; live metadata establishes this document's actual physical inventory and constraints. Older audit counts, stale migration README status notes, proposal documents and test-only objects do not override the verified application schema.
