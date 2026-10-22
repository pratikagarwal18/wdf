<?php
/**
 * Practical 9: Database Connection and Security Operations Module
 * Technology Stack: PHP 7.4+ / 8.x, MySQLi (Object-Oriented), MySQL / MariaDB, XAMPP
 * 
 * Features Implemented:
 * 1. Object-Oriented MySQLi connection layer with utf8mb4 charset.
 * 2. Parameterized MySQLi prepared statements (Intermediate Extension).
 * 3. Bcrypt password hashing and verification via password_hash() / password_verify().
 * 4. Duplicate email and username checking.
 * 5. Cryptographic audit log writer (Advanced Extension).
 * 6. Graceful offline diagnostics (renders help screen instead of unhandled fatal crash).
 */

date_default_timezone_set('Asia/Kolkata');

// Database configuration constants (configured for default XAMPP environment)
if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    3306);
if (!defined('DB_NAME'))    define('DB_NAME',    'studenthub_db');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Global connection state
$mysqli_instance = null;
$mysqli_connection_error = null;

/**
 * Returns an active Object-Oriented MySQLi connection.
 *
 * @param bool $selectDatabase Whether to select DB_NAME upon connection.
 * @return mysqli|null Returns mysqli instance on success, or null on error.
 */
function getMySQLiConnection($selectDatabase = true) {
    global $mysqli_instance, $mysqli_connection_error;

    if ($mysqli_instance instanceof mysqli && @$mysqli_instance->ping()) {
        return $mysqli_instance;
    }

    // Suppress default fatal uncaught warnings to handle gracefully
    mysqli_report(MYSQLI_REPORT_OFF);

    $dbToSelect = $selectDatabase ? DB_NAME : null;
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbToSelect, DB_PORT);

    if ($mysqli->connect_errno) {
        $mysqli_connection_error = $mysqli->connect_error;
        error_log("[Practical 9 MySQLi Error] Connection failed: " . $mysqli->connect_error);
        return null;
    }

    if (!$mysqli->set_charset(DB_CHARSET)) {
        error_log("[Practical 9 MySQLi Warning] Failed to set charset " . DB_CHARSET . ": " . $mysqli->error);
    }

    $mysqli_connection_error = null;
    $mysqli_instance = $mysqli;
    return $mysqli_instance;
}

/**
 * Runs a comprehensive MySQLi connection diagnostic test.
 *
 * @return array Diagnostic report details.
 */
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

/**
 * Checks if a user with the provided email or username already exists.
 * Implemented using a MySQLi parameterized prepared statement (Intermediate Extension).
 *
 * @param mysqli $mysqli An active MySQLi connection.
 * @param string $email The email address to check.
 * @param string $username The username to check.
 * @param int $excludeUserId Optional user ID to exclude (e.g. for updates).
 * @return array Array indicating collision status and specific fields matched.
 */
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
        error_log("[checkDuplicateUser] Prepare failed: " . $mysqli->error);
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

/**
 * Inserts a new user record into the database using a MySQLi prepared statement.
 * Passwords MUST be hashed prior to calling this function.
 *
 * @param mysqli $mysqli Active MySQLi connection.
 * @param array $userData Associative array of user attributes.
 * @return array ['success' => bool, 'user_id' => int|null, 'error' => string|null]
 */
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

/**
 * Logs an event into the audit_logs table (Practical 9 Advanced Extension).
 *
 * @param mysqli $mysqli Active MySQLi connection.
 * @param string $eventType Event label (e.g. 'REGISTRATION_SUCCESS', 'REGISTRATION_DUPLICATE_EMAIL').
 * @param string $identifier Associated email or username.
 * @param string $status 'SUCCESS' | 'FAILED' | 'WARNING' | 'BLOCKED'
 * @param string $message Descriptive log details.
 * @param int|null $userId Optional user ID if created.
 * @return bool
 */
function insertAuditLog($mysqli, $eventType, $identifier, $status, $message, $userId = null) {
    if (!$mysqli instanceof mysqli) {
        return false;
    }

    $sql = "INSERT INTO audit_logs (user_id, event_type, identifier, status, message, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown / CLI', 0, 255);
    $identClean = substr($identifier, 0, 120);
    $msgClean = substr($message, 0, 255);

    $stmt->bind_param(
        "issssss",
        $userId,
        $eventType,
        $identClean,
        $status,
        $msgClean,
        $ipAddress,
        $userAgent
    );

    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Updates the last_login timestamp for a user (Intermediate Extension).
 *
 * @param int $userId User ID
 * @return bool
 */
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

/**
 * Stores a cryptographically hashed remember-me token (Advanced Extension).
 *
 * @param int $userId
 * @param string $selector 32-char hex selector
 * @param string $tokenHash 64-char sha256 hash of validator
 * @param string $expiresAt Y-m-d H:i:s formatted datetime
 * @return bool
 */
function storeRememberToken($userId, $selector, $tokenHash, $expiresAt) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;

    // Remove any existing token with this selector
    $del = $mysqli->prepare("DELETE FROM remember_tokens WHERE selector = ?");
    if ($del) {
        $del->bind_param("s", $selector);
        $del->execute();
        $del->close();
    }

    $stmt = $mysqli->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;

    $stmt->bind_param("isss", $userId, $selector, $tokenHash, $expiresAt);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Looks up a remember-me token record by selector.
 *
 * @param string $selector
 * @return array|null Token record with user details, or null if not found/expired
 */
function findRememberToken($selector) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return null;

    $sql = "SELECT t.token_id, t.user_id, t.selector, t.token_hash, t.expires_at,
                   u.username, u.full_name, u.email, u.role, u.department, u.is_active
            FROM remember_tokens t
            JOIN users u ON t.user_id = u.user_id
            WHERE t.selector = ? AND t.expires_at > NOW() AND u.is_active = 1
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $data;
}

/**
 * Deletes a remember token by selector (e.g. on rotation or logout).
 *
 * @param string $selector
 * @return bool
 */
function deleteRememberToken($selector) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;

    $stmt = $mysqli->prepare("DELETE FROM remember_tokens WHERE selector = ?");
    if (!$stmt) return false;

    $stmt->bind_param("s", $selector);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Deletes all remember tokens for a specific user (e.g. global logout / password change).
 *
 * @param int $userId
 * @return bool
 */
function deleteUserRememberTokens($userId) {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return false;

    $stmt = $mysqli->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $userId);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Cleans up expired remember-me tokens from the database.
 *
 * @return int Number of deleted tokens
 */
function cleanExpiredRememberTokens() {
    $mysqli = getMySQLiConnection(true);
    if (!$mysqli) return 0;

    $res = $mysqli->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");
    return $res ? $mysqli->affected_rows : 0;
}


/**
 * Fallback PDO connection provider (maintains compatibility with Practical 8).
 */
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
        error_log("[PDO Error] " . $e->getMessage());
        return null;
    }
}

/**
 * Runs a PDO connection test for Practical 8 compatibility.
 */
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

/**
 * Safely fetches records using PDO parameterized query (Practical 8 compatibility).
 */
function safeFetchAll($sql, $params = []) {
    $conn = getDBConnection();
    if (!$conn) return [];
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

