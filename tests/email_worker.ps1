param(
    [string]$BaseUrl='http://127.0.0.1:8001',
    [string]$MysqlPath='C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath='C:\xampp\php\php.exe'
)
# USER-RUN ONLY: fixed actual target and fake callback are enforced by PHP.
$ErrorActionPreference='Stop'
& $PhpPath (Join-Path $PSScriptRoot 'email_worker.php')
exit $LASTEXITCODE
