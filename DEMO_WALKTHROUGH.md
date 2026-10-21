# BloodMatch system demonstration walkthrough

Use **http://127.0.0.1:5174** throughout. The application at localhost remains a separate database/session. Open **http://127.0.0.1:8026** for captured mail. All numbers below map to the eleven scenario groups in the preparation request; the repository's older research proposals have different objective numbering.

The data and API checks are prepared. **No browser acceptance passed:** the computer-use inventory had no connected browser/app. Check each screen before presenting. Existing passwords are unchanged; local access files are identified in [DEMO_DATA_MANIFEST.md](DEMO_DATA_MANIFEST.md). Do not show those files during the demonstration.

Use separate browser profiles/private sessions for requester, donor and Admin. Tabs in one browser profile share the same login. Keep the demand-view session visible and online for periodic refresh. The fake image for live uploads is `backend/storage/maintenance/DEMO_20261022_7C91/DEMO_ONLY.png`.

## Objective 1 — Accounts and fresh registration

1. Open `/register`. Use the unused `demo_20261022_7c91.fresh-live@example.com`, synthetic personal details, an adult date of birth and a chapter. Choose a password yourself and acknowledge the displayed privacy notice. Upload only the marked dummy image if the form requests identification.
2. Open the local mail inbox, refresh it, open the new verification message and use its code promptly. Codes expire after ten minutes; resend is subject to the application's normal limits.
3. Complete verification and login. Email verification should be complete while membership remains pending. No message should arrive in a genuine external mailbox.
4. Show existing pending members **89 / 104** and rejected member **69** through their own profile/status screens. The previously API-registered account is separate from the unused fresh-live address.

Expected: staged signup, locally captured verification and distinct email/membership status. **Live:** the fresh-live signup and its first upload. Already checked: HTTP registration, local SMTP capture, OTP verification and subsequent login for a separate synthetic account.

## Objective 2 — Admin verification and chapter assignment

1. Sign in as the existing Admin in the isolated UI. Open `/admin/verifications` and inspect member **89**, document **50**. Explain the visible DEMO watermark.
2. Approve **89** live. Use member **104**, document **51**, for a separate reasoned live rejection. Keep these two decisions independent.
3. Open `/admin/users`, locate the newly approved example and demonstrate chapter assignment. Reopen the profile to check the chapter and verification state.
4. Open member **69** and show its recorded reason/decision history. In a separate member session, replace the fake identification and resubmit. It should return to pending, not automatically verified.

Expected: private documents available only to authorized viewers; decisions/history and chapter assignment recorded. **Live:** approval, rejection, chapter change and resubmission. Already prepared: protected dummy documents and a service-created rejection decision; document decryption passed. Rendered document viewing and the buttons remain browser-unverified.

## Objective 3 — Donor participation and distinct restrictions

1. Open `/profile` as **near (156)** or **far (157)**. Show verified membership, donor enrollment, blood type and availability.
2. Compare **unavailable (158)**, **incompatible AB+ (159)**, **mutual (162)**, **cooldown (169)** and **hold-rejected (166)** using separate logins.
3. Explain that the mutual donor is within the configured **42-hour post-donation window**. The cooldown donor's occurrence is ten days old, outside that first window but inside the **90-day cooldown**.
4. Show rejected report **7** for donor **166**. Its report hold remains active; rejection alone did not restore eligibility.

Expected: unavailable, incompatible, post-donation, cooldown and unresolved-report restrictions remain distinct. **Do not demonstrate non-response standby:** it is retired. Ready data/eligibility checks do not establish clinical eligibility.

## Objective 4 — Request lifecycle

1. Sign in as requester **85**. Open `/profile` and request **105** (`/requests/105/matches`), the main Routine request.
2. Use **106** to demonstrate an edit and its resulting match reconciliation. It is a separate Urgent request.
3. Keep Emergency **107** open until Objective 10. Show historical canceled **108** and reasoned closed **109**.
4. Use the separately listed **close-live** request in the manifest to demonstrate owner closure; do not close 105.
5. Show **expired-static** for expiration history. Use expired **110** for linked renewal with a future deadline; the old expired request should remain in history.

