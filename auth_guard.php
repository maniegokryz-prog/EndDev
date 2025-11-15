<?php
/**
 * Authentication Guard
 * Include this file at the top of protected pages to ensure user is logged in
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: ../login/login.php');
    exit();
}

// Check session timeout (30 minutes)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    // Session expired
    session_unset();
    session_destroy();
    header('Location: ../login/login.php?error=session_expired');
    exit();
}

// Update last activity time
$_SESSION['login_time'] = time();

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to require admin access
function requireAdmin() {
    if (!isAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Admin privileges required.');
    }
}

// Function to get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'employee_id' => $_SESSION['employee_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'user',
        'email' => $_SESSION['user_email'] ?? null,
        'department' => $_SESSION['department'] ?? null,
        'position' => $_SESSION['position'] ?? null,
        'profile_photo' => $_SESSION['profile_photo'] ?? null
    ];
}
?>
