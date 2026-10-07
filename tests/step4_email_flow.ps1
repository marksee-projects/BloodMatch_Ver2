param(
    # The ONLY address that may receive real email (the compatible donor).
    [Parameter(Mandatory = $true)]
    [string]$TargetEmail,
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path $MysqlPath)) { $MysqlPath = 'D:\xampp\mysql\bin\mysql.exe' }

# ---------------------------------------------------------------------------
# Safety guards (run before ANY write)
# ---------------------------------------------------------------------------
$AllowedDb = 'bloodmatch_test'
$ReservedDomainPattern = '(^|\.)(local|test|invalid)$'

$TEST_DB = $env:TEST_DB_NAME
$TEST_PORT = $env:TEST_DB_PORT; if (!$TEST_PORT) { $TEST_PORT = '3306' }
$TEST_HOST = $env:TEST_DB_HOST; if (!$TEST_HOST) { $TEST_HOST = '127.0.0.1' }

if ($TEST_DB -ne $AllowedDb) {
    Write-Host "REFUSING TO RUN: TEST_DB_NAME must be '$AllowedDb' (got '$TEST_DB')."
    Write-Host "Set it first:  `$env:TEST_DB_NAME='$AllowedDb'; `$env:TEST_DB_PORT='3306'"
    exit 2
}

if ($TargetEmail -notmatch '^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$') {
    Write-Host "REFUSING TO RUN: -TargetEmail is not a plain email address."
    exit 2
}
$targetDomain = ($TargetEmail.Split('@')[1]).ToLowerInvariant()
if ($targetDomain -match $ReservedDomainPattern) {
    Write-Host "REFUSING TO RUN: -TargetEmail uses a reserved domain; Mailer would skip it and nothing would be delivered."
    exit 2
}

function DbQuery($sql) {
    (& $MysqlPath -h $TEST_HOST -P $TEST_PORT -u root -N -B $TEST_DB -e $sql) | Where-Object { $_ -ne '' }
}

# Confirm the database we are ACTUALLY connected to (not just what the env says).
$connected = DbQuery "SELECT CONCAT(DATABASE(), '|', @@hostname, '|', @@port);"
if (!$connected) { Write-Host "REFUSING TO RUN: could not connect to $TEST_HOST`:$TEST_PORT/$TEST_DB."; exit 2 }
$parts = "$connected".Split('|')
$connDb = $parts[0]; $connPort = $parts[2]
Write-Host "=== CONNECTED DB: $connDb  (host $TEST_HOST, server port $connPort) ==="
Write-Host "=== API under test: $BaseUrl ==="
if ($connDb -ne $AllowedDb -or $connDb -eq 'bloodmatch') {
    Write-Host "REFUSING TO RUN: connected database '$connDb' is not '$AllowedDb'."
    exit 2
}

# Any other enrolled O- donor with a deliverable address would also get a real email. Abort if so.
$strayDonors = DbQuery "SELECT email FROM users
    WHERE blood_type='O-' AND donor_enrolled_at IS NOT NULL
      AND LOWER(SUBSTRING_INDEX(email,'@',-1)) NOT REGEXP '(^|\\.)(local|test|invalid)$';"
if ($strayDonors) {
    Write-Host "REFUSING TO RUN: $AllowedDb already has enrolled O- donors with deliverable addresses:"
    $strayDonors | ForEach-Object { Write-Host "  - $_" }
    Write-Host "Remove them (or change them to a .test address) so only -TargetEmail can receive mail."
    exit 2
}

$existing = DbQuery "SELECT id FROM users WHERE email='$TargetEmail';"
if ($existing) {
    Write-Host "REFUSING TO RUN: a user with -TargetEmail already exists in $AllowedDb (id $existing). Delete it first."
    exit 2
}

# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------
function Get-Csrf($session) {
    $r = Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
    return $r.data.csrf_token
}

function Invoke-Json($session, $method, $uri, $body, $csrf) {
    try {
        $req = @{ Uri = "$BaseUrl$uri"; Method = $method; WebSession = $session; TimeoutSec = 30; UseBasicParsing = $true }
        if ($null -ne $body) { $req['Body'] = ($body | ConvertTo-Json -Depth 5); $req['ContentType'] = 'application/json' }
        if ($csrf) { $req['Headers'] = @{ 'X-CSRF-Token' = $csrf } }
        $res = Invoke-WebRequest @req
        return @{ status = [int]$res.StatusCode; body = ($res.Content | ConvertFrom-Json); raw = $res.Content }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }
        $status = [int]$resp.StatusCode
        try {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $raw = $reader.ReadToEnd()
        } catch { $raw = '' }
        try { $parsed = $raw | ConvertFrom-Json } catch { $parsed = $null }
        return @{ status = $status; body = $parsed; raw = $raw }
    }
}

