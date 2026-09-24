# Run in a 64-bit Administrator PowerShell after copying licensed SDK files to lib.
$ErrorActionPreference = 'Stop'
if (-not [Environment]::Is64BitProcess) { throw 'Open 64-bit PowerShell first.' }
$admin = [Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $admin.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run PowerShell as Administrator.' }
$root = Split-Path -Parent $PSScriptRoot
$lib = Join-Path $root 'lib'
foreach ($name in @('zkemkeeper.dll','zkemsdk.dll','plcommpro.dll','commpro.dll')) {
    $path = Join-Path $lib $name
    if (-not (Test-Path -LiteralPath $path)) { throw "Missing $name in lib." }
    $bytes = [IO.File]::ReadAllBytes($path)
    $pe = [BitConverter]::ToInt32($bytes,60)
    if ([BitConverter]::ToUInt16($bytes,$pe+4) -ne 0x8664) { throw "$name must be a 64-bit SDK DLL." }
}
Write-Host 'Registers the supplied ZKEM SDK for 64-bit applications on this PC.'
Push-Location $lib
try {
    $process = Start-Process "$env:SystemRoot\System32\regsvr32.exe" -ArgumentList @('/s', ('"' + (Join-Path $lib 'zkemkeeper.dll') + '"')) -Wait -PassThru
    if ($process.ExitCode -ne 0) { throw 'SDK registration failed. Verify the full compatible vendor SDK set.' }
} finally { Pop-Location }
Write-Host 'SDK registered. Run .\sHRMSBridge.exe test-device from the bridge folder.'
