# BloodMatch Safety Fix Progress

Marker: `BM-SAFETY-FIX-PROGRESS-2026-10-08`. Updated 2026-10-10, Asia/Manila.

Latest checkpoint: **accepted prior test-target evidence retained; one specific Emergency material-edit browser scenario user-verified; focused card/notification/registration-location improvements prepared; their database and remaining browser checks pending**. Operational scheduling, real emails and application/deployed rollout remain deferred. The previously identified real-transport donor-cap gap remains open.

## Focused browser and registration-location checkpoint - 2026-10-10

**User-verified scenario only:** user created two accounts, posted an Emergency request with one, responded with the other, changed the required blood type, and observed the responding donor's no-longer-compatible notification. This establishes that specific browser notification scenario only. It does not establish pending-report treatment, all urgency classes, duplicate suppression, quiet unread/popup behavior, acknowledgment, analytics, or delivery outcomes. Preserve accepted earlier code/test-target evidence without repeating completed suites/cleanup.

Focused changes: existing Responded state is visible in the collapsed card header, using the compact existing badge. New opportunity/response/invalidation/withdrawal/report notification wording uses relevant display names and descriptions rather than visible request numbers; own request lifecycle wording uses “your blood request.” Request IDs, recipients, permissions, links, dedup keys and processing remain internal and unchanged. Both notification surfaces render legacy request-number phrases without their numbers, including accessible labels; stored historical rows are not backfilled. Quiet generation refresh retains the same write fields/read timestamps and separate opportunity history.

Registration trace found three missing links: no municipality/barangay selector, neither submission format included a location, and the users INSERT omitted location_id. Registration now uses the existing optional canonical selector, shares JSON/multipart payload preparation, validates active parent municipality and submitted barangay/reference consistency, saves location_id and reference-derived coordinates. Municipality changes clear stale barangay choices. ProfileController's existing own-profile projection resolves the stored reference; its About row has a clear empty state. Other-member profile exposure is unchanged. No new schema, location seed modification, historical correction, donor eligibility rule or email policy is introduced.

Agent lightweight results and exact deferred commands: see [FOCUSED_PROFILE_NOTIFICATION_CHECKPOINT.md](FOCUSED_PROFILE_NOTIFICATION_CHECKPOINT.md). New test-target database checkpoint is prepared, **not agent-run**. New browser presentation/form checks remain manual and unverified until supplied. No database suite, migration, task or real email executed for this checkpoint.

Agent-run results: PHP syntax passed for all 10 affected/new PHP files; 10 offline canonical selection checks passed; 16 offline registration payload/notification presentation checks passed; mocked Home/card suite passed with collapsed and expanded Responded assertions and retained confirmation/repeat/stale guards; git diff --check passed. Final frontend build passed via npm.cmd (npm.ps1 was blocked by local execution policy); existing >500 kB bundle warning remains. No browser automation or database checks were run. Prepared focused database suite expects 17 PASS checks and transaction rollback, not a claimed completed result.

## Explicit one-account removal for re-registration - 2026-10-10

User reports the subsequent single-recipient SMTP check "all good"; accept as user-reported success/receipt only, with no supplied full transport output or numeric exit. This does not establish queued real-worker readiness or resolve the future donor-cap uncertainty gap.

User explicitly chose permanent deletion of fireshorts395@gmail.com in bloodmatch:3306 to register again. Agent read-only review identified exact account ID 136: member, active/pending, email unverified, unenrolled, no donation anchor, requests/offers/reports/verification reviews all zero, one uploaded document. This narrow approval supersedes the earlier no-account-change boundary for this account only. Prepared fixed-ID maintenance helper refuses changed/privileged accounts, any participation/review/safety history, unknown FK scope, active mail leases or shared/unvalidated files. No general deletion API/permission rule or migration added.

Agent performed this explicitly approved real account removal (not a database test or schema migration): exact fresh account, owned document metadata, related notification jobs and exact account-email throttle keys removed transactionally; shared/IP throttles and SMTP attempts retained. A permanent audit entry records user-approved maintenance; older action/target/context/time and accounting fingerprints are retained, with the existing actor FK allowed to become NULL. File cleanup initially hit Permission denied after DB commit; recovery journal was retained. The guarded journaled file-only recovery succeeded with approved sandbox escalation. Final review confirms account136 absent, zero owned document records/files, remaining_email_matches=0 and journal removed; original file Test-Path=False. No password hash, OTP/token, private document content or credentials were printed; no real email sent by the agent.

The address is available for fresh registration. No other account or prior fixture group was removed. This one-account maintenance is separately authorized and must not be generalized to automatic history deletion or verification/safety bypass. Existing test checkpoints and operational deferrals remain unchanged.

## Supplied application 021 readiness confirmation - 2026-10-10

User supplied default review JSON on actual bloodmatch at 127.0.0.1:3306: email_outbox=ready, email_attempts=ready, recorded=true, ledger/parent/prerequisites ready, and SHA-256 matching the approved 021 file. Application 021 full shape/ledger readiness is **CONFIRMED by this user-run read-only output**. The printed CREATE SQL and generic approval footer are review reference text, not additional execution or a request for repeat approval. Do not reapply migration or ask again for the same approved scope.

Backup output and the apply-time notification/report preservation summary were not supplied separately; do not claim independently inspected backup or row-preservation output. No 020/022 application, application worker/schedule, per-donor hardening or other rollout is inferred. Agent applied no migration and executed no database operation or real email. Prior test-target results remain accepted.

Next narrow user action: one explicitly authorized direct SMTP delivery test to marsee24@bpsu.edu.ph through the existing helper, using normal app .env and the shared ledger. Require terminal result and actual Inbox/Spam observation. Success means transport acceptance; inbox receipt is separate evidence. Then an OTP resend still requires an existing unverified account in the running application; already verified/missing accounts intentionally skip. The helper is an ordinary diagnostic SMTP message, not OTP or a queued-worker rollout. Do not bypass budgets or delete accounting reservations.

## Application 021 user-run approval received - 2026-10-10

User explicitly replied "approved" to the concrete proposal for **user-run application of only 021 on bloodmatch:3306**, following supplied missing-table/ready-prerequisite preflight and checksum review. Reuse this approval; do not ask again for the same target/file. Scope is backup, checksum-gated additive 021 application, read-only verification; no other migration, historical backfill/resend, user/password/verification changes, worker/task setup, per-donor hardening or .env changes. Agent still must not execute migration/database suites/real emails.

Application is approved but **NOT YET CONFIRMED APPLIED**. Provide sequential commands: stop app/mail/database writers; create a native mysqldump backup outside the repository via --result-file; apply the fixed-target helper with SHA-256 983bf7abdfa7c4708487da97f43639faeca0d5561353ab1a542652f6f0dd7d4e; then default SELECT-only review. Require both shapes ready, recorded=true and success/exit evidence. DDL auto-commits; failure can retain a valid partial table. Stop and inspect, never drop/reset or apply all migrations. Backup is retained rather than cleaned as a disposable fixture.

SMTP/OTP retry is a later single-recipient check after schema readiness is supplied; verified accounts still do not receive OTP resends. Account login failure remains a separate unresolved diagnosis. Existing completed test-target checkpoint remains accepted; no reruns requested.

## Supplied application 021 preflight - 2026-10-10

User supplied default read-only review JSON confirms actual application bloodmatch at 127.0.0.1:3306, mode=review; email_outbox=missing and email_attempts=missing; 021 recorded=false; existing ledger and parent notification ID ready; prerequisites_ready=true. Supplied SHA-256 983bf7abdfa7c4708487da97f43639faeca0d5561353ab1a542652f6f0dd7d4e matches the agent's local file hash. This confirms the missing application accounting schema, independently of the already verified test target. No application migration is applied.

Concrete proposed user-run action is checksum-gated application of only existing 021 on bloodmatch:3306: create the two empty structures and record this migration, preserving notifications/reports with no backfill, user/password/verification change, extra 020/022 migration, task, real worker, donor-cap hardening or .env edit. Shared limits/allowlist stay unchanged. Queue structures do not supply a real worker; actual SMTP authentication/inbox delivery is not yet proved. Stop app/mail/database writers and back up this application database before any approved application; DDL can leave valid partial structures if it fails.

Approval for this separate application schema target is required by the user's original approval rules. Await explicit approval for user-run 021 application; do not infer it from preflight output. Agent checked file hash/source only; no DB suite, application/DDL or real email executed.

## Normal application OTP/SMTP accounting blocker - 2026-10-10

User authorized narrow manual email probes to specified addresses after the earlier general real-email deferral; no operational worker/scheduling or application schema rollout is implied. Supplied direct SMTP helper output: host smtp.gmail.com:587 reachable, OpenSSL loaded, failed after 0.03 seconds with accounting_unavailable, exit 1, and user reports no received email. Code reserves shared accounting before SMTP; that error prevents the transport callback. TCP reachability is not SMTP authentication/inbox proof.

Read-only metadata supplied earlier identifies normal database bloodmatch:3306, not bloodmatch_test. Its pasted accounting table query listed no rows, consistent with missing 021 structures. Verified recipient markseejr@gmail.com (ID 3, email_verified=1, no OTP hash/expiry) is an independent reason its OTP resend skips delivery. The original fireshorts395 Gmail login failure has not been resolved or correlated to an inspected application account/hash; do not conflate it with the missing-email blocker. No account verification or password changes are authorized/performed.

Prepared database/review_application_email_accounting.php and docs/APPLICATION_EMAIL_ACCOUNTING_REVIEW.md: fixed actual bloodmatch:3306 target; default SELECT-only review; existing 021 SQL/checksum/full-shape/parent/ledger review; optional checksum-gated user application only after separate approval for this target. Proposed DDL is exactly the two existing 021 tables and its ledger entry, with no backfill, other migration, drop/reset, task, real-worker implementation or .env edit. Existing notifications/reports are fingerprinted without requiring the optional 020 rejection column. Eligible direct account emails may attempt after accounting repair; queued Emergency/member decisions remain without a real worker and no delivery guarantee is claimed.

Agent PHP syntax and whitespace checks PASS on new helper; application DB review/application and further transport were NOT RUN. Previously accepted test-target evidence and per-donor uncertainty policy (record only, no hardening implementation) remain unchanged. Next: user runs the default read-only application review; review its concrete output before requesting approval for application 021. No repeated SMTP/OTP probes until the accounting blocker is understood; no bypass of budgets or verification flags.

## Browser checkpoint and per-donor cap review - 2026-10-10

User explicitly accepts the completed checkpoint at the documented test-target evidence level. No completed test or cleanup is repeated. Browser availability was checked once through cua.getState(); the tool returned "trusted Node process exited unexpectedly; kernel reset". Per user instruction, stop retrying browser initialization and provide the five-case manual checklist. No browser action/rendering is verified, and no app server, database suite, task or real email is started.

Code finding (Major; per-donor crash/uncertainty enforcement Partially implemented): TestEmailWorker's two-per-donor/rolling-30-minute query counts DISTINCT sent_at only for outbox status=sent. Pending/leased reservations, failed/unknown transport outcomes and accepted mail whose finishDigest cannot commit are excluded. The run lock serializes ordinary test runs but does not persist donor reservations across crashes/ownership loss. EmailAttemptBudget commits category/time reservations separately before transport and keeps them on failure/crash; those count toward shared 100/hour and 450/24h budgets, not the donor counter. Mailer currently collapses callback false/exception into transport_failure without an uncertain outcome classification. Thus the supplied fake acceptance/cap tests remain valid, but do not establish a hard per-donor cap for real uncertain outcomes. Identify this gap before any real transport work; do not describe the donor cap as crash-safe.

Resolved future policy, user reply in this chat: release donor-cap reservations **only when non-acceptance is conclusively proven**. Retain outstanding, accepted and uncertain attempts for the rolling 30-minute window; unknown outcomes remain uncertain. Releasing a donor slot must **never refund the shared hourly/daily SMTP-attempt budget**. Record only: no current implementation, migration, reservation/outcome-state change, retry change, infrastructure setup or real delivery is authorized by this reply. Separate scoped design/schema approval and focused new failure-boundary checks are required before future implementation. This policy decision must not be asked again.

Manual UI checklist (confirmed test target/disposable cases only): response Cancel/Confirm/double-click/keyboard behavior; non-response acknowledgment including remaining post-donation block and repeated action (requires an already prepared standby case, otherwise untested); read opportunity then genuinely edit request and verify same visible alert/read count with no edit popup, repeat save silent; saved creation versus a safely mocked failed-matching response and unchanged request/card identity (no forced database failure); analytics populated values/date labels/table/chart/empty state without console errors. Repeat at a narrow viewport. Missing standby/failure cases are marked untested, not fabricated or created through historical-data changes. Browser fallback and cap review are complete; manual UI outcomes remain Unverified.

## Supplied latest API fixture cleanup PASS - 2026-10-10

User supplied review on bloodmatch_test:3306: exact IDs 1240-1268 (29 users), request IDs 2602-2606 (5 requests), 7 document records and 7 present files. Cleanup returned cleaned, remaining_users=0, remaining_owned_files=0, audit_history=retained, email_attempts=unchanged, other_user_identities=unchanged and journal_removed=true. Final SELECT-only review returned already_absent with zero selected users/requests/documents/files and no journal. Accept scoped database/file cleanup as confirmed by this supplied output; numeric shell exits were not printed separately, but the user reported pass and the guarded commands continued without reported failure.

No earlier fixture group cleanup or historical repair is inferred. Audit action/target/context/timestamps and accounting entries are retained; fixture actor FKs may become NULL as designed. Identity digest verification covers other users' id/email/created_at, not a fresh certification of every unrelated field or deployment. Agent executed no DB review/cleanup/test, migration, task or real email.

