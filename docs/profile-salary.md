# Salary setup in employee profiles

People → Add employee includes an optional Payroll / Salary section for users with employee create/edit and payroll salary manage permissions.

Enable “Set up salary for this employee”, select the structure, amount and effective date, then save the employee. The profile is created first; the following Edit Employee screen displays a salary preview. Review it, tick confirmation and choose “Confirm & save salary”. Until confirmation, the employee exists but no salary has been assigned.

People → Edit employee → Payroll / Salary adds or revises salary independently of the main profile form. The profile Payroll tab links here. Employee Salaries continues to show the same hr_salary_assignments records.

The effective date defaults to the joining date for initial setup. A revision must begin after the latest salary assignment; the existing payroll service ends the preceding period and retains its snapshot. Salary amounts, manual components, bank/PAN last-four values and overtime rate are carried forward into the revision form.

Preview confirmation is bound to actor, employee, one-time preview token, latest salary revision and the exact calculated breakup. Changing a profile or salary structure after preview requires a fresh preview. Salary creation uses the existing payroll transaction, permission checks and audit.

No schema migration, automatic salary assignment, attendance changes, payroll publication or production salary changes are included in this code update.
