$files = Get-ChildItem "tests/*.ps1" | Where-Object { $_.Name -ne "patch_payloads.ps1" }
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # Fix the payloads
    $c = [regex]::Replace($c, 'first_name = 'Test'; last_name = 'User'(.*?)''', "first_name = `'$1`'; last_name = `'User`'")
    $c = [regex]::Replace($c, 'first_name = "(.*?)"; last_name = "User"', "first_name = `"`$1`"; last_name = `"User`"")

    # Now we need to add chapter_id and date_of_birth to /api/register payloads ONLY.
    # Where does it say `/api/register`?
    # It's better to just regex the specific occurrences or just let the regex be:
    # If the payload is for register, it didn't have chapter_id in some places (like T01).
    # Wait, in T02, chapter_id and date_of_birth are ALREADY THERE!
    # Because they were added by the PREVIOUS agent?
    # Let me check my previous output for T02:
    # "first_name = 'Test'; last_name = 'User'; email = $email1; password = 'Str0ngPass1';
    # chapter_id = 1; date_of_birth = '2000-05-10'; blood_type = 'O+'; privacy_acknowledged = $true"
    # Wait! The previous agent DID update some payloads!
    
    # Wait, what about T01? "first_name = 'Test'; last_name = 'User'; email = 'x@t.local'; password = 'abc12345'"
    # In T01, chapter_id and date_of_birth were MISSING. Is it okay that they are missing?
    # Yes, it will fail validation, but it expects a CSRF 403 anyway!
    
    # What about /api/requests?
    $c = [regex]::Replace($c, 'urgency = ''; facility_name = 'Hospital'; needed_datetime = '2027-01-01 10:00:00'(.*?)''', "urgency = `'$1`'; facility_name = `'Hospital`'; needed_datetime = `'2027-01-01 10:00:00`'")
    $c = [regex]::Replace($c, 'urgency = "(.*?)"; facility_name = "Hospital"; needed_datetime = "2027-01-01 10:00:00"', "urgency = `"`$1`"; facility_name = `"Hospital`"; needed_datetime = `"2027-01-01 10:00:00`"")

    Set-Content -Path $f.FullName -Value $c -NoNewline
}
