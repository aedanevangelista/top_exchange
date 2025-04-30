<?php
include 'db_connection.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $username = $_POST['username'];
        
        // Get the highest order number for this username
        $stmt = $conn->prepare("
            SELECT po_number 
            FROM orders 
            WHERE username = ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // If exists, increment the number
            $current_number = intval(substr($row['po_number'], strrpos($row['po_number'], '-') + 1));
            $next_number = $current_number + 1;
        } else {
            // If no existing orders, start with 1
            $next_number = 1;
        }
        
        $po_number = $username . "-" . $next_number;
        
        echo json_encode([
            'success' => true,
            'po_number' => $po_number
        ]);
        
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