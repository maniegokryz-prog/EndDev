<?php
require '../../db_connection.php';
echo "<h3>Dump Employee Schedules</h3>";
$res = $conn->query("SELECT * FROM employee_schedules");
if ($res->num_rows == 0) echo "Table is EMPTY";
while($row = $res->fetch_assoc()) {
    echo json_encode($row) . "<br>";
}
?>