Current approved code-and-test checkpoint is complete at the documented evidence levels: supplied worker 57/cleanup, supplied enqueue 40 assertions/cleanup plus accepted corrected exit pass, supplied focused safety follow-up 42/exit 0/cleanup, workflow 40/0 and Home 39/0/exit 0 summaries, user-reported phase4/phase5/phase12 passes, and supplied latest API fixture cleanup. 020/021/022 readiness is confirmed only on bloodmatch_test:3306. No repeat of those completed tests, migrations or cleanup experiments is requested.

Remaining scope is explicit: actual browser/mobile interactions; production/application schema/security/performance evidence; separately scoped operational scheduling/real SMTP transport; untouched registration atomicity, durable audit failure recovery, demand refresh cadence, history pagination and management/export expansion; historical cleanup/resend requires its own approval. All current offer/timer/fan-out/transition rules remain resolved. The final BLOODMATCH_FIX_STATUS.md report is refreshed as external attachment or complete Markdown fallback, never written into the repository/docs.

## Latest API fixture inventory and scoped cleanup preparation - 2026-10-10

User supplied SELECT-only inventory confirms bloodmatch_test:3306. Latest groups: phase4 suffix 1843164037 / IDs 1240-1245 (6); phase5 suffix 2011903800 / IDs 1246-1260 (15); phase12 suffix 439947979 / IDs 1261-1268 (8), total 29. Older phase5/phase12 batches in that inventory are outside this cleanup and remain untouched. Inventory timestamps are preserved as supplied, without assuming the mysql CLI's session timezone.

Prepared tests/latest_api_fixture_cleanup.php: fixed actual test-target guard; no-argument/--review SELECT-only default; --cleanup acts only on the 29 hardcoded ID/email pairs from supplied inventory and their validated related records. Refuses partial/mismatched identities, unexpected pictures/requests/donation reports, external reviewer/offer history and shared/changed/symlink/out-of-directory upload files. Known phase12 request owners/facilities are checked; only current fixture event notifications are removed. Older/nonselected users' identities, existing accounting entries and protected audit content/action/target/time are checked by snapshot digests. Audit actor references may become NULL through the existing FK; no audit DELETE/UPDATE is issued.

Cleanup persists a credential-free recovery journal in the PHP system temporary directory before committing scoped DB deletions; database commit precedes individual validated phase5 upload-file removal. A failed/interrupted file stage retains that journal for --review/--cleanup retry; no recursive deletion, storage migration, blanket prefix deletion, schema change or historical repair. If identities/records changed or ownership cannot be proven, stop and inspect rather than force cleanup.

Agent checks: PHP syntax PASS; whitespace PASS; connectionless scope test verifies all 29 ID/email pairs against the supplied attachment, excludes every older listed group, and checks recovery-journal scope serialization. No DB connection, fixture review/cleanup, PowerShell suite, migration, task or real email executed. SQL/FK/file execution remains user-only and unverified. Next commands: php tests/latest_api_fixture_cleanup.php --review, then --cleanup only after successful review, then --review confirmation. Expect cleaned with remaining_users=0, remaining_owned_files=0, retained audit/unchanged attempts, and journal_removed=true; final review already_absent. No test rerun is requested.

## User-reported remaining API regressions PASS - 2026-10-10

User replied "all passed" after the phase4.ps1, phase5.ps1 and phase12.ps1 isolated loop. Accept all three as **USER-REPORTED PASS** on the instructed bloodmatch_test:3306 target. Full assertion counts, per-suite numeric exits and teardown output were not supplied; do not invent or claim independently inspected values. No rerun is required solely to obtain those details.

Current prepared safety paths now retain focused and integration evidence at their stated levels. Existing worker 57, focused follow-up 42/exit 0, workflow 40/0 and Home 39/0/exit 0 supplied summaries remain unchanged. Corrected enqueue success remains accepted by user report with earlier supplied 40 assertions/cleanup. Migration 020/021 prior target evidence and 022 shape/ledger assertions establish only the test target, never application/deployment state.

Remaining immediate housekeeping: phase4/phase5/phase12 legacy suites leave fixture rows and phase5 upload files. No fixture-cleanup output is supplied for these suites; runner server/environment teardown is not database/file cleanup. Obtain a read-only inventory and identify the exact latest run groups before preparing any scoped removal. Retain append-only audit and unrelated accounts/data/attempts. No blanket prefix deletion or reset. Actual browser/mobile checks, operational scheduling/real transport, deployed schema/security and untouched audit items remain separately bounded work; do not call them passed or automatically approved.

Agent changed this progress record only; no DB/PowerShell test, migration, task, real email or data cleanup executed.

## User request/response regression results - 2026-10-10

Supplied summaries: workflow atomicity **40 passed, 0 failed**; Home response **39 passed, 0 failed**, with explicit Home response exit code **0** and test-server/environment restoration output. User states "all passed" for both commands; accept workflow success on that report while noting its numeric exit was not separately supplied. No new per-fixture database cleanup inventory was supplied. Do not re-run either regression, the 57-check worker, accepted enqueue, 022 application or 42-check focused suite merely to repeat established results.

Remaining changed API/readout regressions: phase4 account/role/chapter behavior, phase5 document/verification upload/resubmission, phase12 analytics/active-demand values. Run only these through the fixed isolated bloodmatch_test:3306 runner, with port 8001 free and other writers/schedulers stopped. These older suites create disposable fixtures but do not provide a final general fixture-deletion block; runner server/environment teardown must not be described as database cleanup. No broad cleanup/reset or audit deletion is authorized. Agent source review only in this result follow-up; no DB/PowerShell test, migration, task or real email execution.

## Supplied focused safety follow-up PASS - 2026-10-10

User supplied: **42 safety follow-up database checks passed**, **Exact fixtures removed; audit history retained**, and **Safety follow-up exit code: 0**. Accept this focused checkpoint as passed on bloodmatch_test:3306, enforced by its target guard. Its mandatory opening assertions verify complete 022 table shapes and the migration ledger; this establishes 022 readiness/recording on that test target. Standalone migration-review/application/verification output was not supplied, so no specific application command or other target is inferred. The agent applied no migration and ran no database/PowerShell suite.

The suite supplies focused evidence for quiet notification reuse, latest-generation supersession/history retention, independent request timers, acknowledgment watermark/fresh-timer preservation, no immediate retrigger, closed/overdue exclusions, actual-transition dedup, all-urgency invalidation/report review, account/resubmission rollback/idempotency, >100 donor refresh, 501 Emergency jobs and no transport/accounting attempts in that lifecycle suite. Prior 57-check fake-worker pass and accepted corrected enqueue pass remain retained; no repeat requested.

Next narrow checkpoint: user-run changed request/response regressions (workflow_atomicity.ps1, then home_respond.ps1) via run_isolated.ps1 on bloodmatch_test:3306 with port 8001 free. Require zero failures, final summaries and numeric exits; runner stops its temporary server/restores process environment and suites perform scoped fixture cleanup. Existing scheduled-expiry experiment, worker/enqueue, 022 application and safety_followup need no rerun. Actual browser interaction, further changed API/accuracy regressions and operational scheduling/real delivery remain separate later gates, not claims established by this focused pass.

## Corrected enqueue checkpoint accepted by user report - 2026-10-10

User follow-up: "yup all passed" after the corrected enqueue wrapper commands. Accepted as **USER-REPORTED PASS** for the corrected 40-check enqueue/cleanup/exit-status checkpoint. Complete rerun output and numeric exit code were not supplied separately; do not represent them as freshly inspected output. The earlier supplied 40 assertions and cleanup, all three orphan groups removed with empty inventory, and supplied 57-check fake-worker/cleanup pass remain retained. No worker/enqueue/orphan-cleanup rerun is requested.

Next checkpoint: user reviews/applies/verifies additive 022 on actual bloodmatch_test:3306 and runs only the focused safety_followup suite. No production/application schema or task is inferred. The agent applies no migration and runs no DB/PowerShell test. Require 022 ready/recorded output, focused success summary, exact fixture cleanup and numeric exit before dependent work.

## User cleanup/enqueue assertions and exit-status correction - 2026-10-10

Supplied attachment confirms all three exact failed-enqueue fixture groups (27 users, no requests) were reviewed and cleaned on bloodmatch_test:3306. Audit history was retained, email_attempts untouched, and subsequent SELECT-only inventory returned fixture_groups=[].

Enqueue rerun supplied all **40 database checks passed**, the exact fixture/request/user cleanup line and an explicit **exit code 1**. Assertions and reported cleanup are now confirmed by supplied user output, but the process-level checkpoint is not complete. Root cause: expected synthetic failure assertions use local $failed; the final isset($failed) incorrectly classified that intentionally created test variable as suite failure even after every assertion passed.

Corrected only tests/email_enqueue.php exit bookkeeping: initialized separate $suiteFailed=false, set it in the outer unexpected-exception handler, and return its value at final exit. Expected failure assertions and cleanup remain intact. Agent PHP syntax and whitespace checks PASS; no database/PowerShell test was executed. Next user action: rerun only the enqueue wrapper to confirm the same 40-check/cleanup output with numeric exit 0. No worker or orphan-cleanup rerun is requested; the prior 57-check worker pass and confirmed cleanup remain retained. Hold 022 application until this process-level result is supplied.

## User worker result and interrupted enqueue regression - 2026-10-10

User supplied complete worker output: **57 database fake-worker checks passed**, followed by exact fixture/synthetic-attempt cleanup with audit history retained. This verifies the tested fake grouping/caps/budgets/retries/live cancellation/lease/overlap paths on the fixed bloodmatch_test:3306 target; it does not prove real SMTP/inbox delivery or deployed scheduling. Numeric shell exit output was not separately supplied. No worker rerun is needed for the following verification-service correction.

Enqueue regression is **INCOMPLETE**, not passed: output stops after 25 PASS lines at a Forbidden JSON response during the expected cross-chapter denial test. Source traced VerificationService -> AuthBridge -> HTTP middleware's bare exit, which bypassed exception handling, rollback cleanup and the suite summary. A bare exit may report shell success, so the supplied guard alone cannot establish a passing suite. Fixture cleanup was not printed and is unconfirmed.

Routine correction preserves chapter authorization: VerificationService now throws RuntimeException(403) inside the locked service path. Its existing transaction unwinds and denial audit remains handled by decide(); API/controller handling still returns 403. Prepared tests/email_enqueue_cleanup.php defaults to SELECT-only fixture-group review and requires one reviewed exact 16-hex nonce for optional user-run cleanup. It validates known Enqueue/Fixture reserved-email identities, owned request names and absence of unrelated offer/donation/reviewer history; deletion is transactional and scoped. Audit history and the entire attempt ledger are retained. No new schema/permission or historical-data repair.

Agent checks: syntax PASS on the corrected service and cleanup helper; whitespace PASS. No DB review/cleanup/test or worker was run. Next: user reviews orphan fixture nonce(s), performs exact failed-fixture cleanup, then reruns only tests/email_enqueue.ps1. Require the complete expected 40-check summary, scoped cleanup line and numeric exit code before 022/follow-up work. Worker pass is retained; scheduling and real delivery remain gated.

## Current approval alignment - 2026-10-10

Source of authority: the user's instructions and decision replies in this chat. Historical plan/proposal gates do not revoke approvals. Prepared code is not an applied migration or a passed integration test.

| Decision | Current approval / execution boundary |
|---|---|
| Donor ranking | Approved: compatibility/eligibility first; chapter/distance rank, never exclude an otherwise eligible donor. No compatibility-tier reorder. |
| Closed requests | Approved: close unfinished offers; retain completed donations and pending reports. Closed/overdue reports cannot confirm; explicit authorized rejection requires a reason. |
| Material edit invalidation | Approved for Routine/Urgent/Emergency: recheck compatibility/eligibility; close invalid RESPONDED offers without pending reports. Preserve pending reports for reasoned review; block invalid confirmation. Preserve COMPLETED history. Valid offers and harmless edits create no invalidation alert. |
| Invalidation alerts | Approved: affected responding donor and requester receive one in-app alert per actual invalidation; duplicate processing is silent. |
| Opportunity display | Approved: quietly update/reuse the existing donor/request notification, without unread reset, new popup or a new row per edit. Newly compatible eligible donors receive the normal in-app opportunity alert. Same request/card is edited. |
| Opportunity timers | Approved: 42 hours from the committed in-app opportunity event; independent opportunity/generation history, separate from the visible notification. Harmless edits/retries restart nothing; closed/overdue requests excluded. Only the latest valid notified generation per donor/request counts; older unanswered generations are superseded with history retained. |
| Standby recovery | Approved: explicit donor acknowledgment retires elapsed timers while preserving fresh timers and independent post-donation safety windows. Proposed consumed_through compares due_at, not notified_at. |
| Standby migration | Preparation approved in this chat: additive 022 timer/state structures and acknowledgment flow. Do not apply, backfill or run database tests. Test helper defaults to review and actual bloodmatch_test:3306 verification. |
| Email allowlist | Approved: OTP/reset direct through shared accounting; queued Emergency donor opportunities and membership verification decisions only. All other lifecycle events in-app. No queued secrets, response email, material-edit/rematch email or historical bulk resend. |
| Shared email budgets | Approved: 100 attempts/rolling hour with 10 protected account slots; 450/rolling 24 hours with 50 protected. Non-account maximum 90/hour and 400/24h; account mail may use remaining total capacity. |
| Emergency grouping | Approved: five-minute grouping; maximum two donor-alert emails/rolling 30 minutes across requesters. Prepared fake worker uses one reservation per digest and committed simulated acceptance times for the recipient cap. |
| Withdrawal/re-response identity | **Already approved actual-transition alerts**, reaffirmed in this chat. Each real transition may notify in-app once; retries/duplicate clicks remain silent. No response email or multiple-response permission change. This decision must not be asked again. |
| Routine implementation/checkpoints | Authorized: complete independent enqueue/worker/grouping/retry/in-app/posting/recovery/reconciliation/accuracy work automatically. Required user-test results block dependent scheduling/delivery work, not independent fixes. |
| Protected boundaries | No new permissions/privacy rules, encryption/storage/dependency decisions, application/deployed schema/tasks, historical cleanup/backfill or real email without their own approval. Existing member-email visibility remains unchanged. |

