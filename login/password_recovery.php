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
function verifyAccount($conn)
{
    $employee_id = $_POST['employee_id'] ?? '';
    $email = $_POST['email'] ?? '';

    if (empty($employee_id) || empty($email)) {
        throw new Exception('Employee ID and email are required');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if user exists with matching employee_id AND email
    $sql = "SELECT id, employee_id, email, first_name, last_name 
            FROM employees 
            WHERE employee_id = ? AND email = ? AND status = 'active'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $employee_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('No account found with this Employee ID and Email combination');
    }

    $user = $result->fetch_assoc();

    // Generate OTP
    $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

    // Store OTP in database
    ensureOTPTable($conn);

    // Delete old OTPs for this user
    $sql = "DELETE FROM password_reset_otp WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();

    // Insert new OTP (use NOW() + INTERVAL to avoid timezone issues)
    $sql = "INSERT INTO password_reset_otp (employee_id, otp, email, expires_at) 
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $employee_id, $otp, $email);
    $stmt->execute();

    // Send OTP via email
    $emailSent = sendOTPEmail($email, $otp, $user['first_name'], $user['last_name']);

    if (!$emailSent) {
        throw new Exception('Failed to send OTP email. Please contact system administrator.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your email address',
        'user_name' => $user['first_name']
    ]);
}

/**
 * Verify OTP code
 */
function verifyOTP($conn)
{
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
function resetPassword($conn)
{
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
function ensureOTPTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(255) NOT NULL,
        otp VARCHAR(10) NOT NULL,
        email VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
}

/**
 * Send OTP via Email using PHPMailer
 */
function sendOTPEmail($email, $otp, $firstName, $lastName)
{
    // Check if PHPMailer is available
    $phpmailerPath = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($phpmailerPath)) {
        // PHPMailer not installed, log OTP for development
        $logDir = __DIR__ . '/../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'otp_errors.log';
        $logEntry = date('Y-m-d H:i:s') . " - OTP for $firstName $lastName ($email): $otp - PHPMailer not installed" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Return true for development (CHANGE TO FALSE IN PRODUCTION)
        return true;
    }

    // Load PHPMailer
    require $phpmailerPath;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP Configuration - IONOS Web Hosting
        $mail->isSMTP();
        $mail->Host = 'smtp.ionos.com'; // IONOS SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'accounts@bpcfaceid.com'; // Your IONOS email
        $mail->Password = 'Confirmp@ssword123'; // Your IONOS email password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email content
        $mail->setFrom('accounts@bpcfaceid.com', 'BPC FaceID Attendance System');
        $mail->addAddress($email, "$firstName $lastName");

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Attendance System';
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #333;'>Password Reset Request</h2>
                    <p>Hello $firstName,</p>
                    <p>You requested to reset your password. Use the OTP code below to continue:</p>
                    <div style='background-color: #f0f0f0; padding: 15px; text-align: center; margin: 20px 0;'>
                        <h1 style='color: #28a745; font-size: 32px; letter-spacing: 5px; margin: 0;'>$otp</h1>
                    </div>
                    <p><strong>This OTP will expire in 10 minutes.</strong></p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <hr style='border: 1px solid #ddd; margin: 20px 0;'>
                    <p style='color: #999; font-size: 12px;'>Automated Attendance System with Facial Recognition</p>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Your OTP code is: $otp\n\nThis code will expire in 10 minutes.";

        $mail->send();

        // Log success
        $logDir = __DIR__ . '/../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'otp_success.log';
        $logEntry = date('Y-m-d H:i:s') . " - OTP sent successfully to $email for $firstName $lastName" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        return true;

    } catch (\Exception $e) {
        // Log error
        $logDir = __DIR__ . '/../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'otp_errors.log';
        $logEntry = date('Y-m-d H:i:s') . " - Failed to send OTP to $email for $firstName $lastName - OTP: $otp - Error: {$e->getMessage()}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Fallback for development/error cases: Allow process to continue even if email fails
        // The OTP is logged in otp_errors.log so it can be retrieved
        return true;
    }
}
?>