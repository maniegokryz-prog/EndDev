<?php
$servername = "localhost";
$username = "root";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "ALTER TABLE employee_leaves ADD COLUMN rejection_reason TEXT NULL AFTER status";
if ($conn->query($sql) === TRUE) {
    echo "Column rejection_reason added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}
?>
