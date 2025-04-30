<?php
// Force display of PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Get the correct directory path
$backend_dir = realpath(dirname(__FILE__));

// Include files with absolute paths
require_once($backend_dir . "/db_connection.php");
require_once($backend_dir . "/check_role.php");

// Create logs directory if it doesn't exist
$log_dir = $backend_dir . "/logs";
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Log function for debugging
function log_debug($message) {
    global $log_dir;
    $log_file = $log_dir . "/error_log.txt";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $message . "\n", FILE_APPEND);
}

log_debug("Script started");
log_debug("Script path: " . $backend_dir);

try {
    // Record what we received
    $post_data = file_get_contents('php://input');
    log_debug("Raw POST data: " . $post_data);
    log_debug("POST array: " . json_encode($_POST));
    
    // Get parameters
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $return_materials = isset($_POST['return_materials']) ? (bool)$_POST['return_materials'] : false;
    $deduct_materials = isset($_POST['deduct_materials']) ? (bool)$_POST['deduct_materials'] : false;
    
    log_debug("Parameters: po_number=$po_number, status=$status, return_materials=" . ($return_materials ? 'true' : 'false') . ", deduct_materials=" . ($deduct_materials ? 'true' : 'false'));
    
    if (!empty($po_number) && !empty($status)) {
        // Start transaction for atomicity
        log_debug("Starting transaction");
        $conn->begin_transaction();
        
        try {
            // Get current order info including status and order items
            $stmt = $conn->prepare("SELECT status, orders FROM orders WHERE po_number = ?");
            $stmt->bind_param('s', $po_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Order not found: $po_number");
            }
            
            $orderData = $result->fetch_assoc();
            $currentStatus = $orderData['status'];
            $ordersJson = $orderData['orders'];
            
            log_debug("Current status: $currentStatus, New status: $status");
            log_debug("Orders JSON: $ordersJson");
            
            // Handle inventory changes if needed
            if ($deduct_materials && $status === 'Active' && $currentStatus === 'Pending') {
                log_debug("Attempting to deduct materials from inventory");
                if (!processInventoryDeduction($conn, $po_number, $ordersJson)) {
                    throw new Exception("Failed to deduct materials from inventory.");
                }
                log_debug("Materials deducted successfully");
            }
            
            // Handle returning materials to inventory
            if ($return_materials && ($status === 'Pending' || $status === 'Rejected') && $currentStatus === 'Active') {
                log_debug("Attempting to return materials to inventory");
                if (!processInventoryReturn($conn, $po_number, $ordersJson)) {
                    throw new Exception("Failed to return materials to inventory.");
                }
                log_debug("Materials returned successfully");
            }
            
            // Update status
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
            $stmt->bind_param('ss', $status, $po_number);
            
            if ($stmt->execute()) {
                log_debug("Status updated successfully to: $status");
                
                // If changing to pending or rejected, reset progress
                if ($status === 'Pending' || $status === 'Rejected') {
                    $stmt = $conn->prepare("UPDATE orders SET progress = 0, driver_assigned = 0 WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                    
                    // Also remove any driver assignment
                    $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE po_number = ?");
                    $stmt->bind_param('s', $po_number);
                    $stmt->execute();
                    
                    log_debug("Progress reset and driver assignment removed");
                }
                
                // Commit the transaction
                $conn->commit();
                log_debug("Transaction committed");
                
                $response = array(
                    'success' => true, 
                    'message' => "Order status updated successfully to $status",
                    'inventory_updated' => ($deduct_materials || $return_materials)
                );
            } else {
                throw new Exception("Error updating status: " . $conn->error);
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            log_debug("Transaction rolled back due to error: " . $e->getMessage());
            throw $e; // Re-throw to be caught by outer try-catch
        }
    } else {
        log_debug("Missing required parameters");
        $response = array('success' => false, 'message' => "Missing required parameters");
    }
    
    // Make sure there's no output before this point
    if (ob_get_length()) ob_clean();
    
    // Send response
    log_debug("Sending response: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    log_debug("ERROR: " . $e->getMessage());
    log_debug("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output
    if (ob_get_length()) ob_clean();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
}

// Function to deduct materials from inventory when changing to Active
function processInventoryDeduction($conn, $po_number, $ordersJson) {
    global $log_dir;
    $log_file = $log_dir . "/inventory_log.txt";
    $log_message = "Starting inventory deduction for PO: $po_number\n";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $log_message, FILE_APPEND);
    
    $orders = json_decode($ordersJson, true);
    if (!$orders) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to parse orders JSON\n", FILE_APPEND);
        return false;
    }
    
    // Create the inventory_movements table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        item_type ENUM('raw_material', 'finished_product') NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        po_number VARCHAR(50),
        reason VARCHAR(255),
        user_id VARCHAR(50),
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createTableSQL)) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to create inventory_movements table: " . $conn->error . "\n", FILE_APPEND);
        return false;
    }
    
    // Check for finished products first
    foreach ($orders as $item) {
        $productName = $item['item_description'];
        $quantityNeeded = (int)$item['quantity'];
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Processing product: $productName, quantity: $quantityNeeded\n", FILE_APPEND);
        
        // Check if finished product exists in inventory
        $stmt = $conn->prepare("SELECT quantity FROM finished_products WHERE product_name = ?");
        if (!$stmt) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Prepare failed for finished products: " . $conn->error . "\n", FILE_APPEND);
            return false;
        }
        
        $stmt->bind_param("s", $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Deduct from finished products inventory
            $row = $result->fetch_assoc();
            $currentQuantity = (int)$row['quantity'];
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Found in finished products. Current quantity: $currentQuantity\n", FILE_APPEND);
            
            if ($currentQuantity >= $quantityNeeded) {
                // Update inventory
                $newQuantity = $currentQuantity - $quantityNeeded;
                $updateStmt = $conn->prepare("UPDATE finished_products SET quantity = ? WHERE product_name = ?");
                $updateStmt->bind_param("is", $newQuantity, $productName);
                if (!$updateStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update finished products: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
                
                // Record the deduction in inventory_movements table for tracking
                if (!recordInventoryMovement($conn, $productName, 'finished_product', -$quantityNeeded, $po_number, 'Order Activated')) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to record inventory movement\n", FILE_APPEND);
                }
                
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully deducted $quantityNeeded units from finished products\n", FILE_APPEND);
            } else {
                // If insufficient finished products, check if we need to manufacture
                $shortfall = $quantityNeeded - $currentQuantity;
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Insufficient quantity. Shortfall: $shortfall\n", FILE_APPEND);
                
                // Deduct what we have
                if ($currentQuantity > 0) {
                    $updateStmt = $conn->prepare("UPDATE finished_products SET quantity = 0 WHERE product_name = ?");
                    $updateStmt->bind_param("s", $productName);
                    if (!$updateStmt->execute()) {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update finished products: " . $conn->error . "\n", FILE_APPEND);
                        return false;
                    }
                    
                    // Record the deduction
                    if (!recordInventoryMovement($conn, $productName, 'finished_product', -$currentQuantity, $po_number, 'Order Activated (Partial)')) {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to record inventory movement\n", FILE_APPEND);
                    }
                    
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Deducted $currentQuantity units from finished products\n", FILE_APPEND);
                }
                
                // For the shortfall, we need to deduct raw materials
                if (!deductRawMaterials($conn, $productName, $shortfall, $po_number)) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to deduct raw materials for shortfall\n", FILE_APPEND);
                    return false;
                }
            }
        } else {
            // Product not found in finished products, deduct raw materials directly
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Product not found in finished products. Deducting raw materials directly.\n", FILE_APPEND);
            if (!deductRawMaterials($conn, $productName, $quantityNeeded, $po_number)) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to deduct raw materials\n", FILE_APPEND);
                return false;
            }
        }
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Inventory deduction completed successfully\n", FILE_APPEND);
    return true;
}

