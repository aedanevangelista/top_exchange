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
        $special_instructions = $_POST['special_instructions'] ?? '';
        
        // Get shipping information from clients_accounts
        $ship_to = null;
        $ship_to_attn = null;
        $bill_to = null;
        $bill_to_attn = null;
        $company_address = null;
        
        $getShippingInfo = $conn->prepare("
            SELECT ship_to, ship_to_attn, bill_to, bill_to_attn, company_address
            FROM clients_accounts 
            WHERE username = ?
        ");
        
        if ($getShippingInfo === false) {
            throw new Exception('Failed to prepare shipping info statement: ' . $conn->error);
        }
        
        $getShippingInfo->bind_param("s", $username);
        
        if ($getShippingInfo->execute()) {
            $result = $getShippingInfo->get_result();
            if ($row = $result->fetch_assoc()) {
                $ship_to = $row['ship_to'];
                $ship_to_attn = $row['ship_to_attn'];
                $bill_to = $row['bill_to'];
                $bill_to_attn = $row['bill_to_attn'];
                $company_address = $row['company_address'];
            }
        }
        $getShippingInfo->close();

        // Use ship_to as the delivery address or fall back to alternatives if it's null
        $delivery_address = $ship_to;
        if (empty($delivery_address)) {
            // If ship_to is empty, use bill_to as fallback
            $delivery_address = $bill_to;
            if (empty($delivery_address)) {
                // If bill_to is also empty, use company_address as the final fallback
                $delivery_address = $company_address;
            }
        }
        
        // Check if we have a delivery address
        if (empty($delivery_address)) {
            // Log the error for debugging
            error_log("No delivery address found for user: $username");
            $delivery_address = "No address provided"; // Default value as a last resort
        }

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // First, check if the orders table has the necessary columns
        $checkColumnsQuery = "SHOW COLUMNS FROM orders LIKE 'ship_to'";
        $checkResult = $conn->query($checkColumnsQuery);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Table has been updated with the new columns, use them
            $insertOrder = $conn->prepare("
                INSERT INTO orders (
                    username, order_date, delivery_date, po_number, orders, 
                    total_amount, status, special_instructions, delivery_address,
                    ship_to, ship_to_attn, bill_to, bill_to_attn
                ) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?)
            ");

            $insertOrder->bind_param(
                "sssssdssssss", 
                $username, $order_date, $delivery_date, $po_number, 
                $orders, $total_amount, $special_instructions, $delivery_address,
                $ship_to, $ship_to_attn, $bill_to, $bill_to_attn
            );
        } else {
            // The table hasn't been updated yet, use the old structure
            $insertOrder = $conn->prepare("
                INSERT INTO orders (
                    username, order_date, delivery_date, delivery_address, po_number, 
                    orders, total_amount, status, special_instructions
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");

            $insertOrder->bind_param(
                "ssssssds", 
                $username, $order_date, $delivery_date, $delivery_address, $po_number, 
                $orders, $total_amount, $special_instructions
            );
        }

        if ($insertOrder === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

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