# Team Leader access

Team Leaders sign in through the employee portal and get **My team → Team People / Team Attendance**. Their own dashboard, attendance, payslips and requests remain personal.

## Assign a team

1. As Super Admin, edit the leader's employee profile and choose the active **Team Leader** role (role code `team-leader`). Save the employee.
2. Edit each team member and set **Reporting Manager** to that leader. Save each employee.
3. The leader refreshes their employee portal. No database migration is needed.

Membership is based on the current Reporting Manager, not department, designation text or biometric ID. Direct reports only; reports of another team leader are not inherited. An empty team shows an assignment message rather than all employees. Reassigning an employee removes the previous leader's access on the next request, including to that employee's historical attendance. Archived/deleted profiles are excluded.

## Included

- Work directory: name, employee code, department, designation and employment status.
- Current attendance using the existing punch calculation, with an explicit Refresh attendance control.
- Full monthly attendance for a selected direct report, including overnight shifts and CSV download.

## Access boundary

Team pages are GET-only. They cannot edit employees, record punches, correct attendance or change payroll. Private profile fields, documents and salary are not exposed. Employee IDs, searches and exports are checked on the server. Old company-wide admin pages are blocked for this role, even when broad permissions were previously checked in Roles & Permissions. Super Admin and other configurable roles keep their existing permissions.

A separately granted `leave.approve` permission retains the existing request approval routing. This change neither grants it nor changes its approval scope. Normal employee self-service remains available.

## Deploy

Back up the existing application files, then extract `team-leader-access-update.zip` into the HRMS web root (the folder containing `employee.php`). It contains the new `includes/team` directory, CSS and the updated entry-point/navigation/access files. It contains no credentials and does not alter stored attendance, employee assignments or payroll.

Validation: `php tests/team_access.php`, `python3 tests/team_access_http.py`, and `node tests/team_workspace_ui.cjs` run in the existing disposable CI database workflow. The checks cover cross-team direct URLs and exports, read-only routes, manager changes, disabled roles, no-team states and responsive pages.
