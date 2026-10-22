<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$mysqli = getMySQLiConnection(true);
if ($mysqli) {
    ensureAuditLogsTableReady($mysqli);
}

function runTests() {
    $mysqli = getMySQLiConnection(true);
    $results = [];
    $baseUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI'] ?? '/');
    $baseUrl = rtrim($baseUrl, '/');

    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]);
    $headers = @get_headers("{$baseUrl}/practical14_dashboard.php", 1);
    $statusLine = $headers ? $headers[0] : '';
    $blocked = (strpos($statusLine, '302') !== false || strpos($statusLine, '403') !== false);
    $results[] = [
        'case'   => 'Case 1: RBAC Access Guard',
        'desc'   => 'Block unauthenticated users from admin dashboard',
        'status' => $blocked ? 'PASS' : 'FAIL'
    ];

    $html = @file_get_contents("{$baseUrl}/practical14_dashboard.php?login_as=ADMIN");
    $hasTitle = (strpos($html, 'Admin Dashboard') !== false);
    $results[] = [
        'case'   => 'Case 2: Admin Access Granted',
        'desc'   => 'Admin authorized and dashboard rendered successfully',
        'status' => $hasTitle ? 'PASS' : 'FAIL'
    ];

    $userCnt = (int)($mysqli->query("SELECT COUNT(*) FROM users")->fetch_row()[0] ?? 0);
    $results[] = [
        'case'   => 'Case 3: User Statistics Query',
        'desc'   => "Found {$userCnt} registered users in database",
        'status' => ($userCnt > 0) ? 'PASS' : 'FAIL'
    ];

    $studCnt = (int)($mysqli->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0);
    $results[] = [
        'case'   => 'Case 4: Student Count Query',
        'desc'   => "Found {$studCnt} student records in database",
        'status' => ($studCnt >= 20) ? 'PASS' : 'FAIL'
    ];

    $evtCnt = (int)($mysqli->query("SELECT COUNT(*) FROM events")->fetch_row()[0] ?? 0);
    $results[] = [
        'case'   => 'Case 5: Event Count Query',
        'desc'   => "Found {$evtCnt} campus events in database",
        'status' => ($evtCnt >= 5) ? 'PASS' : 'FAIL'
    ];

    $regRes = $mysqli->query("SELECT r.ticket_code, s.full_name, e.title FROM registrations r JOIN students s ON r.student_id=s.student_id JOIN events e ON r.event_id=e.event_id LIMIT 1");
    $hasRegs = ($regRes && $regRes->num_rows > 0);
    $results[] = [
        'case'   => 'Case 6: Latest Registrations',
        'desc'   => 'Query registrations joined with student and event names',
        'status' => $hasRegs ? 'PASS' : 'FAIL'
    ];

    $adminHtml = @file_get_contents("{$baseUrl}/practical14_dashboard.php?login_as=ADMIN&menu_role=ADMIN");
    $facultyHtml = @file_get_contents("{$baseUrl}/practical14_dashboard.php?login_as=ADMIN&menu_role=FACULTY");
    $hasDiffMenu = (strpos($adminHtml, 'Students CRUD') !== false && strpos($facultyHtml, 'Student Directory') !== false);
    $results[] = [
        'case'   => 'Case 7: Dynamic Role-Based Menu',
        'desc'   => 'Navigation menu options dynamically adapt based on role',
        'status' => $hasDiffMenu ? 'PASS' : 'FAIL'
    ];

    $testAction = 'LOG_' . rand(100, 999);
    $mysqli->query("INSERT INTO audit_logs (username, action, details) VALUES ('admin.portal', '{$testAction}', 'Test log entry')");
    $verifyLog = $mysqli->query("SELECT log_id FROM audit_logs WHERE action='{$testAction}'");
    $results[] = [
        'case'   => 'Case 8: Security Audit Log',
        'desc'   => 'Insert and retrieve audit log records from database',
        'status' => ($verifyLog && $verifyLog->num_rows > 0) ? 'PASS' : 'FAIL'
    ];

    return $results;
}

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    $tests = runTests();
    $pass = 0;
    foreach ($tests as $t) if ($t['status'] === 'PASS') $pass++;
    echo json_encode(['total_pass' => $pass, 'total_tests' => count($tests), 'results' => $tests]);
    exit;
}

$tests = runTests();
$pass = 0;
foreach ($tests as $t) if ($t['status'] === 'PASS') $pass++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Practical 14 Test</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="font-family: sans-serif; background: #f8fafc; padding: 20px;">
    <div style="max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border: 1px solid #cbd5e1; border-radius: 8px;">
        <h2>Practical 14 Verification Report</h2>
        <p><strong>Result: <?= $pass ?> / <?= count($tests) ?> Tests Passed</strong></p>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="background: #f1f5f9;">
                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Test Case</th>
                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Description</th>
                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Status</th>
            </tr>
            <?php foreach ($tests as $t): ?>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong><?= htmlspecialchars($t['case']) ?></strong></td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($t['desc']) ?></td>
                <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; color: <?= $t['status'] === 'PASS' ? 'green' : 'red' ?>; font-weight: bold;"><?= $t['status'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top: 16px;">
            <a href="practical14_dashboard.php?login_as=ADMIN">&larr; Open Admin Dashboard</a>
        </p>
    </div>
</body>
</html>
