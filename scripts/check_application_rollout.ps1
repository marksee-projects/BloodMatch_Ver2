param([Parameter(Mandatory=$true)][string]$CredentialFile)
$ErrorActionPreference='Stop'
$rolloutRoot=Split-Path -Parent $PSScriptRoot
$privateRoot=[IO.Path]::GetFullPath((Join-Path $rolloutRoot 'backend/storage/maintenance')).TrimEnd('\')+'\'
$credentialPath=(Resolve-Path -LiteralPath $CredentialFile).Path
if (-not $credentialPath.StartsWith($privateRoot,[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetFileName($credentialPath) -notmatch '^bloodmatch-\d{8}-\d{6}-[a-f0-9]+-credentials\.json$') { throw 'Expected private application credential file.' }
$manifest=Get-Content -LiteralPath $credentialPath -Raw | ConvertFrom-Json
if ($manifest.status -ne 'COMMITTED' -or @($manifest.accounts).Count -ne 15) { throw 'Expected committed application credentials.' }
$base='http://127.0.0.1:8000'
$clients=New-Object System.Collections.Generic.List[object]
$script:passed=0
function Check($ok,$label) { if (-not $ok) { throw "FAIL $label" }; $script:passed++;Write-Output "PASS $label" }
function Read($client,$path) { (Invoke-RestMethod "$base$path" -WebSession $client.session -TimeoutSec 20).data }
function Login($account) {
    $session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $token=(Invoke-RestMethod "$base/api/csrf" -WebSession $session -TimeoutSec 20).data.csrf_token
    $client=@{session=$session;csrf=$token}
    $result=Invoke-RestMethod "$base/api/login" -Method Post -WebSession $session -Headers @{'X-CSRF-Token'=$token} -ContentType 'application/json' -Body (@{email=$account.email;password=$account.password}|ConvertTo-Json) -TimeoutSec 20
    if ([int]$result.data.user.id -ne [int]$account.id) { throw 'Login identity mismatch.' }
    $clients.Add($client)
    return $client
}
try {
    $adminAccount=@($manifest.accounts | Where-Object { $_.email -eq 'markseejr@gmail.com' -and $_.role -eq 'admin' })
    if ($adminAccount.Count -ne 1) { throw 'Designated administrator missing from private manifest.' }
    $admin=Login $adminAccount[0]
    Check ($null -ne $admin) 'Designated administrator login'
    $users=Read $admin '/api/admin/users?page_size=100'
    Check ($users.source.database_name -eq 'bloodmatch' -and [int]$users.source.port -eq 3306) 'User management resolves the application database'
    Check ([int]$users.total -eq 15 -and @($users.users).Count -eq 15) 'User management returns fifteen retained accounts'
    Check (@($users.users | Where-Object { $_.role -eq 'admin' }).Count -eq 1) 'User management has exactly one administrator'
    foreach ($path in @('/api/admin/chat-reports','/api/profile','/api/my/requests','/api/chat/conversations','/api/chat/requests','/api/chat/unread-count')) {
        $null=Read $admin $path
        Check $true "Administrator endpoint $path"
    }
    $memberRow=@($users.users | Where-Object { $_.role -eq 'member' -and $_.verification_status -eq 'verified' -and $_.account_status -eq 'active' })[0]
    if (-not $memberRow) { throw 'No retained verified member available for smoke check.' }
    $memberAccount=@($manifest.accounts | Where-Object { [int]$_.id -eq [int]$memberRow.id })[0]
    $member=Login $memberAccount
    Check ($null -ne $member) 'Verified member login'
    foreach ($path in @('/api/profile','/api/my/requests','/api/my/responses','/api/chat/conversations','/api/chat/requests','/api/chat/unread-count')) {
        $null=Read $member $path
        Check $true "Member endpoint $path"
    }
    $otherMember=@($users.users | Where-Object { $_.role -eq 'member' -and $_.account_status -eq 'active' -and [int]$_.id -ne [int]$memberRow.id })[0]
    if (-not $otherMember) { throw 'No second retained member available for profile privacy check.' }
    $profile=Read $member "/api/profile/$($otherMember.id)"
    Check (-not ($profile.profile.PSObject.Properties.Name -contains 'email')) 'Other-member profile omits email'
    Write-Output "$script:passed application smoke checks passed; no requests, chats or accounts created/deleted."
} finally {
    foreach ($client in $clients) {
        Invoke-RestMethod "$base/api/logout" -Method Post -WebSession $client.session -Headers @{'X-CSRF-Token'=$client.csrf} -ContentType 'application/json' -Body '{}' -TimeoutSec 20 | Out-Null
    }
}
