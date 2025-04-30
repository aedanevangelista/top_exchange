<?php
session_start();
include "../db_connection.php";
include "../check_role.php";

// Initialize response
$response = array();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get parameters
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $deduct_materials = isset($_POST['deduct_materials']) ? (bool)$_POST['deduct_materials'] : false;
    $return_materials = isset($_POST['return_materials']) ? (bool)$_POST['return_materials'] : false;
    
    // Validate PO number and status
    if (!empty($po_number) && !empty($status)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get the current status of the order
            $stmt = $conn->prepare("SELECT status, orders FROM orders WHERE po_number = ?");
            $stmt->bind_param('s', $po_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Order not found.");
            }
            
            $orderData = $result->fetch_assoc();
            $currentStatus = $orderData['status'];
            $ordersJson = $orderData['orders'];
            
            // If changing from Pending to Active and deduct_materials is true
            if ($status === 'Active' && $currentStatus === 'Pending' && $deduct_materials) {
                // Process inventory deduction
                if (!processInventoryDeduction($conn, $po_number, $ordersJson)) {
                    throw new Exception("Failed to deduct materials from inventory.");
                }
            }
            
            // If changing from Active to Pending or Rejected and return_materials is true
            if (($status === 'Pending' || $status === 'Rejected') && $currentStatus === 'Active' && $return_materials) {
                // Process inventory return
                if (!processInventoryReturn($conn, $po_number, $ordersJson)) {
                    throw new Exception("Failed to return materials to inventory.");
                }
            }
            
            // Update status in the database
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
            $stmt->bind_param('ss', $status, $po_number);
            
            if ($stmt->execute()) {
                // If we're changing to pending or rejected, reset progress
                if ($status === 'Pending' || $status === 'Rejected') {
                    $stmt = $conn->prepare("UPDATE orders SET progress = 0, driver_assigned = 0 WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                    
                    // Also remove any driver assignment
                    $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                }
                
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Order status updated successfully.";
            } else {
                throw new Exception("Error updating order status.");
            }
        } catch (Exception $e) {
            // Rollback transaction in case of error
            $conn->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['success'] = false;
        $response['message'] = "Missing required parameters.";
    }
} else {
    $response['success'] = false;
    $response['message'] = "Invalid request method.";
}

// Function to deduct materials from inventory when changing to Active
function processInventoryDeduction($conn, $po_number, $ordersJson) {
    $orders = json_decode($ordersJson, true);
    if (!$orders) {
        return false;
    }
    
    // Check for finished products first
    foreach ($orders as $item) {
        $productName = $item['item_description'];
        $quantityNeeded = (int)$item['quantity'];
        
        // Check if finished product exists in inventory
        $stmt = $conn->prepare("SELECT quantity FROM finished_products WHERE product_name = ?");
        $stmt->bind_param("s", $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Deduct from finished products inventory
            $row = $result->fetch_assoc();
            $currentQuantity = (int)$row['quantity'];
            
            if ($currentQuantity >= $quantityNeeded) {
                // Update inventory
                $newQuantity = $currentQuantity - $quantityNeeded;
                $stmt = $conn->prepare("UPDATE finished_products SET quantity = ? WHERE product_name = ?");
                $stmt->bind_param("is", $newQuantity, $productName);
                if (!$stmt->execute()) {
                    return false;
                }
                
                // Record the deduction in inventory_movements table for tracking
                recordInventoryMovement($conn, $productName, 'finished_product', -$quantityNeeded, $po_number, 'Order Activated');
            } else {
                // If insufficient finished products, check if we need to manufacture
                $shortfall = $quantityNeeded - $currentQuantity;
                
                // Deduct what we have
                if ($currentQuantity > 0) {
                    $stmt = $conn->prepare("UPDATE finished_products SET quantity = 0 WHERE product_name = ?");
                    $stmt->bind_param("s", $productName);
                    if (!$stmt->execute()) {
                        return false;
                    }
                    
                    // Record the deduction in inventory_movements
                    recordInventoryMovement($conn, $productName, 'finished_product', -$currentQuantity, $po_number, 'Order Activated (Partial)');
                }
                
                // For the shortfall, we need to deduct raw materials
                if (!deductRawMaterials($conn, $productName, $shortfall, $po_number)) {
                    return false;
                }
            }
        } else {
            // Product not found in finished products, deduct raw materials directly
            if (!deductRawMaterials($conn, $productName, $quantityNeeded, $po_number)) {
                return false;
            }
        }
    }
    
    return true;
}

