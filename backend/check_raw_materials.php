<?php
// This file handles checking raw material availability and deducting material quantities

function checkRawMaterialAvailability($conn, $ordersData) {
    // Decode orders JSON
    $orders = json_decode($ordersData, true);
    if (!$orders) {
        return [
            'success' => false,
            'message' => 'Invalid order data'
        ];
    }

    // Initialize arrays to track required materials and available quantities
    $requiredMaterials = [];
    $availableMaterials = [];
    $insufficientMaterials = [];

    // Calculate total required materials for all products in the order
    foreach ($orders as $order) {
        $productId = $order['product_id'];
        $quantity = (int)$order['quantity'];
        
        // Get product ingredients
        $stmt = $conn->prepare("SELECT ingredients FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $ingredients = json_decode($row['ingredients'], true);
            
            if ($ingredients) {
                foreach ($ingredients as $ingredient) {
                    $materialName = $ingredient[0];
                    $materialAmount = $ingredient[1] * $quantity; // Multiply by order quantity
                    
                    if (!isset($requiredMaterials[$materialName])) {
                        $requiredMaterials[$materialName] = 0;
                    }
                    $requiredMaterials[$materialName] += $materialAmount;
                }
            }
        }
        $stmt->close();
    }

    // Get current raw material stock quantities
    foreach ($requiredMaterials as $materialName => $requiredAmount) {
        $stmt = $conn->prepare("SELECT material_id, stock_quantity FROM raw_materials WHERE name = ?");
        $stmt->bind_param("s", $materialName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $availableMaterials[$materialName] = [
                'id' => $row['material_id'],
                'available' => $row['stock_quantity'],
                'required' => $requiredAmount
            ];
            
            // Check if available quantity is insufficient
            if ($row['stock_quantity'] < $requiredAmount) {
                $insufficientMaterials[$materialName] = [
                    'available' => $row['stock_quantity'],
                    'required' => $requiredAmount,
                    'missing' => $requiredAmount - $row['stock_quantity']
                ];
            }
        } else {
            // Material not found in database
            $insufficientMaterials[$materialName] = [
                'available' => 0,
                'required' => $requiredAmount,
                'missing' => $requiredAmount
            ];
        }
        $stmt->close();
    }

    // Return results
    if (empty($insufficientMaterials)) {
        return [
            'success' => true,
            'message' => 'All raw materials are available',
            'materials' => $availableMaterials
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Insufficient raw materials',
            'materials' => $availableMaterials,
            'insufficientMaterials' => $insufficientMaterials
        ];
    }
}

function deductRawMaterials($conn, $ordersData) {
    // First check if materials are available
    $checkResult = checkRawMaterialAvailability($conn, $ordersData);
    
    if (!$checkResult['success']) {
        return $checkResult;
    }
    
    // Begin transaction to ensure all deductions happen or none do
    $conn->begin_transaction();
    
    try {
        foreach ($checkResult['materials'] as $materialName => $info) {
            $materialId = $info['id'];
            $newQuantity = $info['available'] - $info['required'];
            
            $stmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = ? WHERE material_id = ?");
            $stmt->bind_param("di", $newQuantity, $materialId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit the transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Raw materials successfully deducted',
            'materials' => $checkResult['materials']
        ];
    } catch (Exception $e) {
        // Rollback the transaction if any error occurs
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => 'Error deducting raw materials: ' . $e->getMessage()
        ];
    }
}
?>