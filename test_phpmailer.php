<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "Attempting to load PHPMailer...\n";

try {
    if (!class_exists(PHPMailer::class)) {
        throw new Exception("PHPMailer class does not exist!");
    }
    
    $mail = new PHPMailer(true);
    echo "SUCCESS: PHPMailer loaded and instantiated.\n";
    echo "Version: " . PHPMailer::VERSION . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
