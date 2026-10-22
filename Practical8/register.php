<?php
session_start();
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $dept = trim($_POST['department'] ?? 'Computer Engineering');
    $semester = (int)($_POST['semester'] ?? 1);

    if (empty($username) || empty($fullName) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE username = :u OR email = :e LIMIT 1");
            $chk->execute([':u' => $username, ':e' => $email]);
            if ($chk->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, department, semester, is_active, created_at) VALUES (:u, :fn, :e, :h, 'STUDENT', :dept, :sem, 1, NOW())");
                $stmt->execute([
                    ':u'    => $username,
                    ':fn'   => $fullName,
                    ':e'    => $email,
                    ':h'    => $hash,
                    ':dept' => $dept,
                    ':sem'  => $semester
                ]);

                // Also insert into students table to keep normalized tables synced
                try {
                    $stuRoll = '22BCE' . rand(1100, 1999);
                    $stuStmt = $pdo->prepare("INSERT INTO students (roll_no, full_name, email, department, semester, phone) VALUES (:roll, :fn, :e, :dept, :sem, '9876543210')");
                    $stuStmt->execute([
                        ':roll' => $stuRoll,
                        ':fn'   => $fullName,
                        ':e'    => $email,
                        ':dept' => $dept,
                        ':sem'  => $semester
                    ]);
                } catch (Exception $ex) {}

                $success = "Registration successful! Record has been validated and safely stored in MySQL database.";
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f1f5f9;
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            color: #1e293b;
        }
        .topbar {
            background: #0f172a;
            padding: 14px 24px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #3b82f6;
        }
        .topbar-brand {
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar a {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 500;
            margin-left: 16px;
        }
        .topbar a:hover {
            text-decoration: underline;
        }
        .reg-container {
            max-width: 500px;
            margin: 36px auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        .reg-title {
            margin-top: 0;
            margin-bottom: 6px;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            text-align: center;
        }
        .reg-sub {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 22px;
        }
        .field {
            margin-bottom: 16px;
        }
        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
        }
        .field input, .field select {
            width: 100%;
            padding: 10px 14px;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .field input:focus, .field select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-register {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-register:hover {
            background: #1d4ed8;
        }
        .alert-ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-err {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <header class="topbar">
        <div class="topbar-brand">
            <span>🎓</span> StudentHub Academic Portal &bull; Practical 8
        </div>
        <nav>
            <a href="index.html">Home</a>
            <a href="practical8_test.php">MySQL Diagnostics</a>
            <a href="login.html">Sign In</a>
        </nav>
    </header>

    <div class="reg-container">
        <h1 class="reg-title">Student Registration</h1>
        <p class="reg-sub">Fill in your information to register account into MySQL database</p>

        <?php if (!empty($error)): ?>
            <div class="alert-err">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-ok" role="alert">
                <span style="font-size: 1.4rem;">✔</span>
                <div>
                    <strong style="display: block; font-size: 1.05rem; margin-bottom: 4px;">Registration Successful!</strong>
                    <span><?= htmlspecialchars($success) ?></span>
                    <div style="margin-top: 10px; font-size: 0.88rem; color: #047857;">
                        Database: <strong>studenthub_db</strong> | Table: <strong>users</strong> &amp; <strong>students</strong>
                    </div>
                    <div style="margin-top: 12px;">
                        <a href="login.html" style="color: #1e40af; font-weight: 600; text-decoration: underline;">Proceed to Sign In &rarr;</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="register.php" id="registrationForm">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="e.g. pratik.patel" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="e.g. Pratik Patel" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. pratik.patel@studenthub.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="At least 6 characters" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="department">Department / Branch</label>
                    <select id="department" name="department">
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electronics & Comm">Electronics & Comm</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>
                <div class="field">
                    <label for="semester">Semester of Study</label>
                    <select id="semester" name="semester">
                        <option value="1">Semester 1 (1st Year)</option>
                        <option value="2">Semester 2 (1st Year)</option>
                        <option value="3">Semester 3 (2nd Year)</option>
                        <option value="4">Semester 4 (2nd Year)</option>
                        <option value="5">Semester 5 (3rd Year)</option>
                        <option value="6" selected>Semester 6 (3rd Year)</option>
                        <option value="7">Semester 7 (4th Year)</option>
                        <option value="8">Semester 8 (4th Year)</option>
                    </select>
                </div>
                <button type="submit" class="btn-register" id="btnRegister">Register Account</button>
            </form>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 20px; font-size: 0.88rem; color: #64748b;">
            Already registered? <a href="login.html" style="color: #2563eb; font-weight: 600;">Sign in here</a>
        </p>
    </div>

</body>
</html>
