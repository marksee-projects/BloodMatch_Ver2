param(
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$script:pass = 0
$script:fail = 0
$suffix = Get-Random
$password = 'ProfileView123!'
$hash = & $PhpPath -r "echo password_hash('$password', PASSWORD_BCRYPT);"

function Ok($name) { $script:pass++; Write-Host "PASS  $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL  $name -> $why" }
function Assert-Status($name, $actual, $expected) {
    if ($actual -eq $expected) { Ok $name } else { Bad $name "expected $expected, got $actual" }
}
function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3306' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_test' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
}
function New-User($label, $role = 'member', $chapter = 1) {
    $email = "profile_${label}_${suffix}@example.test"
    $chapterSql = if ($null -eq $chapter) { 'NULL' } else { [string]$chapter }
    DbQuery "INSERT INTO users (email,password_hash,first_name,last_name,phone,role,chapter_id,verification_status,account_status,date_of_birth,blood_type,blood_type_source,blood_type_verified,latitude,longitude,email_verified_at) VALUES ('$email','$hash','Profile','$label','09170000000','$role',$chapterSql,'verified','active','1990-01-02','O+','self_reported',0,14.600000,120.500000,UTC_TIMESTAMP());"
    return @{ id = [int](DbQuery "SELECT id FROM users WHERE email='$email'"); email = $email }
}
function New-Request($requesterId, $status = 'OPEN') {
    DbQuery "INSERT INTO blood_requests (requester_id,request_chapter_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status) VALUES ($requesterId,1,'O+',1,'Profile Test Clinic','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),'$status','not_required');"
    return [int](DbQuery "SELECT id FROM blood_requests WHERE requester_id=$requesterId ORDER BY id DESC LIMIT 1")
}
function New-Match($requestId, $donorId, $status) {
    DbQuery "INSERT INTO matches (request_id,donor_id,status) VALUES ($requestId,$donorId,'$status');"
}
function Login($user) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing).data.csrf_token
    $body = @{ email = $user.email; password = $password } | ConvertTo-Json
    $response = Invoke-WebRequest -Uri "$BaseUrl/api/login" -Method Post -Body $body -ContentType 'application/json' -Headers @{ 'X-CSRF-Token' = $csrf } -WebSession $session -TimeoutSec 10 -UseBasicParsing
    if ([int]$response.StatusCode -ne 200) { throw "Fixture login failed for $($user.email)" }
    return $session
}
function Get-Json($session, $uri) {
    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl$uri" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
        return @{ status = [int]$response.StatusCode; raw = $response.Content; body = ($response.Content | ConvertFrom-Json) }
    } catch {
        $response = $_.Exception.Response
        if ($null -eq $response) { throw }
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        $raw = $reader.ReadToEnd()
        try { $body = $raw | ConvertFrom-Json } catch { $body = $null }
        return @{ status = [int]$response.StatusCode; raw = $raw; body = $body }
    }
}
function Get-Profile($session, $id) { return Get-Json $session "/api/profile/$id" }

Write-Host '== Shared Profile View Test Suite =='
$connectedDb = DbQuery 'SELECT DATABASE();'
if ($connectedDb -ne 'bloodmatch_test') { Bad 'DB safety check' "connected to $connectedDb"; exit 1 }
Ok 'DB safety check: bloodmatch_test only'

$target = New-User 'target'
$selfSession = Login $target
$result = Get-Profile $selfSession $target.id
Assert-Status 'T01 owner may view own allow-listed profile' $result.status 200

$admin = New-User 'admin' 'admin' $null
$result = Get-Profile (Login $admin) $target.id
Assert-Status 'T02 admin may view any member' $result.status 200

$officerIn = New-User 'officer_in' 'officer' 1
$result = Get-Profile (Login $officerIn) $target.id
Assert-Status 'T03 in-chapter officer may view member' $result.status 200

$officerOut = New-User 'officer_out' 'officer' 2
$result = Get-Profile (Login $officerOut) $target.id
Assert-Status 'T04 out-of-chapter officer receives 404' $result.status 404

