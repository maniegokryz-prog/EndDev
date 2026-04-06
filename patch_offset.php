<?php
require 'db_connection.php';
$conn->query("ALTER TABLE offset_schedule_requests ADD COLUMN original_day_of_week INT NULL AFTER original_schedule_id");
echo "Done";
?>
