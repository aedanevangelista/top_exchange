<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkApiRole('Department Forecast');

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing required date parameter']);
    exit;
}

$date = $_GET['date'];

// Input validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    // Get all orders for the specific date
    $sql = "SELECT o.orders
            FROM orders o
            WHERE o.delivery_date = ? 
            AND o.status = 'Active'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process orders and organize by category
    $departmentProducts = [];
    $rawMaterials = [];
    
    while ($row = $result->fetch_assoc()) {
        $ordersData = json_decode($row['orders'], true);
        
        if (is_array($ordersData)) {
            foreach ($ordersData as $item) {
                $category = $item['category'] ?? 'Uncategorized';
                $itemDescription = $item['item_description'] ?? 'Unknown Product';
                $packaging = $item['packaging'] ?? 'N/A';
                $quantity = intval($item['quantity'] ?? 0);
                $productId = intval($item['product_id'] ?? 0);
                
                // Create product key based on description and packaging
                $productKey = $itemDescription . '|' . $packaging;
                
                // Initialize category if not exists
                if (!isset($departmentProducts[$category])) {
                    $departmentProducts[$category] = [
                        'products' => [],
                        'materials' => []
                    ];
                }
                
                // Initialize product if not exists or update quantity
                if (!isset($departmentProducts[$category]['products'][$productKey])) {
                    $departmentProducts[$category]['products'][$productKey] = [
                        'item_description' => $itemDescription,
                        'packaging' => $packaging,
                        'total_quantity' => $quantity,
                        'product_id' => $productId
                    ];
                } else {
                    $departmentProducts[$category]['products'][$productKey]['total_quantity'] += $quantity;
                }
                
                // Fetch and calculate raw materials for this product
                if ($productId > 0) {
                    // Fetch ingredients from the products table
                    $ingredientsQuery = "SELECT ingredients FROM products WHERE product_id = ?";
                    $ingredientStmt = $conn->prepare($ingredientsQuery);
                    $ingredientStmt->bind_param("i", $productId);
                    $ingredientStmt->execute();
                    $ingredientResult = $ingredientStmt->get_result();
                    
                    if ($ingredientRow = $ingredientResult->fetch_assoc()) {
                        $ingredients = json_decode($ingredientRow['ingredients'], true);
                        
                        if (is_array($ingredients)) {
                            foreach ($ingredients as $ingredient) {
                                if (count($ingredient) >= 2) {
                                    $materialName = $ingredient[0];
                                    $materialAmount = floatval($ingredient[1]);
                                    
                                    // Calculate total material amount based on ordered quantity
                                    $totalMaterialAmount = $materialAmount * $quantity;
                                    
                                    // Add to category materials
                                    if (!isset($departmentProducts[$category]['materials'][$materialName])) {
                                        $departmentProducts[$category]['materials'][$materialName] = $totalMaterialAmount;
                                    } else {
                                        $departmentProducts[$category]['materials'][$materialName] += $totalMaterialAmount;
                                    }
                                }
                            }
                        }
                    }
                    $ingredientStmt->close();
                }
            }
        }
    }
    
    // Convert to expected format for the frontend
    $formattedResponse = [];
    foreach ($departmentProducts as $category => $data) {
        $formattedResponse[$category] = [
            'products' => array_values($data['products']),
            'materials' => $data['materials']
        ];
    }
    
    echo json_encode($formattedResponse);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>