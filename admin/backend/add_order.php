<?php
session_start();
include "db_connection.php"; // Make sure this path is correct and the file is error-free

// ** CRITICAL DATABASE CONNECTION CHECK **
// Ensure $conn is a valid mysqli object from db_connection.php
if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
    // Log the specific error if available
    $db_error_message = 'Database connection object is invalid or connection failed.';
    if ($conn && $conn->connect_error) {
        $db_error_message .= ' Error: ' . $conn->connect_error;
    }
    error_log("add_order.php - " . $db_error_message);

    // Send a JSON response even for this critical failure
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500); // Server error
    }
    echo json_encode(['success' => false, 'message' => 'Critical database connection error. Please check server logs.']);
    exit;
}

// include "check_role.php";   // Make sure this path is correct if you re-enable it. Ensure it doesn't echo HTML.
// checkRole('some_role_that_can_add_orders'); // If re-enabled

// This header should be sent as early as possible, but AFTER any critical include checks that might need to send their own.
// However, for consistent JSON responses, it's good practice to have it here.
if (!headers_sent()) {
    header('Content-Type: application/json');
}

error_log("--- add_order.php --- Received POST data: " . print_r($_POST, true));

// --- 1. Retrieve Input Data ---
$order_type = $_POST['order_type'] ?? null;
$po_number_from_frontend = $_POST['po_number'] ?? null;
$username_online = $_POST['username_online'] ?? null;
$company_name_final = $_POST['company_name_final'] ?? '';
$order_date = $_POST['order_date'] ?? null;
$delivery_date_frontend = $_POST['delivery_date'] ?? null;
$delivery_address = $_POST['delivery_address'] ?? null;
$special_instructions = $_POST['special_instructions'] ?? '';
$orders_json = $_POST['orders'] ?? null;
$total_amount = $_POST['total_amount'] ?? null;

// --- 2. Initialize variables for DB ---
$db_username = '';
$db_company = $company_name_final; // This now correctly takes the value from the hidden 'company_name_final' field.
$db_delivery_date = null;
$db_generated_po_number = '';

// --- 3. Conditional Logic based on Order Type ---
if ($order_type === 'Walk In') {
    $db_username = 'Walk-In Customer';
    $db_generated_po_number = $po_number_from_frontend; // WI-00X from frontend
    // $db_delivery_date remains null
    if (empty($db_company)) {
        error_log("add_order.php - Validation failed: Full Name/Company Name required for Walk-In.");
        echo json_encode(['success' => false, 'message' => 'Full Name / Company Name is required for Walk-In orders.']);
        exit;
    }
} elseif ($order_type === 'Online') {
    $db_username = $username_online;
    $db_delivery_date = $delivery_date_frontend;

    if (empty($db_username)) {
        error_log("add_order.php - Validation failed: Username required for Online order.");
        echo json_encode(['success' => false, 'message' => 'Username is required for Online orders.']);
        exit;
    }
    if (empty($db_delivery_date)) {
        error_log("add_order.php - Validation failed: Delivery date required for Online order.");
        echo json_encode(['success' => false, 'message' => 'Delivery date is required for Online orders.']);
        exit;
    }

    // PO Number Generation for Online orders (Backend Logic)
    $next_sequence_online = 1;
    $user_part_for_po = strtoupper(substr(preg_replace("/[^A-Za-z0-9]/", '', $db_username), 0, 4)); // Sanitize and take first 4 alphanumeric chars

    $sql_find_max_online = "SELECT po_number FROM orders WHERE username = ? AND po_number LIKE CONCAT('PO-', ?, '-%') ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC LIMIT 1";
    $stmt_find_online = $conn->prepare($sql_find_max_online);

    if ($stmt_find_online === false) {
        error_log("Prepare failed (find max PO for Online): " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error preparing PO check for Online order.']);
        exit;
    }
    $stmt_find_online->bind_param("ss", $db_username, $user_part_for_po);

    if (!$stmt_find_online->execute()) {
        error_log("Execute failed (find max PO for Online): " . $stmt_find_online->error);
        echo json_encode(['success' => false, 'message' => 'Database error executing PO check for Online order.']);
        $stmt_find_online->close();
        exit;
    }
    $result_online = $stmt_find_online->get_result();
    if ($result_online->num_rows > 0) {
        $row_online = $result_online->fetch_assoc();
        $last_po_number_online = $row_online['po_number'];
        $parts_online = explode('-', $last_po_number_online);
        $last_sequence_online = intval(end($parts_online));
        $next_sequence_online = $last_sequence_online + 1;
    }
    $stmt_find_online->close();
    $db_generated_po_number = sprintf('PO-%s-%03d', $user_part_for_po, $next_sequence_online);

} else {
    error_log("add_order.php - Validation failed: Invalid order type specified '" . htmlspecialchars($order_type) . "'.");
    echo json_encode(['success' => false, 'message' => 'Invalid order type specified.']);
    exit;
}

// --- 4. Validate Core Data ---
$errors = [];
if (empty($order_type)) $errors[] = "Order type is missing.";
if (empty($db_username)) $errors[] = "Username could not be determined.";
if (empty($order_date)) $errors[] = "Order date is missing.";
if (empty($delivery_address)) $errors[] = "Address is missing.";
if (empty($orders_json)) $errors[] = "Order items are missing.";
if ($total_amount === null || !is_numeric($total_amount) || $total_amount < 0) $errors[] = "Invalid total amount.";
if (empty($db_generated_po_number)) $errors[] = "PO Number could not be determined or was not provided.";


if (!empty($errors)) {
    error_log("add_order.php - Final Validation failed: " . implode("; ", $errors));
    echo json_encode(['success' => false, 'message' => implode(" ", $errors)]);
    exit;
}

$order_items = json_decode($orders_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_items) || empty($order_items)) {
    error_log("add_order.php - Validation failed: Invalid order items JSON format.");
    echo json_encode(['success' => false, 'message' => 'Invalid order items data format.']);
    exit;
}

