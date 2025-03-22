<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Authentication required");
    }

    // Include database connection
    require_once "db_connection.php";
    
    // Validate required parameters
    if (!isset($_GET['username']) || !isset($_GET['month']) || !isset($_GET['year'])) {
        throw new Exception("Missing required parameters");
    }
    
    // Sanitize inputs
    $username = $_GET['username'];
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    
    // Query to get payment history
    $sql = "SELECT mp.payment_date, mp.amount_paid, mp.payment_status, mp.proof_of_payment, mp.payment_notes 
            FROM monthly_payments mp
            WHERE mp.username = ? AND mp.month = ? AND mp.year = ?
            ORDER BY mp.payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format payment date
            $row['payment_date'] = date('Y-m-d H:i:s', strtotime($row['payment_date']));
            $payments[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>