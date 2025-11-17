<?php
/**
 * Email Validation Endpoint
 * Checks if an email address is already in use
 */

// Start session first
session_start();

require_once '../../db_connection.php';

header('Content-Type: application/json');

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit;
}

// Verify CSRF token
if (!isset($data['csrf_token']) || !isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

// Validate email format
$email = isset($data['email']) ? trim($data['email']) : '';

if (empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'available' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

try {
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
    if (!$stmt) {
        error_log("Email validation - Prepare failed: " . $conn->error);
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        error_log("Email validation - Execute failed: " . $stmt->error);
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    
    $stmt->close();
    
    // Return availability status
    echo json_encode([
        'success' => true,
        'available' => !$exists,
        'message' => $exists ? 'Email already in use' : 'Email is available'
    ]);
    
} catch (Exception $e) {
    error_log("Email validation error: " . $e->getMessage());
    error_log("Email validation stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'debug' => $e->getMessage() // Remove this in production
    ]);
}
