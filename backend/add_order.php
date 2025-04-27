<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Log all incoming data for debugging
        error_log("Order submission data: " . print_r($_POST, true));
        
        // Validate required fields
        $requiredFields = ['username', 'order_date', 'delivery_date', 'po_number', 'orders', 'total_amount'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Get POST data
        $username = $_POST['username'];
        $order_date = $_POST['order_date'];
        $delivery_date = $_POST['delivery_date'];
        $po_number = $_POST['po_number'];
        $orders = $_POST['orders']; // Keep as JSON string
        $total_amount = $_POST['total_amount'];
        $special_instructions = $_POST['special_instructions'] ?? '';
        
        // Validate that orders is valid JSON before proceeding
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format: ' . json_last_error_msg());
        }
        
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
            } else {
                error_log("No client found with username: $username");
            }
        } else {
            throw new Exception('Failed to execute shipping info query: ' . $getShippingInfo->error);
        }
        $getShippingInfo->close();

        // Use ship_to as the delivery address or fall back to alternatives
        $delivery_address = $ship_to;
        if (empty($delivery_address)) {
            $delivery_address = $bill_to;
            if (empty($delivery_address)) {
                $delivery_address = $company_address;
                if (empty($delivery_address)) {
                    $delivery_address = "No address provided";
                    error_log("No delivery address found for user: $username");
                }
            }
        }

        // Check table structure using a safer method
        try {
            // Try the new structure first
            $insertOrder = $conn->prepare("
                INSERT INTO orders (
                    username, order_date, delivery_date, po_number, orders, 
                    total_amount, status, special_instructions,
                    ship_to, ship_to_attn, bill_to, bill_to_attn
                ) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?)
            ");
            
            if ($insertOrder === false) {
                throw new Exception($conn->error);
            }
            
            $insertOrder->bind_param(
                "sssssdsssss", 
                $username, $order_date, $delivery_date, $po_number, 
                $orders, $total_amount, $special_instructions,
                $ship_to, $ship_to_attn, $bill_to, $bill_to_attn
            );
        } catch (Exception $e) {
            // If that fails, try the old structure
            error_log("Failed to use new table structure: " . $e->getMessage());
            error_log("Trying old structure...");
            
            $insertOrder = $conn->prepare("
                INSERT INTO orders (
                    username, order_date, delivery_date, po_number, orders, 
                    total_amount, status, special_instructions, delivery_address
                ) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
            ");
            
            if ($insertOrder === false) {
                throw new Exception('Failed to prepare statement (old structure): ' . $conn->error);
            }
            
            $insertOrder->bind_param(
                "sssssds", 
                $username, $order_date, $delivery_date, $po_number, 
                $orders, $total_amount, $special_instructions, $delivery_address
            );
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
        error_log("Order submission error: " . $e->getMessage());
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