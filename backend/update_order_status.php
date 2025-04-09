<?php
session_start();
require_once "db_connection.php";
require_once "check_role.php"; // Include the check_role.php file

header('Content-Type: application/json');

// Check if the user has permission to access Pending Orders
try {
    // Use checkApiRole instead of a simple session check
    checkApiRole('Pending Orders');
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
        exit;
    }
    
    // Retrieve and sanitize form data
    $po_number = isset($_POST['po_number']) ? trim($_POST['po_number']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    // Validate inputs
    if (empty($po_number) || empty($status)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit;
    }
    
    // Update allowed statuses to match your frontend
    $allowedStatuses = ['Active', 'Rejected', 'Pending', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status value.']);
        exit;
    }
    
    // Prepare the SQL statement to update the status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    
    // Bind parameters (s = string)
    $stmt->bind_param("ss", $status, $po_number);
    
    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Database update failed.');
    }
    
    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>