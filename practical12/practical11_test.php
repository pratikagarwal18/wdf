<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

if ((isset($_GET['ajax']) && $_GET['ajax'] == '1') || (isset($_GET['run']) && $_GET['run'] == 'suite')) {
    header('Content-Type: application/json');

    if (!$mysqli) {
        echo json_encode([
            'success' => false,
            'message' => 'MySQL connection failed. Ensure MySQL is running in XAMPP.',
            'test_cases' => []
        ]);
        exit;
    }

    ensureStudentsTableReady($mysqli);
    $results = [];

    $t1Start = microtime(true);
    $selectRes = fetchStudentsList($mysqli, ['status' => 'ACTIVE'], 1, 10, 'roll_no', 'ASC');
    $t1Time = round((microtime(true) - $t1Start) * 1000, 2);
    $t1Pass = ($selectRes['total'] >= 20 && count($selectRes['data']) <= 10 && $selectRes['total_pages'] >= 2);
    $results[] = [
        'case'        => 'Case 1: SELECT Operation & Pagination',
        'concept'     => 'Object-Oriented MySQLi Prepared SELECT with LIMIT & OFFSET',
        'scenario'    => 'Page 1, 10 records/page, status = ACTIVE',
        'expected'    => 'Fetch active records; total >= 20; paginated output with calculated pages',
        'actual'      => $t1Pass ? "Retrieved " . count($selectRes['data']) . " records (Total in DB: {$selectRes['total']}, Pages: {$selectRes['total_pages']}) in {$t1Time}ms" : "Unexpected count ({$selectRes['total']})",
        'status'      => $t1Pass ? 'PASS' : 'FAIL',
        'latency_ms'  => $t1Time
    ];

    $t2Roll = '99TST' . rand(1000, 9999);
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
    $t2Start = microtime(true);
    $insRes = saveStudentRecord($mysqli, $t2Data, null);
    $t2Time = round((microtime(true) - $t2Start) * 1000, 2);
    $newStudentId = $insRes['student_id'] ?? null;
    $t2Pass = ($insRes['success'] && $newStudentId > 0);
    $results[] = [
        'case'        => 'Case 2: INSERT Operation & Prepared Statement',
        'concept'     => '$mysqli->prepare("INSERT INTO students ...") with bound parameters',
        'scenario'    => "Roll: {$t2Roll} | Email: {$t2Email}",
        'expected'    => 'Student record inserted safely, return insert_id > 0, clear success message',
        'actual'      => $t2Pass ? "Inserted successfully with Student ID: {$newStudentId} in {$t2Time}ms. Message: {$insRes['message']}" : "Insert failed: " . ($insRes['message'] ?? 'error'),
        'status'      => $t2Pass ? 'PASS' : 'FAIL',
        'latency_ms'  => $t2Time
    ];

    $dupRes = saveStudentRecord($mysqli, $t2Data, null);
    $t3Pass = (!$dupRes['success'] && !empty($dupRes['errors']['roll_no']));
    $results[] = [
        'case'        => 'Case 3: Validation & Duplicate Collision Defense',
        'concept'     => 'Pre-insert query validation preventing database unique constraint exception',
        'scenario'    => "Attempt duplicate insertion of Roll: {$t2Roll}",
        'expected'    => 'Reject duplicate cleanly with descriptive validation error message',
        'actual'      => $t3Pass ? "Gracefully rejected: " . ($dupRes['errors']['roll_no'] ?? '') : "Duplicate check failed",
        'status'      => $t3Pass ? 'PASS' : 'FAIL'
    ];

    if ($newStudentId) {
        $updateData = $t2Data;
        $updateData['full_name'] = 'Automated Test Student (Updated)';
        $updateData['department'] = 'Information Technology';
        $updateData['course'] = 'B.Tech Information Technology';
        $updateData['semester'] = 6;

        $t4Start = microtime(true);
        $updRes = saveStudentRecord($mysqli, $updateData, $newStudentId);
        $t4Time = round((microtime(true) - $t4Start) * 1000, 2);

        $freshRow = getStudentRecord($mysqli, $newStudentId);
        $t4Pass = ($updRes['success'] && $freshRow && $freshRow['full_name'] === 'Automated Test Student (Updated)' && (int)$freshRow['semester'] === 6);

        $results[] = [
            'case'        => 'Case 4: UPDATE Operation & Persistence Check',
            'concept'     => '$mysqli->prepare("UPDATE students SET ... WHERE student_id = ?")',
            'scenario'    => "Update student ID #{$newStudentId} to Semester 6, IT department",
            'expected'    => 'Record modified, changes verified via fresh SELECT query',
            'actual'      => $t4Pass ? "Updated successfully in {$t4Time}ms. Verified: {$freshRow['full_name']} | Sem: {$freshRow['semester']}" : "Update verification failed",
            'status'      => $t4Pass ? 'PASS' : 'FAIL',
            'latency_ms'  => $t4Time
        ];
    }

    $searchKeywords = ['Aarav', 'patel', 'Cyber Security', '21BCE'];
    $searchMatches = true;
    foreach ($searchKeywords as $kw) {
        $sRes = fetchStudentsList($mysqli, ['search' => $kw, 'status' => 'ALL'], 1, 10);
        if ($sRes['total'] === 0) {
            $searchMatches = false;
            break;
        }
    }
    $results[] = [
        'case'        => 'Case 5: Multi-Field Search Query (Key Question 2)',
        'concept'     => 'SQL LIKE query matching full_name, email, roll_no, course, department',
        'scenario'    => 'Search terms: "Aarav", "patel", "Cyber Security", "21BCE"',
        'expected'    => 'Accurate parameterized pattern match returning relevant student records',
        'actual'      => $searchMatches ? "Search verified across Name, Email, Course, and Roll Number columns." : "Search missed expected results",
        'status'      => $searchMatches ? 'PASS' : 'FAIL'
    ];

    $filterRes = fetchStudentsList($mysqli, [
        'department' => 'Computer Engineering',
        'semester'   => 6,
        'status'     => 'ACTIVE'
    ], 1, 10);
    $allMatchFilter = true;
    foreach ($filterRes['data'] as $row) {
        if ($row['department'] !== 'Computer Engineering' || (int)$row['semester'] !== 6) {
            $allMatchFilter = false;
            break;
        }
    }
    $results[] = [
        'case'        => 'Case 6: Cascading Filters (Department + Semester)',
        'concept'     => 'Multi-clause parameterized WHERE department = ? AND semester = ?',
        'scenario'    => 'Filter: Department = "Computer Engineering" AND Semester = 6',
        'expected'    => 'All returned records strictly conform to both filter constraints',
        'actual'      => ($allMatchFilter && $filterRes['total'] > 0) ? "Filter verified: Found {$filterRes['total']} students meeting exact criteria." : "Filter mismatch",
        'status'      => ($allMatchFilter && $filterRes['total'] > 0) ? 'PASS' : 'FAIL'
    ];

    if ($newStudentId) {
        $delRes = deleteStudentRecord($mysqli, $newStudentId);
        $afterDel = getStudentRecord($mysqli, $newStudentId);
        $t7Pass = ($delRes['success'] && $afterDel === null);

        $results[] = [
            'case'        => 'Case 7: DELETE Operation & Database Purge',
            'concept'     => '$mysqli->prepare("DELETE FROM students WHERE student_id = ?")',
            'scenario'    => "Purge test student ID #{$newStudentId}",
            'expected'    => 'Row physically deleted, getStudentRecord() returns null',
            'actual'      => $t7Pass ? "Deleted successfully from MySQL database table." : "Delete failed",
            'status'      => $t7Pass ? 'PASS' : 'FAIL'
        ];
    }

    $maliciousInput = "admin' OR '1'='1";
    $sqliTest = fetchStudentsList($mysqli, ['search' => $maliciousInput, 'status' => 'ALL'], 1, 10);
    $t8Pass = ($sqliTest['total'] === 0);
    $results[] = [
        'case'        => 'Case 8: SQL Injection Defense & Query Parameterization',
        'concept'     => 'Prepared statement parameter separation defends against injection payloads',
        'scenario'    => "Search payload: \"{$maliciousInput}\"",
        'expected'    => 'Treated strictly as literal string; does not breach WHERE logic; returns 0 matches',
        'actual'      => $t8Pass ? "Injection payload safely treated as literal text. Total records leaked: 0." : "Potential query injection leak!",
        'status'      => $t8Pass ? 'PASS' : 'FAIL'
    ];

    echo json_encode([
        'success'    => true,
        'timestamp'  => date('Y-m-d H:i:s'),
        'total_pass' => count(array_filter($results, fn($r) => $r['status'] === 'PASS')),
        'total_fail' => count(array_filter($results, fn($r) => $r['status'] === 'FAIL')),
        'test_cases' => $results
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 11: Automated Test Suite &amp; Verification Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f8fafc;
            color: #0f172a;
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding-bottom: 60px;
        }

        .header-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #ffffff;
            padding: 32px 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-bottom: 3px solid #06b6d4;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-content h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            margin: 0 0 6px 0;
            font-weight: 700;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .card h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            margin: 0;
            color: #0f172a;
            background: none;
            padding: 0;
            text-align: left;
        }

        
        .deliverables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .deliv-card {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 18px;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .deliv-card:hover {
            border-color: #2563eb;
            background: #f0f7ff;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.1);
        }

        .deliv-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 8px;
            background: #e0e7ff;
            color: #3730a3;
        }

        .deliv-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .deliv-desc {
            font-size: 0.84rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 14px;
            flex-grow: 1;
        }

        .deliv-cta {
            font-size: 0.84rem;
            font-weight: 700;
            color: #2563eb;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-top: 14px;
        }

        .results-table th {
            background: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 12px 14px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }

        .results-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .badge-pass {
            background: #ecfdf5;
            color: #065f46;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #a7f3d0;
            display: inline-block;
            font-size: 0.75rem;
        }

        .badge-fail {
            background: #fef2f2;
            color: #991b1b;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #fecaca;
            display: inline-block;
            font-size: 0.75rem;
        }

        .btn-run {
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-run:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <header class="header-banner">
        <div class="header-content">
            <div>
                <span style="background: rgba(6, 182, 212, 0.2); color: #38bdf8; font-size: 0.78rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid rgba(56, 189, 248, 0.3);">
                    Laboratory Test Suite &amp; Verification
                </span>
                <h1>Practical 11: Student Management CRUD Verification</h1>
                <p style="margin: 4px 0 0; color: #94a3b8; font-size: 0.95rem;">
                    Automated validation of SELECT, INSERT, UPDATE, DELETE, Search, and Filtering.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="practical11_students.php" style="background: rgba(255,255,255,0.15); color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                    &larr; Open CRUD Portal
                </a>
                <button type="button" class="btn-run" onclick="runSuite();" id="runSuiteBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Run Automated Test Suite
                </button>
            </div>
        </div>
    </header>

    <div class="container">

        <div class="card">
            <div class="card-header-flex">
                <div>
                    <h2>Automated Regression &amp; CRUD Test Suite</h2>
                    <p style="margin: 4px 0 0; color: #64748b; font-size: 0.88rem;">
                        Executes prepared statements, collision testing, and CRUD cycles in real-time.
                    </p>
                </div>
                <div id="summaryBadge" style="display: none;">
                    <span id="passCountBadge" class="badge-pass" style="font-size: 0.88rem; padding: 6px 14px;">8 / 8 PASS</span>
                </div>
            </div>

            <div id="testTableContainer">
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <p>Click <strong>"Run Automated Test Suite"</strong> above to execute all 8 test scenarios against MySQLi.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Post-Laboratory Screenshot Deliverables</h2>
            <p style="color: #64748b; font-size: 0.9rem; margin-top: 4px;">
                As specified in the lab manual, capture the following 4 core interface states for submission:
            </p>

            <div class="deliverables-grid">
                <a href="practical11_students.php" target="_blank" class="deliv-card">
                    <div>
                        <span class="deliv-badge">Screenshot 1</span>
                        <div class="deliv-title">Student Records Table &amp; Search</div>
                        <div class="deliv-desc">
                            Display the 25 seeded records table with pagination, search bar, and KPI metric counters.
                        </div>
                    </div>
                    <div class="deliv-cta">Open View &rarr;</div>
                </a>

                <a href="practical11_students.php?action=test_add_trigger" onclick="alert('In the opened tab, click \'+ Add New Student\' button to reveal the modal and take screenshot.');" target="_blank" class="deliv-card">
                    <div>
                        <span class="deliv-badge">Screenshot 2</span>
                        <div class="deliv-title">Add Student Modal &amp; Insert</div>
                        <div class="deliv-desc">
                            Form modal with inputs for Roll No, Name, Email, Phone, Course, and Semester, followed by green success banner.
                        </div>
                    </div>
                    <div class="deliv-cta">Open Add Modal &rarr;</div>
                </a>

                <a href="practical11_students.php?search=21BCE1001" target="_blank" class="deliv-card">
                    <div>
                        <span class="deliv-badge">Screenshot 3</span>
                        <div class="deliv-title">Edit Student Modal &amp; Update</div>
                        <div class="deliv-desc">
                            Pre-populated edit modal modifying student details and resulting in the updated notification toast.
                        </div>
                    </div>
                    <div class="deliv-cta">Open Edit View &rarr;</div>
                </a>

                <a href="practical11_students.php" onclick="alert('In the opened tab, click any red trash icon to open the confirmation modal.');" target="_blank" class="deliv-card">
                    <div>
                        <span class="deliv-badge">Screenshot 4</span>
                        <div class="deliv-title">Delete Confirmation Dialog</div>
                        <div class="deliv-desc">
                            Confirmation modal showing student name and roll number before execution (Key Question 3).
                        </div>
                    </div>
                    <div class="deliv-cta">Launch Dialog &rarr;</div>
                </a>
            </div>
        </div>

    </div>

    <script>
        function runSuite() {
            const btn = document.getElementById('runSuiteBtn');
            const container = document.getElementById('testTableContainer');
            const summaryBadge = document.getElementById('summaryBadge');
            const passCountBadge = document.getElementById('passCountBadge');

            btn.disabled = true;
            btn.innerHTML = 'Running Test Suite...';
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #2563eb;">Executing test scenarios via MySQLi...</div>';

            fetch('practical11_test.php?ajax=1')
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Re-run Test Suite';

                    if (!data.success) {
                        container.innerHTML = `<div style="padding: 20px; color: #dc2626;">Error: ${data.message}</div>`;
                        return;
                    }

                    summaryBadge.style.display = 'block';
                    passCountBadge.textContent = `${data.total_pass} / ${data.test_cases.length} PASS`;
                    if (data.total_fail > 0) {
                        passCountBadge.className = 'badge-fail';
                    } else {
                        passCountBadge.className = 'badge-pass';
                    }

                    let tableHtml = `
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Test Case &amp; Concept</th>
                                    <th>Scenario &amp; Parameters</th>
                                    <th>Expected Outcome</th>
                                    <th>Actual Result</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.test_cases.forEach(tc => {
                        const isPass = tc.status === 'PASS';
                        tableHtml += `
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;">${tc.case}</div>
                                    <div style="font-size: 0.78rem; color: #64748b;">${tc.concept}</div>
                                </td>
                                <td><code>${tc.scenario}</code></td>
                                <td style="color: #334155;">${tc.expected}</td>
                                <td style="color: #0f172a;">${tc.actual}</td>
                                <td><span class="${isPass ? 'badge-pass' : 'badge-fail'}">${tc.status}</span></td>
                            </tr>
                        `;
                    });

                    tableHtml += `
                            </tbody>
                        </table>
                        <div style="margin-top: 14px; font-size: 0.8rem; color: #64748b; text-align: right;">
                            Test Execution Timestamp: ${data.timestamp} | MySQLi Object-Oriented Driver
                        </div>
                    `;

                    container.innerHTML = tableHtml;
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = 'Run Automated Test Suite';
                    container.innerHTML = `<div style="padding: 20px; color: #dc2626;">Execution failed: ${err.message}</div>`;
                });
        }

        window.addEventListener('DOMContentLoaded', runSuite);
    </script>
</body>
</html>
