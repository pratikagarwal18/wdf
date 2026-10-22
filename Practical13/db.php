<?php
/**
 * Database Connection & Helper Functions
 * Practical: Web Development Fundamentals (WDF)
 */

date_default_timezone_set('Asia/Kolkata');

// Database configuration settings
if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    3306);
if (!defined('DB_NAME'))    define('DB_NAME',    'studenthub_db');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Default global connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

/**
 * Get MySQLi connection
 */
function getMySQLiConnection($selectDatabase = true) {
    $db = $selectDatabase ? DB_NAME : null;
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, $db, DB_PORT);
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}

/**
 * Get PDO connection
 */
function getDBConnection($selectDatabase = true) {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ($selectDatabase ? ";dbname=" . DB_NAME : "") . ";charset=" . DB_CHARSET;
    try {
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Test MySQLi Connection (Used in Practical 8)
 */
function testMySQLiConnection() {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) {
        return [
            'success' => false,
            'message' => 'MySQL connection failed. Ensure MySQL is running in XAMPP.',
            'host'    => DB_HOST . ':' . DB_PORT,
            'database'=> DB_NAME,
            'user'    => DB_USER,
            'driver'  => 'MySQLi'
        ];
    }

    $tables = [];
    $tableCounts = [];
    $res = $mysqli->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_row()) {
            $t = $row[0];
            $tables[] = $t;
            $cRes = $mysqli->query("SELECT COUNT(*) FROM `{$t}`");
            $tableCounts[$t] = $cRes ? (int)$cRes->fetch_row()[0] : 0;
        }
    }

    return [
        'success'      => true,
        'message'      => 'Connected successfully via MySQLi!',
        'host'         => DB_HOST . ':' . DB_PORT,
        'database'     => DB_NAME,
        'user'         => DB_USER,
        'driver'       => 'MySQLi',
        'tables'       => $tables,
        'table_counts' => $tableCounts
    ];
}

/**
 * Test PDO Connection (Used in Practical 8)
 */
function testDBConnection() {
    $pdo = getDBConnection(true);
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'PDO connection failed. Ensure MySQL is running in XAMPP.',
            'host'    => DB_HOST . ':' . DB_PORT,
            'database'=> DB_NAME,
            'user'    => DB_USER,
            'driver'  => 'PDO'
        ];
    }

    $tables = [];
    $tableCounts = [];
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            $tableCounts[$t] = (int)$cnt;
        }
    } catch (Exception $e) {}

    return [
        'success'      => true,
        'message'      => 'Connected successfully via PDO!',
        'host'         => DB_HOST . ':' . DB_PORT,
        'database'     => DB_NAME,
        'user'         => DB_USER,
        'driver'       => 'PDO',
        'tables'       => $tables,
        'table_counts' => $tableCounts
    ];
}

/**
 * Helper to fetch all records using PDO
 */
