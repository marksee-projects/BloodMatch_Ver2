**Main database browser simulation — October 22, 2026 (Singapore/Philippines time)**

**Follow-up:** the missing-schema failures below were subsequently repaired with migrations 028, 030, and 031. All nine targeted browser retests passed, including requester acceptance, two-way chat, donation report access, and logout. See the [repair and retest record](MISSING_TABLES_REPAIR_2026-10-22.md). The original results below are retained as history; proof upload, full donation completion, and scheduler verification remain outstanding.

The requester/donor browser simulation found working account, matching-response, notification, and OTP flows, but the full donation/chat journey cannot complete against the current main database. Missing migrations cause reproducible HTTP 500/503 errors, including logout failure.

The real email tests passed SMTP acceptance for OTP, membership approval, and a two-request grouped emergency email. Gmail Inbox/Spam placement and human receipt remain unconfirmed.

**Environment and scope**

- Target: `bloodmatch`, `127.0.0.1:3306`, using the current working-tree application code.
- Browser: installed Google Chrome, automated through Playwright Core, with independent requester and donor browser contexts. Screenshots were captured from the rendered app, not API-only checks.
- Main synthetic requester: user **138**, `Browser Test Requester`.
- Main synthetic donor: user **139**, `Browser Test Donor`.
- Test requests: **59–63**, marked `BROWSER_MAIN_20261022` in their descriptions.
- A full pre-simulation SQL backup is retained locally at `scratch/browser-check/main-before-browser.sql`. Its SHA-256 and fixture identities are recorded in `scratch/browser-check/main-fixture.json`. That private file contains synthetic credentials and is ignored by Git.
- The earlier isolated-copy login exercise was excluded from these main-database findings after the user explicitly selected main.
- Only migration **036** was applied, in the separately approved preceding task. No additional migration was applied during this simulation.

**Browser results**

| Feature/action | Result | Evidence or practical limit |
|---|---|---|
| Requester and donor login | Passed | Both logged in through the real landing form using separate cookie contexts. |
| Independent sessions | Passed | Actions performed under distinct requester/donor identities. |
| Donor enrollment | Passed | Profile enrollment returned success and showed enrolled status. |
| Own profile and profile editing | Passed | Synthetic donor phone update persisted through the UI. |
| Empty request-form validation | Passed | Missing required blood type prevented advancing. |
| Request-form location and timing controls | Passed | Municipality selection and future timing completed through the UI during OTP gating. |
| Publishing with an unverified email | Passed | HTTP 403 prevented creation and displayed the email-verification dialog. |
| OTP resend | Passed | Real browser action generated a styled message captured by local SMTP. |
| Wrong OTP | Passed | Incorrect code rejected with HTTP 400. |
| Correct OTP | Passed | Actual generated code accepted; synthetic requester email verified. Code was consumed before its sample was sent to the approved inbox. |
| Compatible donor request view | Passed | Donor viewed a scoped A+ request with O+ compatibility and an enabled offer action. |
| Donor offer confirmation | Passed | Confirmation submitted successfully; requester saw the offer. |
| Offer withdrawal | Passed | Donor cancelled the offer through the confirmation UI. |
| Request cancellation | Passed | Requester cancelled request 59 through its post menu and alert-dialog confirmation. |
| Notification history and mark-read | Passed | Synthetic match notifications rendered and the read action worked. |
| Member access to admin dashboard | Passed for the UI route | Member did not receive an administrator dashboard. A full backend authorization audit was not performed. |
| Mobile profile | Passed for the tested viewport | No horizontal overflow at 390 × 844. This is not a complete device/accessibility audit. |
| Expired-post display | Passed for displayed status | Request 61 received a short synthetic deadline; browser history displayed it as expired and disabled new offers. |
| Persisted expiration transition | Not verified | The stored status was still `OPEN` before cleanup. The UI computes overdue status independently, and recurring safety processing stayed off. This test does not establish automatic persistence of `EXPIRED`. |
| Renewal with a past deadline | Passed | Browser rejected the past date with “Choose a future date and time.” No renewed post was submitted. |
| Viewing the other test member's profile | Failed | HTTP 500 from `/api/profile/139`; missing `user_presence` table is recorded in the application log. |
| Chat presence/workspace | Failed | Repeated HTTP 500 from `/api/chat/presence`. |
| Accept donor offer and open request chat | Blocked | `/api/requests/59/offers` and donor context returned HTTP 503. UI explicitly reported “Offer chat requires migration 031”; Accept & chat was disabled. |
| Donation report listing/submission | Blocked | `/api/donation-completion` returned HTTP 503 requiring migration 030. The accepted-offer prerequisite also could not complete. |
| Logout | Failed | Actual alert-dialog confirmation sent `/api/logout`, which returned HTTP 500. The application log traces it to `PresenceService::leave()` querying the absent `user_presence` table. |

Successful request publishing with ordinary broad matching was not exercised. To keep genuine members outside the simulation, the five marked requests and their matches were prepared with bounded fixture helpers; matching was restricted to synthetic donor 139. The ordinary offer, withdrawal, cancellation, profile, OTP, notification, and expiration-display actions were then exercised in the browser.

Two initial automation attempts used the wrong control roles: municipality suggestions render as list items rather than ARIA options, and standard confirmations use `alertdialog`, not `dialog`. Corrected selector reruns passed where indicated above. Those initial automation timeouts are not classified as product defects. Raw attempt history remains in `scratch/browser-check/main-results.json`.

**Real email results**

All real simulation messages were sent only to **markseejr@gmail.com**, labelled `[BloodMatch SIMULATION]`. These are additional controlled checks after the earlier three design samples.

