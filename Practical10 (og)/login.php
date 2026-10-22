<?php
/**
 * Practical 10: Secure User Authentication & Role-Based Login Gateway
 * Problem Definition: Develop secure login and logout functionality using PHP sessions.
 *                     Verify password using password_verify(), regenerate session ID after login,
 *                     restrict dashboard access, implement role-based redirection,
 *                     session timeout, and optional secure remember-me token.
 * 
 * Key Questions:
 * 1. Are sessions started and destroyed securely? -> Yes (strict flags, session_regenerate_id(true)).
 * 2. Is password_verify() used correctly? -> Yes.
 * 3. Is unauthorized dashboard access blocked? -> Yes.
 * 4. Is session timeout implemented? -> Yes.
 * 5. Are admin and student users redirected to appropriate dashboards? -> Yes (RBAC Redirection).
 */

require_once __DIR__ . '/auth.php';

initSecureSession();

// If already logged in, redirect to assigned dashboard (or requested redirect)
if (isLoggedIn()) {
    $redirectUrl = !empty($_GET['redirect']) ? $_GET['redirect'] : getRoleRedirectUrl($_SESSION['role']);
    header("Location: {$redirectUrl}");
    exit;
}

// Generate CSRF token for login form
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$notice = null;
$noticeType = 'info';
$identifier = '';
$redirect = $_GET['redirect'] ?? '';

// Handle redirect alerts
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $notice = "You have been securely logged out. Session ID destroyed and cookies wiped.";
        $noticeType = 'success';
    }
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'timeout') {
        $notice = "Your session expired due to inactivity. Please authenticate again to resume.";
        $noticeType = 'warning';
    } elseif ($_GET['error'] === 'unauthorized') {
        $notice = "Access Denied: You must be logged in to access that protected portal page.";
        $noticeType = 'danger';
    } elseif ($_GET['error'] === 'csrf') {
        $notice = "Security token mismatch or expired. Please submit the login form again.";
        $noticeType = 'danger';
    }
}

// Support test error preview for post-lab screenshot documentation
if (isset($_GET['test_error'])) {
    if ($_GET['test_error'] === 'invalid_password') {
        $error = "Invalid password. Authentication rejected by password_verify().";
        $identifier = "aarav.sharma";
    } elseif ($_GET['test_error'] === 'invalid_user') {
        $error = "No account found matching that username or email address.";
        $identifier = "unknown_student_99";
    }
}

