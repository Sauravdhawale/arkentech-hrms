# sHRMS — Phase 2 Attendance Architecture Changes

We are continuing development of the existing **sHRMS project**.

This is NOT a new project.

Phase 1 is already largely completed and the database already contains real data.

## CRITICAL RULE

Do not unnecessarily modify completed Phase 1 functionality.

Preserve:

- Existing employees
- Existing users
- Existing departments
- Existing designations
- Existing roles
- Existing permissions
- Existing company settings
- Existing employee IDs
- Existing uploaded files
- Existing dashboard
- Existing UI design system
- Existing database records

Do not run destructive database operations.

Never use:

```text
DROP DATABASE
DROP TABLE
TRUNCATE
migrate:fresh
database reset
mass DELETE

```

Only make the specific Phase 2 changes described below.

---

# 1. COMPANY SETTINGS — ADD ATTENDANCE SECTION

Inside the centralized **Company Settings** page, add a new section:

```text
Company Settings

Company
    Company Profile

Organization
    Departments
    Designations

Attendance
    Attendance Settings
    Shifts
    Time Policies
    Biometric Integration

Access Control
    Roles & Permissions

```

Follow the existing Company Settings layout.

Use a settings-style left navigation with the selected section displayed in the right-side content area.

Do NOT create several unnecessary global sidebar items for these settings.

---

# 2. ATTENDANCE SETTINGS

Create:

**Company Settings → Attendance → Attendance Settings**

This should control company-level attendance behavior.

Include:

### Attendance Mode

Allow:

- Manual Attendance
- Manual + Biometric

Manual Attendance must always remain available.

Biometric attendance is optional.

---

# 3. BIOMETRIC ENABLE / DISABLE

Add a setting:

```text
Enable Biometric Attendance
[ ON / OFF ]

```

Default should be OFF unless an existing setting already exists.

## When Biometric = OFF

Attendance module must still work.

Show:

- Attendance Dashboard
- Manual Attendance
- Manual Attendance Upload
- Attendance Requests
- Daily Work Status
- Check-In / Check-Out Log
- Late Arrival & Early Departure
- Monthly Summary
- Time Policies

Hide biometric-specific options such as:

- Biometric Devices
- Biometric Employee Mapping
- Device Sync
- Device Logs

Do NOT disable the overall Attendance module.

---

## When Biometric = ON

Show additional Attendance options:

```text
Biometric Devices
Biometric Employee Mapping
Biometric Sync Logs

```

Biometric options should dynamically appear based on the company setting.

Do not hardcode this only in frontend JavaScript.

Backend permission/settings validation must also check whether biometric functionality is enabled.

---

# 4. ATTENDANCE SIDEBAR

Use a clean Attendance submenu inspired by the supplied Horilla UI reference.

Recommended structure:

```text
Attendance
    Dashboard
    Manual Attendance
    Attendance Requests
    Daily Work Status
    Check-In / Check-Out Log
    Late Arrival & Early Departure
    Monthly Summary
    Time Policies

    Biometric Devices       ← only if biometric enabled
    Biometric Mapping       ← only if biometric enabled
    Biometric Sync Logs     ← only if biometric enabled

```

Avoid making the main sidebar overcrowded.

The Attendance section can expand/collapse.

---

# 5. ATTENDANCE DASHBOARD

Create/update a professional Attendance Dashboard inspired by the supplied screenshot.

Do NOT copy Horilla branding.

Use our existing sHRMS colors, fonts and layout.

Dashboard should include live/current database statistics.

Top cards:

- Present Today
- On Time
- Late Arrival
- Absent
- Pending Attendance Requests
- Overtime

Optional cards where useful:

- On Leave
- Half Day

Never use fake hardcoded values.

---

# 6. ATTENDANCE DASHBOARD CHARTS

Include useful widgets such as:

## Check-In Distribution

Example:

```text
07:00
08:00
09:00
10:00

```

Show how many employees checked in during each time period.

---

## Department Attendance

Show attendance rate by department.

Example:

```text
Sales             92%
Operations        96%
Quality           88%
MIS               95%

```

Use actual database records.

---

## Attendance Exceptions

Show:

- Late arrivals
- Missing check-outs
- Absentees
- Pending attendance requests

Keep UI clean and similar in density to the supplied reference.

---

# 7. MANUAL ATTENDANCE

Manual Attendance must work regardless of biometric setting.

Super Admin should be able to manually add attendance.

Create:

```text
Attendance → Manual Attendance

```

Allow:

