<?php
// /backend/update_order_status.php
session_start();
include "db_connection.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check required parameters
if (!isset($_POST['po_number']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$poNumber = $_POST['po_number'];
$status = $_POST['status'];
$deductMaterials = isset($_POST['deduct_materials']) && $_POST['deduct_materials'] === 'true';

// Begin transaction for database consistency
$conn->begin_transaction();

try {
    // Get the order details first
    $orderStmt = $conn->prepare("SELECT orders, status FROM orders WHERE po_number = ?");
    $orderStmt->bind_param("s", $poNumber);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $orderData = $orderResult->fetch_assoc();
    $currentStatus = $orderData['status'];
    $ordersJson = $orderData['orders'];
    $orders = json_decode($ordersJson, true);
    
    // Only process inventory if changing to Active and current status is Pending
    if ($status === 'Active' && $currentStatus === 'Pending' && $deductMaterials) {
        // Get the ordered products and quantities
        foreach ($orders as $order) {
            if (!isset($order['product_id']) || !isset($order['quantity'])) {
                continue;
            }
            
            $productId = $order['product_id'];
            $quantity = (int)$order['quantity'];
            
            // Check product availability
            $productStmt = $conn->prepare("SELECT stock_quantity, product_name, item_description, ingredients FROM products WHERE product_id = ?");
            $productStmt->bind_param("i", $productId);
            $productStmt->execute();
            $productResult = $productStmt->get_result();
            
            if ($productResult->num_rows === 0) {
                throw new Exception("Product not found: ID {$productId}");
            }
            
            $product = $productResult->fetch_assoc();
            $availableQuantity = (int)$product['stock_quantity'];
            $productName = $product['product_name'];
            $itemDescription = $product['item_description'];
            $ingredients = json_decode($product['ingredients'], true);
            
            // If we have enough stock, simply deduct it
            if ($availableQuantity >= $quantity) {
                $newQuantity = $availableQuantity - $quantity;
                $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
                $updateStmt->bind_param("ii", $newQuantity, $productId);
                $updateStmt->execute();
                $updateStmt->close();
            } 
            // If not enough stock, we need to "manufacture" more
            else {
                // Calculate how many more we need
                $shortfall = $quantity - $availableQuantity;
                
                // First, deduct all available finished products
                if ($availableQuantity > 0) {
                    $zeroQuantity = 0;
                    $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
                    $updateStmt->bind_param("ii", $zeroQuantity, $productId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                
                // Now check if this product has ingredients defined
                if (!is_array($ingredients) || count($ingredients) === 0) {
                    throw new Exception("Cannot manufacture {$itemDescription}: No ingredients defined");
                }
                
                // Calculate raw materials needed for the shortfall
                $materialsNeeded = [];
                foreach ($ingredients as $ingredient) {
                    if (is_array($ingredient) && count($ingredient) >= 2) {
                        $materialName = $ingredient[0];
                        $materialAmount = (float)$ingredient[1];
                        
                        if (!isset($materialsNeeded[$materialName])) {
                            $materialsNeeded[$materialName] = 0;
                        }
                        
                        $materialsNeeded[$materialName] += $materialAmount * $shortfall;
                    }
                }
                
                // Check if we have enough raw materials
                foreach ($materialsNeeded as $material => $amount) {
                    $materialStmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE name = ?");
                    $materialStmt->bind_param("s", $material);
                    $materialStmt->execute();
                    $materialResult = $materialStmt->get_result();
                    
                    if ($materialResult->num_rows === 0) {
                        throw new Exception("Raw material not found: {$material}");
                    }
                    
                    $materialData = $materialResult->fetch_assoc();
                    $availableMaterial = (float)$materialData['stock_quantity'];
                    
                    if ($availableMaterial < $amount) {
                        throw new Exception("Insufficient {$material} to manufacture {$shortfall} units of {$itemDescription}");
                    }
                    
                    $materialStmt->close();
                }
                
                // Now deduct the raw materials
                foreach ($materialsNeeded as $material => $amount) {
                    $materialStmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = stock_quantity - ? WHERE name = ?");
                    $materialStmt->bind_param("ds", $amount, $material);
                    $materialStmt->execute();
                    $materialStmt->close();
                }
                
                // Log the manufacturing process
                $logStmt = $conn->prepare("INSERT INTO manufacturing_logs (po_number, product_id, product_name, quantity, created_at) VALUES (?, ?, ?, ?, NOW())");
                $logStmt->bind_param("sisi", $poNumber, $productId, $itemDescription, $shortfall);
                $logStmt->execute();
                $logStmt->close();
            }
        }
    }
    
    // Now update the order status
    $updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    $updateStmt->bind_param("ss", $status, $poNumber);
    $updateStmt->execute();
    
    // Commit the transaction if everything is successful
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Order status updated to {$status}",
        'deducted' => $deductMaterials
    ]);
    
} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>