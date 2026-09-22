CREATE TABLE IF NOT EXISTS hr_attendance (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, attendance_date DATE NOT NULL,
 check_in DATETIME NOT NULL, check_out DATETIME NULL, shift_id BIGINT UNSIGNED NULL, shift_snapshot LONGTEXT NOT NULL,
 working_minutes INT UNSIGNED NOT NULL DEFAULT 0, late_minutes INT UNSIGNED NOT NULL DEFAULT 0, early_minutes INT UNSIGNED NOT NULL DEFAULT 0, overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
 status VARCHAR(30) NOT NULL, source VARCHAR(20) NOT NULL DEFAULT 'Admin', notes TEXT NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, updated_by INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY employee_day(employee_id,attendance_date), INDEX attendance_day(attendance_date),
 FOREIGN KEY(employee_id) REFERENCES users(id), FOREIGN KEY(shift_id) REFERENCES hr_records(id), FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_leave_details (
 request_id BIGINT UNSIGNED PRIMARY KEY, type_record_id BIGINT UNSIGNED NULL, day_part VARCHAR(20) NOT NULL DEFAULT 'Full Day', days DECIMAL(6,2) NOT NULL,
 policy_snapshot LONGTEXT NOT NULL, attachment_record_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(request_id) REFERENCES hr_requests(id), FOREIGN KEY(type_record_id) REFERENCES hr_records(id), FOREIGN KEY(attachment_record_id) REFERENCES hr_records(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_leave_days (
 request_id BIGINT UNSIGNED NOT NULL, leave_date DATE NOT NULL, units DECIMAL(3,2) NOT NULL, day_part VARCHAR(20) NOT NULL,
 PRIMARY KEY(request_id,leave_date), INDEX leave_day(leave_date), FOREIGN KEY(request_id) REFERENCES hr_requests(id)
) ENGINE=InnoDB;