- Select Employee
- Attendance Date
- Check-In
- Check-Out
- Shift
- Status
- Notes
- Reason

````

If check-in and check-out are entered, calculate:

- Working Hours
- Late Duration
- Early Departure
- Overtime

using the assigned shift.

---

# 8. ADMIN CHECK-IN / CHECK-OUT BUTTON

Add a clear action button for Super Admin.

Example:

```text
+ Check In / Check Out

````

This button is for **Admin attendance actions** during the current Super Admin development stage.

On click, open a professional modal.

Fields:

```text
Employee
Action:
    Check In
    Check Out

Date
Time
Attendance Source
Notes

```

Attendance Source should automatically be:

```text
Admin Manual

```

when entered by administrator.

The action should be logged.

Do not silently overwrite an existing punch.

If a punch already exists, warn the admin.

---

# 9. MANUAL ATTENDANCE UPLOAD

Create an attendance import option:

```text
Attendance → Manual Attendance

```

Button:

```text
Upload Attendance

```

Allow:

- CSV
- XLSX if current project stack safely supports it

Provide a downloadable/import format structure.

Suggested columns:

```text
Employee ID
Date
Check In
Check Out

```

Optional:

```text
Status
Shift
Notes

```

---

# IMPORT VALIDATION

Before saving uploaded attendance:

Validate:

- Employee ID exists
- Date is valid
- Time is valid
- Employee is active
- Duplicate attendance is detected
- Check-out is logically after check-in
- Overnight shift is handled correctly

Show an import preview before final confirmation.

Example:

```text
Valid rows: 91
Warnings: 4
Invalid: 2

```

Admin should see errors before importing.

Do not silently ignore invalid records.

---

# 10. ATTENDANCE RECORD SOURCE

Every attendance/punch record should identify where it came from.

Examples:

```text
Manual
Admin Manual
CSV Import
Biometric
API
Employee Web

```

Use one consistent source architecture.

This is very important for audit and future troubleshooting.

---

# 11. CHECK-IN / CHECK-OUT LOG

Create:

```text
Attendance → Check-In / Check-Out Log

```

Follow the UI style shown in the supplied reference.

Columns:

- Employee
- Employee ID
- Department
- Date
- Check-In
- Check-Out
- Shift
- Source
- Device
- Working Hours
- Status

Filters:

- Employee
- Department
- Date Range
- Shift
- Source
- Device

Search should work.

---

# 12. DAILY WORK STATUS

Create a monthly calendar-grid style view similar to the supplied reference.

Rows:

```text
Employee Name

```

Columns:

```text
1 2 3 4 5 ... 31

```

Statuses can be represented compactly.

Examples:

```text
P  = Present
A  = Absent
L  = Leave
HP = Half Day Present
H  = Holiday
WO = Week Off

```

Include a legend.

Example colors can be used through the existing design system.

Do not copy Horilla's exact colors if they conflict with our application.

---

# 13. LATE ARRIVAL & EARLY DEPARTURE

Create:

```text
Attendance → Late Arrival & Early Departure

```

Columns:

- Employee
- Type
- Attendance Date
- Scheduled Time
- Actual Time
- Check-In
- Check-Out
- Duration
- Shift
- Actions

Types:

```text
Late Arrival
Early Departure

```

Use shift configuration and grace time when calculating this.

---

# 14. MONTHLY ATTENDANCE SUMMARY

Create a Monthly Summary similar in structure to the supplied reference.

Top summary cards:

- Employees
- Total Present
- Total Absent
- Paid Leave
- Unpaid Leave
- Working Days
- Week Off
- Holidays
- Overtime

Below this, show employee-level report.

Suggested columns:

- Employee
- Employee ID
- Department
- Present
- Absent
- Paid Leave
- Unpaid Leave
- Half Day
- Working Days
- Week Off
- Holiday
- Late Count
- Working Hours
- Overtime

Allow filters:

- Employee
- Department
- Month
- Date Range

Provide export support if the project already has an export mechanism.

---

# 15. TIME POLICIES

Create:

```text
Company Settings
    Attendance
        Time Policies

```

and allow Attendance module to link to this page.

Use a UI inspired by the supplied Grace Time reference.

Time Policies should include:

## Grace Time

Allow admin to create:

- Grace Period Name
- Allowed Time
- Apply to Check-In
- Apply to Check-Out
- Assigned Shift
- Default Policy
- Active

Example:

```text
Allowed Time: 10 minutes
Apply to Check-In: Yes
Apply to Check-Out: No

