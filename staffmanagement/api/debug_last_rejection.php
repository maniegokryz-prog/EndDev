<?php
$servername = "localhost";
$username = "root";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT id, employee_id, status, rejection_reason FROM employee_leaves WHERE status = 'rejected' ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($row = $result->fetch_assoc()) {
    echo "Last Rejected Leave ID: " . $row['id'] . "<br>";
    echo "Employee ID: " . $row['employee_id'] . "<br>";
    echo "Status: " . $row['status'] . "<br>";
    echo "Rejection Reason (Raw): [" . $row['rejection_reason'] . "]<br>";
} else {
    echo "No rejected leaves found.";
}
?>
