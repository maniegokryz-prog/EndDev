<?php
// Check if employee_archives table exists
header('Content-Type: text/plain');
require_once '../db_connection.php';

echo "Database Schema Check\n";
echo "=====================\n\n";

// Check if employee_archives table exists
$result = $conn->query("SHOW TABLES LIKE 'employee_archives'");
if ($result->num_rows > 0) {
    echo "✓ Table 'employee_archives' exists\n\n";
    
    // Show structure
    echo "Table Structure:\n";
    echo "----------------\n";
    $result = $conn->query("DESCRIBE employee_archives");
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
} else {
    echo "✗ Table 'employee_archives' does NOT exist\n\n";
    echo "Creating SQL to create the table...\n\n";
    
    // Get employees table structure to replicate
    $result = $conn->query("SHOW CREATE TABLE employees");
    if ($row = $result->fetch_assoc()) {
        $createTable = $row['Create Table'];
        // Replace table name
        $createArchiveTable = str_replace('CREATE TABLE `employees`', 'CREATE TABLE `employee_archives`', $createTable);
        // Add archived_at and archived_by columns
        $createArchiveTable = str_replace(
            'PRIMARY KEY (`id`)',
            'PRIMARY KEY (`id`),
  `archived_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `archived_by` varchar(100) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL',
            $createArchiveTable
        );
        
        echo "SQL to create archive table:\n";
        echo "-----------------------------\n";
        echo $createArchiveTable . ";\n\n";
    }
}

// Check employees table
echo "\nEmployees Table:\n";
echo "----------------\n";
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
$row = $result->fetch_assoc();
echo "Total employees: {$row['count']}\n";

$conn->close();
?>
