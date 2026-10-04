$files = Get-ChildItem "tests/*.ps1"
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # The previous agent replaced full_name with `first_name = ''`, which fails validation (requires 2+ chars).
    $c = $c -replace "first_name = 'Test';", "first_name = 'Test';"
    $c = $c -replace 'first_name = "Test";', 'first_name = "Test";'
    
    # Also some tests might have `first_name = 'Test'; last_name = 'User'`? Let's fix that if it exists.
    $c = [regex]::Replace($c, 'first_name,\s*last_name\s*=\s*''(.*?)''', "first_name = `'$1`'; last_name = `'User`'")

    Set-Content -Path $f.FullName -Value $c -NoNewline
}
