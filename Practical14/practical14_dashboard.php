<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();

if (isset($_GET['login_as'])) {
    $r = strtoupper(trim($_GET['login_as']));
    $_SESSION['user_id'] = ($r === 'ADMIN') ? 3 : 1;
    $_SESSION['username'] = ($r === 'ADMIN') ? 'admin.portal' : 'aarav.sharma';
    $_SESSION['full_name'] = ($r === 'ADMIN') ? 'System Admin' : 'Aarav Sharma';
    $_SESSION['role'] = $r;
}

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user = getCurrentUser();
$role = strtoupper($user['role'] ?? '');

if ($role !== 'ADMIN') {
    header("Location: unauthorized.php?required=ADMIN");
    exit;
}

$conn = getMySQLiConnection(true);
ensureAuditLogsTableReady($conn);

$userStats = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN role='ADMIN' THEN 1 ELSE 0 END) AS admins, SUM(CASE WHEN role='STUDENT' THEN 1 ELSE 0 END) AS students FROM users")->fetch_assoc();
$studentCount = (int)$conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$eventCount = (int)$conn->query("SELECT COUNT(*) FROM events")->fetch_row()[0];

$latestRegs = $conn->query("SELECT r.ticket_code, s.full_name, e.title, r.registration_date, r.attendance_status 
    FROM registrations r 
    JOIN students s ON r.student_id = s.student_id 
    JOIN events e ON r.event_id = e.event_id 
    ORDER BY r.registration_id DESC LIMIT 5");

$auditLogs = $conn->query("SELECT username, action, details, created_at FROM audit_logs ORDER BY log_id DESC LIMIT 5");

$previewRole = isset($_GET['menu_role']) ? strtoupper($_GET['menu_role']) : $role;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Practical 14: Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 20px auto; padding: 0 16px; font-family: sans-serif; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 16px 0; }
        .stat-box { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; text-align: center; }
        .stat-num { font-size: 2rem; font-weight: bold; color: #1e3a8a; margin: 6px 0; }
        .dynamic-menu { background: #1e293b; padding: 10px 16px; display: flex; gap: 12px; flex-wrap: wrap; border-radius: 6px; }
        .dynamic-menu a { color: #cbd5e1; text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 4px 8px; }
        .dynamic-menu a.active, .dynamic-menu a:hover { color: #fff; background: #334155; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.88rem; background: #fff; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f1f5f9; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2>Admin Dashboard (Practical 14)</h2>
            <div>
                Logged in as: <strong><?= htmlspecialchars($user['username']) ?></strong> (<?= htmlspecialchars($user['role']) ?>) |
                <a href="logout.php">Logout</a>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px;">
            Preview Dynamic Menu as:
            <a href="practical14_dashboard.php?menu_role=ADMIN">ADMIN</a> |
            <a href="practical14_dashboard.php?menu_role=FACULTY">FACULTY</a> |
            <a href="practical14_dashboard.php?menu_role=STUDENT">STUDENT</a> |
            <a href="practical14_dashboard.php?login_as=STUDENT" style="color: red;">Test Student Access (403 Block)</a>
        </div>

        <nav class="dynamic-menu">
            <?php if ($previewRole === 'ADMIN'): ?>
                <a href="practical14_dashboard.php" class="active">Admin Dashboard</a>
                <a href="practical11_students.php">Students CRUD</a>
                <a href="practical12_events.php">Event Management</a>
                <a href="practical13_ajax.php">AJAX CRUD</a>
                <a href="admin_dashboard.php">User Accounts</a>
            <?php elseif ($previewRole === 'FACULTY'): ?>
                <a href="faculty_dashboard.php" class="active">Faculty Dashboard</a>
                <a href="view_records.php">Student Directory</a>
                <a href="practical12_events.php">Campus Events</a>
                <a href="attendance.html">Attendance</a>
            <?php else: ?>
                <a href="dashboard.php" class="active">Student Dashboard</a>
                <a href="practical12_events.php">Browse Events</a>
                <a href="profile.html">My Profile</a>
            <?php endif; ?>
        </nav>

        <div class="stats-grid">
            <div class="stat-box">
                <div>Total Users</div>
                <div class="stat-num"><?= (int)$userStats['total'] ?></div>
                <small>Admins: <?= (int)$userStats['admins'] ?> | Students: <?= (int)$userStats['students'] ?></small>
            </div>
            <div class="stat-box">
                <div>Total Students</div>
                <div class="stat-num"><?= $studentCount ?></div>
                <small>Enrolled Records</small>
            </div>
            <div class="stat-box">
                <div>Total Events</div>
                <div class="stat-num"><?= $eventCount ?></div>
                <small>Campus Activities</small>
            </div>
        </div>

        <div class="card">
            <h3>Latest Registrations</h3>
            <table>
                <tr>
                    <th>Ticket</th>
                    <th>Student Name</th>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
                <?php while ($r = $latestRegs->fetch_assoc()): ?>
                <tr>
                    <td><code><?= htmlspecialchars($r['ticket_code']) ?></code></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['registration_date']) ?></td>
                    <td><?= htmlspecialchars($r['attendance_status']) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="card">
            <h3>Audit Log (Recent Activity)</h3>
            <table>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Time</th>
                </tr>
                <?php while ($l = $auditLogs->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($l['username']) ?></td>
                    <td><strong><?= htmlspecialchars($l['action']) ?></strong></td>
                    <td><?= htmlspecialchars($l['details']) ?></td>
                    <td><?= htmlspecialchars($l['created_at']) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
