<?php
/**
 * Password Recovery API
 * Handles OTP generation and password reset
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'verify_account':
            verifyAccount($conn);
            break;
            
        case 'verify_otp':
            verifyOTP($conn);
            break;
            
        case 'reset_password':
            resetPassword($conn);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();

/**
 * Verify account and send OTP
 */
function verifyAccount($conn) {
    $employee_id = $_POST['employee_id'] ?? '';
    $contact = $_POST['contact'] ?? ''; // Email or phone
    
    if (empty($employee_id) || empty($contact)) {
        throw new Exception('Employee ID and email/contact are required');
    }
    
    // Check if user exists
    $sql = "SELECT id, employee_id, email, phone, first_name, last_name 
            FROM employees 
            WHERE employee_id = ? AND (email = ? OR phone = ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $employee_id, $contact, $contact);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Account not found. Please verify your ID and email/contact.');
    }
    
    $user = $result->fetch_assoc();
    
    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP in database
    ensureOTPTable($conn);
    
    // Delete old OTPs for this user
    $sql = "DELETE FROM password_reset_otp WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    
    // Insert new OTP
    $sql = "INSERT INTO password_reset_otp (employee_id, otp, contact, expires_at) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $employee_id, $otp, $contact, $expires_at);
    $stmt->execute();
    
    // Send OTP (in production, use SMS/Email service)
    // For now, we'll just return it in the response (REMOVE IN PRODUCTION)
    sendOTP($contact, $otp, $user['first_name']);
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your ' . (filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone'),
        'otp' => $otp, // REMOVE THIS IN PRODUCTION
        'user_name' => $user['first_name']
    ]);
}

/**
 * Verify OTP code
 */
function verifyOTP($conn) {
    $employee_id = $_POST['employee_id'] ?? '';
    $otp = $_POST['otp'] ?? '';
    
    if (empty($employee_id) || empty($otp)) {
        throw new Exception('Employee ID and OTP are required');
    }
    
    // Check OTP
    $sql = "SELECT id, employee_id, otp, expires_at, verified 
            FROM password_reset_otp 
            WHERE employee_id = ? AND otp = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $employee_id, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid OTP code');
    }
    
    $otp_record = $result->fetch_assoc();
    
    // Check if expired
    if (strtotime($otp_record['expires_at']) < time()) {
        throw new Exception('OTP has expired. Please request a new one.');
    }
    
    // Mark as verified
    $sql = "UPDATE password_reset_otp SET verified = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $otp_record['id']);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully',
        'employee_id' => $employee_id
    ]);
}

/**
 * Reset password
 */
function resetPassword($conn) {
    $employee_id = $_POST['employee_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($employee_id) || empty($new_password) || empty($confirm_password)) {
        throw new Exception('All fields are required');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }
    
    if (strlen($new_password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }
    
    // Check if OTP was verified
    $sql = "SELECT id FROM password_reset_otp 
            WHERE employee_id = ? AND verified = 1 
            ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('OTP verification required');
    }
    
    // Hash password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $sql = "UPDATE employees SET employee_password = ? WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $employee_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update password');
    }
    
    // Delete used OTP
    $sql = "DELETE FROM password_reset_otp WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
}

/**
 * Create OTP table if not exists
 */
function ensureOTPTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(255) NOT NULL,
        otp VARCHAR(10) NOT NULL,
        contact VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
}

/**
 * Send OTP via SMS/Email (mock function)
 */
function sendOTP($contact, $otp, $name) {
    // In production, integrate with SMS/Email service
    // For example: Twilio, SendGrid, PHPMailer, etc.
    
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        // Send via email
        // mail($contact, "Password Reset OTP", "Your OTP is: $otp");
    } else {
        // Send via SMS
        // SMSProvider::send($contact, "Your OTP is: $otp");
    }
    
    // For development, just log it
    error_log("OTP for $name ($contact): $otp");
}
?>
