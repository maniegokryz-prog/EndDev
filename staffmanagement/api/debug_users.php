<?php
$conn = new mysqli("localhost", "root", "Confirmp@ssword123", "database_records");

echo "<h3>Employees:</h3>";
$res = $conn->query("SELECT id, employee_id, first_name, last_name FROM employees");
while($row = $res->fetch_assoc()) {
    echo "Int ID: " . $row['id'] . " - Code: " . $row['employee_id'] . " - " . $row['first_name'] . " " . $row['last_name'] . "<br>";
}

echo "<h3>Leaves by Employee ID:</h3>";
$res = $conn->query("SELECT employee_id, status, count(*) as cnt FROM employee_leaves GROUP BY employee_id, status");
while($row = $res->fetch_assoc()) {
    echo "Emp ID: " . $row['employee_id'] . " - Status: " . $row['status'] . " - Count: " . $row['cnt'] . "<br>";
}
?>
