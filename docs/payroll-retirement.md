# Old Payroll retirement

Requested permanent removal of the old Payroll module. The replacement design remains four sections: Dashboard, Payslips, Payslip Designer, Configuration. This retirement does not implement the replacement.

The old module code, assets, salary forms, employee payroll views and navigation are removed. Old routes and standalone payslip endpoints return HTTP 410. The old monthly scheduler exits without processing payroll.

## Permanent database cleanup

Deploy this retirement before cleanup and stop the old cron job. Run with the existing application's DB environment:

```sh
php bin/remove-old-payroll.php
php bin/remove-old-payroll.php --apply --database=YOUR_ACTUAL_DATABASE_NAME
```

First command reports database name and affected row counts without deleting. Second permanently deletes salary assignments/history, payroll entries/runs/adjustments/events, optional payroll snapshots/overrides/exceptions, payslip documents/templates, old salary/payroll configuration, payroll archives and attached files. It includes the legacy Payroll salary, contracts, advances and components records. Schema remains empty for redevelopment; IDs are not reset. No DROP/TRUNCATE, no disabled foreign keys, no on-deploy cleanup.

Employee identities, employee documents outside these payroll records, attendance, biometric punches, leave, departments and designations are not deleted. External backups/downloaded PDFs are not accessible to this command. Database cleanup is not executed merely by deploying GitHub changes.

The cleanup is transactional and verified. Unknown payroll tables abort for inspection. An unknown foreign-key dependency rolls back; do not disable foreign keys to bypass it.
