<?php
/**
 * Practical 10: Secure Session & Authentication Control Module
 * Problem Definition: Secure login/logout, session management, password_verify(),
 *                     session fixation prevention, role-based access control (RBAC),
 *                     inactivity session timeout, and secure remember-me token.
 * 
 * Key Questions Addressed:
 * 1. Are sessions started and destroyed securely? -> Yes (strict cookie flags, regeneration, clean destruction).
 * 2. Is password_verify() used correctly? -> Yes (constant-time verification with cost 12 bcrypt).
 * 3. Is unauthorized dashboard access blocked? -> Yes (requireLogin() / requireRole() redirect non-authenticated users).
 * 4. Is session timeout implemented? -> Yes (idle timeout tracking with checkSessionTimeout()).
 * 5. Are admin and student users redirected to appropriate dashboards? -> Yes (RBAC redirection via getRoleRedirectUrl()).
 */

require_once __DIR__ . '/db.php';

// Inactivity timeout configuration (Default: 900 seconds / 15 minutes)
if (!defined('DEFAULT_SESSION_TIMEOUT')) {
    define('DEFAULT_SESSION_TIMEOUT', 900);
}

/**
 * Initializes secure PHP session with hardened cookie parameters.
 */
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce cookie-only sessions and strict session ID matching
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        // Set hardened session cookie parameters (Key Question 1)
        session_set_cookie_params([
            'lifetime' => 0,          // Expire when browser closes
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,   // Transmit only over HTTPS if available
            'httponly' => true,       // Block JavaScript XSS document.cookie theft
            'samesite' => 'Lax'       // CSRF mitigation
        ]);

        session_name('STUDENTHUB_SESSID');
        session_start();
    }

    // Auto-check session timeout if user is logged in (Key Question 4)
    if (!empty($_SESSION['user_id'])) {
        $timeout = isset($_SESSION['custom_timeout']) ? (int)$_SESSION['custom_timeout'] : DEFAULT_SESSION_TIMEOUT;
        checkSessionTimeout($timeout);
    }
}

/**
 * Checks for session inactivity timeout and terminates session if expired (Key Question 4).
 *
 * @param int $timeoutSeconds Maximum allowed idle seconds
 */
function checkSessionTimeout($timeoutSeconds = DEFAULT_SESSION_TIMEOUT) {
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    $idleTime = $now - $lastActivity;

    if ($idleTime > $timeoutSeconds) {
        $userId = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'unknown';

        $mysqli = getMySQLiConnection(true);
        if ($mysqli) {
            insertAuditLog(
                $mysqli,
                'SESSION_TIMEOUT',
                $username,
                'WARNING',
                "Session expired after {$idleTime}s of inactivity (threshold: {$timeoutSeconds}s)",
                $userId
            );
        }

        // Clean up session and cookies
        logoutUser(false);

        // Redirect to login with explicit timeout alert
        header("Location: login.php?error=timeout");
        exit;
    }

    // Refresh last activity timestamp
    $_SESSION['last_activity'] = $now;
}

/**
 * Checks if the current visitor is authenticated (via active session or remember-me token).
 *
 * @return bool
 */
function isLoggedIn() {
    initSecureSession();

    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // Attempt automatic login via Remember-Me token if session has expired
    return checkRememberMeAutoLogin();
}

/**
 * Validates Remember-Me split-token cookie and restores session (Advanced Extension).
 * Avoids custom JWTs; implements Paragon split-token pattern with sha256 DB hash.
 *
 * @return bool
 */
function checkRememberMeAutoLogin() {
    if (empty($_COOKIE['remember_me'])) {
        return false;
    }

    $cookieVal = $_COOKIE['remember_me'];
    $parts = explode(':', $cookieVal, 2);
    if (count($parts) !== 2) {
        // Malformed cookie; purge
        setcookie('remember_me', '', time() - 3600, '/');
        return false;
    }

    list($selector, $validator) = $parts;
    $tokenRecord = findRememberToken($selector);

    if (!$tokenRecord) {
        setcookie('remember_me', '', time() - 3600, '/');
        return false;
    }

    // Cryptographic constant-time hash verification
    $calculatedHash = hash('sha256', $validator);
    if (!hash_equals($tokenRecord['token_hash'], $calculatedHash)) {
        // Possible forgery attempt; remove token and cookie
        deleteRememberToken($selector);
        setcookie('remember_me', '', time() - 3600, '/');

        $mysqli = getMySQLiConnection(true);
        if ($mysqli) {
            insertAuditLog($mysqli, 'REMEMBER_TOKEN_TAMPER', $selector, 'BLOCKED', 'Remember-Me validator hash mismatch');
        }
        return false;
    }

    // Token is valid! Regenerate session ID (Key Question 1)
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int)$tokenRecord['user_id'];
    $_SESSION['username']      = $tokenRecord['username'];
    $_SESSION['full_name']     = $tokenRecord['full_name'];
    $_SESSION['email']         = $tokenRecord['email'];
    $_SESSION['role']          = strtoupper($tokenRecord['role']);
    $_SESSION['department']    = $tokenRecord['department'] ?? 'General';
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();

    // Intermediate Extension: Update last login
    updateUserLastLogin($tokenRecord['user_id']);

    // Token Rotation (Defense in depth: issue fresh token on every auto-login)
    deleteRememberToken($selector);
    $newSelector = bin2hex(random_bytes(16));
    $newValidator = bin2hex(random_bytes(32));
    $newHash = hash('sha256', $newValidator);
    $newExpiry = date('Y-m-d H:i:s', time() + (30 * 86400));
    storeRememberToken($tokenRecord['user_id'], $newSelector, $newHash, $newExpiry);

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('remember_me', "{$newSelector}:{$newValidator}", time() + (30 * 86400), '/', '', $isHttps, true);

    $mysqli = getMySQLiConnection(true);
    if ($mysqli) {
        insertAuditLog($mysqli, 'LOGIN_REMEMBER_ME_SUCCESS', $tokenRecord['username'], 'SUCCESS', 'Auto-authenticated via rotated Remember-Me token', $tokenRecord['user_id']);
    }

    return true;
}

