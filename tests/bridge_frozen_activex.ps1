# Packaged-process regression test with a synthetic control, not a vendor SDK.
$ErrorActionPreference = 'Stop'
$fixture = Join-Path ([IO.Path]::GetTempPath()) ('bridge-fixture-' + [Guid]::NewGuid())
New-Item -ItemType Directory -Path $fixture | Out-Null
try {
    $source = @'
using System;
using System.Text;
using System.Windows.Forms;
using System.Runtime.InteropServices;
namespace Axzkemkeeper {
    public class AxCZKEM : Control {
        [DllImport("kernel32.dll", CharSet=CharSet.Unicode)]
        private static extern uint GetDllDirectory(uint size, StringBuilder buffer);
        public void BeginInit() {}
        public void EndInit() {}
        public bool SetCommPassword(int key) { return key == 0; }
        private int failure=0;
        public bool Connect_Net(string host, int port) {
            // The frozen EXE normally passes its DLL directory to this child.
            var directory = new StringBuilder(32768);
            uint length = GetDllDirectory((uint)directory.Capacity, directory);
            if (length != 0 || directory.Length != 0) {
                System.IO.File.WriteAllText(System.IO.Path.Combine(Environment.CurrentDirectory,"fixture-dll-state.txt"), "Length="+length+" Directory="+directory.ToString());
                failure=-901; return false;
            }
            if (!IsHandleCreated) { failure=-902; return false; }
            if (host != "192.0.2.1") { failure=-903; return false; }
            if (port != 4370) { failure=-904; return false; }
            if (System.IO.File.Exists(System.IO.Path.Combine(Environment.CurrentDirectory,"expect-background"))) {
                var form = FindForm();
                if (form == null || form.ShowInTaskbar || form.Opacity > 0.01) { failure=-905; return false; }
                System.IO.File.WriteAllText(System.IO.Path.Combine(Environment.CurrentDirectory,"background-verified"),"ok");
            }
            return true;
        }
        public bool GetSerialNumber(int machine, ref string serial) { serial="TEST"; return true; }
        public void GetLastError(ref int error) { error=failure; }
        public void Disconnect() {}
    }
}
'@
    Add-Type -TypeDefinition $source -ReferencedAssemblies System.Windows.Forms,System.Drawing -OutputAssembly (Join-Path $fixture 'AxInterop.zkemkeeper.DLL')
    Copy-Item (Join-Path $fixture 'AxInterop.zkemkeeper.DLL') (Join-Path $fixture 'Interop.zkemkeeper.DLL')
    Copy-Item bridge/sHRMSBridge.exe,bridge/sHRMSBridgeBackground.exe $fixture
    New-Item -ItemType Directory (Join-Path $fixture 'config') | Out-Null
    $config = @{adapter='essl';sdk_transport='activex';sdk_directory=$fixture;device_host='192.0.2.1';device_port=4370;device_serial='TEST';api_token=('0'*64);server_url='https://invalid.example'}
    $config | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $fixture 'config/config.json')
    & (Join-Path $fixture 'sHRMSBridge.exe') test-device
    if ($LASTEXITCODE -ne 0) {
        $state = Join-Path $fixture 'fixture-dll-state.txt'
        if (Test-Path $state) { Get-Content $state }
        throw 'Frozen ActiveX helper regression failed'
    }
    New-Item -ItemType File (Join-Path $fixture 'expect-background') | Out-Null
    & (Join-Path $fixture 'sHRMSBridge.exe') test-device --background-ui
    if ($LASTEXITCODE -ne 0) { throw 'Hidden ActiveX host console diagnostic failed' }
    $child = Start-Process -FilePath (Join-Path $fixture 'sHRMSBridgeBackground.exe') -ArgumentList @('test-device','--background-ui') -PassThru -Wait
    if ($child.ExitCode -ne 0 -or -not (Test-Path (Join-Path $fixture 'background-verified'))) {
        throw 'Background executable/hidden ActiveX host regression failed'
    }

} finally {
    Remove-Item -LiteralPath $fixture -Recurse -Force
}
