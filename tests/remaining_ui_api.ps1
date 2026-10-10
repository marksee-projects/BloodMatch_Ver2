param([string]$BaseUrl = 'http://127.0.0.1:8001', [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe', [string]$PhpPath = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
if ($BaseUrl.TrimEnd('/') -ne 'http://127.0.0.1:8001' -or $env:TEST_DB_NAME -ne 'bloodmatch_test' -or $env:TEST_DB_PORT -ne '3306') { throw 'Use run_isolated.ps1 on bloodmatch_test:3306 and port 8001 only.' }
$passed = 0; $failed = 0; $fixtureIds = @(); $nonce = [Guid]::NewGuid().ToString('N'); $ownedEmails = @()
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
    $chapters = @(Query 'SELECT id FROM chapters ORDER BY id LIMIT 2;')
    if ($chapters.Count -lt 2) { throw 'Two existing reference chapters required.' }
    $chapter = [int]$chapters[0]; $otherChapter = [int]$chapters[1]
    $hash = & $PhpPath -r "echo password_hash('FixturePass123', PASSWORD_BCRYPT);"
    if ($LASTEXITCODE -ne 0) { throw 'Fixture password setup failed.' }
    foreach ($role in @('admin','officer','member')) {
        $email = "remaining-$nonce-$role@example.test"; $ownedEmails += $email
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
    Check ($csv.status -eq 200 -and $csvRows.Count -gt 0 -and @($csvRows | Where-Object { $_.chapter_id -ne "$chapter" }).Count -eq 0) 'officer export stays in own chapter'
    Check ((Call $officer.session 'GET' "$export&chapter_id=$otherChapter").status -eq 403) 'cross-chapter export forbidden'
    Check ((Call $officer.session 'GET' '/api/admin/users').status -eq 403) 'officer user management forbidden'
    Check ((Call $admin.session 'GET' "/api/admin/users?q=remaining-$nonce").status -eq 200) 'admin existing user list'
    foreach ($action in @('role','chapter','deactivate')) {
        $body = if ($action -eq 'role') { @{ role = 'member' } } elseif ($action -eq 'chapter') { @{ chapter_id = $otherChapter } } else { @{} }
        Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[0])/$action" $body $admin.csrf).status -eq 403) "self $action forbidden"
    }
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/role" @{ role = 'officer'; chapter_id = $chapter } $admin.csrf).status -eq 200) 'existing role API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/chapter" @{ chapter_id = $otherChapter } $admin.csrf).status -eq 200) 'existing chapter API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/deactivate" @{} $admin.csrf).status -eq 200) 'existing deactivation API'
    Check ((Call $admin.session 'POST' "/api/admin/users/$($fixtureIds[2])/reactivate" @{} $admin.csrf).status -eq 200) 'existing reactivation API'
    Check ((Call $admin.session 'GET' '/api/officer/donation-reports').status -eq 200) 'admin existing confirmation queue'
    Check ((Call $member.session 'GET' '/api/officer/donation-reports').status -eq 200) 'changed fixture role follows existing permissions'
} catch { $failed++; Write-Host "FAIL focused API checkpoint: $($_.Exception.Message)" }
finally {
    try {
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
Write-Host "== Remaining UI APIs: $passed passed, $failed failed =="
if ($failed -gt 0) { exit 1 }; exit 0
