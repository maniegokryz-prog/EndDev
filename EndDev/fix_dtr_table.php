<?php
require 'db_connection.php';

echo "<h1>Fix daily_attendance Table</h1>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'daily_attendance'");

if ($result->num_rows > 0) {
    echo "<p style='color:orange;'>⚠️ Table 'daily_attendance' exists. Checking structure...</p>";
    
    // Check if it has the correct columns
    $result = $conn->query("DESCRIBE daily_attendance");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['time_in', 'time_out', 'scheduled_hours', 'actual_hours', 'late_minutes'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "<p style='color:green;'>✅ Table has all required columns!</p>";
        echo "<p><a href='indirep.php?id=1&month=2025-11'>Go to Individual Report</a></p>";
        exit;
    } else {
        echo "<p style='color:red;'>❌ Table is missing columns: " . implode(', ', $missing_columns) . "</p>";
        echo "<p>We need to recreate the table.</p>";
        
        // Check if there's data
        $result = $conn->query("SELECT COUNT(*) as count FROM daily_attendance");
        $row = $result->fetch_assoc();
        $record_count = $row['count'];
        
        if ($record_count > 0) {
            echo "<p style='color:orange;'>⚠️ Warning: Table has {$record_count} records that will be lost!</p>";
            echo "<form method='POST'>";
            echo "<button type='submit' name='recreate' value='yes' onclick='return confirm(\"Are you sure? This will delete {$record_count} records!\")'>Recreate Table (Delete {$record_count} records)</button>";
            echo "</form>";
        } else {
            echo "<form method='POST'>";
            echo "<button type='submit' name='recreate' value='yes'>Recreate Table</button>";
            echo "</form>";
        }
    }
} else {
    echo "<p style='color:red;'>❌ Table 'daily_attendance' does not exist!</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='create' value='yes'>Create Table</button>";
    echo "</form>";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['recreate'])) {
        echo "<hr><h2>Recreating Table...</h2>";
        
        // Drop existing table
        if ($conn->query("DROP TABLE IF EXISTS daily_attendance")) {
            echo "<p style='color:green;'>✅ Dropped old table</p>";
        } else {
            echo "<p style='color:red;'>❌ Error dropping table: " . $conn->error . "</p>";
        }
    }
    
    if (isset($_POST['create']) || isset($_POST['recreate'])) {
        echo "<h2>Creating Table...</h2>";
        
        $sql = "CREATE TABLE IF NOT EXISTS daily_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME,
            time_out TIME,
            scheduled_hours DECIMAL(5,2),
            actual_hours DECIMAL(5,2),
            late_minutes INT DEFAULT 0,
            early_departure_minutes INT DEFAULT 0,
            overtime_minutes INT DEFAULT 0,
            break_time_minutes INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'incomplete',
            notes TEXT,
            calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE(employee_id, attendance_date)
        )";
        
        if ($conn->query($sql)) {
            echo "<p style='color:green;'>✅ Table created successfully!</p>";
            echo "<hr>";
            echo "<h3>Next Steps:</h3>";
            echo "<ol>";
            echo "<li><a href='insert_test_dtr_data.php'>Insert test data</a></li>";
            echo "<li><a href='diagnose_table.php'>Verify table structure</a></li>";
            echo "<li><a href='indirep.php?id=1&month=2025-11'>View Individual Report</a></li>";
            echo "</ol>";
        } else {
            echo "<p style='color:red;'>❌ Error creating table: " . $conn->error . "</p>";
        }
    }
}

$conn->close();
?>
