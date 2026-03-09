<?php
require '../processes/db_connection.php';
$employee_id = '123456';
$stmt = $conn->prepare("SELECT schedule_data FROM schedule_requests WHERE employee_id = (SELECT id FROM employees WHERE employee_id=?) AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('s', $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
echo "Raw data:\n";
var_dump($row['schedule_data']);
echo "Decoded data:\n";
var_dump(json_decode($row['schedule_data'], true));
echo "JS encoded:\n";
echo json_encode(json_decode($row['schedule_data'], true));
