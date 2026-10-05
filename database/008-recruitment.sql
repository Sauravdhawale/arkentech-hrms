-- Phase 4A Recruitment / ATS foundation
-- Additive only: no existing employee, document, attendance, leave or payroll data is modified.

CREATE TABLE IF NOT EXISTS recruitment_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_code VARCHAR(60) NOT NULL UNIQUE,
 title VARCHAR(190) NOT NULL,
 department_id INT UNSIGNED NULL,
 designation_id INT UNSIGNED NULL,
 vacancies INT UNSIGNED NOT NULL DEFAULT 1,
 employment_type VARCHAR(30) NOT NULL DEFAULT 'Full Time',
 experience_required VARCHAR(120) NOT NULL DEFAULT '',
 location VARCHAR(190) NOT NULL DEFAULT '',
 salary_range VARCHAR(120) NOT NULL DEFAULT '',
 description TEXT NOT NULL,
 skills_required TEXT NOT NULL,
 hiring_manager_id INT UNSIGNED NULL,
 opening_date DATE NULL,
 closing_date DATE NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Draft',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(department_id) REFERENCES departments(id),
 FOREIGN KEY(designation_id) REFERENCES designations(id),
 FOREIGN KEY(hiring_manager_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX recruitment_job_status(status,closing_date),
 INDEX recruitment_job_department(department_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_candidates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 candidate_code VARCHAR(60) NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL,
 middle_name VARCHAR(100) NOT NULL DEFAULT '',
 last_name VARCHAR(100) NOT NULL DEFAULT '',
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(40) NOT NULL DEFAULT '',
 alternate_phone VARCHAR(40) NOT NULL DEFAULT '',
 current_location VARCHAR(190) NOT NULL DEFAULT '',
 preferred_location VARCHAR(190) NOT NULL DEFAULT '',
 current_company VARCHAR(190) NOT NULL DEFAULT '',
 current_designation VARCHAR(190) NOT NULL DEFAULT '',
 total_experience VARCHAR(80) NOT NULL DEFAULT '',
 relevant_experience VARCHAR(80) NOT NULL DEFAULT '',
 current_ctc VARCHAR(80) NOT NULL DEFAULT '',
 expected_ctc VARCHAR(80) NOT NULL DEFAULT '',
 notice_period VARCHAR(80) NOT NULL DEFAULT '',
 skills TEXT NOT NULL,
 source VARCHAR(50) NOT NULL DEFAULT 'Other',
 linkedin_url VARCHAR(500) NOT NULL DEFAULT '',
 notes TEXT NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'Active',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX recruitment_candidate_email(email),
 INDEX recruitment_candidate_status(status,source)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_code VARCHAR(60) NOT NULL UNIQUE,
 candidate_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NOT NULL,
 applied_date DATE NOT NULL,
 source VARCHAR(50) NOT NULL DEFAULT 'Other',
 stage VARCHAR(40) NOT NULL DEFAULT 'Applied',
 status VARCHAR(40) NOT NULL DEFAULT 'Active',
 assigned_recruiter_id INT UNSIGNED NULL,
 notes TEXT NOT NULL,
 last_action VARCHAR(255) NOT NULL DEFAULT '',
 next_action VARCHAR(255) NOT NULL DEFAULT '',
 employee_id INT UNSIGNED NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(candidate_id) REFERENCES recruitment_candidates(id),
 FOREIGN KEY(job_id) REFERENCES recruitment_jobs(id),
 FOREIGN KEY(assigned_recruiter_id) REFERENCES users(id),
 FOREIGN KEY(employee_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 UNIQUE KEY candidate_job(candidate_id,job_id),
 INDEX recruitment_application_stage(stage,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_interviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 round_no INT UNSIGNED NOT NULL DEFAULT 1,
 round_name VARCHAR(120) NOT NULL,
 interview_type VARCHAR(40) NOT NULL DEFAULT 'Video',
 interview_date DATE NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NULL,
 location_meeting_link VARCHAR(500) NOT NULL DEFAULT '',
 notes TEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX recruitment_interview_date(interview_date,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_interviewers (
 interview_id BIGINT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(interview_id,user_id),
 FOREIGN KEY(interview_id) REFERENCES recruitment_interviews(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_interview_feedback (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 interview_id BIGINT UNSIGNED NOT NULL,
 interviewer_id INT UNSIGNED NOT NULL,
 technical_rating TINYINT UNSIGNED NULL,
 communication_rating TINYINT UNSIGNED NULL,
 experience_rating TINYINT UNSIGNED NULL,
 culture_fit_rating TINYINT UNSIGNED NULL,
 overall_rating TINYINT UNSIGNED NULL,
 strengths TEXT NOT NULL,
 concerns TEXT NOT NULL,
 comments TEXT NOT NULL,
 recommendation VARCHAR(30) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(interview_id) REFERENCES recruitment_interviews(id) ON DELETE CASCADE,
 FOREIGN KEY(interviewer_id) REFERENCES users(id),
 UNIQUE KEY interview_feedback_once(interview_id,interviewer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_offers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL UNIQUE,
 department_id INT UNSIGNED NULL,
 designation_id INT UNSIGNED NULL,
 employment_type VARCHAR(30) NOT NULL DEFAULT 'Full Time',
 joining_date DATE NULL,
 probation_period VARCHAR(80) NOT NULL DEFAULT '',
 ctc DECIMAL(14,2) NULL,
 offer_expiry_date DATE NULL,
 reporting_manager_id INT UNSIGNED NULL,
 work_location VARCHAR(190) NOT NULL DEFAULT '',
 terms LONGTEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Draft',
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(department_id) REFERENCES departments(id),
 FOREIGN KEY(designation_id) REFERENCES designations(id),
 FOREIGN KEY(reporting_manager_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX recruitment_offer_status(status,offer_expiry_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recruitment_candidate_files (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 candidate_id BIGINT UNSIGNED NOT NULL,
 purpose VARCHAR(40) NOT NULL DEFAULT 'resume',
 filename VARCHAR(190) NOT NULL,
 mime VARCHAR(80) NOT NULL,
 content MEDIUMBLOB NOT NULL,
 uploaded_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(candidate_id) REFERENCES recruitment_candidates(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id),
 INDEX candidate_file(candidate_id,purpose)
) ENGINE=InnoDB;
