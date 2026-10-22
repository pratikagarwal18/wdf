<?php
/**
 * Practical 10: Faculty Academic Dashboard
 * Demonstrates Role-Based Access Control (RBAC).
 * Only accessible by accounts with role in ['FACULTY', 'ADMIN'].
 */

require_once __DIR__ . '/auth.php';

requireLogin();
requireRole(['FACULTY', 'ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);

// Fetch enrolled students
$students = [];
if ($mysqli) {
    $res = $mysqli->query("SELECT student_id, roll_no, full_name, email, department, semester, phone FROM students ORDER BY roll_no ASC LIMIT 10");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $students[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | Practical 10 - StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Inter', sans-serif;
            margin: 0;
            color: #1e293b;
        }
        .fac-nav {
            background: #1e1b4b;
            color: #ffffff;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 20px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .data-table th {
            background: #f1f5f9;
            color: #475569;
        }
    </style>
</head>
<body>

    <header class="fac-nav">
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700;">
            &#128104;&#8205;&#127979; StudentHub &bull; Faculty Portal
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 999px; font-size: 0.82rem;">
                <?= htmlspecialchars($user['full_name']) ?> (FACULTY)
            </span>
            <a href="logout.php" style="background: #ef4444; color: #fff; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Log Out</a>
        </div>
    </header>

    <div class="container">
        <div class="card" style="background: linear-gradient(135deg, #312e81 0%, #4338ca 100%); color: #fff;">
            <h2 style="font-family: 'Outfit', sans-serif; margin: 0 0 8px 0;">Welcome, Prof. <?= htmlspecialchars($user['full_name']) ?></h2>
            <p style="margin: 0; opacity: 0.9;">Faculty &amp; Department Management Portal &bull; Department of <?= htmlspecialchars($user['department']) ?></p>
        </div>

        <div class="card">
            <h3 style="font-family: 'Outfit', sans-serif; margin-top: 0;">Department Student Roster</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th>Institutional Email</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Mobile</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['roll_no']) ?></strong></td>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= htmlspecialchars($s['department']) ?></td>
                            <td>Semester <?= htmlspecialchars($s['semester']) ?></td>
                            <td><?= htmlspecialchars($s['phone']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
