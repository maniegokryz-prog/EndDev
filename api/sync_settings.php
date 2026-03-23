<?php
/**
 * Settings Sync Receiver
 * Upload to Hostinger: /public_html/EndDev/api/sync_settings.php
 * 
 * Receives system_settings updates from localhost and writes them to this DB.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: X-API-KEY, Content-Type');

$API_KEY = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';
$request_key = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';

if ($request_key !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Find db_connection.php — works on both localhost (/EndDev/api/) and Hostinger (/api/)
$db_conn_path = file_exists(__DIR__ . '/../../db_connection.php')
    ? __DIR__ . '/../../db_connection.php'
    : __DIR__ . '/../db_connection.php';
require_once $db_conn_path;

$key   = $_POST['setting_key']   ?? '';
$value = $_POST['setting_value'] ?? '';

if (empty($key)) {
    echo json_encode(['success' => false, 'error' => 'Missing setting_key']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);
$stmt->bind_param('ss', $key, $value);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "Synced: $key = $value"]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
?>
