$files = Get-ChildItem "tests/*.ps1"
foreach ($f in $files) {
    $c = Get-Content $f.FullName -Raw

    # Look for `$port = $env:TEST_DB_PORT; if (!$port) { $port = '3307' }; $db = $env:TEST_DB_NAME; if (!$db) { $db = 'bloodmatch_dev' }; (& $MysqlPath -h 127.0.0.1 -P $port -u root -N -B $db -e $sql)`
    # And replace it with a dynamically-read variable.
    
    # We will replace the entire DbQuery function body or just the call.
    # The call might be slightly different in each file. Let's just regex `-P 3307 -u root -N -B bloodmatch_dev`
    
    # Let's replace the inside of the function. 
    $replacement = "`$port = `$env:TEST_DB_PORT; if (!`$port) { `$port = '3307' }; `$db = `$env:TEST_DB_NAME; if (!`$db) { `$db = 'bloodmatch_dev' }; (& `$MysqlPath -h 127.0.0.1 -P `$port -u root -N -B `$db -e `$sql)"
    
    $c = [regex]::Replace($c, '\(& \$MysqlPath -h 127\.0\.0\.1 -P 3307 -u root -N -B bloodmatch_dev -e \$sql\)', $replacement)
    
    # Wait, some might just be `& $MysqlPath` without parentheses?
    $replacement2 = "`$port = `$env:TEST_DB_PORT; if (!`$port) { `$port = '3307' }; `$db = `$env:TEST_DB_NAME; if (!`$db) { `$db = 'bloodmatch_dev' }; & `$MysqlPath -h 127.0.0.1 -P `$port -u root -N -B `$db -e `$sql"
    
    $c = [regex]::Replace($c, '&\s+\$MysqlPath -h 127\.0\.0\.1 -P 3307 -u root -N -B bloodmatch_dev -e \$sql', $replacement2)
    
    Set-Content -Path $f.FullName -Value $c -NoNewline
}
