$ErrorActionPreference = 'Stop'
$BridgeService = Get-Service -Name 'sHRMSBridge' -ErrorAction SilentlyContinue
if ($BridgeService -and $BridgeService.Status -ne 'Stopped') { Stop-Service sHRMSBridge }
& sc.exe delete sHRMSBridge
Write-Host 'Service removed. Configuration, queue and logs were preserved.'
