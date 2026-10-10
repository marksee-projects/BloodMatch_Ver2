# Confirmed single membership-email experiment

Recipient result: user subsequently confirmed **Received in Inbox**. SMTP acceptance/outbox completion and actual recipient Inbox receipt are now confirmed for this single membership decision. Checkpoint complete; no resend/retry/reset/cleanup. General queue sending remains disabled. Emergency grouping/donor delivery has not been verified by this result.

Result received: user supplied job 1 verification.decision on bloodmatch:3306, claimed=1/sent=1, all failed/cancelled/deferred/lost counts 0, completed/exit_code=0 at 2026-10-10T15:28:42Z. General queue enabled=false and cutover_configured=false. Real SMTP acceptance plus outbox completion confirmed by this scoped user run; Inbox/Spam receipt remains pending. Do not repeat the command/reset the job or ledger. Commands below are a record, not a rerun instruction. Emergency/grouping/general activation remains separate.

User explicitly controls and confirmed **fireshorts395@gmail.com**, account **137**, outbox **job 1**. Fixed target: **bloodmatch, 127.0.0.1:3306**. Policy-checked read-only review: pending current verification.decision, normal priority/account budget, attempts=0, claimable=true, policy_block=null; expires 2026-10-13 08:47:42 UTC. At 2026-10-10T15:22:59Z schemas ready, SMTP configured, quiet cutover ready and general queue disabled. No email was sent by the agent.

Scope: one user-run attempt for this membership decision only. General queue remains **EMAIL_WORKER_ENABLED=0**; no email task installed/started. No account/status change, job copying, backfill/resend or other job processing. Existing lease/recipient/current-decision/expiry checks, protected shared accounting, recipient limit and stable Message-ID remain enforced. --experiment cannot be used for Emergency events or enabled/scheduled general sending. It bounds the worker to one exact job and one claim, with recipient recheck at transport.

Run from repository root, with MySQL/PHP available. Keep EMAIL_WORKER_ENABLED=0; no cutover/env enabling is needed for this exact experiment. Stop any competing email-worker writers. The safety task can remain running. The command performs real SMTP for the confirmed recipient only:

```powershell
$delivery = & 'C:\xampp\php\php.exe' database\run_application_email.php --experiment --deliver --job-id=1 --recipient=fireshorts395@gmail.com | ConvertFrom-Json
$deliveryExit = $LASTEXITCODE
$delivery | ConvertTo-Json -Depth 7
if ($deliveryExit -ne 0) { throw 'Membership experiment failed; stop and retain the output.' }
if ($delivery.worker.sent -ne 1 -or $delivery.worker.claimed -ne 1) { throw 'No single accepted delivery confirmed; stop and share the output rather than retrying.' }
```

Expected: database bloodmatch/port3306, experiment=true, enabled=false, scope.job_ids=[1], notification_type=verification.decision, worker claimed=1/sent=1, failed/lost=0. Exit/status alone is not delivery proof. Budget/recipient deferral or stale-state cancellation can yield zero sends; report output without retry loops. Accepted means SMTP acceptance, not Inbox receipt. Check recipient Inbox/Spam and report separately. Uncertain/lost outcomes must not be treated as proven non-acceptance or blindly retried; no refunds/reset of shared attempts.

Cleanup: none. Retain the delivery/audit/accounting history and sanitized log; do not delete/reset the job or ledger. No fake fixture or historical file was created. This checkpoint does not establish Emergency grouping/donor delivery, full automatic queue readiness or document encryption rollout. General email scheduling stays disabled pending the separate Emergency experiment.
