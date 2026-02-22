<?php
/**
 * Localhost Background Sync Engine
 * URL: http://localhost/api/run_sync.php
 * 
 * This script runs locally, pushes `sync_status=0` local records to VPS,
 * and pulls `sync_status=0` VPS records down to local.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide errors to avoid breaking JSON response

header('Content-Type: application/json');

// This script should strictly only run on Localhost.
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1', '::1', 'localhost:80']);
if (!$isLocalhost && !isset($_GET['force'])) {
    die(json_encode(['success' => false, 'message' => 'Sync engine should only be triggered on Localhost.']));
}

require_once '../db_connection.php'; // Connects to local DB

define('VPS_ENDPOINT', 'http://76.13.210.68/api/sync_endpoint.php');
define('API_KEY', 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo');

$tables_to_sync = [
    'employees',
    'leave_types',
    'holidays',
    'schedules',
    'schedule_periods',
    'employee_schedules',
    'employee_assignments',
    'employee_leaves',
    'attendance_logs',
    'daily_attendance',
    'notifications'
];

$results = [
    'pushed' => 0,
    'pulled' => 0,
    'errors' => []
];

// --- STEP 1: PUSH LOCAL CHANGES TO VPS ---
foreach ($tables_to_sync as $table) {
    if (!checkSyncColumnExists($conn, $table))
        continue;

    $local_changes = $conn->query("SELECT * FROM `$table` WHERE `sync_status` = 0 LIMIT 50");
    if ($local_changes && $local_changes->num_rows > 0) {
        while ($record = $local_changes->fetch_assoc()) {

            // Push to VPS
            $response = sendToVPS('push', $table, $record);

            if ($response && isset($response['success']) && $response['success']) {
                $id = (int) $record['id'];
                $conn->query("UPDATE `$table` SET `sync_status` = 1, `last_sync` = NOW() WHERE `id` = $id");
                $results['pushed']++;
            } else {
                $results['errors'][] = "Push error on $table ID {$record['id']}: " . ($response['error'] ?? 'Network error');
            }
        }
    }
}

// --- STEP 2: PULL VPS CHANGES TO LOCAL ---
$pullResponse = sendToVPS('pull_updates', '', []);

if ($pullResponse && isset($pullResponse['success']) && $pullResponse['success'] && !empty($pullResponse['data'])) {
    $vps_changes = $pullResponse['data'];

    foreach ($vps_changes as $table => $records) {
        if (!checkSyncColumnExists($conn, $table))
            continue;

        $synced_ids = [];
        foreach ($records as $record) {

            // Insert or Update Local DB
            $record['sync_status'] = 1; // Mark as synced once saved locally
            $record['last_sync'] = date('Y-m-d H:i:s');

            $columns = array_keys($record);
            $values = array_values($record);

            $columnNames = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, $columns));
            $placeholders = implode(', ', array_fill(0, count($values), '?'));

            $updateClauses = [];
            foreach ($columns as $col) {
                if ($col !== 'id')
                    $updateClauses[] = "`$col` = VALUES(`$col`)";
            }
            $updateString = implode(', ', $updateClauses);

            $sql = "INSERT INTO `$table` ($columnNames) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateString";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $types = '';
                foreach ($values as $value) {
                    $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
                }
                $stmt->bind_param($types, ...$values);

                if ($stmt->execute()) {
                    $synced_ids[] = (int) $record['id'];
                    $results['pulled']++;
                } else {
                    $results['errors'][] = "Local pull execute error on $table: " . $stmt->error;
                }
            } else {
                $results['errors'][] = "Local pull prepare error on $table: " . $conn->error;
            }
        }

        // --- STEP 3: ACKNOWLEDGE PULL TO VPS ---
        if (!empty($synced_ids)) {
            sendToVPS('mark_synced', $table, $synced_ids);
        }
    }
} else if ($pullResponse && isset($pullResponse['success']) && $pullResponse['success'] === false) {
    $results['errors'][] = "Pull request failed: " . ($pullResponse['error'] ?? 'Unknown network error');
}

echo json_encode([
    'success' => true,
    'message' => 'Sync cycle complete',
    'stats' => $results
]);


// --- HELPER FUNCTIONS ---

function checkSyncColumnExists($conn, $table)
{
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    return ($check && $check->num_rows > 0);
}

function sendToVPS($action, $table, $data)
{
    $ch = curl_init(VPS_ENDPOINT);
    $payload = http_build_query([
        'action' => $action,
        'table' => $table,
        'data' => is_array($data) ? json_encode($data) : $data,
        'ids' => is_array($data) && $action == 'mark_synced' ? json_encode($data) : ''
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . API_KEY
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Keep it fast so it doesn't block the UI

    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return json_decode($result, true) ?: ['success' => false, 'error' => 'Invalid JSON from VPS: ' . substr($result, 0, 100)];
}
?>