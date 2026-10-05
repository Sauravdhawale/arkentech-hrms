-- Payroll workflow / snapshot / template foundation.
-- Additive and re-runnable. Existing salary assignments, payroll runs, entries and published payslips are preserved.
-- Safe for phpMyAdmin re-import: existing columns, foreign keys, tables, permissions and migration marker are skipped.

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN prepared_by INT UNSIGNED NULL AFTER created_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='prepared_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN prepared_at DATETIME NULL AFTER prepared_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='prepared_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER prepared_at',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reviewed_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reviewed_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN approved_by INT UNSIGNED NULL AFTER reviewed_at',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='approved_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='approved_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN locked_by INT UNSIGNED NULL AFTER approved_at',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='locked_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN locked_at DATETIME NULL AFTER locked_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='locked_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN reopened_by INT UNSIGNED NULL AFTER locked_at',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reopened_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN reopened_at DATETIME NULL AFTER reopened_by',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reopened_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  "ALTER TABLE hr_payroll_runs ADD COLUMN reopen_reason VARCHAR(1000) NOT NULL DEFAULT '' AFTER reopened_at",
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reopen_reason'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN payslips_generated_at DATETIME NULL AFTER reopen_reason',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='payslips_generated_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD COLUMN published_at DATETIME NULL AFTER payslips_generated_at',
  'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='published_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD CONSTRAINT fk_payroll_runs_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id)',
  'SELECT 1')
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='prepared_by'
   AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD CONSTRAINT fk_payroll_runs_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)',
  'SELECT 1')
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reviewed_by'
   AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD CONSTRAINT fk_payroll_runs_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)',
  'SELECT 1')
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='approved_by'
   AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD CONSTRAINT fk_payroll_runs_locked_by FOREIGN KEY (locked_by) REFERENCES users(id)',
  'SELECT 1')
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='locked_by'
   AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
 SELECT IF(COUNT(*)=0,
  'ALTER TABLE hr_payroll_runs ADD CONSTRAINT fk_payroll_runs_reopened_by FOREIGN KEY (reopened_by) REFERENCES users(id)',
  'SELECT 1')
 FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_payroll_runs' AND COLUMN_NAME='reopened_by'
   AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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

INSERT IGNORE INTO permissions(code,label) VALUES
 ('payroll.salary.edit','Edit salary'),
 ('payroll.process','Process payroll'),
 ('payroll.adjust','Adjust payroll'),
 ('payroll.approve','Approve payroll'),
 ('payroll.lock','Lock payroll'),
 ('payroll.reopen','Reopen payroll'),
 ('payroll.payslip.generate','Generate payslips'),
 ('payroll.payslip.publish','Publish payslips'),
 ('payroll.reports','View payroll reports');

INSERT IGNORE INTO hr_migrations(name) VALUES('010-payroll-workflow');