```

---

# 16. VALIDATION CONDITIONS

Time Policies should support future validation conditions.

For now prepare:

- Minimum working hours
- Half-day threshold
- Full-day threshold
- Late rule
- Early departure rule
- Overtime rule

Do not overcomplicate it.

Keep logic centralized and configurable.

---

# 17. SHIFT MANAGEMENT CHANGE

Shift definitions belong inside:

```text
Company Settings
    Attendance
        Shifts

```

Admin should be able to:

- Create shift
- Edit shift
- Activate/deactivate shift
- Assign shift
- See assigned employees/departments

Fields:

```text
Shift Name
Shift Code
Start Time
End Time
Grace Period
Required Working Hours
Overtime Start Rule
Status

```

Support overnight shifts.

Example:

```text
Night Shift
Start: 09:00 PM
End: 06:00 AM

```

The system must understand the end time is on the following date.

---

# 18. DEPARTMENT DEFAULT SHIFT

This is an important change.

After creating a shift, allow admin to assign that shift directly to one or more Departments.

Example:

```text
General Shift
Assigned Departments:
[x] Research
[x] Quality
[x] MIS

```

An employee in that department should inherit the department's default shift unless an individual employee shift override exists.

Create proper relationship instead of hardcoding department names.

---

# 19. SHIFT PRIORITY LOGIC

Use this priority:

```text
Employee-specific Shift
        ↓
Department Default Shift
        ↓
Company Default Shift

```

Meaning:

1. If employee has an individually assigned shift → use it.
2. Otherwise use Department default shift.
3. Otherwise use Company default shift.

Centralize this logic in one reusable function/service.

Do not repeat it throughout controllers.

---

# 20. EMPLOYEE PROFILE SHIFT OVERRIDE

Inside Employee Profile / Edit Employee, show:

```text
Attendance & Shift

```

Allow Super Admin to change an employee's shift.

Fields:

```text
Current Effective Shift
Source: Department / Individual / Company

Assign Different Shift
Effective From
Effective To (optional)
Reason

```

When an individual shift is assigned, it should override Department default shift.

Keep history.

Do not delete previous shift assignments.

---

# 21. SHIFT ASSIGNMENT HISTORY

Maintain history such as:

```text
Employee
Shift
Effective From
Effective To
Assignment Type
Assigned By
Created At

```

Assignment Type:

```text
Company
Department
Employee

```

This will help attendance calculations later.

---

# 22. COMPANY CALENDAR

Create a new **Company Calendar**.

This can be available as:

```text
Company Calendar

```

or:

```text
Company Settings → Company Calendar

```

depending on which location fits the existing UI best.

The calendar should show:

- Holidays
- Company Events
- Important Dates

---

# 23. COMPANY CALENDAR UI

Use a professional calendar interface.

At the top/right provide:

```text
+ Add Event

```

Super Admin can click the button to create a company event.

---

# 24. ADD COMPANY EVENT

Fields:

```text
Event Title
Event Type
Start Date
End Date
Start Time (optional)
End Time (optional)
All Day
Description
Location
Applicable To

```

Applicable To can support:

```text
All Employees
Department
Selected Employees

```

Possible Event Types:

```text
Company Event
Holiday
Meeting
Celebration
Training
Announcement
Other

```

Do not hardcode this unnecessarily if an extendable configuration is easy.

---

# 25. HOLIDAYS + COMPANY CALENDAR

Holiday Management should integrate with Company Calendar.

If admin creates a holiday:

```text
Diwali
02 November 2026

```

it should automatically appear in Company Calendar.

Do not create duplicate independent copies of the same holiday.

The calendar should reference the Holiday record.

---

# 26. BIOMETRIC DEVICE MANAGEMENT

Only when:

```text
Enable Biometric Attendance = ON

```

show:

```text
Attendance → Biometric Devices

```

Create a screen inspired by the supplied Biometric Devices reference.

Columns:

- Device Name
- Device Type
- Device Serial Number
- Location
- Last Sync
- Status
- Connection Status
- Actions

Buttons:

```text
+ Add Device
Sync
Test Connection

```

---

# 27. BIOMETRIC ARCHITECTURE — IMPORTANT

Our HRMS is PHP + MySQL on hosted/server infrastructure.

The biometric device may be located inside the office LAN.

Do NOT assume the public web server can directly connect to the biometric device's private LAN IP.

Use a **Bridge Agent architecture**.

Architecture:

```text
eSSL Biometric Device
        ↓
Office LAN
        ↓
Windows Attendance Bridge Agent
        ↓
HTTPS
        ↓
sHRMS API
        ↓
