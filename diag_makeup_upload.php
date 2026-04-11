<?php
/**
 * diag_makeup_upload.php
 * 
 * Upload this to Hostinger and open it to diagnose why makeup attachments
 * are not saving correctly.
 * 
 * URL: http://srv1412737.hstgr.cloud/EndDev/diag_makeup_upload.php
 * DELETE THIS FILE after diagnosis.
 */

$api_dir  = dirname(__FILE__) . '/staffmanagement/api';
$up2      = dirname($api_dir, 2);   // Mirror of dirname(__DIR__, 2) in makeup_class_api.php
$upload_dir = $up2 . '/uploads/makeup_attachments/';

echo "<h2>Makeup Upload Diagnostics</h2>";
echo "<table border='1' cellpadding='6' style='font-family:monospace'>";

// 1. Paths
echo "<tr><th>Variable</th><th>Value</th></tr>";
echo "<tr><td>__FILE__ (this script)</td><td>" . __FILE__ . "</td></tr>";
echo "<tr><td>api_dir (mirrors __DIR__ in makeup_class_api.php)</td><td>$api_dir</td></tr>";
echo "<tr><td>dirname(api_dir, 2)</td><td>$up2</td></tr>";
echo "<tr><td>upload_dir (where files should be saved)</td><td>$upload_dir</td></tr>";

// 2. Directory existence
$dir_exists  = is_dir($upload_dir);
$dir_writable = $dir_exists && is_writable($upload_dir);
echo "<tr><td>Directory exists?</td><td>" . ($dir_exists ? "✅ YES" : "❌ NO") . "</td></tr>";
echo "<tr><td>Directory writable?</td><td>" . ($dir_writable ? "✅ YES" : "❌ NO") . "</td></tr>";

// 3. Try to create directory if missing
if (!$dir_exists) {
    $mkdir_result = @mkdir($upload_dir, 0755, true);
    echo "<tr><td>mkdir() attempt</td><td>" . ($mkdir_result ? "✅ Created OK" : "❌ FAILED - " . error_get_last()['message']) . "</td></tr>";
    if ($mkdir_result) {
        echo "<tr><td>Re-check writable after mkdir</td><td>" . (is_writable($upload_dir) ? "✅ YES" : "❌ NO") . "</td></tr>";
    }
}

// 4. Try writing a test file
$test_file = $upload_dir . 'diag_test.txt';
$write_ok = @file_put_contents($test_file, 'test');
echo "<tr><td>Can write test file?</td><td>" . ($write_ok !== false ? "✅ YES (" . $test_file . ")" : "❌ FAILED") . "</td></tr>";
if ($write_ok !== false) {
    unlink($test_file); // clean up
}

// 5. Check actual URL the file would be served from
echo "<tr><td>Expected URL for uploaded file</td><td>EndDev/uploads/makeup_attachments/{filename}</td></tr>";

// 6. Check PHP upload settings
echo "<tr><td>upload_max_filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td>post_max_size</td><td>" . ini_get('post_max_size') . "</td></tr>";
echo "<tr><td>file_uploads enabled</td><td>" . (ini_get('file_uploads') ? "✅ YES" : "❌ NO") . "</td></tr>";

// 7. List current files in directory (if any)
if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '*');
    echo "<tr><td>Files in upload_dir</td><td>" . (empty($files) ? "(empty)" : implode("<br>", $files)) . "</td></tr>";
}

echo "</table>";

echo "<hr><h3>Server Info</h3>";
echo "<pre>";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "</pre>";
?>
