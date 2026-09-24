"""ActiveX process contract tests; physical SDK behavior is tested on the office PC."""
import json
import subprocess
import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'bridge/src'))
from essl_activex import ActiveXTransport, EsslActiveXAdapter, helper_environment
import os
from essl_sdk import DeviceError

CONFIG = dict(device_host='192.0.2.1', device_serial='TEST', api_token='SECRET_NOT_FOR_HELPER')
ROW = ['177', 1, 0, 2026, 9, 24, 9, 5, 3, 0]
class Tests(unittest.TestCase):
    def response(self, data, code=0):
        return subprocess.CompletedProcess([], code, json.dumps(data), 'PRIVATE SDK ERROR')

    def invoke(self, response):
        with patch('essl_activex.sys.platform', 'win32'), patch.object(Path, 'is_file', return_value=True), patch('essl_activex.subprocess.run', return_value=response) as run:
            result=ActiveXTransport(CONFIG)._call('read')
            request=json.loads(run.call_args.kwargs['input'])
            self.assertNotIn('api_token', request)
            self.assertNotIn('SECRET', run.call_args.kwargs['input'])
            self.assertIn('-STA', run.call_args.args[0])
            return result

    def test_success_and_empty(self):
        for rows in ([], [ROW]):
            self.assertEqual(self.invoke(self.response(dict(ok=True, serial='TEST', rows=rows)))['rows'],rows)

    def test_mismatch_malformed_and_helper_errors(self):
        for data in (dict(ok=True,serial='OTHER',rows=[ROW]),
                     dict(ok=True,serial='TEST',rows=[['177']]),
                     dict(ok=False,error='read',rows=[ROW]),
                     dict(ok=False,error='SECRET_NOT_FOR_HELPER')):
            with self.assertRaises(DeviceError) as raised:
                self.invoke(self.response(data))
            self.assertNotIn('SECRET',str(raised.exception))

    def test_sdk_code_is_visible_without_raw_exception(self):
        with self.assertRaisesRegex(DeviceError, 'SDK error: -201'):
            self.invoke(self.response(dict(ok=False,error='connection',sdk_error=-201)))

    def test_child_path_sanitization_preserves_parent(self):
        bundle=str(Path('bundle').resolve())
        original=os.pathsep.join([bundle,str(Path(bundle)/'pywin32_system32'),'system-tools'])
        with patch.object(sys,'_MEIPASS',bundle,create=True),patch.dict(os.environ,{'PATH':original}):
            self.assertEqual(helper_environment()['PATH'],'system-tools')
            self.assertEqual(os.environ['PATH'],original)

    def test_timeout(self):
        with patch('essl_activex.sys.platform','win32'),patch.object(Path,'is_file',return_value=True),patch('essl_activex.subprocess.run',side_effect=subprocess.TimeoutExpired('test',300)):
            with self.assertRaises(DeviceError): ActiveXTransport(CONFIG)._call('read')

    def test_same_identity_as_com_and_no_partial_records(self):
        a=EsslActiveXAdapter(CONFIG,Path('.'))
        with patch.object(a.sdk,'_call',side_effect=[dict(serial='TEST'),dict(rows=[ROW,ROW])]):
            a.connect()
            events,cursor=a.fetchPunches(None)
        self.assertEqual(len(events),1)
        self.assertEqual(events[0]['biometric_id'],'177')
        self.assertEqual(cursor,'zkem-v1')
        with patch.object(a.sdk,'_call',side_effect=DeviceError('Read interrupted')):
            with self.assertRaises(DeviceError): a.fetchPunches(cursor)
        a.disconnect()
        self.assertFalse(a.connected)

if __name__=='__main__': unittest.main()
