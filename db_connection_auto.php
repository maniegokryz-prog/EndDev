<?php
/**
 * Dual Database Connection
 * Automatically uses local DB on localhost, IONOS DB when deployed
 */

// Detect environment
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost', 
    ['localhost', '127.0.0.1', '::1', 'localhost:80']);

if ($isLocalhost) {
    // LOCALHOST CONFIGURATION
    $servername = "localhost";
    $username = "attendance_admin";
    $password = "Confirmp@ssword123";
    $dbname = "database_records";
} else {
    // IONOS PRODUCTION CONFIGURATION
    $servername = "db5019018805.hosting-data.io"; 
    $username = "dbu58088";
    $password = "Confirmp@ssword123";
    $dbname = "dbs14970485";
}

// Rest of your connection code stays the same
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
