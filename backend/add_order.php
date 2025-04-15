<?php
include 'db_connection.php';
include 'deduct_raw_materials.php';

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

        // Begin transaction
        $conn->begin_transaction();

        try {
            // First deduct the raw materials
            $deductionResult = deductRawMaterials($decoded_orders, $conn);
            
            if (!$deductionResult['success']) {
                // If deduction fails, rollback and return the error
                $conn->rollback();
                throw new Exception($deductionResult['message']);
            }

            // If deduction is successful, proceed with order insertion
            $insertOrder = $conn->prepare("
                INSERT INTO orders (username, order_date, delivery_date, delivery_address, po_number, orders, total_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            if ($insertOrder === false) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            $insertOrder->bind_param("ssssssd", $username, $order_date, $delivery_date, $delivery_address, $po_number, $orders, $total_amount);

            if (!$insertOrder->execute()) {
                throw new Exception('Failed to execute statement: ' . $insertOrder->error);
            }

            $orderId = $conn->insert_id;
            $insertOrder->close();
            
            // Commit the transaction
            $conn->commit();

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Order successfully added and raw materials deducted!',
                'order_id' => $orderId
            ]);

        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            throw $e;
        }

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