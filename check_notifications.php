<?php
require 'db_connection.php';
$res = $conn->query("SELECT id, employee_id, message, created_at FROM notifications WHERE type = 'makeup_request' ORDER BY created_at DESC LIMIT 10");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']}, EmpID: {$row['employee_id']}, Msg: {$row['message']}, Time: {$row['created_at']}\n";
}
?>
