<?php
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db_connection.php";

// Create a log file for debugging
$log_file = "../logs/payment_debug.log";
if (!file_exists("../logs")) {
    mkdir("../logs", 0777, true);
}
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Get Available Years Started\n", FILE_APPEND);

// Check if username parameter is provided
if (!isset($_GET['username'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Missing username parameter\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Username parameter is required']);
    exit;
}

$username = $_GET['username'];
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Fetching available years for: $username\n", FILE_APPEND);

try {
    // Get years from both orders and payments
    $years = [];
    
    // Get years from orders
    $sql = "SELECT DISTINCT YEAR(delivery_date) as year FROM orders WHERE username = ? ORDER BY year DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for orders query: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for orders query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $years[] = intval($row['year']);
    }
    
    // Get years from payments
    $sql = "SELECT DISTINCT year FROM monthly_payments WHERE username = ? ORDER BY year DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for payments query: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for payments query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $year = intval($row['year']);
        if (!in_array($year, $years)) {
            $years[] = $year;
        }
    }
    
    // Sort years in descending order (most recent first)
    rsort($years);
    
    // Always include current year
    $currentYear = date('Y');
    if (!in_array($currentYear, $years)) {
        $years[] = intval($currentYear);
        rsort($years);
    }
    
    // If no years found at all, return current year
    if (empty($years)) {
        $years[] = intval($currentYear);
    }
    
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Available years: " . implode(", ", $years) . "\n", FILE_APPEND);
    echo json_encode(['success' => true, 'data' => $years]);
    
} catch (Exception $e) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - ERROR in get_available_years: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error fetching available years: ' . $e->getMessage()]);
}

$conn->close();
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Get Available Years completed\n", FILE_APPEND);
?>