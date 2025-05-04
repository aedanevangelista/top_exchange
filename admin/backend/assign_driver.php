<?php
session_start();
// --- IMPORTANT: Adjust path if your error log isn't catching web server errors ---
// ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Specify a writable path
// error_reporting(E_ALL);
// --- END IMPORTANT ---

include "db_connection.php"; // Adjust path if needed
include "check_role.php";   // Adjust path if needed

// --- Security & Role Check ---
if (!isset($_SESSION['admin_user_id'])) {
    error_log("[assign_driver] Authentication failed: No admin_user_id in session."); // DEBUG
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
try {
    checkRole('Orders'); // Or appropriate role for assigning drivers
} catch (Exception $e) {
    error_log("[assign_driver] Permission denied: " . $e->getMessage()); // DEBUG
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $e->getMessage()]);
    exit;
}

// --- Response Setup ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

// --- Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['po_number']) || !isset($_POST['driver_id'])) {
    error_log("[assign_driver] Invalid request: Method=" . $_SERVER['REQUEST_METHOD'] . ", POST=" . print_r($_POST, true)); // DEBUG
    $response['message'] = 'Invalid request method or missing parameters.';
    echo json_encode($response);
    exit;
}

// --- Input Processing ---
$po_number = trim($_POST['po_number']);
$new_driver_id = filter_var(trim($_POST['driver_id']), FILTER_VALIDATE_INT); // Ensure it's an integer
error_log("[assign_driver] Received PO: " . $po_number . ", Driver ID: " . $new_driver_id); // DEBUG

if (empty($po_number) || $new_driver_id === false || $new_driver_id <= 0) {
    error_log("[assign_driver] Invalid input values."); // DEBUG
    $response['message'] = 'Invalid PO Number or Driver ID.';
    echo json_encode($response);
    exit;
}

// --- Database Operations within a Transaction ---
error_log("[assign_driver] Starting transaction for PO: " . $po_number); // DEBUG
$conn->begin_transaction();

