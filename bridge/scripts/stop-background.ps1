$ErrorActionPreference = 'Stop'
$taskName = 'sHRMS Attendance Bridge Background'
Disable-ScheduledTask -TaskName $taskName | Out-Null
Stop-ScheduledTask -TaskName $taskName
Write-Host 'Background sync stopped and automatic startup disabled. Queue and configuration were preserved.'
