<?php
/**
 * Practical 10: User Login & Session Authentication
 * Beginner-level, clean PHP implementation
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: " . getRoleRedirectUrl($_SESSION['role'] ?? 'STUDENT'));
    exit;
}

$error = null;
$notice = null;
$identifier = '';

// Handle logout message
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $notice = "You have been logged out successfully.";
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $mysqli = getMySQLiConnection(true);
        if (!$mysqli) {
            $error = "Database connection failed. Ensure MySQL is running in XAMPP.";
        } else {
            // Prepared statement to prevent SQL injection
            $stmt = $mysqli->prepare("SELECT user_id, username, full_name, email, password_hash, role, is_active FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    // Verify hashed password
                    if (password_verify($password, $user['password_hash'])) {
                        if ($user['is_active']) {
                            loginUser($user);
                            header("Location: " . getRoleRedirectUrl($user['role']));
                            exit;
                        } else {
                            $error = "Your account has been deactivated. Please contact the administrator.";
                        }
                    } else {
                        $error = "Invalid password. Please check your credentials.";
                    }
                } else {
                    $error = "No user found with that username or email.";
                }
                $stmt->close();
            } else {
                $error = "Query preparation failed: " . $mysqli->error;
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
    <title>StudentHub Login - Practical 10</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-box {
            max-width: 440px;
            margin: 40px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
        }
        .form-field {
            margin-bottom: 16px;
        }
        .form-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .form-field input {
            width: 100%;
            max-width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-notice {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .demo-box {
            margin-top: 20px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px dashed var(--line);
            font-size: 0.85rem;
        }
        .demo-btn {
            background: #e2e8f0;
            color: #1e293b;
            padding: 4px 10px;
            font-size: 0.8rem;
            margin: 4px 2px;
            border-radius: 4px;
            box-shadow: none;
            display: inline-block;
        }
        .demo-btn:hover {
            background: #cbd5e1;
        }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="register.php">Practical 9 Registration</a> |
            <a href="practical8_test.php">Practical 8</a> |
            <a href="practical11_students.php">Practical 11 Students</a>
        </nav>
    </div>

    <div class="login-box">
        <h2 style="margin-top: 0; text-align: center;">Sign In</h2>
        <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
            Practical 10: Secure Session Authentication
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">&#9888; <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($notice): ?>
            <div class="alert alert-notice">&#10003; <?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="box-shadow: none; border: none; padding: 0; max-width: 100%;">
            <div class="form-field">
                <label for="identifier">Username or Email</label>
                <input type="text" id="identifier" name="identifier" value="<?= htmlspecialchars($identifier) ?>" required placeholder="e.g. aarav.sharma or admin.portal" autofocus>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 8px;">
                Sign In
            </button>
        </form>

        <div class="demo-box">
            <strong>1-Click Demo Accounts:</strong><br>
            <button type="button" class="demo-btn" onclick="fillCreds('admin.portal', 'Admin@123')">Admin</button>
            <button type="button" class="demo-btn" onclick="fillCreds('aarav.sharma', 'Student@123')">Student</button>
            <button type="button" class="demo-btn" onclick="fillCreds('dr.sharma', 'Faculty@123')">Faculty</button>
        </div>

        <p style="text-align: center; margin-top: 18px; font-size: 0.88rem;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>

    <script>
        function fillCreds(u, p) {
            document.getElementById('identifier').value = u;
            document.getElementById('password').value = p;
        }
    </script>

</body>
</html>
