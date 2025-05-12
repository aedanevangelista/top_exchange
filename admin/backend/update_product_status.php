<?php
session_start();
include "db_connection.php"; // Ensure this path is correct relative to update_product_status.php

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the input from the JSON request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

// Validate product_id
if (!isset($input['product_id']) || !is_numeric($input['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing product ID.']);
    exit;
}
$product_id = intval($input['product_id']);

// Validate new_status
if (!isset($input['new_status']) || !in_array($input['new_status'], ['Active', 'Inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing new status. Status must be "Active" or "Inactive".']);
    exit;
}
$new_status = $input['new_status'];

// For now, this script assumes we are always updating the 'products' table.
// If you need to handle different product types (e.g., 'walkin_products')
// the client-side JavaScript would need to send a 'product_type' or similar identifier.
$table = 'products'; 

// Check if the product exists before attempting to update
$check_stmt = $conn->prepare("SELECT product_id FROM $table WHERE product_id = ?");
if (!$check_stmt) {
    error_log("SQL Prepare Error (check_stmt) in update_product_status.php: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing to check product.']);
    exit;
}
$check_stmt->bind_param("i", $product_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();


// Prepare and execute the update statement
$stmt = $conn->prepare("UPDATE $table SET status = ? WHERE product_id = ?");
if (!$stmt) {
    error_log("SQL Prepare Error (update_stmt) in update_product_status.php: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing to update status.']);
    exit;
}

$stmt->bind_param("si", $new_status, $product_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Product status updated successfully.', 'new_status' => $new_status]);
    } else {
        // This can happen if the status was already the new_status, or product_id didn't match (though we checked).
        echo json_encode(['success' => true, 'message' => 'Product status was already up to date or no change made.', 'new_status' => $new_status]);
    }
} else {
    error_log("SQL Execute Error in update_product_status.php: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update product status. Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>