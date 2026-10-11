# User-run fixed-target preview/backup/application. No server/task installation or SMTP.
[CmdletBinding()]
param(
    [ValidateSet('Preview','BackupReview','Apply')][string]$Action='Preview',
    [ValidateSet('test','application')][string]$Target='test',
    [string]$ReviewFile='',
    [switch]$MaintenanceConfirmed,
    [string]$PhpPath='C:\xampp\php\php.exe',
    [string]$MysqlDumpPath='C:\xampp\mysql\bin\mysqldump.exe'
)
$ErrorActionPreference='Stop'
$workspace=Split-Path $PSScriptRoot -Parent
Set-Location $workspace
$database=if ($Target -eq 'test') {'bloodmatch_test'} else {'bloodmatch'}
$helper=Join-Path $workspace 'database\review_staff_role_migration.php'
if ($Action -eq 'Preview') {
    & $PhpPath $helper "--target=$Target"
    if ($LASTEXITCODE -ne 0) { throw 'Read-only impact preview failed.' }
    exit 0
}
if ($Action -eq 'BackupReview') {
    $reviewText=@(& $PhpPath $helper "--target=$Target") -join "`n"
    if ($LASTEXITCODE -ne 0) { throw 'Impact preview failed; no backup/application attempted.' }
    $review=$reviewText | ConvertFrom-Json
    if ($review.database -ne $database -or $review.port -ne 3306 -or $review.writes) { throw 'Unexpected preview target.' }
    $folder=Join-Path $env:LOCALAPPDATA ('BloodMatch\Backups\before-024-'+$Target+'-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
    if (Test-Path -LiteralPath $folder) { throw 'Backup destination exists; refuse overwrite.' }
    New-Item -ItemType Directory -Path $folder -Force | Out-Null
    $backup=Join-Path $folder ($database+'.sql')
    & $MysqlDumpPath --no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=root --password --single-transaction --quick --routines --events --triggers --databases $database "--result-file=$backup"
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed; retain partial file. No migration performed.' }
    if (-not (Get-Content -LiteralPath $backup -Tail 15 | Select-String '^-- Dump completed on')) { throw 'Backup completion marker missing; no migration performed.' }
    # Refresh the review after backup. Any later account changes make Apply fail closed.
    $reviewText=@(& $PhpPath $helper "--target=$Target") -join "`n"
    if ($LASTEXITCODE -ne 0) { throw 'Refreshed impact preview failed.' }
    $review=$reviewText | ConvertFrom-Json
    if ($review.database -ne $database -or $review.port -ne 3306 -or $review.writes) { throw 'Unexpected refreshed target.' }
    $reviewText | Set-Content -LiteralPath (Join-Path $folder 'impact-review.json') -Encoding UTF8
    $record=[ordered]@{database=$database;port=3306;target=$Target;backup_file=$backup;backup_sha256=(Get-FileHash -LiteralPath $backup -Algorithm SHA256).Hash;sql_sha256=$review.sql_sha256;review_sha256=$review.review_sha256;migration=$review.migration}
    $recordFile=Join-Path $folder 'checkpoint.json'
    $record | ConvertTo-Json | Set-Content -LiteralPath $recordFile -Encoding UTF8
    Write-Output $reviewText
    Write-Output "Backup SHA256: $($record.backup_sha256)"
    Write-Output "Saved checkpoint JSON: $recordFile"
    Write-Output 'Read the affected accounts/permissions before running the separate Apply command.'
    exit 0
}
if (-not $MaintenanceConfirmed) { throw 'Stop application/test PHP writers and workers first; Apply requires -MaintenanceConfirmed.' }
if (-not $ReviewFile) { $ReviewFile=(Read-Host 'Paste the complete saved checkpoint.json path').Trim().Trim('"') }
$record=Get-Content -LiteralPath $ReviewFile -Raw | ConvertFrom-Json
if ($record.database -ne $database -or $record.target -ne $Target -or $record.port -ne 3306 -or $record.migration -ne '024_consolidate_staff_role.sql') { throw 'Checkpoint target mismatch.' }
if ((Get-FileHash -LiteralPath $record.backup_file -Algorithm SHA256).Hash -ne $record.backup_sha256) { throw 'Backup hash changed; stop.' }
& $PhpPath $helper "--target=$Target" --apply --maintenance-confirmed=yes "--expect-sql-sha256=$($record.sql_sha256)" "--expect-review-sha256=$($record.review_sha256)"
if ($LASTEXITCODE -ne 0) { throw '024-only application failed; retain backup and inspect partial state. Do not drop a database.' }
& $PhpPath $helper "--target=$Target"
if ($LASTEXITCODE -ne 0) { throw 'Read-only post-application review failed.' }
