<?php
require_once __DIR__ . '/db.php';

$success = false;
$error = null;
$tables = [];

try {
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, null, DB_PORT);
    if ($mysqli->connect_errno) {
        throw new Exception("Connection to MySQL failed: " . $mysqli->connect_error);
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
        if ($res = $mysqli->store_result()) $res->free();
    } while ($mysqli->more_results() && $mysqli->next_result());

    $mysqli->select_db(DB_NAME);
    $res = $mysqli->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_row()) $tables[] = $row[0];
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
    <title>Database Setup - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .box { max-width: 550px; margin: 40px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 24px; font-family: sans-serif; }
        .pill { display: inline-block; background: #e0e7ff; color: #3730a3; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; margin: 2px; }
        .btn { display: inline-block; padding: 8px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">
    <div class="box">
        <h2 style="margin-top: 0; text-align: center;">Database Setup</h2>
        <?php if ($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 14px;">
                <strong>Database setup completed successfully!</strong> (<?= DB_NAME ?>)
            </div>
            <p><strong>Tables:</strong></p>
            <div style="margin-bottom: 14px;">
                <?php foreach ($tables as $t): ?><span class="pill"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
            </div>
            <p><strong>Credentials:</strong><br>
            &bull; Admin: <code>admin.portal</code> / <code>Admin@123</code><br>
            &bull; Student: <code>aarav.sharma</code> / <code>Student@123</code></p>
            <div style="margin-top: 18px;">
                <a href="login.php" class="btn">Go to Login</a>
                <a href="index.html" class="btn" style="background: #0f172a; margin-left: 8px;">Home</a>
            </div>
        <?php else: ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 14px;">
                <strong>Setup Failed:</strong> <?= htmlspecialchars($error) ?>
            </div>
            <a href="db_setup.php" class="btn">Retry</a>
        <?php endif; ?>
    </div>
</body>
</html>
