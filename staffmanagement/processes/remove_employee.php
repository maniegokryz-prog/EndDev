<?php
/**
 * Remove Employee Process
 * Changes employee status to 'inactive' instead of deleting
 * Requires admin password verification
 */

// Disable error display and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unwanted output
ob_start();

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (simplified auth check)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    require '../../db_connection.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Clear any buffered output and set JSON header
ob_end_clean();
header('Content-Type: application/json');

class EmployeeRemovalProcessor {
    private $db;
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        try {
            // Log request received
            $this->logActivity('Remove employee request received', 'Employee ID: ' . ($_POST['employee_id'] ?? 'none'));
            
            // Verify CSRF token
            if (!$this->validateCSRFToken()) {
                $this->logSecurityEvent('CSRF token validation failed for employee removal');
                $this->sendErrorResponse('Invalid CSRF token.', 403);
                return;
            }
            
            $this->logActivity('CSRF token validated');
            
            // Get and validate input
            $employeeId = $_POST['employee_id'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            if (empty($employeeId)) {
                $this->sendErrorResponse('Employee ID is required.', 400);
                return;
            }
            
            if (empty($adminPassword)) {
                $this->sendErrorResponse('Admin password is required.', 400);
                return;
            }
            
            $this->logActivity('Input validated', 'Employee ID: ' . $employeeId);
            
            // Verify admin password
            if (!$this->verifyAdminPassword($adminPassword)) {
                $this->logSecurityEvent('Failed admin password verification for employee removal', [
                    'employee_id' => $employeeId
                ]);
                $this->sendErrorResponse('Invalid admin password.', 403);
                return;
            }
            
            $this->logActivity('Admin password verified');
            
            // Check if employee exists and is active
            if (!$this->employeeExists($employeeId)) {
                $this->sendErrorResponse('Employee not found.', 404);
                return;
            }
            
            $this->logActivity('Employee exists check passed');
            
            // Change employee status to inactive
            $result = $this->deactivateEmployee($employeeId);
            
            if ($result['success']) {
                $this->logActivity('Employee deactivated successfully', 'Employee ID: ' . $employeeId);
                $this->sendSuccessResponse($result);
            } else {
                $this->logError('Employee Deactivation Failed', $result['message']);
                $this->sendErrorResponse($result['message'], 500);
            }
            
        } catch (Exception $e) {
            $this->logError('Unexpected Error in handleRequest', $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            $this->sendErrorResponse('An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }
    
    private function validateCSRFToken() {
        $submitted_token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($submitted_token) || empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $submitted_token);
    }
    
    private function verifyAdminPassword($password) {
        try {
            // Get the current logged-in user from session
            $userId = $_SESSION['user_id'] ?? null;
            $userRole = $_SESSION['user_role'] ?? 'user';
            $username = $_SESSION['employee_id'] ?? 'unknown';
            
            if (empty($userId)) {
                $this->logError('Admin Verification', 'No user session found');
                return false;
            }
            
            // Check if user is system admin or employee-based admin
            $isSystemAdmin = isset($_SESSION['is_system_admin']) && $_SESSION['is_system_admin'] === true;
            
            if ($isSystemAdmin) {
                // System admin from admin_users table
                $stmt = $this->db->prepare("SELECT password_hash, username FROM admin_users WHERE id = ? AND is_active = 1");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $this->logError('Admin Verification', "System admin not found for ID: $userId");
                    return false;
                }
                
                $admin = $result->fetch_assoc();
                $passwordHash = $admin['password_hash'];
                $accountName = $admin['username'];
                
            } else {
                // Employee-based admin from employees table
                $stmt = $this->db->prepare("SELECT employee_password, employee_id, first_name, last_name FROM employees WHERE id = ? AND status = 'active'");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $this->logError('Admin Verification', "Employee admin not found for ID: $userId");
                    return false;
                }
                
                $employee = $result->fetch_assoc();
                $passwordHash = $employee['employee_password'];
                $accountName = $employee['first_name'] . ' ' . $employee['last_name'];
            }
            
            // Verify password against the logged-in user's own password hash
            $isValid = password_verify($password, $passwordHash);
            
            if ($isValid) {
                $accountType = $isSystemAdmin ? 'System Admin' : 'Employee Admin';
                $this->logActivity('Password verified for admin', "$accountType: $accountName (ID: $userId)");
            } else {
                $this->logSecurityEvent('Failed password verification', [
                    'admin_name' => $accountName,
                    'admin_id' => $userId,
                    'is_system_admin' => $isSystemAdmin
                ]);
            }
            
            return $isValid;
            
        } catch (Exception $e) {
            $this->logError('Admin Password Verification', $e->getMessage());
            return false;
        }
    }
    
    private function employeeExists($employeeId) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM employees WHERE employee_id = ? AND status = 'active'");
            $stmt->bind_param('s', $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
            
        } catch (Exception $e) {
            $this->logError('Employee Existence Check', $e->getMessage());
            return false;
        }
    }
    
