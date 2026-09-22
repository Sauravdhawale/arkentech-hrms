CREATE TABLE IF NOT EXISTS hr_bridge_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, device_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE, active TINYINT(1) NOT NULL DEFAULT 1,
 expires_at DATETIME NULL, last_used_at DATETIME NULL, created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(device_id) REFERENCES hr_records(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_bridge_health (
 device_id BIGINT UNSIGNED PRIMARY KEY, last_seen DATETIME NULL, last_success DATETIME NULL,
 device_online TINYINT(1) NOT NULL DEFAULT 0, last_device_timestamp DATETIME NULL,
 last_error VARCHAR(500) NOT NULL DEFAULT '', sync_requested_at DATETIME NULL, test_requested_at DATETIME NULL,
 FOREIGN KEY(device_id) REFERENCES hr_records(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_bridge_sync (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, device_id BIGINT UNSIGNED NOT NULL,
 started_at DATETIME NOT NULL, completed_at DATETIME NULL, received INT UNSIGNED NOT NULL DEFAULT 0,
 imported INT UNSIGNED NOT NULL DEFAULT 0, duplicates INT UNSIGNED NOT NULL DEFAULT 0,
 failed INT UNSIGNED NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL, error_message VARCHAR(500) NOT NULL DEFAULT '',
 FOREIGN KEY(device_id) REFERENCES hr_records(id), INDEX(device_id,started_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_punch_processing (
 punch_id BIGINT UNSIGNED PRIMARY KEY, device_id BIGINT UNSIGNED NOT NULL,
 employee_id INT UNSIGNED NULL, attendance_date DATE NULL, status VARCHAR(30) NOT NULL DEFAULT 'Pending',
 error_message VARCHAR(500) NOT NULL DEFAULT '', raw_payload TEXT NOT NULL,
 fingerprint CHAR(64) NOT NULL UNIQUE, processed_at DATETIME NULL,
 FOREIGN KEY(punch_id) REFERENCES hr_punches(id), FOREIGN KEY(device_id) REFERENCES hr_records(id),
 FOREIGN KEY(employee_id) REFERENCES users(id), INDEX(employee_id,attendance_date)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hr_manual_punch_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, attendance_date DATE NOT NULL,
 action VARCHAR(20) NOT NULL, punched_at DATETIME NOT NULL, source VARCHAR(30) NOT NULL,
 actor_id INT UNSIGNED NOT NULL, notes TEXT NOT NULL, request_key CHAR(64) NOT NULL UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(employee_id) REFERENCES users(id), FOREIGN KEY(actor_id) REFERENCES users(id), INDEX(employee_id,attendance_date)
) ENGINE=InnoDB;
