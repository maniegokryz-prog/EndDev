<?php
require 'db_connection.php';
$sql = "SELECT * FROM offset_schedule_requests";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "\n---JOIN QUERY---\n";
$sql2 = "SELECT r.*, s.schedule_name FROM offset_schedule_requests r JOIN schedules s ON r.original_schedule_id = s.id JOIN employee_schedules es ON (s.id = es.schedule_id AND es.employee_id = r.employee_id) WHERE r.status = 'pending' GROUP BY r.id";
$res2 = $conn->query($sql2);
while($row = $res2->fetch_assoc()) {
    print_r($row);
}
?>
