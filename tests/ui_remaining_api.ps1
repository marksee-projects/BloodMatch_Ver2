param([string]$BaseUrl='http://127.0.0.1:8001',[string]$MysqlPath='C:\xampp\mysql\bin\mysql.exe',[string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
if ($BaseUrl.TrimEnd('/') -ne 'http://127.0.0.1:8001' -or $env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:TEST_DB_PORT -ne '3306') { throw 'Use run_isolated.ps1 on bloodmatch_test:3306 and port 8001 only.' }
$nonce=[Guid]::NewGuid().ToString('N'); $ids=@(); $emails=@(); $requestId=$null; $passed=0; $failed=0
function Query($sql) { $output=& $MysqlPath --no-defaults -h 127.0.0.1 -P 3306 -u root -N -B bloodmatch_test -e $sql; if($LASTEXITCODE -ne 0){throw 'Fixed-target SQL failed.'}; return $output }
function Check($ok,$label) { if(-not $ok){throw "FAIL $label"}; $script:passed++; Write-Host "PASS $label" }
function Call($login,$path) {
    try { $response=Invoke-WebRequest -Uri "$BaseUrl$path" -WebSession $login.session -UseBasicParsing -TimeoutSec 15
        $raw=if($response.Content -is [byte[]]){[Text.Encoding]::UTF8.GetString($response.Content)}else{[string]$response.Content}
        return @{status=[int]$response.StatusCode;raw=$raw;type=[string]$response.Headers['Content-Type']}
    } catch { if(-not $_.Exception.Response){throw}; return @{status=[int]$_.Exception.Response.StatusCode;raw=''} }
}
function Login($email) {
    $session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $csrf=(Invoke-RestMethod -Uri "$BaseUrl/api/csrf" -WebSession $session).data.csrf_token
    Invoke-RestMethod -Uri "$BaseUrl/api/login" -Method Post -WebSession $session -Headers @{'X-CSRF-Token'=$csrf} -ContentType 'application/json' -Body (@{email=$email;password='FixturePass123'}|ConvertTo-Json) | Out-Null
    return @{session=$session}
}
try {
    Check (("$(Query 'SELECT DATABASE(),@@port;')").Trim() -eq "bloodmatch_test`t3306") 'fixed test target'
    Check (("$(Query "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bloodmatch_test' AND TABLE_NAME='users' AND COLUMN_NAME='role';")").Trim() -eq "enum('member','admin')") 'canonical role schema'
    $attempts="$(Query 'SELECT COUNT(*),COALESCE(MAX(id),0) FROM email_attempts;')"
    $chapters=@(Query 'SELECT id FROM chapters ORDER BY id LIMIT 2;'); if($chapters.Count -lt 2){throw 'Two reference chapters required.'}
    $chapter=[int]$chapters[0]; $otherChapter=[int]$chapters[1]
    $location=("$(Query "SELECT id FROM bataan_locations WHERE level='municipality' AND is_active=1 ORDER BY id LIMIT 1;")").Trim()
    if($location -notmatch '^\d+$'){throw 'Active municipality reference required.'}
    $municipality=("$(Query "SELECT municipality_name FROM bataan_locations WHERE id=$location;")").Trim()
    $hash=& $PhpPath -r "echo password_hash('FixturePass123', PASSWORD_BCRYPT);"; if($LASTEXITCODE -ne 0){throw 'Password setup failed.'}
    foreach($role in @('admin','member')) {
        $email="remaining-ui-$nonce-$role@example.test"; $emails+=$email
        Query "INSERT INTO users(email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,date_of_birth,location_id,latitude,longitude) SELECT '$email','$hash','Remaining','UI Fixture','$role',$chapter,'verified','active','1990-01-02',id,latitude,longitude FROM bataan_locations WHERE id=$location;" | Out-Null
        $ids+=[int](Query "SELECT id FROM users WHERE email='$email';")
    }
    $staff=Login $emails[0]; $member=Login $emails[1]
    $path="/api/analytics/confirmations?date_from=2037-01-02&date_to=2037-01-03&chapter_id=$chapter"
    Check ((Call $member $path).status -eq 403) 'member summary forbidden'
    Check ((Call $member '/api/analytics/confirmations/export').status -eq 403) 'member confirmed CSV forbidden'
    $baselineResponse=Call $staff $path
    Check ($baselineResponse.status -eq 200) 'staff baseline summary permitted'
    $baseline=(($baselineResponse.raw|ConvertFrom-Json).data.total_confirmed_reports)
    $otherPath="/api/analytics/confirmations?date_from=2037-01-02&date_to=2037-01-03&chapter_id=$otherChapter"
    $otherBaseline=((Call $staff $otherPath).raw|ConvertFrom-Json).data.total_confirmed_reports
    Query "INSERT INTO blood_requests(requester_id,request_chapter_id,required_blood_type,facility_name,urgency,needed_datetime,created_at) VALUES ($($ids[0]),$chapter,'A+','Remaining UI $nonce','routine','2037-01-10 00:00:00','2020-01-01 00:00:00');" | Out-Null
    $requestId=[int](Query "SELECT id FROM blood_requests WHERE requester_id=$($ids[0]) AND facility_name='Remaining UI $nonce';")
    Query "INSERT INTO matches(request_id,donor_id,status) VALUES ($requestId,$($ids[1]),'COMPLETED');" | Out-Null
    $matchId=[int](Query "SELECT id FROM matches WHERE request_id=$requestId AND donor_id=$($ids[1]);")
    foreach($date in @('2037-01-02 00:00:00','2037-01-03 23:59:59','2037-01-04 00:00:00')) {
        Query "INSERT INTO donation_reports(match_id,donor_id,status,reported_at,confirmed_by,confirmed_at) VALUES ($matchId,$($ids[1]),'CONFIRMED','2020-01-01 00:00:00',$($ids[0]),'$date');" | Out-Null
    }
    foreach($status in @('PENDING','REJECTED')) { Query "INSERT INTO donation_reports(match_id,donor_id,status,reported_at) VALUES ($matchId,$($ids[1]),'$status','2037-01-02 00:00:00');" | Out-Null }
    $response=Call $staff $path; Check ($response.status -eq 200) 'staff confirmed summary permitted'
    $summary=($response.raw|ConvertFrom-Json).data
    Check ($summary.total_confirmed_reports -eq ([int]$baseline+2)) 'counts confirmations, includes full UTC end day, excludes next midnight and pending/rejected reports'
    Check ($summary.chapter_id -eq $chapter -and $summary.date_from -eq '2037-01-02' -and $summary.date_to -eq '2037-01-03') 'scope and dates explicit'
    Check (($summary.daily_confirmations|Measure-Object confirmed_reports -Sum).Sum -eq $summary.total_confirmed_reports) 'daily counts reconcile with total'
    $source=[int](Query "SELECT COUNT(*) FROM donation_reports dr JOIN matches m ON m.id=dr.match_id JOIN blood_requests br ON br.id=m.request_id WHERE dr.status='CONFIRMED' AND dr.confirmed_at>='2037-01-02 00:00:00' AND dr.confirmed_at<'2037-01-04 00:00:00' AND br.request_chapter_id=$chapter;")
    Check ($summary.total_confirmed_reports -eq $source) 'API counts match stored confirmations'
    Check (((((Call $staff $otherPath).raw|ConvertFrom-Json).data.total_confirmed_reports) -eq $otherBaseline)) 'other chapter excludes owned confirmations'
    $csv=Call $staff "/api/analytics/confirmations/export?date_from=2037-01-02&date_to=2037-01-03&chapter_id=$chapter"
    Check ($csv.status -eq 200 -and $csv.type -like 'text/csv*') 'confirmed summary CSV response'
    $rows=@($csv.raw|ConvertFrom-Csv)
    Check ($rows[0].confirmation_date_utc -eq 'TOTAL' -and [int]$rows[0].confirmed_reports -eq $source) 'CSV total matches summary'
    Check (-not ($emails|Where-Object {$response.raw.Contains($_) -or $csv.raw.Contains($_)})) 'aggregate response excludes fixture contacts'
    Check ((Call $staff '/api/analytics/confirmations?date_from=2037-02-30&date_to=2037-03-01').status -eq 400) 'invalid calendar date refused'
    Check ((Call $staff '/api/analytics/confirmations?date_from=2037-01-04&date_to=2037-01-03').status -eq 400) 'reversed interval refused'
    $own=((Call $member '/api/profile').raw|ConvertFrom-Json).data.profile
    Check ($own.location.municipality_name -eq $municipality) 'saved municipality reaches own profile API'
    $other=((Call $staff "/api/profile/$($ids[1])").raw|ConvertFrom-Json).data.profile
    Check ($other.municipality_name -eq $municipality) 'authorized other-profile municipality display value'
    Check (-not ($other.PSObject.Properties.Name|Where-Object {$_ -in @('location','location_id','barangay_name','barangay_code','latitude','longitude','documents','phone','date_of_birth')})) 'other profile excludes precise/private fields'
    Check (("$(Query 'SELECT COUNT(*),COALESCE(MAX(id),0) FROM email_attempts;')") -eq $attempts) 'no SMTP attempts consumed'
} catch { $failed++; Write-Host "FAIL remaining UI API checkpoint: $($_.Exception.Message)" }
finally {
    try {
        if($requestId) {
            if([int](Query "SELECT COUNT(*) FROM blood_requests WHERE id=$requestId AND requester_id=$($ids[0]) AND facility_name='Remaining UI $nonce';") -ne 1){throw 'Request ownership changed; retain fixtures.'}
            if([int](Query "SELECT COUNT(*) FROM matches WHERE request_id=$requestId AND donor_id<>$($ids[1]);") -ne 0){throw 'Unexpected donor; retain fixtures.'}
            Query "DELETE FROM blood_requests WHERE id=$requestId AND requester_id=$($ids[0]) AND facility_name='Remaining UI $nonce';" | Out-Null
        }
        for($index=0;$index -lt $ids.Count;$index++) {
            $id=$ids[$index]; $email=$emails[$index]
            if([int](Query "SELECT COUNT(*) FROM users WHERE id=$id AND email='$email';") -ne 1){throw 'User ownership changed; retain fixtures.'}
            if([int](Query "SELECT (SELECT COUNT(*) FROM blood_requests WHERE requester_id=$id)+(SELECT COUNT(*) FROM matches WHERE donor_id=$id)+(SELECT COUNT(*) FROM member_documents WHERE user_id=$id);") -ne 0){throw 'Unexpected participation; retain fixtures.'}
            Query "DELETE FROM users WHERE id=$id AND email='$email';" | Out-Null
        }
        Write-Host 'Exact remaining-UI fixtures removed; audit and email attempt history retained.'
    } catch { $failed++; Write-Host "Cleanup incomplete: $($_.Exception.Message)" }
}
Write-Host "== Remaining checkpoints APIs: $passed passed, $failed failed =="
if($failed -gt 0){exit 1}; exit 0
