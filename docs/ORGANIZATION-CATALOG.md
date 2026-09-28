# Department and designation catalog update

## Apply on the existing installation

After deployment, open **Company Settings → Organization → Departments** (or Designations). Expand **Arkentech departments & designations**, click **Preview organization update**, review the additions/reuse/renames/conflicts, then click **Apply safe changes**.

The preview reads the current departments, designations and employee relationships. It includes inactive and archived assignments when checking whether a designation can be moved. No update runs automatically on deploy or page load.

The resulting report lists:
1. Departments added.
2. Designations added.
3. Existing records reused, with their IDs.
4. Names normalized and department links corrected.
5. Digital Marketing → Marketing renames, with the same IDs.
6. Conflicts skipped and inactive records retained.
7. Confirmation that employee department/designation IDs were unchanged.

The exact report is persisted in the existing `hr_records` table under `organization_update`; its URL contains `organization_report=<report ID>`. Standard `hr_audit` records identify the actor and changed IDs.

## Scope

The catalog contains the requested **16 departments and 57 designations**. BCL is separate from Operations. Assistant Manager is scoped to Operations. Marketing Manager, Team Lead – Marketing, Marketing Executive and Marketing Intern are scoped to Marketing.

Departments/designations remain inside Company Settings → Organization. No new main-sidebar item or permission role is created.

## Reuse and conflicts

Names are compared case-insensitively with normalized whitespace, dash styles, punctuation, Senior/Sr and Team Lead/Team Leader. A curated alias list covers the requested spelling corrections and common department names, including HR, DBA and Digital Marketing. There is no fuzzy matching.

- A unique matching record is reused. A rename changes its name, not its ID, metadata, status or employee links.
- An unassigned designation may be linked to its requested department if no employee using it has a conflicting non-null department.
- Employees whose department is missing are left unchanged and counted in the report for separate review.
- Identical names already scoped to different departments can be legitimate. Existing target-department matches take priority; ambiguous unassigned matches are reported.
- Multiple matching departments, ambiguous designations, inactive matches and incompatible employee links are skipped, not merged or deleted.
- If both Marketing and Digital Marketing already exist, their IDs are reported as a conflict. The updater does not create another department or silently move employees.
- Rerunning a completed clean update adds no duplicate departments/designations and changes no catalog IDs or timestamps.
- A stale preview is rejected if catalog records or employee assignment versions changed.

## Employee dropdowns and validation

Employee create/edit shows only active designations for the selected department. Changing department hides incompatible options and clears an incompatible selected designation. The server enforces the same relationship even for crafted POST requests.

An existing unbound designation can remain selected only when the employee's department and designation IDs remain unchanged. This preserves older assignments without offering that unbound designation to new employees or other departments.

Employee import reuses a unique semantically matching designation, including one that has already been linked to a department. It does not invent employee department assignments. Ambiguous designation matches stop the import with a review message.

Manual Department/Designation forms also reject equivalent duplicate names in the applicable scope.

## Optional CLI

Read-only preview:

```sh
php bin/organization-catalog.php
```

Apply exactly that preview using its fingerprint and an active Super Admin actor ID:

```sh
php bin/organization-catalog.php --apply --actor=ADMIN_ID --fingerprint=PREVIEW_FINGERPRINT
```

The CLI refuses HTTP execution. It uses the existing application database configuration.

## Database changes

No schema migration, new table, DROP, TRUNCATE, DELETE, employee update, ID reset or role change. The updater only inserts missing departments/designations, updates eligible names/department links, and adds result/audit records to existing tables. Writes use one transaction and the same serialization lock as existing settings and employee writes.

## Files changed

New:
- `includes/foundation/organization.php`
- `includes/foundation/organization-view.php`
- `bin/organization-catalog.php`
- `tests/organization.php`
- `tests/organization_filter.cjs`
- `tests/organization_http.py`
- This document.

Updated:
- `includes/foundation/controller.php`
- `includes/foundation/settings.php`
- `includes/foundation/employees.php`
- `includes/foundation/employee-form.php`
- `assets/foundation.js`
- `.github/workflows/hrms-checks.yml`

## Verification

CI checks all 16 departments and 57 designation links, requested aliases, same-ID Marketing renames, preservation of metadata, repeat runs, stale-preview rejection, conflict handling, authorization/CSRF, duplicate guards, employee create/edit, and the actual dropdown JavaScript.

Complete employee, user, role, attendance, leave, raw-punch and payroll fixture rows are compared before and after applying the update in the disposable CI database. Existing regression suites also run.

The production admin page required sign-in during development. Production added/reused/renamed counts and actual conflicts are therefore obtained from the live preview/apply report, not inferred from test fixtures. No production data update is claimed until that action has been run.
