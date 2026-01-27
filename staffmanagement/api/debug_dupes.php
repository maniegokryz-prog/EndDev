<?php
require '../../db_connection.php';
echo "<h3>Check for Duplicates (111222)</h3>";
$res = $conn->query("SELECT id, employee_id, profile_photo, first_name FROM employees WHERE employee_id = '111222'");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Code: " . $row['employee_id'] . " | Photo: " . $row['profile_photo'] . " | Name: " . $row['first_name'] . "<br>";
}
?>
