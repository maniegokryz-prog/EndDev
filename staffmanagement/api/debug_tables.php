<?php
require '../../db_connection.php';
echo "<h3>Tables:</h3>";
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) { echo $row[0] . "<br>"; }

echo "<h3>Cols in employee_schedules:</h3>";
$res = $conn->query("SHOW COLUMNS FROM employee_schedules");
if($res) {
    while($row = $res->fetch_assoc()) { echo $row['Field'] . "<br>"; }
} else {
    echo "Query failed: " . $conn->error;
}
?>
