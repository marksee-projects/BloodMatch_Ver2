param([string]$PhpPath='C:\xampp\php\php.exe')
# USER-RUN ONLY: PHP verifies bloodmatch_test:3306; no SMTP sockets.
$ErrorActionPreference='Stop'
& $PhpPath (Join-Path $PSScriptRoot 'safety_followup.php')
exit $LASTEXITCODE
