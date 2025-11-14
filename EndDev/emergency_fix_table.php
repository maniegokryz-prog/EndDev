<?php
/**
 * EMERGENCY FIX for daily_attendance table
 * This will DROP and RECREATE the table with correct structure
 */

require 'db_connection.php';

echo "<!DOCTYPE html><html><head><title>Emergency Table Fix</title>";
echo "<style>
    body { font-family: Arial; padding: 40px; background: #f5f5f5; }
    .box { background: white; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .success { background: #4CAF50; color: white; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .error { background: #f44336; color: white; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .warning { background: #ff9800; color: white; padding: 15px; border-radius: 4px; margin: 10px 0; }
    .btn { display: inline-block; padding: 15px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; font-size: 16px; border: none; cursor: pointer; }
    .btn-danger { background: #f44336; }
    h1 { color: #333; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🚨 Emergency Table Fix</h1>";

// Check current state
$check = $conn->query("SHOW TABLES LIKE 'daily_attendance'");
$exists = ($check->num_rows > 0);

if ($exists) {
    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM daily_attendance");
    $count = $count_result->fetch_assoc()['cnt'];
    
    echo "<div class='warning'>";
    echo "<strong>⚠️ WARNING:</strong> Table exists with $count record(s).";
    echo "</div>";
    
    // Show current structure
    echo "<h3>Current Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE daily_attendance");
    echo "<pre>";
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "<div class='error'>❌ Table does NOT exist</div>";
}

// Show action form
if (!isset($_POST['confirm'])) {
    echo "<h2>What do you want to do?</h2>";
    echo "<form method='POST'>";
    
    if ($exists) {
        echo "<p>This will <strong>DELETE ALL DATA</strong> and recreate the table with correct structure.</p>";
        echo "<button type='submit' name='confirm' value='drop_create' class='btn btn-danger' onclick='return confirm(\"Are you ABSOLUTELY sure? This will delete all attendance records!\")'>
                DROP and RECREATE Table
              </button>";
    } else {
        echo "<p>This will create the table with correct structure.</p>";
        echo "<button type='submit' name='confirm' value='create' class='btn'>
                CREATE Table
              </button>";
    }
    
    echo "</form>";
    echo "<p><a href='simple_check.php'>← Back to Diagnostics</a></p>";
    
} else {
    // Execute the fix
    echo "<h2>Executing Fix...</h2>";
    
    $action = $_POST['confirm'];
    
    // Step 1: Drop table if exists and user confirmed
    if ($exists && $action === 'drop_create') {
        echo "<p>Step 1: Dropping existing table...</p>";
        if ($conn->query("DROP TABLE IF EXISTS daily_attendance")) {
            echo "<div class='success'>✅ Table dropped successfully</div>";
        } else {
            echo "<div class='error'>❌ Error dropping table: " . $conn->error . "</div>";
            exit;
        }
    }
    
    // Step 2: Create table with correct structure
    echo "<p>Step 2: Creating table with correct structure...</p>";
    
    $sql = "CREATE TABLE daily_attendance (
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
    
    echo "<h4>SQL Statement:</h4>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Table created successfully!</div>";
        
        // Verify structure
        echo "<h3>New Table Structure:</h3>";
        $structure = $conn->query("DESCRIBE daily_attendance");
        echo "<pre>";
        while ($row = $structure->fetch_assoc()) {
            echo $row['Field'] . " | " . $row['Type'] . "\n";
        }
        echo "</pre>";
        
        // Test the problematic query
        echo "<h3>Testing Query:</h3>";
        $test_query = "SELECT 
                        da.id,
                        da.attendance_date,
                        da.time_in,
                        da.time_out
                      FROM daily_attendance da
                      LIMIT 1";
        
        $test_stmt = $conn->prepare($test_query);
        if ($test_stmt) {
            echo "<div class='success'>✅ Query test PASSED! Table is working correctly!</div>";
            $test_stmt->close();
        } else {
            echo "<div class='error'>❌ Query test FAILED: " . $conn->error . "</div>";
        }
        
        echo "<hr>";
        echo "<h2>✅ SUCCESS! Next Steps:</h2>";
        echo "<ol>";
        echo "<li><a href='insert_test_dtr_data.php' class='btn'>Insert Test Data</a></li>";
        echo "<li><a href='simple_check.php' class='btn'>Verify Table Structure</a></li>";
        echo "<li><a href='indirep.php?id=1&month=2025-11' class='btn'>View Individual Report</a></li>";
        echo "</ol>";
        
    } else {
        echo "<div class='error'>❌ Error creating table: " . $conn->error . "</div>";
    }
}

echo "</div>";
echo "</body></html>";

$conn->close();
?>
