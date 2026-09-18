# Arkentech PeopleFlow HRMS

PHP + MySQL application with two role-specific workspaces, designed for employeeportal.arkentechsolutions.com.

## Two modules

- Super Admin: employee accounts and directory, request review, company notices and audit history. Navigation also covers attendance, biometric devices, shifts, holidays, payroll, recruitment, onboarding, performance, reports and company settings.
- Employee: personal profile, leave requests, attendance regularisation requests, helpdesk requests and company notices. Navigation also covers attendance, holidays, payslips, onboarding and performance.

Login redirects through index.php to super-admin.php or employee.php based on the database role. Each protected request reloads the active account and checks its role on the server. Employee request queries are restricted to their user ID; the server checks ownership when cancelling. Super Admin can approve/reject leave and regularisation requests or resolve/reject helpdesk requests. Review updates lock the pending record and save the decision and audit event in one transaction.

## First administrator setup in Hostinger

Deploy the latest commit, then open /setup.php over HTTPS. Use the private setup key supplied separately in the conversation. Enter your existing Hostinger database host/name/username/password, your admin name/email, and a password of 12–72 characters. On submission the page validates the key, connects to MySQL, creates missing application tables, creates the first Super Admin with a password hash, and locks setup. The account is not created until this form succeeds on the live host.

Only the SHA-256 digest of the high-entropy setup key is committed. The raw key and all passwords are excluded from the repository. The page refuses setup when an existing Super Admin or setup lock is found. A filesystem lock serializes submissions. Existing local configuration is not overwritten. Database credentials are saved in config/peopleflow.local.php, guarded against direct PHP execution, protected by config/.htaccess and ignored by Git. The web process needs write access to the config directory. Configuration already supplied via environment variables takes priority. Existing unrelated API/configuration files remain untouched.

Existing tables are not migrated destructively: CREATE TABLE IF NOT EXISTS preserves them, and incompatible schemas will require review. A failed MySQL DDL step may leave some newly created tables because MySQL DDL auto-commits. After setup succeeds, remove setup.php from the hosting directory if desired; its existing-admin check and local lock keep it disabled on later deployments.

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

Biometric attendance, device syncing/retries, shift calculations, actual punch corrections, leave balances/accruals, holiday-aware day counting, employee HR/document/bank records, payroll calculations and payslip files, recruitment, onboarding tasks, KPI/review records, calendar data, configuration editors and report exports. These modules display explicit setup/empty states. Approving a request records a decision only; it does not modify punches, leave balances or payroll. The Employees page creates login accounts, not a complete employment profile. Email password resets and mandatory first-login password changes are not implemented; initial passwords must be shared privately. Both roles can change their own password with current-password verification. Other existing sessions are not revoked by a password change.

preview.html remains a public, fictional design demo using the earlier sample UI. It does not access live data and is separate from the authenticated role workspaces. Remove it from hosting if it is not needed.

## Validation

JavaScript syntax checks pass. PHP and MySQL are unavailable in this authoring environment, so backend execution and database integration could not be tested here. Chromium is unavailable, so browser rendering was not verified. Before enabling employee access, test on staging: both role logins; direct cross-role URL rejection; another employee's request-ID cancellation rejection; CSRF rejection; duplicate review rejection; create employee; submit/review/cancel request; notice publication; audit events; inactive account rejection; logout and session timeout. Hosting deployment and migration execution must be verified separately.

## Sidebar and account controls

Grouped sidebar matches the reference: Overview, People, Attendance, Leave, Payroll, Performance & PMS, Recruitment, Onboarding, Offboarding, Company settings and System configuration. Attendance and Leave expand to their related pages. Employee navigation remains scoped to personal sections.

Super Admin can deactivate Employee accounts under Offboarding; data is retained and the next protected request rejects inactive accounts. Asset clearance, final settlement and other exit workflows remain unconnected. System configuration reports database and migration readiness and allows changing the current administrator password. Employees use Login & security for their own password changes.

JavaScript syntax was checked for this update. PHP/MySQL execution and live account creation are still unverified from this environment. Validate the one-time setup on a staging database before using real HR data.
