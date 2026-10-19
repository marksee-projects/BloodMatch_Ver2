param([string]$BaseUrl='http://127.0.0.1:8001',[string]$MysqlPath='',[string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
& $PhpPath (Join-Path $PSScriptRoot 'donation_photo_http.php') $BaseUrl
if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}