$donor = New-User 'matched_donor'
$openRequest = New-Request $target.id
New-Match $openRequest $donor.id 'POTENTIAL'
$donorSession = Login $donor
$result = Get-Profile $donorSession $target.id
Assert-Status 'T05 donor matched to target open request may view target' $result.status 200
$feedResult = Get-Json $donorSession '/api/home-feed?q=Profile%20Test%20Clinic'
$feedRequest = @($feedResult.body.data.requests | Where-Object { $_.id -eq $openRequest })
if ($feedResult.status -eq 200 -and $feedRequest.Count -eq 1 -and $feedRequest[0].requester_id -eq $target.id -and $feedRequest[0].can_view_requester_profile -eq $true `
    -and $feedRequest[0].can_respond -eq $false -and $feedRequest[0].reason_code -eq 'not_enrolled' `
    -and -not [string]::IsNullOrWhiteSpace([string]$feedRequest[0].reason_text) `
    -and $null -eq $feedRequest[0].eligible_again_at -and $feedRequest[0].responded -eq $false) {
    Ok 'T05a Home feed exposes the requester public profile'
} else {
    Bad 'T05a Home feed profile access metadata' "status=$($feedResult.status), matches=$($feedRequest.Count)"
}
if ($feedResult.raw -notmatch 'latitude|longitude|phone|date_of_birth|availability') {
    Ok 'T05b Home feed does not expose private profile or exact-location fields'
} else {
    Bad 'T05b Home feed privacy' 'private or exact-location field appeared'
}

$requester = New-User 'requester'
$respondedDonor = New-User 'responded_donor'
$respondedRequest = New-Request $requester.id
New-Match $respondedRequest $respondedDonor.id 'RESPONDED'
$result = Get-Profile (Login $requester) $respondedDonor.id
Assert-Status 'T06 requester may view donor who responded to open request' $result.status 200

$notRespondedDonor = New-User 'not_responded'
$notRespondedRequest = New-Request $requester.id
New-Match $notRespondedRequest $notRespondedDonor.id 'NOTIFIED'
$result = Get-Profile (Login $requester) $notRespondedDonor.id
Assert-Status 'T07 member may view the limited profile of a donor who has not responded' $result.status 200

$closedDonor = New-User 'closed_match'
$closedRequest = New-Request $target.id
New-Match $closedRequest $closedDonor.id 'CLOSED'
$closedDonorSession = Login $closedDonor
$result = Get-Profile $closedDonorSession $target.id
Assert-Status 'T08 active member may view another active member after a match closes' $result.status 200
$closedFeed = Get-Json $closedDonorSession '/api/home-feed?q=Profile%20Test%20Clinic'
$closedFeedEntry = @($closedFeed.body.data.requests | Where-Object { $_.id -eq $closedRequest })
if ($closedFeedEntry.Count -eq 1 -and $closedFeedEntry[0].can_view_requester_profile -eq $true `
    -and $closedFeedEntry[0].can_respond -eq $false -and $closedFeedEntry[0].reason_code -eq 'not_enrolled' `
    -and -not [string]::IsNullOrWhiteSpace([string]$closedFeedEntry[0].reason_text) `
    -and $null -eq $closedFeedEntry[0].eligible_again_at -and $closedFeedEntry[0].responded -eq $false) {
    Ok 'T08a compatible request keeps limited requester profile access'
} else {
    Bad 'T08a compatibility-only Home card' "matches=$($closedFeedEntry.Count)"
}

$cancelledRequester = New-User 'cancelled_requester'
$cancelledDonor = New-User 'cancelled_donor'
$cancelledRequest = New-Request $cancelledRequester.id 'CANCELLED'
New-Match $cancelledRequest $cancelledDonor.id 'RESPONDED'
$cancelledDonorSession = Login $cancelledDonor
$result = Get-Profile $cancelledDonorSession $cancelledRequester.id
Assert-Status 'T09 active members retain limited account-detail visibility after cancellation' $result.status 200
$cancelledFeed = Get-Json $cancelledDonorSession '/api/home-feed'
if (@($cancelledFeed.body.data.requests | Where-Object { $_.id -eq $cancelledRequest }).Count -eq 0) { Ok 'T09a cancelled request has no Home profile link' } else { Bad 'T09a cancelled Home request' 'request was returned' }

