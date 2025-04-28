<?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set content type header
header('Content-Type: application/json');

try {
    // Include database connection
    require_once "db_connection.php";
    
    // Check if username is provided
    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }
    
    $username = $_GET['username'];
    
    // Get optional status filter
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    // Build the SQL query based on parameters
    $sql = "SELECT * FROM orders WHERE username = ?";
    
    // Add status filter if provided
    if ($status) {
        $sql .= " AND status = ?";
    }
    
    // Order by most recent first
    $sql .= " ORDER BY order_date DESC";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    
    if ($status) {
        $stmt->bind_param("ss", $username, $status);
    } else {
        $stmt->bind_param("s", $username);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all orders
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Parse the JSON stored in the orders field
        if ($row['orders'] !== null && $row['orders'] !== '0') {
            $row['orders'] = json_decode($row['orders'], true);
        }
        
        // Include driver information if assigned
        if ($row['driver_assigned'] == 1) {
            $driverSql = "SELECT d.* FROM drivers d 
                         JOIN driver_assignments da ON d.id = da.driver_id 
                         WHERE da.po_number = ?";
            $driverStmt = $conn->prepare($driverSql);
            $driverStmt->bind_param("s", $row['po_number']);
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            
            if ($driverResult->num_rows > 0) {
                $row['driver'] = $driverResult->fetch_assoc();
            }
            
            $driverStmt->close();
        }
        
        $orders[] = $row;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $orders
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>