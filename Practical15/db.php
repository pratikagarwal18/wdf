<?php
date_default_timezone_set('Asia/Kolkata');

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'studenthub_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

function getMySQLiConnection($selectDatabase = true) {
    $db = $selectDatabase ? DB_NAME : null;
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, $db, DB_PORT);
    if ($mysqli->connect_errno) return null;
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}

function getDBConnection($selectDatabase = true) {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ($selectDatabase ? ";dbname=" . DB_NAME : "") . ";charset=" . DB_CHARSET;
    try {
        return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) {
        return null;
    }
}

function testMySQLiConnection() {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return ['success' => false, 'message' => 'MySQL connection failed.'];
    return ['success' => true, 'message' => 'Connected successfully via MySQLi!'];
}

function testDBConnection() {
    $pdo = getDBConnection(true);
    if (!$pdo) return ['success' => false, 'message' => 'PDO connection failed.'];
    return ['success' => true, 'message' => 'Connected successfully via PDO!'];
}

function safeFetchAll($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function checkDuplicateUser($mysqli, $email, $username, $excludeUserId = 0) {
    $dupEmail = false;
    $dupUser = false;
    if (!empty($email)) {
        $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) AND user_id != ?");
        $stmt->bind_param("si", $email, $excludeUserId);
        $stmt->execute();
        $dupEmail = ($stmt->get_result()->num_rows > 0);
        $stmt->close();
    }
    if (!empty($username)) {
        $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) AND user_id != ?");
        $stmt->bind_param("si", $username, $excludeUserId);
        $stmt->execute();
        $dupUser = ($stmt->get_result()->num_rows > 0);
        $stmt->close();
    }
    return ['is_duplicate' => ($dupEmail || $dupUser), 'duplicate_email' => $dupEmail, 'duplicate_username' => $dupUser];
}

function insertUserRecord($mysqli, $userData) {
    $sql = "INSERT INTO users (username, full_name, email, password_hash, phone, department, semester, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return ['success' => false, 'user_id' => null, 'error' => $mysqli->error];
    $sem = (int)($userData['semester'] ?? 1);
    $role = $userData['role'] ?? 'STUDENT';
    $ph = $userData['phone'] ?? '';
    $stmt->bind_param("ssssssis", $userData['username'], $userData['full_name'], $userData['email'], $userData['password_hash'], $ph, $userData['department'], $sem, $role);
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return ['success' => $ok, 'user_id' => $id];
}

