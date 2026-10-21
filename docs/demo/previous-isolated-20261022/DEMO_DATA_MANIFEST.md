# BloodMatch demonstration data manifest

Prepared 22 October 2026 (Asia/Manila). Batch: **DEMO_20261022_7C91**. This is demonstration preparation and API/service evidence, not browser acceptance.

## Database and isolation

- Application source: **127.0.0.1:3306 / bloodmatch**, host Boss-Mark, MariaDB 10.4.32. The normal connection resolved to 3306 even though the application supports port fallback. Preparation uses an explicit source connection; the demo HTTP router rejects any database/port other than the isolated target.
- Demo target: **127.0.0.1:3306 / bloodmatch_demo_20261022_7c91**. Existing table definitions, constraints and triggers were copied, with reference data and five allowlisted account rows. No schema migrations, validation changes, clock changes or production feature changes were made.
- The four existing member examples are established by tests/phase5.ps1. Other pre-existing Gmail/member accounts and volunteer accounts were excluded from the demo copy. The existing designated Admin row is copied unchanged for authorized local access; its credentials were not replaced.
- UI: http://127.0.0.1:5174 . Backend: http://127.0.0.1:8011 . Mail inbox: http://127.0.0.1:8026 . All listeners bind to loopback. Use 127.0.0.1 for demo; the original localhost session and the demo use separate PHP session storage.
- All recurring workers are disabled for this demo process. SMTP is captured on 127.0.0.1:1026. OTP uses the existing Mailer. One membership email was processed by an explicitly scoped worker with a custom local capture transport; the production TLS SMTP transport was not exercised. No global worker was run.
- Current configured restrictions: **42 hours after donation**, **90-day donation cooldown**, independent unresolved-report holds. **Non-response standby is retired**. Report reminder/escalation uses original claim time at **24 / 48 hours**, without silent confirmation/rejection.

## Accounts

| ID | Email | Demo label | Membership | Account | Blood | Chapter |
| --- | --- | --- | --- | --- | --- | --- |
| 3 | markseejr@gmail.com | Existing Admin; unchanged isolated copy | verified | active | B+ | 1 |
| 69 | p5rej346578869@test.local | Rejected resubmission | rejected | active | — | 1 |
| 85 | p5other189351979@test.local | Main requester | verified | active | O+ | 2 |
| 89 | p5rejflow189351979@test.local | Pending live approval | pending | active | — | 1 |
| 104 | p5rejflow918167087@test.local | Pending live rejection | pending | active | — | 1 |
| 156 | demo_20261022_7c91.near@example.com | near | verified | active | O+ | 2 |
| 157 | demo_20261022_7c91.far@example.com | far | verified | active | O+ | 1 |
| 158 | demo_20261022_7c91.unavailable@example.com | unavailable | verified | active | O+ | 1 |
| 159 | demo_20261022_7c91.incompatible@example.com | incompatible | verified | active | AB+ | 1 |
| 160 | demo_20261022_7c91.disposable@example.com | disposable | verified | active | O+ | 1 |
| 161 | demo_20261022_7c91.report-ready@example.com | report-ready | verified | active | O+ | 1 |
| 162 | demo_20261022_7c91.mutual@example.com | mutual | verified | active | O+ | 1 |
| 163 | demo_20261022_7c91.uncertain@example.com | uncertain | verified | active | O+ | 1 |
| 164 | demo_20261022_7c91.clarification@example.com | clarification | verified | active | O+ | 1 |
| 165 | demo_20261022_7c91.correction@example.com | correction | verified | active | O+ | 1 |
| 166 | demo_20261022_7c91.hold-rejected@example.com | hold-rejected | verified | active | O+ | 1 |
| 167 | demo_20261022_7c91.reminder24@example.com | reminder24 | verified | active | O+ | 1 |
| 168 | demo_20261022_7c91.escalation48@example.com | escalation48 | verified | active | O+ | 1 |
| 169 | demo_20261022_7c91.cooldown@example.com | cooldown | verified | active | O+ | 1 |
| 170 | demo_20261022_7c91.registration-check@example.com | Registration API example | pending | active | — | 1 |
| 171 | demo_20261022_7c91.withdraw-only@example.com | withdraw-only | verified | active | O+ | 1 |
| 172 | demo_20261022_7c91.accept-only@example.com | accept-only | verified | active | O+ | 1 |

