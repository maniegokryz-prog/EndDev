<?php
// processes/update_face_registration.php
ob_start(); // Buffer output

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Define global flag to prevent db_connection from setting standard headers/error handling if needed
$GLOBALS['error_reporting_configured'] = true;

try {
    require_once __DIR__ . '/../../db_connection.php';
    require_once __DIR__ . '/../../db_cloud_sync.php';

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Initialization error: ' . $e->getMessage()]);
    exit;
}


class FaceRegistrationUpdater
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
            $this->logActivity('Face registration update request started');

            // Collect data
            $this->validatedData['employee_id_string'] = $_POST['employee_id'] ?? '';
            $this->validatedData['face_photos'] = $_POST['face_photos'] ?? '';

            if (empty($this->validatedData['employee_id_string'])) {
                throw new Exception("Employee ID is missing.");
            }
            if (empty($this->validatedData['face_photos'])) {
                throw new Exception("No face photos provided.");
            }

            $this->db->begin_transaction();

            // Get internal employee ID and Name
            $employee = $this->getEmployeeByStringId($this->validatedData['employee_id_string']);
            if (!$employee) {
                throw new Exception("Employee with ID '{$this->validatedData['employee_id_string']}' not found.");
            }
            $firstName = $employee['first_name'];
            $lastName = $employee['last_name'];
            $internalId = $employee['id'];

            // Process photos and get the path of the first one (we just need them saved for embeddings)
            $this->processFacePhotos($this->validatedData['employee_id_string'], $firstName, $lastName);
            
            // Logic to update profile_photo has been removed to keep the default/existing picture.

            $this->db->commit();

            // Generate embeddings (Outside transaction)
            $embeddingResult = $this->generateFaceEmbeddings($internalId, $this->validatedData['employee_id_string']);

            if ($embeddingResult) {
                $statusMsg = 'Face registration and embeddings completed successfully.';
                $this->logActivity($statusMsg, 'Employee ID: ' . $this->validatedData['employee_id_string']);
                ob_clean();
                echo json_encode(['success' => true, 'message' => $statusMsg]);
            } else {
                $statusMsg = 'Face photos saved, but embedding generation failed. Please try again or contact support.';
                $this->logError('Embedding Error', $statusMsg);
                // We still fail effectively because the face ID feature won't work
                ob_clean();
                echo json_encode(['success' => true, 'message' => $statusMsg . ' (Photos saved)']);
            }

            exit;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Face Registration Failed', $e->getMessage());
            $this->sendErrorResponse($e->getMessage(), 500);
            exit;
        }
    }

    private function getEmployeeByStringId($employeeIdString)
    {
        $stmt = $this->db->prepare("SELECT id, first_name, last_name FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeIdString);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function processFacePhotos($employeeId, $firstName, $lastName)
    {
        $facePhotosJson = $this->validatedData['face_photos'] ?? '';

        if (empty($facePhotosJson)) {
            return null;
        }

        $facePhotos = json_decode($facePhotosJson, true);
        if (!$facePhotos || !is_array($facePhotos)) {
            return null;
        }

        // Create uploads directory if it doesn't exist
        $uploadDir = dirname(__DIR__) . '/../uploads/';
        if (!file_exists($uploadDir)) {
            // Try one level up if double dirname went too far or not far enough
            // processes is inside staffmanagement. __DIR__ is processes. 
            // dirname(__DIR__) is staffmanagement. 
            // dirname(dirname(__DIR__)) is EndDev (root).
            // uploads is in EndDev/uploads.
            $uploadDir = dirname(dirname(__DIR__)) . '/uploads/';
        }
        // Correct path construction
        $rootPath = dirname(dirname(__DIR__));
        $uploadDir = $rootPath . '/uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $firstSavedPath = null;

        foreach ($facePhotos as $index => $photoData) {
            if (isset($photoData['dataURL']) && isset($photoData['angle'])) {
                $savedPath = $this->saveFacePhoto($photoData['dataURL'], $employeeId, $firstName, $lastName, $photoData['angle'], $index + 1);

                // Return the first successfully saved photo path (relative to root)
                if ($savedPath && !$firstSavedPath) {
                    $firstSavedPath = $savedPath;
                }
            }
        }

        return $firstSavedPath;
    }

    private function saveFacePhoto($dataURL, $employeeId, $firstName, $lastName, $angle, $angleNumber)
    {
        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,/', $dataURL, $matches)) {
            $imageType = $matches[1];
            $base64Data = substr($dataURL, strpos($dataURL, ',') + 1);
            $imageData = base64_decode($base64Data);

            if ($imageData === false) {
                $this->logError('Face Photo Processing', "Failed to decode base64 image for employee: $employeeId");
                return false;
            }

            // Generate filename: ID_firstname_lastname_anglenumber.ext
            $filename = $employeeId . '_' . $firstName . '_' . $lastName . '_' . $angleNumber . '.' . $imageType;
            $rootPath = dirname(dirname(__DIR__));
            $filepath = $rootPath . '/uploads/' . $filename;

            // Save the file
            if (file_put_contents($filepath, $imageData)) {
                $this->logActivity('Face Photo Saved', "File: $filename, Angle: $angle, Employee: $employeeId");
                return 'uploads/' . $filename; // Return relative path
            } else {
                $this->logError('Face Photo Processing', "Failed to save image file: $filename");
                return false;
            }
        }

        return false;
    }

    private function generateFaceEmbeddings($dbEmployeeId, $employeeId)
    {
        /**
         * Generate face embeddings from uploaded photos using Python script.
         */

        // --- NEW: Delete existing embeddings for this employee first ---
        // This ensures a clean re-registration
        $stmt = $this->db->prepare("DELETE FROM face_embeddings WHERE employee_id = ?");
        $stmt->bind_param("i", $dbEmployeeId);
        $stmt->execute();
        $this->logActivity('Old Embeddings Deleted', "Employee DB ID: $dbEmployeeId");
        $stmt->close();

        $this->logActivity('Face Embedding Generation Started', "Employee ID: $employeeId, DB ID: $dbEmployeeId");

        // Path to Python script in staffmanagement folder
        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'generate_face_embeddings.py';

        if (!file_exists($scriptPath)) {
            $this->logError('Face Embedding Generation', "Python script not found at: $scriptPath");
            return false;
        }

        // Database credentials
        $dbHost = 'localhost';
        $dbUser = 'attendance_admin';
        $dbPassword = 'Confirmp@ssword123';
        $dbName = 'database_records';

        // Get Python executable path
        $pythonExe = $this->findPythonExecutable();

        if (!$pythonExe) {
            $this->logError('Face Embedding Generation', 'Python executable not found');
            return false;
        }

        // Log the paths for debugging
        $this->logActivity('Python Executable Command', $pythonExe);
        $this->logActivity('Script Path', $scriptPath);

        // For arguments, escape them properly
        $escapedEmployeeId = escapeshellarg($employeeId);
        $escapedDbEmployeeId = escapeshellarg($dbEmployeeId);
        $escapedDbHost = escapeshellarg($dbHost);
        $escapedDbUser = escapeshellarg($dbUser);
        $escapedDbPassword = escapeshellarg($dbPassword);
        $escapedDbName = escapeshellarg($dbName);

        // Set working directory to staffmanagement folder (where script is located)
        $workingDir = dirname(__DIR__);
        $oldDir = getcwd();
        chdir($workingDir);

        // Check if we found the venv Python
        $isVenvPython = (strpos($pythonExe, '.venv') !== false || strpos($pythonExe, 'env' . DIRECTORY_SEPARATOR) !== false);

        // Don't add quotes here - we'll add them properly in the command construction
        $commandArgs = $escapedEmployeeId . ' ' . $escapedDbEmployeeId . ' ' .
            $escapedDbHost . ' ' . $escapedDbUser . ' ' .
            $escapedDbPassword . ' ' . $escapedDbName;

        $command = '';

        if ($isVenvPython) {
            $this->logActivity('Face Embedding Generation', "Using VENV Python. Skipping SET PYTHONPATH.");
            // Use escapeshellarg for the entire command to avoid quote nesting issues
            $command = '"' . $pythonExe . '" "' . $scriptPath . '" ' . $commandArgs . ' 2>&1';
        } else {
            // Using System Python. We must set PYTHONPATH to find venv packages.
            $this->logActivity('Face Embedding Generation', "Using System Python. Setting PYTHONPATH.");
            // Get project root (go up from staffmanagement to EndDev)
            $projectRoot = dirname(dirname(__DIR__));
            $venvPath = $projectRoot . DIRECTORY_SEPARATOR . '.venv';
            $venvSitePackages = $venvPath . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'site-packages';

            $command = 'cmd /c "SET PYTHONPATH=' . $venvSitePackages . ' && ' .
                'SET VIRTUAL_ENV=' . $venvPath . ' && ' .
                '"' . $pythonExe . '" "' . $scriptPath . '" ' .
                $commandArgs . ' 2>&1"';
        }

        $this->logActivity('Executing Python Script', "Command: $command");

        // Execute the Python script
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Restore original directory
        chdir($oldDir);

        // Log the output
        $outputStr = implode("\n", $output);
        $this->logActivity('Python Script Output', $outputStr);

        // Check if execution was successful
        if ($returnCode === 0) {
            $this->logActivity('Face Embedding Generation Successful', "Employee ID: $employeeId");

            // --- NEW: Sync to Kiosk File (embd_up.py) ---
            $this->syncKioskEmbeddings();
            return true;
        } else {
            $this->logError('Face Embedding Generation Failed', "Return code: $returnCode, Output: $outputStr");
            return false;
        }
    }

    private function syncKioskEmbeddings()
    {
        // Run embd_up.py to update authorized_embeddings.npy
        $this->logActivity('Kiosk File Sync', 'Starting sync...');

        $rootPath = dirname(dirname(__DIR__)); // EndDev
        $scriptPath = $rootPath . DIRECTORY_SEPARATOR . 'faceid' . DIRECTORY_SEPARATOR . 'embd_up.py';

        if (!file_exists($scriptPath)) {
            $this->logError('Kiosk Sync', "embd_up.py not found at: $scriptPath");
            return;
        }

        $pythonExe = $this->findPythonExecutable();
        if (!$pythonExe) {
            $this->logError('Kiosk Sync', 'Python executable not found');
            return;
        }

        // Command: python embd_up.py once
        // Similar setup to generateFaceEmbeddings
        $workingDir = dirname($scriptPath); // EndDev/faceid
        // Note: embd_up.py expects database folder relative to itself, so we run from faceid dir

        // We need to setup environment just like before if using system python
        $isVenvPython = (strpos($pythonExe, '.venv') !== false || strpos($pythonExe, 'env' . DIRECTORY_SEPARATOR) !== false);
        $command = '';

        if ($isVenvPython) {
            $command = 'cd "' . $workingDir . '" && "' . $pythonExe . '" "' . $scriptPath . '" once 2>&1';
        } else {
            $venvPath = $rootPath . DIRECTORY_SEPARATOR . '.venv';
            $venvSitePackages = $venvPath . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'site-packages';
            $command = 'cd "' . $workingDir . '" && cmd /c "SET PYTHONPATH=' . $venvSitePackages . ' && SET VIRTUAL_ENV=' . $venvPath . ' && "' . $pythonExe . '" "' . $scriptPath . '" once 2>&1"';
        }

        $this->logActivity('Kiosk Sync Command', $command);
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        if ($returnCode === 0) {
            $this->logActivity('Kiosk Sync Successful', $outputStr);
        } else {
            $this->logError('Kiosk Sync Failed', "Return code: $returnCode, Output: $outputStr");
        }
    }

    private function findPythonExecutable()
    {
        // Get the project root (EndDev folder) - go up from processes -> staffmanagement -> EndDev
        $projectRoot = dirname(dirname(__DIR__));

        // Prioritize the virtual environment's Python executable in project root
        $venvPaths = [
            $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
            $projectRoot . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $projectRoot . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        ];

        foreach ($venvPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Strategy 1: Check environment-based paths
        $userProfile = getenv('USERPROFILE');
        $localAppData = getenv('LOCALAPPDATA');

        $pythonPaths = [];
        if ($userProfile) {
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
            $pythonPaths[] = $userProfile . '\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';
        }
        if ($localAppData) {
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python312\\python.exe';
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python311\\python.exe';
            $pythonPaths[] = $localAppData . '\\Programs\\Python\\Python310\\python.exe';
        }
        $pythonPaths[] = 'C:\\Python312\\python.exe';
        $pythonPaths[] = 'C:\\Python311\\python.exe';
        $pythonPaths[] = 'C:\\Python310\\python.exe';
        $pythonPaths[] = '/usr/bin/python3';
        $pythonPaths[] = '/usr/local/bin/python3';

        foreach ($pythonPaths as $path) {
            if (file_exists($path))
                return $path;
        }

        // Strategy 2: Try 'where python'
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('where python 2>nul', $whereOutput, $whereReturnCode);
            if ($whereReturnCode === 0 && !empty($whereOutput)) {
                foreach ($whereOutput as $foundPath) {
                    $foundPath = trim($foundPath);
                    if (
                        stripos($foundPath, 'py.exe') === false &&
                        stripos($foundPath, 'WindowsApps') === false &&
                        stripos($foundPath, 'System32') === false &&
                        file_exists($foundPath)
                    ) {
                        return $foundPath;
                    }
                }
            }
        } else {
            exec('which python3 2>/dev/null', $whichOutput, $whichReturnCode);
            if ($whichReturnCode === 0 && !empty($whichOutput)) {
                return trim($whichOutput[0]);
            }
        }

        return false;
    }

    private function logActivity($activity, $reference = '')
    {
        $log_dir = dirname(__DIR__) . '/../logs/';
        if (!file_exists($log_dir))
            mkdir($log_dir, 0755, true);

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $ref_str = !empty($reference) ? " - {$reference}" : '';

        $log_entry = "[{$timestamp}] [ACTIVITY] [IP: {$ip}] {$activity}{$ref_str}" . PHP_EOL;
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function logError($context, $message)
    {
        $log_dir = dirname(__DIR__) . '/../logs/';
        if (!file_exists($log_dir))
            mkdir($log_dir, 0755, true);

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $file = basename(__FILE__);

        $log_entry = "[{$timestamp}] [ERROR] [IP: {$ip}] [{$file}] Context: {$context} - Message: {$message}" . PHP_EOL;
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function sendErrorResponse($message, $code = 400)
    {
        http_response_code($code);
        ob_clean();
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

$updater = new FaceRegistrationUpdater($conn);
$updater->handleRequest();
?>