function safeFetchAll($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Check duplicate email or username
 */
function checkDuplicateUser($mysqli, $email, $username, $excludeUserId = 0) {
    $result = ['has_duplicate' => false, 'duplicate_email' => false, 'duplicate_username' => false, 'matched_user' => null];
    if (!$mysqli) return $result;

    $stmt = $mysqli->prepare("SELECT user_id, username, email FROM users WHERE (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) AND user_id != ? LIMIT 1");
    if (!$stmt) return $result;

    $stmt->bind_param("ssi", $email, $username, $excludeUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $result['has_duplicate'] = true;
        $result['matched_user'] = $row;
        $result['duplicate_email'] = (strcasecmp($row['email'], trim($email)) === 0);
        $result['duplicate_username'] = (strcasecmp($row['username'], trim($username)) === 0);
    }
    return $result;
}

/**
 * Insert a new user into database
 */
function insertUserRecord($mysqli, $userData) {
    if (!$mysqli) return ['success' => false, 'user_id' => null, 'error' => 'Database offline'];

    $sql = "INSERT INTO users (username, full_name, email, password_hash, phone, department, semester, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return ['success' => false, 'user_id' => null, 'error' => $mysqli->error];

    $u    = trim($userData['username']);
    $fn   = trim($userData['full_name']);
    $em   = strtolower(trim($userData['email']));
    $hash = $userData['password_hash'];
    $ph   = trim($userData['phone']);
    $dept = trim($userData['department']);
    $sem  = (int)($userData['semester'] ?? 1);
    $role = trim($userData['role'] ?? 'STUDENT');

    $stmt->bind_param("ssssssis", $u, $fn, $em, $hash, $ph, $dept, $sem, $role);
    $ok = $stmt->execute();
    $newId = $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();

    return ['success' => $ok, 'user_id' => $newId, 'error' => $err];
}

/**
 * Update last login timestamp
 */
function updateUserLastLogin($userId) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;
    $stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// =========================================================================
// Practical 11: Student Management Helper Functions
// =========================================================================

function ensureStudentsTableReady(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS `students` (
        `student_id` INT AUTO_INCREMENT PRIMARY KEY,
        `roll_no` VARCHAR(20) NOT NULL UNIQUE,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `department` VARCHAR(60) NOT NULL,
        `course` VARCHAR(80) NOT NULL DEFAULT 'B.Tech Computer Science',
        `semester` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `phone` VARCHAR(15) NOT NULL,
        `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function fetchStudentsList(mysqli $mysqli, array $filters = [], $page = 1, $perPage = 10, $sortCol = 'roll_no', $sortDir = 'ASC') {
    ensureStudentsTableReady($mysqli);

    $where = [];
    $types = '';
    $params = [];

    if (!empty($filters['search'])) {
        $search = '%' . trim($filters['search']) . '%';
        $where[] = "(full_name LIKE ? OR email LIKE ? OR roll_no LIKE ? OR course LIKE ? OR department LIKE ?)";
        $types .= 'sssss';
        $params = array_merge($params, [$search, $search, $search, $search, $search]);
    }
    if (!empty($filters['department'])) {
        $where[] = "department = ?";
        $types .= 's';
        $params[] = trim($filters['department']);
    }
    if (!empty($filters['course'])) {
        $where[] = "course = ?";
        $types .= 's';
        $params[] = trim($filters['course']);
    }
    if (!empty($filters['semester']) && (int)$filters['semester'] > 0) {
        $where[] = "semester = ?";
        $types .= 'i';
        $params[] = (int)$filters['semester'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['ACTIVE', 'INACTIVE'])) {
        $where[] = "status = ?";
        $types .= 's';
        $params[] = $filters['status'];
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total records
    $countSql = "SELECT COUNT(*) FROM students $whereSql";
    $countStmt = $mysqli->prepare($countSql);
    if (!empty($types)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Pagination calculations
    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = max(1, min($totalPages, (int)$page));
    $offset = ($page - 1) * $perPage;

    // Allowed sorting columns
    $allowedCols = ['student_id', 'roll_no', 'full_name', 'email', 'department', 'course', 'semester', 'status', 'created_at'];
    $sortCol = in_array($sortCol, $allowedCols) ? $sortCol : 'roll_no';
    $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

    // Fetch data
    $dataSql = "SELECT * FROM students $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?";
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);

    $dataStmt = $mysqli->prepare($dataSql);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $res = $dataStmt->get_result();
    $records = [];
    while ($row = $res->fetch_assoc()) {
        $records[] = $row;
    }
    $dataStmt->close();

    return [
        'data'        => $records,
        'total'       => $totalCount,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'sort_col'    => $sortCol,
        'sort_dir'    => $sortDir
    ];
}

function getStudentRecord(mysqli $mysqli, $studentId) {
    ensureStudentsTableReady($mysqli);
    $stmt = $mysqli->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function saveStudentRecord(mysqli $mysqli, array $data, $studentId = null) {
    ensureStudentsTableReady($mysqli);
    $errors = [];

    $rollNo     = strtoupper(trim($data['roll_no'] ?? ''));
    $fullName   = trim($data['full_name'] ?? '');
    $email      = strtolower(trim($data['email'] ?? ''));
    $phone      = trim($data['phone'] ?? '');
    $dept       = trim($data['department'] ?? '');
    $course     = trim($data['course'] ?? $dept);
    $semester   = (int)($data['semester'] ?? 1);
    $status     = in_array($data['status'] ?? '', ['ACTIVE', 'INACTIVE']) ? $data['status'] : 'ACTIVE';

    if (empty($rollNo))   $errors['roll_no'] = 'Roll Number is required.';
    if (empty($fullName)) $errors['full_name'] = 'Full Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid Email is required.';
    if (empty($phone))    $errors['phone'] = 'Phone number is required.';
    if (empty($dept))     $errors['department'] = 'Department is required.';

    // Check duplicate roll number
    if (empty($errors['roll_no'])) {
        $dupSql = "SELECT student_id FROM students WHERE roll_no = ?" . ($studentId ? " AND student_id != ?" : "");
        $dupStmt = $mysqli->prepare($dupSql);
        if ($studentId) {
            $dupStmt->bind_param("si", $rollNo, $studentId);
        } else {
            $dupStmt->bind_param("s", $rollNo);
        }
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $errors['roll_no'] = 'Roll number is already taken.';
        }
        $dupStmt->close();
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors, 'student_id' => $studentId];
    }

    if ($studentId === null) {
        $sql = "INSERT INTO students (roll_no, full_name, email, department, course, semester, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssiss", $rollNo, $fullName, $email, $dept, $course, $semester, $phone, $status);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return ['success' => $ok, 'message' => 'Student record added successfully!', 'errors' => [], 'student_id' => $newId];
    } else {
        $sql = "UPDATE students SET roll_no=?, full_name=?, email=?, department=?, course=?, semester=?, phone=?, status=? WHERE student_id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssissi", $rollNo, $fullName, $email, $dept, $course, $semester, $phone, $status, $studentId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => 'Student record updated successfully!', 'errors' => [], 'student_id' => $studentId];
    }
}

function deleteStudentRecord(mysqli $mysqli, $studentId) {
    ensureStudentsTableReady($mysqli);
    $stmt = $mysqli->prepare("DELETE FROM students WHERE student_id = ?");
    if (!$stmt) return ['success' => false, 'message' => 'Prepare failed'];
    $stmt->bind_param("i", $studentId);
    $ok = $stmt->execute();
    $stmt->close();
    return ['success' => $ok, 'message' => $ok ? 'Student record deleted successfully.' : 'Delete failed.'];
}

function getStudentSummaryMetrics(mysqli $mysqli) {
    ensureStudentsTableReady($mysqli);
    $res = $mysqli->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'INACTIVE' THEN 1 ELSE 0 END) AS inactive
        FROM students");
    $counts = $res ? $res->fetch_assoc() : [];

    $depts = [];
    $dRes = $mysqli->query("SELECT DISTINCT department FROM students ORDER BY department");
    if ($dRes) {
        while ($d = $dRes->fetch_row()) if ($d[0]) $depts[] = $d[0];
    }

    $courses = [];
    $cRes = $mysqli->query("SELECT DISTINCT course FROM students ORDER BY course");
    if ($cRes) {
        while ($c = $cRes->fetch_row()) if ($c[0]) $courses[] = $c[0];
    }

    return [
        'total'       => (int)($counts['total'] ?? 0),
        'active'      => (int)($counts['active'] ?? 0),
        'inactive'    => (int)($counts['inactive'] ?? 0),
        'departments' => $depts,
        'courses'     => $courses
    ];
}

// =========================================================================
// Practical 12: Event Management Helper Functions
// =========================================================================

function ensureEventsTableReady(mysqli $mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS `events` (
        `event_id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_code` VARCHAR(20) NOT NULL UNIQUE,
        `title` VARCHAR(150) NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `event_date` DATETIME NOT NULL,
        `venue` VARCHAR(100) NOT NULL,
        `max_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
        `description` TEXT,
        `poster_image` VARCHAR(255) NULL,
        `status` ENUM('UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'UPCOMING',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function validatePosterUpload(array $file, bool $isRequired = false) {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => !$isRequired, 'error' => $isRequired ? 'Poster image is required.' : null, 'file_provided' => false];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error code: ' . $file['error'], 'file_provided' => true];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['valid' => false, 'error' => 'Poster file size exceeds the maximum allowed 2MB limit.', 'file_provided' => true];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        return ['valid' => false, 'error' => "Invalid file extension (.{$ext}). Allowed: JPG, PNG, WEBP.", 'file_provided' => true];
    }
    if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $imgCheck = @getimagesize($file['tmp_name']);
        if ($imgCheck === false) {
            return ['valid' => false, 'error' => 'Uploaded file is not a valid image format.', 'file_provided' => true];
        }
    }
    return ['valid' => true, 'error' => null, 'file_provided' => true, 'extension' => ($ext === 'jpeg' ? 'jpg' : $ext)];
}

