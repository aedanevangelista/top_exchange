<?php
include 'db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    
    try {
        // Get user's address details
        $stmt = $conn->prepare("SELECT bill_to, bill_to_attn, ship_to, ship_to_attn FROM clients_accounts WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate PO number 
            require_once 'get_next_po_number.php';
            $po_generator = new PONumberGenerator($conn);
            $po_number = $po_generator->getNextPONumber($username);
            
            echo json_encode([
                'success' => true,
                'po_number' => $po_number,
                'bill_to' => $user['bill_to'],
                'bill_to_attn' => $user['bill_to_attn'],
                'ship_to' => $user['ship_to'],
                'ship_to_attn' => $user['ship_to_attn']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request or missing username'
    ]);
}
?>