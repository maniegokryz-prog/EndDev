<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$options = [
    "http" => [
        "ignore_errors" => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents('http://localhost/EndDev/dashboard/get_attendance_records.php?type=summary&date=2026-03-02&nocache=' . time(), false, $context);
echo "RESPONSE_NOCACHE: " . $response;
?>
