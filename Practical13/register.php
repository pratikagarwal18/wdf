<?php
/**
 * Practical 9: User Registration & Password Hashing
 * Beginner-friendly, clean PHP implementation
 */

session_start();
require_once __DIR__ . '/db.php';

$mysqli = getMySQLiConnection(true);
$isDbConnected = ($mysqli !== null);

// Handle AJAX Endpoints (Live check & Test Suite)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? '';

    // Check duplicate username or email
    if ($action === 'check_duplicate') {
        $field = $_GET['field'] ?? '';
        $value = trim($_GET['value'] ?? '');
        if (!$isDbConnected || empty($value)) {
            echo json_encode(['status' => 'empty', 'message' => '']);
            exit;
        }
        $dup = ($field === 'email') 
            ? checkDuplicateUser($mysqli, $value, '') 
            : checkDuplicateUser($mysqli, '', $value);

        if (($field === 'email' && $dup['duplicate_email']) || ($field === 'username' && $dup['duplicate_username'])) {
            echo json_encode(['status' => 'duplicate', 'message' => "⚠ {$field} is already taken!"]);
        } else {
            echo json_encode(['status' => 'available', 'message' => "✓ {$field} is available"]);
        }
        exit;
    }

    // Run Automated Lab Test Suite
    if ($action === 'run_test_suite') {
        if (!$isDbConnected) {
            echo json_encode(['success' => false, 'error' => 'Database offline']);
            exit;
        }

        $testResults = [];
        $randId = rand(1000, 9999);
        $testUser = [
            'username'   => 'test_user_' . $randId,
            'full_name'  => 'Test Student ' . $randId,
            'email'      => 'test.' . $randId . '@studenthub.edu',
            'phone'      => '98765' . $randId,
            'department' => 'Computer Engineering',
            'semester'   => 4,
            'password_hash' => password_hash('Pass@123', PASSWORD_BCRYPT)
        ];

        // Case 1: Insert user
        $ins = insertUserRecord($mysqli, $testUser);
        $testResults[] = [
            'case'     => 'Case 1: Valid User Registration',
            'expected' => 'Insert user, return user_id > 0',
            'actual'   => $ins['success'] ? "Success! Created User ID: #{$ins['user_id']}" : "Failed: " . $ins['error'],
            'status'   => $ins['success'] ? 'PASS' : 'FAIL'
        ];

        // Case 2: Duplicate email check
        $dupEmail = checkDuplicateUser($mysqli, $testUser['email'], 'other_user');
        $testResults[] = [
            'case'     => 'Case 2: Duplicate Email Detection',
            'expected' => 'Duplicate email flagged as true',
            'actual'   => $dupEmail['duplicate_email'] ? 'Duplicate email successfully detected' : 'Failed to detect',
            'status'   => $dupEmail['duplicate_email'] ? 'PASS' : 'FAIL'
        ];

        // Case 3: Duplicate username check
        $dupUser = checkDuplicateUser($mysqli, 'new_email@test.com', $testUser['username']);
        $testResults[] = [
            'case'     => 'Case 3: Duplicate Username Detection',
            'expected' => 'Duplicate username flagged as true',
            'actual'   => $dupUser['duplicate_username'] ? 'Duplicate username successfully detected' : 'Failed to detect',
            'status'   => $dupUser['duplicate_username'] ? 'PASS' : 'FAIL'
        ];

        // Case 4: Password Hash Verification
        $verified = password_verify('Pass@123', $testUser['password_hash']);
        $testResults[] = [
            'case'     => 'Case 4: Bcrypt Password Verification',
            'expected' => 'password_verify() returns true for plain password and hash',
            'actual'   => $verified ? 'Password verified against Bcrypt hash' : 'Verification failed',
            'status'   => $verified ? 'PASS' : 'FAIL'
        ];

        echo json_encode(['success' => true, 'test_cases' => $testResults]);
        exit;
    }
}

