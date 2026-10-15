param([string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
$sourceRoot=Split-Path -Parent $PSScriptRoot
if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw "PHP not found: $PhpPath" }
foreach ($tool in @('node','npm.cmd')) {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { throw "Required command missing: $tool" }
}
Push-Location $sourceRoot
try {
    # Syntax checking parses source only. No application bootstrap or SQL runs.
    foreach ($folder in @('backend/src','database','tests')) {
        foreach ($file in (Get-ChildItem -LiteralPath (Join-Path $sourceRoot $folder) -Recurse -Filter '*.php' -File)) {
            & $PhpPath -l $file.FullName
            if ($LASTEXITCODE -ne 0) { throw "PHP syntax failed: $($file.FullName)" }
        }
    }
    foreach ($script in @('check_chat_privacy_source.ps1','chat_api.ps1','run_isolated.ps1')) {
        $parseTokens=$null
        $parseErrors=$null
        [System.Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot $script),[ref]$parseTokens,[ref]$parseErrors) | Out-Null
        if ($parseErrors.Count -gt 0) { throw "PowerShell syntax failed in ${script}: $($parseErrors.Message -join '; ')" }
    }
    # Explicit allowlist: pure policy checks or in-memory PDO substitutes.
    foreach ($test in @('chat_offline.php','chat_consent_expiry_offline.php','demo_cleanup_policy_offline.php','pending_registration_offline.php','session_identity_offline.php','request_lifecycle_offline.php','registration_location_offline.php')) {
        & $PhpPath (Join-Path $PSScriptRoot $test)
        if ($LASTEXITCODE -ne 0) { throw "Offline check failed: $test" }
    }
    foreach ($test in @('request_ui_static.mjs','home_ui.mjs','management_ui_offline.mjs','notification_feedback_offline.mjs','registration_notification_offline.mjs')) {
        & node (Join-Path $PSScriptRoot $test)
        if ($LASTEXITCODE -ne 0) { throw "Frontend offline check failed: $test" }
    }
    Push-Location (Join-Path $sourceRoot 'frontend')
    try {
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    } finally { Pop-Location }
    Write-Host 'Source checks passed. Live APIs, migrations, browser behavior and retention are NOT verified by this script.'
} finally { Pop-Location }
