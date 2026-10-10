# Application 022-only checkpoint

Completed: user supplied missing/unrecorded review, ready/recorded apply, and final ready/recorded read-only JSON for both tables on bloodmatch:3306 with matching SHA and terminal pass. Backup path was supplied; its contents were not agent-inspected. Accept this checkpoint without repeat DDL/review/cleanup. Commands below are retained as a record; no schedule/automatic processing is established by this result.

Prepared under the approved standby scope and confirmed target **bloodmatch, 127.0.0.1:3306**. Existing application 020 user pass and 021 readiness are retained. Independent of the document-storage test; no migration/task is executed by the agent.

022 adds only donor_response_state and donor_opportunities with approved FKs/indexes. No historical timers/accounts/notifications are backfilled. After application, future committed opportunity events can record separate generation history; automatic processing still requires its separately gated runner/schedule. Post-donation safety windows are unchanged. Current SHA-256: `c058bc135204dedf47e60d5ce6efcde884df5af0b9b1dab4c5252cc6b11bc67e`.

Prerequisites: PowerShell at repository root, XAMPP/MySQL running; stop application/database/mail/schedule writers through review/backup/application/verification. Keep automatic-processing flags off and do not install/start tasks. Run only this file, not 023 or all pending migrations:

```powershell
$php = 'C:\xampp\php\php.exe'
$hash022 = 'c058bc135204dedf47e60d5ce6efcde884df5af0b9b1dab4c5252cc6b11bc67e'

# Read-only review
$review022 = & $php database\review_operational_schema.php --target=application --migration=022 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '022 review failed; stop.' }
$review022 | ConvertTo-Json -Depth 5
if ($review022.database -ne 'bloodmatch' -or $review022.port -ne 3306 -or $review022.sql_sha256 -ne $hash022) { throw 'Target/checksum mismatch; stop.' }
$missing022 = $review022.shape.donor_response_state -eq 'missing' -and $review022.shape.donor_opportunities -eq 'missing' -and -not $review022.recorded
$ready022 = $review022.shape.donor_response_state -eq 'ready' -and $review022.shape.donor_opportunities -eq 'ready' -and $review022.recorded
if (-not $missing022 -and -not $ready022) { throw 'Partial schema/ledger state; stop and inspect.' }

if ($missing022) {
    # Native backup outside the repository, retained
    $backup022 = Join-Path $env:TEMP ('bloodmatch-before-022-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
    & 'C:\xampp\mysql\bin\mysqldump.exe' -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers bloodmatch "--result-file=$backup022"
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed; stop.' }
    if (-not (Test-Path -LiteralPath $backup022) -or (Get-Item -LiteralPath $backup022).Length -eq 0) { throw 'Backup missing/empty; stop.' }
    Write-Output "Backup retained: $backup022"

    # Apply ONLY 022
    & $php database\review_operational_schema.php --target=application --migration=022 --apply "--sha256=$hash022"
    if ($LASTEXITCODE -ne 0) { throw '022 apply failed; stop and inspect partial objects.' }
}

# Read-only verification
$verify022 = & $php database\review_operational_schema.php --target=application --migration=022 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '022 verification failed; stop.' }
$verify022 | ConvertTo-Json -Depth 5
if ($verify022.database -ne 'bloodmatch' -or $verify022.port -ne 3306 -or $verify022.shape.donor_response_state -ne 'ready' -or $verify022.shape.donor_opportunities -ne 'ready' -or -not $verify022.recorded -or $verify022.sql_sha256 -ne $hash022) { throw '022 shape/ledger verification failed; stop.' }
Write-Output '022-only application checkpoint passed: both tables ready/recorded; structure only.'
```

Expected: review missing/unrecorded (or already ready/recorded); final review both tables ready and recorded=true, matching fixed target/SHA. Ready/recorded files skip repeat DDL. Cleanup: none; retain backup/ledger/output. DDL auto-commits: stop on any partial state, never drop/reset or retry blindly. Supply final verification before activation. Real emails, 023 application, tasks and historical conversions stay outside this checkpoint.
