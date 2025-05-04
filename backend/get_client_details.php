<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

// Set content type to JSON
header('Content-Type: application/json');

// Check if username is provided
if (!isset($_GET['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Username is required'
    ]);
    exit;
}

$username = $_GET['username'];

try {
    // Get client account details
    $sql = "SELECT * FROM clients_accounts WHERE username = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $clientData = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $clientData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Client not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch client details: ' . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>