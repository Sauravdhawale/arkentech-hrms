-- Phase 4 / Leave Management connectivity upgrade
-- Additive migration: preserves existing hr_requests, hr_records, attendance and leave history.

ALTER TABLE hr_requests
 MODIFY status ENUM('Draft','Pending','Approved','Rejected','Cancelled','Withdrawn','Resolved') NOT NULL DEFAULT 'Pending',
 ADD COLUMN IF NOT EXISTS submitted_by INT UNSIGNED NULL AFTER reviewer_id,
 ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER submitted_by,
 ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER submitted_at,
 ADD COLUMN IF NOT EXISTS approval_comment TEXT NULL AFTER reviewed_at,
 ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approval_comment,
 ADD INDEX IF NOT EXISTS request_kind_status_dates(kind,status,start_date,end_date);

CREATE TABLE IF NOT EXISTS hr_leave_balances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id INT UNSIGNED NOT NULL,
 leave_type_record_id BIGINT UNSIGNED NULL,
 leave_code VARCHAR(40) NOT NULL,
 leave_year SMALLINT UNSIGNED NOT NULL,
 entitled DECIMAL(8,2) NOT NULL DEFAULT 0,
 available DECIMAL(8,2) NOT NULL DEFAULT 0,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY employee_leave_year(employee_id,leave_code,leave_year),
 INDEX leave_balance_type(leave_type_record_id,leave_year),
 FOREIGN KEY(employee_id) REFERENCES users(id),
 FOREIGN KEY(leave_type_record_id) REFERENCES hr_records(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_leave_balance_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 balance_id BIGINT UNSIGNED NOT NULL,
 employee_id INT UNSIGNED NOT NULL,
 leave_type_record_id BIGINT UNSIGNED NULL,
 leave_code VARCHAR(40) NOT NULL,
 leave_year SMALLINT UNSIGNED NOT NULL,
 transaction_type VARCHAR(40) NOT NULL,
 amount DECIMAL(8,2) NOT NULL,
 reference_kind VARCHAR(40) NOT NULL DEFAULT '',
 reference_id BIGINT UNSIGNED NULL,
 unique_key VARCHAR(190) NOT NULL,
 balance_before DECIMAL(8,2) NOT NULL,
 balance_after DECIMAL(8,2) NOT NULL,
 transaction_date DATE NOT NULL,
 remarks VARCHAR(1000) NOT NULL DEFAULT '',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY leave_transaction_key(unique_key),
 INDEX leave_transaction_employee(employee_id,leave_year,leave_code),
 INDEX leave_transaction_reference(reference_kind,reference_id),
 FOREIGN KEY(balance_id) REFERENCES hr_leave_balances(id),
 FOREIGN KEY(employee_id) REFERENCES users(id),
 FOREIGN KEY(leave_type_record_id) REFERENCES hr_records(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_leave_absence_prompts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id INT UNSIGNED NOT NULL,
 absence_date DATE NOT NULL,
 shift_id BIGINT UNSIGNED NULL,
 status ENUM('Open','Dismissed','Pending','Resolved') NOT NULL DEFAULT 'Open',
 leave_request_id BIGINT UNSIGNED NULL,
 detected_at DATETIME NOT NULL,
 dismissed_at DATETIME NULL,
 resolved_at DATETIME NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY employee_absence_prompt(employee_id,absence_date),
 INDEX absence_prompt_state(employee_id,status,absence_date),
 FOREIGN KEY(employee_id) REFERENCES users(id),
 FOREIGN KEY(shift_id) REFERENCES hr_records(id),
 FOREIGN KEY(leave_request_id) REFERENCES hr_requests(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hr_leave_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 absence_grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 absence_prompt_lookback_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO hr_leave_settings(id,absence_grace_minutes,absence_prompt_lookback_days) VALUES(1,30,30);
