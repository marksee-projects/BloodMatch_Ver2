param(
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$suffix = "$(Get-Random)"
$script:pass = 0
$script:fail = 0

function Ok($name) { $script:pass++; Write-Host "PASS  $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL  $name -> $why" }

function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3306' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_test' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
}

Write-Host "== Email Verification Test Suite =="

# Pre-flight check: Refuse anything but bloodmatch_test
$connectedDb = DbQuery "SELECT DATABASE();"
Write-Host "Connected DB: $connectedDb"
if ($connectedDb -ne 'bloodmatch_test') {
    Bad 'DB Safety Check' "Connected to '$connectedDb', REFUSING anything but bloodmatch_test!"
    exit 1
}
Ok 'DB Safety Check: strictly connected to bloodmatch_test'

function Get-Csrf($session) {
    $r = Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
    return $r.data.csrf_token
}

function Post($session, $uri, $body, $csrf) {
    $headers = @{}
    if ($csrf) { $headers['X-CSRF-Token'] = $csrf }
    try {
        $res = Invoke-WebRequest -Uri "$BaseUrl$uri" -Method Post -Body ($body | ConvertTo-Json) `
            -ContentType 'application/json' -Headers $headers -WebSession $session -TimeoutSec 10 -UseBasicParsing
        return @{ status = [int]$res.StatusCode; body = ($res.Content | ConvertFrom-Json) }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }
        $status = [int]$resp.StatusCode
        try {
            $stream = $resp.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            $raw = $reader.ReadToEnd()
        } catch { $raw = '' }
        try { $parsed = $raw | ConvertFrom-Json } catch { $parsed = $null }
        return @{ status = $status; body = $parsed }
    }
}

function GetReq($session, $uri) {
    try {
        $res = Invoke-WebRequest -Uri "$BaseUrl$uri" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
        return @{ status = [int]$res.StatusCode; body = ($res.Content | ConvertFrom-Json) }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }
        $status = [int]$resp.StatusCode
        try {
            $stream = $resp.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            $raw = $reader.ReadToEnd()
        } catch { $raw = '' }
        try { $parsed = $raw | ConvertFrom-Json } catch { $parsed = $null }
        return @{ status = $status; body = $parsed }
    }
}

$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = Get-Csrf $s

# --- T01: Fresh registration starts unverified ---
$email1 = "unverified_$suffix@example.test"
$r = Post $s '/api/register' @{
    first_name = 'Unverified'
    last_name = 'User'
    email = $email1
    password = 'Password123'
    chapter_id = 1
    date_of_birth = '1995-01-01'
    privacy_acknowledged = $true
} $csrf

if ($r.status -eq 201 -and $r.body.data.user.email_verified -eq $false) {
    Ok 'T01 fresh registration returns email_verified=false'
} else {
    Bad 'T01 fresh registration' "status=$($r.status), verified=$($r.body.data.user.email_verified)"
}

$userRow = DbQuery "SELECT CONCAT(IF(email_verified_at IS NULL,'NULL','SET'),'|',IF(email_code_hash IS NOT NULL,'HASH_SET','NO_HASH'),'|',IF(email_code_expires_at > UTC_TIMESTAMP(),'VALID_EXPIRY','BAD_EXPIRY')) FROM users WHERE email='$email1';"
if ($userRow -eq 'NULL|HASH_SET|VALID_EXPIRY') {
    Ok 'T01 DB confirms email_verified_at is NULL, hash set, expiry > UTC_TIMESTAMP()'
} else {
    Bad 'T01 DB check' "got $userRow"
}

# --- T02: Login and /api/auth/me return email_verified=false ---
$rLogin = Post $s '/api/login' @{ email = $email1; password = 'Password123' } $csrf
if ($rLogin.status -eq 200 -and $rLogin.body.data.user.email_verified -eq $false) {
    Ok 'T02 login returns email_verified=false for unverified user'
} else {
    Bad 'T02 login' "status=$($rLogin.status)"
}

$rMe = GetReq $s '/api/auth/me'
if ($rMe.status -eq 200 -and $rMe.body.data.user.email_verified -eq $false) {
    Ok 'T02 /api/auth/me returns email_verified=false'
} else {
    Bad 'T02 /me' "status=$($rMe.status)"
}

# --- T03: Unverified user blocked from creating blood request ---
$locId = [int](DbQuery "SELECT id FROM bataan_locations LIMIT 1;")
$rCreate = Post $s '/api/requests' @{
    required_blood_type = 'O+'
    quantity_units = 2
    facility_name = 'Bataan General Hospital'
    location_id = $locId
    urgency = 'urgent'
    needed_datetime = (Get-Date).AddDays(2).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss')
} $csrf

if ($rCreate.status -eq 403 -and $rCreate.body.error.details.code -eq 'EMAIL_UNVERIFIED') {
    Ok 'T03 unverified user blocked from creating request with code EMAIL_UNVERIFIED'
} else {
    Bad 'T03 create request blocked' "status=$($rCreate.status), code=$($rCreate.body.error.details.code)"
}

# --- T04: Unverified donor blocked from responding to match ---
# Create an open request and a match for this user directly in DB
$uid1 = [int](DbQuery "SELECT id FROM users WHERE email='$email1';")
DbQuery "UPDATE users SET blood_type='O+', blood_type_source='donor_card', verification_status='verified', donor_enrolled_at=UTC_TIMESTAMP(), donor_availability='available' WHERE id=$uid1;"
# Grandfathered verified user to create request
$requesterEmail = "req_$suffix@example.test"
DbQuery "INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, email_verified_at) VALUES ('$requesterEmail', 'hash', 'Requester', 'User', 'member', 1, 'verified', 'active', UTC_TIMESTAMP());"
$reqId = [int](DbQuery "SELECT id FROM users WHERE email='$requesterEmail';")
DbQuery "INSERT INTO blood_requests (requester_id, request_chapter_id, required_blood_type, quantity_units, facility_name, location_id, urgency, needed_datetime, status, review_status) VALUES ($reqId, 1, 'O+', 1, 'Clinic', $locId, 'routine', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), 'OPEN', 'approved');"
$bloodReqId = [int](DbQuery "SELECT id FROM blood_requests WHERE requester_id=$reqId ORDER BY id DESC LIMIT 1;")
DbQuery "INSERT INTO matches (request_id, donor_id, status) VALUES ($bloodReqId, $uid1, 'NOTIFIED');"
$matchId = [int](DbQuery "SELECT id FROM matches WHERE request_id=$bloodReqId AND donor_id=$uid1 LIMIT 1;")

$rRespond = Post $s "/api/matches/$matchId/respond" @{} $csrf
if ($rRespond.status -eq 403 -and $rRespond.body.error.code -eq 'email_unverified') {
    Ok 'T04 unverified donor blocked from responding to match with code email_unverified'
} else {
    Bad 'T04 respond match blocked' "status=$($rRespond.status), code=$($rRespond.body.error.code), message=$($rRespond.body.error.message)"
}

# --- T05: Verification with wrong code returns 400 with constant message ---
$knownCode = '123456'
$knownHash = & $PhpPath -r "echo password_hash('$knownCode', PASSWORD_BCRYPT);"
DbQuery "UPDATE users SET email_code_hash='$knownHash', email_code_expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id=$uid1;"

$rWrong = Post $s '/api/auth/verify' @{ email = $email1; code = '999999' } $csrf
if ($rWrong.status -eq 400 -and $rWrong.body.error.message -eq 'Invalid or expired verification code.') {
    Ok 'T05 wrong code returns 400 with constant error message'
} else {
    Bad 'T05 wrong code' "status=$($rWrong.status), msg=$($rWrong.body.error.message)"
}

# --- T06: Verification with expired code returns 400 with constant message ---
DbQuery "UPDATE users SET email_code_expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id=$uid1;"
$rExp = Post $s '/api/auth/verify' @{ email = $email1; code = $knownCode } $csrf
if ($rExp.status -eq 400 -and $rExp.body.error.message -eq 'Invalid or expired verification code.') {
    Ok 'T06 expired code returns 400 with constant error message'
} else {
    Bad 'T06 expired code' "status=$($rExp.status), msg=$($rExp.body.error.message)"
}

# Reset valid expiry
DbQuery "UPDATE users SET email_code_expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id=$uid1;"

# --- T07: Verification with non-existent email returns 400 with constant message ---
$rUnknown = Post $s '/api/auth/verify' @{ email = "nobody_$suffix@example.test"; code = '123456' } $csrf
if ($rUnknown.status -eq 400 -and $rUnknown.body.error.message -eq 'Invalid or expired verification code.') {
    Ok 'T07 non-existent email returns 400 with constant error message'
} else {
    Bad 'T07 unknown email' "status=$($rUnknown.status), msg=$($rUnknown.body.error.message)"
}

# --- T08: Attempt limit: 5 failures locks account for 15 minutes ---
$sLim = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrfLim = Get-Csrf $sLim
$emailLim = "limit_$suffix@example.test"
DbQuery "INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, email_verified_at, email_code_hash, email_code_expires_at) VALUES ('$emailLim', 'hash', 'Limit', 'User', 'member', 1, 'verified', 'active', NULL, '$knownHash', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE));"

for ($i = 0; $i -lt 5; $i++) {
    Post $sLim '/api/auth/verify' @{ email = $emailLim; code = '000000' } $csrfLim | Out-Null
}
$rLocked = Post $sLim '/api/auth/verify' @{ email = $emailLim; code = $knownCode } $csrfLim
if ($rLocked.status -eq 429) {
    Ok 'T08 5 failed attempts locks verification with 429'
} else {
    Bad 'T08 attempt limit' "status=$($rLocked.status)"
}

# --- T09: Correct code verifies user, sets email_verified_at, clears hash, clears throttle ---
DbQuery "DELETE FROM auth_throttle WHERE identifier LIKE 'verify:%';"
$rCorrect = Post $s '/api/auth/verify' @{ email = $email1; code = $knownCode } $csrf
if ($rCorrect.status -eq 200 -and $rCorrect.body.data.email_verified -eq $true) {
    Ok 'T09 correct code verifies user and returns email_verified=true'
} else {
    Bad 'T09 correct code' "status=$($rCorrect.status)"
}

$dbVerified = DbQuery "SELECT CONCAT(IF(email_verified_at IS NOT NULL,'VERIFIED','UNVERIFIED'),'|',IF(email_code_hash IS NULL,'HASH_CLEARED','HASH_REMAINS'),'|',IF(email_code_expires_at IS NULL,'EXPIRY_CLEARED','EXPIRY_REMAINS')) FROM users WHERE id=$uid1;"
if ($dbVerified -eq 'VERIFIED|HASH_CLEARED|EXPIRY_CLEARED') {
    Ok 'T09 DB confirms email_verified_at set and code hash/expiry cleared'
} else {
    Bad 'T09 DB verified state' "got $dbVerified"
}

# Audit log records event
$auditRows = [int](DbQuery "SELECT COUNT(*) FROM audit_log WHERE action='auth.email_verify.success' AND target_id='$uid1';")
if ($auditRows -ge 1) {
    Ok 'T09 audit log records auth.email_verify.success'
} else {
    Bad 'T09 audit log' "found $auditRows records"
}

# --- T10: Verified user can now create blood request and respond to match ---
# Refresh session user state by logging in again
Post $s '/api/login' @{ email = $email1; password = 'Password123' } $csrf | Out-Null
$rCreateV = Post $s '/api/requests' @{
    required_blood_type = 'O+'
    quantity_units = 1
    facility_name = 'Bataan General Hospital'
    location_id = $locId
    urgency = 'routine'
    needed_datetime = (Get-Date).AddDays(3).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss')
} $csrf
if ($rCreateV.status -eq 201) {
    Ok 'T10 verified user successfully creates blood request'
} else {
    Bad 'T10 create after verify' "status=$($rCreateV.status)"
}

$rRespondV = Post $s "/api/matches/$matchId/respond" @{} $csrf
if ($rRespondV.status -eq 200 -and $rRespondV.body.data.status -eq 'RESPONDED') {
    Ok 'T10 verified donor successfully responds to match'
} else {
    Bad 'T10 respond after verify' "status=$($rRespondV.status)"
}

# --- T11: Resend verification code generates new hash ---
$emailResend = "resend_$suffix@example.test"
DbQuery "INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, email_verified_at, email_code_hash, email_code_expires_at) VALUES ('$emailResend', 'hash', 'Resend', 'User', 'member', 1, 'verified', 'active', NULL, '$knownHash', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE));"
$resendUid = [int](DbQuery "SELECT id FROM users WHERE email='$emailResend';")

$rResend1 = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf
if ($rResend1.status -eq 200) {
    Ok 'T11 resend returns 200'
} else {
    Bad 'T11 resend 1' "status=$($rResend1.status)"
}

$newHash = DbQuery "SELECT email_code_hash FROM users WHERE id=$resendUid;"
if ($newHash -and $newHash -ne $knownHash) {
    Ok 'T11 resend generates new code hash and invalidates old code'
} else {
    Bad 'T11 new hash' "old=$knownHash, new=$newHash"
}

# Old code should now fail
DbQuery "DELETE FROM auth_throttle WHERE identifier LIKE 'verify:%';"
$rOldFail = Post $s '/api/auth/verify' @{ email = $emailResend; code = $knownCode } $csrf
if ($rOldFail.status -eq 400) {
    Ok 'T11 old code fails after resend'
} else {
    Bad 'T11 old code' "status=$($rOldFail.status)"
}

# --- T12: Resend cooldown (immediate second resend returns 429) ---
$rResendCooldown = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf
if ($rResendCooldown.status -eq 429) {
    Ok 'T12 resend cooldown blocks immediate retry with 429'
} else {
    Bad 'T12 cooldown' "status=$($rResendCooldown.status)"
}

# --- T13: Resend limit: max 3 per 15 min ---
# Clear cooldown lock to test 3-attempt limit
DbQuery "DELETE FROM auth_throttle WHERE identifier='resend:cd:$emailResend';"
$rResend2 = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf
DbQuery "DELETE FROM auth_throttle WHERE identifier='resend:cd:$emailResend';"
$rResend3 = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf
DbQuery "DELETE FROM auth_throttle WHERE identifier='resend:cd:$emailResend';"
$rResend4 = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf

if ($rResend4.status -eq 429) {
    Ok 'T13 resend limit: 4th attempt within 15 min returns 429'
} else {
    Bad 'T13 resend limit' "got status $($rResend4.status)"
}

# --- T14: Resend returns identical response for existing vs non-existing email ---
DbQuery "DELETE FROM auth_throttle WHERE identifier LIKE 'resend:%';"
$rResendExist = Post $s '/api/auth/verify/resend' @{ email = $emailResend } $csrf
$rResendNonExist = Post $s '/api/auth/verify/resend' @{ email = "nonexistent_$suffix@example.test" } $csrf

if ($rResendExist.body.data.message -eq $rResendNonExist.body.data.message -and $rResendExist.status -eq 200 -and $rResendNonExist.status -eq 200) {
    Ok 'T14 resend returns identical response whether email exists or not'
} else {
    Bad 'T14 identical response' "exist=$($rResendExist.body.data.message), nonExist=$($rResendNonExist.body.data.message)"
}

# --- T15: Existing users grandfathered ---
$emailGrand = "grand_$suffix@example.test"
DbQuery "INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status) VALUES ('$emailGrand', '$knownHash', 'Grand', 'Father', 'member', 1, 'verified', 'active');"
$grandVerified = DbQuery "SELECT email_verified_at FROM users WHERE email='$emailGrand';"
if ($grandVerified -and $grandVerified -ne 'NULL') {
    Ok 'T15 existing users grandfathered with default CURRENT_TIMESTAMP'
} else {
    Bad 'T15 grandfathered' "email_verified_at=$grandVerified"
}

# --- T16: Plaintext verification codes are NEVER stored or logged ---
$plainCodeLeak = DbQuery "SELECT COUNT(*) FROM users WHERE email_code_hash REGEXP '^[0-9]{6}$';"
$auditCodeLeak = DbQuery "SELECT COUNT(*) FROM audit_log WHERE context LIKE '%$knownCode%';"
if ([int]$plainCodeLeak -eq 0 -and [int]$auditCodeLeak -eq 0) {
    Ok 'T16 no plaintext codes found in users table or audit log'
} else {
    Bad 'T16 security leak' "plainCodeLeak=$plainCodeLeak, auditCodeLeak=$auditCodeLeak"
}

# Cleanup test records
DbQuery "DELETE FROM matches WHERE request_id IN ($bloodReqId);" | Out-Null
DbQuery "DELETE FROM blood_requests WHERE requester_id IN ($reqId, $uid1);" | Out-Null
DbQuery "DELETE FROM users WHERE email IN ('$email1', '$requesterEmail', '$emailLim', '$emailResend', '$emailGrand');" | Out-Null
DbQuery "DELETE FROM auth_throttle WHERE identifier LIKE 'verify:%' OR identifier LIKE 'resend:%';" | Out-Null

Write-Host ''
Write-Host "== RESULT: $($script:pass) passed, $($script:fail) failed =="
if ($script:fail -gt 0) { exit 1 } else { exit 0 }