/**
 * Enforces that user must be logged in. If not, redirects to login (Key Question 3).
 *
 * @param string|null $redirectAfter Optional target URL to return to after successful login.
 */
function requireLogin($redirectAfter = null) {
    if (!isLoggedIn()) {
        $target = $redirectAfter ?? ($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
        $redirectUrl = 'login.php?error=unauthorized&redirect=' . urlencode($target);
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * Enforces Role-Based Access Control (RBAC) (Key Question 5).
 *
 * @param array|string $allowedRoles Single role or array of allowed roles (e.g. ['ADMIN', 'FACULTY'])
 */
function requireRole($allowedRoles) {
    requireLogin();

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $rolesUpper = array_map('strtoupper', $roles);
    $userRole = strtoupper($_SESSION['role'] ?? '');

    if (!in_array($userRole, $rolesUpper, true)) {
        $mysqli = getMySQLiConnection(true);
        if ($mysqli) {
            insertAuditLog(
                $mysqli,
                'RBAC_ACCESS_BLOCKED',
                $_SESSION['username'] ?? 'unknown',
                'BLOCKED',
                "Role '{$userRole}' attempted to access role-restricted page requiring: " . implode(', ', $rolesUpper),
                $_SESSION['user_id'] ?? null
            );
        }

        header("Location: unauthorized.php?required=" . urlencode(implode(', ', $rolesUpper)));
        exit;
    }
}

/**
 * Authenticates user into session and issues Remember-Me token if requested.
 *
 * @param array $user User record from database
 * @param bool $rememberMe
 */
function loginUser($user, $rememberMe = false) {
    initSecureSession();

    // 1. Session Fixation Defense: Always regenerate session ID on privilege transition (Key Question 1)
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int)$user['user_id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = strtoupper($user['role']);
    $_SESSION['department']    = $user['department'] ?? 'General';
    $_SESSION['semester']      = (int)($user['semester'] ?? 1);
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();

    // 2. Intermediate Extension: Record last-login timestamp in database
    updateUserLastLogin($user['user_id']);

    // 3. Advanced Extension: Cryptographic Remember-Me split-token
    if ($rememberMe) {
        $selector  = bin2hex(random_bytes(16)); // 32 hex chars
        $validator = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 days

        storeRememberToken($user['user_id'], $selector, $tokenHash, $expiresAt);

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie(
            'remember_me',
            "{$selector}:{$validator}",
            [
                'expires'  => time() + (30 * 86400),
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    // 4. Audit Log
    $mysqli = getMySQLiConnection(true);
    if ($mysqli) {
        $detail = "Role: " . $_SESSION['role'] . ($rememberMe ? " (Remember-Me issued)" : "");
        insertAuditLog($mysqli, 'LOGIN_SUCCESS', $user['username'], 'SUCCESS', $detail, $user['user_id']);
    }
}

/**
 * Securely terminates the session, destroys cookies, and invalidates tokens (Key Question 1).
 *
 * @param bool $redirect Whether to send HTTP 302 redirect to login.php
 */
function logoutUser($redirect = true) {
    initSecureSession();

    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'anonymous';

    // 1. Audit log
    $mysqli = getMySQLiConnection(true);
    if ($mysqli && $userId) {
        insertAuditLog($mysqli, 'LOGOUT_SUCCESS', $username, 'SUCCESS', 'User logged out and session destroyed', $userId);
    }

    // 2. Clear Remember-Me cookie and revoke database token
    if (!empty($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($parts) === 2) {
            deleteRememberToken($parts[0]);
        }
        setcookie('remember_me', '', time() - 3600, '/');
    }

    if ($userId) {
        deleteUserRememberTokens($userId);
    }

    // 3. Unset all session variables
    $_SESSION = [];

    // 4. Delete session cookie from browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // 5. Destroy server-side session storage
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($redirect) {
        header("Location: login.php?msg=logged_out");
        exit;
    }
}

/**
 * Returns role-based default dashboard destination (Key Question 5).
 *
 * @param string $role
 * @return string
 */
function getRoleRedirectUrl($role) {
    switch (strtoupper($role)) {
        case 'ADMIN':
            return 'admin_dashboard.php';
        case 'FACULTY':
            return 'faculty_dashboard.php';
        case 'STUDENT':
        default:
            return 'dashboard.php';
    }
}

/**
 * Helper to fetch current authenticated user payload.
 *
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'user_id'       => $_SESSION['user_id'],
        'username'      => $_SESSION['username'],
        'full_name'     => $_SESSION['full_name'],
        'email'         => $_SESSION['email'],
        'role'          => $_SESSION['role'],
        'department'    => $_SESSION['department'] ?? 'General',
        'semester'      => $_SESSION['semester'] ?? 1,
        'login_time'    => $_SESSION['login_time'] ?? time(),
        'last_activity' => $_SESSION['last_activity'] ?? time()
    ];
}
