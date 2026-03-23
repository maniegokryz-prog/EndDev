<?php
// Test pushing a setting to Hostinger's confirmed sync_settings.php
$apiKey   = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';
$cloudUrl = 'http://srv1412737.hstgr.cloud/api/sync_settings.php';
$testVal  = 'sync_ok_' . date('H:i:s');

$payload = http_build_query([
    'setting_key'   => 'test_sync',
    'setting_value' => $testVal,
]);

$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nX-API-KEY: $apiKey\r\n",
        'content' => $payload,
        'timeout' => 8,
        'ignore_errors' => true,
    ],
];

$response = @file_get_contents($cloudUrl, false, stream_context_create($opts));
echo "Response: " . htmlspecialchars($response ?: '(empty/no response)') . "<br>";
echo "Sent value: <b>$testVal</b><br>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON ✅' : 'OFF ❌') . "<br>";
?>
