<?php
include "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'delete') {
    header('Content-Type: application/json');

    $customer_id = intval($_POST['customer_id']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
        exit;
    }
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete customer.']);
    }
    $stmt->close();
    exit;
}
?>