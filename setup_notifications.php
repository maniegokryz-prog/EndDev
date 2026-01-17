<?php
/**
 * Setup Notifications Table
 * Run this once to create the notifications table
 */

require_once 'db_connection.php';

echo "<h2>Setting up Notifications System</h2>";

// Add attachment column to employee_leaves if it doesn't exist
echo "<h3>1. Checking employee_leaves table...</h3>";
$result = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'attachment'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE employee_leaves ADD COLUMN attachment VARCHAR(255) NULL AFTER reason";
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Added attachment column to employee_leaves table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding attachment column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Attachment column already exists in employee_leaves</p>";
}

// Create notifications table
echo "<h3>2. Checking notifications table...</h3>";
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    leave_id INT,
    type VARCHAR(50),
    message TEXT,
    link VARCHAR(255) NULL,
    target ENUM('admin', 'employee') DEFAULT 'admin',
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target),
    INDEX idx_employee (employee_id),
    INDEX idx_read (is_read),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_id) REFERENCES employee_leaves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ Notifications table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating notifications table: " . $conn->error . "</p>";
}

// Add link column if it doesn't exist
echo "<h3>3. Checking for link column...</h3>";
$result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER message";
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Added link column to notifications table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding link column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Link column already exists in notifications</p>";
}

// Check if table exists and show structure
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Notifications table exists</p>";
    
    // Show column structure
    $columns = $conn->query("DESCRIBE notifications");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ Notifications table does not exist</p>";
}

echo "<br><a href='dashboard/dashboard.php'>Go to Dashboard</a>";

$conn->close();
?>
