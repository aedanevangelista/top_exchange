<?php
// Force display of PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Get the correct directory path
$backend_dir = realpath(dirname(__FILE__));

// Include files with absolute paths
require_once($backend_dir . "/db_connection.php");
require_once($backend_dir . "/check_role.php");

// Create logs directory if it doesn't exist
$log_dir = $backend_dir . "/logs";
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Log function for debugging
function log_debug($message) {
    global $log_dir;
    $log_file = $log_dir . "/error_log.txt";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

log_debug("Script started");
log_debug("Script path: " . $backend_dir);

try {
    // Record what we received
    $post_data = file_get_contents('php://input');
    log_debug("Raw POST data: " . $post_data);
    log_debug("POST array: " . json_encode($_POST));
    
    // Get parameters
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $return_materials = isset($_POST['return_materials']) ? (bool)$_POST['return_materials'] : false;
    $deduct_materials = isset($_POST['deduct_materials']) ? (bool)$_POST['deduct_materials'] : false;
    
    log_debug("Parameters: po_number=$po_number, status=$status, return_materials=" . ($return_materials ? 'true' : 'false') . ", deduct_materials=" . ($deduct_materials ? 'true' : 'false'));
    
    if (!empty($po_number) && !empty($status)) {
        log_debug("Updating order status");
        
        // Simply update the status without any transaction or complex logic for now
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
        $stmt->bind_param('ss', $status, $po_number);
        
        if ($stmt->execute()) {
            log_debug("Status updated successfully to: $status");
            
            // If we're changing to pending or rejected, reset progress
            if ($status === 'Pending' || $status === 'Rejected') {
                $stmt = $conn->prepare("UPDATE orders SET progress = 0, driver_assigned = 0 WHERE po_number = ?");
                $stmt->bind_param('s', $po_number);
                $stmt->execute();
                
                // Also remove any driver assignment
                $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE po_number = ?");
                $stmt->bind_param('s', $po_number);
                $stmt->execute();
                
                log_debug("Progress reset and driver assignment removed");
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