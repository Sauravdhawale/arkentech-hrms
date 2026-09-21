# Phase 1 — Super Admin foundation

## Scope
Super Admin employee management, company settings, configurable admin permissions, account recovery, and a dashboard based on stored data. Existing Employee portal and later HR suite routes are preserved. This release does not connect biometric devices or add attendance/payroll calculation rules.

## Deployment
1. Back up the existing database and private configuration before deployment.
2. Deploy the repository files into the existing subdomain document root `public_html/employeeportal/`. Do not create a nested `public_html` directory. Preserve `config/peopleflow.local.php` and other local configuration.
3. Sign in as the existing Super Admin and open `super-admin.php`. Click **Install Phase 1 database** once. This runs the additive migration, backfills existing employees and preserves their credentials and legacy records. Requires CREATE, ALTER, SELECT, INSERT, UPDATE and DELETE privileges on the application database. MySQL DDL commits independently; if interrupted, the installer can be rerun. No production migration is performed by GitHub CI.
4. Configure Company Settings → Company profile, Departments, Designations, then Roles & permissions. CEO, Director, HR, Manager, Team Leader and Employee start with no admin permissions. Super Admin is protected and retains full access. Grant View with corresponding create/edit/delete actions. Permissions are checked on each request.
5. Under People, preview and import `02-Physical-Id-card.xlsx` privately. Uses Sheet1 (107 employees in the supplied workbook). Existing identical names are skipped, including archived accounts. Review those matches manually; they may represent a different person. Sheet2 duplicates are not imported. No workbook or personal rows are committed to GitHub.
6. Initial imported password is `User@123`; first sign-in requires a new 12–72 character password. Usernames use first.last, with numeric suffixes on collision. Employee IDs remain blank. Missing email, department and joining date remain blank; finish these in employee editing. Card preparation status is retained as a source note, not used to infer employment status.
7. Configure recovery: copy `config/mail.example.php` to `config/mail.local.php`, set an authorized sender and the HTTPS application URL. Delivery uses PHP `mail()` and requires hosting mail configuration. Verify a real delivery and reset on Hostinger before relying on it. Users without a work email can have their password reset by Super Admin from the profile Employment tab.

## Routes
- `login.php`, `logout.php` (POST), `forgot-password.php`, `reset-password.php`
- `super-admin.php?page=overview` — dashboard
- `?page=employees` — search, filters, pagination, private workbook import
- `?page=employee-add`, `?page=employee-edit&id=...`
- `?page=employee-view&id=...&tab=overview|employment|documents`
- `?page=settings`, `?page=departments`, `?page=designations`
- `?page=roles`, `?page=account` (`system` is an alias)
- `document.php?id=...`, `media.php?employee=...` — authenticated file endpoints

## Data and behavior
`database/004-foundation.sql` adds normalized roles, permissions, user_roles, departments, designations, employees, company_settings, hr_media, password_resets and hr_migrations. The PHP migration also adds users.session_version and missing username/first-login fields. Documents reuse hr_records and hr_files. No existing tables are dropped.

Employee deletion is archival: login disabled, sessions revoked, history retained. Assigned departments/designations/roles cannot be deleted. Employee edits use optimistic version checks. Deactivation immediately blocks login; reactivation requires an eligible employment status. Changing roles takes effect on the next request. Reset tokens are hashed, expire after one hour and are single-use. Account recovery returns the same response for existing and unknown accounts and rate limits requests.

Photos and documents are authenticated database blobs, not public uploads. Supported photos: JPEG/PNG <=2 MB. Documents: PDF/JPEG/PNG <=4 MB. Employee documents show approaching/expired dates; this release does not send expiry reminder emails. Document deletion permanently removes its uploaded versions after confirmation.

Dashboard cards show real directory totals, active accounts, active departments and incomplete profiles. The donut reflects account status, not attendance. Growth uses known joining dates, excludes unknown dates and describes current employees only; it is not a historical headcount snapshot. The calendar marks recorded birthdays/anniversaries. Existing pending requests appear for Super Admin with links to their existing review flows. No demonstration employee counts are shown in production.

## Files
- `includes/foundation/`: controller, migration, permission and employee services, recovery service, shared layout, dashboard, employee and settings screens
- `assets/foundation.css`, `assets/foundation.js`: responsive layout, dark mode, dependent selectors
- `forgot-password.php`, `reset-password.php`, `media.php`, `config/mail.example.php`
- Updated admin routing, login recovery link, document permissions and session invalidation
- `tests/foundation.php`, `tests/foundation_http.py`: disposable MySQL and HTTP integration tests

## Validation and remaining deployment checks
GitHub Actions lints PHP and executes existing plus Phase 1 regression tests using an isolated MySQL 8 database. Tests do not modify Hostinger or send real recovery email. Run the production migration, privately import the workbook, configure the sender, verify reset-email delivery and check real hosting upload limits after deployment. Existing legacy employee profile screens still use their old records; normalized employee self-service is a separate next phase.
