# Local worker setup

Code, migrations and task definitions travel with Git. `.env`, SMTP credentials and Windows scheduled tasks do not. Each teammate using their own local database must configure their own environment and install the workers. A shared deployment needs workers on its host, not on each developer's PC. Current application helpers explicitly target `bloodmatch` on `127.0.0.1:3306`.

## Safety: expiry and non-response standby

Apply the required project schema using the existing migration handoffs. Review readiness and candidate counts:

```powershell
& 'C:\xampp\php\php.exe' database/run_application_safety.php
```

After readiness passes, set `SAFETY_AUTOMATION_ENABLED=1` in that machine's local `.env`. Safety processes overdue requests and qualifying non-response opportunities. It does not send SMTP; membership email is a separate worker. Current user's activation of both safety operations was explicitly approved in chat; the reviewed counts were three due requests and two opportunity timer candidates, subject to live rechecks.

Run and schedule it from the repository root (user-run):

```powershell
& 'C:\xampp\php\php.exe' database/run_application_safety.php --run
if ($LASTEXITCODE -ne 0) { throw 'Safety run failed. Save the output before proceeding.' }
$safetyAction = if (Get-ScheduledTask -TaskName 'BloodMatch-Application-Safety' -ErrorAction SilentlyContinue) { 'Upgrade' } else { 'Install' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action $safetyAction -Kind Safety
if ($LASTEXITCODE -ne 0) { throw 'Safety schedule setup failed.' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action Verify -Kind Safety
```

The ownership-checked Upgrade preserves an existing schedule/identity; Install creates a one-minute schedule. The Safety launcher now uses windowless `php-win.exe`. Windows/MySQL must be running and the configured Windows user signed in. Expiry is periodic, so notification may follow the deadline by approximately one scheduling interval plus processing time; it is not a browser timer. Reload/open the owner's expired-request view to inspect the resulting status and renewal action. Pending donation reports survive expiry.

## Membership approval/rejection email

Configure actual SMTP values in the local `.env` without committing secrets. A newly configured machine needs `MEMBERSHIP_EMAIL_ENABLED=1` and an explicitly reviewed `MEMBERSHIP_EMAIL_START_AT_UTC` in `YYYY-MM-DD HH:mm:ss` UTC format. For first activation, use the current UTC time to exclude historical backlog; do not overwrite an established cutover to troubleshoot a particular email. Keep `EMAIL_WORKER_ENABLED=0` unless the separate general-email coverage is approved. Follow the existing quiet-cutover/budget protections; synthetic `.test` recipients do not receive real mail.

Read-only readiness and recipient investigation (replace the address with the affected actual member address):

```powershell
& 'C:\xampp\php\php.exe' database/run_application_membershipemail.php --recipient='member@example.com'
```

This review sends no mail and now includes recent terminal/pending verification delivery states, sanitized error/cancellation codes and timestamps, including notifications with no queued job. Existing worker logs with `claimed:0` and `transport_calls:0` mean no due scoped jobs were attempted on those ticks; they do not prove email delivery or SMTP failure. Check queue status, event validity, configured cutover and recipient eligibility before any exact-job experiment or resend. Never reset attempt history or bulk replay backlog.

For a teammate's configured membership worker, review first, then install or upgrade its owned task:

```powershell
$membershipAction = if (Get-ScheduledTask -TaskName 'BloodMatch-Application-MembershipEmail' -ErrorAction SilentlyContinue) { 'Upgrade' } else { 'Install' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action $membershipAction -Kind MembershipEmail
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/manage_application_tasks.ps1 -Action Verify -Kind MembershipEmail
```

Creating/upgrading this schedule enables future scoped decisions on its configured machine; do not also install the general Email task. The existing local membership schedule was already observed running, so there is no need to reinstall it for the current investigation. `sent`/`emailed_at` indicate SMTP acceptance, not proof of Inbox placement; inspect Inbox/Spam separately when accepted.

No agent-run database processing, task installation/start or real-email experiment was performed for this checkpoint. Current local Safety flag was changed at the user's explicit request. Apply/run/schedule commands remain user-run.
