# tests/run_isolated.ps1
# Runs the full regression suite against bloodmatch_test ONLY.
# - Starts a separate PHP server on port 8001 pointed at bloodmatch_test.
# - Forces SMTP to a closed local port, so no real email can ever be sent
#   and mail attempts fail instantly instead of hanging.
# - Always stops the server and restores your environment variables.
#
# Usage (from the repo root):
#   powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1

param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [int]$Port = 8001,
    [string]$DbPort = '3306'
)

$db = 'bloodmatch_test'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Test-Path $PhpPath)) { throw "PHP not found at $PhpPath" }
if (-not (Test-Path $MysqlPath)) { throw "mysql.exe not found at $MysqlPath" }
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    throw "Port $Port is already in use. Stop that server first."
}

$check = & $MysqlPath -h 127.0.0.1 -P $DbPort -u root -N -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db';"
if (("$check").Trim() -ne $db) {
    throw "Cannot reach MySQL on port $DbPort, or database $db does not exist."
}
Write-Host "=== TEST DB: $db (port $DbPort) ===" -ForegroundColor Cyan

$names = 'DB_HOST', 'DB_PORT', 'DB_NAME', 'TEST_DB_NAME', 'TEST_DB_PORT', 'MAIL_HOST', 'MAIL_PORT'
$saved = @{}
foreach ($n in $names) { $saved[$n] = [Environment]::GetEnvironmentVariable($n, 'Process') }

$out = Join-Path $env:TEMP 'bm_isolated_tests.txt'
$srv = $null

try {
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = $DbPort
    $env:DB_NAME = $db
    $env:TEST_DB_NAME = $db
    $env:TEST_DB_PORT = $DbPort
    $env:MAIL_HOST = '127.0.0.1'
    $env:MAIL_PORT = '1'

    $srv = Start-Process $PhpPath -ArgumentList '-d', 'variables_order=EGPCS', '-S', "127.0.0.1:$Port", '-t', 'backend/public' -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2

    & powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'run_all.ps1') -BaseUrl "http://127.0.0.1:$Port" -MysqlPath $MysqlPath -PhpPath $PhpPath 2>&1 | Tee-Object -FilePath $out | Out-Host

    $lines = @(Get-Content $out | Where-Object { $_ -match '^==.*\d+ passed, \d+ failed' })
    $pass = 0
    $fail = 0
    foreach ($l in $lines) {
        if ($l -match '(\d+) passed, (\d+) failed') {
            $pass += [int]$matches[1]
            $fail += [int]$matches[2]
        }
    }
    Write-Host ''
    Write-Host "SUITES REPORTING: $($lines.Count)   ASSERTIONS PASSED: $pass   FAILED: $fail" -ForegroundColor Cyan

    $errors = @(Get-Content $out | Where-Object { $_ -match '^ERROR:' })
    foreach ($e in $errors) { Write-Host $e -ForegroundColor Red }
}
finally {
    if ($srv) { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
    foreach ($n in $names) { [Environment]::SetEnvironmentVariable($n, $saved[$n], 'Process') }
    Write-Host 'Test server stopped and environment restored.'
}
