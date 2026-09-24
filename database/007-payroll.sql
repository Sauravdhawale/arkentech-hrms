CREATE TABLE IF NOT EXISTS hr_salary_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id INT UNSIGNED NOT NULL, structure_id BIGINT UNSIGNED NOT NULL,
 effective_from DATE NOT NULL, effective_to DATE NULL,
 monthly_gross BIGINT NOT NULL, annual_ctc BIGINT NOT NULL,
 snapshot LONGTEXT NOT NULL, reason VARCHAR(1000) NOT NULL, notes TEXT NOT NULL,
 created_by INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY salary_start(employee_id,effective_from), INDEX salary_period(employee_id,effective_from,effective_to),
 FOREIGN KEY(employee_id) REFERENCES employees(user_id), FOREIGN KEY(structure_id) REFERENCES hr_records(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_payroll_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, month CHAR(7) NOT NULL UNIQUE,
 status VARCHAR(20) NOT NULL DEFAULT 'Draft', version INT UNSIGNED NOT NULL DEFAULT 1,
 settings_snapshot LONGTEXT NOT NULL, created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 finalized_at DATETIME NULL, paid_at DATETIME NULL, FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_payroll_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, run_id BIGINT UNSIGNED NOT NULL,
 employee_id INT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Draft',
 gross BIGINT NOT NULL DEFAULT 0, deductions BIGINT NOT NULL DEFAULT 0, net BIGINT NOT NULL DEFAULT 0,
 employer_cost BIGINT NOT NULL DEFAULT 0, snapshot LONGTEXT NOT NULL, exceptions TEXT NOT NULL,
 payslip_number VARCHAR(100) NULL UNIQUE, published_at DATETIME NULL,
 UNIQUE KEY payroll_employee(run_id,employee_id),
 FOREIGN KEY(run_id) REFERENCES hr_payroll_runs(id), FOREIGN KEY(employee_id) REFERENCES employees(user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_payroll_adjustments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entry_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(30) NOT NULL, component VARCHAR(80) NOT NULL, amount BIGINT NOT NULL DEFAULT 0,
 units DECIMAL(12,4) NOT NULL DEFAULT 0, reason VARCHAR(1000) NOT NULL,
 created_by INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
 FOREIGN KEY(entry_id) REFERENCES hr_payroll_entries(id), FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(approved_by) REFERENCES users(id), INDEX adjustment_entry(entry_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_payroll_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, run_id BIGINT UNSIGNED NULL, entry_id BIGINT UNSIGNED NULL,
 actor_id INT UNSIGNED NOT NULL, action VARCHAR(80) NOT NULL, reason VARCHAR(1000) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(run_id) REFERENCES hr_payroll_runs(id), FOREIGN KEY(entry_id) REFERENCES hr_payroll_entries(id),
 FOREIGN KEY(actor_id) REFERENCES users(id), INDEX payroll_event_run(run_id,id)
) ENGINE=InnoDB;
