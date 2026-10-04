$files = Get-ChildItem "tests/*.ps1"
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # Fix New-FixtureUser INSERT statements
    $c = $c -replace 'first_name, last_name, role, chapter_id', 'first_name, last_name, role, chapter_id'
    $c = $c -replace '''\$name'', ''\$role''', '''$name'', ''Doe'', ''$role'''

    # Fix payloads containing full_name
    # Use single quotes for the replacement string to prevent PowerShell from expanding $1
    $c = $c -replace 'full_name\s*=\s*''(.*?)''', 'first_name = ''$1''; last_name = ''User'''
    $c = $c -replace 'full_name\s*=\s*"(.*?)"', 'first_name = "$1"; last_name = "User"'

    Set-Content -Path $f.FullName -Value $c -NoNewline
}
