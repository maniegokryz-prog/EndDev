<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">🔐 Login System Test Panel</h4>
            </div>
            <div class="card-body">
                
                <h5 class="border-bottom pb-2">1. Current Session</h5>
                <div class="mb-4">
                    <?php
                    if (!isset($_SESSION)) {
                        session_start();
                    }
                    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
                        echo '<div class="alert alert-success">';
                        echo '<strong>✓ LOGGED IN</strong><br>';
                        echo 'User Type: <strong>' . ($_SESSION['user_type'] ?? 'unknown') . '</strong><br>';
                        
                        if ($_SESSION['user_type'] === 'admin') {
                            echo 'Username: ' . ($_SESSION['username'] ?? 'N/A') . '<br>';
                            echo 'Email: ' . ($_SESSION['email'] ?? 'N/A') . '<br>';
                            echo 'Role: ' . ($_SESSION['role'] ?? 'N/A') . '<br>';
                        } else {
                            echo 'Employee ID: ' . ($_SESSION['employee_id'] ?? 'N/A') . '<br>';
                            echo 'Name: ' . ($_SESSION['employee_name'] ?? 'N/A') . '<br>';
                            echo 'Department: ' . ($_SESSION['department'] ?? 'N/A') . '<br>';
                        }
                        
                        echo '</div>';
                        echo '<a href="logout.php" class="btn btn-danger">Logout</a>';
                    } else {
                        echo '<div class="alert alert-warning">Not logged in</div>';
                        echo '<a href="login.php" class="btn btn-success">Go to Login</a>';
                    }
                    ?>
                </div>
                
                <h5 class="border-bottom pb-2">2. Database Accounts</h5>
                <div class="mb-4">
                    <?php
                    require_once '../db_connection.php';
                    
                    echo '<h6>Admin Users:</h6>';
                    $result = $conn->query("SELECT username, email, is_active FROM admin_users LIMIT 5");
                    if ($result && $result->num_rows > 0) {
                        echo '<table class="table table-sm">';
                        echo '<tr><th>Username</th><th>Email</th><th>Status</th></tr>';
                        while ($row = $result->fetch_assoc()) {
                            $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                            echo "<tr><td>{$row['username']}</td><td>{$row['email']}</td><td>$status</td></tr>";
                        }
                        echo '</table>';
                    } else {
                        echo '<p class="text-muted">No admin users found</p>';
                    }
                    
                    echo '<h6 class="mt-3">Employees:</h6>';
                    $result = $conn->query("SELECT employee_id, first_name, last_name, status FROM employees WHERE employee_password IS NOT NULL AND employee_password != '' LIMIT 5");
                    if ($result && $result->num_rows > 0) {
                        echo '<table class="table table-sm">';
                        echo '<tr><th>Employee ID</th><th>Name</th><th>Status</th></tr>';
                        while ($row = $result->fetch_assoc()) {
                            $name = $row['first_name'] . ' ' . $row['last_name'];
                            $status = strtolower($row['status']) === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                            echo "<tr><td>{$row['employee_id']}</td><td>$name</td><td>$status</td></tr>";
                        }
                        echo '</table>';
                    } else {
                        echo '<p class="text-muted">No employees with passwords found</p>';
                    }
                    
                    $conn->close();
                    ?>
                </div>
                
                <h5 class="border-bottom pb-2">3. Backend Files</h5>
                <div class="mb-4">
                    <?php
                    $files = [
                        'authenticate.php' => 'Main login handler',
                        'verify_account.php' => 'Step 1: Verify account',
                        'verify_otp.php' => 'Step 2: Verify OTP',
                        'reset_password.php' => 'Step 3: Reset password',
                        'auth_handler.js' => 'Frontend JavaScript',
                        'session_check.php' => 'Session protection',
                        'logout.php' => 'Logout handler'
                    ];
                    
                    echo '<ul class="list-group">';
                    foreach ($files as $file => $desc) {
                        $exists = file_exists($file);
                        $class = $exists ? 'success' : 'danger';
                        $icon = $exists ? '✓' : '✗';
                        echo "<li class='list-group-item list-group-item-$class'>$icon $file <small class='text-muted'>($desc)</small></li>";
                    }
                    echo '</ul>';
                    ?>
                </div>
                
                <h5 class="border-bottom pb-2">4. Test Instructions</h5>
                <div class="alert alert-info">
                    <strong>To test the system:</strong>
                    <ol class="mb-0">
                        <li>Click "View Accounts" below to see existing database accounts</li>
                        <li>Use those credentials to test login at <a href="login.php">login.php</a></li>
                        <li>Test forgot password flow with existing account details</li>
                    </ol>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="view_accounts.php" class="btn btn-primary" target="_blank">View Accounts</a>
                    <a href="login.php" class="btn btn-success">Login Page</a>
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
