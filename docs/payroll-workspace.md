# Payroll workspace

Payroll has three sidebar entries: Dashboard, Payslips, and Loans & Salary Advances.
Existing payroll records, salary revisions, published snapshots, and working routes are retained.

## Salary to payslip

1. Company Settings → Allowances & Deductions: define earning/deduction components.
2. Company Settings → Salary Structures: group the components into a structure.
3. Payslips → Set employee salary: select the employee, structure, salary amount and effective date. Preview, then confirm the salary revision.
4. Payslips → Generate payroll: create a monthly draft, calculate, review exceptions, approve where required, finalize and publish.
5. Employees open Payroll to view their current structure and published slips; View and PDF retain the existing owner authorization.

The sample structure shown in Payslips uses Basic 50% of gross, HRA 50% of Basic, example Bonus 8.33% of Basic, and a balancing Special Allowance. It is illustrative and does not create salary assignments or statutory rules.

## Designer and attributes

Company Settings → Payslip Designer retains the safe HTML editor and custom attributes. Annexure A and Earnings Statement layouts are available. Selecting a layout replaces the editor contents after confirmation; save a dated revision to use it for future payroll runs. Existing runs retain their saved template.

Annualized figures in the demo and component view are monthly amounts multiplied by twelve. They are not a replacement for annual CTC or a contractual salary statement.

## Employee / department allocations

Inside a Draft or Calculated payroll, choose Allocate allowance / deduction. Select Employee or Department, choose the target, and enter a label, positive per-employee amount, and reason. Confirm and recalculate.

Department allocation uses current department membership among employees already in that run. All matched allocations are saved in one transaction, audit logged, and protected by the run version. A stale repeated submission is rejected. Finalized payroll cannot be adjusted. Individual adjustment types, including authorized loan and salary-advance recoveries, remain available.

## Loans and advances

The Loans & Salary Advances page contains labeled, interest-free examples only. It does not issue loans, create employee debts, or schedule automatic repayments. Real approved recoveries can be recorded as draft-payroll adjustments.

## Access and history

Admin employee profiles expose the Payroll tab only with salary-view permission. Employee payroll always uses the signed-in user's ID; URL employee IDs do not change the owner. Only published Finalized/Paid slips appear in the employee dashboard. No database migration or cleanup is introduced by this UI update.