Both residual questions are resolved in this chat. Older unanswered generations are superseded and retained; only the latest valid notified generation per donor/request counts, while other requests remain independent. Emergency jobs enqueue for every eligible donor in bounded batches with no 500-job total cap. The user states unlimited Emergency enqueueing was already approved: do not ask this again. No remaining business-rule question blocks the requested prepared scope. Operational tests/targets and protected infrastructure/historical-data boundaries still apply.

Conflicts found: older CONTEXT excludes all non-response standby; old proposal sends Emergency response/account/donation/lifecycle emails and creates a new visible opportunity alert per generation; old progress lists offer treatment, lifecycle coverage and response identity as pending; the roadmap's blanket 100% claims are historical. Current chat decisions supersede these. Update the current rules without treating dated test counts as fresh evidence.

Verification evidence retained: user-supplied 020/021 shapes/ledger on bloodmatch_test:3306; supplied 34 accounting checks; user-reported enqueue/API pass without full counts/cleanup/exit output; supplied 16 scheduled-expiry assertions and subsequent scoped cleanup/task removal. No other migration target is inferred. New fake-worker/grouping, material reconciliation, standby schema/flow and accuracy SQL are prepared and await current user-run database results. Earlier agent offline checks/builds are not database proof.

Current dependency order: resolved rule alignment -> finish independent prepared fixes and lightweight checks -> user verifies fake worker/enqueue -> user reviews/applies/verifies 022 on the fixed test target and runs focused follow-up/workflow/analytics regressions -> only then consider separately scoped operational scheduling/real delivery. No agent migration, database suite, task or real email execution.


## Prepared follow-up and final lightweight checkpoint - 2026-10-10

Completed prepared paths: unlimited bounded Emergency enqueueing; fake-worker digest/grouping/recipient cap with shared attempts, leases, retries and stale cancellation; atomic lifecycle/account/review alerts; genuine withdrawal/re-response identities; explicit creation feedback; material edit plus reconciliation in one transaction; invalid-offer confirmation blocking/reasoned rejection; bounded donor refresh beyond 100 requests; quiet opportunity reuse; separate latest-generation 42-hour history/standby/acknowledgment (022 unapplied); donor readiness DTO/indicators and analytics API fields/date windows. Upload metadata/status/review alerts and resubmission now share a transaction; failure removes only the newly uncommitted document file. Registration atomicity, crash-orphan scanning and audit-loss recovery remain separate untouched gaps.

Final agent-run checks: syntax PASS on 34 affected PHP files; offline worker 21, enqueue 15, accounting 19, lifecycle 22, age/consent 23 and due-at acknowledgment boundaries 4 PASS; request UI 19 static checks and Home mock/callback suite PASS; frontend build PASS (existing >500 kB bundle warning); two PowerShell wrappers parse-only PASS; whitespace PASS. No database/PowerShell suite, migration, worker run, task or real email was executed. Browser/SQL/concurrency/fake-worker delivery assertions remain user-run/unverified; existing 020/021 and earlier user results are unchanged. No code/source edit establishes a production/applied target.

[Exact worker, additive 022 review/apply/verify and focused/API commands](SAFETY_FOLLOWUP_CHECKPOINT.md). Required current user results block dependent scheduling/real-delivery rollout. All current business-rule questions are resolved; do not re-request them. The requested BLOODMATCH_FIX_STATUS.md report is delivered outside the repository (or complete Markdown attachment fallback), never in docs.


## Phase 5A manual test-worker checkpoint — 2026-10-10

User's “proceed” authorizes the previously recommended manual test-only worker preparation. Added fixed-target SELECT-only verification and explicit single-job fake run CLI, worker policy/orchestration, lease/claim/completion repository operations and deferred DB/PowerShell tests. Existing approved coverage remains Emergency donor opportunities and membership decisions; no SMTP adapter/real mode, schedule, channel expansion or backfill. OTP/reset remain direct through the same shared gateway; Mailer now shares its reservation-and-result wrapper with fake worker attempts. Budgets/grouping remain 100/hour total, 10 protected, maximum 90 Emergency; 450/rolling 24h, 50 protected, maximum 400 Emergency. Account jobs retain access to all total capacity; verification uses normal priority/account budget, Emergency remains Emergency.

Prepared bounded Emergency-first claims, 120-second leases, ownership/expiry fencing, recovery and atomic sent/emailed_at completion. Database run lock skips overlap and serializes normal-recipient accounting. Six attempts/backoff 1/5/15/60/240 minutes, terminal exhaustion, five-minute configuration pause, one-minute budget/accounting/recipient deferrals. Normal-priority per-recipient cap uses actual emailed_at; Emergency retains bypass within shared capacity. Fake callback executes with no active workflow/claim/accounting transaction; the run lock remains held. Failed/crashed attempts retain committed reservations. Late safety changes after reservation may consume an unused slot conservatively; no refund or exactly-once claim.

Live checks cancel expired/excluded/unverified jobs, superseded membership decisions, and Emergency jobs with closed/non-Emergency requests, elapsed deadline, changed generation/resolved offer or current donor account/verification/age/consent/availability/windows/compatibility failure. Current checks repeat after reservation before callback. Chapter/location ranking and donor safety remain unchanged. Codes/summary/fake metadata expose no secrets, addresses or bodies. Stored fake sent state means simulated acceptance only, not SMTP/inbox proof; eligibility can change after the final check and an acceptance/crash boundary can duplicate future real delivery.

Agent-run lightweight checks: seven PHP syntax files PASS; 18 worker, 19 accounting and 15 enqueue offline checks PASS; PowerShell wrapper parse-only PASS; whitespace check PASS. New DB/PowerShell suites and CLI verification/run are **NOT RUN**. [Exact readiness, focused worker/enqueue regression and optional scoped fake commands](TEST_EMAIL_WORKER_CHECKPOINT.md). Expected focused worker summary: 52 checks, zero failures, exact fixture/synthetic-attempt cleanup retaining audit history. No new migration or application/deployed target; prior 020/021 confirmation remains test-only user evidence.

Remaining/next: await user-run worker and enqueue-regression output before separately approved fake-worker scheduling. Real transport/rendering/timeouts/delivery readiness, production schema/rollout and any notification/fan-out expansion remain separate approvals. Existing 500-opportunity cap and failed-matching recovery are deferred. No real worker/scheduler command exists. Agent did not run database/PowerShell tests, a worker, migrations, tasks or real emails, and did not change .env.

User result follow-up: user says “all passed” and supplies `{"success":false,"error":{"message":"Forbidden."}}`. Record the test result as **USER-REPORTED PASS**, without asserting the expected 52 count, cleanup or exit codes were supplied. The accompanying JSON resembles the HTTP API error envelope; the worker CLI and focused PHP suites do not emit that envelope. Source review confirms phase5.ps1 intentionally tests multiple 403 permission denials, but the command/endpoint and surrounding summary for this particular response are unknown. Asked for that context; do not label it an expected assertion or an application regression without evidence. No permission rule was weakened, no test rerun or next checkpoint started. Clarify the response before declaring this follow-up fully resolved or preparing scheduling; real delivery remains unavailable.

| File | Change | Search marker | Found? |
|---|---|---|---|
| backend/src/Services/TestEmailWorker.php / EmailWorkerPolicy.php | Scoped fake orchestration, live guards, policy | bm-test-email-worker | Yes |
| backend/src/Repositories/EmailOutboxRepository.php | Claims, attempt fencing and atomic completion | lease_expires_at | Yes |
| backend/src/Services/Mailer.php | Shared direct/fake attempt gateway, safe outcomes | attemptWithTransport | Yes |
| database/run_test_email_worker.php | Default verification, explicit fake-only single-job runs | Real delivery is unavailable | Yes |
| tests/email_worker.php / .ps1 / email_worker_offline.php | Deferred SQL/parallel/failure/safety tests and offline policy checks | stable Message-ID | Yes |
| docs/TEST_EMAIL_WORKER_CHECKPOINT.md | User commands and simulated-delivery boundaries | BM-SAFETY-PHASE5A-TEST-WORKER | Yes |

## Phase 5A transaction-safe enqueue checkpoint — 2026-10-10

User approved only Emergency donor-opportunity alerts and membership verification decisions for queued email. Source inspected before edits; notification-email allowlist now enforces these two channels and retains other events in-app only. Routine/Urgent, rematch/material-edit/donor-refresh and excluded lifecycle email stay off. Eligibility/compatibility/ranking, event recipients, 500-opportunity cap and protected workflow/history rules are unchanged. OTP/reset remain direct through the shared gateway/ledger, with hashes/TTLs preserved and no queued secrets. Budgets remain 100/hour total, 10 protected account slots, maximum 90 Emergency; 450/rolling 24h total, 50 protected, maximum 400 Emergency. Normal/Emergency share unprotected capacity; account mail may use all remaining total capacity. Verification jobs use normal priority and retain account budget grouping; Emergency grouping is unchanged. Enqueueing itself consumes no attempts.

New notifications and reference-only outbox rows share an owned transaction or nested savepoint. Strict insertion suppresses only proven duplicate events; current locking reads handle concurrent duplicates. SQL/enqueue failures propagate as sanitized 503 and remove partial writes. Verification now locks/rechecks actor and target, serializes pending decisions, and atomically persists status/history/successful audit/notification/job; decision-row identity avoids audit-dependent random dedup. A separate verification savepoint protects caught nested failures. Denial audit remains after rollback; best-effort audit failure policy remains. Match reconciliation occurs after owned verification commit to preserve lock ordering; nested owners control their later reconciliation. Emergency matching already has an owning transaction. Request creation remains separately saved; failed matching/enqueueing is explicitly returned as matching=null / matching_status=failed_retry_required, with no automatic recovery claim.

Pending jobs have zero attempts, no lease/sent timestamp, request-deadline expiry for Emergency and 72-hour metadata for decisions. No job contains copied secret, address or body. No worker/SMTP/lease/retry/TTL enforcement/scheduler/supersession or notification expansion is implemented. No backfill. No new migration: 020/021 remain confirmed only by prior user output on bloodmatch_test:3306, with no agent application or new database check.

Agent-run lightweight checks: syntax PASS on 12 PHP files; 15 offline enqueue, 19 accounting and 16 workflow checks PASS; new PowerShell wrapper parse-only PASS; whitespace check PASS. Focused duplicate/concurrency/rollback/forced-failure/coverage DB suite and related phase5 API suite are **NOT RUN**. Earlier 34-check user pass verifies schema/shared accounting, not this new implementation. [Exact readiness, direct-PHP/PowerShell and API regression commands](EMAIL_ENQUEUE_CHECKPOINT.md). Expected focused result: 40 checks, zero failures, fixture cleanup retaining audit history; no SMTP sockets. Return user results before dependent delivery work.

Remaining/next checkpoint: after enqueue validation, separately approve a test-only delivery worker with current recipient/request/generation/eligibility checks, preserved budget grouping, leases/retries/expiry/supersession and fake-transport tests. Scheduling and lifecycle expansion stay separate. Application/deployed schema/rollout, recipient fan-out, per-recipient acceptance counter and failed-matching recovery remain unverified/deferred. No worker commands exist yet. Agent executed no database/PowerShell suite, migration, task, real email or environment-file change.

User follow-up received 2026-10-10: **USER-REPORTED PASS — all requested tests passed**, understood as the supplied test-target readiness/enqueue suite and related phase5 API regression commands. Full output, actual assertion counts, cleanup output and numeric exit codes were not supplied; do not promote the expected 40 checks to a supplied count or independently confirmed cleanup result. Agent ran no database/API suite. This supersedes pending user execution above: enqueue checkpoint is complete on the user-reported test evidence, with no rerun requested. Prior explicit 021/020 test-target evidence remains unchanged; no new migration/application target is inferred.

Current plan sequence: schema/shared accounting complete with supplied DB evidence -> atomic enqueueing complete with user-reported pass -> **next: separately approved manual test-only delivery worker preparation** -> after worker validation, separately approved scheduling -> later explicitly approved rollout or channel expansion. Next worker preserves only Emergency donor-opportunity and membership-decision email, direct OTP/reset, current budgets/grouping and privacy/safety. It must claim bounded jobs, recheck live recipient/request/deadline/generation/donor eligibility and decision supersession, use the shared gateway/ledger, handle retries/leases/expiry and sanitized failures, and be validated with fake transport before any real delivery. Scheduling, real emails and excluded lifecycle coverage remain outside that checkpoint. No runnable worker command exists yet; approval to prepare this next scope is still needed. This follow-up updates progress only.

