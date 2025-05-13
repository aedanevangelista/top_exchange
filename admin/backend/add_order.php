<?php
session_start();

if (!headers_sent()) {
    header('Content-Type: application/json');
}

include "db_connection.php";
include_once "raw_material_manager.php"; // Include the new manager

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("add_order.php - \$conn is not a valid mysqli object after include.");
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['success' => false, 'message' => 'Database connection variable not initialized. Check db_connection.php.']);
    exit;
}
if ($conn->connect_error) {
    error_log("add_order.php - Database connection failed: " . $conn->connect_error);
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    $received_order_type_for_log = $_POST['order_type'] ?? 'NOT SET';
    error_log("--- add_order.php --- Received POST data at " . date("Y-m-d H:i:s") . ". Order Type Received: " . $received_order_type_for_log . ". Full POST: " . print_r($_POST, true));

    $order_type = $_POST['order_type'] ?? null;
    $username_online = $_POST['username_online'] ?? null;
    $company_name_final = $_POST['company_name_final'] ?? '';
    $order_date = $_POST['order_date'] ?? null;
    $delivery_date_frontend = $_POST['delivery_date'] ?? null;
    $delivery_address = $_POST['delivery_address'] ?? null;
    $special_instructions = $_POST['special_instructions'] ?? '';
    $orders_json = $_POST['orders'] ?? null; // Crucial for material deduction
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

        $next_walkin_seq = 1;
        $sql_find_max_walkin = "SELECT po_number FROM orders WHERE po_number LIKE 'WI-%' ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC LIMIT 1";
        $stmt_find_walkin = $conn->prepare($sql_find_max_walkin);

        if ($stmt_find_walkin === false) { throw new Exception('DB error (Walk-In PO Check Prep): ' . $conn->error); }
        if (!$stmt_find_walkin->execute()) { $stmt_find_walkin->close(); throw new Exception('DB error (Walk-In PO Check Exec): ' . $stmt_find_walkin->error); }
        $result_walkin = $stmt_find_walkin->get_result();
        if ($result_walkin->num_rows > 0) { $row_walkin = $result_walkin->fetch_assoc(); $last_walkin_po = $row_walkin['po_number']; $parts_walkin = explode('-', $last_walkin_po); $last_seq_walkin = intval(end($parts_walkin)); $next_walkin_seq = $last_seq_walkin + 1; }
        $stmt_find_walkin->close();
        $db_generated_po_number = sprintf('WI-%03d', $next_walkin_seq);
        error_log("add_order.php - Generated Walk-In PO: " . $db_generated_po_number);

    } elseif ($order_type === 'Online') {
        $db_username = $username_online;
        $db_delivery_date = $delivery_date_frontend;
        $db_status = 'Active'; 
        $db_progress = 0;      
        
        if (empty($db_username) || empty($db_delivery_date) || empty($orders_json)) {
             error_log("add_order.php - Online order missing core data (username, delivery date, or order items).");
             echo json_encode(['success' => false, 'message' => 'Online order details incomplete for processing.']);
             exit;
        }
        
        // Check Raw Materials for new Online (Active) order BEFORE generating PO and attempting insert
        $material_check_result = check_raw_materials_for_order($conn, $orders_json); 
        if (!$material_check_result['all_sufficient']) {
            $missing_list_log = [];
            foreach($material_check_result['materials'] as $name => $details) {
                if (!$details['sufficient']) {
                    $missing_list_log[] = "{$name} (Needs: {$details['required']}, Has: {$details['available']})";
                }
            }
            $missing_summary_log = implode('; ', $missing_list_log);
            error_log("add_order.php - Insufficient raw materials for new Online order. PO not generated. Details: {$missing_summary_log}");
            
            $missing_summary_user = implode(', ', array_keys(array_filter($material_check_result['materials'], fn($m) => !$m['sufficient'])));
            echo json_encode([
                'success' => false, 
                'message' => "Cannot create Online order as 'Active' due to insufficient raw materials. Please create as 'Pending' or check stock. Missing: {$missing_summary_user}",
                'materials_detail' => $material_check_result['materials'],
                'recommend_pending' => true // Flag for frontend
            ]);
            exit;
        }
        error_log("add_order.php - Raw materials sufficient for new Online order. Proceeding to PO generation for user: {$db_username}");

        // If sufficient, proceed to generate PO
        $db_generated_po_number = generate_online_po_number_backend($conn, $db_username); // Using the helper function
        error_log("add_order.php - Generated Online PO: " . $db_generated_po_number . " for user: {$db_username}");


    } else {
        error_log("add_order.php - Validation failed: Invalid order type specified. Received from POST: '" . htmlspecialchars($received_order_type_for_log) . "'.");
        echo json_encode(['success' => false, 'message' => 'Invalid order type specified. Please select a valid order type.']);
        exit;
    }

    if ($order_type !== 'Online' && $order_type !== 'Walk In') {
        error_log("add_order.php - CRITICAL: order_type variable is invalid after conditional block: '" . htmlspecialchars($order_type ?? 'NULL') . "'.");
        echo json_encode(['success' => false, 'message' => 'Internal server error: Order type became invalid.']);
        exit;
    }

    $errors = [];
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

    $order_items_array = json_decode($orders_json, true); // Changed variable name for clarity
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_items_array) || empty($order_items_array)) {
        error_log("add_order.php - Validation failed: Invalid order items JSON format. Error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid order items data format. JSON Error: ' . json_last_error_msg()]);
        exit;
    }

    $driver_assigned_default = 0;
    
    $conn->begin_transaction();
    try {
        $sql_insert = "INSERT INTO orders
                       (po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress, company, special_instructions, driver_assigned, order_type) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert === false) {
            throw new Exception("Prepare failed (insert order): " . $conn->error);
        }

        $stmt_insert->bind_param(
            "ssssssdsissis", 
            $db_generated_po_number, $db_username, $order_date, $db_delivery_date,
            $delivery_address, $orders_json, $total_amount, $db_status, $db_progress,
            $db_company, $special_instructions, $driver_assigned_default, $order_type
        );

        error_log("add_order.php - Attempting INSERT within transaction. PO: '{$db_generated_po_number}', OrderType: '{$order_type}', Status: '{$db_status}'");

        if (!$stmt_insert->execute()) {
            if ($stmt_insert->errno == 1062) { // Unique key violation for PO
                 throw new Exception("Error: Duplicate PO Number ('" . htmlspecialchars($db_generated_po_number) . "'). This PO number might already exist.");
            }
            throw new Exception("Order insert failed: (" . $stmt_insert->errno . ") " . $stmt_insert->error);
        }
        $inserted_po = $db_generated_po_number; // Use this for logging/response
        $stmt_insert->close(); // Close statement after successful execution

        // If it's an Online order that was set to Active, deduct raw materials now
        if ($order_type === 'Online' && $db_status === 'Active') {
            // Material check was done before, deduction happens now within transaction
            if (!deduct_raw_materials_for_order($conn, $orders_json, $inserted_po)) {
                // This will trigger rollback due to the exception thrown by deduct_raw_materials_for_order on failure
                throw new Exception("Order (PO: {$inserted_po}) was recorded, but failed to deduct raw materials. The order has been rolled back.");
            }
            error_log("add_order.php - Raw materials deducted successfully for new Active Online order PO: {$inserted_po}");
        }

        $conn->commit();
        error_log("add_order.php - Transaction COMMITTED. INSERT successful for PO: " . $inserted_po);
        echo json_encode(['success' => true, 'message' => 'Order added successfully!', 'po_number' => $inserted_po, 'order_type_inserted' => $order_type, 'status_inserted' => $db_status]);

    } catch (Exception $e_transaction) {
        $conn->rollback();
        error_log("add_order.php - Transaction ROLLED BACK. Error: " . $e_transaction->getMessage() . " For PO attempt: {$db_generated_po_number}");
        echo json_encode(['success' => false, 'message' => 'Order creation failed: ' . $e_transaction->getMessage()]);
    }

} catch (Throwable $e) { // Catch any other general errors
    error_log("add_order.php - Uncaught Throwable: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("add_order.php - Stack trace: " . $e->getTraceAsString());
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(['success' => false, 'message' => 'A critical server error occurred. Error: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    // Ensure exit is called, especially if inside a try/finally without explicit exits in all paths
    // However, the transaction block should manage its own exit or fall through to here.
    // The main paths within try already have exit.
}

// Helper function for Online PO generation (moved from inline for clarity)
// Throws Exception on DB error to be caught by the transaction block
function generate_online_po_number_backend($conn, $db_username_param) {
    $next_sequence_online = 1;
    $user_part_raw = preg_replace("/[^A-Za-z0-9]/", '', $db_username_param);
    $user_part_for_po = strtoupper(substr($user_part_raw, 0, 4));
    if (empty($user_part_for_po)) { $user_part_for_po = "USER"; }

    $sql_find_max_online = "SELECT po_number FROM orders WHERE username = ? AND po_number LIKE CONCAT('PO-', ?, '-%') ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC LIMIT 1";
    $stmt_find_online = $conn->prepare($sql_find_max_online);
    if ($stmt_find_online === false) { 
        error_log("generate_online_po_number_backend - Prepare failed: " . $conn->error);
        throw new Exception("DB error (PO Check Online Prep): " . $conn->error); 
    }
    $stmt_find_online->bind_param("ss", $db_username_param, $user_part_for_po);
    if (!$stmt_find_online->execute()) { 
        $err = $stmt_find_online->error;
        $stmt_find_online->close(); 
        error_log("generate_online_po_number_backend - Execute failed: " . $err);
        throw new Exception("DB error (PO Check Online Exec): " . $err); 
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
    return sprintf('PO-%s-%03d', $user_part_for_po, $next_sequence_online);
}
?>