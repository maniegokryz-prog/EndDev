<?php
/**
 * STARTUP.PHP - Database Initialization Script
 * 
 * ⚠️ IMPORTANT: Run this file ONCE to create all database tables and schema
 * 
 * This script should only be run when:
 * - Setting up the database for the first time
 * - Recreating the database structure
 * - Adding new tables to the schema
 * 
 * For regular database connections, use: require 'db_connection.php';
 * 
 * To run: Navigate to http://localhost/Face_Recognition_Attendance_System/startup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
set_time_limit(300);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://unpkg.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; connect-src \'self\' https://cdn.jsdelivr.net https://unpkg.com; font-src \'self\' https://cdn.jsdelivr.net;');

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = "localhost";
$username = "attendance_admin"; // Default XAMPP username
$password = "Confirmp@ssword123"; // Default XAMPP password
$dbname = "database_records";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    // echo "Database created successfully or already exists<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

function createTable($conn, $sql, $tableName)
{
    if ($conn->query($sql) === TRUE) {
        // echo "Table '$tableName' created successfully or already exists<br>";
    } else {
        die("Error creating table '$tableName': " . $conn->error);
    }
}

//Employees table
$sql_employees = "CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(255) NOT NULL UNIQUE,
    employee_password VARCHAR(255),
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    last_name VARCHAR(255) NOT NULL,
    suffix VARCHAR(50),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(255),
    roles TEXT,
    department VARCHAR(255),
    position VARCHAR(255),
    hire_date DATE,
    status VARCHAR(50) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    profile_photo VARCHAR(255)
)";
createTable($conn, $sql_employees, "employees");

//Schedules table
$sql_schedules = "CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
createTable($conn, $sql_schedules, "schedules");

//Schedule periods
$sql_schedule_periods = "CREATE TABLE IF NOT EXISTS schedule_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    day_of_week INT NOT NULL,
    period_name VARCHAR(255),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
)";
createTable($conn, $sql_schedule_periods, "schedule_periods");

//Employee schedules
$sql_employee_schedules = "CREATE TABLE IF NOT EXISTS employee_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    schedule_id INT NOT NULL,
    effective_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
)";
createTable($conn, $sql_employee_schedules, "employee_schedules");

//Attendance logs
$sql_attendance_logs = "CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    log_date DATE NOT NULL,
    log_type VARCHAR(50) NOT NULL,
    log_time DATETIME NOT NULL,
    source VARCHAR(50) DEFAULT 'kiosk',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_attendance_logs, "attendance_logs");

//Daily attendance summary
$sql_daily_attendance = "CREATE TABLE IF NOT EXISTS daily_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME,
    break_out TIME DEFAULT NULL,
    break_in TIME DEFAULT NULL,
    time_out TIME,
    scheduled_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    late_minutes INT DEFAULT 0,
    early_departure_minutes INT DEFAULT 0,
    overtime_minutes INT DEFAULT 0,
    break_time_minutes INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'incomplete',
    notes TEXT,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id, attendance_date)
)";
createTable($conn, $sql_daily_attendance, "daily_attendance");

// Holidays table (previously `leave` table)
$sql_holidays = "CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(255) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
createTable($conn, $sql_holidays, "holidays");

// Leave Types table
$sql_leave_types = "CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
createTable($conn, $sql_leave_types, "leave_types");

// Employee Leaves table (for individual leave requests)
$sql_employee_leaves = "CREATE TABLE IF NOT EXISTS employee_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cloud_id INT NULL DEFAULT NULL,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    INDEX (cloud_id)
)";
createTable($conn, $sql_employee_leaves, "employee_leaves");

// Offset Schedule Requests table
$sql_offset_requests = "CREATE TABLE IF NOT EXISTS offset_schedule_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    original_schedule_id INT NOT NULL,
    original_day_of_week INT NULL,
    requested_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (original_schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
)";
createTable($conn, $sql_offset_requests, "offset_schedule_requests");

// CTO Requests table
$sql_cto_requests = "CREATE TABLE IF NOT EXISTS cto_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    requested_date DATE NOT NULL,
    hours_used DECIMAL(5,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_cto_requests, "cto_requests");

// Time Bank Ledger table
$sql_time_bank = "CREATE TABLE IF NOT EXISTS time_bank_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    transaction_type ENUM('earned', 'used', 'expired') NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    source_id INT DEFAULT NULL,
    description TEXT,
    reference_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_time_bank, "time_bank_ledger");

//Admin users table
$sql_admin_users = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'admin',
    last_login DATETIME,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
createTable($conn, $sql_admin_users, "admin_users");

//Employee assignments
$sql_employee_assignments = "CREATE TABLE IF NOT EXISTS employee_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    schedule_period_id INT NOT NULL,
    subject_code VARCHAR(255),
    designate_class VARCHAR(255),
    room_num VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_period_id) REFERENCES schedule_periods(id) ON DELETE CASCADE,
    UNIQUE(employee_id, schedule_period_id)
)";
createTable($conn, $sql_employee_assignments, "employee_assignments");

//Notifications table
$sql_notifications = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    leave_id INT,
    type VARCHAR(50),
    message TEXT,
    link VARCHAR(255),
    target ENUM('admin', 'employee') DEFAULT 'admin',
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (employee_id),
    INDEX (target),
    INDEX (is_read)
)";
createTable($conn, $sql_notifications, "notifications");

//Face embeddings table (MUST be after employees table due to foreign key)
$sql_face_embeddings = "CREATE TABLE IF NOT EXISTS face_embeddings (
    embedding_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    embedding_data BLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_face_embeddings, "face_embeddings");

// System Settings table
$sql_system_settings = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
createTable($conn, $sql_system_settings, "system_settings");

// Schedule Requests table
$sql_schedule_requests = "CREATE TABLE IF NOT EXISTS schedule_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employee_id_string VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    schedule_data TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
createTable($conn, $sql_schedule_requests, "schedule_requests");

// Offset Schedule Requests table
$sql_offset_requests = "CREATE TABLE IF NOT EXISTS offset_schedule_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    original_schedule_id INT NOT NULL,
    requested_date DATE NOT NULL,
    status ENUM('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (original_schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
)";
createTable($conn, $sql_offset_requests, "offset_schedule_requests");

// CTO Requests table
$sql_cto_requests = "CREATE TABLE IF NOT EXISTS cto_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    requested_date DATE NOT NULL,
    hours_used DECIMAL(5,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_cto_requests, "cto_requests");

// Time Bank Ledger table
$sql_time_bank = "CREATE TABLE IF NOT EXISTS time_bank_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    transaction_type ENUM('earned','used','expired') NOT NULL,
    reference_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_time_bank, "time_bank_ledger");


// Insert default settings if they don't exist
$check_setting = $conn->query("SELECT id FROM system_settings WHERE setting_key = 'leave_notice_period_days'");
if ($check_setting->num_rows == 0) {
    $conn->query("INSERT INTO system_settings (setting_key, setting_value, description) VALUES ('leave_notice_period_days', '0', 'Minimum number of days in advance a leave request must be made.')");
}

// Create indexes for better performance (MySQL Community Server compatible)
// Check and create indexes only if they don't exist
$indexes = [
    ['table' => 'employees', 'name' => 'idx_employee_id', 'columns' => 'employee_id'],
    ['table' => 'attendance_logs', 'name' => 'idx_attendance_date', 'columns' => 'log_date'],
    ['table' => 'attendance_logs', 'name' => 'idx_employee_attendance', 'columns' => 'employee_id, log_date'],
    ['table' => 'daily_attendance', 'name' => 'idx_daily_attendance_date', 'columns' => 'employee_id, attendance_date'],
    ['table' => 'employee_assignments', 'name' => 'idx_employee_assignments', 'columns' => 'employee_id, schedule_period_id'],
    ['table' => 'employee_leaves', 'name' => 'idx_employee_leaves_dates', 'columns' => 'employee_id, start_date, end_date']
];

foreach ($indexes as $index) {
    $check_index = "SHOW INDEX FROM {$index['table']} WHERE Key_name = '{$index['name']}'";
    $result = $conn->query($check_index);
    if ($result->num_rows == 0) {
        $create_index = "CREATE INDEX {$index['name']} ON {$index['table']}({$index['columns']})";
        $conn->query($create_index);
    }
}

// Create default admin account if it doesn't exist
$check_admin = "SELECT id FROM admin_users WHERE username = 'admin'";
$admin_exists = $conn->query($check_admin);

if ($admin_exists->num_rows == 0) {
    // Default credentials: username: admin, password: admin123
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO admin_users (username, email, password_hash, role, is_active) 
                     VALUES ('admin', 'admin@system.local', '$default_password', 'admin', 1)";

    if ($conn->query($insert_admin) === TRUE) {
        // echo "Default admin account created successfully (username: admin, password: admin123)<br>";
    } else {
        // echo "Error creating default admin: " . $conn->error . "<br>";
    }
}

// Check if suffix exists in employees
$check_suffix = $conn->query("SHOW COLUMNS FROM employees LIKE 'suffix'");
if ($check_suffix->num_rows == 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN suffix VARCHAR(50) NULL AFTER last_name");
}

// Check if break_out exists in daily_attendance
$check_break_out = $conn->query("SHOW COLUMNS FROM daily_attendance LIKE 'break_out'");
if ($check_break_out->num_rows == 0) {
    $conn->query("ALTER TABLE daily_attendance ADD COLUMN break_out TIME DEFAULT NULL AFTER time_in");
}

// Check if break_in exists in daily_attendance
$check_break_in = $conn->query("SHOW COLUMNS FROM daily_attendance LIKE 'break_in'");
if ($check_break_in->num_rows == 0) {
    $conn->query("ALTER TABLE daily_attendance ADD COLUMN break_in TIME DEFAULT NULL AFTER break_out");
}

// Check if cloud_id exists in employee_leaves
$check_cloud_id = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'cloud_id'");
if ($check_cloud_id->num_rows == 0) {
    $conn->query("ALTER TABLE employee_leaves ADD COLUMN cloud_id INT NULL DEFAULT NULL AFTER id, ADD INDEX idx_cloud_id (cloud_id)");
}

// Check if link exists in notifications
$check_link = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
if ($check_link->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER message");
}

// Check and add sync_status and last_sync to all syncable tables
$tables_to_sync = [
    'employees',
    'schedules',
    'schedule_requests',
    'schedule_periods',
    'employee_schedules',
    'attendance_logs',
    'daily_attendance',
    'holidays',
    'leave_types',
    'employee_leaves',
    'employee_assignments',
    'notifications',
    'offset_schedule_requests',
    'time_bank_ledger'
];

foreach ($tables_to_sync as $table) {
    // Check if sync_status exists
    $check_sync = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
    if ($check_sync->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `sync_status` TINYINT DEFAULT 0");
    }

    // Check if last_sync exists
    $check_last = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'last_sync'");
    if ($check_last->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `last_sync` DATETIME DEFAULT NULL");
    }

    // Add index on sync_status for faster queries
    $index_name = "idx_" . $table . "_sync_status";
    
    $check_idx = $conn->query("SHOW INDEX FROM `$table`");
    $index_exists = false;
    if ($check_idx) {
        while ($row = $check_idx->fetch_assoc()) {
            if ($row['Key_name'] === 'idx_sync_status' || $row['Key_name'] === $index_name) {
                $index_exists = true;
                break;
            }
        }
    }
    
    if (!$index_exists) {
        $conn->query("CREATE INDEX `$index_name` ON `$table`(`sync_status`)");
    }
}

// ============================================================
// PATCH: system_settings sync support in api/sync_endpoint.php
// This ensures Hostinger's sync endpoint accepts system_settings
// ============================================================
$syncEndpointPath = __DIR__ . '/api/sync_endpoint.php';
if (file_exists($syncEndpointPath)) {
    $sc = file_get_contents($syncEndpointPath);
    $scPatched = false;

    // 1. Add system_settings to allowed_tables whitelist
    if (strpos($sc, "'system_settings'") === false && strpos($sc, '"system_settings"') === false) {
        $sc = str_replace("'notifications'\n    ];", "'notifications',\n        'system_settings'\n    ];", $sc);
        $sc = str_replace("'notifications'\r\n    ];", "'notifications',\r\n        'system_settings'\r\n    ];", $sc);
        $scPatched = true;
    }

    // 2. Add 'push' case to switch if missing
    if (strpos($sc, "case 'push':") === false) {
        $sc = preg_replace(
            '/(switch\s*\(\$action\)\s*\{[\r\n]+\s*)(case\s)/m',
            "$1case 'push':\n            handleUpsert(\$conn, \$table, \$data);\n            break;\n\n        $2",
            $sc,
            1
        );
        $scPatched = true;
    }

    // 3. Add handleUpsert function if missing
    if (strpos($sc, 'function handleUpsert') === false) {
        $upsertFunc = "\nfunction handleUpsert(\$conn, \$table, \$data) {\n" .
            "    if (empty(\$data) || !is_array(\$data)) { echo json_encode(['success'=>true]); return; }\n" .
            "    \$cols = array_keys(\$data); \$vals = array_values(\$data);\n" .
            "    \$cs = implode(', ', array_map(function(\$c){ return \"`\$c`\"; }, \$cols));\n" .
            "    \$vs = implode(', ', array_fill(0, count(\$vals), '?'));\n" .
            "    \$us = implode(', ', array_map(function(\$c){ return \"`\$c`=VALUES(`\$c`)\"; }, \$cols));\n" .
            "    \$sql = \"INSERT INTO `\$table` (\$cs) VALUES (\$vs) ON DUPLICATE KEY UPDATE \$us\";\n" .
            "    \$s = \$conn->prepare(\$sql);\n" .
            "    if (!\$s) throw new Exception(\$conn->error);\n" .
            "    \$s->bind_param(str_repeat('s', count(\$vals)), ...\$vals);\n" .
            "    \$s->execute();\n" .
            "    echo json_encode(['success'=>true, 'message'=>'Upserted']);\n" .
            "}\n";
        $sc = preg_replace('/\?>\s*$/', $upsertFunc . "\n?>", $sc);
        $scPatched = true;
    }

    if ($scPatched) {
        file_put_contents($syncEndpointPath, $sc);
    }
}
// ============================================================
// END PATCH
// ============================================================

// Also ensure default settings exist (break_deduction_minutes, grace_period_minutes, deduct_late_time)
$defaults = [
    'break_deduction_minutes' => '60',
    'grace_period_minutes'    => '0',
    'deduct_late_time'        => '1',
];
foreach ($defaults as $key => $val) {
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('$key', '$val')");
}

header('Location: dashboard/dashboard.php');
?>