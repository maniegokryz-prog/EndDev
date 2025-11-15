<?php
/**
 * Logout Handler
 * Destroys session and redirects to login page
 */

session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header('Location: ../login/login.php?message=logged_out');
exit();
?>
