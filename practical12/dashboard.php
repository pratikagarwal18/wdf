<?php


require_once __DIR__ . '/auth.php';


requireLogin();


requireRole(['STUDENT', 'FACULTY', 'ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);


$lastLoginDisplay = 'First Login Session';
if ($mysqli && !empty($user['user_id'])) {
    $stmt = $mysqli->prepare("SELECT full_name, email, department, semester, role, last_login, created_at FROM users WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['last_login'])) {
            $lastLoginDisplay = date('d M Y, h:i:s A', strtotime($row['last_login']));
        }
        $stmt->close();
    }
}


$timeoutSeconds = isset($_SESSION['custom_timeout']) ? (int)$_SESSION['custom_timeout'] : DEFAULT_SESSION_TIMEOUT;
$idleTime = time() - ($_SESSION['last_activity'] ?? time());
$remainingSeconds = max(0, $timeoutSeconds - $idleTime);


if (isset($_POST['set_test_timeout'])) {
    $_SESSION['custom_timeout'] = 10;
    header("Location: dashboard.php?msg=timeout_set");
    exit;
}
if (isset($_POST['reset_timeout'])) {
    unset($_SESSION['custom_timeout']);
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Practical 10 - StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --navy: #0f172a;
            --blue: #2563eb;
            --light-bg: #f8fafc;
            --border: #e2e8f0;
        }

        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
            margin: 0;
            color: #1e293b;
        }

        .top-navbar {
            background: #0f172a;
            color: #ffffff;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .brand-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        .role-badge {
            background: #38bdf8;
            color: #0c4a6e;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 2px 8px;
            border-radius: 999px;
            text-transform: uppercase;
        }

        .btn-logout {
            background: #ef4444;
            color: #ffffff;
            text-decoration: none;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #dc2626;
        }

        .container {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 20px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            border-radius: 18px;
            padding: 30px 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-card h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.9rem;
            margin: 0 0 8px 0;
        }

        .welcome-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .meta-chips {
            display: flex;
            gap: 12px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .meta-chip {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.84rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 860px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
        }

        .card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            color: #0f172a;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e3a8a;
            font-family: 'Outfit', sans-serif;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
            font-weight: 600;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .timer-box {
            background: #fefce8;
            border: 1.5px solid #fef08a;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .countdown-digits {
            font-size: 2rem;
            font-weight: 800;
            font-family: 'Outfit', monospace;
            color: #854d0e;
        }

        .timer-bar {
            width: 100%;
            height: 6px;
            background: #fef08a;
            border-radius: 999px;
            overflow: hidden;
            margin: 10px 0;
        }

        .timer-fill {
            height: 100%;
            background: #eab308;
            width: 100%;
            transition: width 1s linear;
        }

        .btn-sim {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .btn-sim:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>

    
    <header class="top-navbar">
        <a href="index.html" class="brand-logo">
            &#127891; StudentHub <span>Portal</span>
        </a>
        <div class="nav-actions">
            <a href="courses.html" style="color: #cbd5e1; text-decoration: none; font-size: 0.88rem;">Courses</a>
            <a href="attendance.html" style="color: #cbd5e1; text-decoration: none; font-size: 0.88rem;">Attendance</a>
            <a href="result.html" style="color: #cbd5e1; text-decoration: none; font-size: 0.88rem;">Results</a>
            <a href="practical11_students.php" style="color: #60a5fa; text-decoration: none; font-size: 0.88rem; font-weight: 700;">Practical 11 (CRUD) &rarr;</a>
            <a href="practical10_test.php" style="color: #38bdf8; text-decoration: none; font-size: 0.88rem; font-weight: 700;">Test Suite &rarr;</a>

            <div class="user-tag">
                <span>&#128100; <?= htmlspecialchars($user['full_name']) ?></span>
                <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
            </div>

            <?php if ($user['role'] === 'ADMIN'): ?>
                <a href="admin_dashboard.php" style="background: #38bdf8; color: #0c4a6e; text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; font-weight: 700;">
                    Switch to Admin View &rarr;
                </a>
            <?php endif; ?>

            <a href="logout.php" class="btn-logout">&#10162; Log Out</a>
        </div>
    </header>

    <div class="container">
        
        <div class="welcome-card">
            <div>
                <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                <p>Academic &amp; Student Registration Management Portal</p>
                <div class="meta-chips">
                    <div class="meta-chip">&#127979; Department: <strong><?= htmlspecialchars($user['department']) ?></strong></div>
                    <div class="meta-chip">&#128337; <strong>Intermediate Extension:</strong> Last Login: <strong><?= $lastLoginDisplay ?></strong></div>
                </div>
            </div>
            <div>
                <a href="logout.php" style="background: rgba(255,255,255,0.2); color: #fff; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; backdrop-filter: blur(8px);">
                    End Session
                </a>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <div>
                <div class="card">
                    <h3>&#128202; Academic Snapshot</h3>
                    <div class="stat-grid">
                        <div class="stat-box">
                            <div class="stat-value">6</div>
                            <div class="stat-label">Enrolled Courses</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">92%</div>
                            <div class="stat-label">Attendance Rate</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">3.85</div>
                            <div class="stat-label">Current CGPA</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">5</div>
                            <div class="stat-label">Pending Tasks</div>
                        </div>
                    </div>

                    <h4 style="margin: 20px 0 12px 0; color: #0f172a;">Enrolled Subjects (Semester <?= htmlspecialchars($user['semester']) ?>)</h4>
                    <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left;">
                                <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Course Code</th>
                                <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Course Title</th>
                                <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Credits</th>
                                <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><strong>WDF301</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Web Development Frameworks</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">4</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><span class="badge" style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 999px;">Active</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><strong>DBMS204</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Database Management Systems</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">4</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><span class="badge" style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 999px;">Active</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><strong>OS302</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Operating Systems</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">3</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;"><span class="badge" style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 999px;">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                
                <div class="card">
                    <h3>&#128736; Integrated Practicals (All Practicals Accessible)</h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-top: 0;">
                        Seamlessly jump between all modules of this integrated laboratory project:
                    </p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="practical6.html" style="background: #f1f5f9; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; color: #334155; font-weight: 600;">Practical 6 (JSON Explorer)</a>
                        <a href="practical7_register.php" style="background: #fef3c7; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; color: #92400e; font-weight: 600;">Practical 7 (JSON Forms)</a>
                        <a href="practical8_test.php" style="background: #e0f2fe; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; color: #0369a1; font-weight: 600;">Practical 8 (MySQL &amp; PDO)</a>
                        <a href="register.php" style="background: #ede9fe; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; color: #5b21b6; font-weight: 600;">Practical 9 (Secure Register)</a>
                        <a href="practical10_test.php" style="background: #0284c7; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; color: #ffffff; font-weight: 700;">Practical 10 (Test Suite &amp; Report) &rarr;</a>
                    </div>
                </div>
            </div>

            
            <div>
                
                <div class="card">
                    <h3>&#9201; Session Idle Timer</h3>
                    <div class="timer-box">
                        <div style="font-size: 0.82rem; color: #854d0e; font-weight: 600;">INACTIVITY TIMEOUT WINDOW</div>
                        <div class="countdown-digits" id="timerCount"><?= $remainingSeconds ?>s</div>
                        <div class="timer-bar">
                            <div class="timer-fill" id="timerFill"></div>
                        </div>
                        <p style="margin: 6px 0 0 0; font-size: 0.78rem; color: #713f12;">
                            Threshold: <strong><?= $timeoutSeconds ?> seconds</strong>. If idle exceeds this limit, session terminates automatically.
                        </p>
                    </div>

                    
                    <div style="margin-top: 14px; padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px;">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #475569;">VIVA / SCREENSHOT SIMULATOR:</span>
                        <form method="POST" style="margin-top: 8px;">
                            <?php if ($timeoutSeconds > 15): ?>
                                <button type="submit" name="set_test_timeout" class="btn-sim" style="background: #fef2f2; border-color: #fca5a5; color: #b91c1c;">
                                    &#9888; Test Fast Timeout (10 Seconds)
                                </button>
                            <?php else: ?>
                                <button type="submit" name="reset_timeout" class="btn-sim" style="background: #f0fdf4; border-color: #86efac; color: #166534;">
                                    &#10003; Reset Timeout to Default (15 mins)
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                
                <div class="card">
                    <h3>&#128274; Session Security Audit</h3>
                    <ul class="info-list">
                        <li>
                            <span style="color: #64748b;">Session ID:</span>
                            <code><?= substr(session_id(), 0, 10) ?>...</code>
                        </li>
                        <li>
                            <span style="color: #64748b;">HttpOnly Cookie:</span>
                            <span style="color: #16a34a; font-weight: 700;">&#10003; Active</span>
                        </li>
                        <li>
                            <span style="color: #64748b;">SameSite Defense:</span>
                            <span style="color: #16a34a; font-weight: 700;">Lax (CSRF Protected)</span>
                        </li>
                        <li>
                            <span style="color: #64748b;">Session Fixation:</span>
                            <span style="color: #16a34a; font-weight: 700;">Regenerated on Login</span>
                        </li>
                        <li>
                            <span style="color: #64748b;">Login Timestamp:</span>
                            <span><?= date('h:i:s A', $user['login_time']) ?></span>
                        </li>
                        
                    </ul>
                </div>
            </div>
        </div>
    </div>

    
    <script>
        let secondsLeft = <?= (int)$remainingSeconds ?>;
        const totalWindow = <?= (int)$timeoutSeconds ?>;
        const countElem = document.getElementById("timerCount");
        const fillElem = document.getElementById("timerFill");

        const interval = setInterval(function () {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(interval);
                countElem.textContent = "0s (Expired)";
                fillElem.style.width = "0%";
                // Trigger auto-redirect to login with timeout notification
                window.location.href = "login.php?error=timeout";
            } else {
                countElem.textContent = secondsLeft + "s";
                const pct = (secondsLeft / totalWindow) * 100;
                fillElem.style.width = pct + "%";
            }
        }, 1000);
    </script>

</body>
</html>
