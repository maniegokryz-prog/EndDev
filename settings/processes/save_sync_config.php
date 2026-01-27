<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$configDir = '../../config';
$configFile = $configDir . '/sync_config.json';

// Create config dir if not exists
if (!file_exists($configDir)) {
    mkdir($configDir, 0777, true);
}

// Get POST data
$syncEnabled = isset($_POST['sync_enabled']) && $_POST['sync_enabled'] == '1';
$apiUrl = $_POST['api_url'] ?? '';
$apiKey = $_POST['api_key'] ?? '';
$syncInterval = intval($_POST['sync_interval'] ?? 60);

if (empty($apiUrl) || empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'API URL and Key are required']);
    exit;
}

// Prepare config data
$config = [
    'sync_enabled' => $syncEnabled,
    'api_url' => $apiUrl,
    'api_key' => $apiKey,
    'sync_interval' => $syncInterval,
    'updated_at' => date('Y-m-d H:i:s')
];

// Save to file
if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write config file']);
}
?>
