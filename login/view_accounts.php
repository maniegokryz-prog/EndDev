<?php
require_once '../db_connection.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Database Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>📊 Database Accounts</h2>
        <p class="text-muted">Use these credentials to test the login system</p>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Admin Users</h5>
            </div>
            <div class="card-body">
                <?php
                $result = $conn->query("SELECT username, email, is_active, password_hash FROM admin_users");
                
                if ($result && $result->num_rows > 0) {
                    echo '<table class="table table-striped">';
                    echo '<thead><tr><th>Username</th><th>Email</th><th>Password Status</th><th>Active</th></tr></thead><tbody>';
                    
                    while ($row = $result->fetch_assoc()) {
                        $pass = $row['password_hash'] ?? '';
                        if (empty($pass)) {
                            $status = '<span class="badge bg-danger">NO PASSWORD</span>';
                        } elseif (substr($pass, 0, 4) === '$2y$' || substr($pass, 0, 4) === '$2a$') {
                            $status = '<span class="badge bg-success">HASHED</span>';
                        } else {
                            $status = '<span class="badge bg-warning">PLAIN: ' . htmlspecialchars(substr($pass, 0, 15)) . '...</span>';
                        }
                        
                        $active = $row['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                        
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($row['username']) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '<td>' . $active . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '<p class="text-success"><strong>Total:</strong> ' . $result->num_rows . ' admin users</p>';
                } else {
                    echo '<p class="text-warning">No admin users found</p>';
                }
                ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Employees</h5>
            </div>
            <div class="card-body">
                <?php
                $result = $conn->query("SELECT employee_id, first_name, last_name, email, status, employee_password FROM employees LIMIT 20");
                
                if ($result && $result->num_rows > 0) {
                    echo '<table class="table table-striped">';
                    echo '<thead><tr><th>Employee ID</th><th>Name</th><th>Email</th><th>Password Status</th><th>Status</th></tr></thead><tbody>';
                    
                    while ($row = $result->fetch_assoc()) {
                        $pass = $row['employee_password'] ?? '';
                        if (empty($pass)) {
                            $status = '<span class="badge bg-danger">NO PASSWORD</span>';
                        } elseif (substr($pass, 0, 4) === '$2y$' || substr($pass, 0, 4) === '$2a$') {
                            $status = '<span class="badge bg-success">HASHED</span>';
                        } else {
                            $status = '<span class="badge bg-warning">PLAIN: ' . htmlspecialchars(substr($pass, 0, 15)) . '...</span>';
                        }
                        
                        $activeStatus = strtolower($row['status']) === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                        
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($row['employee_id']) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '<td>' . $activeStatus . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '<p class="text-success"><strong>Showing:</strong> First 20 employees with passwords</p>';
                } else {
                    echo '<p class="text-warning">No employees found</p>';
                }
                
                $conn->close();
                ?>
            </div>
        </div>
        
        <div class="alert alert-info">
            <strong>How to use:</strong>
            <ul class="mb-0">
                <li><strong>HASHED</strong> passwords: Login works, secure ✓</li>
                <li><strong>PLAIN</strong> passwords: Login works, but password is shown here (not secure)</li>
                <li><strong>NO PASSWORD</strong>: Cannot login, need to set password first</li>
            </ul>
        </div>
        
        <a href="login.php" class="btn btn-primary">← Back to Login</a>
        <a href="test.php" class="btn btn-secondary">Test Panel</a>
    </div>
</body>
</html>
