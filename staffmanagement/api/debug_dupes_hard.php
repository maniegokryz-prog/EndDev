<?php
$conn = new mysqli("localhost", "root", "Confirmp@ssword123", "database_records");
if ($conn->connect_error) die("Conn failed");

echo "<h3>Check for Duplicates (Hardcoded Conn) for 111222</h3>";
$res = $conn->query("SELECT id, employee_id, profile_photo, first_name FROM employees WHERE employee_id = '111222'");
if (!$res) die("Q failed: " . $conn->error);

if ($res->num_rows == 0) echo "NO ROWS FOUND";

while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Code: " . $row['employee_id'] . " | Photo: " . $row['profile_photo'] . " | Name: " . $row['first_name'] . "<br>";
}
?>
