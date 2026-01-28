<?php
require_once 'db_connection.php';

echo "Adding cloud_id column to employee_leaves...\n";

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'cloud_id'");
if ($result->num_rows > 0) {
    echo "Column 'cloud_id' already exists.\n";
} else {
    // Add column
    $sql = "ALTER TABLE employee_leaves ADD COLUMN cloud_id INT NULL DEFAULT NULL AFTER id, ADD INDEX idx_cloud_id (cloud_id)";
    if ($conn->query($sql)) {
        echo "Successfully added 'cloud_id' column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}
?>
