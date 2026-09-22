import json
import sqlite3
import hashlib
from pathlib import Path

class Queue:
    def __init__(self, path):
        Path(path).parent.mkdir(parents=True, exist_ok=True)
        self.db = sqlite3.connect(path)
        self.db.execute('PRAGMA journal_mode=WAL')
        self.db.execute('PRAGMA synchronous=FULL')
        self.db.executescript('''CREATE TABLE IF NOT EXISTS punches(
            event_key TEXT PRIMARY KEY, payload TEXT NOT NULL, acknowledged INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE IF NOT EXISTS state(key TEXT PRIMARY KEY,value TEXT NOT NULL);''')
    def cursor(self):
        row=self.db.execute("SELECT value FROM state WHERE key='cursor'").fetchone()
        return row[0] if row else None
    def bind(self, serial, adapter):
        identity=json.dumps([serial,adapter])
        with self.db:
            row=self.db.execute("SELECT value FROM state WHERE key='identity'").fetchone()
            if row and row[0]!=identity:
                raise ValueError('Queue belongs to another device/adapter; use a separate data directory')
            self.db.execute("INSERT OR IGNORE INTO state VALUES('identity',?)",(identity,))
    def enqueue(self, serial, events, cursor):
        with self.db:
            for raw in events:
                event={k:raw[k] for k in ['biometric_id','punched_at']}
                event['direction']=raw.get('direction','unknown')
                event['biometric_id']=str(event['biometric_id'])
                event['event_key']=str(raw.get('event_key') or hashlib.sha256(
                    json.dumps([serial,event],sort_keys=True).encode()).hexdigest())
                payload=json.dumps(event,sort_keys=True)
                old=self.db.execute('SELECT payload FROM punches WHERE event_key=?',(event['event_key'],)).fetchone()
                if old and old[0]!=payload: raise ValueError('Device reused an event key with different data')
                self.db.execute('INSERT OR IGNORE INTO punches(event_key,payload) VALUES(?,?)',(event['event_key'],payload))
            self.db.execute("INSERT INTO state VALUES('cursor',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",(str(cursor),))
    def pending(self):
        return [json.loads(r[0]) for r in self.db.execute('SELECT payload FROM punches WHERE acknowledged=0 ORDER BY rowid LIMIT 500')]
    def acknowledge(self, sent, result):
        if result.get('persisted') is not True: raise ValueError('Server did not confirm persistence')
        allowed={e['event_key'] for e in sent}
        keys=set(result.get('accepted',[]))|set(result.get('duplicates',[]))
        if not keys<=allowed: raise ValueError('Unexpected acknowledgement keys')
        with self.db:
            self.db.executemany('UPDATE punches SET acknowledged=1 WHERE event_key=?',[(key,) for key in keys])
        return len(keys)
