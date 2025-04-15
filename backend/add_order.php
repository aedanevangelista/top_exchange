<?php
include 'db_connection.php';
include 'check_raw_materials.php'; // Include our new raw materials functionality

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

        // Check raw material availability
        $materialCheck = checkRawMaterialAvailability($conn, $orders);
        if (!$materialCheck['success']) {
            http_response_code(400); // Bad request
            echo json_encode([
                'success' => false,
                'message' => $materialCheck['message'],
                'insufficientMaterials' => $materialCheck['insufficientMaterials']
            ]);
            exit;
        }

        // Begin transaction to ensure data consistency
        $conn->begin_transaction();

        try {
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
                // Deduct raw materials
                $materialDeduction = deductRawMaterials($conn, $orders);
                if (!$materialDeduction['success']) {
                    throw new Exception($materialDeduction['message']);
                }
                
                // Commit the transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order successfully added!',
                    'order_id' => $conn->insert_id,
                    'materialsDeducted' => $materialDeduction['materials']
                ]);
            } else {
                throw new Exception('Failed to execute statement: ' . $insertOrder->error);
            }

            $insertOrder->close();
        } catch (Exception $e) {
            // Rollback the transaction if any error occurs
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