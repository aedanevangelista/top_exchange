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
    
    // Process each order item
    foreach ($orders as $order) {
        if (!isset($order['product_id']) || !isset($order['quantity'])) {
            continue;
        }
        
        $productId = $order['product_id'];
        $quantity = (int)$order['quantity'];
        
        // Get product ingredients
        $stmt = $conn->prepare("SELECT ingredients FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $ingredients = json_decode($row['ingredients'], true);
            
            if (is_array($ingredients)) {
                foreach ($ingredients as $ingredient) {
                    if (is_array($ingredient) && count($ingredient) >= 2) {
                        $materialName = $ingredient[0];
                        $materialAmount = (float)$ingredient[1];
                        
                        if (!isset($requiredMaterials[$materialName])) {
                            $requiredMaterials[$materialName] = 0;
                        }
                        
                        $requiredMaterials[$materialName] += $materialAmount * $quantity;
                    }
                }
            }
        }
        
        $stmt->close();
    }
    
    // If no materials required, return an empty result
    if (empty($requiredMaterials)) {
        echo json_encode([
            'success' => true,
            'materials' => [],
            'message' => 'No raw materials required for this order'
        ]);
        exit;
    }
    
    // Get available quantities for each required material
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
            'required' => $required
        ];
    }
    
    echo json_encode([
        'success' => true,
        'materials' => $materialsData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>