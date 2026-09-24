import sys,tempfile,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'bridge/src'))
from storage import Queue
from adapters import EsslAdapter,MockAdapter
from main import cycle
class QueueTests(unittest.TestCase):
 def test_durable_retry_and_cursor(self):
  with tempfile.TemporaryDirectory() as d:
   path=Path(d)/'q.db';q=Queue(path);q.bind('D1','mock')
   event={'biometric_id':'1','punched_at':'2026-01-01 09:00:00','event_key':'1'}
   q.enqueue('D1',[event],'1');q.db.close();q=Queue(path)
   self.assertEqual(q.cursor(),'1');pending=q.pending();self.assertEqual(len(pending),1)
   q.enqueue('D1',[event],'1');self.assertEqual(len(q.pending()),1)
   with self.assertRaises(ValueError):q.acknowledge(pending,{'accepted':['1']})
   self.assertEqual(len(q.pending()),1)
   q.acknowledge(pending,{'persisted':True,'duplicates':['1']});self.assertEqual(q.pending(),[])
   with self.assertRaises(ValueError):q.bind('D2','mock')
   q.db.close()
 def test_atomic_conflict(self):
  with tempfile.TemporaryDirectory() as d:
   q=Queue(Path(d)/'q.db');e={'biometric_id':'1','punched_at':'2026-01-01 09:00:00','event_key':'1'}
   q.enqueue('D',[e],'1')
   with self.assertRaises(ValueError):q.enqueue('D',[dict(e,punched_at='2026-01-01 10:00:00')],'2')
   self.assertEqual(q.cursor(),'1')
   q.db.close()
 def test_offline_read_is_queued(self):
  class Adapter:
   def connect(self):pass
   def disconnect(self):pass
   def getSerialNumber(self):return 'D'
   def testConnection(self):return True
   def fetchPunches(self,cursor):return [{'biometric_id':'1','punched_at':'2026-01-01 09:00:00'}],'1'
  class Offline:
   def call(self,*args):raise ConnectionError('offline')
  with tempfile.TemporaryDirectory() as d:
   q=Queue(Path(d)/'q.db')
   with self.assertRaises(ConnectionError):cycle({'device_serial':'D'},Adapter(),q,Offline())
   self.assertEqual(len(q.pending()),1)
   self.assertEqual(q.cursor(),'1')
   q.db.close()
 def test_backlog_drains_without_repeated_device_read(self):
  class Adapter:
   reads=0
   def connect(self):pass
   def disconnect(self):pass
   def getSerialNumber(self):return 'D'
   def testConnection(self):return True
   def fetchPunches(self,cursor):
    self.reads+=1
    return [],cursor
  class Server:
   batches=0
   def call(self,endpoint,data=None):
    if endpoint=='ping':return {'device_serial':'D','commands':{}}
    if endpoint=='punches':
     self.batches+=1
     return {'persisted':True,'accepted':[e['event_key'] for e in data['events']]}
    return {'ok':True}
  with tempfile.TemporaryDirectory() as d:
   q=Queue(Path(d)/'q.db')
   events=[{'biometric_id':'1','punched_at':'2026-01-01 09:00:00','event_key':str(i)} for i in range(1201)]
   q.enqueue('D',events,'cursor')
   adapter=Adapter();server=Server()
   cycle({'device_serial':'D'},adapter,q,server)
   self.assertEqual(adapter.reads,1)
   self.assertEqual(server.batches,3)
   self.assertEqual(q.pending(),[])
   q.db.close()
 def test_partial_ack_stops_drain_and_retains_pending(self):
  class Adapter:
   def connect(self):pass
   def disconnect(self):pass
   def getSerialNumber(self):return 'D'
   def testConnection(self):return True
   def fetchPunches(self,cursor):return [],cursor
  class Server:
   batches=0
   def call(self,endpoint,data=None):
    if endpoint=='ping':return {'device_serial':'D','commands':{}}
    if endpoint=='punches':
     self.batches+=1
     return {'persisted':True,'accepted':[]}
    return {'ok':True}
  with tempfile.TemporaryDirectory() as d:
   q=Queue(Path(d)/'q.db');q.enqueue('D',[{'biometric_id':'1','punched_at':'2026-01-01 09:00:00'}],'1')
   server=Server();cycle({'device_serial':'D'},Adapter(),q,server)
   self.assertEqual(server.batches,1)
   self.assertEqual(len(q.pending()),1)
   q.db.close()
 def test_sdk_not_faked(self):
  with self.assertRaises(RuntimeError):EsslAdapter().connect()
  with self.assertRaises(ValueError):MockAdapter({'test_mode':False},'.')
if __name__=='__main__':unittest.main()
