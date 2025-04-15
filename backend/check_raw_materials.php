<?php
include 'db_connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $orders = $_POST['orders']; // JSON string of ordered products

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        $insufficientMaterials = [];
        
        foreach ($decoded_orders as $item) {
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
                            $materialStmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE name = ?");
                            $materialStmt->bind_param('s', $materialName);
                            $materialStmt->execute();
                            $materialResult = $materialStmt->get_result();
                            
                            if ($materialResult && $materialRow = $materialResult->fetch_assoc()) {
                                $currentStock = $materialRow['stock_quantity'];
                                
                                // Check if there's enough stock
                                if ($currentStock < $requiredAmount) {
                                    $insufficientMaterials[] = [
                                        'product' => $item['item_description'],
                                        'material' => $materialName,
                                        'required' => $requiredAmount,
                                        'available' => $currentStock
                                    ];
                                }
                                
                                $materialStmt->close();
                            } else {
                                // Material not found
                                $insufficientMaterials[] = [
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
        }
        
        if (count($insufficientMaterials) > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient raw materials for some products',
                'insufficient_materials' => $insufficientMaterials
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'All raw materials are available in sufficient quantities'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>