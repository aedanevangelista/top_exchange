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
        $bill_to = $_POST['bill_to'];
        $bill_to_attn = $_POST['bill_to_attn'];
        $ship_to = $_POST['ship_to'];  // This replaces delivery_address
        $ship_to_attn = $_POST['ship_to_attn'];
        
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
            $stmt = $conn->prepare("SELECT bill_to, bill_to_attn, ship_to, ship_to_attn FROM clients_accounts WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (empty($bill_to)) $bill_to = $user['bill_to'];
                if (empty($bill_to_attn)) $bill_to_attn = $user['bill_to_attn'];
                if (empty($ship_to)) $ship_to = $user['ship_to'];
                if (empty($ship_to_attn)) $ship_to_attn = $user['ship_to_attn'];
            }
            
            $stmt->close();
        }

        // Insert into orders table with updated column names
        $sql = "INSERT INTO orders (username, order_date, delivery_date, bill_to, bill_to_attn, 
                ship_to, ship_to_attn, po_number, orders, total_amount, status, special_instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssssdss", 
            $username, $order_date, $delivery_date, 
            $bill_to, $bill_to_attn, $ship_to, $ship_to_attn,
            $po_number, $orders, $total_amount, $special_instructions
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Order successfully added!',
                'order_id' => $conn->insert_id
            ]);
        } else {
            throw new Exception("Error executing statement: " . $stmt->error);
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
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>