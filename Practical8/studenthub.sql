CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

DROP VIEW IF EXISTS `vw_student_registrations`;
DROP VIEW IF EXISTS `vw_event_summary`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;

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

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_register_student_for_event`$$
CREATE PROCEDURE `sp_register_student_for_event`(
    IN  p_student_id   INT,
    IN  p_event_id     INT,
    OUT p_status       VARCHAR(50),
    OUT p_message      VARCHAR(255),
    OUT p_ticket_code  VARCHAR(32)
)
proc_label: BEGIN
    DECLARE v_student_exists INT DEFAULT 0;
    DECLARE v_event_exists INT DEFAULT 0;
    DECLARE v_event_status VARCHAR(20);
    DECLARE v_max_capacity INT DEFAULT 0;
    DECLARE v_current_registrations INT DEFAULT 0;
    DECLARE v_already_registered INT DEFAULT 0;
    DECLARE v_new_ticket VARCHAR(32);

    SET p_status = 'ERROR';
    SET p_message = 'Internal registration error';
    SET p_ticket_code = NULL;

    SELECT COUNT(*) INTO v_student_exists FROM `students` WHERE `student_id` = p_student_id;
    IF v_student_exists = 0 THEN
        SET p_status = 'NOT_FOUND';
        SET p_message = CONCAT('Student with ID ', p_student_id, ' does not exist.');
        LEAVE proc_label;
    END IF;

    SELECT COUNT(*), status, max_capacity 
    INTO v_event_exists, v_event_status, v_max_capacity 
    FROM `events` 
    WHERE `event_id` = p_event_id
    GROUP BY status, max_capacity;

    IF v_event_exists = 0 THEN
        SET p_status = 'NOT_FOUND';
        SET p_message = CONCAT('Event with ID ', p_event_id, ' does not exist.');
        LEAVE proc_label;
    END IF;

    IF v_event_status != 'UPCOMING' THEN
        SET p_status = 'EVENT_INACTIVE';
        SET p_message = CONCAT('Cannot register. Event is currently marked as ', v_event_status, '.');
        LEAVE proc_label;
    END IF;

    SELECT COUNT(*) INTO v_already_registered 
    FROM `registrations` 
    WHERE `student_id` = p_student_id 
      AND `event_id` = p_event_id 
      AND `attendance_status` != 'CANCELLED';

    IF v_already_registered > 0 THEN
        SET p_status = 'ALREADY_REGISTERED';
        SET p_message = 'Student is already registered for this event.';
        LEAVE proc_label;
    END IF;

    SELECT COUNT(*) INTO v_current_registrations 
    FROM `registrations` 
    WHERE `event_id` = p_event_id 
      AND `attendance_status` != 'CANCELLED';

    IF v_current_registrations >= v_max_capacity THEN
        SET p_status = 'EVENT_FULL';
        SET p_message = CONCAT('Event capacity of ', v_max_capacity, ' seats has been reached.');
        LEAVE proc_label;
    END IF;

    SET v_new_ticket = CONCAT('TKT-', p_event_id, '-', p_student_id, '-', UPPER(SUBSTRING(MD5(RAND()), 1, 6)));

    START TRANSACTION;
        INSERT INTO `registrations` (
            `student_id`, 
            `event_id`, 
            `attendance_status`, 
            `payment_status`, 
            `ticket_code`
        ) VALUES (
            p_student_id, 
            p_event_id, 
            'REGISTERED', 
            'FREE', 
            v_new_ticket
        );
    COMMIT;

    SET p_status = 'SUCCESS';
    SET p_message = 'Registration completed successfully.';
    SET p_ticket_code = v_new_ticket;

END proc_label$$

DROP PROCEDURE IF EXISTS `sp_get_event_roster`$$
CREATE PROCEDURE `sp_get_event_roster`(
    IN p_event_id INT
)
BEGIN
    SELECT 
        r.`registration_id`,
        r.`ticket_code`,
        r.`registration_date`,
        r.`attendance_status`,
        s.`student_id`,
        s.`roll_no`,
        s.`full_name`,
        s.`email`,
        s.`department`,
        s.`semester`
    FROM `registrations` r
    JOIN `students` s ON r.`student_id` = s.`student_id`
    WHERE r.`event_id` = p_event_id
    ORDER BY r.`registration_date` ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_get_student_summary`$$
CREATE PROCEDURE `sp_get_student_summary`(
    IN  p_student_id INT,
    OUT p_total_registered INT,
    OUT p_total_attended INT
)
BEGIN
    SELECT 
        COUNT(*),
        COALESCE(SUM(CASE WHEN `attendance_status` = 'ATTENDED' THEN 1 ELSE 0 END), 0)
    INTO 
        p_total_registered,
        p_total_attended
    FROM `registrations`
    WHERE `student_id` = p_student_id
      AND `attendance_status` != 'CANCELLED';
END$$

DELIMITER ;

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

ALTER TABLE `students` AUTO_INCREMENT = 9;
ALTER TABLE `events` AUTO_INCREMENT = 7;
ALTER TABLE `registrations` AUTO_INCREMENT = 15;
