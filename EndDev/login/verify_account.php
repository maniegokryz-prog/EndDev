<?php
/**
 * Forgot Password Step 1: Verify Account
 */
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idNumber = trim($input['idNumber'] ?? '');
$contact = trim($input['contact'] ?? '');

if (empty($idNumber) || empty($contact)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all fields']);
    exit;
}

$userFound = false;
$userType = null;
$email = null;

// Check admin_users
$stmt = $conn->prepare("SELECT id, email, username FROM admin_users WHERE (username = ? OR email = ?) AND (email = ? OR username = ?)");
$stmt->bind_param('ssss', $idNumber, $idNumber, $contact, $contact);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $userFound = true;
    $userType = 'admin';
    $email = $user['email'];
    $_SESSION['reset_user_id'] = $user['id'];
    $_SESSION['reset_user_type'] = 'admin';
}
$stmt->close();

// Check employees
if (!$userFound) {
    $stmt = $conn->prepare("SELECT id, employee_id, email, phone FROM employees WHERE employee_id = ? AND (email = ? OR phone = ?)");
    $stmt->bind_param('sss', $idNumber, $contact, $contact);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userFound = true;
        $userType = 'employee';
        $email = $user['email'];
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_employee_id'] = $user['employee_id'];
        $_SESSION['reset_user_type'] = 'employee';
    }
    $stmt->close();
}

if (!$userFound) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    exit;
}

// Generate OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['reset_otp'] = $otp;
$_SESSION['reset_otp_time'] = time();
$_SESSION['reset_otp_attempts'] = 0;

// Mask email/phone
$maskedContact = maskContact($contact);

echo json_encode([
    'success' => true,
    'message' => 'Verification code sent',
    'masked_contact' => $maskedContact,
    'otp' => $otp // REMOVE IN PRODUCTION
]);

function maskContact($contact) {
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $parts = explode('@', $contact);
        $name = $parts[0];
        $domain = $parts[1];
        $masked = substr($name, 0, 2) . str_repeat('*', strlen($name) - 3) . substr($name, -1);
        return $masked . '@' . $domain;
    } else {
        $len = strlen($contact);
        return substr($contact, 0, 2) . str_repeat('*', $len - 4) . substr($contact, -2);
    }
}

$conn->close();
?>
