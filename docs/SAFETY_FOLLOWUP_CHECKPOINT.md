# Safety follow-up checkpoint

Prepared 2026-10-10, Asia/Manila. Current chat approvals supersede historical proposal gates. Code, migration and tests are prepared; current SQL/API/database results are **not agent-run**. No real transport or schedule is installed.

Current result update: the user supplied 42 focused checks passed, exact fixture cleanup and exit 0; supplied workflow/Home summaries and reported all phase4/phase5/phase12 passes. Supplied latest scoped cleanup removed 29 users, 5 requests and 7 upload files; final review is already_absent and journal removed. Earlier worker/enqueue results remain retained. Commands below are historical execution instructions for this completed checkpoint, not requests to rerun. Schema evidence is test-only (bloodmatch_test:3306); browser, operational scheduling, real transport and deployed targets remain unverified/separately scoped.

Resolved rules: all eligible donors receive in-app coverage; all eligible initial Emergency donors enqueue in batches of 500 with no total truncation. Material-edit/rematch and response emails stay off. Actual withdrawal/re-response transitions notify once in-app; retries remain silent. Material edits and offer reconciliation commit together, preserve completed donations, close invalid responding offers without pending reports, preserve pending reports for reasoned review, and block invalid confirmation. Valid offers produce no invalidation alert. Quiet opportunity reuse preserves read/creation timestamps and one visible notification.

## Additive 022 impact

`donor_opportunities` records request, donor, generation, notification reference, `notified_at`, `due_at = notified_at + 42 hours`, and resolution status. New-generation notification/timer writes share the event transaction and become observable after commit. Only the latest valid notified generation counts; older unanswered generations become `superseded` and retain their timestamps/history. Duplicate processing does not update timer dates. Different requests retain independent timers. Notification updates/deletion cannot erase timer history; its optional reference uses SET NULL.

`donor_response_state` stores non-response standby separately from the existing availability/donation anchor and safety windows. Acknowledgment compares each pending timer's **due_at** to database UTC `consumed_through`, not its notification creation time. It retires elapsed timers (`due_at <= acknowledgment time`), preserves fresh timers (`due_at > acknowledgment time`), and clears only non-response standby. It cannot reset/bypass post-donation windows, verification or account restrictions. Closed/overdue requests and resolved offers do not count. Detector selections drain bounded batches and recheck request/donor state under the existing lock order.

No historical notification backfill, user-row rewrite, donation/rejection/history rewrite, new SMTP secrets, dependencies, permissions or task setup. Before 022 is fully created and recorded, timer recording/detection is inactive and acknowledgment returns 503. Deploying these prepared files does not prove the feature is active. An application/deployed target needs its own reviewed rollout; do not use the generic all-migrations runner.

## User commands, in dependency order

Use an existing disposable **bloodmatch_test:3306**, migrations 020/021 ready and reference data present. Stop all test writers/schedulers; focused worker accounting assumes an empty attempt ledger and refuses unrelated attempts. Do not reset or delete accounting history to force a test pass. No new migration is needed for the worker/grouping checkpoint.

```powershell
# 1. SELECT-only worker readiness, then current fake-worker/enqueue validation.
& 'C:\xampp\php\php.exe' database\run_test_email_worker.php --verify
powershell -ExecutionPolicy Bypass -File tests\email_worker.ps1 -PhpPath 'C:\xampp\php\php.exe'
powershell -ExecutionPolicy Bypass -File tests\email_enqueue.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

Worker expected summary: 57 fake-worker checks, zero failures; enqueue expected summary: 40 checks, zero failures. These are **expected**, not claimed passes. Fake acceptance is not SMTP/inbox proof. Grouping consumes one shared attempt per digest; sent/emailed_at group completion is atomic. The per-donor rolling cap counts committed simulated acceptances, not unknowable inbox/crash-boundary outcomes. Stable Message-ID is for an unchanged digest member set; eligibility changes/regrouping can change it. No exactly-once external delivery claim.

After the worker/enqueue checkpoint passes, review 022 and perform user-controlled test-only application if accepting the displayed SQL. The helper defaults to read-only review, verifies the actual target, refuses existing shape drift, creates only missing approved structures, and records 022 after complete verification. DDL auto-commits; valid partial structures are retained after failure. No backfill.

```powershell
# 2. Review only, no writes.
& 'C:\xampp\php\php.exe' database\apply_non_response_checkpoint.php
# User-controlled additive test application after reviewing the SQL.
& 'C:\xampp\php\php.exe' database\apply_non_response_checkpoint.php --apply
# Read-only shape/ledger verification.
& 'C:\xampp\php\php.exe' database\apply_non_response_checkpoint.php
& 'C:\xampp\php\php.exe' database\run_test_non_response.php --verify

# 3. Focused schema/timer/offer/notification/transition/fan-out tests, no sockets.
powershell -ExecutionPolicy Bypass -File tests\safety_followup.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

The follow-up suite checks quiet reuse, generation supersession, acknowledgment across requests/generations, repeated actions, all urgency classes, invalid pending-report confirmation/reasoned rejection, account/resubmission atomicity, >100 reconciliation and 501 Emergency jobs. It refuses existing due timers to avoid unrelated standby transitions. Fixture notifications/matches/reports/timers/users are removed by exact owned IDs; audit history is retained. Synthetic document references create no actual files. Assertion total is printed by the suite; no unrun pass/count/cleanup result is claimed.

Then run changed workflow/API/accuracy paths on the isolated target, with port 8001 free and XAMPP/proc_open available:

```powershell
# 4. Related current regressions; isolated runner suppresses real SMTP.
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite workflow_atomicity.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite home_respond.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite donor_refresh.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase4.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase5.ps1
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase12.ps1
```

Stop at a failing command and provide sanitized output, summary, cleanup result and numeric exit code. Do not repeat earlier completed scheduled-expiry setup/cleanup. No task or real-email command is part of this checkpoint. Actual acknowledgment keyboard/mobile behavior and analytics values still need browser/API checks after readiness. Further operational scheduling/real delivery waits for current user-test results and explicit target/infrastructure scope.
