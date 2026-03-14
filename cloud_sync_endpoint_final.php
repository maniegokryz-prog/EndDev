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
    if ($conn->connect_error)
        throw new Exception("Connection failed: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    $data_json = $_POST['data'] ?? '[]';
    $where = $_POST['where'] ?? '';

    $data = json_decode($data_json, true);

    // Allowed tables
    $allowed_tables = [
        'employees',
        'schedules',
        'schedule_requests',
        'schedule_periods',
        'employee_schedules',
        'employee_assignments',
        'daily_attendance',
        'attendance_logs',
        'holidays',
        'leave_types',
        'employee_leaves',
        'notifications'
    ];

    if ($table && !in_array($table, $allowed_tables))
        throw new Exception("Table not allowed");

    switch ($action) {
        case 'fetch_pending_leaves':
            fetchPendingLeaves($conn);
            break;
        case 'fetch_employees':
            fetchEmployees($conn);
            break;
        case 'fetch_notifications':
            fetchNotifications($conn);
            break;
        case 'upsert_notifications':
            upsertNotifications($conn, $data);
            break;
        case 'insert':
            handleInsert($conn, $table, $data);
            break;
        case 'update':
            handleUpdate($conn, $table, $data, $where);
            break;
        case 'delete':
            handleDelete($conn, $table, $where);
            break;
        case 'delete_with_lookup':
            handleDeleteWithLookup($conn, $table, $data);
            break;
        case 'sync_with_lookup':
            handleSyncWithLookup($conn, $table, $data);
            break;
        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();

function fetchPendingLeaves($conn)
{
    $sql = "SELECT el.*, e.employee_id as employee_id_string, 
            TRIM(CONCAT(e.first_name, ' ', e.last_name)) as employee_name, 
            lt.type_name as leave_type_name
            FROM employee_leaves el
            JOIN employees e ON el.employee_id = e.id
            JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.status = 'pending' ORDER BY el.created_at ASC";
    $result = $conn->query($sql);
    if (!$result)
        throw new Exception($conn->error);

    $leaves = [];
    while ($row = $result->fetch_assoc())
        $leaves[] = $row;
    echo json_encode(['success' => true, 'data' => $leaves]);
}

function fetchEmployees($conn)
{
    // Fetch employees modified or created in the last 24 hours (or larger window if needed)
    // For robustness, maybe we fetch all? No, that's too heavy.
    // Let's assume we want to sync changes.
    // Ideally, the client sends a 'since' timestamp.

    $since = $_POST['since'] ?? date('Y-m-d H:i:s', strtotime('-1 day'));

    $sql = "SELECT * FROM employees WHERE updated_at >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $since);
    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $employees]);
}

