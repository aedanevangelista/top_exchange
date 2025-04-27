<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $username = $_POST['username'];
        $order_date = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        $po_number = $_POST['po_number'];
        $orders = $_POST['orders']; // Keep as JSON string
        $total_amount = $_POST['total_amount'];

        // Get billing and shipping info
        $bill_to = $_POST['bill_to'] ?? '';
        $bill_to_attn = $_POST['bill_to_attn'] ?? '';
        $ship_to = $_POST['ship_to'] ?? '';
        $ship_to_attn = $_POST['ship_to_attn'] ?? '';
        
        $special_instructions = $_POST['special_instructions'] ?? '';

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // Check if the PO number already exists in the database
        $checkStmt = $conn->prepare("SELECT po_number FROM orders WHERE po_number = ?");
        if ($checkStmt === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $checkStmt->bind_param("s", $po_number);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            throw new Exception('Duplicate entry: PO number already exists. Please try again with a new PO number.');
        }
        $checkStmt->close();

        // Insert into orders table with billing and shipping fields
        $insertOrder = $conn->prepare("
            INSERT INTO orders (po_number, username, order_date, delivery_date, orders, total_amount, 
                              bill_to, bill_to_attn, ship_to, ship_to_attn, status, special_instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ");

        if ($insertOrder === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $insertOrder->bind_param("sssssdsssss", $po_number, $username, $order_date, $delivery_date, 
                              $orders, $total_amount, $bill_to, $bill_to_attn, $ship_to, $ship_to_attn, 
                              $special_instructions);

        if ($insertOrder->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Order successfully added!',
                'order_id' => $conn->insert_id
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