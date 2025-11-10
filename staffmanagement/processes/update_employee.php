<?php
require '../../db_connection.php';

class EmployeeUpdater {
    private $db;
    private $errors = [];
    private $validatedData = [];

    public function __construct($database) {
        $this->db = $database;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Method not allowed.', 405);
            return;
        }

        try {
            $this->logActivity('Employee update request started');

            // Basic validation and data collection
            $this->validatedData['employee_id_string'] = $_POST['employee_id'] ?? '';
            $this->validatedData['first_name'] = $_POST['first_name'] ?? '';
            $this->validatedData['middle_name'] = $_POST['middle_name'] ?? '';
            $this->validatedData['last_name'] = $_POST['last_name'] ?? '';
            $this->validatedData['email'] = $_POST['email'] ?? '';
            $this->validatedData['phone'] = $_POST['phone'] ?? '';
            $this->validatedData['roles'] = $_POST['roles'] ?? '';
            $this->validatedData['department'] = $_POST['department'] ?? '';
            $this->validatedData['position'] = $_POST['position'] ?? '';
            $this->validatedData['hire_date'] = $_POST['hire_date'] ?? '';
            $this->validatedData['status'] = $_POST['status'] ?? 'Active';

            if (empty($this->validatedData['employee_id_string'])) {
                throw new Exception("Employee ID is missing.");
            }

            $this->db->begin_transaction();

            // Get internal employee ID
            $employee = $this->getEmployeeByStringId($this->validatedData['employee_id_string']);
            if (!$employee) {
                throw new Exception("Employee with ID '{$this->validatedData['employee_id_string']}' not found.");
            }
            $employeeId = $employee['id'];

            // Handle profile picture upload
            $this->handleProfilePictureUpload($employee);

            // 1. Update basic employee info
            $this->updateEmployeeDetails($employeeId);

            $this->db->commit();

            $this->logActivity('Employee updated successfully', 'Employee ID: ' . $this->validatedData['employee_id_string']);
            header('Location: ../staffinfo.php?id=' . urlencode($this->validatedData['employee_id_string']) . '&status=updated');
            exit;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Update Failed', $e->getMessage());
            // Redirect with error message
            $_SESSION['update_error'] = 'Update failed: ' . $e->getMessage();
            header('Location: ../staffinfo.php?id=' . urlencode($_POST['employee_id'] ?? ''));
            exit;
        }
    }

    private function getEmployeeByStringId($employeeIdString) {
        $stmt = $this->db->prepare("SELECT id, profile_photo FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeIdString);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function handleProfilePictureUpload($employee) {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['profile_photo'];

            // --- Validation ---
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($photo['type'], $allowedTypes)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }

            $maxSize = 5 * 1024 * 1024; // 5 MB
            if ($photo['size'] > $maxSize) {
                throw new Exception("File size exceeds the 5MB limit.");
            }

            // --- File Processing ---
            $uploadDir = dirname(__DIR__) . '/assets/profile_pic/';
            if (!file_exists($uploadDir)) {
                // Create the directory recursively. The mode is ignored on Windows.
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Failed to create profile picture directory. Please check permissions.");
                }
            }
            if (!is_writable($uploadDir)) {
                throw new Exception("The profile picture directory is not writable. Please check permissions: {$uploadDir}");
            }

            // Generate a unique filename: employee_id_timestamp.ext
            $fileExtension = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $newFilename = $this->validatedData['employee_id_string'] . '_' . time() . '.' . $fileExtension;
            $newFilepath = $uploadDir . $newFilename;

            // Move the uploaded file
            if (!move_uploaded_file($photo['tmp_name'], $newFilepath)) {
                throw new Exception("Failed to save the uploaded profile picture.");
            }

            // --- Set File Permissions ---
            // This is crucial for IIS to be able to read the new file.
            chmod($newFilepath, 0644);

            // --- Database Update ---
            $relativePath = '../../assets/profile_pic/' . $newFilename;
            $stmt = $this->db->prepare("UPDATE employees SET profile_photo = ? WHERE id = ?");
            $stmt->bind_param('si', $relativePath, $employee['id']);
            if (!$stmt->execute()) {
                // If DB update fails, try to delete the uploaded file
                unlink($newFilepath);
                throw new Exception("Failed to update profile picture path in the database.");
            }

            // --- Cleanup Old Photo ---
            $oldPhotoPath = $employee['profile_photo'] ?? '';
            $defaultPhoto = '../../assets/profile_pic/user.png';
            if (!empty($oldPhotoPath) && $oldPhotoPath !== $defaultPhoto) {
                $fullOldPath = dirname(__DIR__) . '/' . $oldPhotoPath;
                if (file_exists($fullOldPath)) {
                    unlink($fullOldPath);
                }
            }

            $this->logActivity('Profile picture updated', "Employee ID: {$employee['id']}, Path: {$relativePath}");
        }
    }

    private function updateEmployeeDetails($employeeId) {
        $stmt = $this->db->prepare("
            UPDATE employees SET
                first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?,
                roles = ?, department = ?, position = ?, hire_date = ?, status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ssssssssssi',
            $this->validatedData['first_name'], $this->validatedData['middle_name'], $this->validatedData['last_name'],
            $this->validatedData['email'], $this->validatedData['phone'], $this->validatedData['roles'],
            $this->validatedData['department'], $this->validatedData['position'], $this->validatedData['hire_date'],
            $this->validatedData['status'], $employeeId
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to update employee details: " . $stmt->error);
        }
        $this->logActivity('Employee details updated', "ID: {$employeeId}");
    }

    private function logActivity($activity, $reference = '') {
        // Logging implementation...
    }

    private function logError($context, $message) {
        // Error logging implementation...
    }

    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

$updater = new EmployeeUpdater($conn);
$updater->handleRequest();
?>