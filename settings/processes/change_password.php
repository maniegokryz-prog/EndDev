<?php
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$loggedInEmployeeId = $_SESSION['employee_id'];

require_once '../../db_connection.php';

// Determine action
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send_otp':
        sendOTP();
        break;
    case 'verify_otp':
        verifyOTP();
        break;
    case 'change_password':
        changePassword();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Send OTP to the logged-in user's email
 */
function sendOTP() {
    global $conn, $loggedInEmployeeId;
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        return;
    }
    
    // Verify email matches logged-in user
    $stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, email FROM employees WHERE employee_id = ? AND email = ? AND status = 'active'");
    $stmt->bind_param("ss", $loggedInEmployeeId, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Email does not match your account']);
        return;
    }
    
    $employee = $result->fetch_assoc();
    $employeeName = trim($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']);
    
    // Ensure OTP table exists
    ensureOTPTable();
    
    // Generate 6-digit OTP
    $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    
    // Delete any existing OTP for this employee
    $deleteStmt = $conn->prepare("DELETE FROM password_reset_otp WHERE employee_id = ?");
    $deleteStmt->bind_param("s", $loggedInEmployeeId);
    $deleteStmt->execute();
    
    // Insert new OTP (use NOW() + INTERVAL to avoid timezone issues)
    $insertStmt = $conn->prepare("INSERT INTO password_reset_otp (employee_id, otp, email, expires_at, verified) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)");
    $insertStmt->bind_param("sss", $loggedInEmployeeId, $otp, $email);
    
    if ($insertStmt->execute()) {
        // Send OTP email
        if (sendOTPEmail($email, $employeeName, $otp)) {
            echo json_encode(['success' => true, 'employee_id' => $loggedInEmployeeId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send OTP email. Please check logs.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to generate OTP']);
    }
}

/**
 * Verify OTP code
 */
function verifyOTP() {
    global $conn, $loggedInEmployeeId;
    
    $otp = $_POST['otp'] ?? '';
    
    if (empty($otp)) {
        echo json_encode(['success' => false, 'error' => 'OTP is required']);
        return;
    }
    
    // Debug: Check what's in the database
    $debugStmt = $conn->prepare("SELECT employee_id, otp, verified, expires_at FROM password_reset_otp WHERE employee_id = ?");
    $debugStmt->bind_param("s", $loggedInEmployeeId);
    $debugStmt->execute();
    $debugResult = $debugStmt->get_result();
    
    if ($debugResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No OTP found for your account. Please request a new one.']);
        return;
    }
    
    $debugRow = $debugResult->fetch_assoc();
    
    // Check if OTP matches
    if ($debugRow['otp'] !== $otp) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP code']);
        return;
    }
    
    // Check if already verified
    if ($debugRow['verified'] == 1) {
        echo json_encode(['success' => false, 'error' => 'OTP already used. Please request a new one.']);
        return;
    }
    
    // Check if expired
    if (strtotime($debugRow['expires_at']) <= time()) {
        echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
        return;
    }
    
    // Check OTP
    $stmt = $conn->prepare("SELECT * FROM password_reset_otp WHERE employee_id = ? AND otp = ? AND verified = 0 AND expires_at > NOW()");
    $stmt->bind_param("ss", $loggedInEmployeeId, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
        return;
    }
    
    // Mark OTP as verified
    $updateStmt = $conn->prepare("UPDATE password_reset_otp SET verified = 1 WHERE employee_id = ? AND otp = ?");
    $updateStmt->bind_param("ss", $loggedInEmployeeId, $otp);
    $updateStmt->execute();
    
    echo json_encode(['success' => true]);
}

/**
 * Change password after OTP verification
 */
function changePassword() {
    global $conn, $loggedInEmployeeId;
    
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Verify OTP was verified
    $stmt = $conn->prepare("SELECT * FROM password_reset_otp WHERE employee_id = ? AND verified = 1 AND expires_at > NOW()");
    $stmt->bind_param("s", $loggedInEmployeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'OTP verification required']);
        return;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $conn->prepare("UPDATE employees SET employee_password = ? WHERE employee_id = ?");
    $updateStmt->bind_param("ss", $hashedPassword, $loggedInEmployeeId);
    
    if ($updateStmt->execute()) {
        // Delete used OTP
        $deleteStmt = $conn->prepare("DELETE FROM password_reset_otp WHERE employee_id = ?");
        $deleteStmt->bind_param("s", $loggedInEmployeeId);
        $deleteStmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update password']);
    }
}

/**
 * Ensure password_reset_otp table exists
 */
function ensureOTPTable() {
    global $conn;
    
    $createTableSQL = "CREATE TABLE IF NOT EXISTS password_reset_otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(50) NOT NULL,
        otp VARCHAR(6) NOT NULL,
        email VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->query($createTableSQL);
}

/**
 * Send OTP email using PHPMailer with IONOS SMTP
 */
function sendOTPEmail($toEmail, $employeeName, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.ionos.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'accounts@bpcfaceid.com';
        $mail->Password = 'Confirmp@ssword123';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('accounts@bpcfaceid.com', 'BPC Face ID System');
        $mail->addAddress($toEmail, $employeeName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Change Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #083c34;'>Password Change Verification</h2>
                <p>Hello {$employeeName},</p>
                <p>You requested to change your password. Use the verification code below:</p>
                <div style='background-color: #f4f4f4; padding: 20px; text-align: center; margin: 20px 0;'>
                    <h1 style='color: #083c34; letter-spacing: 5px; margin: 0;'>{$otp}</h1>
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p>If you did not request this, please ignore this email.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>BPC Face ID Attendance System</p>
            </div>
        ";
        $mail->AltBody = "Hello {$employeeName},\n\nYour password change verification code is: {$otp}\n\nThis code will expire in 10 minutes.";
        
        $mail->send();
        
        // Log success
        $logFile = __DIR__ . '/../../logs/password_change_success.log';
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - OTP sent to {$toEmail}\n", FILE_APPEND);
        
        return true;
    } catch (Exception $e) {
        // Log error
        $logFile = __DIR__ . '/../../logs/password_change_errors.log';
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to send OTP to {$toEmail}: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        
        return false;
    }
}
?>
