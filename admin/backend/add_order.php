<?php
session_start();
include "db_connection.php";
// include "check_role.php"; // Uncomment if you have role checks for this action

header('Content-Type: application/json');

error_log("--- add_order.php --- Received POST data: " . print_r($_POST, true));

// --- 1. Retrieve Input Data ---
$order_type = $_POST['order_type'] ?? null;
$po_number_from_frontend = $_POST['po_number'] ?? null; // WI-00X for Walk-In, or PO-USER-XXX from JS for Online (though backend may override for Online)

$username_online = $_POST['username_online'] ?? null;
$company_name_final = $_POST['company_name_final'] ?? ''; // company from form (Walk-In name or Online client's company)

$order_date = $_POST['order_date'] ?? null;
$delivery_date_frontend = $_POST['delivery_date'] ?? null; // Only for Online
$delivery_address = $_POST['delivery_address'] ?? null;
$special_instructions = $_POST['special_instructions'] ?? '';
$orders_json = $_POST['orders'] ?? null;
$total_amount = $_POST['total_amount'] ?? null;

// --- 2. Initialize variables for DB ---
$db_username = '';
$db_company = $company_name_final;
$db_delivery_date = null;
$db_generated_po_number = ''; // For Online orders if backend generates

// --- 3. Conditional Logic based on Order Type ---
if ($order_type === 'Walk In') {
    $db_username = 'Walk-In Customer';
    // For Walk-In, PO number comes directly from frontend (WI-00X)
    $db_generated_po_number = $po_number_from_frontend; 
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
    $user_part_for_po = strtoupper(substr($db_username, 0, 4));

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
    error_log("add_order.php - Validation failed: Invalid order type specified '" . $order_type . "'.");
    echo json_encode(['success' => false, 'message' => 'Invalid order type specified.']);
    exit;
}

// --- 4. Validate Core Data ---
$errors = [];
if (empty($order_type)) $errors[] = "Order type is missing.";
if (empty($db_username)) $errors[] = "Username could not be determined.";
if (empty($order_date)) $errors[] = "Order date is missing.";
// Delivery date validation already handled within Online type block
if (empty($delivery_address)) $errors[] = "Delivery address/Address is missing.";
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
$driver_assigned_default = 0; // Assuming this is an integer

$sql_insert = "INSERT INTO orders
               (po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress, company, special_instructions, driver_assigned)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = $conn->prepare($sql_insert);
if ($stmt_insert === false) {
    error_log("Prepare failed (insert order): " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error preparing to save order.']);
    exit;
}

// Correct binding types: s,s,s,s,s,s,d,s,i,s,s,i
$stmt_insert->bind_param(
    "ssssssd sissi",
    $db_generated_po_number,    // PO Number (Generated for Online, from frontend for Walk-In)
    $db_username,               // Username (Actual for Online, "Walk-In Customer" for Walk-In)
    $order_date,                // Order Date
    $db_delivery_date,          // Delivery Date (NULL for Walk-In)
    $delivery_address,          // Delivery Address
    $orders_json,               // Order Items JSON
    $total_amount,              // Total Amount
    $status,                    // Status (Pending)
    $progress,                  // Progress (0)
    $db_company,                // Company Name
    $special_instructions,      // Special Instructions
    $driver_assigned_default    // Driver Assigned (0)
);

error_log("add_order.php - Attempting INSERT. PO: '{$db_generated_po_number}', User: '{$db_username}', Company: '{$db_company}', OrderDate: '{$order_date}', DeliveryDate: '" . ($db_delivery_date ?? 'NULL') . "'");

if ($stmt_insert->execute()) {
    error_log("add_order.php - INSERT successful for PO: " . $db_generated_po_number);
    echo json_encode(['success' => true, 'message' => 'Order added successfully!', 'po_number' => $db_generated_po_number]);
} else {
    error_log("Execute failed (insert order): (" . $stmt_insert->errno . ") " . $stmt_insert->error . " PO: " . $db_generated_po_number);
     if ($stmt_insert->errno == 1062) {
          echo json_encode(['success' => false, 'message' => 'Error: Duplicate PO Number (' . $db_generated_po_number . '). Please try creating the order again. If the problem persists, check recent orders.']);
     } else {
          echo json_encode(['success' => false, 'message' => 'Database error saving order. Details: ' . $stmt_insert->error]);
     }
}

$stmt_insert->close();
$conn->close();
?>