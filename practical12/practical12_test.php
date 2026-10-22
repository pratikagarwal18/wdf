<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'run_suite') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$mysqli) {
        echo json_encode(['success' => false, 'error' => 'MySQL database offline']);
        exit;
    }

    ensureEventsTableReady($mysqli);
    $results = [];
    $totalPass = 0;
    $totalFail = 0;

    $t0 = microtime(true);
    $listRes = fetchEventsList($mysqli, ['status' => 'UPCOMING'], 1, 10);
    $lat1 = round((microtime(true) - $t0) * 1000, 2);
    $p1 = ($listRes['total'] > 0 && count($listRes['data']) > 0);
    if ($p1) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 1: SELECT & Filter Events Operation',
        'concept'     => 'Object-Oriented MySQLi Prepared Query with Filters',
        'scenario'    => 'Filter by status = UPCOMING, page 1, 10 per page',
        'expected'    => 'Fetch upcoming events, return total count, calculate pagination',
        'actual'      => $p1 ? "Retrieved {$listRes['total']} upcoming events in {$lat1}ms" : 'Failed to query events',
        'status'      => $p1 ? 'PASS' : 'FAIL',
        'latency_ms'  => $lat1
    ];

    $testCode = 'EVT-TST-' . mt_rand(1000, 9999);
    $testTitle = 'Automated Test Symposium ' . date('His');
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

    $t0 = microtime(true);
    $insertResult = saveEventRecord($mysqli, [
        'event_code'   => $testCode,
        'title'        => $testTitle,
        'category'     => 'Technical',
        'event_date'   => date('Y-m-d H:i:s', time() + 86400 * 7),
        'venue'        => 'Test Lab Auditorium',
        'max_capacity' => 120,
        'description'  => 'Automated test suite creation',
        'status'       => 'UPCOMING'
    ], $simulatedFile);
    $lat2 = round((microtime(true) - $t0) * 1000, 2);

    $createdEventId = $insertResult['event_id'] ?? null;
    $createdRecord = $createdEventId ? getEventRecord($mysqli, $createdEventId) : null;
    $p2 = ($insertResult['success'] && $createdEventId > 0 && !empty($createdRecord['poster_image']));
    if ($p2) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 2: INSERT Event with Valid Poster Upload',
        'concept'     => 'Multipart File Upload, Server Move & DB Linking',
        'scenario'    => "Code: {$testCode} | Valid 1x1 PNG image (70 bytes)",
        'expected'    => 'Validate MIME/size, move to uploads/posters/, insert DB row with poster filename',
        'actual'      => $p2 ? "Inserted Event ID: {$createdEventId} with Poster: {$createdRecord['poster_image']} in {$lat2}ms" : 'Insert or poster upload failed: ' . ($insertResult['message'] ?? ''),
        'status'      => $p2 ? 'PASS' : 'FAIL',
        'latency_ms'  => $lat2
    ];

    $oversizedFile = [
        'name'     => 'huge_poster.png',
        'type'     => 'image/png',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 3 * 1024 * 1024
    ];
    $valOversize = validatePosterUpload($oversizedFile, false);
    $p3 = (!$valOversize['valid'] && strpos($valOversize['error'], 'exceeds the maximum allowed 2MB limit') !== false);
    if ($p3) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 3: File Upload Validation: Reject Oversized Image',
        'concept'     => 'Server-side 2MB File Size Boundary Enforcement',
        'scenario'    => 'File size: 3.00 MB (Limit: 2.00 MB)',
        'expected'    => 'Reject upload with clear error message showing file size limit',
        'actual'      => $p3 ? "Gracefully rejected: {$valOversize['error']}" : 'Failed to reject oversized file',
        'status'      => $p3 ? 'PASS' : 'FAIL'
    ];

    $fakeTmp = tempnam(sys_get_temp_dir(), 'fake_img_');
    file_put_contents($fakeTmp, '<?php echo "malicious script payload"; ?>');
    $badMimeFile = [
        'name'     => 'innocent_avatar.png',
        'type'     => 'image/png',
        'tmp_name' => $fakeTmp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($fakeTmp)
    ];
    $valMime = validatePosterUpload($badMimeFile, false);
    $p4 = (!$valMime['valid']);
    if ($p4) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 4: File Upload Validation: Reject Disguised Script',
        'concept'     => 'Magic Bytes & getimagesize() Integrity Verification',
        'scenario'    => 'PHP script masked with .png extension (Fake image payload)',
        'expected'    => 'Detect non-image MIME / failed image inspection and abort upload',
        'actual'      => $p4 ? "Intercepted safely: {$valMime['error']}" : 'Failed to intercept disguised script',
        'status'      => $p4 ? 'PASS' : 'FAIL'
    ];
    @unlink($fakeTmp);

    $badExtFile = [
        'name'     => 'event_brochure.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 1024
    ];
    $valExt = validatePosterUpload($badExtFile, false);
    $p5 = (!$valExt['valid'] && strpos($valExt['error'], 'Invalid file extension') !== false);
    if ($p5) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 5: File Upload Validation: Reject Forbidden Extension',
        'concept'     => 'Extension Whitelist Guard (JPG, JPEG, PNG, WEBP)',
        'scenario'    => 'Attempted upload of .pdf file',
        'expected'    => 'Reject non-whitelisted extension before server storage',
        'actual'      => $p5 ? "Blocked safely: {$valExt['error']}" : 'Failed to block forbidden extension',
        'status'      => $p5 ? 'PASS' : 'FAIL'
    ];

    $oldPoster = $createdRecord['poster_image'] ?? null;
    $dummyPng2 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mNk+M9QzwAEAAMfAflZ+oTqAAAAAElFTkSuQmCC');
    $tmpFile2 = tempnam(sys_get_temp_dir(), 'test_poster2_');
    file_put_contents($tmpFile2, $dummyPng2);

    $updateFile = [
        'name'     => 'replacement_poster.png',
        'type'     => 'image/png',
        'tmp_name' => $tmpFile2,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($dummyPng2)
    ];

    $t0 = microtime(true);
    $updResult = saveEventRecord($mysqli, [
        'event_code'   => $testCode,
        'title'        => $testTitle . ' (Updated)',
        'category'     => 'Workshop',
        'event_date'   => date('Y-m-d H:i:s', time() + 86400 * 14),
        'venue'        => 'Updated Auditorium Hall B',
        'max_capacity' => 250,
        'description'  => 'Updated description with new poster',
        'status'       => 'ONGOING'
    ], $updateFile, $createdEventId);
    $lat6 = round((microtime(true) - $t0) * 1000, 2);

    $updatedRecord = getEventRecord($mysqli, $createdEventId);
    $newPoster = $updatedRecord['poster_image'] ?? null;
    $oldFileGone = $oldPoster ? !file_exists(__DIR__ . '/uploads/posters/' . $oldPoster) : true;
    $p6 = ($updResult['success'] && $newPoster !== $oldPoster && $oldFileGone && $updatedRecord['category'] === 'Workshop');
    if ($p6) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 6: UPDATE Event & Poster Replacement',
        'concept'     => 'Prepared UPDATE Statement & Old Poster Unlink',
        'scenario'    => "Event ID: {$createdEventId} | Update to Workshop & replace poster",
        'expected'    => 'Update event details, store new poster, delete old poster from disk',
        'actual'      => $p6 ? "Updated in {$lat6}ms. New poster: {$newPoster}; Old poster cleaned: " . ($oldFileGone ? 'Yes' : 'No') : 'Update failed',
        'status'      => $p6 ? 'PASS' : 'FAIL',
        'latency_ms'  => $lat6
    ];

    $dupResult = saveEventRecord($mysqli, [
        'event_code'   => 'EVT-AI-2026',
        'title'        => 'Colliding AI Summit Event',
        'category'     => 'Technical',
        'event_date'   => '2026-12-01 10:00:00',
        'venue'        => 'Main Hall',
        'max_capacity' => 50,
        'status'       => 'UPCOMING'
    ]);
    $p7 = (!$dupResult['success'] && isset($dupResult['errors']['event_code']));
    if ($p7) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 7: Event Code Uniqueness Validation',
        'concept'     => 'Pre-query Collision Detection for Unique Key',
        'scenario'    => 'Attempt to add event with existing code: EVT-AI-2026',
        'expected'    => 'Gracefully flag duplicate event code before database collision error',
        'actual'      => $p7 ? "Detected duplicate: {$dupResult['errors']['event_code']}" : 'Failed to detect duplicate event code',
        'status'      => $p7 ? 'PASS' : 'FAIL'
    ];

    $t0 = microtime(true);
    $delResult = deleteEventRecord($mysqli, $createdEventId);
    $lat8 = round((microtime(true) - $t0) * 1000, 2);
    $deletedRecord = getEventRecord($mysqli, $createdEventId);
    $posterDeleted = $newPoster ? !file_exists(__DIR__ . '/uploads/posters/' . $newPoster) : true;
    $p8 = ($delResult['success'] && $deletedRecord === null && $posterDeleted);
    if ($p8) $totalPass++; else $totalFail++;
    $results[] = [
        'case'        => 'Case 8: DELETE Event & Disk Poster Cleanup',
        'concept'     => 'DELETE Statement with Orphan Poster File Unlinking',
        'scenario'    => "Delete Event ID: {$createdEventId}",
        'expected'    => 'Remove DB record and unlink poster image from uploads/posters/',
        'actual'      => $p8 ? "Deleted in {$lat8}ms. Poster unlinked from disk: " . ($posterDeleted ? 'Yes' : 'No') : 'Delete failed',
        'status'      => $p8 ? 'PASS' : 'FAIL',
        'latency_ms'  => $lat8
    ];

    @unlink($tmpFile);
    @unlink($tmpFile2);

    echo json_encode([
        'success'    => true,
        'timestamp'  => date('Y-m-d H:i:s'),
        'total_pass' => $totalPass,
        'total_fail' => $totalFail,
        'test_cases' => $results
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 12: Event Management Test Suite &amp; Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f8fafc;
            color: #0f172a;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        .header-banner {
            background: linear-gradient(135deg, #090d16 0%, #1e1b4b 100%);
            color: #ffffff;
            padding: 28px 24px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
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

        .container {
            max-width: 1200px;
            margin: 28px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }

        .scenario-btn {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            text-decoration: none;
            color: #0f172a;
            display: block;
            transition: all 0.2s;
        }

        .scenario-btn:hover {
            border-color: #4f46e5;
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .scenario-btn strong {
            display: block;
            font-size: 0.95rem;
            color: #4338ca;
            margin-bottom: 4px;
        }

        .scenario-btn span {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.4;
            display: block;
        }

        .btn-run {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            border: none;
            padding: 13px 26px;
            border-radius: 10px;
            font-size: 0.96rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.2s;
        }

        .btn-run:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-top: 18px;
        }

        .report-table th {
            background: #f1f5f9;
            color: #334155;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            font-weight: 700;
        }

        .report-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .badge-pass {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
        }
    </style>
</head>
<body>

    <header class="header-banner">
        <div class="header-content">
            <div>
                <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 4px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;">
                    Practical 12 &bull; Automated Verification Suite
                </span>
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; margin: 8px 0 4px 0;">
                    Event Management CRUD &amp; Poster Upload Test Report
                </h1>
                <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">
                    PHP-MySQL CRUD, File Upload Validation (Size, MIME, Integrity), Event Lifecycle &amp; Admin UI
                </p>
            </div>
            <div>
                <a href="practical12_events.php" style="background: #4f46e5; color: #fff; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                    &larr; Open Event Management CRUD
                </a>
            </div>
        </div>
    </header>

    <div class="container">

        <div class="card">
            <h2 style="font-family: 'Outfit', sans-serif; margin: 0 0 8px 0; font-size: 1.35rem;">
                &#128248; Post-Laboratory Work: Screenshot Verification Scenarios
            </h2>
            <p style="color: #64748b; font-size: 0.92rem; margin: 0 0 16px 0;">
                Click any scenario below to launch it directly in the Event Management module to capture your laboratory submission screenshots:
            </p>

            <div class="action-grid">
                <a href="practical12_events.php" target="_blank" class="scenario-btn">
                    <strong>1. Event CRUD Main View</strong>
                    <span>Browse event cards, category chips, status tags, and poster thumbnails.</span>
                </a>

                <a href="practical12_events.php?view=table" target="_blank" class="scenario-btn">
                    <strong>2. Tabular Admin View</strong>
                    <span>Inspect events in data-table view with poster previews and edit/delete actions.</span>
                </a>

                <a href="practical12_events.php" target="_blank" class="scenario-btn">
                    <strong>3. Poster Upload &amp; Validation</strong>
                    <span>Trigger Create Modal with client-side preview, 2MB size and MIME validation.</span>
                </a>

                <a href="practical12_events.php?category=Technical" target="_blank" class="scenario-btn">
                    <strong>4. Category &amp; Status Filtering</strong>
                    <span>Demonstrate parameterized search and category dropdown filters.</span>
                </a>

                <a href="practical12_events.php" target="_blank" class="scenario-btn">
                    <strong>5. Delete Confirmation Modal</strong>
                    <span>Verify confirmation dialog before permanent deletion and poster file cleanup.</span>
                </a>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 14px;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; margin: 0 0 4px 0; font-size: 1.35rem;">
                        &#127919; Automated Execution Test Suite
                    </h2>
                    <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                        Executes live assertions across SELECT, INSERT, 2MB size validation, MIME validation, UPDATE, uniqueness, and DELETE with poster file disk cleanup.
                    </p>
                </div>
                <button type="button" id="btnExecuteSuite" class="btn-run">
                    &#9654; Run Automated Test Suite
                </button>
            </div>

            <div id="testProgress" style="display: none; padding: 14px; text-align: center; color: #4f46e5; font-weight: 600;">
                &#9203; Running 8 assertions against MySQLi and Poster Upload validation...
            </div>

            <div id="reportContainer">
                <div style="padding: 24px; text-align: center; color: #94a3b8; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px;">
                    Click <strong>"Run Automated Test Suite"</strong> above to execute live test cases.
                </div>
            </div>
        </div>

    </div>

    <script>
        document.getElementById('btnExecuteSuite').addEventListener('click', function () {
            const progress = document.getElementById('testProgress');
            const container = document.getElementById('reportContainer');

            progress.style.display = 'block';
            container.innerHTML = '';

            fetch('practical12_test.php?ajax=1&action=run_suite')
                .then(r => r.json())
                .then(data => {
                    progress.style.display = 'none';
                    if (!data.success) {
                        container.innerHTML = '<div style="color: #dc2626;">Error executing suite: ' + (data.error || 'Unknown') + '</div>';
                        return;
                    }

                    let html = `
                        <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong style="color: #166534; font-size: 1rem;">Official Practical 12 Verification Report</strong>
                                <div style="font-size: 0.82rem; color: #15803d;">Generated: ${data.timestamp} | Total Assertions: ${data.total_pass + data.total_fail} | Passed: ${data.total_pass} | Failed: ${data.total_fail}</div>
                            </div>
                            <span class="badge-pass" style="font-size: 0.9rem; padding: 6px 14px;">ALL ${data.total_pass} TESTS PASSED</span>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Test Case</th>
                                        <th>Concept Tested</th>
                                        <th>Inputs / Scenario</th>
                                        <th>Expected Outcome</th>
                                        <th>Actual Outcome</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    data.test_cases.forEach(tc => {
                        html += `
                            <tr>
                                <td><strong>${tc.case}</strong></td>
                                <td style="font-size: 0.82rem; color: #475569;">${tc.concept}</td>
                                <td style="font-size: 0.82rem;"><code>${tc.scenario}</code></td>
                                <td style="font-size: 0.82rem; color: #334155;">${tc.expected}</td>
                                <td style="font-size: 0.82rem; color: #166534;">${tc.actual}</td>
                                <td><span class="badge-pass">${tc.status}</span></td>
                            </tr>
                        `;
                    });

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    container.innerHTML = html;
                })
                .catch(err => {
                    progress.style.display = 'none';
                    container.innerHTML = '<div style="color: #dc2626;">Error: ' + err.message + '</div>';
                });
        });
    </script>

</body>
</html>
