param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$script:pass = 0
$script:fail = 0
$suffix = "$(Get-Random)"
$password = 'HomeSafety123!'
$hash = & $PhpPath -r "echo password_hash('$password', PASSWORD_BCRYPT);"

function Ok($name) { $script:pass++; Write-Host "PASS  $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL  $name -> $why" }
function DbQuery($sql) {
    $port = $env:TEST_DB_PORT; if (!$port) { $port = '3306' }
    $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_test' }
    (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql) | Where-Object { $_ -ne '' }
}
function Url($value) { return [uri]::EscapeDataString([string]$value) }
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
function Login($user) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -Method Get -WebSession $session -TimeoutSec 10 -UseBasicParsing).data.csrf_token
    $r = Invoke-Json $session 'Post' '/api/login' @{ email = $user.email; password = $password } $csrf
    if ($r.status -ne 200) { throw "Login failed for $($user.email): $($r.status)" }
    return @{ s = $session; csrf = $csrf }
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
    $email = "home-safety-$label-$suffix@example.test"
    if ($email -notmatch '@[A-Za-z0-9.-]+\.(test|invalid|local)$') { throw "Non-reserved fixture email: $email" }
    $bloodSql = if ($null -eq $bloodType) { 'NULL' } else { "'$bloodType'" }
    $sourceSql = if ($null -eq $bloodType) { 'NULL' } else { "'self_reported'" }
    $enrolledSql = if ($enrolled) { 'UTC_TIMESTAMP()' } else { 'NULL' }
    $availabilitySql = if ($null -eq $availability) { 'NULL' } else { "'$availability'" }
    $emailVerifiedSql = if ($emailVerified) { 'UTC_TIMESTAMP()' } else { 'NULL' }
    DbQuery "INSERT INTO users
        (email,password_hash,first_name,last_name,phone,role,chapter_id,verification_status,account_status,
         date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,donor_availability,
         last_verified_donation_at,email_verified_at)
        VALUES ('$email','$hash','Home','$label','09170000000','$role',1,'$verification','active',
                '1990-01-02',$bloodSql,$sourceSql,0,$enrolledSql,$availabilitySql,$lastDonationSql,$emailVerifiedSql);" | Out-Null
    return @{ id = [int](DbQuery "SELECT id FROM users WHERE email='$email' LIMIT 1;"); email = $email }
}
function New-Request($requesterId, $blood, $facility, $urgency, $neededAt, $status = 'OPEN', $locationId = $null) {
    $locationSql = if ($null -eq $locationId) { 'NULL' } else { [string]$locationId }
    DbQuery "INSERT INTO blood_requests
        (requester_id,request_chapter_id,required_blood_type,quantity_units,facility_name,location_id,urgency,needed_datetime,status,review_status)
        VALUES ($requesterId,1,'$blood',1,'$facility',$locationSql,'$urgency','$neededAt','$status','not_required');" | Out-Null
    return [int](DbQuery "SELECT id FROM blood_requests WHERE requester_id=$requesterId AND facility_name='$facility' ORDER BY id DESC LIMIT 1;")
}
function Feed($login, $query = '') { return Invoke-Json $login.s 'Get' ("/api/home-feed" + $query) $null $login.csrf }
function Card($response, $id) { return @($response.body.data.requests | Where-Object { [int]$_.id -eq $id }) }
function Assert-State($name, $login, $requestId, $canRespond, $reasonCode, $needsDate = $false) {
    $r = Feed $login ("?q=" + (Url "State Hub $suffix"))
    $card = Card $r $requestId
    $dateOk = if ($needsDate) { $card.Count -eq 1 -and -not [string]::IsNullOrWhiteSpace([string]$card[0].eligible_again_at) } else { $true }
    if ($r.status -eq 200 -and $card.Count -eq 1 -and $card[0].can_respond -eq $canRespond -and $card[0].reason_code -eq $reasonCode -and $dateOk) {
        Ok $name
    } else {
        Bad $name "status=$($r.status) cards=$($card.Count) can=$($card[0].can_respond) reason=$($card[0].reason_code) date=$($card[0].eligible_again_at)"
    }
}

Write-Host '== Home safety suite =='
$connectedDb = "$(DbQuery 'SELECT DATABASE();')".Trim()
if ($connectedDb -ne 'bloodmatch_test') { Bad 'DB safety check' "connected to $connectedDb"; exit 1 }
Ok 'DB safety check: bloodmatch_test only'

