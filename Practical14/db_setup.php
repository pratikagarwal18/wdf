<?php


require_once __DIR__ . '/db.php';

$success = false;
$error = null;
$tables = [];

try {
    
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, null, DB_PORT);
    if ($mysqli->connect_errno) {
        throw new Exception("Connection to MySQL failed: " . $mysqli->connect_error . ". Please make sure MySQL is running in XAMPP.");
    }

    
    $sqlFile = __DIR__ . '/studenthub.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at " . $sqlFile);
    }
    $sql = file_get_contents($sqlFile);

    
    $sql = preg_replace('/DELIMITER\s+\$\$/i', '', $sql);
    $sql = preg_replace('/DELIMITER\s+;/i', '', $sql);
    $sql = str_replace('$$', ';', $sql);

    
    if (!$mysqli->multi_query($sql)) {
        throw new Exception("Failed to execute SQL: " . $mysqli->error);
    }

    
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    
    $mysqli->select_db(DB_NAME);
    $res = $mysqli->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_row()) {
            $tables[] = $row[0];
        }
        $res->free();
    }

    
    $adminHash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $studentHash = password_hash('Student@123', PASSWORD_BCRYPT);
    $facultyHash = password_hash('Faculty@123', PASSWORD_BCRYPT);

    $mysqli->query("UPDATE users SET password_hash = '{$adminHash}' WHERE username = 'admin.portal'");
    $mysqli->query("UPDATE users SET password_hash = '{$studentHash}' WHERE username IN ('aarav.sharma', 'diya.patel')");
    $mysqli->query("UPDATE users SET password_hash = '{$facultyHash}' WHERE username = 'dr.sharma'");

    $success = true;
    $mysqli->close();

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .setup-card {
            max-width: 650px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }
        .pill {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            margin: 3px;
        }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div class="setup-card">
        <h2 style="margin-top: 0; text-align: center;">Database Setup &amp; Initialization</h2>

        <?php if ($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 14px; border-radius: 6px; border: 1px solid #86efac; margin-bottom: 20px;">
                <strong>&#10003; Database setup completed successfully!</strong><br>
                Database <code><?= DB_NAME ?></code> created and populated.
            </div>

            <p><strong>Created Database Tables:</strong></p>
            <div style="margin-bottom: 20px;">
                <?php foreach ($tables as $t): ?>
                    <span class="pill"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
            </div>

            <p><strong>Demo Login Credentials:</strong></p>
            <ul>
                <li><strong>Admin:</strong> <code>admin.portal</code> / <code>Admin@123</code></li>
                <li><strong>Student:</strong> <code>aarav.sharma</code> / <code>Student@123</code></li>
                <li><strong>Faculty:</strong> <code>dr.sharma</code> / <code>Faculty@123</code></li>
            </ul>

            <div style="margin-top: 24px; display: flex; gap: 10px;">
                <a href="login.php" style="flex:1; text-align:center; background:var(--blue); color:#fff; padding:12px; border-radius:6px; text-decoration:none; font-weight:bold;">
                    Go to Login &rarr;
                </a>
                <a href="index.html" style="flex:1; text-align:center; background:var(--navy); color:#fff; padding:12px; border-radius:6px; text-decoration:none; font-weight:bold;">
                    Portal Home &rarr;
                </a>
            </div>

        <?php else: ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 14px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 20px;">
                <strong>&#9888; Setup Failed!</strong><br>
                <?= htmlspecialchars($error) ?>
            </div>
            <p>Make sure MySQL is running in your XAMPP Control Panel, then reload this page.</p>
            <button onclick="location.reload();">Retry Setup</button>
        <?php endif; ?>
    </div>

</body>
</html>
