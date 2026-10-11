# User-run cold staging/startup checkpoint. Never replaces XAMPP data/data_old.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$mysqlRoot = 'C:\xampp\mysql'
$cleanData = 'C:\Users\PC\BM-Clean-Validation-20261011-112410\data'
$backupRoot = 'C:\Users\PC\BloodMatch-Recovery-20261011-111103\Validated-All-20261011-113943'
$backupFile = Join-Path $backupRoot 'all-four-validated.sql'
$expectedHash = 'CA7B809ECF0584F4E24980BBEE95D820434E9124F46DD6E3D23D653BDBB5113B'
if ((Get-FileHash -LiteralPath $backupFile -Algorithm SHA256).Hash -ne $expectedHash) {
    throw 'Validated backup changed; stop.'
}
if (-not (Test-Path -LiteralPath $cleanData -PathType Container)) { throw 'Clean data folder missing.' }
$workspace = Split-Path $PSScriptRoot -Parent
foreach ($flag in @('SAFETY_AUTOMATION_ENABLED','EMAIL_WORKER_ENABLED')) {
    if (-not (Select-String -LiteralPath (Join-Path $workspace '.env') -Pattern ('^' + $flag + '=0\s*$') -Quiet)) {
        throw "$flag must remain 0."
    }
}
if (Get-ChildItem -LiteralPath $mysqlRoot -Directory -Filter 'data_recovered_*') {
    throw 'A staging folder already exists; review it instead of repeating the copy.'
}
if (Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(3306,3309) }) {
    throw 'Application or clean server still listening; stop without copying.'
}
$servers = @(Get-CimInstance Win32_Process -Filter "name='mysqld.exe'")
if ($servers.Count -gt 1 -or ($servers.Count -eq 1 -and
    $servers[0].CommandLine -notlike '*--defaults-file="C:\Users\PC\BM-Recovery-Level6-20261011-111722\recovery.ini"*')) {
    throw 'Unexpected MySQL process; stop without modifying anything.'
}
if ($servers.Count -eq 1) {
    & (Join-Path $mysqlRoot 'bin\mysqladmin.exe') --no-defaults --protocol=TCP --host=127.0.0.1 --port=3308 --user=root --password shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Recovery server graceful shutdown failed; no copy performed.' }
}
Start-Sleep -Seconds 3
if (Get-Process mysqld,mariadbd -ErrorAction SilentlyContinue) { throw 'Database process remains running; refuse cold copy.' }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stageData = Join-Path $mysqlRoot "data_recovered_$stamp"
$checkpointRoot = Join-Path $backupRoot "Candidate-$stamp"
if ((Test-Path -LiteralPath $stageData) -or (Test-Path -LiteralPath $checkpointRoot)) { throw 'Candidate destination exists.' }
New-Item -ItemType Directory -Path $checkpointRoot | Out-Null
robocopy "$cleanData" "$stageData" /E /COPY:DAT /DCOPY:DAT /R:1 /W:1 /XJ /NFL /NDL
if ($LASTEXITCODE -ge 8) { throw 'Cold candidate copy failed; preserve partial copy.' }
robocopy "$cleanData" "$stageData" /L /E /COPY:DAT /DCOPY:DAT /R:0 /W:0 /XJ /NFL /NDL
if ($LASTEXITCODE -ne 0) { throw 'Cold comparison found differences; refuse startup.' }

$dataPath = $stageData.Replace('\','/')
$logPath = $checkpointRoot.Replace('\','/')
$configFile = Join-Path $checkpointRoot 'candidate.ini'
@"
[mysqld]
basedir=C:/xampp/mysql
datadir=$dataPath
tmpdir=$logPath
port=3309
bind-address=127.0.0.1
pid-file=$logPath/candidate.pid
log-error=$logPath/candidate-error.log
innodb_data_home_dir=$dataPath
innodb_log_group_home_dir=$dataPath
innodb_data_file_path=ibdata1:10M:autoextend
innodb_log_file_size=5M
innodb_force_recovery=0
read_only=ON
event_scheduler=OFF
skip-slave-start
"@ | Set-Content -LiteralPath $configFile -Encoding ASCII
$stdoutFile = Join-Path $checkpointRoot 'startup-stdout.log'
$stderrFile = Join-Path $checkpointRoot 'startup-stderr.log'
$server = Start-Process -FilePath (Join-Path $mysqlRoot 'bin\mysqld.exe') -ArgumentList ('--defaults-file="' + $configFile + '"') -WindowStyle Hidden -RedirectStandardOutput $stdoutFile -RedirectStandardError $stderrFile -PassThru
Start-Sleep -Seconds 5
$server.Refresh()
foreach ($log in @((Join-Path $checkpointRoot 'candidate-error.log'),$stderrFile,$stdoutFile)) {
    if (Test-Path -LiteralPath $log) {
        Write-Output "Log: $log"
        Get-Content -LiteralPath $log -Tail 25
    }
}
if ($server.HasExited) { throw 'Candidate exited; keep files and send the captured logs. Do not restart.' }
$listeners = @(Get-NetTCPConnection -State Listen -LocalPort 3309 -ErrorAction SilentlyContinue | Where-Object { $_.OwningProcess -eq $server.Id })
if ($listeners.Count -eq 0) { throw 'Candidate not listening yet; send logs, do not switch or retry.' }
[pscustomobject]@{
    status='candidate_running_not_switched'
    port=3309
    process_id=$server.Id
    staged_data_directory=$stageData
    checkpoint_directory=$checkpointRoot
    application_switched=$false
} | ConvertTo-Json
