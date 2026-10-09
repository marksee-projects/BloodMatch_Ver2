# Phase 5A schema and shared accounting

Marker: BM-SAFETY-PHASE5A-SCHEMA. Prepared 2026-10-09, Asia/Manila. Migration/database tests remain user-run.

This checkpoint implements shared reservations for existing direct mail paths (FR-17 and authentication mail) and prepares the outbox schema. It does not enqueue notifications, start a worker, install a schedule, expand recipients/channels, change workflow transactions or backfill historical notifications. Existing notification email remains synchronous; the outbox stays empty until a later checkpoint.

## Limits and behavior

| Rolling window | Total attempts | Protected account slots | Emergency maximum |
|---|---:|---:|---:|
| 1 hour | 100 | 10 | 90 |
| 24 hours | 450 | 50 | 400 |

Account mail can use all remaining total capacity, with no separate 10/hour or 50/day maximum. Account class covers direct verification OTP/password reset and existing `verification.decision` / `account.status_changed` emails. Other normal notifications and Emergency traffic share unprotected capacity (90/hour, 400/24h), preserving slots from normal notifications too. Day is implemented as rolling 24 hours rather than midnight reset. Exactly cutoff-old reservations are outside the window; time comes from the database UTC clock with microseconds. Existing five normal acceptances/recipient/hour and Emergency bypass remain; correcting that existing acceptance counter is deferred.

Mailer reserves once immediately before transport. Failures/crashes retain reservations; rate denial, malformed/reserved recipients, missing configuration and errors before reservation consume no attempt. A crash between reservation and transport may conservatively consume an unused slot. Mock acceptance counts as a simulated attempt. A future worker must use this Mailer path for each retry and must not reserve twice.

Reservations use a database-scoped named lock and an independent short transaction on a fresh connection to the workflow's actual resolved target, without connection fallback. Commit/release occurs before SMTP; workflow rollback cannot erase attempts. Missing schema/SQL/lock errors prevent transport and log a sanitized failure. Existing callers may still hold workflow locks during synchronous SMTP until the queue checkpoint. No pruning/refund routine is introduced.

OTP/password reset remain direct, preserving current hashes/TTLs; no queued codes/tokens. Direct denial retains existing generic API behavior: a saved code/token may not be delivered and has no durable retry. Mailer continues to return boolean success/failure.

## Migration

021 creates two InnoDB tables only:

- `email_outbox`: unique notification/delivery references; priority/status, attempts, due/lease/expiry and UTC timestamps, safe error-code fields. BIGINT UNSIGNED notification FK cascades when its notification is deleted. No payload, recipient or secret columns. Future writers must enforce error allowlists and ownership transitions; a table is not a queue implementation.
- `email_attempts`: category and reservation time only, indexed by time/category. No user/job FK, so deletions and business rollbacks cannot free capacity. No email addresses, body, subject, OTP/reset tokens or credentials.

The helper checks all declared columns/types/null/defaults, indexes, FK, engine/collation and the existing migration ledger. Default review performs SELECTs only. Apply creates only missing tables and records only 021 after verification. Valid objects surviving partial DDL failure are retained; schema drift is refused. DDL auto-commits and can leave a table without a ledger entry. Inspect and rerun review; do not drop/reset data. Existing notifications/report values are compared before/after apply; 020 is unchanged.

Stop test servers/mail callers and tasks before applying/testing. Back up the disposable test DB if its contents matter. Missing accounting schema prevents configured mail: application/deployed use needs separately reviewed/applied schema. This helper cannot target application/deployed data.

## User-run commands

From repository root, with XAMPP PHP/pdo_mysql, existing `bloodmatch_test:3306` and migrations through 020 ready. Credentials load internally; `.env` is unchanged. Stop at any failure and return sanitized output.

```powershell
# SELECT-only review.
& 'C:\xampp\php\php.exe' database\apply_email_checkpoint_migration.php

# Apply only 021 to the fixed test target after reviewing SQL.
& 'C:\xampp\php\php.exe' database\apply_email_checkpoint_migration.php --apply

# Verify both shapes ready and recorded=true (SELECT-only).
& 'C:\xampp\php\php.exe' database\apply_email_checkpoint_migration.php

# Focused DB suite; no HTTP server, SMTP, scheduler, seed or reset.
& 'C:\xampp\php\php.exe' tests\email_accounting.php
```

The suite requires empty new tables and `proc_open`; run before other mail tests. Every process loads a fake PHPMailer with no socket/SMTP implementation. It tests real SQL/parallel hour caps, daily protection, aging/cutoff boundaries, independent commit/lock release, failure accounting/skips, outbox uniqueness/FK/cascade, report preservation and cleanup. Expected **34 database checks passed**, exit 0 and empty new tables afterward. Disposable account/notification and synthetic attempt IDs are cleaned; audit history stays intact. No other mail writer may run concurrently: fake-transport assertions capture synthetic ledger IDs under this explicit exclusive-test precondition. Unknown attempts from a worker crashing before returning its ID are retained; inspect/report rather than deleting all attempts/resetting.

Agent validation: syntax passed on all 12 new/changed PHP files, 19 offline checks passed, whitespace check passed. No migration/helper/database test execution, real mail, task, `.env` value read or dependency change. Schema/server locking remain unverified until user output. Enqueueing, retries/leases, eligibility rechecks, TTL, scheduling and lifecycle events are later checkpoints.
