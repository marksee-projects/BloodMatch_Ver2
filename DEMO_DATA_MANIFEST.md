# BloodMatch MAIN database demo manifest

**Current target: bloodmatch on 127.0.0.1:3306.** Host Boss-Mark. Batch **DEMO_MAIN_20261022_B42E**, prepared 22 October 2026 (Asia/Manila). This replaces the earlier isolated-database plan at your explicit request. The old isolated database and backups remain intact; neither cleanup nor restore was executed.

## Access and safety

The records are in the database used by the normal BloodMatch application and phpMyAdmin. The local capture interface **http://127.0.0.1:5174** now also uses **bloodmatch**, not the separate demo database. Its backend is http://127.0.0.1:8011 and mail inbox http://127.0.0.1:8026. Use this interface for dummy documents, protected proof and captured signup mail; the normal server's SMTP/key configuration was not edited.

Only confirmed existing examples **69, 85, 89, 104** were reused. Existing requester 85 received demo donor/profile fields after backup; its password, role, email and original history were preserved. Original Admin **3** was not changed. Seventeen independent synthetic accounts were added. The earlier nine user-confirmed working-email releases remain in place, with their original backup/restore script; those addresses were not reoccupied by this transfer.

The transfer used an explicit main connection, a private full pre-transfer backup and one transaction. Constraints stayed enabled. Primary IDs, FK IDs, snapshot references and logical chat evidence IDs were remapped. No schema change or global matching, follow-up or email worker ran. Existing business rows were checked unchanged except for backed-up demo fields on example 85. **71 imported notifications target only confirmed example accounts; zero new email jobs were imported.** Genuine donations, genuine member profiles and Admin credentials were preserved.

Main HTTP checks passed for example login, requester matching read, Admin dummy PNG decryption, main reported-message evidence, analytics/nonempty CSV and denied outsider proof access. No browser UI behavior was tested. Imported response/donation/timer histories are fixtures; previous isolated service/API results are historical evidence, not proof of a main-database live workflow.

## Accounts

| Main ID | Email | Label | Membership | Blood | Chapter |
| --- | --- | --- | --- | --- | --- |
| 3 | markseejr@gmail.com | Existing Admin; unchanged isolated copy | verified | B+ | 1 |
| 69 | p5rej346578869@test.local | Rejected resubmission | rejected | — | 1 |
| 85 | p5other189351979@test.local | Main requester | verified | O+ | 2 |
| 89 | p5rejflow189351979@test.local | Pending live approval | pending | — | 1 |
| 104 | p5rejflow918167087@test.local | Pending live rejection | pending | — | 1 |
| 167 | demo_main_20261022_b42e.near@example.com | near | verified | O+ | 2 |
| 168 | demo_main_20261022_b42e.far@example.com | far | verified | O+ | 1 |
| 169 | demo_main_20261022_b42e.unavailable@example.com | unavailable | verified | O+ | 1 |
| 170 | demo_main_20261022_b42e.incompatible@example.com | incompatible | verified | AB+ | 1 |
| 171 | demo_main_20261022_b42e.disposable@example.com | disposable | verified | O+ | 1 |
| 172 | demo_main_20261022_b42e.report-ready@example.com | report-ready | verified | O+ | 1 |
| 173 | demo_main_20261022_b42e.mutual@example.com | mutual | verified | O+ | 1 |
| 174 | demo_main_20261022_b42e.uncertain@example.com | uncertain | verified | O+ | 1 |
| 175 | demo_main_20261022_b42e.clarification@example.com | clarification | verified | O+ | 1 |
| 176 | demo_main_20261022_b42e.correction@example.com | correction | verified | O+ | 1 |
| 177 | demo_main_20261022_b42e.hold-rejected@example.com | hold-rejected | verified | O+ | 1 |
| 178 | demo_main_20261022_b42e.reminder24@example.com | reminder24 | verified | O+ | 1 |
| 179 | demo_main_20261022_b42e.escalation48@example.com | escalation48 | verified | O+ | 1 |
| 180 | demo_main_20261022_b42e.cooldown@example.com | cooldown | verified | O+ | 1 |
| 181 | demo_main_20261022_b42e.registration-check@example.com | Registration check | pending | — | 1 |
| 182 | demo_main_20261022_b42e.withdraw-only@example.com | withdraw-only | verified | O+ | 1 |
| 183 | demo_main_20261022_b42e.accept-only@example.com | accept-only | verified | O+ | 1 |

