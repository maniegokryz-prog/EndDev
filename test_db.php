<?php
require 'db_connection.php';
$res = $conn->query("SELECT id, scheduled_hours FROM daily_attendance WHERE attendance_date='2026-04-12'");
while($r = $res->fetch_assoc()) var_dump($r);
?>
