<?php
/**
 * HOSTINGER SYNC ENDPOINT PATCHER
 * ═══════════════════════════════
 * Upload this file to your Hostinger site root (e.g. /public_html/patch_sync.php)
 * Then visit: https://bpcfaceid.com/patch_sync.php
 * 
 * This will update your api/sync_endpoint.php to accept system_settings syncing.
 * DELETE THIS FILE after the patch is applied.
 */

// Safety check – only site admin should run this
$secret = $_GET['key'] ?? '';
if ($secret !== 'patch2026bpc') {
    die('Access denied. Append ?key=patch2026bpc to the URL to run this patch.');
}

$targetFile = __DIR__ . '/api/sync_endpoint.php';

if (!file_exists($targetFile)) {
    die("Error: Could not find /api/sync_endpoint.php");
}

$content = file_get_contents($targetFile);
$changed = false;

// 1. Add system_settings to allowed_tables if not already there
if (strpos($content, "'system_settings'") === false) {
    // Add before the closing bracket of the allowed_tables array
    $content = str_replace(
        "'notifications'\n    ];",
        "'notifications',\n        'system_settings'\n    ];",
        $content
    );
    
    // Also try with double quotes
    $content = str_replace(
        "'notifications'\r\n    ];",
        "'notifications',\r\n        'system_settings'\r\n    ];",
        $content
    );
    $changed = true;
}

// 2. Add the 'push' case and handleUpsert function if not already there
if (strpos($content, "case 'push':") === false && strpos($content, "case \"push\":") === false) {

    // Add 'push' case to switch
    $caseToAdd = "    case 'push': // Generic upsert from localhost\n            handleUpsert(\$conn, \$table, \$data);\n            break;\n\n        ";
    
    // Find the first case in the switch and insert before it
    $content = preg_replace(
        '/(switch\s*\(\$action\)\s*\{[\r\n]+\s*)(case\s)/m',
        "$1$caseToAdd$2",
        $content,
        1
    );
    $changed = true;
}

// 3. Add handleUpsert function if not already there
if (strpos($content, 'function handleUpsert') === false) {
    $upsertFn = <<<'PHP'


function handleUpsert($conn, $table, $data)
{
    if (empty($data) || !is_array($data)) {
        echo json_encode(['success' => true, 'message' => 'No data']);
        return;
    }
    $columns = array_keys($data);
    $values  = array_values($data);
    $cols_sql = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
    $vals_sql = implode(', ', array_fill(0, count($values), '?'));
    $update_parts = array_map(function($c) { return "`$c` = VALUES(`$c`)"; }, $columns);
    $update_sql = implode(', ', $update_parts);
    $sql  = "INSERT INTO `$table` ($cols_sql) VALUES ($vals_sql) ON DUPLICATE KEY UPDATE $update_sql";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Upserted successfully']);
    } else {
        throw new Exception($stmt->error);
    }
}
PHP;
    // Insert just before the last closing ?>
    $content = preg_replace('/\?\>\s*$/', $upsertFn . "\n?>", $content);
    $changed = true;
}

if ($changed) {
    file_put_contents($targetFile, $content);
    echo "<strong style='color:green'>✅ Patch applied successfully!</strong><br>";
    echo "The following changes were made to <code>api/sync_endpoint.php</code>:<br><ul>";
    echo "<li>Added <code>system_settings</code> to the allowed tables whitelist</li>";
    echo "<li>Added <code>push</code> action case to the switch</li>";
    echo "<li>Added <code>handleUpsert()</code> function</li></ul>";
    echo "<br><strong>⚠️ Please delete this file (patch_sync.php) from your server for security.</strong>";
} else {
    echo "<strong style='color:orange'>⚠️ No changes were needed — patch may already be applied, or the file structure didn't match.</strong>";
    echo "<br>Current file content preview:<br><pre>" . htmlspecialchars(substr($content, 0, 800)) . "...</pre>";
}
?>