Private access files: backend/storage/maintenance/DEMO_MAIN_20261022_B42E/existing-access.json and synthetic-access.json. A readable email/password handoff requested by the user is **backend/storage/maintenance/DEMO_MAIN_20261022_B42E/DEMO_LOGIN_ACCOUNTS.local.md**; it remains gitignored and excludes the Admin password. Existing credentials were checked against current hashes; no passwords were replaced. Fresh live signup address: **demo_main_20261022_b42e.fresh-live@example.com** (unused).

Regular Ito User (64) is **A−** and cannot donate to main O+ request 115. A separate **A+ demo request 136** was added for this account's compatible feed. Its profile, blood type, restrictions and password were not changed, and no match notification was sent to account 64. Main requester 85 owns 136; matching notifications were limited to confirmed synthetic donors. Exact visibility checks are recorded privately in visibility-check.json and regular-feed-api-check.json.

## Request starting states and exact screens

| Main request ID | Scenario | Urgency | Chapter | Starting status | Screen |
| --- | --- | --- | --- | --- | --- |
| 115 | main-live | routine | 1 | OPEN | /requests/115/matches |
| 116 | editable | urgent | 2 | OPEN | /requests/116/matches |
| 117 | cancel-live | emergency | 3 | OPEN | /requests/117/matches |
| 118 | canceled | routine | 1 | CANCELLED | /requests/118/matches |
| 119 | closed | urgent | 2 | CANCELLED | /requests/119/matches |
| 120 | expired-renewable | routine | 3 | EXPIRED | /requests/120/matches |
| 121 | expiry-live | routine | 3 | OPEN | /requests/121/matches |
| 122 | response-withdrawal | routine | 1 | OPEN | /requests/122/matches |
| 123 | requester-accept-live | routine | 1 | OPEN | /requests/123/matches |
| 124 | report-ready | routine | 1 | OPEN | /requests/124/matches |
| 125 | mutual | routine | 2 | OPEN | /requests/125/matches |
| 126 | uncertain | routine | 1 | OPEN | /requests/126/matches |
| 127 | clarification | routine | 1 | OPEN | /requests/127/matches |
| 128 | correction | routine | 1 | OPEN | /requests/128/matches |
| 129 | hold-rejected | routine | 1 | OPEN | /requests/129/matches |
| 130 | reminder24 | routine | 1 | OPEN | /requests/130/matches |
| 131 | escalation48 | routine | 1 | OPEN | /requests/131/matches |
| 132 | cooldown | routine | 1 | OPEN | /requests/132/matches |
| 133 | Canceled demand API check | emergency | 2 | CANCELLED | /requests/133/matches |
| 134 | expired-static | routine | 1 | EXPIRED | /requests/134/matches |
| 135 | close-live | routine | 1 | OPEN | /requests/135/matches |
| 136 | regular-account-compatible | urgent | 2 | OPEN | /requests/136/matches |

Main request **115** is OPEN with zero donation reports. Use **124** for the separate live donation report. Use **117** for live cancellation and demand refresh. Leave main 115 open. Donors for withdrawal, acceptance, cooldown, report review and disposable closure are separate.

## Responses, chat and donations

