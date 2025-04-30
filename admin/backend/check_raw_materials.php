<?php
// /backend/check_raw_materials.php
session_start();
include "db_connection.php";

// Basic error handling
header('Content-Type: application/json');

if (!isset($_POST['orders']) || !isset($_POST['po_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data'
    ]);
    exit;
}

try {
    $orders = json_decode($_POST['orders'], true);
    $poNumber = $_POST['po_number'];
    
    if (!is_array($orders)) {
        throw new Exception('Invalid orders data');
    }
    
    // Initialize arrays to store data
    $requiredMaterials = [];
    $availableMaterials = [];
    $finishedProductsStatus = [];
    $needsManufacturing = false;
    
    // Process each order item
    foreach ($orders as $order) {
        if (!isset($order['product_id']) || !isset($order['quantity'])) {
            continue;
        }
        
        $productId = $order['product_id'];
        $quantity = (int)$order['quantity'];
        
        // First check if we have enough finished products
        $stmt = $conn->prepare("SELECT product_id, item_description, stock_quantity, ingredients FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $availableQuantity = (int)$row['stock_quantity'];
            $itemDescription = $row['item_description'];
            $ingredients = json_decode($row['ingredients'], true);
            
            // Record status of finished product
            $finishedProductsStatus[$itemDescription] = [
                'available' => $availableQuantity,
                'required' => $quantity,
                'sufficient' => $availableQuantity >= $quantity,
                'shortfall' => max(0, $quantity - $availableQuantity)
            ];
            
            // If not enough finished products, calculate raw materials needed
            if ($availableQuantity < $quantity) {
                $needsManufacturing = true;
                $shortfall = $quantity - $availableQuantity;
                
                // Make sure there are ingredients defined
                if (!is_array($ingredients) || count($ingredients) === 0) {
                    // No ingredients defined, can't manufacture
                    $finishedProductsStatus[$itemDescription]['canManufacture'] = false;
                    $finishedProductsStatus[$itemDescription]['message'] = 'No ingredients defined';
                    continue;
                }
                
                $finishedProductsStatus[$itemDescription]['canManufacture'] = true;
                
                // Calculate required raw materials for shortfall
                foreach ($ingredients as $ingredient) {
                    if (is_array($ingredient) && count($ingredient) >= 2) {
                        $materialName = $ingredient[0];
                        $materialAmount = (float)$ingredient[1];
                        
                        if (!isset($requiredMaterials[$materialName])) {
                            $requiredMaterials[$materialName] = 0;
                        }
                        
                        $requiredMaterials[$materialName] += $materialAmount * $shortfall;
                    }
                }
            }
        }
        
        $stmt->close();
    }
    
    // If all finished products are sufficient, no need to check raw materials
    if (!$needsManufacturing) {
        echo json_encode([
            'success' => true,
            'finishedProducts' => $finishedProductsStatus,
            'needsManufacturing' => false,
            'message' => 'All finished products are in stock'
        ]);
        exit;
    }
    
    // If we need manufacturing, get available quantities for each required material
    foreach ($requiredMaterials as $material => $required) {
        $stmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE name = ?");
        $stmt->bind_param("s", $material);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $availableMaterials[$material] = (float)$row['stock_quantity'];
        } else {
            $availableMaterials[$material] = 0; // Material not found
        }
        
        $stmt->close();
    }
    
    // Prepare the response
    $materialsData = [];
    
    foreach ($requiredMaterials as $material => $required) {
        $available = isset($availableMaterials[$material]) ? $availableMaterials[$material] : 0;
        
        $materialsData[$material] = [
            'available' => $available,
            'required' => $required,
            'sufficient' => $available >= $required
        ];
    }
    
    echo json_encode([
        'success' => true,
        'finishedProducts' => $finishedProductsStatus,
        'materials' => $materialsData,
        'needsManufacturing' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>