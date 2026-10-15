param(
    [ValidateSet('bloodmatch','bloodmatch_test')][string]$Target='bloodmatch_test',
    [ValidateSet('Review','Backup','Repair','Cleanup')][string]$Action='Review',
    [string]$PhpPath='C:\xampp\php\php.exe',
    [switch]$Offline
)
$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$scriptPath=Join-Path $root 'database/demo_maintenance.php'
if ($Action -eq 'Backup') {
    & $PhpPath $scriptPath "--target=$Target" --action=backup
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed.' }
    exit
}
$text=(& $PhpPath $scriptPath "--target=$Target" | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Read-only maintenance review failed.' }
$review=$text | ConvertFrom-Json
$review | ConvertTo-Json -Depth 8 | Write-Output
if ($Action -eq 'Review') { exit }
if ($review.review_ready -ne $true -or -not $review.review -or -not $review.sha256) {
    throw "Maintenance blocked: $($review.review_blocked_reason) No applicable review manifest was generated."
}
if ($Action -eq 'Cleanup' -and $review.cleanup_allowed -ne $true) {
    throw "Cleanup blocked: $($review.cleanup_blocked_reason) No account changes applied."
}
if (-not $Offline) { throw 'Stop backend servers and scheduled workers, then use -Offline. No database maintenance applied.' }
$mode=if ($Action -eq 'Repair') { 'repair-import' } else { 'cleanup-demo' }
& $PhpPath $scriptPath "--target=$Target" "--action=$mode" --apply --offline-ack "--review=$($review.review)" "--sha256=$($review.sha256)"
if ($LASTEXITCODE -ne 0) { throw 'Maintenance stopped. Inspect the output and backup before retrying; DDL can commit partially.' }
