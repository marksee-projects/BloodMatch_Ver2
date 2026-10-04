$files = "tests/phase11.ps1", "tests/phase16_security.ps1"
foreach ($f in $files) {
    $c = Get-Content $f -Raw
    
    # Inject variables at the top of the file, after param block
    if ($c -notmatch "`$TEST_DB = ") {
        $c = $c -replace '\)\r?\n', ")`n`$TEST_DB = if (`$env:TEST_DB_NAME) { `$env:TEST_DB_NAME } else { 'bloodmatch_dev' }`n`$TEST_PORT = if (`$env:TEST_DB_PORT) { `$env:TEST_DB_PORT } else { '3307' }`n"
    }

    # Replace TABLE_SCHEMA='bloodmatch_dev' -> TABLE_SCHEMA='$TEST_DB'
    $c = $c -replace "TABLE_SCHEMA='bloodmatch_dev'", "TABLE_SCHEMA='`$TEST_DB'"
    
    # Replace TRIGGER_SCHEMA='bloodmatch_dev' -> TRIGGER_SCHEMA='$TEST_DB'
    $c = $c -replace "TRIGGER_SCHEMA='bloodmatch_dev'", "TRIGGER_SCHEMA='`$TEST_DB'"

    # Replace dbname=bloodmatch_dev -> dbname=$TEST_DB
    $c = $c -replace 'dbname=bloodmatch_dev', 'dbname=`$TEST_DB'
    
    # Replace port=3307 -> port=$TEST_PORT
    $c = $c -replace 'port=3307', 'port=`$TEST_PORT'

    Set-Content -Path $f -Value $c -NoNewline
}
