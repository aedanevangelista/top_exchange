<?php
// Force display of PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
if (!file_exists("../logs")) {
    mkdir("../logs", 0777, true);
}

// Log function for debugging
function log_debug($message) {
    $log_file = "../logs/error_log.txt";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

log_debug("Script started");

try {
    // Start session
    log_debug("Starting session");
    session_start();
    
    // Include files
    log_debug("Including db_connection.php");
    include "../db_connection.php";
    
    log_debug("Including check_role.php");
    include "../check_role.php";
    
    log_debug("POST data received: " . json_encode($_POST));
    
    // Initialize response
    $response = array('success' => true, 'message' => 'Testing connection only');
    
    // Send response
    log_debug("Sending response: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    log_debug("ERROR: " . $e->getMessage());
    log_debug("Stack trace: " . $e->getTraceAsString());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
}
?>