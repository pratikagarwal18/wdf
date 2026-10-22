<?php

date_default_timezone_set('Asia/Kolkata');

if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    3306);
if (!defined('DB_NAME'))    define('DB_NAME',    'studenthub_db');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

$mysqli_instance = null;
$mysqli_connection_error = null;

function getMySQLiConnection($selectDatabase = true) {
    global $mysqli_instance, $mysqli_connection_error;

    if ($mysqli_instance instanceof mysqli && @$mysqli_instance->ping()) {
        return $mysqli_instance;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $dbToSelect = $selectDatabase ? DB_NAME : null;
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbToSelect, DB_PORT);

    if ($mysqli->connect_errno) {
        $mysqli_connection_error = $mysqli->connect_error;
        error_log("Connection failed: " . $mysqli->connect_error);
        return null;
    }

    if (!$mysqli->set_charset(DB_CHARSET)) {
        error_log("Failed to set charset " . DB_CHARSET . ": " . $mysqli->error);
    }

    $mysqli_connection_error = null;
    $mysqli_instance = $mysqli;
    return $mysqli_instance;
}

function testMySQLiConnection() {
    $startTime = microtime(true);
    $mysqli = getMySQLiConnection(true);
    $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

    if ($mysqli === null) {
        global $mysqli_connection_error;
        return [
            'success'        => false,
            'message'        => 'MySQL connection failed. Ensure MySQL is running in XAMPP Control Panel.',
            'error_detail'   => $mysqli_connection_error,
            'host'           => DB_HOST . ':' . DB_PORT,
            'database'       => DB_NAME,
            'user'           => DB_USER,
            'driver'         => 'MySQLi (Object-Oriented)',
            'latency_ms'     => $latencyMs,
            'tables'         => [],
            'table_counts'   => []
        ];
    }

    $tables = [];
    $tableCounts = [];
    $res = $mysqli->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            $tableName = $row[0];
            $tables[] = $tableName;
            $countRes = $mysqli->query("SELECT COUNT(*) FROM `{$tableName}`");
            if ($countRes) {
                $countRow = $countRes->fetch_row();
                $tableCounts[$tableName] = (int)$countRow[0];
                $countRes->free();
            } else {
                $tableCounts[$tableName] = 'N/A';
            }
        }
        $res->free();
    }

    return [
        'success'        => true,
        'message'        => 'Successfully connected via Object-Oriented MySQLi!',
        'host'           => DB_HOST . ':' . DB_PORT,
        'database'       => DB_NAME,
        'user'           => DB_USER,
        'charset'        => $mysqli->character_set_name(),
        'driver'         => 'MySQLi (Object-Oriented)',
        'server_version' => $mysqli->server_info,
        'client_version' => $mysqli->client_info,
        'protocol'       => $mysqli->protocol_version,
        'latency_ms'     => $latencyMs,
        'tables'         => $tables,
        'table_counts'   => $tableCounts
    ];
}

function checkDuplicateUser($mysqli, $email, $username, $excludeUserId = 0) {
    $result = [
        'has_duplicate'       => false,
        'duplicate_email'    => false,
        'duplicate_username' => false,
        'matched_user'       => null
    ];

    $emailClean = strtolower(trim($email));
    $usernameClean = trim($username);

    $sql = "SELECT user_id, username, email FROM users 
            WHERE (LOWER(email) = ? OR LOWER(username) = LOWER(?)) 
              AND user_id != ? 
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        return $result;
    }

    $stmt->bind_param("ssi", $emailClean, $usernameClean, $excludeUserId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $result['has_duplicate'] = true;
        $result['matched_user'] = $row;
        if (strcasecmp($row['email'], $emailClean) === 0) {
            $result['duplicate_email'] = true;
        }
        if (strcasecmp($row['username'], $usernameClean) === 0) {
            $result['duplicate_username'] = true;
        }
    }

    $stmt->close();
    return $result;
}

