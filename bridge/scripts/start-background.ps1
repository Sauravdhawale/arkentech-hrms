$ErrorActionPreference = 'Stop'
$taskName = 'sHRMS Attendance Bridge Background'
Enable-ScheduledTask -TaskName $taskName | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Host 'Background sync start requested. Verify HRMS heartbeat or logs/bridge.log.'
