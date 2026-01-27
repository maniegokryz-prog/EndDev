<?php
// Simulate get_employee_requests
$servername = "localhost";
$username = "root";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$employee_id = 7;

echo "<h3>Debugging API Response for Employee ID: $employee_id</h3>";

$sql = "SELECT el.*, el.rejection_reason, lt.type_name as leave_type
        FROM employee_leaves el
        INNER JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.employee_id = $employee_id
        ORDER BY el.created_at DESC LIMIT 5";

$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
?>