    private function deactivateEmployee($employeeId) {
        try {
            $this->logActivity('Starting deactivateEmployee', 'Employee ID: ' . $employeeId);
            $this->db->begin_transaction();
            
            // First, get the internal employee ID
            $stmt = $this->db->prepare("SELECT id FROM employees WHERE employee_id = ? AND status = 'active'");
            $stmt->bind_param('s', $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->db->rollback();
                $this->logError('Deactivate Employee', 'Employee not found in deactivateEmployee');
                return [
                    'success' => false,
                    'message' => 'Employee not found or already inactive.'
                ];
            }
            
            $employee = $result->fetch_assoc();
            $internalEmployeeId = $employee['id'];
            $this->logActivity('Got internal employee ID', 'ID: ' . $internalEmployeeId);
            
            // Update employee status to 'inactive' instead of deleting
            $stmt = $this->db->prepare("
                UPDATE employees 
                SET status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->bind_param('i', $internalEmployeeId);
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception('Failed to update employee status.');
            }
            
            $this->logActivity('Employee status updated to inactive');
            
            // Sync employee deactivation to cloud (non-blocking, disabled for performance)
            // Cloud sync temporarily disabled to prevent timeout issues
            /*
            try {
                require_once __DIR__ . '/../../db_cloud_sync.php';
                syncToCloud('employees', [
                    'status' => 'inactive'
                ], 'update', "id = {$internalEmployeeId}");
            } catch (Exception $syncError) {
                // Log but don't fail the operation
                $this->logError('Cloud Sync', 'Failed to sync employee deactivation: ' . $syncError->getMessage());
            }
            */
            
            $this->logActivity('Cloud sync skipped (disabled)');
            
            // Deactivate employee schedules
            $stmt = $this->db->prepare("
                UPDATE employee_schedules 
                SET is_active = 0,
                    end_date = CURRENT_DATE
                WHERE employee_id = ? AND is_active = 1
            ");
            
            $stmt->bind_param('i', $internalEmployeeId);
            $stmt->execute();
            
            $this->logActivity('Employee schedules deactivated');
            
            // Cloud sync for schedules (disabled)
            /*
            try {
                syncToCloud('employee_schedules', [
                    'is_active' => 0,
                    'end_date' => date('Y-m-d')
                ], 'update', "employee_id = {$internalEmployeeId} AND is_active = 1");
            } catch (Exception $syncError) {
                $this->logError('Cloud Sync', 'Failed to sync schedules deactivation: ' . $syncError->getMessage());
            }
            */
            
            // Deactivate employee assignments
            $stmt = $this->db->prepare("
                UPDATE employee_assignments 
                SET is_active = 0
                WHERE employee_id = ? AND is_active = 1
            ");
            
            $stmt->bind_param('i', $internalEmployeeId);
            $stmt->execute();
            
            $this->logActivity('Employee assignments deactivated');
            
            // Cloud sync for assignments (disabled)
            /*
            try {
                syncToCloud('employee_assignments', [
                    'is_active' => 0
                ], 'update', "employee_id = {$internalEmployeeId} AND is_active = 1");
            } catch (Exception $syncError) {
                $this->logError('Cloud Sync', 'Failed to sync assignments deactivation: ' . $syncError->getMessage());
            }
            */
            
            $this->db->commit();
            $this->logActivity('Transaction committed successfully');
            
            return [
                'success' => true,
                'message' => 'Employee has been moved to archive successfully.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Deactivate Employee Exception', $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return [
                'success' => false,
                'message' => 'Error deactivating employee: ' . $e->getMessage()
            ];
        }
    }
    
    private function logActivity($activity, $reference = '') {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
        if ($reference) $log_entry .= " - " . $reference;
        $log_entry .= PHP_EOL;
        
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($context, $message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ERROR] Context: " . $context . " - Message: " . $message . PHP_EOL;
        
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logSecurityEvent($event, $data = []) {
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $data_str = !empty($data) ? json_encode($data) : 'No additional data';
        
        $log_entry = "[{$timestamp}] [SECURITY] [IP: {$ip}] {$event} - {$data_str}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendSuccessResponse($data) {
        echo json_encode([
            'success' => true,
            'message' => $data['message']
        ]);
        exit;
    }
    
    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $processor = new EmployeeRemovalProcessor($conn);
        $processor->handleRequest();
    } catch (Exception $e) {
        // Catch any unexpected errors and return JSON
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
