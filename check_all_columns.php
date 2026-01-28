<?php
require_once 'db_connection.php';

function checkTable($conn, $table) {
    echo "=== $table ===\n";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table not found or error: " . $conn->error . "\n";
    }
    echo "\n";
}

checkTable($conn, 'employee_leaves');
checkTable($conn, 'notifications');
?>
