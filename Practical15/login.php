<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();

if (isLoggedIn()) {
    header("Location: " . getRoleRedirectUrl($_SESSION['role'] ?? 'STUDENT'));
    exit;
}

$error = '';
$notice = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $notice = "You have been logged out successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $mysqli = getMySQLiConnection(true);
        $stmt = $mysqli->prepare("SELECT user_id, username, full_name, email, password_hash, role, is_active FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_active']) {
                loginUser($user);
                header("Location: " . getRoleRedirectUrl($user['role']));
                exit;
            } else {
                $error = "Account is inactive. Contact admin.";
            }
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .box { max-width: 420px; margin: 40px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 24px; font-family: sans-serif; }
        .field { margin-bottom: 12px; }
        .field label { display: block; margin-bottom: 4px; font-size: 0.88rem; font-weight: 600; color: #334155; }
        .field input { width: 100%; padding: 8px 10px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn { width: 100%; padding: 10px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 14px; }
        .alert-err { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 14px; }
        .demo-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px; margin-top: 16px; font-size: 0.8rem; color: #475569; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div style="background: #0f172a; padding: 12px 20px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
        <strong>StudentHub Portal</strong>
        <div>
            <a href="index.html" style="color: #93c5fd; text-decoration: none; margin-right: 12px;">Home</a>
            <a href="register.php" style="color: #93c5fd; text-decoration: none;">Register</a>
        </div>
    </div>

    <div class="box">
        <h2 style="margin-top: 0; text-align: center;">Sign In</h2>

        <?php if (!empty($notice)): ?>
            <div class="alert-ok"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Username or Email</label>
                <input type="text" name="identifier" required placeholder="e.g. admin.portal or aarav.sharma">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn">Login</button>
        </form>

        <p style="text-align: center; margin-top: 14px; font-size: 0.88rem; color: #64748b;">
            New to StudentHub? <a href="register.php" style="color: #2563eb; font-weight: 600;">Create an account</a>
        </p>

        <div class="demo-box">
            <strong>Demo Accounts:</strong><br>
            &bull; Admin: <code>admin.portal</code> / <code>Admin@123</code><br>
            &bull; Student: <code>aarav.sharma</code> / <code>Student@123</code>
        </div>
    </div>

</body>
</html>
