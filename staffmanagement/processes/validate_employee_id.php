<?php
/**
 * Secure Employee ID Validation Endpoint
 * Production-level validation with rate limiting and security measures
 */

require '../../db_connection.php';

// Security Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS Headers (if needed for same-origin requests)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

class EmployeeIDValidator {
    private $db;
    private $rateLimit = 10; // Maximum requests per minute
    private $rateLimitWindow = 60; // Time window in seconds
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        try {
            // Only allow POST requests
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendResponse(false, 'Method not allowed', 405);
                return;
            }
            
            // Verify AJAX request
            if (!$this->isAjaxRequest()) {
                $this->sendResponse(false, 'Invalid request type', 400);
                return;
            }
            
            // Rate limiting
            if (!$this->checkRateLimit()) {
                $this->sendResponse(false, 'Too many requests. Please try again later.', 429);
                return;
            }
            
            // CSRF Token validation
            if (!$this->validateCSRFToken()) {
                $this->logActivity('CSRF Validation Failed', 'Session ID: ' . session_id());
                $this->sendResponse(false, 'Invalid security token. Please refresh the page and try again.', 403, [
                    'error_type' => 'csrf_validation_failed',
                    'session_active' => session_status() === PHP_SESSION_ACTIVE,
                    'session_id' => session_id()
                ]);
                return;
            }
            
            // Get and validate input
            $employeeId = $this->getAndValidateInput();
            if (!$employeeId) {
                return; // Error already sent
            }
            
            // Check if ID exists
            $exists = $this->checkEmployeeIdExists($employeeId);
            
            if ($exists) {
                $this->sendResponse(false, 'Employee ID already exists. Please choose a different ID.', 200, [
                    'available' => false,
                    'employee_id' => $employeeId
                ]);
            } else {
                $this->sendResponse(true, 'Employee ID is available.', 200, [
                    'available' => true,
                    'employee_id' => $employeeId
                ]);
            }
            
            // Log successful validation
            $this->logActivity('Employee ID validation', "ID: {$employeeId}, Available: " . ($exists ? 'false' : 'true'));
            
        } catch (Exception $e) {
            $this->logError('Validation Error', $e->getMessage());
            $this->sendResponse(false, 'Validation service temporarily unavailable', 500);
        }
    }
    
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    private function validateCSRFToken() {
        // Get token from JSON input or POST data
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $submittedToken = $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        // Debug logging for development
        $this->logActivity('CSRF Token Check', "Session exists: " . (session_id() ? 'yes' : 'no') . 
                          ", Session token: " . (empty($sessionToken) ? 'empty' : 'present') . 
                          ", Submitted token: " . (empty($submittedToken) ? 'empty' : 'present'));
        
        if (empty($submittedToken) || empty($sessionToken)) {
            $this->logActivity('CSRF Token Failed', 'Empty token(s)');
            return false;
        }
        
        $isValid = hash_equals($sessionToken, $submittedToken);
        $this->logActivity('CSRF Token Result', $isValid ? 'Valid' : 'Invalid');
        
        return $isValid;
    }
    
    private function getAndValidateInput() {
        // Get JSON input or POST data
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $employeeId = $input['employee_id'] ?? '';
        
        // Validate input
        if (empty($employeeId)) {
            $this->sendResponse(false, 'Employee ID is required', 400);
            return false;
        }
        
        // Sanitize input
        $employeeId = trim($employeeId);
        
        // Validate format (alphanumeric, dashes, underscores allowed)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $employeeId)) {
            $this->sendResponse(false, 'Employee ID contains invalid characters', 400);
            return false;
        }
        
        // Length validation
        if (strlen($employeeId) < 2 || strlen($employeeId) > 20) {
            $this->sendResponse(false, 'Employee ID must be between 2 and 20 characters', 400);
            return false;
        }
        
        return $employeeId;
    }
    
    private function checkEmployeeIdExists($employeeId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM employees WHERE employee_id = ? LIMIT 1");
            $stmt->bind_param('s', $employeeId);
            $stmt->execute();
            
            $resultSet = $stmt->get_result();
            $row = $resultSet->fetch_assoc();
            
            if (!$row) {
                throw new Exception('Database query failed');
            }
            
            return ($row['count'] > 0);
            
        } catch (Exception $e) {
            $this->logError('Database Error', $e->getMessage());
            throw new Exception('Database validation failed');
        }
    }
    
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $currentTime = time();
        
        // Create logs directory
        $logDir = dirname(__DIR__) . '/logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $rateLimitFile = $logDir . 'validation_rate_limits_' . md5($ip) . '.json';
        
        // Load existing requests
        $requests = [];
        if (file_exists($rateLimitFile)) {
            $data = file_get_contents($rateLimitFile);
            $requests = json_decode($data, true) ?: [];
        }
        
        // Filter requests within time window
        $requests = array_filter($requests, function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->rateLimitWindow;
        });
        
        // Check if rate limit exceeded
        if (count($requests) >= $this->rateLimit) {
            return false;
        }
        
        // Add current request
        $requests[] = $currentTime;
        
        // Save updated requests
        file_put_contents($rateLimitFile, json_encode($requests), LOCK_EX);
        
        return true;
    }
    
    private function sendResponse($success, $message, $httpCode = 200, $data = []) {
        http_response_code($httpCode);
        
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response);
        exit;
    }
    
    private function logActivity($activity, $reference = '') {
        $logDir = dirname(__DIR__) . '/logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $refStr = !empty($reference) ? " - {$reference}" : '';
        
        $logEntry = "[{$timestamp}] [VALIDATION] [IP: {$ip}] {$activity}{$refStr}" . PHP_EOL;
        
        file_put_contents($logDir . 'validation.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($context, $message) {
        $logDir = dirname(__DIR__) . '/logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $logEntry = "[{$timestamp}] [VALIDATION ERROR] [IP: {$ip}] Context: {$context} - Message: {$message}" . PHP_EOL;
        
        file_put_contents($logDir . 'validation.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Initialize session and handle request
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists in session
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$validator = new EmployeeIDValidator($conn);
$validator->handleRequest();
?>