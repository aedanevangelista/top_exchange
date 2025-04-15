<?php
// /backend/update_order_status.php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Using your existing check_role function instead of our custom checkAccess
checkRole('Pending Orders');

header('Content-Type: application/json');

if (!isset($_POST['po_number']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields'
    ]);
    exit;
}

$poNumber = $_POST['po_number'];
$status = $_POST['status'];
$deductMaterials = isset($_POST['deduct_materials']) ? (bool)$_POST['deduct_materials'] : false;

// Begin transaction
$conn->begin_transaction();

try {
    // First get the order details
    $stmt = $conn->prepare("SELECT orders FROM orders WHERE po_number = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $poNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $orderData = $result->fetch_assoc();
    $orders = json_decode($orderData['orders'], true);
    $stmt->close();
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $status, $poNumber);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to update order status");
    }
    
    $stmt->close();
    
    // If status is Active and deduct_materials is true, deduct raw materials
    if ($status === 'Active' && $deductMaterials && is_array($orders)) {
        // Calculate required materials from order
        $requiredMaterials = calculateRequiredMaterials($conn, $orders);
        
        // Deduct materials from stock
        foreach ($requiredMaterials as $material => $amount) {
            $stmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = stock_quantity - ? WHERE name = ?");
            
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ds", $amount, $material);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function to calculate required materials from orders
function calculateRequiredMaterials($conn, $orders) {
    $requiredMaterials = [];
    
    // Get product IDs
    $productIds = array_map(function($order) {
        return $order['product_id'];
    }, $orders);
    
    if (empty($productIds)) {
        return $requiredMaterials;
    }
    
    // Create placeholder string for SQL query
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    // Fetch products with ingredients
    $stmt = $conn->prepare("SELECT product_id, ingredients FROM products WHERE product_id IN ($placeholders)");
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    // Bind product IDs to the query
    $types = str_repeat('i', count($productIds));
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productIngredients = [];
    while ($row = $result->fetch_assoc()) {
        $productIngredients[$row['product_id']] = json_decode($row['ingredients'], true);
    }
    
    $stmt->close();
    
    // Calculate required materials for each order item
    foreach ($orders as $order) {
        $productId = $order['product_id'];
        $quantity = $order['quantity'];
        
        if (isset($productIngredients[$productId]) && is_array($productIngredients[$productId])) {
            $ingredients = $productIngredients[$productId];
            
            foreach ($ingredients as $ingredient) {
                $materialName = $ingredient[0];
                $materialAmount = $ingredient[1];
                
                if (!isset($requiredMaterials[$materialName])) {
                    $requiredMaterials[$materialName] = 0;
                }
                
                $requiredMaterials[$materialName] += $materialAmount * $quantity;
            }
        }
    }
    
    return $requiredMaterials;
}
?>