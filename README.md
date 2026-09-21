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

## HR suite update

Requires PHP 8.0+ with PDO MySQL, zip, SimpleXML and fileinfo. Deploy code, sign in as Super Admin, then open System configuration and choose **Install / update HR modules**. This creates the additive `003-hr-suite.sql` tables and adds nullable unique usernames, optional email and the first-login password-change flag. Existing accounts/passwords are preserved. Back up the database before applying a production migration.

The new sections persist records in MySQL: employment profiles, devices, employee mapping, shift definitions and assignments, holidays, leave entitlements/configuration/restricted periods, salary structures, employment contracts, advances, components, payroll, jobs, candidates, interviews, offers, onboarding/exit tasks, goals, reviews, improvement plans, assets, licenses, policies, tasks, documents, expenses, departments and designations. Company or employee visibility is enforced on the server. Forms support create/edit, status changes, optimistic version checks and CSV export (latest 1,000 records). Employee expenses/advances start Pending; employees can update their own task/onboarding status. Other record administration is Super Admin only. Published payroll is locked and only published payroll/reviews/policies are shown to employees.

### Employee import

In People → Import employee accounts, upload the original **Physical Id card.xlsx**, review, then create accounts. Only Sheet1 is read, with named header columns. Duplicate names are deduplicated in the workbook; existing account names are skipped without password changes. Names are transliterated into `firstname.lastname`; database username collisions receive numeric suffixes. Existing employees with the same name require manual review if they are different people.

Import sets the requested initial password `User@123`, hashes it independently for each account, and forces password change before workspace/document/payslip access. The workbook contains no emails. Emails and business employee IDs remain empty; admins may add them later through People and Employee profiles. Imported source ID-card preparation status is metadata, not account activation status. The original workbook is not committed to GitHub. Actual accounts are only created when import is run against the hosting database.

### Payroll and leave boundaries

Payroll is an explicit draft → approval → publication workflow for manually verified amounts. Net pay is calculated from entered components. Statutory tax/PF/ESI rules, attendance-based automatic deductions, bulk payroll generation, disbursement, advance repayment scheduling and salary-template automation are not implemented. Enter and verify the final amounts before approval. Download is the browser's Print / Save as PDF flow.

Paid leave approval requires annual entitlements, rejects overlap and prevents over-allocation under a per-employee lock. It currently counts inclusive calendar days, including holidays/weekends. Leave policy/restricted-period records document policy but do not yet drive eligibility or accrual rules. No separate Manager/HR/Payroll roles have been introduced: the requested two-role model remains.

### Documents and policies

Employee-linked document records accept PDF/JPEG/PNG uploads up to 4 MB. Binary content stays in MySQL and is served only through the authenticated `document.php` download route after an ownership check. Uploads reset the document to Received for review. Policies can be acknowledged per employee and record version. Editing a policy requires re-acknowledgement. The dashboard lists upcoming holidays, tasks and documents expiring within 30 days. No outbound email/SMS jobs run.

### Biometric endpoint

`api/punches.php` accepts normalized bridge events over HTTPS with `Authorization: Bearer <key>`. Create private `config/biometric.local.php` returning `['api_key' => '<random secret of at least 32 characters>']` with the same PEOPLEFLOW_INTERNAL guard as the DB configuration. Never commit this file. Register the matching enabled device code in Biometric devices.

Example JSON (illustrative values only):
```json
{"device_code":"PUNE-01","events":[{"event_key":"event-0001","biometric_id":"1001","punched_at":"2026-09-18 09:00:00","direction":"in"}]}
```

Batches accept 1–500 events, validate completely before insertion, and deduplicate by device/event key. Successful batch counts are logged. The client must retry failed batches with the same event keys. A Windows/eSSL SDK bridge, EXE installer, device discovery, persistent client retry queue and live-device verification remain required. Current attendance views group first/last punches by calendar date and explicitly show elapsed span rather than worked hours. Shift/overnight allocation, break pairing, late/early/overtime rules and approved regularisation application are not yet calculated.

### Verification

`tests/integration.php` only runs against a disposable database named `peopleflow_ci`. The GitHub HRMS checks workflow lints PHP and checks schema installation, repeated migration, record isolation, published-payslip visibility, arithmetic, input validation, leave calculations and synthetic XLSX parsing. This does not replace Hostinger deployment and real-device verification.

## Phase 1 Super Admin foundation

See [the Phase 1 deployment and feature guide](docs/PHASE-1.md) for the new admin dashboard, normalized employee management, Company Settings, configurable roles, private workbook import and password recovery. Deploy to the existing subdomain root and run the signed-in **Install Phase 1 database** action. Imported employee IDs remain blank until assigned; no employee workbook is committed here. PHP/MySQL and authenticated HTTP regression tests now run in GitHub Actions; production migration, real recovery-email delivery and browser rendering on the hosting environment require separate verification.
