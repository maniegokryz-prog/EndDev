<?php
require 'db_connection.php';
$employee_id = 1; // Lord Gabriel
$empSql = "SELECT employee_id, first_name, last_name FROM employees WHERE id = ?";
$empStmt = $conn->prepare($empSql);
$empStmt->bind_param("i", $employee_id);
$empStmt->execute();
$empData = $empStmt->get_result()->fetch_assoc();
print_r($empData);
$emp_name = trim(($empData['first_name'] ?? '') . ' ' . ($empData['last_name'] ?? ''));
if (!$emp_name) $emp_name = "A Faculty Member";
echo "\nName resolved: " . $emp_name;
?>
