<?php
/**
 * DB CONNECTION TESTER
 * Upload this to IONOS as: /api/test_db.php
 * Access it via browser: http://bpcfaceid.com/api/test_db.php
 */

header('Content-Type: text/plain');

$user = "dbu58088";
$pass = "Confirmp@ssword123";
$name = "dbs14970485";

$hosts_to_test = [
    'localhost',
    '127.0.0.1',
    'db5016556637.hosting-data.io' // Example guess, unlikely to work but shows pattern
];

echo "=== IONOS DATABASE CONNECTION TEST ===\n";
echo "User: $user\n";
echo "DB: $name\n\n";

foreach ($hosts_to_test as $host) {
    echo "Testing Host: '$host' ... ";
    try {
        $conn = new mysqli($host, $user, $pass, $name);
        
        if ($conn->connect_error) {
            echo "FAILED.\nError: " . $conn->connect_error . "\n";
            // Check for socket error specifically
            if (strpos($conn->connect_error, 'No such file or directory') !== false) {
                echo "-> Hint: 'localhost' usually requires a Unix socket. IONOS likely uses a specific hostname (e.g., db123.hosting-data.io).\n";
            }
            if (strpos($conn->connect_error, 'Connection refused') !== false) {
                 echo "-> Hint: TCP connection refused. Database is not listening on this IP.\n";
            }
        } else {
            echo "SUCCESS! ✅\n";
            echo "Server Info: " . $conn->server_info . "\n";
            echo "Host Info: " . $conn->host_info . "\n";
            $conn->close();
            echo "\n>>> USE THIS HOST ($host) IN YOUR sync_endpoint.php <<<\n";
            break;
        }
    } catch (Exception $e) {
        echo "EXCEPTION.\nMessage: " . $e->getMessage() . "\n";
    }
    echo "----------------------------------------\n";
}

echo "\n\nIf all failed, please log in to your IONOS Hosting Control Panel:\n";
echo "1. Go to Databases\n";
echo "2. Find database '$name'\n";
echo "3. Look for 'Host' or 'Server' (usually looks like db12345.hosting-data.io)\n";
echo "4. Update that host in your sync_endpoint.php file.\n";
?>
