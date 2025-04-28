<?php
include 'db_connection.php';
include 'get_next_po_number.php'; // Include this to generate PO number

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['username']) || empty($_POST['username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Username is required'
        ]);
        exit;
    }
    
    $username = $_POST['username'];
    
    try {
        // Get the next PO number for this user
        $po_generator = new PONumberGenerator($conn);
        $po_number = $po_generator->getNextPONumber($username);
        
        // Get the user's details including all address fields
        $stmt = $conn->prepare("SELECT company, company_address, bill_to, bill_to_attn, ship_to, ship_to_attn 
                               FROM clients_accounts 
                               WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'po_number' => $po_number,
                'company' => $user_data['company'],
                'company_address' => $user_data['company_address'],
                'bill_to' => $user_data['bill_to'],
                'bill_to_attn' => $user_data['bill_to_attn'],
                'ship_to' => $user_data['ship_to'],
                'ship_to_attn' => $user_data['ship_to_attn']
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
        'message' => 'Invalid request method'
    ]);
}
?>