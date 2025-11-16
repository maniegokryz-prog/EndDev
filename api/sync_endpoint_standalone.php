<?php
/**
 * IONOS API Endpoint - Standalone Version
 * Upload this file to your IONOS server at: /api/sync_endpoint.php
 * 
 * This receives data from localhost and inserts it into IONOS database
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to client
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/sync_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API key authentication
define('API_KEY', 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo');

// Check API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// IONOS Database Configuration
// Try these in order: actual hostname, localhost, 127.0.0.1
$possibleHosts = [
    'db5019018805.hosting-data.io',  // IONOS external hostname
    'localhost',
    '127.0.0.1'
];

$username = "dbu58088";
$password = "Confirmp@ssword123";
$database = "dbs14970485";

// Connect to database
$conn = null;
$lastError = '';

foreach ($possibleHosts as $host) {
    try {
        $conn = new mysqli($host, $username, $password, $database);
        
        if (!$conn->connect_error) {
            $conn->set_charset("utf8mb4");
            break; // Connection successful
        }
        
        $lastError = $conn->connect_error;
        $conn = null;
        
    } catch (Exception $e) {
        $lastError = $e->getMessage();
        continue;
    }
}

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $lastError]);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$table = $_POST['table'] ?? '';
$data = $_POST['data'] ?? '';
$whereCondition = $_POST['where'] ?? '';

// Decode JSON data
if (is_string($data) && !empty($data)) {
    $data = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data: ' . json_last_error_msg()]);
        exit;
    }
}

// Process action
switch ($action) {
    case 'insert':
        syncInsert($conn, $table, $data);
        break;
    
    case 'update':
        syncUpdate($conn, $table, $data, $whereCondition);
        break;
    
    case 'delete':
        syncDelete($conn, $table, $whereCondition);
        break;
    
    case 'sync_with_lookup':
        // Special action for syncing tables that need ID mapping
        syncWithLookup($conn, $table, $data);
        break;
    
    case 'test':
        echo json_encode(['success' => true, 'message' => 'API is working', 'server_time' => date('Y-m-d H:i:s')]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
}

$conn->close();

function syncInsert($conn, $table, $data) {
    if (empty($table) || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'Missing table or data']);
        return;
    }
    
    // Validate table name (prevent SQL injection)
    $allowedTables = [
        'employees', 'schedules', 'schedule_periods', 'employee_schedules', 
        'employee_assignments', 'attendance_logs', 'daily_attendance',
        'holidays', 'leave_types', 'employee_leaves', 'admin_users', 'face_embeddings'
    ];
    
    if (!in_array($table, $allowedTables)) {
        echo json_encode(['success' => false, 'error' => 'Invalid table name']);
        return;
    }
    
    // Handle foreign key conversions for tables with employee_id references
    if (in_array($table, ['employee_schedules', 'employee_assignments', 'attendance_logs', 'daily_attendance'])) {
        if (isset($data['employee_id']) && is_numeric($data['employee_id'])) {
            // This is an internal ID from localhost, skip syncing
            // These tables will be synced by auto_sync.py which handles ID mapping
            echo json_encode(['success' => true, 'message' => 'Skipped - handled by auto-sync', 'affected_rows' => 0]);
            return;
        }
    }
    
    $columns = array_keys($data);
    $values = array_values($data);
    
    $columnNames = implode(', ', array_map(function($col) use ($conn) {
        return "`" . $conn->real_escape_string($col) . "`";
    }, $columns));
    
    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    
    // Build ON DUPLICATE KEY UPDATE clause
    $updateClauses = [];
    foreach ($columns as $col) {
        $updateClauses[] = "`" . $conn->real_escape_string($col) . "` = VALUES(`" . $conn->real_escape_string($col) . "`)";
    }
    $updateClause = implode(', ', $updateClauses);
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicates
    $sql = "INSERT INTO `$table` ($columnNames) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error, 'sql' => $sql]);
        return;
    }
    
    $types = getBindTypes($values);
    
    // Handle empty types (no values to bind)
    if (empty($types) || empty($values)) {
        echo json_encode(['success' => false, 'error' => 'No values to insert']);
        $stmt->close();
        return;
    }
    
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Data inserted', 
            'id' => $conn->insert_id,
            'affected_rows' => $stmt->affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function syncUpdate($conn, $table, $data, $whereCondition) {
    if (empty($table) || empty($data) || empty($whereCondition)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters for update']);
        return;
    }
    
    // Validate table name
    $allowedTables = [
        'employees', 'schedules', 'schedule_periods', 'employee_schedules', 
        'employee_assignments', 'attendance_logs', 'daily_attendance',
        'holidays', 'leave_types', 'employee_leaves', 'admin_users'
    ];
    
    if (!in_array($table, $allowedTables)) {
        echo json_encode(['success' => false, 'error' => 'Invalid table name']);
        return;
    }
    
    $setClauses = [];
    $values = [];
    
    foreach ($data as $column => $value) {
        $setClauses[] = "`" . $conn->real_escape_string($column) . "` = ?";
        $values[] = $value;
    }
    
    $setString = implode(', ', $setClauses);
    $sql = "UPDATE `$table` SET $setString WHERE $whereCondition";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error, 'sql' => $sql]);
        return;
    }
    
    $types = getBindTypes($values);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Data updated', 
            'affected_rows' => $stmt->affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function syncDelete($conn, $table, $whereCondition) {
    if (empty($table) || empty($whereCondition)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters for delete']);
        return;
    }
    
    // Validate table name
    $allowedTables = [
        'employees', 'schedules', 'schedule_periods', 'employee_schedules', 
        'employee_assignments', 'attendance_logs', 'daily_attendance',
        'holidays', 'leave_types', 'employee_leaves', 'admin_users'
    ];
    
    if (!in_array($table, $allowedTables)) {
        echo json_encode(['success' => false, 'error' => 'Invalid table name']);
        return;
    }
    
    $sql = "DELETE FROM `$table` WHERE $whereCondition";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Data deleted', 
            'affected_rows' => $conn->affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $conn->error]);
    }
}

function getBindTypes($values) {
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value) || is_double($value)) {
            $types .= 'd';
        } elseif (is_null($value)) {
            $types .= 's'; // NULL values as string
        } else {
            $types .= 's';
        }
    }
    return $types;
}

function syncWithLookup($conn, $table, $data) {
    /**
     * Sync tables that reference employee_id or schedule_id
     * Looks up the correct IDs on IONOS database
     */
    
    if ($table === 'employee_schedules') {
        // Data should contain: employee_id_string, schedule_name, effective_date, is_active
        if (!isset($data['employee_id_string']) || !isset($data['schedule_name'])) {
            echo json_encode(['success' => false, 'error' => 'Missing employee_id_string or schedule_name']);
            return;
        }
        
        // Look up employee internal ID
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $data['employee_id_string']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Employee not found']);
            return;
        }
        $employee = $result->fetch_assoc();
        $employee_id = $employee['id'];
        
        // Look up schedule internal ID
        $stmt = $conn->prepare("SELECT id FROM schedules WHERE schedule_name = ?");
        $stmt->bind_param('s', $data['schedule_name']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Schedule not found']);
            return;
        }
        $schedule = $result->fetch_assoc();
        $schedule_id = $schedule['id'];
        
        // Insert with mapped IDs
        $sql = "INSERT INTO employee_schedules (employee_id, schedule_id, effective_date, is_active) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iisi', $employee_id, $schedule_id, $data['effective_date'], $data['is_active']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee schedule synced', 'affected_rows' => $stmt->affected_rows]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        
    } elseif ($table === 'employee_assignments') {
        // Data should contain: employee_id_string, schedule_name, day_of_week, start_time, end_time, subject_code, designate_class, room_num
        if (!isset($data['employee_id_string']) || !isset($data['schedule_name'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        // Look up employee ID
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $data['employee_id_string']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Employee not found']);
            return;
        }
        $employee = $result->fetch_assoc();
        $employee_id = $employee['id'];
        
        // Look up schedule_period ID
        $stmt = $conn->prepare("
            SELECT sp.id FROM schedule_periods sp
            JOIN schedules s ON sp.schedule_id = s.id
            WHERE s.schedule_name = ? AND sp.day_of_week = ? AND sp.start_time = ? AND sp.end_time = ?
        ");
        $stmt->bind_param('siss', $data['schedule_name'], $data['day_of_week'], $data['start_time'], $data['end_time']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Schedule period not found']);
            return;
        }
        $period = $result->fetch_assoc();
        $period_id = $period['id'];
        
        // Insert with mapped IDs
        $sql = "INSERT INTO employee_assignments (employee_id, schedule_period_id, subject_code, designate_class, room_num, is_active) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE subject_code = VALUES(subject_code), designate_class = VALUES(designate_class), room_num = VALUES(room_num)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iisssi', $employee_id, $period_id, $data['subject_code'], $data['designate_class'], $data['room_num'], $data['is_active']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee assignment synced', 'affected_rows' => $stmt->affected_rows]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Table not supported for lookup sync']);
    }
}
?>
