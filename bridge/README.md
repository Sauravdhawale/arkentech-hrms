# sHRMS Windows Attendance Bridge

Copy the complete `sHRMSBridge` folder from the Windows build artifact to `C:\sHRMSBridge`.
The package includes a Windows executable built from the source in this folder.

## Setup
1. On HRMS, enable Core HR and the Attendance Upgrade, then select Manual + Biometric in Company Settings → Attendance Settings.
2. Register the exact device serial under Attendance → Biometric Devices. Map each device user ID to the correct HR employee.
3. Generate a device token. Copy `config/config.example.json` to `config/config.json` and set the HTTPS server URL, token and exact device serial.
4. Run `sHRMSBridge.exe test-api` in an elevated terminal.
5. Keep eTimeTrackLite installed. The default `sdk_transport` is now `activex`, which uses its existing `Interop.zkemkeeper.DLL` and `AxInterop.zkemkeeper.DLL`. The default `sdk_directory` is `C:\Program Files (x86)\essl\eTimeTrackLite`; set this optional configuration field if installed elsewhere. Do not re-register or replace DLLs for this update.
6. From a logged-in Windows desktop, run `./sHRMSBridge.exe test-device`, then `./sHRMSBridge.exe once`. Verify HRMS Raw Punch Logs and Biometric Sync Logs. Run `./sHRMSBridge.exe run` for repeated sync, keeping the laptop awake and terminal open.

## ActiveX update and hardware verification
The office X2008 connected successfully using a Windows Forms ActiveX control in PowerShell. This release uses that initialization approach with a fresh STA helper process per operation. The installed vendor application is not launched. The helper receives device connection settings only, never the HRMS token, and checks the physical serial before reading attendance.

Windows PowerShell 5.1 and the installed SDK wrappers are required. The helper is embedded in the EXE. It resets its inherited DLL search override and starts in the vendor installation directory. A small connection window may briefly appear, matching the tested initialization sequence. Connection failures include the numeric SDK error. It does not change the PowerShell execution policy, SDK registration or installed files. Machine connection has been demonstrated in the diagnostic test; attendance reads in this packaged build still require an office test.

ActiveX mode currently supports foreground `run` on a logged-in desktop. Windows Service mode is explicitly blocked until session-zero operation is verified. Do not run install/start service scripts for ActiveX mode. Existing installations requiring direct COM may select `"sdk_transport": "com"`; that retains the original adapter.

The adapter reads serial and attendance logs only: it does not clear punches, enroll users, read biometric templates, change the clock or disable the device. Vendor files are not redistributed.

## Explicit test mode
Use a separate test device/token and data directory. Set `adapter` to `mock` and `test_mode` to `true`. Add synthetic events to `config/mock-punches.json` (an array of biometric_id, punched_at in company-local `YYYY-MM-DD HH:MM:SS`, direction in/out/unknown and stable event_key). Mock mode never represents live device connectivity. Do not use a production employee mapping for mock punches.

## Queue and retries
SQLite WAL with FULL durability stores events and the read cursor in one transaction. Events are acknowledged only after the server confirms persistent acceptance or duplication. Retries retain stable event keys. A server outage retains the queue and backs off up to 15 minutes. Device read failures still allow already queued punches to upload. Keep the same data directory across restarts; never delete it to fix a sync problem.

HTTPS certificate verification is mandatory. Redirects are rejected to prevent forwarding tokens. The bridge needs no SQL or administrator credentials. The installer limits folder access to SYSTEM and local administrators. Logs rotate in `logs/bridge.log` and omit API tokens, payloads and exception text. Review logs locally.

## Commands / troubleshooting
- `test-api`: verifies HTTPS, token and serial. 401 means token revoked/invalid or device disabled; 403 means biometrics disabled; 422 means invalid payload; 503 means retry later.
- `test-device`: verifies the configured adapter. The eSSL adapter loads the installed ZKEM SDK and checks the actual serial; failures now identify missing DLLs, registration or connection problems.
- `once`: one read/upload/status cycle; `run`: foreground loop; `service`: Windows Service entry point.
- Bridge heartbeat and device reachability are separate in HRMS. A recent heartbeat does not prove device connectivity.
- Connection test and Sync buttons queue commands for the bridge; shared hosting never connects to a private LAN IP.
- Unknown employee mappings keep raw punches for review. Manual corrections are protected. Resolve mappings, then use Retry pending raw punches on the HRMS raw log or sync page.

## Build
Windows with Python 3.12: run `scripts/build.ps1`. Requirements are pinned in requirements-build.txt. The GitHub Windows job builds and packages the executable. The build test verifies CLI startup; actual Windows Service installation and physical eSSL connectivity require testing on the target office PC.

## ZKEM configuration and existing installations
Keep your working `config/config.json`, `data/` queue and logs when replacing the EXE. Do not overwrite them with examples. The device communication password, if configured on the machine, is a separate optional integer `device_password`; it is not the HRMS API token. `machine_number` defaults to 1.

Punch directions default to `unknown` because device in/out modes can vary. After verifying the device convention, an optional `punch_direction_map` such as `{"0":"in","1":"out"}` can be set before the first sync. Changing it later can conflict with already queued events; do not delete the queue to work around this.

The adapter rereads retained device logs each cycle and deduplicates stable identities in the durable queue. This preserves delayed and same-second punches, at the cost of reading full device history. `max_device_records` defaults to 200000; exceeding it fails without queuing a partial batch. Match the machine clock to company local time. First sync may queue historical records; review the device's retained history before running `once` or `run`. `test-device` reads only the serial and never uploads attendance.

SDK contract tests use doubles, not the uploaded vendor DLLs or a physical machine. A successful CI build proves packaging, not live connection or service operation.