| File | Change | Search marker | Found? |
|---|---|---|---|
| backend/src/Services/EmailEnqueueService.php / Exceptions/EmailEnqueueException.php | Two-event allowlist and rollback/savepoints | bm_email_enqueue | Yes |
| backend/src/Repositories/EmailOutboxRepository.php / NotificationRepository.php | Reference-only job; strict event duplicate handling | notification: | Yes |
| backend/src/Services/NotificationService.php | Atomic approved jobs; other event email excluded | email_expires_at | Yes |
| backend/src/Services/VerificationService.php / Repositories/VerificationRepository.php | Locked atomic decisions and persistent event identity | bm_verification_decision | Yes |
| backend/src/Controllers/Officer/OfficerVerificationController.php / RequestsController.php | Explicit queue failure / matching status | failed_retry_required | Yes |
| tests/email_enqueue.php / .ps1 / email_enqueue_offline.php / helpers/fake_mail_transport.php | Prepared DB races/rollbacks; offline contracts; transport-call observation | poisonNextDelivery | Yes |
| docs/EMAIL_ENQUEUE_CHECKPOINT.md | Exact user commands, scope and limitations | BM-SAFETY-PHASE5A-ENQUEUE | Yes |

### Status before enqueue approval — 2026-10-10 (historical)

- **Latest checkpoint: VERIFIED on the test target by user-run evidence.** Schema/shared accounting is implemented; outbox structure is prepared, but enqueueing/worker behavior is not implemented. Read the master implementation plan (`ai_context/ROADMAP.md`), Phase 5 proposal and checkpoint docs; rechecked migration/helper/target guard, budget/Mailer/independent connection, direct OTP/reset classification, NotificationService and test cleanup. Historical roadmap completion claims do not establish current queue/lifecycle completion.
- **Passed checks:** prior agent-run PHP syntax on 12 files, 19 offline policy/PDO checks and whitespace checks; supplied user output shows 34 database/fake-transport checks passed, including concurrency and empty-table cleanup. Numeric shell exit code was not separately supplied. Today: source/documentation review only; no test rerun or real SMTP/inbox verification.
- **Migrations/targets:** user applied 021 and confirmed both table shapes plus ledger on **bloodmatch_test:3306**. User previously confirmed 020's rejection-reason column/ledger on the same target. Agent applied neither. Application/deployed state and other migration targets are not freshly verified.
- **Policy unchanged:** rolling-hour total 100 / protected account 10 / Emergency maximum 90; rolling-24h total 450 / protected account 50 / Emergency maximum 400. Account mail can use all remaining total capacity; normal/Emergency share unprotected capacity. Grouping remains account (direct OTP/reset, verification decision, account-status change), normal (other normal notifications), Emergency (existing alerts). OTP/reset stay direct with existing hashes/TTLs and no queued secrets. Existing recipients/channels/dedup remain; Routine/Urgent and material-edit/rematch opportunity email stay off. Compatibility, donor safety, chapter authorization, completed donations/pending reports and append-only audit remain protected.
- **Remaining/approvals:** enqueue transaction wiring; worker leases/retries/TTL/supersession/current-eligibility checks; later user-run scheduling. These need authorization beyond the completed schema-only scope. Proposed lifecycle coverage, all-recipient fan-out, repeated-response identity, standby/offer-treatment implementation and application/deployed rollout retain separate decisions. Existing synchronous SMTP/workflow blocking, per-recipient acceptance counter and 500-opportunity cap remain limitations. No backfill authorized.
- **Next narrow checkpoint recommended:** prepare transaction-safe notification/outbox insertion for existing email coverage only, duplicate/rollback tests and removal of SMTP from those handlers; defer worker delivery/scheduling. Dependencies: confirmed 021 test schema, existing locked transitions, preserved grouping/channels and explicit scope approval; user-run validation before dependent delivery work. No runnable enqueue/worker test commands exist yet. Optional existing SELECT-only readiness command: `& 'C:\xampp\php\php.exe' database\apply_email_checkpoint_migration.php` (without `--apply`). Completed schema tests need no rerun for this update; exact future test commands follow preparation.

## Phase 5A schema/shared-accounting checkpoint — 2026-10-09

User authorized only this checkpoint: 100 total attempts/rolling hour, 10 protected account slots, 90 Emergency/hour; 450/day total, 50 protected slots, 400 Emergency/day. Account emails may use remaining total capacity. Minimal read succeeded on this retry after the previous setup error. Current instructions/context/progress/proposal and relevant schema/mail paths read; database skill and folder guide used. No broad audit repeated or `.env` values read.

Prepared additive migration 021 creates notification-reference-only outbox structure and category/time-only shared attempt ledger, with no backfill. Single-file helper defaults to SELECT-only review and requires actual bloodmatch_test:3306. It verifies full column/index/FK shape and ledger, retains valid partial DDL objects and refuses drift/drop/reset. No migration applied by the agent.

Mailer reserves a committed attempt on a separate same-target connection; database-scoped cross-process lock releases before transport. Failures/crashes retain reservations; rate/configuration/reserved-recipient skips do not consume attempts. Accounting/schema failure prevents transport with sanitized errors. Windows are rolling one hour/24 hours, database UTC microseconds. All non-account mail shares 90/hour and 400/24h to protect slots from normal traffic too. Direct OTP/reset and existing verification/account-status email use account class, which has no separate 10/50 maximum. Existing per-recipient normal policy remains.

OTP/reset remain direct, with existing hash/TTL storage and no queued secrets. Outbox is schema only: no enqueue integration, worker, retries/leases, schedule or lifecycle/recipient expansion. Existing synchronous mail/workflow blocking and durable retries are deferred. Configured mail fails closed without the accounting schema; application/deployed use needs separate schema preparation. Only test-target commands are provided.

Checks run: PHP syntax PASS on all 12 new/changed PHP files; 19 connectionless policy/PDO checks PASS; git diff --check PASS. Real schema/concurrency/fake-transport DB assertions NOT RUN by agent. [Exact review/apply/verify/database-test commands](EMAIL_SCHEMA_ACCOUNTING_CHECKPOINT.md); expected 34 DB checks and fixture cleanup, with no SMTP sockets. Return user results before dependent queue work. No database execution, migration, task, real email, dependency or environment-file change.

User results received: **PASS — 34 database email checkpoint checks passed** on bloodmatch_test:3306. Initial SELECT-only review showed both tables missing and ledger unrecorded; user apply reported 021 ready/recorded, existing notifications/reports unchanged and no backfill/other migration. Subsequent SELECT-only review returned both shapes ready and recorded=true. Supplied suite output confirms parallel Emergency 90/hour and total account 100/hour boundaries, protected hourly/daily slots, 400/24h Emergency and 450 total/day accounting, rolling cutoff/aging, reservation survival across workflow rollback, lock release before transport, failed fake transport consuming an attempt, skips, outbox uniqueness/FK/cascade, report preservation and empty fixture tables after cleanup. The sanitized mailer failure line is expected from the synthetic failure assertion; no real SMTP delivery was tested. The shell exit code was not separately supplied, but the complete success summary and cleanup assertions are present. This supersedes the pending user-validation status above; no rerun is required. This follow-up changes documentation only. Enqueue integration, worker/retry/lease/eligibility/TTL behavior, lifecycle expansion and application/deployed rollout remain deferred; this result does not authorize those steps.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| database/migrations/021_email_outbox_accounting.sql | Two additive structures | BM-SAFETY-PHASE5A-SCHEMA | Yes |
| database/apply_email_checkpoint_migration.php / email_checkpoint_support.php | Fixed target, default review, complete shape verification | Review only; no writes | Yes |
| backend/src/Services/EmailAttemptBudget.php / Config/Database.php | Independent atomic reservations | independentPdo | Yes |
| backend/src/Services/Mailer.php / EmailVerificationService.php / AuthService.php | Direct shared accounting, secrets unchanged | EmailAttemptBudget::ACCOUNT | Yes |
| backend/src/Services/NotificationService.php / Repositories/NotificationRepository.php | Classify existing notification email | findMailType | Yes |
| tests/email_accounting_offline.php / email_accounting.php / helpers/fake_mail_transport.php | Offline checks and deferred focused DB suite | no SMTP/socket implementation | Yes |
| docs/EMAIL_SCHEMA_ACCOUNTING_CHECKPOINT.md / database/migrations/README.md | User commands, rollout limits and scope | 021_email_outbox_accounting.sql | Yes |

## Phase 5 checkpoint — proposal only

User selected email outbox/notification proposal as the next point; the brief availability investigation made no edits and is deferred. Read only current progress, relevant NotificationService/Repository/Mailer, creation/matching/response/lifecycle/donation/verification/account call sites, migration 013 and finalized notification-channel rules. Used database skill for schema/dependency review. No completed audit/test was repeated; no .env values were read.

Source verified: Emergency matching can send synchronously inside its request transaction; response email occurs after commit; duplicate notification insertion suppresses failed-mail retries; unconfigured/rate-limited mail has no durable delivery state. Several status events notify after business writes, so durable coverage needs appropriate transaction wiring. Missing lifecycle events and the current 500-opportunity recipient cap remain. Original request creation commits before matching; email queueing alone does not repair failed matching generation. Routine/Urgent and material-edit/rematch opportunity email remain disabled unless explicitly approved otherwise.

Concrete review artifact: [EMAIL_OUTBOX_NOTIFICATION_PROPOSAL.md](EMAIL_OUTBOX_NOTIFICATION_PROPOSAL.md), marker BM-SAFETY-PHASE5-PROPOSAL. Includes proposed additive outbox columns/indexes/uniqueness/FK, references-only payload/privacy, leases/retries/normal rate accounting/Emergency priority/TTL/supersession, honest SMTP acceptance/crash/closure-race limits, no historical backfill, and current-versus-proposed event/recipient/channel table. Proposed stages: 5A queues existing coverage and needed transaction wiring; user validates that checkpoint before 5B approved lifecycle expansion/all-compatible-recipient fan-out. OTP/reset, standby/window-ended transitions, offer-treatment policy and management UI remain separate.

Approval decisions pending: A) implement 5A using existing MySQL/PHP with six attempts/backoff 1/5/15/60/240 minutes, normal TTL 72h, one-minute signed-in test worker, bounded claims/leases, no backfill; prepare migration and commands but user applies/runs only. B) approve proposed 5B event/recipient/channel table and remove total 500 cap using bounded persistence batches, or keep existing coverage only. C) actual-transition notifications on withdrawal/re-response (recommended) versus lifetime response suppression. Material-edit opportunity email stays off by default. No answer or elapsed time is treated as approval.

Implementation status: **NOT IMPLEMENTED**; no migration SQL, outbox worker/test/helper, schedule install, database mutation, email send or notification coverage change. Planned future offline/mock and user-run migration/API/worker/failure/scope/cleanup checks are specified in the proposal; runnable new commands will be provided after approved implementation, not fabricated now. Phase 4B evidence is preserved; no Phase 5 tests are marked passed.

Checks this checkpoint: proposal-to-call-site/transaction review and documentation diff whitespace check only; no syntax/build/tests needed for documentation-only changes. Time consumed primarily by investigation of transaction/dedup boundaries and drafting the concrete schema/channel/default choices; tool waits were brief. Only this latest progress entry and the new proposal document changed.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| docs/EMAIL_OUTBOX_NOTIFICATION_PROPOSAL.md | Concrete design, current behavior, stages and approval choices | Approval A: outbox design | Yes |
| docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | Current proposal checkpoint and preserved test-only boundary | BM-SAFETY-PHASE5-PROPOSAL | Yes |

## Phase 4B checkpoint — test schedule preparation, 2026-10-09

Approved scope: prepare a Windows one-minute signed-in schedule for bloodmatch_test on port 3306, with overlap protection and secret-free logs. Agent must not register/start the task, execute database tests, or target application/deployed data. Inspected the expiry CLI/lifecycle, connection/environment and audit dependencies, existing test wrapper and local runtime convention only. Completed audits and unrelated code were not repeated.

Phase 4A result: **USER-REPORTED PASS**, including phase12; agent did not execute it or receive full assertion output. Previous scheduler inventory was not repeated. Test schedule dispatch is now verified by the user's supplied metadata and log output below.

Prepared behavior:

| Item | Implementation / impact |
|---|---|
| Target | Fixed 127.0.0.1 / bloodmatch_test / 3306; process overrides before .env load, then actual database/port verification on the same cached PDO before expiry writes. Connector fallback to 3307 is refused |
| Schedule | Every minute indefinitely, current Windows user Interactive/Limited, hidden action; machine awake and user signed in. No task credentials |
| Worker | Dedicated run_test_expiry.php calls the existing atomic lifecycle; generic run_expiry.php and .env untouched. --verify reads target/due count only |
| Overlap | Task IgnoreNew plus nonblocking file lock for manual/scheduled dedicated-worker runs. Generic CLI is outside that file lock; do not run competing workers during verification |
| Results | logs/test-expiry.jsonl: fixed target, UTC, invocation/status, counts, duration and numeric exit code only. Raw errors discarded. Partial committed count retained; next tick retries remaining requests |
| Data effects | Existing expiry rules only: due OPEN becomes EXPIRED, unfinished offers CLOSED, completed donations/pending reports preserved, pending confirmation blocked, no donation cooldown. Existing requester in-app notification and batch audit retained |
| Setup/removal | User-only Preview/Setup/Verify/Remove helper; no overwrite of existing tasks; ownership/action guard; removal disables first and refuses to kill a running transaction. Logs retained |
| Boundary | No migration, queue, standby, notification expansion or production target; separate approval required after successful testing |

Checks actually run once after code edits: PHP syntax for run_test_expiry.php PASS; PowerShell parse-only for both task scripts PASS, no script/task/database execution. Diff whitespace review PASS (existing line-ending warnings only). Windows task registration, actual repetition/identity/overlap, log writes and target/expiry SQL remain NOT RUN by agent.

Exact readiness/setup/verification/removal commands and expected results: [TEST_EXPIRY_SCHEDULER.md](TEST_EXPIRY_SCHEDULER.md). Prerequisites: current disposable test schema/reference data on 3306, PHP pdo_mysql, existing .env database credentials, signed-in Windows account and available Task Scheduler. Do not run fixture regression suites concurrently with the installed timer.

