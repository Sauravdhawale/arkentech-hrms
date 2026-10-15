# Payroll workspace

The administration sidebar has four entries: Dashboard, Payslips, Payslip Designer and Configuration.

## First use after deployment

1. Open Payroll → Dashboard as Super Admin and select **Set up Payroll** if shown.
2. Review the sample common salary structure in Configuration → Salary Rules. Starter percentages are editable examples, not a determination of statutory obligations.
3. Set each employee's salary and effective dates in People → Edit employee → Payroll / Salary.
4. Confirm attendance is processed for the month. Missing salaries, missing shifts and unresolved attendance block finalization.
5. Choose a completed month in Payslips and select Generate Payslips. Review exceptions, draft previews and totals.
6. Select Finalize & publish when ready. Employees can access only their own published copies.

No deleted salaries, payslips or attendance records are restored by setup. Setup adds missing configuration and permissions; it does not fabricate attendance or salary assignments.

## Configuration

General and Attendance & LOP edit different parts of the same effective-dated settings record. Changes preserve the other fields. Earlier monthly runs retain their captured settings and template.

Salary Rules contains allowances, deductions, employer contributions and the common structure. Employee salary revisions capture their component rules; changing configuration does not silently rewrite saved salary assignments.

Statutory rules are optional and require your company's applicable rates. Payslip Settings controls display fields. Payslip Designer controls layout only and cannot change salary calculations. Preview values are synthetic; browser Print / Save as PDF follows the HTML layout.

## Scheduling

Automatic processing requires a hosting cron job; toggling a setting alone does not start a background worker. Configure the host to run the following hourly, substituting the actual path and active Super Admin ID:

    php /absolute/project/path/bin/monthly-payroll.php --actor=ADMIN_ID --apply --scheduled

The scheduler checks the configured closing day/time (Asia/Kolkata), completed month and existing run before proceeding. With approval required, it prepares drafts only. No cron job is installed by deploying these files.

## Removed interfaces

Payroll Runs, old Employee Salaries, Loans, Contracts and old report/configuration routes are not navigation items. Retired routes return 410. Salary management remains in employee profiles.

The old cleanup CLI is now inert and refuses to delete newly created payroll data.