Use the existing account passwords. Matching saved credentials were verified locally without changing hashes. Existing access is in ignored private storage at backend/storage/maintenance/DEMO_20261022_7C91/existing-access.json; additional synthetic access is in synthetic-credentials.jsonl in the same directory. These private files contain secrets and must not be committed, printed during the presentation or included in the paper. This manifest intentionally contains no credentials or tokens.

The additional accounts separate live matching, withdrawal, acceptance, report states and disposable closure. The fresh-registration API-check account is intentionally membership-pending. For a fresh live signup use **demo_20261022_7c91.fresh-live@example.com**, which has not been registered. Its mail will be captured locally. Account 160 is reserved for live deactivation/deletion and has no donation reports; its donor enrollment was removed in the isolated copy.

## Requests and screens

All requests belong to existing example requester **85**. Open each path on the demo UI origin.

| ID | Scenario | Urgency | Chapter | Starting status | Closure reason | Screen |
| --- | --- | --- | --- | --- | --- | --- |
| 105 | main-live | routine | 1 | OPEN |  | /requests/105/matches |
| 106 | editable | urgent | 2 | OPEN |  | /requests/106/matches |
| 107 | cancel-live | emergency | 3 | OPEN |  | /requests/107/matches |
| 108 | canceled | routine | 1 | CANCELLED |  | /requests/108/matches |
| 109 | closed | urgent | 2 | CANCELLED | need_met | /requests/109/matches |
| 110 | expired-renewable | routine | 3 | EXPIRED |  | /requests/110/matches |
| 111 | expiry-live | routine | 3 | OPEN |  | /requests/111/matches |
| 112 | response-withdrawal | routine | 1 | OPEN |  | /requests/112/matches |
| 113 | requester-accept-live | routine | 1 | OPEN |  | /requests/113/matches |
| 114 | report-ready | routine | 1 | OPEN |  | /requests/114/matches |
| 115 | mutual | routine | 2 | OPEN |  | /requests/115/matches |
| 116 | uncertain | routine | 1 | OPEN |  | /requests/116/matches |
| 117 | clarification | routine | 1 | OPEN |  | /requests/117/matches |
| 118 | correction | routine | 1 | OPEN |  | /requests/118/matches |
| 119 | hold-rejected | routine | 1 | OPEN |  | /requests/119/matches |
| 120 | reminder24 | routine | 1 | OPEN |  | /requests/120/matches |
| 121 | escalation48 | routine | 1 | OPEN |  | /requests/121/matches |
| 122 | cooldown | routine | 1 | OPEN |  | /requests/122/matches |
| 123 | Demand API check (canceled) | emergency | 2 | CANCELLED |  | /requests/123/matches |
| 124 | expired-static | routine | 1 | EXPIRED |  | /requests/124/matches |
| 125 | close-live | routine | 1 | OPEN |  | /requests/125/matches |

Main **105** remains OPEN, with no donation reports. Leave it open after the matching/response segment. Use request **114** for the later live report segment. Request **107** is reserved for cancellation and the two-session demand change. Request **125** is separate live closure; **124** is a separately service-expired example, while **110** remains available for live renewal.

Venue: Bataan General Hospital and Medical Center, directory hospital 1 / locality 26, Balanga. The hospital's Manahan Street, Tenejero, Balanga address is supported by the [DOH directory](https://ntp.doh.gov.ph/resources/facilities/?yiiwp-page=40). Stored coordinates **14.683333, 120.533333** are the existing locality reference, not a surveyed entrance. Approximate distance demonstration is usable; entrance-level geolocation remains unverified.

Matching code orders known distance ascending, unknown distance last, same-chapter first only when distances tie, then donor ID ascending. Comparator tie/unknown checks passed. Near donor **156** is Chapter 2 (~0.81 km); farther donor **157** is Chapter 1 (~12.97 km). This demonstrates that chapter membership does not override proximity. Report-ready donor 161 is another suggested candidate; reserve that donor for request 114. Stored distance is rounded to two decimals; do not interpret it as travel distance. One small-fixture generation took 28.63 ms; this is not a load/performance acceptance claim.

## Responses, chat, donations and notifications

