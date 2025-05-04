<?php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-05-04 13:15:45
// Current User's Login: aedanevangelista

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Parse JSON input
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Include database connection - Ensure this file ONLY establishes $conn and doesn't output anything
include_once __DIR__ . '/db_connection.php';

// --- JSON Response Helper Function ---
function sendJsonResponse($success, $message, $data = null) {
    // Prevent any prior output from interfering (important now that display_errors is off)
    if (headers_sent()) {
        error_log("sendJsonResponse failed: Headers already sent.");
        // Avoid echoing more if headers are sent, maybe just log
        exit;
    }
    header('Content-Type: application/json; charset=utf-8'); // Specify charset
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); // Add flags for readability/compatibility

    // Optional: Close DB connection here if not handled elsewhere
    // if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && $GLOBALS['conn']->ping()) {
    //    $GLOBALS['conn']->close();
    // }
    exit; // IMPORTANT: Stop script execution after sending JSON
}
// **** END FUNCTION DEFINITION ****


// Basic security check - ensure user is logged in and is admin/staff
// Adapt role check as needed
if (!isset($_SESSION['admin_user_id'])) { // Example: Only allow admins
    // Using sendJsonResponse for consistency, even for auth errors
    sendJsonResponse(false, 'Access Denied. Admin privileges required.', ['error_code' => 'AUTH_REQUIRED']);
}

// Check request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(false, 'Invalid request method.', ['error_code' => 'INVALID_METHOD']);
}

// Check required parameters
if (!isset($data['po_number']) || !isset($data['driver_id'])) {
    sendJsonResponse(false, 'Missing required parameters (PO Number or Driver ID).', ['error_code' => 'MISSING_PARAMS']);
}

// Sanitize inputs
$po_number = trim($data['po_number']);
$driver_id = filter_var(trim($data['driver_id']), FILTER_VALIDATE_INT);

if ($driver_id === false || $driver_id <= 0) {
    sendJsonResponse(false, 'Invalid Driver ID provided.', ['error_code' => 'INVALID_DRIVER_ID']);
}
// Check if po_number is empty after trimming
if (empty($po_number)) {
    sendJsonResponse(false, 'Invalid PO Number provided.', ['error_code' => 'INVALID_PO_NUMBER']);
}

// Database connection check
// Check if $conn was set by the include file
if (!isset($conn) || !($conn instanceof mysqli)) {
     // If $conn isn't set, the include might have failed or $conn has a different name
     error_log("Assign Driver - DB Connection variable \$conn is not set or not a mysqli object after include.");
     // Don't check $conn->connect_error if $conn isn't a valid object
     sendJsonResponse(false, 'Database connection failed (Object not available).', ['error_code' => 'DB_CONNECTION_FAILED']);
} elseif ($conn->connect_error) {
    // If $conn is set but connection failed
     error_log("Assign Driver - DB Connection Error: " . $conn->connect_error);
     sendJsonResponse(false, 'Database connection failed: ' . $conn->connect_error, ['error_code' => 'DB_CONNECTION_FAILED']);
}


// --- Database Operations ---
// Check if $conn is valid before starting transaction
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    // Error already logged and potentially response sent, but double-check
    error_log("Assign Driver - Cannot begin transaction, invalid DB connection.");
    // Ensure response is sent if not already
    if (!headers_sent()) { // Check if response was already sent by earlier check
       sendJsonResponse(false, 'Database connection failed before transaction.', ['error_code' => 'DB_CONNECTION_FAILED']);
    }
    exit; // Stop execution
}

$conn->begin_transaction();
$old_driver_id = 0; // Variable to store the ID of the previously assigned driver, if any

