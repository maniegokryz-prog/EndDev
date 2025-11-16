<?php
/**
 * Employee Password Management API
 * Handles password reset and password change operations
 */

// Start output buffering and error control
ob_start();
$GLOBALS['error_reporting_configured'] = true;
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Clear any output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

require '../../db_connection.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'reset_to_default':
            resetToDefaultPassword($conn);
            break;
            
        case 'change_password':
            changePassword($conn);
            break;
            
        case 'admin_reset':
            adminResetPassword($conn);
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
ob_end_flush();

/**
 * Reset employee password to default (employee_id)
 * Used by admins or for forgotten passwords
 */
function resetToDefaultPassword($conn) {
    // Check if user is admin
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        throw new Exception('Unauthorized. Admin access required.');
    }
    
    $employee_id_string = $_POST['employee_id'] ?? '';
    
    if (empty($employee_id_string)) {
        throw new Exception('Employee ID is required');
    }
    
    // Find employee
    $sql = "SELECT id, employee_id, first_name, last_name FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $result->fetch_assoc();
    
    // Set default password (employee_id) and hash it
    $default_password = $employee['employee_id'];
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_sql = "UPDATE employees SET employee_password = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $hashed_password, $employee['id']);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to reset password');
    }
    
    // Log the action
    logPasswordAction($conn, $employee['id'], 'password_reset_to_default', $_SESSION['employee_id'] ?? 'system');
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset to default successfully',
        'default_password' => $default_password,
        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name']
    ]);
}

/**
 * Change password for logged-in user
 * Requires current password verification
 */
function changePassword($conn) {
    // Check if user is logged in
    if (!isset($_SESSION['employee_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        throw new Exception('All fields are required');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('New passwords do not match');
    }
    
    if (strlen($new_password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }
    
    // Get employee record
    $sql = "SELECT id, employee_id, employee_password FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['employee_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $result->fetch_assoc();
    
    // Verify current password
    if (empty($employee['employee_password']) || !password_verify($current_password, $employee['employee_password'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_sql = "UPDATE employees SET employee_password = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $hashed_password, $employee['id']);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update password');
    }
    
    // Log the action
    logPasswordAction($conn, $employee['id'], 'password_changed', $employee['employee_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
}

/**
 * Admin can reset any employee's password to a custom password
 */
function adminResetPassword($conn) {
    // Check if user is admin
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        throw new Exception('Unauthorized. Admin access required.');
    }
    
    $employee_id_string = $_POST['employee_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($employee_id_string)) {
        throw new Exception('Employee ID is required');
    }
    
    if (empty($new_password) || empty($confirm_password)) {
        throw new Exception('Password fields are required');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }
    
    if (strlen($new_password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }
    
    // Find employee
    $sql = "SELECT id, employee_id, first_name, last_name FROM employees WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $result->fetch_assoc();
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_sql = "UPDATE employees SET employee_password = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $hashed_password, $employee['id']);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to reset password');
    }
    
    // Log the action
    logPasswordAction($conn, $employee['id'], 'admin_password_reset', $_SESSION['employee_id'] ?? 'system');
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully',
        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name']
    ]);
}

/**
 * Log password-related actions for security audit
 */
function logPasswordAction($conn, $employee_id, $action, $performed_by) {
    // Create password_audit_log table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS password_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        performed_by VARCHAR(255),
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $insert_sql = "INSERT INTO password_audit_log (employee_id, action, performed_by, ip_address) 
                   VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("isss", $employee_id, $action, $performed_by, $ip_address);
    $stmt->execute();
}
?>