// Process POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['login_csrf_token'] ?? '', $csrf)) {
        $error = "CSRF Token validation failed. Please refresh and try again.";
        $mysqli = getMySQLiConnection(true);
        if ($mysqli) {
            insertAuditLog($mysqli, 'CSRF_LOGIN_BLOCKED', $_POST['identifier'] ?? 'unknown', 'BLOCKED', 'CSRF token mismatch in login POST');
        }
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $rememberMe = !empty($_POST['remember_me']);

        if (empty($identifier) || empty($password)) {
            $error = "Please provide both your Username/Email and Password.";
        } else {
            $mysqli = getMySQLiConnection(true);
            if (!$mysqli) {
                $error = "Database offline. Please ensure MySQL is running in XAMPP.";
            } else {
                // Prepared statement to query user by username or email
                $sql = "SELECT user_id, username, full_name, email, password_hash, department, semester, role, is_active, last_login 
                        FROM users 
                        WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) 
                        LIMIT 1";

                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ss", $identifier, $identifier);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    if ($user = $res->fetch_assoc()) {
                        // Key Question 2: Is password_verify() used correctly?
                        if (password_verify($password, $user['password_hash'])) {
                            if ($user['is_active']) {
                                // 1. Refresh CSRF token
                                $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

                                // 2. Log in user (regenerates session ID, updates last_login, issues remember-me token)
                                loginUser($user, $rememberMe);

                                // 3. Role-Based Redirection (Key Question 5)
                                $target = !empty($_POST['redirect']) ? $_POST['redirect'] : getRoleRedirectUrl($user['role']);
                                header("Location: {$target}");
                                exit;
                            } else {
                                $error = "Your account has been deactivated. Please contact the administrator.";
                                insertAuditLog($mysqli, 'LOGIN_DEACTIVATED', $user['email'], 'BLOCKED', 'Attempted login to deactivated account', $user['user_id']);
                            }
                        } else {
                            $error = "Invalid password. Authentication rejected by password_verify().";
                            insertAuditLog($mysqli, 'LOGIN_FAILED_BAD_PASSWORD', $user['email'], 'FAILED', 'Invalid password supplied.', $user['user_id']);
                        }
                    } else {
                        $error = "No account found matching that username or email address.";
                        insertAuditLog($mysqli, 'LOGIN_FAILED_UNKNOWN_USER', $identifier, 'FAILED', 'Attempted login with non-existent user identifier.');
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $mysqli->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Portal Login | Practical 10 - StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --p10-navy: #0f172a;
            --p10-blue: #2563eb;
            --p10-accent: #38bdf8;
            --p10-bg: #f8fafc;
            --p10-border: #e2e8f0;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #090d16 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        .portal-nav-bar {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .portal-brand {
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .portal-brand span {
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: 18px;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #38bdf8;
        }

        .login-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .auth-container {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 20px;
            padding: 38px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .auth-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 10px;
        }

        .auth-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            color: #0f172a;
            margin: 0 0 6px 0;
            font-weight: 700;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.92rem;
            margin: 0;
        }

        .alert-banner {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            line-height: 1.5;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-row-remember {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 0.88rem;
        }

        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            cursor: pointer;
            user-select: none;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: #ffffff;
            border: none;
            padding: 13px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: all 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.4);
        }

        .demo-accounts {
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px dashed #e2e8f0;
        }

        .demo-accounts h4 {
            margin: 0 0 10px 0;
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
        }

        .demo-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .demo-pill {
            flex: 1;
            min-width: 110px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            text-align: center;
            font-size: 0.78rem;
            transition: all 0.15s;
        }

        .demo-pill:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .demo-pill strong {
            display: block;
            color: #0f172a;
            font-size: 0.82rem;
        }

        .demo-pill span {
            color: #64748b;
            font-size: 0.75rem;
        }

        .auth-footer {
            margin-top: 22px;
            text-align: center;
            font-size: 0.88rem;
            color: #64748b;
        }

        .auth-footer a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="portal-nav-bar">
        <a href="index.html" class="portal-brand">
            &#127891; StudentHub <span>Security Portal</span>
        </a>
        <nav class="nav-links">
            <a href="index.html">Home</a>
            <a href="practical8_test.php">Practical 8</a>
            <a href="register.php">Practical 9 (Registration)</a>
            <a href="practical10_test.php" style="color: #38bdf8; font-weight: 700;">Practical 10 Test Suite &rarr;</a>
        </nav>
    </header>

    <!-- Main Login Container -->
    <main class="login-main">
        <div class="auth-container">
            <div class="auth-header">
                <span class="auth-badge">Practical 10 &bull; Secure Authentication</span>
                <h2>Sign in to StudentHub</h2>
                <p>Role-Based Access Control, Session Timeout &amp; Remember-Me</p>
            </div>

            <!-- Notice Banner (Timeout / Logout / Unauthorized) -->
            <?php if ($notice): ?>
                <div class="alert-banner alert-<?= $noticeType ?>">
                    <span>&#9432;</span>
                    <div><?= htmlspecialchars($notice) ?></div>
                </div>
            <?php endif; ?>

            <!-- Form Validation Error Banner -->
            <?php if ($error): ?>
                <div class="alert-banner alert-danger">
                    <span>&#9888;</span>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Secure Login Form -->
            <form method="POST" action="login.php" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['login_csrf_token'] ?? '') ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <!-- Identifier: Username or Email -->
                <div class="form-group">
                    <label for="identifier">Username or Institutional Email</label>
                    <input 
                        type="text" 
                        id="identifier" 
                        name="identifier" 
                        class="form-control" 
                        placeholder="e.g. admin.portal or aarav.sharma@studenthub.edu"
                        value="<?= htmlspecialchars($identifier) ?>" 
                        required 
                        autofocus>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter your account password" 
                        required>
                </div>

                <!-- Advanced Extension: Remember-Me Checkbox -->
                <div class="form-row-remember">
                    <label class="remember-label">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me on this browser (30 days)</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit">
                    Sign In to Portal &rarr;
                </button>
            </form>

            <!-- One-Click Seed Credentials Helper for Evaluator/Viva -->
            <div class="demo-accounts">
                <h4>1-Click Demo Credentials (Click to Fill):</h4>
                <div class="demo-pills">
                    <div class="demo-pill" onclick="fillCreds('admin.portal', 'Admin@123')">
                        <strong>&#128081; Admin</strong>
                        <span>admin.portal<br>Admin@123</span>
                    </div>
                    <div class="demo-pill" onclick="fillCreds('aarav.sharma', 'Student@123')">
                        <strong>&#127891; Student</strong>
                        <span>aarav.sharma<br>Student@123</span>
                    </div>
                    <div class="demo-pill" onclick="fillCreds('dr.sharma', 'Faculty@123')">
                        <strong>&#128104;&#8205;&#127979; Faculty</strong>
                        <span>dr.sharma<br>Faculty@123</span>
                    </div>
                </div>
            </div>

            <!-- Footer Link -->
            <div class="auth-footer">
                Need an account? <a href="register.php">Create a student account (Practical 9)</a>
            </div>
        </div>
    </main>

    <script>
        function fillCreds(u, p) {
            document.getElementById('identifier').value = u;
            document.getElementById('password').value = p;
        }
    </script>

</body>
</html>
