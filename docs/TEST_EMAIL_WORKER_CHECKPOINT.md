# Manual fake-transport worker checkpoint

Marker: BM-SAFETY-PHASE5A-TEST-WORKER. Prepared 2026-10-10, Asia/Manila; database validation awaits the user.

This checkpoint prepares a manual **test-only** worker for bloodmatch_test:3306. It contains no SMTP adapter, real-delivery mode or scheduler. Default CLI action is SELECT-only verification. Explicit runs require a particular job ID and fake outcome. Fake acceptance writes sent/emailed_at metadata and consumes simulated attempts; it proves neither SMTP acceptance nor inbox delivery. Use only disposable test jobs for manual simulation.

Approved channels stay Emergency donor opportunities at initial request creation and membership verification decisions. OTP/password reset remain direct. Normal priority membership jobs use account budget grouping; Emergency jobs use Emergency grouping. Budgets remain rolling-hour 100 total / 10 protected account / maximum 90 Emergency; rolling-24h 450 total / 50 protected / maximum 400 Emergency. Account mail can use all remaining total capacity. Normal/Emergency share unprotected capacity. No excluded lifecycle email, backfill, recipient/ranking expansion, donor-safety change or new migration.

## Prepared behavior

- At most 20 claims per service run, with a 45-second soft budget checked between jobs; CLI scopes one job. Emergency priority first. A database run lock skips overlapping manual/parallel runs and serializes the recipient counter; claims use short row-lock transactions compatible with XAMPP, without SKIP LOCKED.
- Durable 120-second leases, new tokens on recovery and ownership/expiry checks before beginning an attempt and recording completion. Expired leases can recover. Stale owners cannot mark sent or alter emailed_at. Sent state and notification emailed_at commit together. No automatic transaction/SMTP replay.
- Recheck current verified email, approved channel and job expiry. Membership jobs must identify an actual decision row, match the latest decision and current status; older decisions/resubmission supersede them. Deactivated accounts can still receive their valid membership decision, preserving prior status-email handling.
- Emergency additionally checks current request OPEN/Emergency/deadline, notification/match generation, unresolved POTENTIAL/NOTIFIED offer, and centralized live donor account/verification/age/consent/availability/safety-window/blood compatibility. Cross-chapter/location remains ranking, never exclusion. These checks repeat after the reservation wait, before simulated transport. Changed/stale jobs cancel without restoring other channels.
- Mailer's shared gateway reserves once immediately before the callback, commits accounting independently, and keeps reservations on failure/crash. Owned job attempts increment before transport; no business/claim/accounting transaction spans the callback. The run advisory lock remains held. A late safety change after reservation can conservatively consume a slot without transport; a crash before callback can also consume an unused reservation. No refund.
- Six total attempts: retries after 1/5/15/60/240 minutes; sixth failure is terminal. Expired-lease recovery respects attempts already recorded. Missing configuration defers five minutes, without attempt increments; shared limits/accounting outage/recipient cap defer one minute. Normal-priority jobs retain the prior five accepted notification emails per recipient/hour rule, now counted by actual emailed_at; Emergency uses a separate maximum of two accepted digests per recipient/rolling 30 minutes across requesters. It waits five minutes from the first job creation, groups explicitly scoped jobs created inside that window, and consumes one shared attempt for the digest. Group members commit sent/emailed_at together. Deferrals can run again later; no scheduler runs them yet.
- Codes/summaries contain no address, body, credential, OTP/reset token or raw SQL/transport exception. Fake transport receives job ID, budget class and a stable derived Message-ID only. Pending jobs outside the explicit scope remain untouched.

The service exposes core processing with a required fake callback; the shipped CLI cannot attach SMTP. Real transport/rendering, measured timeouts and operational deployment need another approved checkpoint. Exactly-once external mail is not promised: acceptance followed by a crash before completion may duplicate a retry even with a stable Message-ID. Closure/eligibility can also change after the last check; no workflow locks are held across transport. These boundaries remain relevant to future real delivery.

## User-run validation

