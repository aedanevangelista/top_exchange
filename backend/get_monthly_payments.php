<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_GET['username']) || !isset($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_GET['username']);
$year = (int)$_GET['year'];

try {
    // Get monthly payments
    $sql = "SELECT 
                mp.month, 
                mp.total_amount, 
                mp.payment_status,
                mp.remaining_balance,
                mp.proof_image
            FROM monthly_payments mp
            WHERE mp.username = ? AND mp.year = ?
            ORDER BY mp.month";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $payments]);
    
} catch (Exception $e) {
    error_log("Error getting monthly payments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>