function updateUserLastLogin($userId) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;
    $stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ensureStudentsTableReady(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS `students` (
        `student_id` INT AUTO_INCREMENT PRIMARY KEY,
        `roll_no` VARCHAR(20) NOT NULL UNIQUE,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `department` VARCHAR(60) NOT NULL,
        `course` VARCHAR(80) NOT NULL DEFAULT 'Computer Engineering',
        `semester` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `phone` VARCHAR(15) NOT NULL,
        `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function fetchStudentsList(mysqli $mysqli, array $filters = [], $page = 1, $perPage = 10, $sortCol = 'roll_no', $sortDir = 'ASC') {
    ensureStudentsTableReady($mysqli);
    $where = [];
    if (!empty($filters['search'])) {
        $s = $mysqli->real_escape_string(trim($filters['search']));
        $where[] = "(full_name LIKE '%$s%' OR email LIKE '%$s%' OR roll_no LIKE '%$s%')";
    }
    if (!empty($filters['department'])) {
        $d = $mysqli->real_escape_string(trim($filters['department']));
        $where[] = "department = '$d'";
    }
    $w = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $offset = (max(1, (int)$page) - 1) * max(1, (int)$perPage);
    $res = $mysqli->query("SELECT * FROM students $w ORDER BY student_id DESC LIMIT " . (int)$perPage . " OFFSET $offset");
    $records = [];
    while ($r = $res->fetch_assoc()) { $records[] = $r; }
    $total = (int)$mysqli->query("SELECT COUNT(*) FROM students $w")->fetch_row()[0];
    return ['data' => $records, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, ceil($total / $perPage))];
}

function getStudentRecord(mysqli $mysqli, $studentId) {
    ensureStudentsTableReady($mysqli);
    $stmt = $mysqli->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r;
}

function saveStudentRecord(mysqli $mysqli, array $data, $studentId = null) {
    ensureStudentsTableReady($mysqli);
    $roll = strtoupper(trim($data['roll_no'] ?? ''));
    $name = trim($data['full_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $dept = trim($data['department'] ?? 'Computer Engineering');
    $course = trim($data['course'] ?? $dept);
    $sem = (int)($data['semester'] ?? 1);
    $status = in_array($data['status'] ?? '', ['ACTIVE', 'INACTIVE']) ? $data['status'] : 'ACTIVE';

    if (empty($roll) || empty($name) || empty($email)) {
        return ['success' => false, 'message' => 'Roll number, name, and email are required.'];
    }

    if ($studentId === null) {
        $stmt = $mysqli->prepare("INSERT INTO students (roll_no, full_name, email, department, course, semester, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiss", $roll, $name, $email, $dept, $course, $sem, $phone, $status);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? "Student added successfully." : "Error: $err", 'student_id' => $newId];
    } else {
        $stmt = $mysqli->prepare("UPDATE students SET roll_no=?, full_name=?, email=?, department=?, course=?, semester=?, phone=?, status=? WHERE student_id=?");
        $stmt->bind_param("sssssissi", $roll, $name, $email, $dept, $course, $sem, $phone, $status, $studentId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? "Student updated successfully." : "Update failed."];
    }
}

function deleteStudentRecord(mysqli $mysqli, $studentId) {
    ensureStudentsTableReady($mysqli);
    $stmt = $mysqli->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $studentId);
    $ok = $stmt->execute();
    $stmt->close();
    return ['success' => $ok, 'message' => $ok ? "Student record deleted successfully." : "Delete failed."];
}

function getStudentSummaryMetrics(mysqli $mysqli) {
    ensureStudentsTableReady($mysqli);
    $tot = (int)$mysqli->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
    return ['total' => $tot];
}

function ensureEventsTableReady(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS `events` (
        `event_id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_code` VARCHAR(30) NULL,
        `title` VARCHAR(150) NOT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'General',
        `event_date` DATE NOT NULL,
        `event_time` TIME NULL,
        `venue` VARCHAR(120) NOT NULL,
        `capacity` INT NOT NULL DEFAULT 100,
        `max_capacity` INT NOT NULL DEFAULT 100,
        `fee` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        `description` TEXT NULL,
        `poster_path` VARCHAR(255) NULL,
        `poster_image` VARCHAR(255) NULL,
        `status` ENUM('UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED', 'ACTIVE') NOT NULL DEFAULT 'UPCOMING',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function validatePosterUpload(array $file, bool $isRequired = false) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => !$isRequired, 'error' => $isRequired ? 'Poster image is required.' : null];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['valid' => false, 'error' => 'Poster file size exceeds 2MB limit.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        return ['valid' => false, 'error' => 'Only JPG, PNG, and WEBP formats allowed.'];
    }
    return ['valid' => true, 'extension' => $ext];
}

function fetchEventsList(mysqli $mysqli, array $filters = [], $page = 1, $perPage = 9, $sortCol = 'event_date', $sortDir = 'ASC') {
    ensureEventsTableReady($mysqli);
    $res = $mysqli->query("SELECT * FROM events ORDER BY event_date ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) { $data[] = $r; }
    return ['data' => $data, 'total' => count($data)];
}

function getEventRecord(mysqli $mysqli, $eventId) {
    ensureEventsTableReady($mysqli);
    $stmt = $mysqli->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r;
}

function saveEventRecord(mysqli $mysqli, array $data, ?array $posterFile = null, ?int $eventId = null) {
    ensureEventsTableReady($mysqli);
    $title = trim($data['title'] ?? '');
    $cat = trim($data['category'] ?? 'General');
    $date = trim($data['event_date'] ?? date('Y-m-d'));
    $venue = trim($data['venue'] ?? '');
    $cap = max(1, (int)($data['capacity'] ?? $data['max_capacity'] ?? 100));
    $fee = (float)($data['fee'] ?? 0);
    $desc = trim($data['description'] ?? '');

    if (empty($title) || empty($venue)) {
        return ['success' => false, 'message' => 'Event title and venue are required.'];
    }

    $posterPath = null;
    if ($posterFile && !empty($posterFile['name'])) {
        $val = validatePosterUpload($posterFile);
        if (!$val['valid']) return ['success' => false, 'message' => $val['error']];
        $uploadDir = __DIR__ . '/uploads/posters';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = 'poster_' . time() . '.' . $val['extension'];
        move_uploaded_file($posterFile['tmp_name'], $uploadDir . '/' . $fileName);
        $posterPath = 'uploads/posters/' . $fileName;
    }

    if ($eventId === null) {
        $stmt = $mysqli->prepare("INSERT INTO events (title, category, event_date, venue, capacity, max_capacity, fee, description, poster_path, poster_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')");
        $stmt->bind_param("ssssiidsss", $title, $cat, $date, $venue, $cap, $cap, $fee, $desc, $posterPath, $posterPath);
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return ['success' => $ok, 'message' => 'Event created successfully!', 'event_id' => $id];
    } else {
        $sql = "UPDATE events SET title=?, category=?, event_date=?, venue=?, capacity=?, fee=?, description=?" . ($posterPath ? ", poster_path=?" : "") . " WHERE event_id=?";
        $stmt = $mysqli->prepare($sql);
        if ($posterPath) {
            $stmt->bind_param("ssssidsisi", $title, $cat, $date, $venue, $cap, $fee, $desc, $posterPath, $eventId);
        } else {
            $stmt->bind_param("ssssidsi", $title, $cat, $date, $venue, $cap, $fee, $desc, $eventId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => 'Event updated successfully!'];
    }
}

function deleteEventRecord(mysqli $mysqli, $eventId) {
    ensureEventsTableReady($mysqli);
    $stmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);
    $ok = $stmt->execute();
    $stmt->close();
    return ['success' => $ok, 'message' => $ok ? 'Event deleted successfully.' : 'Delete failed.'];
}

function getEventSummaryMetrics(mysqli $mysqli) {
    ensureEventsTableReady($mysqli);
    $tot = (int)$mysqli->query("SELECT COUNT(*) FROM events")->fetch_row()[0];
    return ['total' => $tot];
}

function ensureAuditLogsTableReady(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS audit_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function logAuditEvent(mysqli $mysqli, $username, $action, $details, $userId = null) {
    ensureAuditLogsTableReady($mysqli);
    $stmt = $mysqli->prepare("INSERT INTO audit_logs (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("isss", $userId, $username, $action, $details);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function getAuditLogs(mysqli $mysqli, $limit = 10) {
    ensureAuditLogsTableReady($mysqli);
    $res = $mysqli->query("SELECT * FROM audit_logs ORDER BY log_id DESC LIMIT " . (int)$limit);
    $logs = [];
    while ($r = $res->fetch_assoc()) { $logs[] = $r; }
    return $logs;
}

function getAdminDashboardMetrics(mysqli $mysqli) {
    $u = (int)$mysqli->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $s = (int)$mysqli->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
    $e = (int)$mysqli->query("SELECT COUNT(*) FROM events")->fetch_row()[0];
    $r = (int)$mysqli->query("SELECT COUNT(*) FROM registrations")->fetch_row()[0];
    return ['total_users' => $u, 'total_students' => $s, 'total_events' => $e, 'total_registrations' => $r];
}

function getLatestRegistrations(mysqli $mysqli, $limit = 5) {
    $sql = "SELECT r.ticket_code, r.registration_date, r.attendance_status, s.full_name, e.title 
            FROM registrations r 
            JOIN students s ON r.student_id = s.student_id 
            JOIN events e ON r.event_id = e.event_id 
            ORDER BY r.registration_id DESC LIMIT " . (int)$limit;
    $res = $mysqli->query($sql);
    $list = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) { $list[] = $r; }
    }
    return $list;
}
