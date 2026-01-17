<?php
/**
 * Test endpoint to check if PHP is accessible
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

session_start();

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'session_logged_in' => $_SESSION['logged_in'] ?? false,
    'post_data' => $_POST,
    'request_method' => $_SERVER['REQUEST_METHOD']
]);
