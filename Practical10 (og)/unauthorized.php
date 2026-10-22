<?php
/**
 * Practical 10: 403 Unauthorized Access Guard & Security Warning
 */

require_once __DIR__ . '/auth.php';
initSecureSession();

$currentUser = getCurrentUser();
$required = htmlspecialchars($_GET['required'] ?? 'Authorized Role');
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied | StudentHub Security</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Inter', sans-serif;
            margin: 0;
            color: #f8fafc;
        }
        .security-card {
            background: #ffffff;
            color: #0f172a;
            max-width: 560px;
            width: 100%;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin-bottom: 20px;
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.2);
        }
        .security-badge {
            display: inline-block;
            background: #fef2f2;
            color: #991b1b;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 999px;
            margin-bottom: 12px;
            border: 1px solid #fecaca;
        }
        h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            margin: 0 0 12px 0;
            color: #0f172a;
        }
        p {
            color: #475569;
            font-size: 0.96rem;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }
        .info-table {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
            text-align: left;
            font-size: 0.9rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: #3b82f6;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>

    <div class="security-card">
        <div class="icon-circle">&#128737;</div>
        <div><span class="security-badge">HTTP 403 &bull; Role-Based Access Control</span></div>
        <h2>Access Restricted</h2>
        <p>
            You do not possess the required privilege level to view this resource. 
            This access attempt has been recorded in the security audit trail.
        </p>

        <div class="info-table">
            <div class="info-row">
                <span style="color: #64748b;">Current Authenticated User:</span>
                <strong><?= htmlspecialchars($currentUser['username'] ?? 'Anonymous / Guest') ?></strong>
            </div>
            <div class="info-row">
                <span style="color: #64748b;">Your Assigned Role:</span>
                <span style="color: #0369a1; font-weight: 700;"><?= htmlspecialchars($currentUser['role'] ?? 'None') ?></span>
            </div>
            <div class="info-row">
                <span style="color: #64748b;">Privilege Required for Page:</span>
                <span style="color: #dc2626; font-weight: 700;"><?= $required ?></span>
            </div>
        </div>

        <div class="btn-group">
            <?php if ($currentUser): ?>
                <a href="<?= getRoleRedirectUrl($currentUser['role']) ?>" class="btn-primary">
                    &larr; Return to My Dashboard
                </a>
                <a href="logout.php" class="btn-secondary">
                    Log Out / Switch User
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-primary">
                    Log In to Continue &rarr;
                </a>
                <a href="index.html" class="btn-secondary">
                    Portal Home
                </a>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