function insertUserRecord($mysqli, $userData) {
    $sql = "INSERT INTO users 
            (username, full_name, email, password_hash, phone, department, semester, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [
            'success' => false,
            'user_id' => null,
            'error'   => 'Prepare statement failed: ' . $mysqli->error
        ];
    }

    $username     = trim($userData['username']);
    $fullName     = trim($userData['full_name']);
    $email        = strtolower(trim($userData['email']));
    $passwordHash = $userData['password_hash'];
    $phone        = trim($userData['phone']);
    $department   = trim($userData['department']);
    $semester     = (int)($userData['semester'] ?? 1);
    $role         = trim($userData['role'] ?? 'STUDENT');

    $stmt->bind_param(
        "ssssssis",
        $username,
        $fullName,
        $email,
        $passwordHash,
        $phone,
        $department,
        $semester,
        $role
    );

    $executed = $stmt->execute();
    if ($executed) {
        $newId = $stmt->insert_id;
        $stmt->close();
        return [
            'success' => true,
            'user_id' => $newId,
            'error'   => null
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'user_id' => null,
            'error'   => $error
        ];
    }
}

function updateUserLastLogin($userId) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;

    $stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $userId);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

function getDBConnection($selectDatabase = true) {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = DB_HOST;
    $port    = DB_PORT;
    $dbName  = DB_NAME;
    $charset = DB_CHARSET;
    $user    = DB_USER;
    $pass    = DB_PASS;

    $dsn = "mysql:host={$host};port={$port}" . ($selectDatabase ? ";dbname={$dbName}" : "") . ";charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        return null;
    }
}

function testDBConnection() {
    $startTime = microtime(true);
    $pdo = getDBConnection(true);
    $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

    if ($pdo === null) {
        return [
            'success'        => false,
            'message'        => 'Connection failed. MySQL server may be stopped or database not created.',
            'error_detail'   => 'PDO connection failed',
            'host'           => DB_HOST . ':' . DB_PORT,
            'database'       => DB_NAME,
            'user'           => DB_USER,
            'driver'         => 'PDO_MYSQL',
            'latency_ms'     => $latencyMs,
            'tables'         => [],
            'table_counts'   => []
        ];
    }

    try {
        $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $driverName    = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $clientVersion = $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
        $connectionStatus = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);

        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $tableCounts = [];
        foreach ($tables as $table) {
            try {
                $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $tableCounts[$table] = (int)$countStmt->fetchColumn();
            } catch (Exception $ex) {
                $tableCounts[$table] = 'View / N/A';
            }
        }

        return [
            'success'           => true,
            'message'           => 'Successfully connected via PDO MySQL!',
            'host'              => DB_HOST . ':' . DB_PORT,
            'database'          => DB_NAME,
            'user'              => DB_USER,
            'charset'           => DB_CHARSET,
            'driver'            => strtoupper($driverName),
            'server_version'    => $serverVersion,
            'client_version'    => $clientVersion,
            'connection_status' => $connectionStatus,
            'latency_ms'        => $latencyMs,
            'tables'            => $tables,
            'table_counts'      => $tableCounts,
            'emulate_prepares'  => false,
            'errmode'           => 'ERRMODE_EXCEPTION'
        ];
    } catch (PDOException $e) {
        return [
            'success'      => false,
            'message'      => 'Connected to MySQL, but failed to query database schema.',
            'error_detail' => $e->getMessage(),
            'latency_ms'   => $latencyMs
        ];
    }
}

