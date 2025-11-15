<?php
/**
 * Authentication API
 * Handles login, logout, and session management
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
    
    // Query user from database
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
    if (!password_verify($password, $user['employee_password'])) {
        // Log failed attempt
        logLoginAttempt($conn, $employee_id, false, 'Invalid password');
        throw new Exception('Invalid credentials');
    }
    
    // Determine user role (admin or user)
    $role = 'user';
    if (!empty($user['roles'])) {
        $roles_lower = strtolower($user['roles']);
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
    $redirect_url = ($role === 'admin') ? '../dashboard/dashboard.php' : '../dashboard/dashboard.php';
    
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
    session_start();
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
?>
