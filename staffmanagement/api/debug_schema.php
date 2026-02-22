<?php
$servername = "localhost";
$username = "attendance_admin";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SHOW COLUMNS FROM employee_leaves");
echo "<h2>Columns in employee_leaves:</h2><ul>";
while($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . "</li>";
}
echo "</ul>";
?>
