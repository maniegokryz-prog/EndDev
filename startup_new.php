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
$username = "root"; // Default XAMPP username
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

function createTable($conn, $sql, $tableName) {
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
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
)";
createTable($conn, $sql_employee_leaves, "employee_leaves");

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

//Face embeddings table (MUST be after employees table due to foreign key)
$sql_face_embeddings = "CREATE TABLE IF NOT EXISTS face_embeddings (
    embedding_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    embedding_data BLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
createTable($conn, $sql_face_embeddings, "face_embeddings");

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

header('Location: dashboard/dashboard.php');
// echo "All tables and indexes created successfully.<br>";

// Do not close the connection here if you need to use it in other included files
// $conn->close();
?>