| Scenario | Donor ID | Request ID | Match ID | Context / conversation |
| --- | --- | --- | --- | --- |
| Main near donor | 167 | 115 | suggested | live response remains undone |
| Main farther donor | 168 | 115 | suggested | live response remains undone |
| Withdraw live | 182 | 122 | 574 | not accepted |
| Accept live | 183 | 123 | 575 | not accepted |
| Report live | 172 | 124 | 564 | 13 / 25 |

Pending pair-consent request **36** is between near/far donors. Reported-message review: **/admin/chat-reports**, case **3**, conversation **34**, message **133**. Member chat opens on **/chat**. Dummy National ID document IDs: **50, 51, 52** for members 69/89/104. They remain additional clearly marked dummy records; original example documents were not deleted.

| Main report ID | Scenario | Donor | Match | Starting state | Version | Credited units | Report hold |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 3 | mutual | 173 | 565 | CONFIRMED/completed | 1 | 1 | released |
| 4 | uncertain | 174 | 566 | PENDING/needs_review | 2 | 0 | held |
| 5 | clarification | 175 | 567 | PENDING/clarification | 1 | 0 | held |
| 6 | correction | 176 | 568 | CONFIRMED/completed | 1 | 1 | released |
| 7 | hold-rejected | 177 | 569 | REJECTED/rejected | 1 | 0 | held |
| 8 | reminder24 | 178 | 570 | PENDING/awaiting_requester | 1 | 0 | held |
| 9 | escalation48 | 179 | 571 | PENDING/needs_review | 1 | 0 | held |
| 10 | cooldown | 180 | 572 | CONFIRMED/completed | 1 | 1 | released |

Participants open **/donation-reports**; Admin opens **/admin/confirmations** and selects the listed report. Optional dummy proof evidence is **2**. Uncertain report remains version 2 with the older uncertainty explicitly tied to version 1. All synthetic donation notes contain DEMO_MAIN_20261022_B42E; the reused marked dummy image shows the original DEMO watermark and is never a real ID/proof.

Configured safety rules remain **42-hour post-donation restriction**, **90-day cooldown**, unresolved-report holds, request expiry and separate **24-hour reminder / 48-hour escalation**. Non-response standby is retired. The reminder/escalation timestamps were transferred unchanged; no global follow-up processing occurred on main records. Accepted offers have zero credit until a valid report is completed.

## Matching and demand

