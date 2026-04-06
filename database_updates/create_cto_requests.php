<?php
error_reporting(E_ALL);
ini_Set('display_errors', 1);

require __DIR__ . '/../db_connection.php';

echo "<h2>Setting up CTO (Time Bank Usage) Tables...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS cto_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    requested_date DATE NOT NULL,
    hours_used DECIMAL(5,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "cto_requests table created or already exists.<br>";
} else {
    echo "Error creating cto_requests table: " . $conn->error . "<br>";
}

echo "Done.";
$conn->close();
?>
