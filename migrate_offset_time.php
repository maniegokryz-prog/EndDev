<?php
require 'db_connection.php';

$res = $conn->query("SHOW COLUMNS FROM offset_schedule_requests LIKE 'start_time'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN start_time TIME NULL DEFAULT NULL AFTER original_day_of_week");
    $conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN end_time TIME NULL DEFAULT NULL AFTER start_time");
    echo "start_time and end_time added successfully.\n";
} else {
    echo "Columns already exist.\n";
}
?>
