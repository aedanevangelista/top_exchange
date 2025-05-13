<?php
include 'db_connection.php'; // Adjust path as necessary

header('Content-Type: application/json');

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_type = 'Walk In'");
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $next_sequence_number = (int)$row['count'] + 1;
    $stmt->close();
    
    echo json_encode(['success' => true, 'next_sequence_number' => $next_sequence_number]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching Walk-In order count: ' . $e->getMessage()
    ]);
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>