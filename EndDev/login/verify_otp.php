<?php
/**
 * Forgot Password Step 2: Verify OTP
 */
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$otp = trim($input['otp'] ?? '');

if (empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Please enter OTP']);
    exit;
}

if (!isset($_SESSION['reset_otp'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Start over.']);
    exit;
}

// Check expiry (10 minutes)
if ((time() - $_SESSION['reset_otp_time']) > 600) {
    unset($_SESSION['reset_otp']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Request new code.']);
    exit;
}

// Check attempts
if (!isset($_SESSION['reset_otp_attempts'])) {
    $_SESSION['reset_otp_attempts'] = 0;
}

if ($_SESSION['reset_otp_attempts'] >= 5) {
    unset($_SESSION['reset_otp']);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Start over.']);
    exit;
}

// Verify OTP
if ($otp === $_SESSION['reset_otp']) {
    $_SESSION['otp_verified'] = true;
    $_SESSION['otp_verified_time'] = time();
    echo json_encode(['success' => true, 'message' => 'OTP verified']);
} else {
    $_SESSION['reset_otp_attempts']++;
    $remaining = 5 - $_SESSION['reset_otp_attempts'];
    echo json_encode(['success' => false, 'message' => "Invalid OTP. $remaining attempt(s) remaining."]);
}
?>
