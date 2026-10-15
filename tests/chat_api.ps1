param([string]$BaseUrl='http://127.0.0.1:8001',[string]$MysqlPath='C:\xampp\mysql\bin\mysql.exe',[string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
if ($BaseUrl.TrimEnd('/') -ne 'http://127.0.0.1:8001' -or $env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:TEST_DB_PORT -ne '3306') { throw 'Run through tests/run_isolated.ps1 against bloodmatch_test:3306 only.' }
$fixturePath=$null; $passed=0
function Check($condition,$label) { if (-not $condition) { throw "FAIL $label" }; $script:passed++; Write-Host "PASS $label" }
function Call($client,$method,$path,$body=$null,$csrf=$true) {
    # Windows PowerShell retains request headers on WebRequestSession. Remove
    # the previous token so the missing-token case actually omits the header.
    [void]$client.session.Headers.Remove('X-CSRF-Token')
    $options=@{Uri="$BaseUrl$path";Method=$method;WebSession=$client.session;UseBasicParsing=$true;TimeoutSec=20}
    if ($csrf -and $method -ne 'GET') { $options.Headers=@{'X-CSRF-Token'=$client.csrf} }
    if ($null -ne $body) { $options.ContentType='application/json';$options.Body=$body | ConvertTo-Json -Depth 8 }
    try { $r=Invoke-WebRequest @options; return @{status=[int]$r.StatusCode;data=($r.Content | ConvertFrom-Json).data} }
    catch {
        if ($null -eq $_.Exception.Response) { throw }
        $apiError=$null
        if ($_.ErrorDetails.Message) { try { $apiError=($_.ErrorDetails.Message | ConvertFrom-Json).error } catch {} }
        return @{status=[int]$_.Exception.Response.StatusCode;data=$null;error=$apiError}
    }
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
    $anonSession=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $anonToken=(Invoke-RestMethod "$BaseUrl/api/csrf" -WebSession $anonSession).data.csrf_token
    $anon=@{session=$anonSession;csrf=$anonToken}
    $chapter=(Call $anon 'GET' '/api/chapters').data.chapters[0].id
    $municipality=(Call $anon 'GET' '/api/locations/municipalities').data.municipalities[0]
    $registered=Call $anon 'POST' '/api/register' @{first_name='Signup';last_name='Fixture';email=$fixture.registration.email;password=$fixture.registration.password;chapter_id=$chapter;date_of_birth='1990-01-01';blood_type='O+';location_id=$municipality.location_id;municipality_code=$municipality.psgc_code;privacy_acknowledged=$true}
    Check ($registered.status -eq 201 -and $registered.data.user.pending_registration) "registration stages data before OTP (HTTP $($registered.status); $($registered.error.message); $($registered.error.details | ConvertTo-Json -Compress))"
    $state=(& $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--signup-status=$fixturePath" | Out-String | ConvertFrom-Json)
    Check ($state.user_count -eq 0 -and $state.pending_count -eq 1) 'no account exists before OTP'
    & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--otp=$fixturePath" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not prepare owned test OTP.' }
    Check ((Call $anon 'POST' '/api/auth/verify' @{email=$fixture.registration.email;code='654321'}).status -eq 400) 'incorrect OTP denied'
    Check ((Call $anon 'POST' '/api/auth/verify' @{email=$fixture.registration.email;code='123456'}).status -eq 200) 'correct OTP creates account'
    $state=(& $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--signup-status=$fixturePath" | Out-String | ConvertFrom-Json)
    Check ($state.user_count -eq 1 -and $state.pending_count -eq 0 -and $state.verification_status -eq 'pending' -and $state.location_id -eq $municipality.location_id) 'OTP preserves location and requires membership review'
    $a=Login $fixture.accounts.a; $b=Login $fixture.accounts.b
    $pending=Login $fixture.accounts.pending; $rejected=Login $fixture.accounts.rejected; $outsider=Login $fixture.accounts.outsider
    $null=Login $fixture.accounts.inactive 403
    Check ((Call $a 'GET' '/api/chat/unread-count').data.unread_count -eq 0) 'unaccepted imported messages do not inflate badge'
    Check ((Call $a 'GET' "/api/chat/conversations/$($fixture.legacy_conversation_id)/messages").status -eq 403) 'existing participant cannot bypass acceptance'
    Check ((Call $a 'POST' '/api/chat/conversations' @{recipient_id=$fixture.accounts.b.id}).status -eq 403) 'direct start cannot bypass recipient consent'
    $requested=Call $a 'POST' '/api/chat/requests' @{recipient_id=$fixture.accounts.b.id}
    Check ($requested.status -eq 200 -and $requested.data.request.status -eq 'pending') 'sender creates pending request'
    $requestId=[int]$requested.data.request.id
    Check ((Call $outsider 'POST' "/api/chat/requests/$requestId/decision" @{action='accept'}).status -eq 403) 'outsider cannot accept request'
    Check ((Call $a 'GET' '/api/chat/conversations').data.conversations.Count -eq 0) 'pending contacts do not appear in normal chat'
    & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--expire-request=$fixturePath" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not expire owned pending chat request.' }
    Check ((Call $b 'POST' "/api/chat/requests/$requestId/decision" @{action='accept'}).status -eq 410) 'seven-day request cannot be accepted without cleanup worker'
    $incoming=Call $b 'GET' '/api/chat/requests'
    Check ($incoming.status -eq 200 -and @($incoming.data.requests | Where-Object { $_.id -eq $requestId }).Count -eq 0) 'expired request is absent from pending list'
    $renewed=Call $a 'POST' '/api/chat/requests' @{recipient_id=$fixture.accounts.b.id}
    Check ($renewed.status -eq 200 -and $renewed.data.request.status -eq 'pending' -and $renewed.data.request.id -eq $requestId) 'new request reuses expired pair without granting consent'
    Check ((Call $b 'POST' "/api/chat/requests/$requestId/decision" @{action='accept'}).status -eq 200) 'recipient accepts request'
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
    Check ((Call $outsider 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'nonparticipant member cannot read private chat'
    Check ((Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='No CSRF'} $false).status -eq 403) 'sending requires CSRF'
    Check ((Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='Stale displayed account';expected_sender_id=$fixture.accounts.outsider.id}).status -eq 409) 'stale displayed account cannot send under another session identity'
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
    $report=Call $a 'POST' "/api/chat/conversations/$id/reports" @{reason='Owned test report';message_ids=@([int]$sent.data.message.id)}
    Check ($report.status -eq 200) 'participant can report selected conversation messages'
    Check ((Call $outsider 'POST' "/api/chat/conversations/$id/reports" @{reason='Not a participant';message_ids=@([int]$sent.data.message.id)}).status -eq 403) 'outsider cannot report/read unrelated thread'
    Check ((Call $outsider 'GET' '/api/admin/chat-reports').status -eq 403) 'member cannot access administrator reports'
    if ($fixture.administrator) {
        $admin=Login $fixture.administrator
        Check ((Call $admin 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'administrator cannot browse an unrelated private conversation'
        Check ((Call $admin 'GET' '/api/admin/chat-reports').status -eq 200) 'designated administrator can list reports'
        $reportId=[int]$report.data.report_id
        $evidence=Call $admin 'GET' "/api/admin/chat-reports/$reportId"
        Check ($evidence.status -eq 200 -and @($evidence.data.evidence).Count -eq 2 -and @($evidence.data.evidence | Where-Object { $_.is_selected -eq 1 }).Count -eq 1) 'administrator sees selected evidence and bounded adjacent context'
        Check (-not [string]::IsNullOrWhiteSpace($evidence.data.report.reporter_name) -and @($evidence.data.evidence | Where-Object { [string]::IsNullOrWhiteSpace($_.sender_name) }).Count -eq 0) 'authorized evidence identifies reporter and message senders by name'
        Check ((Call $admin 'POST' "/api/admin/chat-reports/$reportId/decision" @{status='under_review';findings='Owned fixture investigation'}).status -eq 200) 'administrator can record an investigation'
        Check ((Call $admin 'POST' "/api/admin/chat-reports/$reportId/decision" @{status='resolved';findings='Owned fixture resolved'}).status -eq 200) 'administrator can resolve a report'
        Check ((Call $admin 'POST' "/api/admin/chat-reports/$reportId/decision" @{status='dismissed';findings='Duplicate fixture decision'}).status -eq 409) 'resolved reports reject duplicate decisions'
        $adminRequest=Call $a 'POST' '/api/chat/requests' @{recipient_id=$fixture.administrator.id}
        Check ($adminRequest.status -eq 200) 'admin conversation still requires a message request'
        $adminConsent=Call $admin 'POST' "/api/chat/requests/$($adminRequest.data.request.id)/decision" @{action='accept'}
        Check ($adminConsent.status -eq 200) 'administrator explicitly accepts the request'
        $adminThread=[int]$adminConsent.data.conversation_id
        $conflictMessage=Call $a 'POST' "/api/chat/conversations/$adminThread/messages" @{body='Owned conflict-review fixture'}
        Check ($conflictMessage.status -eq 200) 'accepted administrator conversation supports participant messaging'
        $conflictReport=Call $a 'POST' "/api/chat/conversations/$adminThread/reports" @{reason='Owned conflict report';message_ids=@([int]$conflictMessage.data.message.id)}
        Check ($conflictReport.status -eq 200) 'participant can report a conversation involving the administrator'
        $conflictId=[int]$conflictReport.data.report_id
        Check ((Call $admin 'GET' "/api/admin/chat-reports/$conflictId").status -eq 403) 'administrator cannot inspect own conversation evidence through moderation'
        Check ((Call $admin 'POST' "/api/admin/chat-reports/$conflictId/decision" @{status='resolved';findings='Forbidden self-review'}).status -eq 403) 'administrator cannot resolve a report involving own conversation'
    } else { Write-Host 'SKIP administrator evidence/conflict checks: no matching private test-cleanup credential available.' }
    & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--revoke=$fixturePath" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not revoke fixture verification.' }
    Check ((Call $b 'GET' "/api/chat/conversations/$id/messages").status -eq 403) 'verification revocation blocks an existing session'
    $blocked=Call $a 'POST' "/api/chat/conversations/$id/messages" @{body='Cannot contact pending recipient'}
    Check ($blocked.status -in @(400,403)) 'existing conversation cannot bypass recipient verification'
    Check ((Call $b 'POST' '/api/account/delete' @{password='incorrect';confirmation='DELETE'}).status -eq 400) 'incorrect password cannot close account'
    $closed=Call $b 'POST' '/api/account/delete' @{password=$fixture.accounts.b.password;confirmation='DELETE'}
    Check ($closed.status -eq 200 -and $closed.data.closed) 'identity-confirmed member closes account'
    Check ((Call $b 'GET' '/api/profile').status -eq 401) 'closed account session is revoked'
    Write-Host "PASS $passed functional chat API checks. No load test was run."
    Write-Host "== chat_api: $passed passed, 0 failed =="
} finally {
    if ($fixturePath -and (Test-Path -LiteralPath $fixturePath)) {
        & $PhpPath (Join-Path $PSScriptRoot 'chat_api_fixture.php') "--cleanup=$fixturePath"
        if ($LASTEXITCODE -ne 0) { throw "Fixture cleanup failed. Keep the private manifest at $fixturePath for recovery." }
    }
}
