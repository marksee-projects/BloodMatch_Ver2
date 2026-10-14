param([string]$BaseUrl='http://127.0.0.1:8001',[string]$MysqlPath='C:\xampp\mysql\bin\mysql.exe',[string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
if ($BaseUrl.TrimEnd('/') -ne 'http://127.0.0.1:8001' -or $env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:TEST_DB_PORT -ne '3306') { throw 'Run through tests/run_isolated.ps1 against bloodmatch_test:3306 only.' }
$fixturePath=$null; $passed=0
function Check($condition,$label) { if (-not $condition) { throw "FAIL $label" }; $script:passed++; Write-Host "PASS $label" }
function Call($client,$method,$path,$body=$null,$csrf=$true) {
    $options=@{Uri="$BaseUrl$path";Method=$method;WebSession=$client.session;UseBasicParsing=$true;TimeoutSec=20}
    if ($csrf -and $method -ne 'GET') { $options.Headers=@{'X-CSRF-Token'=$client.csrf} }
    if ($null -ne $body) { $options.ContentType='application/json';$options.Body=$body | ConvertTo-Json -Depth 8 }
    try { $r=Invoke-WebRequest @options; return @{status=[int]$r.StatusCode;data=($r.Content | ConvertFrom-Json).data} }
    catch { if ($null -eq $_.Exception.Response) { throw }; return @{status=[int]$_.Exception.Response.StatusCode;data=$null} }
}
function Login($account,$expected=200) {
    $session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $token=(Invoke-RestMethod "$BaseUrl/api/csrf" -WebSession $session).data.csrf_token
    $client=@{session=$session;csrf=$token}
    $r=Call $client 'POST' '/api/login' @{email=$account.email;password=$account.password}
    Check ($r.status -eq $expected) "fixture login returns $expected"
    return $client
}
try {
    $fixturePath=(& $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') --create | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Fixture preparation failed.' }
    $fixture=Get-Content -LiteralPath $fixturePath -Raw | ConvertFrom-Json
    $a=Login $fixture.accounts.a; $b=Login $fixture.accounts.b
    $pending=Login $fixture.accounts.pending; $rejected=Login $fixture.accounts.rejected; $outsider=Login $fixture.accounts.outsider
    $null=Login $fixture.accounts.inactive 403
    $started=Call $a 'POST' '/api/chat/conversations' @{recipient_id=$fixture.accounts.b.id}
    Check ($started.status -eq 200 -and $started.data.conversation.id -gt 0) 'verified users start a direct conversation'
    $id=[int]$started.data.conversation.id
    $again=Call $b 'POST' '/api/chat/conversations' @{recipient_id=$fixture.accounts.a.id}
    Check ($again.status -eq 200 -and $again.data.conversation.id -eq $id) 'reverse start reuses the conversation'
    foreach ($client in @($pending,$rejected)) {
        Check ((Call $client 'GET' '/api/chat/conversations').status -eq 403) 'unverified user cannot list chats'
        Check ((Call $client 'GET' '/api/chat/unread-count').status -eq 403) 'unverified user cannot fetch unread count'
        Check ((Call $client 'POST' '/api/chat/conversations' @{recipient_id=$fixture.accounts.a.id}).status -eq 403) 'unverified user cannot start chat'
        Check ((Call $client 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'unverified user cannot read existing chat'
        Check ((Call $client 'POST' "/api/chat/conversations/$id/messages" @{body='Blocked'}).status -eq 403) 'unverified user cannot send'
        Check ((Call $client 'POST' "/api/chat/conversations/$id/read" @{last_message_id=0}).status -eq 403) 'unverified user cannot mark chat read'
    }
    Check ((Call $outsider 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'nonparticipant admin cannot read private chat'
    Check ((Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='No CSRF'} $false).status -eq 403) 'sending requires CSRF'
    $sent=Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='Hello from A';sender_id=$fixture.accounts.outsider.id}
    Check ($sent.status -eq 200 -and $sent.data.message.sender_id -eq $fixture.accounts.a.id) 'sender identity comes from session'
    $first=[int]$sent.data.message.id
    $incoming=Call $b 'GET' "/api/chat/conversations/$id/messages?limit=50"
    Check ($incoming.status -eq 200 -and @($incoming.data.messages).Count -eq 1 -and $incoming.data.messages[0].body -eq 'Hello from A') 'recipient receives the persisted message'
    Check ((Call $b 'GET' '/api/chat/unread-count').data.unread_count -eq 1) 'recipient sees one unread message'
    $reply=Call $b 'POST' "/api/chat/conversations/$id/messages" @{body='Reply from B'}
    Check ($reply.status -eq 200) 'recipient can reply'
    $poll=Call $a 'GET' "/api/chat/conversations/$id/messages?after_id=$first"
    Check ($poll.status -eq 200 -and @($poll.data.messages).Count -eq 1 -and $poll.data.messages[0].body -eq 'Reply from B') 'cursor polling retrieves only the newer reply'
    Check ((Call $b 'POST' "/api/chat/conversations/$id/read" @{last_message_id=$reply.data.message.id}).status -eq 200) 'read marker is updated'
    Check ((Call $b 'GET' '/api/chat/unread-count').data.unread_count -eq 0) 'read conversation clears recipient unread count'
    Check ((Call $a 'GET' "/api/chat/conversations/$id/messages").data.messages.Count -eq 2) 'reloading retains both messages'
    & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--revoke=$fixturePath" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not revoke fixture verification.' }
    Check ((Call $b 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'verification revocation blocks an existing session'
    $blocked=Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='Cannot contact pending recipient'}
    Check ($blocked.status -in @(400,403)) 'existing conversation cannot bypass recipient verification'
    Write-Host "PASS $passed functional chat API checks. No load test was run."
} finally {
    if ($fixturePath -and (Test-Path -LiteralPath $fixturePath)) {
        & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--cleanup=$fixturePath"
        if ($LASTEXITCODE -ne 0) { throw "Fixture cleanup failed. Keep the private manifest at $fixturePath for recovery." }
    }
}
