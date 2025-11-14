<?php
/**
 * Employee Archive Management API
 * 
 * Handles archiving (soft delete) and restoring employees
 * Also provides permanent deletion from archives
 */

// Start output buffering
ob_start();

// Use API-specific database connection
require_once '../../db_connection_api.php';

// Clear output
ob_clean();

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

class EmployeeArchiveManager {
    private $db;
    private $currentUser;
    private $logFile;
    
    public function __construct($database) {
        $this->db = $database;
        $this->logFile = __DIR__ . '/../logs/archive_operations.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Main request handler
     */
    public function handleRequest() {
        try {
            // Check authentication
            if (!$this->authenticateUser()) {
                $this->sendResponse(false, 'Unauthorized access', 401);
                return;
            }
            
            // Only admins can archive/restore employees
            if ($this->currentUser['user_type'] !== 'admin') {
                $this->sendResponse(false, 'Only administrators can manage employee archives', 403);
                return;
            }
            
            // Get action from request
            $action = $_POST['action'] ?? $_GET['action'] ?? '';
            
            switch ($action) {
                case 'archive':
                    $this->archiveEmployees();
                    break;
                    
                case 'restore':
                    $this->restoreEmployees();
                    break;
                    
                case 'delete':
                    $this->permanentlyDeleteEmployees();
                    break;
                    
                case 'list_archived':
                    $this->listArchivedEmployees();
                    break;
                    
                default:
                    $this->sendResponse(false, 'Invalid action specified', 400);
            }
            
        } catch (Exception $e) {
            $this->logError('Request handling failed', $e->getMessage());
            $this->sendResponse(false, 'An error occurred: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Archive (soft delete) employees
     */
    private function archiveEmployees() {
        $employeeIds = $_POST['employee_ids'] ?? [];
        $reason = $_POST['reason'] ?? 'Administrative action';
        
        if (empty($employeeIds) || !is_array($employeeIds)) {
            $this->sendResponse(false, 'No employees selected for archiving', 400);
            return;
        }
        
        $this->db->begin_transaction();
        
        try {
            $archived = [];
            $failed = [];
            
            foreach ($employeeIds as $employeeId) {
                try {
                    // Get employee data
                    $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $employee = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$employee) {
                        $failed[] = ['id' => $employeeId, 'reason' => 'Employee not found'];
                        continue;
                    }
                    
                    // Insert into archive
                    $stmt = $this->db->prepare("
                        INSERT INTO employee_archives 
                        (id, employee_id, employee_password, first_name, middle_name, last_name, 
                         email, phone, roles, department, position, hire_date, status, 
                         created_at, updated_at, profile_photo, original_id, archived_by, archive_reason)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $originalId = $employee['id'];
                    $archivedBy = $this->currentUser['username'];
                    
                    $stmt->bind_param('issssssssssssssssss',
                        $employee['id'],
                        $employee['employee_id'],
                        $employee['employee_password'],
                        $employee['first_name'],
                        $employee['middle_name'],
                        $employee['last_name'],
                        $employee['email'],
                        $employee['phone'],
                        $employee['roles'],
                        $employee['department'],
                        $employee['position'],
                        $employee['hire_date'],
                        $employee['status'],
                        $employee['created_at'],
                        $employee['updated_at'],
                        $employee['profile_photo'],
                        $originalId,
                        $archivedBy,
                        $reason
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to archive: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Delete from employees table
                    $stmt = $this->db->prepare("DELETE FROM employees WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to remove from active employees: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    $archived[] = [
                        'id' => $employeeId,
                        'name' => "{$employee['first_name']} {$employee['last_name']}"
                    ];
                    
                    $this->logActivity('Employee archived', "ID: {$employeeId}, By: {$archivedBy}");
                    
                } catch (Exception $e) {
                    $failed[] = ['id' => $employeeId, 'reason' => $e->getMessage()];
                }
            }
            
            $this->db->commit();
            
            $this->sendResponse(true, 'Archive operation completed', 200, [
                'archived' => $archived,
                'failed' => $failed,
                'archived_count' => count($archived),
                'failed_count' => count($failed)
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Restore employees from archive
     */
    private function restoreEmployees() {
        $employeeIds = $_POST['employee_ids'] ?? [];
        
        if (empty($employeeIds) || !is_array($employeeIds)) {
            $this->sendResponse(false, 'No employees selected for restoration', 400);
            return;
        }
        
        $this->db->begin_transaction();
        
        try {
            $restored = [];
            $failed = [];
            
            foreach ($employeeIds as $employeeId) {
                try {
                    // Get archived employee data
                    $stmt = $this->db->prepare("SELECT * FROM employee_archives WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $employee = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$employee) {
                        $failed[] = ['id' => $employeeId, 'reason' => 'Employee not found in archive'];
                        continue;
                    }
                    
                    // Check if employee_id already exists in active employees
                    $stmt = $this->db->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $failed[] = ['id' => $employeeId, 'reason' => 'Employee ID already exists in active employees'];
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                    
                    // Restore to employees table
                    $stmt = $this->db->prepare("
                        INSERT INTO employees 
                        (employee_id, employee_password, first_name, middle_name, last_name, 
                         email, phone, roles, department, position, hire_date, status, 
                         created_at, updated_at, profile_photo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    
                    $status = 'Active';
                    
                    $stmt->bind_param('ssssssssssssss',
                        $employee['employee_id'],
                        $employee['employee_password'],
                        $employee['first_name'],
                        $employee['middle_name'],
                        $employee['last_name'],
                        $employee['email'],
                        $employee['phone'],
                        $employee['roles'],
                        $employee['department'],
                        $employee['position'],
                        $employee['hire_date'],
                        $status,
                        $employee['created_at'],
                        $employee['profile_photo']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to restore: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Delete from archive
                    $stmt = $this->db->prepare("DELETE FROM employee_archives WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to remove from archive: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    $restored[] = [
                        'id' => $employeeId,
                        'name' => "{$employee['first_name']} {$employee['last_name']}"
                    ];
                    
                    $this->logActivity('Employee restored', "ID: {$employeeId}, By: {$this->currentUser['username']}");
                    
                } catch (Exception $e) {
                    $failed[] = ['id' => $employeeId, 'reason' => $e->getMessage()];
                }
            }
            
            $this->db->commit();
            
            $this->sendResponse(true, 'Restore operation completed', 200, [
                'restored' => $restored,
                'failed' => $failed,
                'restored_count' => count($restored),
                'failed_count' => count($failed)
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Permanently delete employees from archive
     */
    private function permanentlyDeleteEmployees() {
        $employeeIds = $_POST['employee_ids'] ?? [];
        
        if (empty($employeeIds) || !is_array($employeeIds)) {
            $this->sendResponse(false, 'No employees selected for deletion', 400);
            return;
        }
        
        $this->db->begin_transaction();
        
        try {
            $deleted = [];
            $failed = [];
            
            foreach ($employeeIds as $employeeId) {
                try {
                    // Get employee info before deletion
                    $stmt = $this->db->prepare("SELECT first_name, last_name FROM employee_archives WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $employee = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$employee) {
                        $failed[] = ['id' => $employeeId, 'reason' => 'Employee not found in archive'];
                        continue;
                    }
                    
                    // Permanently delete
                    $stmt = $this->db->prepare("DELETE FROM employee_archives WHERE employee_id = ?");
                    $stmt->bind_param('s', $employeeId);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to delete: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    $deleted[] = [
                        'id' => $employeeId,
                        'name' => "{$employee['first_name']} {$employee['last_name']}"
                    ];
                    
                    $this->logActivity('Employee permanently deleted', "ID: {$employeeId}, By: {$this->currentUser['username']}");
                    
                } catch (Exception $e) {
                    $failed[] = ['id' => $employeeId, 'reason' => $e->getMessage()];
                }
            }
            
            $this->db->commit();
            
            $this->sendResponse(true, 'Delete operation completed', 200, [
                'deleted' => $deleted,
                'failed' => $failed,
                'deleted_count' => count($deleted),
                'failed_count' => count($failed)
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * List all archived employees
     */
    private function listArchivedEmployees() {
        try {
            $stmt = $this->db->prepare("
                SELECT employee_id, first_name, middle_name, last_name, email, phone, 
                       roles, department, position, profile_photo, archived_at, archived_by, archive_reason
                FROM employee_archives 
                ORDER BY archived_at DESC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $archived = [];
            while ($row = $result->fetch_assoc()) {
                $archived[] = $row;
            }
            $stmt->close();
            
            $this->sendResponse(true, 'Archived employees retrieved', 200, [
                'employees' => $archived,
                'count' => count($archived)
            ]);
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Authenticate user
     */
    private function authenticateUser() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        $this->currentUser = [
            'employee_id' => $_SESSION['employee_id'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'user_type' => $_SESSION['user_type'] ?? ''
        ];
        
        return true;
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $code = 200, $data = []) {
        http_response_code($code);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $data));
        exit;
    }
    
    /**
     * Log activity
     */
    private function logActivity($activity, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [ACTIVITY] {$activity}";
        if ($details) {
            $logEntry .= " - {$details}";
        }
        $logEntry .= PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log error
     */
    private function logError($context, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [ERROR] {$context} - {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Initialize and handle request
$manager = new EmployeeArchiveManager($conn);
$manager->handleRequest();
?>
