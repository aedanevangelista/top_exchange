<?php
include 'db_connection.php';

/**
 * Deducts raw materials based on the order items
 * 
 * @param array $orders JSON decoded array of order items
 * @param mysqli $conn Database connection
 * @return array Status of the operation
 */
function deductRawMaterials($orders, $conn) {
    $successCount = 0;
    $failedItems = [];
    
    // Begin transaction to ensure all deductions are atomic
    $conn->begin_transaction();
    
    try {
        foreach ($orders as $item) {
            // Get the product ingredients from the database
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            // Fetch the ingredients for the product
            $stmt = $conn->prepare("SELECT ingredients FROM products WHERE product_id = ?");
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                // Decode the ingredients JSON array
                $ingredients = json_decode($row['ingredients'], true);
                
                if ($ingredients && is_array($ingredients)) {
                    // Process each ingredient
                    foreach ($ingredients as $ingredient) {
                        if (is_array($ingredient) && count($ingredient) >= 2) {
                            $materialName = $ingredient[0];
                            $requiredAmount = $ingredient[1] * $quantity; // Amount per product * order quantity
                            
                            // Get current stock of the raw material
                            $materialStmt = $conn->prepare("SELECT material_id, stock_quantity FROM raw_materials WHERE name = ?");
                            $materialStmt->bind_param('s', $materialName);
                            $materialStmt->execute();
                            $materialResult = $materialStmt->get_result();
                            
                            if ($materialResult && $materialRow = $materialResult->fetch_assoc()) {
                                $materialId = $materialRow['material_id'];
                                $currentStock = $materialRow['stock_quantity'];
                                
                                // Check if there's enough stock
                                if ($currentStock >= $requiredAmount) {
                                    // Deduct the required amount
                                    $newStock = $currentStock - $requiredAmount;
                                    
                                    // Update the stock
                                    $updateStmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = ? WHERE material_id = ?");
                                    $updateStmt->bind_param('di', $newStock, $materialId);
                                    
                                    if (!$updateStmt->execute()) {
                                        throw new Exception("Failed to update stock for {$materialName}");
                                    }
                                    
                                    $updateStmt->close();
                                } else {
                                    // Not enough stock
                                    $failedItems[] = [
                                        'product' => $item['item_description'],
                                        'material' => $materialName,
                                        'required' => $requiredAmount,
                                        'available' => $currentStock
                                    ];
                                }
                                
                                $materialStmt->close();
                            } else {
                                // Material not found
                                $failedItems[] = [
                                    'product' => $item['item_description'],
                                    'material' => $materialName,
                                    'error' => 'Material not found in inventory'
                                ];
                            }
                        }
                    }
                }
            }
            
            $stmt->close();
            $successCount++;
        }
        
        // If there are any failed items, rollback the transaction
        if (count($failedItems) > 0) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => 'Insufficient raw materials for some products',
                'failed_items' => $failedItems
            ];
        }
        
        // Commit the transaction if all deductions were successful
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Raw materials deducted successfully',
            'items_processed' => $successCount
        ];
        
    } catch (Exception $e) {
        // If any error occurs, rollback the transaction
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error during raw material deduction: ' . $e->getMessage()
        ];
    }
}
?>