Prerequisites: existing bloodmatch_test:3306, migrations 020/021 already ready/recorded, XAMPP PHP/pdo_mysql, proc_open, existing chapter/compatibility/settings reference data. Stop test servers, schedulers and other database/mail writers. Do not apply/reset/seed. The focused suite requires an empty attempt ledger for controlled budget fixtures; if it refuses existing attempts, report the result and inspect rather than deleting unrelated accounting rows. Existing outbox rows need not be empty: tests process only their own job IDs.

From repository root:

```powershell
# SELECT-only verification; no claims, sends, lease recovery or reservations.
& 'C:\xampp\php\php.exe' database\run_test_email_worker.php --verify

# Focused database suite; all transports are callbacks without sockets.
powershell -ExecutionPolicy Bypass -File tests\email_worker.ps1 -PhpPath 'C:\xampp\php\php.exe'

# Related enqueue/concurrency regression after worker suite passes.
powershell -ExecutionPolicy Bypass -File tests\email_enqueue.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

Alternatively, execute the focused suite directly with `& 'C:\xampp\php\php.exe' tests\email_worker.php`; choose direct PHP or its wrapper, not both. Expected: **57 database fake-worker checks passed**, exact fixture/synthetic-attempt cleanup, zero failures and exit 0. The suite exercises Emergency priority, account classification, retries/stable identity, total/protected caps, emailed_at recipient limits/Emergency bypass, configuration deferral, TTL/supersession/live safety/closure/generation, lease recovery/stale ownership, lost completion ownership, parallel/overlap and unchanged donation history. It retains audit records and removes exact owned users/requests/notification children and recorded synthetic attempt IDs. It assumes exclusive test writers when capturing gateway-created ledger IDs. A crash or child failure can leave unreturned fixture attempts; report failures and use scoped cleanup rather than resetting data.

The enqueue regression previously passed by user report, but is user-run again here because its shared outbox repository/gateway dependencies changed. Its expected summary is 40 checks; no new pass is claimed. Stop on any failure and provide full sanitized output, including cleanup and exit status.

Optional manual simulation for **an explicitly chosen disposable test job**, after review:

```powershell
$fixtureJobId = 123 # Replace with the exact disposable job ID; this is an example.
& 'C:\xampp\php\php.exe' database\run_test_email_worker.php --run --fake-mode=accept "--job-id=$fixtureJobId"
# Supported alternatives: --fake-mode=fail or --fake-mode=unconfigured.
```

This changes that test job's delivery state/accounting. There is no --real mode or task command. Tests clean their fixture jobs, so their IDs cannot be reused after cleanup. No need to run the optional command unless you already own a separate disposable fixture job.

Agent-run checks: syntax PASS on seven new/changed PHP files; 21 worker policy, 19 accounting and 15 enqueue offline checks PASS; wrapper parse-only PASS; git diff --check PASS. No database check/suite, worker invocation, migration, PowerShell test, task, network email or environment-file change was executed by the agent. Worker SQL/locking/live checks await user output; prior schema/enqueue passes do not verify this worker. After successful user validation, recommend separately scoped scheduling preparation for fake transport only; real-delivery readiness and deployment remain separate decisions.

Follow-up 2026-10-10: grouping/cap code is prepared and has offline validation only. Prior worker validation remains pending. Test callbacks now receive job_ids for a digest; a one-job CLI scope cannot demonstrate multi-job grouping. Message-ID stays stable for an unchanged job set; changes in eligible members or regrouping after a crash can change it. The rolling cap counts committed simulated acceptances in outbox sent_at, not inbox delivery or uncertain crash-boundary acceptances. Exact-once delivery remains unavailable. The new safety follow-up suite and migration 022 are documented in SAFETY_FOLLOWUP_CHECKPOINT.md.

Current approvals supersede the earlier 500-job total cap: all eligible initial Emergency donor jobs enqueue in bounded batches. Material-edit/rematch and response emails remain excluded. Current source is prepared, not database-validated by the agent; use SAFETY_FOLLOWUP_CHECKPOINT.md for the latest dependency order.
