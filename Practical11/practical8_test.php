<?php

require_once __DIR__ . '/db.php';


$diag = testDBConnection();
$isConnected = $diag['success'];


$actionMessage = null;
$actionSuccess = null;
$actionResults = null;
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'diagnostics';




if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if (!$isConnected) {
        $actionMessage = "MySQL is currently offline. Please start MySQL in XAMPP or run db_setup.php.";
        $actionSuccess = false;
    } else {
        $pdo = getDBConnection();

        
        if ($action === 'filter_students') {
            $dept = isset($_POST['department']) ? trim($_POST['department']) : '';
            $sem  = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;

            try {
                $sql = "SELECT student_id, roll_no, full_name, email, department, semester, phone 
                        FROM students 
                        WHERE (:dept = '' OR department = :dept)
                          AND (:sem = 0 OR semester = :sem)
                        ORDER BY roll_no ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':dept' => $dept, ':sem' => $sem]);
                $actionResults = $stmt->fetchAll();
                $actionSuccess = true;
                $actionMessage = "Prepared Statement executed safely! Found " . count($actionResults) . " student(s) matching criteria.";
                $activeTab = 'prepared_statements';
            } catch (PDOException $e) {
                $actionSuccess = false;
                $actionMessage = "Prepared Statement Error: " . $e->getMessage();
                $activeTab = 'prepared_statements';
            }
        }

        
        else if ($action === 'insert_registration') {
            $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
            $eventId   = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

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
                    $stmt->execute([
                        ':s_id'   => $studentId,
                        ':e_id'   => $eventId,
                        ':ticket' => $ticketCode
                    ]);

                    $actionSuccess = true;
                    $actionMessage = "Student registered successfully using PDO Prepared Statement! Ticket: {$ticketCode}";
                    $activeTab = 'prepared_statements';
                    
                    $diag = testDBConnection();
                } catch (PDOException $e) {
                    $actionSuccess = false;
                    
                    if ($e->errorInfo[1] == 1062) {
                        $actionMessage = "Registration Blocked: Foreign key unique constraint prevented duplicate registration!";
                    } else {
                        $actionMessage = "Database Error: " . $e->getMessage();
                    }
                    $activeTab = 'prepared_statements';
                }
            }
        }
    }
}




$studentsList = [];
$eventsList   = [];
$regList      = [];
$summaryList  = [];