function safeFetchAll($sql, $params = []) {
    $conn = getDBConnection();
    if (!$conn) return [];
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ensureStudentsTableReady(mysqli $mysqli) {
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'students'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        $mysqli->query("CREATE TABLE IF NOT EXISTS `students` (
            `student_id` INT AUTO_INCREMENT PRIMARY KEY,
            `roll_no` VARCHAR(20) NOT NULL UNIQUE,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `department` VARCHAR(60) NOT NULL,
            `course` VARCHAR(80) NOT NULL DEFAULT 'B.Tech Computer Science',
            `semester` TINYINT UNSIGNED NOT NULL CHECK (`semester` BETWEEN 1 AND 8),
            `phone` VARCHAR(15) NOT NULL,
            `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_students_department` (`department`),
            INDEX `idx_students_course` (`course`),
            INDEX `idx_students_roll` (`roll_no`),
            INDEX `idx_students_status` (`status`),
            INDEX `idx_students_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if ($tableCheck) $tableCheck->free();

    $colRes = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'course'");
    if ($colRes && $colRes->num_rows === 0) {
        $mysqli->query("ALTER TABLE `students` ADD COLUMN `course` VARCHAR(80) NOT NULL DEFAULT 'B.Tech Computer Science' AFTER `department`");
        $mysqli->query("ALTER TABLE `students` ADD INDEX `idx_students_course` (`course`)");
    }
    if ($colRes) $colRes->free();

    $statusColRes = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'status'");
    if ($statusColRes && $statusColRes->num_rows === 0) {
        $mysqli->query("ALTER TABLE `students` ADD COLUMN `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE' AFTER `phone`");
        $mysqli->query("ALTER TABLE `students` ADD INDEX `idx_students_status` (`status`)");
    } else {
        $mysqli->query("UPDATE `students` SET `status` = 'ACTIVE' WHERE `status` = 'DELETED'");
        $mysqli->query("ALTER TABLE `students` MODIFY COLUMN `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE'");
    }
    if ($statusColRes) $statusColRes->free();

    $updColRes = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'updated_at'");
    if ($updColRes && $updColRes->num_rows === 0) {
        $mysqli->query("ALTER TABLE `students` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    if ($updColRes) $updColRes->free();

    $countRes = $mysqli->query("SELECT COUNT(*) FROM `students`");
    $count = 0;
    if ($countRes) {
        $count = (int)$countRes->fetch_row()[0];
        $countRes->free();
    }

    if ($count < 20) {
        $seeds = [
            ['21BCE1001', 'Aarav Sharma',       'aarav.sharma@studenthub.edu',   'Computer Engineering',   'B.Tech Computer Science',            6, '9876543210', 'ACTIVE'],
            ['21BCE1002', 'Diya Patel',         'diya.patel@studenthub.edu',     'Information Technology', 'B.Tech Information Technology',      6, '9876543211', 'ACTIVE'],
            ['22BIT1023', 'Rohan Mehta',        'rohan.mehta@studenthub.edu',    'Information Technology', 'B.Tech Information Technology',      4, '9876543212', 'ACTIVE'],
            ['22BEC1045', 'Ananya Gupta',       'ananya.gupta@studenthub.edu',   'Electronics & Comm',     'B.Tech Electronics & Communication', 4, '9876543213', 'ACTIVE'],
            ['23BME1012', 'Kabir Verma',        'kabir.verma@studenthub.edu',    'Mechanical Engineering', 'B.Tech Mechanical Engineering',      2, '9876543214', 'ACTIVE'],
            ['21BCE1088', 'Isha Deshmukh',      'isha.deshmukh@studenthub.edu',  'Computer Engineering',   'B.Tech Computer Science',            6, '9876543215', 'ACTIVE'],
            ['22BIT1090', 'Pratik Agarwal',     'pratik.agarwal@studenthub.edu', 'Information Technology', 'B.Tech Data Science & AI',           4, '9876543216', 'ACTIVE'],
            ['23BCE1055', 'Sneha Kulkarni',     'sneha.k@studenthub.edu',        'Computer Engineering',   'B.Tech Computer Science',            2, '9876543217', 'ACTIVE'],
            ['21BCE1092', 'Vikramaditya Roy',   'vikram.roy@studenthub.edu',     'Computer Engineering',   'B.Tech Artificial Intelligence',     6, '9876543218', 'ACTIVE'],
            ['22BIT1034', 'Tanvi Joshi',        'tanvi.joshi@studenthub.edu',    'Information Technology', 'B.Tech Information Technology',      4, '9876543219', 'ACTIVE'],
            ['23BEC1019', 'Aditya Nambiar',     'aditya.n@studenthub.edu',       'Electronics & Comm',     'B.Tech Electronics & VLSI',          2, '9876543220', 'ACTIVE'],
            ['20BCE1005', 'Meera Singhania',    'meera.s@studenthub.edu',        'Computer Engineering',   'B.Tech Cyber Security',              8, '9876543221', 'ACTIVE'],
            ['22BME1040', 'Siddharth Rao',      'siddharth.rao@studenthub.edu',  'Mechanical Engineering', 'B.Tech Robotics & Automation',       4, '9876543222', 'ACTIVE'],
            ['23BIT1015', 'Pooja Hegde',        'pooja.hegde@studenthub.edu',    'Information Technology', 'B.Tech Cloud Computing',             2, '9876543223', 'ACTIVE'],
            ['21BEC1077', 'Karan Malhotra',     'karan.m@studenthub.edu',        'Electronics & Comm',     'B.Tech Electronics & Communication', 6, '9876543224', 'ACTIVE'],
            ['20BME1002', 'Ritika Sen',         'ritika.sen@studenthub.edu',     'Mechanical Engineering', 'B.Tech Mechanical Engineering',      8, '9876543225', 'ACTIVE'],
            ['22BCE1120', 'Harshvardhan Nair',  'harsh.nair@studenthub.edu',     'Computer Engineering',   'B.Tech Computer Science',            4, '9876543226', 'ACTIVE'],
            ['23BCE1080', 'Rhea Chawla',        'rhea.chawla@studenthub.edu',    'Computer Engineering',   'B.Tech Software Engineering',        2, '9876543227', 'ACTIVE'],
            ['21BIT1052', 'Gaurav Bhatt',       'gaurav.bhatt@studenthub.edu',   'Information Technology', 'B.Tech Data Science & AI',           6, '9876543228', 'ACTIVE'],
            ['22BCE1104', 'Simran Kaur',        'simran.kaur@studenthub.edu',    'Computer Engineering',   'B.Tech Computer Science',            4, '9876543229', 'ACTIVE'],
            ['23BIT1044', 'Devendra Pandey',    'devendra.p@studenthub.edu',     'Information Technology', 'B.Tech Cloud Computing',             2, '9876543230', 'ACTIVE'],
            ['21BEC1032', 'Alia Fernandez',     'alia.f@studenthub.edu',         'Electronics & Comm',     'B.Tech Electronics & Communication', 6, '9876543231', 'ACTIVE'],
            ['22BME1065', 'Nikhil Saxena',      'nikhil.saxena@studenthub.edu',  'Mechanical Engineering', 'B.Tech Automotive Systems',          4, '9876543232', 'INACTIVE'],
            ['20BCE1018', 'Shruti Banerjee',    'shruti.b@studenthub.edu',       'Computer Engineering',   'B.Tech Cyber Security',              8, '9876543233', 'ACTIVE'],
            ['21BIT1061', 'Kunal Goswami',      'kunal.g@studenthub.edu',        'Information Technology', 'B.Tech Information Technology',      6, '9876543234', 'ACTIVE']
        ];

        $insStmt = $mysqli->prepare("INSERT INTO `students` (`roll_no`, `full_name`, `email`, `department`, `course`, `semester`, `phone`, `status`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `course` = VALUES(`course`)");

        if ($insStmt) {
            foreach ($seeds as $s) {
                $insStmt->bind_param("sssssiss", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]);
                $insStmt->execute();
            }
            $insStmt->close();
        }
    }
}

function fetchStudentsList(mysqli $mysqli, array $filters = [], $page = 1, $perPage = 10, $sortCol = 'roll_no', $sortDir = 'ASC') {
    ensureStudentsTableReady($mysqli);

    $whereClauses = [];
    $types = '';
    $params = [];

    if (!empty($filters['search'])) {
        $searchWildcard = '%' . trim($filters['search']) . '%';
        $whereClauses[] = "(`full_name` LIKE ? OR `email` LIKE ? OR `roll_no` LIKE ? OR `course` LIKE ? OR `department` LIKE ?)";
        $types .= 'sssss';
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }

    if (!empty($filters['department'])) {
        $whereClauses[] = "`department` = ?";
        $types .= 's';
        $params[] = trim($filters['department']);
    }

    if (!empty($filters['course'])) {
        $whereClauses[] = "`course` = ?";
        $types .= 's';
        $params[] = trim($filters['course']);
    }

    if (!empty($filters['semester']) && (int)$filters['semester'] > 0) {
        $whereClauses[] = "`semester` = ?";
        $types .= 'i';
        $params[] = (int)$filters['semester'];
    }

    $statusFilter = $filters['status'] ?? '';
    if ($statusFilter === 'ACTIVE') {
        $whereClauses[] = "`status` = 'ACTIVE'";
    } elseif ($statusFilter === 'INACTIVE') {
        $whereClauses[] = "`status` = 'INACTIVE'";
    }

    $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $allowedCols = [
        'student_id' => 'student_id',
        'roll_no'    => 'roll_no',
        'full_name'  => 'full_name',
        'email'      => 'email',
        'department' => 'department',
        'course'     => 'course',
        'semester'   => 'semester',
        'status'     => 'status',
        'created_at' => 'created_at'
    ];
    $sortCol = $allowedCols[$sortCol] ?? 'roll_no';
    $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

    $countSql = "SELECT COUNT(*) FROM `students` {$whereSql}";
    $countStmt = $mysqli->prepare($countSql);
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $perPage = max(1, min(100, (int)$perPage));
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = max(1, min($totalPages, (int)$page));
    $offset = ($page - 1) * $perPage;

    $dataSql = "SELECT `student_id`, `roll_no`, `full_name`, `email`, `department`, `course`, `semester`, `phone`, `status`, `created_at`, `updated_at` 
                FROM `students` 
                {$whereSql} 
                ORDER BY `{$sortCol}` {$sortDir} 
                LIMIT ? OFFSET ?";

    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);

    $dataStmt = $mysqli->prepare($dataSql);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $result = $dataStmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
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
    $stmt = $mysqli->prepare("SELECT * FROM `students` WHERE `student_id` = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

function saveStudentRecord(mysqli $mysqli, array $data, $studentId = null) {
    ensureStudentsTableReady($mysqli);
    $errors = [];

    $rollNo     = strtoupper(trim($data['roll_no'] ?? ''));
    $fullName   = trim($data['full_name'] ?? '');
    $email      = strtolower(trim($data['email'] ?? ''));
    $phone      = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    $department = trim($data['department'] ?? '');
    $course     = trim($data['course'] ?? '');
    $semester   = (int)($data['semester'] ?? 1);
    $status     = strtoupper(trim($data['status'] ?? 'ACTIVE'));

    if (empty($rollNo)) {
        $errors['roll_no'] = 'Roll Number is required.';
    } elseif (!preg_match('/^[A-Z0-9]{3,20}$/', $rollNo)) {
        $errors['roll_no'] = 'Roll Number must be 3-20 alphanumeric characters (e.g. 21BCE1001).';
    } else {
        $dupSql = "SELECT `student_id` FROM `students` WHERE `roll_no` = ?" . ($studentId ? " AND `student_id` != ?" : "");
        $dupStmt = $mysqli->prepare($dupSql);
        if ($studentId) {
            $dupStmt->bind_param("si", $rollNo, $studentId);
        } else {
            $dupStmt->bind_param("s", $rollNo);
        }
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $errors['roll_no'] = 'Roll Number "' . htmlspecialchars($rollNo) . '" is already registered to another student.';
        }
        $dupStmt->close();
    }

    if (empty($fullName)) {
        $errors['full_name'] = 'Full Name is required.';
    } elseif (strlen($fullName) < 2 || strlen($fullName) > 100) {
        $errors['full_name'] = 'Full Name must be between 2 and 100 characters.';
    }

    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email address format.';
    } else {
        $dupEmailSql = "SELECT `student_id` FROM `students` WHERE `email` = ?" . ($studentId ? " AND `student_id` != ?" : "");
        $dupEmailStmt = $mysqli->prepare($dupEmailSql);
        if ($studentId) {
            $dupEmailStmt->bind_param("si", $email, $studentId);
        } else {
            $dupEmailStmt->bind_param("s", $email);
        }
        $dupEmailStmt->execute();
        if ($dupEmailStmt->get_result()->num_rows > 0) {
            $errors['email'] = 'Email "' . htmlspecialchars($email) . '" is already taken by another student.';
        }
        $dupEmailStmt->close();
    }

    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required.';
    } elseif (strlen($phone) < 10 || strlen($phone) > 15) {
        $errors['phone'] = 'Phone number must contain between 10 and 15 digits.';
    }

    if (empty($department)) {
        $errors['department'] = 'Department is required.';
    }
    if (empty($course)) {
        $course = $department;
    }

    if ($semester < 1 || $semester > 8) {
        $errors['semester'] = 'Semester must be between 1 and 8.';
    }

    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        $status = 'ACTIVE';
    }

    if (!empty($errors)) {
        return [
            'success'    => false,
            'message'    => 'Please resolve the highlighted validation errors.',
            'errors'     => $errors,
            'student_id' => $studentId
        ];
    }

    if ($studentId === null) {
        $stmt = $mysqli->prepare("INSERT INTO `students` (`roll_no`, `full_name`, `email`, `department`, `course`, `semester`, `phone`, `status`) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Query preparation failed: ' . $mysqli->error, 'errors' => []];
        }
        $stmt->bind_param("sssssiss", $rollNo, $fullName, $email, $department, $course, $semester, $phone, $status);
        if ($stmt->execute()) {
            $newId = $mysqli->insert_id;
            $stmt->close();
            return [
                'success'    => true,
                'message'    => "Student record for {$fullName} ({$rollNo}) added successfully!",
                'errors'     => [],
                'student_id' => $newId
            ];
        } else {
            $errMsg = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Insert failed: ' . $errMsg, 'errors' => []];
        }
    } else {
        $stmt = $mysqli->prepare("UPDATE `students` 
                                  SET `roll_no` = ?, `full_name` = ?, `email` = ?, `department` = ?, `course` = ?, `semester` = ?, `phone` = ?, `status` = ? 
                                  WHERE `student_id` = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Query preparation failed: ' . $mysqli->error, 'errors' => []];
        }
        $stmt->bind_param("sssssissi", $rollNo, $fullName, $email, $department, $course, $semester, $phone, $status, $studentId);
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success'    => true,
                'message'    => "Student record for {$fullName} ({$rollNo}) updated successfully!",
                'errors'     => [],
                'student_id' => $studentId
            ];
        } else {
            $errMsg = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Update failed: ' . $errMsg, 'errors' => []];
        }
    }
}

