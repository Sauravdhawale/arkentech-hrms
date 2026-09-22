import sys,tempfile,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'bridge/src'))
from storage import Queue
from adapters import EsslAdapter,MockAdapter
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
 def test_atomic_conflict(self):
  with tempfile.TemporaryDirectory() as d:
   q=Queue(Path(d)/'q.db');e={'biometric_id':'1','punched_at':'2026-01-01 09:00:00','event_key':'1'}
   q.enqueue('D',[e],'1')
   with self.assertRaises(ValueError):q.enqueue('D',[dict(e,punched_at='2026-01-01 10:00:00')],'2')
   self.assertEqual(q.cursor(),'1')
 def test_sdk_not_faked(self):
  with self.assertRaises(RuntimeError):EsslAdapter().connect()
  with self.assertRaises(ValueError):MockAdapter({'test_mode':False},'.')
if __name__=='__main__':unittest.main()
