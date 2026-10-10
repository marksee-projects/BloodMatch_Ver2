# Transaction-safe enqueue checkpoint

Marker: BM-SAFETY-PHASE5A-ENQUEUE. Prepared 2026-10-10, Asia/Manila. Database/API validation awaits user execution.

Approved queued coverage is limited to **Emergency donor-opportunity alerts at initial request creation** and **membership verification approved/rejected decisions**. NotificationService has an explicit allowlist; other existing event calls remain in-app only, including response, account-status, donation, resubmission and request-lifecycle events. No excluded lifecycle email is restored. Routine/Urgent, material-edit/rematch and donor-refresh opportunity email stay off. Existing 500-opportunity cap, eligibility, compatibility, chapter/distance ranking and event recipients are unchanged; removing the cap is separate work.

OTP/password reset remain direct through Mailer and EmailAttemptBudget, with existing hashes/TTLs and no queued secrets. Budgets stay 100 attempts/rolling hour with 10 protected account slots and maximum 90 Emergency; 450/rolling 24h with 50 protected slots and maximum 400 Emergency. Account mail uses all remaining total capacity, while normal/Emergency share unprotected capacity. Verification jobs have **normal delivery priority but account budget grouping** (inferred from notification type); Emergency jobs retain Emergency grouping. Enqueueing reserves no transport capacity. A future worker must use the shared gateway once per actual attempt and preserve this classification.

## What changed

- Notification and reference-only outbox row commit together, using the workflow's PDO. An owned transaction handles standalone calls; savepoints protect nested calls, including caught failures. No SMTP or secret/address/body copies in the enqueue path. A verified, valid email is required for a job; in-app notification remains for an unverified address. Missing SMTP configuration does not discard approved jobs.
- Notification inserts no longer use broad INSERT IGNORE. Only a proven duplicate event is suppressed, with a locking current read so concurrent commits are visible. New notification IDs get one unique delivery key and one job. Duplicate historical notifications are not backfilled.
- Verification locks actor/target in ascending ID order and rechecks active actor, role/chapter, self/privileged-target rules and pending state. Status, decision history, successful audit, notification and job share the transaction. Nested verification has its own savepoint, so a caller catching an enqueue error cannot commit a partial decision. The decision-row ID supplies event identity even if best-effort auditing fails. Authorization-denial logging is retained after rollback. AuditLogger's existing best-effort failure policy is unchanged.
- Approved donor-card provenance logic is preserved. Normal HTTP verification approval reconciles matches only after commit to avoid user-to-request lock inversion. Nested service callers own commit/rollback and any later reconciliation; the DB fixture uses this to avoid touching preexisting requests.
- Enqueue failure raises sanitized EmailEnqueueException/503; verification API returns 503 and rolls back its decision. Request creation retains its existing separate saved-request boundary. If matching/enqueueing fails, the API returns `matching=null` and `matching_status=failed_retry_required`; it does not claim donors were notified. No automatic matching retry/recovery is introduced.

New jobs start pending with zero attempts and no lease/sent timestamp. Emergency expiry is the request deadline; membership decision expiry uses the existing proposed 72-hour lifetime. These are stored metadata, not implemented expiry/delivery behavior. Future worker rechecks, leases, supersession, retries, SMTP and scheduling remain separate and require approval. Pending jobs currently remain undelivered.

## User-run commands

No new migration. 020 and 021 were previously user-confirmed on **bloodmatch_test:3306**; application/deployed state remains unverified. Stop test servers, schedulers and other database/mail writers before the focused suite. It deliberately poisons only a disposable job's delivery key using the next notification ID to force insertion failure; this requires exclusive test writers. No DDL, trigger, seed, reset or worker is invoked.

From the repository root, using XAMPP PHP/pdo_mysql and `proc_open`:

```powershell
# Existing SELECT-only schema/ledger review; do not add --apply.
& 'C:\xampp\php\php.exe' database\apply_email_checkpoint_migration.php

# Direct database suite with fake transport (no HTTP server or SMTP sockets).
& 'C:\xampp\php\php.exe' tests\email_enqueue.php

# Equivalent wrapper, if preferred; choose this OR the direct command above.
powershell -ExecutionPolicy Bypass -File tests\email_enqueue.ps1 -PhpPath 'C:\xampp\php\php.exe'

# Related verification API regression, after the focused suite passes.
# Requires port 8001 free; isolated runner disables real SMTP.
powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -DbPort 3306 -Suite phase5.ps1
```

Stop at failure and supply the sanitized summary/errors. Expected focused suite: **40 database enqueue checks passed**, followed by exact fixture cleanup; exit code 0. The suite fixes and verifies the actual database/port before writes; every child does the same. Fixtures use reserved .test accounts and a fake PHPMailer without sockets. It checks event duplication/concurrency; one accepted concurrent review and one 409; owned/nested rollback and forced outbox failure; approved/rejected decisions, current authorization and denial audit; normal/Emergency job metadata; cross-chapter compatible eligibility; excluded channels, Routine/Urgent/rematch policy; unchanged attempt ledger/donation history; and absence of transport calls. It cleans exact disposable users/requests and their notification/outbox children, including new notifications to existing donors for fixture requests. Audit rows are retained. A machine/process crash may require scoped fixture cleanup; do not reset/delete unrelated data.

The related API regression must report zero failures; its assertions/count are defined in phase5.ps1. Request-creation failure metadata and real MySQL races are not agent-verified from source review. No worker/inbox delivery claim follows from fake transport. The completed 34-check schema/accounting suite is earlier user evidence, not a pass for this changed enqueue implementation.

Agent checks actually run once after source/test edits: PHP syntax on 12 files PASS; 15 offline enqueue, 19 accounting and 16 workflow checks PASS; PowerShell wrapper parse-only PASS; git diff --check PASS (line-ending warnings only). No PowerShell/database suite, migration, task or real email was executed by the agent. Await these user-run results before delivery work.
