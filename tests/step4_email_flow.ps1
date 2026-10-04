param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path $MysqlPath)) { $MysqlPath = 'D:\xampp\mysql\bin\mysql.exe' }

function Get-Csrf($session) {
    $r = Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
    return $r.data.csrf_token
}

function Invoke-Json($session, $method, $uri, $body, $csrf) {
    $headers = @{}
    if ($csrf) { $headers['X-CSRF-Token'] = $csrf }
    try {
        $args = @{ Uri = "$BaseUrl$uri"; Method = $method; WebSession = $session; TimeoutSec = 15; UseBasicParsing = $true }
        if ($null -ne $body) { $args['Body'] = ($body | ConvertTo-Json -Depth 5); $args['ContentType'] = 'application/json' }
        if ($csrf) { $args['Headers'] = $headers }
        $res = Invoke-WebRequest @args
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

function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3307' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_dev' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
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
             VALUES ('$email', '$hash', '$name', 'Doe', 'Doe', '$role', $chapSql, '$vs', 'active', $btCols, $srcCols, $latSql, $lngSql, $enrSql, $avSql, '1995-06-15');"
    return (DbQuery "SELECT id FROM users WHERE email='$email';")
}

function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = Get-Csrf $s
    $r = Invoke-Json $s 'Post' '/api/login' @{ email = $email; password = 'Str0ngPass1' } $csrf
    if ($r.status -ne 200) { throw "login failed for $email ($($r.status))" }
    return @{ s = $s; csrf = $csrf }
}

# START TEST
$port = $env:TEST_DB_PORT; if (!$port) { $port = '3307' }
$db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_dev' }
Write-Host "--- TEST RUNNING ON DB: $db (port $port) ---"

$suffix = "$(Get-Random)"
$donorEmail = "bloodmatchprojecta@gmail.com"
$incompEmail = "incomp$suffix@test.local"
$requesterEmail = "req$suffix@test.local"
$officerEmail = "off$suffix@test.local"

# 1. Setup records
$donorId = New-FixtureUser $donorEmail 'Test Donor (Compatible)' 'member' 1 'verified' 'O-' 14.68 120.54 $true 'available'
$incompId = New-FixtureUser $incompEmail 'Test Donor (Incompatible)' 'member' 1 'verified' 'AB+' 14.68 120.54 $true 'available'
$reqId = New-FixtureUser $requesterEmail 'Test Requester' 'member' 1 'verified' 'A+' 14.68 120.54 $false $null
$offId = New-FixtureUser $officerEmail 'Test Officer' 'officer' 1 'verified' $null $null $null $false $null

Write-Host "Created test records: Donor=$donorId ($donorEmail), Incompatible=$incompId, Requester=$reqId, Officer=$offId"

# Capture notifications and emails before
$notifsBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
$emailedBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
$incompBefore = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$incompId;")

# 2. Create the request as the requester (Looking for O-)
$reqAuth = Login $requesterEmail
$r = Invoke-Json $reqAuth.s 'Post' '/api/requests' @{
    patient_name = 'Urgent Patient'
    required_blood_type = 'O-'
    units_required = 1
    urgency = ''; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'
    location_id = [int](DbQuery "SELECT id FROM bataan_locations LIMIT 1")
        facility_name = 'General Hospital'
        needed_datetime = '2027-01-01 10:00:00'
    facility_name = 'Bataan General Hospital'
    needed_datetime = (Get-Date).AddDays(1).ToString('yyyy-MM-dd HH:mm:ss')
} $reqAuth.csrf
if ($r.status -ne 201) { throw "Failed to create request: $($r.raw)" }
$bloodReqId = $r.body.data.id
Write-Host "Created Blood Request #$bloodReqId"

Start-Sleep -Seconds 1

# 3. Check notifications after
$notifsAfter = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
$emailedAfter = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")
$incompAfter = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$incompId;")

Write-Host "Donor notifications: Before=$notifsBefore, After=$notifsAfter"
Write-Host "Donor emailed: Before=$emailedBefore, After=$emailedAfter"
Write-Host "Incompatible notifications: Before=$incompBefore, After=$incompAfter"

if ($notifsAfter -ne ($notifsBefore + 1)) { Write-Host "FAIL: Expected exactly 1 new notification for compatible donor." }
if ($emailedAfter -ne ($emailedBefore + 1)) { Write-Host "FAIL: Expected exactly 1 new emailed_at for compatible donor." }
if ($incompAfter -ne $incompBefore) { Write-Host "FAIL: Expected 0 new notifications for incompatible donor." }

# 4. Trigger duplicate-prone cases
# A: Edit without material change
Invoke-Json $reqAuth.s 'Put' "/api/requests/$bloodReqId" @{
    patient_name = 'Urgent Patient (Edited)'
} $reqAuth.csrf | Out-Null

# B: Officer manual rematch
$offAuth = Login $officerEmail
Invoke-Json $offAuth.s 'Post' "/api/officer/requests/$bloodReqId/re-match" @{} $offAuth.csrf | Out-Null

$notifsFinal = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId;")
$emailedFinal = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND emailed_at IS NOT NULL;")

Write-Host "After edits/rematch:"
Write-Host "Donor notifications: Final=$notifsFinal"
Write-Host "Donor emailed: Final=$emailedFinal"

if ($notifsFinal -ne $notifsAfter) { Write-Host "FAIL: Expected NO duplicate notification." }
if ($emailedFinal -ne $emailedAfter) { Write-Host "FAIL: Expected NO duplicate email." }

# 5. Clean up
DbQuery "DELETE FROM blood_requests WHERE id=$bloodReqId;"
DbQuery "DELETE FROM users WHERE id IN ($donorId, $incompId, $reqId, $offId);"

$checkDonor = DbQuery "SELECT id FROM users WHERE id=$donorId;"
if (!$checkDonor) { Write-Host "Cleanup successful." } else { Write-Host "Cleanup FAILED." }
