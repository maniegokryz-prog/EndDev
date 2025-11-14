<?php
require_once '../db_connection.php';

// Check admin_users
$result = $conn->query("SELECT COUNT(*) as count FROM admin_users");
$data = $result->fetch_assoc();
echo "Admin users: " . $data['count'] . "\n";

// Check employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
$data = $result->fetch_assoc();
echo "Employees: " . $data['count'] . "\n";

// Check employees with passwords
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE employee_password IS NOT NULL AND employee_password != ''");
$data = $result->fetch_assoc();
echo "Employees with passwords: " . $data['count'] . "\n";

$conn->close();
?>
