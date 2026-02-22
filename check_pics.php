<?php
require_once 'db_connection.php';
$res = $conn->query("SELECT id, employee_id, first_name, profile_photo FROM employees WHERE profile_photo != 'assets/profile_pic/user.png' LIMIT 5");
echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
?>