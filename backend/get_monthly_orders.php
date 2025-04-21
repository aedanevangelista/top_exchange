<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include the database connection file
    require_once "db_connection.php";

    // Validate input
    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }
    
    if (!isset($_GET['month']) || !is_numeric($_GET['month'])) {
        throw new Exception('Valid month is required');
    }
    
    if (!isset($_GET['year']) || !is_numeric($_GET['year'])) {
        throw new Exception('Valid year is required');
    }
    
    // Sanitize inputs
    $username = $_GET['username'];
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    
    // Get orders for the specified month
    $sql = "SELECT 
                po_number,
                order_date,
                delivery_date,
                delivery_address,
                orders,
                total_amount
            FROM orders 
            WHERE username = ? 
            AND MONTH(order_date) = ? 
            AND YEAR(order_date) = ?
            AND status = 'Completed'
            ORDER BY order_date DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'po_number' => $row['po_number'],
            'order_date' => $row['order_date'],
            'delivery_date' => $row['delivery_date'],
            'delivery_address' => $row['delivery_address'],
            'orders' => $row['orders'],
            'total_amount' => floatval($row['total_amount'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>