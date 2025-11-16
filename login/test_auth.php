<?php
// Test script to check what errors auth.php is producing
ob_start();

// Simulate a login request
$_GET['action'] = 'login';
$_POST['employee_id'] = 'admin';
$_POST['password'] = 'admin123';

include 'auth.php';

$output = ob_get_clean();

// Check if output is valid JSON
$json = json_decode($output);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "ERROR - Output is not valid JSON:\n";
    echo "JSON Error: " . json_last_error_msg() . "\n\n";
    echo "Raw output:\n";
    echo $output;
} else {
    echo "SUCCESS - Valid JSON output:\n";
    print_r($json);
}
?>
