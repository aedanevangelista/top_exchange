<?php
session_start();
include "db_connection.php"; // Adjust path if needed
include "check_role.php";   // Adjust path if needed
// Optional: Include material handling functions if they are in separate files
// include "material_handler.php";

// --- Security & Role Check ---
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
$admin_username = $_SESSION['admin_username'] ?? 'admin'; // Get admin username for logging

try {
    checkRole('Orders'); // Role required to change order status
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $e->getMessage()]);
    exit;
}

// --- Response Setup ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];
$deduction_message = '';
$return_message = '';

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
// Get flags for material handling (expecting '1' or '0')
$deduct_materials_flag = isset($_POST['deduct_materials']) && $_POST['deduct_materials'] === '1';
$return_materials_flag = isset($_POST['return_materials']) && $_POST['return_materials'] === '1';

$allowed_statuses = ['Pending', 'Active', 'For Delivery', 'In Transit', 'Completed', 'Rejected'];
if (empty($po_number) || !in_array($new_status, $allowed_statuses)) {
    $response['message'] = 'Invalid PO Number or Status provided.';
    error_log("[update_order_status] Invalid input: PO=" . $po_number . ", Status=" . $new_status);
    echo json_encode($response);
    exit;
}

error_log("[update_order_status] Attempting update for PO: {$po_number} to Status: {$new_status} by Admin: {$admin_username}. Deduct: {$deduct_materials_flag}, Return: {$return_materials_flag}");

// --- Database Operations within a Transaction ---
$conn->begin_transaction();

try {
    // 1. Get current order status and details (like the 'orders' JSON for material handling)
    $stmt_get_order = $conn->prepare("SELECT status, orders FROM orders WHERE po_number = ?");
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

    // Prevent changing status if it's already the target status
    if ($old_status === $new_status) {
        // Commit nothing, but report success as no change was needed
        $conn->commit(); // Commit the empty transaction
        $response['success'] = true;
        $response['message'] = "Order status is already '{$new_status}'. No change made.";
        error_log("[update_order_status] No status change needed for PO: {$po_number}. Already {$new_status}.");
        echo json_encode($response);
        exit;
    }

    // 2. Handle Material Deduction/Return based on flags
    if ($deduct_materials_flag) {
        error_log("[update_order_status] Deduct materials flag is TRUE for PO: {$po_number}");
        // Call your function or include logic here
        // Example: deduct_materials_for_order($conn, $po_number, $orders_json);
        $deduction_message = 'Inventory deduction processed.'; // Set success message
        // If deduction fails, throw an exception: throw new Exception("Material deduction failed.");
    } elseif ($return_materials_flag) {
        error_log("[update_order_status] Return materials flag is TRUE for PO: {$po_number}");
        // Call your function or include logic here
        // Example: return_materials_for_order($conn, $po_number, $orders_json);
        $return_message = 'Inventory return processed.'; // Set success message
        // If return fails, throw an exception: throw new Exception("Material return failed.");
    }

    // 3. Decrement Driver Count if status changes to 'Completed'
    $driver_id_to_update = null;
    if ($new_status === 'Completed') {
        error_log("[update_order_status] New status is 'Completed'. Checking driver assignment for PO: {$po_number}");
        // *** USE driver_assignments TABLE ***
        $stmt_find_driver = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        if (!$stmt_find_driver) throw new Exception("Prepare failed (find driver): " . $conn->error);

        $stmt_find_driver->bind_param("s", $po_number);
        if (!$stmt_find_driver->execute()) throw new Exception("Execute failed (find driver): " . $stmt_find_driver->error);

        $result_driver = $stmt_find_driver->get_result();
        if ($row_driver = $result_driver->fetch_assoc()) {
            $driver_id_to_update = $row_driver['driver_id'];
            error_log("[update_order_status] Found Driver ID: {$driver_id_to_update} assigned to completed PO: {$po_number}");
        } else {
             error_log("[update_order_status] No driver found in driver_assignments for completed PO: {$po_number}.");
        }
        $stmt_find_driver->close();

        // If a driver was found, decrement their count
        if ($driver_id_to_update) {
            $stmt_update_driver = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
            if (!$stmt_update_driver) throw new Exception("Prepare failed (update driver): " . $conn->error);

            $stmt_update_driver->bind_param("i", $driver_id_to_update);
            if (!$stmt_update_driver->execute()) throw new Exception("Execute failed (update driver): " . $stmt_update_driver->error);
            $stmt_update_driver->close();
            error_log("[update_order_status] Decremented delivery count for driver ID: {$driver_id_to_update} for PO: {$po_number}.");
        }
    }

    // 4. Update the Order Status in the 'orders' table
    error_log("[update_order_status] Updating orders table status for PO: {$po_number} from {$old_status} to {$new_status}");
    $stmt_update_order = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    if (!$stmt_update_order) throw new Exception("Prepare failed (update order status): " . $conn->error);
    $stmt_update_order->bind_param("ss", $new_status, $po_number);
    if (!$stmt_update_order->execute()) throw new Exception("Execute failed (update order status): " . $stmt_update_order->error);
    $stmt_update_order->close();

    // 5. Log the status change
    error_log("[update_order_status] Logging status change for PO: {$po_number}");
    $stmt_log = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt_log) throw new Exception("Prepare failed (log status): " . $conn->error);
    $stmt_log->bind_param("ssss", $po_number, $old_status, $new_status, $admin_username);
    if (!$stmt_log->execute()) throw new Exception("Execute failed (log status): " . $stmt_log->error); // Log failure but don't necessarily rollback unless critical
    $stmt_log->close();

    // 6. Commit the transaction
    $conn->commit();
    $response['success'] = true;
    $response['message'] = "Order status updated to '{$new_status}' successfully.";
    // Include material adjustment messages if they occurred
    if ($deduction_message) $response['deduction_message'] = $deduction_message;
    if ($return_message) $response['return_message'] = $return_message;
    error_log("[update_order_status] Transaction COMMITTED for PO: {$po_number}");

} catch (Exception $e) {
    // Rollback transaction on any error
    $conn->rollback();
    $response['message'] = "Error updating status: " . $e->getMessage();
    error_log("[update_order_status] Transaction ROLLBACK for PO: {$po_number}. Error: " . $e->getMessage());
}

// --- Cleanup and Response ---
$conn->close();
echo json_encode($response);
?>