"""OS-released, per-installation lock prevents competing readers/uploaders."""
from contextlib import contextmanager
from pathlib import Path
import os
from essl_sdk import DeviceError

@contextmanager
def single_instance(root):
    path=Path(root)/'data/bridge.lock'
    path.parent.mkdir(parents=True,exist_ok=True)
    handle=path.open('a+b')
    locked=False
    try:
        handle.seek(0,2)
        if handle.tell()==0:
            handle.write(b'0');handle.flush()
        handle.seek(0)
        try:
            if os.name=='nt':
                import msvcrt
                msvcrt.locking(handle.fileno(),msvcrt.LK_NBLCK,1)
            else:
                import fcntl
                fcntl.flock(handle.fileno(),fcntl.LOCK_EX|fcntl.LOCK_NB)
            locked=True
        except OSError:
            raise DeviceError('Another bridge process is using this folder. Stop it before starting another sync or device test.') from None
        yield
    finally:
        if locked:
            handle.seek(0)
            if os.name=='nt':
                import msvcrt
                msvcrt.locking(handle.fileno(),msvcrt.LK_UNLCK,1)
            else:
                import fcntl
                fcntl.flock(handle.fileno(),fcntl.LOCK_UN)
        handle.close()
