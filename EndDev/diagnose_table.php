<?php
require 'db_connection.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Database Diagnostic</h1>

<?php
// Check if daily_attendance table exists
echo "<h2>1. Check if table exists</h2>";
$result = $conn->query("SHOW TABLES LIKE 'daily_attendance'");
if ($result->num_rows == 0) {
    echo "<p class='error'>❌ Table 'daily_attendance' does NOT exist!</p>";
    echo "<p><a href='startup_new.php'>Click here to run startup_new.php</a></p>";
    exit;
} else {
    echo "<p class='success'>✅ Table 'daily_attendance' exists</p>";
}

// Show table structure
echo "<h2>2. Table Structure</h2>";
$result = $conn->query("DESCRIBE daily_attendance");
echo "<table>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>{$row['Field']}</strong></td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check required columns
echo "<h2>3. Check Required Columns</h2>";
$required_columns = ['id', 'employee_id', 'attendance_date', 'time_in', 'time_out', 
                     'scheduled_hours', 'actual_hours', 'late_minutes', 'status'];
$result = $conn->query("DESCRIBE daily_attendance");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($required_columns as $col) {
    if (in_array($col, $existing_columns)) {
        echo "<p class='success'>✅ Column '{$col}' exists</p>";
    } else {
        echo "<p class='error'>❌ Column '{$col}' is MISSING!</p>";
    }
}

// Count records
echo "<h2>4. Record Count</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM daily_attendance");
$row = $result->fetch_assoc();
echo "<p>Total records: <strong>{$row['count']}</strong></p>";

if ($row['count'] > 0) {
    // Show sample records
    echo "<h2>5. Sample Records (First 5)</h2>";
    $result = $conn->query("SELECT * FROM daily_attendance LIMIT 5");
    if ($result->num_rows > 0) {
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
    }
}

// Test the problematic query
echo "<h2>6. Test Query from indirep.php</h2>";
try {
    $test_query = "SELECT 
                    da.id,
                    da.attendance_date,
                    da.time_in,
                    da.time_out,
                    da.scheduled_hours,
                    da.actual_hours,
                    da.late_minutes,
                    da.status
                  FROM daily_attendance da
                  LIMIT 1";
    
    $stmt = $conn->prepare($test_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p class='success'>✅ Query executed successfully!</p>";
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p>Sample data: <pre>" . print_r($row, true) . "</pre></p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p class='error'>❌ Query failed: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

    <hr>
    <p><a href="indirep.php?id=1&month=2025-11">Back to Individual Report</a></p>
</body>
</html>
