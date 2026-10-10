param([ValidateSet('Safety','Email')][string]$Kind, [Parameter(Mandatory = $true)][string]$PhpPath)
$ErrorActionPreference = 'Stop'
try {
    $runner = Join-Path (Split-Path -Parent $PSScriptRoot) "database\run_application_$($Kind.ToLowerInvariant()).php"
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf) -or -not (Test-Path -LiteralPath $runner -PathType Leaf)) { throw 'Runner unavailable.' }
    & $PhpPath -d display_errors=0 -d log_errors=0 $runner --scheduled *> $null
    exit $LASTEXITCODE
} catch { exit 14 }