$unrelated = New-User 'unrelated'
$unrelatedSession = Login $unrelated
$result = Get-Profile $unrelatedSession $target.id
Assert-Status 'T10 unrelated active member receives the limited account view' $result.status 200
$unrelatedFeed = Get-Json $unrelatedSession '/api/home-feed?q=Profile%20Test%20Clinic'
$unrelatedEntry = @($unrelatedFeed.body.data.requests | Where-Object { $_.id -eq $openRequest })
if ($unrelatedFeed.status -eq 200 -and $unrelatedEntry.Count -eq 1 -and $unrelatedEntry[0].can_view_requester_profile -eq $true `
    -and $unrelatedEntry[0].can_respond -eq $false -and $unrelatedEntry[0].reason_code -eq 'not_enrolled' `
    -and -not [string]::IsNullOrWhiteSpace([string]$unrelatedEntry[0].reason_text) `
    -and $null -eq $unrelatedEntry[0].eligible_again_at -and $unrelatedEntry[0].responded -eq $false) {
    Ok 'T10a compatible member can browse the requester limited profile'
} else {
    Bad 'T10a compatibility-only Home feed' "status=$($unrelatedFeed.status), matches=$($unrelatedEntry.Count)"
}

$enumViewer = New-User 'enumerator'
$enumA = New-User 'enum_a'; $enumB = New-User 'enum_b'; $enumC = New-User 'enum_c'
$enumSession = Login $enumViewer
$enumStatuses = @((Get-Profile $enumSession $enumA.id).status, (Get-Profile $enumSession $enumB.id).status, (Get-Profile $enumSession $enumC.id).status)
if (@($enumStatuses | Where-Object { $_ -ne 200 }).Count -eq 0) { Ok 'T11 active members receive only allow-listed account details' } else { Bad 'T11 member profile visibility' ($enumStatuses -join ',') }

$allowResult = Get-Profile (Login (New-User 'allow_admin' 'admin' $null)) $target.id
$actualKeys = @($allowResult.body.data.profile.PSObject.Properties.Name | Sort-Object)
$expectedKeys = @('blood_type','chapter_name','email','full_name','member_since','profile_picture_url','role_label','verification_status' | Sort-Object)
if (($actualKeys -join ',') -eq ($expectedKeys -join ',')) { Ok 'T12 response contains only allow-listed profile fields' } else { Bad 'T12 allow-list keys' ($actualKeys -join ',') }
$forbidden = @('phone','date_of_birth','document','ocr','latitude','longitude','barangay','donation','availability','password','token')
$leaks = @($forbidden | Where-Object { $allowResult.raw -match $_ })
if ($leaks.Count -eq 0) { Ok 'T13 sensitive profile fields and values never appear' } else { Bad 'T13 sensitive data leak' ($leaks -join ',') }

$auditCount = [int](DbQuery "SELECT COUNT(*) FROM audit_log WHERE actor_id=$($donor.id) AND action='profile.viewed' AND target_type='user' AND target_id='$($target.id)' AND context IS NULL;")
if ($auditCount -ge 1) { Ok 'T14 another-member view writes field-free audit event' } else { Bad 'T14 audit event' "count=$auditCount" }

$rateAdmin = New-User 'rate_admin' 'admin' $null
$rateTarget = New-User 'rate_target'
$rateSession = Login $rateAdmin
$firstSixtyOk = $true
for ($i = 1; $i -le 60; $i++) {
    if ((Get-Profile $rateSession $rateTarget.id).status -ne 200) { $firstSixtyOk = $false; break }
}
if ($firstSixtyOk) { Ok 'T15 first 60 authorized profile views are allowed' } else { Bad 'T15 rate-limit allowance' "failed at view $i" }
$result = Get-Profile $rateSession $rateTarget.id
Assert-Status 'T16 61st profile view in one hour is rate-limited' $result.status 429

Write-Host "== Profile view: $script:pass passed, $script:fail failed =="
if ($script:fail -gt 0) { exit 1 } else { exit 0 }
