"""Read-only ZKEM COM adapter. Vendor DLLs stay on the customer's Windows PC."""
import hashlib
import json
import re
import sys
from pathlib import Path
from datetime import datetime

class DeviceError(RuntimeError):
    """Only static, credential-free messages may be displayed to the operator."""

class EsslAdapter:
    def __init__(self, config=None, root=None, transport=None):
        self.config = config or {}
        self.root = Path(root) if root else None
        self.dlls = []
        self.dll_directory = None
        self.sdk = transport
        self.injected = transport is not None
        self.com = None
        self.connected = False
        self.machine = int(self.config.get('machine_number', 1))

    def connect(self):
        if self.connected:
            return True
        if not str(self.config.get('device_host', '')).strip():
            raise DeviceError('Configure device_host before connecting to the device.')
        try:
            if self.sdk is None:
                if sys.platform != 'win32':
                    raise DeviceError('The eSSL SDK adapter requires Windows.')
                import os
                import ctypes
                if not self.dlls and self.root:
                    try:
                        self.dll_directory = os.add_dll_directory(str(self.root / 'lib'))
                        for name in ('commpro.dll', 'plcommpro.dll', 'zkemsdk.dll', 'zkemkeeper.dll'):
                            self.dlls.append(ctypes.WinDLL(str(self.root / 'lib' / name)))
                    except OSError:
                        self.dlls = []
                        raise DeviceError('Cannot load SDK dependencies. Copy the four matching 64-bit vendor DLLs into lib.') from None
                import pythoncom
                from win32com.client.dynamic import DumbDispatch
                self.com = pythoncom
                pythoncom.CoInitialize()
                try:
                    self.sdk = DumbDispatch('zkemkeeper.ZKEM.1')
                except Exception:
                    raise DeviceError('SDK unavailable: register the matching 64-bit vendor DLLs using scripts/register-sdk.ps1.') from None
            host = str(self.config.get('device_host', '')).strip()
            port = int(self.config.get('device_port', 4370))
            if not host or not 1 <= port <= 65535:
                raise DeviceError('Configure device_host and a valid device_port.')
            password = int(self.config.get('device_password', 0))
            if password and not self.sdk.SetCommPassword(password):
                raise DeviceError('The SDK could not apply the configured device communication password.')
            if not self.sdk.Connect_Net(host, port):
                raise DeviceError('Device connection failed. Check the office LAN, device IP, port and communication password.')
            self.connected = True
            if self.getSerialNumber() != str(self.config.get('device_serial', '')).strip():
                raise DeviceError('Hardware serial does not match config.json. No punches were read.')
            return True
        except Exception:
            self.disconnect()
            raise

    def _ref(self, kind, value):
        if self.injected:
            return self.sdk.make_ref(kind, value)
        from win32com.client import VARIANT
        return VARIANT(self.com.VT_BYREF | getattr(self.com, 'VT_' + kind), value)

    def _require(self):
        if not self.connected:
            raise DeviceError('Connect to the device before reading attendance.')

    def _end(self):
        code = self._ref('I4', 0)
        self.sdk.GetLastError(code)
        if code.value not in (0, -8):
            raise DeviceError('SDK attendance read failed; no partial batch was queued. Retry after checking the device.')

    def disconnect(self):
        try:
            if self.sdk is not None and self.connected:
                self.sdk.Disconnect()
        except Exception:
            pass
        finally:
            self.connected = False
            if not self.injected:
                self.sdk = None
            if self.com is not None:
                self.com.CoUninitialize()
                self.com = None

    def getSerialNumber(self):
        self._require()
        serial = self._ref('BSTR', '')
        if not self.sdk.GetSerialNumber(self.machine, serial):
            raise DeviceError('The SDK could not read the device serial number.')
        return str(serial.value).strip()

    def testConnection(self):
        self._require()
        return self.getSerialNumber() == str(self.config.get('device_serial', '')).strip()

    def fetchUsers(self):
        # Do not request passwords, fingerprint templates or biometric images.
        raise DeviceError('User export is not enabled. Use HRMS employee mapping; attendance reads use device user IDs.')

    def fetchPunches(self, cursor):
        self._require()
        if not self.sdk.ReadGeneralLogData(self.machine):
            self._end()
            return [], 'zkem-v1'
        events = {}
        maximum = int(self.config.get('max_device_records', 200000))
        for _ in range(maximum):
            pin = self._ref('BSTR', '')
            # verify, in/out, year, month, day, hour, minute, second, workcode
            fields = [self._ref('I4', 0) for _ in range(9)]
            if not self.sdk.SSR_GetGeneralLogData(self.machine, pin, *fields):
                self._end()
                break
            verify, mode, year, month, day, hour, minute, second, work = [x.value for x in fields]
            user = str(pin.value).strip()
            if not re.fullmatch(r'[A-Za-z0-9._-]{1,80}', user):
                raise DeviceError('An attendance record has an unsupported device user ID. No partial batch was queued.')
            try:
                stamp = datetime(year, month, day, hour, minute, second).strftime('%Y-%m-%d %H:%M:%S')
            except (ValueError, TypeError):
                raise DeviceError('An attendance record has an invalid timestamp. No partial batch was queued.') from None
            # Keep direction unknown unless explicitly configured for this device.
            direction = self.config.get('punch_direction_map', {}).get(str(mode), 'unknown')
            if direction not in ('in', 'out', 'unknown'):
                raise DeviceError('punch_direction_map values must be in, out or unknown.')
            key = hashlib.sha256(json.dumps([self.config['device_serial'], user, stamp, verify, mode, work], separators=(',', ':')).encode()).hexdigest()
            events[key] = dict(biometric_id=user, punched_at=stamp, direction=direction, event_key=key)
        else:
            raise DeviceError('Device record limit reached. No partial batch was queued; increase max_device_records after review.')
        # Reread retained device logs each cycle. The durable queue deduplicates by
        # immutable event identity, so same-second, delayed and reordered logs survive.
        return sorted(events.values(), key=lambda x: (x['punched_at'], x['event_key'])), 'zkem-v1'
