# Run once from the office Windows account after stopping the foreground bridge.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$exe = Join-Path $root 'sHRMSBridgeBackground.exe'
if (-not (Test-Path -LiteralPath $exe)) { throw 'Copy sHRMSBridgeBackground.exe into the bridge folder first.' }
if (-not (Test-Path -LiteralPath (Join-Path $root 'config/config.json'))) { throw 'Keep your working config/config.json in this folder.' }
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$taskName = 'sHRMS Attendance Bridge Background'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    if ($existing.Principal.UserId -notin @($identity.Name,$identity.User.Value)) { throw 'This task belongs to another Windows account. Use that account to manage it.' }
    if ($existing.State -eq 'Running') { throw 'Stop the existing background task before updating it.' }
}
# Interactive token retains the ActiveX desktop. No password is collected/stored.
$principal = New-ScheduledTaskPrincipal -UserId $identity.Name -LogonType Interactive -RunLevel Limited
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $identity.Name
$action = New-ScheduledTaskAction -Execute $exe -Argument 'background' -WorkingDirectory $root
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Sync eSSL punches to HRMS while this Windows user is signed in.' -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Host 'Background startup installed and start requested. You can close this PowerShell window.'
Write-Host 'Keep Windows signed in and awake. Locking the screen is OK; signing out or sleeping stops sync.'
Write-Host 'Check HRMS heartbeat and logs/bridge.log to confirm actual syncing.'