// Handle Form Submission
$errors = [];
$successData = null;
$formData = [
    'fullName'   => '',
    'username'   => '',
    'email'      => '',
    'phone'      => '',
    'department' => 'Computer Engineering',
    'semester'   => '1'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['fullName']   = trim($_POST['fullName'] ?? '');
    $formData['username']   = trim($_POST['username'] ?? $_POST['enrollment'] ?? '');
    $formData['email']      = trim($_POST['email'] ?? '');
    $formData['phone']      = trim($_POST['phone'] ?? $_POST['mobile'] ?? '');
    $formData['department'] = trim($_POST['department'] ?? 'Computer Engineering');
    $formData['semester']   = (int)($_POST['semester'] ?? 1);
    $password               = $_POST['password'] ?? '';
    $confirmPassword        = $_POST['confirmPassword'] ?? '';

    // Validation
    if (empty($formData['fullName'])) $errors['fullName'] = 'Full Name is required.';
    if (empty($formData['username'])) $errors['username'] = 'Username is required.';
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid Email is required.';
    if (empty($formData['phone'])) $errors['phone'] = 'Phone number is required.';
    if (empty($password) || strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    elseif ($password !== $confirmPassword) $errors['confirmPassword'] = 'Passwords do not match.';

    // Check duplicate in DB
    if (empty($errors) && $isDbConnected) {
        $dup = checkDuplicateUser($mysqli, $formData['email'], $formData['username']);
        if ($dup['duplicate_email']) $errors['email'] = 'This email is already registered.';
        if ($dup['duplicate_username']) $errors['username'] = 'This username is already taken.';
    }

    // Insert user
    if (empty($errors)) {
        if (!$isDbConnected) {
            $errors['db'] = 'Database connection failed. Please ensure MySQL is running in XAMPP.';
        } else {
            // Securely hash password with Bcrypt
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $userData = [
                'username'      => $formData['username'],
                'full_name'     => $formData['fullName'],
                'email'         => $formData['email'],
                'password_hash' => $hash,
                'phone'         => $formData['phone'],
                'department'    => $formData['department'],
                'semester'      => $formData['semester'],
                'role'          => 'STUDENT'
            ];
            $res = insertUserRecord($mysqli, $userData);
            if ($res['success']) {
                $successData = [
                    'user_id'  => $res['user_id'],
                    'username' => $formData['username'],
                    'email'    => $formData['email'],
                    'hash'     => $hash
                ];
                // Reset fields
                foreach ($formData as $k => $v) $formData[$k] = '';
            } else {
                $errors['db'] = 'Insert failed: ' . $res['error'];
            }
        }
    }
}

// Fetch recent users for display
$recentUsers = [];
if ($isDbConnected) {
    $rRes = $mysqli->query("SELECT user_id, username, full_name, email, password_hash, department, role, created_at FROM users ORDER BY user_id DESC LIMIT 5");
    if ($rRes) {
        while ($row = $rRes->fetch_assoc()) $recentUsers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 9: User Registration &amp; Password Hashing</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .p9-box {
            max-width: 700px;
            margin: 30px auto;
            background: #fff;
            padding: 26px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }
        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 14px;
        }
        .form-row .form-field {
            flex: 1;
        }
        .form-field label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .form-field input, .form-field select {
            width: 100%;
            max-width: 100%;
            padding: 9px;
            box-sizing: border-box;
            border: 1px solid var(--line);
            border-radius: 6px;
        }
        .err { color: #b91c1c; font-size: 0.82rem; margin-top: 3px; display: block; }
        .live-hint { font-size: 0.8rem; margin-top: 3px; display: block; }
        .banner-ok { background: #dcfce7; color: #166534; padding: 14px; border-radius: 6px; border: 1px solid #86efac; margin-bottom: 18px; }
        .banner-err { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 18px; }
        .tab-bar { display: flex; gap: 8px; border-bottom: 2px solid var(--line); margin-bottom: 20px; }
        .tab-btn { background: none; border: none; padding: 10px 18px; font-weight: bold; cursor: pointer; border-bottom: 3px solid transparent; }
        .tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="login.php">Login (Practical 10)</a> |
            <a href="practical8_test.php">Practical 8</a> |
            <a href="db_setup.php">DB Setup</a>
        </nav>
    </div>

    <div class="p9-box">
        <h2 style="margin-top: 0; text-align: center;">Practical 9: User Registration &amp; Password Hashing</h2>
        <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
            Demonstrates MySQLi Prepared INSERT, Duplicate Checking, and Bcrypt Hashing
        </p>

        <div class="tab-bar">
            <button class="tab-btn active" id="btnTabForm" onclick="switchTab('form')">Registration Form</button>
            <button class="tab-btn" id="btnTabTests" onclick="switchTab('tests')">Automated Test Suite</button>
        </div>

        <!-- TAB 1: FORM -->
        <div id="tabForm">
            <?php if ($successData): ?>
                <div class="banner-ok">
                    <strong>&#10003; Registration Successful!</strong> User #<?= $successData['user_id'] ?> created.<br>
                    <small><strong>Bcrypt Hash Stored in DB:</strong> <code><?= htmlspecialchars($successData['hash']) ?></code></small><br>
                    <a href="login.php" style="color: #166534; font-weight: bold; display: inline-block; margin-top: 6px;">Sign in now &rarr;</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['db'])): ?>
                <div class="banner-err">&#9888; <?= htmlspecialchars($errors['db']) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <div class="form-row">
                    <div class="form-field">
                        <label for="fullName">Full Name *</label>
                        <input type="text" id="fullName" name="fullName" value="<?= htmlspecialchars($formData['fullName']) ?>" placeholder="e.g. Aarav Sharma" required>
                        <?php if (isset($errors['fullName'])): ?><span class="err"><?= $errors['fullName'] ?></span><?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($formData['username']) ?>" placeholder="e.g. aarav.sharma" required onblur="checkDup('username', this.value)">
                        <span id="hint-username" class="live-hint"></span>
                        <?php if (isset($errors['username'])): ?><span class="err"><?= $errors['username'] ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" placeholder="student@studenthub.edu" required onblur="checkDup('email', this.value)">
                        <span id="hint-email" class="live-hint"></span>
                        <?php if (isset($errors['email'])): ?><span class="err"><?= $errors['email'] ?></span><?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>" placeholder="9876543210" required>
                        <?php if (isset($errors['phone'])): ?><span class="err"><?= $errors['phone'] ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Electronics &amp; Comm">Electronics &amp; Comm</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester">
                            <?php for ($s=1; $s<=8; $s++): ?>
                                <option value="<?= $s ?>" <?= ($formData['semester'] == $s) ? 'selected' : '' ?>>Semester <?= $s ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="password">Password (min 6 chars) *</label>
                        <input type="password" id="password" name="password" placeholder="Create password" required>
                        <?php if (isset($errors['password'])): ?><span class="err"><?= $errors['password'] ?></span><?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required>
                        <?php if (isset($errors['confirmPassword'])): ?><span class="err"><?= $errors['confirmPassword'] ?></span><?php endif; ?>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 10px;">
                    Register Account (with Bcrypt Hashing)
                </button>
            </form>

            <?php if (!empty($recentUsers)): ?>
                <h3 style="margin-top: 30px; padding: 0;">Recently Registered Users (From MySQL):</h3>
                <table style="width: 100%; font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Password Hash (Bcrypt)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>#<?= $u['user_id'] ?></td>
                                <td><b><?= htmlspecialchars($u['username']) ?></b></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><code style="font-size:0.75rem;"><?= substr($u['password_hash'], 0, 25) ?>...</code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- TAB 2: AUTOMATED TESTS -->
        <div id="tabTests" style="display: none;">
            <p>Run automated laboratory verification test cases for Practical 9:</p>
            <button type="button" onclick="runSuite()" style="margin-bottom: 16px;">
                ▶ Run Automated Test Cases
            </button>
            <div id="testOutput"></div>
        </div>
    </div>

    <script>
        function switchTab(t) {
            document.getElementById('tabForm').style.display = (t === 'form') ? 'block' : 'none';
            document.getElementById('tabTests').style.display = (t === 'tests') ? 'block' : 'none';
            document.getElementById('btnTabForm').className = 'tab-btn' + (t === 'form' ? ' active' : '');
            document.getElementById('btnTabTests').className = 'tab-btn' + (t === 'tests' ? ' active' : '');
        }

        function checkDup(field, val) {
            if (!val) return;
            fetch(`register.php?ajax=1&action=check_duplicate&field=${field}&value=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(d => {
                    const el = document.getElementById('hint-' + field);
                    if (el) {
                        el.innerText = d.message;
                        el.style.color = (d.status === 'duplicate') ? '#b91c1c' : '#166534';
                    }
                });
        }

        function runSuite() {
            const out = document.getElementById('testOutput');
            out.innerHTML = '<em>Running automated tests against MySQL database...</em>';
            fetch('register.php?ajax=1&action=run_test_suite')
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        out.innerHTML = '<span style="color:red;">Test failed: ' + d.error + '</span>';
                        return;
                    }
                    let html = '<table style="width:100%; font-size:0.85rem;"><thead><tr><th>Test Case</th><th>Expected</th><th>Actual</th><th>Status</th></tr></thead><tbody>';
                    d.test_cases.forEach(c => {
                        const color = (c.status === 'PASS') ? '#166534' : '#b91c1c';
                        html += `<tr><td><b>${c.case}</b></td><td>${c.expected}</td><td>${c.actual}</td><td><b style="color:${color}">${c.status}</b></td></tr>`;
                    });
                    html += '</tbody></table>';
                    out.innerHTML = html;
                })
                .catch(err => {
                    out.innerHTML = '<span style="color:red;">Error running test suite: ' + err + '</span>';
                });
        }

        if (window.location.hash === '#tests') {
            switchTab('tests');
        }
    </script>

</body>
</html>
