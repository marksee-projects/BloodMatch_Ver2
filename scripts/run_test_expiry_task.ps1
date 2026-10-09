param(
    [Parameter(Mandatory = $true)][string]$PhpPath,
    [switch]$Scheduled
)
$ErrorActionPreference = 'Stop'
# No raw PHP output/error is persisted by the wrapper. The PHP runner owns sanitized logging.
try {
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP missing' }
    $runner = Join-Path (Split-Path $PSScriptRoot -Parent) 'database\run_test_expiry.php'
    $phpArgs = @('-d', 'display_errors=0', '-d', 'log_errors=0', $runner)
    if ($Scheduled) { $phpArgs += '--scheduled' }
    & $PhpPath @phpArgs *> $null
    exit $LASTEXITCODE
} catch {
    # Scheduler LastTaskResult=14 signals wrapper/runtime setup failure, without secret text.
    exit 14
}
