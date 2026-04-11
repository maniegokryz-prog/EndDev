<?php
require 'db_connection.php';
$employee_id = 2; // Suppose Ronnel is 2, or whatever. Let's just find an employee_id that exists.
$res = $conn->query("SELECT id FROM employees LIMIT 1");
$employee_id = $res->fetch_assoc()['id'];

$requested_date = '2026-05-01';

$empSql = "SELECT employee_id, first_name, last_name FROM employees WHERE id = ?";
$empStmt = $conn->prepare($empSql);
$empStmt->bind_param("i", $employee_id);
$empStmt->execute();
$empData = $empStmt->get_result()->fetch_assoc();
$emp_pub_id = $empData['employee_id'] ?? '';

$emp_name = trim(($empData['first_name'] ?? '') . ' ' . ($empData['last_name'] ?? ''));
if (!$emp_name) $emp_name = "A Faculty Member";

$msg = "{$emp_name} requested a Makeup Class on " . date('M d, Y', strtotime($requested_date));
echo $msg;
?>
