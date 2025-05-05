<?php
// Set timezone to ensure correct date calculations
date_default_timezone_set('Asia/Manila'); // Philippines timezone

// Enhanced version of get_times.php with better error handling and debugging

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests with detailed information
error_log("get_times.php received request: " . print_r($_REQUEST, true));
error_log("HTTP method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

// Set headers to prevent caching and allow CORS
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// Create a response array
$response = array('booked' => array());

// Check for date in both POST and GET
$date = null;
if (isset($_POST['date'])) {
    $date = $_POST['date'];
    error_log("Date provided in POST: {$date}");
} elseif (isset($_GET['date'])) {
    $date = $_GET['date'];
    error_log("Date provided in GET: {$date}");
} else {
    // Try to get data from php://input for JSON requests
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        error_log("Raw input: {$input}");
        $jsonData = json_decode($input, true);
        if ($jsonData && isset($jsonData['date'])) {
            $date = $jsonData['date'];
            error_log("Date provided in JSON: {$date}");
        }
    }
}

// Process the date if provided
if ($date) {
    // Add a test booked time at 10:00 AM for demonstration
    $response['booked'][] = "10:00:00";
    $response['date_received'] = $date;

    // Include database connection to get real booked times
    try {
        include '../db_connect.php';

        // Get booked times for the selected date from the database
        $stmt = $conn->prepare("SELECT preferred_time FROM appointments WHERE preferred_date = ? AND status != 'cancelled'");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        // Clear the test data and use real data
        $response['booked'] = [];

        while ($row = $result->fetch_assoc()) {
            $response['booked'][] = $row['preferred_time'];
        }

        $response['source'] = 'database';
        error_log("Retrieved " . count($response['booked']) . " booked times from database");

    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        // Keep the test data if database fails
        $response['source'] = 'test_data';
        $response['db_error'] = $e->getMessage();
    }
} else {
    error_log("No date provided in any format");
    $response['error'] = "No date provided";
    $response['status'] = 'error';
    http_response_code(400); // Bad request
}

// Add timestamp for debugging cache issues
$response['timestamp'] = date('Y-m-d H:i:s');
$response['server_time'] = time();

// Return the response
echo json_encode($response);
?>