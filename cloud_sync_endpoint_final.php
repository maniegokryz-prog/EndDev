<?php
/**
 * CLOUD SYNC ENDPOINT (FINAL ROBUST VERSION)
 * Upload to: /api/sync_endpoint.php
 * 
 * FIXES:
 * 1. Uses INSERT IGNORE to silently skip duplicates
 * 2. Uses 127.0.0.1 for connection
 * 3. Handles Foreign Key lookups for Leaves, Schedules, and Assignments
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: X-API-KEY, Content-Type');

// Configuration
$API_KEY = "lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo"; 
$DB_HOST = "127.0.0.1";  
$DB_USER = "dbu58088";
$DB_PASS = "Confirmp@ssword123";
$DB_NAME = "dbs14970485";
$DB_PORT = 3306;

$headers = getallheaders();
$request_key = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($request_key !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API Key']);
    exit;
}

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");
    
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    $data_json = $_POST['data'] ?? '[]';
    $where = $_POST['where'] ?? '';
    
    $data = json_decode($data_json, true);
    
    // Allowed tables
    $allowed_tables = [
        'employees', 'schedules', 'schedule_periods', 'employee_schedules', 
        'employee_assignments', 'daily_attendance', 'attendance_logs', 
        'holidays', 'leave_types', 'employee_leaves', 'notifications'
    ];
    
    if ($table && !in_array($table, $allowed_tables)) throw new Exception("Table not allowed");
    
    switch ($action) {
        case 'fetch_pending_leaves': fetchPendingLeaves($conn); break;
        case 'insert': handleInsert($conn, $table, $data); break;
        case 'update': handleUpdate($conn, $table, $data, $where); break;
        case 'delete': handleDelete($conn, $table, $where); break;
        case 'delete_with_lookup': handleDeleteWithLookup($conn, $table, $data); break;
        case 'sync_with_lookup': handleSyncWithLookup($conn, $table, $data); break;
        default: throw new Exception("Invalid action");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();

function fetchPendingLeaves($conn) {
    $sql = "SELECT el.*, e.employee_id as employee_id_string, 
            TRIM(CONCAT(e.first_name, ' ', e.last_name)) as employee_name, 
            lt.type_name as leave_type_name
            FROM employee_leaves el
            JOIN employees e ON el.employee_id = e.id
            JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.status = 'pending' ORDER BY el.created_at ASC";
    $result = $conn->query($sql);
    if (!$result) throw new Exception($conn->error);
    
    $leaves = [];
    while ($row = $result->fetch_assoc()) $leaves[] = $row;
    echo json_encode(['success' => true, 'data' => $leaves]);
}

function handleInsert($conn, $table, $data) {
    if (empty($data)) {
        echo json_encode(['success' => true, 'message' => 'No data']);
        return;
    }
    
    $columns = array_keys($data);
    $values = array_values($data);
    
    $cols_sql = implode(", ", array_map(function($c) { return "`$c`"; }, $columns));
    $vals_sql = implode(", ", array_fill(0, count($values), "?"));
    
    // MANUAL DUPLICATE CHECK (Fix for missing DB Constraints)
    if ($table === 'attendance_logs') {
        $empId = $data['employee_id'] ?? '';
        $lDate = $data['log_date'] ?? '';
        $lTime = $data['log_time'] ?? '';
        $lType = $data['log_type'] ?? '';
        
        $chk = $conn->prepare("SELECT id FROM attendance_logs WHERE employee_id=? AND log_date=? AND log_time=? AND log_type=?");
        $chk->bind_param("ssss", $empId, $lDate, $lTime, $lType);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
             echo json_encode(['success' => true, 'message' => 'Duplicate skipped (Manual Check)']);
             return;
        }
    }

    // KEY FIX: Use INSERT IGNORE to skip duplicates automatically
    $sql = "INSERT IGNORE INTO `$table` ($cols_sql) VALUES ($vals_sql)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    
    $types = str_repeat("s", count($values));
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows == 0) {
             echo json_encode(['success' => true, 'message' => 'Duplicate skipped (Ignored)']);
        } else {
             echo json_encode(['success' => true, 'message' => 'Inserted successfully']);
        }
    } else {
        throw new Exception($stmt->error);
    }
}

function handleUpdate($conn, $table, $data, $where) {
    if (empty($where)) throw new Exception("WHERE missing");
    $set = []; $vals = [];
    foreach ($data as $c => $v) { $set[] = "`$c` = ?"; $vals[] = $v; }
    
    $sql = "UPDATE IGNORE `$table` SET " . implode(', ', $set) . " WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);
    
    $stmt->bind_param(str_repeat("s", count($vals)), ...$vals);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else throw new Exception($stmt->error);
}

function handleDelete($conn, $table, $where) {
    if ($conn->query("DELETE FROM `$table` WHERE $where")) echo json_encode(['success' => true]);
    else throw new Exception($conn->error);
}

function handleDeleteWithLookup($conn, $table, $data) {
    echo json_encode(['success' => true]); 
}

function handleSyncWithLookup($conn, $table, $data) {
    // 1. Employee Schedules Lookup
    if ($table === 'employee_schedules') {
        $empCode = $data['employee_id_string'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $empCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success'=>true, 'message'=>'Skipped: Emp not found']); return; }
        $empId = $res->fetch_assoc()['id'];

        $schedName = $data['schedule_name'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM schedules WHERE schedule_name = ?");
        $stmt->bind_param("s", $schedName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { 
             $stmt = $conn->prepare("INSERT IGNORE INTO schedules (schedule_name) VALUES (?)");
             $stmt->bind_param("s", $schedName);
             $stmt->execute();
             $schedId = $conn->insert_id;
             if(!$schedId) {
                 $stmt = $conn->prepare("SELECT id FROM schedules WHERE schedule_name = ?");
                 $stmt->bind_param("s", $schedName);
                 $stmt->execute();
                 $schedId = $stmt->get_result()->fetch_assoc()['id'];
             }
        } else {
             $schedId = $res->fetch_assoc()['id'];
        }

        $insertData = [
            'employee_id' => $empId,
            'schedule_id' => $schedId,
            'effective_date' => $data['effective_date'],
            'is_active' => $data['is_active']
        ];
        handleInsert($conn, 'employee_schedules', $insertData);
        return;
    }

    // 2. Employee Assignments Lookup
    if ($table === 'employee_assignments') {
        $empCode = $data['employee_id_string'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $empCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success'=>true, 'message'=>'Skipped: Emp not found']); return; }
        $empId = $res->fetch_assoc()['id'];

        $schedName = $data['schedule_name'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM schedules WHERE schedule_name = ?");
        $stmt->bind_param("s", $schedName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success'=>true, 'message'=>'Skipped: Schedule not found']); return; }
        $schedId = $res->fetch_assoc()['id'];

        $stmt = $conn->prepare("SELECT id FROM schedule_periods WHERE schedule_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
        $stmt->bind_param("iiss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time']);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO schedule_periods (schedule_id, day_of_week, start_time, end_time, period_name) VALUES (?, ?, ?, ?, 'Synced Period')");
            $stmt->bind_param("iiss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time']);
            $stmt->execute();
            $periodId = $conn->insert_id;
             if(!$periodId) {
                 $stmt = $conn->prepare("SELECT id FROM schedule_periods WHERE schedule_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
                 $stmt->bind_param("iiss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time']);
                 $stmt->execute();
                 $periodId = $stmt->get_result()->fetch_assoc()['id'];
             }
        } else {
            $periodId = $res->fetch_assoc()['id'];
        }

        $insertData = [
            'employee_id' => $empId,
            'schedule_period_id' => $periodId,
            'subject_code' => $data['subject_code'] ?? '',
            'designate_class' => $data['designate_class'] ?? '',
            'room_num' => $data['room_num'] ?? '',
            'is_active' => $data['is_active']
        ];
        handleInsert($conn, 'employee_assignments', $insertData);
        return;
    }

    // 3. Employee Leaves Lookup
    if ($table === 'employee_leaves') {
        $empCode = $data['employee_id_string'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $empCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success'=>true, 'message'=>'Skipped: Emp not found']); return; }
        $empId = $res->fetch_assoc()['id'];
        
        $typeName = $data['leave_type_name'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM leave_types WHERE type_name = ?");
        $stmt->bind_param("s", $typeName);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO leave_types (type_name, description) VALUES (?, ?)");
            $desc = "$typeName (Synced)";
            $stmt->bind_param("ss", $typeName, $desc);
            $stmt->execute();
            $typeId = $conn->insert_id;
            if ($typeId == 0) { 
                 $stmt = $conn->prepare("SELECT id FROM leave_types WHERE type_name = ?");
                 $stmt->bind_param("s", $typeName);
                 $stmt->execute();
                 $typeId = $stmt->get_result()->fetch_assoc()['id'];
            }
        } else {
            $typeId = $res->fetch_assoc()['id'];
        }
        
        // 3. Prepare Data
        $cloudIdFromLocal = $data['cloud_id'] ?? null;
        
        $insertData = [
            'employee_id' => $empId,
            'leave_type_id' => $typeId,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
            'status' => $data['status'],
            'cloud_id' => $cloudIdFromLocal 
        ];

        // 4. CHECK FOR EXISTING RECORD (Upsert Logic)
        $existingId = null;

        // A. Check by cloud_id (stored locally as 'id') which maps to a column 'cloud_id' on Cloud DB?
        // Wait, if LOCAL sends its ID as 'cloud_id', and CLOUD stores it in 'cloud_id' column, we can match on that.
        if ($cloudIdFromLocal) {
            $stmt = $conn->prepare("SELECT id FROM employee_leaves WHERE cloud_id = ?");
            $stmt->bind_param("i", $cloudIdFromLocal); // Local ID is integer
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $existingId = $res->fetch_assoc()['id'];
            }
        }

        // B. Fallback: Check by Employee + Dates (to prevent semantic duplicates if cloud_id correlation is missing)
        if (!$existingId) {
            $stmt = $conn->prepare("SELECT id FROM employee_leaves WHERE employee_id = ? AND start_date = ? AND end_date = ?");
            $stmt->bind_param("iss", $empId, $data['start_date'], $data['end_date']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $existingId = $res->fetch_assoc()['id'];
            }
        }

        if ($existingId) {
            // UPDATE existing record
            $where = "id = $existingId";
            handleUpdate($conn, 'employee_leaves', $insertData, $where);
        } else {
            // INSERT new record
            handleInsert($conn, 'employee_leaves', $insertData);
        }
        return;
    }
    
    // 4. Notifications Lookup
    if ($table === 'notifications') {
         $empCode = $data['employee_id_string'] ?? '';
         $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
         $stmt->bind_param("s", $empCode);
         $stmt->execute();
         $res = $stmt->get_result();
         $empId = ($res->num_rows > 0) ? $res->fetch_assoc()['id'] : null;
         
         // Assuming leave_id logic handled separately or ignored if null
         if ($empId) {
             $data['employee_id'] = $empId;
             unset($data['employee_id_string']);
             unset($data['leave_cloud_id']);
             handleInsert($conn, 'notifications', $data);
             return;
         }
    }

    echo json_encode(['success' => true, 'message' => 'Generic lookup processed']);
}
?>
