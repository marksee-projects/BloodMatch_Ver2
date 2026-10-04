$files = "tests/phase10.ps1", "tests/phase11.ps1", "tests/phase16_security.ps1"
foreach ($f in $files) {
    $c = Get-Content $f -Raw
    
    # Prepend TEST_DB and TEST_PORT definitions securely
    if ($c -notmatch '\$TEST_DB =') {
        $c = $c -replace 'param\(([^)]*)\)\r?\n', "param(`$1)`n`$TEST_DB = if (`$env:TEST_DB_NAME) { `$env:TEST_DB_NAME } else { 'bloodmatch_dev' }`n`$TEST_PORT = if (`$env:TEST_DB_PORT) { `$env:TEST_DB_PORT } else { '3307' }`n"
    }

    # Use string replacement to avoid regex issues
    $c = $c.Replace("TABLE_SCHEMA='bloodmatch_dev'", "TABLE_SCHEMA='`$TEST_DB'")
    $c = $c.Replace("TRIGGER_SCHEMA='bloodmatch_dev'", "TRIGGER_SCHEMA='`$TEST_DB'")
    $c = $c.Replace("dbname=bloodmatch_dev", "dbname=`$TEST_DB")
    $c = $c.Replace("port=3307", "port=`$TEST_PORT")

    Set-Content -Path $f -Value $c -NoNewline
}
