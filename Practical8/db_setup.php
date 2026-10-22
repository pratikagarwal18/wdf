<?php

require_once __DIR__ . '/db.php';

$isCli = (php_sapi_name() === 'cli');
$results = [
    'success' => false,
    'steps'   => [],
    'error'   => null
];

try {
    $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
    $pdoSetup = new PDO($dsnNoDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ]);

    $results['steps'][] = "Connected to MySQL Server at " . DB_HOST . ":" . DB_PORT;

    $sqlFile = __DIR__ . '/studenthub.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL script not found at {$sqlFile}");
    }

    $sqlContent = file_get_contents($sqlFile);
    $results['steps'][] = "Loaded studenthub.sql (" . strlen($sqlContent) . " bytes)";

    $cleanedSql = preg_replace('/DELIMITER\s+\$\$/i', '', $sqlContent);
    $cleanedSql = preg_replace('/DELIMITER\s+;/i', '', $cleanedSql);
    $cleanedSql = str_replace('$$', ';', $cleanedSql);

    $pdoSetup->exec($cleanedSql);
    $results['steps'][] = "Successfully executed database creation, schema tables, views, and seed data.";

    $pdoSetup->exec("USE `" . DB_NAME . "`");
    $stmt = $pdoSetup->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $results['steps'][] = "Verified tables in `" . DB_NAME . "`: " . implode(', ', $tables);

    $results['success'] = true;
    $results['tables'] = $tables;

} catch (PDOException $pe) {
    $results['success'] = false;
    $results['error'] = "Database Setup Error: " . $pe->getMessage();
} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
}

if ($isCli) {
    echo "========================================================\n";
    echo "Practical 8: StudentHub Database Setup Script\n";
    echo "========================================================\n";
    foreach ($results['steps'] as $step) {
        echo "[OK] " . $step . "\n";
    }
    if ($results['success']) {
        echo "\n>>> Database Setup Succeeded! studenthub_db is ready.\n";
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
    <title>Database Setup - StudentHub Practical 8</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .setup-container {
            max-width: 760px;
            margin: 40px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(28, 37, 65, 0.08);
            border: 1px solid var(--line);
        }
        .step-item {
            padding: 10px 14px;
            margin: 8px 0;
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: monospace;
        }
        .error-item {
            padding: 14px;
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            font-family: monospace;
        }
        .btn-group {
            margin-top: 25px;
            display: flex;
            gap: 12px;
        }
        .btn-action {
            display: inline-block;
            background: var(--blue);
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background: var(--navy);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
    </style>
</head>
<body>
    <h1>StudentHub: Database Setup (Practical 8)</h1>

    <div class="setup-container">
        <h2>Database Initialization Results</h2>
        <p>Execution log for importing <code>studenthub.sql</code> into MySQL:</p>

        <?php if ($results['success']): ?>
            <div style="padding: 12px 18px; background: #ecfdf5; border-radius: 8px; color: #065f46; font-weight: 600; margin-bottom: 20px;">
                &#10004; Database <code>studenthub_db</code> successfully configured with all 3NF tables, constraints, stored procedures, and seed records!
            </div>
            <div>
                <?php foreach ($results['steps'] as $s): ?>
                    <div class="step-item">&#10003; <?= htmlspecialchars($s) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="btn-group">
                <a href="practical8_test.php" class="btn-action">Open Connection Test & PDO Demo &rarr;</a>
                <a href="dashboard.html" class="btn-action btn-secondary">StudentHub Dashboard</a>
            </div>
        <?php else: ?>
            <div class="error-item">
                <strong>Setup Failed:</strong> <?= htmlspecialchars($results['error']) ?>
            </div>
            <p style="margin-top: 15px; color: var(--ink-soft);">
                <strong>Troubleshooting tips:</strong>
                <ol>
                    <li>Make sure <strong>XAMPP</strong> is installed and the <strong>MySQL</strong> service is started.</li>
                    <li>Verify in <code>db.php</code> that port 3306 and username <code>root</code> are matching your local MySQL setup.</li>
                    <li>Alternatively, open <strong>phpMyAdmin</strong> (<a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a>), click "Import", and select <code>studenthub.sql</code>.</li>
                </ol>
            </p>
            <div class="btn-group">
                <a href="db_setup.php" class="btn-action">Retry Setup</a>
                <a href="practical8_test.php" class="btn-action btn-secondary">Open Demonstration Interface</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
