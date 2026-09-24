"""Contract tests use an SDK double; they do not prove hardware compatibility."""
import sys, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'bridge/src'))
from essl_sdk import EsslAdapter, DeviceError
class Ref:
 def __init__(self, value): self.value=value
class SDK:
 def __init__(self, rows=(), serial='TEST', error=0): self.rows=list(rows);self.serial=serial;self.error=error;self.closed=0
 def make_ref(self,kind,value): return Ref(value)
 def Connect_Net(self,host,port): return True
 def Disconnect(self): self.closed+=1
 def GetSerialNumber(self,machine,out): out.value=self.serial;return True
 def ReadGeneralLogData(self,machine): self.i=iter(self.rows);return True
 def SSR_GetGeneralLogData(self,machine,pin,*fields):
  row=next(self.i,None)
  if row is None:return False
  pin.value=row[0]
  for f,v in zip(fields,row[1:]):f.value=v
  return True
 def GetLastError(self,out):out.value=self.error
class Tests(unittest.TestCase):
 def adapter(self,sdk):return EsslAdapter({'device_host':'192.0.2.1','device_serial':'TEST'},transport=sdk)
 def test_serial_mismatch_disconnects(self):
  s=SDK(serial='OTHER');a=self.adapter(s)
  with self.assertRaises(DeviceError):a.connect()
  self.assertEqual(s.closed,1);self.assertFalse(a.connected)
 def test_duplicate_and_reordered_records_stable(self):
  row=('177',1,0,2026,9,24,9,5,3,0);row2=('178',1,1,2026,9,24,9,5,3,0)
  s=SDK([row,row2,row]);a=self.adapter(s);a.connect();first,c=a.fetchPunches(None)
  self.assertEqual(len(first),2);self.assertEqual(first[0]['direction'],'unknown')
  s.rows=[row2,row];self.assertEqual(first,a.fetchPunches(c)[0])
  a.disconnect()
 def test_read_error_does_not_return_partial_batch(self):
  a=self.adapter(SDK([('177',1,0,2026,9,24,9,5,3,0)],error=-1));a.connect()
  with self.assertRaises(DeviceError):a.fetchPunches(None)
 def test_empty_records(self):
  a=self.adapter(SDK(error=-8));a.connect();self.assertEqual(a.fetchPunches(None)[0],[])
 def test_invalid_record_fails(self):
  a=self.adapter(SDK([('177',1,0,2026,99,24,9,5,3,0)]));a.connect()
  with self.assertRaises(DeviceError):a.fetchPunches(None)
 def test_no_user_secrets_exported(self):
  with self.assertRaises(DeviceError):self.adapter(SDK()).fetchUsers()
if __name__=='__main__':unittest.main()