function fetchEventsList(mysqli $mysqli, array $filters = [], $page = 1, $perPage = 9, $sortCol = 'event_date', $sortDir = 'ASC') {
    ensureEventsTableReady($mysqli);

    $where = [];
    $types = '';
    $params = [];

    if (!empty($filters['search'])) {
        $search = '%' . trim($filters['search']) . '%';
        $where[] = "(title LIKE ? OR event_code LIKE ? OR venue LIKE ? OR category LIKE ?)";
        $types .= 'ssss';
        $params = array_merge($params, [$search, $search, $search, $search]);
    }
    if (!empty($filters['category'])) {
        $where[] = "category = ?";
        $types .= 's';
        $params[] = trim($filters['category']);
    }
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $types .= 's';
        $params[] = strtoupper(trim($filters['status']));
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countSql = "SELECT COUNT(*) FROM events $whereSql";
    $cStmt = $mysqli->prepare($countSql);
    if (!empty($types)) {
        $cStmt->bind_param($types, ...$params);
    }
    $cStmt->execute();
    $totalCount = (int)$cStmt->get_result()->fetch_row()[0];
    $cStmt->close();

    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = max(1, min($totalPages, (int)$page));
    $offset = ($page - 1) * $perPage;

    $allowedCols = ['event_id', 'event_code', 'title', 'category', 'event_date', 'venue', 'max_capacity', 'status', 'created_at'];
    $sortCol = in_array($sortCol, $allowedCols) ? $sortCol : 'event_date';
    $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

    $dataSql = "SELECT * FROM events $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?";
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);

    $dStmt = $mysqli->prepare($dataSql);
    $dStmt->bind_param($dataTypes, ...$dataParams);
    $dStmt->execute();
    $res = $dStmt->get_result();
    $records = [];
    while ($r = $res->fetch_assoc()) {
        $records[] = $r;
    }
    $dStmt->close();

    return [
        'data'        => $records,
        'total'       => $totalCount,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'sort_col'    => $sortCol,
        'sort_dir'    => $sortDir
    ];
}

