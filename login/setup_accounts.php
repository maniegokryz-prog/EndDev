<?php
require_once '../db_connection.php';

echo "<h2>🔐 Setup Login Accounts</h2>";
echo "<hr>";

// Create a test admin user
echo "<h3>Creating Admin User...</h3>";
$adminUsername = 'admin';
$adminPassword = 'admin123';
$adminEmail = 'admin@system.com';
$adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

// Check if admin exists
$check = $conn->query("SELECT id FROM admin_users WHERE username = '$adminUsername'");
if ($check && $check->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Admin user 'admin' already exists. Skipping...</p>";
} else {
    $sql = "INSERT INTO admin_users (username, email, password_hash, role, is_active, created_at) 
            VALUES ('$adminUsername', '$adminEmail', '$adminPasswordHash', 'Administrator', 1, NOW())";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✅ Created admin user:</p>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> admin</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "<li><strong>Email:</strong> admin@system.com</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create admin user: " . $conn->error . "</p>";
    }
}

// Update existing employees with passwords
echo "<hr><h3>Updating Employee Passwords...</h3>";

$result = $conn->query("SELECT id, employee_id, first_name, last_name, employee_password FROM employees WHERE employee_password IS NULL OR employee_password = '' LIMIT 10");

if ($result && $result->num_rows > 0) {
    echo "<p>Setting default password for employees without passwords...</p>";
    echo "<ul>";
    
    while ($row = $result->fetch_assoc()) {
        // Use employee_id as default password (for testing)
        $defaultPassword = $row['employee_id'];
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        $updateSql = "UPDATE employees SET employee_password = '$passwordHash' WHERE id = " . $row['id'];
        
        if ($conn->query($updateSql)) {
            echo "<li style='color: green;'>✅ <strong>" . $row['employee_id'] . "</strong> (" . $row['first_name'] . " " . $row['last_name'] . ") - Password: <strong>" . $defaultPassword . "</strong></li>";
        } else {
            echo "<li style='color: red;'>❌ Failed to update " . $row['employee_id'] . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: blue;'>ℹ️ All employees already have passwords set.</p>";
}

echo "<hr>";
echo "<h3>✅ Setup Complete!</h3>";
echo "<p><strong>You can now login with:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #4CAF50;'>";
echo "<p><strong>Admin Account:</strong></p>";
echo "<ul>";
echo "<li>Username: <strong>admin</strong></li>";
echo "<li>Password: <strong>admin123</strong></li>";
echo "</ul>";

// Show employee accounts
$empResult = $conn->query("SELECT employee_id, first_name, last_name FROM employees WHERE employee_password IS NOT NULL LIMIT 5");
if ($empResult && $empResult->num_rows > 0) {
    echo "<p><strong>Employee Accounts:</strong></p>";
    echo "<ul>";
    while ($emp = $empResult->fetch_assoc()) {
        echo "<li>ID: <strong>" . $emp['employee_id'] . "</strong> - Password: <strong>" . $emp['employee_id'] . "</strong> (" . $emp['first_name'] . " " . $emp['last_name'] . ")</li>";
    }
    echo "</ul>";
}
echo "</div>";

echo "<p style='margin-top: 20px;'>";
echo "<a href='login.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a> ";
echo "<a href='view_accounts.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View All Accounts</a>";
echo "</p>";

$conn->close();
?>
