$ErrorActionPreference='Stop'
$demoPrivate=Join-Path (Split-Path -Parent $PSScriptRoot) 'backend/storage/maintenance/DEMO_MAIN_20261022_B42E'
$demoProcesses=Get-Content -LiteralPath "$demoPrivate/processes.json" -Raw | ConvertFrom-Json
foreach($demoKind in @('php','node')) {
 $demoId=$demoProcesses.("${demoKind}_pid");$demoTicks=$demoProcesses.("${demoKind}_start_ticks")
 $demoProcess=Get-Process -Id $demoId -ErrorAction SilentlyContinue
 if($null -eq $demoProcess){continue}
 if($demoProcess.ProcessName -ne $demoKind -or $demoProcess.StartTime.ToUniversalTime().Ticks -ne $demoTicks){throw "Process $demoId no longer matches the recorded demo process; refusing to stop it."}
 Stop-Process -Id $demoId
}
Write-Output 'Stopped only recorded demo servers. No database or file cleanup performed.'
