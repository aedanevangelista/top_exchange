<?php
// Current Date: 2025-05-04 05:10:48 UTC
// Author: aedanevangelista (Modified by Copilot)

session_start();
include "db_connection.php"; // Adjust path if needed relative to this script
include "check_role.php";   // Adjust path if needed relative to this script

// --- Basic Security Check ---
// Ensure the user is logged in. Replace 'admin_user_id' if your session variable is different.
if (!isset($_SESSION['admin_user_id'])) {
    // Send JSON error response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit;
}

// --- Role Check ---
// Ensure the user has the necessary permission to update order statuses.
// Adjust the role name 'Orders' if needed.
// Assuming checkRole exits or throws an exception on failure.
try {
    checkRole('Orders');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $e->getMessage()]);
    exit;
}

// --- Response Setup ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred processing the request.'];

// --- Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['po_number']) || !isset($_POST['status'])) {
    $response['message'] = 'Missing required parameters (po_number or status).';
    echo json_encode($response);
    exit;
}

// --- Input Processing ---
$po_number = trim($_POST['po_number']);
$new_status = trim($_POST['status']);
// Optional flags for material handling (ensure they are treated as booleans)
$deduct_materials = isset($_POST['deduct_materials']) && $_POST['deduct_materials'] === '1';
$return_materials = isset($_POST['return_materials']) && $_POST['return_materials'] === '1';

// --- Input Validation ---
$allowed_statuses = ['Pending', 'Active', 'Rejected', 'For Delivery', 'Completed']; // Define all valid statuses
if (empty($po_number)) {
    $response['message'] = 'PO Number cannot be empty.';
    echo json_encode($response);
    exit;
}
if (!in_array($new_status, $allowed_statuses)) {
    $response['message'] = 'Invalid status provided.';
    echo json_encode($response);
    exit;
}

// --- Database Operations within a Transaction ---
$conn->begin_transaction();

try {
    $driver_id_to_update = null; // Variable to hold the driver ID if status becomes 'Completed'

    // 1. Check if the NEW status is 'Completed' to potentially decrement driver count
    if ($new_status === 'Completed') {
        // Find the driver assigned to this PO from the driver_assignments table
        $stmt_find_driver = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        if (!$stmt_find_driver) {
            // Use trigger_error for better logging if needed
            error_log("Prepare failed (find driver): " . $conn->error);
            throw new Exception("Database error preparing statement (find driver).");
        }
        $stmt_find_driver->bind_param("s", $po_number);
        if (!$stmt_find_driver->execute()) {
             error_log("Execute failed (find driver): " . $stmt_find_driver->error);
             throw new Exception("Database error executing statement (find driver).");
        }

        $result_driver = $stmt_find_driver->get_result();
        if ($row_driver = $result_driver->fetch_assoc()) {
            $driver_id_to_update = $row_driver['driver_id'];
        }
        $stmt_find_driver->close();

        // If a driver was found associated with this completed order, decrement their count
        if ($driver_id_to_update) {
            $stmt_update_driver = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
             if (!$stmt_update_driver) {
                 error_log("Prepare failed (update driver): " . $conn->error);
                 throw new Exception("Database error preparing statement (update driver).");
             }
            $stmt_update_driver->bind_param("i", $driver_id_to_update);
            if (!$stmt_update_driver->execute()) {
                 error_log("Execute failed (update driver): " . $stmt_update_driver->error);
                 throw new Exception("Database error executing statement (update driver).");
            }
            $stmt_update_driver->close();
            // Log the successful decrement action
            error_log("Decremented delivery count for driver ID: " . $driver_id_to_update . " due to PO: " . $po_number . " completion.");
        } else {
             error_log("No driver found assigned to PO: " . $po_number . " upon completion. No driver count decremented.");
        }
    }

    // 2. Placeholder for Material Deduction/Return Logic
    // IMPORTANT: Integrate your actual material handling logic here if needed.
    // Ensure any database operations within this logic are part of the transaction.
    if ($deduct_materials) {
        // Example: Your logic to deduct materials based on $po_number
         error_log("Placeholder: Deducting materials for PO: " . $po_number . " (Status change to: " . $new_status . ")");
         // Make sure this logic uses $conn and throws exceptions on failure
         // e.g., include_once 'material_handler.php'; deductMaterialsForOrder($po_number, $conn);
    }
    if ($return_materials) {
        // Example: Your logic to return materials based on $po_number
        error_log("Placeholder: Returning materials for PO: " . $po_number . " (Status change to: " . $new_status . ")");
        // Make sure this logic uses $conn and throws exceptions on failure
        // e.g., include_once 'material_handler.php'; returnMaterialsForOrder($po_number, $conn);
    }


    // 3. Update the Order Status itself
    $stmt_update_order = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
     if (!$stmt_update_order) {
         error_log("Prepare failed (update order): " . $conn->error);
         throw new Exception("Database error preparing statement (update order).");
     }
    $stmt_update_order->bind_param("ss", $new_status, $po_number);

    if ($stmt_update_order->execute()) {
        // Check if any rows were actually affected
        if ($stmt_update_order->affected_rows > 0) {
            // Commit transaction only if all steps were successful
            $conn->commit();
            $response['success'] = true;
            $response['message'] = "Order status updated to '{$new_status}' successfully.";
            if ($driver_id_to_update && $new_status === 'Completed') {
                 $response['message'] .= " Driver delivery count updated.";
            }
             error_log("Successfully updated status for PO {$po_number} to {$new_status}. Transaction committed.");
        } else {
             // No rows affected - maybe PO number didn't exist? Rollback.
             $conn->rollback();
             $response['message'] = "Order status update failed: PO Number '{$po_number}' not found.";
             error_log("Update failed: PO Number {$po_number} not found. Transaction rolled back.");
        }
    } else {
         error_log("Execute failed (update order): " . $stmt_update_order->error);
        throw new Exception("Database error executing statement (update order).");
    }
    $stmt_update_order->close();

} catch (Exception $e) {
    // Rollback transaction on any error during the try block
    $conn->rollback();
    $response['message'] = "Error updating status: " . $e->getMessage();
    // Log the detailed error
    error_log("Transaction Rollback - Error updating status for PO {$po_number} to {$new_status}: " . $e->getMessage());
}

// --- Cleanup and Response ---
$conn->close();
echo json_encode($response);
?>