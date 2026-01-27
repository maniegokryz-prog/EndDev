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


class FaceRegistrationUpdater {
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

            // Process photos
            $this->processFacePhotos($this->validatedData['employee_id_string'], $firstName, $lastName);

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

    private function getEmployeeByStringId($employeeIdString) {
        $stmt = $this->db->prepare("SELECT id, first_name, last_name FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeIdString);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // ... (keep processFacePhotos and saveFacePhoto)

    // ... (keep generateFaceEmbeddings and findPythonExecutable)
    
    // ... (keep logActivity and logError)

    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        ob_clean();
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

$updater = new FaceRegistrationUpdater($conn);
$updater->handleRequest();
?>
