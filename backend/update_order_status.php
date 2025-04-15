<?php
// /backend/update_order_status.php
session_start();
include "../../backend/db_connection.php";

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

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First get the order details
    $stmt = $conn->prepare("SELECT orders FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $poNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception("Order not found");
    }
    
    $orderData = $result->fetch_assoc();
    $orders = json_decode($orderData['orders'], true);
    $stmt->close();
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    $stmt->bind_param("ss", $status, $poNumber);
    $stmt->execute();
    
    if ($stmt->affected_rows == 0) {
        throw new Exception("Failed to update order status");
    }
    
    $stmt->close();
    
    // If activating order and deduction is enabled, deduct materials
    if ($status === 'Active' && $deductMaterials && is_array($orders)) {
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
                            $materialAmount = (float)$ingredient[1] * $quantity;
                            
                            // Deduct material from inventory
                            $updateStmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = stock_quantity - ? WHERE name = ?");
                            $updateStmt->bind_param("ds", $materialAmount, $materialName);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }
                }
            }
            
            $stmt->close();
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>