Expected: validated facility/deadline/quantity, separate state histories, renewal linked to its expired original. **Live:** edit, reasoned closure and renewal. Already checked: HTTP request creation/cancellation and `expireOne` for one exact demo request. The future expiry fixture is not evidence that a scheduled worker is installed; no global expiry worker ran.

## Objective 5 — Compatibility and proximity ranking

1. Keep main request **105** open. In its Suggested donors view, locate **156** and **157**. Request blood is O+; both are O+.
2. Explain approximate straight-line reference distances: **156**, Chapter 2, about **0.81 km**; **157**, Chapter 1, about **12.97 km**. The nearer different-chapter donor ranks ahead of the farther same-chapter donor.
3. Explain current tie-breaking: known distance first, ascending distance, same chapter for equal distance, then ascending donor ID. The comparator's tie/unknown-distance checks passed; this is not sequential biological tier execution.
4. Show that unavailable/incompatible/held/cooldown accounts cannot make eligible new offers. Do not select report-ready donor 161 for the main workflow.

The hospital is the correct Balanga facility in the existing directory. Its stored point is a locality reference; do not describe it as an exact entrance or travel distance. **Live:** visual ordering and filters. A small generation measured 28.63 ms; no broad load target was proven.

## Objective 6 — Donor offers, withdrawal and acceptance

1. As **156**, offer help on main **105** after reading the confirmation. As requester **85**, view the new Donor offer. Leave this request open for later inspection.
2. As the dedicated **withdraw-only** donor, open `/responses` and request **112**. Withdraw its prepared active offer. It must not withdraw the main donor's offer.
3. As requester **85**, open request **113** and accept the prepared **accept-only** donor offer. Open the resulting request-linked chat.
4. Show that acceptance alone records **zero donation credit** and does not fulfill the request. Continue to use the separate report-ready request for the donation segment.

Expected: explicit offers/withdrawal, current eligibility, consented coordination and no premature donation credit. **Live:** main response, dedicated withdrawal and acceptance. Response/acceptance services were exercised while staging independent examples.

## Objective 7 — Chat consent, coordination and reported messages

1. As **far (157)**, open `/chat` or its message-request area. Accept the prepared consent request from **near (156)**. This grants chat, not a donor offer or donation credit.
2. As requester **85**, open the conversation with **report-ready (161)**, request **114**, context **6**, conversation **17**. Show the prepared coordination messages and request context.
3. Send a synthetic coordination message and compare unread/read status in the other session. The stored example has requester-read and donor-unread states.
4. As Admin, open `/admin/chat-reports`, select **case 3**, and inspect bounded selected-message evidence for separate conversation **26**. Record a reasoned finding live if desired.

Expected: consent gating, participant-only messages, request context and limited staff evidence access. Already checked through HTTP: separate consent acceptance, message send, read update, message-report creation and Admin evidence read. Actual browser delivery/read indicators remain untested.

## Objective 8 — Donation reports, confirmation and holds

1. As **report-ready (161)**, open `/responses` or the request-linked report action for request **114**, match **549**. Acceptance already exists; no report has been submitted.
2. Submit a clearly synthetic report, with an actual occurrence at or after the recorded acceptance and no later than now. For this fixture, use a time during the live demo after acceptance. Upload only `DEMO_ONLY.png` as optional proof. Do not use a future time, backdate before the response, or claim a genuine donation.
3. As requester **85**, open `/donation-reports`, review that exact current version and choose Donation received. Expected: one unit of credit; request 114 has quantity two, so one credit alone does not fulfill it. Demonstrate its new donor restrictions separately from main 105.
4. Show prepared reports: **3** mutually confirmed; **4** uncertain with proof version 2 and older uncertainty on version 1; **5** clarification; **6** completed with a correction request; **7** rejected with hold retained.
5. As Admin, use the separate report 5 for clarification/revision. A donor revision requires renewed acknowledgment. For report 7, explain that only a justified explicit no-donation conclusion can release that report's hold. A correction request does not reverse credit.

