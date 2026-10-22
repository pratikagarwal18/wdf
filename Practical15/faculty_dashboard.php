<?php
require_once __DIR__ . '/auth.php';

requireLogin();
requireRole(['FACULTY', 'ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);

$students = [];
if ($mysqli) {
    $res = $mysqli->query("SELECT roll_no, full_name, email, department, semester, phone FROM students ORDER BY roll_no ASC LIMIT 10");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $students[] = $r; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 20px auto; padding: 0 16px; font-family: sans-serif; }
        .nav-bar { background: #1e1b4b; color: #fff; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.88rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div class="container">
        <div class="nav-bar">
            <div><strong>StudentHub</strong> &bull; Prof. <?= htmlspecialchars($user['full_name']) ?> (FACULTY)</div>
            <div>
                <a href="dashboard.php" style="color: #93c5fd; text-decoration: none; margin-right: 12px;">Student View</a>
                <a href="logout.php" style="color: #fca5a5; text-decoration: none;">Log Out</a>
            </div>
        </div>

        <div class="card" style="background: #312e81; color: #fff;">
            <h2 style="margin: 0 0 6px 0;">Welcome, Prof. <?= htmlspecialchars($user['full_name']) ?></h2>
            <div style="font-size: 0.9rem; opacity: 0.9;">Department: <?= htmlspecialchars($user['department']) ?></div>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Department Student Roster</h3>
            <table>
                <thead>
                    <tr><th>Roll No</th><th>Name</th><th>Email</th><th>Department</th><th>Sem</th><th>Phone</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="6" style="text-align:center;">No students found</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['roll_no']) ?></strong></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['department']) ?></td>
                                <td><?= htmlspecialchars($s['semester']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
