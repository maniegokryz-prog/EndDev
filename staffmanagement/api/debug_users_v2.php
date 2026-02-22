<?php
$servername = "localhost";
$username = "attendance_admin";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "<h3>Employees List:</h3>";
$sql = "SELECT id, employee_id, first_name, last_name FROM employees";
$result = $conn->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        echo "Int ID: " . $row['id'] . " - Code: " . $row['employee_id'] . " - " . $row['first_name'] . " " . $row['last_name'] . "<br>";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
