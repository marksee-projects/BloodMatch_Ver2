# Application 023-only checkpoint

Completed: user supplied missing/unrecorded review, ready/recorded apply, final ready/recorded review on bloodmatch at 127.0.0.1:3306 with matching SHA, historical_backfill=false and terminal pass. Backup path supplied: C:\Users\PC\AppData\Local\Temp\bloodmatch-before-023-20261010-224139.sql; its contents were not agent-inspected. Accept without repeat DDL/review/cleanup. Creating this table proves neither actual transport nor schedule execution.

Approved donor-cap hardening scope; target **bloodmatch, 127.0.0.1:3306**. Existing application 020/021/022 and passed test checkpoints (65 fake worker, 20 API, 8 storage) are retained. Apply only 023, no repeat tests or other migrations.

023 adds email_donor_reservations with an FK to committed shared attempts. Outstanding/accepted/uncertain sends retain donor capacity; only conclusive non-acceptance releases the donor slot, never the shared budget. Structure only: no historical attempts/jobs backfilled, no resend and no attempt cleanup. Current SHA-256: `546182bf9fb7b1187c0d4a9591e65c5ba3806f45c854214986b32582b2504937`.

Prerequisites: repository root in PowerShell, PHP/XAMPP MySQL running; stop application/database/mail/schedule writers through final verification. Keep automatic-processing flags off; no task/send command in this checkpoint.

```powershell
$php = 'C:\xampp\php\php.exe'
$hash023 = '546182bf9fb7b1187c0d4a9591e65c5ba3806f45c854214986b32582b2504937'

# 1. Read-only review
$review023 = & $php database\review_operational_schema.php --target=application --migration=023 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '023 review failed; stop.' }
$review023 | ConvertTo-Json -Depth 5
if ($review023.database -ne 'bloodmatch' -or $review023.port -ne 3306 -or $review023.sql_sha256 -ne $hash023) { throw 'Target/checksum mismatch; stop.' }
$missing023 = $review023.shape.email_donor_reservations -eq 'missing' -and -not $review023.recorded
$ready023 = $review023.shape.email_donor_reservations -eq 'ready' -and $review023.recorded
if (-not $missing023 -and -not $ready023) { throw 'Partial schema/ledger state; stop and inspect.' }

if ($missing023) {
    # 2. Backup outside the repository, retained
    $backup023 = Join-Path $env:TEMP ('bloodmatch-before-023-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
    & 'C:\xampp\mysql\bin\mysqldump.exe' -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers bloodmatch "--result-file=$backup023"
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed; stop.' }
    if (-not (Test-Path -LiteralPath $backup023) -or (Get-Item -LiteralPath $backup023).Length -eq 0) { throw 'Backup missing/empty; stop.' }
    Write-Output "Backup retained: $backup023"

    # 3. Apply ONLY 023
    & $php database\review_operational_schema.php --target=application --migration=023 --apply "--sha256=$hash023"
    if ($LASTEXITCODE -ne 0) { throw '023 application failed; stop and inspect partial objects.' }
}

# 4. Read-only verification
$verify023 = & $php database\review_operational_schema.php --target=application --migration=023 | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw '023 verification failed; stop.' }
$verify023 | ConvertTo-Json -Depth 5
if ($verify023.database -ne 'bloodmatch' -or $verify023.port -ne 3306 -or $verify023.shape.email_donor_reservations -ne 'ready' -or -not $verify023.recorded -or $verify023.sql_sha256 -ne $hash023) { throw '023 shape/ledger verification failed; stop.' }
Write-Output '023-only application checkpoint passed: reservation table ready/recorded; structure only.'
```

Expected: ready/recorded=true, matching target/SHA. Already ready/recorded skips repeat DDL. Cleanup: none; retain SQL backup and ledger/output, never reset attempt/reservation history. DDL auto-commits: on partial failure stop, never drop/reset or retry blindly. Send final verification before operational activation. Real email needs the later guarded experiment and quiet cutover; creating the table starts neither SMTP nor schedules. Application keys/conversion are separate.
