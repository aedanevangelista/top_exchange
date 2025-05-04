<?php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-05-04 08:29:48
// Current User's Login: aedanevangelista

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection - Ensure this file ONLY establishes $conn and doesn't output anything
include_once __DIR__ . '/db_connection.php';

// --- JSON Response Helper Function ---
// **** ADDED THIS FUNCTION DEFINITION ****
function sendJsonResponse($success, $message, $data = null) {
    // Prevent any prior output from interfering
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
    // No need for exit here, it's in sendJsonResponse
}

// Check request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJsonResponse(false, 'Invalid request method.', ['error_code' => 'INVALID_METHOD']);
    // No need for exit here
}

// Check required parameters
if (!isset($_POST['po_number']) || !isset($_POST['driver_id'])) {
    sendJsonResponse(false, 'Missing required parameters (PO Number or Driver ID).', ['error_code' => 'MISSING_PARAMS']);
    // No need for exit here
}

// Sanitize inputs
$po_number = filter_var(trim($_POST['po_number']), FILTER_SANITIZE_STRING);
$driver_id = filter_var(trim($_POST['driver_id']), FILTER_VALIDATE_INT);

if ($driver_id === false || $driver_id <= 0) {
    sendJsonResponse(false, 'Invalid Driver ID provided.', ['error_code' => 'INVALID_DRIVER_ID']);
    // No need for exit here
}
if (empty($po_number)) {
    sendJsonResponse(false, 'Invalid PO Number provided.', ['error_code' => 'INVALID_PO_NUMBER']);
    // No need for exit here
}

// Database connection check
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     error_log("Assign Driver - DB Connection Error: " . ($conn->connect_error ?? 'Unknown error'));
     sendJsonResponse(false, 'Database connection failed.', ['error_code' => 'DB_CONNECTION_FAILED']);
     // No need for exit here
}

// --- Database Operations ---
$conn->begin_transaction();
$old_driver_id = 0; // Variable to store the ID of the previously assigned driver, if any

