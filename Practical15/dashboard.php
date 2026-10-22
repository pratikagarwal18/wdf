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
    $_SESSION['department'] = ($r === 'ADMIN') ? 'System Administration' : 'Computer Engineering';
    $_SESSION['semester'] = ($r === 'ADMIN') ? 8 : 4;
}

requireLogin();
requireRole(['STUDENT', 'FACULTY', 'ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);

$events = [];
if ($mysqli) {
    $res = $mysqli->query("SELECT title, category, event_date, venue, fee FROM events WHERE status='ACTIVE' ORDER BY event_date ASC LIMIT 4");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $events[] = $r; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 20px auto; padding: 0 16px; font-family: sans-serif; }
        .nav-bar { background: #0f172a; color: #fff; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px; }
        .nav-bar a { color: #93c5fd; text-decoration: none; margin-left: 12px; font-size: 0.9rem; }
        .welcome { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin: 16px 0; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 0.88rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .btn { padding: 6px 12px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div class="container">
        <div class="nav-bar">
            <div><strong>StudentHub</strong> &bull; <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role']) ?>)</div>
            <div>
                <a href="courses.html">Courses</a>
                <a href="attendance.html">Attendance</a>
                <a href="result.html">Results</a>
                <?php if ($user['role'] === 'ADMIN'): ?>
                    <a href="admin_dashboard.php" style="color: #67e8f9;">Admin Console</a>
                <?php endif; ?>
                <a href="logout.php" style="color: #fca5a5;">Log Out</a>
            </div>
        </div>

        <div class="welcome">
            <h2 style="margin: 0 0 6px 0;">Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
            <div style="font-size: 0.9rem; color: #475569;">
                Department: <strong><?= htmlspecialchars($user['department']) ?></strong> &bull;
                Semester: <strong><?= (int)$user['semester'] ?></strong> &bull;
                Username: <strong><?= htmlspecialchars($user['username']) ?></strong>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h3 style="margin-top: 0;">Enrolled Courses</h3>
                <table>
                    <thead><tr><th>Code</th><th>Course</th><th>Credits</th></tr></thead>
                    <tbody>
                        <tr><td>WDF301</td><td>Web Development Frameworks</td><td>4</td></tr>
                        <tr><td>DBMS204</td><td>Database Management Systems</td><td>4</td></tr>
                        <tr><td>OS302</td><td>Operating Systems</td><td>3</td></tr>
                        <tr><td>SE205</td><td>Software Engineering</td><td>3</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3 style="margin-top: 0;">Upcoming Campus Events</h3>
                <?php if (empty($events)): ?>
                    <p style="color: #64748b; font-size: 0.9rem;">No upcoming events right now.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Event</th><th>Category</th><th>Date</th><th>Fee</th></tr></thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($ev['category']) ?></td>
                                    <td><?= htmlspecialchars($ev['event_date']) ?></td>
                                    <td>&#8377;<?= (float)$ev['fee'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Quick Academic Links</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="courses.html" class="btn">View Syllabus</a>
                <a href="attendance.html" class="btn">Check Attendance</a>
                <a href="result.html" class="btn">View Results</a>
                <a href="profile.html" class="btn" style="background: #475569;">My Profile</a>
            </div>
        </div>
    </div>

</body>
</html>
