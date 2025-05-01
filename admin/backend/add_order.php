<?php
// Current Date: 2025-05-01 17:36:57
// Author: aedanevangelista

session_start();
include "db_connection.php"; // Make sure this path is correct
include "check_role.php";   // Make sure this path is correct

// Basic role check (adjust role name if needed)
// checkRole('some_role_that_can_add_orders');

header('Content-Type: application/json'); // Set response type

// *** START DEBUG LOGGING ***
// Log the entire POST array to see what's being received
error_log("--- add_order.php --- Received POST data: " . print_r($_POST, true));
// *** END DEBUG LOGGING ***


// --- 1. Retrieve and Validate Input Data ---
$username = $_POST['username'] ?? null;
$order_date = $_POST['order_date'] ?? null; // Already set by frontend JS
$delivery_date = $_POST['delivery_date'] ?? null;
$delivery_address = $_POST['delivery_address'] ?? null;
$special_instructions = $_POST['special_instructions'] ?? ''; // Default to empty string
$orders_json = $_POST['orders'] ?? null; // JSON string from hidden input
$total_amount = $_POST['total_amount'] ?? null;
$company = $_POST['company'] ?? ''; // Get company name

// *** MORE DEBUG LOGGING ***
// Log the specific value assigned to $company
error_log("add_order.php - Value assigned to \$company variable: '" . $company . "'");
// *** END DEBUG LOGGING ***


// Basic validation (add more robust validation as needed)
if (!$username || !$order_date || !$delivery_date || !$delivery_address || !$orders_json || $total_amount === null) {
    // *** Log validation failure reason ***
    error_log("add_order.php - Validation failed: Missing required info.");
    echo json_encode(['success' => false, 'message' => 'Missing required order information.']);
    exit;
}

// Validate JSON
$order_items = json_decode($orders_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_items) || empty($order_items)) {
     // *** Log validation failure reason ***
     error_log("add_order.php - Validation failed: Invalid order items JSON.");
    echo json_encode(['success' => false, 'message' => 'Invalid order items data.']);
    exit;
}

// Validate total amount
if (!is_numeric($total_amount) || $total_amount < 0) {
     // *** Log validation failure reason ***
     error_log("add_order.php - Validation failed: Invalid total amount.");
     echo json_encode(['success' => false, 'message' => 'Invalid total amount.']);
     exit;
}


// --- 2. Generate Sequential PO Number ---
// ... (PO Number generation code remains the same) ...
$new_po_number = '';
$next_sequence = 1; // Default for the first order

$sql_find_max = "SELECT po_number
                 FROM orders
                 WHERE username = ?
                 ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC
                 LIMIT 1";

$stmt_find = $conn->prepare($sql_find_max);

if ($stmt_find === false) {
    error_log("Prepare failed (find max PO): " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing PO check.']);
    exit;
}

$stmt_find->bind_param("s", $username);

if (!$stmt_find->execute()) {
     error_log("Execute failed (find max PO): " . $stmt_find->error);
     echo json_encode(['success' => false, 'message' => 'Database error executing PO check.']);
     $stmt_find->close();
     exit;
}

$result = $stmt_find->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_po_number = $row['po_number'];
    $parts = explode('-', $last_po_number);
    $last_sequence = intval(end($parts));
    $next_sequence = $last_sequence + 1;
}

$stmt_find->close();
$new_po_number = sprintf('PO-%s-%03d', $username, $next_sequence);


// --- 3. Insert the Order into Database ---
$status = 'Pending';
$progress = 0;

$sql_insert = "INSERT INTO orders
               (po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress, company, special_instructions, driver_assigned)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = $conn->prepare($sql_insert);

if ($stmt_insert === false) {
    error_log("Prepare failed (insert order): " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing insert.']);
    exit;
}

$driver_assigned_default = 0;

// Use the corrected bind_param string
$stmt_insert->bind_param(
    "ssssssdsissi",
    $new_po_number,
    $username,
    $order_date,
    $delivery_date,
    $delivery_address,
    $orders_json,
    $total_amount,
    $status,
    $progress,
    $company, // Check the logged value for this variable
    $special_instructions,
    $driver_assigned_default
);

// *** Log BEFORE execute ***
error_log("add_order.php - About to execute INSERT with Company: '" . $company . "'");
// *** END LOGGING ***

if ($stmt_insert->execute()) {
    // Success
    error_log("add_order.php - INSERT successful for PO: " . $new_po_number);
    echo json_encode(['success' => true, 'message' => 'Order added successfully!', 'po_number' => $new_po_number]);
} else {
    // Failure
    error_log("Execute failed (insert order): (" . $stmt_insert->errno . ") " . $stmt_insert->error);
     if ($conn->errno == 1062) {
          echo json_encode(['success' => false, 'message' => 'Error: Duplicate PO Number generated. Please try again.']);
     } else {
          echo json_encode(['success' => false, 'message' => 'Database error saving order.']);
     }
}

$stmt_insert->close();
$conn->close();

?>