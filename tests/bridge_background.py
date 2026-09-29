"""Silent executable selection, independent of a Windows desktop."""
import sys, unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'bridge/src'))
from main import background_executable

class BackgroundTests(unittest.TestCase):
    def test_frozen_background_defaults_silent(self):
        with patch.object(sys, 'frozen', True, create=True), patch.object(sys, 'executable', '/bridge/sHRMSBridgeBackground.exe'):
            self.assertTrue(background_executable())
    def test_console_and_source_not_misidentified(self):
        with patch.object(sys, 'frozen', True, create=True), patch.object(sys, 'executable', '/bridge/sHRMSBridge.exe'):
            self.assertFalse(background_executable())
        with patch.object(sys, 'frozen', False, create=True):
            self.assertFalse(background_executable())

if __name__ == '__main__': unittest.main()
