<?php
/**
 * Migration script to add attachment column to employee_leaves table
 * Run this file once to add file attachment support
 */

require 'db_connection.php';

echo "Adding attachment column to employee_leaves table...\n";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM employee_leaves LIKE 'attachment'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "✓ Column 'attachment' already exists. No changes needed.\n";
} else {
    // Add the column
    $alter_sql = "ALTER TABLE employee_leaves 
                  ADD COLUMN attachment VARCHAR(255) NULL 
                  AFTER reason";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "✓ Column 'attachment' added successfully!\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
    }
}

$conn->close();
echo "\nMigration complete!\n";
?>
