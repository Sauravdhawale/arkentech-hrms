# Arkentech PeopleFlow HRMS

PHP + MySQL application with two role-specific workspaces, designed for employeeportal.arkentechsolutions.com.

## Two modules

- Super Admin: employee accounts and directory, request review, company notices and audit history. Navigation also covers attendance, biometric devices, shifts, holidays, payroll, recruitment, onboarding, performance, reports and company settings.
- Employee: personal profile, leave requests, attendance regularisation requests, helpdesk requests and company notices. Navigation also covers attendance, holidays, payslips, onboarding and performance.

Login redirects through index.php to super-admin.php or employee.php based on the database role. Each protected request reloads the active account and checks its role on the server. Employee request queries are restricted to their user ID; the server checks ownership when cancelling. Super Admin can approve/reject leave and regularisation requests or resolve/reject helpdesk requests. Review updates lock the pending record and save the decision and audit event in one transaction.

## Install or upgrade

1. PHP 8.1+ with PDO MySQL, MySQL 8+ or compatible MariaDB, and HTTPS are required.
2. For a new database, import database/schema.sql first. For an existing installation with users and login_attempts, skip that base schema.
3. Import database/002-workspaces.sql to create the requests, announcements and audit tables. Back up an existing database before migration. No existing rows are modified by this migration.
4. Configure DB_HOST, DB_NAME, DB_USER and DB_PASSWORD in the server environment. The application does not automatically read existing config-folder files. Keep credentials out of GitHub.
5. Create the first Super Admin via the CLI-only helper: php bin/create-user.php 'Admin Name' admin@example.com super_admin. Supply a unique password of at least 12 characters through standard input. No default credentials or users are shipped. Admin can then create Employee accounts from the Employees page.
6. Deploy to the existing subdomain document root. Preserve existing api and config files. Confirm Hostinger's directory field resolves to the existing public_html/employeeportal folder, without nesting another public_html directory.
7. Periodically remove login_attempts older than one day.

## Implemented data workflows

- Employee account creation (Employee role only) and directory.
- Employee leave requests with CL/SL/PL/LWP category, dates and reason; cancel pending requests; Super Admin decisions.
- Attendance-correction requests with date range and details; Super Admin decisions.
- Employee helpdesk requests and Super Admin resolution.
- Company notices published by Super Admin and readable by employees.
- Audit events for those writes.
- Database-backed role enforcement, account-active checks, CSRF protection, password hashing, prepared SQL, session rotation, 30-minute idle expiry and logout.
- Responsive two-workspace UI and device-local dark-mode preference.

## Not connected yet

Biometric attendance, device syncing/retries, shift calculations, actual punch corrections, leave balances/accruals, holiday-aware day counting, employee HR/document/bank records, payroll calculations and payslip files, recruitment, onboarding tasks, KPI/review records, calendar data, configuration editors and report exports. These modules display explicit setup/empty states. Approving a request records a decision only; it does not modify punches, leave balances or payroll. The Employees page creates login accounts, not a complete employment profile. Email password resets and mandatory first-login password changes are not implemented; initial passwords must be shared privately.

preview.html remains a public, fictional design demo using the earlier sample UI. It does not access live data and is separate from the authenticated role workspaces. Remove it from hosting if it is not needed.

## Validation

JavaScript syntax checks pass. PHP and MySQL are unavailable in this authoring environment, so backend execution and database integration could not be tested here. Chromium is unavailable, so browser rendering was not verified. Before enabling employee access, test on staging: both role logins; direct cross-role URL rejection; another employee's request-ID cancellation rejection; CSRF rejection; duplicate review rejection; create employee; submit/review/cancel request; notice publication; audit events; inactive account rejection; logout and session timeout. Hosting deployment and migration execution must be verified separately.