function deleteStudentRecord(mysqli $mysqli, $studentId) {
    ensureStudentsTableReady($mysqli);
    $student = getStudentRecord($mysqli, $studentId);
    if (!$student) {
        return ['success' => false, 'message' => 'Student record not found.'];
    }

    $stmt = $mysqli->prepare("DELETE FROM `students` WHERE `student_id` = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Query preparation failed: ' . $mysqli->error];
    }
    $stmt->bind_param("i", $studentId);
    $res = $stmt->execute();
    $stmt->close();

    if ($res) {
        return [
            'success' => true,
            'message' => "Student record for {$student['full_name']} ({$student['roll_no']}) deleted successfully."
        ];
    }
    return ['success' => false, 'message' => 'Failed to delete student record.'];
}

function getStudentSummaryMetrics(mysqli $mysqli) {
    ensureStudentsTableReady($mysqli);

    $metrics = [
        'total'       => 0,
        'active'      => 0,
        'inactive'    => 0,
        'departments' => [],
        'courses'     => []
    ];

    $countRes = $mysqli->query("SELECT 
        COUNT(*) AS total_all,
        SUM(CASE WHEN `status` = 'ACTIVE' THEN 1 ELSE 0 END) AS count_active,
        SUM(CASE WHEN `status` = 'INACTIVE' THEN 1 ELSE 0 END) AS count_inactive
        FROM `students`");
    if ($countRes) {
        $cRow = $countRes->fetch_assoc();
        $metrics['total']    = (int)($cRow['total_all'] ?? 0);
        $metrics['active']   = (int)($cRow['count_active'] ?? 0);
        $metrics['inactive'] = (int)($cRow['count_inactive'] ?? 0);
        $countRes->free();
    }

    $deptRes = $mysqli->query("SELECT DISTINCT `department` FROM `students` ORDER BY `department` ASC");
    if ($deptRes) {
        while ($d = $deptRes->fetch_row()) {
            if (!empty($d[0])) $metrics['departments'][] = $d[0];
        }
        $deptRes->free();
    }

    $courseRes = $mysqli->query("SELECT DISTINCT `course` FROM `students` ORDER BY `course` ASC");
    if ($courseRes) {
        while ($c = $courseRes->fetch_row()) {
            if (!empty($c[0])) $metrics['courses'][] = $c[0];
        }
        $courseRes->free();
    }

    return $metrics;
}
