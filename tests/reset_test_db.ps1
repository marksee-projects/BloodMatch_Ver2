# tests/reset_test_db.ps1
# Drops and recreates the TEST database (bloodmatch_test) and rebuilds it from
# database/migrations and database/seeds.
# - It only ever drops bloodmatch_test; the name is hard-coded.
# - It refuses to run if your .env DB_NAME is bloodmatch_test.
# - It prints your dev database counts before and after, to prove it was untouched.
#
# Usage (from the repo root, with MySQL running):
#   powershell -ExecutionPolicy Bypass -File tests\reset_test_db.ps1

param(
    [string]$PhpPath = '',
    [string]$MysqlPath = '',
    [string]$DbHost = '127.0.0.1',
    [string]$DbPort = '3306',
    [string]$Yes
)

$target = 'bloodmatch_test'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Find-Exe([string]$given, [string[]]$candidates) {
    if ($given -and (Test-Path $given)) { return $given }
    foreach ($c in $candidates) { if (Test-Path $c) { return $c } }
    return $null
}

$PhpPath = Find-Exe $PhpPath @('C:\xampp\php\php.exe', 'D:\xampp\php\php.exe')
$MysqlPath = Find-Exe $MysqlPath @('C:\xampp\mysql\bin\mysql.exe', 'D:\xampp\mysql\bin\mysql.exe')
if (-not $PhpPath) { throw 'php.exe not found. Pass -PhpPath.' }
if (-not $MysqlPath) { throw 'mysql.exe not found. Pass -MysqlPath.' }

$devName = $null
if (Test-Path .env) {
    foreach ($line in Get-Content .env) {
        if ($line -match '^DB_NAME=(.*)$') { $devName = $matches[1].Trim() }
    }
}
if (-not $devName) { throw 'Could not read DB_NAME from .env.' }
if ($devName -eq $target) { throw "Your .env DB_NAME is already $target. Point .env at your dev database first." }

function Invoke-Sql([string]$db, [string]$query) {
    $mysqlArgs = @('-h', $DbHost, '-P', $DbPort, '-u', $DbUser, '-N', '-B')
    if ($db) { $mysqlArgs += $db }
    $mysqlArgs += @('-e', $query)
    $out = & $MysqlPath @mysqlArgs 2>&1
    return ("$out").Trim()
}

$countQuery = "SELECT CONCAT((SELECT COUNT(*) FROM users),' users, ',(SELECT COUNT(*) FROM blood_requests),' requests, ',(SELECT COUNT(*) FROM matches),' matches, ',(SELECT COUNT(*) FROM notifications),' notifications');"

$devBefore = Invoke-Sql $devName $countQuery
$testBefore = Invoke-Sql $target $countQuery
Write-Host "Dev database  ($devName): $devBefore" -ForegroundColor Cyan
Write-Host "Test database ($target) BEFORE: $testBefore" -ForegroundColor Cyan

$confirm = if ($Yes) { 'RESET' } else { Read-Host "Type RESET to drop and rebuild $target (the dev database will not be touched)" } if ($confirm -ne 'RESET') {
    Write-Host 'Cancelled. Nothing was changed.'
    return
}

Invoke-Sql '' "DROP DATABASE IF EXISTS $target; CREATE DATABASE $target CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null

$names = 'DB_HOST', 'DB_PORT', 'DB_NAME'
$saved = @{}
foreach ($n in $names) { $saved[$n] = [Environment]::GetEnvironmentVariable($n, 'Process') }

try {
    $env:DB_HOST = $DbHost
    $env:DB_PORT = $DbPort
    $env:DB_NAME = $target

    Write-Host 'Running migrations...' -ForegroundColor Cyan
    & $PhpPath -d variables_order=EGPCS database\run_migrations.php
    Write-Host 'Running seeds...' -ForegroundColor Cyan
    & $PhpPath -d variables_order=EGPCS database\run_seeds.php
}
finally {
    foreach ($n in $names) { [Environment]::SetEnvironmentVariable($n, $saved[$n], 'Process') }
}

$testAfter = Invoke-Sql $target $countQuery
$devAfter = Invoke-Sql $devName $countQuery
Write-Host "Test database ($target) AFTER:  $testAfter" -ForegroundColor Cyan
Write-Host "Dev database  ($devName) AFTER: $devAfter" -ForegroundColor Cyan

if ($devAfter -ne $devBefore) {
    Write-Host 'WARNING: dev database counts changed. Stop and check before doing anything else.' -ForegroundColor Red
}
else {
    Write-Host 'Dev database unchanged. Test database rebuilt.' -ForegroundColor Green
}