<?php
require_once 'db_connection.php';

echo "Checking for link column in notifications table...\n";

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
if ($result->num_rows == 0) {
    echo "Column 'link' not found. Adding it...\n";
    $sql = "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER message";
    if ($conn->query($sql)) {
        echo "SUCCESS: Added link column to notifications table.\n";
    } else {
        echo "ERROR: Failed to add link column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'link' already exists.\n";
}

echo "Done.\n";
$conn->close();
?>
