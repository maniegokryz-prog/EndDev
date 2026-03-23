<?php
/**
 * Hostinger VPS API Endpoint
 * File location: https://yourdomain.com/api/sync_endpoint.php
 * 
 * This receives data from localhost and inserts it into VPS database.
 * It also provides VPS data back to localhost.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from localhost
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple API key authentication
define('API_KEY', 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

require_once '../db_connection.php'; // Connects to the local database where this file is run

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$table = $_POST['table'] ?? '';
$data = $_POST['data'] ?? '';

if (is_string($data) && !empty($data)) {
    $data = json_decode($data, true);
}

switch ($action) {
    case 'push': // Localhost pushing to VPS
        syncPush($conn, $table, $data);
        break;

    case 'pull_updates': // Localhost pulling from VPS
        syncPullUpdates($conn);
        break;

    case 'fetch_notifications': // Hostinger serving notifications to Localhost
        fetchNotifications($conn);
        break;

    case 'fetch_settings': // Hostinger serving system_settings to Localhost
        fetchSettings($conn);
        break;

    case 'mark_synced': // Localhost telling VPS it pulled successfully
        $ids = $_POST['ids'] ?? '';
        if (is_string($ids))
            $ids = json_decode($ids, true);
        syncMarkSynced($conn, $table, $ids);
        break;

    case 'upload_file': // Localhost uploading a physical file to VPS
        syncUploadFile();
        break;

    case 'insert': // Legacy fallback for auto_sync.py
        syncPush($conn, $table, $data);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function syncPush($conn, $table, $record)
{
    if (empty($table) || empty($record)) {
        echo json_encode(['success' => false, 'error' => 'Missing table or data']);
        return;
    }

    // Safety check: Don't push if `sync_status` doesn't exist yet on VPS
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    if ($check && $check->num_rows > 0) {
        $record['sync_status'] = 1;
        $record['last_sync'] = date('Y-m-d H:i:s');
    }

    $columns = array_keys($record);
    $values = array_values($record);

    $columnNames = implode(', ', array_map(function ($col) {
        return "`$col`";
    }, $columns));
    $placeholders = implode(', ', array_fill(0, count($values), '?'));

    // Handle both inserts and updates gracefully based on Primary Key
    $updateClauses = [];
    foreach ($columns as $col) {
        if ($col !== 'id') {
            $updateClauses[] = "`$col` = VALUES(`$col`)";
        }
    }
    $updateString = implode(', ', $updateClauses);

    $sql = "INSERT INTO `$table` ($columnNames) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateString";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        return;
    }

    $types = '';
    foreach ($values as $value) {
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data pushed successfully', 'id' => $record['id'] ?? $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    }
}

function syncPullUpdates($conn)
{
    $tables_to_sync = [
        'employees',
        'schedules',
        'schedule_requests',
        'schedule_periods',
        'employee_schedules',
        'attendance_logs',
        'daily_attendance',
        'holidays',
        'leave_types',
        'employee_leaves',
        'employee_assignments',
        'notifications',
        'system_settings'
    ];

    $changes = [];
    $total_changes = 0;
    foreach ($tables_to_sync as $table) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
        if ($check && $check->num_rows > 0) {
            $result = $conn->query("SELECT * FROM `$table` WHERE `sync_status` = 0 LIMIT 100");
            if ($result && $result->num_rows > 0) {
                $changes[$table] = $result->fetch_all(MYSQLI_ASSOC);
                $total_changes += $result->num_rows;
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $changes, 'total' => $total_changes]);
}

function fetchNotifications($conn)
{
    // Fetch notifications modified or created in the last 24 hours
    $since = $_POST['since'] ?? date('Y-m-d H:i:s', strtotime('-1 day'));

    $sql = "SELECT id, type, target, message, link, deleted_by, actioned_by, is_read, created_at FROM notifications WHERE created_at >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $since);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $notifications]);
}

function fetchSettings($conn)
{
    $sql = "SELECT setting_key, setting_value FROM system_settings";
    $result = $conn->query($sql);

    $settings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    echo json_encode(['success' => true, 'data' => $settings]);
}

function syncMarkSynced($conn, $table, $ids)
{
    if (empty($table) || empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'error' => 'Missing table or IDs']);
        return;
    }

    // Just a sanity check to ensure the column exists
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    if (!$check || $check->num_rows == 0) {
        echo json_encode(['success' => true]); // pretend success if column missing
        return;
    }

    $idsList = implode(',', array_map('intval', $ids));
    $sql = "UPDATE `$table` SET `sync_status` = 1, `last_sync` = NOW() WHERE `id` IN ($idsList)";

    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Marked synced']);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}

function syncUploadFile()
{
    $path = $_POST['path'] ?? '';
    if (empty($path) || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'Missing path or physical file']);
        return;
    }

    // Safety check to prevent directory traversal
    if (strpos($path, '..') !== false) {
        echo json_encode(['success' => false, 'error' => 'Invalid path structure']);
        return;
    }

    $target_file = __DIR__ . '/../' . ltrim($path, '/');
    $target_dir = dirname($target_file);

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
        echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }
}

function handleUpsert($conn, $table, $data) {
    if (empty($data) || !is_array($data)) { echo json_encode(['success'=>true]); return; }
    $cols = array_keys($data); $vals = array_values($data);
    $cs = implode(', ', array_map(function($c){ return "`$c`"; }, $cols));
    $vs = implode(', ', array_fill(0, count($vals), '?'));
    $us = implode(', ', array_map(function($c){ return "`$c`=VALUES(`$c`)"; }, $cols));
    $sql = "INSERT INTO `$table` ($cs) VALUES ($vs) ON DUPLICATE KEY UPDATE $us";
    $s = $conn->prepare($sql);
    if (!$s) throw new Exception($conn->error);
    $s->bind_param(str_repeat('s', count($vals)), ...$vals);
    $s->execute();
    echo json_encode(['success'=>true, 'message'=>'Upserted']);
}

?>