// Helper function to deduct raw materials for manufacturing
function deductRawMaterials($conn, $productName, $quantity, $po_number) {
    // Get recipe for the product
    $stmt = $conn->prepare("SELECT ingredients FROM product_recipes WHERE product_name = ?");
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No recipe found - can't manufacture
        return false;
    }
    
    $recipe = json_decode($result->fetch_assoc()['ingredients'], true);
    
    // Deduct each ingredient from raw materials inventory
    foreach ($recipe as $ingredient) {
        $materialName = $ingredient['name'];
        $amountNeeded = $ingredient['amount'] * $quantity;
        
        $stmt = $conn->prepare("SELECT quantity FROM raw_materials WHERE material_name = ?");
        $stmt->bind_param("s", $materialName);
        $stmt->execute();
        $materialResult = $stmt->get_result();
        
        if ($materialResult->num_rows === 0) {
            return false;  // Material doesn't exist
        }
        
        $row = $materialResult->fetch_assoc();
        $currentQuantity = (float)$row['quantity'];
        
        if ($currentQuantity < $amountNeeded) {
            return false;  // Not enough material
        }
        
        // Update inventory
        $newQuantity = $currentQuantity - $amountNeeded;
        $stmt = $conn->prepare("UPDATE raw_materials SET quantity = ? WHERE material_name = ?");
        $stmt->bind_param("ds", $newQuantity, $materialName);
        if (!$stmt->execute()) {
            return false;
        }
        
        // Record the deduction in inventory_movements
        recordInventoryMovement($conn, $materialName, 'raw_material', -$amountNeeded, $po_number, 'Manufacturing for Order');
    }
    
    return true;
}

// Function to return materials to inventory when changing from Active to Pending/Rejected
function processInventoryReturn($conn, $po_number, $ordersJson) {
    $orders = json_decode($ordersJson, true);
    if (!$orders) {
        return false;
    }
    
    // Get the inventory movements for this PO to determine what was deducted
    $stmt = $conn->prepare("SELECT item_name, item_type, quantity FROM inventory_movements WHERE po_number = ? AND quantity < 0");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No inventory movements found, nothing to return
        return true;
    }
    
    // Process each inventory movement (return the materials)
    while ($row = $result->fetch_assoc()) {
        $itemName = $row['item_name'];
        $itemType = $row['item_type'];
        $deductedQuantity = abs($row['quantity']); // Make it positive for returning
        
        if ($itemType === 'finished_product') {
            // Return to finished products inventory
            $stmt = $conn->prepare("SELECT quantity FROM finished_products WHERE product_name = ?");
            $stmt->bind_param("s", $itemName);
            $stmt->execute();
            $prodResult = $stmt->get_result();
            
            if ($prodResult->num_rows > 0) {
                $prodRow = $prodResult->fetch_assoc();
                $currentQuantity = (int)$prodRow['quantity'];
                $newQuantity = $currentQuantity + $deductedQuantity;
                
                $stmt = $conn->prepare("UPDATE finished_products SET quantity = ? WHERE product_name = ?");
                $stmt->bind_param("is", $newQuantity, $itemName);
                if (!$stmt->execute()) {
                    return false;
                }
            } else {
                // Product doesn't exist in inventory yet, insert it
                $stmt = $conn->prepare("INSERT INTO finished_products (product_name, quantity) VALUES (?, ?)");
                $stmt->bind_param("si", $itemName, $deductedQuantity);
                if (!$stmt->execute()) {
                    return false;
                }
            }
            
            // Record the return in inventory_movements
            recordInventoryMovement($conn, $itemName, 'finished_product', $deductedQuantity, $po_number, 'Order Status Change Return');
        } else if ($itemType === 'raw_material') {
            // Return to raw materials inventory
            $stmt = $conn->prepare("SELECT quantity FROM raw_materials WHERE material_name = ?");
            $stmt->bind_param("s", $itemName);
            $stmt->execute();
            $matResult = $stmt->get_result();
            
            if ($matResult->num_rows > 0) {
                $matRow = $matResult->fetch_assoc();
                $currentQuantity = (float)$matRow['quantity'];
                $newQuantity = $currentQuantity + $deductedQuantity;
                
                $stmt = $conn->prepare("UPDATE raw_materials SET quantity = ? WHERE material_name = ?");
                $stmt->bind_param("ds", $newQuantity, $itemName);
                if (!$stmt->execute()) {
                    return false;
                }
            } else {
                // Material doesn't exist in inventory yet, insert it
                $stmt = $conn->prepare("INSERT INTO raw_materials (material_name, quantity) VALUES (?, ?)");
                $stmt->bind_param("sd", $itemName, $deductedQuantity);
                if (!$stmt->execute()) {
                    return false;
                }
            }
            
            // Record the return in inventory_movements
            recordInventoryMovement($conn, $itemName, 'raw_material', $deductedQuantity, $po_number, 'Order Status Change Return');
        }
    }
    
    return true;
}

// Helper function to record inventory movements
function recordInventoryMovement($conn, $itemName, $itemType, $quantity, $po_number, $reason) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system';
    $stmt = $conn->prepare("INSERT INTO inventory_movements (item_name, item_type, quantity, po_number, reason, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsss", $itemName, $itemType, $quantity, $po_number, $reason, $userId);
    return $stmt->execute();
}

// Send the response
header('Content-Type: application/json');
echo json_encode($response);
?>