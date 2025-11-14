<?php
/**
 * Authentication Backend
 * Handles login for both Admin and Employee accounts
 */
require_once '../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$idNumber = trim($input['idNumber'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($idNumber) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter both ID and password']);
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$attempt_key = 'login_attempts_' . md5($ip);
if (!isset($_SESSION[$attempt_key])) {
    $_SESSION[$attempt_key] = ['count' => 0, 'time' => time()];
}

$attempts = &$_SESSION[$attempt_key];
if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 900) {
    $remaining = ceil((900 - (time() - $attempts['time'])) / 60);
    echo json_encode(['success' => false, 'message' => "Too many attempts. Try again in $remaining minutes."]);
    exit;
}

if ((time() - $attempts['time']) >= 900) {
    $attempts = ['count' => 0, 'time' => time()];
}

$authenticated = false;
$userType = null;
$userData = null;

// Check ADMIN_USERS table first
$stmt = $conn->prepare("SELECT id, username, email, password_hash, role, is_active FROM admin_users WHERE (username = ? OR email = ?) LIMIT 1");
$stmt->bind_param('ss', $idNumber, $idNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    if (!$admin['is_active']) {
        $attempts['count']++;
        $attempts['time'] = time();
        echo json_encode(['success' => false, 'message' => 'Account is deactivated']);
        exit;
    }
    
    $passwordHash = $admin['password_hash'];
    
    // Check if password is hashed or plain text
    if (substr($passwordHash, 0, 4) === '$2y$' || substr($passwordHash, 0, 4) === '$2a$') {
        // Hashed password
        if (password_verify($password, $passwordHash)) {
            $authenticated = true;
            $userType = 'admin';
            $userData = $admin;
        }
    } else {
        // Plain text password (for backward compatibility)
        if ($password === $passwordHash) {
            $authenticated = true;
            $userType = 'admin';
            $userData = $admin;
        }
    }
}
$stmt->close();

// If not admin, check EMPLOYEES table
if (!$authenticated) {
    $stmt = $conn->prepare("SELECT id, employee_id, first_name, middle_name, last_name, email, phone, roles, department, position, status, employee_password FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param('s', $idNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        
        if (strtolower($employee['status']) !== 'active') {
            $attempts['count']++;
            $attempts['time'] = time();
            echo json_encode(['success' => false, 'message' => 'Employee account is inactive']);
            exit;
        }
        
        $passwordHash = $employee['employee_password'];
        
        if (empty($passwordHash)) {
            echo json_encode(['success' => false, 'message' => 'No password set. Contact administrator.']);
            exit;
        }
        
        // Check if password is hashed or plain text
        if (substr($passwordHash, 0, 4) === '$2y$' || substr($passwordHash, 0, 4) === '$2a$') {
            // Hashed password
            if (password_verify($password, $passwordHash)) {
                $authenticated = true;
                $userType = 'employee';
                $userData = $employee;
            }
        } else {
            // Plain text password (for backward compatibility)
            if ($password === $passwordHash) {
                $authenticated = true;
                $userType = 'employee';
                $userData = $employee;
            }
        }
    }
    $stmt->close();
}

// Process authentication result
if ($authenticated) {
    // Reset attempts
    $attempts = ['count' => 0, 'time' => time()];
    
    // Create session
    $_SESSION['logged_in'] = true;
    $_SESSION['user_type'] = $userType;
    $_SESSION['login_time'] = time();
    
    if ($userType === 'admin') {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role'] = $userData['role'];
        
        // Update last login
        $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param('i', $userData['id']);
        $stmt->execute();
        $stmt->close();
        
        $redirect = '../dashboard/dashboard.php';
    } else {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['employee_id'] = $userData['employee_id'];
        $_SESSION['employee_name'] = trim($userData['first_name'] . ' ' . $userData['last_name']);
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role'] = $userData['roles'];
        $_SESSION['department'] = $userData['department'];
        $_SESSION['position'] = $userData['position'];
        
        $redirect = '../dashboard/dashboard.php';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_type' => $userType,
        'redirect' => $redirect
    ]);
    
} else {
    // Failed login
    $attempts['count']++;
    $attempts['time'] = time();
    
    $remaining = 5 - $attempts['count'];
    if ($remaining > 0) {
        echo json_encode(['success' => false, 'message' => "Invalid credentials. $remaining attempt(s) remaining."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Account locked for 15 minutes.']);
    }
}

$conn->close();
?>
