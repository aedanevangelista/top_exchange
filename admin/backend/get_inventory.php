<?php
include 'db_connection.php';

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
    WHERE stock_quantity > 0
    ORDER BY category, item_description";

    $result = $conn->query($query);

    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    $inventory = [];
    while ($row = $result->fetch_assoc()) {
        $inventory[] = [
            'product_id' => (int)$row['product_id'],
            'category' => $row['category'],
            'item_description' => $row['item_description'],
            'packaging' => $row['packaging'],
            'price' => (float)$row['price'],
            'stock_quantity' => (int)$row['stock_quantity']
        ];
    }

    echo json_encode($inventory);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>