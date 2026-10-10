# Controlled alias registration and Emergency grouping

Scope: user performs registration/verification/approval and request creation; agent runs only source/offline checks, **no database commands, email, migrations or account changes**. Keep EMAIL_WORKER_ENABLED=0 and the general email task uninstalled. Existing safety schedule may remain active. Membership job 1 is completed/received; never resend it.

## Alias support and private registration

Code inspection: frontend registrationPayload trims/lowercases only; Request::str trims; AuthService lowercases without removing +tags; Validator uses FILTER_VALIDATE_EMAIL (accepts this alias); UserRepository checks/binds the entire email against uq_users_email. Login likewise trims/lowercases only. Therefore **markseejr+requester2@gmail.com is supported as a distinct BloodMatch email from markseejr@gmail.com**. No uniqueness rule, schema or Gmail account/password change. Actual availability has not been queried; normal registration checks it. Offline backend validation and 3 JSON/multipart checks passed.

1. Keep the existing markseejr@gmail.com admin session in your normal browser. Use a separate browser profile/private session to open http://localhost:5173/register.
2. Register **markseejr+requester2@gmail.com** using your normal truthful details, assigned chapter and the existing location selector if desired. Choose the **BloodMatch password privately in the form** (8–72 characters, letter and number); never paste it, OTP, document contents or credentials into chat. Registration creates a member, not another admin. No need to enroll this requester as donor.
3. Complete the email verification dialog with the OTP received in your existing Gmail inbox. This is the approved direct account-email flow and works independently of the disabled general queue. Use the normal resend control if needed; do not change verified flags/reset credentials in SQL. If registration says this exact alias is already registered or appears to conflict with the base admin email, stop and report the message; no uniqueness changes or forced account creation.
4. Upload the normal required National ID through registration/Profile with the privacy acknowledgment. Do not upload a fabricated ID or expose documents in chat.
5. In the existing admin session, open **Admin → Account Verifications**, select the row whose exact email is the alias, inspect its submitted document and use the existing membership approval workflow where appropriate. Keep the markseejr@gmail.com admin row/role/chapter/status/password unchanged. Existing self-review/authorization rules remain enforced; no SQL bypass. The alias's queued membership-decision email stays pending while general queue is off and is not part of the Emergency experiment.
6. Return to the alias session and reload Profile. Confirm a separate member account, email verified and membership verified. Do not infer success from the registration screen alone.

## Proposed actors — verify before creating requests

| Role | Account |
|---|---|
| Donor inbox | officeradmin24@gmail.com |
| Requester 1 | fireshorts395@gmail.com |
| Requester 2 | markseejr+requester2@gmail.com |
| Approval/admin only | markseejr@gmail.com, unchanged |

These roles are proposed, not live eligibility evidence. From the repository root, **you** run these read-only reviews after normal setup:

```powershell
$php = 'C:\xampp\php\php.exe'
& $php database\run_application_email.php --recipient=officeradmin24@gmail.com
if ($LASTEXITCODE -ne 0) { throw 'Donor review failed; stop.' }
& $php database\run_application_email.php --recipient=fireshorts395@gmail.com
if ($LASTEXITCODE -ne 0) { throw 'Requester 1 review failed; stop.' }
& $php database\run_application_email.php --recipient=markseejr+requester2@gmail.com
if ($LASTEXITCODE -ne 0) { throw 'Requester 2 review failed; stop.' }
```

Expected: fixed bloodmatch:3306, smtp=false, enabled=false, schema/quiet-cutover ready; each exact email matches one distinct account. Donor must show donor_eligible=true and a blood type; requesters must show can_create_request=true and email/membership verified. Share these summaries for role/eligibility review. If ordinary enrollment is missing, use normal Profile enrollment/availability only; never bypass age/consent/cooldown/standby or change historical donation anchors. Request-specific compatibility will be rechecked for the actual jobs.

## Create and review two fresh opportunities (after actor readiness)

