<?php
// Start the session
session_start();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if key and value are provided
    if (isset($_POST['key']) && isset($_POST['value'])) {
        $key = $_POST['key'];
        $value = $_POST['value'];
        
        // Set the session variable
        $_SESSION[$key] = $value;
        
        // Log for debugging
        error_log("Session variable set: {$key} = {$value}");
        
        // Return success response
        echo json_encode(['success' => true]);
        exit;
    }
}

// Return error response
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
