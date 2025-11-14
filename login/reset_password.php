<?php
/**
 * Forgot Password Step 3: Reset Password
 */
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newPassword = trim($input['newPassword'] ?? '');
$confirmPassword = trim($input['confirmPassword'] ?? '');

if (empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all fields']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    echo json_encode(['success' => false, 'message' => 'OTP not verified']);
    exit;
}

if (!isset($_SESSION['reset_user_type']) || !isset($_SESSION['reset_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Check verification timeout (5 minutes)
if ((time() - $_SESSION['otp_verified_time']) > 300) {
    echo json_encode(['success' => false, 'message' => 'Verification expired']);
    exit;
}

try {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $userType = $_SESSION['reset_user_type'];
    $userId = $_SESSION['reset_user_id'];
    
    if ($userType === 'admin') {
        $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE employees SET employee_password = ? WHERE id = ?");
    }
    
    $stmt->bind_param('si', $passwordHash, $userId);
    
    if ($stmt->execute()) {
        // Clear session
        unset($_SESSION['reset_otp']);
        unset($_SESSION['reset_otp_time']);
        unset($_SESSION['reset_otp_attempts']);
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_employee_id']);
        unset($_SESSION['reset_user_type']);
        unset($_SESSION['otp_verified']);
        unset($_SESSION['otp_verified_time']);
        
        echo json_encode(['success' => true, 'message' => 'Password reset successful']);
    } else {
        throw new Exception('Update failed');
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
}

$conn->close();
?>
