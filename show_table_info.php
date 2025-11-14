<?php
require 'db_connection.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Show Table Columns</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; }
        .warning { background: #fff3e0; color: #e65100; padding: 15px; border-radius: 4px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🔍 Database Table Investigation</h1>

<?php
// Show all tables
echo "<div class='box'>";
echo "<h2>All Tables in Database</h2>";
$result = $conn->query("SHOW TABLES");
echo "<ul>";
while ($row = $result->fetch_row()) {
    $table_name = $row[0];
    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM `$table_name`");
    $count = $count_result->fetch_assoc()['cnt'];
    echo "<li><strong>$table_name</strong> ($count records)</li>";
}
echo "</ul>";
echo "</div>";

// Check specifically for daily_attendance
echo "<div class='box'>";
echo "<h2>daily_attendance Table</h2>";
$result = $conn->query("SHOW TABLES LIKE 'daily_attendance'");
if ($result->num_rows == 0) {
    echo "<div class='error'>❌ Table 'daily_attendance' does NOT exist!</div>";
    echo "<p><a href='fix_dtr_table.php' style='font-size: 18px; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Create Table Now</a></p>";
} else {
    echo "<div class='success'>✅ Table exists</div>";
    
    // Show columns
    echo "<h3>Column Structure:</h3>";
    $result = $conn->query("DESCRIBE daily_attendance");
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    $column_names = [];
    while ($row = $result->fetch_assoc()) {
        $column_names[] = $row['Field'];
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>" . ($row['Default'] ?? '<em>NULL</em>') . "</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show column names list
    echo "<h3>All Column Names:</h3>";
    echo "<pre>" . implode(", ", $column_names) . "</pre>";
    
    // Check required columns
    echo "<h3>Required Columns Check:</h3>";
    $required = ['time_in', 'time_out', 'scheduled_hours', 'actual_hours', 'late_minutes', 
                 'early_departure_minutes', 'overtime_minutes', 'break_time_minutes', 'status', 'notes'];
    
    $missing = array_diff($required, $column_names);
    if (empty($missing)) {
        echo "<div class='success'>✅ All required columns exist!</div>";
    } else {
        echo "<div class='error'>❌ Missing columns: " . implode(", ", $missing) . "</div>";
        echo "<p><a href='fix_dtr_table.php' style='font-size: 18px; padding: 10px 20px; background: #f44336; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Fix Table Structure</a></p>";
    }
    
    // Show record count
    $result = $conn->query("SELECT COUNT(*) as count FROM daily_attendance");
    $count = $result->fetch_assoc()['count'];
    echo "<h3>Records:</h3>";
    echo "<p>Total records: <strong>$count</strong></p>";
    
    // Show sample data if exists
    if ($count > 0) {
        echo "<h3>Sample Records (First 3):</h3>";
        $result = $conn->query("SELECT * FROM daily_attendance LIMIT 3");
        echo "<div style='overflow-x: auto;'>";
        echo "<table>";
        $first = true;
        while ($record = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach ($record as $key => $value) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
}
echo "</div>";

// Test the exact query from indirep.php
echo "<div class='box'>";
echo "<h2>Test Query from indirep.php</h2>";
try {
    $test_employee_id = 1;
    $test_month = '2025-11';
    
    echo "<p>Testing with: employee_id = $test_employee_id, month = $test_month</p>";
    
    $query = "SELECT 
                da.id,
                da.attendance_date,
                da.time_in,
                da.time_out,
                da.scheduled_hours,
                da.actual_hours,
                da.late_minutes,
                da.early_departure_minutes,
                da.overtime_minutes,
                da.break_time_minutes,
                da.status,
                da.notes
              FROM daily_attendance da
              WHERE da.employee_id = ?
              AND DATE_FORMAT(da.attendance_date, '%Y-%m') = ?
              LIMIT 5";
    
    echo "<h4>SQL Query:</h4>";
    echo "<pre>" . htmlspecialchars($query) . "</pre>";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('is', $test_employee_id, $test_month);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    echo "<div class='success'>✅ Query executed successfully!</div>";
    echo "<p>Rows returned: " . $result->num_rows . "</p>";
    
    if ($result->num_rows > 0) {
        echo "<h4>Results:</h4>";
        echo "<table>";
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach ($row as $key => $value) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No records found for this employee/month</div>";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Query Failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p>This is the exact error happening in indirep.php</p>";
    echo "<p><a href='fix_dtr_table.php' style='font-size: 18px; padding: 10px 20px; background: #f44336; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Fix Table Now</a></p>";
}
echo "</div>";

$conn->close();
?>

<div class='box'>
    <h2>Next Steps</h2>
    <ol>
        <li>If table doesn't exist or has wrong columns: <a href='fix_dtr_table.php'><strong>Fix Table Structure</strong></a></li>
        <li>If table is correct but no data: <a href='insert_test_dtr_data.php'><strong>Insert Test Data</strong></a></li>
        <li>If everything looks good: <a href='indirep.php?id=1&month=2025-11'><strong>View Individual Report</strong></a></li>
    </ol>
</div>

</body>
</html>
