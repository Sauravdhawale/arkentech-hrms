-- Payroll workflow / snapshot / template foundation.
-- Additive only. Existing salary assignments, payroll runs, entries and published payslips are preserved.

ALTER TABLE hr_payroll_runs
 ADD COLUMN prepared_by INT UNSIGNED NULL AFTER created_by,
 ADD COLUMN prepared_at DATETIME NULL AFTER prepared_by,
 ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER prepared_at,
 ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
 ADD COLUMN approved_by INT UNSIGNED NULL AFTER reviewed_at,
 ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
 ADD COLUMN locked_by INT UNSIGNED NULL AFTER approved_at,
 ADD COLUMN locked_at DATETIME NULL AFTER locked_by,
 ADD COLUMN reopened_by INT UNSIGNED NULL AFTER locked_at,
 ADD COLUMN reopened_at DATETIME NULL AFTER reopened_by,
 ADD COLUMN reopen_reason VARCHAR(1000) NOT NULL DEFAULT '' AFTER reopened_at,
 ADD COLUMN payslips_generated_at DATETIME NULL AFTER reopen_reason,
 ADD COLUMN published_at DATETIME NULL AFTER payslips_generated_at,
 ADD FOREIGN KEY(prepared_by) REFERENCES users(id),
 ADD FOREIGN KEY(reviewed_by) REFERENCES users(id),
 ADD FOREIGN KEY(approved_by) REFERENCES users(id),
 ADD FOREIGN KEY(locked_by) REFERENCES users(id),
 ADD FOREIGN KEY(reopened_by) REFERENCES users(id);

CREATE TABLE IF NOT EXISTS hr_payroll_attendance_snapshots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 employee_id INT UNSIGNED NOT NULL,
 payroll_month CHAR(7) NOT NULL,
 snapshot LONGTEXT NOT NULL,
 source_hash CHAR(64) NOT NULL,
 captured_by INT UNSIGNED NOT NULL,
 captured_at DATETIME NOT NULL,
 last_live_hash CHAR(64) NULL,
 changed_after_capture BOOLEAN NOT NULL DEFAULT FALSE,
 checked_at DATETIME NULL,
 UNIQUE KEY payroll_attendance_employee(run_id,employee_id),
 INDEX payroll_attendance_month(payroll_month,employee_id),
 FOREIGN KEY(run_id) REFERENCES hr_payroll_runs(id) ON DELETE CASCADE,
 FOREIGN KEY(employee_id) REFERENCES employees(user_id),
 FOREIGN KEY(captured_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_payroll_exceptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 entry_id BIGINT UNSIGNED NULL,
 employee_id INT UNSIGNED NULL,
 code VARCHAR(80) NOT NULL,
 severity ENUM('Info','Warning','Critical') NOT NULL DEFAULT 'Warning',
 details VARCHAR(2000) NOT NULL,
 status ENUM('Open','Resolved','Ignored') NOT NULL DEFAULT 'Open',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 resolved_by INT UNSIGNED NULL,
 resolved_at DATETIME NULL,
 resolution_note VARCHAR(1000) NOT NULL DEFAULT '',
 INDEX payroll_exception_run(run_id,status,severity),
 INDEX payroll_exception_employee(employee_id,status),
 FOREIGN KEY(run_id) REFERENCES hr_payroll_runs(id) ON DELETE CASCADE,
 FOREIGN KEY(entry_id) REFERENCES hr_payroll_entries(id) ON DELETE CASCADE,
 FOREIGN KEY(employee_id) REFERENCES employees(user_id),
 FOREIGN KEY(resolved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_payroll_overrides (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 entry_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(80) NOT NULL,
 old_value VARCHAR(500) NOT NULL,
 new_value VARCHAR(500) NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX payroll_override_entry(entry_id,id),
 FOREIGN KEY(run_id) REFERENCES hr_payroll_runs(id) ON DELETE CASCADE,
 FOREIGN KEY(entry_id) REFERENCES hr_payroll_entries(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_payslip_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_code VARCHAR(60) NOT NULL,
 name VARCHAR(190) NOT NULL,
 version INT UNSIGNED NOT NULL,
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 status ENUM('Draft','Active','Archived') NOT NULL DEFAULT 'Draft',
 builder_json LONGTEXT NOT NULL,
 html_template LONGTEXT NOT NULL,
 css_template LONGTEXT NOT NULL,
 variables_json LONGTEXT NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY payslip_template_version(template_code,version),
 INDEX payslip_template_effective(template_code,status,effective_from,effective_to),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_payslip_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entry_id BIGINT UNSIGNED NOT NULL UNIQUE,
 template_id BIGINT UNSIGNED NOT NULL,
 template_snapshot LONGTEXT NOT NULL,
 rendered_html LONGTEXT NOT NULL,
 generated_by INT UNSIGNED NOT NULL,
 generated_at DATETIME NOT NULL,
 published_by INT UNSIGNED NULL,
 published_at DATETIME NULL,
 FOREIGN KEY(entry_id) REFERENCES hr_payroll_entries(id) ON DELETE CASCADE,
 FOREIGN KEY(template_id) REFERENCES hr_payslip_templates(id),
 FOREIGN KEY(generated_by) REFERENCES users(id),
 FOREIGN KEY(published_by) REFERENCES users(id)
) ENGINE=InnoDB;
