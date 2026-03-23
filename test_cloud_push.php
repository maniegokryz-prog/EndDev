<?php
// Test if Hostinger sync endpoint is reachable and accepts system_settings push
$cloudUrl = 'http://bpcfaceid.com/api/sync_endpoint.php';
$apiKey   = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';

$payload = http_build_query([
    'action' => 'push',
    'table'  => 'system_settings',
    'data'   => json_encode(['setting_key' => 'grace_period_minutes', 'setting_value' => '99']),
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
echo "<pre>";
echo "Response: " . htmlspecialchars($response ?: '(empty / no response)') . "\n";
echo "HTTP response headers:\n";
print_r($http_response_header ?? 'none');
echo "</pre>";

// Also check if allow_url_fopen is enabled
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "<br>";
echo "curl_enabled: " . (function_exists('curl_init') ? 'YES' : 'NO') . "<br>";
?>
