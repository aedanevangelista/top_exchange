<?php
include "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $formType = $_POST['formType'];
    if ($formType == 'add') {
        $customer_name = trim($_POST['customer_name']);
        $contact_number = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $created_at = date('Y-m-d H:i:s');

        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
        if ($checkStmt === false) {
            error_log("Prepare failed (Check Email): (" . $conn->errno . ") " . $conn->error);
            echo json_encode(['success' => false, 'reload' => false, 'message' => 'Database error occurred.']);
            exit;
        }
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'reload' => false, 'message' => 'Customer with this email already exists.']);
            $checkStmt->close();
            exit;
        }
        $checkStmt->close();

        // Insert new customer
        $stmt = $conn->prepare("INSERT INTO customers (customer_name, contact_number, email, address, created_at) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("Prepare failed (Insert Customer): (" . $conn->errno . ") " . $conn->error);
            echo json_encode(['success' => false, 'reload' => false, 'message' => 'Database error occurred.']);
            exit;
        }
        $stmt->bind_param("sssss", $customer_name, $contact_number, $email, $address, $created_at);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
            error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
            echo json_encode(['success' => false, 'reload' => false, 'message' => 'Database error occurred.']);
        }
        $stmt->close();
    }
    exit;
}
?>