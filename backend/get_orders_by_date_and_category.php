<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkApiRole('Department Forecast');

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing required date parameter']);
    exit;
}

$date = $_GET['date'];

// Input validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    // Get all orders for the specific date
    $sql = "SELECT o.orders
            FROM orders o
            WHERE o.delivery_date = ? 
            AND o.status = 'Active'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process orders and organize by category
    $departmentProducts = [];
    
    while ($row = $result->fetch_assoc()) {
        $ordersData = json_decode($row['orders'], true);
        
        if (is_array($ordersData)) {
            foreach ($ordersData as $item) {
                $category = $item['category'] ?? 'Uncategorized';
                $itemDescription = $item['item_description'] ?? 'Unknown Product';
                $packaging = $item['packaging'] ?? 'N/A';
                $quantity = intval($item['quantity'] ?? 0);
                
                // Create product key based on description and packaging
                $productKey = $itemDescription . '|' . $packaging;
                
                // Initialize category if not exists
                if (!isset($departmentProducts[$category])) {
                    $departmentProducts[$category] = [];
                }
                
                // Initialize product if not exists or update quantity
                if (!isset($departmentProducts[$category][$productKey])) {
                    $departmentProducts[$category][$productKey] = [
                        'item_description' => $itemDescription,
                        'packaging' => $packaging,
                        'total_quantity' => $quantity
                    ];
                } else {
                    $departmentProducts[$category][$productKey]['total_quantity'] += $quantity;
                }
            }
        }
    }
    
    // Convert to expected format for the frontend
    $formattedResponse = [];
    foreach ($departmentProducts as $category => $products) {
        $formattedResponse[$category] = array_values($products);
    }
    
    echo json_encode($formattedResponse);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>