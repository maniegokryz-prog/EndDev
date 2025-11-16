<?php
/**
 * Fix Employee Passwords Utility Script
 * 
 * This script updates all employees with NULL passwords to have a default password
 * Default password = their employee_id (hashed)
 * 
 * Run this once to fix existing employee records
 */

require '../../db_connection.php';

// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Employee Password Fix Utility</h2>";
echo "<p>This script will set default passwords for employees with NULL passwords.</p>";
echo "<hr>";

try {
    // Find all employees with NULL or empty passwords
    $sql = "SELECT id, employee_id, first_name, last_name, employee_password 
            FROM employees 
            WHERE employee_password IS NULL OR employee_password = ''";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo "<p style='color: green;'>✓ All employees already have passwords set!</p>";
    } else {
        echo "<p>Found " . $result->num_rows . " employees without passwords.</p>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Employee ID</th><th>Name</th><th>Status</th></tr>";
        
        $updated_count = 0;
        $failed_count = 0;
        
        while ($employee = $result->fetch_assoc()) {
            // Default password is the employee_id
            $default_password = $employee['employee_id'];
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            
            // Update the employee record
            $update_sql = "UPDATE employees SET employee_password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('si', $hashed_password, $employee['id']);
            
            if ($stmt->execute()) {
                $full_name = $employee['first_name'] . ' ' . $employee['last_name'];
                echo "<tr>";
                echo "<td>{$employee['id']}</td>";
                echo "<td>{$employee['employee_id']}</td>";
                echo "<td>{$full_name}</td>";
                echo "<td style='color: green;'>✓ Password set (default: {$employee['employee_id']})</td>";
                echo "</tr>";
                $updated_count++;
            } else {
                echo "<tr>";
                echo "<td>{$employee['id']}</td>";
                echo "<td>{$employee['employee_id']}</td>";
                echo "<td>{$employee['first_name']} {$employee['last_name']}</td>";
                echo "<td style='color: red;'>✗ Failed to update</td>";
                echo "</tr>";
                $failed_count++;
            }
        }
        
        echo "</table>";
        echo "<hr>";
        echo "<h3>Summary:</h3>";
        echo "<p style='color: green;'>✓ Successfully updated: $updated_count employees</p>";
        if ($failed_count > 0) {
            echo "<p style='color: red;'>✗ Failed: $failed_count employees</p>";
        }
        echo "<p><strong>Default Password Policy:</strong> Each employee's default password is set to their Employee ID.</p>";
        echo "<p><em>Employees should change their password after first login.</em></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();

// Flush output
ob_end_flush();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Fix Utility</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        table {
            background-color: white;
            width: 100%;
        }
        h2 {
            color: #333;
        }
    </style>
</head>
<body>
    <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ddd;">
        <h3>How to use this script:</h3>
        <ol>
            <li>Run this script by navigating to: <code>http://localhost/EndDev/staffmanagement/processes/fix_employee_passwords.php</code></li>
            <li>It will automatically detect and fix all employees with NULL passwords</li>
            <li>Default password will be set to the employee's ID</li>
            <li>Employees can use their Employee ID as password to login</li>
            <li>After running once, you can delete this file for security</li>
        </ol>
        <p><a href="../../dashboard/dashboard.php" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">Return to Dashboard</a></p>
    </div>
</body>
</html>
