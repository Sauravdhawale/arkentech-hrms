"""Read-only ActiveX transport using the customer's installed eTimeTrackLite SDK.

A fresh STA Windows PowerShell process owns each control and native SDK lifetime.
The vendor application itself is never launched. No DLL registration is changed.
"""
import json
import os
import subprocess
import sys
from pathlib import Path
from essl_sdk import EsslAdapter, DeviceError

ERRORS = {
    'sdk_initialization': 'Cannot initialize the installed eTimeTrackLite ActiveX SDK. Check sdk_directory and use a logged-in Windows desktop.',
    'connection': 'ActiveX device connection failed. Check the device connection and communication key.',
    'serial': 'Device serial could not be verified or does not match config.json. No punches were read.',
    'read': 'SDK attendance read failed. No partial batch was queued.',
    'limit': 'Device record limit reached. No partial batch was queued.',
}

class Ref:
    def __init__(self, value): self.value = value

class ActiveXTransport:
    def __init__(self, config):
        self.config = config
        self.serial = ''
        self.rows = iter(())

    def make_ref(self, kind, value): return Ref(value)
    def SetCommPassword(self, password): return True  # Applied by the helper.
    def Disconnect(self): self.serial = ''; self.rows = iter(())
    def GetLastError(self, out): out.value = 0
    def GetSerialNumber(self, machine, out): out.value = self.serial; return bool(self.serial)

    def _call(self, action):
        if sys.platform != 'win32':
            raise DeviceError('The eSSL ActiveX adapter requires Windows.')
        folder = Path(self.config.get('sdk_directory') or
                      r'C:\Program Files (x86)\essl\eTimeTrackLite')
        if not all((folder / name).is_file() for name in
                   ('Interop.zkemkeeper.DLL', 'AxInterop.zkemkeeper.DLL')):
            raise DeviceError('eTimeTrackLite SDK wrappers not found. Set sdk_directory to the installed eTimeTrackLite folder.')
        request = dict(action=action, sdk_directory=str(folder),
                       device_host=str(self.config.get('device_host', '')).strip(),
                       device_port=int(self.config.get('device_port', 4370)),
                       device_password=int(self.config.get('device_password', 0)),
                       device_serial=str(self.config.get('device_serial', '')).strip(),
                       machine_number=int(self.config.get('machine_number', 1)),
                       max_device_records=int(self.config.get('max_device_records', 200000)))
        if not request['device_host'] or not 1 <= request['device_port'] <= 65535:
            raise DeviceError('Configure device_host and a valid device_port.')
        if not 1 <= request['max_device_records'] <= 1000000:
            raise DeviceError('max_device_records must be between 1 and 1000000.')
        helper_root = Path(getattr(sys, '_MEIPASS', Path(__file__).parent))
        script = (helper_root / 'essl_activex.ps1').read_text(encoding='utf-8')
        powershell = Path(os.environ.get('SystemRoot', r'C:\Windows')) / 'System32/WindowsPowerShell/v1.0/powershell.exe'
        try:
            proc = subprocess.run([str(powershell), '-NoLogo', '-NoProfile', '-NonInteractive',
                                   '-STA', '-Command', script],
                                  input=json.dumps(request) + '\n', capture_output=True,
                                  encoding='utf-8', errors='replace', timeout=300,
                                  creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
        except subprocess.TimeoutExpired:
            raise DeviceError('SDK operation timed out. No partial batch was queued; existing queued punches are retained.') from None
        except OSError:
            raise DeviceError('Windows PowerShell could not start the ActiveX SDK host.') from None
        try:
            if proc.returncode != 0: raise ValueError('Helper failed')
            result = json.loads(proc.stdout.lstrip('\ufeff'))
            if not isinstance(result, dict): raise ValueError('Invalid response')
        except (ValueError, TypeError):
            raise DeviceError('ActiveX SDK host failed to return a complete response. No partial batch was queued.') from None
        if result.get('ok') is not True:
            raise DeviceError(ERRORS.get(result.get('error'), 'ActiveX SDK operation failed. No partial batch was queued.'))
        if result.get('serial') != request['device_serial']:
            raise DeviceError(ERRORS['serial'])
        rows = result.get('rows')
        if not isinstance(rows, list) or len(rows) > request['max_device_records']:
            raise DeviceError(ERRORS['read'])
        for row in rows:
            if not isinstance(row, list) or len(row) != 10 or not isinstance(row[0], str) or any(type(x) is not int for x in row[1:]):
                raise DeviceError(ERRORS['read'])
        return result

    def Connect_Net(self, host, port):
        self.serial = self._call('test')['serial']
        return True

    def ReadGeneralLogData(self, machine):
        self.rows = iter(self._call('read')['rows'])
        return True

    def SSR_GetGeneralLogData(self, machine, pin, *fields):
        row = next(self.rows, None)
        if row is None: return False
        pin.value = row[0]
        for field, value in zip(fields, row[1:]): field.value = value
        return True

class EsslActiveXAdapter(EsslAdapter):
    def __init__(self, config, root):
        super().__init__(config, root, transport=ActiveXTransport(config))
