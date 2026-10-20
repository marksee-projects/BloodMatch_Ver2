# SOP and Objectives Feature Audit

Prepared **20 October 2026 (Asia/Manila)** against revision `865ab18` and the local application schema. Companion: [updated flow audit](../BLOODMATCH_FLOW_SAFETY_AUDIT.md).

This document proposes a feature-grounded Statement of the Problem and Objectives of the Study. No existing SOP/objectives manuscript was located in the repository or supplied with the request. Original wording, numbering and the named software-quality instrument therefore cannot be verified. Development, testing and software-quality evaluation are retained explicitly, without claiming verbatim preservation. `[AUDIT FILE PATH]` was resolved from the IDE context to `docs/BLOODMATCH_FLOW_SAFETY_AUDIT.md`.

## 1. Changes since the previous audit, with evidence

The 8 October audit and its checkpoint notices were treated as historical claims. The detailed before/after table in [updated audit section 2](../BLOODMATCH_FLOW_SAFETY_AUDIT.md#2-changes-since-the-previous-audit) records the rechecks.

| Change | Current evidence and qualification |
| --- | --- |
| Registration and officer verification separated | E01 stages a member registration, then creates an email-verified pending account after OTP. E04 handles membership approval independently. E02/E03 restrict staff to the designated pre-existing admin. No officer self-registration or working ordinary promotion process exists. |
| Roles and operational pages changed | E02/E03/E18 show member/admin only, a global Admin Officer portal, user management and confirmations, plus redirects for old officer bookmarks. Fresh DB enum and aggregate counts agree. |
| Donor-response safety added | E05/E08–E10 establish live age/consent checks, confirmation dialogs, current-row locks, withdrawal guards, material-edit reconciliation and unfinished-offer closure. Availability mutation concerns remain. |
| Standby implemented separately | E14/022 implement fixed 42-hour unanswered-opportunity timing and explicit recovery. E05/E11 establish separate post-donation windows and report holds. Local settings are 42 hours and 90 days, both from the occurrence anchor. Recurring operation remains unverified. |
| Email transport changed | E15 provides a scoped outbox, retry/cancellation/accounting and emergency grouping. OTP is still synchronous. New donation/chat actions are in-app only; broad lifecycle email cannot be assumed. |
| Donation completion expanded | E10–E13 replace routine staff-only confirmation with accepted-offer coordination, donor reporting, exact-version requester response, mutual completion and exceptional staff review. Holds, clarification/revision, photos and completed-record correction responses are distinct features. |
| Chat/privacy lifecycle added | E16/E17/E20 add consent, private messages/read state/presence, bounded abuse evidence, independent staff case review, permanent closure and retention processing. |
| Analytics/report gaps changed | E18/E19 show deadline-aware demand, live donor statistics, matching trend fields, confirmation-date filtering, CSV and print/Save PDF. Calendar-period definitions, automatic map cadence and runtime acceptance remain open. |
| Schema consolidated; obsolete features removed | E21–E23 confirm migrations through 035, 34 tables, report-based credit/hold state, normalized compatibility_pairs, identity triggers and unique constraints. Password reset and three superseded source tables are absent. |

### Evidence register

Identifiers resolve to current source files and inspected behavior, not historical PASS claims. Paths are relative to this document.

| ID | Source and behavior |
| --- | --- |
| E01 | [AuthService](../../backend/src/Services/AuthService.php) register/login/publicUser; [PendingRegistrationService](../../backend/src/Services/PendingRegistrationService.php) stage/verify/resend; [EmailVerificationService](../../backend/src/Services/EmailVerificationService.php); [RegisterPage](../../frontend/src/pages/RegisterPage.jsx). Member payload, privacy, location/ID, OTP staging/expiry/resend. |
| E02 | [AuthMiddleware](../../backend/src/Middleware/AuthMiddleware.php), [CapabilityMatrix](../../backend/src/Services/CapabilityMatrix.php), [API routes](../../backend/routes/api.php), [App](../../frontend/src/App.jsx), [SessionIdentityPolicy](../../backend/src/Services/SessionIdentityPolicy.php). Endpoint roles, active accounts, password fingerprints and routes. |
| E03 | [AccountLifecycleService](../../backend/src/Services/AccountLifecycleService.php), [UserAdminController](../../backend/src/Controllers/Admin/UserAdminController.php), [027](../../database/migrations/027_single_administrator.sql), [historical seed](../../database/seed_demo_users.php), [rollout record](../CHAT_APPLICATION_ROLLOUT_RESULTS.md). Locked administrator assignment, chapter/account changes and retained designated admin. |
| E04 | [DocumentController](../../backend/src/Controllers/DocumentController.php), [DocumentStorageService](../../backend/src/Services/DocumentStorageService.php), [VerificationService](../../backend/src/Services/VerificationService.php), [MembershipVerificationController](../../backend/src/Controllers/Admin/MembershipVerificationController.php), [VerificationsView](../../frontend/src/pages/admin/views/VerificationsView.jsx). Private document review, decisions, provenance and resubmission. |
| E05 | [ProfileController](../../backend/src/Controllers/ProfileController.php), [DonorEligibilityService](../../backend/src/Services/DonorEligibilityService.php), [AgeEligibilityService](../../backend/src/Services/AgeEligibilityService.php), [UserRepository](../../backend/src/Repositories/UserRepository.php). Profile/enrollment/availability and live readiness gates. |
| E06 | [MemberProfileController](../../backend/src/Controllers/MemberProfileController.php), [ProfileViewPolicy](../../backend/src/Services/ProfileViewPolicy.php), [ProfilePictureController](../../backend/src/Controllers/ProfilePictureController.php), [LocationService](../../backend/src/Services/LocationService.php), [ProfilePage](../../frontend/src/pages/ProfilePage.jsx). Limited other-member DTO, own profile/picture/location. |
| E07 | [RequestsController](../../backend/src/Controllers/RequestsController.php), [RequestService](../../backend/src/Services/RequestService.php), [BloodRequestRepository](../../backend/src/Repositories/BloodRequestRepository.php), [RequestFormModal](../../frontend/src/components/RequestFormModal.jsx). Creation/validation, owner history, feed filters/access and saved-request matching failure status. |
| E08 | [BloodCompatibilityService](../../backend/src/Services/BloodCompatibilityService.php), [MatchService](../../backend/src/Services/MatchService.php), [MatchesPage](../../frontend/src/pages/MatchesPage.jsx), [HomeFeedPage](../../frontend/src/pages/HomeFeedPage.jsx). Substitutions, ranking, reconciliation and donor/requester DTOs. |
| E09 | [MatchResponseService](../../backend/src/Services/MatchResponseService.php), [WorkflowLockService](../../backend/src/Services/WorkflowLockService.php), [FeedCard](../../frontend/src/components/FeedCard.jsx), [MyResponsesPage](../../frontend/src/pages/MyResponsesPage.jsx). Locked response, idempotence, direct response, offer cycles, withdrawal and confirmation. |
| E10 | [RequestLifecycleService](../../backend/src/Services/RequestLifecycleService.php), [RequestActionsController](../../backend/src/Controllers/RequestActionsController.php), [MatchRepository](../../backend/src/Repositories/MatchRepository.php), [RequestHistoryActions](../../frontend/src/components/RequestHistoryActions.jsx), [safety runner](../../database/run_application_safety.php). Edit/cancel/expiry, reasoned closure, renewal, offer cleanup and follow-up. |
| E11 | [DonationCompletionService](../../backend/src/Services/DonationCompletionService.php), [DonationCompletionPolicy](../../backend/src/Services/DonationCompletionPolicy.php), [DonationCompletionController](../../backend/src/Controllers/DonationCompletionController.php), [DonationWorkflowRepository](../../backend/src/Repositories/DonationWorkflowRepository.php), [DonationStorageRepository](../../backend/src/Repositories/DonationStorageRepository.php), [DonationReportInbox](../../frontend/src/components/DonationReportInbox.jsx). Versioned report, acknowledgment, review, holds, history and fulfillment. |
| E12 | [OfferChatService](../../backend/src/Services/OfferChatService.php), [OfferAcceptancePanel](../../frontend/src/components/OfferAcceptancePanel.jsx), [DonationContextCard](../../frontend/src/components/DonationContextCard.jsx). Acceptance, consent reuse, immutable offer-cycle context and coordination/prompt/dispute. |
| E13 | [DonationProofService](../../backend/src/Services/DonationProofService.php), [DonationCorrectionService](../../backend/src/Services/DonationCorrectionService.php), [DonationProofController](../../backend/src/Controllers/DonationProofController.php), [DonationProofPanel](../../frontend/src/components/DonationProofPanel.jsx), [DonationDateTimeFields](../../frontend/src/components/DonationDateTimeFields.jsx). Evidence, versions, corrections and occurrence input. |
| E14 | [NonResponseStandbyService](../../backend/src/Services/NonResponseStandbyService.php) opportunity/processDue/acknowledge; [022](../../database/migrations/022_non_response_opportunities.sql); [DonationFollowupService](../../backend/src/Services/DonationFollowupService.php); [follow-up runner](../../database/run_application_donationfollowup.php). Timeout/recovery and 24h/48h processing. |
| E15 | [NotificationService](../../backend/src/Services/NotificationService.php), [EmailEnqueueService](../../backend/src/Services/EmailEnqueueService.php), [EmailWorker](../../backend/src/Services/EmailWorker.php), [EmailWorkerPolicy](../../backend/src/Services/EmailWorkerPolicy.php), [EmailAttemptBudget](../../backend/src/Services/EmailAttemptBudget.php), [EmailOutboxRepository](../../backend/src/Repositories/EmailOutboxRepository.php), [notification UI](../../frontend/src/pages/NotificationsPage.jsx), [delivery](../../frontend/src/services/notificationDelivery.js), [read state](../../frontend/src/services/notificationReadState.js), [deep links](../../frontend/src/services/notifications.js). Event channels, recovery, limits and UI. |
| E16 | [ChatConsentService](../../backend/src/Services/ChatConsentService.php), [ChatPolicy](../../backend/src/Services/ChatPolicy.php), [ChatService](../../backend/src/Services/ChatService.php), [ChatRepository](../../backend/src/Repositories/ChatRepository.php), [PresenceService](../../backend/src/Services/PresenceService.php), [ChatPage](../../frontend/src/pages/ChatPage.jsx), [SuggestedChatService](../../backend/src/Services/SuggestedChatService.php). Consent, participant gates, messages, pagination/read/unread, presence and suggestions. |
| E17 | [ChatReportsController](../../backend/src/Controllers/ChatReportsController.php), [ChatReportsView](../../frontend/src/pages/admin/views/ChatReportsView.jsx). Abuse reporting, selected/adjacent evidence and independent review/status/findings. |
| E18 | [AdminDashboardController](../../backend/src/Controllers/Admin/AdminDashboardController.php), [DemandMapController](../../backend/src/Controllers/Analytics/DemandMapController.php), [AnalyticsRepository](../../backend/src/Repositories/AnalyticsRepository.php), [DashboardView](../../frontend/src/pages/admin/views/DashboardView.jsx), [DemandMapWidget](../../frontend/src/components/DemandMapWidget.jsx). Metrics, live eligibility, deadline-aware demand and member chapter filtering. |
| E19 | [AnalyticsController](../../backend/src/Controllers/Analytics/AnalyticsController.php), [AnalyticsView](../../frontend/src/pages/admin/views/AnalyticsView.jsx), [AnalyticsPrintReport](../../frontend/src/components/AnalyticsPrintReport.jsx), [AggregateCsv](../../backend/src/Utils/AggregateCsv.php), [ConfirmedDonationSummary](../../backend/src/Utils/ConfirmedDonationSummary.php), [summary UI](../../frontend/src/components/ConfirmedDonationSummary.jsx). Date/chapter reporting, aggregate/confirmation CSV and print. |
| E20 | [AccountDeletionController](../../backend/src/Controllers/AccountDeletionController.php), [DeleteAccount](../../frontend/src/components/DeleteAccount.jsx), [RetentionService](../../backend/src/Services/RetentionService.php), [DocumentCipher](../../backend/src/Services/DocumentCipher.php), [retention runner](../../database/run_retention.php). Permanent closure, encryption modes and held-data retention. |
| E21 | [AuditLogger](../../backend/src/Services/AuditLogger.php), [AuditLogRepository](../../backend/src/Repositories/AuditLogRepository.php), [AuditLogAdminController](../../backend/src/Controllers/Admin/AuditLogAdminController.php), [AuditLogsView](../../frontend/src/pages/admin/views/AuditLogsView.jsx), [026](../../database/migrations/026_audit_retention.sql). Protected searchable logs, ordinary append-only protection, aged retention exception and best-effort writes. |
| E22 | [Migrations](../../database/migrations), particularly 024–035; [settings seeds](../../database/seeds/003_system_settings.sql), [SystemSettingsService](../../backend/src/Services/SystemSettingsService.php), [normalized compatibility seed](../../database/seeds/006_compatibility_pairs.sql). Roles, privacy, presence, completion/proof, constraints and source retirement. |
| E23 | Fresh 20 October SELECT-only inspection: bloodmatch:3306, 34 tables, both 021 files and migrations through 035 in ledger, enum member/admin, one active admin, settings 42h/90d, eight recipient types/27 pairs, relevant FKs/uniques/checks/triggers, zero report/donor identity mismatches. No fixtures or writes. Updated audit sections 1/6 state limits. |
| E24 | [Baseline](../../.agents/requirements/BloodMatch_System_Requirements_and_Features.md), [approved donation plan](../DONOR_REQUESTER_CONFIRMATION_PLAN.md), [ratified offer/proof plan](../MATCHING_TO_DONATION_REVISED_PLAN.md), [staff record](../STAFF_ROLE_CONSOLIDATION_CHECKPOINT.md), [safety checkpoint](../SAFETY_FOLLOWUP_CHECKPOINT.md), [worker setup](../LOCAL_WORKER_SETUP.md), [retirement evidence](../DONATION_SOURCE_RETIREMENT_035.md). Approval lineage/acceptance boundaries, not fresh test results. |

## 2. Feature coverage matrix

**I** = implemented source path; **P** = partial/conflicting/operationally incomplete; **M** = baseline-planned but missing; **U** = acceptance unverified. Qn and On refer to the same numbered question/objective below. Mapping establishes traceability for this identified inventory, not full implementation or exhaustive discovery. Removed/excluded behavior is separately listed in section 4.

| Feature | Actor | Status | Evidence | SOP question | Objective |
| --- | --- | --- | --- | --- | --- |
| F01 Member-only registration, chapter/DOB/credentials, optional blood/location/ID and privacy acknowledgment | Prospective member | I; optional location differs from baseline wording | E01, E22: 025 | Q1 | O1 |
| F02 Pending registration, OTP, ten-minute code, 24-hour staging, resend/throttling and expiry recovery | Prospective member | P: delivery/recurring cleanup unverified | E01, E20 | Q1 | O1 |
| F03 Login/logout, failure lockout, session regeneration and password-fingerprint invalidation | Member, Admin Officer | I; password reset removed | E01, E02 | Q2 | O2 |
| F04 Separate email/membership status; active/deactivated access and legacy email-unverified restrictions | All accounts | P: endpoint policies vary; capability browse conflict | E02, E05, E07 | Q2 | O2 |
| F05 Own identity/phone/DOB/blood/picture and canonical municipality/barangay updates; protected fields | Member, Admin Officer | I; self-chapter changes absent | E05, E06 | Q3 | O3 |
| F06 Limited other-member profile and online indicator; sensitive-field exclusion | Active account | I; viewer need not be membership-verified | E06, E16 | Q3 | O3 |
| F07 National ID/donor card/parental-consent upload with privacy acknowledgment, own listing and private viewing; verified National ID upload locked | Member, Admin Officer | P: encryption/storage acceptance unverified | E04, E20 | Q4 | O4 |
| F08 Global verification queue/detail, National-ID-based approval, reasoned rejection, decision history and no self-review | Admin Officer | I | E04 | Q4 | O4 |
| F09 Donor-card administrative blood provenance and supplemental-consent inspection | Admin Officer | I; not medical verification | E04 | Q4 | O4 |
| F10 Rejected-member replacement ID/resubmission, pending reset and status/decision alerts | Member, Admin Officer | I | E04, E15 | Q5 | O5 |
| F11 Verified-member donor enrollment, DOB checks, under-16 restriction and consent for ages 16–17 | Member | I; enrollment differs from new-offer email gate | E05 | Q6 | O6 |
| F12 Available/unavailable control, live readiness reasons and staff donation prohibition | Member; staff read-only | P: availability mutation lacks full guards and repeats unlocked write | E05, E09 | Q7 | O7 |
| F13 Reconciliation after relevant profile/document/verification/enrollment/availability changes across bounded request batches | System | P: per-request failures logged; no universal automatic repair guarantee | E04, E05, E08 | Q7 | O7 |
| F14 Routine/urgent/emergency request creation, quantity/facility/hospital/location/deadline validation and throttling | Email-verified pending/verified requester | I; initial save separate from matching | E07 | Q8 | O8 |
| F15 Pending-membership request marked pending_review | Pending requester | I label; no inferred separate approval workflow | E07 | Q8 | O8 |
| F16 Owner request/history/status; Donor offers/Suggested donors tabs, search/chapter/status filters and five-per-tab pagination; donor own-entry view and response tracking | Requester, donor, Admin Officer | I with restricted DTOs | E07–E09 | Q9 | O9 |
| F17 Owner edits, material-change generation/reconciliation, invalidated-offer alerts and preserved pending reports | Requester, system | I source; races not freshly tested | E08, E10 | Q10 | O10 |
| F18 Saved-request matching-failure notice, manual staff rematch and donor refresh without generation/email expansion | Requester, Admin Officer, system | P: retry explicit; automatic repair not guaranteed | E07, E08 | Q10 | O10 |
| F19 Owner/staff cancellation and owner need-met-elsewhere/no-longer-needed closure | Requester, Admin Officer | I; no donation credit | E10 | Q11 | O11 |
| F20 Terminal cleanup of unfinished offers; completed donations and pending reports survive | System, participants | I source/schema | E10, E11, E23 | Q11 | O11 |
| F21 Deadline-aware expiry, affected-party alerts and periodic catch-up processor | System | P: recurring execution unverified | E10, E14, E24 | Q12 | O12 |
| F22 Linked expired-request renewal, one replacement per old request; active-request deadline extension | Owner | I; old history preserved | E10, E22: 032 | Q12 | O12 |
| F23 ABO/Rh compatible substitutions and normalized ordered reference | System, authorized staff viewing reference | I; clinical screening outside scope | E08, E22, E23 | Q13 | O13 |
| F24 Sequential multi-tier biological cascading | System | P: compatible pool exists; sequential tiers absent | E08, E24 | Q13 | O13 |
| F25 Chapter/distance ranking; Home search/blood/urgency/municipality filters, paging and request-inspection restrictions | Member; staff browse | I for ranking/request filters | E07, E08 | Q13 | O13 |
| F26 Chapter/municipality candidate filters in baseline | Requester, Admin Officer | P: ranking policy conflicts with hard-exclusion wording | E08, E24 | Q13 | O13 |
| F27 Confirm-before-response in Home/Matches, eligible direct response, locked revalidation and repeated-response idempotence | Eligible donor | I source | E05, E09 | Q14 | O14 |
| F28 Withdrawal before pending report, participant alerts and later response/new offer cycle | Donor, requester | I; request must remain OPEN | E09, E12 | Q14 | O14 |
| F29 Requester accepts current donor offer; pair consent/conversation reuse and immutable offer context | Verified requester and donor | I; acceptance awards no credit | E12, E22: 031/033 | Q15 | O15 |
| F30 Still arranging/did not happen, donor dispute and requester reporting prompt with 24-hour prompt limit | Offer participants | I; signal alone awards/removes no credit | E12 | Q15 | O15 |
| F31 Ordinary chat request/accept/decline/cancel, seven-day pending expiry and one-day retry after decline/cancel | Active verified accounts | I; not limited to matched pairs | E16 | Q16 | O16 |
| F32 Participant-only messaging, accepted consent and active/verified rechecks; existing chat usable during donor holds/windows | Chat participants | I; no arbitrary staff thread inspection | E12, E16 | Q17 | O17 |
| F33 Suggested messages, paged/incremental history, read/unread state and UI delivery | Chat participants | I source; browser/timing acceptance unverified | E16 | Q17 | O17 |
| F34 Session heartbeat/logout presence and 90-second online threshold | Chat/profile viewers | I; presence is not donor readiness | E06, E16 | Q17 | O17 |
| F35 Reasoned report of 1–10 chat messages with bounded adjacent evidence | Chat participant | I | E17 | Q18 | O18 |
| F36 Staff case queue/filter/detail, audited evidence, under-review/resolved/dismissed findings and self-involvement exclusion | Admin Officer | P: sole-admin independent-review fallback absent | E17, E03 | Q18 | O18 |
| F37 Donor report after response, actual occurrence validation, optional note and closed-match historical reporting | Verified donor | I; distinct from new-offer eligibility | E11, E13 | Q19 | O19 |
| F38 Up to three optional encrypted photos, format/size/pixel validation, metadata stripping and protected audited viewing | Participants, independent staff | P: GD/keys/upload acceptance unverified | E13, E20 | Q19 | O19 |
| F39 Proof updates with versions and retained evidence/disputes | Report participants | I source | E11, E13 | Q19 | O19 |
| F40 Exact-version received/did-not-donate/not-sure response; no self-acknowledgment; mutual completion or review | Requester | I | E11 | Q20 | O20 |
| F41 Atomic credit/history/anchor/hold release and quantity fulfillment; one completed match is one tracking unit | System | I source/constraints; races unverified | E11, E22, E23 | Q20 | O20 |
| F42 Late completion after terminal request without reopening; overdue OPEN expires rather than fulfilling | Participants, system | I | E10, E11 | Q20 | O20 |
| F43 Independent admin confirm with basis/reject with reason/clarify; retained private event history | Admin Officer | P: mechanisms implemented; sole-reviewer fallback absent | E11, E03 | Q21 | O21 |
| F44 Participant clarification replies, donor revisions, rejected-report reopening and renewed acknowledgment | Participants, Admin Officer | I source | E11 | Q21 | O21 |
| F45 Report-specific hold through unresolved/disputed/rejected states, separate from standby/cooldown | Donor, system | I source/schema | E11, E23 | Q22 | O22 |
| F46 Explicit rejected-report no-donation hold release with basis; other restrictions retained; reopening restores own hold | Independent Admin Officer | I; not automatic on rejection | E11 | Q22 | O22 |
| F47 Completed-record correction request/history and reasoned staff response | Participants, Admin Officer | I for response only; reversal excluded | E13, E24 | Q23 | O23 |
| F48 Owner donation follow-up and once-per-request staff alert | Requester, Admin Officer | I source | E10 | Q24 | O24 |
| F49 One 24-hour reminder and 48-hour escalation from original report; catch-up, silence never decides | System, requester, staff | P: recurring processor operation unverified | E11, E14, E24 | Q24 | O24 |
| F50 Opportunity persistence, notified+42h, current-generation/unanswered/open/unexpired checks and separate standby | System, donor | P: implemented; timeout exclusions/operations unresolved | E14, E15, E23 | Q25 | O25 |
| F51 Standby acknowledgment, consumed-due watermark, fresh-timer preservation, alerts/audit and reconciliation | Donor, system | I source; other gates remain | E05, E14 | Q25 | O25 |
| F52 Occurrence-based configurable post-donation 42h standby/90d cooldown, latest anchor and timed effective recovery | Donor, system | I source/local settings; no return alert/storage rewrite proven | E05, E11, E22, E23 | Q26 | O26 |
| F53 Match/verification/response/withdrawal/invalidation/terminal/account/standby and review in-app events | Relevant members, Admin Officer | P: generic profile/availability/recovery alerts incomplete | E04, E09–E15 | Q27 | O27 |
| F54 Chat-consent, abuse-case, offer/coordination, donation/proof/correction/hold/reminder destinations | Participants, Admin Officer | I source; browser destinations unverified | E11–E17 | Q27 | O27 |
| F55 Popup/flyout/list, unread counts, mark-one/all-read, deep links, deduplication and quiet opportunity refresh | Active accounts | I source; no exactly-once claim | E15 | Q27 | O27 |
| F56 Synchronous OTP; queued emergency creation-match and membership-decision email | Registrant, donor, reviewed member | P: narrow coverage versus baseline; inbox delivery unverified | E01, E15, E24 | Q28 | O28 |
| F57 Outbox/attempts, leases/retries/backoff, stale cancellation, emergency grouping, protected budgets and donor reservations | System/operator | P: recovery exists; active workers/transport unverified | E15, E22: both 021 and 023 | Q28 | O28 |
| F58 Controlled designated-admin maintenance, two-role access, locked promotions and old officer aliases | Maintainer, Admin Officer | P: transfer/replacement process absent | E02, E03, E22, E23 | Q29 | O29 |
| F59 User search/filter/paging, chapter assignment, deactivate/reactivate, self/closure safeguards and alerts | Admin Officer | I for permitted operations; no role assignment | E03, E18 | Q29 | O29 |
| F60 Global/chapter dashboard and request/donor/verification/fulfillment/live-readiness metrics | Admin Officer | I source; metric acceptance unverified | E18 | Q30 | O30 |
| F61 Privacy-safe active-demand chapter map and chapter/blood/urgency filters/refresh | Active member, Admin Officer | P: no periodic refresh cadence | E18 | Q30 | O30 |
| F62 Date/chapter request, fulfillment, blood/urgency, donor, verification and donation analytics/daily trend | Admin Officer | P: UTC rolling dates versus calendar-period interpretation | E18, E19 | Q31 | O31 |
| F63 Aggregate CSV, confirmation-date summary CSV and browser print/Save PDF | Admin Officer | I source; browser/PDF acceptance unverified | E19 | Q31 | O31 |
| F64 Protected activity/security logs, search/date/action/actor/target filters and ordinary update/delete protection | Admin Officer, system | P: best-effort writes; retention conflicts with permanent baseline | E21, E22: 026, E23 | Q32 | O32 |
| F65 Private documents/evidence/DTOs, consent, no-store and encryption/security controls | Authorized accounts, system | P: infrastructure/full encryption unverified | E02, E04, E06, E13, E20 | Q33 | O33 |
| F66 Password+DELETE permanent member closure, owned-request/pending-consent/mail cancellation, eligible-offer closure and no reactivation | Member | I source; reports retained; admin closure prohibited | E20, E03 | Q33 | O33 |
| F67 Expired-registration cleanup, profile erasure 30 days after closure, ID erasure 30 days after final decision, aged chat/case/request/log/proof cleanup and hold protection | Maintenance operator | P: scheduling/erasure/restore enforcement unverified | E20, E21, E22 | Q33 | O33 |
| F68 Chapter/system-configuration CRUD beyond reference lists/user chapter assignment | Admin Officer | M: baseline only; mutation routes/UI absent | E02 routes, E22 settings, E24 | Q34 | O34 |
| F69 Web-system development and integration | Research/development team | Planned study activity; no certification implied | E24; current architecture | Q35 | O35 |
| F70 Functional/integration/role/privacy/recovery/concurrency testing | Research/test team | U: no workflow tests executed in this audit | E02–E23; audit limits | Q36 | O36 |
| F71 Software-quality evaluation, mobile/security, <=3s matching and 99.5% uptime targets | Evaluators, members, Admin Officer | U: measurements/instrument absent | E24 nonfunctional requirements | Q37 | O37 |

## 3. Complete proposed SOP and objectives

These are study questions and intended objectives, not declarations that every feature is implemented or accepted. Q13, Q28, Q29, Q32 and Q34 require the scope decisions in section 4 before final manuscript approval. Matrix status qualifications remain part of the draft.

### Statement of the Problem

This study seeks to determine how BloodMatch can support blood-request coordination among DeMolay Bataan members through donor matching, member verification, communication and authorized monitoring. Specifically, it seeks to answer the following questions:

1. How can members register with their required account and chapter information, acknowledge the privacy notice, verify their email through an OTP, and recover from an expired or undelivered registration code?
2. How can the system support login and logout, failed-login lockout, session validation and invalidation after password replacement, account-status restrictions, and separate email verification from membership verification?
3. How can users manage their permitted profile, blood-type, picture and location information and view privacy-limited profiles of other members?
4. How can members submit and view protected National IDs, donor cards and parental consent within document privacy and upload restrictions, and how can the Admin Officer review them, approve or reject membership, record donor-card provenance and retain decision history?
5. How can rejected members replace their verification documents, resubmit for review and track the resulting status and decision?
6. How can verified members enroll as donors while the system checks age, parental consent and other enrollment prerequisites?
7. How can donors manage availability, understand eligibility restrictions and have blood type, location, age, consent, verification, enrollment and availability changes reflected in their donation opportunities?
8. How can eligible requesters create routine, urgent and emergency requests with validated quantity, facility, location and deadline information, including requests from members awaiting verification?
9. How can requesters track requests and inspect permitted Donor offers and Suggested donors through search, chapter and status filters and pagination, while donors track their own responses and participation history?
10. How can owners edit requests, reconcile affected matches and recover when matching fails through an authorized rematch process?
11. How can owners and authorized staff cancel requests, record owner closure reasons and close unfinished offers while preserving donation records and unresolved reports?
12. How can overdue requests expire, notify affected users and support deadline extension or linked renewal without losing earlier request history?
13. How can the system identify compatible substitute donors, provide an authorized compatibility reference, rank results by chapter and distance, support paged request search and blood, urgency and municipality filters, and address requirements for biological cascading and candidate filtering?
14. How can eligible donors confirm an offer to help, respond with or without a prior match notification, withdraw before a pending donation report and submit a later response safely?
15. How can requesters accept donor offers and participants coordinate through request-linked chat, record arrangement or no-donation updates and prompt reporting without treating coordination as donation completion?
16. How can verified users request, accept, decline or cancel chat consent and handle expired requests and retry restrictions?
17. How can consenting participants exchange private and suggested messages, browse history, track read, unread and online status, and retain authorized chat access during donor restrictions?
18. How can participants report problematic messages, and how can an independent Admin Officer review limited evidence, record findings and mark cases under review, resolved or dismissed?
19. How can donors report actual donation occurrence with an optional note and participants submit or update optional protected proof photos while preserving evidence versions and authorized access?
20. How can requesters acknowledge the current report version, distinguish agreement from dispute or uncertainty, and record completion, history and fulfillment without duplicating credit or reopening terminal requests?
21. How can an independent Admin Officer confirm, reject or seek clarification on exceptional reports, reopen rejected reports for clarification, and obtain participant replies and donor revisions for renewed acknowledgment?
22. How can report-specific holds restrict new donor activity, and how can staff release only the appropriate rejected-report hold after documenting that no donation occurred?
23. How can participants request correction of a completed donation record and receive a documented staff response while preserving its credit and restriction history?
24. How can requesters seek donation follow-up, and how can the system issue a 24-hour reminder and escalate an unacknowledged report after 48 hours without treating silence as a decision?
25. How can the system apply standby 42 hours after a committed in-app donation opportunity remains unanswered, exclude obsolete or resolved opportunities and support explicit acknowledgment without reapplying consumed timeouts?
26. How can the system apply configured post-donation standby and cooldown from actual occurrence, preserve a later existing restriction and restore effective eligibility when all applicable conditions permit?
27. How can the system deliver relevant in-app member, request, donor, chat and donation notifications, provide appropriate destinations and read controls, and limit duplicate or stale alerts?
28. How can the system deliver OTP and approved match and verification email alerts and manage queued retries, grouping, cancellation, accounting and sending limits?
29. How can controlled Admin Officer provisioning and authorization be distinguished from member registration, and how can permitted member search, chapter assignment, deactivation and reactivation be managed with self-action and permanent-closure safeguards?
30. How can the Admin Officer monitor requests, verification, donor readiness and fulfillment while active users view privacy-safe chapter demand aggregates and filters?
31. How can the Admin Officer generate date- and chapter-based activity and donation reports, download authorized CSV summaries and print aggregate reports or save them as PDF?
32. How can important activities and security events be recorded and reviewed through protected searchable audit logs with date, action, actor and target filters while addressing audit-write failures and the conflict between permanent records and approved retention?
33. How can the system protect private data, support password-confirmed permanent member closure and apply documented retention and erasure rules while preserving unresolved claims and investigations?
34. What chapter-maintenance and system-configuration functions, beyond reference lists and member chapter assignment, are required to satisfy the approved administrative scope?
35. How can BloodMatch be developed and integrated as a web-based blood-donor coordination platform for DeMolay Bataan?
36. How can functional, integration, authorization, privacy, restriction, recovery and concurrent-action testing establish whether BloodMatch meets its approved requirements?
37. How can BloodMatch be evaluated for software quality, including functional suitability, usability, security, reliability, performance and mobile responsiveness, against the study's approved evaluation criteria and documented service targets?

### Objectives of the Study

The general objective is to develop and evaluate BloodMatch as a web-based platform for coordinating blood requests and compatible donors among DeMolay Bataan members. Specifically, the study aims to:

1. Provide member registration with required account and chapter information, privacy acknowledgment, OTP email verification, code resend and recovery from expired or undelivered registration codes.
2. Provide secure login and logout, failed-login lockout, session validation and invalidation after password replacement; enforce account-status restrictions; and distinguish email verification from membership verification.
3. Enable users to update permitted profile, blood-type, picture and location information and view privacy-limited profiles of other members.
4. Enable members to submit and view protected National IDs, donor cards and parental consent within document privacy and upload restrictions, and the Admin Officer to review them, approve or reject membership, record donor-card provenance and retain decision history.
5. Enable rejected members to replace verification documents, resubmit for review and track their updated status and decision.
6. Enable verified members to enroll as donors subject to age, parental-consent and other enrollment prerequisites.
7. Enable donors to manage availability and view eligibility restrictions, and reconcile donation opportunities after blood type, location, age, consent, verification, enrollment or availability changes.
8. Enable eligible requesters to create routine, urgent and emergency requests with validated quantity, facility, location and deadline information, including pending-review status for requests from members awaiting verification.
9. Provide requester access to request status, Donor offers and Suggested donors with search, chapter and status filters and pagination, and donor access to their own responses and participation history.
10. Support owner request edits, affected-match reconciliation, clear matching-failure notices and authorized staff rematching.
11. Support owner and authorized staff cancellation, reasoned owner closure and unfinished-offer cleanup while preserving completed donations and unresolved reports.
12. Provide deadline-based expiry, affected-user notifications, permitted deadline extension and linked renewal that retains the original request history.
13. Provide compatible donor substitutions, an authorized compatibility reference, chapter-and-distance ranking, paged request search and blood, urgency and municipality filters, and any additional biological cascading or candidate filtering confirmed through requirements reconciliation.
14. Provide confirmation before offering help, eligible direct or notified responses, withdrawal before a pending report and safe later responses with current eligibility checks.
15. Enable requester acceptance of donor offers and request-linked chat coordination, arrangement and no-donation updates, and reporting prompts without awarding credit for coordination actions.
16. Provide chat-consent requests, acceptance, decline, cancellation, expiry handling and retry restrictions for verified users.
17. Provide private messaging, suggested messages, history browsing, read and unread tracking, and session-based online status for consenting participants, retaining authorized chat access during donor restrictions.
18. Enable participant message reports and independent Admin Officer review of limited evidence, with recorded findings and under-review, resolved or dismissed case outcomes.
19. Enable donor reporting of actual occurrence with an optional note and participant submission or updating of optional protected proof photos, with evidence versions and authorized access.
20. Provide exact-version requester acknowledgment, distinguish agreement from dispute or uncertainty, and record completion, history and eligible fulfillment atomically while preventing duplicate credit and terminal-request reopening.
21. Enable independent Admin Officer confirmation, reasoned rejection, clarification and reopening of rejected reports for clarification, with participant replies and donor revisions requiring renewed acknowledgment.
22. Maintain report-specific holds on new donor activity and provide explicit, reasoned release of only the rejected report's hold after staff establish that no donation occurred.
23. Provide completed-record correction requests and documented staff responses while retaining existing credit and restriction history.
24. Provide requester-initiated follow-up, one reminder after 24 hours and escalation after 48 hours without acknowledgment, with no automatic decision based on silence.
25. Track qualifying unanswered opportunities from their committed in-app notifications, apply non-response standby at 42 hours, exclude obsolete or resolved opportunities and provide explicit acknowledgment that retires consumed timeouts while retaining fresh timers and other restrictions.
26. Apply configured post-donation standby and cooldown from actual occurrence, preserve later restriction anchors and restore effective eligibility only when all applicable conditions permit.
27. Provide relevant in-app member, request, donor, chat and donation notifications, appropriate destinations, unread and read controls, and duplicate and stale-alert handling.
28. Provide OTP and approved match and verification email alerts, with queued retries, grouping, cancellation, accounting and enforced sending limits.
29. Document and enforce controlled Admin Officer provisioning separately from member registration, and provide permitted member search, chapter assignment, deactivation and reactivation with self-action and permanent-closure safeguards.
30. Provide operational monitoring of requests, verification, donor readiness and fulfillment for the Admin Officer, together with privacy-safe chapter demand aggregates and filters for active users.
31. Provide date- and chapter-based activity and donation reporting, authorized aggregate and confirmation-summary CSV downloads, and aggregate printing or browser PDF saving.
32. Provide protected activity and security audit recording, search and date/action/actor/target filters; assess audit-write failures; and reconcile permanent-record requirements with approved retention rules.
33. Protect private data, provide password-confirmed permanent member closure and apply documented retention and erasure rules while preserving unresolved claims and investigations.
34. Define and, if retained in the approved scope, provide chapter-maintenance and system-configuration functions beyond reference listing and member chapter assignment.
35. Develop and integrate BloodMatch as a web-based blood-donor coordination platform for DeMolay Bataan.
36. Conduct functional, integration, authorization, privacy, restriction, recovery and concurrent-action tests against approved requirements and record defects and results.
37. Evaluate BloodMatch using the study's approved software-quality criteria, including functional suitability, usability, security, reliability, performance and mobile responsiveness, and assess the documented three-second matching and 99.5% uptime targets through appropriate evidence.

The platform coordinates participation; administrative verification, pair acknowledgment and donation credit do not establish clinical eligibility or medical confirmation. The baseline excludes collecting, storing, transporting or medically processing blood.

## 4. Unresolved questions and unmapped features

All 71 rows in the identified inventory are assigned to Q1–Q37/O1–O37. This is a traceable draft, **not a claim of complete project coverage or compliance**: the current manuscript is missing, some baseline functions lack implementation, requirements conflict, and runtime acceptance remains open. Mapping neither resolves a conflict nor authorizes a planned feature.

| Open item | What is verifiable now | What still needs resolution |
| --- | --- | --- |
| Original manuscript and evaluation instrument | No current SOP/objectives found; development/tests/quality evaluation retained as O35–O37 | Supply the current text/path and approved standard/instrument to verify preservation and final wording. No ISO edition was invented. |
| Staff provisioning/transfer | Registration fixes member; controlled historical promotion/conversion and retained admin evidenced; live enum/count/triggers agree | Confirm replacement/transfer process and independent reviewer for cases involving the sole admin. No public officer signup exists. |
| Three-role baseline versus later approvals | Current code/schema support two stored roles and designated global staff | Formally reconcile baseline chapter-officer and role-management wording. O29 documents controls, not an implemented transfer feature. |
| Cascading/locality | Substitutes, ordered reference data and ranking exist; Home filters request cards | Decide whether biological tiers and candidate filters remain required and reconcile with no-hard-exclusion policy. O13 is conditional for extra disputed behavior. |
| Availability mutation/timeout exclusions | Availability writes twice, including outside lock; timeout processor lacks full readiness recheck | Confirm exemptions for unavailable/unverified/age-ineligible donors and plan guard/race work separately. No code edits made. |
| Email scope | OTP synchronous; queued emergency creation matches and membership decisions; named new events in-app only | Reconcile baseline broad email wording with approved narrow coverage. O28 does not promise routine/urgent, response, chat or donation email. |
| Timers/automation | Non-response 42h in code, local post-donation 42h/90d settings, report follow-up 24h/48h | Establish recurring safety/follow-up/retention/mail operation and flags. Task discovery was unavailable; old checkpoints prove neither current enabled nor disabled state. |
| Reporting/map freshness | UTC Today/last 7/30/365 dates, custom ranges, confirmation-date summary and manual/filter refresh | Confirm calendar periods and measurable map cadence. Keep reporting-date, occurrence-date and completion-date semantics explicit. |
| Retention/permanent audit | Ordinary append-only protection plus aged-delete exception and holds exist | Reconcile permanent baseline with one-year retention; define backup/quarantine and restore-time deletion enforcement. |
| Chapter/configuration CRUD | Reference lists, member chapter assignment and settings exist; mutation routes/UI absent | Confirm whether missing CRUD remains approved under single-admin scope. O34 is planned scope, not completed functionality. |
| Security/quality acceptance | Source controls and selected constraints verified; no full workflow tests run here | Verify configured encryption/files/infrastructure, mobile/browser behavior, races/recovery, actual delivery, performance, uptime and scores. Historical tests are not fresh passes. |

### Deliberately unmapped retired or excluded behavior

These have no separate functional objective because they are not active features in the reviewed approved implementation. Revisit if the missing manuscript still promises them.

| Former or excluded behavior | Disposition/evidence | Mapping consequence |
| --- | --- | --- |
| Public Admin Officer registration or ordinary promotion | No public staff signup established; E03/027 lock role mutation | Not an implemented user workflow; provisioning restrictions map to Q29/O29 |
| Separate chapter-officer authority/portal | Retired by 024 and canonical aliases in E02 | No separate officer-registration or chapter-only review objective |
| Password-reset recovery | No reset route/UI; 034 removed password_resets | Unmapped removed feature; future recovery needs an approved process |
| Automatic completed-credit reversal | Outside revised plan; correction response retains credit/fulfillment/windows (E13/E24) | Unmapped excluded action; correction request/response maps to Q23/O23 |
| Per-member chat blocking | Outside revised scope (E24); no block API | Unmapped excluded action; consent/report alternatives map to Q16/O16 and Q18/O18 |
| Separate donation_credits, donation_report_holds and compatibility_matrix storage | Retired by 035; authoritative consolidated equivalents verified | No lost capability inferred; credit/hold/compatibility functions remain mapped |

No identified **active functional row** lacks a pair. This is limited to the reviewed inventory; the missing manuscript and unresolved planned/conflicting requirements prevent blanket complete-coverage claims.

### Verification record

- Fresh source comparison and SELECT-only schema/settings/constraint checks completed on 20 October 2026. No DML, DDL, cleanup, migration, HTTP workflow, worker, schedule activation or SMTP action was performed.
- Task discovery yielded no usable inventory; it cannot establish presence, absence or operational health.
- Documentation validation checks numbering/order, matrix references, evidence identifiers and local link targets. Application tests were not rerun for documentation-only changes.
- Only `docs/BLOODMATCH_FLOW_SAFETY_AUDIT.md` and this file were changed by this review.
