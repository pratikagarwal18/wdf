<?php

require_once __DIR__ . '/db.php';

if (!defined('DEFAULT_SESSION_TIMEOUT')) {
    define('DEFAULT_SESSION_TIMEOUT', 900);
}

function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_name('STUDENTHUB_SESSID');
        session_start();
    }

    if (!empty($_SESSION['user_id'])) {
        $timeout = isset($_SESSION['custom_timeout']) ? (int)$_SESSION['custom_timeout'] : DEFAULT_SESSION_TIMEOUT;
        checkSessionTimeout($timeout);
    }
}

function checkSessionTimeout($timeoutSeconds = DEFAULT_SESSION_TIMEOUT) {
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    $idleTime = $now - $lastActivity;

    if ($idleTime > $timeoutSeconds) {
        logoutUser(false);
        header("Location: login.php?error=timeout");
        exit;
    }

    $_SESSION['last_activity'] = $now;
}

function isLoggedIn() {
    initSecureSession();
    return !empty($_SESSION['user_id']);
}

function requireLogin($redirectAfter = null) {
    if (!isLoggedIn()) {
        $target = $redirectAfter ?? ($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
        $redirectUrl = 'login.php?error=unauthorized&redirect=' . urlencode($target);
        header("Location: {$redirectUrl}");
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $rolesUpper = array_map('strtoupper', $roles);
    $userRole = strtoupper($_SESSION['role'] ?? '');

    if (!in_array($userRole, $rolesUpper, true)) {
        header("Location: unauthorized.php?required=" . urlencode(implode(', ', $rolesUpper)));
        exit;
    }
}

function loginUser($user, $rememberMe = false) {
    initSecureSession();

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

    updateUserLastLogin($user['user_id']);
}

function logoutUser($redirect = true) {
    initSecureSession();

    $_SESSION = [];

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

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($redirect) {
        header("Location: login.php?msg=logged_out");
        exit;
    }
}

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

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        initSecureSession();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        initSecureSession();
    }
    if (empty($_SESSION['csrf_token']) || empty($token) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
