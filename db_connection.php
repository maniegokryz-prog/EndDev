<?php
/**
 * Database Connection File
 * Include this file whenever you need database access
 */

// Don't override error settings if they're already configured
// This allows API files to control their own error display settings
if (!isset($GLOBALS['error_reporting_configured'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Changed to 0 for production safety
    ini_set('log_errors', 1);
}

// Set timezone to Philippine Time
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Manila');
}

// Only set headers if not already sent (API files may need different headers)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://unpkg.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; connect-src \'self\' https://cdn.jsdelivr.net https://unpkg.com; img-src \'self\' data: blob:; font-src \'self\' https://cdn.jsdelivr.net;');
}

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "Confirmp@ssword123";
$dbname = "database_records";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // For API endpoints, throw exception instead of die()
    if (isset($GLOBALS['error_reporting_configured'])) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Set MySQL timezone to match PHP timezone
$conn->query("SET time_zone = '+08:00'");

// Optional: You can uncomment this for debugging during development
// echo "Connected successfully to database";
?>
