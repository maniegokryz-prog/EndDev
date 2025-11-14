<?php
/**
 * Secure Profile Picture Upload API Endpoint
 * 
 * Features:
 * - Role-based authorization (Admin can update any, Employee only their own)
 * - File validation (JPEG/PNG, max 5MB)
 * - Secure file storage with sanitized naming
 * - Database update with transaction support
 * - Comprehensive error handling and logging
 * 
 * Endpoint: POST /staffmanagement/api/upload_profile_picture.php
 * Content-Type: multipart/form-data
 * 
 * Parameters:
 * - profile_picture (file): Image file (JPEG/PNG, max 5MB)
 * - employee_id (string): Target employee ID
 * 
 * Authorization:
 * - Must be logged in (session-based)
 * - Admin: Can upload for any employee
 * - Employee: Can only upload for their own employee_id
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// Enable error logging (disable display for production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent header issues
ob_start();

// Database connection and session
require_once '../../db_connection.php';

// Clear any buffered output before sending JSON
ob_end_clean();

/**
 * Secure Profile Picture Upload Handler
 */
class SecureProfilePictureAPI {
    private $db;
    private $uploadDir;
    private $logFile;
    
    // Configuration
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const MAX_DIMENSION = 4000; // 4000x4000px
    private const OPTIMIZE_MAX_DIMENSION = 1000; // Resize to max 1000px
    
    private $currentUser = null;
    private $isAdmin = false;
    
    public function __construct($database) {
        $this->db = $database;
        $this->uploadDir = dirname(__DIR__) . '/assets/profile_pic/';
        $this->logFile = dirname(__DIR__) . '/logs/api_upload.log';
        
        // Ensure directories exist
        $this->initializeDirectories();
    }
    
    /**
     * Main request handler
     */
    public function handleRequest() {
        try {
            // Step 1: Validate HTTP method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->errorResponse('Method not allowed. Use POST.', 405);
            }
            
            // Step 2: Authenticate user
            if (!$this->authenticateUser()) {
                return $this->errorResponse('Unauthorized. Please log in.', 401);
            }
            
            // Step 3: Validate CSRF token
            if (!$this->validateCSRFToken()) {
                return $this->errorResponse('Invalid CSRF token. Request rejected.', 403);
            }
            
            // Step 4: Get and validate employee_id
            $targetEmployeeId = $this->getTargetEmployeeId();
            
            // Step 5: Check authorization
            if (!$this->authorizeUpload($targetEmployeeId)) {
                return $this->errorResponse(
                    'Forbidden. You can only update your own profile picture.', 
                    403
                );
            }
            
            // Step 6: Validate file upload
            $file = $this->validateFileUpload();
            
            // Step 7: Get employee record
            $employee = $this->getEmployeeRecord($targetEmployeeId);
            if (!$employee) {
                return $this->errorResponse('Employee not found.', 404);
            }
            
            // Step 8: Process upload with transaction
            $result = $this->processUpload($file, $employee);
            
            // Step 9: Log activity
            $this->logActivity('SUCCESS', "Profile picture updated for employee: {$targetEmployeeId}");
            
            // Step 10: Return success response
            return $this->successResponse($result);
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', $e->getMessage());
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
    
    /**
     * Authenticate user from session
     */
    private function authenticateUser() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        $this->currentUser = [
            'type' => $_SESSION['user_type'] ?? null,
            'id' => $_SESSION['user_id'] ?? null,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ];
        
        // Check if user is admin
        $this->isAdmin = ($this->currentUser['type'] === 'admin');
        
        return true;
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRFToken() {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get target employee ID from request
     */
    private function getTargetEmployeeId() {
        $employeeId = trim($_POST['employee_id'] ?? '');
        
        if (empty($employeeId)) {
            throw new Exception('Employee ID is required.');
        }
        
        return $employeeId;
    }
    
    /**
     * Check if user is authorized to upload for target employee
     */
    private function authorizeUpload($targetEmployeeId) {
        // Admin can upload for anyone
        if ($this->isAdmin) {
            return true;
        }
        
        // Employee can only upload for themselves
        if ($this->currentUser['type'] === 'employee') {
            return ($this->currentUser['employee_id'] === $targetEmployeeId);
        }
        
        return false;
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFileUpload() {
        // Check if file was uploaded
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('No file uploaded. Please select an image.');
        }
        
        $file = $_FILES['profile_picture'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }
        
        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $sizeMB = self::MAX_FILE_SIZE / 1024 / 1024;
            throw new Exception("File size exceeds {$sizeMB}MB limit.");
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new Exception('Invalid file type. Only JPEG and PNG images are allowed.');
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception('Invalid file extension. Only .jpg, .jpeg, and .png are allowed.');
        }
        
        // Verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('File is not a valid image.');
        }
        
        // Check image dimensions
        if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
            throw new Exception("Image dimensions exceed maximum of " . self::MAX_DIMENSION . "x" . self::MAX_DIMENSION . "px.");
        }
        
