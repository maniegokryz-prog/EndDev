<?php
// Upload cloud_sync_endpoint_final.php to Hostinger's api/sync_endpoint.php
$cloudUrl = 'http://bpcfaceid.com/api/sync_endpoint.php';
$apiKey   = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';
$localFile = __DIR__ . '/cloud_sync_endpoint_final.php';

if (!file_exists($localFile)) {
    die("Local file not found: $localFile");
}

$ch = curl_init($cloudUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ["X-API-KEY: $apiKey"],
    CURLOPT_POSTFIELDS => [
        'action' => 'upload_file',
        'path'   => 'api/sync_endpoint.php',
        'file'   => new CURLFile($localFile, 'application/octet-stream', 'sync_endpoint.php'),
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: " . htmlspecialchars($response ?: "(empty)") . "<br>";
if ($err) echo "cURL Error: $err";
?>
