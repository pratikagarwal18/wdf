<?php
/**
 * Practical 8: MySQL Schema & Database Connection Verification (MySQLi + PDO)
 * Beginner-friendly, clean PHP implementation
 */

require_once __DIR__ . '/db.php';

$diag = testDBConnection();
$mysqliDiag = testMySQLiConnection();
$isConnected = $diag['success'];

$actionMessage = null;
$actionSuccess = null;
$actionResults = null;
$activeTab = $_GET['tab'] ?? 'diagnostics';

// Process PDO Prepared Statement demonstrations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$isConnected) {
        $actionMessage = "MySQL is offline. Please start MySQL in XAMPP.";
        $actionSuccess = false;
    } else {
        $pdo = getDBConnection();

        // 1. Prepared SELECT with parameter binding
        if ($action === 'filter_students') {
            $dept = trim($_POST['department'] ?? '');
            $sem  = (int)($_POST['semester'] ?? 0);

            try {
                $sql = "SELECT student_id, roll_no, full_name, email, department, semester, phone 
                        FROM students 
                        WHERE (:dept = '' OR department = :dept) AND (:sem = 0 OR semester = :sem)
                        ORDER BY roll_no ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':dept' => $dept, ':sem' => $sem]);
                $actionResults = $stmt->fetchAll();
                $actionSuccess = true;
                $actionMessage = "Prepared Statement executed safely! Found " . count($actionResults) . " student(s).";
                $activeTab = 'prepared_statements';
            } catch (PDOException $e) {
                $actionSuccess = false;
                $actionMessage = "Query Error: " . $e->getMessage();
                $activeTab = 'prepared_statements';
            }
        }

        // 2. Prepared INSERT with parameter binding & constraint check
        elseif ($action === 'insert_registration') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $eventId   = (int)($_POST['event_id'] ?? 0);

            if ($studentId <= 0 || $eventId <= 0) {
                $actionSuccess = false;
                $actionMessage = "Please select a valid Student and Event.";
                $activeTab = 'prepared_statements';
            } else {
                try {
                    $ticketCode = 'TKT-' . $eventId . '-' . $studentId . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                    $sql = "INSERT INTO registrations (student_id, event_id, attendance_status, payment_status, ticket_code)
                            VALUES (:s_id, :e_id, 'REGISTERED', 'FREE', :ticket)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':s_id' => $studentId, ':e_id' => $eventId, ':ticket' => $ticketCode]);

                    $actionSuccess = true;
                    $actionMessage = "Registered successfully using PDO Prepared Statement! Ticket: {$ticketCode}";
                    $activeTab = 'prepared_statements';
                } catch (PDOException $e) {
                    $actionSuccess = false;
                    $actionMessage = ($e->errorInfo[1] == 1062) 
                        ? "Duplicate prevented: Student is already registered for this event!"
                        : "Error: " . $e->getMessage();
                    $activeTab = 'prepared_statements';
                }
            }
        }
    }
}

