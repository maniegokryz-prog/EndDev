<?php
/**
 * Session Protection
 * Include this at the top of protected pages
 */
session_start();

// Check if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login/login.php');
    exit;
}

// Session timeout (30 minutes)
$timeout = 1800;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ../login/login.php?timeout=1');
    exit;
}

// Update activity time
$_SESSION['login_time'] = time();

// Helper functions
function isAdmin() {
    return $_SESSION['user_type'] === 'admin';
}

function isEmployee() {
    return $_SESSION['user_type'] === 'employee';
}

function getUserName() {
    if ($_SESSION['user_type'] === 'admin') {
        return $_SESSION['username'] ?? 'Admin';
    } else {
        return $_SESSION['employee_name'] ?? 'User';
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>
