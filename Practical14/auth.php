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

function getDynamicMenu($role) {
    $role = strtoupper($role);
    if ($role === 'ADMIN') {
        return [
            ['label' => 'Admin Dashboard', 'url' => 'practical14_dashboard.php', 'icon' => '&#128202;', 'active' => true],
            ['label' => 'Student CRUD (P11)', 'url' => 'practical11_students.php', 'icon' => '&#128101;', 'active' => false],
            ['label' => 'Event Management (P12)', 'url' => 'practical12_events.php', 'icon' => '&#127881;', 'active' => false],
            ['label' => 'AJAX CRUD (P13)', 'url' => 'practical13_ajax.php', 'icon' => '&#9889;', 'active' => false],
            ['label' => 'Security Audit Logs', 'url' => '#audit-log-section', 'icon' => '&#128220;', 'active' => false],
            ['label' => 'User Accounts', 'url' => 'admin_dashboard.php', 'icon' => '&#128100;', 'active' => false]
        ];
    } elseif ($role === 'FACULTY') {
        return [
            ['label' => 'Faculty Dashboard', 'url' => 'faculty_dashboard.php', 'icon' => '&#127979;', 'active' => true],
            ['label' => 'Student Directory', 'url' => 'view_records.php', 'icon' => '&#128218;', 'active' => false],
            ['label' => 'Campus Events', 'url' => 'practical12_events.php', 'icon' => '&#128197;', 'active' => false],
            ['label' => 'Attendance Roster', 'url' => 'attendance.html', 'icon' => '&#9989;', 'active' => false]
        ];
    } else {
        return [
            ['label' => 'Student Dashboard', 'url' => 'dashboard.php', 'icon' => '&#127891;', 'active' => true],
            ['label' => 'My Registrations', 'url' => '#registrations', 'icon' => '&#127915;', 'active' => false],
            ['label' => 'Browse Events', 'url' => 'practical12_events.php', 'icon' => '&#127775;', 'active' => false],
            ['label' => 'My Profile', 'url' => 'profile.html', 'icon' => '&#128100;', 'active' => false]
        ];
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
