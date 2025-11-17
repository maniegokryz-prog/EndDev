<?php
/**
 * Password Validation Endpoint
 * Validates password strength and requirements
 */

// Start session first
session_start();

header('Content-Type: application/json');

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

// Validate password
$password = isset($data['password']) ? $data['password'] : '';

if (empty($password)) {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Password is required'
    ]);
    exit;
}

// Password validation rules
$errors = [];
$isValid = true;

// Minimum length check
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long';
    $isValid = false;
}

// Maximum length check
if (strlen($password) > 255) {
    $errors[] = 'Password must not exceed 255 characters';
    $isValid = false;
}

// Check for at least one uppercase letter
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter';
    $isValid = false;
}

// Check for at least one lowercase letter
if (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter';
    $isValid = false;
}

// Check for at least one number
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number';
    $isValid = false;
}

// Check for at least one special character
if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $errors[] = 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>)';
    $isValid = false;
}

// Check for common weak passwords
$weakPasswords = ['password', 'password123', '12345678', 'qwerty123', 'admin123', 'letmein', 'welcome123'];
if (in_array(strtolower($password), $weakPasswords)) {
    $errors[] = 'This password is too common. Please choose a stronger password';
    $isValid = false;
}

// Return validation result
if ($isValid) {
    echo json_encode([
        'success' => true,
        'valid' => true,
        'message' => 'Password meets all requirements',
        'strength' => calculatePasswordStrength($password)
    ]);
} else {
    echo json_encode([
        'success' => true,
        'valid' => false,
        'message' => implode('. ', $errors),
        'errors' => $errors
    ]);
}

/**
 * Calculate password strength score
 * @param string $password
 * @return string weak|medium|strong
 */
function calculatePasswordStrength($password) {
    $score = 0;
    
    // Length score
    if (strlen($password) >= 8) $score++;
    if (strlen($password) >= 12) $score++;
    if (strlen($password) >= 16) $score++;
    
    // Character variety score
    if (preg_match('/[a-z]/', $password)) $score++;
    if (preg_match('/[A-Z]/', $password)) $score++;
    if (preg_match('/[0-9]/', $password)) $score++;
    if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $score++;
    
    // Multiple numbers or special chars
    if (preg_match_all('/[0-9]/', $password) >= 2) $score++;
    if (preg_match_all('/[!@#$%^&*(),.?":{}|<>]/', $password) >= 2) $score++;
    
    if ($score <= 3) return 'weak';
    if ($score <= 6) return 'medium';
    return 'strong';
}
