# sHRMS — Phase 1 Adjustment + Phase 2 Core HR

We are continuing development of the existing **sHRMS project**.

This is **NOT a fresh project**.

A significant portion of **Phase 1 is already completed**, and the database already contains real/current data.

## CRITICAL RULE — PRESERVE EXISTING WORK AND DATA

Before doing anything:

1. Inspect the current project.
2. Inspect the current database schema.
3. Inspect existing routes/pages/components.
4. Inspect existing Company Settings, Departments, Designations, Roles & Permissions.
5. Inspect existing employees and other stored data.
6. Understand what is already working before making changes.

### DO NOT:

- Rebuild Phase 1 from scratch.
- Delete existing database records.
- Reset/truncate tables.
- Drop working tables.
- Rename tables unnecessarily.
- Change existing IDs.
- Replace existing data with dummy/sample data.
- Recreate users/employees/departments/designations.
- Modify existing records unless required by the requested feature.
- Change the overall existing HRMS design unnecessarily.
- Break existing URLs or functionality if they can be reused.
- Run destructive migrations.

There is already data inside the system.

**Existing data must remain exactly as it is unless a specific change is required.**

Any new database migration should preferably be **additive and backward-compatible**.

---

# CURRENT DEVELOPMENT STATUS

Phase 1 is mostly completed.

Current Phase 1 includes:

- Authentication
- Super Admin access
- Dashboard
- Employee Management
- Company Profile
- Departments
- Designations
- Roles & Permissions
- Company Settings
- Existing employee/database data

Do not redesign or rebuild these modules unnecessarily.

We only need the following Phase 1 structural adjustment.

---

# PHASE 1 CHANGE — COMPANY SETTINGS STRUCTURE

Currently the Administration sidebar may look similar to:

- Company Settings
  - Company Profile
  - Departments
  - Designations
- Roles & Permissions

I DO NOT want Company Profile, Departments, Designations, and Roles & Permissions displayed as separate main/sidebar navigation items anymore.

Instead, create **one Company Settings module/page**.

The UI should follow the general structure of the provided settings reference image.

The reference is for layout/UX inspiration only.

Do not copy unrelated settings from the reference.

---

# NEW COMPANY SETTINGS UI

Sidebar should contain only:

**Administration**

- Company Settings

When clicking:

**Company Settings**

open one centralized Settings screen.

Inside the Company Settings screen, create internal tabs/navigation for:

1. Company Profile
2. Departments
3. Designations
4. Roles & Permissions

Example:

```text
Company Settings

[ Company Profile ]
[ Departments ]
[ Designations ]
[ Roles & Permissions ]

```

Or preferably a left-side settings navigation similar to the supplied reference:

```text
Company Settings

GENERAL
   Company Profile

ORGANIZATION
   Departments
   Designations

ACCESS CONTROL
   Roles & Permissions

```

When the admin clicks an option, show that module inside the **same Company Settings layout/container**.

---

# IMPORTANT: REUSE EXISTING MODULES

Company Profile, Departments, Designations, and Roles & Permissions already exist or may already have logic implemented.

Do not rebuild their backend unnecessarily.

Reuse:

- Existing database tables
- Existing CRUD logic
- Existing validation
- Existing routes/controllers where appropriate
- Existing data
- Existing permissions
- Existing forms/components

The task is mainly to consolidate them into a professional **Company Settings interface**.

If existing routes are already being used elsewhere, keep them functional.

You may internally load those modules inside the new Company Settings shell/layout instead of destroying the current architecture.

---

# COMPANY SETTINGS PAGE LAYOUT

The page should look professional and similar in structure to the supplied Settings reference.

Suggested structure:

```text
--------------------------------------------------------
Company Settings
Manage company, organization and access configurations
--------------------------------------------------------

LEFT SETTINGS MENU          RIGHT CONTENT AREA

Company
  Company Profile

Organization
  Departments
  Designations

Access Control
  Roles & Permissions

```

The active option should be visually highlighted.

Example:

```text
Organization

> Departments
  Designations

```

Right side should display the selected content.

The page should support responsive behavior.

On mobile, the settings menu can become:

- Dropdown
- Accordion
- Offcanvas menu

depending on what best fits the current project design.

---

# COMPANY PROFILE

