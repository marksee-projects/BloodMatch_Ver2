# User-run recovery checkpoint: no application-directory replacement or task activation.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'

$mysqlRoot = 'C:\xampp\mysql'
$preservedRoot = 'C:\Users\PC\BloodMatch-Recovery-20261011-111103'
$recoveryData = 'C:\Users\PC\BM-Recovery-Level6-20261011-111722\data'
$client = Join-Path $mysqlRoot 'bin\mysql.exe'
$dump = Join-Path $mysqlRoot 'bin\mysqldump.exe'
$admin = Join-Path $mysqlRoot 'bin\mysqladmin.exe'
$workspace = Split-Path $PSScriptRoot -Parent

foreach ($flag in @('SAFETY_AUTOMATION_ENABLED','EMAIL_WORKER_ENABLED')) {
    if (-not (Select-String -LiteralPath (Join-Path $workspace '.env') -Pattern ('^' + $flag + '=0\s*$') -Quiet)) {
        throw "$flag must be 0 before staging recovery."
    }
}
if (-not (Test-Path -LiteralPath $preservedRoot -PathType Container)) {
    throw 'Preserved recovery folder missing.'
}
$cleanDirectory = Get-ChildItem -LiteralPath $env:USERPROFILE -Directory -Filter 'BM-Clean-Validation-*' |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $cleanDirectory) { throw 'Clean validation directory missing.' }
$cleanData = Join-Path $cleanDirectory.FullName 'data'

function Connection([int]$Port) {
    @('--no-defaults','--protocol=TCP','--host=127.0.0.1',"--port=$Port",'--user=root','--password')
}
function Assert-Identity([int]$Port, [int]$Level, [string]$Data) {
    $arguments = Connection $Port
    $result = & $client @arguments --batch --raw --skip-column-names "--execute=SELECT CONCAT(@@port,'|',@@innodb_force_recovery,'|',@@datadir)"
    if ($LASTEXITCODE -ne 0) { throw "Identity query failed: $Port" }
    $parts = ($result -join '').Trim().Split('|')
    if ($parts.Count -ne 3 -or $parts[0] -ne "$Port" -or $parts[1] -ne "$Level" -or
        $parts[2].Replace('/','\').TrimEnd('\') -ne $Data.Replace('/','\').TrimEnd('\')) {
        throw "Unexpected database target: $Port"
    }
}
Assert-Identity 3308 6 $recoveryData
Assert-Identity 3309 0 $cleanData

# Check relationships not covered by the previous application-only checkpoint.
$sql = @'
SET SESSION group_concat_max_len=1000000;
SELECT GROUP_CONCAT(check_sql SEPARATOR ' UNION ALL ') INTO @checks FROM (
 SELECT CONCAT('SELECT COUNT(*) AS orphan_rows FROM `',TABLE_SCHEMA,'`.`',TABLE_NAME,'` c WHERE ',
 GROUP_CONCAT(CONCAT('c.`',COLUMN_NAME,'` IS NOT NULL') ORDER BY ORDINAL_POSITION SEPARATOR ' AND '),
 ' AND NOT EXISTS (SELECT 1 FROM `',REFERENCED_TABLE_SCHEMA,'`.`',REFERENCED_TABLE_NAME,'` p WHERE ',
 GROUP_CONCAT(CONCAT('p.`',REFERENCED_COLUMN_NAME,'` = c.`',COLUMN_NAME,'`') ORDER BY ORDINAL_POSITION SEPARATOR ' AND '),')') AS check_sql
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA IN ('bloodmatch_test','infomanagement_db','resort_db') AND REFERENCED_TABLE_NAME IS NOT NULL
 GROUP BY TABLE_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME
) q;
SET @checks=IF(@checks IS NULL,'SELECT 0',CONCAT('SELECT SUM(orphan_rows) FROM (',@checks,') q'));
PREPARE s FROM @checks;
EXECUTE s;
DEALLOCATE PREPARE s;
'@
$arguments = Connection 3309
$orphans = & $client @arguments --batch --raw --skip-column-names "--execute=$sql"
if ($LASTEXITCODE -ne 0 -or ($orphans -join '').Trim() -ne '0') {
    throw 'Other-database relationship check failed. No shutdown/copy performed.'
}
Write-Output 'Other-database orphan rows: 0'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDirectory = Join-Path $preservedRoot "Validated-All-$stamp"
$stageDirectory = Join-Path $mysqlRoot "data_recovered_$stamp"
if ((Test-Path -LiteralPath $backupDirectory) -or (Test-Path -LiteralPath $stageDirectory)) {
    throw 'Checkpoint destination already exists; refuse overwrite.'
}
New-Item -ItemType Directory -Path $backupDirectory | Out-Null
$backupFile = Join-Path $backupDirectory 'all-four-validated.sql'
& $dump @arguments --single-transaction --quick --skip-lock-tables --hex-blob --routines --events --triggers "--result-file=$backupFile" --databases bloodmatch bloodmatch_test infomanagement_db resort_db
if ($LASTEXITCODE -ne 0) { throw "Backup failed; retain partial file: $backupFile" }
$completion = Get-Content -LiteralPath $backupFile -Tail 15 | Select-String '^-- Dump completed on'
if (-not $completion) { throw 'Backup completion marker missing; refuse shutdown/copy.' }
Get-Item -LiteralPath $backupFile | Select-Object FullName,Length | Format-List
Get-FileHash -LiteralPath $backupFile -Algorithm SHA256 | Format-List
$completion.Line
Copy-Item -LiteralPath (Join-Path $mysqlRoot 'bin\my.ini') -Destination (Join-Path $backupDirectory 'xampp-my.ini')

foreach ($port in @(3308,3309)) {
    $shutdownArguments = Connection $port
    & $admin @shutdownArguments shutdown
    if ($LASTEXITCODE -ne 0) { throw "Graceful shutdown failed on $port. Do not copy/switch directories." }
}
Start-Sleep -Seconds 3
if (Get-Process mysqld,mariadbd -ErrorAction SilentlyContinue) {
    throw 'A database server is still running. No cold copy performed.'
}

# Cold copy only. XAMPP data and data_old are never moved or deleted here.
robocopy "$cleanData" "$stageDirectory" /E /COPY:DAT /DCOPY:DAT /R:1 /W:1 /XJ /NFL /NDL
if ($LASTEXITCODE -ge 8) { throw 'Cold copy failed; preserve partial staging folder.' }
robocopy "$cleanData" "$stageDirectory" /L /E /COPY:DAT /DCOPY:DAT /R:0 /W:0 /XJ /NFL /NDL
if ($LASTEXITCODE -ne 0) { throw 'Cold-copy comparison found differences; do not switch.' }

[pscustomobject]@{
    status = 'staged_not_switched'
    backup_directory = $backupDirectory
    staged_data_directory = $stageDirectory
    original_data_directory = Join-Path $mysqlRoot 'data'
    servers_stopped = $true
    application_switched = $false
    schedules_enabled = $false
} | ConvertTo-Json
