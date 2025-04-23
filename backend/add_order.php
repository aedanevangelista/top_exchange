<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $username = $_POST['username'];
        $order_date = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        $delivery_address = $_POST['delivery_address']; // New field for delivery address
        $po_number = $_POST['po_number'];
        $orders = $_POST['orders']; // Keep as JSON string
        $total_amount = $_POST['total_amount'];

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // Set maximum quantity limit per product
        $MAX_QUANTITY = 100;

        // Check each product's quantity against the maximum limit
        foreach ($decoded_orders as $item) {
            if ($item['quantity'] > $MAX_QUANTITY) {
                throw new Exception('Product "' . ($item['item_description'] ?? $item['product_name']) . '" exceeds maximum allowed quantity of ' . $MAX_QUANTITY);
            }
        }

        // Insert into orders table (now including delivery_address)
        $insertOrder = $conn->prepare("
            INSERT INTO orders (username, order_date, delivery_date, delivery_address, po_number, orders, total_amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");

        if ($insertOrder === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $insertOrder->bind_param("ssssssd", $username, $order_date, $delivery_date, $delivery_address, $po_number, $orders, $total_amount);

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