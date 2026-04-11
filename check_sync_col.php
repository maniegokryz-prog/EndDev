<?php
require_once 'db_connection.php';

$tables = ['makeup_class_requests', 'notifications'];

foreach ($tables as $table) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    if ($res && $res->num_rows > 0) {
        echo "$table HAS sync_status\n";
    } else {
        echo "$table DOES NOT HAVE sync_status. Iterating to add...\n";
        if ($conn->query("ALTER TABLE `$table` ADD COLUMN `sync_status` tinyint(4) NOT NULL DEFAULT 0")) {
            echo "Successfully added sync_status to $table\n";
        } else {
            echo "Failed adding sync_status to $table: " . $conn->error . "\n";
        }
    }
}
?>
