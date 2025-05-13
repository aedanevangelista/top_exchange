<?php
session_start();
include "db_connection.php";
include_once "raw_material_manager.php"; // Include the new manager
// include "check_role.php"; // Assuming this is handled if you uncomment

// --- Security & Role Check (Using your structure) ---
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
$admin_username = $_SESSION['admin_username'] ?? 'admin';

// Example Role Check (adjust as needed if you use checkRole function elsewhere)
/*
try {
    // Assuming checkRole is defined in check_role.php
    // checkRole('Orders'); 
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $e->getMessage()]);
    exit;
}
*/


// --- Response Setup ---
if (!headers_sent()) {
    header('Content-Type: application/json');
}
$response = ['success' => false, 'message' => 'An error occurred.'];
$material_adjustment_message = '';


// --- Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['po_number']) || !isset($_POST['status'])) {
    $response['message'] = 'Invalid request method or missing parameters.';
    error_log("[update_order_status] Invalid request: Method=" . $_SERVER['REQUEST_METHOD'] . ", POST=" . print_r($_POST, true));
    echo json_encode($response);
    exit;
}

// --- Input Processing ---
$po_number = trim($_POST['po_number']);
$new_status = trim($_POST['status']);
// Flag for material management will now be determined by status transition logic
// $deduct_materials_flag = isset($_POST['deduct_materials']) && $_POST['deduct_materials'] === '1'; // No longer direct flags
// $return_materials_flag = isset($_POST['return_materials']) && $_POST['return_materials'] === '1'; // No longer direct flags
$manage_raw_materials_action = $_POST['manage_raw_materials'] ?? null; // 'deduct' or 'return' (from JS)


$allowed_statuses = ['Pending', 'Active', 'For Delivery', 'In Transit', 'Completed', 'Rejected'];
if (empty($po_number) || !in_array($new_status, $allowed_statuses)) {
    $response['message'] = 'Invalid PO Number or Status provided.';
    error_log("[update_order_status] Invalid input: PO=" . $po_number . ", Status=" . $new_status);
    echo json_encode($response);
    exit;
}

error_log("[update_order_status] Attempting update for PO: {$po_number} to Status: {$new_status} by Admin: {$admin_username}. Manage Raw Materials Action: {$manage_raw_materials_action}");

// --- Database Operations within a Transaction ---
$conn->begin_transaction();