**Live:** report creation, exact-version agreement, optional review/clarification and no-donation release. Already staged through services: separate state transitions and credit/holds. Multipart HTTP proof persisted, private listing succeeded and an outsider proof request returned 403. The proof upload's original response was not captured as a usable JSON envelope by the check script, so only its persisted evidence/version and access checks are claimed; inspect the live upload UI before presenting.

## Objective 9 — Notifications, captured email and 24/48-hour follow-up

1. Open `/notifications` for requester **85**, the relevant donors and Admin. Follow a request, chat or donation notification to its authorized destination.
2. Open the local mail inbox and show the captured signup code and membership-decision email. No global email worker or external SMTP delivery was used.
3. Show report **8**: original claim older than 24 hours, one reminder, still unresolved. Show report **9**: original claim older than 48 hours, reminder plus staff escalation, still unresolved.
4. Explain that silence never confirms/rejects a donation, and that these timers are unrelated to retired non-response standby. A repeated scoped processing pass produced no duplicate actions.

**Live:** notification navigation/read controls. The historical fixtures use explicit business timestamps; audit entry timestamps reflect when reconstruction was recorded, not an assertion that a live workflow occurred days ago. Queued membership delivery used the real bounded worker with a custom loopback transport; production TLS delivery remains unverified.

## Objective 10 — Demand and automatic refresh in a second session

1. Keep session A visible on the Home demand view or `/admin/demand-map` as an authorized user. Select **Chapter 3 / Meridian Heights** and **Emergency**. Its initial open-request count should be **1** (request 107).
2. In separate requester session B, cancel **107**. Keep main 105 unchanged.
3. Return focus to session A. A successful focus refresh may update it immediately; otherwise keep it visible/online for the next **30-second** poll. Expected count **1 → 0**, units **2 → 0**. The chapter/urgency filters should remain selected.
4. If it does not update, record the visible error and use the existing Refresh control. Do not claim automatic refresh passed from an API count alone.

Source interval is exactly **30000 ms**, disabled while hidden/offline or after access denial. Focus/reconnect also refreshes. API evidence on a separate canceled request showed **0 → 1 → 0**. **Live/unverified:** the mounted browser refresh and second-session rendering.

## Objective 11 — Reports, privacy, audit and disposable closure

1. As Admin, open `/admin/analytics`. Use a date range covering **1–22 October 2026**, then compare chapter filters. Current and ten-day-old synthetic donation history support different date scopes; request records cover all three chapters.
2. Export aggregate CSV and confirmation-summary CSV, inspect their actual contents, then open print/Save as PDF. These browser interactions/layouts have not been verified this session.
3. Open `/admin/audit-logs`, filter by request/report IDs from the manifest or actions such as `offer.accepted`, `donation.*` and `demo.*`. Private proof/note content should not appear in general audit metadata.
4. As a normal member, attempt protected report/admin URLs and another participant's proof. Already checked: anonymous analytics 401; member analytics/staff chat reports 403; outsider proof 403. Verify corresponding browser behavior live.
5. Reserve **disposable account 160** for Admin deactivation or the password-confirmed permanent deletion workflow. It has no donation reports. Do this last; do not delete requester 85, Admin 3 or any report-state donor.

Expected: authorized reports and exports, scoped/private access and controlled closure. **Live:** CSV download, print preview, rendered access denial and disposable closure. No cleanup, restore or permanent account deletion has been executed automatically.

Reset commands and limitations are in [DEMO_COMMANDS.md](DEMO_COMMANDS.md). The concise readiness/verification table is in [DEMO_COVERAGE_CHECKLIST.md](DEMO_COVERAGE_CHECKLIST.md).
