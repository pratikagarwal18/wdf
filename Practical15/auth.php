<?php
require_once __DIR__ . '/db.php';

function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('STUDENTHUB_SESSID');
        session_start();
    }
}

function isLoggedIn() {
    initSecureSession();
    return !empty($_SESSION['user_id']);
}

function requireLogin($redirectAfter = null) {
    if (!isLoggedIn()) {
        header("Location: login.php?error=unauthorized");
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

function loginUser($user) {
    initSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = strtoupper($user['role']);
    $_SESSION['department'] = $user['department'] ?? 'General';
    $_SESSION['semester'] = (int)($user['semester'] ?? 1);
    $_SESSION['login_time'] = time();
    updateUserLastLogin($user['user_id']);
}

function logoutUser($redirect = true) {
    initSecureSession();
    $_SESSION = [];
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
        case 'ADMIN': return 'admin_dashboard.php';
        case 'FACULTY': return 'faculty_dashboard.php';
        default: return 'dashboard.php';
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'user_id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'User',
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'STUDENT',
        'department' => $_SESSION['department'] ?? 'General',
        'semester' => $_SESSION['semester'] ?? 1,
        'login_time' => $_SESSION['login_time'] ?? time()
    ];
}