try {
    // 1. Get current order status and details (like the 'orders' JSON for material handling)
    $stmt_get_order = $conn->prepare("SELECT status, orders FROM orders WHERE po_number = ? FOR UPDATE"); // Lock row
    if (!$stmt_get_order) throw new Exception("Prepare failed (get order): " . $conn->error);
    $stmt_get_order->bind_param("s", $po_number);
    if (!$stmt_get_order->execute()) throw new Exception("Execute failed (get order): " . $stmt_get_order->error);
    $result_order = $stmt_get_order->get_result();
    $order_data = $result_order->fetch_assoc();
    $stmt_get_order->close();

    if (!$order_data) {
        throw new Exception("Order with PO Number '{$po_number}' not found.");
    }
    $old_status = $order_data['status'];
    $orders_json = $order_data['orders']; // Needed for material handling

    if ($old_status === $new_status) {
        $conn->commit(); 
        $response['success'] = true;
        $response['message'] = "Order status is already '{$new_status}'. No change made.";
        error_log("[update_order_status] No status change needed for PO: {$po_number}. Already {$new_status}.");
        echo json_encode($response);
        exit;
    }

    // 2. Handle Raw Material Deduction/Return
    if ($manage_raw_materials_action === 'deduct' && $new_status === 'Active' && $old_status === 'Pending') {
        error_log("[update_order_status] Action: Deduct raw materials for PO: {$po_number}");
        // Pre-check raw materials (important before deduction)
        $material_check = check_raw_materials_for_order($conn, $orders_json);
        if (!$material_check['all_sufficient']) {
            $missing_list = implode(', ', array_keys(array_filter($material_check['materials'], fn($m) => !$m['sufficient'])));
            throw new Exception("Cannot activate order: Insufficient raw materials. Missing: {$missing_list}");
        }
        if (!deduct_raw_materials_for_order($conn, $orders_json, $po_number)) {
            throw new Exception("Raw material deduction failed for PO: {$po_number}. Status not changed.");
        }
        $material_adjustment_message = 'Raw materials deducted successfully.';
        error_log("[update_order_status] Raw materials deducted for PO: {$po_number}");

    } elseif ($manage_raw_materials_action === 'return' && ($new_status === 'Pending' || $new_status === 'Rejected') && $old_status === 'Active') {
        error_log("[update_order_status] Action: Return raw materials for PO: {$po_number}");
        if (!return_raw_materials_for_order($conn, $orders_json, $po_number)) {
            // Log a warning, but don't necessarily fail the entire status change transaction
            // if partial return is acceptable. For stricter handling, throw new Exception here.
            error_log("[update_order_status] WARNING: One or more raw materials failed to return for PO {$po_number}. Please check inventory manually.");
            $material_adjustment_message = 'Attempted to return raw materials; some may have failed. Please verify inventory.';
        } else {
            $material_adjustment_message = 'Raw materials returned successfully.';
            error_log("[update_order_status] Raw materials returned for PO: {$po_number}");
        }
    }


    // 3. Decrement Driver Count if status changes to 'Completed' (Using your existing logic)
    $driver_id_to_update = null;
    if ($new_status === 'Completed' && $old_status !== 'Completed') { // Only if changing TO completed
        error_log("[update_order_status] New status is 'Completed'. Checking driver assignment for PO: {$po_number}");
        $stmt_find_driver = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        if (!$stmt_find_driver) throw new Exception("Prepare failed (find driver): " . $conn->error);
        $stmt_find_driver->bind_param("s", $po_number);
        if (!$stmt_find_driver->execute()) throw new Exception("Execute failed (find driver): " . $stmt_find_driver->error);
        $result_driver = $stmt_find_driver->get_result();
        if ($row_driver = $result_driver->fetch_assoc()) {
            $driver_id_to_update = $row_driver['driver_id'];
        }
        $stmt_find_driver->close();

        if ($driver_id_to_update) {
            $stmt_update_driver = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
            if (!$stmt_update_driver) throw new Exception("Prepare failed (update driver): " . $conn->error);
            $stmt_update_driver->bind_param("i", $driver_id_to_update);
            if (!$stmt_update_driver->execute()) throw new Exception("Execute failed (update driver): " . $stmt_update_driver->error);
            $stmt_update_driver->close();
            error_log("[update_order_status] Decremented delivery count for driver ID: {$driver_id_to_update} for PO: {$po_number}.");
        } else {
            error_log("[update_order_status] No driver found or already processed for completed PO: {$po_number}.");
        }
    }

    // 4. Update the Order Status and Progress in the 'orders' table
    $sql_update_order = "UPDATE orders SET status = ?";
    $params_update = [$new_status];
    $types_update = "s";

    if ($new_status === 'For Delivery' || $new_status === 'Completed') {
        $sql_update_order .= ", progress = 100";
    } elseif ($new_status === 'Active' && ($old_status === 'Completed' || $old_status === 'For Delivery')) {
        // If reactivating a previously completed/delivery order, reset progress unless specified otherwise
        // For simplicity, let's assume progress should be reset if not explicitly handled by frontend for this transition.
        $sql_update_order .= ", progress = 0"; // Reset progress
    }
    // Add more specific progress logic if needed based on transitions

    $sql_update_order .= " WHERE po_number = ?";
    $params_update[] = $po_number;
    $types_update .= "s";
    
    error_log("[update_order_status] Updating orders table status for PO: {$po_number} from {$old_status} to {$new_status}. SQL: {$sql_update_order}");
    $stmt_update_order = $conn->prepare($sql_update_order);
    if (!$stmt_update_order) throw new Exception("Prepare failed (update order status): " . $conn->error);
    
    $stmt_update_order->bind_param($types_update, ...$params_update);
    
    if (!$stmt_update_order->execute()) throw new Exception("Execute failed (update order status): " . $stmt_update_order->error);
    $stmt_update_order->close();

    // 5. Log the status change (Using your existing logic)
    error_log("[update_order_status] Logging status change for PO: {$po_number}");
    $stmt_log = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt_log) { /* Log error but don't rollback transaction for log failure */ error_log("Prepare failed (log status): " . $conn->error); }
    else {
        $stmt_log->bind_param("ssss", $po_number, $old_status, $new_status, $admin_username);
        if (!$stmt_log->execute()) { error_log("Execute failed (log status): " . $stmt_log->error); }
        $stmt_log->close();
    }

    // 6. Commit the transaction
    $conn->commit();
    $response['success'] = true;
    $response['message'] = "Order status updated to '{$new_status}' successfully.";
    if (!empty($material_adjustment_message)) {
        $response['material_message'] = $material_adjustment_message;
    }
    error_log("[update_order_status] Transaction COMMITTED for PO: {$po_number}");

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error updating status: " . $e->getMessage();
    error_log("[update_order_status] Transaction ROLLBACK for PO: {$po_number}. Error: " . $e->getMessage());
}

// --- Cleanup and Response ---
if (isset($conn) && $conn->thread_id) { // Check if connection is still valid before closing
    $conn->close();
}
echo json_encode($response);
exit; // Ensure script terminates
?>