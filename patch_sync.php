<?php
/**
 * HOSTINGER SYNC ENDPOINT & DB PATCHER
 * ════════════════════════════════════
 * Upload this file to your Hostinger site root (e.g. /public_html/patch_sync.php)
 * Then visit: https://yourdomain.com/patch_sync.php?key=patch2026bpc
 * 
 * This will update your api/sync_endpoint.php AND add new columns to your database.
 * DELETE THIS FILE after the patch is applied.
 */

// Safety check
$secret = $_GET['key'] ?? '';
if ($secret !== 'patch2026bpc') {
    die('Access denied. Append ?key=patch2026bpc to the URL to run this patch.');
}

echo "<h3>Applying Patches...</h3><ul>";

// 1. UPDATE DATABASE SCHEMA
if (file_exists(__DIR__ . '/db_connection.php')) {
    require __DIR__ . '/db_connection.php';
    echo "<li>Database connected successfully. Checking tables...</li>";

    // Makeup class requests alterations
    $check1 = $conn->query("SHOW COLUMNS FROM makeup_class_requests LIKE 'attachment_path'");
    if ($check1 && $check1->num_rows == 0) {
        $conn->query("ALTER TABLE makeup_class_requests ADD COLUMN attachment_path VARCHAR(500) NULL");
        echo "<li>✅ Added attachment_path to makeup_class_requests</li>";
    }

    $check2 = $conn->query("SHOW COLUMNS FROM makeup_class_requests LIKE 'cancel_reason'");
    if ($check2 && $check2->num_rows == 0) {
        $conn->query("ALTER TABLE makeup_class_requests ADD COLUMN cancel_reason TEXT NULL");
        echo "<li>✅ Added cancel_reason to makeup_class_requests</li>";
    }

    // Offset schedule requests alterations
    $check3 = $conn->query("SHOW COLUMNS FROM offset_schedule_requests LIKE 'cancel_reason'");
    if ($check3 && $check3->num_rows == 0) {
        $conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN start_time TIME NULL AFTER original_day_of_week");
        $conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN end_time TIME NULL AFTER start_time");
        $conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN cancel_reason TEXT NULL AFTER status");
        echo "<li>✅ Added start_time, end_time, cancel_reason to offset_schedule_requests</li>";
    }

    // Ensure sync_status and last_sync exist on the new sync tables
    $sync_tables = ['makeup_class_requests', 'offset_schedule_requests', 'cto_requests'];
    foreach ($sync_tables as $st) {
        $checkSync = $conn->query("SHOW COLUMNS FROM `$st` LIKE 'sync_status'");
        if ($checkSync && $checkSync->num_rows == 0) {
            $conn->query("ALTER TABLE `$st` ADD COLUMN `sync_status` tinyint(4) NOT NULL DEFAULT 0");
            $conn->query("ALTER TABLE `$st` ADD COLUMN `last_sync` datetime NULL");
            echo "<li>✅ Added sync_status and last_sync to $st</li>";
        }
    }

    // Allow NULL for manual overrides in offset
    $conn->query("ALTER TABLE offset_schedule_requests MODIFY original_schedule_id INT NULL");
    $conn->query("ALTER TABLE offset_schedule_requests MODIFY original_day_of_week INT NULL");
    echo "<li>✅ Updated offset_schedule_requests foreign keys to allow NULL</li>";

} else {
    echo "<li>⚠️ Unable to find db_connection.php. Database patching skipped.</li>";
}


// 2. PATCH SYNC ENDPOINT
$targetFile = __DIR__ . '/api/sync_endpoint.php';

if (!file_exists($targetFile)) {
    die("Error: Could not find /api/sync_endpoint.php");
}

$content = file_get_contents($targetFile);
$changed = false;

if (strpos($content, "'system_settings'") === false) {
    $content = str_replace(
        "'notifications'\n    ];",
        "'notifications',\n        'system_settings'\n    ];",
        $content
    );
    $content = str_replace(
        "'notifications'\r\n    ];",
        "'notifications',\r\n        'system_settings'\r\n    ];",
        $content
    );
    $changed = true;
}

if (strpos($content, "case 'push':") === false && strpos($content, "case \"push\":") === false) {
    $caseToAdd = "    case 'push':\n            handleUpsert(\$conn, \$table, \$data);\n            break;\n\n        ";
    $content = preg_replace(
        '/(switch\s*\(\$action\)\s*\{[\r\n]+\s*)(case\s)/m',
        "$1$caseToAdd$2",
        $content,
        1
    );
    $changed = true;
}

if (strpos($content, 'function handleUpsert') === false) {
    $upsertFn = "\n\nfunction handleUpsert(\$conn, \$table, \$data) { if (empty(\$data)) { echo json_encode(['success'=>true]); return; } \$cols = array_keys(\$data); \$vals = array_values(\$data); \$cs = implode(', ', array_map(function(\$c){return \"`\$c`\";}, \$cols)); \$vs = implode(', ', array_fill(0, count(\$vals), '?')); \$us = implode(', ', array_map(function(\$c){return \"`\$c`=VALUES(`\$c`)\";}, \$cols)); \$sql = \"INSERT INTO `\$table` (\$cs) VALUES (\$vs) ON DUPLICATE KEY UPDATE \$us\"; \$stmt = \$conn->prepare(\$sql); if (!\$stmt) throw new Exception(\$conn->error); \$types = str_repeat('s', count(\$vals)); \$stmt->bind_param(\$types, ...\$vals); if (\$stmt->execute()) { echo json_encode(['success'=>true]); } else { throw new Exception(\$stmt->error); } }\n";
    $content = preg_replace('/\?\>\s*$/', $upsertFn . "\n?>", $content);
    $changed = true;
}

if ($changed) {
    file_put_contents($targetFile, $content);
    echo "<li>✅ api/sync_endpoint.php patched successfully!</li>";
} else {
    echo "<li>⚠️ api/sync_endpoint.php already up to date.</li>";
}

echo "</ul><strong style='color:green'>Done!</strong><br><br><strong>⚠️ Please delete this file (patch_sync.php) from your server for security.</strong>";
?>
