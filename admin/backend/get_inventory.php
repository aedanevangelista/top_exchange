<?php
include 'db_connection.php'; // Make sure this path is correct

header('Content-Type: application/json');

try {
    $query = "SELECT 
        product_id,
        category,
        item_description,
        packaging,
        price,
        stock_quantity 
    FROM products 
    WHERE stock_quantity > 0 AND status = 'active'
    ORDER BY category, item_description"; // Assuming you only want 'active' products

    $result = $conn->query($query);

    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    $inventory = [];
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $inventoryItem = [
            'product_id' => (int)$row['product_id'],
            'category' => $row['category'],
            'item_description' => $row['item_description'],
            'packaging' => $row['packaging'],
            'price' => (float)$row['price'],
            'stock_quantity' => (int)$row['stock_quantity'] // It's good to have this, though the JS might not use it directly for adding to cart
        ];
        $inventory[] = $inventoryItem;

        if (!in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
    
    // Sort categories alphabetically
    sort($categories);

    echo json_encode([
        'success' => true,
        'inventory' => $inventory,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// It's good practice to close the connection, though PHP often does this automatically at script end.
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>