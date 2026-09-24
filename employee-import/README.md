# Arkentech employee database import

Use `Arkentech_Employees_Database_Import.sql` in phpMyAdmin. The CSV is the source only.

1. Download the SQL using GitHub's **Download raw file** button.
2. In phpMyAdmin select database `u491689847_employeeportal` (the database itself, not a table).
3. Export a SQL backup of the database before importing.
4. Open **Import**, select the downloaded `.sql`, keep format **SQL**, then click **Import / Go**.
5. Look for **IMPORT COMPLETED** and the counts returned by the procedure. If any error appears, stop and share its text; do not manually continue the statements.
6. Verify employees and Biometric Mapping in HRMS, then keep the office bridge running to process pending punches.

For the screenshot showing only AT00177 and AT00223, expected first-run counts are **105 employees created**, **2 existing employees preserved**, and **107 mappings created** if no mappings already exist. Actual counts depend on the current database. Running the same file again preserves existing employees and mappings.

New accounts use the CSV's `Proposed Username`, temporary password `User@123`, and mandatory password change at first sign-in. Existing passwords, personal details, roles, attendance and raw punches are not updated. New account passwords are stored as independently salted bcrypt hashes. New records have Employee access only.

This import registers 107 source employees with their employee IDs and maps them to device serial `CQQC231360514`. `AT00177` becomes eSSL user `177`. Device and Employee role must already exist. Existing identity or mapping conflicts stop and roll back the whole employee transaction. The database must support stored routines and transactional InnoDB tables.

DOB formats are normalized to YYYY-MM-DD. Physical ID-card statuses such as Done and Problem are notes, not employment statuses. Missing department, manager, joining date and other unknown fields remain unset. HR should review the employment status of newly imported employees; the source is an ID-card list, not an active-employment register. Archived/inactive existing profiles are preserved, with new mappings inactive for those profiles.

Keep this branch private. Do not merge it into the deployment branch or copy these files into a public web directory. This file has been prepared for the repository schema; execution on the live database must still be performed in phpMyAdmin.
