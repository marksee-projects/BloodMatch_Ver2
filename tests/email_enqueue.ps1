param(
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)
# USER-RUN ONLY. PHP fixes and verifies bloodmatch_test:3306; no HTTP/SMTP needed.
$ErrorActionPreference = 'Stop'
& $PhpPath (Join-Path $PSScriptRoot 'email_enqueue.php')
exit $LASTEXITCODE