| Case | Donor | Request | Match | Context/conversation | Starting action |
| --- | --- | --- | --- | --- | --- |
| Live withdrawal | 171 | 112 | 559 | none | Withdraw live on /responses |
| Live acceptance | 172 | 113 | 560 | not accepted | Owner Accept & chat live |
| Live report | 161 | 114 | 549 | 6/17 | Accepted, no report/credit; report live |
| Chat consent pending | 157 | pair 156/157 | — | 27 | Recipient accepts live |
| Reported message | 158 | separate pair | — | 26 | Admin /admin/chat-reports: case 3 |


| Report | Scenario | Donor | Request | Starting state | Version | Credit units | Report hold |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 3 | mutual | 162 | 115 | CONFIRMED/completed | v1 | 1 | released |
| 4 | uncertain | 163 | 116 | PENDING/needs_review | v2 | 0 | held |
| 5 | clarification | 164 | 117 | PENDING/clarification | v1 | 0 | held |
| 6 | correction | 165 | 118 | CONFIRMED/completed | v1 | 1 | released |
| 7 | hold-rejected | 166 | 119 | REJECTED/rejected | v1 | 0 | held |
| 8 | reminder24 | 167 | 120 | PENDING/awaiting_requester | v1 | 0 | held |
| 9 | escalation48 | 168 | 121 | PENDING/needs_review | v1 | 0 | held |
| 10 | cooldown | 169 | 122 | CONFIRMED/completed | v1 | 1 | released |

Reports open from **/donation-reports** for participants or **/admin/confirmations** for Admin; select the listed ID. Report 4 has dummy proof evidence **2** on version 2 while its previous uncertainty remains explicitly acknowledged on version 1. This is deliberate: proof changes do not erase a dispute or imply renewed agreement. Clarification is report 5; completed-correction request is attached to report 6; rejected report 7 keeps its hold until an explicit justified no-donation release.

Report 8 has its 24-hour reminder. Report 9 has the reminder plus 48-hour escalation. First scoped pass returned reminded=2, escalated=1; repeat returned 0/0. Both remain unresolved. Notification screens: **/notifications**, bell flyout and the local capture inbox. The scoped follow-up never queries the genuine report pool.

Dummy documents **49 / 50 / 51** belong to members **69 / 89 / 104**. They use freshly encrypted, clearly marked fake raster images. All three decrypted successfully, and the source image was visually inspected. The same marked image was processed as optional proof through multipart HTTP. No real identification files were copied.

## Authorized release of working emails in the application database

You identified the following nine accounts for email reuse. Their original working emails are absent from users and pending_registrations. Only these test profiles were changed to deactivated and given reserved released-ID-demo addresses. Password hashes and all request, donation and chat history were preserved. This is a reversible email release, not destructive history deletion.

| Application ID | Freed email |
| --- | --- |
| 137 | fireshorts395@gmail.com |
| 138 | emeraldfae16@gmail.com |
| 139 | feyestipular@gmail.com |
| 140 | deleon.ak1010@gmail.com |
| 141 | ninamaemanzano5@gmail.com |
| 142 | marsee24@bpsu.edu.ph |
| 143 | jamessrusssel33@gmail.com |
| 144 | jrbremodo24@bpsu.edu.ph |
| 147 | solaralor14@gmail.com |


Affected application records: users **137, 138, 139, 140, 141, 142, 143, 144, 147**; changed columns email, account_status, deactivated_at, updated_at. Changed mail-job IDs: **23, 24, 30, 31, 32, 35, 39, 40, 44**. Exact before/after rows are in released-emails-backup.json. Created append-only demo.email.apply audit IDs: **2735, 2736, 2737, 2738, 2739, 2740, 2741, 2742, 2743**. No cleanup/restore was executed.

Safety checks: all business tables match their original fingerprint after accounting for the authorized release; all nine password hashes match their backup; all nine working emails are free; demo table definitions match the copied schema. The original open application session independently changed audit/presence rows while polling after deactivation. Those concurrent records were retained; a blanket whole-database unchanged claim would be false.

## Exhaustive record inventory and backups

The following lists every created or changed demo record relative to the copied baseline. Foreign-key references identify child history, notifications, evidence, outbox and audit records as part of this batch even where the schema has no batch column. Reference tables were copied unchanged; exact row inventories are in the private baseline and snapshot.

