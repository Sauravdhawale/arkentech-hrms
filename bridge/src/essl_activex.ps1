# Read-only SDK host. Request arrives on stdin; no HRMS token is passed here.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$form = $null
$zk = $null
$result = $null
$failure = 'sdk_initialization'
[int]$sdkError = 0
try {
    $request = [Console]::ReadLine() | ConvertFrom-Json
    if ($request.action -notin @('test','read')) { throw 'Unsupported action' }
    $folder = [string]$request.sdk_directory
    Set-Location -LiteralPath $folder
    [Environment]::CurrentDirectory = $folder
    # PyInstaller's SetDllDirectory state is inherited by this child process.
    # Reset only this helper's DLL search path before loading the vendor SDK.
    Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class BridgeNativeSearch {
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    public static extern bool SetDllDirectory(IntPtr path);
}
'@
    if (-not [BridgeNativeSearch]::SetDllDirectory([IntPtr]::Zero)) { throw 'DLL search reset failed' }
    Add-Type -AssemblyName System.Windows.Forms
    [void][Reflection.Assembly]::LoadFrom((Join-Path $folder 'Interop.zkemkeeper.DLL'))
    [void][Reflection.Assembly]::LoadFrom((Join-Path $folder 'AxInterop.zkemkeeper.DLL'))
    if ($request.background_ui -eq $true) {
        Add-Type -ReferencedAssemblies System.Windows.Forms -TypeDefinition @'
public class BridgeQuietForm : System.Windows.Forms.Form {
    protected override bool ShowWithoutActivation { get { return true; } }
}
'@
        $form = New-Object BridgeQuietForm
    } else {
        $form = New-Object System.Windows.Forms.Form
    }
    $form.Text = 'sHRMS device connection'
    if ($request.background_ui -eq $true) {
        # Retain the real window/control initialization needed by the SDK.
        # Invisible and off-screen; foreground diagnostics remain unchanged.
        $form.ShowInTaskbar = $false
        $form.Opacity = 0
        $form.StartPosition = [System.Windows.Forms.FormStartPosition]::Manual
        $form.Location = New-Object System.Drawing.Point(-32000,-32000)
    }
    $zk = New-Object Axzkemkeeper.AxCZKEM
    $zk.BeginInit()
    $form.Controls.Add($zk)
    $zk.EndInit()
    $form.Show()
    [System.Windows.Forms.Application]::DoEvents()
    $failure = 'connection'
    $null = $zk.SetCommPassword([int]$request.device_password)
    if (-not $zk.Connect_Net([string]$request.device_host,[int]$request.device_port)) {
        $null = $zk.GetLastError([ref]$sdkError)
        throw 'Connection failed'
    }
    $failure = 'serial'
    [string]$serial = ''
    if (-not $zk.GetSerialNumber([int]$request.machine_number,[ref]$serial)) { throw 'Serial unavailable' }
    $serial = $serial.Trim()
    if ($serial -cne [string]$request.device_serial) { throw 'Serial mismatch' }
    $rows = New-Object 'System.Collections.Generic.List[object]'
    if ($request.action -eq 'read') {
        $failure = 'read'
        $loaded = $zk.ReadGeneralLogData([int]$request.machine_number)
        if (-not $loaded) {
            [int]$code = 0
            $null = $zk.GetLastError([ref]$code)
            if ($code -notin @(0,-8)) { throw 'Read failed' }
        } else {
            while ($true) {
                [string]$pin = ''
                [int]$verify = 0; [int]$mode = 0; [int]$year = 0
                [int]$month = 0; [int]$day = 0; [int]$hour = 0
                [int]$minute = 0; [int]$second = 0; [int]$work = 0
                $found = $zk.SSR_GetGeneralLogData([int]$request.machine_number,[ref]$pin,
                    [ref]$verify,[ref]$mode,[ref]$year,[ref]$month,[ref]$day,
                    [ref]$hour,[ref]$minute,[ref]$second,[ref]$work)
                if (-not $found) {
                    [int]$code = 0
                    $null = $zk.GetLastError([ref]$code)
                    if ($code -notin @(0,-8)) { throw 'Read interrupted' }
                    break
                }
                if ($rows.Count -ge [int]$request.max_device_records) {
                    $failure = 'limit'
                    throw 'Record limit reached'
                }
                $rows.Add(@($pin,$verify,$mode,$year,$month,$day,$hour,$minute,$second,$work))
            }
        }
    }
    $result = @{ok=$true; serial=$serial; rows=$rows.ToArray()}
} catch {
    # Never emit raw SDK exceptions, request values or a partial attendance batch.
    $result = @{ok=$false; error=$failure; sdk_error=$sdkError}
} finally {
    if ($null -ne $zk) { try { $zk.Disconnect() } catch {} }
    if ($null -ne $form) { $form.Dispose() }
}
[Console]::WriteLine((ConvertTo-Json -InputObject $result -Depth 5 -Compress))
