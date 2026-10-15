# Monthly payroll workflow

Existing routes and tables are retained. No salary, attendance or payslip records are reset.

1. Employees → Profile → Payroll & Salary: save an effective-dated salary. Revisions retain history.
2. Attendance Sheet: resolve missing check-outs, pending leave and salary coverage. Present, paid leave, holidays and week offs do not incur LOP. Absent/LWP incur configured daily deduction; unpaid half days count 0.5.
3. Payroll → Monthly Payroll: choose month, prepare, calculate and inspect each employee’s calculation details. Correct attendance/salary and recalculate before finalization.
4. Payroll → Payslips: generated draft copies are visible only to authorized payroll staff. Published copies are visible to the employee who owns them.

## Configuration

Payroll Settings → Calculation & Schedule contains the divisor, effective date, schedule and generation controls. Settings are effective-dated: save a revision covering the payroll month. Calendar Days / Fixed 30 Days / Working Days reuse the existing calculation rules. No tax rates are introduced.

- Automatic payroll: enables scheduled calculation.
- Automatic draft payslips: prepares a draft copy for each exception-free calculation.
- Closing day and time: following month, Asia/Kolkata. Processing also waits through the last shift’s end and configured attendance collection window.
- Approval required: retains review/approval before finalization.
- Publish on finalization: makes final slips available to employees. When approval is disabled, scheduled processing can finalize and publish automatically if this option is enabled.

## Hosting setup required

Run hourly using the host's PHP CLI and actual project path:

```sh
php /ABSOLUTE/PROJECT/PATH/bin/monthly-payroll.php --actor=SUPER_ADMIN_USER_ID --scheduled --apply
```

The command targets the previous calendar month. It must run under the application's normal DB environment. Replace the path and ID with the actual deployment values. Without `--apply` it does not write. For a historical period use `--month=YYYY-MM` explicitly.

Repeated execution preserves existing runs. Exceptions require HR correction and recalculation; the scheduler never silently replaces an existing payroll. Failed partial creation is therefore reviewable in Monthly Payroll. Deployment does not install cron or enable the switches automatically.

## Locks and preservation

Finalized/Paid payroll cannot be normally recalculated. Only Super Admin can reopen with a reason. Reopen archives the complete prior run and entry snapshots in `hr_records` (`payroll_archive`) and records an audit event, withdraws employee publication, and requires recalculation. Original payslip numbers are retained for the corrected revision. The prior snapshots remain in the archive, not deleted.

Payslip Designer changes only appearance. Existing payroll retains its saved template, salary, settings and attendance snapshot. Custom HTML prints through the branded browser PDF flow; the direct PDF download remains the existing text PDF renderer.

No production DB access or hosting scheduler installation was performed by this code change. Attendance not yet received from an offline biometric bridge cannot be detected by a payroll scheduler: HR must resolve incomplete attendance before publication.
