<?php
include "db_connection.php";

// Enable error logging
ini_set('log_errors', 'On');
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    try {
        $formType = $_POST['formType'];
        if ($formType == 'add') {
            $customer_name = trim($_POST['customer_name']);
            $contact_number = trim($_POST['contact_number']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            $created_at = date('Y-m-d H:i:s');

            $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
            if ($checkStmt === false) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
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

            $stmt = $conn->prepare("INSERT INTO customers (customer_name, contact_number, email, address, created_at) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("sssss", $customer_name, $contact_number, $email, $address, $created_at);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'reload' => true]);
            } else {
                echo json_encode(['success' => false, 'reload' => false, 'message' => 'Execute failed: (' . $stmt->errno . ') ' . $stmt->error]);
            }
            $stmt->close();
        } elseif ($formType == 'edit') {
            $customer_id = intval($_POST['customer_id']);
            $customer_name = trim($_POST['customer_name']);
            $contact_number = trim($_POST['contact_number']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);

            $stmt = $conn->prepare("UPDATE customers SET customer_name = ?, contact_number = ?, email = ?, address = ? WHERE customer_id = ?");
            if ($stmt === false) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("ssssi", $customer_name, $contact_number, $email, $address, $customer_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'reload' => true]);
            } else {
                echo json_encode(['success' => false, 'reload' => false, 'message' => 'Execute failed: (' . $stmt->errno . ') ' . $stmt->error]);
            }
            $stmt->close();
        } elseif ($formType == 'delete') {
            $customer_id = intval($_POST['customer_id']);

            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            if ($stmt === false) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("i", $customer_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'reload' => true]);
            } else {
                echo json_encode(['success' => false, 'reload' => false, 'message' => 'Execute failed: (' . $stmt->errno . ') ' . $stmt->error]);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'reload' => false]);
    }
    exit;
}
?>