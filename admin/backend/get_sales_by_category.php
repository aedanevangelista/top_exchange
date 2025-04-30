<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    include "db_connection.php";

    // Get the requested time period (default to 'weekly')
    $timePeriod = isset($_GET['period']) ? $_GET['period'] : 'weekly';
    
    // Function to get sales data for a specific year with time period filtering
    function getSalesByCategory($year, $timePeriod = 'weekly') {
        global $conn;
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Base SQL query
        $sql = "SELECT 
                    o.id,
                    o.orders,
                    o.status,
                    o.order_date
                FROM orders o
                WHERE YEAR(o.order_date) = ?
                AND o.status = 'Completed'";
        
        // If monthly, we don't need additional filtering
        // For weekly, we'll use data from the current week
        if ($timePeriod === 'weekly') {
            // Get data for current week
            $currentDate = date('Y-m-d');
            $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
            $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentDate)));
            
            $sql .= " AND o.order_date BETWEEN ? AND ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $year, $weekStart, $weekEnd);
        } else {
            // For monthly, just use the year filter
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $year);
        }
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

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

    $currentYearData = getSalesByCategory($currentYear, $timePeriod);
    $lastYearData = getSalesByCategory($lastYear, $timePeriod);

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
        'timePeriod' => $timePeriod,
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