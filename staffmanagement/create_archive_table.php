<?php
// Create employee_archives table
header('Content-Type: text/plain');
require_once '../db_connection.php';

echo "Creating employee_archives table...\n";
echo "====================================\n\n";

$sql = "CREATE TABLE IF NOT EXISTS `employee_archives` (
  `id` int NOT NULL,
  `employee_id` varchar(255) NOT NULL,
  `employee_password` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `roles` text,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'archived',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `original_id` int NOT NULL,
  `archived_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `archived_by` varchar(100) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";

if ($conn->query($sql)) {
    echo "✓ Table 'employee_archives' created successfully!\n\n";
    
    // Show structure
    echo "Table Structure:\n";
    echo "----------------\n";
    $result = $conn->query("DESCRIBE employee_archives");
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
    }
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
