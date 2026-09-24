import subprocess,sys,tempfile,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'bridge/src'))
from instance import single_instance
class Tests(unittest.TestCase):
 def test_competing_process_blocked_and_exit_releases_lock(self):
  with tempfile.TemporaryDirectory() as folder:
   script="from instance import single_instance; from pathlib import Path; import sys\nwith single_instance(Path(sys.argv[1])): print('acquired')"
   import os
   env=os.environ|{'PYTHONPATH':str(Path(__file__).resolve().parents[1]/'bridge/src')}
   with single_instance(folder):
    p=subprocess.run([sys.executable,'-c',script,folder],env=env,capture_output=True,text=True)
    self.assertNotEqual(p.returncode,0)
    self.assertIn('Another bridge process',p.stderr)
   p=subprocess.run([sys.executable,'-c',script,folder],env=env,capture_output=True,text=True)
   self.assertEqual(p.returncode,0,p.stderr)
if __name__=='__main__':unittest.main()
