<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $servername = "localhost";
    $username = "u701062148_XXXXX"; // Replace XXXXX with your Hostinger database username
    $password = "YOUR_HOSTINGER_PASSWORD"; // Replace with your Hostinger database password
    $dbname = "u701062148_top_exchange"; // Your Hostinger database name

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    
    // Only return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection error']);
        exit;
    } else {
        // For regular page loads, show error message
        die("Database connection failed. Please try again later.");
    }
}
?>