function getEventRecord(mysqli $mysqli, $eventId) {
    ensureEventsTableReady($mysqli);
    $stmt = $mysqli->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function saveEventRecord(mysqli $mysqli, array $data, ?array $posterFile = null, ?int $eventId = null) {
    ensureEventsTableReady($mysqli);
    $errors = [];

    $code     = strtoupper(trim($data['event_code'] ?? ''));
    $title    = trim($data['title'] ?? '');
    $cat      = trim($data['category'] ?? 'Technical');
    $date     = trim($data['event_date'] ?? '');
    $venue    = trim($data['venue'] ?? '');
    $cap      = max(1, (int)($data['max_capacity'] ?? 50));
    $desc     = trim($data['description'] ?? '');
    $status   = in_array($data['status'] ?? '', ['UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED']) ? $data['status'] : 'UPCOMING';

    if (empty($code))  $errors['event_code'] = 'Event Code is required.';
    if (empty($title)) $errors['title'] = 'Event Title is required.';
    if (empty($date))  $errors['event_date'] = 'Event Date & Time is required.';
    if (empty($venue)) $errors['venue'] = 'Venue is required.';

    // Check duplicate code
    if (empty($errors['event_code'])) {
        $dupSql = "SELECT event_id FROM events WHERE event_code = ?" . ($eventId ? " AND event_id != ?" : "");
        $dupStmt = $mysqli->prepare($dupSql);
        if ($eventId) {
            $dupStmt->bind_param("si", $code, $eventId);
        } else {
            $dupStmt->bind_param("s", $code);
        }
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $errors['event_code'] = 'Event Code is already taken.';
        }
        $dupStmt->close();
    }

    // Handle poster upload
    $newPoster = null;
    if ($posterFile && !empty($posterFile['name'])) {
        $val = validatePosterUpload($posterFile, false);
        if (!$val['valid']) {
            $errors['poster'] = $val['error'];
        } else {
            $uploadDir = __DIR__ . '/uploads/posters';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $newPoster = 'poster_' . time() . '_' . rand(100, 999) . '.' . $val['extension'];
            $dest = $uploadDir . '/' . $newPoster;
            if (is_uploaded_file($posterFile['tmp_name'])) {
                move_uploaded_file($posterFile['tmp_name'], $dest);
            } else {
                copy($posterFile['tmp_name'], $dest);
            }
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors, 'event_id' => $eventId];
    }

    $existing = $eventId ? getEventRecord($mysqli, $eventId) : null;
    $posterToSave = $newPoster ?? ($existing['poster_image'] ?? null);

    if ($eventId === null) {
        $sql = "INSERT INTO events (event_code, title, category, event_date, venue, max_capacity, description, poster_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssisss", $code, $title, $cat, $date, $venue, $cap, $desc, $posterToSave, $status);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return ['success' => $ok, 'message' => "Event '{$title}' created successfully!", 'errors' => [], 'event_id' => $newId];
    } else {
        $sql = "UPDATE events SET event_code=?, title=?, category=?, event_date=?, venue=?, max_capacity=?, description=?, poster_image=?, status=? WHERE event_id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssssisssi", $code, $title, $cat, $date, $venue, $cap, $desc, $posterToSave, $status, $eventId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => "Event '{$title}' updated successfully!", 'errors' => [], 'event_id' => $eventId];
    }
}

