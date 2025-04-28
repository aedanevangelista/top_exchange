<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $username = $_POST['username'];
        $order_date = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        
        // Get the new fields instead of delivery_address
        $bill_to = $_POST['bill_to'] ?? '';
        $bill_to_attn = $_POST['bill_to_attn'] ?? '';
        $ship_to = $_POST['ship_to']; // This replaces delivery_address
        $ship_to_attn = $_POST['ship_to_attn'] ?? '';
        
        $po_number = $_POST['po_number'];
        
        // Check if the po_number already exists to prevent duplicates
        $checkPO = $conn->prepare("SELECT COUNT(*) FROM orders WHERE po_number = ?");
        if ($checkPO === false) {
            throw new Exception('Failed to prepare check statement: ' . $conn->error);
        }
        
        $checkPO->bind_param("s", $po_number);
        $checkPO->execute();
        $checkPO->bind_result($poExists);
        $checkPO->fetch();
        $checkPO->close();
        
        if ($poExists > 0) {
            throw new Exception('PO Number already exists. Please try again with a different PO number.');
        }
        
        $orders = $_POST['orders']; // Keep as JSON string
        $total_amount = $_POST['total_amount'];
        $special_instructions = $_POST['special_instructions'] ?? '';

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // Insert into orders table with updated column names
        $insertOrder = $conn->prepare("
            INSERT INTO orders (username, order_date, delivery_date, bill_to, bill_to_attn, ship_to, ship_to_attn, po_number, orders, total_amount, status, special_instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ");

        if ($insertOrder === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $insertOrder->bind_param("ssssssssdss", 
            $username, 
            $order_date, 
            $delivery_date, 
            $bill_to, 
            $bill_to_attn, 
            $ship_to, 
            $ship_to_attn, 
            $po_number, 
            $orders, 
            $total_amount, 
            $special_instructions
        );

        if ($insertOrder->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Order successfully added!',
                'order_id' => $conn->insert_id,
                'po_number' => $po_number
            ]);
        } else {
            throw new Exception('Failed to execute statement: ' . $insertOrder->error);
        }

        $insertOrder->close();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>