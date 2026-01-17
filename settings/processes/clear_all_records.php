<?php
/**
 * Clear All Attendance Records
 * Permanently deletes all attendance records from the system
 * Requires admin password verification
 */

// Disable error display and enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

require '../../db_connection.php';

// Clear buffer and set JSON header
ob_end_clean();
header('Content-Type: application/json');

class ClearRecordsProcessor {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        try {
            // Verify CSRF token
            if (!$this->validateCSRFToken()) {
                $this->logSecurityEvent('CSRF token validation failed for clear all records');
                $this->sendErrorResponse('Invalid CSRF token.', 403);
                return;
            }
            
            // Get and validate admin password
            $adminPassword = $_POST['admin_password'] ?? '';
            
            if (empty($adminPassword)) {
                $this->sendErrorResponse('Admin password is required.', 400);
                return;
            }
            
            // Verify admin password
            if (!$this->verifyAdminPassword($adminPassword)) {
                $this->logSecurityEvent('Failed admin password verification for clear all records');
                $this->sendErrorResponse('Invalid admin password.', 403);
                return;
            }
            
            // Get count before deletion
            $count = $this->getRecordCount();
            
            // Clear all attendance records
            $result = $this->clearAllRecords();
            
            if ($result['success']) {
                $this->logActivity('All attendance records cleared', "Total records deleted: $count");
                $this->sendSuccessResponse([
                    'success' => true,
                    'message' => "Successfully deleted $count attendance record(s).",
                    'count' => $count
                ]);
            } else {
                $this->logError('Clear All Records Failed', $result['message']);
                $this->sendErrorResponse($result['message'], 500);
            }
            
        } catch (Exception $e) {
            $this->logError('Unexpected Error in clearAllRecords', $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
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
                $this->logActivity('Password verified for clear records', "$accountType: $accountName (ID: $userId)");
            } else {
                $this->logSecurityEvent('Failed password verification for clear records', [
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
    
    private function getRecordCount() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM daily_attendance");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return $row['total'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function clearAllRecords() {
        try {
            $this->db->begin_transaction();
            
            // Delete all daily attendance records
            $stmt = $this->db->prepare("DELETE FROM daily_attendance");
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception('Failed to delete attendance records.');
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'All attendance records cleared successfully.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Error clearing records: ' . $e->getMessage()
            ];
        }
    }
    
    private function logActivity($activity, $reference = '') {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
        if ($reference) $log_entry .= " - " . $reference;
        $log_entry .= PHP_EOL;
        
        $log_dir = dirname(__DIR__) . '/../staffmanagement/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($context, $message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ERROR] Context: " . $context . " - Message: " . $message . PHP_EOL;
        
        $log_dir = dirname(__DIR__) . '/../staffmanagement/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logSecurityEvent($event, $data = []) {
        $log_dir = dirname(__DIR__) . '/../staffmanagement/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $data_str = !empty($data) ? json_encode($data) : 'No additional data';
        
        $log_entry = "[{$timestamp}] [SECURITY] [IP: {$ip}] {$event} - {$data_str}" . PHP_EOL;
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendSuccessResponse($data) {
        echo json_encode($data);
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
        $processor = new ClearRecordsProcessor($conn);
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
