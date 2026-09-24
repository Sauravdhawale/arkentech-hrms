import argparse
import json
import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path
import ssl
import sys
import threading
import urllib.request
from urllib.parse import urlparse
from adapters import EsslAdapter, MockAdapter
from essl_sdk import DeviceError
from essl_activex import EsslActiveXAdapter
from storage import Queue

ROOT=Path(sys.executable).parent if getattr(sys,'frozen',False) else Path(__file__).resolve().parents[1]
STOP=threading.Event()

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):
        raise ValueError('API redirects are not allowed; configure the final HTTPS URL')

class Api:
    def __init__(self, config):
        self.base=config['server_url'].rstrip('/')
        url=urlparse(self.base)
        if url.scheme!='https' or not url.hostname or url.username or url.password or url.query or url.fragment:
            raise ValueError('server_url must be a plain HTTPS URL')
        self.token=config['api_token']
        if len(self.token)!=64 or any(c not in '0123456789abcdef' for c in self.token):
            raise ValueError('Configure a device token generated in HRMS')
        self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context()))
    def call(self, endpoint, data=None):
        request=urllib.request.Request(self.base+'/api/attendance/bridge/'+endpoint+'.php',
            data=None if data is None else json.dumps(data).encode(),
            headers={'Authorization':'Bearer '+self.token,'Content-Type':'application/json'})
        with self.opener.open(request,timeout=30) as response:
            return json.loads(response.read(1000000))

def load():
    config=json.loads((ROOT/'config/config.json').read_text(encoding='utf-8-sig'))
    kind=config.get('adapter','essl')
    if kind not in ('essl','mock'): raise ValueError('Unsupported adapter')
    transport=config.get('sdk_transport', 'activex')
    if transport not in ('activex','com'): raise ValueError('Unsupported sdk_transport')
    adapter=MockAdapter(config,ROOT) if kind=='mock' else (EsslActiveXAdapter(config, ROOT) if transport=='activex' else EsslAdapter(config, ROOT))
    queue=Queue(ROOT/'data/queue.sqlite3')
    queue.bind(config['device_serial'],kind)
    return config,adapter,queue,Api(config)

def cycle(config,adapter,queue,api):
    online=False
    last=None
    error=''
    try:
        adapter.connect()
        if adapter.getSerialNumber()!=config['device_serial']: raise ValueError('Hardware serial mismatch')
        online=bool(adapter.testConnection())
        if not online: raise RuntimeError('Device connection failed')
        events,cursor=adapter.fetchPunches(queue.cursor())
        queue.enqueue(config['device_serial'],events,cursor)
        if events: last=events[-1]['punched_at']
    except Exception as exc:
        # Log error type only: SDK exceptions can contain configuration secrets.
        error=str(exc) if isinstance(exc, DeviceError) else 'Device read failed: '+type(exc).__name__
        logging.error(error)
    finally:
        adapter.disconnect()
    ping=api.call('ping')
    if ping.get('device_serial')!=config['device_serial']: raise ValueError('Token/device serial mismatch')
    commands=ping.get('commands',{})
    pending=queue.pending()
    if pending:
        result=api.call('punches',{'device_serial':config['device_serial'],'events':pending})
        count=queue.acknowledge(pending,result)
        logging.info('Acknowledged %d of %d queued punches',count,len(pending))
    api.call('sync-status',{'device_serial':config['device_serial'],'device_online':online,
        'last_device_timestamp':last,'error':error,'ack_commands':commands if online else {}})
    if error: raise RuntimeError(error)

def run():
    (ROOT/'logs').mkdir(parents=True,exist_ok=True)
    handler=RotatingFileHandler(ROOT/'logs/bridge.log',maxBytes=2_000_000,backupCount=5)
    logging.basicConfig(level=logging.INFO,handlers=[handler],format='%(asctime)s %(levelname)s %(message)s')
    config,adapter,queue,api=load()
    interval=max(10,int(config.get('sync_interval_seconds',60)))
    delay=interval
    while not STOP.is_set():
        try:
            cycle(config,adapter,queue,api)
            delay=interval
        except Exception as exc:
            logging.error('Sync failed (%s); queue retained; retry in %ds',type(exc).__name__,delay)
            delay=min(900,max(interval,delay*2))
        STOP.wait(delay)

def service():
    config=json.loads((ROOT/'config/config.json').read_text(encoding='utf-8-sig'))
    if config.get('adapter','essl')=='essl' and config.get('sdk_transport','activex')=='activex':
        raise DeviceError('ActiveX mode requires a logged-in Windows desktop. Use run; unattended Windows Service mode is not yet verified.')
    import win32serviceutil,win32service,win32event,servicemanager
    class BridgeService(win32serviceutil.ServiceFramework):
        _svc_name_='sHRMSBridge'
        _svc_display_name_='sHRMS Attendance Bridge'
        def __init__(self,args):
            super().__init__(args)
        def SvcStop(self):
            self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
            STOP.set()
        def SvcDoRun(self):
            self.ReportServiceStatus(win32service.SERVICE_RUNNING)
            run()
    servicemanager.Initialize()
    servicemanager.PrepareToHostSingle(BridgeService)
    servicemanager.StartServiceCtrlDispatcher()

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('command',choices=['run','once','test-api','test-device','service'],default='run',nargs='?')
    command=parser.parse_args().command
    try:
        if command=='service': service()
        elif command=='run': run()
        else:
            config,adapter,queue,api=load()
            if command=='test-api':
                result=api.call('ping')
                if result.get('device_serial')!=config['device_serial']: raise ValueError('Token/device mismatch')
                print('HTTPS API connected; token matches configured device.')
            elif command=='test-device':
                try:
                    adapter.connect()
                    if not adapter.testConnection() or adapter.getSerialNumber()!=config['device_serial']:
                        raise RuntimeError('Device verification failed')
                    print('Device test passed ('+config['adapter']+' adapter).')
                finally: adapter.disconnect()
            else:
                cycle(config,adapter,queue,api)
                print('Sync cycle completed. Pending queued punches (up to 500): '+str(len(queue.pending())))
    except KeyboardInterrupt: STOP.set()
    except Exception as exc:
        message = str(exc) if isinstance(exc, DeviceError) else type(exc).__name__ + '. Check configuration and logs.'
        print('Bridge failed: ' + message, file=sys.stderr)
        sys.exit(1)