// Fetch records for display
$studentsList = [];
$eventsList   = [];
$regList      = [];
if ($isConnected) {
    try {
        $studentsList = safeFetchAll("SELECT * FROM students ORDER BY student_id ASC LIMIT 15");
        $eventsList   = safeFetchAll("SELECT * FROM events ORDER BY event_id ASC LIMIT 10");
        $regList      = safeFetchAll("SELECT * FROM vw_student_registrations ORDER BY registration_id DESC LIMIT 10");
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 8: MySQL Schema &amp; PDO Connection</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; padding: 24px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); margin-bottom: 22px; }
        .tab-nav { display: flex; gap: 8px; border-bottom: 2px solid var(--line); margin-bottom: 20px; }
        .tab-btn { background: none; border: none; padding: 10px 16px; font-weight: bold; cursor: pointer; border-bottom: 3px solid transparent; font-size: 0.95rem; }
        .tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
        .status-box { padding: 14px 18px; border-radius: 8px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; }
        .status-online { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-offline { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .form-row { display: flex; gap: 14px; margin-bottom: 12px; }
        .form-row > div { flex: 1; }
        .form-row label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.88rem; }
        .form-row select { width: 100%; max-width: 100%; padding: 8px; }
        .banner-msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 0.9rem; }
        .banner-msg.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .banner-msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .norm-box { background: #f8fafc; border-left: 4px solid var(--blue); padding: 12px 16px; border-radius: 4px; margin-bottom: 12px; }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical7_register.php">Practical 7 (JSON Form)</a> |
            <a href="register.php">Practical 9 (Registration)</a> |
            <a href="login.php">Practical 10 (Login)</a> |
            <a href="db_setup.php">DB Setup</a>
        </nav>
    </div>

    <div class="container">
        <div class="card">
            <h2 style="margin-top: 0; text-align: center;">Practical 8: Database Connection &amp; Schema Verification</h2>
            <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
                Verification of MySQLi and PDO database drivers, prepared statements, and relational 3NF schema.
            </p>

            <!-- Status Banner -->
            <div class="status-box <?= $isConnected ? 'status-online' : 'status-offline' ?>">
                <div>
                    <strong>Connection Status:</strong> <?= $isConnected ? '&#10003; Online &amp; Connected' : '&#9888; Offline' ?>
                    <span style="margin-left: 10px; font-size: 0.85rem;">(Database: <code><?= DB_NAME ?></code>)</span>
                </div>
                <div>
                    <span style="font-size: 0.85rem;">Driver: <strong>MySQLi + PDO (PHP Data Objects)</strong></span>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn <?= $activeTab === 'diagnostics' ? 'active' : '' ?>" onclick="switchTab('diagnostics')">1. Connection Diagnostics</button>
                <button class="tab-btn <?= $activeTab === 'prepared_statements' ? 'active' : '' ?>" onclick="switchTab('prepared_statements')">2. Prepared Statements Demo</button>
                <button class="tab-btn <?= $activeTab === 'schema' ? 'active' : '' ?>" onclick="switchTab('schema')">3. Schema &amp; Data Tables</button>
                <button class="tab-btn <?= $activeTab === 'normalization' ? 'active' : '' ?>" onclick="switchTab('normalization')">4. Normalization (1NF - 3NF)</button>
            </div>

            <!-- TAB 1: DIAGNOSTICS -->
            <div id="tab-diagnostics" style="display: <?= $activeTab === 'diagnostics' ? 'block' : 'none' ?>;">
                <h3>Driver Health Checks</h3>
                <div style="display: flex; gap: 14px; margin-bottom: 20px;">
                    <div style="flex: 1; background: #f8fafc; border: 1px solid var(--line); padding: 14px; border-radius: 8px;">
                        <strong>MySQLi Driver:</strong>
                        <p style="margin: 4px 0; font-size: 0.9rem; color: <?= $mysqliDiag['success'] ? '#166534' : '#991b1b' ?>;">
                            <?= htmlspecialchars($mysqliDiag['message']) ?>
                        </p>
                        <small>Host: <?= DB_HOST ?>:<?= DB_PORT ?> | User: <?= DB_USER ?></small>
                    </div>
                    <div style="flex: 1; background: #f8fafc; border: 1px solid var(--line); padding: 14px; border-radius: 8px;">
                        <strong>PDO Driver:</strong>
                        <p style="margin: 4px 0; font-size: 0.9rem; color: <?= $diag['success'] ? '#166534' : '#991b1b' ?>;">
                            <?= htmlspecialchars($diag['message']) ?>
                        </p>
                        <small>Database: <?= DB_NAME ?> | Mode: ERRMODE_EXCEPTION</small>
                    </div>
                </div>

                <h3>Tables in Database</h3>
                <table style="width: 100%; font-size: 0.88rem;">
                    <thead>
                        <tr><th>Table Name</th><th>Record Count</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($diag['tables'])): ?>
                            <?php foreach ($diag['tables'] as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t) ?></strong></td>
                                    <td><?= $diag['table_counts'][$t] ?? 0 ?> rows</td>
                                    <td><span style="color:#166534;">&#10003; Ready</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center; color:#991b1b;">No tables found. Please run <a href="db_setup.php">db_setup.php</a>.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: PREPARED STATEMENTS DEMO -->
            <div id="tab-prepared_statements" style="display: <?= $activeTab === 'prepared_statements' ? 'block' : 'none' ?>;">
                <?php if ($actionMessage): ?>
                    <div class="banner-msg <?= $actionSuccess ? 'ok' : 'err' ?>">
                        <?= htmlspecialchars($actionMessage) ?>
                    </div>
                <?php endif; ?>

                <h3>Demo 1: Filter Students (PDO Prepared SELECT)</h3>
                <form method="POST" action="practical8_test.php?tab=prepared_statements" style="border:none; box-shadow:none; padding:0;">
                    <input type="hidden" name="action" value="filter_students">
                    <div class="form-row">
                        <div>
                            <label>Department</label>
                            <select name="department">
                                <option value="">All Departments</option>
                                <option value="Computer Engineering">Computer Engineering</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Electronics &amp; Comm">Electronics &amp; Comm</option>
                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                            </select>
                        </div>
                        <div>
                            <label>Semester</label>
                            <select name="semester">
                                <option value="0">All Semesters</option>
                                <?php for ($s=1; $s<=8; $s++): ?>
                                    <option value="<?= $s ?>">Semester <?= $s ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Execute Prepared SELECT</button>
                </form>

                <?php if ($actionResults !== null): ?>
                    <h4 style="margin-top: 18px;">Results (<?= count($actionResults) ?> matching records):</h4>
                    <table style="width: 100%; font-size: 0.85rem;">
                        <thead>
                            <tr><th>Roll No</th><th>Full Name</th><th>Email</th><th>Department</th><th>Sem</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actionResults as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['roll_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                                    <td><?= htmlspecialchars($r['email']) ?></td>
                                    <td><?= htmlspecialchars($r['department']) ?></td>
                                    <td><?= htmlspecialchars($r['semester']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <hr style="margin: 24px 0;">

                <h3>Demo 2: Register Student for Event (PDO Prepared INSERT)</h3>
                <form method="POST" action="practical8_test.php?tab=prepared_statements" style="border:none; box-shadow:none; padding:0;">
                    <input type="hidden" name="action" value="insert_registration">
                    <div class="form-row">
                        <div>
                            <label>Select Student</label>
                            <select name="student_id" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($studentsList as $s): ?>
                                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['roll_no']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Select Event</label>
                            <select name="event_id" required>
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($eventsList as $e): ?>
                                    <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Execute Prepared INSERT</button>
                </form>
            </div>

            <!-- TAB 3: SCHEMA & DATA TABLES -->
            <div id="tab-schema" style="display: <?= $activeTab === 'schema' ? 'block' : 'none' ?>;">
                <h3>Students Table (Sample Data)</h3>
                <table style="width: 100%; font-size: 0.82rem;">
                    <thead>
                        <tr><th>ID</th><th>Roll No</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($studentsList, 0, 5) as $s): ?>
                            <tr>
                                <td>#<?= $s['student_id'] ?></td>
                                <td><b><?= htmlspecialchars($s['roll_no']) ?></b></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['department']) ?></td>
                                <td><?= htmlspecialchars($s['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Events Table (Sample Data)</h3>
                <table style="width: 100%; font-size: 0.82rem;">
                    <thead>
                        <tr><th>ID</th><th>Code</th><th>Title</th><th>Category</th><th>Venue</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventsList as $e): ?>
                            <tr>
                                <td>#<?= $e['event_id'] ?></td>
                                <td><b><?= htmlspecialchars($e['event_code']) ?></b></td>
                                <td><?= htmlspecialchars($e['title']) ?></td>
                                <td><?= htmlspecialchars($e['category']) ?></td>
                                <td><?= htmlspecialchars($e['venue']) ?></td>
                                <td><?= htmlspecialchars($e['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB 4: NORMALIZATION (1NF - 3NF) -->
            <div id="tab-normalization" style="display: <?= $activeTab === 'normalization' ? 'block' : 'none' ?>;">
                <h3>Relational Database Normalization Stages</h3>
                <div class="norm-box">
                    <strong>1. First Normal Form (1NF) - Atomicity &amp; Primary Keys:</strong>
                    <p style="margin: 4px 0 0; font-size: 0.88rem; color: #475569;">
                        Every table column contains atomic (indivisible) values. No repeating multi-value fields exist. Each table has an explicit Primary Key (<code>student_id</code>, <code>event_id</code>, <code>registration_id</code>).
                    </p>
                </div>
                <div class="norm-box" style="border-left-color: #d97706;">
                    <strong>2. Second Normal Form (2NF) - No Partial Dependencies:</strong>
                    <p style="margin: 4px 0 0; font-size: 0.88rem; color: #475569;">
                        The schema is in 1NF and all non-key columns depend on the entire Primary Key. In <code>registrations</code>, attributes like <code>ticket_code</code> depend on the whole registration record.
                    </p>
                </div>
                <div class="norm-box" style="border-left-color: #166534;">
                    <strong>3. Third Normal Form (3NF) - No Transitive Dependencies:</strong>
                    <p style="margin: 4px 0 0; font-size: 0.88rem; color: #475569;">
                        The schema is in 2NF and no non-key attribute depends on another non-key attribute. Student information is kept in <code>students</code> and event dates in <code>events</code>, avoiding duplication in <code>registrations</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(t) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('[id^="tab-"]').forEach(d => d.style.display = 'none');
            const target = document.getElementById('tab-' + t);
            if (target) target.style.display = 'block';
            event.target.classList.add('active');
        }
    </script>

</body>
</html>
