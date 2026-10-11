param([string]$BaseUrl = 'http://127.0.0.1:8001', [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe', [string]$PhpPath = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
if ($BaseUrl.TrimEnd('/') -ne 'http://127.0.0.1:8001' -or $env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:TEST_DB_PORT -ne '3306') { throw 'Use run_isolated.ps1 on bloodmatch_test:3306 and port 8001 only.' }
$passed = 0; $failed = 0; $fixtureIds = @(); $nonce = [Guid]::NewGuid().ToString('N'); $ownedEmails = @(); $ownedRequestId = $null; $attemptSnapshot = $null
function Query($sql) {
    $result = & $MysqlPath -h 127.0.0.1 -P 3306 -u root -N -B bloodmatch_test -e $sql
    if ($LASTEXITCODE -ne 0) { throw 'Fixed-target SQL failed.' }
    return $result
}
function Check($valid, $name) { if ($valid) { $script:passed++; Write-Host "PASS $name" } else { throw "FAIL $name" } }
function Call($session, $method, $path, $body = $null, $csrf = $null) {
    $requestArgs = @{ Uri = "$BaseUrl$path"; Method = $method; WebSession = $session; UseBasicParsing = $true; TimeoutSec = 15 }
    if ($csrf) { $requestArgs.Headers = @{ 'X-CSRF-Token' = $csrf } }
    if ($null -ne $body) { $requestArgs.ContentType = 'application/json'; $requestArgs.Body = $body | ConvertTo-Json }
    try {
        $response = Invoke-WebRequest @requestArgs
        $text = if ($response.Content -is [byte[]]) { [Text.Encoding]::UTF8.GetString($response.Content) } else { [string]$response.Content }
        return @{ status = [int]$response.StatusCode; raw = $text; content_type = [string]$response.Headers['Content-Type'] }
    }
    catch {
        if ($null -eq $_.Exception.Response) { throw }
        return @{ status = [int]$_.Exception.Response.StatusCode; raw = ''; content_type = [string]$_.Exception.Response.Headers['Content-Type'] }
    }
}
function Login($email) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf = (Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -WebSession $session).data.csrf_token
    $result = Call $session 'POST' '/api/login' @{ email = $email; password = 'FixturePass123' } $csrf
    Check ($result.status -eq 200) 'fixture login'
    return @{ session = $session; csrf = $csrf }
}
try {
    Check (("$(Query 'SELECT DATABASE(),@@port;')").Trim() -eq "bloodmatch_test`t3306") 'fixed test target'
    Check ([int](Query "SELECT COUNT(*) FROM schema_migrations WHERE name='024_consolidate_staff_role.sql';") -eq 1) '024 recorded on the test target'
    $attemptSnapshot = "$(Query 'SELECT COUNT(*),COALESCE(MAX(id),0) FROM email_attempts;')"
    $chapters = @(Query 'SELECT id FROM chapters ORDER BY id LIMIT 2;')
    if ($chapters.Count -lt 2) { throw 'Two existing reference chapters required.' }
    $chapter = [int]$chapters[0]; $otherChapter = [int]$chapters[1]
    $hash = & $PhpPath -r "echo password_hash('FixturePass123', PASSWORD_BCRYPT);"
    if ($LASTEXITCODE -ne 0) { throw 'Fixture password setup failed.' }
    for ($fixtureIndex=0; $fixtureIndex -lt 3; $fixtureIndex++) {
        $role = @('admin','admin','member')[$fixtureIndex]
        $email = "remaining-$nonce-$fixtureIndex-$role@example.test"; $ownedEmails += $email
        Query "INSERT INTO users(email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,email_verified_at) VALUES ('$email','$hash','Remaining','Fixture','$role',$chapter,'verified','active','1990-01-02',UTC_TIMESTAMP());" | Out-Null
        $fixtureIds += [int](Query "SELECT id FROM users WHERE email='$email';")
    }
    $admin = Login $ownedEmails[0]; $officer = Login $ownedEmails[1]; $member = Login $ownedEmails[2]
    $export = '/api/analytics/export?date_from=2026-01-01&date_to=2026-12-31'
    $csv = Call $admin.session 'GET' $export
    Check ($csv.status -eq 200 -and $csv.content_type -like 'text/csv*' -and $csv.raw.StartsWith('section,metric,value,date_from,date_to,chapter_id')) "admin aggregate CSV (HTTP $($csv.status); content-type $($csv.content_type))"
    Check (-not ($ownedEmails | Where-Object { $csv.raw.Contains($_) })) 'CSV excludes fixture contact fields'
    Check ((Call $member.session 'GET' $export).status -eq 403) 'member export forbidden'
    $csv = Call $officer.session 'GET' $export
    $csvRows = @($csv.raw | ConvertFrom-Csv)
    Check ($csv.status -eq 200 -and $csvRows.Count -gt 0 -and @($csvRows | Where-Object { $_.chapter_id -ne 'all' }).Count -eq 0) 'combined staff export is global'
    Check ((Call $officer.session 'GET' "$export&chapter_id=$otherChapter").status -eq 200) 'staff may select any chapter export'
    Check ((Call $officer.session 'GET' '/api/admin/users').status -eq 200) 'combined staff user management permitted'
    Check ((Call $admin.session 'GET' "/api/admin/users?q=remaining-$nonce").status -eq 200) 'admin existing user list'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/role" @{role='officer'} $admin.csrf).status -eq 400) 'obsolete officer role cannot be assigned'
    $members = (Call $admin.session 'GET' "/api/admin/users?role_group=member&q=remaining-$nonce").raw | ConvertFrom-Json
    Check (@($members.data.users).Count -eq 1 -and $members.data.users[0].role -eq 'member') 'member filter keeps membership separate from staff'
    $staff = (Call $admin.session 'GET' "/api/admin/users?role_group=staff&q=remaining-$nonce").raw | ConvertFrom-Json
    Check (@($staff.data.users).Count -eq 2 -and @($staff.data.users | Where-Object {$_.role -ne 'admin'}).Count -eq 0) 'staff filter returns canonical staff only'
    Query "UPDATE users SET chapter_id=$otherChapter WHERE id=$($fixtureIds[2]) AND email='$($ownedEmails[2])';" | Out-Null
    $location = @(Query "SELECT id,municipality_name FROM bataan_locations WHERE level='municipality' AND is_active=1 ORDER BY id LIMIT 1;")
    if ($location.Count -ne 1) { throw 'Municipality reference required.' }
    $locationParts = $location[0] -split "`t"
    Query "UPDATE users SET location_id=$($locationParts[0]) WHERE id=$($fixtureIds[2]) AND email='$($ownedEmails[2])';" | Out-Null
    $profile = (Call $officer.session 'GET' "/api/profile/$($fixtureIds[2])").raw | ConvertFrom-Json
    Check ($profile.data.profile.municipality_name -eq $locationParts[1]) 'global staff see approved municipality across chapters'
    Check (-not ($profile.data.profile.PSObject.Properties.Name | Where-Object {$_ -in @('location','location_id','barangay','barangay_name','barangay_code','latitude','longitude','documents','date_of_birth','phone')})) 'other profile omits precise location and private fields'
    Check ((Call $member.session 'GET' '/api/admin/documents/0/file').status -eq 403) 'member cannot use staff document route'
    Check ((Call $officer.session 'GET' '/api/admin/documents/0/file').status -eq 404) 'staff missing-document response reveals no file'
    Check ((Call $officer.session 'GET' '/api/officer/users').status -eq 200) 'legacy URL delegates to canonical global management'
    Check ((Call $officer.session 'GET' '/api/officer/audit-logs').status -eq 200) 'legacy audit URL delegates to global staff scope'
    Check ((Call $admin.session 'POST' "/api/admin/verifications/$($fixtureIds[0])/decision" @{decision='rejected';reason='Synthetic self-review check'} $admin.csrf).status -eq 403) 'self membership review remains forbidden'
    Check ((Call $officer.session 'POST' '/api/donation-reports' @{match_id=0} $officer.csrf).status -eq 403) 'staff cannot submit donor reports'
    Check ((Call $officer.session 'POST' '/api/matches/0/withdraw' @{} $officer.csrf).status -eq 403) 'staff cannot withdraw donor offers'
    Check ((Call $member.session 'GET' '/api/admin/donation-reports').status -eq 403) 'member cannot use staff confirmation queue'
    Query "UPDATE users SET account_status='deactivated' WHERE id=$($fixtureIds[1]) AND email='$($ownedEmails[1])';" | Out-Null
    Check ((Call $officer.session 'GET' '/api/admin/users').status -eq 403) 'inactive staff privileges remain blocked'
    Query "UPDATE users SET account_status='active' WHERE id=$($fixtureIds[1]) AND email='$($ownedEmails[1])';" | Out-Null
    foreach ($action in @('role','chapter','deactivate')) {
        $body = if ($action -eq 'role') { @{ role = 'member' } } elseif ($action -eq 'chapter') { @{ chapter_id = $otherChapter } } else { @{} }
        Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[0])/$action" $body $admin.csrf).status -eq 403) "self $action forbidden"
    }
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/role" @{ role = 'admin'; chapter_id = $chapter } $admin.csrf).status -eq 200) 'canonical staff role API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/chapter" @{ chapter_id = $otherChapter } $admin.csrf).status -eq 200) 'existing chapter API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/deactivate" @{} $admin.csrf).status -eq 200) 'existing deactivation API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/reactivate" @{} $admin.csrf).status -eq 200) 'existing reactivation API'
    Check ((Call $admin.session 'GET' '/api/officer/donation-reports').status -eq 200) 'admin existing confirmation queue'
    Check ((Call $member.session 'GET' '/api/officer/donation-reports').status -eq 200) 'changed fixture role follows existing permissions'
    Query "INSERT INTO blood_requests(requester_id,request_chapter_id,required_blood_type,facility_name,urgency,needed_datetime) VALUES ($($fixtureIds[1]),$otherChapter,'A+','Staff regression $nonce','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY));" | Out-Null
    $ownedRequestId = [int](Query "SELECT id FROM blood_requests WHERE requester_id=$($fixtureIds[1]) AND facility_name='Staff regression $nonce';")
    Query "INSERT INTO matches(request_id,donor_id,status) VALUES ($ownedRequestId,$($fixtureIds[0]),'RESPONDED');" | Out-Null
    $ownedMatchId = [int](Query "SELECT id FROM matches WHERE request_id=$ownedRequestId AND donor_id=$($fixtureIds[0]);")
    Query "INSERT INTO donation_reports(match_id,donor_id,reported_at) VALUES ($ownedMatchId,$($fixtureIds[0]),UTC_TIMESTAMP());" | Out-Null
    $ownedReportId = [int](Query "SELECT id FROM donation_reports WHERE match_id=$ownedMatchId AND donor_id=$($fixtureIds[0]);")
    Check ((Call $admin.session 'POST' "/api/admin/donation-reports/$ownedReportId/confirm" @{} $admin.csrf).status -eq 403) 'self donation confirmation remains forbidden'
    Check ((Call $admin.session 'POST' "/api/admin/donation-reports/$ownedReportId/reject" @{rejection_reason='Synthetic self-review'} $admin.csrf).status -eq 403) 'self donation rejection remains forbidden'
    Check ((Call $officer.session 'POST' "/api/admin/donation-reports/$ownedReportId/reject" @{rejection_reason='Synthetic invalid legacy staff offer'} $officer.csrf).status -eq 200) 'staff can reject an invalid report across chapters with a reason'
    Check ([int](Query "SELECT COUNT(*) FROM donation_reports WHERE id=$ownedReportId AND status='REJECTED' AND rejection_reason='Synthetic invalid legacy staff offer';") -eq 1) 'reasoned rejection persists on the disposable report'
    Check (("$(Query 'SELECT COUNT(*),COALESCE(MAX(id),0) FROM email_attempts;')") -eq $attemptSnapshot) 'API regression consumes no SMTP attempts'
} catch { $failed++; Write-Host "FAIL combined staff API checkpoint: $($_.Exception.Message)" }
finally {
    try {
        if ($ownedRequestId) {
            $owned = [int](Query "SELECT COUNT(*) FROM blood_requests WHERE id=$ownedRequestId AND requester_id=$($fixtureIds[1]) AND facility_name='Staff regression $nonce';")
            $otherDonors = [int](Query "SELECT COUNT(*) FROM matches WHERE request_id=$ownedRequestId AND donor_id NOT IN ($($fixtureIds -join ','));")
            if ($owned -ne 1 -or $otherDonors -ne 0) { throw 'Request ownership changed; retain fixtures.' }
            Query "DELETE FROM blood_requests WHERE id=$ownedRequestId AND requester_id=$($fixtureIds[1]) AND facility_name='Staff regression $nonce';" | Out-Null
        }
        for ($index = 0; $index -lt $fixtureIds.Count; $index++) {
            $id = [int]$fixtureIds[$index]; $email = $ownedEmails[$index]
            $owned = [int](Query "SELECT COUNT(*) FROM users WHERE id=$id AND email='$email';")
            if ($owned -ne 1) { throw 'Fixture identity changed; stop cleanup.' }
            $participation = [int](Query "SELECT (SELECT COUNT(*) FROM blood_requests WHERE requester_id=$id)+(SELECT COUNT(*) FROM matches WHERE donor_id=$id)+(SELECT COUNT(*) FROM member_documents WHERE user_id=$id); ")
            if ($participation -ne 0) { throw 'Unexpected fixture participation; stop cleanup.' }
            Query "DELETE FROM users WHERE id=$id AND email='$email';" | Out-Null
        }
        Write-Host 'Exact disposable users removed; audit and email attempt history retained.'
    } catch { $failed++; Write-Host "Cleanup incomplete: $($_.Exception.Message)" }
}
Write-Host "== Combined staff APIs: $passed passed, $failed failed =="
if ($failed -gt 0) { exit 1 }; exit 0
