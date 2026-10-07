param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$script:pass = 0
$script:fail = 0

function Ok($name) { $script:pass++; Write-Host "PASS  $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL  $name -> $why" }

function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3306' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_test' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
}

function Get-Csrf($session) {
    $r = Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing
    return $r.data.csrf_token
}

function Invoke-Json($session, $method, $uri, $body, $csrf) {
    try {
        $args = @{ Uri = "$BaseUrl$uri"; Method = $method; WebSession = $session; TimeoutSec = 30; UseBasicParsing = $true }
        if ($null -ne $body) { $args['Body'] = ($body | ConvertTo-Json -Depth 5); $args['ContentType'] = 'application/json' }
        if ($csrf) { $args['Headers'] = @{ 'X-CSRF-Token' = $csrf } }
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

function Login($email) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = Get-Csrf $session
    $r = Invoke-Json $session 'Post' '/api/login' @{ email = $email; password = 'Str0ngPass1' } $csrf
    if ($r.status -ne 200) { throw "Login failed for $email ($($r.status))." }
    return @{ s = $session; csrf = $csrf }
}

function New-User($email, $name, $role, $verification, $bloodType, $enrolled, $availability, $locationId) {
    if ($email -notmatch '@[A-Za-z0-9.-]+\.(test|invalid|local)$') {
        throw "Fixture email must use a reserved domain: $email"
    }
    $hash = & $PhpPath -r "echo password_hash('Str0ngPass1', PASSWORD_BCRYPT);"
    $bloodSql = 'NULL'; $sourceSql = 'NULL'
    if ($null -ne $bloodType) { $bloodSql = "'$bloodType'"; $sourceSql = "'self_reported'" }
    $enrolledSql = if ($enrolled) { 'UTC_TIMESTAMP()' } else { 'NULL' }
    $availabilitySql = if ($null -ne $availability) { "'$availability'" } else { 'NULL' }
    $locationSql = if ($null -ne $locationId) { "$locationId" } else { 'NULL' }
    DbQuery "INSERT INTO users
        (email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,
         date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,location_id,email_verified_at)
        VALUES ('$email','$hash','$name','Refresh','$role',1,'$verification','active','1995-06-15',
                $bloodSql,$sourceSql,$enrolledSql,$availabilitySql,$locationSql,UTC_TIMESTAMP());" | Out-Null
    return [int](DbQuery "SELECT id FROM users WHERE email='$email' LIMIT 1;")
}

function New-Request($requesterId, $bloodType, $facility, $neededAt, $locationId, $urgency = 'routine') {
    DbQuery "INSERT INTO blood_requests
        (requester_id,request_chapter_id,required_blood_type,quantity_units,facility_name,location_id,urgency,needed_datetime,status,review_status)
        VALUES ($requesterId,1,'$bloodType',1,'$facility',$locationId,'$urgency','$neededAt','OPEN','not_required');" | Out-Null
    return [int](DbQuery "SELECT id FROM blood_requests WHERE requester_id=$requesterId AND facility_name='$facility' ORDER BY id DESC LIMIT 1;")
}

function Match-Count($requestId, $donorId) {
    return [int](DbQuery "SELECT COUNT(*) FROM matches WHERE request_id=$requestId AND donor_id=$donorId;")
}

function Match-Status($requestId, $donorId) {
    return "$(DbQuery "SELECT status FROM matches WHERE request_id=$requestId AND donor_id=$donorId LIMIT 1;")".Trim()
}

function Match-Generation($requestId, $donorId) {
    return [int](DbQuery "SELECT COALESCE(MAX(generation),0) FROM matches WHERE request_id=$requestId AND donor_id=$donorId;")
}

function Match-NotificationCount($requestId, $donorId) {
    return [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id=$donorId AND type='match.new' AND related_id=$requestId;")
}

$connectedDb = "$(DbQuery 'SELECT DATABASE();')".Trim()
if ($connectedDb -ne 'bloodmatch_test') {
    Bad 'DB safety check' "connected to $connectedDb"
    exit 1
}
Ok 'DB safety check: bloodmatch_test only'

$suffix = "$(Get-Random)"
$future = (Get-Date).ToUniversalTime().AddDays(2).ToString('yyyy-MM-dd HH:mm:ss')
$past = (Get-Date).ToUniversalTime().AddMinutes(-5).ToString('yyyy-MM-dd HH:mm:ss')
$balangaLocId = [int](DbQuery "SELECT id FROM bataan_locations WHERE psgc_code='030803000' LIMIT 1;")
$oraniLocId = [int](DbQuery "SELECT id FROM bataan_locations WHERE psgc_code='030809000' LIMIT 1;")

Write-Host '== Donor reconciliation suite =='

$requesterEmail = "refresh-requester-$suffix@example.test"
$requesterId = New-User $requesterEmail 'Requester' 'member' 'verified' 'A+' $false $null $balangaLocId
$compatibleRequest = New-Request $requesterId 'O+' "Refresh Compatible $suffix" $future $balangaLocId 'critical'
$incompatibleRequest = New-Request $requesterId 'A-' "Refresh Incompatible $suffix" $future $balangaLocId
$staleRequest = New-Request $requesterId 'O+' "Refresh Stale $suffix" $past $balangaLocId

# Existing unrelated donor state must never be changed or notified by another donor's reconciliation.
$bystanderEmail = "refresh-bystander-$suffix@example.test"
$bystanderId = New-User $bystanderEmail 'Bystander' 'member' 'verified' 'O+' $true 'available' $balangaLocId
DbQuery "INSERT INTO matches (request_id,donor_id,generation,status,rank_score) VALUES ($compatibleRequest,$bystanderId,1,'POTENTIAL',1);" | Out-Null

# Enrollment backfills compatible current requests only.
$enrolleeEmail = "refresh-enrollee-$suffix@example.test"
$enrolleeId = New-User $enrolleeEmail 'Enrollee' 'member' 'verified' 'O+' $false $null $balangaLocId
$ownRequest = New-Request $enrolleeId 'O+' "Refresh Own $suffix" $future $balangaLocId
$enrollee = Login $enrolleeEmail
$r = Invoke-Json $enrollee.s 'Post' '/api/profile/enroll-donor' @{} $enrollee.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $enrolleeId) -eq 1) { Ok 'R01 enrollment creates compatible persisted match' } else { Bad 'R01 enrollment reconciliation' "status=$($r.status)" }
if ((Match-Count $incompatibleRequest $enrolleeId) -eq 0) { Ok 'R02 incompatible request excluded' } else { Bad 'R02 incompatible request' 'match created' }
if ((Match-Count $ownRequest $enrolleeId) -eq 0) { Ok 'R03 requester is not matched to own request' } else { Bad 'R03 self-match' 'match created' }
if ((Match-Count $staleRequest $enrolleeId) -eq 0) { Ok 'R04 past needed-by OPEN request skipped' } else { Bad 'R04 stale request' 'match created' }
if ((Match-Status $compatibleRequest $bystanderId) -eq 'POTENTIAL' -and (Match-NotificationCount $compatibleRequest $bystanderId) -eq 0) { Ok 'R05 unrelated donor is unchanged and not notified' } else { Bad 'R05 unrelated donor isolation' "status=$(Match-Status $compatibleRequest $bystanderId)" }

