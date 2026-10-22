<?php
/**
 * Practical 10: Exclusive Administrator Dashboard
 * Demonstrates Role-Based Access Control (RBAC).
 * Only accessible by accounts with role === 'ADMIN'.
 */

require_once __DIR__ . '/auth.php';

// Key Question 3 & 5: Restrict dashboard access by role
requireLogin();
requireRole(['ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);

// 1. Fetch system metrics
$totalUsers = 0;
$totalStudents = 0;
$totalFaculty = 0;
$activeTokens = 0;
$usersList = [];
$auditLogs = [];

if ($mysqli) {
    // Total users
    $r = $mysqli->query("SELECT COUNT(*) FROM users");
    if ($r) $totalUsers = (int)$r->fetch_row()[0];

    // Total students
    $r = $mysqli->query("SELECT COUNT(*) FROM users WHERE role = 'STUDENT'");
    if ($r) $totalStudents = (int)$r->fetch_row()[0];

    // Total faculty
    $r = $mysqli->query("SELECT COUNT(*) FROM users WHERE role = 'FACULTY'");
    if ($r) $totalFaculty = (int)$r->fetch_row()[0];

    // Active remember-me tokens
    $r = $mysqli->query("SELECT COUNT(*) FROM remember_tokens WHERE expires_at > NOW()");
    if ($r) $activeTokens = (int)$r->fetch_row()[0];

    // User accounts list
    $resUsers = $mysqli->query("SELECT user_id, username, full_name, email, role, department, semester, is_active, last_login, created_at FROM users ORDER BY user_id ASC LIMIT 20");
    if ($resUsers) {
        while ($row = $resUsers->fetch_assoc()) {
            $usersList[] = $row;
        }
    }

    // Security audit logs
    $resLogs = $mysqli->query("SELECT log_id, event_type, identifier, status, message, ip_address, created_at FROM audit_logs ORDER BY log_id DESC LIMIT 15");
    if ($resLogs) {
        while ($row = $resLogs->fetch_assoc()) {
            $auditLogs[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Practical 10 - StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --admin-navy: #090d16;
            --admin-card-bg: #111827;
            --admin-accent: #38bdf8;
            --admin-border: #1f2937;
        }

        body {
            background: #0b0f19;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }

        .admin-nav {
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--admin-border);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .admin-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-brand span {
            background: linear-gradient(135deg, #f43f5e, #fb7185);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-tag {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fda4af;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            text-transform: uppercase;
        }

        .btn-logout {
            background: #ef4444;
            color: #ffffff;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #dc2626;
        }

        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 20px;
        }

        .admin-banner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
        }

        .admin-banner h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            margin: 0 0 6px 0;
            color: #ffffff;
        }

        .admin-banner p {
            margin: 0;
            color: #cbd5e1;
            font-size: 0.95rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .metric-card {
            background: #111827;
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .metric-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }

        .metric-val {
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: #ffffff;
        }

        .metric-title {
            color: #94a3b8;
            font-size: 0.84rem;
            font-weight: 600;
        }

        .admin-card {
            background: #111827;
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
        }

        .admin-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.3rem;
            margin: 0 0 16px 0;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }

        .admin-table th {
            background: #1f2937;
            color: #94a3b8;
            padding: 12px 14px;
            font-weight: 600;
            border-bottom: 1px solid #374151;
            white-space: nowrap;
        }

        .admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #1f2937;
            color: #e2e8f0;
        }

        .admin-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-role {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-admin {
            background: rgba(244, 63, 94, 0.2);
            color: #fda4af;
            border: 1px solid rgba(244, 63, 94, 0.4);
        }

        .badge-student {
            background: rgba(56, 189, 248, 0.2);
            color: #7dd3fc;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        .badge-faculty {
            background: rgba(168, 85, 247, 0.2);
            color: #d8b4fe;
            border: 1px solid rgba(168, 85, 247, 0.4);
        }

        .badge-status {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .status-success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .status-warning {
            background: rgba(234, 179, 8, 0.15);
            color: #facc15;
        }

        .status-blocked {
            background: rgba(217, 70, 239, 0.15);
            color: #f0abfc;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="admin-nav">
        <a href="index.html" class="admin-brand">
            &#128081; StudentHub <span>Admin Console</span>
        </a>
        <div style="display: flex; align-items: center; gap: 14px;">
            <a href="dashboard.php" style="color: #94a3b8; text-decoration: none; font-size: 0.85rem;">Student View</a>
            <a href="practical10_test.php" style="color: #38bdf8; text-decoration: none; font-size: 0.85rem; font-weight: 700;">Test Suite &rarr;</a>
            <div class="user-tag">&#128737; <?= htmlspecialchars($user['username']) ?> &bull; <?= htmlspecialchars($user['role']) ?></div>
            <a href="logout.php" class="btn-logout">&#10162; Log Out</a>
        </div>
    </header>

    <div class="container">
        <!-- Admin Welcome Banner -->
        <div class="admin-banner">
            <div>
                <h1>Welcome, Administrator <?= htmlspecialchars($user['full_name']) ?>!</h1>
                <p>Role-Based Access Control verified. You have unrestricted administrative privileges.</p>
            </div>
            <div>
                <a href="practical10_test.php" style="background: #38bdf8; color: #0c4a6e; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.9rem;">
                    Run Practical 10 Verification Suite &rarr;
                </a>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">&#128101;</div>
                <div>
                    <div class="metric-val"><?= $totalUsers ?></div>
                    <div class="metric-title">Total Users</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(74, 222, 128, 0.15); color: #4ade80;">&#127891;</div>
                <div>
                    <div class="metric-val"><?= $totalStudents ?></div>
                    <div class="metric-title">Registered Students</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">&#128104;&#8205;&#127979;</div>
                <div>
                    <div class="metric-val"><?= $totalFaculty ?></div>
                    <div class="metric-title">Faculty Staff</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon" style="background: rgba(251, 146, 60, 0.15); color: #fb923c;">&#128273;</div>
                <div>
                    <div class="metric-val"><?= $activeTokens ?></div>
                    <div class="metric-title">Active Remember-Me Tokens</div>
                </div>
            </div>
        </div>

        <!-- User Accounts Table -->
        <div class="admin-card">
            <h3>&#128196; System User Accounts &amp; Last-Login Timestamps</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Last Login (Extension)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($u['user_id']) ?></td>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php
                                    $roleBadge = 'badge-student';
                                    if ($u['role'] === 'ADMIN') $roleBadge = 'badge-admin';
                                    if ($u['role'] === 'FACULTY') $roleBadge = 'badge-faculty';
                                    ?>
                                    <span class="badge-role <?= $roleBadge ?>"><?= htmlspecialchars($u['role']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($u['department'] ?? 'General') ?></td>
                                <td style="font-size: 0.82rem; color: #38bdf8;">
                                    <?= !empty($u['last_login']) ? date('d M Y, h:i A', strtotime($u['last_login'])) : '<span style="color: #64748b;">Never</span>' ?>
                                </td>
                                <td>
                                    <span class="badge-status status-success">Active</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Security Audit Trail Table -->
        <div class="admin-card">
            <h3>&#128737; Real-Time Security Audit Trail (`audit_logs`)</h3>
            <p style="color: #94a3b8; font-size: 0.88rem; margin-top: 0;">
                All authentication events, session timeouts, role blockings, and remember-me tokens are logged relationally:
            </p>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>Event Type</th>
                            <th>Identifier</th>
                            <th>Status</th>
                            <th>Client IP</th>
                            <th>Message / Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <?php
                            $statusClass = 'status-success';
                            if ($log['status'] === 'FAILED') $statusClass = 'status-failed';
                            if ($log['status'] === 'WARNING') $statusClass = 'status-warning';
                            if ($log['status'] === 'BLOCKED') $statusClass = 'status-blocked';
                            ?>
                            <tr>
                                <td>#<?= htmlspecialchars($log['log_id']) ?></td>
                                <td style="white-space: nowrap; font-size: 0.82rem; color: #94a3b8;">
                                    <?= date('d M H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td><code><?= htmlspecialchars($log['event_type']) ?></code></td>
                                <td><?= htmlspecialchars($log['identifier']) ?></td>
                                <td>
                                    <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars($log['status']) ?></span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.82rem;"><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td style="font-size: 0.84rem;"><?= htmlspecialchars($log['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
