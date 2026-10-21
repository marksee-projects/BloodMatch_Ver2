# Demo coverage checklist

22 October 2026. Ready means **data is staged for the live demonstration with the stated service/API checks**. It does not mean browser acceptance passed. Numbers map to the eleven scenario groups in the preparation request; older manuscript proposals in the repository use different numbering and are not silently substituted.

| Objective / scenario group | Status | Checks actually performed and limits |
| --- | --- | --- |
| 1 Accounts | Ready | Existing pending 89/104 and rejected 69; fresh registration + captured OTP + login tested through HTTP. Live fresh signup remains unused. |
| 2 Admin verification | Ready | Dummy documents encrypted/decrypted; rejection service created decision history; Admin credentials unchanged and staff chat evidence API readable. Live pending decisions retained. |
| 3 Donor participation | Ready | Available, unavailable, incompatible, post-donation, cooldown and rejected-report hold fixtures present; window/hold services checked. |
| 4 Requests | Ready | Routine/Urgent/Emergency, edit/cancel/close, canceled/closed, separate expired/renewable records. Scoped expiry service and HTTP create/cancel exercised. Edit/renew/close UI remains live. |
| 5 Matching | Partial | Compatible near/far fixtures, current ranking/ties checked; small generation 28.63 ms. Hospital reference is locality-level; exact entrance and browser ordering unverified. |
| 6 Responses | Ready | Dedicated active offers for withdrawal and acceptance; accepted report-ready offer has zero report/credit. Response/acceptance services exercised. |
| 7 Chat | Ready | Pending consent plus accepted request context, messages/read state; separate selected-message report created through APIs and readable by Admin. |
| 8 Donations | Ready | Independent report-ready, mutual, uncertain, clarification, correction, rejected-hold and cooldown fixtures. Multipart dummy proof persisted; outsider proof request denied. |
| 9 Notifications | Ready | OTP and membership-decision email captured locally; scoped 24h/48h processor checked, retry 0/0, unresolved status preserved. Production SMTP not tested. |
| 10 Demand refresh | Partial | Source interval 30000 ms, API create/cancel aggregate 0→1→0 verified. Request 107 remains for second-session 1→0. Mounted automatic refresh not browser-tested. |
| 11 Reports/privacy | Partial | Admin analytics/CSV endpoints 200; member analytics/staff queue 403; anonymous analytics 401; private proof denial. Browser CSV download, print and disposable closure remain live/unverified. |

## Browser blocker

Computer-use browser creation failed and inventory returned no connected browsers or apps. No rendered UI behavior passed this session. The frontend and local capture HTTP servers respond, but this is not evidence of React rendering, navigation, button behavior, downloads, print layout or automatic mounted polling. Manual live steps are in the walkthrough.

## Safety and repeatability

- Explicit application source and isolated demo database verified before writes.
- Existing fixture origins and exact account allowlist checked. User separately identified nine working emails for reversible release.
- No password replacement, Admin mutation, real donation edits, schema changes, global workers or external email sends.
- No demo orphan foreign-key references found; foreign keys enabled; response ≤ acceptance ≤ occurrence ≤ claim chronology check found zero violations.
- Schema-copy comparison passed for every demo table; source business-table normalization passed, with concurrent original-app audit/presence drift recorded separately.
- Re-running preparation and API preparation commands skipped already-recorded fixtures without duplicates. PHP/Node syntax checks passed.
- Cleanup/restore commands were prepared and reviewed from source; **neither was executed**, as requested. Database restore needs the retained dummy files and demo key. Original-email restore refuses reused addresses or intervening profile/mail changes.
