<?php
$conn = new mysqli("localhost", "root", "Confirmp@ssword123", "database_records");
if ($conn->connect_error) die("Conn failed");

echo "<h3>Dump Employee Schedules (Hardcoded Conn)</h3>";
$res = $conn->query("SELECT * FROM employee_schedules");

if (!$res) die("Query failed: " . $conn->error);

if ($res->num_rows == 0) echo "Table is EMPTY";

while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['schedule_id'] . " EmpID: " . $row['employee_id'] . "<br>";
}
echo "<hr>";
echo "<h3>Dump Schedule Periods</h3>";
$res = $conn->query("SELECT * FROM schedule_periods");
while($row = $res->fetch_assoc()) {
    echo "SchedID: " . $row['schedule_id'] . " Day: " . $row['day_of_week'] . "<br>";
}
?>
