# sHRMS Windows Attendance Bridge

Copy the complete `sHRMSBridge` folder from the Windows build artifact to `C:\sHRMSBridge`.
The package includes a Windows executable built from the source in this folder.

## Setup
1. On HRMS, enable Core HR and the Attendance Upgrade, then select Manual + Biometric in Company Settings → Attendance Settings.
2. Register the exact device serial under Attendance → Biometric Devices. Map each device user ID to the correct HR employee.
3. Generate a device token. Copy `config/config.example.json` to `config/config.json` and set the HTTPS server URL, token and exact device serial.
4. Run `sHRMSBridge.exe test-api` in an elevated terminal.
5. Integrate the actual vendor SDK for your eSSL model into `src/adapters.py` and rebuild using `scripts/build.ps1`. Run `sHRMSBridge.exe test-device` before installing a service.
6. Run `scripts/install.ps1` as Administrator, then `scripts/start.ps1`. Startup is automatic. Stop/remove using the supplied scripts.

## Hardware limitation
**The eSSL adapter deliberately fails until the correct vendor SDK is integrated.** No SDK was supplied. This package does not claim a real device connection. Put licensed vendor libraries in `lib/` only after reviewing their redistribution terms. Match the SDK architecture and driver prerequisites when rebuilding. The `DeviceAdapter` contract separates vendor calls from queue/HTTPS processing.

## Explicit test mode
Use a separate test device/token and data directory. Set `adapter` to `mock` and `test_mode` to `true`. Add synthetic events to `config/mock-punches.json` (an array of biometric_id, punched_at in company-local `YYYY-MM-DD HH:MM:SS`, direction in/out/unknown and stable event_key). Mock mode never represents live device connectivity. Do not use a production employee mapping for mock punches.

## Queue and retries
SQLite WAL with FULL durability stores events and the read cursor in one transaction. Events are acknowledged only after the server confirms persistent acceptance or duplication. Retries retain stable event keys. A server outage retains the queue and backs off up to 15 minutes. Device read failures still allow already queued punches to upload. Keep the same data directory across restarts; never delete it to fix a sync problem.

HTTPS certificate verification is mandatory. Redirects are rejected to prevent forwarding tokens. The bridge needs no SQL or administrator credentials. The installer limits folder access to SYSTEM and local administrators. Logs rotate in `logs/bridge.log` and omit API tokens, payloads and exception text. Review logs locally.

## Commands / troubleshooting
- `test-api`: verifies HTTPS, token and serial. 401 means token revoked/invalid or device disabled; 403 means biometrics disabled; 422 means invalid payload; 503 means retry later.
- `test-device`: verifies the configured adapter. The default eSSL adapter reports SDK required.
- `once`: one read/upload/status cycle; `run`: foreground loop; `service`: Windows Service entry point.
- Bridge heartbeat and device reachability are separate in HRMS. A recent heartbeat does not prove device connectivity.
- Connection test and Sync buttons queue commands for the bridge; shared hosting never connects to a private LAN IP.
- Unknown employee mappings keep raw punches for review. Manual corrections are protected. Resolve mappings, then use Retry pending raw punches on the HRMS raw log or sync page.

## Build
Windows with Python 3.12: run `scripts/build.ps1`. Requirements are pinned in requirements-build.txt. The GitHub Windows job builds and packages the executable. The build test verifies CLI startup; actual Windows Service installation and physical eSSL connectivity require testing on the target office PC.