function deleteEventRecord(mysqli $mysqli, $eventId) {
    ensureEventsTableReady($mysqli);
    $stmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
    if (!$stmt) return ['success' => false, 'message' => 'Prepare failed'];
    $stmt->bind_param("i", $eventId);
    $ok = $stmt->execute();
    $stmt->close();
    return ['success' => $ok, 'message' => $ok ? 'Event deleted successfully.' : 'Delete failed.'];
}

function getEventSummaryMetrics(mysqli $mysqli) {
    ensureEventsTableReady($mysqli);
    $res = $mysqli->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'UPCOMING' THEN 1 ELSE 0 END) AS upcoming,
        SUM(CASE WHEN status = 'ONGOING' THEN 1 ELSE 0 END) AS ongoing,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
        SUM(max_capacity) AS total_capacity
        FROM events");
    $counts = $res ? $res->fetch_assoc() : [];

    $cats = [];
    $cRes = $mysqli->query("SELECT DISTINCT category FROM events ORDER BY category");
    if ($cRes) {
        while ($c = $cRes->fetch_row()) if ($c[0]) $cats[] = $c[0];
    }

    return [
        'total'          => (int)($counts['total'] ?? 0),
        'upcoming'       => (int)($counts['upcoming'] ?? 0),
        'ongoing'        => (int)($counts['ongoing'] ?? 0),
        'completed'      => (int)($counts['completed'] ?? 0),
        'cancelled'      => (int)($counts['cancelled'] ?? 0),
        'total_capacity' => (int)($counts['total_capacity'] ?? 0),
        'categories'     => $cats
    ];
}