$future = (Get-Date).ToUniversalTime().AddDays(3).ToString('yyyy-MM-dd HH:mm:ss')
$past = (Get-Date).ToUniversalTime().AddMinutes(-5).ToString('yyyy-MM-dd HH:mm:ss')
$balangaId = [int](DbQuery "SELECT id FROM bataan_locations WHERE psgc_code='030803000' LIMIT 1;")
$oraniId = [int](DbQuery "SELECT id FROM bataan_locations WHERE psgc_code='030809000' LIMIT 1;")
$requester = New-User 'requester' 'member' 'verified' 'A+' $false $null
$stateRequest = New-Request $requester.id 'O+' "State Hub $suffix" 'critical' $future 'OPEN' $balangaId

$unverifiedUser = New-User 'unverified' 'member' 'verified' 'O+' $true 'available'
$unverified = Login $unverifiedUser
DbQuery "UPDATE users SET verification_status='pending' WHERE id=$($unverifiedUser.id);" | Out-Null
$r = Feed $unverified
if ($r.status -eq 200 -and @($r.body.data.requests).Count -eq 0 -and $r.body.data.reason_code -eq 'not_verified') { Ok 'H01 unverified viewer receives explained empty feed' } else { Bad 'H01 unverified state' "status=$($r.status) reason=$($r.body.data.reason_code)" }

$inactiveUser = New-User 'inactive' 'member' 'verified' 'O+' $true 'available'
$inactive = Login $inactiveUser
DbQuery "UPDATE users SET account_status='deactivated', deactivated_at=UTC_TIMESTAMP() WHERE id=$($inactiveUser.id);" | Out-Null
$r = Feed $inactive
if ($r.status -eq 200 -and @($r.body.data.requests).Count -eq 0 -and $r.body.data.reason_code -eq 'not_active') { Ok 'H02 inactive session receives explained empty feed' } else { Bad 'H02 inactive state' "status=$($r.status) reason=$($r.body.data.reason_code)" }

$staff = Login (New-User 'staff' 'officer' 'verified' 'O+' $false $null)
$r = Feed $staff ("?q=" + (Url "State Hub $suffix"))
$staffCard = Card $r $stateRequest
if ($staffCard.Count -eq 1 -and $staffCard[0].can_respond -eq $false -and $staffCard[0].reason_code -eq 'staff_account' -and $staffCard[0].reason_text -eq "Staff accounts can't donate") { Ok 'H03 staff with blood type sees read-only cards' } else { Bad 'H03 staff read-only state' "cards=$($staffCard.Count) reason=$($staffCard[0].reason_text)" }
$staffNoBlood = Login (New-User 'staff-noblood' 'admin' 'verified' $null $false $null)
$r = Feed $staffNoBlood
if (@($r.body.data.requests).Count -eq 0 -and $r.body.data.reason_code -eq 'staff_no_blood_type') { Ok 'H04 staff without blood type never receives unfiltered requests' } else { Bad 'H04 staff no-blood state' "reason=$($r.body.data.reason_code)" }

