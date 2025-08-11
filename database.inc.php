<?php
// Database connection details
$host = 'localhost';
$dbname = 'birzeit_flat_rent';
$username = 'root';
$password = ''; // اتركها فاضية

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Log error message to a file instead of displaying it
    error_log("Connection failed: " . $e->getMessage(), 0);
    die("Database connection failed. Please try again later.");
}
?>