Do not remove current information.

Continue supporting existing fields such as:

- Company Name
- Company Logo
- Company Email
- Company Phone
- Website
- Address
- City
- State
- Country
- PIN Code

Keep existing values.

---

# DEPARTMENTS

Keep all currently saved departments.

Admin should continue to be able to:

- View departments
- Add department
- Edit department
- Activate/deactivate department
- Delete department where allowed

Do not recreate the Department system.

Only integrate it into Company Settings.

---

# DESIGNATIONS

Keep all currently saved designations.

Admin should continue to be able to:

- View designations
- Add designation
- Edit designation
- Activate/deactivate designation
- Delete designation where allowed

Maintain the existing department/designation relationship.

Do not replace existing data.

---

# ROLES & PERMISSIONS

Move **Roles & Permissions** inside Company Settings.

Location:

```text
Company Settings
    Access Control
        Roles & Permissions

```

Do not display Roles & Permissions as a separate Administration sidebar item after this change.

Reuse the existing role/permission system.

Do not redesign the RBAC architecture unless something is broken.

Existing roles/data must remain intact.

Examples may include:

- Super Admin
- CEO
- Director
- HR
- Manager
- Team Leader
- Employee

But always use existing database records.

Do not recreate them if they already exist.

---

# AFTER THIS CHANGE — START PHASE 2

Once the Company Settings restructuring is completed and tested, start:

# PHASE 2 — CORE HR

Phase 2 contains:

1. Attendance Management
2. Leave Management
3. Holiday Management
4. Shift Management

The modules should integrate with the existing employee, department, designation, role, and company data.

Do not create duplicate employee systems.

---

# 5. ATTENDANCE MANAGEMENT

Create an Attendance module.

Main navigation:

```text
Attendance

```

Possible submenu:

```text
Attendance
    Overview
    Daily Attendance
    Attendance History
    Monthly Report

```

Do not make the sidebar unnecessarily complex if the current design uses page tabs instead.

---

## Attendance Data

The Attendance system must support:

- Check-in
- Check-out
- Working hours
- Late arrival
- Early leaving
- Overtime
- Attendance status
- Attendance history
- Monthly attendance reports

---

# ATTENDANCE RECORD

An attendance record should be linked to an existing employee.

Suggested data:

- Employee ID
- Attendance Date
- Check-in Time
- Check-out Time
- Working Hours
- Late Duration
- Early Leaving Duration
- Overtime
- Attendance Status
- Shift
- Source
- Notes
- Created At
- Updated At

Possible Attendance Status:

- Present
- Absent
- Late
- Half Day
- Leave
- Holiday
- Week Off

Do not hardcode business logic everywhere.

Use a centralized attendance calculation service/helper.

---

# CHECK-IN / CHECK-OUT

Support:

### Check-in

Store:

- Employee
- Date
- Check-in timestamp

### Check-out

Store:

- Check-out timestamp

Calculate:

```text
Working Hours = Check-out - Check-in

```

But actual calculations should also consider:

- Assigned shift
- Break rules if implemented later
- Late threshold
- Early leaving threshold
- Overtime rules

Design the architecture so eSSL/device attendance can later be connected without rebuilding the Attendance module.

---

# IMPORTANT — eSSL FUTURE INTEGRATION

This HRMS is planned to work with an **eSSL attendance device** later.

Therefore attendance records should support a source field such as:

- Manual
- Web
- Admin
- eSSL
- API

Do not implement eSSL integration now unless the project already contains it.

But ensure the Attendance database architecture can accept device punches later.

---

# DAILY ATTENDANCE

Create Daily Attendance screen.

Suggested columns:

- Employee
- Employee ID
- Department
- Shift
- Check In
- Check Out
- Working Hours
- Late
- Early Leaving
- Overtime
- Status
- Actions

Filters:

- Date
- Department
- Status
- Employee
- Shift

Search should also be supported.

---

# ATTENDANCE HISTORY

Create Attendance History.

Filters:

- Employee
- Date Range
- Department
- Attendance Status
- Shift

Admin should be able to view employee attendance history.

---

# MONTHLY ATTENDANCE REPORT

Create monthly attendance reporting.

Admin selects:

- Month
- Year

Optional filters:

- Department
- Employee
- Shift

