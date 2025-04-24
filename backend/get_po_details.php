<?php
include 'db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['po_number'])) {
    try {
        $po_number = $_GET['po_number'];
        
        // Prepare and execute the query to get order details
        $stmt = $conn->prepare("
            SELECT o.po_number, o.username, o.company, o.order_date, o.delivery_date, 
                   o.delivery_address, o.orders, o.total_amount, o.status
            FROM orders o
            WHERE o.po_number = ?
        ");
        
        if ($stmt === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $order = $result->fetch_assoc();
            
            // Return the order details as JSON
            echo json_encode([
                'success' => true,
                'order' => $order
            ]);
        } else {
            throw new Exception('Order not found');
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Missing PO number.'
    ]);
}
?>