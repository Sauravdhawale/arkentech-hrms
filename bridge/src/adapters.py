"""Device adapters. No proprietary SDK is bundled or impersonated."""
from abc import ABC, abstractmethod
import json
from pathlib import Path

class DeviceAdapter(ABC):
    @abstractmethod
    def connect(self): pass
    @abstractmethod
    def disconnect(self): pass
    @abstractmethod
    def testConnection(self): pass
    @abstractmethod
    def fetchUsers(self): pass
    @abstractmethod
    def fetchPunches(self, cursor): pass
    @abstractmethod
    def getSerialNumber(self): pass

from essl_sdk import EsslAdapter

class MockAdapter(DeviceAdapter):
    def __init__(self, config, root):
        if config.get('test_mode') is not True:
            raise ValueError('Mock adapter requires explicit test_mode=true')
        self.serial = config['device_serial']
        self.path = Path(root) / config.get('mock_file', 'config/mock-punches.json')
    def connect(self): return True
    def disconnect(self): pass
    def testConnection(self): return True
    def fetchUsers(self): return []
    def getSerialNumber(self): return self.serial
    def fetchPunches(self, cursor):
        events = json.loads(self.path.read_text(encoding='utf-8'))
        offset = int(cursor or 0)
        if len(events) < offset:
            raise ValueError('Mock input shrank; cursor must not be reset silently')
        return events[offset:offset+500], str(min(len(events), offset+500))
