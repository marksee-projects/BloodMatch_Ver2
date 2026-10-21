# MAIN database demo walkthrough

Use **bloodmatch**, with the local capture interface **http://127.0.0.1:5174** and inbox **http://127.0.0.1:8026**. These ports now connect to the main database. Use separate browser profiles/private sessions for requester, donor and Admin. Existing passwords remain unchanged; private access files are identified in the manifest.

The eleven numbers below follow the user's eleven demo scenario groups. No UI behavior passed browser testing. Imported fixture states are not proof that the corresponding main user workflow works. Keep main request **115** open; it currently has zero reports.

## 1 — Accounts

Use /register with the unused fresh-live address above and synthetic details. Show captured OTP at port 8026 and keep email verification distinct from membership. Existing pending members 89/104 and rejected 69 are reused. Fresh main signup remains live; earlier successful signup checks occurred on the isolated source.

## 2 — Admin verification

Use /admin/verifications. Open dummy documents 51 and 52; approve 89 and reject 104 with a DEMO reason live. Use /admin/users for chapter assignment. Replace/resubmit member 69's dummy document in its own session. Admin credentials are unchanged. Admin viewing of the main dummy PNG passed through HTTP; decisions remain live.

## 3 — Participation

Compare near 167, unavailable 169, incompatible AB+ 170, recent mutual 173, cooldown 180 and rejected-report hold 177. Show /profile and /donation-reports. Keep the 42-hour post-donation window, 90-day cooldown and report hold distinct. Non-response standby is retired.

## 4 — Requests

Show main Routine 115, Urgent 116 and Emergency 117. Show canceled 118, closed 119, expired 134 and renewable 120. Use 135 for live reasoned closure. Main-database broad create/edit/renew/rematch has not been run; use only scoped CLI creation/editing in the commands file while genuine members share the database.

## 5 — Matching

Open /requests/115/matches as requester 85. Show near 167 ahead of farther 168 despite the latter's same-chapter status. Explain distance→chapter tie→ID ordering and locality-level coordinate limits. Main matching data read passed via HTTP; browser ordering/performance remains unverified.

## 6 — Responses

As near 167, offer help on main 115 live. Use dedicated donor 182 to withdraw on request 122. As requester 85, accept dedicated donor 183 on 123. Acceptance must not create credit. Keep main open and use the separate report-ready case for completion.

## 7 — Chat

As far 168, accept pending chat consent 36 from near. Open requester 85/report-ready donor 172, conversation 25, to show request-linked coordination and messages/read state. Admin opens /admin/chat-reports case 3; bounded evidence is separate from the live coordination pair. Main staff evidence read passed; browser delivery/read indicators remain live.

## 8 — Donations

As donor 172, report request 124, match 564, after a valid occurrence at/after acceptance and no later than now. Use only backend/storage/maintenance/DEMO_MAIN_20261022_B42E/DEMO_ONLY.png for optional proof. Requester 85 acknowledges the exact version. Quantity is two, so one credited unit leaves it open. Separately show report states in the manifest: mutual, uncertainty, clarification, correction and retained rejected hold. No new main donation was completed during migration.

## 9 — Notifications

Use /notifications for confirmed example recipients. The imported 24/48-hour fixtures remain unresolved; silence never decides them. Show the loopback transport smoke message in the capture inbox. Live signup mail uses capture through port 5174. Do not run a global worker; previous 2-reminder/1-escalation and 0/0 retry processing evidence is from the isolated source, not a newly executed main worker.

## 10 — Demand refresh

Keep session A visible on Home demand or /admin/demand-map, Chapter 3/Emergency. Measured main baseline is 2. In separate requester session B, cancel 117. Expected 2→1 on a successful focus refresh or next 30-second poll. Concurrent genuine activity can also change the count. Browser automatic refresh remains unverified.

## 11 — Reports/privacy

As Admin use /admin/analytics with 1–22 October 2026 and chapter filters, CSV and print; genuine main history is included alongside marked demo history. Main analytics/nonempty CSV returned 200. Anonymous/member analytics returned 401/403 and outsider proof returned 403. Use /admin/audit-logs for marked batch/IDs. Reserve disposable 171 for final deactivation/deletion; do not delete requester 85 or report donors. Browser CSV download, print and closure remain untested.

Only clearly marked dummy files may be uploaded. No cleanup/restore runs automatically. Use [DEMO_COMMANDS.md](DEMO_COMMANDS.md) for scoped main-database recovery.
