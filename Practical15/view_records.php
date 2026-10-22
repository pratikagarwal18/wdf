<?php
$dataDir = __DIR__ . '/data';
$jsonFile = $dataDir . '/submissions.json';
$csvFile = $dataDir . '/submissions.csv';

if (isset($_GET['download']) && $_GET['download'] === 'json' && file_exists($jsonFile)) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="submissions.json"');
    readfile($jsonFile);
    exit;
}
if (isset($_GET['download']) && $_GET['download'] === 'csv' && file_exists($csvFile)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="submissions.csv"');
    readfile($csvFile);
    exit;
}

$records = [];
if (file_exists($jsonFile)) {
    $decoded = json_decode(file_get_contents($jsonFile), true);
    if (is_array($decoded)) $records = $decoded;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Records - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 20px auto; padding: 0 16px; font-family: sans-serif; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.88rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; }
        .btn { padding: 6px 12px; background: #2563eb; color: #fff; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div style="background: #0f172a; padding: 12px 20px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
        <strong>StudentHub Records Viewer</strong>
        <div>
            <a href="index.html" style="color: #93c5fd; text-decoration: none; margin-right: 12px;">Home</a>
            <a href="register.html" style="color: #93c5fd; text-decoration: none;">Registration Form</a>
        </div>
    </div>

    <div class="container">
        <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>Total Submissions: <strong><?= count($records) ?></strong></div>
            <div>
                <a href="?download=json" class="btn">Download JSON</a>
                <a href="?download=csv" class="btn" style="background: #059669; margin-left: 6px;">Download CSV</a>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Stored Submissions</h3>
            <?php if (empty($records)): ?>
                <p style="color: #64748b;">No submissions stored yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Enrollment</th><th>Email</th><th>Phone</th><th>Department</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($r['fullName'] ?? $r['full_name'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($r['enrollment'] ?? $r['roll_no'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['department'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
