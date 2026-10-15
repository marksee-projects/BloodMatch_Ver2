# tests/run_isolated.ps1
# Runs one explicitly selected current suite against bloodmatch_test ONLY.
# - Starts a separate PHP server on port 8001 pointed at bloodmatch_test.
# - Forces SMTP to a closed local port, so no real email can ever be sent
#   and mail attempts fail instantly instead of hanging.
# - Always stops the server and restores your environment variables.
#
# Usage (from the repo root):
#   powershell -ExecutionPolicy Bypass -File tests\run_isolated.ps1 -Suite staff_role_api.ps1

param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [int]$Port = 8001,
    [string]$DbPort = '3306',
    [string]$Suite = ''
)

$db = 'bloodmatch_test'
if (-not $Suite) { throw 'Select a current suite explicitly. For CP0 use -Suite staff_role_api.ps1; the old default full suite is retired.' }
$retiredRoleSuites = @('phase4.ps1','phase5.ps1','phase6.ps1','phase7.ps1','phase8.ps1','phase9.ps1','phase10.ps1','phase11.ps1','phase12.ps1','donor_refresh.ps1','home_safety.ps1','home_respond.ps1','profile_view.ps1','rejection_reason.ps1','step4_email_flow.ps1')
if ($Suite -in $retiredRoleSuites) { throw 'This suite contains superseded Officer roles/chapter permissions. Use the canonical staff_role_api.ps1 CP0 replacement; no server or fixtures started.' }
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

$names = 'DB_HOST', 'DB_PORT', 'DB_NAME', 'TEST_DB_NAME', 'TEST_DB_PORT', 'MAIL_HOST', 'MAIL_PORT', 'APP_SECURE_COOKIES'
$saved = @{}
foreach ($n in $names) { $saved[$n] = [Environment]::GetEnvironmentVariable($n, 'Process') }

$out = Join-Path $env:TEMP 'bm_isolated_tests.txt'
$srv = $null
$testExit = 0
$testSessionRoot = Join-Path $root 'backend/storage/test-sessions'
$testSessionPath = Join-Path $testSessionRoot ([Guid]::NewGuid().ToString('N'))
[System.IO.Directory]::CreateDirectory($testSessionPath) | Out-Null

try {
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = $DbPort
    $env:DB_NAME = $db
    $env:TEST_DB_NAME = $db
    $env:TEST_DB_PORT = $DbPort
    $env:MAIL_HOST = '127.0.0.1'
    $env:MAIL_PORT = '1'
    $env:APP_SECURE_COOKIES = 'false'

    $srv = Start-Process $PhpPath -ArgumentList '-d', 'variables_order=EGPCS', '-d', "session.save_path=`"$testSessionPath`"", '-S', "127.0.0.1:$Port", '-t', 'backend/public' -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2

    if ($Suite) {
        if ([System.IO.Path]::GetFileName($Suite) -ne $Suite -or $Suite -notmatch '^[A-Za-z0-9_.-]+\.ps1$') {
            throw 'Suite must be a PowerShell filename from the tests directory.'
        }
        $suitePath = Join-Path $PSScriptRoot $Suite
        if (-not (Test-Path -LiteralPath $suitePath -PathType Leaf)) { throw "Test suite not found: $Suite" }
        & powershell -ExecutionPolicy Bypass -File $suitePath -BaseUrl "http://127.0.0.1:$Port" -MysqlPath $MysqlPath -PhpPath $PhpPath 2>&1 | Tee-Object -FilePath $out | Out-Host
        $testExit = $LASTEXITCODE
    } else {
        & powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'run_all.ps1') -BaseUrl "http://127.0.0.1:$Port" -MysqlPath $MysqlPath -PhpPath $PhpPath 2>&1 | Tee-Object -FilePath $out | Out-Host
        $testExit = $LASTEXITCODE
    }

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
    $resolvedTestSessionPath = [System.IO.Path]::GetFullPath($testSessionPath)
    $resolvedTestSessionRoot = [System.IO.Path]::GetFullPath($testSessionRoot).TrimEnd('\') + '\'
    if (-not $resolvedTestSessionPath.StartsWith($resolvedTestSessionRoot, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'Unexpected test-session cleanup path.' }
    if (Test-Path -LiteralPath $resolvedTestSessionPath) { Remove-Item -LiteralPath $resolvedTestSessionPath -Recurse -Force }
    foreach ($n in $names) { [Environment]::SetEnvironmentVariable($n, $saved[$n], 'Process') }
    Write-Host 'Test server stopped and environment restored.'
}

if ($testExit -ne 0) { exit $testExit }
