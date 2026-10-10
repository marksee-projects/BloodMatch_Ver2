# Application email-accounting repair proposal

Prepared 2026-10-10, Asia/Manila. Application target: **bloodmatch at 127.0.0.1:3306**, separate from the completed bloodmatch_test checkpoint. No application migration is applied by the agent.

The supplied direct SMTP test reached smtp.gmail.com:587 with OpenSSL loaded, then returned accounting_unavailable and exit 1 before the Mailer transport callback. This establishes an accounting blocker for that attempt, not successful SMTP authentication or inbox delivery. The application database output listed no email_attempts/email_outbox rows. A verified recipient also causes the OTP resend endpoint to skip sending independently of accounting; the generic response is not delivery evidence.

Proposed repair: apply only the existing, test-validated **021_email_outbox_accounting.sql** on bloodmatch:3306. It creates the category/time attempt ledger and reference-only outbox structures, then records 021 in the existing ledger. No user verification/password changes, old-record backfill/resend, 020/022 application, worker/task setup, donor-cap hardening or .env changes. New ledger accounting begins with new reservations; it does not reconstruct historical SMTP attempts.

This unblocks eligible direct OTP/password-reset accounting when schema absence is the cause. It does not guarantee credentials/delivery. The current notification code can also enqueue approved Emergency/member-decision jobs once the outbox exists; those jobs remain pending without a separately approved real worker. Operational scheduling, real queued transport and broader rollout remain deferred.

## Review now: SELECT-only, no mail

```powershell
& 'C:\xampp\php\php.exe' database\review_application_email_accounting.php
if ($LASTEXITCODE -ne 0) { throw 'Application accounting review failed; stop.' }
```

Expected actual target: bloodmatch:3306. The helper prints ready/missing table shapes, 021 recorded state, parent/ledger prerequisites, SQL and its SHA-256. It refuses drift or a recorded-but-missing schema. Default review performs no DDL/data writes or SMTP; values and credentials are not printed. Share the JSON/preflight result before application approval.

## Application requires separate approval

The user's earlier rules require approval before a schema change on this separate application target. Do not use --apply or the generic migration runner yet. After approval, stop app/mail/database writers, take a database backup, apply only this reviewed file using its checksum, and verify readiness. DDL auto-commits: valid partial tables remain after failure; no drop/reset/conversion is attempted. Existing notification/report fingerprints must remain unchanged before recording 021. Missing prerequisites do not authorize additional migrations or ledger creation.

Any later transport test is one explicitly selected recipient/request only. Already-verified accounts still do not receive OTP resends. Never disable accounting, manually unverify users or bulk-resend to work around the failure.
