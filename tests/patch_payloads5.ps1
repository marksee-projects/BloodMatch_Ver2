$files = Get-ChildItem "tests/*.ps1"
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    $c = $c -replace "first_name = 'Test';", "first_name = 'Test';"
    $c = $c -replace 'first_name = "Test";', 'first_name = "Test";'
    
    Set-Content -Path $f.FullName -Value $c -NoNewline
}
