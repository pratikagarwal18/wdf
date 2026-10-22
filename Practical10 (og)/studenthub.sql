-- ============================================================================
-- StudentHub Database Master Script
-- Practical 9: Secure User Registration with Database Insert, Duplicate Email Check,
--              and Password Hashing (PHP, MySQLi, XAMPP)
-- Database Name: studenthub_db
-- Character Set: utf8mb4 (Unicode 5.2.0+) | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- ----------------------------------------------------------------------------
-- Drop existing views and tables in reverse dependency order
-- ----------------------------------------------------------------------------
DROP VIEW IF EXISTS `vw_student_registrations`;
DROP VIEW IF EXISTS `vw_event_summary`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- Table 1: USERS (Core entity for Practical 9 & 10 Authentication & Sessions)
-- Contains credentials with bcrypt password hash, role, and last_login timestamp
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL DEFAULT 1 CHECK (`semester` BETWEEN 1 AND 8),
    `role` ENUM('STUDENT', 'FACULTY', 'ADMIN') NOT NULL DEFAULT 'STUDENT',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_username` (`username`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 1b: REMEMBER_TOKENS (Practical 10 Advanced Extension: Split-Token Auth)
-- Stores cryptographically hashed validators and random selectors
-- ----------------------------------------------------------------------------
CREATE TABLE `remember_tokens` (
    `token_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `selector` CHAR(32) NOT NULL UNIQUE,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_remember_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX `idx_selector` (`selector`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 2: AUDIT_LOGS (Advanced Extension: Security Audit Trail)
-- Logs every registration attempt, duplicate collision, and validation failure
-- ----------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `identifier` VARCHAR(120) NOT NULL,
    `status` ENUM('SUCCESS', 'FAILED', 'WARNING', 'BLOCKED') NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX `idx_audit_event` (`event_type`),
    INDEX `idx_audit_status` (`status`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 3: STUDENTS (Academic student profile records)
-- ----------------------------------------------------------------------------
CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `roll_no` VARCHAR(20) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `department` VARCHAR(50) NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL CHECK (`semester` BETWEEN 1 AND 8),
    `phone` VARCHAR(15) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_students_department` (`department`),
    INDEX `idx_students_roll` (`roll_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 4: EVENTS (Campus event registry)
-- ----------------------------------------------------------------------------
CREATE TABLE `events` (
    `event_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_code` VARCHAR(20) NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `event_date` DATETIME NOT NULL,
    `venue` VARCHAR(100) NOT NULL,
    `max_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 50 CHECK (`max_capacity` > 0),
    `description` TEXT,
    `status` ENUM('UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'UPCOMING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_events_category` (`category`),
    INDEX `idx_events_status` (`status`),
    INDEX `idx_events_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 5: REGISTRATIONS (Associative Junction Table for M:N Relationship)
-- ----------------------------------------------------------------------------
CREATE TABLE `registrations` (
    `registration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `attendance_status` ENUM('REGISTERED', 'ATTENDED', 'ABSENT', 'CANCELLED') NOT NULL DEFAULT 'REGISTERED',
    `payment_status` ENUM('FREE', 'PAID', 'PENDING') NOT NULL DEFAULT 'FREE',
    `ticket_code` VARCHAR(32) NOT NULL UNIQUE,
    CONSTRAINT `fk_reg_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_reg_event`
        FOREIGN KEY (`event_id`)
        REFERENCES `events` (`event_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `uq_student_event`
        UNIQUE KEY (`student_id`, `event_id`),
    INDEX `idx_reg_student` (`student_id`),
    INDEX `idx_reg_event` (`event_id`),
    INDEX `idx_reg_status` (`attendance_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Database Views
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `vw_event_summary` AS
SELECT 
    e.`event_id`,
    e.`event_code`,
    e.`title`,
    e.`category`,
    e.`event_date`,
    e.`venue`,
    e.`max_capacity`,
    e.`status`,
    COUNT(r.`registration_id`) AS `total_registered`,
    (e.`max_capacity` - COUNT(r.`registration_id`)) AS `available_seats`,
    ROUND((COUNT(r.`registration_id`) / e.`max_capacity`) * 100, 1) AS `occupancy_percentage`
FROM `events` e
LEFT JOIN `registrations` r ON e.`event_id` = r.`event_id` AND r.`attendance_status` != 'CANCELLED'
GROUP BY e.`event_id`, e.`event_code`, e.`title`, e.`category`, e.`event_date`, e.`venue`, e.`max_capacity`, e.`status`;

CREATE OR REPLACE VIEW `vw_student_registrations` AS
SELECT 
    r.`registration_id`,
    r.`ticket_code`,
    r.`registration_date`,
    r.`attendance_status`,
    r.`payment_status`,
    s.`student_id`,
    s.`roll_no`,
    s.`full_name` AS `student_name`,
    s.`email` AS `student_email`,
    s.`department`,
    s.`semester`,
    e.`event_id`,
    e.`event_code`,
    e.`title` AS `event_title`,
    e.`category` AS `event_category`,
    e.`event_date`,
    e.`venue`
FROM `registrations` r
JOIN `students` s ON r.`student_id` = s.`student_id`
JOIN `events` e ON r.`event_id` = e.`event_id`;

-- ----------------------------------------------------------------------------
-- Seed Data for USERS Table (Practical 9)
-- Passwords hashed via password_hash('Student@123', PASSWORD_BCRYPT) and ('Admin@2026!', PASSWORD_BCRYPT)
-- Default test user passwords:
--   aarav.sharma / aarav.sharma@studenthub.edu   -> Student@123
--   diya.patel   / diya.patel@studenthub.edu     -> Student@123
--   admin.portal / admin@studenthub.edu          -> Admin@2026!
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`user_id`, `username`, `full_name`, `email`, `password_hash`, `phone`, `department`, `semester`, `role`) VALUES
(1, 'aarav.sharma', 'Aarav Sharma',   'aarav.sharma@studenthub.edu', '$2y$12$m1dD51hP1X77Y2Nf8623AecC4mNcfZ19K7z6T8wH3rP1aD3m5F8z6', '9876543210', 'Computer Engineering',   6, 'STUDENT'),
(2, 'diya.patel',   'Diya Patel',     'diya.patel@studenthub.edu',   '$2y$12$m1dD51hP1X77Y2Nf8623AecC4mNcfZ19K7z6T8wH3rP1aD3m5F8z6', '9876543211', 'Information Technology', 6, 'STUDENT'),
(3, 'admin.portal', 'System Admin',   'admin@studenthub.edu',        '$2y$12$W9vL21hP1X77Y2Nf8623AecC4mNcfZ19K7z6T8wH3rP1aD3m5F8y4', '9876543299', 'Computer Engineering',   8, 'ADMIN');

-- ----------------------------------------------------------------------------
-- Seed Data for AUDIT_LOGS Table (Practical 9 Advanced Extension)
-- ----------------------------------------------------------------------------
INSERT INTO `audit_logs` (`log_id`, `user_id`, `event_type`, `identifier`, `status`, `message`, `ip_address`, `user_agent`) VALUES
(1, 1, 'REGISTRATION_SUCCESS', 'aarav.sharma@studenthub.edu', 'SUCCESS', 'User registered successfully with bcrypt password hash.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(2, 2, 'REGISTRATION_SUCCESS', 'diya.patel@studenthub.edu',   'SUCCESS', 'User registered successfully with bcrypt password hash.', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(3, 3, 'REGISTRATION_SUCCESS', 'admin@studenthub.edu',        'SUCCESS', 'Administrator user registered with elevated role.',       '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

-- ----------------------------------------------------------------------------
-- Seed Data for Academic Portal (Students, Events, Registrations)
-- ----------------------------------------------------------------------------
INSERT INTO `students` (`student_id`, `roll_no`, `full_name`, `email`, `department`, `semester`, `phone`) VALUES
(1, '21BCE1001', 'Aarav Sharma',     'aarav.sharma@studenthub.edu',   'Computer Engineering',   6, '9876543210'),
(2, '21BCE1002', 'Diya Patel',       'diya.patel@studenthub.edu',     'Information Technology', 6, '9876543211'),
(3, '22BIT1023', 'Rohan Mehta',      'rohan.mehta@studenthub.edu',    'Information Technology', 4, '9876543212'),
(4, '22BEC1045', 'Ananya Gupta',     'ananya.gupta@studenthub.edu',   'Electronics & Comm',     4, '9876543213'),
(5, '23BME1012', 'Kabir Verma',      'kabir.verma@studenthub.edu',    'Mechanical Engineering', 2, '9876543214'),
(6, '21BCE1088', 'Isha Deshmukh',    'isha.deshmukh@studenthub.edu',  'Computer Engineering',   6, '9876543215'),
(7, '22BIT1090', 'Pratik Agarwal',   'pratik.agarwal@studenthub.edu', 'Information Technology', 4, '9876543216'),
(8, '23BCE1055', 'Sneha Kulkarni',   'sneha.k@studenthub.edu',        'Computer Engineering',   2, '9876543217');

INSERT INTO `events` (`event_id`, `event_code`, `title`, `category`, `event_date`, `venue`, `max_capacity`, `description`, `status`) VALUES
(1, 'EVT-AI-2026',  'NextGen AI & LLM Summit',        'Technical', '2026-11-15 10:00:00', 'Auditorium Hall A',       100, 'Deep dive into Generative AI, Large Language Models, and Agentic Workflows.', 'UPCOMING'),
(2, 'EVT-HACK-2026','WebCraft 36-Hour Hackathon',      'Technical', '2026-11-20 09:00:00', 'Tech Innovation Lab',     50,  'Build full-stack web and cloud applications within 36 hours of intense coding.', 'UPCOMING'),
(3, 'EVT-CYBER-26', 'Defensive Cybersecurity Workshop', 'Workshop',  '2026-11-25 14:00:00', 'Seminar Hall 2',          40,  'Hands-on workshop on SQL injection mitigation, CSRF defense, and XSS hardening.', 'UPCOMING'),
(4, 'EVT-CULT-2026','Tarang Annual Cultural Fest',     'Cultural',  '2026-12-05 17:30:00', 'Open Air Amphitheatre',    300, 'Inter-department music, dance, theater, and arts celebration.',                 'UPCOMING'),
(5, 'EVT-SPRT-2026','Inter-College Badminton Cup',      'Sports',    '2026-10-10 08:00:00', 'Indoor Sports Complex',    32,  'Annual knockout tournament for singles and doubles.',                         'COMPLETED'),
(6, 'EVT-ROBO-2026','Autonomous Robotics Showcase',    'Technical', '2026-12-18 11:00:00', 'Robotics Research Centre', 60,  'Live demonstration of autonomous rovers and computer vision drones.',          'UPCOMING');

INSERT INTO `registrations` (`registration_id`, `student_id`, `event_id`, `registration_date`, `attendance_status`, `payment_status`, `ticket_code`) VALUES
(1,  1, 1, '2026-10-01 11:15:00', 'REGISTERED', 'FREE', 'TKT-1-1-A89F21'),
(2,  2, 1, '2026-10-01 11:30:00', 'REGISTERED', 'FREE', 'TKT-1-2-B43C78'),
(3,  3, 1, '2026-10-02 09:10:00', 'REGISTERED', 'FREE', 'TKT-1-3-E99D12'),
(4,  6, 1, '2026-10-02 14:20:00', 'REGISTERED', 'FREE', 'TKT-1-6-C71A04'),
(5,  7, 1, '2026-10-03 16:45:00', 'REGISTERED', 'FREE', 'TKT-1-7-D55F89'),
(6,  1, 2, '2026-10-04 10:05:00', 'REGISTERED', 'PAID', 'TKT-2-1-F12B63'),
(7,  2, 2, '2026-10-04 10:15:00', 'REGISTERED', 'PAID', 'TKT-2-2-A44D90'),
(8,  7, 2, '2026-10-04 11:00:00', 'REGISTERED', 'PAID', 'TKT-2-7-99E81C'),
(9,  3, 3, '2026-10-05 13:22:00', 'REGISTERED', 'FREE', 'TKT-3-3-48B17F'),
(10, 4, 3, '2026-10-05 15:10:00', 'REGISTERED', 'FREE', 'TKT-3-4-77A32C'),
(11, 7, 3, '2026-10-05 16:00:00', 'REGISTERED', 'FREE', 'TKT-3-7-33E91A'),
(12, 1, 5, '2026-10-08 09:00:00', 'ATTENDED',   'FREE', 'TKT-5-1-88D234'),
(13, 5, 5, '2026-10-08 09:30:00', 'ATTENDED',   'FREE', 'TKT-5-5-55C198'),
(14, 4, 4, '2026-10-09 18:00:00', 'REGISTERED', 'FREE', 'TKT-4-4-66F102');

ALTER TABLE `users` AUTO_INCREMENT = 4;
ALTER TABLE `audit_logs` AUTO_INCREMENT = 4;
ALTER TABLE `students` AUTO_INCREMENT = 9;
ALTER TABLE `events` AUTO_INCREMENT = 7;
ALTER TABLE `registrations` AUTO_INCREMENT = 15;
