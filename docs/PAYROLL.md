# Payroll implementation and operations

This module extends the existing PeopleFlow/sHRMS application. It uses the existing employees, departments, roles, company settings, attendance, shifts and leave services. It does not rebuild these modules.

## Enable after deployment

1. Deploy the verified `main` commit through the existing Hostinger Git deployment.
2. Sign in as Super Admin. Open **Payroll → Dashboard** and click **Install Payroll upgrade** once. Installation is repeat-safe and adds the five tables below and permission definitions. It does not run automatically on page load.
3. Open **Company Settings → Payroll**. Create dated Salary Components, Salary Structures, Statutory Settings where applicable, Payroll Settings and Payslip Settings. Use effective dates that cover the salary and payroll month.
4. Assign employee salaries and review the monthly/annual preview before confirming. Existing legacy salary records remain available via **Previous salary records**; they are not silently converted into new assignments.
5. Confirm existing shift assignments, attendance and approved leave. Create a monthly run, calculate, resolve exceptions, review, approve, finalize after month closing, generate payslips and mark paid when payment is actually completed.

No live hosting deployment or production database migration is performed by the code review/test workflow. Production amounts and company rules still require administrator configuration and review.

## 1. Modules implemented

- Payroll Dashboard: monthly totals, exceptions, published payslip count, department net-pay distribution and missing-salary list.
- Employee Salary: employee/department/designation/structure/effective-status filters, gross or annual-CTC preview, dated assignments, revision history, monthly/annual components and printable detail.
- Payroll Processing: monthly runs; eligible active employees; adding employees to an open draft; employee, department, status and structure filters; LOP, overtime, variable, other earnings, deductions and net columns.
- Controlled run workflow; employee holds; documented adjustments; overtime approval; audit history.
- Published Payslips, Payroll History and filtered Payroll Reports.
- Company payroll component, structure, statutory, payroll and payslip configuration.

## 2. Files created

- `database/007-payroll.sql`
- `includes/payroll/calculation.php`
- `includes/payroll/config.php`
- `includes/payroll/service.php`
- `includes/payroll/controller.php`
- `includes/payroll/navigation.php`
- `includes/payroll/view.php`
- `includes/payroll/settings-view.php`
- `includes/payroll/salary-view.php`
- `includes/payroll/run-view.php`
- `includes/payroll/pdf.php`
- `assets/payroll.css`
- `payroll-slip.php`
- `tests/payroll.php`
- `tests/payroll_http.py`
- This document.

## 3. Files modified

- `super-admin.php`: registers new routes; existing routes remain.
- `includes/foundation/controller.php`: payroll page registry, protected POST dispatch and report export.
- `includes/foundation/layout.php`: Payroll main navigation and Company Settings sections.
- `includes/workspace.php`: access to new personal payslips beside legacy payslips.
- `includes/suite.php`: prevents creating a legacy payroll payment for an employee/month already finalized in the new module.
- `.github/workflows/hrms-checks.yml`: runs payroll tests after existing regression checks.

## 4–6. Migration, tables and columns

Migration: **007-payroll**. Five new tables:

| Table | Purpose |
| --- | --- |
| hr_salary_assignments | Dated employee salaries, structure/calculation snapshots, reason and actor |
| hr_payroll_runs | Month, workflow state, saved company/settings, version and final/payment timestamps |
| hr_payroll_entries | Employee amounts, calculated snapshots, exceptions, payslip number and publication |
| hr_payroll_adjustments | Audited earnings/deductions, OT units, variable pay and OT approver |
| hr_payroll_events | Append-only workflow event history |

No columns are added to or removed from existing tables. Monetary values in the new tables are integer minor currency units. Existing `hr_records` is reused for configuration modules `pay_component`, `pay_structure`, `pay_statutory`, `pay_settings` and `pay_payslip`. Configuration revisions close prior effective periods while preserving their records. Employee salary revisions likewise close the prior assignment's end date and retain its original snapshot.

