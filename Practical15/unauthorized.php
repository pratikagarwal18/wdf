<?php
require_once __DIR__ . '/auth.php';
initSecureSession();

$user = getCurrentUser();
if (isset($_GET['user_role'])) {
    $user = ['username' => htmlspecialchars($_GET['username'] ?? 'aarav.sharma'), 'role' => htmlspecialchars($_GET['user_role'])];
}
$required = htmlspecialchars($_GET['required'] ?? 'Authorized Role');
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #0f172a; color: #fff; font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
        .card { background: #fff; color: #1e293b; max-width: 480px; width: 100%; border-radius: 8px; padding: 30px; text-align: center; }
        .icon { font-size: 3rem; color: #dc2626; margin-bottom: 10px; }
        .info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin: 16px 0; font-size: 0.9rem; text-align: left; }
        .btn { display: inline-block; padding: 8px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#9888;</div>
        <h2 style="margin-top: 0; color: #dc2626; background: none;">403 - Access Denied</h2>
        <p>You do not have permission to access this page.</p>
        <div class="info">
            <div>Current User: <strong><?= htmlspecialchars($user['username'] ?? 'Guest') ?></strong></div>
            <div>Your Role: <strong><?= htmlspecialchars($user['role'] ?? 'None') ?></strong></div>
            <div>Required Role: <strong style="color: #dc2626;"><?= $required ?></strong></div>
        </div>
        <div style="margin-top: 20px;">
            <?php if ($user): ?>
                <a href="<?= getRoleRedirectUrl($user['role']) ?>" class="btn">Go to My Dashboard</a>
                <a href="logout.php" class="btn" style="background: #64748b; margin-left: 8px;">Log Out</a>
            <?php else: ?>
                <a href="login.php" class="btn">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
