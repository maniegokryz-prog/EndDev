<?php
/**
 * Remove Employee Process
 * Changes employee status to 'inactive' instead of deleting
 * Requires admin password verification
 */

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

require '../../db_connection.php';

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
            // Verify CSRF token
            if (!$this->validateCSRFToken()) {
                $this->logSecurityEvent('CSRF token validation failed for employee removal');
                $this->sendErrorResponse('Invalid CSRF token.', 403);
                return;
            }
            
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
            
            // Verify admin password
            if (!$this->verifyAdminPassword($adminPassword)) {
                $this->logSecurityEvent('Failed admin password verification for employee removal', [
                    'employee_id' => $employeeId
                ]);
                $this->sendErrorResponse('Invalid admin password.', 403);
                return;
            }
            
            // Check if employee exists and is active
            if (!$this->employeeExists($employeeId)) {
                $this->sendErrorResponse('Employee not found.', 404);
                return;
            }
            
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
            $this->logError('Unexpected Error', $e->getMessage());
            $this->sendErrorResponse('An unexpected error occurred.', 500);
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
            // Get the current logged-in admin user from session
            $userId = $_SESSION['user_id'] ?? null;
            
            if (empty($userId)) {
                $this->logError('Admin Verification', 'No user session found');
                return false;
            }
            
            // Get admin password hash from database
            $stmt = $this->db->prepare("SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->logError('Admin Verification', 'Admin user not found');
                return false;
            }
            
            $admin = $result->fetch_assoc();
            
            // Verify password
            return password_verify($password, $admin['password_hash']);
            
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
            $this->db->begin_transaction();
            
            // First, get the internal employee ID
            $stmt = $this->db->prepare("SELECT id FROM employees WHERE employee_id = ? AND status = 'active'");
            $stmt->bind_param('s', $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => 'Employee not found or already inactive.'
                ];
            }
            
            $employee = $result->fetch_assoc();
            $internalEmployeeId = $employee['id'];
            
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
            
            // Deactivate employee schedules
            $stmt = $this->db->prepare("
                UPDATE employee_schedules 
                SET is_active = 0,
                    end_date = CURRENT_DATE
                WHERE employee_id = ? AND is_active = 1
            ");
            
            $stmt->bind_param('i', $internalEmployeeId);
            $stmt->execute();
            
            // Deactivate employee assignments
            $stmt = $this->db->prepare("
                UPDATE employee_assignments 
                SET is_active = 0
                WHERE employee_id = ? AND is_active = 1
            ");
            
            $stmt->bind_param('i', $internalEmployeeId);
            $stmt->execute();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Employee has been moved to archive successfully.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
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