$enrolleeGeneration = Match-Generation $compatibleRequest $enrolleeId
$r = Invoke-Json $enrollee.s 'Put' '/api/profile' @{ location_id = $oraniLocId } $enrollee.csrf
if ((Match-Count $compatibleRequest $enrolleeId) -eq 1 -and (Match-NotificationCount $compatibleRequest $enrolleeId) -eq 1 -and (Match-Generation $compatibleRequest $enrolleeId) -eq $enrolleeGeneration) { Ok 'R06 repeat refresh has no duplicate row, notification, or generation bump' } else { Bad 'R06 repeated refresh dedup' 'row, notification, or generation changed' }
$r = Invoke-Json $enrollee.s 'Post' '/api/profile/donor-availability' @{ availability = 'unavailable' } $enrollee.csrf
if ((Match-Status $compatibleRequest $enrolleeId) -eq 'CLOSED') { Ok 'R07 unavailable closes unanswered match row' } else { Bad 'R07 unavailable reconciliation' "status=$(Match-Status $compatibleRequest $enrolleeId)" }
$r = Invoke-Json $enrollee.s 'Post' '/api/profile/donor-availability' @{ availability = 'available' } $enrollee.csrf
if ((Match-Status $compatibleRequest $enrolleeId) -eq 'CLOSED' -and (Match-Count $compatibleRequest $enrolleeId) -eq 1) { Ok 'R08 available does not reopen CLOSED match' } else { Bad 'R08 closed preservation' "status=$(Match-Status $compatibleRequest $enrolleeId)" }

