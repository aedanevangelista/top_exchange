<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    include "db_connection.php";

    // Function to get sales data for a specific year
    function getSalesByCategory($year) {
        global $conn;
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Query to get orders and their items from the JSON data
        $sql = "SELECT 
                    o.id,
                    o.orders,
                    o.status
                FROM orders o
                WHERE YEAR(o.order_date) = ?
                AND o.status = 'Completed'";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $year);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        $salesData = [];
        while ($row = $result->fetch_assoc()) {
            // Decode the JSON orders data
            $orderItems = json_decode($row['orders'], true);
            
            // Count items by category
            foreach ($orderItems as $item) {
                $category = $item['category'];
                if (!isset($salesData[$category])) {
                    $salesData[$category] = 0;
                }
                $salesData[$category] += $item['quantity'];
            }
        }
        
        return $salesData;
    }

    // Get data for both years
    $currentYear = 2025;
    $lastYear = 2024;

    $currentYearData = getSalesByCategory($currentYear);
    $lastYearData = getSalesByCategory($lastYear);

    // Get all unique categories from products table
    $sql = "SELECT DISTINCT category FROM products ORDER BY category";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error getting categories: " . $conn->error);
    }

    $allCategories = [];
    while ($row = $result->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }

    // Prepare the response data
    $response = [
        'categories' => $allCategories,
        'currentYear' => [
            'year' => $currentYear,
            'data' => array_map(function($category) use ($currentYearData) {
                return $currentYearData[$category] ?? 0;
            }, $allCategories)
        ],
        'lastYear' => [
            'year' => $lastYear,
            'data' => array_map(function($category) use ($lastYearData) {
                return $lastYearData[$category] ?? 0;
            }, $allCategories)
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>