# USER-RUN ONLY via tests/run_isolated.ps1. No real SMTP.
param([string]$BaseUrl='http://127.0.0.1:8001', [string]$MysqlPath='', [string]$PhpPath='')
$ErrorActionPreference='Stop'
$session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf=(Invoke-RestMethod "$BaseUrl/api/csrf" -WebSession $session).data.csrf_token
$checks=0
foreach($path in @('/api/donation-completion','/api/donation-completion/1/events')) {
    try { Invoke-RestMethod "$BaseUrl$path" -WebSession $session | Out-Null; throw "Unexpected anonymous access: $path" }
    catch { if ([int]$_.Exception.Response.StatusCode -ne 401) { throw }; $checks++ }
}
foreach($action in @('acknowledge','review','clarification','revise','release-hold')) {
    try { Invoke-RestMethod "$BaseUrl/api/donation-completion/1/$action" -WebSession $session -Method Post -ContentType 'application/json' -Headers @{'X-CSRF-Token'=$csrf} -Body '{}' | Out-Null; throw "Unexpected anonymous mutation: $action" }
    catch { if ([int]$_.Exception.Response.StatusCode -ne 401) { throw }; $checks++ }
}
Write-Output "== donation_completion_api: $checks passed, 0 failed =="
