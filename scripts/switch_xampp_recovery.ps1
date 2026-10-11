# Approved user-run cutover; no deletion, scheduling, SMTP or automatic server start.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$mysqlRoot = 'C:\xampp\mysql'
$candidate = Join-Path $mysqlRoot 'data_recovered_20261011-114808'
$activeData = Join-Path $mysqlRoot 'data'
$checkpoint = 'C:\Users\PC\BloodMatch-Recovery-20261011-111103\Validated-All-20261011-113943\Candidate-20261011-114808'
$candidateConfig = Join-Path $checkpoint 'candidate.ini'
$backup = 'C:\Users\PC\BloodMatch-Recovery-20261011-111103\Validated-All-20261011-113943\all-four-validated.sql'
if ((Get-FileHash -LiteralPath $backup -Algorithm SHA256).Hash -ne 'CA7B809ECF0584F4E24980BBEE95D820434E9124F46DD6E3D23D653BDBB5113B') {
    throw 'Validated backup hash changed; stop.'
}
foreach ($path in @($candidate,$activeData)) {
    if (-not (Test-Path -LiteralPath $path -PathType Container)) { throw "Required folder missing: $path" }
    if ([IO.Path]::GetFullPath((Split-Path $path -Parent)).TrimEnd('\') -ne $mysqlRoot) {
        throw 'Folder is outside the approved XAMPP root.'
    }
    if ((Get-Item -LiteralPath $path).Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Refuse directory junction/symlink cutover.'
    }
}
$workspace = Split-Path $PSScriptRoot -Parent
foreach ($flag in @('SAFETY_AUTOMATION_ENABLED','EMAIL_WORKER_ENABLED')) {
    if (-not (Select-String -LiteralPath (Join-Path $workspace '.env') -Pattern ('^' + $flag + '=0\s*$') -Quiet)) {
        throw "$flag must stay 0."
    }
}
$ini = Join-Path $mysqlRoot 'bin\my.ini'
$iniText = Get-Content -LiteralPath $ini -Raw
foreach ($setting in @('datadir','innodb_data_home_dir','innodb_log_group_home_dir')) {
    if ($iniText -notmatch ('(?m)^\s*' + $setting + '\s*=\s*"?C:/xampp/mysql/data"?\s*$')) {
        throw "Unexpected application $setting configuration."
    }
}
if ($iniText -match '(?m)^\s*innodb_force_recovery\s*=\s*[1-6]') { throw 'Application recovery mode is enabled; refuse cutover.' }
if ($iniText -match '(?m)^\s*port\s*=\s*(?!3306\s*$)\d+') { throw 'Unexpected configured application port.' }
$servers = @(Get-CimInstance Win32_Process -Filter "name='mysqld.exe'")
if ($servers.Count -ne 1 -or $servers[0].CommandLine -notlike ('*--defaults-file="' + $candidateConfig + '"*')) {
    throw 'Unexpected running MySQL process; refuse cutover.'
}
$connection = @('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3309','--user=root','--password')
$identity = & (Join-Path $mysqlRoot 'bin\mysql.exe') @connection --batch --raw --skip-column-names "--execute=SELECT CONCAT(@@port,'|',@@innodb_force_recovery,'|',@@datadir,'|',(SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('bloodmatch','bloodmatch_test','infomanagement_db','resort_db')))"
if ($LASTEXITCODE -ne 0) { throw 'Candidate identity check failed.' }
$parts = ($identity -join '').Trim().Split('|')
if ($parts.Count -ne 4 -or $parts[0] -ne '3309' -or $parts[1] -ne '0' -or
    $parts[2].Replace('/','\').TrimEnd('\') -ne $candidate -or $parts[3] -ne '4') {
    throw 'Candidate identity/database inventory differs; stop.'
}
& (Join-Path $mysqlRoot 'bin\mysqladmin.exe') @connection shutdown
if ($LASTEXITCODE -ne 0) { throw 'Candidate graceful shutdown failed; no folder changes.' }
Start-Sleep -Seconds 3
if (Get-Process mysqld,mariadbd -ErrorAction SilentlyContinue) { throw 'Database process remains; no folder changes.' }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$externalBackup = Join-Path $checkpoint "Before-Switch-$stamp"
$retainedData = Join-Path $mysqlRoot "data_before_recovery_$stamp"
foreach ($path in @($externalBackup,$retainedData)) {
    if (Test-Path -LiteralPath $path) { throw 'Preservation destination exists; stop.' }
}
if ([IO.Path]::GetFullPath((Split-Path $retainedData -Parent)).TrimEnd('\') -ne $mysqlRoot) {
    throw 'Preservation target outside XAMPP root.'
}
New-Item -ItemType Directory -Path $externalBackup | Out-Null
Copy-Item -LiteralPath $ini -Destination (Join-Path $externalBackup 'xampp-my.ini')
$coldBackup = Join-Path $externalBackup 'current-xampp-data'
robocopy "$activeData" "$coldBackup" /E /COPY:DAT /DCOPY:DAT /R:1 /W:1 /XJ /NFL /NDL
if ($LASTEXITCODE -ge 8) { throw 'Pre-switch data backup failed; no folder switch.' }
robocopy "$activeData" "$coldBackup" /L /E /COPY:DAT /DCOPY:DAT /R:0 /W:0 /XJ /NFL /NDL
if ($LASTEXITCODE -ne 0) { throw 'Pre-switch backup differs; no folder switch.' }

# Checked literal siblings under C:\xampp\mysql. Retain the previous folder.
Move-Item -LiteralPath $activeData -Destination $retainedData
try {
    Move-Item -LiteralPath $candidate -Destination $activeData
} catch {
    if (-not (Test-Path -LiteralPath $activeData)) {
        Move-Item -LiteralPath $retainedData -Destination $activeData
    }
    throw
}
[pscustomobject]@{
    status='switched_mysql_still_stopped'
    database='bloodmatch'
    application_port=3306
    active_data_directory=$activeData
    retained_previous_data=$retainedData
    external_pre_switch_backup=$externalBackup
    config_changed=$false
    schedules_enabled=$false
} | ConvertTo-Json
