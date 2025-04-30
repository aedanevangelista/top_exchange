<?php
// Force display of PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . "/../logs")) {
    mkdir(__DIR__ . "/../logs", 0777, true);
}

// Log function for debugging
function log_debug($message) {
    $log_file = __DIR__ . "/../logs/error_log.txt";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

log_debug("Script started");
log_debug("Script path: " . __DIR__);

try {
    // Start session
    log_debug("Starting session");
    session_start();
    
    // Include files - use absolute paths
    log_debug("Including db_connection.php");
    require_once(__DIR__ . "/../db_connection.php");
    
    log_debug("Including check_role.php");
    require_once(__DIR__ . "/../check_role.php");
    
    // Record what we received
    $post_data = file_get_contents('php://input');
    log_debug("Raw POST data: " . $post_data);
    log_debug("POST array: " . json_encode($_POST));
    
    // Get parameters
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    log_debug("Parameters: po_number=$po_number, status=$status");
    
    if (!empty($po_number) && !empty($status)) {
        log_debug("Updating order status");
        
        // Simply update the status without any transaction or complex logic
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
        $stmt->bind_param('ss', $status, $po_number);
        
        if ($stmt->execute()) {
            log_debug("Status updated successfully");
            
            // If we're changing to pending or rejected, reset progress
            if ($status === 'Pending' || $status === 'Rejected') {
                $stmt = $conn->prepare("UPDATE orders SET progress = 0, driver_assigned = 0 WHERE po_number = ?");
                $stmt->bind_param('s', $po_number);
                $stmt->execute();
                
                // Also remove any driver assignment
                $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE po_number = ?");
                $stmt->bind_param('s', $po_number);
                $stmt->execute();
            }
            
            $response = array('success' => true, 'message' => "Order status updated successfully to $status");
        } else {
            log_debug("Error updating status: " . $conn->error);
            $response = array('success' => false, 'message' => "Error updating status: " . $conn->error);
        }
    } else {
        log_debug("Missing required parameters");
        $response = array('success' => false, 'message' => "Missing required parameters");
    }
    
    // Make sure there's no output before this point
    if (ob_get_length()) ob_clean();
    
    // Send response
    log_debug("Sending response: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    log_debug("ERROR: " . $e->getMessage());
    log_debug("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output
    if (ob_get_length()) ob_clean();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
}
?>