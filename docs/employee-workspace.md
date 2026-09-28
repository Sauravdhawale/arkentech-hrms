# Employee workspace

This update extends the existing `employee.php` workspace. It does not recreate employee, attendance, leave, payroll or organization tables. Existing module records and employee IDs remain in place.

## Deployment and setup

Deploy the tested GitHub `main` revision through the existing Hostinger Git deployment. Existing Foundation, Core HR and Attendance upgrades must already be enabled. No new SQL import is required for this update.

- Employees sign in through the existing login page and open `employee.php`.
- Super Admin continues to use `super-admin.php`.
- Company Settings → Roles & Permissions controls permission grants. A designation does not grant permissions.
- Company Settings → Leave Approval Routes configures one to three eligible approvers per employee, in sequence or any-one mode. Approvers need `leave.approve` and an active login. Applicants cannot approve their own leave.
- Without an explicit route, an eligible reporting manager is used. If no manager is assigned, the active Super Admin queue is used. An invalid assigned manager blocks submission with an actionable message.
- New applications snapshot their route. Later reporting/route changes affect future requests only. Super Admin can resolve a stuck request with a reasoned override. Legacy pending applications remain in the Super Admin queue.
- Company Settings → Attendance Mode allows employee web punches. This is off by default; enabling it does not disable biometric attendance. Punches use company timezone and server time, with existing calculations and audit records.
- Birthday sharing is off by default. The preference on Leave Approval Routes allows colleague names and birthday day/month on the employee dashboard; birth years are not displayed.

## Personal information and workflow

Employees can view their own attendance, effective shift, leave balances/history, salary, published payslips, documents, tasks and performance records. Personal contact fields and profile photo are editable; identity, employee code, department, designation, reporting and permission fields remain administrator-controlled.

Inbox and the header bell show request status changes, assigned approvals, tasks, published reviews, announcements and published payslips. Read/archive state belongs to the signed-in user. Updates appear on page reload.

Leave remains pending until the required approval stages finish. Pending leave does not reserve balance. The final approval rechecks balance and overlap under the existing transaction lock. Approval decisions and route snapshots are retained in versioned `hr_records` records.

Work and overtime cards show processed attendance totals. Scheduled break is explicitly labelled: this update does not invent measured break sessions from a single IN/OUT pair. Actual biometric delivery still depends on the office bridge and device connection. Existing shift assignments and attendance rules are reused without changing them.

## Verification

`tests/ess.php` covers profile field and owner protection, route snapshots, approval order, reasons, duplicate decisions, notification ownership, web punch ownership and enable/disable controls. `tests/ess_http.py` checks employee pages and direct-route isolation. Both run after the existing PHP/MySQL regression suite in GitHub Actions.