$eligibleUser = New-User 'eligible' 'member' 'verified' 'O+' $true 'available'
$eligible = Login $eligibleUser
Assert-State 'H05 eligible donor can respond' $eligible $stateRequest $true $null
Assert-State 'H06 not-enrolled donor is read-only' (Login (New-User 'not-enrolled' 'member' 'verified' 'O+' $false $null)) $stateRequest $false 'not_enrolled'
Assert-State 'H07 unavailable donor is read-only' (Login (New-User 'unavailable' 'member' 'verified' 'O+' $true 'unavailable')) $stateRequest $false 'unavailable'
Assert-State 'H08 standby donor includes eligible-again UTC' (Login (New-User 'standby' 'member' 'verified' 'O+' $true 'standby' $true 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)')) $stateRequest $false 'standby' $true
Assert-State 'H09 cooldown donor includes eligible-again UTC' (Login (New-User 'cooldown' 'member' 'verified' 'O+' $true 'available' $true 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)')) $stateRequest $false 'cooldown' $true
Assert-State 'H10 email-unverified donor is read-only' (Login (New-User 'email-unverified' 'member' 'verified' 'O+' $true 'available' $false)) $stateRequest $false 'email_unverified'

$ownRequest = New-Request $eligibleUser.id 'O+' "Exclude Own $suffix" 'critical' $future 'OPEN' $balangaId
$closedRequest = New-Request $requester.id 'O+' "Exclude Closed $suffix" 'critical' $future 'CANCELLED' $balangaId
$expiredRequest = New-Request $requester.id 'O+' "Exclude Expired $suffix" 'critical' $past 'OPEN' $balangaId
$incompatibleRequest = New-Request $requester.id 'A-' "Exclude Incompatible $suffix" 'critical' $future 'OPEN' $balangaId
$includedRequest = New-Request $requester.id 'A+' "Exclude Included $suffix" 'critical' $future 'OPEN' $balangaId
$r = Feed $eligible ("?q=" + (Url "Exclude"))
$ids = @($r.body.data.requests | ForEach-Object { [int]$_.id })
if ($ids -contains $includedRequest -and $ids -notcontains $ownRequest -and $ids -notcontains $closedRequest -and $ids -notcontains $expiredRequest -and $ids -notcontains $incompatibleRequest) { Ok 'H11 own, closed, expired, and incompatible requests are excluded' } else { Bad 'H11 base safety scope' ($ids -join ',') }

$pageRequester = New-User 'page-requester' 'member' 'verified' 'A+' $false $null
$pageIds = @()
for ($i = 1; $i -le 22; $i++) {
    $pageIds += New-Request $pageRequester.id 'O+' ("PageSet $suffix Item $i") 'routine' $future 'OPEN' $balangaId
}
$page1 = Feed $eligible ("?q=" + (Url "PageSet $suffix") + '&page=1')
$page2 = Feed $eligible ("?q=" + (Url "PageSet $suffix") + '&page=2')
$pagingOk = @($page1.body.data.requests).Count -eq 20 -and @($page2.body.data.requests).Count -eq 2 `
    -and [int]$page1.body.data.page -eq 1 -and [int]$page1.body.data.page_size -eq 20 `
    -and $page1.body.data.has_more -eq $true -and $page2.body.data.has_more -eq $false `
    -and [int]$page1.body.data.total_matching -eq 22 -and [int]$page2.body.data.total_matching -eq 22 `
    -and [int]$page1.body.data.total_unfiltered -eq [int]$page2.body.data.total_unfiltered `
    -and [int]$page1.body.data.total_unfiltered -ge 22
if ($pagingOk) { Ok 'H12 paging is fixed at 20 with stable matching and unfiltered totals' } else { Bad 'H12 paging metadata' "p1=$(@($page1.body.data.requests).Count) p2=$(@($page2.body.data.requests).Count) matching=$($page1.body.data.total_matching)" }

$filterPrefix = "FilterSet $suffix"
$alpha = New-Request $requester.id 'O+' "$filterPrefix Alpha" 'critical' $future 'OPEN' $balangaId
$beta = New-Request $requester.id 'A+' "$filterPrefix Beta" 'urgent' $future 'OPEN' $oraniId
$gamma = New-Request $requester.id 'B+' "$filterPrefix Gamma" 'routine' $future 'OPEN' $null
$delta = New-Request $requester.id 'AB+' "$filterPrefix Delta" 'critical' $future 'OPEN' $oraniId
$r = Feed $eligible ("?q=" + (Url ("filterset $suffix alpha")))
if ([int]$r.body.data.total_matching -eq 1 -and (Card $r $alpha).Count -eq 1) { Ok 'H13 q is case-insensitive and facility-only capable' } else { Bad 'H13 q filter' "total=$($r.body.data.total_matching)" }
$r = Feed $eligible '?municipality_code=030803000'
$municipalityRows = @($r.body.data.requests)
$municipalityOk = $municipalityRows.Count -gt 0 -and @($municipalityRows | Where-Object { $_.location.municipality_name -ne 'City of Balanga' }).Count -eq 0
if ($municipalityOk) { Ok 'H14 municipality filter excludes unlocated and other-municipality requests' } else { Bad 'H14 municipality filter' "rows=$($municipalityRows.Count)" }
$r = Feed $eligible '?blood=O%2B,A%2B'
if (@($r.body.data.requests).Count -gt 0 -and @($r.body.data.requests | Where-Object { $_.required_blood_type -notin @('O+','A+') }).Count -eq 0) { Ok 'H15 blood filter only narrows compatible results' } else { Bad 'H15 blood filter' 'unexpected blood type returned' }
$r = Feed $eligible '?urgency=urgent,critical'
if (@($r.body.data.requests).Count -gt 0 -and @($r.body.data.requests | Where-Object { $_.urgency -notin @('urgent','critical') }).Count -eq 0) { Ok 'H16 urgency filter narrows results' } else { Bad 'H16 urgency filter' 'unexpected urgency returned' }
$r = Feed $eligible ("?q=" + (Url $filterPrefix) + '&municipality_code=030803000&blood=O%2B&urgency=critical')
if ([int]$r.body.data.total_matching -eq 1 -and (Card $r $alpha).Count -eq 1) { Ok 'H17 combined filters intersect without widening' } else { Bad 'H17 combined filters' "total=$($r.body.data.total_matching)" }
$r = Feed $eligible ("?q=" + (Url $filterPrefix))
$orderedIds = @($r.body.data.requests | ForEach-Object { [int]$_.id })
if (($orderedIds -join ',') -eq (@($delta,$alpha,$beta,$gamma) -join ',')) { Ok 'H18 ordering is critical, urgent, routine, then newest' } else { Bad 'H18 feed order' ($orderedIds -join ',') }

$percentId = New-Request $requester.id 'O+' "Literal % $suffix" 'routine' $future 'OPEN' $balangaId
$underscoreId = New-Request $requester.id 'O+' "Literal _ $suffix" 'routine' $future 'OPEN' $balangaId
$rPercent = Feed $eligible '?q=%25'
$rUnderscore = Feed $eligible '?q=%5F'
if ([int]$rPercent.body.data.total_matching -ge 1 -and [int]$rPercent.body.data.total_matching -lt [int]$rPercent.body.data.total_unfiltered -and (Card $rPercent $percentId).Count -eq 1) { Ok "H19 q='%' is escaped literally" } else { Bad 'H19 percent escaping' "matching=$($rPercent.body.data.total_matching) unfiltered=$($rPercent.body.data.total_unfiltered)" }
if ([int]$rUnderscore.body.data.total_matching -ge 1 -and [int]$rUnderscore.body.data.total_matching -lt [int]$rUnderscore.body.data.total_unfiltered -and (Card $rUnderscore $underscoreId).Count -eq 1) { Ok "H20 q='_' is escaped literally" } else { Bad 'H20 underscore escaping' "matching=$($rUnderscore.body.data.total_matching) unfiltered=$($rUnderscore.body.data.total_unfiltered)" }
$r = Feed $eligible ("?q=" + (Url "%' OR 1=1 --"))
if ($r.status -eq 200 -and [int]$r.body.data.total_matching -eq 0) { Ok 'H21 injection-looking q is harmless' } else { Bad 'H21 injection query' "status=$($r.status) total=$($r.body.data.total_matching)" }

$patient = New-User "PatientNeedle$suffix" 'member' 'verified' 'A+' $false $null
$patientRequest = New-Request $patient.id 'O+' "Ordinary Facility $suffix" 'routine' $future 'OPEN' $balangaId
$r = Feed $eligible ("?q=" + (Url "PatientNeedle$suffix"))
if ([int]$r.body.data.total_matching -eq 0 -and (Card $r $patientRequest).Count -eq 0) { Ok 'H22 requester or patient name is not searchable' } else { Bad 'H22 patient-name privacy' "total=$($r.body.data.total_matching)" }

$tooLong = 'x' * 81
$invalids = @(
    @{ name = 'H23 over-length q rejected'; uri = "?q=$(Url $tooLong)" },
    @{ name = 'H24 invalid municipality rejected'; uri = '?municipality_code=999999999' },
    @{ name = 'H25 invalid blood type rejected'; uri = '?blood=X' },
    @{ name = 'H26 invalid urgency rejected'; uri = '?urgency=emergency' }
)
foreach ($case in $invalids) {
    $r = Feed $eligible $case.uri
    if ($r.status -eq 422) { Ok $case.name } else { Bad $case.name "status=$($r.status)" }
}

$cardResponse = Feed $eligible ("?q=" + (Url "State Hub $suffix"))
$card = (Card $cardResponse $stateRequest)[0]
$details = Invoke-Json $eligible.s 'Get' "/api/requests/$stateRequest/matches" $null $eligible.csrf
$detail = $details.body.data.request
$sameState = $details.status -eq 200 -and $details.body.data.viewer_mode -eq 'browser' `
    -and $detail.can_respond -eq $card.can_respond -and $detail.reason_code -eq $card.reason_code `
    -and $detail.reason_text -eq $card.reason_text -and $detail.eligible_again_at -eq $card.eligible_again_at `
    -and $detail.responded -eq $card.responded
if ($sameState) { Ok 'H27 donor request details matches Home card state without a match row' } else { Bad 'H27 details state parity' "status=$($details.status) mode=$($details.body.data.viewer_mode)" }

DbQuery "INSERT INTO matches (request_id,donor_id,generation,status) VALUES ($stateRequest,$($eligibleUser.id),1,'RESPONDED');" | Out-Null
$respondedFeed = Feed $eligible ("?q=" + (Url "State Hub $suffix"))
$respondedCard = (Card $respondedFeed $stateRequest)[0]
if ($respondedCard.responded -eq $true -and $respondedCard.match_status -eq 'RESPONDED') { Ok 'H28 card reports an existing response' } else { Bad 'H28 responded flag' "responded=$($respondedCard.responded) status=$($respondedCard.match_status)" }

$cardKeys = @($card.PSObject.Properties.Name)
$forbiddenKeys = @('requester_email','requester_blood_type','patient_name','notes','contact_details','phone','latitude','longitude')
$leaks = @($forbiddenKeys | Where-Object { $cardKeys -contains $_ })
if ($leaks.Count -eq 0) { Ok 'H29 cards retain the privacy-safe field allow-list' } else { Bad 'H29 card privacy' ($leaks -join ',') }

Write-Host ''
Write-Host "== Home safety: $($script:pass) passed, $($script:fail) failed =="
if ($script:fail -gt 0) { exit 1 }