// --- 5. Insert the Order into Database ---
$status = 'Pending';
$progress = 0;
$driver_assigned_default = 0;

$sql_insert = "INSERT INTO orders
               (po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress, company, special_instructions, driver_assigned)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = $conn->prepare($sql_insert);
if ($stmt_insert === false) {
    error_log("Prepare failed (insert order): " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing to save order.']);
    exit;
}

// Bind parameters:
// po_number (s), username (s), order_date (s), delivery_date (s, can be null), 
// delivery_address (s), orders (s, JSON), total_amount (d), 
// status (s), progress (i), company (s), special_instructions (s), driver_assigned (i)
$stmt_insert->bind_param(
    "ssssssd sissi", // 12 types
    $db_generated_po_number,
    $db_username,
    $order_date,
    $db_delivery_date, // This will correctly bind NULL if $db_delivery_date is null
    $delivery_address,
    $orders_json,
    $total_amount,
    $status,
    $progress,
    $db_company,
    $special_instructions,
    $driver_assigned_default
);

error_log("add_order.php - Attempting INSERT. PO: '{$db_generated_po_number}', User: '{$db_username}', Company: '{$db_company}', OrderDate: '{$order_date}', DeliveryDate: '" . ($db_delivery_date ?? 'NULL') . "'");

if ($stmt_insert->execute()) {
    error_log("add_order.php - INSERT successful for PO: " . $db_generated_po_number);
    echo json_encode(['success' => true, 'message' => 'Order added successfully!', 'po_number' => $db_generated_po_number]);
} else {
    error_log("Execute failed (insert order): (" . $stmt_insert->errno . ") " . $stmt_insert->error . " PO: " . $db_generated_po_number);
     if ($stmt_insert->errno == 1062) { // Duplicate entry for a unique key
          echo json_encode(['success' => false, 'message' => 'Error: Duplicate PO Number (' . htmlspecialchars($db_generated_po_number) . '). This PO number might already exist. Please try creating the order again.']);
     } else {
          echo json_encode(['success' => false, 'message' => 'Database error saving order. Error: ' . htmlspecialchars($stmt_insert->error)]);
     }
}

$stmt_insert->close();
$conn->close();
?>