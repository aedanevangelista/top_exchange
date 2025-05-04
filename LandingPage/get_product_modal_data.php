<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = $_POST['product_name'] ?? null;
    $category = $_POST['category'] ?? null;
    
    if (!$productName) {
        echo json_encode(['error' => 'Invalid product name']);
        exit;
    }
    
    // Fetch all variants of this product
    $query = "SELECT * FROM products WHERE ";
    $params = [];
    $types = '';
    
    if (!empty($productName)) {
        // Try to match by product_name first
        $query .= "(product_name = ? OR (product_name IS NULL AND item_description LIKE ?))";
        $params[] = $productName;
        $params[] = $productName . '%';
        $types .= 'ss';
    }
    
    if (!empty($category)) {
        $query .= " AND category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $query .= " ORDER BY price ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $variants = [];
    $mainProduct = null;
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Parse ingredients if they exist
            if (!empty($row['ingredients'])) {
                $row['ingredients_array'] = json_decode($row['ingredients'], true);
            } else {
                $row['ingredients_array'] = [];
            }
            
            $variants[] = $row;
            
            // Use the first product as the main product
            if ($mainProduct === null) {
                $mainProduct = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'main_product' => $mainProduct,
            'variants' => $variants
        ]);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
