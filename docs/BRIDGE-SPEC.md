# eSSL BIOMETRIC INTEGRATION — REQUIRED ARCHITECTURE

IMPORTANT:

Do NOT connect the public VPS/shared hosting server directly to the eSSL biometric device.

The eSSL biometric device will normally be connected to the office LAN and may have a private IP such as:

192.168.x.x

The public HRMS server cannot reliably access that private LAN address.

Therefore implement biometric integration using this architecture:

eSSL Biometric Device
        ↓
Office LAN
        ↓
Windows Laptop / PC
        ↓
sHRMS Attendance Bridge
        ↓
HTTPS API
        ↓
sHRMS Server
        ↓
MySQL

The bridge application will run on a Windows computer located in the same office network as the biometric device.

The bridge reads attendance punches from the device and securely sends them to the HRMS server.

Do not redesign the main HRMS around a specific device SDK.

Keep Device Communication and HRMS Attendance Processing separate.

This allows the company to change:

- eSSL device
- Device IP
- Device model
- Office network
- Windows bridge computer
- SDK/library

without rebuilding the complete HRMS Attendance module.


# SERVER-SIDE FILES

First inspect the existing PHP project structure.

Do not blindly use these exact folders if the project already follows another architecture.

Create equivalent files using the current application's conventions.

Suggested structure:

/api/attendance/bridge/
    ping.php
    punches.php
    sync-status.php

/app/services/
    BiometricAuthService.php
    BiometricPunchService.php
    AttendanceProcessingService.php

/app/controllers/
    BiometricController.php

/config/
    biometric.php

/database/migrations/
    create_biometric_devices_table.php
    create_biometric_employee_mappings_table.php
    create_biometric_api_tokens_table.php
    create_biometric_punch_logs_table.php
    create_biometric_sync_logs_table.php

If equivalent existing tables/services/routes already exist, reuse them.

Never duplicate working attendance tables.


# SERVER ENDPOINT 1 — HEALTH CHECK

Create an authenticated endpoint similar to:

GET /api/attendance/bridge/ping

Purpose:

The Windows Bridge uses it to verify:

- HRMS server reachable
- API token valid
- Company/device active
- Biometric integration enabled

Possible response:

{
    "success": true,
    "message": "Bridge connected",
    "server_time": "...",
    "device_enabled": true
}


# SERVER ENDPOINT 2 — PUSH PUNCHES

Create:

POST /api/attendance/bridge/punches

It must accept batch records.

Example conceptual payload:

{
    "device_serial": "DEVICE123",
    "records": [
        {
            "biometric_user_id": "44",
            "punch_time": "2026-09-22 09:03:15",
            "punch_type": "unknown",
            "source_punch_id": "optional"
        }
    ]
}

Do not blindly trust Employee ID coming from the bridge.

Server must map:

Device
+
Biometric User ID

to an existing HRMS Employee.


# SERVER ENDPOINT 3 — SYNC STATUS

Create:

POST /api/attendance/bridge/sync-status

The Windows Bridge can report:

- Sync started
- Sync completed
- Number received
- Number accepted
- Number duplicate
- Number failed
- Error message
- Last device timestamp


# AUTHENTICATION

The bridge must NOT receive:

- MySQL username
- MySQL password
- Hosting control-panel credentials
- Administrator password

Use a dedicated Bridge API Token.

Example request header:

Authorization: Bearer <BRIDGE_API_TOKEN>

The token should:

- Be unique
- Be revocable
- Be regeneratable
- Belong to a company/device/bridge
- Be validated server-side

Use HTTPS only.


# DATABASE

Reuse the current attendance database where possible.

Only add the following tables if equivalent structures do not already exist.

biometric_devices

Fields approximately:

id
company_id
device_name
device_model
serial_number
local_ip
port
location
status
last_sync_at
created_at
updated_at


biometric_employee_mappings

Fields:

id
employee_id
device_id
biometric_user_id
status
created_at
updated_at


biometric_api_tokens

Fields:

id
device_id
token_hash
status
last_used_at
expires_at
created_at


