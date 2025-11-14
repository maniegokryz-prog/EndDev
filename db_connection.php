<?php
/**
 * Database Connection File
 * Include this file whenever you need database access
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

<<<<<<< HEAD
=======
// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://unpkg.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; connect-src \'self\' https://cdn.jsdelivr.net https://unpkg.com; img-src \'self\' data: blob:; font-src \'self\' https://cdn.jsdelivr.net;');

session_start();
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
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

<<<<<<< HEAD
=======
// Set MySQL timezone to match PHP timezone
$conn->query("SET time_zone = '+08:00'");

>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
// Optional: You can uncomment this for debugging during development
// echo "Connected successfully to database";
?>
