$files = Get-ChildItem "tests/*.ps1" | Where-Object { $_.Name -ne "patch_payloads.ps1" -and $_.Name -ne "patch_payloads2.ps1" -and $_.Name -ne "patch_payloads3.ps1" }
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # 1. Fix New-FixtureUser INSERT statements
    $c = $c -replace 'first_name, last_name, role, chapter_id', 'first_name, last_name, role, chapter_id'
    $c = $c -replace '''\$name'', ''\$role''', '''$name'', ''Doe'', ''$role'''

    # 2. Fix payloads containing full_name
    $c = [regex]::Replace($c, 'full_name\s*=\s*''(.*?)''', "first_name = `'$1`'; last_name = `'User`'")
    $c = [regex]::Replace($c, 'full_name\s*=\s*"(.*?)"', "first_name = `"`$1`"; last_name = `"User`"")

    # 3. Add chapter_id and date_of_birth to register payloads
    # I don't need to add them if they already exist, but for T01 it doesn't matter (403 CSRF).
    # Some payloads in phase3 ALREADY have chapter_id because of the previous agent!
    # Wait, in the unpatched phase3, T02 HAS `chapter_id = 1; date_of_birth = '2000-05-10'`.
    # That means the previous agent DID update the payloads manually but failed or missed some!

    # 4. Fix Requests payload
    $c = [regex]::Replace($c, 'urgency\s*=\s*''(.*?)''', "urgency = `'$1`'; facility_name = `'Hospital`'; needed_datetime = `'2027-01-01 10:00:00`'")
    $c = [regex]::Replace($c, 'urgency\s*=\s*"(.*?)"', "urgency = `"`$1`"; facility_name = `"Hospital`"; needed_datetime = `"2027-01-01 10:00:00`"")

    Set-Content -Path $f.FullName -Value $c -NoNewline
}