function New-FixtureUser($email, $name, $role, $chapterId, $vs, $bloodType, $lat, $lng, $enrolled, $avail) {
    $hash = & $PhpPath -r "echo password_hash('Str0ngPass1', PASSWORD_BCRYPT);"
    $chapSql = 'NULL'; if ($null -ne $chapterId) { $chapSql = "$chapterId" }
    $btCols = 'NULL';  if ($null -ne $bloodType) { $btCols = "'$bloodType'" }
    $srcCols = 'NULL'; if ($null -ne $bloodType) { $srcCols = "'self_reported'" }
    $latSql = 'NULL';  if ($null -ne $lat) { $latSql = "$lat" }
    $lngSql = 'NULL';  if ($null -ne $lng) { $lngSql = "$lng" }
    $enrSql = 'NULL';  if ($enrolled) { $enrSql = 'UTC_TIMESTAMP()' }
    $avSql = 'NULL';   if ($null -ne $avail) { $avSql = "'$avail'" }
    DbQuery "INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, blood_type, blood_type_source, latitude, longitude, donor_enrolled_at, donor_availability, date_of_birth)
             VALUES ('$email', '$hash', '$name', 'Doe', '$role', $chapSql, '$vs', 'active', $btCols, $srcCols, $latSql, $lngSql, $enrSql, $avSql, '1995-06-15');" | Out-Null
    return (DbQuery "SELECT id FROM users WHERE email='$email';")
}

function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = Get-Csrf $s
    $r = Invoke-Json $s 'Post' '/api/login' @{ email = $email; password = 'Str0ngPass1' } $csrf
    if ($r.status -ne 200) {
        throw "login failed for $email ($($r.status)). Is the server at $BaseUrl pointed at $AllowedDb?"
    }
    return @{ s = $s; csrf = $csrf }
}

# ---------------------------------------------------------------------------
# Test
# ---------------------------------------------------------------------------
$failures = 0
function Check($cond, $msg) {
    if ($cond) { Write-Host "PASS: $msg" } else { Write-Host "FAIL: $msg"; $script:failures++ }
}

$suffix = "$(Get-Random)"
$donorEmail = $TargetEmail                              # real inbox, the only deliverable address
$incompEmail = "incomp$suffix@bloodmatch.test"          # reserved: Mailer skips
$requesterEmail = "req$suffix@bloodmatch.test"          # reserved: Mailer skips
$officerEmail = "off$suffix@bloodmatch.test"            # reserved: Mailer skips

$createdUserIds = @()
$bloodReqIds = @()

