<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $username = $_POST['username'];
        $order_date = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        
        // Get the billing and shipping data directly from the form
        $bill_to = $_POST['bill_to'] ?? '';
        $bill_to_attn = $_POST['bill_to_attn'] ?? '';
        $ship_to = $_POST['ship_to'] ?? '';  // This replaces delivery_address
        $ship_to_attn = $_POST['ship_to_attn'] ?? '';
        
        $po_number = $_POST['po_number'];
        $orders = $_POST['orders']; // Keep as JSON string
        $total_amount = $_POST['total_amount'];
        $special_instructions = $_POST['special_instructions'] ?? '';

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // If ship_to is empty, get the user's ship_to from the database
        if (empty($ship_to)) {
            $stmt = $conn->prepare("SELECT ship_to, bill_to, bill_to_attn, ship_to_attn FROM clients_accounts WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($db_ship_to, $db_bill_to, $db_bill_to_attn, $db_ship_to_attn);
            if ($stmt->fetch()) {
                $ship_to = $db_ship_to;
                
                // If other fields are also empty, use database values
                if (empty($bill_to)) $bill_to = $db_bill_to;
                if (empty($bill_to_attn)) $bill_to_attn = $db_bill_to_attn;
                if (empty($ship_to_attn)) $ship_to_attn = $db_ship_to_attn;
            }
            $stmt->close();
        }

        // Insert into orders table with updated column names
        $insertOrder = $conn->prepare("
            INSERT INTO orders (username, order_date, delivery_date, bill_to, bill_to_attn, ship_to, ship_to_attn, po_number, orders, total_amount, status, special_instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
        ");

        if ($insertOrder === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $insertOrder->bind_param("ssssssssdss", $username, $order_date, $delivery_date, $bill_to, $bill_to_attn, $ship_to, $ship_to_attn, $po_number, $orders, $total_amount, $special_instructions);

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