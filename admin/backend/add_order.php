<?php
session_start();

if (!headers_sent()) {
    header('Content-Type: application/json');
}

include "db_connection.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("add_order.php - \$conn is not a valid mysqli object after include.");
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'message' => 'Database connection variable not initialized. Check db_connection.php.']);
    exit;
}
if ($conn->connect_error) {
    error_log("add_order.php - Database connection failed: " . $conn->connect_error);
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // Log received POST data, especially order_type
    $received_order_type_for_log = $_POST['order_type'] ?? 'NOT SET';
    error_log("--- add_order.php --- Received POST data at " . date("Y-m-d H:i:s") . ". Order Type Received: " . $received_order_type_for_log . ". Full POST: " . print_r($_POST, true));


    $order_type = $_POST['order_type'] ?? null; // This should be 'Online' or 'Walk In'
    // $po_number_from_frontend = $_POST['po_number'] ?? null; // We will not directly use this for Walk-In PO generation anymore
    $username_online = $_POST['username_online'] ?? null;
    $company_name_final = $_POST['company_name_final'] ?? '';
    $order_date = $_POST['order_date'] ?? null;
    $delivery_date_frontend = $_POST['delivery_date'] ?? null;
    $delivery_address = $_POST['delivery_address'] ?? null;
    $special_instructions = $_POST['special_instructions'] ?? '';
    $orders_json = $_POST['orders'] ?? null;
    $total_amount = $_POST['total_amount'] ?? null;

    $db_username = '';
    $db_company = $company_name_final;
    $db_delivery_date = null;
    $db_generated_po_number = '';
    $db_status = 'Pending'; 
    $db_progress = 0;       

    if ($order_type === 'Walk In') {
        $db_username = 'Walk-In Customer';
        $db_status = 'Completed'; 
        $db_progress = 100;       
        
        if (empty($order_date)) {
            error_log("add_order.php - Validation failed: Order date is missing for Walk-In.");
            echo json_encode(['success' => false, 'message' => 'Order date is missing and required for Walk-In orders.']);
            exit;
        }
        $db_delivery_date = $order_date; 

        if (empty($db_company)) {
            error_log("add_order.php - Validation failed: Full Name/Company Name required for Walk-In.");
            echo json_encode(['success' => false, 'message' => 'Full Name / Company Name is required for Walk-In orders.']);
            exit;
        }

        // Backend Walk-In PO Number Generation
        $next_walkin_seq = 1;
        $sql_find_max_walkin = "SELECT po_number FROM orders WHERE po_number LIKE 'WI-%' ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC LIMIT 1";
        $stmt_find_walkin = $conn->prepare($sql_find_max_walkin);

        if ($stmt_find_walkin === false) {
            error_log("Prepare failed (find max Walk-In PO): " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'DB error (Walk-In PO Check Prep): ' . $conn->error]);
            exit;
        }
        if (!$stmt_find_walkin->execute()) {
            error_log("Execute failed (find max Walk-In PO): " . $stmt_find_walkin->error);
            echo json_encode(['success' => false, 'message' => 'DB error (Walk-In PO Check Exec): ' . $stmt_find_walkin->error]);
            $stmt_find_walkin->close();
            exit;
        }
        $result_walkin = $stmt_find_walkin->get_result();
        if ($result_walkin->num_rows > 0) {
            $row_walkin = $result_walkin->fetch_assoc();
            $last_walkin_po = $row_walkin['po_number'];
            $parts_walkin = explode('-', $last_walkin_po);
            $last_seq_walkin = intval(end($parts_walkin));
            $next_walkin_seq = $last_seq_walkin + 1;
        }
        $stmt_find_walkin->close();
        $db_generated_po_number = sprintf('WI-%03d', $next_walkin_seq);
        // Log the generated Walk-In PO
        error_log("add_order.php - Generated Walk-In PO: " . $db_generated_po_number);


    } elseif ($order_type === 'Online') {
        $db_username = $username_online;
        $db_delivery_date = $delivery_date_frontend;
        // $db_status remains 'Pending' for Online
        // $db_progress remains 0 for Online

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

        $next_sequence_online = 1;
        // Sanitize username part for PO more carefully
        $user_part_raw = preg_replace("/[^A-Za-z0-9]/", '', $db_username);
        $user_part_for_po = strtoupper(substr($user_part_raw, 0, 4));
        if (empty($user_part_for_po)) { // Handle cases where username might be all special chars
            $user_part_for_po = "USER"; 
        }


        $sql_find_max_online = "SELECT po_number FROM orders WHERE username = ? AND po_number LIKE CONCAT('PO-', ?, '-%') ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC LIMIT 1";
        $stmt_find_online = $conn->prepare($sql_find_max_online);

        if ($stmt_find_online === false) {
            error_log("Prepare failed (find max PO for Online): " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'DB error (PO Check Online Prep): ' . $conn->error]);
            exit;
        }
        $stmt_find_online->bind_param("ss", $db_username, $user_part_for_po);

        if (!$stmt_find_online->execute()) {
            error_log("Execute failed (find max PO for Online): " . $stmt_find_online->error);
            echo json_encode(['success' => false, 'message' => 'DB error (PO Check Online Exec): ' . $stmt_find_online->error]);
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
        error_log("add_order.php - Generated Online PO: " . $db_generated_po_number);

    } else {
        error_log("add_order.php - Validation failed: Invalid order type specified. Received from POST: '" . htmlspecialchars($received_order_type_for_log) . "'.");
        echo json_encode(['success' => false, 'message' => 'Invalid order type specified. Please select a valid order type.']);
        exit;
    }

    // --- Validation for $order_type itself ---
    if ($order_type !== 'Online' && $order_type !== 'Walk In') {
        error_log("add_order.php - CRITICAL: order_type variable is invalid after conditional block: '" . htmlspecialchars($order_type ?? 'NULL') . "'.");
        echo json_encode(['success' => false, 'message' => 'Internal server error: Order type became invalid.']);
        exit;
    }


    $errors = [];
    // if (empty($order_type)) $errors[] = "Order type is missing."; // Already checked
    if (empty($db_username)) $errors[] = "Username could not be determined.";
    if (empty($order_date)) $errors[] = "Order date is missing.";
    if (empty($db_delivery_date)) $errors[] = "Delivery date could not be determined.";
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
        error_log("add_order.php - Validation failed: Invalid order items JSON format. Error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid order items data format. JSON Error: ' . json_last_error_msg()]);
        exit;
    }

    $driver_assigned_default = 0;

    // Ensure $order_type is included in the INSERT statement
    $sql_insert = "INSERT INTO orders
                   (po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress, company, special_instructions, driver_assigned, order_type) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Added order_type, now 13 placeholders

    $stmt_insert = $conn->prepare($sql_insert);
    if ($stmt_insert === false) {
        error_log("Prepare failed (insert order): " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'DB error (Insert Prep): ' . $conn->error]);
        exit;
    }

    // Add 's' for order_type at the end of the bind_param string
    $stmt_insert->bind_param(
        "ssssssdsissis", // Added 's' for order_type
        $db_generated_po_number, $db_username, $order_date, $db_delivery_date,
        $delivery_address, $orders_json, $total_amount, $db_status, $db_progress,
        $db_company, $special_instructions, $driver_assigned_default,
        $order_type // Bind the $order_type variable
    );

    error_log("add_order.php - Attempting INSERT. PO: '{$db_generated_po_number}', User: '{$db_username}', OrderType: '{$order_type}', Company: '{$db_company}', OrderDate: '{$order_date}', DeliveryDate: '{$db_delivery_date}', Status: '{$db_status}', Progress: '{$db_progress}'");

    if ($stmt_insert->execute()) {
        error_log("add_order.php - INSERT successful for PO: " . $db_generated_po_number);
        echo json_encode(['success' => true, 'message' => 'Order added successfully!', 'po_number' => $db_generated_po_number, 'order_type_inserted' => $order_type]);
    } else {
        error_log("Execute failed (insert order): (" . $stmt_insert->errno . ") " . $stmt_insert->error . " PO: " . $db_generated_po_number);
        if ($stmt_insert->errno == 1062) { // Unique key violation
            echo json_encode(['success' => false, 'message' => 'Error: Duplicate PO Number (' . htmlspecialchars($db_generated_po_number) . '). This PO number might already exist. Please try again or contact support if the issue persists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error saving order. Error: ' . htmlspecialchars($stmt_insert->error)]);
        }
    }

    if (isset($stmt_insert)) {
        $stmt_insert->close();
    }

} catch (Throwable $e) {
    error_log("add_order.php - Uncaught Throwable: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("add_order.php - Stack trace: " . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'A critical server error occurred. Error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    exit; 
}
?>