Report should include:

- Total Working Days
- Present Days
- Absent Days
- Leave Days
- Holidays
- Week Offs
- Late Count
- Half Days
- Total Working Hours
- Overtime

Use actual database calculations.

Do not hardcode report numbers.

---

# LATE ARRIVAL

Late arrival should depend on the employee's assigned shift.

Example:

```text
Shift Start: 09:00 AM
Employee Check-in: 09:18 AM

Late = 18 minutes

```

Do not permanently use 09:00 AM as the default for all employees.

Use the assigned shift.

---

# EARLY LEAVING

Example:

```text
Shift End: 06:00 PM
Employee Check-out: 05:30 PM

Early Leave = 30 minutes

```

Calculate based on assigned shift timing.

---

# OVERTIME

Overtime should be calculated only after the scheduled shift end or configured required working duration.

Prepare the architecture for future overtime approval rules.

Do not overcomplicate the current implementation.

---

# 6. LEAVE MANAGEMENT

Create a Leave module.

Suggested navigation:

```text
Leave
    Leave Requests
    Leave Types
    Leave Balances
    Leave History

```

---

# LEAVE TYPES

Admin should be able to configure leave types.

Examples:

- Casual Leave
- Sick Leave
- Paid Leave
- Unpaid Leave
- Earned Leave

But do not hardcode these as permanent records if current company data/configuration exists.

Leave Type fields can include:

- Leave Name
- Code
- Description
- Paid / Unpaid
- Annual Allocation
- Carry Forward
- Maximum Carry Forward
- Status

---

# LEAVE BALANCE

Each employee should have leave balances.

Example:

```text
Casual Leave
Allocated: 12
Used: 4
Remaining: 8

```

Balance should be calculated/stored consistently.

Do not allow negative balance unless leave type/company rules permit it.

---

# APPLY FOR LEAVE

Support leave request creation.

Fields:

- Employee
- Leave Type
- From Date
- To Date
- Full Day / Half Day
- Half-Day Period if required
- Reason
- Attachment — optional

Calculate the number of leave days automatically.

Consider:

- Holidays
- Week Off
- Half Day

Use the simplest reliable implementation compatible with the existing project.

---

# LEAVE APPROVAL

Leave requests should support:

- Pending
- Approved
- Rejected
- Cancelled

Prepare approval architecture for:

- Manager
- HR
- Super Admin

For the current stage, Super Admin must have access to manage all leave requests.

Do not redesign the existing role system.

Reuse the existing roles/permissions.

---

# LEAVE REQUEST TABLE

Suggested columns:

- Employee
- Leave Type
- From
- To
- Days
- Reason
- Applied Date
- Status
- Approved By
- Actions

Actions:

- View
- Approve
- Reject
- Cancel where permitted

---

# LEAVE HISTORY

Provide employee leave history.

Filters:

- Employee
- Leave Type
- Status
- Department
- Date Range

---

# HALF-DAY LEAVE

Support:

- First Half
- Second Half

Half-day leave should count as:

```text
0.5 day

```

and update leave balance correctly.

---

# 7. HOLIDAY MANAGEMENT

Create Holiday Management.

Suggested location:

```text
Attendance / Core HR
    Holidays

```

or use a standalone:

```text
Holidays

```

depending on the existing sidebar architecture.

Do not overpopulate the main sidebar.

---

# HOLIDAY FEATURES

Support:

- Add Holiday
- Edit Holiday
- Delete Holiday
- Activate/deactivate Holiday
- Holiday Calendar
- Year-wise Holidays

---

# HOLIDAY FIELDS

Suggested fields:

- Holiday Name
- Holiday Date
- Year
- Description
- Holiday Type
- Status

Optional Holiday Type:

- Public Holiday
- Company Holiday
- Optional Holiday

---

# HOLIDAY LIST

Admin should be able to select a year:

```text
2026
2027
2028

```

and see holidays for that year.

Do not delete old holiday records when a new year begins.

---

# HOLIDAY CALENDAR

Provide a clean calendar/list view showing company holidays.

Attendance calculations should recognize configured holidays.

---

# 8. SHIFT MANAGEMENT

Create Shift Management.

Suggested location:

```text
Company Settings
    Work Configuration
        Shifts

```

