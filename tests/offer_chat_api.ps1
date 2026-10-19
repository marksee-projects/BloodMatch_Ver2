# USER-RUN ONLY via run_isolated.ps1. Anonymous authorization guards; no SMTP.
param([string]$BaseUrl='http://127.0.0.1:8001', [string]$MysqlPath='', [string]$PhpPath='')
$ErrorActionPreference='Stop'
$session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf=(Invoke-RestMethod "$BaseUrl/api/csrf" -WebSession $session).data.csrf_token
$checks=0
foreach($path in @('/api/requests/1/offers','/api/donation-offers/1/context','/api/chat/conversations/1/donation-contexts','/api/donation-completion/1/proof','/api/donation-proof/1/file','/api/donation-completion/1/correction-requests')) {
    try { Invoke-RestMethod "$BaseUrl$path" -WebSession $session | Out-Null; throw "Unexpected anonymous access: $path" }
    catch { if ([int]$_.Exception.Response.StatusCode -ne 401) { throw }; $checks++ }
}
foreach($path in @('/api/donation-offers/1/accept','/api/donation-contexts/1/signal','/api/donation-completion/1/proof','/api/donation-completion/1/correction-request','/api/donation-completion/1/correction-response','/api/donation-completion','/api/donation-reports')) {
    try { Invoke-RestMethod "$BaseUrl$path" -WebSession $session -Method Post -ContentType 'application/json' -Headers @{'X-CSRF-Token'=$csrf} -Body '{}' | Out-Null; throw "Unexpected anonymous mutation: $path" }
    catch { if ([int]$_.Exception.Response.StatusCode -ne 401) { throw }; $checks++ }
}
Write-Output "== offer_chat_api: $checks passed, 0 failed =="