        return [
            'file' => $file,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }
    
    /**
     * Get employee record from database
     */
    private function getEmployeeRecord($employeeId) {
        $stmt = $this->db->prepare("SELECT id, employee_id, profile_photo FROM employees WHERE employee_id = ? AND status = 'active'");
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Process file upload with transaction
     */
    private function processUpload($fileData, $employee) {
        // Start transaction
        $this->db->begin_transaction();
        
        try {
            // Generate secure filename
            $filename = $this->generateSecureFilename($employee['employee_id'], $fileData['extension']);
            $targetPath = $this->uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['file']['tmp_name'], $targetPath)) {
                throw new Exception('Failed to save uploaded file.');
            }
            
            // Set file permissions
            chmod($targetPath, 0644);
            
            // Optimize image
            $this->optimizeImage($targetPath, $fileData['extension'], $fileData['width'], $fileData['height']);
            
            // Update database
            $relativePath = 'assets/profile_pic/' . $filename;
            $this->updateDatabase($employee['id'], $relativePath);
            
            // Delete old photo (if exists and not default)
            $this->deleteOldPhoto($employee['profile_photo']);
            
            // Commit transaction
            $this->db->commit();
            
            // Return result
            return [
                'employee_id' => $employee['employee_id'],
                'profile_picture_url' => '../' . $relativePath,
                'filename' => $filename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            // Rollback on error
            $this->db->rollback();
            
            // Delete uploaded file if it exists
            if (isset($targetPath) && file_exists($targetPath)) {
                @unlink($targetPath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($employeeId, $extension) {
        // Sanitize employee ID
        $safeEmployeeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employeeId);
        
        // Add timestamp and random string for uniqueness
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "{$safeEmployeeId}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Optimize uploaded image
     */
    private function optimizeImage($filePath, $extension, $width, $height) {
        // Only resize if larger than max dimension
        if ($width <= self::OPTIMIZE_MAX_DIMENSION && $height <= self::OPTIMIZE_MAX_DIMENSION) {
            return;
        }
        
        // Load image based on type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($filePath);
                break;
            default:
                return;
        }
        
        if (!$image) {
            return;
        }
        
        // Calculate new dimensions
        if ($width > $height) {
            $newWidth = self::OPTIMIZE_MAX_DIMENSION;
            $newHeight = intval(($height / $width) * self::OPTIMIZE_MAX_DIMENSION);
        } else {
            $newHeight = self::OPTIMIZE_MAX_DIMENSION;
            $newWidth = intval(($width / $height) * self::OPTIMIZE_MAX_DIMENSION);
        }
        
        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resized, $filePath, 85);
                break;
            case 'png':
                imagepng($resized, $filePath, 8);
                break;
        }
        
        // Free memory
        imagedestroy($image);
        imagedestroy($resized);
    }
    
    /**
     * Update database with new photo path
     */
    private function updateDatabase($employeeInternalId, $photoPath) {
        $stmt = $this->db->prepare("UPDATE employees SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $photoPath, $employeeInternalId);
        
        if (!$stmt->execute()) {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No rows updated. Employee may not exist.');
        }
    }
    
    /**
     * Delete old profile photo
     */
    private function deleteOldPhoto($oldPhotoPath) {
        if (empty($oldPhotoPath) || $oldPhotoPath === 'N/A') {
            return;
        }
        
        // Don't delete default images
        $defaultImages = ['user.png', 'pic.png', 'default.png', 'avatar.png'];
        $filename = basename($oldPhotoPath);
        
        if (in_array($filename, $defaultImages)) {
            return;
        }
        
        // Construct full path
        $fullPath = dirname(__DIR__) . '/' . $oldPhotoPath;
        
        // Delete if exists
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
    
    /**
     * Initialize required directories
     */
    private function initializeDirectories() {
        // Create upload directory
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        // Create log directory
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds maximum allowed size.';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension.';
            default:
                return 'Unknown upload error.';
        }
    }
    
    /**
     * Log activity
     */
    private function logActivity($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $user = $this->currentUser['type'] ?? 'unknown';
        $userId = $this->currentUser['employee_id'] ?? $this->currentUser['username'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] [{$level}] User: {$user}#{$userId} - {$message}" . PHP_EOL;
        
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Success response
     */
    private function successResponse($data) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture uploaded successfully.',
            'data' => $data
        ]);
        exit;
    }
    
    /**
     * Error response
     */
    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
}

// ===== Main Execution =====
try {
    // Check database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed.');
    }
    
    // Initialize API handler
    $api = new SecureProfilePictureAPI($conn);
    $api->handleRequest();
    
} catch (Throwable $e) {
    // Catch any uncaught errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'code' => 500
    ]);
    
    // Log fatal error
    $logFile = dirname(__DIR__) . '/logs/api_upload.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] [FATAL] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
