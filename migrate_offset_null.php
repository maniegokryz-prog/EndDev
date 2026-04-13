<?php
require 'db_connection.php';
$conn->query("ALTER TABLE offset_schedule_requests MODIFY original_schedule_id INT NULL DEFAULT NULL");
$conn->query("ALTER TABLE offset_schedule_requests MODIFY original_day_of_week INT NULL DEFAULT NULL");
echo "DB updated.\n";
?>
