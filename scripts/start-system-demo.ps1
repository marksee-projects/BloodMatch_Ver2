$ErrorActionPreference='Stop'
$demoRoot=Split-Path -Parent $PSScriptRoot
$demoPrivate=Join-Path $demoRoot 'backend/storage/maintenance/DEMO_MAIN_20261022_B42E'
if (!(Test-Path -LiteralPath "$demoPrivate/manifest.json")) { throw 'Prepare the main database demo first.' }
foreach ($demoPort in @(5174,8011,8026,1026)) {
 $demoSocket=New-Object System.Net.Sockets.TcpClient
 try {$demoOpen=$demoSocket.ConnectAsync('127.0.0.1',$demoPort);try {$demoOpen.Wait(500) | Out-Null} catch {};if($demoSocket.Connected){throw "Port $demoPort already in use. Stop only the recorded demo processes before restarting."}}
 finally {$demoSocket.Dispose()}
}
$demoKeys=Get-Content -LiteralPath "$demoPrivate/document-key-location.json" -Raw | ConvertFrom-Json
$demoVars=@{DB_HOST='127.0.0.1';DB_PORT='3306';DB_NAME='bloodmatch';MAIL_HOST='127.0.0.1';MAIL_PORT='1026';MAIL_USER='';MAIL_PASS='';MAIL_FROM='demo@bloodmatch.local';APP_CORS_ORIGINS='http://127.0.0.1:5174';EMAIL_WORKER_ENABLED='0';MEMBERSHIP_EMAIL_ENABLED='0';SAFETY_AUTOMATION_ENABLED='0';DONATION_FOLLOWUP_ENABLED='0';DOCUMENT_ENCRYPTION_MODE='strict';DOCUMENT_KEY_DIRECTORY=$demoKeys.directory;DOCUMENT_ACTIVE_KEY_ID=$demoKeys.id}
$demoOld=@{}
try {
 foreach($demoName in $demoVars.Keys){$demoOld[$demoName]=[Environment]::GetEnvironmentVariable($demoName,'Process');[Environment]::SetEnvironmentVariable($demoName,$demoVars[$demoName],'Process')}
 $demoSessions=Join-Path $demoPrivate 'sessions';New-Item -ItemType Directory -Force -Path $demoSessions | Out-Null
 $demoPhp=Start-Process -FilePath 'C:/xampp/php/php.exe' -ArgumentList @('-d',"session.save_path=$demoSessions",'-S','127.0.0.1:8011','scripts/demo-server.php') -WorkingDirectory $demoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput "$demoPrivate/php-out.log" -RedirectStandardError "$demoPrivate/php-error.log"
 $demoNode=Start-Process -FilePath 'node.exe' -ArgumentList @('scripts/demo-local.mjs') -WorkingDirectory $demoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput "$demoPrivate/node-out.log" -RedirectStandardError "$demoPrivate/node-error.log"
 @{php_pid=$demoPhp.Id;php_start_ticks=$demoPhp.StartTime.ToUniversalTime().Ticks;node_pid=$demoNode.Id;node_start_ticks=$demoNode.StartTime.ToUniversalTime().Ticks;started=(Get-Date).ToString('o')} | ConvertTo-Json | Set-Content -LiteralPath "$demoPrivate/processes.json"
} finally {foreach($demoName in $demoOld.Keys){[Environment]::SetEnvironmentVariable($demoName,$demoOld[$demoName],'Process')}}
Write-Output 'DEMO: http://127.0.0.1:5174 | Captured mail: http://127.0.0.1:8026'