try {
    error_log("Assign Driver Attempt: PO='{$po_number}', New Driver='{$driver_id}'");

    // 1. Check if the order exists and is 'Active' (only active orders can have drivers assigned/changed)
    $sqlCheckOrder = "SELECT status, driver_assigned FROM orders WHERE po_number = ?";
    $stmtCheck = $conn->prepare($sqlCheckOrder);
    if (!$stmtCheck) throw new mysqli_sql_exception("DB prepare error (Check Order): " . $conn->error, $conn->errno);
    $stmtCheck->bind_param("s", $po_number);
    $stmtCheck->execute();
    $stmtCheck->bind_result($current_status, $currently_assigned_flag);
    if (!$stmtCheck->fetch()) {
        $stmtCheck->close();
        throw new Exception("Order with PO Number '{$po_number}' not found.");
    }
    $stmtCheck->close();

    if ($current_status !== 'Active') {
         throw new Exception("Cannot assign driver. Order status is '{$current_status}', must be 'Active'.");
    }

    // 2. Find the previously assigned driver ID (if any)
    $sqlFindOld = "SELECT driver_id FROM driver_assignments WHERE po_number = ?";
    $stmtFindOld = $conn->prepare($sqlFindOld);
    if (!$stmtFindOld) throw new mysqli_sql_exception("DB prepare error (Find Old Driver): " . $conn->error, $conn->errno);
    $stmtFindOld->bind_param("s", $po_number);
    $stmtFindOld->execute();
    $stmtFindOld->bind_result($old_driver_id_result);
    if ($stmtFindOld->fetch()) {
        $old_driver_id = (int)$old_driver_id_result; // Store the old driver ID
    }
    $stmtFindOld->close();
    error_log("Old Driver ID found: {$old_driver_id}");


    // 3. Check if the new driver is the same as the old one
    if ($old_driver_id === $driver_id) {
         // No actual change needed, maybe just ensure flags are set correctly?
         // Or just report success without DB changes. Let's report success.
         $conn->rollback(); // No changes needed, rollback any potential implicit locks
         error_log("No driver change needed (New driver ID {$driver_id} is same as old {$old_driver_id}).");
         sendJsonResponse(true, 'Driver assignment remains the same.');
         // Exit is handled by sendJsonResponse
    }


    // 4. Upsert (Update or Insert) the assignment in driver_assignments
    // Using INSERT ... ON DUPLICATE KEY UPDATE assumes po_number is a UNIQUE key
    $sqlUpsert = "INSERT INTO driver_assignments (po_number, driver_id, assigned_at) VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), assigned_at = NOW()";
    $stmtUpsert = $conn->prepare($sqlUpsert);
    if (!$stmtUpsert) throw new mysqli_sql_exception("DB prepare error (Upsert Assignment): " . $conn->error, $conn->errno);
    $stmtUpsert->bind_param("si", $po_number, $driver_id);
    if (!$stmtUpsert->execute()) {
        $err = $stmtUpsert->error; $errno = $stmtUpsert->errno; $stmtUpsert->close();
        throw new mysqli_sql_exception("DB execute error (Upsert Assignment): " . $err, $errno);
    }
    $affectedRowsUpsert = $stmtUpsert->affected_rows; // 1 for INSERT, 2 for UPDATE, 0 if no change
    $stmtUpsert->close();
    error_log("Driver assignment upserted/updated. Affected rows: {$affectedRowsUpsert}");


    // 5. Update the driver_assigned flag in the orders table
    $sqlUpdateOrder = "UPDATE orders SET driver_assigned = 1 WHERE po_number = ?";
    $stmtUpdateOrder = $conn->prepare($sqlUpdateOrder);
    if (!$stmtUpdateOrder) throw new mysqli_sql_exception("DB prepare error (Update Order Flag): " . $conn->error, $conn->errno);
    $stmtUpdateOrder->bind_param("s", $po_number);
    if (!$stmtUpdateOrder->execute()) {
        $err = $stmtUpdateOrder->error; $errno = $stmtUpdateOrder->errno; $stmtUpdateOrder->close();
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
        $err = $stmtUpdateNew->error; $errno = $stmtUpdateNew->errno; $stmtUpdateNew->close();
        throw new mysqli_sql_exception("DB execute error (Increment New Driver): " . $err, $errno);
    }
    $stmtUpdateNew->close();
    error_log("Incremented delivery count for new driver ID {$driver_id}");


    // 7. Decrement current_deliveries for the old driver (if there was one and it's different from the new one)
    if ($old_driver_id > 0 && $old_driver_id !== $driver_id) {
        $sqlUpdateOldDriver = "UPDATE drivers SET current_deliveries = GREATEST(0, current_deliveries - 1) WHERE id = ?"; // Use GREATEST to prevent going below 0
        $stmtUpdateOld = $conn->prepare($sqlUpdateOldDriver);
        if (!$stmtUpdateOld) throw new mysqli_sql_exception("DB prepare error (Decrement Old Driver): " . $conn->error, $conn->errno);
        $stmtUpdateOld->bind_param("i", $old_driver_id);
        if (!$stmtUpdateOld->execute()) {
             $err = $stmtUpdateOld->error; $errno = $stmtUpdateOld->errno; $stmtUpdateOld->close();
             // Log error but maybe don't fail the whole transaction? Or maybe do fail it. Let's fail it for consistency.
            throw new mysqli_sql_exception("DB execute error (Decrement Old Driver): " . $err, $errno);
        }
        $stmtUpdateOld->close();
        error_log("Decremented delivery count for old driver ID {$old_driver_id}");
    }

    // If all queries succeeded, commit the transaction
    $conn->commit();
    error_log("Transaction committed for driver assignment PO {$po_number}");
    // **** CALLING sendJsonResponse ON SUCCESS ****
    sendJsonResponse(true, 'Driver assigned successfully.'); // Exit is handled inside

} catch (mysqli_sql_exception $e) {
    $conn->rollback(); // Rollback on SQL error
    error_log("!!! SQL Exception during driver assignment: " . $e->getMessage());
    // **** CALLING sendJsonResponse ON ERROR ****
    sendJsonResponse(false, 'Database error during assignment: ' . $e->getMessage(), ['error_code' => 'DB_ERROR', 'sql_errno' => $e->getCode()]); // Exit is handled inside

} catch (Exception $e) {
    $conn->rollback(); // Rollback on general error
    error_log("!!! General Exception during driver assignment: " . $e->getMessage());
     // **** CALLING sendJsonResponse ON ERROR ****
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), ['error_code' => 'GENERAL_ERROR']); // Exit is handled inside
}

// Close connection if it's still open (might be handled by sendJsonResponse or globally)
// This might be redundant if sendJsonResponse always exits and potentially closes connection
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
    error_log("Assign Driver - Explicit DB close at end.");
}

?>