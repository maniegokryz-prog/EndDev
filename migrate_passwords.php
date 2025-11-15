<?php
/**
 * Password Migration Script
 * Run this once to hash existing plain-text passwords
 */

require 'db_connection.php';

echo "<h2>Password Migration Script</h2>";

// Get all employees with passwords
$sql = "SELECT id, employee_id, employee_password FROM employees WHERE employee_password IS NOT NULL";
$result = $conn->query($sql);

$updated = 0;
$skipped = 0;

while ($row = $result->fetch_assoc()) {
    $password = $row['employee_password'];
    
    // Check if password is already hashed (bcrypt hashes start with $2y$)
    if (substr($password, 0, 4) === '$2y$' || substr($password, 0, 4) === '$2a$') {
        echo "Skipping {$row['employee_id']} - already hashed<br>";
        $skipped++;
        continue;
    }
    
    // Hash the password
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Update database
    $update_sql = "UPDATE employees SET employee_password = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $hashed, $row['id']);
    
    if ($stmt->execute()) {
        echo "Updated {$row['employee_id']} - password hashed<br>";
        $updated++;
    } else {
        echo "Error updating {$row['employee_id']}<br>";
    }
}

echo "<br><strong>Migration Complete!</strong><br>";
echo "Updated: $updated passwords<br>";
echo "Skipped: $skipped (already hashed)<br>";

$conn->close();
?>
