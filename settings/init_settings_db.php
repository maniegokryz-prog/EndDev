<?php
require_once '../db_connection.php';

// Create system_settings table if not exists
$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table system_settings checked/created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert default break_deduction_minutes if not exists
$key = 'break_deduction_minutes';
$defaultValue = '60'; // Default 1 hour

$checkSql = "SELECT * FROM system_settings WHERE setting_key = '$key'";
$result = $conn->query($checkSql);

if ($result->num_rows == 0) {
    $insertSql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$defaultValue')";
    if ($conn->query($insertSql) === TRUE) {
        echo "Default setting '$key' inserted with value '$defaultValue'.<br>";
    } else {
        echo "Error inserting default setting: " . $conn->error . "<br>";
    }
} else {
    echo "Setting '$key' already exists.<br>";
}

// Also ensure leave_notice_period_days exists (from previous context)
$leaveKey = 'leave_notice_period_days';
$leaveDefault = '0';
$checkLeave = $conn->query("SELECT * FROM system_settings WHERE setting_key = '$leaveKey'");
if ($checkLeave->num_rows == 0) {
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('$leaveKey', '$leaveDefault')");
    echo "Default setting '$leaveKey' inserted.<br>";
}

echo "Database initialization complete.";
?>
