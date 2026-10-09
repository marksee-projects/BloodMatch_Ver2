# BloodMatch Safety Fix Progress

Marker: `BM-SAFETY-FIX-PROGRESS-2026-10-08`. Updated 2026-10-09, Asia/Manila.

Latest checkpoint: **Phase 5A schema/shared accounting passed on bloodmatch_test:3306 by supplied user-run output**. Marker `BM-SAFETY-PHASE5A-SCHEMA`. Migration 021 ready/recorded, 34 database checks passed and fixture cleanup confirmed. Phase 4B remains complete; scheduling remains test-only. Earlier entries below remain historical records.

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

## Decisions and approval gates

| Decision | State | Approved behavior / pending details |
|---|---|---|
| Location/chapter | APPROVED by user's fix request | Ranking only; farther/cross-chapter compatible donors remain eligible. Preserve blood substitutions; do not change compatibility-tier ordering without approval. |
| Non-response standby | APPROVED behavior; schema/execution pending | Donor enters standby after 42 hours without responding to a notified opportunity. In-app commit anchor, per-opportunity counting/interactions and explicit acknowledgment/recovery approved; retire consumed timeout events to prevent immediate retrigger. Retain independent post-donation safety windows. Present migration/impact before applying it. |
| Closed requests | APPROVED | CANCELLED/EXPIRED/FULFILLED closes all uncompleted offers. Pending reports remain in history, cannot be confirmed, and require explicit authorized officer/admin rejection with a reason. Closure records no donation and starts no post-donation cooldown. |
| Rejection reason persistence | PREPARATION/API/UI APPROVED; execution is user-only and pending | User approved nullable VARCHAR(500), nonblank 1-500 character new reasons, authorized history/audit display, existing NULL/status preservation. Migration 020 and test-only review/apply helper prepared; agent has not applied it. |
| Requester permissions / multiple responses | PENDING | Preserve current permissions and multiple active responses until the user approves any change. Atomic safety does not imply a new one-response-per-donor policy. |
| Notification recipients/channels | PENDING | Proposed matrix below is not authorization to change coverage. |
| Email queue / scheduler / schema / old data | PENDING | Propose concrete infrastructure and migration first; never apply to production during this task. |
| Member-email visibility | UNCHANGED | Current CONTEXT section 9.8 amendment explicitly approves member email visibility. Do not treat its mere presence as an automatically authorized privacy removal. |
| Encryption / dependencies / storage moves | PENDING | Restore cache protection now; no new encryption scheme or storage migration without approval. |
| Management/report expansion | PENDING | Do not add directories, management pages, downloads or reports without approval. |

Approved non-response details (not implemented): anchor at committed in-app opportunity notification; count unanswered POTENTIAL/NOTIFIED opportunities only while request OPEN and before deadline; earliest overdue opportunity triggers standby, responses resolve only their own opportunity; terminal requests no longer count; a new material-change notification generation restarts that opportunity's clock. Explicit donor acknowledgment allows recovery, retiring overdue timeout events while preserving post-donation/account restrictions. Persistent reason/event tracking needs a separately approved schema proposal before migration execution. User approved both timing and recovery on 2026-10-08; do not ask those same questions again.

## Phased plan

Each phase receives its own source review, focused tests, lightweight validation, and result report. PowerShell/database suites are executed by the user only. Dependent database work waits for required user-run results.