User sequence: run test runner --verify, Preview, Setup, wait awake/signed in for at least two ticks, Verify plus new sanitized scheduled records, final --verify, then Remove. Require actual recent Task Scheduler runs and at least two new completed records approximately one minute apart; zero-count runs prove dispatch only. Existing workflow_atomicity regression is documented as optional before installation, not marked passed by agent. Report safe metadata/results before any application/deployed target approval.

User result received: test target verification PASSED (bloodmatch_test:3306, invocation verify, status verified, exit_code 0, due_count 0 at 2026-10-09T11:03:12Z). Task registration/configuration and removal succeeded per supplied output: PT1M, Interactive, Limited, IgnoreNew. At inspection it had not run: LastTaskResult 267011 = 0x41303 (SCHED_S_TASK_HAS_NOT_RUN), sentinel LastRunTime 1999-11-30, next trigger 2026-10-09 19:04:18 local. No scheduled invocation record was supplied. This proves readiness/setup/removal, not automatic execution. [Microsoft status reference](https://learn.microsoft.com/windows/win32/taskschd/task-scheduler-error-and-success-constants).

Subsequent user verification: **PASS for automatic dispatch on the approved test target**. LastRunTime 2026-10-09 19:17:26 local, LastTaskResult 0; PT1M / Interactive / Limited / IgnoreNew. Sanitized scheduled records at 2026-10-09T11:16:26Z and 11:17:27Z (61 seconds apart) both show bloodmatch_test:3306, completed, exit_code 0, expired_count 0 (16/15 ms). User then successfully removed the task. These zero-count runs prove scheduled execution/target checks, not closure of an overdue fixture or contention behavior.

Remaining gaps: real overdue-fixture closure by the timer and forced-overlap behavior were not demonstrated by this output; existing lifecycle suites remain separately user-reported passed. Application/deployed target, signed-out availability and log retention policy require a separate decision. Next approval question: recommend preparing the same one-minute signed-in schedule for the Windows local application DB after confirming its exact name/port and expiry-write effects; alternatively keep scheduling test-only or prepare a deployed-server plan after OS/runtime details. Agent ran no task/database test, sent no email and changed no implementation in this result follow-up.

Summary-review steering: user requested a plan/status summary before proceeding; no implementation or application/deployed scheduler preparation is authorized by this review. Recommended next narrow checkpoint is now a test-only overdue-fixture scheduling/closure check on bloodmatch_test:3306, before any non-test target decision. Existing evidence remains two automatic runs 61 seconds apart, exit_code 0, both expired_count 0, successful task removal; scheduled overdue-request closure is still UNVERIFIED. The outstanding target approval question concerns preparing a local application schedule (exact DB name/port required), a deployed-server plan (OS/runtime/target required), or staying test-only; it is deferred, not a prerequisite to this summary. Migration 020 is prepared and agent-unapplied; user-reported suite passes do not independently establish its application/deployed migration state.

Current approved follow-up: **stay test-only** and prepare one disposable-fixture scheduled expiry check. Added tests/scheduled_expiry_fixture.php with fixed actual-target verification, SELECT-only check of migration 020's column shape plus ledger presence, and separate setup/assert/cleanup actions. No worker is invoked by this helper. Setup refuses preexisting overdue OPEN rows, creates one overdue request, four unfinished offers, a pending report and a historical completed donation in one transaction; saves exact token-owned IDs and synthetic safety/donation baselines plus scheduler-log offset, without credentials/password hashes. Existing worker file lock prevents setup/assert/cleanup races.

Assertions require fresh successful scheduled-run evidence with expired_count >= 1, correlate expiry time, then check EXPIRED, four CLOSED offers, unchanged pending report and completed records/safety anchors. Scoped officer queue retains the pending report; confirmation must return 409, blank reason must fail, and explicit reasoned rejection must persist into own history/audit without a new donation/cooldown. Corrected cleanup validates ownership and removes exact fixture rows while retaining all audit history and scheduler logs. No schema/backfill/application behavior changes.

Exact separate setup/scheduler/assertion/cleanup commands: [SCHEDULED_EXPIRY_FIXTURE_CHECK.md](SCHEDULED_EXPIRY_FIXTURE_CHECK.md). User first runs `php tests/scheduled_expiry_fixture.php check` (read-only); both column_ready and migration_020_recorded must be true before proceeding. Then Remove prior task, setup fixtures, Setup task, wait three minutes signed in/awake, capture Verify/log output, Remove task, assert once, cleanup. Expected 16 assertions passed / 0 failed, manifest removed and task absent. If any command fails, stop, remove the task and run the dedicated cleanup block; do not reset data or apply migration automatically.

Checks actually run for this follow-up: PHP syntax PASS on new fixture helper; git diff --check PASS with existing line-ending warnings only. No database check, fixture setup/assertion/cleanup, PowerShell suite or task installation/start was run by the agent. Migration 020 presence and scheduled overdue closure remain **UNVERIFIED until user output**. Forced overlap and browser rendering are outside this focused experiment. Application/deployed preparation and unrelated work remain deferred. Time spent chiefly on fixture ownership, baseline preservation and fresh scheduling evidence; lightweight checks/tool waits were brief. Report this checkpoint's outcome after the user's results.

User fixture result received: **16 assertions passed, 0 failed**. Scheduled run at 2026-10-09T11:55:53Z completed with expired_count 1, exit_code 0; following runs at 11:56:54Z and 11:57:54Z completed with zero expirations. Supplied metadata shows LastTaskResult 0, PT1M / Interactive / Limited / IgnoreNew. Task removal succeeded. Scheduled overdue closure, unfinished-offer cleanup, pending report retention/confirmation blocking/reasoned rejection, history/audit reason persistence and unchanged completed donations/safety anchors are now verified by supplied user output. Migration column readiness is supported by setup and rejection success, but the standalone read-only migration-check JSON/ledger result was not supplied explicitly.

Cleanup failed before fixture deletion: PDOException code 45000 at cleanup, matching migration 004's audit_log_block_delete trigger. The helper incorrectly attempted audit deletion. Removed that statement; all audit records are now retained, existing ON DELETE SET NULL clears fixture actor references, and cleanup asserts preservation of audit action/target/context/timestamps. No audit trigger, FK, permission or schema is changed. [MySQL foreign-key reference](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html) documents that cascaded FK actions do not activate triggers. Cleanup transaction rollback retains fixture IDs/manifest for safe retry. User should run only check (if supplying migration JSON) and cleanup; do not rerun setup/assertions/scheduling. Cleanup remains **PENDING USER RETRY**, not passed. Forced overlap, browser rendering and application/deployed scheduling remain outside this checkpoint. Agent executed no database test/task.

Final user results received: **cleanup PASS** — exact fixture rows removed; fixture audit history retained with cleared actor references; scheduler logs/all audit history retained; manifest Test-Path returned False. Standalone SELECT-only check returned database bloodmatch_test, port 3306, column_ready true, migration_020_recorded true. Migration 020's expected column and ledger presence are now **CONFIRMED ON THE TEST DATABASE by user-run read-only output**; the agent has not applied any migration. Earlier cleanup retry/migration uncertainty above is superseded by this result. All requested focused checks are complete; no rerun is required. Forced overlap and actual browser rendering were outside this experiment and remain unverified; application/deployed scheduling and unrelated safety fixes remain deferred. No code change, database test or task action was performed by the agent in this result follow-up.

Time consumed: this correction focused on tracing the audit trigger/FK and preserving audit history, then updating cleanup assertions/instructions; lightweight checks and tool waits were brief. Final result follow-up updated only this current progress entry. Earlier edits were preserved.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| database/run_test_expiry.php | Strict test target guard, overlap lock and sanitized results | BM-TEST-EXPIRY | Yes |
| scripts/run_test_expiry_task.ps1 | Hidden task worker wrapper, raw output discarded | Scheduler LastTaskResult | Yes |
| scripts/manage_test_expiry_task.ps1 | User-controlled task preparation/setup/inspection/removal | BM-TEST-EXPIRY-v1 | Yes |
| docs/TEST_EXPIRY_SCHEDULER.md | Exact user commands/setup/expected results and boundaries | bloodmatch_test | Yes |
| tests/scheduled_expiry_fixture.php | User-only read-only migration check and transactional fixture setup/assert/cleanup | BM-SCHEDULED-EXPIRY-FIXTURE | Yes |
| docs/SCHEDULED_EXPIRY_FIXTURE_CHECK.md | Focused scheduled-closure experiment and exact user commands | 16 assertions passed | Yes |
| docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | Only current checkpoint updated | BM-SAFETY-PHASE4B-TEST-SCHEDULE | Yes |

## Phase 4A checkpoint — 2026-10-09

Narrow scope: active request demand in the existing map, officer/admin dashboards, chapter comparisons and analytics OPEN count. Inspected AnalyticsRepository, DemandMapController and phase12 fixtures/dependencies only. No completed audit was repeated and no frontend, migration, expiry writer, standby, queue, notification or permission changes were made.

Implemented in AnalyticsRepository:

- Active demand requires stored OPEN and needed_datetime >= the current UTC cutoff, matching the established Home/deadline boundary. Each repository response captures one application UTC cutoff for all its active queries.
- Map counts, units, blood/urgency breakdowns, officer active metrics/blood demand, scoped/global admin active counts/units/chapter comparisons and analytics active OPEN counts exclude overdue OPEN rows even before the expiry writer runs.
- Historical totals, recorded terminal statuses, resolved-rate formulas, historical blood/urgency demand and date windows are preserved. Readouts do not mark a row EXPIRED or modify donations/offers/reports.
- Existing approved member map access, officer chapter scope, aggregate-only privacy, blood substitutions and chapter/distance donor ranking are untouched.

Tests: existing phase12 fixtures now mark their reserved accounts email verified, satisfying the established request-creation gate. Assertions were retained. Added D01-D12: move one two-unit routine/A+ fixture past its deadline while leaving it OPEN; check map/member/filter breakdowns and all affected active counters drop appropriately; historical totals/status remain unchanged; restore its future deadline in finally and verify demand returns. No expiry CLI/database suite was executed by the agent.

Checks actually run once after code edits:

| Check | Result |
|---|---|
| PHP syntax on AnalyticsRepository | PASS |
| PowerShell parse-only on phase12.ps1 | PASS; no suite execution |
| Diff/parameter-order review and git diff --check | PASS; line-ending warnings only |
| Local Windows scheduler metadata (read-only) | No matching BloodMatch/expiry-named or direct run_expiry.php action found. Wrapper/external scheduling remains UNVERIFIED. No task was installed or started; no action arguments/credentials were printed |
| Actual aggregate SQL/API/database assertions | NOT RUN by agent; user execution required |
| Frontend build / unrelated regressions | Not repeated; no frontend source changed |

User command from repository root, existing disposable bloodmatch_test/reference data on **3306**, XAMPP paths installed and **8001 free**:

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase12.ps1
```

Expected: zero failures, including D01-D12. The isolated runner writes only test fixtures and suppresses real SMTP. Return the summary/errors; the current changes are not marked database-verified based on earlier suite passes. No new migration is required.

Remaining: live SQL/binding and endpoint results await this test. Automatic expiry still needs proven scheduler execution; adding a task requires the existing infrastructure approval step. Map refresh cadence, donor-availability statistics, other analytics display mismatches, historical cleanup, non-response standby persistence and queued mail remain outside this checkpoint. No independent work from those phases was started.

Time consumed: primarily investigation of active-vs-historical query scopes and bound-parameter order, then implementation and focused regression authoring. Syntax/parse checks and scheduler metadata were brief; one sandbox startup failure required the already working execution fallback.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| backend/src/Repositories/AnalyticsRepository.php | Deadline-aware active readouts; historical totals preserved | needed_datetime >= ? | Yes |
| tests/phase12.ps1 | Valid email fixtures and 12 deadline regression assertions | D12 future OPEN fixture | Yes |
| docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | Current checkpoint, user-reported prior passes, checks and test command | BM-SAFETY-PHASE4A-ACTIVE-DEMAND | Yes |

## Phase 3B2 checkpoint — 2026-10-09

Authorized scope: consistent request locking across edit/cancel/expiry/respond/confirm; deadlines on edit/respond/report/confirm; unfinished-offer cleanup on cancellation/expiry/fulfillment; completed donations and pending reports retained. No standby, queue, notification expansion, ranking/exclusion change, new permission, scheduler installation, schema/data migration or live chat.

Source recheck found edit/cancel used unprotected read-then-write; expiry updated at most 500 rows and rediscovered them by shared expired_at timestamp; submit/confirm and staff queue checked stored OPEN without deadline. The checkpoint now:

- Routes edit/cancel through RequestLifecycleService with request -> sorted user IDs -> offer/report ordering, matching the existing workflows. Current actor authorization is rechecked under lock: owner edits only; active owner/admin/same-chapter officer can cancel, as before. No extra edit permission is granted to staff/admin.
- Guards all request field/status UPDATE statements with status='OPEN'; setStatus permits terminal transitions only. Confirmation fulfillment uses the guarded transition and shared cleanup. Closed requests cannot be reopened/overwritten by these paths.
- Centralizes deadline comparison in RequestService::hasDeadlinePassed and WorkflowLockService::assertOpenBeforeDeadline. Time is rechecked after lock waits and before edits/report insertion/confirmation. An overdue stored OPEN request cannot be extended by edit or used to respond/report/confirm. Existing exact-second equality boundary is retained (past means needed time < now). Cancellation remains governed by stored OPEN and current authorization, preserving its existing behavior; no new cancellation-priority rule was introduced.
- Shares closeUnfinishedForRequest for CANCELLED/EXPIRED/FULFILLED: POTENTIAL/NOTIFIED/RESPONDED -> CLOSED, COMPLETED untouched. Closure does not change reports or donor donation/availability windows. Pending reports stay visible to the existing scoped staff queue, cannot confirm on a closed/overdue request, and require the already approved reason for explicit rejection.
- Keeps matching generation in its existing separate request-locked transaction after edit commits. If cancellation/fulfillment/expiry wins before generation, generation refuses the terminal request; it cannot restore offers on that closed request. Compatible blood substitutions and chapter/distance scoring are unchanged.
- Makes expiry select candidate IDs in ascending bounded batches and recheck each under its own request/user locks. Each request's status, offer cleanup and existing requester in-app alert commit together. A generator drains beyond 500 with a fixed run cutoff and yields only committed rows, so partial failure reports the correct completed count and remaining OPEN rows can be retried on rerun. No old expired_at timestamp collision can select unrelated already-expired requests. No automatic repair/backfill of preexisting terminal rows is performed.
- Preserves existing requester-only cancellation/expiry notification event/channel/dedup behavior and the existing expiry batch audit. No new recipients, email, or event coverage was added. Existing best-effort audit limitations remain.

| Check actually run | Result |
|---|---|
| tests/request_lifecycle_offline.php | PASS: 22 pure deadline/boundary/role-policy checks; no DB connection |
| tests/donor_age_offline.php | PASS: 23 pure eligibility checks after shared deadline change |
| tests/workflow_lock_offline.php | PASS: 16 connectionless transaction/review-policy checks |
| PHP syntax | PASS on changed production files, expiry CLI, new pure test and extended deferred DB suite; follow-up service cleanup rechecked |
| PowerShell parser | Parse-only check on updated phase6 fixture script; no suite execution |
| git diff --check | PASS; line-ending normalization warnings only |
| Database suites / expiry CLI execution | NOT RUN by agent; all actual SQL locking/draining/persistence remains user-tested |
| Frontend build/browser | Not repeated: no frontend source changed in this checkpoint |

Deferred workflow_atomicity coverage now includes the prior 17 checks plus 23 lifecycle assertions (40 total): all four deadline paths; staff queue visibility; explicit closed-report rejection/reason without donation; cancel/expiry preserve confirmed records/anchors and retain pending history; cancel vs fulfill, edit vs cancel and expiry vs edit/respond/confirm; guards against late raw-repository edits/status changes; four forced request-lock waits crossing the deadline; expiry CLI backlog of 501; repeat-drain idempotency. Parallel PHP workers avoid the single-threaded development HTTP server. Agent ran php -l only on this database file.

The older phase6 fixtures did not mark request creators email verified, and its cross-chapter test mixed one officer's session with another's CSRF token (testing CSRF instead of scope). Fixture email verification and the matching officer session/token were corrected without removing assertions or weakening production rules.

User-run commands, from repo root, **existing disposable bloodmatch_test on 3306**, its reference data and approved migration 020 ready, XAMPP paths installed, free port 8001 and proc_open enabled. Do not reset/seed production. The isolated runner disables real SMTP and restores its process environment. The new workflow tests deliberately invoke the expiry CLI only against the inherited test DB, including 501 temporary fixtures.

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite workflow_atomicity.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase6.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_respond.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite rejection_reason.ps1
```

Expected: zero failures, workflow atomicity **40 passed**. Check final state invariants, not which actor wins a race. Return full summaries/errors before dependent work. Migration 020 is the previously approved rejection-reason prerequisite, not a new migration; if not yet ready, use the review/apply/test commands in [rejection-reason-migration.md](rejection-reason-migration.md). No migration or database suite was executed here.

Unfinished/unverified: real MySQL race/wait outcomes, expiry drain/persistence, related API regressions and migration readiness await user results. Installed scheduler execution remains UNVERIFIED and is outside this checkpoint; a callable draining CLI is not proof of automatic scheduling. Historical already-closed rows with stale offers are not backfilled; that would be a separate existing-data action. Existing-offer compatibility revalidation after material edits, availability/analytics accuracy, approved non-response standby persistence, queues and notification expansion remain later work. No such phase was started.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| backend/src/Services/RequestLifecycleService.php | Locked edit/cancel/per-request expiry and drain | expireDueRequests | Yes |
| backend/src/Services/RequestService.php | Shared deadline comparison | hasDeadlinePassed | Yes |
| backend/src/Services/WorkflowLockService.php | OPEN + deadline assertion | assertOpenBeforeDeadline | Yes |
| backend/src/Services/DonorEligibilityService.php | Respond uses shared deadline rule | RequestService::hasDeadlinePassed | Yes |
| backend/src/Services/DonationService.php | Submission/confirmation deadline checks | assertOpenBeforeDeadline | Yes |
| backend/src/Controllers/RequestsController.php | Delegate edit/cancel to locked service | new RequestLifecycleService | Yes |
| backend/src/Repositories/BloodRequestRepository.php | Guarded updates, keyset due IDs | dueRequestIds | Yes |
| backend/src/Repositories/MatchRepository.php | Shared unfinished-offer cleanup | closeUnfinishedForRequest | Yes |
| backend/src/Repositories/DonationReportRepository.php | Pending queue deadline projection | br.needed_datetime | Yes |
| backend/src/Controllers/DonationReportController.php | Disable overdue confirmation in queue | hasDeadlinePassed($r) | Yes |
| database/run_expiry.php | Drain and accurate committed failure counts | committed expirations | Yes |
| tests/request_lifecycle_offline.php | 22 pure boundary/permission checks | offline lifecycle checks passed | Yes |
| tests/workflow_atomicity.php | 23 new deferred lifecycle assertions | L15 expiry CLI drains | Yes |
| tests/phase6.ps1 | Valid email fixtures; genuine chapter denial | $offB.s | Yes |
| docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | Scoped checkpoint, checks and commands | BM-SAFETY-PHASE3B2-LIFECYCLE-2026-10-09 | Yes |

Actual test DB port: **3306**, confirmed by user. This overrides the older guide's 3307 assumption for subsequent commands. No environment file was changed.

This is the staged remediation record for [the audit](BLOODMATCH_FLOW_SAFETY_AUDIT.md). Findings are rechecked against current source before changes. The original audit is a dated snapshot, not proof that every finding remains current. Live chat is outside this task.

## Approval gates - current chat alignment above takes precedence

| Decision | State | Approved behavior / pending details |
|---|---|---|
| Location/chapter | APPROVED by user's fix request | Ranking only; farther/cross-chapter compatible donors remain eligible. Preserve blood substitutions; do not change compatibility-tier ordering without approval. |
| Non-response standby | APPROVED behavior and additive preparation; execution user-only | Donor enters standby after 42 hours without responding to a notified opportunity. In-app commit anchor, per-opportunity counting/interactions and explicit acknowledgment/recovery approved; retire consumed timeout events to prevent immediate retrigger. Retain independent post-donation safety windows. Present migration/impact before applying it. |
| Closed requests | APPROVED | CANCELLED/EXPIRED/FULFILLED closes all uncompleted offers. Pending reports remain in history, cannot be confirmed, and require explicit authorized officer/admin rejection with a reason. Closure records no donation and starts no post-donation cooldown. |
| Rejection reason persistence | PREPARATION/API/UI APPROVED; execution is user-only and pending | User approved nullable VARCHAR(500), nonblank 1-500 character new reasons, authorized history/audit display, existing NULL/status preservation. Migration 020 and test-only review/apply helper prepared; agent has not applied it. |
| Requester permissions / multiple responses | PRESERVE APPROVED BEHAVIOR | Current permissions and multiple active responses remain. Notification changes grant no new permission or one-response restriction; any future rule change requires separate approval. |
| Notification recipients/channels | APPROVED as amended in current chat | In-app lifecycle coverage; only Emergency donor opportunities and membership decisions queue email. Historical proposed email expansion is superseded. |
| Email queue / scheduler / schema / old data | STAGED approvals; see current alignment | 021/enqueue/manual fake worker and 022 preparation approved. Application/deployed rollout, further task infrastructure and historical data changes remain separate gates. |
| Member-email visibility | UNCHANGED | Current CONTEXT section 9.8 amendment explicitly approves member email visibility. Do not treat its mere presence as an automatically authorized privacy removal. |
| Encryption / dependencies / storage moves | PENDING | Restore cache protection now; no new encryption scheme or storage migration without approval. |
| Management/report expansion | PENDING | Do not add directories, management pages, downloads or reports without approval. |

Historical non-response description (prepared follow-up now exists; generation supersession is resolved in the current approval alignment): anchor at committed in-app opportunity notification; count unanswered POTENTIAL/NOTIFIED opportunities only while request OPEN and before deadline; earliest overdue opportunity triggers standby, responses resolve only their own opportunity; terminal requests no longer count; a genuinely new material generation starts its own clock and supersedes older unanswered generations for that donor/request, retaining history. Explicit donor acknowledgment allows recovery, retiring overdue timeout events while preserving post-donation/account restrictions. Additive 022 timer/state preparation is approved in the current chat; application is user-only, with no backfill. Additional or application/deployed schema changes require their own scope. User approved both timing and recovery on 2026-10-08; do not ask those same questions again.

## Phased plan

Each phase receives its own source review, focused tests, lightweight validation, and result report. PowerShell/database suites are executed by the user only. Dependent database work waits for required user-run results.

| Phase | Expected behavior | Planned affected files | Checks / gates |
|---|---|---|---|
| 1: document caching and response UX | No cached sensitive documents; Home confirms before sending; Matches obeys server eligibility and can respond by request ID without candidate row; structured errors retained | DocumentController.php; FeedCard.jsx; ConfirmationDialog.jsx; MatchesPage.jsx; apiClient.js; home_ui.mjs; request_ui_static.mjs; api_error_offline.mjs; audit/progress | PHP syntax, frontend build, focused offline tests; user-run Home/document regressions; no DB/schema/business rule changes |
| 2: live donor age/consent | Match/feed/respond reject underage/missing DOB/consent after enrollment; birth-date/consent changes update eligibility without destroying history; far/cross-chapter compatibility remains | DonorEligibilityService.php; AgeEligibilityService.php if needed; MatchService.php; UserRepository.php; DocumentRepository.php; ProfileController.php; focused backend tests | Existing age policy preserved; syntax/offline tests where feasible; user-run fixtures for adult-to-underage/null DOB, minors with/without consent, compatibility/ranking; no enrollment-data rewrite without approval |
| 3: atomic transitions | Shared consistent request/donor/match/report lock order; current donor checked under lock; serial cancel/edit/respond/confirm/withdraw/report; no duplicate pending reports | RequestsController.php; MatchesController.php; MatchResponseService.php; DonationService.php; BloodRequestRepository.php; MatchRepository.php; DonationReportRepository.php; UserRepository.php; focused concurrency suites | User-run concurrent action tests; approved closure semantics; request permissions/multiple responses unchanged pending decision; migration approval if reason/constraint storage required |
| 4: expiry and accurate active surfaces | Drain all due requests with bounded batches; deadline-aware active map/action queries; close uncompleted offers; block confirmation of closed pending reports; retain reasons/history | database/run_expiry.php; shared request lifecycle service if justified; request/match/report repositories; AnalyticsRepository.php; AnalyticsView.jsx; availability DTO/UI; scheduler documentation | User chooses/install schedule before new infrastructure; user verifies task configuration and >500 overdue cases; no production job invocation by agent |
| 5: durable notifications/email | Creation commits quickly without SMTP; durable jobs, retry/failure visibility; approved recipient/channel coverage; no duplicate queued event; no stale emergency outreach after closure | NotificationService.php; NotificationRepository.php; Mailer.php; proposed outbox repository/worker/migration; lifecycle/verification/account event calls | Queue/schema/scheduler/matrix approval first; mocked delivery checks; user-run isolated outage/retry/dedup tests; no real emails |
| 6: rule alignment and remaining accuracy | Approved 42-hour behavior implemented with independent cooldown; current offers revalidated after material edits; source/docs agree; availability/analytics fields corrected | approved timeout service/CLI/schema; MatchService.php; request edit path; analytics/auth DTO/UI; requirements/CONTEXT/traceability/audit/progress; focused tests | Timer/recovery, offer treatment, actual-transition alerts and 022 preparation approved; migration execution/user-run integration still required. Latest-generation supersession and unlimited bounded Emergency enqueueing are resolved; browser verification remains separate |

Not every historical claim is a current bug: member-email visibility has an explicit October 6 amendment; ordinary/urgent request-alert email is intentionally disabled by current CONTEXT section 9.9; location exclusion contradicts the user's explicit ranking decision. These remain decision/documentation questions rather than unapproved code changes.

## Phase 1 source verification and implementation

Before editing, read requirements, audit, `.agents/plan/AGENTS.md`, `docs/ai_context/AGENTS.md` and relevant CONTEXT business rules, design-system instructions, DESIGN.md/tokens, existing shared dialog, response APIs, documents/storage/routing and existing offline test fixtures. No environment values were read.

Verified against current code:

- DocumentController still removed the global no-store header at streaming time. Storage resolves random validated names under `backend/storage/documents`, outside intended `backend/public`; own/staff endpoints still check owner, role, chapter and audit access. Actual deployed web root/ACLs/encryption are unverified.
- FeedCard still posted immediately; existing shared ConfirmationDialog provides focus/inert handling, keyboard dismissal, busy state and token-based presentation. Reused it; compact card CSS/design unchanged.
- Matches still gated its response action on candidate match status, ignoring request.can_respond, and could not offer as an eligible browser without a persisted match. It now follows server eligibility and uses the existing request-ID endpoint. Legacy match-ID endpoint remains untouched.
- Response API emits error.code/date, while apiClient retained only error.details. Both root eligibility fields and legacy details now remain available.

Changes:

| File | Change | Search marker |
|---|---|---|
| backend/src/Controllers/DocumentController.php | Explicit private/no-store/max-age=0, Pragma no-cache and Expires 0 while streaming | `private, no-store, max-age=0` |
| frontend/src/components/FeedCard.jsx | Shared confirmation; no request on open/cancel; synchronous repeat guard; stale eligibility blocks confirmation; refetch on response outcome | `confirmingResponse` |
| frontend/src/components/ConfirmationDialog.jsx | Optional confirmDisabled prop, default false; existing users retain behavior | `confirmDisabled = false` |
| frontend/src/pages/MatchesPage.jsx | Server can_respond gating and reason/date; request-ID response; repeated-click guard; read-only staff without donor setup advice | `const canRespond =` |
| frontend/src/services/apiClient.js | Preserve root code/date plus legacy validation details for normal/upload errors | `error.eligibleAgainAt` |
| tests/home_ui.mjs | Offline actual callback checks: confirm/cancel, request-ID mock, rapid repeat and stale disable; existing design/filter checks retained | `Stale confirmation cannot submit` |
| tests/request_ui_static.mjs | Eligible browser, stale NOTIFIED donor, staff read-only render cases | `Stale NOTIFIED match obeys server eligibility` |
| tests/api_error_offline.mjs | Fully mocked fetch for root eligibility, legacy validation, upload errors | `PASS 3 offline error checks` |

Validation ledger:

| Check | Execution / result |
|---|---|
| PHP syntax on DocumentController | Ran: passed; does not exercise document HTTP headers |
| node tests/home_ui.mjs | Ran: final checks passed, including confirmation/cancel/rapid repeat/stale eligibility with mocked response API and preserved filters/pagination/card rendering |
| node tests/request_ui_static.mjs | Ran: final 17 checks passed, including eligible browser, stale blocked match and staff read-only behavior |
| node tests/api_error_offline.mjs | Ran: 3 passed, every fetch mocked |
| npm.cmd run build (frontend) | Ran: final build passed (Vite 5.4.21). Warning: main JS bundle about 560 kB (>500 kB); no unrelated bundle refactor in this phase |
| git diff --check | Ran: passed; Git reports line-ending normalization warnings only |
| Phase 1 PowerShell/database suites | USER-REPORTED PASS: home_safety.ps1, home_respond.ps1, phase5.ps1, all without failures, on port 3306. Agent did not run them or receive assertion counts/full output |
| Browser keyboard/modal, actual document response headers | NOT VERIFIED by offline rendering/build; user manual check required |
| Deployment storage/ACL/TLS/encryption | UNVERIFIED; no storage moves/encryption/dependencies introduced |

The final shared-dialog disabled state and staff setup suppression were added after reviewing first check results. This is a concrete reason to repeat only affected Home/request checks and the build; the unchanged API error check/PHP syntax need not repeat.

## User-run Phase 1 checks

Run from repository root. Prerequisites: existing disposable `bloodmatch_test` with current migrations/reference data, XAMPP PHP/MySQL at paths accepted by `run_isolated.ps1`, MySQL on the specified port, free TCP port 8001, installed frontend dependencies for the offline checks. No reset/seed instruction is included. The isolated runner writes fixtures to the test DB, sets SMTP to a closed local port, restores process environment and stops its API afterwards. It must never target production.

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_safety.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_respond.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase5.ps1
```

The user confirmed port 3306 and reported these Phase 1 suites passed with no failures. Existing suites do not prove live browser confirmation or deployed cache/storage security. Do not treat the newly extended Phase 2 home_respond.ps1 as already passed: its 11 added assertions were not present in that user run.

Manual UI/HTTP checks using local test accounts: Home disclosure/card remains compact; Respond opens confirmation, Not now sends no response, Confirm submits once, ineligible and staff actions disabled; Matches browser can offer without prior candidate; stale blocked match shows server reason/date. On an authorized ID response, Network response headers must show `Cache-Control: private, no-store, max-age=0`, `Pragma: no-cache`, `Expires: 0`; member cannot retrieve another member's ID, officer cannot retrieve other-chapter ID. Return observations; do not share document contents, cookies or secrets.

Phase 1 database regression gate is satisfied by the user's report. Browser/header/deployment verification is still separate. Closed-request/timer/queue changes are not included in Phase 1.

## Phase 2: current age/consent eligibility

Marker: `BM-SAFETY-PHASE2-LIVE-AGE`.

Source recheck confirmed enrollment applied AgeEligibilityService, but the shared donor evaluator ignored it. UserRepository's authenticated actor and MatchService's candidate pool did not both project consent, and profile DOB changes / consent uploads did not refresh candidates.

Implemented:

- Shared evaluator now requires current DOB/age and, for ages 16-17, a parental_consent document. Under 16, missing/invalid/future DOB returns age_ineligible; minor without consent returns parental_consent_required. Membership, email, enrollment, account, availability, windows, compatibility and request rules remain enforced.
- Consent signal is computed from member_documents using EXISTS in authenticated-user and candidate-pool SQL, rather than from request JSON. No extra per-card/per-donor document query and no migration are introduced. Existing (user_id,doc_type) index supports the lookup.
- AgeEligibilityService rejects future legacy DOB and uses DateTime's completed-years calculation, retaining existing server-calendar timezone and age thresholds. No new timezone policy was introduced.
- Profile DOB changes and successful parental-consent uploads use existing donor reconciliation for unanswered matches. No enrollment/history backfill/reset, notification channel expansion, location exclusion or blood-tier reordering.
- Existing RESPONDED/COMPLETED and CLOSED history remains intact under scoped reconciliation. A corrected eligible donor can still respond by request ID, including a previously CLOSED candidate. Respond does not accept an enrollment flag as proof of current age/consent.

| Check | Result |
|---|---|
| `C:\xampp\php\php.exe tests\donor_age_offline.php` | AGENT-RUN PASS: 23 pure checks; matrix loaded from local seed into process cache; no Env::load, database, HTTP or email |
| PHP syntax on changed six production PHP files | AGENT-RUN PASS after correcting an initially detected SQL string quoting error |
| PowerShell parser on tests/home_respond.ps1 | AGENT-RUN PASS, parse-only; no suite execution |
| git diff --check | AGENT-RUN PASS before final documentation updates; line-ending warnings only |
| Extended home_respond.ps1 | USER-REPORTED PASS: user reported all Phase 2 tests passed; includes A01-A11. Agent did not execute suite or receive assertion counts/full output |
| Home safety, donor refresh, verification/document regressions after Phase 2 | USER-REPORTED PASS: home_safety.ps1, donor_refresh.ps1, phase5.ps1 on port 3306 |

Phase 2 database gate is satisfied by the user's report. At Phase 2 completion, donor was still loaded before the response transaction; Phase 3A below addresses this. Reconciliation still caps at 100 requests and preserves offered/completed rows; existing-offer treatment after eligibility/material changes remains a later approved-policy step. Consent remains the established document-presence policy, not a new officer consent-approval workflow. Existing notification policies remain unchanged. No schema, production data, credentials, environment, live chat or SMTP operation was changed.

User-run Phase 2 commands (same existing disposable bloodmatch_test/reference data, port 3306, free 8001, no reset/seed):

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_respond.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_safety.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite donor_refresh.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase5.ps1
```

Expected: all suites report zero failures; home_respond includes A01-A11. Underage/null-DOB donors remain visible in compatible discovery but cannot respond; stored minor consent permits response/matching; distant/cross-chapter O- donor still matches AB+; existing histories/enrollment persist. Minor integration fixtures model consent-document presence in the test DB and remove them during fixture cleanup; no physical file or real email is sent by the added assertions. Actual multipart consent-upload reconciliation still merits a local test-account check after uploading a consent file; the baseline document suite validates upload but is not proof of candidate-refresh timing. Return suite output before dependent lock/lifecycle work.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| backend/src/Services/AgeEligibilityService.php | Reject future DOB; completed-years calculation | $birth > $today | Yes |
| backend/src/Services/DonorEligibilityService.php | Current age/consent guard | parental_consent_required | Yes |
| backend/src/Repositories/UserRepository.php | DB-derived consent on actor | AS has_parental_consent | Yes |
| backend/src/Services/MatchService.php | DOB/consent in candidate pool | AS has_parental_consent | Yes |
| backend/src/Controllers/ProfileController.php | DOB reconciliation trigger | donor_birth_date_change | Yes |
| backend/src/Controllers/DocumentController.php | Consent reconciliation trigger | donor_parental_consent_change | Yes |
| tests/donor_age_offline.php | 23 pure safety checks | offline donor age/consent checks passed | Yes |
| tests/home_respond.ps1 | 11 deferred integration assertions | A11 matching reads stored parental consent | Yes |

## Phase 3A: donor/offer/report serialization

Marker: `BM-SAFETY-PHASE3A-LOCKS`.

Phase 3 is split into 3A (response, withdrawal, report, confirmation, generation) and 3B (edit/cancel terminal closure and rejection-reason history). Phase 3A requires no migration. The user subsequently approved preparing the rejection_reason column/API/UI in Phase 3B1 below; actual migration execution remains user-only.

Source recheck: response used a pre-transaction donor snapshot; submit and withdraw checked then wrote without a shared lock; confirmation locked joined report/request/match rows in a database-dependent order and did not verify locked match RESPONDED; generation could overwrite a concurrent match completion. Other locked-repository callers were traced before changing their return shapes.

Implemented:

- WorkflowLockService owns begin/commit/rollback only when it starts a transaction; donor reconciliation retains ownership of its existing transaction. Lock order for donor/report actions is request row -> user IDs ascending -> match -> report. Locked reads use explicit base tables rather than joins that implicitly lock in an unknown order.
- Respond re-loads current donor DOB/consent/account/role/availability/donation anchor under user lock and evaluates time after lock waits. Request/donor uniqueness and existing request-ID/legacy endpoint behavior remain. Requester is locked for consistent notification/FK writes, without imposing a new requester role/account eligibility rule.
- Submit locks current request/actor/match, rechecks owner/active/request OPEN/match RESPONDED, then performs a locking pending-report read and insert. Concurrent API submissions cannot both create pending reports through this path; no schema constraint or old-data deletion was added.
- Withdraw uses the same lock order and locking pending-report read. Either withdrawal wins before report creation or report creation wins and blocks withdrawal; they cannot both commit inconsistent states.
- Confirmation/rejection locks request/users/match/report and rechecks current reviewer role, active state, chapter and self restriction. Confirmation additionally requires the locked match to remain RESPONDED. A second legacy pending report for a COMPLETED match cannot re-confirm it or move its cooldown anchor. Successful audit writes participate in the same transaction; AuditLogger's best-effort failure policy remains a separate gap.
- Confirmation locks donor before updating the anchor/standby, so responses on other requests re-load the resulting window. Existing multiple-response permission and treatment of previously submitted reports on different requests are not narrowed. New cross-request offer cancellation or donation restrictions still require a decision.
- Matching generation now holds its request lock in a transaction, including when nested in donor reconciliation. It cannot rewrite a match while a confirmation on that same request holds its request lock. Blood substitutions, ranking, generation/dedup/channel rules remain unchanged.
- Database deadlock/lock-wait conflicts (1205/1213) roll back and return retryable 409 when WorkflowLockService owns the transaction. No automatic retry replays existing SMTP side effects. This reduces inconsistent writes, not a guarantee that deadlocks can never occur.

Validation:

| Check | Result |
|---|---|
| tests/workflow_lock_offline.php | AGENT-RUN PASS: 16 connectionless PDO-double/policy checks (ownership, nested commit/rollback, conflict mapping without retries, current review permissions, terminal status guards). Initial test-counter closure capture was corrected before final pass |
| PHP syntax | AGENT-RUN PASS: changed production classes and both new PHP test files |
| PowerShell parse-only | AGENT-RUN PASS: workflow_atomicity.ps1 and corrected phase8.ps1; neither suite executed |
| git diff --check | AGENT-RUN PASS; line-ending warnings only |
| tests/workflow_atomicity.ps1 / .php at Phase 3A | USER-REPORTED PASS: user reported all Phase 3A tests passed; agent did not run them or receive full summaries/counts |
| Existing response/donation/refresh suites at Phase 3A | USER-REPORTED PASS: home_respond.ps1, phase8.ps1, donor_refresh.ps1 on port 3306. New Phase 3B1 changes need fresh related tests after migration |

Focused database suite: workflow_atomicity.php refuses any resolved DB name other than bloodmatch_test, creates token-marked disposable users/requests, and invokes direct services plus parallel PHP CLI workers (avoiding the single-threaded dev HTTP server). It tests stale donor snapshots, concurrent duplicate reports, report/withdraw invariants, legacy duplicate pending report confirmation, current cooldown across requests, parallel quota confirmations, generation vs confirmation, and terminal-request confirmation denial without a donation anchor. Workers inherit the isolated runner's environment; fixture recipients are reserved .test addresses, ordinary requests, and SMTP is disabled by the runner. Cleanup deletes only created fixture IDs and their related notifications. proc_open must be available in test PHP. Database tests are intentionally user-run; only php -l was run on this file by the agent.

The phase8 donation suite's old fixture helper omitted email_verified_at despite testing eligible donors. Its fixture SQL now marks those reserved test accounts email verified, preserving current production email eligibility rather than weakening it. Assertions are retained. This fixture correction is not a user-data backfill.

User-run commands, from repository root with existing disposable bloodmatch_test/reference data, MySQL 3306, free port 8001 and XAMPP paths:

```powershell
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite workflow_atomicity.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_respond.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase8.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite donor_refresh.ps1
```

Expected: zero failures; new atomicity suite reports 17 passing checks (W15 runs for each terminal status). Each parallel worker pair must preserve its asserted final state; no duplicate pending report from normal submission, no withdrawal with a pending report, no second completion for the same match, and fulfillment after two legitimate completed units. The script does not prove every possible interleaving; repeat/load experiments may later be needed if new failures arise. Return results before dependent Phase 3B work.

Remaining limitations in this step:

- Edit/cancel/expiry paths are not yet moved into the common locked transition path. Phase 3A is not a claim that all request races/terminal-offer cleanup are solved.
- Stored OPEN checks for report/withdraw/confirmation are preserved; deadline consistency and draining expiry are Phase 4 work. Response already checks deadline after locks.
- Rejection reasons/history were deferred in this 3A step and are now prepared in Phase 3B1 below, awaiting user application/test results. Existing notification recipients/channels remain unchanged.
- Existing synchronous generation email may still hold the request lock while sending and block creation; the pending queue proposal is required to remove that operational limitation. No queue or worker has been installed, no retry/send behavior or recipient/channel expansion was introduced.
- Actual MySQL FK/lock behavior, storage, deployment and SMTP delivery are not verified by syntax/offline tests. No real email, production-data change, migration, dependency, environment change or live chat was performed.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| backend/src/Services/WorkflowLockService.php | Transaction ownership/ordered locks/conflicts | Request -> user IDs ascending | Yes |
| backend/src/Services/MatchResponseService.php | Fresh locked donor and atomic withdrawal | Evaluate after all lock waits | Yes |
| backend/src/Services/DonationService.php | Atomic submit/decision, locked RESPONDED guard | Only a responded match can be confirmed | Yes |
| backend/src/Services/MatchService.php | Serialize generation on request | generateLockedForRequest | Yes |
| backend/src/Repositories/UserRepository.php | Fresh locked donor/consent projection | findByIdForUpdate | Yes |
| backend/src/Repositories/BloodRequestRepository.php | Complete base request locked read | SELECT * | Yes |
| backend/src/Repositories/MatchRepository.php | Match-only locked read | SELECT * FROM matches | Yes |
| backend/src/Repositories/DonationReportRepository.php | Report-only/pending locking reads | pendingExistsForMatchForUpdate | Yes |
| backend/src/Controllers/MatchesController.php | Delegate withdrawal to transaction service | ->withdraw($actor | Yes |
| tests/workflow_lock_offline.php | 16 pure contract checks | offline workflow contract checks passed | Yes |
| tests/workflow_atomicity.php / .ps1 | Deferred direct-service/parallel DB suite | W14 generation cannot undo | Yes |
| tests/phase8.ps1 | Current email-verified donation fixtures | donation fixtures represent email-verified | Yes |

## Phase 3B1: approved rejection reasons (prepared, migration not applied)

Marker: `BM-SAFETY-PHASE3B1-REJECTION`.

User explicitly approved preparation of `donation_reports.rejection_reason VARCHAR(500) NULL`, requiring a nonblank reason of up to 500 characters for new rejections and displaying it in authorized history/audit. Existing reason values stay NULL and statuses stay unchanged. No permission for the agent to execute migration/database tests is inferred from this approval.

Prepared:

- Migration 020 contains only the additive ALTER, with no backfill/update/delete. [Review and exact execution/test commands](rejection-reason-migration.md) are ready. The new CLI helper defaults to review only, fixes its target to bloodmatch_test/3306, checks actual resolved DB/port and column shape, and applies only 020 when the user supplies --apply. Existing IDs/statuses and initially NULL old reasons are verified before ledger recording; no reset or generic all-pending migration run.
- Rejection validation is centralized in DonationService and runs after staff/scope/self authorization. Non-string/invalid UTF-8/blank/format-only/over-500 reasons produce field-specific 400. New accepted text is trimmed at outer whitespace/format marks, preserves internal lines, counts Unicode characters, persists with reviewer/time under the existing locked decision, and is recorded as rejection_reason in the protected audit event.
- Existing generic donation rejection notifications retain their recipient/channel/body; reason text is not newly copied into notifications/email. Own report history adds rejection_reason; another member's profile remains limited and contains no reports/history. Legacy NULL is shown honestly as “No reason recorded,” with no fabricated explanation.
- Officer Confirmations opens a required, accessible reason dialog, blocks blank/overlength/duplicate submissions, retains typed reason on error and disables self-review and closed-report confirmation according to new queue capabilities. Shared dialog focus trapping now includes inputs/selects/textareas/links. Home compact cards and unrelated management/report surfaces are unchanged.
- Pre-migration own history projects NULL rather than failing on a missing column. A new rejection cannot commit if exact nullable VARCHAR(500) storage is unavailable: it returns 503 and rolls back, preserving PENDING. This compatibility path is source-reviewed, not live-DB tested by the agent.

| Check | Result |
|---|---|
| tests/rejection_reason_offline.php | AGENT-RUN PASS: 19 validation/additive-SQL review checks; no migration execution |
| tests/rejection_ui_offline.mjs | AGENT-RUN PASS: 25 validation/render/callback checks; all API calls mocked |
| tests/request_ui_static.mjs | AGENT-RUN PASS: 19 checks including escaped own rejection text, legacy fallback, other-member history privacy |
| PHP syntax / PowerShell parser | AGENT-RUN PASS on changed PHP and deferred scripts; new CLI guard rechecked separately |
| Frontend build | AGENT-RUN PASS; existing >500 kB JS bundle warning remains |
| Browser tool | Could not initialize (sandbox helper setup error); actual keyboard/layout verification remains user-manual |
| Review/apply helper against DB | NOT RUN by agent; user commands in migration document |
| rejection_reason.ps1 and related DB suites after 3B1 | NOT RUN by agent; require schema readiness and user execution |

The new user-run rejection suite has 29 focused checks covering validation without mutation, role/chapter/self rules, storage/reviewer/time, own history, protected scoped audit, no cross-member history/profile exposure, repeat decision, terminal-request pending reports, legacy NULL preservation and the 500-Unicode-character boundary. Existing phase8 rejection assertion now submits a reason; no assertions were removed. Workflow atomicity remains a useful confirmation/report regression after applying 020, but its earlier user pass does not mark the changed code as freshly verified.

Phase 3B1 is complete as a prepared artifact/source change with lightweight validation. Wait for user review/application/test results before dependent Phase 3B2 request edit/cancel closure changes. Automatic expiry, historical offer cleanup, all-lifecycle notifications, non-response standby persistence and queued mail remain separate pending work. No production migration, database mutation/test, seed/reset, real email, dependency, environment modification or live chat was performed by the agent.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| database/migrations/020_donation_report_rejection_reason.sql | Approved additive nullable column | ADD COLUMN rejection_reason VARCHAR(500) NULL | Yes |
| database/apply_rejection_reason_migration.php | Test-only default-review/single-file apply tool | Review only; no writes | Yes |
| backend/src/Services/DonationService.php | Validate/store/audit reason | normalizeRejectionReason | Yes |
| backend/src/Repositories/DonationReportRepository.php | Nullable history projection and safe rejection write | supportsRejectionReason | Yes |
| backend/src/Controllers/DonationReportController.php | Reason payload/errors/history; queue capabilities | can_reject | Yes |
| frontend/src/pages/officer/views/ConfirmationsView.jsx / .module.css | Reason dialog and capability-safe actions | Rejection reason | Yes |
| frontend/src/services/rejectionReason.js | Shared frontend normalization/limits | rejectionReasonError | Yes |
| frontend/src/components/ConfirmationDialog.jsx | Focus trap includes reason textarea | focusableSelector | Yes |
| frontend/src/pages/ProfilePage.jsx / .module.css | Escaped own reason/legacy fallback | No reason recorded | Yes |
| tests/fixtures/rejection_reason_cases.json | Shared boundary/type/Unicode cases | 500 Unicode characters | Yes |
| tests/rejection_reason_offline.php / tests/rejection_ui_offline.mjs | Pure validation, markup and mock callbacks | offline rejection | Yes |
| tests/rejection_reason.ps1 | Deferred 29-check API/DB suite | Rejection reasons: | Yes |
| tests/request_ui_static.mjs / tests/phase8.ps1 | Authorized history/privacy and valid rejection payload | rejection_reason | Yes |
| docs/rejection-reason-migration.md / database/migrations/README.md | Review/apply/test/rollout documentation | 020_donation_report_rejection_reason.sql | Yes |

## Historical email queue proposal - superseded by current approvals

Recommended: a MySQL outbox and PHP CLI worker invoked every minute by Windows Task Scheduler/cron, using existing PHP/MySQL rather than Redis or a new service dependency. New notification email jobs are inserted transactionally with their business event; creation does not call SMTP. Worker claims bounded jobs with durable leases, retries transient failures with backoff (proposed 1, 5, 15, 60, 240 minutes), records attempts/sanitized errors and a terminal failed state. Interrupted leases recover. Emergency opportunity jobs recheck current request/deadline/donor eligibility before send and stop on closure. Normal rate-limit deferrals are rescheduled rather than lost; emergency priority is maintained.

Proposed new email_outbox table contains notification/event reference, unique delivery key, status, attempts, next_attempt_at, lease metadata, last_error, timestamps; migration creates structure only, with no automatic old-notification backfill. Applying migration/installing task/running workers requires approval. Existing failed emails require an explicit, scoped backfill decision. SMTP cannot guarantee exactly-once inbox delivery across a send-success/process-crash boundary; use stable Message-ID, dedup/lease and honest at-least-once failure semantics rather than claiming a transaction covers an external mail server.

OTP/password-reset job content needs a separate approved secret-handling/TTL design because storing plaintext codes/tokens in an outbox changes security exposure. Start with notification-backed emergency/status emails; retain auth mail behavior until that separate decision. This is a staged proposal, not a claim that all email recovery is solved.

## Historical lifecycle proposal - current chat allowlist overrides this matrix

Emails only for verified recipients. No contact documents/phone/DOB in notification content. Officer recipients remain request/member chapter scoped; administrators receive relevant escalation/system review, not indiscriminate donor contact. All new coverage below is pending approval.

| Event | Proposed recipient | In-app | Email proposal |
|---|---|---|---|
| Compatible Routine/Urgent opportunity | Currently eligible donors | Yes | No; retain established policy |
| Emergency opportunity | Currently eligible compatible donors across chapters, ranked; do not introduce location exclusion | Yes | Queued; recheck current eligibility/deadline |
| Verification ID upload/resubmission | Assigned chapter officers + active admins; member gets resubmission acknowledgment | Yes | Staff no; member status normal email |
| Verification approved/rejected | Reviewed member | Yes | Queued normal |
| Account deactivate/reactivate; role/chapter change | Affected member/account | Yes | Queued normal |
| Donor response | Requester | Yes | Queued only for Emergency; retain current response channel policy |
| Donor withdraws / offer becomes invalid after material edit | Requester and affected donor | Yes | No by default; requester Emergency alert proposed |
| Donation report submitted | Assigned request-chapter officers; admin escalation only if no assigned active officer | Yes | No by default |
| Donation confirmed/rejected (including report on closed request) | Reporting donor; requester for confirmation relevant to its request | Yes | Donor normal; requester normal on fulfillment only |
| Request fulfilled/cancelled/expired | Requester and donors with unresolved active offers or pending reports | Yes | Queued normal; cancel pending emergency opportunity jobs |
| Non-response standby/recovery | Donor | Yes | Queued normal; timer/recovery approval first |
| Post-donation window ends | Donor | Yes proposed | No by default; requires a scheduled transition/event decision |

Do not add this coverage until the user approves recipients/channels, generation/withdraw re-response dedup semantics, and queue design. Likewise, exact historical rejection reason column/UI changes will be presented before a migration.

## Historical remaining-work snapshot

Phase 2 and 3A integration were reported passed by the user. Phase 3B1 rejection reason migration/API/UI and Phase 3B2 request lifecycle changes are prepared with lightweight validation; migration readiness and current database/concurrency results await the user. Remaining work: verified expiry scheduling, any separately approved historical offer cleanup, non-response standby persistence/recovery, queues/notifications, material-offer reconciliation, availability/analytics accuracy and requirement alignment. No schema migration execution, data modification, scheduler install, encryption, real email, new dependency, management expansion or live chat has been performed by the agent.

| File | What changed | Distinctive string to search for | Found? |
|---|---|---|---|
| backend/src/Controllers/DocumentController.php | Private cache protection | private, no-store, max-age=0 | Yes |
| frontend/src/components/FeedCard.jsx | Home confirmation and repeat guard | confirmingResponse | Yes |
| frontend/src/components/ConfirmationDialog.jsx | Optional disabled confirmation | confirmDisabled = false | Yes |
| frontend/src/pages/MatchesPage.jsx | Server response gating/request-ID flow | const canRespond = | Yes |
| frontend/src/services/apiClient.js | Eligibility error code/date | error.eligibleAgainAt | Yes |
| tests/home_ui.mjs | Mocked confirmation callbacks/stale checks | Stale confirmation cannot submit | Yes |
| tests/request_ui_static.mjs | 3 eligibility/staff cases added | Stale NOTIFIED match obeys server eligibility | Yes |
| tests/api_error_offline.mjs | 3 mocked API error checks | PASS 3 offline error checks | Yes |
| docs/BLOODMATCH_FLOW_SAFETY_AUDIT.md | Current remediation/decision notice; original findings preserved | Remediation update | Yes |
| docs/BLOODMATCH_SAFETY_FIX_PROGRESS.md | Phase plan, approvals, checks and pending queue/matrix proposals | BM-SAFETY-FIX-PROGRESS-2026-10-08 | Yes |