MySQL

```

The Windows machine where the bridge runs can communicate with the device on the local network.

The bridge then securely pushes punch records to the HRMS server.

---

# 28. WINDOWS BIOMETRIC BRIDGE

Prepare backend/API architecture for a Windows bridge application.

The bridge should eventually:

1. Connect to eSSL device.
2. Read attendance punches.
3. Convert punches into normalized records.
4. Send records to sHRMS HTTPS API.
5. Remember last successful synchronization.
6. Retry failed records.
7. Prevent duplicate uploads.

Do not attempt unsafe direct browser-device communication.

---

# 29. BIOMETRIC DEVICE CONFIGURATION

Biometric device record can contain:

```text
Device Name
Device Model
Device Serial Number
Local IP Address
Port
Location
Status
Last Sync At

```

Sensitive connection credentials must not be exposed unnecessarily in frontend output.

---

# 30. BIOMETRIC API AUTHENTICATION

Create secure machine-to-server authentication.

Recommended:

```text
Device/Bridge API Token

```

Requirements:

- Unique token
- Store hashed/token-safe representation if possible
- Token can be revoked/regenerated
- Associate token with configured bridge/device/company
- HTTPS only

Do not expose database username/password to the bridge.

Do not allow the bridge direct MySQL access.

---

# 31. BIOMETRIC PUNCH API

Prepare endpoint similar to:

```text
POST /api/attendance/biometric/punches

```

Request should support batches.

Example normalized information:

```text
Device Serial
Biometric User ID
Punch Timestamp
Punch Type
Source

```

The server should determine/match the HRMS employee.

Do not trust arbitrary Employee IDs from unauthenticated requests.

---

# 32. BIOMETRIC EMPLOYEE MAPPING

Biometric devices often use their own user/enrollment IDs.

Create mapping:

```text
Employee
Employee ID
Biometric User ID
Device
Status

```

Example:

```text
Saurav Dhawale
EMP0012
Biometric ID: 44
Device: Main Office

```

Do not require employee records to use biometric IDs as the HRMS primary key.

Keep them separate.

---

# 33. DUPLICATE BIOMETRIC PUNCH PROTECTION

Biometric synchronization can resend the same record.

The server must detect duplicates.

Use a reliable unique identity based on appropriate fields such as:

```text
Device
Biometric User
Punch Timestamp

```

or a generated source punch identifier when the hardware supplies one.

Repeated sync must NOT create duplicate attendance punches.

---

# 34. RAW PUNCHES VS ATTENDANCE

Do not directly store only one Check-In and one Check-Out value from the biometric device.

Maintain raw punch data.

Architecture:

```text
Biometric Punch Logs
        ↓
Attendance Processing
        ↓
Daily Attendance Record

```

Example raw punches:

```text
09:01
13:04
14:01
18:22

```

This allows future break/lunch/OT rules.

Then derive daily attendance from those punches.

---

# 35. BIOMETRIC SYNC LOGS

Create:

```text
Attendance → Biometric Sync Logs

```

Show:

- Device
- Sync Started
- Sync Completed
- Records Received
- Records Imported
- Duplicates
- Failed
- Status
- Error Message

This is essential for troubleshooting.

---

# 36. OFFLINE SUPPORT

The office internet may temporarily fail.

The bridge should eventually queue punches locally.

When connectivity returns:

```text
Queued Punches
        ↓
Retry
        ↓
HRMS API

```

The server must still deduplicate them.

Prepare the API for this architecture.

---

# 37. ATTENDANCE PROCESSING

Use one centralized attendance calculation service.

It should consider:

```text
Employee Shift
Department Shift
Company Shift
Grace Period
Check-In
Check-Out
Holiday
Approved Leave
Working Hours
Late Arrival
Early Departure
Overtime

```

Do not calculate these rules separately in every page.

---

# 38. ATTENDANCE REQUESTS

Create Attendance Requests similar to the supplied reference.

Used when admin later allows attendance corrections/regularization.

Status:

```text
Pending
Validated
Rejected

