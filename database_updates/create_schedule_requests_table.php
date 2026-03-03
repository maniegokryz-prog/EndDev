<?php
require_once __DIR__ . '/../db_connection.php';

$sql = "
CREATE TABLE IF NOT EXISTS `schedule_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `employee_id_string` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `schedule_data` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `sync_status` tinyint(4) DEFAULT 0,
  `last_sync` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'schedule_requests' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
