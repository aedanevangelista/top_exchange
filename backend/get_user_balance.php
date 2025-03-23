<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include the database connection
    require_once "db_connection.php";
    
    // Validate required parameter
    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception("Username is required");
    }
    
    // Sanitize input
    $username = $_GET['username'];
    
    // Check if user_balance table exists, if not create it
    $sql = "CREATE TABLE IF NOT EXISTS user_balance (
        username VARCHAR(255) PRIMARY KEY,
        balance DECIMAL(10,2) DEFAULT 0.00,
        last_updated DATETIME
    )";
    $conn->query($sql);
    
    // First check if user has a balance record
    $sql = "SELECT balance FROM user_balance WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User has a record, get the balance
        $row = $result->fetch_assoc();
        $balance = (float)$row['balance'];
    } else {
        // Calculate the balance from scratch
        // Calculate total from all orders
        $sql = "SELECT IFNULL(SUM(o.total_amount), 0) as total_orders
                FROM orders o
                WHERE o.username = ? AND o.status = 'Active'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $totalOrders = (float)$row['total_orders'];
        
        // Calculate total payments
        $sql = "SELECT IFNULL(SUM(amount_paid), 0) as total_paid
                FROM monthly_payments
                WHERE username = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $totalPaid = (float)$row['total_paid'];
        
        // Calculate balance (positive means credit, negative means debt)
        $balance = $totalPaid - $totalOrders;
        
        // Insert into user_balance table
        $sql = "INSERT INTO user_balance (username, balance, last_updated)
                VALUES (?, ?, NOW())";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sd", $username, $balance);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $balance
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>