// Helper function to deduct raw materials for manufacturing
function deductRawMaterials($conn, $productName, $quantity, $po_number) {
    global $log_dir;
    $log_file = $log_dir . "/inventory_log.txt";
    $log_message = "Starting raw material deduction for product: $productName, quantity: $quantity\n";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $log_message, FILE_APPEND);
    
    // Get recipe for the product
    $stmt = $conn->prepare("SELECT ingredients FROM product_recipes WHERE product_name = ?");
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No recipe found - can't manufacture
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": No recipe found for product: $productName\n", FILE_APPEND);
        return false;
    }
    
    $recipe = json_decode($result->fetch_assoc()['ingredients'], true);
    if (!$recipe) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to parse recipe JSON\n", FILE_APPEND);
        return false;
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Recipe found with " . count($recipe) . " ingredients\n", FILE_APPEND);
    
    // Deduct each ingredient from raw materials inventory
    foreach ($recipe as $ingredient) {
        $materialName = $ingredient['name'];
        $amountNeeded = $ingredient['amount'] * $quantity;
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Processing ingredient: $materialName, amount needed: $amountNeeded\n", FILE_APPEND);
        
        $stmt = $conn->prepare("SELECT quantity FROM raw_materials WHERE material_name = ?");
        $stmt->bind_param("s", $materialName);
        $stmt->execute();
        $materialResult = $stmt->get_result();
        
        if ($materialResult->num_rows === 0) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Material doesn't exist: $materialName\n", FILE_APPEND);
            return false;  // Material doesn't exist
        }
        
        $row = $materialResult->fetch_assoc();
        $currentQuantity = (float)$row['quantity'];
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Current quantity of $materialName: $currentQuantity\n", FILE_APPEND);
        
        if ($currentQuantity < $amountNeeded) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Not enough material. Have: $currentQuantity, Need: $amountNeeded\n", FILE_APPEND);
            return false;  // Not enough material
        }
        
        // Update inventory
        $newQuantity = $currentQuantity - $amountNeeded;
        $updateStmt = $conn->prepare("UPDATE raw_materials SET quantity = ? WHERE material_name = ?");
        $updateStmt->bind_param("ds", $newQuantity, $materialName);
        if (!$updateStmt->execute()) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update raw materials: " . $conn->error . "\n", FILE_APPEND);
            return false;
        }
        
        // Record the deduction in inventory_movements
        if (!recordInventoryMovement($conn, $materialName, 'raw_material', -$amountNeeded, $po_number, 'Manufacturing for Order')) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to record inventory movement\n", FILE_APPEND);
        }
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully deducted $amountNeeded of $materialName\n", FILE_APPEND);
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Raw material deduction completed successfully\n", FILE_APPEND);
    return true;
}

