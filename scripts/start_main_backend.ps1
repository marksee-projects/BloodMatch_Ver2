param([string]$PhpPath = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
$workspace = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP executable not found.' }
$savedLocation = Get-Location
$names = @('DB_HOST','DB_PORT','DB_NAME','BLOODMATCH_TEST_ISOLATION')
$saved = @{}
foreach ($name in $names) { $saved[$name] = [Environment]::GetEnvironmentVariable($name,'Process') }
try {
    Set-Location -LiteralPath $workspace
    # A reused test terminal must never silently select the disposable database.
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3306'
    $env:DB_NAME = 'bloodmatch'
    $env:BLOODMATCH_TEST_ISOLATION = '0'
    $probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,8000)
    try { $probe.Start() } catch { throw 'Port 8000 is already in use. Stop the old backend before starting main.' }
    finally { $probe.Stop() }
    $check = @'
<?php
require 'backend/src/autoload.php';
BloodMatch\Config\Env::load('.env');
try {
    $p=BloodMatch\Config\Database::pdo();
    $r=$p->query('SELECT DATABASE() AS db,@@port AS port')->fetch();
    if($r['db']!=='bloodmatch'||(int)$r['port']!==3306)throw new RuntimeException();
    echo "Verified main database: bloodmatch:3306\n";
} catch(Throwable $e) { fwrite(STDERR,"Main database target verification failed; server not started.\n");exit(1); }
'@
    $check | & $PhpPath
    if ($LASTEXITCODE -ne 0) { throw 'Main database preflight failed.' }
    & $PhpPath -S 127.0.0.1:8000 -t backend/public
} finally {
    foreach ($name in $names) { [Environment]::SetEnvironmentVariable($name,$saved[$name],'Process') }
    Set-Location -LiteralPath $savedLocation
}