```

Super Admin should be able to:

- View
- Approve
- Reject
- Edit where appropriate

Do not implement an employee portal workflow yet unless it already exists.

---

# 39. UI DIRECTION

Use the supplied screenshots as UI/UX inspiration.

Important characteristics:

- Clean white content area
- Compact professional cards
- Rounded cards
- Search bar
- Filter button
- Action button
- Status badges
- Responsive data tables
- Dashboard metric cards
- Chart sections
- Monthly attendance grid
- Settings tabs
- Professional empty states

Do NOT copy:

- Horilla logo
- Horilla name
- Their exact branding
- Their exact icons/colors

Use current sHRMS branding and existing application layout.

---

# 40. DO NOT CHANGE PHASE 1 UNNECESSARILY

Do not redesign:

- Login
- Employee List
- Company Profile
- Departments
- Designations
- Roles & Permissions
- Existing Dashboard

unless a minor integration change is required.

The priority is implementing these new Phase 2 functions without disturbing completed work.

---

# IMPLEMENTATION ORDER

Follow exactly this order.

## STEP 1

Inspect existing code/database.

Document what already exists.

---

## STEP 2

Add:

```text
Company Settings
    Attendance
        Attendance Settings
        Shifts
        Time Policies
        Biometric Integration

```

---

## STEP 3

Implement biometric enable/disable setting.

Test conditional menu rendering.

---

## STEP 4

Implement Shift Management.

Include:

- Company default shift
- Department default shift
- Employee override
- Shift history

---

## STEP 5

Implement Time Policies.

Include grace time and validation rules.

---

## STEP 6

Implement Manual Attendance.

Include admin Check-In / Check-Out.

---

## STEP 7

Implement CSV/XLSX Attendance Upload with preview and validation.

---

## STEP 8

Implement attendance pages:

- Dashboard
- Attendance Requests
- Daily Work Status
- Check-In / Check-Out Log
- Late Arrival & Early Departure
- Monthly Summary

---

## STEP 9

Implement Company Calendar and Add Event.

Integrate Holidays.

---

## STEP 10

Implement biometric database/backend architecture.

Include:

- Devices
- Employee biometric mapping
- API tokens
- Punch endpoint
- Raw punch logs
- Sync logs
- Deduplication

---

## STEP 11

Prepare bridge integration specification for eSSL.

Do not fake device communication if no real eSSL device/API/SDK is currently available.

Build the server side properly and clearly identify what the Windows bridge must send.

---

# DATABASE SAFETY

Any required migration must be additive.

Before creating any table:

Check whether equivalent functionality/table already exists.

Possible tables if not already present:

```text
attendance_settings
shifts
department_shift_assignments
employee_shift_assignments
attendance_records
attendance_punches
attendance_requests
time_policies
holidays
company_events
biometric_devices
biometric_employee_mappings
biometric_api_tokens
biometric_sync_logs

```

These are architectural suggestions.

Do not blindly create all of them if equivalent current tables already exist.

Reuse the existing schema where appropriate.

---

# PERMISSIONS

Reuse existing Roles & Permissions.

Add permissions only where necessary:

```text
attendance.view
attendance.manage
attendance.manual
attendance.import
attendance.approve

shifts.view
shifts.manage

time_policies.view
time_policies.manage

biometric.view
biometric.manage
biometric.sync

calendar.view
calendar.manage

```

Super Admin gets all permissions.

Do not recreate existing roles.

---

# FINAL TESTING

Verify:

## Existing System

- Login still works
- Existing employee data remains unchanged
- Existing departments remain unchanged
- Existing designations remain unchanged
- Roles remain unchanged
- Company Settings still works

## Attendance

- Attendance Dashboard loads
- Manual attendance works
- Admin Check-In works
- Admin Check-Out works
- Attendance upload validates data
- Attendance source is recorded
- Monthly summary works
- Daily Work Status works
- Late/Early calculation works
- Overtime calculation works

## Shifts

- Shift can be created
- Department default shift works
- Employee override works
- Company fallback works
- Historical assignment remains intact
- Overnight shift works

## Biometric

When OFF:

- Biometric menu is hidden
- Manual Attendance still works

When ON:

- Biometric menu becomes visible
- Device management is available
- Mapping is available
- Secure API accepts valid authenticated punch data
- Duplicate punches are rejected/ignored safely
- Invalid API token is rejected
- Raw punches remain available for audit

## Company Calendar

- Calendar loads
-

* Add Event works

1. Holiday appears on calendar
2. Department event works
3. Company-wide event works

---

# FINAL REPORT

After completing the requested changes, provide:

1. Existing functionality reviewed
2. Phase 1 files changed
3. Phase 2 files created
4. Phase 2 files modified
5. Database migrations created
6. Tables/columns added
7. Routes created
8. APIs created
9. Attendance calculations implemented
10. Shift resolution logic implemented
11. Biometric architecture implemented
12. Remaining work required for real eSSL hardware
13. Testing performed
14. Confirmation that existing data was preserved

Do not proceed to unrelated modules after completing this scope.