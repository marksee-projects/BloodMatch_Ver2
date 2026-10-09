param(
    [ValidateSet('Preview', 'Setup', 'Verify', 'Remove')][string]$Action = 'Preview',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$taskName = 'BloodMatch-Test-Expiry'
$taskPath = '\'
$root = Split-Path $PSScriptRoot -Parent
$wrapper = Join-Path $PSScriptRoot 'run_test_expiry_task.ps1'
$sid = [System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value
$description = "BM-TEST-EXPIRY-v1; bloodmatch_test:3306; checkout=$root; owner=$sid"
if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP executable missing; provide -PhpPath.' }
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
if ($PhpPath.Contains('"') -or $wrapper.Contains('"')) { throw 'Unsupported quote in task path.' }
$exe = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $wrapper + '" -PhpPath "' + $PhpPath + '" -Scheduled'
$existing = Get-ScheduledTask -TaskName $taskName -TaskPath $taskPath -ErrorAction SilentlyContinue
if ($existing) {
    if ($existing.Description -ne $description -or @($existing.Actions).Count -ne 1 -or
        $existing.Actions[0].Execute -ne $exe -or $existing.Actions[0].Arguments -ne $arguments) {
        throw 'Existing task does not match this checkout/owner/action. Refusing to modify it.'
    }
}
switch ($Action) {
    'Preview' {
        [pscustomobject]@{ Task = $taskName; Database = 'bloodmatch_test'; Port = 3306;
            Interval = 'PT1M'; Logon = 'Interactive'; RunLevel = 'Limited'; Overlap = 'IgnoreNew + file lock';
            Php = $PhpPath; Log = (Join-Path $root 'logs\test-expiry.jsonl'); Installed = [bool]$existing }
    }
    'Setup' {
        if ($existing) { throw 'Task already exists. Use Verify, or Remove then Setup to replace it.' }
        $taskAction = New-ScheduledTaskAction -Execute $exe -Argument $arguments -WorkingDirectory $root
        # No Duration: repeat indefinitely, while this account is signed in.
        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
        $principal = New-ScheduledTaskPrincipal -UserId $sid -LogonType Interactive -RunLevel Limited
        $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
        Register-ScheduledTask -TaskName $taskName -TaskPath $taskPath -Action $taskAction -Trigger $trigger -Principal $principal -Settings $settings -Description $description | Out-Null
        Write-Output 'Registered test-only task. First automatic trigger is in approximately one minute; no manual start issued.'
    }
    'Verify' {
        if (-not $existing) { throw 'Test task is not installed.' }
        $info = Get-ScheduledTaskInfo -TaskName $taskName -TaskPath $taskPath
        [pscustomobject]@{ Task = $taskName; Database = 'bloodmatch_test'; Port = 3306;
            State = $existing.State; Interval = $existing.Triggers[0].Repetition.Interval;
            Logon = $existing.Principal.LogonType; RunLevel = $existing.Principal.RunLevel;
            Overlap = $existing.Settings.MultipleInstances; LastRunTime = $info.LastRunTime;
            NextRunTime = $info.NextRunTime; LastTaskResult = $info.LastTaskResult }
    }
    'Remove' {
        if (-not $existing) { Write-Output 'Test task is absent.'; return }
        Disable-ScheduledTask -TaskName $taskName -TaskPath $taskPath | Out-Null
        $current = Get-ScheduledTask -TaskName $taskName -TaskPath $taskPath
        if ($current.State -eq 'Running') { throw 'Task disabled; allow current run to finish, then repeat Remove. No running transaction was killed.' }
        Unregister-ScheduledTask -TaskName $taskName -TaskPath $taskPath -Confirm:$false
        Write-Output 'Test task removed; sanitized logs retained.'
    }
}
