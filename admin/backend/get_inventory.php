<?php
include 'db_connection.php';

header('Content-Type: application/json');

try {
    // Add error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Get products with their categories - using explicit fields
    $query = "SELECT 
        product_id,
        category,
        item_description,
        packaging,
        price,
        stock_quantity 
    FROM products 
    WHERE stock_quantity > 0
    ORDER BY category, item_description";

    $result = $conn->query($query);

    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    $inventory = [];
    while ($row = $result->fetch_assoc()) {
        // Debug log
        error_log("Processing row: " . print_r($row, true));
        
        $inventory[] = [
            'product_id' => $row['product_id'],
            'category' => $row['category'],
            'item_description' => $row['item_description'],
            'packaging' => $row['packaging'],
            'price' => (float)$row['price'],
            'stock_quantity' => (int)$row['stock_quantity']
        ];
    }

    // Debug log
    error_log("Final inventory array: " . print_r($inventory, true));

    // Set proper content type and encode as JSON
    header('Content-Type: application/json');
    echo json_encode($inventory);

} catch (Exception $e) {
    error_log("Error in get_inventory.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>