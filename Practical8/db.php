<?php

if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT',    3306);
if (!defined('DB_NAME'))    define('DB_NAME',    'studenthub_db');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

$pdo = null;
$db_connection_error = null;

function getDBConnection($selectDatabase = true) {
    global $pdo, $db_connection_error;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = DB_HOST;
    $port    = DB_PORT;
    $dbName  = DB_NAME;
    $charset = DB_CHARSET;
    $user    = DB_USER;
    $pass    = DB_PASS;

    if ($selectDatabase) {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    } else {
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $db_connection_error = null;
        return $pdo;
    } catch (PDOException $e) {
        $db_connection_error = $e->getMessage();
        error_log("[Practical 8 PDO Connection Error] " . $e->getMessage());
        return null;
    }
}

function testDBConnection() {
    $startTime = microtime(true);
    $pdo = getDBConnection(true);
    $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

    if ($pdo === null) {
        global $db_connection_error;
        return [
            'success'        => false,
            'message'        => 'Connection failed. MySQL server may be stopped or database not created.',
            'error_detail'   => $db_connection_error,
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

$pdo = getDBConnection(true);
