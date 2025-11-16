<?php
require_once 'db_connection.php';

echo "=== Employees Table Structure ===\n\n";

$result = $conn->query("DESCRIBE employees");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n\n=== Sample Employee Data ===\n\n";
$result = $conn->query("SELECT * FROM employees LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        echo "$key: $value\n";
    }
}
?>