# An enrolled unavailable donor gains a new match on the available transition, then loses only the unanswered match.
$toggleEmail = "refresh-toggle-$suffix@example.test"
$toggleId = New-User $toggleEmail 'Toggle' 'member' 'verified' 'O+' $true 'unavailable' $balangaLocId
$toggle = Login $toggleEmail
$r = Invoke-Json $toggle.s 'Post' '/api/profile/donor-availability' @{ availability = 'available' } $toggle.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $toggleId) -eq 1) { Ok 'R09 available transition backfills match' } else { Bad 'R09 available transition' "status=$($r.status)" }
$r = Invoke-Json $toggle.s 'Post' '/api/profile/donor-availability' @{ availability = 'unavailable' } $toggle.csrf
if ((Match-Status $compatibleRequest $toggleId) -eq 'CLOSED') { Ok 'R10 unavailable transition closes its unanswered match' } else { Bad 'R10 unavailable transition' "status=$(Match-Status $compatibleRequest $toggleId)" }

# Verification approval reconciles a legacy/pre-enrolled pending member.
$officerEmail = "refresh-officer-$suffix@example.test"
$officerId = New-User $officerEmail 'Officer' 'officer' 'verified' $null $false $null $balangaLocId
$officer = Login $officerEmail
$pendingEmail = "refresh-pending-$suffix@example.test"
$pendingId = New-User $pendingEmail 'Pending' 'member' 'pending' 'O+' $true 'available' $balangaLocId
$docName = ([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))
DbQuery "INSERT INTO member_documents (user_id,doc_type,stored_name,mime_type,original_ext,size_bytes,uploaded_at) VALUES ($pendingId,'national_id','$docName','application/pdf','pdf',128,UTC_TIMESTAMP());" | Out-Null
$r = Invoke-Json $officer.s 'Post' "/api/officer/verifications/$pendingId/decision" @{ decision = 'verified' } $officer.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $pendingId) -eq 1) { Ok 'R11 verification approval backfills eligible donor' } else { Bad 'R11 verified approval reconciliation' "status=$($r.status)" }

# Profile location and blood-type eligibility changes both reconcile.
$locationEmail = "refresh-location-$suffix@example.test"
$locationId = New-User $locationEmail 'Location' 'member' 'verified' 'O+' $true 'available' $null
$locationUser = Login $locationEmail
$r = Invoke-Json $locationUser.s 'Put' '/api/profile' @{ location_id = $balangaLocId } $locationUser.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $locationId) -eq 1) { Ok 'R12 location change backfills match' } else { Bad 'R12 location reconciliation' "status=$($r.status)" }
$locationGeneration = Match-Generation $compatibleRequest $locationId
$r = Invoke-Json $locationUser.s 'Put' '/api/profile' @{ location_id = $oraniLocId } $locationUser.csrf
if ((Match-Count $compatibleRequest $locationId) -eq 1 -and (Match-NotificationCount $compatibleRequest $locationId) -eq 1 -and (Match-Generation $compatibleRequest $locationId) -eq $locationGeneration) { Ok 'R13 repeated location refresh is deduplicated' } else { Bad 'R13 location dedup' 'duplicate or generation bump' }

$bloodEmail = "refresh-blood-$suffix@example.test"
$bloodId = New-User $bloodEmail 'Blood' 'member' 'verified' 'B+' $true 'available' $balangaLocId
$bloodUser = Login $bloodEmail
$r = Invoke-Json $bloodUser.s 'Put' '/api/profile' @{ blood_type = 'O+' } $bloodUser.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $bloodId) -eq 1) { Ok 'R14 blood-type change backfills match' } else { Bad 'R14 blood-type reconciliation' "status=$($r.status)" }
$r = Invoke-Json $bloodUser.s 'Put' '/api/profile' @{ blood_type = 'B+' } $bloodUser.csrf
if ($r.status -eq 200 -and (Match-Status $compatibleRequest $bloodId) -eq 'CLOSED') { Ok 'R15 losing blood-type compatibility closes unanswered match' } else { Bad 'R15 blood-type eligibility loss' "status=$(Match-Status $compatibleRequest $bloodId)" }

