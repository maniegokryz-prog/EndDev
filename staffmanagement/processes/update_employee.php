<?php
// Start session to update user info in session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../db_connection.php';

class EmployeeUpdater
{
    private $db;
    private $errors = [];
    private $validatedData = [];

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Method not allowed.', 405);
            return;
        }

        try {
            $this->logActivity('Employee update request started');

            // Check if current user is admin
            $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

            // Basic validation and data collection
            $this->validatedData['employee_id_string'] = $_POST['employee_id'] ?? '';
            $this->validatedData['first_name'] = $_POST['first_name'] ?? '';
            $this->validatedData['middle_name'] = $_POST['middle_name'] ?? '';
            $this->validatedData['last_name'] = $_POST['last_name'] ?? '';
            $this->validatedData['email'] = $_POST['email'] ?? '';
            $this->validatedData['phone'] = $_POST['phone'] ?? '';

            // Only allow admin to update role, department, and position
            if ($isAdmin) {
                $this->validatedData['roles'] = $_POST['roles'] ?? '';
                $this->validatedData['department'] = $_POST['department'] ?? '';
                $this->validatedData['position'] = $_POST['position'] ?? '';
            } else {
                // For non-admin users, fetch current values from database to prevent changes
                $currentEmployee = $this->getEmployeeByStringId($_POST['employee_id'] ?? '');
                if ($currentEmployee) {
                    $fullEmployee = $this->getFullEmployeeData($currentEmployee['id']);
                    $this->validatedData['roles'] = $fullEmployee['roles'] ?? '';
                    $this->validatedData['department'] = $fullEmployee['department'] ?? '';
                    $this->validatedData['position'] = $fullEmployee['position'] ?? '';
                    $this->logActivity('Non-admin user attempted to update - role/dept/position preserved', 'Employee ID: ' . $this->validatedData['employee_id_string']);
                }
            }

            $this->validatedData['hire_date'] = $_POST['hire_date'] ?? '';
            $this->validatedData['status'] = $_POST['status'] ?? 'Active';

            if (empty($this->validatedData['employee_id_string'])) {
                throw new Exception("Employee ID is missing.");
            }

            $this->db->begin_transaction();

            // Get internal employee ID and current data
            $employee = $this->getEmployeeByStringId($this->validatedData['employee_id_string']);
            if (!$employee) {
                throw new Exception("Employee with ID '{$this->validatedData['employee_id_string']}' not found.");
            }
            $employeeId = $employee['id'];

            // Preserve hire_date if not provided
            if (empty($this->validatedData['hire_date'])) {
                $this->validatedData['hire_date'] = $employee['hire_date'];
            }

            // Handle profile picture upload
            $this->handleProfilePictureUpload($employee);

            // 1. Update basic employee info
            $this->updateEmployeeDetails($employeeId);

            $this->db->commit();

            $this->logActivity('Employee updated successfully', 'Employee ID: ' . $this->validatedData['employee_id_string']);

            // Add cache control headers to prevent caching
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Add timestamp to force browser reload
            header('Location: ../staff_profile.php?id=' . urlencode($this->validatedData['employee_id_string']) . '&status=updated&t=' . time());
            exit;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Update Failed', $e->getMessage());
            // Redirect with error message
            $_SESSION['update_error'] = 'Update failed: ' . $e->getMessage();
            header('Location: ../staff_profile.php?id=' . urlencode($_POST['employee_id'] ?? ''));
            exit;
        }
    }

    private function getEmployeeByStringId($employeeIdString)
    {
        $stmt = $this->db->prepare("SELECT id, profile_photo, hire_date FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeIdString);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getFullEmployeeData($employeeId)
    {
        $stmt = $this->db->prepare("SELECT roles, department, position FROM employees WHERE id = ?");
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function handleProfilePictureUpload($employee)
    {
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
            // Upload to root/assets/profile_pic/ (go up 2 levels from processes folder)
            $uploadDir = dirname(dirname(__DIR__)) . '/assets/profile_pic/';
            if (!file_exists($uploadDir)) {
                // Create the directory recursively. The mode is ignored on Windows.
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Failed to create profile picture directory. Please check permissions.");
                }
            }
            if (!is_writable($uploadDir)) {
                throw new Exception("The profile picture directory is not writable. Please check permissions: {$uploadDir}");
            }

            // --- Delete Old Photos with Same Employee ID ---
            // Remove ALL files starting with this employee ID (to avoid duplicates)
            $employeeId = $this->validatedData['employee_id_string'];
            $files = glob($uploadDir . $employeeId . '_*.*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $this->logActivity('Old profile picture deleted', "File: " . basename($file));
                }
            }

            // Also delete exact match if exists (e.g., MA22013613.jpg)
            $extensions = ['jpg', 'jpeg', 'png', 'gif'];
            foreach ($extensions as $ext) {
                $exactFile = $uploadDir . $employeeId . '.' . $ext;
                if (file_exists($exactFile) && is_file($exactFile)) {
                    unlink($exactFile);
                    $this->logActivity('Old profile picture deleted', "File: " . basename($exactFile));
                }
            }

            // --- Generate Consistent Filename ---
            // Use employee_id + extension (no timestamp, so it overwrites)
            $fileExtension = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $newFilename = $employeeId . '.' . $fileExtension;
            $newFilepath = $uploadDir . $newFilename;

            // Delete ANY existing profile pictures for this employee to prevent overlapping extensions (.jpg vs .png)
            $existingFiles = glob($uploadDir . $employeeId . '.*');
            if ($existingFiles) {
                foreach ($existingFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Move the uploaded file (overwrites if exists)
            if (!move_uploaded_file($photo['tmp_name'], $newFilepath)) {
                throw new Exception("Failed to save the uploaded profile picture.");
            }

            // --- Set File Permissions ---
            // This is crucial for IIS to be able to read the new file.
            chmod($newFilepath, 0644);

            // --- Database Update ---
            $relativePath = 'assets/profile_pic/' . $newFilename;
            $stmt = $this->db->prepare("UPDATE employees SET profile_photo = ? WHERE id = ?");
            $stmt->bind_param('si', $relativePath, $employee['id']);
            if (!$stmt->execute()) {
                // If DB update fails, try to delete the uploaded file
                unlink($newFilepath);
                throw new Exception("Failed to update profile picture path in the database.");
            }

            // Update session if this is the current user's profile
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $employee['id'] && empty($_SESSION['is_system_admin'])) {
                $_SESSION['profile_photo'] = $relativePath;
                // Force session write to ensure it persists
                session_write_close();
                session_start();
                $this->logActivity('Session profile_photo updated', "New path: {$relativePath}");
            }

            // Sync profile photo update to cloud
            require_once __DIR__ . '/../../db_cloud_sync.php';
            syncToCloud('employees', [
                'profile_photo' => $relativePath
            ], 'update', "employee_id = '{$this->validatedData['employee_id_string']}'");

            $this->logActivity('Profile picture updated', "Employee ID: {$employee['id']}, Path: {$relativePath}");
        }
    }

    private function updateEmployeeDetails($employeeId)
    {
        $stmt = $this->db->prepare("
            UPDATE employees SET
                first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?,
                roles = ?, department = ?, position = ?, hire_date = ?, status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            'ssssssssssi',
            $this->validatedData['first_name'],
            $this->validatedData['middle_name'],
            $this->validatedData['last_name'],
            $this->validatedData['email'],
            $this->validatedData['phone'],
            $this->validatedData['roles'],
            $this->validatedData['department'],
            $this->validatedData['position'],
            $this->validatedData['hire_date'],
            $this->validatedData['status'],
            $employeeId
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to update employee details: " . $stmt->error);
        }

        // Update session if this is the current user's profile
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $employeeId && empty($_SESSION['is_system_admin'])) {
            $_SESSION['user_name'] = trim($this->validatedData['first_name'] . ' ' . $this->validatedData['last_name']);
            $_SESSION['department'] = $this->validatedData['department'];
            $_SESSION['position'] = $this->validatedData['position'];
            // Force session write to ensure it persists
            session_write_close();
            session_start();
            $this->logActivity('Session user info updated', "Name: {$_SESSION['user_name']}");
        }

        // Sync employee update to cloud
        require_once __DIR__ . '/../../db_cloud_sync.php';
        syncToCloud('employees', [
            'employee_id' => $this->validatedData['employee_id_string'],
            'first_name' => $this->validatedData['first_name'],
            'middle_name' => $this->validatedData['middle_name'],
            'last_name' => $this->validatedData['last_name'],
            'email' => $this->validatedData['email'],
            'phone' => $this->validatedData['phone'],
            'roles' => $this->validatedData['roles'],
            'department' => $this->validatedData['department'],
            'position' => $this->validatedData['position'],
            'hire_date' => $this->validatedData['hire_date'],
            'status' => $this->validatedData['status']
        ], 'update', "employee_id = '{$this->validatedData['employee_id_string']}'");

        $this->logActivity('Employee details updated', "ID: {$employeeId}");
    }

    private function logActivity($activity, $reference = '')
    {
        // Logging implementation...
    }

    private function logError($context, $message)
    {
        file_put_contents('../../update_error.log', date('Y-m-d H:i:s') . " - $context: $message\n", FILE_APPEND);
    }

    private function sendErrorResponse($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

$updater = new EmployeeUpdater($conn);
$updater->handleRequest();
?>