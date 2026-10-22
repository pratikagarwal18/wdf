<?php
/**
 * Practical 7 - Intermediate Extension:
 * Display stored JSON records in a clean, interactive web table.
 */

$dataDir = __DIR__ . '/data';
$jsonFile = $dataDir . '/submissions.json';

// Handle direct JSON file download if requested
if (isset($_GET['download']) && $_GET['download'] === 'json' && file_exists($jsonFile)) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="submissions_' . date('Ymd_His') . '.json"');
    readfile($jsonFile);
    exit;
}

// Read JSON Data safely
$jsonRecords = [];
if (file_exists($jsonFile) && filesize($jsonFile) > 0) {
    $content = file_get_contents($jsonFile);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $jsonRecords = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Stored Records - Practical 7</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 0 16px;
        }
        .controls-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin: 20px auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow);
        }
        .record-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-deep) 100%);
            color: #fff;
        }
        .action-links {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        .download-btn {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 7px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .download-btn:hover {
            background: #dcfce7;
            color: #14532d;
        }
        .table-responsive {
            overflow-x: auto;
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            margin-bottom: 30px;
        }
        .records-table {
            width: 100%;
            margin: 0;
            border: none !important;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .records-table th {
            white-space: nowrap;
            position: sticky;
            top: 0;
            background: var(--navy);
            color: #fff;
        }
        .records-table td {
            white-space: nowrap;
        }
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--ink-soft);
        }
        .search-box {
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 6px;
            font-size: 0.9rem;
            width: 240px;
        }
    </style>
</head>
<body>

    <h1>Student Portal</h1>
    <hr>
    <h2>Practical 7: Intermediate Extension — JSON Records Table</h2>

    <div class="container">
        
        <div class="controls-card">
            <div class="record-badge">
                📦 Stored Records (<?php echo count($jsonRecords); ?> in submissions.json)
            </div>

            <div class="action-links">
                <input type="text" id="tableSearch" class="search-box" placeholder="Search / filter records..." onkeyup="filterTable()">
                <a href="?download=json" class="download-btn">
                    ⬇ Download submissions.json
                </a>
                <a href="practical7_register.php" style="font-weight: 600;">← Back to Form</a>
            </div>
        </div>

        <div class="table-responsive">
            <?php if (empty($jsonRecords)): ?>
                <div class="empty-state">
                    <p>No records found in <code>data/submissions.json</code> yet.</p>
                    <p><a href="practical7_register.php">Submit a registration</a> to create the first record.</p>
                </div>
            <?php else: ?>
                <table class="records-table" id="dataTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Full Name</th>
                            <th>Enrollment ID</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jsonRecords as $index => $item): ?>
                            <tr>
                                <td><strong><?php echo $index + 1; ?></strong></td>
                                <td><?php echo htmlspecialchars($item['timestamp'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['full_name'] ?? ''); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['enrollment_id'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['mobile'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['course'] ?? ''); ?></td>
                                <td>Year <?php echo htmlspecialchars($item['year'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['gender'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['dob'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['address'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function filterTable() {
            const input = document.getElementById("tableSearch");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            if (!table) return;
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                const rowText = tr[i].textContent.toLowerCase();
                tr[i].style.display = rowText.indexOf(filter) > -1 ? "" : "none";
            }
        }
    </script>

    <hr>
    <p>© 2026 Student Portal • Practical 7 JSON Records Viewer</p>
</body>
</html>
