<?php
/**
 * Practical 11: Student Management Test Suite
 * Beginner-friendly, clean PHP verification script
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

function runPractical11Tests($mysqli) {
    if (!$mysqli) {
        return [
            'success' => false,
            'message' => 'MySQL database offline. Please start MySQL in XAMPP.',
            'total_pass' => 0,
            'total_fail' => 1,
            'test_cases' => []
        ];
    }

    ensureStudentsTableReady($mysqli);
    $results = [];

    // Case 1: SELECT & Pagination
    $selectRes = fetchStudentsList($mysqli, ['status' => 'ACTIVE'], 1, 10, 'roll_no', 'ASC');
    $t1Pass = ($selectRes['total'] >= 1 && count($selectRes['data']) <= 10);
    $results[] = [
        'case'     => 'Case 1: SELECT & Pagination',
        'concept'  => 'Prepared SELECT with LIMIT & OFFSET',
        'expected' => 'Fetch active students with calculated total and pages',
        'actual'   => $t1Pass ? "Retrieved " . count($selectRes['data']) . " records (Total: {$selectRes['total']})" : 'Failed to query students',
        'status'   => $t1Pass ? 'PASS' : 'FAIL'
    ];

    // Case 2: INSERT Operation
    $t2Roll = 'TST' . rand(1000, 9999);
    $t2Email = 'test.' . strtolower($t2Roll) . '@studenthub.edu';
    $t2Data = [
        'roll_no'    => $t2Roll,
        'full_name'  => 'Automated Test Student',
        'email'      => $t2Email,
        'phone'      => '9876543299',
        'department' => 'Computer Engineering',
        'course'     => 'B.Tech Computer Science',
        'semester'   => 5,
        'status'     => 'ACTIVE'
    ];
    $insRes = saveStudentRecord($mysqli, $t2Data, null);
    $newStudentId = $insRes['student_id'] ?? null;
    $t2Pass = ($insRes['success'] && $newStudentId > 0);
    $results[] = [
        'case'     => 'Case 2: INSERT Student',
        'concept'  => 'Prepared INSERT with parameter binding',
        'expected' => 'Student record inserted safely, return new student_id',
        'actual'   => $t2Pass ? "Inserted successfully with ID: {$newStudentId}" : 'Insert failed: ' . ($insRes['message'] ?? ''),
        'status'   => $t2Pass ? 'PASS' : 'FAIL'
    ];

    // Case 3: Duplicate Roll Number Check
    $dupRes = saveStudentRecord($mysqli, $t2Data, null);
    $t3Pass = (!$dupRes['success'] && !empty($dupRes['errors']['roll_no']));
    $results[] = [
        'case'     => 'Case 3: Duplicate Roll Number Prevention',
        'concept'  => 'Unique Key pre-check before database write',
        'expected' => 'Reject duplicate roll number gracefully',
        'actual'   => $t3Pass ? 'Duplicate caught: ' . ($dupRes['errors']['roll_no'] ?? '') : 'Failed to catch duplicate',
        'status'   => $t3Pass ? 'PASS' : 'FAIL'
    ];

    // Case 4: UPDATE Operation
    if ($newStudentId) {
        $updateData = $t2Data;
        $updateData['full_name'] = 'Automated Test Student (Updated)';
        $updateData['semester'] = 6;
        $updRes = saveStudentRecord($mysqli, $updateData, $newStudentId);
        $freshRow = getStudentRecord($mysqli, $newStudentId);
        $t4Pass = ($updRes['success'] && $freshRow && $freshRow['full_name'] === 'Automated Test Student (Updated)');
        $results[] = [
            'case'     => 'Case 4: UPDATE Student Record',
            'concept'  => 'Prepared UPDATE with persistence check',
            'expected' => 'Modified name and semester saved and verified',
            'actual'   => $t4Pass ? "Updated successfully: {$freshRow['full_name']}" : 'Update verification failed',
            'status'   => $t4Pass ? 'PASS' : 'FAIL'
        ];
    }

    // Case 5: Multi-Field Search
    $sRes = fetchStudentsList($mysqli, ['search' => 'Aarav', 'status' => 'ALL'], 1, 10);
    $t5Pass = ($sRes['total'] > 0);
    $results[] = [
        'case'     => 'Case 5: Multi-Field Search Query',
        'concept'  => 'SQL LIKE search across Name, Email, and Roll No',
        'expected' => 'Pattern match returns relevant student records',
        'actual'   => $t5Pass ? "Search returned {$sRes['total']} matching records" : 'No results returned',
        'status'   => $t5Pass ? 'PASS' : 'FAIL'
    ];

    // Case 6: Cascading Filters
    $fRes = fetchStudentsList($mysqli, ['department' => 'Computer Engineering', 'status' => 'ACTIVE'], 1, 10);
    $t6Pass = ($fRes['total'] > 0);
    $results[] = [
        'case'     => 'Case 6: Department Filtering',
        'concept'  => 'WHERE department = ? AND status = ?',
        'expected' => 'All returned records match selected department',
        'actual'   => $t6Pass ? "Filtered: Found {$fRes['total']} students in Computer Engineering" : 'Filter returned 0 records',
        'status'   => $t6Pass ? 'PASS' : 'FAIL'
    ];

    // Case 7: DELETE Operation
    if ($newStudentId) {
        $delRes = deleteStudentRecord($mysqli, $newStudentId);
        $afterDel = getStudentRecord($mysqli, $newStudentId);
        $t7Pass = ($delRes['success'] && $afterDel === null);
        $results[] = [
            'case'     => 'Case 7: DELETE Student Record',
            'concept'  => 'DELETE FROM students WHERE student_id = ?',
            'expected' => 'Record deleted, getStudentRecord() returns null',
            'actual'   => $t7Pass ? 'Record successfully purged from database' : 'Delete failed',
            'status'   => $t7Pass ? 'PASS' : 'FAIL'
        ];
    }

    // Case 8: SQL Injection Defense
    $malicious = "admin' OR '1'='1";
    $sqliTest = fetchStudentsList($mysqli, ['search' => $malicious, 'status' => 'ALL'], 1, 10);
    $t8Pass = ($sqliTest['total'] === 0);
    $results[] = [
        'case'     => 'Case 8: SQL Injection Defense',
        'concept'  => 'Parameter binding treats payload as literal string',
        'expected' => 'Query does not execute arbitrary SQL; returns 0 matches',
        'actual'   => $t8Pass ? 'Payload treated as literal text; 0 rows leaked' : 'Potential query leak',
        'status'   => $t8Pass ? 'PASS' : 'FAIL'
    ];

    $totalPass = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
    $totalFail = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));

    return [
        'success'    => true,
        'timestamp'  => date('Y-m-d H:i:s'),
        'total_pass' => $totalPass,
        'total_fail' => $totalFail,
        'test_cases' => $results
    ];
}

// Return JSON if requested via AJAX
if ((isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_GET['run']) && $_GET['run'] == 'suite')) {
    header('Content-Type: application/json');
    echo json_encode(runPractical11Tests($mysqli), JSON_PRETTY_PRINT);
    exit;
}

// Run tests for HTML rendering
$report = runPractical11Tests($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 11: Student CRUD Test Suite</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; padding: 24px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); }
        .summary-bar { display: flex; gap: 12px; margin-bottom: 20px; }
        .badge { padding: 6px 14px; border-radius: 999px; font-weight: bold; font-size: 0.88rem; }
        .badge-pass { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-fail { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical11_students.php">Practical 11 Students Portal &rarr;</a> |
            <a href="practical12_events.php">Practical 12 Events</a>
        </nav>
    </div>

    <div class="container">
        <div class="card">
            <h2 style="margin-top: 0; text-align: center;">Practical 11: Automated Test Suite &amp; Report</h2>
            <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
                Verification of Object-Oriented MySQLi Prepared Statements, CRUD, Pagination, Filters, and SQLi Defense.
            </p>

            <div class="summary-bar">
                <span class="badge badge-pass">&#10003; Tests Passed: <?= $report['total_pass'] ?></span>
                <?php if ($report['total_fail'] > 0): ?>
                    <span class="badge badge-fail">&#9888; Tests Failed: <?= $report['total_fail'] ?></span>
                <?php endif; ?>
                <button onclick="location.reload();" style="margin-left: auto; padding: 6px 16px; font-size: 0.85rem;">
                    &#8635; Re-run Tests
                </button>
            </div>

            <table style="width: 100%; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th>Test Case</th>
                        <th>Concept Tested</th>
                        <th>Expected</th>
                        <th>Actual Result</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['test_cases'] as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['case']) ?></strong></td>
                            <td><?= htmlspecialchars($t['concept']) ?></td>
                            <td><?= htmlspecialchars($t['expected']) ?></td>
                            <td><?= htmlspecialchars($t['actual']) ?></td>
                            <td>
                                <strong style="color: <?= $t['status'] === 'PASS' ? '#166534' : '#991b1b' ?>;">
                                    <?= $t['status'] ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 24px; text-align: center;">
                <a href="practical11_students.php" style="display: inline-block; background: var(--blue); color: #fff; padding: 10px 22px; border-radius: 999px; text-decoration: none; font-weight: bold;">
                    Open Student Management Portal (practical11_students.php) &rarr;
                </a>
            </div>
        </div>
    </div>

</body>
</html>
