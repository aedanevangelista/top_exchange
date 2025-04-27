<?php
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!isset($_POST['username']) || empty($_POST['username'])) {
            throw new Exception('Username is required');
        }
        
        $username = $_POST['username'];
        
        $stmt = $conn->prepare("
            SELECT ship_to, ship_to_attn, bill_to, bill_to_attn, company, company_address
            FROM clients_accounts 
            WHERE username = ?
        ");
        
        if ($stmt === false) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'ship_to' => $row['ship_to'],
                    'ship_to_attn' => $row['ship_to_attn'],
                    'bill_to' => $row['bill_to'],
                    'bill_to_attn' => $row['bill_to_attn'],
                    'company' => $row['company'],
                    'company_address' => $row['company_address']
                ]);
            } else {
                throw new Exception('Client not found');
            }
        } else {
            throw new Exception('Failed to execute query: ' . $stmt->error);
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