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
        public bool Connect_Net(string host, int port) {
            // The frozen EXE normally passes its DLL directory to this child.
            return GetDllDirectory(0, null) == 0 && IsHandleCreated &&
                   host == "192.0.2.1" && port == 4370;
        }
        public bool GetSerialNumber(int machine, ref string serial) { serial="TEST"; return true; }
        public void GetLastError(ref int error) { error=-201; }
        public void Disconnect() {}
    }
}
'@
    Add-Type -TypeDefinition $source -ReferencedAssemblies System.Windows.Forms,System.Drawing -OutputAssembly (Join-Path $fixture 'AxInterop.zkemkeeper.DLL')
    Copy-Item (Join-Path $fixture 'AxInterop.zkemkeeper.DLL') (Join-Path $fixture 'Interop.zkemkeeper.DLL')
    Copy-Item bridge/sHRMSBridge.exe $fixture
    New-Item -ItemType Directory (Join-Path $fixture 'config') | Out-Null
    $config = @{adapter='essl';sdk_transport='activex';sdk_directory=$fixture;device_host='192.0.2.1';device_port=4370;device_serial='TEST';api_token=('0'*64);server_url='https://invalid.example'}
    $config | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $fixture 'config/config.json')
    & (Join-Path $fixture 'sHRMSBridge.exe') test-device
    if ($LASTEXITCODE -ne 0) { throw 'Frozen ActiveX helper regression failed' }
} finally {
    Remove-Item -LiteralPath $fixture -Recurse -Force
}
