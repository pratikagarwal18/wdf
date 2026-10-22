<?php


require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

function runPractical12Tests($mysqli) {
    if (!$mysqli) {
        return [
            'success' => false,
            'message' => 'MySQL database offline. Please start MySQL in XAMPP.',
            'total_pass' => 0,
            'total_fail' => 1,
            'test_cases' => []
        ];
    }

    ensureEventsTableReady($mysqli);
    $results = [];

    
    $listRes = fetchEventsList($mysqli, ['status' => 'UPCOMING'], 1, 10);
    $p1 = ($listRes['total'] > 0);
    $results[] = [
        'case'     => 'Case 1: SELECT Upcoming Events',
        'concept'  => 'Prepared SELECT with status filter',
        'expected' => 'Fetch upcoming events, return total count & pagination',
        'actual'   => $p1 ? "Retrieved {$listRes['total']} upcoming events" : 'Failed to query events',
        'status'   => $p1 ? 'PASS' : 'FAIL'
    ];

    
    $testCode = 'EVT-TST-' . rand(1000, 9999);
    $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_poster_');
    file_put_contents($tmpFile, $dummyPng);

    $simulatedFile = [
        'name'     => 'test_poster.png',
        'type'     => 'image/png',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($dummyPng)
    ];

    $insertRes = saveEventRecord($mysqli, [
        'event_code'   => $testCode,
        'title'        => 'Automated Test Event',
        'category'     => 'Technical',
        'event_date'   => date('Y-m-d H:i:s', time() + 86400 * 7),
        'venue'        => 'Test Lab Auditorium',
        'max_capacity' => 100,
        'description'  => 'Automated test suite event',
        'status'       => 'UPCOMING'
    ], $simulatedFile);

    $createdEventId = $insertRes['event_id'] ?? null;
    $createdRecord = $createdEventId ? getEventRecord($mysqli, $createdEventId) : null;
    $p2 = ($insertRes['success'] && $createdEventId > 0 && !empty($createdRecord['poster_image']));
    $results[] = [
        'case'     => 'Case 2: INSERT Event & Poster Upload',
        'concept'  => 'Validated file upload and database linking',
        'expected' => 'Validate image, copy to uploads/posters/, insert DB row',
        'actual'   => $p2 ? "Event ID: {$createdEventId} with Poster: {$createdRecord['poster_image']}" : 'Insert failed: ' . ($insertRes['message'] ?? ''),
        'status'   => $p2 ? 'PASS' : 'FAIL'
    ];

    
    $oversizedFile = [
        'name'     => 'large.png',
        'type'     => 'image/png',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 3 * 1024 * 1024
    ];
    $valOversize = validatePosterUpload($oversizedFile, false);
    $p3 = (!$valOversize['valid'] && strpos($valOversize['error'], '2MB limit') !== false);
    $results[] = [
        'case'     => 'Case 3: Reject Oversized Image (>2MB)',
        'concept'  => 'Server-side 2MB file size enforcement',
        'expected' => 'Reject file with clear 2MB limit error message',
        'actual'   => $p3 ? "Rejected safely: {$valOversize['error']}" : 'Failed to reject',
        'status'   => $p3 ? 'PASS' : 'FAIL'
    ];

    
    $fakeTmp = tempnam(sys_get_temp_dir(), 'fake_');
    file_put_contents($fakeTmp, '<?php echo "fake script"; ?>');
    $badMimeFile = [
        'name'     => 'avatar.png',
        'type'     => 'image/png',
        'tmp_name' => $fakeTmp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($fakeTmp)
    ];
    $valMime = validatePosterUpload($badMimeFile, false);
    $p4 = (!$valMime['valid']);
    $results[] = [
        'case'     => 'Case 4: Reject Disguised Script Payload',
        'concept'  => 'getimagesize() image structure verification',
        'expected' => 'Detect non-image content and abort upload',
        'actual'   => $p4 ? "Blocked: {$valMime['error']}" : 'Failed to block fake image',
        'status'   => $p4 ? 'PASS' : 'FAIL'
    ];
    @unlink($fakeTmp);

    
    $badExtFile = [
        'name'     => 'brochure.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 1024
    ];
    $valExt = validatePosterUpload($badExtFile, false);
    $p5 = (!$valExt['valid'] && strpos($valExt['error'], 'Invalid file extension') !== false);
    $results[] = [
        'case'     => 'Case 5: Reject Forbidden File Extension',
        'concept'  => 'Extension whitelist check (JPG, PNG, WEBP)',
        'expected' => 'Reject non-whitelisted extension',
        'actual'   => $p5 ? "Blocked: {$valExt['error']}" : 'Failed to block .pdf',
        'status'   => $p5 ? 'PASS' : 'FAIL'
    ];

    
    $dupRes = saveEventRecord($mysqli, [
        'event_code'   => $testCode,
        'title'        => 'Duplicate Event',
        'category'     => 'Technical',
        'event_date'   => date('Y-m-d H:i:s', time() + 86400 * 7),
        'venue'        => 'Test Venue',
        'max_capacity' => 50
    ]);
    $p6 = (!$dupRes['success'] && isset($dupRes['errors']['event_code']));
    $results[] = [
        'case'     => 'Case 6: Duplicate Event Code Collision',
        'concept'  => 'Unique key verification before database insert',
        'expected' => 'Flag duplicate event code gracefully',
        'actual'   => $p6 ? "Duplicate detected: {$dupRes['errors']['event_code']}" : 'Duplicate check failed',
        'status'   => $p6 ? 'PASS' : 'FAIL'
    ];

    
    if ($createdEventId) {
        $delRes = deleteEventRecord($mysqli, $createdEventId);
        $deleted = getEventRecord($mysqli, $createdEventId);
        $p7 = ($delRes['success'] && $deleted === null);
        $results[] = [
            'case'     => 'Case 7: DELETE Event Record',
            'concept'  => 'DELETE FROM events WHERE event_id = ?',
            'expected' => 'Event record purged from database',
            'actual'   => $p7 ? 'Event record successfully deleted' : 'Delete failed',
            'status'   => $p7 ? 'PASS' : 'FAIL'
        ];
    }

    @unlink($tmpFile);

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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(runPractical12Tests($mysqli), JSON_PRETTY_PRINT);
    exit;
}


$report = runPractical12Tests($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 12: Event Management Test Suite</title>
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
            <a href="practical12_events.php">Practical 12 Events CRUD &rarr;</a> |
            <a href="practical11_students.php">Practical 11 Students</a>
        </nav>
    </div>

    <div class="container">
        <div class="card">
            <h2 style="margin-top: 0; text-align: center;">Practical 12: Automated Test Suite &amp; Report</h2>
            <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
                Verification of Event CRUD Operations, File Upload Validation (JPG, PNG, WEBP &le; 2MB), and Disguised Script Protection.
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
                <a href="practical12_events.php" style="display: inline-block; background: var(--blue); color: #fff; padding: 10px 22px; border-radius: 999px; text-decoration: none; font-weight: bold;">
                    Open Event Management Portal (practical12_events.php) &rarr;
                </a>
            </div>
        </div>
    </div>

</body>
</html>