| Email | Result | How it was tested |
|---|---|---|
| OTP | SMTP accepted, October 22 at 2:51:55 AM | Browser-triggered resend went to local capture. Wrong/correct code handling was verified in the UI, then the same captured HTML was sent to the approved inbox with an already-consumed-code notice. |
| Membership approval | SMTP accepted, 2:48:32 AM | Approval for the synthetic donor used the existing VerificationService with a clearly synthetic PDF document and valid admin actor. Outbox job **23** was processed by the real EmailWorker. This was a fixture-driven service check, not a browser test of the administrator review screen. |
| Grouped emergency matches | SMTP accepted, 2:54:24 AM | Real NotificationService enqueue and EmailWorker processing combined outbox jobs **24** and **25** into one transport call/message containing two request cards. |
| Duplicate run | Passed | Rerunning the worker at 2:55:23 AM claimed zero jobs and made zero transport calls. |

**Five-minute grouping timeline**

- **2:49:07 AM:** two fresh synthetic emergency email jobs were enqueued.
- **2:49:07 AM:** immediate worker run deferred both jobs until **2:54:07 AM**. Both retained `attempts=0`; there were no SMTP calls.
- **2:54:24 AM:** a later manual scoped worker run claimed both jobs and sent **one message**, with both jobs marked sent and `attempts=1`.
- **2:55:23 AM:** repeat run sent nothing, verifying no replay of those delivered jobs.

No timestamps were backdated to skip the grouping wait. Actual delivery was approximately five minutes seventeen seconds after enqueue because the scoped worker was invoked after the window had elapsed.

Queue transport was explicitly redirected to the approved test inbox while leaving test account email addresses unchanged. Eligibility, event validation, grouping, leases, attempt budgets, donor reservation, and final outbox transitions used the existing EmailWorker. This proves the worker path under that controlled transport; it does not verify Windows Task Scheduler, automatic worker activation, or receipt in a normal donor's inbox.

The browser OTP used local SMTP capture on port 1036; queued membership/grouped delivery used the configured real TLS SMTP account. All four recurring flags remained `0`, and `.env` was not edited.

All three message types were also rendered in Chrome at desktop and mobile widths. The grouped message displayed two cards, and none of the three tested email layouts overflowed horizontally at 390 pixels. This is not a Gmail-client rendering or deliverability certification.

**Priorities before another full journey**

1. Reconcile the main database with the intended application version. It began this run with **15 original users**, and its migration ledger lacks 028–035, whereas the supplied `bloodmatch-13.sql` contains substantially newer demo/workflow data. The simulation used the actual existing main database rather than replacing it with that dump.
2. Review and apply **028_user_presence.sql** with a backup. Missing presence schema currently breaks presence, other-member profiles, and logout.
3. Review **030_donation_completion_workflow.sql** and **031_offer_chat_donation_proof.sql**, including their historical report handling and prerequisites, before applying them. Their absence blocks the requester acceptance/reporting journey. Review 029 and later consolidation/retirement migrations separately; do not blindly run every pending migration.
4. Check document keys and GD before testing encrypted proof uploads. Current local readiness reported legacy document mode, no configured active encryption key, and GD unavailable. Existing files/keys from the other PC were not copied by this test.
5. Repeat requester acceptance, two-way chat, donation acknowledgment, completion, report holds, reminder/escalation, and proof access checks once prerequisites are ready. Staff analytics/downloads, admin review, erasure/retention, and real scheduler operation remain outside the verified two-member flow.

**Final state and preservation**

- All **15 original user rows** exactly matched their pre-simulation snapshot, including password hashes and verification/availability fields.
- Synthetic accounts **138 and 139** were deactivated and marked unavailable after the run; they remain as historical evidence.
- Synthetic requests **59–63** were all closed as `CANCELLED`. No test post remains an active blood need.
- Notifications, audit history, synthetic document metadata, email accounting, and sent-job records were retained. No original account was impersonated in a browser or had its password reset.
- The test used the existing admin actor only for the bounded synthetic verification service fixture; no admin browser acceptance is claimed.
- No recurring worker was activated, and no old email backlog was processed. Only jobs 23–25 were passed to the scoped email worker.

**Selected screenshots and logs**

- [Requester offer and missing-migration message](../scratch/browser-check/main-evidence/13-requester-offers.png)
- [Donor offer confirmation](../scratch/browser-check/main-evidence/11-offer-confirmation.png)
- [Mobile profile](../scratch/browser-check/main-evidence/19-mobile-profile.png)
- [OTP verification result](../scratch/browser-check/main-evidence/21-otp-verification-success.png)
- [Expired-post history](../scratch/browser-check/main-evidence/24-expired-history.png)
- [Past-date renewal validation](../scratch/browser-check/main-evidence/25-renewal-past-deadline.png)
- [Cancellation confirmation](../scratch/browser-check/main-evidence/28-cancellation-confirmation.png)
- [Logout failure](../scratch/browser-check/main-evidence/30-logout-result.png)
- [OTP email, mobile](../scratch/browser-check/main-evidence/email-browser-otp-mobile.png)
- [Membership email, mobile](../scratch/browser-check/main-evidence/email-membership-mobile.png)
- [Grouped email, mobile](../scratch/browser-check/main-evidence/email-grouped-mobile.png)
- [Worker run history](../scratch/browser-check/email-runs.jsonl)
- [SMTP transport outcomes](../scratch/browser-check/email-transport.jsonl)
- [Final cleanup and preservation check](../scratch/browser-check/finish.json)

Screenshots and raw logs are retained locally under ignored `scratch/browser-check`; they are not bundled into Git automatically. The private fixture/session/backup files should not be published.