# Privileged roles cannot enroll and remain excluded.
$adminEmail = "refresh-admin-$suffix@example.test"
$adminId = New-User $adminEmail 'Admin' 'admin' 'verified' 'O+' $false $null $balangaLocId
$admin = Login $adminEmail
$rAdmin = Invoke-Json $admin.s 'Post' '/api/profile/enroll-donor' @{} $admin.csrf
$rOfficer = Invoke-Json $officer.s 'Post' '/api/profile/enroll-donor' @{} $officer.csrf
if ($rAdmin.status -eq 403 -and $rOfficer.status -eq 403 -and (Match-Count $compatibleRequest $adminId) -eq 0 -and (Match-Count $compatibleRequest $officerId) -eq 0) { Ok 'R16 admin and officer enrollment rejected and excluded' } else { Bad 'R16 privileged-role exclusion' "admin=$($rAdmin.status) officer=$($rOfficer.status)" }

# System-enforced donation windows remain authoritative.
$cooldownEmail = "refresh-cooldown-$suffix@example.test"
$cooldownId = New-User $cooldownEmail 'Cooldown' 'member' 'verified' 'O+' $true 'available' $null
DbQuery "UPDATE users SET last_verified_donation_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY) WHERE id=$cooldownId;" | Out-Null
$cooldown = Login $cooldownEmail
$r = Invoke-Json $cooldown.s 'Put' '/api/profile' @{ location_id = $balangaLocId } $cooldown.csrf
$standbyEmail = "refresh-standby-$suffix@example.test"
$standbyId = New-User $standbyEmail 'Standby' 'member' 'verified' 'O+' $true 'standby' $null
DbQuery "UPDATE users SET last_verified_donation_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id=$standbyId;" | Out-Null
$standby = Login $standbyEmail
$r = Invoke-Json $standby.s 'Put' '/api/profile' @{ location_id = $balangaLocId } $standby.csrf
if ((Match-Count $compatibleRequest $cooldownId) -eq 0 -and (Match-Count $compatibleRequest $standbyId) -eq 0) { Ok 'R17 cooldown and standby donors excluded' } else { Bad 'R17 enforced windows' 'blocked donor matched' }

$emailPendingEmail = "refresh-email-pending-$suffix@example.test"
$emailPendingId = New-User $emailPendingEmail 'EmailPending' 'member' 'verified' 'O+' $true 'available' $null
DbQuery "UPDATE users SET email_verified_at=NULL WHERE id=$emailPendingId;" | Out-Null
$emailPending = Login $emailPendingEmail
$r = Invoke-Json $emailPending.s 'Put' '/api/profile' @{ location_id = $balangaLocId } $emailPending.csrf
if ($r.status -eq 200 -and (Match-Count $compatibleRequest $emailPendingId) -eq 0) { Ok 'R18 email-unverified donor excluded' } else { Bad 'R18 email verification eligibility' "status=$($r.status)" }

# RESPONDED is protected even when eligibility is lost.
$respondedEmail = "refresh-responded-$suffix@example.test"
$respondedId = New-User $respondedEmail 'Responded' 'member' 'verified' 'O+' $true 'available' $balangaLocId
DbQuery "INSERT INTO matches (request_id,donor_id,generation,status,rank_score) VALUES ($compatibleRequest,$respondedId,1,'RESPONDED',1);" | Out-Null
$responded = Login $respondedEmail
$r = Invoke-Json $responded.s 'Post' '/api/profile/donor-availability' @{ availability = 'unavailable' } $responded.csrf
if ((Match-Status $compatibleRequest $respondedId) -eq 'RESPONDED') { Ok 'R19 RESPONDED match survives unavailable transition' } else { Bad 'R19 responded preservation' "status=$(Match-Status $compatibleRequest $respondedId)" }

$refreshDonorIds = @($enrolleeId,$toggleId,$pendingId,$locationId,$bloodId,$cooldownId,$standbyId,$emailPendingId) -join ','
$emailedRefreshes = [int](DbQuery "SELECT COUNT(*) FROM notifications WHERE user_id IN ($refreshDonorIds) AND type='match.new' AND emailed_at IS NOT NULL;")
if ($emailedRefreshes -eq 0) { Ok 'R20 donor refresh notifications have no email marker, including critical' } else { Bad 'R20 refresh email policy' "emailed=$emailedRefreshes" }

Write-Host ''
Write-Host "== RESULT: $($script:pass) passed, $($script:fail) failed =="
if ($script:fail -gt 0) { exit 1 }