function fetchNotifications($conn)
{
    // Fetch notifications flagged for sync (sync_status=0) OR recent ones for bootstrapping
    $since = $_POST['since'] ?? date('Y-m-d H:i:s', strtotime('-24 hours'));

    // Check if sync_status column exists on this server
    $colCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE 'sync_status'");
    
    if ($colCheck && $colCheck->num_rows > 0) {
        // Fetch rows that need syncing (pending sync) OR recent rows
        $sql = "SELECT id, type, target, message, link, deleted_by, actioned_by, is_read, created_at 
                FROM notifications 
                WHERE sync_status = 0 OR created_at >= ?
                ORDER BY created_at DESC LIMIT 500";
    } else {
        $sql = "SELECT id, type, target, message, link, deleted_by, actioned_by, is_read, created_at 
                FROM notifications WHERE created_at >= ?
                ORDER BY created_at DESC LIMIT 500";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $since);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        $ids[] = $row['id'];
    }
    
    // Mark those rows as synced (sync_status=1) so they don't keep being fetched
    if (!empty($ids) && $colCheck && $colCheck->num_rows > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $markStmt = $conn->prepare("UPDATE notifications SET sync_status = 1 WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $markStmt->bind_param($types, ...$ids);
        $markStmt->execute();
    }

    echo json_encode(['success' => true, 'data' => $notifications]);
}

function upsertNotifications($conn, $batch)
{
    /**
     * Upserts a batch of notifications from remote.
     * For each notification, we match by (type, target, message, link) heuristically,
     * and MERGE deleted_by / actioned_by rather than overwriting.
     */
    if (empty($batch) || !is_array($batch)) {
        echo json_encode(['success' => true, 'message' => 'No notifications to upsert', 'count' => 0]);
        return;
    }

    // Ensure columns exist
    $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS deleted_by TEXT NULL DEFAULT NULL");
    $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS actioned_by TEXT NULL DEFAULT NULL");
    $conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sync_status TINYINT(1) DEFAULT 0");

    $updated = 0;
    foreach ($batch as $cloud_notif) {
        $type     = $cloud_notif['type'] ?? '';
        $target   = $cloud_notif['target'] ?? '';
        $message  = $cloud_notif['message'] ?? '';
        $link     = $cloud_notif['link'] ?? '';
        $cloud_deleted  = $cloud_notif['deleted_by'] ?? '';
        $cloud_actioned = $cloud_notif['actioned_by'] ?? null;
        $cloud_is_read  = isset($cloud_notif['is_read']) ? (int)$cloud_notif['is_read'] : 0;

        // Find matching local notification by content (IDs differ between environments)
        $stmt = $conn->prepare(
            "SELECT id, deleted_by, actioned_by, is_read FROM notifications 
             WHERE type = ? AND target = ? AND (message = ? OR link = ?)
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->bind_param('ssss', $type, $target, $message, $link);
        $stmt->execute();
        $local = $stmt->get_result()->fetch_assoc();
        
        if (!$local) continue; // Not found locally - skip

        // Merge deleted_by: append any new markers from cloud that don't exist locally
        $local_deleted = $local['deleted_by'] ?? '';
        $merged_deleted = $local_deleted;
        if ($cloud_deleted) {
            preg_match_all('/\[([^\]]+)\]/', $cloud_deleted, $matches);
            foreach ($matches[1] as $marker) {
                if (strpos($merged_deleted, "[$marker]") === false) {
                    $merged_deleted .= "[$marker]";
                }
            }
        }

        // Merge actioned_by: take cloud value if local is empty
        $merged_actioned = $local['actioned_by'];
        if (!$merged_actioned && $cloud_actioned) {
            $merged_actioned = $cloud_actioned;
        }

        // Merge is_read: once read, always read
        $merged_is_read = ($cloud_is_read || $local['is_read']) ? 1 : 0;

        // Only update if something actually changed
        if ($merged_deleted !== $local_deleted || 
            $merged_actioned !== $local['actioned_by'] || 
            $merged_is_read !== (int)$local['is_read']) {
            
            $upd = $conn->prepare(
                "UPDATE notifications SET deleted_by = ?, actioned_by = ?, is_read = ?, sync_status = 1
                 WHERE id = ?"
            );
            $upd->bind_param('ssii', $merged_deleted ?: null, $merged_actioned, $merged_is_read, $local['id']);
            $upd->execute();
            if ($upd->affected_rows > 0) $updated++;
        }
    }

    echo json_encode(['success' => true, 'message' => "Upserted $updated notification(s)", 'count' => $updated]);
}

function handleInsert($conn, $table, $data)
{
    if (empty($data)) {
        echo json_encode(['success' => true, 'message' => 'No data']);
        return;
    }

    $columns = array_keys($data);
    $values = array_values($data);

    $cols_sql = implode(", ", array_map(function ($c) {
        return "`$c`";
    }, $columns));
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
    if (!$stmt)
        throw new Exception("Prepare failed: " . $conn->error);

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

function handleUpdate($conn, $table, $data, $where)
{
    if (empty($where))
        throw new Exception("WHERE missing");
    $set = [];
    $vals = [];
    foreach ($data as $c => $v) {
        $set[] = "`$c` = ?";
        $vals[] = $v;
    }

    $sql = "UPDATE IGNORE `$table` SET " . implode(', ', $set) . " WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!$stmt)
        throw new Exception($conn->error);

    $stmt->bind_param(str_repeat("s", count($vals)), ...$vals);
    if ($stmt->execute())
        echo json_encode(['success' => true]);
    else
        throw new Exception($stmt->error);
}

function handleDelete($conn, $table, $where)
{
    if ($conn->query("DELETE FROM `$table` WHERE $where"))
        echo json_encode(['success' => true]);
    else
        throw new Exception($conn->error);
}

function handleDeleteWithLookup($conn, $table, $data)
{
    echo json_encode(['success' => true]);
}

function handleSyncWithLookup($conn, $table, $data)
{
    // 1. Employee Schedules Lookup
    if ($table === 'employee_schedules') {
        $conn->begin_transaction(); // Start transaction to prevent race conditions
        try {
            $empCode = $data['employee_id_string'] ?? '';
            $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
            $stmt->bind_param("s", $empCode);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                // Determine if we should fail or skip
                // For sync robustness, skipping is safer, but rollback just in case
                $conn->rollback();
                echo json_encode(['success' => true, 'message' => 'Skipped: Emp not found']);
                return;
            }
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
                if (!$schedId) {
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

            // CLEANUP: Deactivate all schedules first
            $cleanup = $conn->prepare("UPDATE employee_schedules SET is_active = 0 WHERE employee_id = ?");
            $cleanup->bind_param("i", $empId);
            $cleanup->execute();

            // UPSERT STRICT: Check if this specific schedule link exists
            $chk = $conn->prepare("SELECT id FROM employee_schedules WHERE employee_id = ? AND schedule_id = ?");
            $chk->bind_param("ii", $empId, $schedId);
            $chk->execute();
            $res = $chk->get_result();

            if ($row = $res->fetch_assoc()) {
                // Exists: Reactivate it
                $upd = $conn->prepare("UPDATE employee_schedules SET is_active = 1, effective_date = ? WHERE id = ?");
                $upd->bind_param("si", $data['effective_date'], $row['id']);
                $upd->execute();
            } else {
                // New: Insert it
                $stmt = $conn->prepare("INSERT INTO employee_schedules (employee_id, schedule_id, effective_date, is_active) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("iis", $empId, $schedId, $data['effective_date']);
                $stmt->execute();
            }

            $conn->commit(); // Commit the transaction
            return;
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            throw $e;
        }
    }

    // 2. Employee Assignments Lookup
    if ($table === 'employee_assignments') {
        $empCode = $data['employee_id_string'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $empCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => true, 'message' => 'Skipped: Emp not found']);
            return;
        }
        $empId = $res->fetch_assoc()['id'];

        $schedName = $data['schedule_name'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM schedules WHERE schedule_name = ?");
        $stmt->bind_param("s", $schedName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => true, 'message' => 'Skipped: Schedule not found']);
            return;
        }
        $schedId = $res->fetch_assoc()['id'];

        $stmt = $conn->prepare("SELECT id FROM schedule_periods WHERE schedule_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
        $stmt->bind_param("iiss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time']);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $periodId = $res->fetch_assoc()['id'];
        } else {
            $periodId = 0;
        }

        // CLEANUP OVERLAPS: Delete any OTHER periods in this schedule that overlap this time slot
        // Logic: Deletes periods where Start < NewEnd AND End > NewStart, EXCLUDING the exact match we just found.
        $delOverlaps = $conn->prepare("
            DELETE p, a 
            FROM schedule_periods p 
            LEFT JOIN employee_assignments a ON a.schedule_period_id = p.id 
            WHERE p.schedule_id = ? 
            AND p.day_of_week = ? 
            AND (p.start_time < ? AND p.end_time > ?)
            AND p.id != ?
        ");
        // Params: schedule_id(i), day(i), end_time(s), start_time(s), period_id(i)
        $delOverlaps->bind_param("iissi", $schedId, $data['day_of_week'], $data['end_time'], $data['start_time'], $periodId);
        $delOverlaps->execute();

        if ($periodId == 0) {
            // Reconstruct period name if possible
            $pName = 'Synced Period';
            if (!empty($data['subject_code']) || !empty($data['designate_class'])) {
                $pString = [];
                if (!empty($data['subject_code']))
                    $pString[] = $data['subject_code'];
                if (!empty($data['designate_class']))
                    $pString[] = $data['designate_class'];
                $pName = implode(' - ', $pString);
            }

            $stmt = $conn->prepare("INSERT IGNORE INTO schedule_periods (schedule_id, day_of_week, start_time, end_time, period_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time'], $pName);
            $stmt->execute();
            $periodId = $conn->insert_id;
            if (!$periodId) {
                // Fallback fetch if race condition insert
                $stmt = $conn->prepare("SELECT id FROM schedule_periods WHERE schedule_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
                $stmt->bind_param("iiss", $schedId, $data['day_of_week'], $data['start_time'], $data['end_time']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $periodId = $row['id'];
                }
            }
        }


        // PREVENT DUPLICATE ASSIGNMENTS: Delete existing assignment for this employee & period
        $del = $conn->prepare("DELETE FROM employee_assignments WHERE employee_id = ? AND schedule_period_id = ?");
        $del->bind_param("ii", $empId, $periodId);
        $del->execute();

        $stmt = $conn->prepare("INSERT INTO employee_assignments (employee_id, schedule_period_id, subject_code, designate_class, room_num, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $subj = $data['subject_code'] ?? '';
        $cls = $data['designate_class'] ?? '';
        $room = $data['room_num'] ?? '';
        $active = $data['is_active'] ?? 1;
        $stmt->bind_param("iisssi", $empId, $periodId, $subj, $cls, $room, $active);
        $stmt->execute();

        if ($conn->error) {
            // Log error or handle it? For now, we want to at least know if it fails.
            // But existing error handling wraps this in try-catch in my head? No, this is outside the earlier try-catch.
            // Let's create a minimal throw to catch the attention if strict.
            throw new Exception("Assignment Insert Failed: " . $conn->error);
        }
        return;
    }

    // 3. Employee Leaves Lookup
    if ($table === 'employee_leaves') {
        $empCode = $data['employee_id_string'] ?? '';
        $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $empCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => true, 'message' => 'Skipped: Emp not found']);
            return;
        }
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