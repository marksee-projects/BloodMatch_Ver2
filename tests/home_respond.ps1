param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$script:pass = 0
$script:fail = 0
$token = [guid]::NewGuid().ToString('N')
$password = 'HomeRespond123!'
$hash = & $PhpPath -r "echo password_hash('$password', PASSWORD_BCRYPT);"
$jobs = @()

function Ok($name) { $script:pass++; Write-Host "PASS  $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL  $name -> $why" }
function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3306' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_test' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
}
function Invoke-Json($session, $method, $uri, $body, $csrf) {
    try {
        $args = @{ Uri = "$BaseUrl$uri"; Method = $method; WebSession = $session; TimeoutSec = 30; UseBasicParsing = $true }
        if ($null -ne $body) { $args['Body'] = ($body | ConvertTo-Json -Depth 6); $args['ContentType'] = 'application/json' }
        if ($csrf) { $args['Headers'] = @{ 'X-CSRF-Token' = $csrf } }
        $res = Invoke-WebRequest @args
        return @{ status = [int]$res.StatusCode; body = ($res.Content | ConvertFrom-Json); raw = $res.Content }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }
        try {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $raw = $reader.ReadToEnd()
        } catch { $raw = '' }
        try { $parsed = $raw | ConvertFrom-Json } catch { $parsed = $null }
        return @{ status = [int]$resp.StatusCode; body = $parsed; raw = $raw }
    }
}
function New-User(
    $label,
    $role = 'member',
    $verification = 'verified',
    $bloodType = 'O+',
    $enrolled = $true,
    $availability = 'available',
    $emailVerified = $true,
    $lastDonationSql = 'NULL'
) {
    $email = "hr-$token-$label@example.test"
    if ($email -notmatch '@[A-Za-z0-9.-]+\.(test|invalid|local)$') { throw "Non-reserved fixture email: $email" }
    $bloodSql = if ($null -eq $bloodType) { 'NULL' } else { "'$bloodType'" }
    $sourceSql = if ($null -eq $bloodType) { 'NULL' } else { "'self_reported'" }
    $enrolledSql = if ($enrolled) { 'UTC_TIMESTAMP()' } else { 'NULL' }
    $availabilitySql = if ($null -eq $availability) { 'NULL' } else { "'$availability'" }
    $emailVerifiedSql = if ($emailVerified) { 'UTC_TIMESTAMP()' } else { 'NULL' }
    DbQuery "INSERT INTO users
        (email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,
         date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,
         donor_availability,last_verified_donation_at,email_verified_at)
        VALUES ('$email','$hash','HR$label','$token','$role',1,'$verification','active','1990-01-02',
                $bloodSql,$sourceSql,0,$enrolledSql,$availabilitySql,$lastDonationSql,$emailVerifiedSql);" | Out-Null
    return @{ id = [int](DbQuery "SELECT id FROM users WHERE email='$email' LIMIT 1;"); email = $email }
}
function New-Request($requesterId, $label, $blood = 'O+', $urgency = 'routine', $status = 'OPEN', $neededAt = $script:future) {
    $facility = "HR-$token-$label"
    DbQuery "INSERT INTO blood_requests
        (requester_id,request_chapter_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status)
        VALUES ($requesterId,1,'$blood',1,'$facility','$urgency','$neededAt','$status','not_required');" | Out-Null
    return [int](DbQuery "SELECT id FROM blood_requests WHERE facility_name='$facility' LIMIT 1;")
}
function New-Match($requestId, $donorId, $status) {
    DbQuery "INSERT INTO matches (request_id,donor_id,generation,status) VALUES ($requestId,$donorId,1,'$status');" | Out-Null
    return [int](DbQuery "SELECT id FROM matches WHERE request_id=$requestId AND donor_id=$donorId LIMIT 1;")
}
function Login($user) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing).data.csrf_token
    $r = Invoke-Json $session 'Post' '/api/login' @{ email = $user.email; password = $password } $csrf
    if ($r.status -ne 200) { throw "Login failed for $($user.email): $($r.status) $($r.raw)" }
    return @{ s = $session; csrf = $csrf }
}
function Respond($auth, $requestId) {
    return Invoke-Json $auth.s 'Post' "/api/requests/$requestId/respond" @{} $auth.csrf
}
function Match-Count($requestId, $donorId) {
    return [int](DbQuery "SELECT COUNT(*) FROM matches WHERE request_id=$requestId AND donor_id=$donorId;")
}
function Notification-Count($requestId, $requesterId) {
    return [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$requesterId AND type='match.responded' AND related_id=$requestId;")
}
function Assert-Rejected($name, $auth, $requestId, $donorId, $expectedCode) {
    $r = Respond $auth $requestId
    $count = Match-Count $requestId $donorId
    if ($r.status -eq 403 -and $r.body.error.code -eq $expectedCode -and $count -eq 0) {
        Ok $name
    } else {
        Bad $name "status=$($r.status) code=$($r.body.error.code) matches=$count"
    }
}
function Cookie-Header($auth) {
    $cookies = $auth.s.Cookies.GetCookies([uri]$BaseUrl)
    return (($cookies | ForEach-Object { "$($_.Name)=$($_.Value)" }) -join '; ')
}

Write-Host '== Home response suite =='
$connectedDb = "$(DbQuery 'SELECT DATABASE();')".Trim()
if ($connectedDb -ne 'bloodmatch_test') { Bad 'DB safety check' "connected to $connectedDb"; exit 1 }
Ok 'DB safety check: bloodmatch_test only'

$script:future = (Get-Date).ToUniversalTime().AddDays(3).ToString('yyyy-MM-dd HH:mm:ss')
$past = (Get-Date).ToUniversalTime().AddMinutes(-5).ToString('yyyy-MM-dd HH:mm:ss')

try {
    $requester = New-User 'requester' 'member' 'verified' 'A+' $false $null
    $requesterAuth = Login $requester

    # Canonical no-match response, notification, audit, and idempotency.
    $eligible = New-User 'eligible'
    $eligibleAuth = Login $eligible
    $routineRequest = New-Request $requester.id 'routine'
    $r = Respond $eligibleAuth $routineRequest
    $row = "$(DbQuery "SELECT CONCAT(id,'|',status) FROM matches WHERE request_id=$routineRequest AND donor_id=$($eligible.id);")".Trim()
    if ($r.status -eq 200 -and $r.body.data.created -eq $true -and $row -match '^\d+\|RESPONDED$') { Ok 'R01 eligible Home donor creates a RESPONDED match' } else { Bad 'R01 canonical response' "status=$($r.status) row=$row" }
    if ((Notification-Count $routineRequest $requester.id) -eq 1) { Ok 'R02 requester receives one response notification' } else { Bad 'R02 response notification' "count=$(Notification-Count $routineRequest $requester.id)" }
    $dedup = "$(DbQuery "SELECT dedup_key FROM notifications WHERE user_id=$($requester.id) AND type='match.responded' AND related_id=$routineRequest LIMIT 1;")".Trim()
    if ($dedup -eq "match-response:${routineRequest}:$($eligible.id)") { Ok 'R03 response notification uses donor/request dedup key' } else { Bad 'R03 response dedup' $dedup }
    $r = Respond $eligibleAuth $routineRequest
    if ($r.status -eq 200 -and $r.body.data.already_responded -eq $true -and (Match-Count $routineRequest $eligible.id) -eq 1 -and (Notification-Count $routineRequest $requester.id) -eq 1) { Ok 'R04 repeated response is idempotent' } else { Bad 'R04 idempotency' "status=$($r.status) rows=$(Match-Count $routineRequest $eligible.id) notifications=$(Notification-Count $routineRequest $requester.id)" }
    $emailMarkers = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$($requester.id) AND type='match.responded' AND related_id=$routineRequest AND emailed_at IS NOT NULL;")
    if ($emailMarkers -eq 0) { Ok 'R05 non-emergency response does not send email' } else { Bad 'R05 non-emergency email policy' "emailed=$emailMarkers" }
    $auditContext = "$(DbQuery "SELECT context FROM audit_log WHERE actor_id=$($eligible.id) AND action='match.responded' AND target_type='blood_request' AND target_id='$routineRequest' ORDER BY id DESC LIMIT 1;")"
    if ($auditContext -match 'match_id' -and $auditContext -notmatch '@|email|phone|full_name') { Ok 'R06 response audit contains no personal data' } else { Bad 'R06 audit privacy' $auditContext }

    # Every live evaluator denial is returned unchanged and inserts no row.
    $notEnrolled = New-User 'not-enrolled' 'member' 'verified' 'O+' $false $null
    Assert-Rejected 'R07 not enrolled rejected' (Login $notEnrolled) $routineRequest $notEnrolled.id 'not_enrolled'
    $unavailable = New-User 'unavailable' 'member' 'verified' 'O+' $true 'unavailable'
    Assert-Rejected 'R08 unavailable rejected' (Login $unavailable) $routineRequest $unavailable.id 'unavailable'
    $standby = New-User 'standby' 'member' 'verified' 'O+' $true 'standby' $true 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)'
    $standbyAuth = Login $standby
    $r = Respond $standbyAuth $routineRequest
    if ($r.status -eq 403 -and $r.body.error.code -eq 'standby' -and -not [string]::IsNullOrWhiteSpace([string]$r.body.error.eligible_again_at) -and (Match-Count $routineRequest $standby.id) -eq 0) { Ok 'R09 standby rejected with eligible-again time' } else { Bad 'R09 standby rejection' "status=$($r.status) code=$($r.body.error.code)" }
    $cooldown = New-User 'cooldown' 'member' 'verified' 'O+' $true 'available' $true 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)'
    $cooldownAuth = Login $cooldown
    $r = Respond $cooldownAuth $routineRequest
    if ($r.status -eq 403 -and $r.body.error.code -eq 'cooldown' -and -not [string]::IsNullOrWhiteSpace([string]$r.body.error.eligible_again_at) -and (Match-Count $routineRequest $cooldown.id) -eq 0) { Ok 'R10 cooldown rejected with eligible-again time' } else { Bad 'R10 cooldown rejection' "status=$($r.status) code=$($r.body.error.code)" }
    $emailPending = New-User 'email-pending' 'member' 'verified' 'O+' $true 'available' $false
    Assert-Rejected 'R11 unverified email rejected' (Login $emailPending) $routineRequest $emailPending.id 'email_unverified'
    $staff = New-User 'staff' 'officer' 'verified' 'O+' $true 'available'
    Assert-Rejected 'R12 staff account rejected' (Login $staff) $routineRequest $staff.id 'staff_account'
    $missingBlood = New-User 'missing-blood' 'member' 'verified' $null $true 'available'
    Assert-Rejected 'R13 missing blood type rejected' (Login $missingBlood) $routineRequest $missingBlood.id 'blood_type_missing'

    $pending = New-User 'pending' 'member' 'verified' 'O+' $true 'available'
    $pendingAuth = Login $pending
    DbQuery "UPDATE users SET verification_status='pending' WHERE id=$($pending.id);" | Out-Null
    Assert-Rejected 'R14 unverified membership rejected' $pendingAuth $routineRequest $pending.id 'membership_unverified'
    $inactive = New-User 'inactive'
    $inactiveAuth = Login $inactive
    DbQuery "UPDATE users SET account_status='deactivated', deactivated_at=UTC_TIMESTAMP() WHERE id=$($inactive.id);" | Out-Null
    Assert-Rejected 'R15 inactive account rejected' $inactiveAuth $routineRequest $inactive.id 'inactive_account'

    $ownRequest = New-Request $eligible.id 'own'
    Assert-Rejected 'R16 requester cannot respond to own request' $eligibleAuth $ownRequest $eligible.id 'own_request'
    $closedRequest = New-Request $requester.id 'closed-request' 'O+' 'routine' 'CANCELLED'
    Assert-Rejected 'R17 non-OPEN request rejected' $eligibleAuth $closedRequest $eligible.id 'request_not_open'
    $expiredRequest = New-Request $requester.id 'expired' 'O+' 'routine' 'OPEN' $past
    Assert-Rejected 'R18 past needed-by request rejected' $eligibleAuth $expiredRequest $eligible.id 'request_expired'
    $incompatibleRequest = New-Request $requester.id 'incompatible' 'A-'
    Assert-Rejected 'R19 incompatible request rejected' $eligibleAuth $incompatibleRequest $eligible.id 'blood_incompatible'

    # Existing row state rules.
    $closedDonor = New-User 'closed-eligible'
    $closedAuth = Login $closedDonor
    $closedMatchRequest = New-Request $requester.id 'closed-match'
    $closedMatchId = New-Match $closedMatchRequest $closedDonor.id 'CLOSED'
    $r = Respond $closedAuth $closedMatchRequest
    $closedStatus = "$(DbQuery "SELECT status FROM matches WHERE id=$closedMatchId;")".Trim()
    if ($r.status -eq 200 -and $closedStatus -eq 'RESPONDED') { Ok 'R20 eligible CLOSED match reopens as RESPONDED' } else { Bad 'R20 CLOSED reopen' "status=$($r.status) db=$closedStatus" }

    $closedBlocked = New-User 'closed-blocked' 'member' 'verified' 'O+' $true 'unavailable'
    $closedBlockedAuth = Login $closedBlocked
    $closedBlockedRequest = New-Request $requester.id 'closed-blocked'
    $closedBlockedId = New-Match $closedBlockedRequest $closedBlocked.id 'CLOSED'
    $r = Respond $closedBlockedAuth $closedBlockedRequest
    $closedBlockedStatus = "$(DbQuery "SELECT status FROM matches WHERE id=$closedBlockedId;")".Trim()
    if ($r.status -eq 403 -and $r.body.error.code -eq 'unavailable' -and $closedBlockedStatus -eq 'CLOSED') { Ok 'R21 ineligible CLOSED match remains CLOSED' } else { Bad 'R21 CLOSED eligibility guard' "status=$($r.status) db=$closedBlockedStatus" }

    $completedDonor = New-User 'completed'
    $completedAuth = Login $completedDonor
    $completedRequest = New-Request $requester.id 'completed'
    $completedMatchId = New-Match $completedRequest $completedDonor.id 'COMPLETED'
    $r = Respond $completedAuth $completedRequest
    $completedStatus = "$(DbQuery "SELECT status FROM matches WHERE id=$completedMatchId;")".Trim()
    if ($r.status -eq 409 -and $completedStatus -eq 'COMPLETED' -and (Notification-Count $completedRequest $requester.id) -eq 0) { Ok 'R22 COMPLETED match is untouched' } else { Bad 'R22 COMPLETED preservation' "status=$($r.status) db=$completedStatus" }

    # Two simultaneous callers serialize on the request lock and deduplicate.
    $parallelDonor = New-User 'parallel'
    $parallelA = Login $parallelDonor
    $parallelB = Login $parallelDonor
    $parallelRequest = New-Request $requester.id 'parallel'
    $parallelScript = {
        param($base, $requestId, $csrf, $cookieHeader)
        try {
            $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
            $session.Cookies.SetCookies([uri]$base, $cookieHeader)
            $res = Invoke-WebRequest -Uri "$base/api/requests/$requestId/respond" -Method Post -Body '{}' `
                -ContentType 'application/json' -WebSession $session -Headers @{ 'X-CSRF-Token' = $csrf } `
                -TimeoutSec 30 -UseBasicParsing
            return [int]$res.StatusCode
        } catch {
            if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
            return 0
        }
    }
    $jobs = @(
        Start-Job -ScriptBlock $parallelScript -ArgumentList $BaseUrl,$parallelRequest,$parallelA.csrf,(Cookie-Header $parallelA)
        Start-Job -ScriptBlock $parallelScript -ArgumentList $BaseUrl,$parallelRequest,$parallelB.csrf,(Cookie-Header $parallelB)
    )
    $jobs | Wait-Job | Out-Null
    $parallelStatuses = @($jobs | Receive-Job)
    $jobs | Remove-Job -Force
    $jobs = @()
    if (@($parallelStatuses | Where-Object { $_ -eq 200 }).Count -eq 2 -and (Match-Count $parallelRequest $parallelDonor.id) -eq 1 -and (Notification-Count $parallelRequest $requester.id) -eq 1) { Ok 'R23 parallel responses create one row and one notification' } else { Bad 'R23 parallel idempotency' "statuses=$($parallelStatuses -join ',') rows=$(Match-Count $parallelRequest $parallelDonor.id) notifications=$(Notification-Count $parallelRequest $requester.id)" }

    # Emergency mail is attempted after commit; reserved address/unreachable SMTP cannot fail the response.
    $emergencyDonor = New-User 'emergency'
    $emergencyAuth = Login $emergencyDonor
    $emergencyRequest = New-Request $requester.id 'emergency' 'O+' 'emergency'
    $r = Respond $emergencyAuth $emergencyRequest
    $emergencyEmailMarkers = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$($requester.id) AND type='match.responded' AND related_id=$emergencyRequest AND emailed_at IS NOT NULL;")
    if ($r.status -eq 200 -and (Match-Count $emergencyRequest $emergencyDonor.id) -eq 1 -and (Notification-Count $emergencyRequest $requester.id) -eq 1 -and $emergencyEmailMarkers -eq 0) { Ok 'R24 emergency response succeeds when email cannot be delivered' } else { Bad 'R24 emergency best-effort email' "status=$($r.status) emailed=$emergencyEmailMarkers" }

    # Legacy route delegates to the same eligibility-checked service.
    $legacyDonor = New-User 'legacy'
    $legacyAuth = Login $legacyDonor
    $legacyRequest = New-Request $requester.id 'legacy'
    $legacyMatchId = New-Match $legacyRequest $legacyDonor.id 'POTENTIAL'
    $r = Invoke-Json $legacyAuth.s 'Post' "/api/matches/$legacyMatchId/respond" @{} $legacyAuth.csrf
    $legacyStatus = "$(DbQuery "SELECT status FROM matches WHERE id=$legacyMatchId;")".Trim()
    if ($r.status -eq 200 -and $legacyStatus -eq 'RESPONDED') { Ok 'R25 legacy match-ID route still responds' } else { Bad 'R25 legacy route' "status=$($r.status) db=$legacyStatus" }

    $legacyBlocked = New-User 'legacy-blocked' 'member' 'verified' 'O+' $true 'unavailable'
    $legacyBlockedAuth = Login $legacyBlocked
    $legacyBlockedRequest = New-Request $requester.id 'legacy-blocked'
    $legacyBlockedMatchId = New-Match $legacyBlockedRequest $legacyBlocked.id 'POTENTIAL'
    $r = Invoke-Json $legacyBlockedAuth.s 'Post' "/api/matches/$legacyBlockedMatchId/respond" @{} $legacyBlockedAuth.csrf
    $legacyBlockedStatus = "$(DbQuery "SELECT status FROM matches WHERE id=$legacyBlockedMatchId;")".Trim()
    if ($r.status -eq 403 -and $r.body.error.code -eq 'unavailable' -and $legacyBlockedStatus -eq 'POTENTIAL') { Ok 'R26 legacy route re-checks eligibility' } else { Bad 'R26 legacy eligibility' "status=$($r.status) code=$($r.body.error.code) db=$legacyBlockedStatus" }
}
finally {
    if ($jobs.Count -gt 0) {
        $jobs | Stop-Job -ErrorAction SilentlyContinue
        $jobs | Remove-Job -Force -ErrorAction SilentlyContinue
    }
    # Requests cascade matches; user deletion cascades notifications. Audit rows
    # remain append-only by design and lose their actor FK through ON DELETE SET NULL.
    DbQuery "DELETE FROM blood_requests WHERE facility_name LIKE '%$token%';" | Out-Null
    DbQuery "DELETE FROM users WHERE email LIKE 'hr-$token-%@example.test';" | Out-Null
    $remainingRequests = [int](DbQuery "SELECT COUNT(*) FROM blood_requests WHERE facility_name LIKE '%$token%';")
    $remainingUsers = [int](DbQuery "SELECT COUNT(*) FROM users WHERE email LIKE 'hr-$token-%@example.test';")
    if ($remainingRequests -eq 0 -and $remainingUsers -eq 0) { Ok 'R27 mutable fixtures cleaned up' } else { Bad 'R27 fixture cleanup' "requests=$remainingRequests users=$remainingUsers" }
}

Write-Host ''
Write-Host "== Home response: $($script:pass) passed, $($script:fail) failed =="
if ($script:fail -gt 0) { exit 1 }
