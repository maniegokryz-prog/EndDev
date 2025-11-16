<?php
/**
 * Restore Employee Process
 * Changes employee status from 'inactive' to 'active'
 * Requires admin password verification
 */

// Start output buffering
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require '../../db_connection.php';

// Clear buffer and set JSON header
ob_end_clean();
header('Content-Type: application/json');

class EmployeeRestoreProcessor {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        try {
            // Verify CSRF token
            if (!$this->validateCSRFToken()) {
                $this->logSecurityEvent('CSRF token validation failed for employee restore');
                $this->sendErrorResponse('Invalid CSRF token.', 403);
                return;
            }
            
            // Get and validate input
            $employeeIds = $_POST['employee_ids'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            if (empty($employeeIds)) {
                $this->sendErrorResponse('Employee IDs are required.', 400);
                return;
            }
            
            if (empty($adminPassword)) {
                $this->sendErrorResponse('Admin password is required.', 400);
                return;
            }
            
            // Verify admin password
            if (!$this->verifyAdminPassword($adminPassword)) {
                $this->logSecurityEvent('Failed admin password verification for employee restore', [
                    'employee_ids' => $employeeIds
                ]);
                $this->sendErrorResponse('Invalid admin password.', 403);
                return;
            }
            
            // Parse employee IDs (comma-separated)
            $ids = explode(',', $employeeIds);
            $ids = array_map('trim', $ids);
            $ids = array_filter($ids);
            
            if (empty($ids)) {
                $this->sendErrorResponse('No valid employee IDs provided.', 400);
                return;
            }
            
            // Restore employees
            $result = $this->restoreEmployees($ids);
            
            if ($result['success']) {
                $this->logActivity('Employees restored successfully', 'Count: ' . $result['count']);
                $this->sendSuccessResponse($result);
            } else {
                $this->logError('Employee Restore Failed', $result['message']);
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
            $userId = $_SESSION['user_id'] ?? null;
            
            if (empty($userId)) {
                $this->logError('Admin Verification', 'No user session found');
                return false;
            }
            
            $stmt = $this->db->prepare("SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->logError('Admin Verification', 'Admin user not found');
                return false;
            }
            
            $admin = $result->fetch_assoc();
            return password_verify($password, $admin['password_hash']);
            
        } catch (Exception $e) {
            $this->logError('Admin Password Verification', $e->getMessage());
            return false;
        }
    }
    
    private function restoreEmployees($employeeIds) {
        try {
            $this->db->begin_transaction();
            
            $restoredCount = 0;
            $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
            
            // Update employee status to 'active'
            $sql = "UPDATE employees 
                    SET status = 'active',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE employee_id IN ($placeholders) AND status = 'inactive'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param(str_repeat('s', count($employeeIds)), ...$employeeIds);
            $stmt->execute();
            $restoredCount = $stmt->affected_rows;
            
            if ($restoredCount === 0) {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => 'No employees were restored. They may already be active.'
                ];
            }
            
            // Get internal IDs for the restored employees
            $sql = "SELECT id FROM employees WHERE employee_id IN ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param(str_repeat('s', count($employeeIds)), ...$employeeIds);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $internalIds = [];
            while ($row = $result->fetch_assoc()) {
                $internalIds[] = $row['id'];
            }
            
            // Reactivate employee schedules
            if (!empty($internalIds)) {
                $placeholders = implode(',', array_fill(0, count($internalIds), '?'));
                $sql = "UPDATE employee_schedules 
                        SET is_active = 1,
                            end_date = NULL
                        WHERE employee_id IN ($placeholders)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param(str_repeat('i', count($internalIds)), ...$internalIds);
                $stmt->execute();
                
                // Reactivate employee assignments
                $sql = "UPDATE employee_assignments 
                        SET is_active = 1
                        WHERE employee_id IN ($placeholders)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param(str_repeat('i', count($internalIds)), ...$internalIds);
                $stmt->execute();
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'count' => $restoredCount,
                'message' => $restoredCount . ' employee(s) restored successfully.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Error restoring employees: ' . $e->getMessage()
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
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $data_str = !empty($data) ? json_encode($data) : 'No additional data';
        
        $log_entry = "[{$timestamp}] [SECURITY] [IP: {$ip}] {$event} - {$data_str}" . PHP_EOL;
        
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendSuccessResponse($data) {
        echo json_encode([
            'success' => true,
            'message' => $data['message'],
            'count' => $data['count']
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
        $processor = new EmployeeRestoreProcessor($conn);
        $processor->handleRequest();
    } catch (Exception $e) {
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
