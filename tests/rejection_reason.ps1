param(
    [string]$BaseUrl = 'http://127.0.0.1:8001',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

# USER-RUN ONLY through run_isolated.ps1, after reviewing/applying migration 020.
$ErrorActionPreference = 'Stop'
if ($env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:DB_NAME -ne 'bloodmatch_test') { throw 'Use run_isolated.ps1 with bloodmatch_test; no standalone production run.' }
$testPort = if ($env:TEST_DB_PORT) { $env:TEST_DB_PORT } else { '3306' }
$script:pass = 0; $script:fail = 0
$token = [guid]::NewGuid().ToString('N')
$password = 'RejectReason123!'
$hash = & $PhpPath -r "echo password_hash('RejectReason123!', PASSWORD_BCRYPT);"
$script:userIds = @(); $script:requestIds = @()
function Ok($name) { $script:pass++; Write-Host "PASS $name" }
function Bad($name, $why) { $script:fail++; Write-Host "FAIL $name -> $why" }
function DbQuery($sql) {
    $out = & $MysqlPath -h 127.0.0.1 -P $testPort -u root -N -B bloodmatch_test -e $sql
    if ($LASTEXITCODE -ne 0) { throw 'Test database query failed.' }
    return $out | Where-Object { $_ -ne '' }
}
if (("$(DbQuery 'SELECT DATABASE();')").Trim() -ne 'bloodmatch_test') { throw 'Wrong database.' }
$shape = ("$(DbQuery "SELECT CONCAT(DATA_TYPE,'|',CHARACTER_MAXIMUM_LENGTH,'|',IS_NULLABLE) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' AND COLUMN_NAME='rejection_reason';")").Trim()
if ($shape -ne 'varchar|500|YES') { throw 'Review/apply migration 020 before running rejection_reason.ps1.' }
function Invoke-Json($session, $method, $uri, $body, $csrf) {
    try {
        $args = @{ Uri = "$BaseUrl$uri"; Method = $method; WebSession = $session; TimeoutSec = 30; UseBasicParsing = $true }
        if ($null -ne $body) { $args.Body = ($body | ConvertTo-Json -Depth 7); $args.ContentType = 'application/json; charset=utf-8' }
        if ($csrf) { $args.Headers = @{ 'X-CSRF-Token' = $csrf } }
        $res = Invoke-WebRequest @args
        return @{ status = [int]$res.StatusCode; body = ($res.Content | ConvertFrom-Json) }
    } catch {
        $response = $_.Exception.Response
        if ($null -eq $response) { throw }
        $raw = (New-Object System.IO.StreamReader($response.GetResponseStream())).ReadToEnd()
        try { $parsed = $raw | ConvertFrom-Json } catch { $parsed = $null }
        return @{ status = [int]$response.StatusCode; body = $parsed }
    }
}
function New-User($label, $role = 'member', $chapter = 1) {
    $email = "reject-$token-$label@example.test"
    DbQuery "INSERT INTO users (email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at) VALUES ('$email','$hash','Reject','Fixture','$role',$chapter,'verified','active','1990-01-02','O-','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP());" | Out-Null
    $id = [int](DbQuery "SELECT id FROM users WHERE email='$email';")
    $script:userIds += $id
    return @{ id = $id; email = $email }
}
function Login($user) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -WebSession $s -UseBasicParsing).data.csrf_token
    $r = Invoke-Json $s 'Post' '/api/login' @{ email = $user.email; password = $password } $csrf
    if ($r.status -ne 200) { throw 'Could not log in token-marked test account; refusing further API mutations.' }
    return @{ s = $s; csrf = $csrf }
}
function New-Report($donorId, $requesterId, $requestStatus = 'OPEN', $reportStatus = 'PENDING', $matchStatus = 'RESPONDED') {
    $facility = "Reject-$token-$($script:requestIds.Count)"
    DbQuery "INSERT INTO blood_requests (requester_id,request_chapter_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status) VALUES ($requesterId,1,'O+',1,'$facility','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'$requestStatus','not_required');" | Out-Null
    $requestId = [int](DbQuery "SELECT id FROM blood_requests WHERE facility_name='$facility';")
    $script:requestIds += $requestId
    DbQuery "INSERT INTO matches (request_id,donor_id,generation,status) VALUES ($requestId,$donorId,1,'$matchStatus');" | Out-Null
    $matchId = [int](DbQuery "SELECT id FROM matches WHERE request_id=$requestId AND donor_id=$donorId;")
    DbQuery "INSERT INTO donation_reports (match_id,donor_id,status,reported_at) VALUES ($matchId,$donorId,'$reportStatus',UTC_TIMESTAMP());" | Out-Null
    return [int](DbQuery "SELECT id FROM donation_reports WHERE match_id=$matchId;")
}
try {
    $donor = New-User 'donor'; $requester = New-User 'requester'
    $officer = New-User 'officer' 'officer'; $otherOfficer = New-User 'other-officer' 'officer' 2
    $admin = New-User 'admin' 'admin'
    $d = Login $donor; $m = Login $requester; $o = Login $officer; $other = Login $otherOfficer; $a = Login $admin
    $report = New-Report $donor.id $requester.id
    $invalidCases = @(
        @{ label = 'missing'; body = @{} },
        @{ label = 'empty'; body = @{ rejection_reason = '' } },
        @{ label = 'whitespace'; body = @{ rejection_reason = " `r`n`t " } },
        @{ label = 'invisible'; body = @{ rejection_reason = ([char]0x00A0).ToString() + ([char]0x200B).ToString() } },
        @{ label = 'array'; body = @{ rejection_reason = @('reason') } },
        @{ label = 'number'; body = @{ rejection_reason = 42 } },
        @{ label = 'overlength'; body = @{ rejection_reason = ('a' * 501) } }
    )
    foreach ($case in $invalidCases) {
        $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$report/reject" $case.body $o.csrf
        $unchanged = [int](DbQuery "SELECT COUNT(*) FROM donation_reports WHERE id=$report AND status='PENDING' AND rejection_reason IS NULL;")
        if ($r.status -eq 400 -and $r.body.error.details.rejection_reason -and $unchanged -eq 1) { Ok "R $($case.label) reason rejected without changing report" } else { Bad "R $($case.label)" "HTTP=$($r.status) unchanged=$unchanged" }
    }
    $payload = @{ rejection_reason = "  Private reason $token.  " }
    $r = Invoke-Json $m.s 'Post' "/api/officer/donation-reports/$report/reject" $payload $m.csrf
    if ($r.status -eq 403) { Ok 'Member cannot reject' } else { Bad 'Member rejection authorization' $r.status }
    $r = Invoke-Json $other.s 'Post' "/api/officer/donation-reports/$report/reject" $payload $other.csrf
    if ($r.status -eq 403) { Ok 'Other-chapter officer cannot reject' } else { Bad 'Chapter rejection authorization' $r.status }
    $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$report/reject" $payload $o.csrf
    $stored = [int](DbQuery "SELECT COUNT(*) FROM donation_reports WHERE id=$report AND status='REJECTED' AND rejection_reason='Private reason $token.' AND confirmed_by=$($officer.id) AND confirmed_at IS NOT NULL;")
    if ($r.status -eq 200 -and $stored -eq 1) { Ok 'Authorized rejection persists trimmed reason, reviewer and time' } else { Bad 'Persist rejection' "HTTP=$($r.status) stored=$stored" }
    $r = Invoke-Json $d.s 'Get' '/api/my/donation-reports' $null $d.csrf
    $own = @($r.body.data.reports | Where-Object { $_.id -eq $report })
    if ($own.Count -eq 1 -and $own[0].rejection_reason -eq "Private reason $token.") { Ok 'Donor own history returns reason' } else { Bad 'Own reason history' $r.status }
    $r = Invoke-Json $o.s 'Get' "/api/officer/audit-logs?action=donation.rejected&target_type=donation_report&target_id=$report" $null $o.csrf
    $audit = @($r.body.data.logs | Where-Object { $_.context.rejection_reason -eq "Private reason $token." })
    if ($r.status -eq 200 -and $audit.Count -eq 1) { Ok 'Scoped audit retains rejection reason' } else { Bad 'Reason audit' "HTTP=$($r.status) count=$($audit.Count)" }
    $r = Invoke-Json $m.s 'Get' '/api/officer/audit-logs?action=donation.rejected' $null $m.csrf
    if ($r.status -eq 403) { Ok 'Member cannot read protected rejection audit' } else { Bad 'Audit role privacy' $r.status }
    $r = Invoke-Json $other.s 'Get' "/api/officer/audit-logs?action=donation.rejected&target_type=donation_report&target_id=$report" $null $other.csrf
    if ($r.status -eq 200 -and @($r.body.data.logs).Count -eq 0) { Ok 'Other-chapter officer cannot read this rejection audit' } else { Bad 'Audit chapter privacy' $r.status }
    $r = Invoke-Json $m.s 'Get' "/api/my/donation-reports?donor_id=$($donor.id)" $null $m.csrf
    if ($r.status -eq 200 -and @($r.body.data.reports | Where-Object { $_.id -eq $report }).Count -eq 0) { Ok 'History donor_id manipulation cannot reveal another donor reason' } else { Bad 'History ownership' $r.status }
    $r = Invoke-Json $m.s 'Get' "/api/profile/$($donor.id)" $null $m.csrf
    if ($r.status -eq 200 -and ($r.body | ConvertTo-Json -Depth 9) -notmatch ([regex]::Escape("Private reason $token."))) { Ok 'Other member profile excludes reason/history' } else { Bad 'Profile reason privacy' $r.status }
    $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$report/reject" $payload $o.csrf
    if ($r.status -eq 409) { Ok 'Repeat decision cannot overwrite stored reason' } else { Bad 'Repeat rejection' $r.status }
    foreach ($status in @('CANCELLED','EXPIRED','FULFILLED')) {
        $closed = New-Report $donor.id $requester.id $status 'PENDING' 'CLOSED'
        $queue = Invoke-Json $o.s 'Get' '/api/officer/donation-reports' $null $o.csrf
        $entry = @($queue.body.data.pending_reports | Where-Object { $_.id -eq $closed })
        if ($entry.Count -eq 1 -and $entry[0].can_confirm -eq $false -and $entry[0].can_reject -eq $true) { Ok "$status pending report remains reviewable but not confirmable" } else { Bad "$status queue" $queue.status }
        $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$closed/confirm" @{} $o.csrf
        if ($r.status -eq 409) { Ok "$status confirmation blocked" } else { Bad "$status confirm" $r.status }
        $r = Invoke-Json $a.s 'Post' "/api/officer/donation-reports/$closed/reject" @{ rejection_reason = "Request is $status; report cannot be confirmed." } $a.csrf
        $safe = [int](DbQuery "SELECT COUNT(*) FROM users WHERE id=$($donor.id) AND last_verified_donation_at IS NULL AND donor_availability='available';")
        if ($r.status -eq 200 -and $safe -eq 1) { Ok "$status explicit rejection starts no donation cooldown" } else { Bad "$status reject" "HTTP=$($r.status) safe=$safe" }
    }
    $legacy = New-Report $donor.id $requester.id 'OPEN' 'REJECTED'
    $r = Invoke-Json $d.s 'Get' '/api/my/donation-reports' $null $d.csrf
    $row = @($r.body.data.reports | Where-Object { $_.id -eq $legacy })
    if ($row.Count -eq 1 -and $null -eq $row[0].rejection_reason -and $row[0].status -eq 'REJECTED') { Ok 'Legacy NULL reason/status returned unchanged' } else { Bad 'Legacy history' $r.status }
    $boundary = New-Report $donor.id $requester.id
    $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$boundary/reject" @{ rejection_reason = ([char]::ConvertFromUtf32(0x1F642) * 500) } $o.csrf
    $length = [int](DbQuery "SELECT COALESCE(CHAR_LENGTH(rejection_reason),0) FROM donation_reports WHERE id=$boundary;")
    if ($r.status -eq 200 -and $length -eq 500) { Ok '500 Unicode characters accepted and persisted' } else { Bad 'Unicode boundary' "HTTP=$($r.status) length=$length" }
    $self = New-Report $officer.id $requester.id
    $r = Invoke-Json $o.s 'Post' "/api/officer/donation-reports/$self/reject" @{ rejection_reason = 'Cannot self-review.' } $o.csrf
    if ($r.status -eq 403) { Ok 'Reviewer cannot reject own report' } else { Bad 'Self rejection' $r.status }
} finally {
    if ($script:requestIds.Count) {
        $ids = $script:requestIds -join ','
        DbQuery "DELETE FROM notifications WHERE related_type='blood_request' AND related_id IN ($ids); DELETE FROM blood_requests WHERE id IN ($ids);" | Out-Null
    }
    if ($script:userIds.Count) { $ids = $script:userIds -join ','; DbQuery "DELETE FROM users WHERE id IN ($ids);" | Out-Null }
}
Write-Host "== Rejection reasons: $($script:pass) passed, $($script:fail) failed =="
if ($script:fail -gt 0) { exit 1 }
