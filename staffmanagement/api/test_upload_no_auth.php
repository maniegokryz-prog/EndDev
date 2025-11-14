<?php
/**
 * Profile Picture Upload API - Development Test Version
 * 
 * ⚠️ WARNING: This version has RELAXED SECURITY for testing purposes only
 * DO NOT use in production! Use api/upload_profile_picture.php instead.
 * 
 * This version allows:
 * - Optional authentication (can test without login)
 * - Manual employee_id specification
 * - Bypasses CSRF check in test mode
 */

// Enable test mode
define('TEST_MODE', true);

// Security headers
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();
require_once '../../db_connection.php';
ob_end_clean();

class TestProfilePictureAPI {
    private $db;
    private $uploadDir;
    private $logFile;
    
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const MAX_DIMENSION = 4000;
    private const OPTIMIZE_MAX_DIMENSION = 1000;
    
    public function __construct($database) {
        $this->db = $database;
        $this->uploadDir = dirname(__DIR__) . '/assets/profile_pic/';
        $this->logFile = dirname(__DIR__) . '/logs/api_test_upload.log';
        $this->initializeDirectories();
    }
    
    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->errorResponse('Method not allowed. Use POST.', 405);
            }
            
            $targetEmployeeId = $this->getTargetEmployeeId();
            $file = $this->validateFileUpload();
            $employee = $this->getEmployeeRecord($targetEmployeeId);
            
            if (!$employee) {
                return $this->errorResponse('Employee not found.', 404);
            }
            
            $result = $this->processUpload($file, $employee);
            $this->logActivity('SUCCESS', "Profile picture updated for employee: {$targetEmployeeId}");
            
            return $this->successResponse($result);
            
        } catch (Exception $e) {
            $this->logActivity('ERROR', $e->getMessage());
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
    
    private function getTargetEmployeeId() {
        $employeeId = trim($_POST['employee_id'] ?? '');
        if (empty($employeeId)) {
            throw new Exception('Employee ID is required.');
        }
        return $employeeId;
    }
    
    private function validateFileUpload() {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('No file uploaded.');
        }
        
        $file = $_FILES['profile_picture'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception("File size exceeds 5MB limit.");
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new Exception('Invalid file type. Only JPEG and PNG images are allowed.');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception('Invalid file extension.');
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('File is not a valid image.');
        }
        
        if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
            throw new Exception("Image dimensions exceed maximum.");
        }
        
        return [
            'file' => $file,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }
    
    private function getEmployeeRecord($employeeId) {
        $stmt = $this->db->prepare("SELECT id, employee_id, profile_photo FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function processUpload($fileData, $employee) {
        $this->db->begin_transaction();
        
        try {
            $filename = $this->generateSecureFilename($employee['employee_id'], $fileData['extension']);
            $targetPath = $this->uploadDir . $filename;
            
            if (!move_uploaded_file($fileData['file']['tmp_name'], $targetPath)) {
                throw new Exception('Failed to save uploaded file.');
            }
            
            chmod($targetPath, 0644);
            $this->optimizeImage($targetPath, $fileData['extension'], $fileData['width'], $fileData['height']);
            
            $relativePath = 'assets/profile_pic/' . $filename;
            $this->updateDatabase($employee['id'], $relativePath);
            $this->deleteOldPhoto($employee['profile_photo']);
            
            $this->db->commit();
            
            return [
                'employee_id' => $employee['employee_id'],
                'profile_picture_url' => '../' . $relativePath,
                'filename' => $filename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            if (isset($targetPath) && file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw $e;
        }
    }
    
    private function generateSecureFilename($employeeId, $extension) {
        $safeEmployeeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employeeId);
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        return "{$safeEmployeeId}_{$timestamp}_{$random}.{$extension}";
    }
    
    private function optimizeImage($filePath, $extension, $width, $height) {
        if ($width <= self::OPTIMIZE_MAX_DIMENSION && $height <= self::OPTIMIZE_MAX_DIMENSION) {
            return;
        }
        
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
        
        if (!$image) return;
        
        if ($width > $height) {
            $newWidth = self::OPTIMIZE_MAX_DIMENSION;
            $newHeight = intval(($height / $width) * self::OPTIMIZE_MAX_DIMENSION);
        } else {
            $newHeight = self::OPTIMIZE_MAX_DIMENSION;
            $newWidth = intval(($width / $height) * self::OPTIMIZE_MAX_DIMENSION);
        }
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resized, $filePath, 85);
                break;
            case 'png':
                imagepng($resized, $filePath, 8);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($resized);
    }
    
    private function updateDatabase($employeeInternalId, $photoPath) {
        $stmt = $this->db->prepare("UPDATE employees SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $photoPath, $employeeInternalId);
        
        if (!$stmt->execute()) {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No rows updated.');
        }
    }
    
    private function deleteOldPhoto($oldPhotoPath) {
        if (empty($oldPhotoPath) || $oldPhotoPath === 'N/A') {
            return;
        }
        
        $defaultImages = ['user.png', 'pic.png', 'default.png', 'avatar.png'];
        $filename = basename($oldPhotoPath);
        
        if (in_array($filename, $defaultImages)) {
            return;
        }
        
        $fullPath = dirname(__DIR__) . '/' . $oldPhotoPath;
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
    
    private function initializeDirectories() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
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
    
    private function logActivity($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] TEST MODE - {$message}" . PHP_EOL;
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function successResponse($data) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture uploaded successfully. (TEST MODE - No authentication required)',
            'data' => $data
        ]);
        exit;
    }
    
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

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed.');
    }
    
    $api = new TestProfilePictureAPI($conn);
    $api->handleRequest();
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'code' => 500
    ]);
}
?>
