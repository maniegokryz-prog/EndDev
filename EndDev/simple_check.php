<?php
require 'db_connection.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Table Check</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn-danger { background: #f44336; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🔍 Simple Table Check</h1>

<?php
// Check if table exists
echo "<div class='box'><h2>Step 1: Does table exist?</h2>";
$result = $conn->query("SHOW TABLES LIKE 'daily_attendance'");
if ($result->num_rows == 0) {
    echo "<div class='error'>❌ Table 'daily_attendance' does NOT exist!</div>";
    echo "<p><a href='startup_new.php' class='btn'>Run startup_new.php to Create</a></p>";
    echo "</div></body></html>";
    exit;
} else {
    echo "<div class='success'>✅ Table 'daily_attendance' exists</div>";
}
echo "</div>";

// Show ALL columns without using aliases
echo "<div class='box'><h2>Step 2: What columns exist?</h2>";
$result = $conn->query("DESCRIBE daily_attendance");
if (!$result) {
    echo "<div class='error'>Error: " . $conn->error . "</div>";
} else {
    echo "<table>";
    echo "<tr><th>#</th><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    $i = 1;
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo "<tr>";
        echo "<td>$i</td>";
        echo "<td><strong style='color: blue;'>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "</tr>";
        $i++;
    }
    echo "</table>";
    
    echo "<h3>Column Names (copy-paste friendly):</h3>";
    echo "<pre>" . implode("\n", $columns) . "</pre>";
}
echo "</div>";

// Check specific columns
echo "<div class='box'><h2>Step 3: Check Required Columns</h2>";
$required = [
    'id', 'employee_id', 'attendance_date', 'time_in', 'time_out', 
    'scheduled_hours', 'actual_hours', 'late_minutes', 'status'
];

echo "<table>";
echo "<tr><th>Required Column</th><th>Status</th></tr>";
foreach ($required as $col) {
    $exists = in_array($col, $columns);
    echo "<tr>";
    echo "<td><strong>$col</strong></td>";
    if ($exists) {
        echo "<td style='color: green;'>✅ EXISTS</td>";
    } else {
        echo "<td style='color: red;'>❌ MISSING</td>";
    }
    echo "</tr>";
}
echo "</table>";

$missing = array_diff($required, $columns);
if (!empty($missing)) {
    echo "<div class='error'>";
    echo "<strong>Missing columns:</strong> " . implode(", ", $missing);
    echo "</div>";
    echo "<p><a href='fix_dtr_table.php' class='btn btn-danger'>Fix Table Now</a></p>";
}
echo "</div>";

// Try simple query without alias
echo "<div class='box'><h2>Step 4: Test Simple Query (No Alias)</h2>";
try {
    $sql = "SELECT * FROM daily_attendance LIMIT 1";
    $result = $conn->query($sql);
    if ($result) {
        echo "<div class='success'>✅ Simple SELECT * works!</div>";
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<p>Sample record found:</p>";
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "<p>No records in table (but table structure is OK)</p>";
        }
    } else {
        echo "<div class='error'>❌ Error: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Try query with alias (like indirep.php does)
echo "<div class='box'><h2>Step 5: Test Query WITH Alias (like indirep.php)</h2>";
try {
    $sql = "SELECT 
                time_in,
                time_out,
                scheduled_hours,
                actual_hours,
                late_minutes,
                status
            FROM daily_attendance 
            WHERE employee_id = 1 
            LIMIT 1";
    
    echo "<h4>SQL without alias:</h4>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    $result = $conn->query($sql);
    if ($result) {
        echo "<div class='success'>✅ Query without alias works!</div>";
    } else {
        echo "<div class='error'>❌ Query failed: " . $conn->error . "</div>";
    }
    
    // Now try WITH alias
    $sql2 = "SELECT 
                da.time_in,
                da.time_out,
                da.scheduled_hours,
                da.actual_hours,
                da.late_minutes,
                da.status
            FROM daily_attendance da
            WHERE da.employee_id = 1 
            LIMIT 1";
    
    echo "<h4>SQL with alias 'da':</h4>";
    echo "<pre>" . htmlspecialchars($sql2) . "</pre>";
    
    $result2 = $conn->query($sql2);
    if ($result2) {
        echo "<div class='success'>✅ Query WITH alias works! Table is OK!</div>";
        echo "<p><strong>Conclusion:</strong> The table structure is correct. The error might be in how we're executing the query.</p>";
    } else {
        echo "<div class='error'>❌ Query with alias FAILED: " . $conn->error . "</div>";
        echo "<p><strong>This is the problem!</strong> The columns exist but something is wrong with the table structure.</p>";
        echo "<p><a href='fix_dtr_table.php' class='btn btn-danger'>Recreate Table</a></p>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}
echo "</div>";

$conn->close();
?>

<div class='box'>
    <h2>📋 Actions</h2>
    <a href='fix_dtr_table.php' class='btn btn-danger'>Fix/Recreate Table</a>
    <a href='startup_new.php' class='btn'>Run Startup Script</a>
    <a href='insert_test_dtr_data.php' class='btn'>Insert Test Data</a>
    <a href='indirep.php?id=1&month=2025-11' class='btn'>Try Individual Report</a>
</div>

</body>
</html>
