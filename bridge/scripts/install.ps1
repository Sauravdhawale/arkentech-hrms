# Run as Administrator. Copy the full package to C:\sHRMSBridge first.
$ErrorActionPreference = 'Stop'
$BridgeRoot = Split-Path -Parent $PSScriptRoot
$BridgeExe = Join-Path $BridgeRoot 'sHRMSBridge.exe'
if (!(Test-Path $BridgeExe)) { throw 'sHRMSBridge.exe is missing. Use the Windows build package.' }
if (!(Test-Path (Join-Path $BridgeRoot 'config\config.json'))) { throw 'Create config\config.json from the example first.' }
# Restrict the token and durable queue to local administrators and SYSTEM.
& icacls $BridgeRoot /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F'
if ($LASTEXITCODE -ne 0) { throw 'Unable to protect bridge configuration.' }
New-Service -Name 'sHRMSBridge' -DisplayName 'sHRMS Attendance Bridge' -BinaryPathName ('"' + $BridgeExe + '" service') -StartupType Automatic
& sc.exe failure sHRMSBridge reset= 86400 actions= restart/60000/restart/120000/restart/300000
Write-Host 'Installed. Test API and device first, then run scripts\start.ps1.'
