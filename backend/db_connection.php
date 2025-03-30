<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Changed from 0 to 1 temporarily

try {
    $servername = "localhost";
    $username = "u701062148_top_exchange";
    $password = "YOUR_HOSTINGER_PASSWORD"; // Make sure this is your actual Hostinger password
    $dbname = "u701062148_top_exchange";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error . " (Error No: " . $conn->connect_errno . ")");
    }

    // Set charset
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    
    // Show the actual error message temporarily
    die("Database connection failed: " . $e->getMessage());
}
?>