$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
python -m pip install -r requirements-build.txt
if ($LASTEXITCODE -ne 0) { throw 'Dependency installation failed' }
python -m PyInstaller --clean --noconfirm --onefile --name sHRMSBridge --hidden-import pythoncom --hidden-import win32com.client --hidden-import win32com.client.dynamic --hidden-import win32timezone --hidden-import win32serviceutil --hidden-import win32service --hidden-import win32event --hidden-import servicemanager src/main.py
if ($LASTEXITCODE -ne 0) { throw 'Windows executable build failed' }
Copy-Item dist/sHRMSBridge.exe ./sHRMSBridge.exe
