# Core HR development report

## Status

Core HR was published to GitHub and verified on 22 September 2026. The full PHP/MySQL integration and authenticated HTTP suites passed for implementation commit `7e66262b6ea4b6e343d8ef81266fa2c64167dfc7`.

Validation run: https://github.com/Sauravdhawale/arkentech-hrms/actions/runs/35736625704

**Hostinger deployment is not verified.** No live Hostinger database connection, migration or employee import has been performed by this development session. Tests used a separate disposable MySQL database.

## Phase 1 retained

Company Profile, Departments, Designations and Roles & Permissions continue inside the single Company Settings screen, using their existing routes and CRUD. Shifts is added under Work Configuration. The Phase 1 dashboard, employee identities, login credentials and existing role assignments are not recreated.

## Implemented

- Shift definitions: code, custom times, overnight shifts, break, required hours, late/early tolerance, half-day threshold, three overtime rules, weekly offs and status.
- Dated assignments: overlap prevention, optional end date, historical rule snapshots and protected started assignments. When editing a legacy shift, missing snapshots are added to its existing assignments to preserve their rules; other assignment data remains intact.
- Holidays: add/edit/status, safe deletion of future entries, year-filtered list, public/company/optional types. Optional holidays remain scheduled working days unless leave is approved.
- Attendance: check-in and optional check-out, later corrections with reason, optimistic version check, shift-aware calculations, source labels, filters, daily/history views and monthly CSV reports. No invented attendance counts.
- Leave: configurable types, paid/unpaid, annual allocation, explicit carry-forward with cap, optional negative balances, employee allocations, full/first-half/second-half requests, private attachments, approval/rejection/cancellation and filtered history.
- Integration: holidays and weekly offs are excluded from new leave requests; approved leave appears in daily/monthly attendance; cancellation releases balance without deleting history. Existing employee leave submission uses the same accounting after Core HR is enabled.
- Authorization: existing RBAC, new granular permissions, CSRF, audit entries, prepared queries, serialized approvals and protected attachment downloads.

## Storage and migration

`database/005-core-hr.sql` adds only:

| Table | Purpose |
| --- | --- |
| `hr_attendance` | Daily punches, rule snapshot, calculated durations, source, correction version |
| `hr_leave_details` | Request policy/day-period snapshot and optional attachment link |
| `hr_leave_days` | Per-date debit units, including 0.5-day periods and excluded days |

**No columns added to existing tables.** Existing shifts, rosters, holidays, leave policies and allocations remain in `hr_records`. Requests remain in `hr_requests`; uploads use `hr_files`; audits use `hr_audit`. The application installer adds new permission definitions and the `005-core-hr` migration marker. It does not assign new permissions to existing custom roles, seed employees or reset passwords.

The installer uses an advisory lock, idempotent table creation and column-presence verification. Database compatibility and preservation passed the supplied MySQL tests. Production data has not been inspected or verified here.

## Files

New:

- `database/005-core-hr.sql`
- `includes/core-hr/service.php`, `leave.php`, `attendance.php`
- `includes/core-hr/controller.php`, `view.php`, `configuration-view.php`, `attendance-view.php`, `leave-view.php`
- `tests/core_hr_calculations.php`, `core_hr.php`, `core_hr_http.py`
- `docs/PHASE-2.md`

Modified:

- `super-admin.php`
- `includes/foundation/controller.php`, `layout.php`
- `includes/leave-balances.php`, `workspace.php`, `suite-reports.php`
- `document.php`, `assets/foundation.css`
- `.github/workflows/hrms-checks.yml`
- `docs/CONTINUATION.md`

## Routes

Existing Super Admin route names reused: `shifts`, `roster`, `holidays`, `attendance`, `monthly`, `leaves`, `leave_policy`, `balances`.

New pages: `attendance_history`, `leave_history`. Company Settings uses `shifts` internally. Attendance export uses `export=1` on attendance/history/monthly with report permission.

Existing device punch reports remain accessible as `device_attendance` and `device_monthly`; raw logs and sync history keep their current URLs. No new public API endpoint was added. `api/punches.php` is unchanged.

## Accounting choices

- Times use the company timezone. An overnight check-out must include the next date.
- Breaks are a fixed deduction from elapsed minutes. Grace suppresses the flag inside the tolerance; outside it, the full late/early duration is counted.
- Overtime is after shift end, after required net hours, or the smaller excess when both conditions are required. It is calculated time, not an approved payroll payment.
- Existing saved attendance retains its shift-rule snapshot on correction.
- No automatic absence is inferred for today, future dates, unassigned shifts or dates before joining. Archived employees retain historical reporting; unscheduled post-archive dates are not marked absent.
- New leave snapshots exclude current published holidays and assigned weekly offs at submission. Later configuration edits do not rewrite submitted requests. No default weekend is assumed without a shift.
- Legacy requests keep their original inclusive calendar-day accounting. Their rows are not rewritten.
- Pending requests do not reserve balance. Approval checks the current entitlement and approved usage within a transaction. Custom approval permissions currently cover the company; manager-only team scoping is not introduced.
- Employee-specific allocation overrides the type's annual allocation; carry-forward is explicitly entered and capped, not automatically rolled over.
- A half-day leave plus completed attendance counts as 0.5 leave and 0.5 presence. The absent remainder is shown for an elapsed scheduled day without a punch. Two approved opposite halves appear as a full day of leave.
- Device raw punches are preserved separately and are **not automatically converted** to the new daily attendance records. Source labels do not imply an active device connection.

## Validation

Completed locally:

- PHP syntax lint across all 49 PHP files using PHP 8.5 WebAssembly: passed.
- Pure PHP calculation regression suite: passed (overnight, break, grace, early leaving, overtime variants, open punch, date bounds, half-day and daily/monthly integration).
- Rendering smoke checks for ten Core HR pages and configuration edit forms using disposable in-memory SQLite fixtures: passed. These check PHP rendering, not visual browser quality or MySQL behavior.
- Python HTTP test file compilation: passed.

Completed in GitHub Actions with MySQL 8 and authenticated HTTP:

- MySQL migration idempotence and snapshots proving existing users/profiles/roles/settings/uploads/records unchanged by installation.
- Database-backed CRUD, assignment snapshots, stale writes, holiday exclusions, half-day approvals, overlap/balance rejection, cancellation, saved attendance and permission tests.
- Authenticated HTTP routes, CSV, year filters, CSRF and role-denial tests.
- Full existing Phase 1 regression rerun together with Core HR.

## Deployment and remaining verification

The user approved public GitHub publication. The review branch passed the full workflow and the verified code is being promoted to main with these updated notes. Visual browser/mobile review and verification against the actual hosting configuration remain outstanding.

Deploy to the existing subdomain root `public_html/employeeportal` (do not create a nested `public_html`). Enable Core HR once from the Super Admin page to create its additive tables and permissions. No production rollout is claimed by this report.