// Function to return materials to inventory when changing from Active to Pending/Rejected
function processInventoryReturn($conn, $po_number, $ordersJson) {
    global $log_dir;
    $log_file = $log_dir . "/inventory_log.txt";
    $log_message = "Starting inventory return for PO: $po_number\n";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $log_message, FILE_APPEND);
    
    // Get the inventory movements for this PO to determine what was deducted
    $stmt = $conn->prepare("SELECT item_name, item_type, quantity FROM inventory_movements WHERE po_number = ? AND quantity < 0");
    if (!$stmt) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Prepare failed for inventory movements: " . $conn->error . "\n", FILE_APPEND);
        return false;
    }
    
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No inventory movements found, nothing to return
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": No inventory movements found for PO: $po_number\n", FILE_APPEND);
        return true;
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Found " . $result->num_rows . " inventory movements to return\n", FILE_APPEND);
    
    // Process each inventory movement (return the materials)
    while ($row = $result->fetch_assoc()) {
        $itemName = $row['item_name'];
        $itemType = $row['item_type'];
        $deductedQuantity = abs($row['quantity']); // Make it positive for returning
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Returning $deductedQuantity of $itemName (type: $itemType)\n", FILE_APPEND);
        
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
                
                $updateStmt = $conn->prepare("UPDATE finished_products SET quantity = ? WHERE product_name = ?");
                $updateStmt->bind_param("is", $newQuantity, $itemName);
                if (!$updateStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update finished product: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
            } else {
                // Product doesn't exist in inventory yet, insert it
                $insertStmt = $conn->prepare("INSERT INTO finished_products (product_name, quantity) VALUES (?, ?)");
                $insertStmt->bind_param("si", $itemName, $deductedQuantity);
                if (!$insertStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to insert finished product: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
            }
            
            // Record the return in inventory_movements
            if (!recordInventoryMovement($conn, $itemName, 'finished_product', $deductedQuantity, $po_number, 'Order Status Change Return')) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to record inventory movement\n", FILE_APPEND);
            }
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully returned $deductedQuantity of $itemName to finished products\n", FILE_APPEND);
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
                
                $updateStmt = $conn->prepare("UPDATE raw_materials SET quantity = ? WHERE material_name = ?");
                $updateStmt->bind_param("ds", $newQuantity, $itemName);
                if (!$updateStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update raw material: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
            } else {
                // Material doesn't exist in inventory yet, insert it
                $insertStmt = $conn->prepare("INSERT INTO raw_materials (material_name, quantity) VALUES (?, ?)");
                $insertStmt->bind_param("sd", $itemName, $deductedQuantity);
                if (!$insertStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to insert raw material: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
            }
            
            // Record the return in inventory_movements
            if (!recordInventoryMovement($conn, $itemName, 'raw_material', $deductedQuantity, $po_number, 'Order Status Change Return')) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to record inventory movement\n", FILE_APPEND);
            }
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully returned $deductedQuantity of $itemName to raw materials\n", FILE_APPEND);
        }
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Inventory return completed successfully\n", FILE_APPEND);
    return true;
}

// Helper function to record inventory movements
function recordInventoryMovement($conn, $itemName, $itemType, $quantity, $po_number, $reason) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system';
    
    $stmt = $conn->prepare("INSERT INTO inventory_movements (item_name, item_type, quantity, po_number, reason, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ssdsss", $itemName, $itemType, $quantity, $po_number, $reason, $userId);
    return $stmt->execute();
}
?>