| Table | Created IDs / keys after copied baseline | Changed copied IDs (columns) |
| --- | --- | --- |
| audit_log | 2752, 2753, 2754, 2755, 2756, 2757, 2758, 2759, 2760, 2761, 2762, 2763, 2764, 2765, 2766, 2767, 2768, 2769, 2770, 2771, 2772, 2773, 2774, 2775, 2776, 2777, 2778, 2779, 2780, 2781, 2782, 2783, 2784, 2785, 2786, 2787, 2788, 2789, 2790, 2791, 2792, 2793, 2794, 2795, 2796, 2797, 2798, 2799, 2800, 2801, 2802, 2803, 2804, 2805, 2806, 2807, 2808, 2809, 2810, 2811, 2812, 2813, 2814, 2815, 2816, 2817, 2818, 2819, 2820, 2821, 2822, 2823, 2824, 2825, 2826, 2827, 2828, 2829, 2830, 2831, 2832, 2833, 2834, 2835, 2836, 2837, 2838, 2839, 2840, 2841, 2842, 2843, 2844, 2845, 2846, 2847, 2848, 2849, 2850, 2851, 2852 | — |
| auth_throttle | Exact keys retained in private final-inventory.json; 6 rows | — |
| blood_requests | 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125 | — |
| chat_report_evidence | 12 | — |
| chat_reports | 3 | — |
| chat_requests | 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28 | — |
| conversation_participants | 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52 | — |
| conversations | 17, 18, 19, 20, 21, 22, 23, 24, 25, 26 | — |
| donation_chat_contexts | 6, 7, 8, 9, 10, 11, 12, 13, 14 | — |
| donation_correction_requests | 1 | — |
| donation_report_events | 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26 | — |
| donation_report_evidence | 2 | — |
| donation_reports | 3, 4, 5, 6, 7, 8, 9, 10 | — |
| email_attempts | 25, 26 | — |
| email_outbox | 46, 47, 48, 49, 50, 51 | — |
| matches | 544, 545, 546, 547, 548, 549, 550, 551, 552, 553, 554, 555, 556, 557, 558, 559, 560, 561, 562, 563, 564, 565 | — |
| member_documents | 49, 50, 51 | — |
| messages | 128, 129, 130 | — |
| notifications | 1068, 1069, 1070, 1071, 1072, 1073, 1074, 1075, 1076, 1077, 1078, 1079, 1080, 1081, 1082, 1083, 1084, 1085, 1086, 1087, 1088, 1089, 1090, 1091, 1092, 1093, 1094, 1095, 1096, 1097, 1098, 1099, 1100, 1101, 1102, 1103, 1104, 1105, 1106, 1107, 1108, 1109, 1110, 1111, 1112, 1113, 1114, 1115, 1116, 1117, 1118, 1119, 1120, 1121, 1122, 1123, 1124, 1125, 1126, 1127, 1128, 1129, 1130, 1131, 1132, 1133, 1134, 1135, 1136, 1137, 1138, 1139, 1140, 1141, 1142 | — |
| users | 156, 157, 158, 159, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 172 | 69 (updated_at); 85 (date_of_birth, blood_type, blood_type_source, donor_enrolled_at, donor_availability, latitude, longitude, updated_at) |
| verification_decisions | 38 | — |

The complete local inventory is backend/storage/maintenance/DEMO_20261022_7C91/final-inventory.json. The exact restorable prepared database (DDL, rows excluding generated columns, triggers) is prepared.json; baseline.json records the initial copied state; source-account-backup.json backs up the original allowlisted examples and Admin row; released-emails-backup.json backs up the nine released application accounts and their mail jobs. These backups contain private data and remain gitignored. manifest.json, api-checks.json, demand-api-check.json and safety-checks.json preserve detailed verification evidence.

New encrypted dummy files are enumerated with table/record IDs and SHA-256 hashes in final-inventory.json. A rolled-back preparation attempt left three extra encrypted dummy files; their exact paths are retained under prior_attempt_files in manifest.json. They are unused and retained deliberately. No automatic file cleanup occurs. The source image is backend/storage/maintenance/DEMO_20261022_7C91/DEMO_ONLY.png. The demo-only document key lives outside the repository in the OS temporary directory; keep it while using the saved demo snapshot. No key contents are included here.

See [DEMO_WALKTHROUGH.md](DEMO_WALKTHROUGH.md), [DEMO_COMMANDS.md](DEMO_COMMANDS.md), and [DEMO_COVERAGE_CHECKLIST.md](DEMO_COVERAGE_CHECKLIST.md).
