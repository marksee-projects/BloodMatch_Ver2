param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

if ($env:TEST_DB_NAME) { $env:DB_NAME = $env:TEST_DB_NAME }
if ($env:TEST_DB_PORT) { $env:DB_PORT = $env:TEST_DB_PORT }

$suites = @(
    'phase3.ps1',
    'phase4.ps1',
    'phase5.ps1',
    'phase6.ps1',
    'phase7.ps1',
    'phase8.ps1',
    'phase9.ps1',
    'phase10.ps1',
    'phase11.ps1',
    'phase12.ps1',
    'location.ps1',
    'phase16_security.ps1'
)

$passed = 0
$failed = 0

foreach ($s in $suites) {
    Write-Host "`n======================================================="
    Write-Host " RUNNING SUITE: $s"
    Write-Host "======================================================="
    $path = Join-Path $PSScriptRoot $s
    & powershell -ExecutionPolicy Bypass -File $path -BaseUrl $BaseUrl -MysqlPath $MysqlPath -PhpPath $PhpPath
    if ($LASTEXITCODE -eq 0) {
        $passed++
    } else {
        $failed++
        Write-Host "ERROR: $s failed with exit code $LASTEXITCODE"
    }
}

Write-Host "`n======================================================="
Write-Host " ALL TEST SUITES FINISHED: $passed passed, $failed failed"
Write-Host "======================================================="

if ($failed -gt 0) { exit 1 } else { exit 0 }