Near donor **167** is Chapter 2 (~0.81 km); farther **168** is Chapter 1 (~12.97 km). Ranking is distance, then same chapter for ties, then donor ID; unknown distance sorts last. The hospital is BGHMC in Balanga, hospital 1/locality 26. Its existing locality coordinates (14.683333,120.533333) are not a surveyed entrance. See the [DOH facility directory](https://ntp.doh.gov.ph/resources/facilities/?yiiwp-page=40) for the hospital address. Old 28.63 ms generation and comparator checks came from the isolated preparation; no main matching-speed acceptance claim is made.

The main database includes genuine history, so its demand count differs from the isolated demo. Actual HTTP baseline for Chapter 3/Emergency is **2**. Cancel demo request **117** in session B; session A should change **2 → 1** on its next successful refresh, unless other main users change requests concurrently. Source interval is 30000 ms while visible/online. Mounted polling remains browser-unverified.

General request create/material edit/renew/rematch can notify genuine donors in a shared database. The local demo helper blocks these broad routes; use the bounded CLI helper in DEMO_COMMANDS.md. Normal application feature code is unchanged. The prepared cancellation, closure, response, acceptance, chat and report cases remain independent and available for live actions.

## Exhaustive imported-record inventory and recovery

| Main table | Created main IDs |
| --- | --- |
| users | 167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 178, 179, 180, 181, 182, 183 |
| member_documents | 50, 51, 52 |
| verification_decisions | 38 |
| blood_requests | 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136 |
| matches | 559, 560, 561, 562, 563, 564, 565, 566, 567, 568, 569, 570, 571, 572, 573, 574, 575, 576, 577, 578, 579, 580, 581, 582, 583, 584, 585 |
| conversations | 25, 26, 27, 28, 29, 30, 31, 32, 33, 34 |
| messages | 131, 132, 133 |
| conversation_participants | 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68 |
| chat_requests | 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37 |
| donation_chat_contexts | 13, 14, 15, 16, 17, 18, 19, 20, 21 |
| donation_reports | 3, 4, 5, 6, 7, 8, 9, 10 |
| donation_report_evidence | 2 |
| donation_report_events | 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26 |
| donation_correction_requests | 1 |
| chat_reports | 3 |
| chat_report_evidence | 12 |
| notifications | 1059, 1060, 1061, 1062, 1063, 1064, 1065, 1066, 1067, 1068, 1069, 1070, 1071, 1072, 1073, 1074, 1075, 1076, 1077, 1078, 1079, 1080, 1081, 1082, 1083, 1084, 1085, 1086, 1087, 1088, 1089, 1090, 1091, 1092, 1093, 1094, 1095, 1096, 1097, 1098, 1099, 1100, 1101, 1102, 1103, 1104, 1105, 1106, 1107, 1108, 1109, 1110, 1111, 1112, 1113, 1114, 1115, 1116, 1117, 1118, 1119, 1120, 1121, 1122, 1123, 1124, 1125, 1126, 1127, 1128, 1129, 1130, 1131, 1132, 1133, 1134 |
| audit_log | 2785, 2786, 2787, 2788, 2789, 2790, 2791, 2792, 2793, 2794, 2795, 2796, 2797, 2798, 2799, 2800, 2801, 2802, 2803, 2804, 2805, 2806, 2807, 2808, 2809, 2810, 2811, 2812, 2813, 2814, 2815, 2816, 2817, 2818, 2819, 2820, 2821, 2822, 2823, 2824, 2825, 2826, 2827, 2828, 2829, 2830, 2831, 2832, 2833, 2834, 2835, 2836, 2837, 2838, 2839, 2840, 2841, 2842, 2843, 2844, 2845, 2846, 2847, 2848, 2849, 2850, 2851, 2852, 2853, 2854, 2855, 2856, 2857, 2858, 2859, 2860, 2861, 2862, 2863, 2864, 2865, 2866, 2867, 2868, 2869, 2870, 2871, 2872, 2873, 2874, 2875, 2876, 2877, 2878, 2879, 2880, 2881, 2882, 2883, 2884, 2885, 2886, 2918, 2919, 2920, 2921, 2922, 2923, 2924, 2925, 2926, 2927, 2928, 2929, 2930, 2931, 2932, 2933, 2934, 2935, 2936, 2937, 2938 |

Existing changed example **85**: date_of_birth, blood_type, blood_type_source, donor_enrolled_at, donor_availability, latitude, longitude. Every before/after field is in manifest.json; password/role/email are excluded from updates. Full original main schema/data/triggers backup: **backend/storage/maintenance/DEMO_MAIN_20261022_B42E/before.json**. Exact created row images: created-records.json. Source→main ID maps and scenario states: manifest.json. Main HTTP evidence: api-checks.json. Relational/chronology checks: checks.json. Local captured transport smoke message: mail-check.json. Private originals from the previous email release remain under backend/storage/maintenance/DEMO_20261022_7C91/.

The captured main email is an explicit loopback transport smoke test, not a claimed automated match/decision workflow. Main verification may append authentication/audit/accounting records; immutable audit/accounting records and dummy image files are retained by cleanup. No genuine original record is removed by the scoped cleanup command; it refuses intervening profile changes, modified fixtures or unrecorded FK links.

See [walkthrough](DEMO_WALKTHROUGH.md), [commands](DEMO_COMMANDS.md), and [coverage](DEMO_COVERAGE_CHECKLIST.md). Earlier isolated-only documents are archived under docs/demo/previous-isolated-20261022/.
