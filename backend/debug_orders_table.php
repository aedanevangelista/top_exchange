<?php
include 'db_connection.php';

header('Content-Type: application/json');

try {
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Check if orders table exists
    $result = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($result->num_rows == 0) {
        throw new Exception("Orders table does not exist");
    }
    
    // Check orders table structure
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM orders");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    } else {
        throw new Exception("Failed to get column information: " . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'columns' => $columns
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>