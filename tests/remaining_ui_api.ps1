# Legacy suite name delegates to the approved canonical staff regression.
param([string]$BaseUrl='http://127.0.0.1:8001',[string]$MysqlPath='C:\xampp\mysql\bin\mysql.exe',[string]$PhpPath='C:\xampp\php\php.exe')
& (Join-Path $PSScriptRoot 'staff_role_api.ps1') -BaseUrl $BaseUrl -MysqlPath $MysqlPath -PhpPath $PhpPath
exit $LASTEXITCODE