This is preferred because Shift definitions are company configuration.

However employee shift assignment and reports can be accessed from Attendance where appropriate.

---

# SHIFT TYPES

Do not permanently hardcode:

- Morning
- Evening
- Night

Admin should be able to create custom shifts.

Example shifts can include:

```text
General Shift
Morning Shift
Evening Shift
Night Shift

```

---

# SHIFT FIELDS

Create support for:

- Shift Name
- Shift Code
- Start Time
- End Time
- Required Working Hours
- Grace Period
- Late Arrival Rule
- Early Leaving Rule
- Overtime Start Rule
- Status

Optional:

- Description

---

# OVERNIGHT SHIFT SUPPORT

The system must support shifts where:

```text
Start: 09:00 PM
End: 06:00 AM

```

The system should correctly understand that the end time belongs to the next day.

Do not calculate this as a negative duration.

---

# ASSIGN EMPLOYEE TO SHIFT

Allow Super Admin to assign an existing employee to a shift.

Support:

- Employee
- Shift
- Effective From
- Effective To — optional

The current/default employee shift should be identifiable.

Do not overwrite historic shift assignments unnecessarily.

If an employee changes shifts, preserve assignment history where practical.

---

# ATTENDANCE + SHIFT INTEGRATION

Attendance calculations must use employee shift information.

Example:

```text
Employee: John
Shift: General Shift
Start: 09:00
End: 18:00

Check-in: 09:15
Late: 15 minutes

Check-out: 18:45
Overtime: 45 minutes

```

Use one centralized calculation mechanism.

---

# HOLIDAY + ATTENDANCE INTEGRATION

If an attendance date is a company holiday:

system should identify it as a Holiday where appropriate.

Do not automatically mark an employee absent on a configured holiday.

---

# LEAVE + ATTENDANCE INTEGRATION

If an approved leave exists:

Attendance/monthly report should recognize:

- Leave
- Half Day Leave

instead of simply marking the employee absent.

Build this carefully to avoid duplicate conflicting statuses.

---

# PHASE 2 PERMISSIONS

Reuse the Phase 1 Roles & Permissions system.

Add new permissions only if required.

Examples:

```text
attendance.view
attendance.manage
attendance.reports

leave.view
leave.create
leave.approve
leave.manage

holidays.view
holidays.manage

shifts.view
shifts.manage

```

Super Admin should automatically have full access.

Do not break existing role/permission records.

---

# SIDEBAR AFTER THESE CHANGES

Keep the sidebar clean.

Suggested structure:

```text
Dashboard

Employees

Attendance

Leave

Administration
    Company Settings

```

Inside Company Settings:

```text
Company
    Company Profile

Organization
    Departments
    Designations

Work Configuration
    Shifts

Access Control
    Roles & Permissions

```

Holiday placement can be:

```text
Attendance
    Holidays

```

or inside Company Settings if that better fits the existing architecture.

Choose whichever keeps navigation cleaner and consistent with the current UI.

---

# IMPORTANT — EMPLOYEE PORTAL

Do not build a completely separate Employee Portal unless it is already part of the current project.

For Phase 2, build the underlying Core HR functionality and Super Admin management screens first.

Prepare the system so employees can later:

- Check in/out
- Apply for leave
- View attendance
- View leave balance
- View holidays

But do not unnecessarily redesign the application around the employee portal right now.

---

# DASHBOARD

Do not redesign the current dashboard.

Keep the current Phase 1 dashboard.

Only add Phase 2 information if it fits the existing dashboard naturally.

Possible later/additional cards:

- Present Today
- Absent Today
- Late Today
- On Leave
- Upcoming Holiday

Use actual database values.

Do not replace existing dashboard cards unnecessarily.

---

# DATA SAFETY REQUIREMENT

This requirement is extremely important.

The system already contains data.

Before any schema change:

- Inspect the existing table.
- Check whether the column/table already exists.
- Avoid duplicates.
- Use safe migrations.
- Preserve IDs.
- Preserve relationships.
- Preserve uploaded files.
- Preserve passwords/users.
- Preserve employees.
- Preserve roles.
- Preserve departments.
- Preserve designations.
- Preserve settings.

Never use commands equivalent to:

