<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "../db_connection.php";
include "../check_role.php";

// Initialize response
$response = array();

// Create a log function for debugging
function log_message($message) {
    $log_file = "../logs/status_update_log.txt";
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

log_message("Script started");
log_message("POST data: " . json_encode($_POST));

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get parameters
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $deduct_materials = isset($_POST['deduct_materials']) ? (bool)$_POST['deduct_materials'] : false;
    $return_materials = isset($_POST['return_materials']) ? (bool)$_POST['return_materials'] : false;
    
    log_message("Parameters: po_number=$po_number, status=$status, deduct=$deduct_materials, return=$return_materials");
    
    // Validate PO number and status
    if (!empty($po_number) && !empty($status)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            log_message("Transaction started");
            
            // Get the current status of the order
            $stmt = $conn->prepare("SELECT status FROM orders WHERE po_number = ?");
            $stmt->bind_param('s', $po_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Order not found.");
            }
            
            $orderData = $result->fetch_assoc();
            $currentStatus = $orderData['status'];
            
            log_message("Current status: $currentStatus, New status: $status");
            
            // Update status in the database
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
            $stmt->bind_param('ss', $status, $po_number);
            
            if ($stmt->execute()) {
                log_message("Status updated in database");
                
                // If we're changing to pending or rejected, reset progress
                if ($status === 'Pending' || $status === 'Rejected') {
                    $stmt = $conn->prepare("UPDATE orders SET progress = 0, driver_assigned = 0 WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                    
                    log_message("Progress reset");
                    
                    // Also remove any driver assignment
                    $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                    
                    log_message("Driver assignment removed");
                }
                
                // Commit the transaction
                $conn->commit();
                log_message("Transaction committed");
                
                $response['success'] = true;
                $response['message'] = "Order status updated successfully.";
            } else {
                throw new Exception("Error updating order status: " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $conn->rollback();
            log_message("ERROR: " . $e->getMessage());
            log_message("Transaction rolled back");
            
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
    } else {
        log_message("Missing required parameters");
        $response['success'] = false;
        $response['message'] = "Missing required parameters.";
    }
} else {
    log_message("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $response['success'] = false;
    $response['message'] = "Invalid request method.";
}

// Send the response
header('Content-Type: application/json');
log_message("Response: " . json_encode($response));
echo json_encode($response);
?>