biometric_punch_logs

This table stores RAW punches.

Fields:

id
device_id
employee_id nullable
biometric_user_id
punch_timestamp
punch_type
source_punch_id nullable
raw_payload nullable
processed
processing_status
created_at


biometric_sync_logs

Fields:

id
device_id
sync_started_at
sync_completed_at
records_received
records_imported
duplicate_records
failed_records
status
error_message


# VERY IMPORTANT — RAW PUNCH STORAGE

Do NOT immediately reduce all biometric data to only:

Check-In
Check-Out

Store raw device punches first.

Example:

09:01
13:02
14:05
18:34

Then AttendanceProcessingService decides:

Check-In
Check-Out
Working Hours
Late
Early Departure
Overtime

This is required because future rules may need:

- Lunch breaks
- Multiple punches
- Half day
- Out-of-office punches
- Overtime
- Missed punch correction


# DUPLICATE PREVENTION

The bridge may upload the same punches multiple times.

This must be safe.

Repeated synchronization must NOT generate duplicate attendance.

Use a unique identity using available fields such as:

device_id
+
biometric_user_id
+
punch_timestamp

or preferably the vendor/device source punch ID when available.

Enforce duplicate protection on the server/database as well as application code.


# WINDOWS BRIDGE APPLICATION

Create a separate Windows Bridge application.

Preferred architecture:

sHRMSBridge/
    src/
    config/
    data/
    logs/
    lib/
    scripts/

Final deployable Windows folder should approximately contain:

sHRMSBridge.exe

config/
    appsettings.json

data/
    bridge.db

logs/
    bridge.log

lib/
    <eSSL/vendor SDK DLL files>

scripts/
    install-service.ps1
    uninstall-service.ps1
    start-service.ps1
    stop-service.ps1

README.txt


# IMPORTANT ABOUT eSSL SDK

Do NOT invent SDK library names.

The user/company will provide the SDK/library supplied for the actual eSSL model.

Create a DeviceAdapter interface/abstraction.

For example:

DeviceAdapter
    connect()
    disconnect()
    testConnection()
    fetchUsers()
    fetchPunches()
    getSerialNumber()

Then create:

ESSLDeviceAdapter

using the SDK actually supplied by eSSL.

This allows a different device implementation later without rewriting the bridge.


# WINDOWS CONFIG FILE

Create:

config/appsettings.json

Example structure:

{
    "server": {
        "base_url": "https://hrms.example.com",
        "api_token": "SET_DURING_INSTALLATION"
    },

    "device": {
        "ip": "192.168.1.201",
        "port": 4370,
        "device_id": "MAIN-OFFICE"
    },

    "sync": {
        "interval_seconds": 60,
        "batch_size": 500
    }
}

Do not commit real production tokens into GitHub.


# LOCAL QUEUE DATABASE

The Windows Bridge must work even if internet temporarily fails.

Use a small local queue database.

Recommended:

SQLite

File:

data/bridge.db

Flow:

eSSL
 ↓
Bridge reads punches
 ↓
Store locally
 ↓
Attempt HRMS API upload
 ↓
Success → mark synced
Failure → keep queued
 ↓
Retry later

This protects attendance during internet outages.


# BRIDGE SYNC LOGIC

The Bridge must remember:

- Last successful device synchronization
- Last uploaded timestamp/record
- Queued records
- Failed API requests
- Last HRMS connection
- Last device connection

Do not download complete device history every minute if incremental synchronization is possible.


# WINDOWS SERVICE

The Bridge should be able to run continuously without the user manually opening it every day.

Preferred deployment:

Windows Service

Example service name:

sHRMS Attendance Bridge

The service should:

1. Start when Windows starts.
2. Connect to configured eSSL device.
3. Poll/sync punches.
4. Queue records locally.
5. Push records to HRMS.
6. Retry failures.
7. Write local logs.


# INSTALL SERVICE SCRIPT

Provide:

scripts/install-service.ps1

It should install:

sHRMSBridge.exe

as:

