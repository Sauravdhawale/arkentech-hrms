# Monthly attendance-based payroll

Run these commands from the deployed HRMS folder using the hosting account's PHP CLI. Substitute the real active Super Admin user ID for `ADMIN_ID`; this is not the employee code. No web endpoint is exposed.

## Monthly schedule

Preview: `php bin/monthly-payroll.php --actor=ADMIN_ID`

Create and calculate last month's draft: `php bin/monthly-payroll.php --actor=ADMIN_ID --apply`

Schedule that command once daily in Hostinger Cron Jobs using absolute paths to PHP and this script. The script uses Asia/Kolkata and targets the previous calendar month. Existing runs are skipped, so repeated execution cannot create duplicates or overwrite review work. Cron activation must be done on the hosting account; deploying the file alone does not schedule it.

The calculation reads real attendance and effective salary assignments. Missing salaries, unresolved punches, unpaid leave and absences retain the existing payroll rules. Review the run, resolve exceptions, approve/finalize and publish. The job does not automatically publish, mark paid or send messages. If creation succeeds but calculation fails, open the existing draft and calculate manually; the job deliberately will not overwrite it.

## September 2026 attendance test

Back up the live database before applying test corrections.

Preview the exact employee/date corrections: `php bin/september-attendance-test.php --actor=ADMIN_ID`

Apply the listed corrections: `php bin/september-attendance-test.php --actor=ADMIN_ID --apply`

This is a real, audited Attendance Sheet status change, not a mock PDF. Device punches and employee IDs are preserved. Present/late days, holidays, weekly offs and dates before joining are retained. Existing leave or missing shifts block the operation before writes. Reviewed/locked September payroll also blocks it. Each correction is committed separately; a concurrent change stops processing and reports the number already saved. Check the results before retrying. To undo a test correction, use Attendance Sheet → Automatic to restore the underlying punch-based result.

Salary assignments must cover September; this command never backdates salary. After checking salary and attendance, create/recalculate September payroll and review before publishing. Deploying this script does not execute it or change attendance.
