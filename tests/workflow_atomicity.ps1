param(
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

# User-run only, through run_isolated.ps1. PHP guard requires bloodmatch_test.
# Uses direct services and parallel PHP workers, not a single-threaded HTTP server.
$ErrorActionPreference = 'Stop'
& $PhpPath (Join-Path $PSScriptRoot 'workflow_atomicity.php')
exit $LASTEXITCODE
