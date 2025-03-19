<?php
// Set JSON header first
header('Content-Type: application/json');

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once "../db_connection.php";

    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }

    $username = $_GET['username'];
    $year = $_GET['year'] ?? date('Y');

    // Validate the connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $sql = "SELECT month, total_amount, payment_status 
            FROM monthly_payments 
            WHERE username = ? AND year = ?";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];
    while ($row = $result->fetch_assoc()) {
        // Convert numerical values to ensure proper JSON encoding
        $row['total_amount'] = floatval($row['total_amount']);
        $row['month'] = intval($row['month']);
        $payments[] = $row;
    }

    echo json_encode($payments);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while fetching the data',
        'debug' => $e->getMessage() // Remove this line in production
    ]);
}