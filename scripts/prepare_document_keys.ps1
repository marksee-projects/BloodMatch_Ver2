param([switch]$Prepare, [string]$PhpPath = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$reviewTool = Join-Path $projectRoot 'database\review_document_key_requirements.php'
$keyTool = Join-Path $projectRoot 'database\document_keys.php'
$reviewText = & $PhpPath $reviewTool
if ($LASTEXITCODE -ne 0) { throw 'Existing storage/key review stopped. Do not create a replacement key.' }
$review = $reviewText | ConvertFrom-Json
if (!$Prepare) { $reviewText; return }
if ($review.key_directory_configured -or $review.active_key_id_configured -or $review.stored_files.encrypted -gt 0) {
    throw 'Existing encryption configuration/files found. Reuse that setup; do not initialize another key here.'
}
if (!$env:LOCALAPPDATA) { throw 'LocalAppData unavailable.' }
$keyDir = Join-Path $env:LOCALAPPDATA 'BloodMatch\document-keys'
$backupDir = Join-Path $env:LOCALAPPDATA 'BloodMatch\document-key-backups'
$windowsUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
foreach ($directory in @($keyDir,$backupDir)) {
    # Check each existing ancestor to avoid creating secrets through a junction.
    $ancestor = [IO.Path]::GetFullPath($directory)
    while ($ancestor) {
        if (Test-Path -LiteralPath $ancestor) {
            if ((Get-Item -LiteralPath $ancestor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Key paths cannot contain reparse points.' }
        }
        $ancestor = Split-Path $ancestor -Parent
    }
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
    & icacls.exe $directory /inheritance:r /grant:r "${windowsUser}:(OI)(CI)F" 'SYSTEM:(OI)(CI)F' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Private directory ACL setup failed.' }
    # Refuse an existing directory with grants to any other identity.
    $allowedSids = @([Security.Principal.WindowsIdentity]::GetCurrent().User.Value,'S-1-5-18')
    foreach ($rule in (Get-Acl -LiteralPath $directory).Access) {
        if ($rule.AccessControlType -eq 'Allow' -and $rule.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value -notin $allowedSids) { throw 'Unexpected key-directory access grant; review ACLs.' }
    }
    & $PhpPath $keyTool "--directory=$directory" --review-directory | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Unsafe key or backup directory.' }
}
$existing = @(Get-ChildItem -LiteralPath $keyDir -Force -File -Filter '*.key')
if ($existing.Count -gt 1) { throw 'Multiple keys exist without active configuration; do not choose or replace one automatically.' }
if ($existing.Count -eq 1) {
    $keyId = $existing[0].BaseName
} else {
    if (@(Get-ChildItem -LiteralPath $backupDir -Force -File -Filter '*.key').Count -gt 0) { throw 'Backup keys exist; restore/review them before creating any new key.' }
    $createdText = & $PhpPath $keyTool "--directory=$keyDir" --create
    if ($LASTEXITCODE -ne 0) { throw 'Key creation failed. Preserve existing files; do not retry blindly.' }
    $keyId = ($createdText | ConvertFrom-Json).active_key_id
}
if ($keyId -notmatch '^[a-f0-9]{32}$') { throw 'Unexpected key ID; stop.' }
$sourceKey = Join-Path $keyDir ($keyId + '.key')
$backupKey = Join-Path $backupDir ($keyId + '.key')
if ((Get-Item -LiteralPath $sourceKey -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Key file cannot be a link.' }
if ((Get-Item -LiteralPath $sourceKey).Length -ne 32) { throw 'Existing key is invalid; do not replace it.' }
if (Test-Path -LiteralPath $backupKey) {
    if ((Get-Item -LiteralPath $backupKey -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Backup key cannot be a link.' }
} else {
    [IO.File]::Copy($sourceKey,$backupKey,$false)
}
$savedDirectory = [Environment]::GetEnvironmentVariable('DOCUMENT_KEY_DIRECTORY','Process')
$savedId = [Environment]::GetEnvironmentVariable('DOCUMENT_ACTIVE_KEY_ID','Process')
try {
    $env:DOCUMENT_KEY_DIRECTORY = $keyDir
    $env:DOCUMENT_ACTIVE_KEY_ID = $keyId
    & $PhpPath $keyTool "--verify-backup=$backupDir"
    if ($LASTEXITCODE -ne 0) { throw 'Key/backup verification failed; retain both files.' }
} finally {
    [Environment]::SetEnvironmentVariable('DOCUMENT_KEY_DIRECTORY',$savedDirectory,'Process')
    [Environment]::SetEnvironmentVariable('DOCUMENT_ACTIVE_KEY_ID',$savedId,'Process')
}
Write-Output 'Prepared only: .env, PHP configuration and historical uploads were not changed.'
