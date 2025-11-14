<?php
require 'db_connection.php';

echo "<h2>Checking daily_attendance table structure</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'daily_attendance'");
if ($result->num_rows == 0) {
    echo "<p style='color:red;'>❌ Table 'daily_attendance' does NOT exist!</p>";
    echo "<p>Run startup_new.php to create it.</p>";
} else {
    echo "<p style='color:green;'>✅ Table 'daily_attendance' exists</p>";
    
    // Show columns
    echo "<h3>Columns:</h3>";
    $result = $conn->query("DESCRIBE daily_attendance");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count records
    $result = $conn->query("SELECT COUNT(*) as count FROM daily_attendance");
    $row = $result->fetch_assoc();
    echo "<p>Total records: <strong>{$row['count']}</strong></p>";
}

$conn->close();
?>
