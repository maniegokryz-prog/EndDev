<?php
// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json');

try {
    // Check dependencies
    $vendorPath = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        throw new Exception('Composer dependencies (vendor) not found. Please run composer install.');
    }
    require_once $vendorPath;

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['employee_id'])) {
        throw new Exception('Not authenticated');
    }

    $loggedInEmployeeId = $_SESSION['employee_id'];

    $dbPath = __DIR__ . '/../../db_connection.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database connection file not found.');
    }
    require_once $dbPath;

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
            throw new Exception('Invalid action');
    }

} catch (Throwable $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => 'File: ' . $e->getFile() . ' Line: ' . $e->getLine()
    ]);
}

/**
 * Send OTP to the logged-in user's email
 */
function sendOTP() {
    global $conn, $loggedInEmployeeId;
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    // Verify email matches logged-in user
    $stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, email FROM employees WHERE employee_id = ? AND email = ? AND status = 'active'");
    $stmt->bind_param("ss", $loggedInEmployeeId, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Email does not match your account');
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
            throw new Exception('Failed to send OTP email. Please check logs.');
        }
    } else {
        throw new Exception('Failed to generate OTP');
    }
}

/**
 * Verify OTP code
 */
function verifyOTP() {
    global $conn, $loggedInEmployeeId;
    
    $otp = $_POST['otp'] ?? '';
    
    if (empty($otp)) {
        throw new Exception('OTP is required');
    }
    
    // Debug: Check what's in the database
    $debugStmt = $conn->prepare("SELECT employee_id, otp, verified, expires_at FROM password_reset_otp WHERE employee_id = ?");
    $debugStmt->bind_param("s", $loggedInEmployeeId);
    $debugStmt->execute();
    $debugResult = $debugStmt->get_result();
    
    if ($debugResult->num_rows === 0) {
        throw new Exception('No OTP found for your account. Please request a new one.');
    }
    
    $debugRow = $debugResult->fetch_assoc();
    
    // Check if OTP matches
    if ($debugRow['otp'] !== $otp) {
        throw new Exception('Invalid OTP code');
    }
    
    // Check if already verified
    if ($debugRow['verified'] == 1) {
        throw new Exception('OTP already used. Please request a new one.');
    }
    
    // Check if expired
    if (strtotime($debugRow['expires_at']) <= time()) {
        throw new Exception('OTP has expired. Please request a new one.');
    }
    
    // Check OTP
    $stmt = $conn->prepare("SELECT * FROM password_reset_otp WHERE employee_id = ? AND otp = ? AND verified = 0 AND expires_at > NOW()");
    $stmt->bind_param("ss", $loggedInEmployeeId, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired OTP');
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
        throw new Exception('All fields are required');
    }
    
    if ($newPassword !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Verify OTP was verified
    $stmt = $conn->prepare("SELECT * FROM password_reset_otp WHERE employee_id = ? AND verified = 1 AND expires_at > NOW()");
    $stmt->bind_param("s", $loggedInEmployeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('OTP verification required');
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
        throw new Exception('Failed to update password');
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
    try {
        $mail = new PHPMailer(true); // Using the imported class name

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
        logMessage("OTP sent to {$toEmail}", 'success');
        
        return true;
    } catch (PHPMailerException $e) {
        // Log error
        logMessage("Failed to send OTP to {$toEmail}: " . $e->getMessage(), 'error');
        return false;
    } catch (Exception $e) {
        logMessage("General error sending OTP to {$toEmail}: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Helper to log messages
 */
function logMessage($message, $type = 'info') {
    $logFile = __DIR__ . '/../../logs/password_change_' . $type . '.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0777, true)) {
            return; // Silently fail if can't create directory
        }
    }
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}
?>
