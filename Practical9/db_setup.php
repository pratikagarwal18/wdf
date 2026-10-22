<?php
/**
 * Practical 9: Database Setup & Migration Script
 * Uses Object-Oriented MySQLi to initialize `studenthub_db` and seed schema.
 */

require_once __DIR__ . '/db.php';

$isCli = (php_sapi_name() === 'cli');
$results = [
    'success' => false,
    'steps'   => [],
    'error'   => null,
    'tables'  => []
];

try {
    // 1. Connect to MySQL server without choosing database first
    $mysqliSetup = @new mysqli(DB_HOST, DB_USER, DB_PASS, null, DB_PORT);

    if ($mysqliSetup->connect_errno) {
        throw new Exception("MySQL Server Connection Failed: " . $mysqliSetup->connect_error . " (Error #" . $mysqliSetup->connect_errno . "). Ensure MySQL is running in XAMPP.");
    }

    $results['steps'][] = "Connected to MySQL Server at " . DB_HOST . ":" . DB_PORT . " (Server: " . $mysqliSetup->server_info . ")";

    // 2. Read studenthub.sql
    $sqlFile = __DIR__ . '/studenthub.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Master SQL schema file not found at " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);
    $results['steps'][] = "Loaded studenthub.sql (" . strlen($sqlContent) . " bytes)";

    // Clean delimiters if any
    $cleanedSql = preg_replace('/DELIMITER\s+\$\$/i', '', $sqlContent);
    $cleanedSql = preg_replace('/DELIMITER\s+;/i', '', $cleanedSql);
    $cleanedSql = str_replace('$$', ';', $cleanedSql);

    // 3. Execute multi query
    if (!$mysqliSetup->multi_query($cleanedSql)) {
        throw new Exception("Failed to execute database schema: " . $mysqliSetup->error);
    }

    // Flush all result sets from multi_query
    do {
        if ($res = $mysqliSetup->store_result()) {
            $res->free();
        }
    } while ($mysqliSetup->more_results() && $mysqliSetup->next_result());

    $results['steps'][] = "Executed SQL DDL: Created `studenthub_db`, schema tables (`users`, `audit_logs`, etc.), views, and seed data.";

    // 4. Verify created tables
    $mysqliSetup->select_db(DB_NAME);
    $tableRes = $mysqliSetup->query("SHOW TABLES");
    $tables = [];
    if ($tableRes) {
        while ($t = $tableRes->fetch_array(MYSQLI_NUM)) {
            $tables[] = $t[0];
        }
        $tableRes->free();
    }

    $results['tables'] = $tables;
    $results['steps'][] = "Verified tables in `" . DB_NAME . "`: " . implode(', ', $tables);

    // 5. Ensure bcrypt hashes for seed users are authentic (Practical 10 RBAC)
    $studentHash = password_hash('Student@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $adminHash   = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $facultyHash = password_hash('Faculty@123', PASSWORD_BCRYPT, ['cost' => 12]);

    $updateStmt = $mysqliSetup->prepare("UPDATE users SET password_hash = ? WHERE username IN ('aarav.sharma', 'diya.patel')");
    if ($updateStmt) {
        $updateStmt->bind_param("s", $studentHash);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $adminStmt = $mysqliSetup->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin.portal'");
    if ($adminStmt) {
        $adminStmt->bind_param("s", $adminHash);
        $adminStmt->execute();
        $adminStmt->close();
    }

    // Insert faculty if not exists
    $facCheck = $mysqliSetup->query("SELECT user_id FROM users WHERE username = 'dr.sharma'");
    if ($facCheck && $facCheck->num_rows === 0) {
        $facIns = $mysqliSetup->prepare("INSERT INTO users (username, full_name, email, password_hash, phone, department, semester, role, is_active) VALUES ('dr.sharma', 'Dr. Rajiv Sharma', 'sharma@studenthub.edu', ?, '9876501234', 'Computer Science', 1, 'FACULTY', 1)");
        if ($facIns) {
            $facIns->bind_param("s", $facultyHash);
            $facIns->execute();
            $facIns->close();
        }
    }

    $results['steps'][] = "Generated and calibrated authentic Bcrypt password hashes for pre-seeded users: Admin ('Admin@123'), Student ('Student@123'), Faculty ('Faculty@123').";

    $results['success'] = true;
    $mysqliSetup->close();

} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
}

if ($isCli) {
    echo "========================================================\n";
    echo "Practical 9: StudentHub MySQLi Database Setup\n";
    echo "========================================================\n";
    foreach ($results['steps'] as $step) {
        echo "[OK] " . $step . "\n";
    }
    if ($results['success']) {
        echo "\n>>> Database Setup Succeeded! studenthub_db is ready for Practical 9.\n";
    } else {
        echo "\n[ERROR] " . $results['error'] . "\n";
    }
    exit($results['success'] ? 0 : 1);
}

// Return JSON if requested via AJAX
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Practical 9</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 32px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }
        .step-item {
            padding: 12px 16px;
            margin: 8px 0;
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
            border-radius: 6px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-err {
            padding: 14px 18px;
            margin: 12px 0;
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
        }
        .badge-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 3px;
            background: #e0e7ff;
            color: #3730a3;
        }
        .btn-action {
            display: inline-block;
            background: var(--blue);
            color: #fff;
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background: var(--navy);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <h1>Practical 9: Database Setup &amp; Verification</h1>

    <div style="text-align: center; padding: 12px 0; background: #ffffff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 16px; flex-wrap: wrap;">
            <a href="index.html">Portal Home</a> |
            <a href="register.php" style="font-weight: bold; color: var(--blue);">Practical 9 Registration</a> |
            <a href="login.php">Login</a> |
            <a href="db_setup.php">Rerun DB Setup</a>
        </nav>
    </div>

    <main class="setup-container">
        <h2 style="margin-top: 0; background: none; color: var(--navy); text-align: left; padding: 0;">
            MySQLi Database Initialization
        </h2>
        <p style="color: var(--ink-soft);">
            This tool initializes the <code>studenthub_db</code> database, creates the required Practical 9 tables (<code>users</code> and <code>audit_logs</code>), verifies schema constraints, and calibrates Bcrypt password hashes.
        </p>

        <h3 style="margin: 24px 0 12px 0; padding: 0;">Setup Execution Log:</h3>

        <?php if ($results['success']): ?>
            <?php foreach ($results['steps'] as $step): ?>
                <div class="step-item">
                    <span>✓</span>
                    <span><?= htmlspecialchars($step) ?></span>
                </div>
            <?php endforeach; ?>

            <div style="margin: 20px 0; padding: 16px; background: #eef2ff; border-radius: 10px; border: 1px solid #c7d2fe;">
                <h4 style="margin: 0 0 8px 0; color: #312e81;">Active Tables in Database:</h4>
                <div>
                    <?php foreach ($results['tables'] as $tbl): ?>
                        <span class="badge-pill"><?= htmlspecialchars($tbl) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top: 28px; text-align: center; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="register.php" class="btn-action">👉 Open Practical 9 Secure Registration</a>
                <a href="login.php" class="btn-action" style="background: #0f766e;">Go to Login Page</a>
                <a href="index.html" class="btn-action" style="background: #475569;">Back to Portal</a>
            </div>

        <?php else: ?>
            <div class="step-err">
                <h4 style="margin: 0 0 6px 0;">Setup Error:</h4>
                <p style="margin: 0;"><?= htmlspecialchars($results['error']) ?></p>
            </div>

            <div style="margin-top: 20px; padding: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 0.9rem;">
                <strong>Troubleshooting Guide for XAMPP:</strong>
                <ol style="margin: 8px 0 0 18px; padding: 0;">
                    <li>Open <strong>XAMPP Control Panel</strong> on your computer.</li>
                    <li>Ensure the <strong>MySQL</strong> service shows a green "Running" status on Port 3306.</li>
                    <li>If MySQL is running on a custom port, edit <code>DB_PORT</code> in <a href="db.php">db.php</a>.</li>
                    <li>Reload this page once MySQL is active.</li>
                </ol>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
