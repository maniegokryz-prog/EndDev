<?php
require '../db_connection.php';

echo "<h2>Leave Management Database Check</h2>";

// Check if leave_types table exists
echo "<h3>1. Checking leave_types table...</h3>";
$result = $conn->query("SHOW TABLES LIKE 'leave_types'");
if ($result->num_rows > 0) {
    echo "✅ Table 'leave_types' exists<br>";
    
    // Show structure
    $result = $conn->query("DESCRIBE leave_types");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table><br>";
    
    // Count records
    $result = $conn->query("SELECT COUNT(*) as count FROM leave_types");
    $count = $result->fetch_assoc()['count'];
    echo "Total leave types: $count<br><br>";
} else {
    echo "❌ Table 'leave_types' does NOT exist<br><br>";
}

// Check if employee_leaves table exists
echo "<h3>2. Checking employee_leaves table...</h3>";
$result = $conn->query("SHOW TABLES LIKE 'employee_leaves'");
if ($result->num_rows > 0) {
    echo "✅ Table 'employee_leaves' exists<br>";
    
    // Show structure
    $result = $conn->query("DESCRIBE employee_leaves");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table><br>";
    
    // Count records
    $result = $conn->query("SELECT COUNT(*) as count FROM employee_leaves");
    $count = $result->fetch_assoc()['count'];
    echo "Total leave requests: $count<br>";
    
    // Show all records
    if ($count > 0) {
        echo "<h4>All Leave Requests:</h4>";
        $result = $conn->query("SELECT el.*, e.employee_id as emp_code, CONCAT(e.first_name, ' ', e.last_name) as emp_name, lt.type_name 
                                FROM employee_leaves el 
                                JOIN employees e ON el.employee_id = e.id 
                                JOIN leave_types lt ON el.leave_type_id = lt.id 
                                ORDER BY el.created_at DESC");
        echo "<table border='1'><tr><th>ID</th><th>Employee</th><th>Type</th><th>Start</th><th>End</th><th>Status</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['emp_name']} ({$row['emp_code']})</td>";
            echo "<td>{$row['type_name']}</td>";
            echo "<td>{$row['start_date']}</td>";
            echo "<td>{$row['end_date']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "<br>";
} else {
    echo "❌ Table 'employee_leaves' does NOT exist<br><br>";
}

// Check employees table
echo "<h3>3. Checking employees table...</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
$count = $result->fetch_assoc()['count'];
echo "Active employees: $count<br>";

$result = $conn->query("SELECT id, employee_id, first_name, last_name FROM employees WHERE status = 'active' LIMIT 5");
echo "<table border='1'><tr><th>DB ID</th><th>Employee ID</th><th>Name</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['employee_id']}</td><td>{$row['first_name']} {$row['last_name']}</td></tr>";
}
echo "</table><br>";

echo "<h3>4. Testing API Path</h3>";
$api_path = __DIR__ . '/api/leave_management.php';
echo "API Path: $api_path<br>";
echo "File exists: " . (file_exists($api_path) ? "✅ YES" : "❌ NO") . "<br>";

echo "<h3>5. Testing Direct Database Insert</h3>";
try {
    // Get first active employee
    $result = $conn->query("SELECT id FROM employees WHERE status = 'active' LIMIT 1");
    if ($result->num_rows > 0) {
        $emp_id = $result->fetch_assoc()['id'];
        
        // Get or create a leave type
        $result = $conn->query("SELECT id FROM leave_types WHERE type_name = 'Test Leave' LIMIT 1");
        if ($result->num_rows > 0) {
            $leave_type_id = $result->fetch_assoc()['id'];
        } else {
            $conn->query("INSERT INTO leave_types (type_name) VALUES ('Test Leave')");
            $leave_type_id = $conn->insert_id;
        }
        
        echo "Employee ID: $emp_id<br>";
        echo "Leave Type ID: $leave_type_id<br>";
        
        // Try to insert a test leave (will be deleted after)
        $test_start = date('Y-m-d');
        $test_end = date('Y-m-d', strtotime('+1 day'));
        
        $stmt = $conn->prepare("INSERT INTO employee_leaves (employee_id, leave_type_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'Test insert', 'pending')");
        $stmt->bind_param("iiss", $emp_id, $leave_type_id, $test_start, $test_end);
        
        if ($stmt->execute()) {
            $test_id = $stmt->insert_id;
            echo "✅ Test insert successful! Leave ID: $test_id<br>";
            
            // Delete the test record
            $conn->query("DELETE FROM employee_leaves WHERE id = $test_id");
            echo "✅ Test record deleted<br>";
        } else {
            echo "❌ Test insert failed: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "❌ No active employees found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

$conn->close();
?>
