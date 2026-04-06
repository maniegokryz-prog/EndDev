<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connection.php';

echo "Running offset tables patch...\n";

// Offset Schedule Requests table
$sql_offset_requests = "CREATE TABLE IF NOT EXISTS offset_schedule_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    original_schedule_id INT NOT NULL,
    requested_date DATE NOT NULL,
    status ENUM('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (original_schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
)";

if ($conn->query($sql_offset_requests) === TRUE) {
    echo "offset_schedule_requests created/exists.\n";
} else {
    echo "Error creating offset_schedule_requests: " . $conn->error . "\n";
}

// Time Bank Ledger table
$sql_time_bank = "CREATE TABLE IF NOT EXISTS time_bank_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    transaction_type ENUM('earned','used','expired') NOT NULL,
    reference_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";

if ($conn->query($sql_time_bank) === TRUE) {
    echo "time_bank_ledger created/exists.\n";
} else {
    echo "Error creating time_bank_ledger: " . $conn->error . "\n";
}

// Ensure sync columns exist
$tables_to_sync = ['offset_schedule_requests', 'time_bank_ledger'];
foreach ($tables_to_sync as $table) {
    $check_sync = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    if ($check_sync->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `sync_status` TINYINT DEFAULT 0");
    }

    $check_last = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'last_sync'");
    if ($check_last->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `last_sync` DATETIME DEFAULT NULL");
    }

    $index_name = "idx_" . $table . "_sync_status";
    $check_idx = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
    if ($check_idx->num_rows == 0) {
        $conn->query("CREATE INDEX `$index_name` ON `$table`(`sync_status`)");
    }
}
echo "Sync columns ensured for new tables.\n";

echo "Done!\n";
$conn->close();
?>
