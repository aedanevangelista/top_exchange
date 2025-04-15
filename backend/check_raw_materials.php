<?php
// /backend/check_raw_materials.php
session_start();
include "../../backend/db_connection.php";

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if order data was provided
if (!isset($_POST['orders']) || !isset($_POST['po_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data: ' . json_encode($_POST)
    ]);
    exit;
}

try {
    $orders = json_decode($_POST['orders'], true);
    
    if (!is_array($orders)) {
        throw new Exception('Invalid orders data format: ' . $_POST['orders']);
    }
    
    $poNumber = $_POST['po_number'];
    
    // Fetch product information with ingredients data
    $productIngredients = [];
    $productIds = array_map(function($order) {
        return $order['product_id'];
    }, $orders);
    
    if (empty($productIds)) {
        echo json_encode([
            'success' => false,
            'message' => 'No products in order'
        ]);
        exit;
    }
    
    // Create placeholder string for SQL query
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    // Fetch products with ingredients
    $stmt = $conn->prepare("SELECT product_id, item_description, ingredients FROM products WHERE product_id IN ($placeholders)");
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    // Bind product IDs to the query
    $bindParams = array_merge([str_repeat('i', count($productIds))], $productIds);
    $stmt->bind_param(...$bindParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $productIngredients[$row['product_id']] = [
            'name' => $row['item_description'],
            'ingredients' => json_decode($row['ingredients'], true)
        ];
    }
    
    $stmt->close();
    
    // If there are no products with ingredients, return empty result
    if (empty($productIngredients)) {
        echo json_encode([
            'success' => true,
            'materials' => [],
            'message' => 'No ingredients found for these products'
        ]);
        exit;
    }
    
    // Calculate required materials
    $requiredMaterials = [];
    $productsWithoutIngredients = [];
    
    foreach ($orders as $order) {
        $productId = $order['product_id'];
        $quantity = $order['quantity'];
        
        if (!isset($productIngredients[$productId])) {
            // Product not found in database
            $productsWithoutIngredients[] = $order['item_description'] ?? "Product ID: $productId";
            continue;
        }
        
        $ingredients = $productIngredients[$productId]['ingredients'];
        
        if (!is_array($ingredients)) {
            // Product has no ingredients data
            $productsWithoutIngredients[] = $productIngredients[$productId]['name'];
            continue;
        }
        
        foreach ($ingredients as $ingredient) {
            if (!is_array($ingredient) || count($ingredient) < 2) {
                continue; // Skip invalid ingredient format
            }
            
            $materialName = $ingredient[0];
            $materialAmount = $ingredient[1];
            
            if (!isset($requiredMaterials[$materialName])) {
                $requiredMaterials[$materialName] = 0;
            }
            
            $requiredMaterials[$materialName] += $materialAmount * $quantity;
        }
    }
    
    // If no valid ingredients were found
    if (empty($requiredMaterials)) {
        $message = empty($productsWithoutIngredients) ? 
            'No ingredients data found for any products' : 
            'Missing ingredients data for products: ' . implode(', ', $productsWithoutIngredients);
            
        echo json_encode([
            'success' => true,
            'materials' => [],
            'message' => $message
        ]);
        exit;
    }
    
    // Fetch available materials
    $availableMaterials = [];
    $materialNames = array_keys($requiredMaterials);
    
    if (!empty($materialNames)) {
        $placeholders = str_repeat('?,', count($materialNames) - 1) . '?';
        
        $stmt = $conn->prepare("SELECT name, stock_quantity FROM raw_materials WHERE name IN ($placeholders)");
        
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $bindParams = array_merge([str_repeat('s', count($materialNames))], $materialNames);
        $stmt->bind_param(...$bindParams);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $availableMaterials[$row['name']] = floatval($row['stock_quantity']);
        }
        
        $stmt->close();
    }
    
    // Prepare response data
    $materialsData = [];
    $missingMaterials = [];
    
    foreach ($requiredMaterials as $material => $requiredAmount) {
        if (!isset($availableMaterials[$material])) {
            $missingMaterials[] = $material;
            $availableAmount = 0; // Material not found in database
        } else {
            $availableAmount = $availableMaterials[$material];
        }
        
        $materialsData[$material] = [
            'available' => $availableAmount,
            'required' => $requiredAmount
        ];
    }
    
    $responseData = [
        'success' => true,
        'materials' => $materialsData
    ];
    
    if (!empty($productsWithoutIngredients)) {
        $responseData['warning'] = 'Missing ingredients data for some products: ' . implode(', ', $productsWithoutIngredients);
    }
    
    if (!empty($missingMaterials)) {
        $responseData['missing_materials'] = 'Materials not found in inventory: ' . implode(', ', $missingMaterials);
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>