Use normal request creation as requester 1 and requester 2, one new controlled Emergency request each, **within the same five-minute window**. Select a required blood type compatible with the chosen donor, valid normal location/facility and future needed time long enough for the experiment. Do not edit/rematch an old request to force an email: those channels remain off. Do not let the donor respond/withdraw/report before the opportunity-email check.

Normal matching still includes all eligible compatible donors: **other donors may receive in-app alerts and queued jobs**. Only the two explicitly selected jobs for the chosen donor can be sent; general queue remains off. Do not add artificial donor exclusions or alter account permissions. Cancel the controlled requests through their normal owner UI after the experiment to close opportunities/cancel remaining jobs; keep audit/delivery history, no SQL deletion/reset.

After both requests are created, review the donor's pending jobs:

```powershell
& $php database\run_application_email.php --recipient=officeradmin24@gmail.com
if ($LASTEXITCODE -ne 0) { throw 'Job review failed; stop.' }
```

Identify exactly two **new** pending match.new/emergency jobs, attempts=0, policy_block=null, same recipient_user_id, distinct request_id/requester_id matching your two requests. Ignore old job 7 (previously stale_generation), membership jobs, other recipients and earlier history. Both created_at timestamps must fall in one five-minute grouping window. The reader emits no message body, password, OTP or document contents.

Wait normally until the first window's grouping_ready_at; do not bypass the five-minute grouping delay or poll/retry repeatedly. Then fill **numeric job IDs from your actual review**, no placeholder literals:

```powershell
$firstJob = [long](Read-Host 'First reviewed new Emergency outbox job ID')
$secondJob = [long](Read-Host 'Second reviewed new Emergency outbox job ID')
if ($firstJob -lt 1 -or $secondJob -lt 1 -or $firstJob -eq $secondJob) { throw 'Two distinct positive IDs required.' }
$selectedJobs = "$firstJob,$secondJob"
& $php database\run_application_email.php --recipient=officeradmin24@gmail.com "--job-ids=$selectedJobs"
if ($LASTEXITCODE -ne 0) { throw 'Exact grouping review failed; stop.' }
```

**Share this exact-job review for confirmation before the real send.** No actual jobs have been selected/approved for sending by this preparation. If either is stale/ineligible/unclaimable or the timestamps do not group, stop; do not change generation, attempts, leases, deadlines or eligibility to make it pass.

## Prepared one-digest command — only after exact jobs/recipient confirmed

```powershell
$delivery = & $php database\run_application_email.php --emergency-experiment --deliver "--job-ids=$selectedJobs" --recipient=officeradmin24@gmail.com | ConvertFrom-Json
$deliveryExit = $LASTEXITCODE
$delivery | ConvertTo-Json -Depth 8
if ($deliveryExit -ne 0) { throw 'Emergency experiment failed; stop and retain output.' }
if ($delivery.worker.claimed -ne 2 -or $delivery.worker.sent -ne 2 -or $delivery.worker.accepted_messages -ne 1 -or $delivery.worker.transport_calls -ne 1) { throw 'One grouped accepted message not established; stop without retrying.' }
```

The restricted path requires disabled general queue, exactly two fresh/live jobs for one donor from distinct requesters, current compatibility/safety/lease checks and the normal five-minute window. Worker scope is those two IDs with limit=2; the transport refuses a partial/mismatched digest. Existing shared budget, donor cap/reservation outcomes, grouping, stale cancellation and retries are preserved. No sender/account secrets are logged. Expected sent=2 means **two jobs**, accepted_messages=1 means **one SMTP-accepted digest**; check the controlled donor Inbox/Spam for one message covering both requests separately.

On failed/deferred/uncertain/lost output, stop/share result; do not blindly retry or refund/reset shared/donor reservations. Do not process other jobs, enable general queue or install email tasks. Keep logs/audit/accounting; cancel experiment requests normally after the result. This proves only the scoped digest observed, not general automatic delivery or every real donor-cap boundary. Prior 65 fake checks remain valid; no repeat completed suite merely for this preparation.