```text
DROP DATABASE
DROP TABLE
TRUNCATE
DELETE FROM table
migrate:fresh
database reset

```

unless I specifically instruct you to do so.

Do not seed dummy records over existing production data.

---

# IMPLEMENTATION STRATEGY

Do not attempt to rewrite everything at once.

Follow this sequence.

## Step 1

Inspect and document the current Phase 1 implementation.

Identify:

- Existing database tables
- Existing settings pages
- Existing Company Profile
- Existing Departments
- Existing Designations
- Existing Roles & Permissions
- Existing routes
- Existing CRUD code
- Existing reusable UI components

---

## Step 2

Make only the requested Phase 1 adjustment:

Create centralized:

**Company Settings**

and move/integrate:

- Company Profile
- Departments
- Designations
- Roles & Permissions

into this screen.

Test it.

Make sure existing data is unchanged.

---

## Step 3

Implement:

**Shift Management**

because Attendance calculations depend on shifts.

---

## Step 4

Implement:

**Holiday Management**

because attendance calculations need holiday information.

---

## Step 5

Implement:

**Attendance Management**

including:

- Check-in/out structure
- Daily attendance
- Working hours
- Late arrival
- Early leaving
- Overtime
- Attendance history
- Monthly attendance reports

---

## Step 6

Implement:

**Leave Management**

including:

- Leave types
- Leave balances
- Leave requests
- Half day
- Approval
- Leave history

---

## Step 7

Integrate:

- Attendance ↔ Shift
- Attendance ↔ Holidays
- Attendance ↔ Approved Leave

---

## Step 8

Test all Phase 1 existing features again.

Make sure the Phase 2 changes did not break:

- Login
- Dashboard
- Employees
- Employee creation/edit
- Company Profile
- Departments
- Designations
- Roles
- Permissions

---

# UI RULES

Follow the existing project's design system.

Do not suddenly introduce a completely different UI framework.

The Company Settings page can take layout inspiration from the supplied reference image:

- Settings sidebar inside page
- Section categories
- Highlight active setting
- Large right-side content area
- Clean cards/forms
- Compact professional layout

But keep the current sHRMS branding, typography, spacing, and colors.

---

# CODE QUALITY

Use:

- Existing project architecture
- Existing database connection
- Existing authentication
- Existing session system
- Existing routing structure
- Existing UI components

Avoid:

- Duplicate CSS
- Duplicate JS
- Duplicate CRUD code
- Duplicate database tables
- Hardcoded employee information
- Hardcoded company information

Use reusable services/helpers for:

- Attendance calculations
- Shift calculations
- Leave day calculation
- Permission checks

---

# FINAL VERIFICATION

Before considering this task complete, verify:

### Phase 1

- Login still works
- Existing data remains intact
- Employees remain intact
- Departments remain intact
- Designations remain intact
- Roles remain intact
- Permissions remain intact
- Company Profile remains intact
- Dashboard remains intact

### Company Settings

- Company Profile loads inside Company Settings
- Departments load inside Company Settings
- Designations load inside Company Settings
- Roles & Permissions load inside Company Settings
- Active menu highlighting works
- Old unnecessary sidebar items are removed
- CRUD operations still work

### Phase 2

- Shift CRUD works
- Employee shift assignment works
- Overnight shifts calculate correctly
- Holidays work
- Year-wise holiday filtering works
- Attendance works
- Check-in/check-out data works
- Working hours calculate correctly
- Late timing calculates correctly
- Early leaving calculates correctly
- Overtime calculates correctly
- Monthly attendance report works
- Leave types work
- Leave balance works
- Leave application works
- Half-day leave works
- Approval/rejection works
- Leave history works
- Approved leave integrates with attendance

---

# AFTER DEVELOPMENT

When finished, provide a concise development report containing:

1. Phase 1 changes made
2. Phase 2 modules completed
3. Files created
4. Files modified
5. Database tables added
6. Database columns added
7. Migrations added
8. Routes added
9. APIs/endpoints added
10. Existing data verification
11. Tests performed
12. Any remaining issue

Most importantly:

**Do not make unnecessary changes to completed Phase 1 work.**

**Do not alter or delete my existing data.**

First make the requested Company Settings restructuring, verify that existing functionality is safe, and then proceed with Phase 2.