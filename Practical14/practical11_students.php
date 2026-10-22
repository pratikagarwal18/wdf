<?php


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

if ($mysqli) {
    ensureStudentsTableReady($mysqli);
}

$message = '';
$msgType = 'ok';
$editStudent = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['student_id'] ?? 0);
    $res = deleteStudentRecord($mysqli, $delId);
    $message = $res['message'];
    $msgType = $res['success'] ? 'ok' : 'err';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create', 'update'])) {
    $studentId = ($_POST['action'] === 'update') ? (int)($_POST['student_id'] ?? 0) : null;
    $res = saveStudentRecord($mysqli, $_POST, $studentId);
    $message = $res['message'];
    $msgType = $res['success'] ? 'ok' : 'err';
}


if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editStudent = getStudentRecord($mysqli, (int)$_GET['edit']);
}


$searchParam     = trim($_GET['search'] ?? '');
$departmentParam = trim($_GET['department'] ?? '');
$statusParam     = strtoupper(trim($_GET['status'] ?? 'ACTIVE'));
$pageParam       = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 10;

$filters = [
    'search'     => $searchParam,
    'department' => $departmentParam,
    'status'     => $statusParam
];

$studentData = [
    'data'        => [],
    'total'       => 0,
    'page'        => 1,
    'per_page'    => $perPage,
    'total_pages' => 1
];

if ($mysqli) {
    $studentData = fetchStudentsList($mysqli, $filters, $pageParam, $perPage, 'roll_no', 'ASC');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 11: Student Management CRUD</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1100px; margin: 26px auto; padding: 0 16px; }
        .card { background: #fff; padding: 22px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); margin-bottom: 22px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px; }
        .form-grid .field { margin-bottom: 0; }
        .form-grid label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.88rem; }
        .form-grid input, .form-grid select { width: 100%; max-width: 100%; padding: 8px; box-sizing: border-box; }
        .banner-ok { background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; border: 1px solid #86efac; margin-bottom: 18px; }
        .banner-err { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 16px; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid var(--line); border-radius: 6px; }
        .action-btn { padding: 4px 10px; font-size: 0.82rem; border-radius: 4px; text-decoration: none; display: inline-block; }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-del { background: #fee2e2; color: #991b1b; border: none; cursor: pointer; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 18px; align-items: center; }
        .pagination a, .pagination span { padding: 6px 14px; border: 1px solid var(--line); border-radius: 6px; text-decoration: none; color: var(--ink); }
        .pagination .active { background: var(--blue); color: #fff; border-color: var(--blue); }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical11_test.php">Practical 11 Test Suite &rarr;</a> |
            <a href="practical12_events.php">Practical 12 Events</a> |
            <a href="login.php">Login</a>
        </nav>
    </div>

    <div class="container">

        <?php if ($message): ?>
            <div class="<?= $msgType === 'ok' ? 'banner-ok' : 'banner-err' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        
        <div class="card">
            <h2 style="margin-top: 0; text-align: left; background: none; color: var(--navy); padding: 0;">
                <?= $editStudent ? 'Edit Student Record' : 'Add New Student Record' ?>
            </h2>

            <form method="POST" action="practical11_students.php" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <input type="hidden" name="action" value="<?= $editStudent ? 'update' : 'create' ?>">
                <?php if ($editStudent): ?>
                    <input type="hidden" name="student_id" value="<?= $editStudent['student_id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="field">
                        <label for="roll_no">Roll Number *</label>
                        <input type="text" id="roll_no" name="roll_no" value="<?= htmlspecialchars($editStudent['roll_no'] ?? '') ?>" placeholder="e.g. 21BCE1001" required>
                    </div>
                    <div class="field">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($editStudent['full_name'] ?? '') ?>" placeholder="e.g. Aarav Sharma" required>
                    </div>
                    <div class="field">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($editStudent['email'] ?? '') ?>" placeholder="student@studenthub.edu" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($editStudent['phone'] ?? '') ?>" placeholder="9876543210" required>
                    </div>
                    <div class="field">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <?php 
                            $depts = ['Computer Engineering', 'Information Technology', 'Electronics & Comm', 'Mechanical Engineering'];
                            foreach ($depts as $d): ?>
                                <option value="<?= $d ?>" <?= (($editStudent['department'] ?? '') === $d) ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="semester">Semester *</label>
                        <select id="semester" name="semester">
                            <?php for ($s=1; $s<=8; $s++): ?>
                                <option value="<?= $s ?>" <?= (($editStudent['semester'] ?? 1) == $s) ? 'selected' : '' ?>>Semester <?= $s ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="ACTIVE" <?= (($editStudent['status'] ?? 'ACTIVE') === 'ACTIVE') ? 'selected' : '' ?>>ACTIVE</option>
                            <option value="INACTIVE" <?= (($editStudent['status'] ?? '') === 'INACTIVE') ? 'selected' : '' ?>>INACTIVE</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit"><?= $editStudent ? 'Save Changes' : 'Add Student' ?></button>
                    <?php if ($editStudent): ?>
                        <a href="practical11_students.php" style="background:#e2e8f0; color:#1e293b; padding:10px 18px; border-radius:999px; text-decoration:none; font-weight:600;">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        
        <div class="card">
            <h2 style="margin-top: 0; text-align: left; background: none; color: var(--navy); padding: 0;">
                Student Records (Total: <?= $studentData['total'] ?>)
            </h2>

            
            <form method="GET" action="practical11_students.php" class="filter-bar" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <input type="text" name="search" value="<?= htmlspecialchars($searchParam) ?>" placeholder="Search name, roll no, email...">
                
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?= $d ?>" <?= ($departmentParam === $d) ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="ACTIVE" <?= ($statusParam === 'ACTIVE') ? 'selected' : '' ?>>Active Only</option>
                    <option value="INACTIVE" <?= ($statusParam === 'INACTIVE') ? 'selected' : '' ?>>Inactive Only</option>
                    <option value="ALL" <?= ($statusParam === 'ALL') ? 'selected' : '' ?>>All Statuses</option>
                </select>

                <button type="submit" style="padding:8px 16px;">Apply Filters</button>
                <a href="practical11_students.php" style="padding:8px 12px; font-size:0.88rem; text-decoration:none;">Reset</a>
            </form>

            
            <table style="width: 100%; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Sem</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($studentData['data'])): ?>
                        <?php foreach ($studentData['data'] as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['roll_no']) ?></strong></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['department']) ?></td>
                                <td><?= htmlspecialchars($s['semester']) ?></td>
                                <td>
                                    <span style="color: <?= $s['status'] === 'ACTIVE' ? '#166534' : '#991b1b' ?>; font-weight: bold;">
                                        <?= htmlspecialchars($s['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="practical11_students.php?edit=<?= $s['student_id'] ?>" class="action-btn btn-edit">Edit</a>
                                    <form method="POST" action="practical11_students.php" style="display:inline; border:none; box-shadow:none; padding:0;" onsubmit="return confirm('Delete student <?= htmlspecialchars($s['full_name']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                        <button type="submit" class="action-btn btn-del">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; color: var(--ink-soft);">No matching student records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            
            <?php if ($studentData['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($studentData['page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $studentData['page'] - 1])) ?>">&laquo; Prev</a>
                    <?php endif; ?>

                    <span>Page <?= $studentData['page'] ?> of <?= $studentData['total_pages'] ?></span>

                    <?php if ($studentData['page'] < $studentData['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $studentData['page'] + 1])) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