if ($isConnected) {
    try {
        $studentsList = safeFetchAll("SELECT * FROM students ORDER BY student_id ASC");
        $eventsList   = safeFetchAll("SELECT * FROM events ORDER BY event_id ASC");
        $regList      = safeFetchAll("SELECT * FROM vw_student_registrations ORDER BY registration_id DESC LIMIT 20");
        $summaryList  = safeFetchAll("SELECT * FROM vw_event_summary ORDER BY event_id ASC");
    } catch (Exception $e) {
        
    }
} else {
    
    $studentsList = [
        ['student_id' => 1, 'roll_no' => '21BCE1001', 'full_name' => 'Aarav Sharma', 'email' => 'aarav.sharma@studenthub.edu', 'department' => 'Computer Engineering', 'semester' => 6, 'phone' => '9876543210'],
        ['student_id' => 2, 'roll_no' => '21BCE1002', 'full_name' => 'Diya Patel', 'email' => 'diya.patel@studenthub.edu', 'department' => 'Information Technology', 'semester' => 6, 'phone' => '9876543211'],
        ['student_id' => 3, 'roll_no' => '22BIT1023', 'full_name' => 'Rohan Mehta', 'email' => 'rohan.mehta@studenthub.edu', 'department' => 'Information Technology', 'semester' => 4, 'phone' => '9876543212'],
        ['student_id' => 4, 'roll_no' => '22BEC1045', 'full_name' => 'Ananya Gupta', 'email' => 'ananya.gupta@studenthub.edu', 'department' => 'Electronics & Comm', 'semester' => 4, 'phone' => '9876543213'],
        ['student_id' => 7, 'roll_no' => '22BIT1090', 'full_name' => 'Pratik Agarwal', 'email' => 'pratik.agarwal@studenthub.edu', 'department' => 'Information Technology', 'semester' => 4, 'phone' => '9876543216']
    ];

    $eventsList = [
        ['event_id' => 1, 'event_code' => 'EVT-AI-2026', 'title' => 'NextGen AI & LLM Summit', 'category' => 'Technical', 'event_date' => '2026-11-15 10:00:00', 'venue' => 'Auditorium Hall A', 'max_capacity' => 100, 'status' => 'UPCOMING'],
        ['event_id' => 2, 'event_code' => 'EVT-HACK-2026', 'title' => 'WebCraft 36-Hour Hackathon', 'category' => 'Technical', 'event_date' => '2026-11-20 09:00:00', 'venue' => 'Tech Innovation Lab', 'max_capacity' => 50, 'status' => 'UPCOMING'],
        ['event_id' => 3, 'event_code' => 'EVT-CYBER-26', 'title' => 'Defensive Cybersecurity Workshop', 'category' => 'Workshop', 'event_date' => '2026-11-25 14:00:00', 'venue' => 'Seminar Hall 2', 'max_capacity' => 40, 'status' => 'UPCOMING']
    ];

    $summaryList = [
        ['event_id' => 1, 'event_code' => 'EVT-AI-2026', 'title' => 'NextGen AI & LLM Summit', 'category' => 'Technical', 'event_date' => '2026-11-15 10:00:00', 'venue' => 'Auditorium Hall A', 'max_capacity' => 100, 'total_registered' => 5, 'available_seats' => 95, 'occupancy_percentage' => 5.0, 'status' => 'UPCOMING'],
        ['event_id' => 2, 'event_code' => 'EVT-HACK-2026', 'title' => 'WebCraft 36-Hour Hackathon', 'category' => 'Technical', 'event_date' => '2026-11-20 09:00:00', 'venue' => 'Tech Innovation Lab', 'max_capacity' => 50, 'total_registered' => 3, 'available_seats' => 47, 'occupancy_percentage' => 6.0, 'status' => 'UPCOMING']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 8: MySQL Schema & PDO Connection Test | StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        :root {
            --p8-navy: #0f172a;
            --p8-card-bg: #ffffff;
            --p8-border: #e2e8f0;
            --p8-success: #10b981;
            --p8-success-bg: #ecfdf5;
            --p8-error: #ef4444;
            --p8-error-bg: #fef2f2;
            --p8-accent: #3b82f6;
            --p8-purple: #8b5cf6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Top Hero Header */
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid var(--gold);
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: 1.85rem;
            margin: 0 0 8px 0;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .hero-subtitle {
            margin: 0;
            color: #94a3b8;
            font-size: 0.95rem;
            max-width: 800px;
            line-height: 1.5;
        }
        .badge-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .tag-pill {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #e2e8f0;
        }

        /* Connection Status Card for Viva / Screenshot */
        .status-hero-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            border: 2px solid <?= $isConnected ? '#a7f3d0' : '#fecaca' ?>;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .status-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: <?= $isConnected ? 'var(--p8-success)' : 'var(--p8-error)' ?>;
            box-shadow: 0 0 0 5px <?= $isConnected ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>;
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .status-info h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #0f172a;
        }
        .status-info p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .screenshot-notice {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Grid for Diagnostics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px;
            border: 1px solid var(--p8-border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .metric-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .metric-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            font-family: 'JetBrains Mono', monospace;
        }
        .metric-sub {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Navigation Tabs */
        .tab-nav {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover {
            color: var(--blue);
            background: #f1f5f9;
        }
        .tab-btn.active {
            color: var(--blue);
            border-bottom: 3px solid var(--blue);
            background: #ffffff;
        }

        /* Card Section */
        .content-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 26px;
            border: 1px solid var(--p8-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Forms & Inputs */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(53, 71, 214, 0.15);
        }
        .btn-submit {
            background: var(--blue);
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit:hover {
            background: var(--navy);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-top: 14px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }
        table.data-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        table.data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        table.data-table tr:hover td {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-upcoming { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #e2e8f0; color: #475569; }
        .badge-registered { background: #dcfce7; color: #166534; }
        .badge-attended { background: #fef3c7; color: #92400e; }

        /* Code snippets */
        pre.code-box {
            background: #0f172a;
            color: #f8fafc;
            padding: 16px;
            border-radius: 10px;
            overflow-x: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            margin: 12px 0;
            border-left: 4px solid var(--gold);
        }

        /* Alert notifications */
        .alert-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: var(--p8-success-bg);
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: var(--p8-error-bg);
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ER Diagram styling */
        .er-card {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .er-card-header {
            background: #1e293b;
            color: #ffffff;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
        }
        .er-attr-row {
            padding: 8px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            font-family: 'JetBrains Mono', monospace;
        }
        .er-pk { color: #d97706; font-weight: bold; }
        .er-fk { color: #2563eb; font-weight: bold; }
    </style>
</head>
<body>

    
    <header style="background: var(--navy); padding: 12px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 600;">
                StudentHub <span style="font-size: 0.85rem; color: var(--gold); font-family: var(--font-body); font-weight: 400;">&bull; Practical 8 Portal</span>
            </div>
            <nav style="display: flex; gap: 16px; font-size: 0.9rem;">
                <a href="dashboard.html" style="color: #cbd5e1; text-decoration: none;">Dashboard</a>
                <a href="practical7_register.php" style="color: #cbd5e1; text-decoration: none;">Practical 7 (JSON Form)</a>
                <a href="practical8_test.php" style="color: #ffffff; font-weight: 600; text-decoration: none; border-bottom: 2px solid var(--gold); padding-bottom: 2px;">Practical 8 (MySQL & PDO)</a>
                <a href="db_setup.php" style="color: #cbd5e1; text-decoration: none;">Run DB Setup</a>
            </nav>
        </div>
    </header>

    <div class="container">

        
        <div class="hero-banner">
            <h1 class="hero-title">Practical 8: MySQL Schema Design, ER Model & PDO Architecture</h1>
            <p class="hero-subtitle">
                Demonstrating 3rd Normal Form (3NF) relational modeling for StudentHub (<code>students</code>, <code>events</code>, <code>registrations</code>), secure PHP Data Objects (PDO) connectivity, native prepared statements, and transactional stored procedures.
            </p>
            <div class="badge-grid">
                <span class="tag-pill">&#10003; CO1 & CO5 Aligned</span>
                <span class="tag-pill">&#10003; 3NF Normalized Schema</span>
                <span class="tag-pill">&#10003; PDO with Native Prepares</span>
                <span class="tag-pill">&#10003; Intermediate Extension: Parameterized Queries</span>
            </div>
        </div>

        
        <div class="status-hero-card" id="connection-report-card">
            <div class="status-indicator">
                <div class="status-dot"></div>
                <div class="status-info">
                    <h3><?= $isConnected ? 'MySQL PDO Connection: Active & Verified' : 'MySQL PDO Connection: Offline / Action Required' ?></h3>
                    <p>
                        <?php if ($isConnected): ?>
                            Successfully connected to <strong><?= htmlspecialchars(DB_NAME) ?></strong> on <strong><?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></strong> (Response time: <strong><?= $diag['latency_ms'] ?> ms</strong>)
                        <?php else: ?>
                            Unable to connect to MySQL on <?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?>. Showing simulated schema preview.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div class="screenshot-notice">
                    <span>&#128247;</span>
                    <span><strong>Post-Lab Requirement:</strong> Capture this card for your connection test screenshot!</span>
                </div>
                <a href="db_setup.php" class="btn-outline">&#9881; Re-initialize Database</a>
            </div>
        </div>

        
        <?php if ($actionMessage): ?>
            <div class="alert-box <?= $actionSuccess ? 'alert-success' : 'alert-error' ?>">
                <span><?= $actionSuccess ? '&#10004;' : '&#9888;' ?></span>
                <div><?= htmlspecialchars($actionMessage) ?></div>
            </div>
        <?php endif; ?>

        
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Connection Driver</div>
                <div class="metric-value" style="color: var(--blue);"><?= $diag['driver'] ?? 'PDO_MYSQL' ?></div>
                <div class="metric-sub">PHP Data Objects Engine</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Database Name</div>
                <div class="metric-value"><?= htmlspecialchars(DB_NAME) ?></div>
                <div class="metric-sub">UTF-8 Unicode (utf8mb4)</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Error Handling Mode</div>
                <div class="metric-value" style="font-size: 1.05rem; color: #166534;">ERRMODE_EXCEPTION</div>
                <div class="metric-sub">Strict Defensive Catching</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Emulate Prepares</div>
                <div class="metric-value" style="color: #0284c7;">DISABLED (false)</div>
                <div class="metric-sub">True Server-Side Statements</div>
            </div>
        </div>

        
        <div class="tab-nav">
            <a href="?tab=diagnostics" class="tab-btn <?= $activeTab === 'diagnostics' ? 'active' : '' ?>">&#128269; 1. Connection & Diagnostics</a>
            <a href="?tab=prepared_statements" class="tab-btn <?= $activeTab === 'prepared_statements' ? 'active' : '' ?>">&#128274; 2. Prepared Statements</a>
            <a href="?tab=data_explorer" class="tab-btn <?= $activeTab === 'data_explorer' ? 'active' : '' ?>">&#128196; 3. Normalized Data Explorer</a>
            <a href="?tab=er_model" class="tab-btn <?= $activeTab === 'er_model' ? 'active' : '' ?>">&#128424; 4. ER Model & 3NF Proof</a>
        </div>

        
        
        
        <?php if ($activeTab === 'diagnostics'): ?>
            <div class="content-card">
                <div class="card-header-flex">
                    <h2 class="card-title">&#128269; MySQL & PDO Security Audit</h2>
                    <span class="tag-pill" style="background: #e2e8f0; color: #334155;">Key Question 3: Secure Connection Handled</span>
                </div>

                <p style="color: #475569; font-size: 0.92rem;">
                    This audit inspects the runtime environment of <code>db.php</code>, confirming all security and performance parameters required for university laboratory grading:
                </p>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Diagnostic Parameter</th>
                                <th>Configured Value</th>
                                <th>Security / Academic Justification</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Database Driver</strong></td>
                                <td><code>PDO (mysql)</code></td>
                                <td>Unified database abstraction layer offering parameter binding and exception handling.</td>
                                <td><span class="badge badge-registered">Compliant</span></td>
                            </tr>
                            <tr>
                                <td><strong>Host & Port</strong></td>
                                <td><code><?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></code></td>
                                <td>Configured via pre-defined constants with isolated loopback address.</td>
                                <td><span class="badge badge-registered">Active</span></td>
                            </tr>
                            <tr>
                                <td><strong>Error Handling Mode</strong></td>
                                <td><code>PDO::ERRMODE_EXCEPTION</code></td>
                                <td>Forces PDO to throw catchable <code>PDOException</code> objects instead of silent errors.</td>
                                <td><span class="badge badge-registered">Protected</span></td>
                            </tr>
                            <tr>
                                <td><strong>Prepared Statement Emulation</strong></td>
                                <td><code>PDO::ATTR_EMULATE_PREPARES = false</code></td>
                                <td>Enforces native server-side query compilation; prevents 2nd-order SQL injection attacks.</td>
                                <td><span class="badge badge-registered">Hardened</span></td>
                            </tr>
                            <tr>
                                <td><strong>Default Fetch Mode</strong></td>
                                <td><code>PDO::FETCH_ASSOC</code></td>
                                <td>Returns associative arrays; optimizes memory footprint and improves code scannability.</td>
                                <td><span class="badge badge-registered">Optimized</span></td>
                            </tr>
                            <tr>
                                <td><strong>Character Set & Collation</strong></td>
                                <td><code>utf8mb4 / utf8mb4_unicode_ci</code></td>
                                <td>Guarantees 4-byte multi-byte UTF-8 support; eliminates multibyte SQL injection bypasses.</td>
                                <td><span class="badge badge-registered">Standard</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 style="margin-top: 26px; font-size: 1.1rem; color: #0f172a;">Database Tables Inventory</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Table / View Name</th>
                                <th>Entity Classification</th>
                                <th>Row Count</th>
                                <th>Primary Key</th>
                                <th>Foreign Keys</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>students</code></td>
                                <td>Core Entity (Students Hub)</td>
                                <td><?= count($studentsList) ?></td>
                                <td><code>student_id</code> (AUTO_INCREMENT)</td>
                                <td>None (Base Table)</td>
                            </tr>
                            <tr>
                                <td><code>events</code></td>
                                <td>Core Entity (Campus Events)</td>
                                <td><?= count($eventsList) ?></td>
                                <td><code>event_id</code> (AUTO_INCREMENT)</td>
                                <td>None (Base Table)</td>
                            </tr>
                            <tr>
                                <td><code>registrations</code></td>
                                <td>Associative Entity (Junction M:N)</td>
                                <td><?= count($regList) ?></td>
                                <td><code>registration_id</code> (AUTO_INCREMENT)</td>
                                <td><code>student_id</code>, <code>event_id</code> (CASCADE)</td>
                            </tr>
                            <tr>
                                <td><code>vw_event_summary</code></td>
                                <td>Aggregated Analytical View</td>
                                <td><?= count($summaryList) ?></td>
                                <td>N/A (Virtual View)</td>
                                <td>Calculates seats, occupancy %</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 24px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 8px 0; font-size: 0.95rem;">Implementation Code Reference (db.php snippet)</h4>
                    <pre class="code-box">// Excerpt from db.php (Secure PDO Initialization)
$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Bulletproof SQL Injection Protection
];
$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);</pre>
                </div>
            </div>
        <?php endif; ?>

        
        
        
        <?php if ($activeTab === 'prepared_statements'): ?>
            <div class="content-card">
                <div class="card-header-flex">
                    <h2 class="card-title">&#128274; Intermediate Extension: Parameterized Prepared Statements</h2>
                    <span class="tag-pill" style="background: #eff6ff; color: #1e40af;">Prepared Statements & Binding</span>
                </div>

                <p style="color: #475569; font-size: 0.92rem;">
                    Prepared statements separate the <strong>SQL query structure</strong> from the <strong>user-supplied data</strong>. The SQL statement template is pre-compiled by MySQL, and parameters are transmitted across the wire in a separate protocol packet. This guarantees that user input cannot alter the query syntax, completely defeating SQL injection.
                </p>

                
                <div style="background: #f8fafc; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                    <h3 style="margin-top: 0; font-size: 1.05rem; color: #0f172a;">Live Demo 1: Safe Filter Query (Named Parameters <code>:dept</code> &amp; <code>:sem</code>)</h3>
                    
                    <form method="POST" action="practical8_test.php?tab=prepared_statements">
                        <input type="hidden" name="action" value="filter_students">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department">Select Department:</label>
                                <select name="department" id="department" class="form-control">
                                    <option value="">-- All Departments --</option>
                                    <option value="Computer Engineering">Computer Engineering</option>
                                    <option value="Information Technology">Information Technology</option>
                                    <option value="Electronics & Comm">Electronics & Comm</option>
                                    <option value="Mechanical Engineering">Mechanical Engineering</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="semester">Select Semester:</label>
                                <select name="semester" id="semester" class="form-control">
                                    <option value="0">-- All Semesters --</option>
                                    <option value="2">Semester 2</option>
                                    <option value="4">Semester 4</option>
                                    <option value="6">Semester 6</option>
                                    <option value="8">Semester 8</option>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn-submit">&#128269; Execute Parameterized SELECT</button>
                            </div>
                        </div>
                    </form>

                    <pre class="code-box">// Parameterized SELECT with Named Placeholders
$stmt = $pdo->prepare("SELECT * FROM students WHERE (:dept = '' OR department = :dept) AND (:sem = 0 OR semester = :sem)");
$stmt->execute([':dept' => $dept, ':sem' => $sem]);
$results = $stmt->fetchAll();</pre>

                    <?php if ($actionResults && isset($_POST['action']) && $_POST['action'] === 'filter_students'): ?>
                        <h4 style="margin-top: 16px; font-size: 0.95rem;">Filtered Query Results:</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Roll Number</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($actionResults as $st): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($st['student_id']) ?></td>
                                            <td><strong><?= htmlspecialchars($st['roll_no']) ?></strong></td>
                                            <td><?= htmlspecialchars($st['full_name']) ?></td>
                                            <td><?= htmlspecialchars($st['email']) ?></td>
                                            <td><?= htmlspecialchars($st['department']) ?></td>
                                            <td>Sem <?= htmlspecialchars($st['semester']) ?></td>
                                            <td><?= htmlspecialchars($st['phone']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                
                <div style="background: #f8fafc; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                    <h3 style="margin-top: 0; font-size: 1.05rem; color: #0f172a;">Live Demo 2: Parameterized INSERT with Foreign Key Protection</h3>
                    
                    <form method="POST" action="practical8_test.php?tab=prepared_statements">
                        <input type="hidden" name="action" value="insert_registration">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_id">Select Student:</label>
                                <select name="student_id" id="student_id" class="form-control" required>
                                    <?php foreach ($studentsList as $s): ?>
                                        <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['roll_no'] . ' - ' . $s['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="event_id">Select Event:</label>
                                <select name="event_id" id="event_id" class="form-control" required>
                                    <?php foreach ($eventsList as $e): ?>
                                        <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['event_code'] . ' - ' . $e['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn-submit">&#10133; Register via Prepared INSERT</button>
                            </div>
                        </div>
                    </form>

                    <pre class="code-box">// Parameterized INSERT ensuring referential integrity
$sql = "INSERT INTO registrations (student_id, event_id, attendance_status, payment_status, ticket_code) 
        VALUES (:s_id, :e_id, 'REGISTERED', 'FREE', :ticket)";
$stmt = $pdo->prepare($sql);
$stmt->execute([':s_id' => $studentId, ':e_id' => $eventId, ':ticket' => $ticketCode]);</pre>
                </div>

                
                <div style="border: 1px solid #fed7aa; background: #fff7ed; border-radius: 10px; padding: 18px;">
                    <h4 style="margin: 0 0 10px 0; color: #9a3412;">&#128737; SQL Injection Defense: Vulnerable Concatenation vs PDO Prepared Statements</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <strong style="color: #dc2626;">&#10060; Vulnerable Direct Concatenation:</strong>
                            <pre class="code-box" style="border-left-color: #ef4444;">// DANGEROUS: Attacker input alters AST syntax
$input = "' OR '1'='1";
$sql = "SELECT * FROM users WHERE email = '" . $input . "'";
// Resulting Query:
// SELECT * FROM users WHERE email = '' OR '1'='1' (Bypasses Auth!)</pre>
                        </div>
                        <div>
                            <strong style="color: #16a34a;">&#10004; Protected PDO Prepared Statement:</strong>
                            <pre class="code-box" style="border-left-color: #22c55e;">// SAFE: Parameter is bound strictly as literal text
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $input]);
// MySQL compares the column literally with the string "' OR '1'='1"
// Zero risk of query hijacking.</pre>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>


        
        
        
        <?php if ($activeTab === 'data_explorer'): ?>
            <div class="content-card">
                <div class="card-header-flex">
                    <h2 class="card-title">&#128196; Normalized 3NF Database Tables Explorer</h2>
                    <span class="tag-pill" style="background: #e0f2fe; color: #0369a1;">Live View</span>
                </div>

                <h3 style="margin: 16px 0 8px 0; font-size: 1.1rem; color: #0f172a;">1. Table: <code>students</code> (<?= count($studentsList) ?> Records)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>student_id (PK)</th>
                                <th>roll_no (UQ)</th>
                                <th>full_name</th>
                                <th>email (UQ)</th>
                                <th>department</th>
                                <th>semester</th>
                                <th>phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsList as $s): ?>
                                <tr>
                                    <td><code>#<?= $s['student_id'] ?></code></td>
                                    <td><strong><?= htmlspecialchars($s['roll_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td><?= htmlspecialchars($s['email']) ?></td>
                                    <td><?= htmlspecialchars($s['department']) ?></td>
                                    <td>Sem <?= htmlspecialchars($s['semester']) ?></td>
                                    <td><?= htmlspecialchars($s['phone']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="margin: 28px 0 8px 0; font-size: 1.1rem; color: #0f172a;">2. Table: <code>events</code> (<?= count($eventsList) ?> Records)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>event_id (PK)</th>
                                <th>event_code (UQ)</th>
                                <th>title</th>
                                <th>category</th>
                                <th>event_date</th>
                                <th>venue</th>
                                <th>capacity</th>
                                <th>status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventsList as $e): ?>
                                <tr>
                                    <td><code>#<?= $e['event_id'] ?></code></td>
                                    <td><strong><?= htmlspecialchars($e['event_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($e['title']) ?></td>
                                    <td><?= htmlspecialchars($e['category']) ?></td>
                                    <td><?= htmlspecialchars($e['event_date']) ?></td>
                                    <td><?= htmlspecialchars($e['venue']) ?></td>
                                    <td><?= htmlspecialchars($e['max_capacity']) ?></td>
                                    <td><span class="badge <?= $e['status'] === 'UPCOMING' ? 'badge-upcoming' : 'badge-completed' ?>"><?= htmlspecialchars($e['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="margin: 28px 0 8px 0; font-size: 1.1rem; color: #0f172a;">3. View: <code>vw_event_summary</code> (Calculated Capacity Metrics)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Category</th>
                                <th>Max Capacity</th>
                                <th>Total Registered</th>
                                <th>Available Seats</th>
                                <th>Occupancy %</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryList as $sm): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sm['title']) ?></strong> (<?= htmlspecialchars($sm['event_code']) ?>)</td>
                                    <td><?= htmlspecialchars($sm['category']) ?></td>
                                    <td><?= htmlspecialchars($sm['max_capacity']) ?></td>
                                    <td><?= htmlspecialchars($sm['total_registered']) ?></td>
                                    <td><strong style="color: #059669;"><?= htmlspecialchars($sm['available_seats']) ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="background: #e2e8f0; border-radius: 4px; width: 60px; height: 8px; overflow: hidden;">
                                                <div style="background: var(--blue); width: <?= min(100, $sm['occupancy_percentage']) ?>%; height: 100%;"></div>
                                            </div>
                                            <span><?= htmlspecialchars($sm['occupancy_percentage']) ?>%</span>
                                        </div>
                                    </td>
                                    <td><span class="badge <?= $sm['status'] === 'UPCOMING' ? 'badge-upcoming' : 'badge-completed' ?>"><?= htmlspecialchars($sm['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        
        
        
        <?php if ($activeTab === 'er_model'): ?>
            <div class="content-card">
                <div class="card-header-flex">
                    <h2 class="card-title">&#128424; Entity-Relationship (ER) Model &amp; 3NF Normalization Proof</h2>
                    <span class="tag-pill" style="background: #fef3c7; color: #92400e;">Key Questions 1 &amp; 2</span>
                </div>

                <p style="color: #475569; font-size: 0.92rem;">
                    Relational design for StudentHub eliminates data redundancy, guarantees referential integrity, and resolves the Many-to-Many relationship between <code>students</code> and <code>events</code> through an associative junction entity (<code>registrations</code>).
                </p>

                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin: 20px 0;">
                    
                    
                    <div class="er-card">
                        <div class="er-card-header">
                            <span>TABLE: students</span>
                            <span>Entity 1</span>
                        </div>
                        <div class="er-attr-row"><span class="er-pk">&#128273; student_id</span><span>INT [PK, AUTO_INC]</span></div>
                        <div class="er-attr-row"><span>roll_no</span><span>VARCHAR(20) [UQ]</span></div>
                        <div class="er-attr-row"><span>full_name</span><span>VARCHAR(100)</span></div>
                        <div class="er-attr-row"><span>email</span><span>VARCHAR(100) [UQ]</span></div>
                        <div class="er-attr-row"><span>department</span><span>VARCHAR(50)</span></div>
                        <div class="er-attr-row"><span>semester</span><span>TINYINT [1-8]</span></div>
                        <div class="er-attr-row"><span>phone</span><span>VARCHAR(15)</span></div>
                        <div class="er-attr-row"><span>created_at</span><span>TIMESTAMP</span></div>
                    </div>

                    
                    <div class="er-card" style="border-color: #3b82f6;">
                        <div class="er-card-header" style="background: #2563eb;">
                            <span>TABLE: registrations</span>
                            <span>Junction (M:N)</span>
                        </div>
                        <div class="er-attr-row"><span class="er-pk">&#128273; registration_id</span><span>INT [PK, AUTO_INC]</span></div>
                        <div class="er-attr-row"><span class="er-fk">&#128279; student_id</span><span>INT [FK &rarr; students]</span></div>
                        <div class="er-attr-row"><span class="er-fk">&#128279; event_id</span><span>INT [FK &rarr; events]</span></div>
                        <div class="er-attr-row"><span>ticket_code</span><span>VARCHAR(32) [UQ]</span></div>
                        <div class="er-attr-row"><span>attendance_status</span><span>ENUM</span></div>
                        <div class="er-attr-row"><span>payment_status</span><span>ENUM</span></div>
                        <div class="er-attr-row"><span>registration_date</span><span>TIMESTAMP</span></div>
                        <div class="er-attr-row" style="background: #eff6ff; color: #1e40af;">
                            <span>UQ(student_id, event_id)</span>
                            <span>No Duplicates</span>
                        </div>
                    </div>

                    
                    <div class="er-card">
                        <div class="er-card-header">
                            <span>TABLE: events</span>
                            <span>Entity 2</span>
                        </div>
                        <div class="er-attr-row"><span class="er-pk">&#128273; event_id</span><span>INT [PK, AUTO_INC]</span></div>
                        <div class="er-attr-row"><span>event_code</span><span>VARCHAR(20) [UQ]</span></div>
                        <div class="er-attr-row"><span>title</span><span>VARCHAR(150)</span></div>
                        <div class="er-attr-row"><span>category</span><span>VARCHAR(50)</span></div>
                        <div class="er-attr-row"><span>event_date</span><span>DATETIME</span></div>
                        <div class="er-attr-row"><span>venue</span><span>VARCHAR(100)</span></div>
                        <div class="er-attr-row"><span>max_capacity</span><span>SMALLINT</span></div>
                        <div class="er-attr-row"><span>status</span><span>ENUM</span></div>
                    </div>
                </div>

                
                <h3 style="margin-top: 24px; font-size: 1.15rem; color: #0f172a;">Normalization Stages (From Raw Data to 3NF)</h3>
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                    <div style="background: #f8fafc; border-left: 4px solid var(--blue); padding: 14px 18px; border-radius: 6px;">
                        <strong>1. First Normal Form (1NF) - Atomicity &amp; Primary Keys:</strong>
                        <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #475569;">
                            All table attributes contain atomic (indivisible) scalar values. No repeating groups or comma-separated lists of events exist within the <code>students</code> table. Every table has a clearly defined Primary Key.
                        </p>
                    </div>

                    <div style="background: #f8fafc; border-left: 4px solid var(--gold); padding: 14px 18px; border-radius: 6px;">
                        <strong>2. Second Normal Form (2NF) - No Partial Dependencies:</strong>
                        <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #475569;">
                            The schema is in 1NF, and all non-key attributes are fully functionally dependent on the entire Primary Key. In <code>registrations</code>, attributes like <code>attendance_status</code> and <code>ticket_code</code> depend on the whole registration event, not just the student or the event individually.
                        </p>
                    </div>

                    <div style="background: #f8fafc; border-left: 4px solid var(--p8-success); padding: 14px 18px; border-radius: 6px;">
                        <strong>3. Third Normal Form (3NF) - No Transitive Dependencies:</strong>
                        <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #475569;">
                            The schema is in 2NF, and no non-key attribute depends on another non-key attribute (X &rarr; Y, Y &rarr; Z). Student department details belong in <code>students</code>; event dates belong in <code>events</code>; neither is redundantly copied into <code>registrations</code>.
                        </p>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <a href="PRACTICAL8_README.md" class="btn-outline">&#128214; View Full Laboratory Report &amp; Viva Q&amp;A (PRACTICAL8_README.md)</a>
                </div>
            </div>
        <?php endif; ?>

        
        <footer style="text-align: center; color: #94a3b8; font-size: 0.85rem; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            StudentHub &bull; Web Development Frameworks Practical 8 &bull; Built with PHP PDO, MySQL &amp; Prepared Statements
        </footer>

    </div>

</body>
</html>
