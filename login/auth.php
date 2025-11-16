<?php
/**
 * Authentication API
 * Handles login, logout, and session management
 */

// Start output buffering to catch any stray output
ob_start();

// Disable ALL error display to prevent breaking JSON output BEFORE any other code
$GLOBALS['error_reporting_configured'] = true;
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

// Clear any output that might have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');

require '../db_connection.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            handleLogin($conn);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'check_session':
            checkSession();
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
 * Handle user login
 */
function handleLogin($conn) {
    $employee_id = $_POST['employee_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($employee_id) || empty($password)) {
        throw new Exception('Employee ID and password are required');
    }
    
    // First, try to find user in admin_users table
    $sql_admin = "SELECT id, username as employee_id, email, role, is_active 
                  FROM admin_users 
                  WHERE username = ? AND is_active = 1";
    
    $stmt = $conn->prepare($sql_admin);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        
        // Verify admin password
        $sql_pass = "SELECT password_hash FROM admin_users WHERE username = ?";
        $stmt_pass = $conn->prepare($sql_pass);
        $stmt_pass->bind_param("s", $employee_id);
        $stmt_pass->execute();
        $pass_result = $stmt_pass->get_result();
        $pass_data = $pass_result->fetch_assoc();
        
        if (empty($pass_data['password_hash']) || !password_verify($password, $pass_data['password_hash'])) {
            logLoginAttempt($conn, $employee_id, false, 'Invalid password (admin)');
            throw new Exception('Invalid credentials');
        }
        
        // Create admin session
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['employee_id'] = $admin['employee_id'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = ucfirst($admin['employee_id']);
        $_SESSION['user_email'] = $admin['email'];
        $_SESSION['department'] = 'Administration';
        $_SESSION['position'] = 'System Administrator';
        $_SESSION['profile_photo'] = null;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['is_system_admin'] = true;
        
        // Update last login
        $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $admin['id']);
        $update_stmt->execute();
        
        logLoginAttempt($conn, $employee_id, true, 'Admin login successful');
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $admin['id'],
                'employee_id' => $admin['employee_id'],
                'name' => $_SESSION['user_name'],
                'role' => 'admin',
                'department' => 'Administration',
                'position' => 'System Administrator'
            ],
            'redirect_url' => '../dashboard/dashboard.php'
        ]);
        return;
    }
    
    // If not found in admin_users, check employees table
    $sql = "SELECT id, employee_id, employee_password, first_name, last_name, email, 
                   roles, department, position, status, profile_photo 
            FROM employees 
            WHERE employee_id = ? AND status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Log failed attempt
        logLoginAttempt($conn, $employee_id, false, 'User not found');
        throw new Exception('Invalid credentials');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password (assuming passwords are hashed with password_hash)
    if (empty($user['employee_password']) || !password_verify($password, $user['employee_password'])) {
        // Log failed attempt
        logLoginAttempt($conn, $employee_id, false, 'Invalid password');
        throw new Exception('Invalid credentials');
    }
    
    // Determine user role (admin or user)
    $role = 'user';
    if (!empty($user['roles'])) {
        $roles_lower = strtolower(trim($user['roles']));
        if (strpos($roles_lower, 'admin') !== false || 
            strpos($roles_lower, 'administrator') !== false) {
            $role = 'admin';
        }
    }
    
    // Create session
    session_regenerate_id(true); // Prevent session fixation
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['user_role'] = $role;
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['position'] = $user['position'];
    $_SESSION['profile_photo'] = $user['profile_photo'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Log successful login
    logLoginAttempt($conn, $employee_id, true, 'Login successful');
    
    // Determine redirect URL based on role
    if ($role === 'admin') {
        $redirect_url = '../dashboard/dashboard.php';
    } else {
        // Normal users go to their individual staff info page
        $redirect_url = '../staffmanagement/staffinfo.php?id=' . $user['employee_id'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'employee_id' => $user['employee_id'],
            'name' => $_SESSION['user_name'],
            'role' => $role,
            'department' => $user['department'],
            'position' => $user['position']
        ],
        'redirect_url' => $redirect_url
    ]);
}

/**
 * Handle user logout
 */
function handleLogout() {
    // Session already started at the top of the file
    session_unset();
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Check if user session is valid
 */
function checkSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Not authenticated'
        ]);
        return;
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        session_unset();
        session_destroy();
        
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Session expired'
        ]);
        return;
    }
    
    // Update last activity time
    $_SESSION['login_time'] = time();
    
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'] ?? null,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? 'user',
            'department' => $_SESSION['department'] ?? null,
            'position' => $_SESSION['position'] ?? null
        ]
    ]);
}

/**
 * Log login attempts
 */
function logLoginAttempt($conn, $employee_id, $success, $message) {
    // Create login_logs table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(255),
        success BOOLEAN,
        message VARCHAR(255),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $sql = "INSERT INTO login_logs (employee_id, success, message, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisss", $employee_id, $success, $message, $ip_address, $user_agent);
    $stmt->execute();
}

// Flush output buffer
ob_end_flush();
?>