try {
    $old_driver_id = null;

    // 1. Check if an assignment already exists for this PO
    error_log("[assign_driver] Checking existing assignment for PO: " . $po_number); // DEBUG
    $stmt_check = $conn->prepare("SELECT driver_id FROM driver_orders WHERE po_number = ?");
    if (!$stmt_check) { // Check prepare failure
         error_log("[assign_driver] PREPARE FAILED (check existing): " . $conn->error); // DEBUG
         throw new Exception("Prepare failed (check existing): " . $conn->error);
    }
    $stmt_check->bind_param("s", $po_number);
    if (!$stmt_check->execute()) { // Check execute failure
         error_log("[assign_driver] EXECUTE FAILED (check existing): " . $stmt_check->error); // DEBUG
         throw new Exception("Execute failed (check existing): " . $stmt_check->error);
    }
    $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
        $old_driver_id = $row_check['driver_id'];
        error_log("[assign_driver] Found existing assignment. Old Driver ID: " . $old_driver_id); // DEBUG
    } else {
         error_log("[assign_driver] No existing assignment found for PO: " . $po_number); // DEBUG
    }
    $stmt_check->close();

    // 2. Handle Assignment Logic (Insert or Update)
    if ($old_driver_id !== null) {
        error_log("[assign_driver] Handling update/change logic."); // DEBUG
        // Existing assignment found - Handle driver change
        if ($old_driver_id != $new_driver_id) {
            error_log("[assign_driver] Driver ID changed from " . $old_driver_id . " to " . $new_driver_id); // DEBUG
            // Update the assignment record
            $stmt_update_assign = $conn->prepare("UPDATE driver_orders SET driver_id = ?, assigned_at = NOW() WHERE po_number = ?");
             if (!$stmt_update_assign) { error_log("[assign_driver] PREPARE FAILED (update assignment): " . $conn->error); throw new Exception("Prepare failed (update assignment): " . $conn->error); } // DEBUG
            $stmt_update_assign->bind_param("is", $new_driver_id, $po_number);
            if (!$stmt_update_assign->execute()) { error_log("[assign_driver] EXECUTE FAILED (update assignment): " . $stmt_update_assign->error); throw new Exception("Execute failed (update assignment): " . $stmt_update_assign->error); } // DEBUG
            $stmt_update_assign->close();
            error_log("[assign_driver] driver_orders table updated."); // DEBUG

            // Decrement old driver's count
            $stmt_dec = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
            if (!$stmt_dec) { error_log("[assign_driver] PREPARE FAILED (decrement driver): " . $conn->error); throw new Exception("Prepare failed (decrement driver): " . $conn->error); } // DEBUG
            $stmt_dec->bind_param("i", $old_driver_id);
            if (!$stmt_dec->execute()) { error_log("[assign_driver] EXECUTE FAILED (decrement driver): " . $stmt_dec->error); throw new Exception("Execute failed (decrement driver): " . $stmt_dec->error); } // DEBUG
            $stmt_dec->close();
            error_log("[assign_driver] Decremented delivery count for OLD driver ID: " . $old_driver_id); // DEBUG

            // Increment new driver's count
            $stmt_inc = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
            if (!$stmt_inc) { error_log("[assign_driver] PREPARE FAILED (increment driver): " . $conn->error); throw new Exception("Prepare failed (increment driver): " . $conn->error); } // DEBUG
            $stmt_inc->bind_param("i", $new_driver_id);
             if (!$stmt_inc->execute()) { error_log("[assign_driver] EXECUTE FAILED (increment driver): " . $stmt_inc->error); throw new Exception("Execute failed (increment driver): " . $stmt_inc->error); } // DEBUG
            $stmt_inc->close();
            error_log("[assign_driver] Incremented delivery count for NEW driver ID: " . $new_driver_id); // DEBUG

        } else {
            // Same driver assigned again - no change needed in counts or driver_orders
            error_log("[assign_driver] Re-assigned same driver ID: " . $new_driver_id . " to PO: " . $po_number . ". No count change."); // DEBUG
        }
    } else {
        error_log("[assign_driver] Handling insert logic."); // DEBUG
        // No existing assignment - Insert new record
        $stmt_insert_assign = $conn->prepare("INSERT INTO driver_orders (driver_id, po_number, assigned_at) VALUES (?, ?, NOW())");
        if (!$stmt_insert_assign) { error_log("[assign_driver] PREPARE FAILED (insert assignment): " . $conn->error); throw new Exception("Prepare failed (insert assignment): " . $conn->error); } // DEBUG
        $stmt_insert_assign->bind_param("is", $new_driver_id, $po_number);
        if (!$stmt_insert_assign->execute()) { error_log("[assign_driver] EXECUTE FAILED (insert assignment): " . $stmt_insert_assign->error); throw new Exception("Execute failed (insert assignment): " . $stmt_insert_assign->error); } // DEBUG
        $stmt_insert_assign->close();
        error_log("[assign_driver] Inserted into driver_orders."); // DEBUG

        // Increment new driver's count
        $stmt_inc = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
         if (!$stmt_inc) { error_log("[assign_driver] PREPARE FAILED (increment driver): " . $conn->error); throw new Exception("Prepare failed (increment driver): " . $conn->error); } // DEBUG
        $stmt_inc->bind_param("i", $new_driver_id);
        if (!$stmt_inc->execute()) { error_log("[assign_driver] EXECUTE FAILED (increment driver): " . $stmt_inc->error); throw new Exception("Execute failed (increment driver): " . $stmt_inc->error); } // DEBUG
        $stmt_inc->close();
        error_log("[assign_driver] Incremented delivery count for NEW driver ID: " . $new_driver_id); // DEBUG
    }

    // 3. Update the 'orders' table to reflect assignment (optional but good practice)
    error_log("[assign_driver] Updating orders table flag for PO: " . $po_number); // DEBUG
    $stmt_update_order = $conn->prepare("UPDATE orders SET driver_assigned = 1 WHERE po_number = ?");
    if (!$stmt_update_order) { error_log("[assign_driver] PREPARE FAILED (update order flag): " . $conn->error); throw new Exception("Prepare failed (update order flag): " . $conn->error); } // DEBUG
    $stmt_update_order->bind_param("s", $po_number);
    if (!$stmt_update_order->execute()) { error_log("[assign_driver] EXECUTE FAILED (update order flag): " . $stmt_update_order->error); throw new Exception("Execute failed (update order flag): " . $stmt_update_order->error); } // DEBUG
    $stmt_update_order->close();

    // 4. Commit the transaction
    error_log("[assign_driver] Attempting to commit transaction for PO: " . $po_number); // DEBUG
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Driver assigned successfully.';
    error_log("[assign_driver] Transaction COMMITTED successfully for PO: " . $po_number); // DEBUG


} catch (Exception $e) {
    // Rollback transaction on any error
    error_log("[assign_driver] Exception caught: " . $e->getMessage() . " - Rolling back transaction for PO: " . $po_number); // DEBUG
    $conn->rollback();
    $response['message'] = "Error assigning driver: " . $e->getMessage();
    // Keep the error_log from the original suggestion
    error_log("[assign_driver] Transaction Rollback - Error assigning driver for PO {$po_number}: " . $e->getMessage());
}

// --- Cleanup and Response ---
$conn->close();
error_log("[assign_driver] Sending response for PO: " . $po_number . " - " . json_encode($response)); // DEBUG
echo json_encode($response);
?>