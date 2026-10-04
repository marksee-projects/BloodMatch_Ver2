$files = Get-ChildItem "tests/*.ps1" | Where-Object { $_.Name -ne "patch_payloads.ps1" -and $_.Name -ne "patch_payloads2.ps1" -and $_.Name -ne "patch_payloads3.ps1" }
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # Fix New-FixtureUser INSERT statements
    $c = $c -replace 'first_name, last_name, role, chapter_id', 'first_name, last_name, role, chapter_id'
    # Use PowerShell's -replace operator which handles $1 correctly in single quotes
    $c = $c -replace '''\$name'', ''\$role''', '''$name'', ''Doe'', ''$role'''

    # Fix payloads containing full_name
    # Use -replace which takes a regex and replacement string!
    # In PowerShell -replace, $1 works correctly in SINGLE QUOTED STRINGS.
    $c = $c -replace 'full_name\s*=\s*''(.*?)''', 'first_name = ''$1''; last_name = ''User'''
    $c = $c -replace 'full_name\s*=\s*"(.*?)"', 'first_name = "$1"; last_name = "User"'

    # Fix Requests payload
    $c = $c -replace 'urgency\s*=\s*''(.*?)''', 'urgency = ''; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'$1''; facility_name = ''Hospital''; needed_datetime = ''2027-01-01 10:00:00'''
    $c = $c -replace 'urgency\s*=\s*"(.*?)"', 'urgency = "$1"; facility_name = "Hospital"; needed_datetime = "2027-01-01 10:00:00"; facility_name = "Hospital"; needed_datetime = "2027-01-01 10:00:00"'

    Set-Content -Path $f.FullName -Value $c -NoNewline
}
