<?php
// db_connection.php

$host = '151.106.122.5'; // Your database host
$dbname = 'u701062148_top_exchange'; // Your database name
$username = 'u701062148_top_exchange'; // Your database username
$password = 'Aedanpogi123'; // Updated with the correct password you provided

try {
    // Using mysqli instead of PDO for consistency with other files
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset for proper encoding
    if (!$conn->set_charset("utf8mb4")) {
        die("Error setting charset: " . $conn->error);
    }
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>