| Phase | Expected behavior | Planned affected files | Checks / gates |
|---|---|---|---|
| 1: document caching and response UX | No cached sensitive documents; Home confirms before sending; Matches obeys server eligibility and can respond by request ID without candidate row; structured errors retained | DocumentController.php; FeedCard.jsx; ConfirmationDialog.jsx; MatchesPage.jsx; apiClient.js; home_ui.mjs; request_ui_static.mjs; api_error_offline.mjs; audit/progress | PHP syntax, frontend build, focused offline tests; user-run Home/document regressions; no DB/schema/business rule changes |
| 2: live donor age/consent | Match/feed/respond reject underage/missing DOB/consent after enrollment; birth-date/consent changes update eligibility without destroying history; far/cross-chapter compatibility remains | DonorEligibilityService.php; AgeEligibilityService.php if needed; MatchService.php; UserRepository.php; DocumentRepository.php; ProfileController.php; focused backend tests | Existing age policy preserved; syntax/offline tests where feasible; user-run fixtures for adult-to-underage/null DOB, minors with/without consent, compatibility/ranking; no enrollment-data rewrite without approval |
| 3: atomic transitions | Shared consistent request/donor/match/report lock order; current donor checked under lock; serial cancel/edit/respond/confirm/withdraw/report; no duplicate pending reports | RequestsController.php; MatchesController.php; MatchResponseService.php; DonationService.php; BloodRequestRepository.php; MatchRepository.php; DonationReportRepository.php; UserRepository.php; focused concurrency suites | User-run concurrent action tests; approved closure semantics; request permissions/multiple responses unchanged pending decision; migration approval if reason/constraint storage required |
| 4: expiry and accurate active surfaces | Drain all due requests with bounded batches; deadline-aware active map/action queries; close uncompleted offers; block confirmation of closed pending reports; retain reasons/history | database/run_expiry.php; shared request lifecycle service if justified; request/match/report repositories; AnalyticsRepository.php; AnalyticsView.jsx; availability DTO/UI; scheduler documentation | User chooses/install schedule before new infrastructure; user verifies task configuration and >500 overdue cases; no production job invocation by agent |
| 5: durable notifications/email | Creation commits quickly without SMTP; durable jobs, retry/failure visibility; approved recipient/channel coverage; no duplicate queued event; no stale emergency outreach after closure | NotificationService.php; NotificationRepository.php; Mailer.php; proposed outbox repository/worker/migration; lifecycle/verification/account event calls | Queue/schema/scheduler/matrix approval first; mocked delivery checks; user-run isolated outage/retry/dedup tests; no real emails |
| 6: rule alignment and remaining accuracy | Approved 42-hour behavior implemented with independent cooldown; current offers revalidated after material edits; source/docs agree; availability/analytics fields corrected | approved timeout service/CLI/schema; MatchService.php; request edit path; analytics/auth DTO/UI; requirements/CONTEXT/traceability/audit/progress; focused tests | Timer/recovery approved; schema approval still required; offer treatment after material edit approved before destructive status changes; user-run integration results; full mobile/browser verification recorded honestly |

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

## Proposed email queue (not approved, not implemented)

Recommended: a MySQL outbox and PHP CLI worker invoked every minute by Windows Task Scheduler/cron, using existing PHP/MySQL rather than Redis or a new service dependency. New notification email jobs are inserted transactionally with their business event; creation does not call SMTP. Worker claims bounded jobs with durable leases, retries transient failures with backoff (proposed 1, 5, 15, 60, 240 minutes), records attempts/sanitized errors and a terminal failed state. Interrupted leases recover. Emergency opportunity jobs recheck current request/deadline/donor eligibility before send and stop on closure. Normal rate-limit deferrals are rescheduled rather than lost; emergency priority is maintained.

Proposed new email_outbox table contains notification/event reference, unique delivery key, status, attempts, next_attempt_at, lease metadata, last_error, timestamps; migration creates structure only, with no automatic old-notification backfill. Applying migration/installing task/running workers requires approval. Existing failed emails require an explicit, scoped backfill decision. SMTP cannot guarantee exactly-once inbox delivery across a send-success/process-crash boundary; use stable Message-ID, dedup/lease and honest at-least-once failure semantics rather than claiming a transaction covers an external mail server.

OTP/password-reset job content needs a separate approved secret-handling/TTL design because storing plaintext codes/tokens in an outbox changes security exposure. Start with notification-backed emergency/status emails; retain auth mail behavior until that separate decision. This is a staged proposal, not a claim that all email recovery is solved.

## Proposed lifecycle recipient/channel matrix (approval required)

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

## Remaining work

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
