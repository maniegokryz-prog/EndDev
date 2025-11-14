<?php
/**
 * Database Connection File for APIs
 * Use this for API endpoints that return JSON
 * This version does NOT set security headers or start sessions prematurely
 */

// Suppress all warnings
error_reporting(0);
ini_set('display_errors', 0);

// Start session if not already started (without warnings)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// CSRF token
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
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>
