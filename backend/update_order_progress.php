<?php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-05-04 09:20:00
// Current User's Login: aedanevangelista

session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the user has the necessary permissions
if (!isset($_SESSION['admin_user_id'])) {
    // Set header for JSON response
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Set header for JSON response (do this early)
header('Content-Type: application/json; charset=utf-8');

// Get JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

// --- MODIFIED: Check for 'overall_progress' instead of 'progress' ---
if (!isset($data['po_number']) || !isset($data['overall_progress'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (po_number or overall_progress)']);
    exit;
}

$po_number = $data['po_number'];
// --- MODIFIED: Use 'overall_progress' ---
$progress = intval($data['overall_progress']);

// Get other data, ensuring keys match what JS sends
$completed_items = isset($data['completed_items']) ? json_encode($data['completed_items']) : null;
// --- MODIFIED: Use 'quantity_progress' ---
$quantity_progress_data = isset($data['quantity_progress']) ? json_encode($data['quantity_progress']) : null;

// --- REMOVED/COMMENTED: 'item_progress_percentages' is not directly sent by saveProgressChanges JS ---
// $item_progress_percentages = isset($data['item_progress_percentages']) ? json_encode($data['item_progress_percentages']) : null;
// Let's decide if we need to store something else here, or adjust the query.
// For now, let's assume we don't store item_progress_percentages separately.

// Optional driver/delivery data (not sent by saveProgressChanges, but check anyway)
$driver_id = isset($data['driver_id']) ? intval($data['driver_id']) : 0;
$auto_delivery = isset($data['auto_delivery']) && $data['auto_delivery'] === true;

// --- Database Connection Check (Good Practice) ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     error_log("Update Order Progress - DB Connection Error: " . ($conn->connect_error ?? 'Unknown error'));
     echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
     exit;
}
// --- End DB Check ---

$conn->begin_transaction();

try {
    // Update the order progress
    // --- MODIFIED: Removed item_progress_percentages from query/binding ---
    $stmt = $conn->prepare("UPDATE orders SET progress = ?, completed_items = ?, quantity_progress_data = ? WHERE po_number = ?");
    if (!$stmt) throw new mysqli_sql_exception("Prepare failed: " . $conn->error, $conn->errno); // Check prepare

    // --- MODIFIED: Adjusted binding types/variables (removed one 's') ---
    $stmt->bind_param("isss", $progress, $completed_items, $quantity_progress_data, $po_number);

    if (!$stmt->execute()) throw new mysqli_sql_exception("Execute failed: " . $stmt->error, $stmt->errno); // Check execute
    $stmt->close();

    // --- The rest of the driver assignment and auto-delivery logic remains the same ---
    // If a driver ID is provided, assign the driver (This part won't run from saveProgressChanges)
    if ($driver_id) {
        // ... (driver assignment logic as before) ...
         // First check if the driver exists and is available
        $checkDriverStmt = $conn->prepare("SELECT id FROM drivers WHERE id = ? AND availability = 'Available' AND current_deliveries < 20");
        if (!$checkDriverStmt) throw new mysqli_sql_exception("Prepare failed (Check Driver): " . $conn->error, $conn->errno);
        $checkDriverStmt->bind_param("i", $driver_id);
        if (!$checkDriverStmt->execute()) throw new mysqli_sql_exception("Execute failed (Check Driver): " . $checkDriverStmt->error, $checkDriverStmt->errno);
        $checkDriverResult = $checkDriverStmt->get_result();

        if ($checkDriverResult->num_rows === 0) {
            throw new Exception('Driver is not available or has reached maximum deliveries'); // Throw exception for rollback
        }
        $checkDriverStmt->close();

        // Check if there's already a driver assigned to this order
        $checkAssignmentStmt = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        if (!$checkAssignmentStmt) throw new mysqli_sql_exception("Prepare failed (Check Assignment): " . $conn->error, $conn->errno);
        $checkAssignmentStmt->bind_param("s", $po_number);
        if (!$checkAssignmentStmt->execute()) throw new mysqli_sql_exception("Execute failed (Check Assignment): " . $checkAssignmentStmt->error, $checkAssignmentStmt->errno);
        $checkAssignmentResult = $checkAssignmentStmt->get_result();
        $current_assignment_driver_id = null;
        if ($row = $checkAssignmentResult->fetch_assoc()) {
            $current_assignment_driver_id = (int)$row['driver_id'];
        }
        $checkAssignmentStmt->close();


        // Only proceed if the driver is changing or wasn't assigned
        if ($current_assignment_driver_id !== $driver_id) {
            // Decrement old driver if exists and different
            if ($current_assignment_driver_id !== null && $current_assignment_driver_id > 0) {
                 $updateOldDriverStmt = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?");
                 if (!$updateOldDriverStmt) throw new mysqli_sql_exception("Prepare failed (Decrement Old Driver): " . $conn->error, $conn->errno);
                 $updateOldDriverStmt->bind_param("i", $current_assignment_driver_id);
                 if (!$updateOldDriverStmt->execute()) throw new mysqli_sql_exception("Execute failed (Decrement Old Driver): " . $updateOldDriverStmt->error, $updateOldDriverStmt->errno);
                 $updateOldDriverStmt->close();
            }

            // Upsert assignment (Insert or Update)
            $upsertAssignmentStmt = $conn->prepare(
                "INSERT INTO driver_assignments (po_number, driver_id, assigned_at, status) VALUES (?, ?, NOW(), 'Assigned')
                 ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), assigned_at = NOW(), status = 'Assigned'"
            );
             if (!$upsertAssignmentStmt) throw new mysqli_sql_exception("Prepare failed (Upsert Assignment): " . $conn->error, $conn->errno);
            $upsertAssignmentStmt->bind_param("si", $po_number, $driver_id);
            if (!$upsertAssignmentStmt->execute()) throw new mysqli_sql_exception("Execute failed (Upsert Assignment): " . $upsertAssignmentStmt->error, $upsertAssignmentStmt->errno);
            $upsertAssignmentStmt->close();

            // Update the driver_assigned flag in the orders table
            $updateOrderStmt = $conn->prepare("UPDATE orders SET driver_assigned = 1 WHERE po_number = ?");
            if (!$updateOrderStmt) throw new mysqli_sql_exception("Prepare failed (Update Order Flag): " . $conn->error, $conn->errno);
            $updateOrderStmt->bind_param("s", $po_number);
            if (!$updateOrderStmt->execute()) throw new mysqli_sql_exception("Execute failed (Update Order Flag): " . $updateOrderStmt->error, $updateOrderStmt->errno);
            $updateOrderStmt->close();

            // Increment the new driver's current_deliveries count
            $updateDriverStmt = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
            if (!$updateDriverStmt) throw new mysqli_sql_exception("Prepare failed (Increment New Driver): " . $conn->error, $conn->errno);
            $updateDriverStmt->bind_param("i", $driver_id);
            if (!$updateDriverStmt->execute()) throw new mysqli_sql_exception("Execute failed (Increment New Driver): " . $updateDriverStmt->error, $updateDriverStmt->errno);
            $updateDriverStmt->close();
        }
    }

    // If progress is 100% and auto_delivery is true, check if a driver is assigned
    // (This part also won't run from saveProgressChanges unless JS is modified to send auto_delivery=true)
    if ($auto_delivery && $progress === 100) {
        // ... (auto-delivery logic as before) ...
        // Check if a driver is assigned to this order
        $checkDriverAssignedStmt = $conn->prepare("SELECT driver_assigned FROM orders WHERE po_number = ?");
        if (!$checkDriverAssignedStmt) throw new mysqli_sql_exception("Prepare failed (Check Driver Assigned): " . $conn->error, $conn->errno);
        $checkDriverAssignedStmt->bind_param("s", $po_number);
         if (!$checkDriverAssignedStmt->execute()) throw new mysqli_sql_exception("Execute failed (Check Driver Assigned): " . $checkDriverAssignedStmt->error, $checkDriverAssignedStmt->errno);
        $checkDriverAssignedResult = $checkDriverAssignedStmt->get_result();

        if ($checkDriverAssignedResult->num_rows === 0) {
            throw new Exception("Order not found for auto-delivery check");
        }

        $row = $checkDriverAssignedResult->fetch_assoc();
        $driver_assigned = (bool)$row['driver_assigned'];
        $checkDriverAssignedStmt->close();

        // If a driver is assigned and progress is 100%, update order status to "For Delivery"
        if ($driver_assigned) { // Progress is already checked ($progress === 100)
            $updateStatusStmt = $conn->prepare("UPDATE orders SET status = 'For Delivery' WHERE po_number = ? AND status = 'Active'"); // Add status check
             if (!$updateStatusStmt) throw new mysqli_sql_exception("Prepare failed (Update Status to Delivery): " . $conn->error, $conn->errno);
            $updateStatusStmt->bind_param("s", $po_number);
            if (!$updateStatusStmt->execute()) throw new mysqli_sql_exception("Execute failed (Update Status to Delivery): " . $updateStatusStmt->error, $updateStatusStmt->errno);
            $affectedRows = $updateStatusStmt->affected_rows; // Check if status was actually changed
            $updateStatusStmt->close();

            // Create a log entry for the status change only if the status was updated
            if ($affectedRows > 0) {
                $createLogStmt = $conn->prepare(
                    "INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at)
                    VALUES (?, 'Active', 'For Delivery', ?, NOW())"
                );
                if (!$createLogStmt) throw new mysqli_sql_exception("Prepare failed (Create Status Log): " . $conn->error, $conn->errno);
                $changed_by = $_SESSION['username'] ?? 'system_auto'; // Indicate auto change
                $createLogStmt->bind_param("ss", $po_number, $changed_by);
                 if (!$createLogStmt->execute()) throw new mysqli_sql_exception("Execute failed (Create Status Log): " . $createLogStmt->error, $createLogStmt->errno);
                $createLogStmt->close();
                 error_log("Order {$po_number} automatically set to 'For Delivery'.");
            }
        } else {
             error_log("Order {$po_number} reached 100% progress but no driver assigned for auto-delivery.");
        }
    }

    // If all successful, commit
    if ($conn->commit()) {
        echo json_encode(['success' => true]);
    } else {
         throw new mysqli_sql_exception("Transaction commit failed: " . $conn->error, $conn->errno);
    }

} catch (Exception $e) { // Catch both mysqli_sql_exception and general Exception
    $conn->rollback();
    error_log("Update Order Progress Failed: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString()); // Log detailed error
    echo json_encode(['success' => false, 'message' => 'Failed to update order progress: ' . $e->getMessage()]);
}

// Close connection if still open
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
}
exit; // Ensure script stops here
?>