sHRMS Attendance Bridge

and configure automatic startup.

Also provide:

uninstall-service.ps1
start-service.ps1
stop-service.ps1


# BRIDGE LOGGING

Store logs in:

logs/bridge.log

Log:

Bridge started
Device connection success/failure
Device IP
Punches fetched
Punches queued
API connectivity
Records uploaded
Duplicate response
Failed records
Retry attempts

Never log the complete API token.


# DEVICE CONNECTION STATUS

HRMS → Biometric Devices should show:

Device Name
Location
Device Serial
Last Sync
Last Successful Punch
Bridge Online/Offline
Device Online/Offline
Status

Bridge should periodically call the server so HRMS can determine whether it is alive.


# EMPLOYEE MAPPING

Provide HRMS screen:

Attendance
    Biometric Mapping

Columns:

Employee
Employee ID
Department
Biometric User ID
Device
Mapping Status

Admin must be able to manually map device users to existing HRMS employees.

Do NOT create duplicate employees from biometric device users automatically without administrator confirmation.


# OPTIONAL DEVICE USER DISCOVERY

If SDK supports listing enrolled users:

Bridge
 ↓
Get device users
 ↓
Send user list to HRMS
 ↓
Admin maps each biometric ID to Employee

Example:

Device User 44
    ↓
Employee EMP0025


# PROCESSING PIPELINE

Use this processing architecture:

eSSL Device
    ↓
RAW Device Punch
    ↓
Windows Bridge Local Queue
    ↓
HTTPS API
    ↓
biometric_punch_logs
    ↓
Employee Mapping
    ↓
AttendanceProcessingService
    ↓
Shift Resolver
    ↓
Time Policy
    ↓
Leave/Holiday Validation
    ↓
Final attendance_records


# SHIFT RESOLUTION

AttendanceProcessingService must resolve shift in this order:

Employee-specific Shift
        ↓
Department Default Shift
        ↓
Company Default Shift

Then apply:

Grace Time
Late Rule
Early Departure Rule
Overtime Rule


# BIOMETRIC OFF MODE

If Company Settings:

Enable Biometric Attendance = OFF

then:

- Bridge API must reject/ignore biometric attendance according to safe application rules.
- Biometric Devices menu is hidden.
- Biometric Mapping menu is hidden.
- Sync Logs menu is hidden.

But:

Manual Attendance
Admin Check-In/Check-Out
CSV/XLSX Upload

must continue working normally.


# DELIVERY REQUIREMENT

At the end of biometric implementation, provide TWO separate deployable packages.

PACKAGE 1 — SERVER

Clearly list all files that must be uploaded/deployed to the HRMS hosting/server.

Example:

server-package/
    api/
    app/
    database/
    config/
    README-SERVER.txt


PACKAGE 2 — WINDOWS BRIDGE

Provide:

windows-bridge/
    sHRMSBridge.exe
    config/
    data/
    logs/
    lib/
    scripts/
    README-WINDOWS.txt


# README-SERVER.txt

Explain:

1. Files to upload
2. Database migration procedure
3. Environment variables/config required
4. API token creation
5. How to enable biometric integration
6. How to register a device
7. How to test API


# README-WINDOWS.txt

Explain:

1. Minimum Windows requirements
2. Which folder to copy
3. Where eSSL SDK DLLs must be placed
4. How to configure device IP/port
5. How to configure HRMS URL
6. How to configure API token
7. How to test device
8. How to test HRMS API
9. How to install Windows Service
10. How to start/stop service
11. Where logs are stored
12. Troubleshooting steps


# DO NOT FAKE HARDWARE INTEGRATION

If the actual eSSL SDK/device is not currently available:

Complete:

- Bridge application architecture
- Server APIs
- Database
- Authentication
- Queue
- Sync engine
- DeviceAdapter abstraction
- Mock/Test Device Adapter

But clearly mark:

ESSLDeviceAdapter

as requiring the actual SDK/library for the specific eSSL model.

Do not claim real hardware synchronization has been tested when no device/SDK is available.