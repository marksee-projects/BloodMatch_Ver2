# Application 020-only checkpoint

User approval: **bloodmatch at 127.0.0.1:3306**, only `020_donation_report_rejection_reason.sql`, structure only. Adds nullable `rejection_reason VARCHAR(500)`; no report/status/reason backfill. Agent has not applied it. Current source SHA-256: `d2669a60ee8826f1db0a06eeafbc16cbae772c125ead1e538e2332e65b21c9d0`.

Prerequisites: repository root in PowerShell; PHP/XAMPP MySQL running; stop application, schedule and database writers. Keep writers stopped through final preservation verification. Existing local root connection follows the previous setup; if it requires a password, use interactive `-p` for mysqldump, never put a password in the command. No real-email/suite/task/conversion command is involved.

Run in this order, in one PowerShell session:

```powershell
$php = 'C:\xampp\php\php.exe'
$expected020Hash = 'd2669a60ee8826f1db0a06eeafbc16cbae772c125ead1e538e2332e65b21c9d0'

# 1. Read-only review: target, shape, ledger and existing-report fingerprint.
$before020 = & $php database\review_operational_schema.php --target=application --migration=020 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '020 review failed; stop.' }
$before020 | ConvertTo-Json -Depth 6
if ($before020.database -ne 'bloodmatch' -or $before020.port -ne 3306 -or $before020.sql_sha256 -ne $expected020Hash) { throw 'Target/checksum mismatch; stop.' }

$needs020 = $before020.shape.donation_report_rejection_reason -eq 'missing' -and -not $before020.recorded
if (-not $needs020 -and -not ($before020.shape.donation_report_rejection_reason -eq 'ready' -and $before020.recorded)) { throw 'Partial schema/ledger state; stop and retain evidence.' }

if ($needs020) {
    # 2. Native backup outside the repository; retain it.
    $backup020 = Join-Path $env:TEMP ('bloodmatch-before-020-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
    & 'C:\xampp\mysql\bin\mysqldump.exe' -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers bloodmatch "--result-file=$backup020"
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed; do not apply 020.' }
    if (-not (Test-Path -LiteralPath $backup020 -PathType Leaf) -or (Get-Item -LiteralPath $backup020).Length -eq 0) { throw 'Backup missing/empty; stop.' }
    Write-Output "Backup retained: $backup020"

    # 3. Apply ONLY 020. No generic migration runner or other migration.
    $apply020 = & $php database\review_operational_schema.php --target=application --migration=020 --apply --acknowledge-application-020 "--sha256=$expected020Hash" | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw '020 apply failed; stop and inspect partial objects. Do not reset/reapply blindly.' }
    $apply020 | ConvertTo-Json -Depth 6
    if ($apply020.reports_preserved_during_apply -ne $true) { throw 'Report preservation not confirmed; stop.' }
} else {
    Write-Output '020 already ready/recorded; no backup or application repeated.'
}

# 4. Read-only verification, with the same stopped-writer baseline.
$after020 = & $php database\review_operational_schema.php --target=application --migration=020 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '020 verification failed; stop.' }
$after020 | ConvertTo-Json -Depth 6
if ($after020.database -ne 'bloodmatch' -or $after020.port -ne 3306 -or $after020.shape.donation_report_rejection_reason -ne 'ready' -or -not $after020.recorded -or $after020.sql_sha256 -ne $expected020Hash) { throw '020 shape/ledger verification failed; stop.' }
if ($after020.reports.report_count -ne $before020.reports.report_count -or $after020.reports.existing_fields_sha256 -ne $before020.reports.existing_fields_sha256) { throw 'Existing report fields changed; stop and inspect.' }
if ($needs020 -and $after020.reports.reports_with_rejection_reason -ne 0) { throw 'Existing reasons are not all NULL; stop and inspect.' }
Write-Output '020-only checkpoint passed: ready/recorded; existing report fields preserved; no backfill.'
```

Expected fresh application: review `missing`/recorded=false; apply `ready`/recorded=true/reports_preserved_during_apply=true; final review mode, same full-row fingerprint/count/statuses, zero non-NULL reasons. Full report contents are never emitted. If already applied, it is reviewed and verified without repeat DDL; existing populated reasons are not cleared. The helper's migration ledger insert is the only write besides the scoped ADD COLUMN.

Cleanup: none. Retain the SQL backup, migration ledger and verification output. DDL auto-commits; a failure may leave a valid column with an incomplete ledger, so stop and report output instead of dropping/resetting objects. Keep unrelated 022/023 application, suites, schedules, file conversion and real emails outside this checkpoint. Send the final review JSON/terminal result before dependent application activation.