The migration uses `CREATE TABLE IF NOT EXISTS`, foreign keys and a migration marker. It contains no DROP, TRUNCATE, DELETE, credential reset or employee-ID changes. New permissions use the existing Roles & Permissions mechanism; Super Admin has full access. They are not automatically granted to employee roles.

## 7–8. Routes and APIs

All administrative pages use the existing `super-admin.php?page=` entry point:

| Route | Navigation |
| --- | --- |
| payroll_dashboard | Payroll → Dashboard |
| employee_salary | Payroll → Employee Salary |
| payroll_processing | Payroll → Payroll Processing |
| payroll_payslips | Payroll → Payslips |
| payroll_history | Payroll → Payroll History |
| payroll_reports | Payroll → Reports |
| salary_components | Company Settings → Payroll → Salary Components |
| salary_structures | Company Settings → Payroll → Salary Structures |
| statutory_settings | Company Settings → Payroll → Statutory Settings |
| payroll_settings | Company Settings → Payroll → Payroll Settings |
| payslip_settings | Company Settings → Payroll → Payslip Settings |

`payroll-slip.php` lists the authenticated employee's published new payslips; `?entry=ID` opens one; `&download=pdf` returns a text PDF. An employee can access only their own published payslips unless granted the explicit staff `payslip.view` permission. Legacy `payroll`, `salary` and `payslip.php?id=ID` remain supported.

No public API endpoints are added. CSRF-protected form actions run through the existing controller with permission checks for each action. Workflow changes use transactions, a shared serialization lock, an optimistic run version, reasons and explicit confirmation.

## 9. Salary calculations

- Fixed amount, percentage of a component/expression, restricted arithmetic formula, manual amount, and balancing earnings.
- Formula parser accepts uppercase component codes, GROSS, numbers, parentheses and arithmetic only. No PHP evaluation, function calls or external execution.
- Dependency cycles, missing references, negative components, excess deductions and multiple balancing components are rejected.
- One optional balancing component fills gross after the other gross earnings.
- Configurable gross/CTC inclusion, taxable flag, fixed/variable section and payslip visibility.
- Monthly Gross entry or annual CTC reconciliation; annual CTC includes configured CTC earnings and employer contributions. Structures with an unreconcilable or nonmonotonic CTC equation are rejected; Monthly Gross remains available.
- Variable salary components are not guaranteed fixed earnings. Variable amounts or percentages are applied only as payroll adjustments within enabled company/structure limits.
- Salary revisions during a month are prorated across their effective dates.

## 10–11. Attendance, leave and LOP

Uses existing `chr_report_context`, `chr_day` and `chr_monthly` results. No attendance or leave data is rewritten.

- Calendar Days, Working Days and Fixed 30 Days proration.
- Working Days uses the employee's existing shift/calendar schedule over the full month.
- Joining-date eligibility and dated salary coverage.
- Paid leave, unpaid leave, half days, holidays and week offs use existing attendance classifications.
- LOP basis is a configured salary component or GROSS; charge unpaid leave alone or absence plus unpaid leave.
- Fixed-30 full-month salary is normalized to a full salary; LOP uses a 30-day divisor. Partial months use eligible calendar days over 30.
- Missing salary, missing shifts, unresolved attendance, open punches and conflicting leave/punch activity are visible exceptions and block finalization.
- Normal administrative absence notes do not create a payroll exception.
- Existing attendance OT minutes cap approved monthly overtime hours. The existing attendance module has no independent OT-approval record, so payroll adjustments add an explicit permission-controlled OT approval.
- Fixed hourly, basic-based, gross-based or custom employee OT rates. Where salary changes during a month, the monthly hourly rate is blended across eligible days; approved hours are monthly totals.

## 12. Statutory configuration

PF, ESI, TDS, Professional Tax and other rules have dated enablement, basis component, percentage or fixed employee/employer values, wage cap and eligibility limit. No legal rates are hardcoded or seeded. Rules applied to each payroll date are saved in the calculation snapshot.

