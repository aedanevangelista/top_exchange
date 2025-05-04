<?php
session_start();
include "db_connection.php"; // Adjust path if needed
include "check_role.php";   // Adjust path if needed

// --- Security & Role Check ---
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
try {
    checkRole('Orders'); // Or appropriate role for assigning drivers
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $e->getMessage()]);
    exit;
}

// --- Response Setup ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

// --- Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['po_number']) || !isset($_POST['driver_id'])) {
    $response['message'] = 'Invalid request method or missing parameters.';
    echo json_encode($response);
    exit;
}

// --- Input Processing ---
$po_number = trim($_POST['po_number']);
$new_driver_id = filter_var(trim($_POST['driver_id']), FILTER_VALIDATE_INT); // Ensure it's an integer

if (empty($po_number) || $new_driver_id === false || $new_driver_id <= 0) {
    $response['message'] = 'Invalid PO Number or Driver ID.';
    echo json_encode($response);
    exit;
}

// --- Database Operations within a Transaction ---
$conn->begin_transaction();

try {
    $old_driver_id = null;

    // 1. Check if an assignment already exists for this PO
    $stmt_check = $conn->prepare("SELECT driver_id FROM driver_orders WHERE po_number = ?");
    if (!$stmt_check) throw new Exception("Prepare failed (check existing): " . $conn->error);
    $stmt_check->bind_param("s", $po_number);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
        $old_driver_id = $row_check['driver_id'];
    }
    $stmt_check->close();

    // 2. Handle Assignment Logic (Insert or Update)
    if ($old_driver_id !== null) {
        // Existing assignment found - Handle driver change
        if ($old_driver_id != $new_driver_id) {
            // Update the assignment record
            $stmt_update_assign = $conn->prepare("UPDATE driver_orders SET driver_id = ?, assigned_at = NOW() WHERE po_number = ?");
             if (!$stmt_update_assign) throw new Exception("Prepare failed (update assignment): " . $conn->error);
            $stmt_update_assign->bind_param("is", $new_driver_id, $po_number);
            if (!$stmt_update_assign->execute()) throw new Exception("Execute failed (update assignment): " . $stmt_update_assign->error);
            $stmt_update_assign->close();

            // Decrement old driver's count
            $stmt_dec = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
            if (!$stmt_dec) throw new Exception("Prepare failed (decrement driver): " . $conn->error);
            $stmt_dec->bind_param("i", $old_driver_id);
            if (!$stmt_dec->execute()) throw new Exception("Execute failed (decrement driver): " . $stmt_dec->error);
            $stmt_dec->close();
            error_log("Decremented delivery count for OLD driver ID: " . $old_driver_id . " due to change on PO: " . $po_number);

            // Increment new driver's count
            $stmt_inc = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
            if (!$stmt_inc) throw new Exception("Prepare failed (increment driver): " . $conn->error);
            $stmt_inc->bind_param("i", $new_driver_id);
             if (!$stmt_inc->execute()) throw new Exception("Execute failed (increment driver): " . $stmt_inc->error);
            $stmt_inc->close();
            error_log("Incremented delivery count for NEW driver ID: " . $new_driver_id . " due to assignment/change on PO: " . $po_number);

        } else {
            // Same driver assigned again - no change needed in counts or driver_orders
            error_log("Re-assigned same driver ID: " . $new_driver_id . " to PO: " . $po_number . ". No count change.");
        }
    } else {
        // No existing assignment - Insert new record
        $stmt_insert_assign = $conn->prepare("INSERT INTO driver_orders (driver_id, po_number, assigned_at) VALUES (?, ?, NOW())");
        if (!$stmt_insert_assign) throw new Exception("Prepare failed (insert assignment): " . $conn->error);
        $stmt_insert_assign->bind_param("is", $new_driver_id, $po_number);
        if (!$stmt_insert_assign->execute()) throw new Exception("Execute failed (insert assignment): " . $stmt_insert_assign->error);
        $stmt_insert_assign->close();

        // Increment new driver's count
        $stmt_inc = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
         if (!$stmt_inc) throw new Exception("Prepare failed (increment driver): " . $conn->error);
        $stmt_inc->bind_param("i", $new_driver_id);
        if (!$stmt_inc->execute()) throw new Exception("Execute failed (increment driver): " . $stmt_inc->error);
        $stmt_inc->close();
        error_log("Incremented delivery count for NEW driver ID: " . $new_driver_id . " due to assignment on PO: " . $po_number);
    }

    // 3. Update the 'orders' table to reflect assignment (optional but good practice)
    // You might store the driver_id or just a boolean flag. Here we set driver_assigned = 1.
    $stmt_update_order = $conn->prepare("UPDATE orders SET driver_assigned = 1 WHERE po_number = ?");
    if (!$stmt_update_order) throw new Exception("Prepare failed (update order flag): " . $conn->error);
    $stmt_update_order->bind_param("s", $po_number);
    if (!$stmt_update_order->execute()) throw new Exception("Execute failed (update order flag): " . $stmt_update_order->error);
    $stmt_update_order->close();

    // 4. Commit the transaction
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Driver assigned successfully.';
     error_log("Successfully assigned driver ID {$new_driver_id} to PO {$po_number}. Transaction committed.");


} catch (Exception $e) {
    // Rollback transaction on any error
    $conn->rollback();
    $response['message'] = "Error assigning driver: " . $e->getMessage();
    error_log("Transaction Rollback - Error assigning driver for PO {$po_number}: " . $e->getMessage());
}

// --- Cleanup and Response ---
$conn->close();
echo json_encode($response);
?>