try {
    error_log("Assign Driver Attempt: PO='{$po_number}', New Driver='{$driver_id}'");

    // 1. Check if the order exists and has a valid status for driver assignment
    $sqlCheckOrder = "SELECT status, driver_assigned FROM orders WHERE po_number = ?";
    $stmtCheck = $conn->prepare($sqlCheckOrder);
    // Check prepare result
    if (!$stmtCheck) throw new mysqli_sql_exception("DB prepare error (Check Order): " . $conn->error, $conn->errno);

    $stmtCheck->bind_param("s", $po_number);
    $stmtCheck->execute();
    // Check execute result (though fetch is usually where errors appear if execute worked)
    if ($stmtCheck->errno) throw new mysqli_sql_exception("DB execute error (Check Order): " . $stmtCheck->error, $stmtCheck->errno);

    $stmtCheck->bind_result($current_status, $currently_assigned_flag);
    $fetch_result = $stmtCheck->fetch();
    // Check fetch result
    if ($fetch_result === false) { // Error during fetch
         throw new mysqli_sql_exception("DB fetch error (Check Order): " . $stmtCheck->error, $stmtCheck->errno);
    }
    if ($fetch_result === null) { // No rows found
        $stmtCheck->close(); // Close statement before throwing exception
        throw new Exception("Order with PO Number '{$po_number}' not found.");
    }
    // Row found, close statement
    $stmtCheck->close();

    // Check for potential undefined variable if fetch failed (though exception should cover this)
    if (!isset($current_status)) {
         throw new Exception("Failed to fetch current order status after successful fetch check."); // Should not happen if fetch worked
    }

    // MODIFIED: Changed the status check to allow 'For Delivery' or 'In Transit' 
    $allowedStatuses = ['For Delivery', 'In Transit'];
    if (!in_array($current_status, $allowedStatuses)) {
         throw new Exception("Cannot assign driver. Order status is '{$current_status}', must be 'For Delivery' or 'In Transit'.");
    }

    // 2. Find the previously assigned driver ID (if any)
    $sqlFindOld = "SELECT driver_id FROM driver_assignments WHERE po_number = ?";
    $stmtFindOld = $conn->prepare($sqlFindOld);
    if (!$stmtFindOld) throw new mysqli_sql_exception("DB prepare error (Find Old Driver): " . $conn->error, $conn->errno);

    $stmtFindOld->bind_param("s", $po_number);
    $stmtFindOld->execute();
    if ($stmtFindOld->errno) throw new mysqli_sql_exception("DB execute error (Find Old Driver): " . $stmtFindOld->error, $stmtFindOld->errno);

    $stmtFindOld->bind_result($old_driver_id_result);
    $fetch_old_result = $stmtFindOld->fetch();
     if ($fetch_old_result === false) { // Error during fetch
         throw new mysqli_sql_exception("DB fetch error (Find Old Driver): " . $stmtFindOld->error, $stmtFindOld->errno);
    }
    if ($fetch_old_result === true) { // Row found
        $old_driver_id = (int)$old_driver_id_result; // Store the old driver ID
    } else {
        // No row found or null fetched, $old_driver_id remains 0, which is correct.
    }
    $stmtFindOld->close();
    error_log("Old Driver ID found: {$old_driver_id}");


    // 3. Check if the new driver is the same as the old one
    if ($old_driver_id === $driver_id) {
         $conn->rollback(); // No changes needed
         error_log("No driver change needed (New driver ID {$driver_id} is same as old {$old_driver_id}).");
         sendJsonResponse(true, 'Driver assignment remains the same.');
         // Exit is handled by sendJsonResponse
    }


    // 4. Upsert (Update or Insert) the assignment in driver_assignments
    $sqlUpsert = "INSERT INTO driver_assignments (po_number, driver_id, assigned_at) VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), assigned_at = NOW()";
    $stmtUpsert = $conn->prepare($sqlUpsert);
    if (!$stmtUpsert) throw new mysqli_sql_exception("DB prepare error (Upsert Assignment): " . $conn->error, $conn->errno);

    $stmtUpsert->bind_param("si", $po_number, $driver_id);
    if (!$stmtUpsert->execute()) {
        $err = $stmtUpsert->error; $errno = $stmtUpsert->errno; $stmtUpsert->close(); // Close before throw
        throw new mysqli_sql_exception("DB execute error (Upsert Assignment): " . $err, $errno);
    }
    $affectedRowsUpsert = $stmtUpsert->affected_rows;
    $stmtUpsert->close();
    error_log("Driver assignment upserted/updated. Affected rows: {$affectedRowsUpsert}");


    // 5. Update the driver_assigned flag in the orders table
    $sqlUpdateOrder = "UPDATE orders SET driver_assigned = 1 WHERE po_number = ?";
    $stmtUpdateOrder = $conn->prepare($sqlUpdateOrder);
    if (!$stmtUpdateOrder) throw new mysqli_sql_exception("DB prepare error (Update Order Flag): " . $conn->error, $conn->errno);

    $stmtUpdateOrder->bind_param("s", $po_number);
    if (!$stmtUpdateOrder->execute()) {
        $err = $stmtUpdateOrder->error; $errno = $stmtUpdateOrder->errno; $stmtUpdateOrder->close(); // Close before throw
        throw new mysqli_sql_exception("DB execute error (Update Order Flag): " . $err, $errno);
    }
    $stmtUpdateOrder->close();
    error_log("Order flag updated for PO {$po_number}");


    // 6. Increment current_deliveries for the new driver
    $sqlUpdateNewDriver = "UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?";
    $stmtUpdateNew = $conn->prepare($sqlUpdateNewDriver);
     if (!$stmtUpdateNew) throw new mysqli_sql_exception("DB prepare error (Increment New Driver): " . $conn->error, $conn->errno);

    $stmtUpdateNew->bind_param("i", $driver_id);
    if (!$stmtUpdateNew->execute()) {
        $err = $stmtUpdateNew->error; $errno = $stmtUpdateNew->errno; $stmtUpdateNew->close(); // Close before throw
        throw new mysqli_sql_exception("DB execute error (Increment New Driver): " . $err, $errno);
    }
    $stmtUpdateNew->close();
    error_log("Incremented delivery count for new driver ID {$driver_id}");


    // 7. Decrement current_deliveries for the old driver (if there was one and it's different from the new one)
    if ($old_driver_id > 0 && $old_driver_id !== $driver_id) {
        $sqlUpdateOldDriver = "UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?";
        $stmtUpdateOld = $conn->prepare($sqlUpdateOldDriver);
        if (!$stmtUpdateOld) throw new mysqli_sql_exception("DB prepare error (Decrement Old Driver): " . $conn->error, $conn->errno);

        $stmtUpdateOld->bind_param("i", $old_driver_id);
        if (!$stmtUpdateOld->execute()) {
             $err = $stmtUpdateOld->error; $errno = $stmtUpdateOld->errno; $stmtUpdateOld->close(); // Close before throw
            throw new mysqli_sql_exception("DB execute error (Decrement Old Driver): " . $err, $errno);
        }
        $stmtUpdateOld->close();
        error_log("Decremented delivery count for old driver ID {$old_driver_id}");
    }

    // If all queries succeeded, commit the transaction
    if ($conn->commit()) {
        error_log("Transaction committed for driver assignment PO {$po_number}");
        // **** CALLING sendJsonResponse ON SUCCESS ****
        sendJsonResponse(true, 'Driver assigned successfully.'); // Exit is handled inside
    } else {
         throw new mysqli_sql_exception("Transaction commit failed: " . $conn->error, $conn->errno);
    }


} catch (mysqli_sql_exception $e) {
    // Attempt to rollback
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) { // Check connection before rollback
        $conn->rollback();
    }
    $error_message = 'Database error during assignment: ' . $e->getMessage();
    error_log("!!! SQL Exception during driver assignment: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString()); // Log trace
    // **** CALLING sendJsonResponse ON ERROR ****
    sendJsonResponse(false, $error_message, ['error_code' => 'DB_ERROR', 'sql_errno' => $e->getCode()]); // Exit is handled inside

} catch (Exception $e) {
     // Attempt to rollback
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) { // Check connection before rollback
        $conn->rollback();
    }
    $error_message = 'An error occurred: ' . $e->getMessage();
    error_log("!!! General Exception during driver assignment: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString()); // Log trace
     // **** CALLING sendJsonResponse ON ERROR ****
    sendJsonResponse(false, $error_message, ['error_code' => 'GENERAL_ERROR']); // Exit is handled inside
}

// Final connection close check (might be redundant)
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
    error_log("Assign Driver - Explicit DB close at end.");
}

?>