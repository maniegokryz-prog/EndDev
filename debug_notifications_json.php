<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

$result = $conn->query("SELECT n.id, n.employee_id, n.message, n.link, n.created_at, e.employee_id as emp_code, e.first_name 
                        FROM notifications n 
                        LEFT JOIN employees e ON n.employee_id = e.id 
                        ORDER BY n.id DESC LIMIT 5");
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data, JSON_PRETTY_PRINT);
?>