try {
    # 1. Fixture records (written to the confirmed bloodmatch_test only)
    $donorId = New-FixtureUser $donorEmail 'Test Donor (Compatible)' 'member' 1 'verified' 'O-' 14.68 120.54 $true 'available'
    $createdUserIds += $donorId
    $incompId = New-FixtureUser $incompEmail 'Test Donor (Incompatible)' 'member' 1 'verified' 'AB+' 14.68 120.54 $true 'available'
    $createdUserIds += $incompId
    $reqId = New-FixtureUser $requesterEmail 'Test Requester' 'member' 1 'verified' 'A+' 14.68 120.54 $false $null
    $createdUserIds += $reqId
    $offId = New-FixtureUser $officerEmail 'Test Officer' 'officer' 1 'verified' $null $null $null $false $null
    $createdUserIds += $offId

    Write-Host "Created test users: Donor=$donorId ($donorEmail), Incompatible=$incompId, Requester=$reqId, Officer=$offId"

    $notifsBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
    $emailedBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
    $incompBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$incompId;")

    # 2. Create a nonemergency request: in-app notification only.
    $reqAuth = Login $requesterEmail
    $r = Invoke-Json $reqAuth.s 'Post' '/api/requests' @{
        required_blood_type = 'O-'
        quantity_units = 1
        urgency = 'urgent'
        facility_name = 'Bataan General Hospital'
        location_id = [int](DbQuery "SELECT id FROM bataan_locations LIMIT 1;")
        needed_datetime = (Get-Date).ToUniversalTime().AddDays(1).ToString('yyyy-MM-dd HH:mm:ss')
    } $reqAuth.csrf
    if ($r.status -ne 201) { throw "Failed to create request ($($r.status)): $($r.raw)" }
    $urgentReqId = $r.body.data.request.id
    $bloodReqIds += $urgentReqId
    Write-Host "Created urgent Blood Request #$urgentReqId"

    Start-Sleep -Seconds 1

    $notifsAfterUrgent = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
    $emailedAfterUrgent = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
    $incompAfter = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$incompId;")

    Write-Host "Urgent notifications: Before=$notifsBefore, After=$notifsAfterUrgent"
    Write-Host "Urgent emailed:       Before=$emailedBefore, After=$emailedAfterUrgent"
    Write-Host "Incompatible notifs: Before=$incompBefore, After=$incompAfter"

    Check ($notifsAfterUrgent -eq ($notifsBefore + 1)) 'urgent request creates exactly 1 in-app notification'
    Check ($emailedAfterUrgent -eq $emailedBefore) 'urgent request sends no email'
    Check ($incompAfter -eq $incompBefore) '0 new notifications for incompatible donor'

    # 3. Emergency request: exactly one email remains required at creation.
    $r = Invoke-Json $reqAuth.s 'Post' '/api/requests' @{
        required_blood_type = 'O-'
        quantity_units = 1
        urgency = 'emergency'
        facility_name = 'Emergency Bataan General Hospital'
        location_id = [int](DbQuery "SELECT id FROM bataan_locations LIMIT 1;")
        needed_datetime = (Get-Date).ToUniversalTime().AddDays(1).ToString('yyyy-MM-dd HH:mm:ss')
    } $reqAuth.csrf
    if ($r.status -ne 201) { throw "Failed to create emergency request ($($r.status)): $($r.raw)" }
    $emergencyReqId = $r.body.data.request.id
    $bloodReqIds += $emergencyReqId
    Start-Sleep -Seconds 1

    $notifsAfterEmergency = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
    $emailedAfterEmergency = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
    Check ($notifsAfterEmergency -eq ($notifsAfterUrgent + 1)) 'emergency request creates exactly 1 in-app notification'
    Check ($emailedAfterEmergency -eq ($emailedBefore + 1)) 'emergency request creation sends exactly 1 email'

    # 4. Duplicate-prone paths: neither non-material edits nor manual re-match resend.
    Invoke-Json $reqAuth.s 'Put' "/api/requests/$urgentReqId" @{ facility_name = 'Bataan General Hospital' } $reqAuth.csrf | Out-Null
    $offAuth = Login $officerEmail
    Invoke-Json $offAuth.s 'Post' "/api/officer/requests/$urgentReqId/re-match" @{} $offAuth.csrf | Out-Null
    Invoke-Json $offAuth.s 'Post' "/api/officer/requests/$emergencyReqId/re-match" @{} $offAuth.csrf | Out-Null

    $notifsFinal = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
    $emailedFinal = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
    Write-Host "After edit/re-match: notifications=$notifsFinal, emailed=$emailedFinal"

    Check ($notifsFinal -eq $notifsAfterEmergency) 'no duplicate notification after edit/re-match'
    Check ($emailedFinal -eq $emailedAfterEmergency) 'no duplicate email after edit/re-match'
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)"
    $failures++
}
finally {
    # 5. Cleanup (bloodmatch_test only; connection already verified above)
    foreach ($bloodReqId in $bloodReqIds) {
        if ($bloodReqId) { DbQuery "DELETE FROM blood_requests WHERE id=$bloodReqId;" | Out-Null }
    }
    $ids = ($createdUserIds | Where-Object { $_ }) -join ','
    if ($ids) {
        DbQuery "DELETE FROM notifications WHERE user_id IN ($ids);" | Out-Null
        DbQuery "DELETE FROM users WHERE id IN ($ids);" | Out-Null
        $left = DbQuery "SELECT COUNT(*) FROM users WHERE id IN ($ids);"
        if ([int]$left -eq 0) { Write-Host "Cleanup successful." } else { Write-Host "Cleanup FAILED ($left users left)."; $failures++ }
    }
}

Write-Host "Check your inbox ($TargetEmail) for exactly ONE '[BloodMatch] New compatible donation opportunity' email."
if ($failures -gt 0) { Write-Host "RESULT: $failures failure(s)"; exit 1 } else { Write-Host "RESULT: all checks passed"; exit 0 }
