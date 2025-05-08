<?php
session_start();
include "db_connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

try {
    // Check for any unsent notifications in the last 24 hours
    $stmt = $conn->prepare("SELECT COUNT(*) as unsent FROM email_notifications 
                           WHERE sent_status = 0 AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'unsent_notifications' => $data['unsent'] ?? 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>