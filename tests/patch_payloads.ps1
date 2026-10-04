$files = Get-ChildItem "tests/*.ps1" | Where-Object { $_.Name -ne "patch_payloads.ps1" }
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # Fix INSERT statements
    $c = $c -replace 'full_name', 'first_name, last_name'
    $c = $c -replace '''\$name''', '''$name'', ''Doe'''

    # Revert my previous regex breakage where I replaced full_name keys
    # I had replaced `first_name = "Test User"; last_name = "User"` with `first_name = "Test User"; last_name = "Test"...`
    # Let's just fix the payload hashes if they are broken.
    
    # Let's run a manual regex to fix the hashes for phase3 and phase6, etc.
    # Actually, it might be easier to run `git restore tests/*.ps1` then run a simpler string replace.
    Set-Content -Path $f.FullName -Value $c -NoNewline
}
