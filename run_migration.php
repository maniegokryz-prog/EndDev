<?php
/**
 * Quick migration runner with output
 */
require 'db_connection.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📋 Leave Attachment Migration</h2>
        
        <?php
        echo "<p class='info'>Checking database structure...</p>";
        
        // Check if column already exists
        $check_sql = "SHOW COLUMNS FROM employee_leaves LIKE 'attachment'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows > 0) {
            echo "<p class='success'>✓ Column 'attachment' already exists!</p>";
            echo "<p>No migration needed. You're all set!</p>";
        } else {
            echo "<p class='info'>Adding 'attachment' column to employee_leaves table...</p>";
            
            // Add the column
            $alter_sql = "ALTER TABLE employee_leaves 
                          ADD COLUMN attachment VARCHAR(255) NULL 
                          AFTER reason";
            
            if ($conn->query($alter_sql) === TRUE) {
                echo "<p class='success'>✓ Migration completed successfully!</p>";
                echo "<p>The <code>attachment</code> column has been added to the database.</p>";
                echo "<p>You can now submit leave requests with file attachments.</p>";
            } else {
                echo "<p class='error'>✗ Migration failed!</p>";
                echo "<p class='error'>Error: " . $conn->error . "</p>";
            }
        }
        
        $conn->close();
        ?>
        
        <hr style="margin: 20px 0;">
        <p><a href="staffmanagement/staffinfo.php" style="color: #4CAF50; text-decoration: none; font-weight: bold;">← Back to Staff Management</a></p>
    </div>
</body>
</html>