Contributions use configured monthly wage bases, caps and eligibility; amounts are prorated and reduced proportionally to configured LOP. Administrators must confirm that this calculation model matches their company's applicable rules. TDS is a configured amount/rate, not an individual tax-declaration, progressive annual tax or statutory filing engine. Taxable component flags are metadata for review; this module does not infer tax from them.

## 13. Payslips

Saved company/logo/address, employee name/code/department/designation, component lines, gross, deductions, net, amount in words, optional attendance, signatory and footer. Only bank/PAN suffixes are accepted; full sensitive identifiers are not collected.

Published slips use snapshots, never current salary settings. Hidden components are aggregated as “Other” lines so displayed totals still reconcile. Employer contributions are shown separately from employee net pay.

- Branded HTML with browser Print / Save PDF retains company logo and Unicode.
- Downloadable paginated text PDF has no external library dependency; it transliterates text to the PDF standard font and does not embed the logo.
- Unique payslip numbering with configurable year/month/ID placeholders.

## 14. History and workflow

Draft → Calculated → Reviewed → Approved → Finalized → Paid. Approval can be disabled in the saved run settings, but review remains required. Finalization is available after the month closes and the configured day of the following month is reached.

Holds, exceptions and uncalculated employees block review/finalization. Release holds and recalculate. Adjustments invalidate calculation and return the run to Draft. Finalized/Paid runs reject recalculation, salary adjustments and reopening. Corrections are documented adjustments in a later payroll.

Settings, company identity, salary segments, statutory rules, attendance results, component totals, approved OT and adjustments are saved with the run/entry. Later employee, salary and configuration edits cannot rewrite a published payslip. Legacy/new finalized payroll duplication is checked in both workflows.

## Reports

Filtered monthly register, department selection, employee selection, component/statutory totals, LOP, variable pay, OT and employer contribution totals. CSV includes individual component columns as well as reconciled amounts. Spreadsheet-formula text is escaped. Browser print supports PDF output. XLSX export is not added; CSV opens in spreadsheet software.

## 15. Verification

GitHub Actions uses a disposable MySQL database and runs all existing regression tests plus:

- Migration repeatability and preservation of pre-existing employee, user, attendance, leave, generic-record, file and raw-punch rows.
- Fixed/percentage/formula arithmetic, balancing, CTC, configured statutory wage caps and employer costs.
- Normal monthly salary, LOP, approved versus unapproved OT, variable limits and joining-date proration.
- Missing salary exceptions and ordinary administrative absence notes.
- Salary effective periods, concurrent/stale submission rejection and historical snapshot stability.
- Workflow approvals, finalization, publication, payment and final edit locks.
- Permission enforcement, CSRF, cross-employee payslip access denial.
- Payroll routes, salary-preview submission, filtered views, CSV and PDF response checks.
- Compatibility with existing Phase 1, Attendance, Leave, XLSX import and biometric regression suites.

The GitHub Actions run attached to the final commit is the verification record. No live production payments or attendance calculations are triggered by CI.

## 16. Existing data verification

The migration test compares complete rows before and after installation in the disposable database, including existing users/IDs and legacy records. Existing attendance and employee regression suites run unchanged. Production data was not accessible for a before/after comparison, and this implementation does not claim that the production database has been migrated.

## 17. Remaining operational work / boundaries

- Deploy, install the additive upgrade and configure company-specific salary/statutory rules.
- Review a representative closed month against your existing payroll before publishing real payslips.
- No automatic legacy-salary conversion, automatic bank payment, tax declaration portal, statutory returns filing, PDF email delivery or XLSX report export.
- The downloadable text PDF is intentionally simpler than the branded print layout.
- Existing biometric connectivity is not modified by Payroll. Attendance exceptions remain visible and must be resolved in the existing Attendance module.
