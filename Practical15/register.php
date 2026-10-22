<?php
session_start();
require_once __DIR__ . '/db.php';

$mysqli = getMySQLiConnection(true);
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
        $chk = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $chk->bind_param("ss", $username, $email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $error = 'Username or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("INSERT INTO users (username, full_name, email, password_hash, role, department, semester, is_active, created_at) VALUES (?, ?, ?, ?, 'STUDENT', ?, ?, 1, NOW())");
            $stmt->bind_param("sssssi", $username, $fullName, $email, $hash, $dept, $semester);
            if ($stmt->execute()) {
                $success = "Registration successful! You can now log in.";
            } else {
                $error = 'Registration failed: ' . $mysqli->error;
            }
            $stmt->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .box { max-width: 440px; margin: 30px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 24px; font-family: sans-serif; }
        .field { margin-bottom: 12px; }
        .field label { display: block; margin-bottom: 4px; font-size: 0.88rem; font-weight: 600; color: #334155; }
        .field input, .field select { width: 100%; padding: 8px 10px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn { width: 100%; padding: 10px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 14px; }
        .alert-err { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 14px; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div style="background: #0f172a; padding: 12px 20px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
        <strong>StudentHub Registration</strong>
        <div>
            <a href="index.html" style="color: #93c5fd; text-decoration: none; margin-right: 12px;">Home</a>
            <a href="login.php" style="color: #93c5fd; text-decoration: none;">Sign In</a>
        </div>
    </div>

    <div class="box">
        <h2 style="margin-top: 0; text-align: center;">Create Account</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-ok">
                <?= htmlspecialchars($success) ?><br>
                <a href="login.php" style="color: #166534; font-weight: bold;">Click here to Login &rarr;</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Choose a username">
                </div>
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="Your full name">
                </div>
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your.email@example.com">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="At least 6 characters">
                </div>
                <div class="field">
                    <label>Department</label>
                    <select name="department">
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>
                <div class="field">
                    <label>Semester</label>
                    <select name="semester">
                        <option value="1">Semester 1</option><option value="2">Semester 2</option>
                        <option value="3">Semester 3</option><option value="4">Semester 4</option>
                        <option value="5">Semester 5</option><option value="6">Semester 6</option>
                    </select>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 14px; font-size: 0.88rem; color: #64748b;">
            Already have an account? <a href="login.php" style="color: #2563eb; font-weight: 600;">Sign in here</a>
        </p>
    </div>

</body>
</html>
