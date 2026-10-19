param(
    [ValidateSet('Preview','Install','Upgrade','Verify','Remove')][string]$Action = 'Preview',
    [ValidateSet('Safety','Email','MembershipEmail','DonationFollowup','All')][string]$Kind = 'All',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$workspace = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$executable = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$wrapper = Join-Path $workspace 'scripts\run_application_task.ps1'
$kinds = if ($Kind -eq 'All') { @('Safety','Email') } else { @($Kind) }
$specs = foreach ($item in $kinds) {
    $entry = Join-Path $workspace "database\run_application_$($item.ToLowerInvariant()).php"
    if (-not (Test-Path -LiteralPath $entry -PathType Leaf)) { throw "Missing runner: $entry" }
    $legacyArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $wrapper + '" -Kind ' + $item + ' -PhpPath "' + $php + '"'
    $taskExecutable = $executable
    $taskArguments = $legacyArguments
    if ($item -in @('MembershipEmail','DonationFollowup')) {
        # Console applications can flash a window even under hidden PowerShell.
        # php-win is the installed PHP runtime built without a console window.
        $taskExecutable = (Resolve-Path -LiteralPath (Join-Path (Split-Path -Parent $php) 'php-win.exe')).Path
        $taskArguments = '-d display_errors=0 -d log_errors=0 "' + $entry + '" --scheduled'
    }
    [PSCustomObject]@{ Name = "BloodMatch-Application-$item"; Entry = $entry; Execute = $taskExecutable; Arguments = $taskArguments; LegacyArguments = $legacyArguments }
}
function Assert-Owned($task, $spec) {
    $actions = @($task.Actions)
    $current = $actions.Count -eq 1 -and $actions[0].Execute -eq $spec.Execute -and $actions[0].Arguments -eq $spec.Arguments
    $legacy = $actions.Count -eq 1 -and $actions[0].Execute -eq $executable -and $actions[0].Arguments -eq $spec.LegacyArguments
    if ((-not $current -and -not $legacy) -or $actions[0].WorkingDirectory -ne $workspace) {
        throw "Task $($spec.Name) does not match this workspace/runner; refuse modification."
    }
}
# Review every name before installing/removing any task; never overwrite another task.
foreach ($spec in $specs) {
    $existing = Get-ScheduledTask -TaskName $spec.Name -ErrorAction SilentlyContinue
    if ($existing) {
        Assert-Owned $existing $spec
        if ($Action -eq 'Install') { throw "Task exists: $($spec.Name). Verify or remove it explicitly first." }
        if ($Action -eq 'Remove' -and $existing.State -eq 'Running') { throw "Task running: $($spec.Name). Wait for it to finish; do not kill a transaction." }
    }
}
foreach ($spec in $specs) {
    switch ($Action) {
        'Preview' { [PSCustomObject]@{ Task = $spec.Name; Database = 'bloodmatch'; Host = '127.0.0.1'; Port = 3306; Interval = '1 minute'; Execute = $spec.Execute; Arguments = $spec.Arguments; WorkingDirectory = $workspace; Identity = 'Current signed-in user, Limited'; Overlap = 'IgnoreNew plus runner lock'; Writes = $false } }
        'Install' {
            $taskAction = New-ScheduledTaskAction -Execute $spec.Execute -Argument $spec.Arguments -WorkingDirectory $workspace
            $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
            $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)
            $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
            $principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
            Register-ScheduledTask -TaskName $spec.Name -Action $taskAction -Trigger $trigger -Settings $settings -Principal $principal -Description 'BloodMatch approved local application target bloodmatch:3306; no deployed target.' | Out-Null
            Write-Output "Installed $($spec.Name). Runs only while the current user is signed in and the computer is awake."
        }
        'Upgrade' {
            $task = Get-ScheduledTask -TaskName $spec.Name -ErrorAction Stop
            Assert-Owned $task $spec
            if ($task.State -eq 'Running') { throw 'Task running; wait for completion before changing its launcher.' }
            $taskAction = New-ScheduledTaskAction -Execute $spec.Execute -Argument $spec.Arguments -WorkingDirectory $workspace
            Set-ScheduledTask -TaskName $spec.Name -Action $taskAction | Out-Null
            Write-Output "Updated launcher for $($spec.Name); schedule, identity and settings preserved."
        }
        'Verify' {
            $task = Get-ScheduledTask -TaskName $spec.Name -ErrorAction SilentlyContinue
            if ($null -eq $task) { Write-Output "$($spec.Name): absent"; continue }
            Assert-Owned $task $spec
            $info = Get-ScheduledTaskInfo -TaskName $spec.Name
            [PSCustomObject]@{ Task = $spec.Name; State = $task.State; Execute = $task.Actions[0].Execute; Interval = $task.Triggers[0].Repetition.Interval; LastRunTime = $info.LastRunTime; LastTaskResult = $info.LastTaskResult; NextRunTime = $info.NextRunTime; LogonType = $task.Principal.LogonType; RunLevel = $task.Principal.RunLevel; MultipleInstances = $task.Settings.MultipleInstances }
        }
        'Remove' {
            $task = Get-ScheduledTask -TaskName $spec.Name -ErrorAction SilentlyContinue
            if ($null -eq $task) { Write-Output "$($spec.Name): already absent"; continue }
            Disable-ScheduledTask -TaskName $spec.Name | Out-Null
            $task = Get-ScheduledTask -TaskName $spec.Name
            if ($task.State -eq 'Running') { throw 'A run started while removing; task disabled, wait and retry Remove.' }
            Unregister-ScheduledTask -TaskName $spec.Name -Confirm:$false
            Write-Output "Removed $($spec.Name); logs retained."
        }
    }
}
