<?php


$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI'] ?? '/');
$apiUrl = rtrim($baseUrl, '/') . '/practical13_api.php';

function runApiTests($apiUrl) {
    $results = [];

    
    function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $json = json_decode($body, true);

        return [
            'code'         => $httpCode,
            'content_type' => $contentType,
            'json'         => $json,
            'body'         => $body
        ];
    }

    
    $res1 = makeRequest("{$apiUrl}?action=list");
    $isJson = ($res1['json'] !== null);
    $hasHeader = (stripos($res1['content_type'], 'application/json') !== false);
    $p1 = ($isJson && $hasHeader && $res1['code'] === 200 && ($res1['json']['success'] ?? false) === true);
    $results[] = [
        'case'     => 'Case 1: JSON Response & Content-Type Header',
        'concept'  => 'header("Content-Type: application/json") & json_encode()',
        'expected' => 'HTTP 200, Content-Type: application/json, valid JSON object',
        'actual'   => $p1 ? "Valid JSON received with application/json header (Count: {$res1['json']['count']})" : 'Invalid response or header',
        'status'   => $p1 ? 'PASS' : 'FAIL'
    ];

    
    $rand = rand(1000, 9999);
    $newRoll = "24API{$rand}";
    $createPayload = [
        'action'     => 'create',
        'roll_no'    => $newRoll,
        'full_name'  => "API Student {$rand}",
        'email'      => "api.student{$rand}@studenthub.edu",
        'phone'      => '9876543210',
        'department' => 'Information Technology',
        'semester'   => 4,
        'status'     => 'ACTIVE'
    ];
    $res2 = makeRequest($apiUrl, 'POST', $createPayload);
    $createdId = $res2['json']['student_id'] ?? null;
    $p2 = ($res2['code'] === 201 && ($res2['json']['success'] ?? false) === true && $createdId > 0);
    $results[] = [
        'case'     => 'Case 2: POST / Create Student Asynchronously',
        'concept'  => 'Asynchronous POST request processing & database insertion',
        'expected' => 'HTTP 201 Created, success: true, valid student_id',
        'actual'   => $p2 ? "Created successfully with Student ID: #{$createdId}" : 'Create failed: ' . ($res2['json']['message'] ?? ''),
        'status'   => $p2 ? 'PASS' : 'FAIL'
    ];

    
    $p3 = false;
    if ($createdId) {
        $res3 = makeRequest("{$apiUrl}?action=get&id={$createdId}");
        $p3 = ($res3['code'] === 200 && ($res3['json']['data']['roll_no'] ?? '') === $newRoll);
    }
    $results[] = [
        'case'     => 'Case 3: GET / Fetch Single Record by ID',
        'concept'  => 'GET request with query parameter to populate form',
        'expected' => 'HTTP 200, returns student record object matching requested ID',
        'actual'   => $p3 ? "Retrieved student: {$res3['json']['data']['full_name']} ({$newRoll})" : 'Fetch record failed',
        'status'   => $p3 ? 'PASS' : 'FAIL'
    ];

    
    $badPayload = [
        'action'    => 'create',
        'roll_no'   => '',
        'full_name' => '',
        'email'     => 'not-an-email'
    ];
    $res4 = makeRequest($apiUrl, 'POST', $badPayload);
    $p4 = ($res4['code'] === 400 && ($res4['json']['success'] ?? false) === false && isset($res4['json']['errors']));
    $results[] = [
        'case'     => 'Case 4: Input Validation Enforcement',
        'concept'  => 'Server-side validation before any database execution',
        'expected' => 'HTTP 400 Bad Request, success: false, validation errors object',
        'actual'   => $p4 ? "Gracefully rejected: " . implode(', ', $res4['json']['errors'] ?? []) : 'Failed to reject invalid input',
        'status'   => $p4 ? 'PASS' : 'FAIL'
    ];

    
    $p5 = false;
    if ($createdId) {
        $updatePayload = [
            'action'     => 'update',
            'student_id' => $createdId,
            'full_name'  => "API Student {$rand} (Updated)",
            'semester'   => 5
        ];
        $res5 = makeRequest($apiUrl, 'POST', $updatePayload);
        $p5 = ($res5['code'] === 200 && ($res5['json']['success'] ?? false) === true);
    }
    $results[] = [
        'case'     => 'Case 5: POST / Update Student Record',
        'concept'  => 'Asynchronous update without full page refresh',
        'expected' => 'HTTP 200 OK, success: true, confirmation message',
        'actual'   => $p5 ? "Updated record #{$createdId} successfully" : 'Update failed',
        'status'   => $p5 ? 'PASS' : 'FAIL'
    ];

    
    $p6 = false;
    if ($createdId) {
        $delPayload = ['action' => 'delete', 'student_id' => $createdId];
        $res6 = makeRequest($apiUrl, 'POST', $delPayload);
        $p6 = ($res6['code'] === 200 && ($res6['json']['success'] ?? false) === true);
    }
    $results[] = [
        'case'     => 'Case 6: POST / Delete Student Record',
        'concept'  => 'Asynchronous delete and dynamic DOM row removal',
        'expected' => 'HTTP 200 OK, record purged from MySQL',
        'actual'   => $p6 ? "Deleted student record #{$createdId}" : 'Delete failed',
        'status'   => $p6 ? 'PASS' : 'FAIL'
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


if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(runApiTests($apiUrl), JSON_PRETTY_PRINT);
    exit;
}

$report = runApiTests($apiUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 13: JSON API Test Suite</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 960px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; padding: 24px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); }
        .summary-bar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
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
            <a href="practical13_ajax.php" style="color:var(--blue); font-weight:bold;">Practical 13 AJAX CRUD Demo &rarr;</a> |
            <a href="practical11_students.php">Practical 11 Students</a>
        </nav>
    </div>

    <div class="container">
        <div class="card">
            <h2 style="margin-top: 0; text-align: center;">Practical 13: Automated API Test Suite &amp; Report</h2>
            <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
                Verifies JSON response formatting, HTTP status codes (200, 201, 400), asynchronous CRUD operations, and input validation.
            </p>

            <div class="summary-bar">
                <span class="badge badge-pass">&#10003; Tests Passed: <?= $report['total_pass'] ?></span>
                <?php if ($report['total_fail'] > 0): ?>
                    <span class="badge badge-fail">&#9888; Tests Failed: <?= $report['total_fail'] ?></span>
                <?php endif; ?>
                <button onclick="location.reload();" style="margin-left: auto; padding: 6px 16px; font-size: 0.85rem;">
                    &#8635; Re-run API Tests
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
                <a href="practical13_ajax.php" style="display: inline-block; background: var(--blue); color: #fff; padding: 10px 22px; border-radius: 999px; text-decoration: none; font-weight: bold;">
                    Open AJAX / Fetch API CRUD Demo (practical13_ajax.php) &rarr;
                </a>
            </div>
        </div>
    </div>

</body>
</html>
