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
    
    // For each product in the order
    foreach ($orders as $item) {
        $productName = $item['item_description'];
        $quantityNeeded = (int)$item['quantity'];
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Processing product: $productName, quantity: $quantityNeeded\n", FILE_APPEND);
        
        // Check if product exists in products table
        $stmt = $conn->prepare("SELECT id, stock FROM products WHERE item_description = ?");
        if (!$stmt) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Prepare failed for products: " . $conn->error . "\n", FILE_APPEND);
            return false;
        }
        
        $stmt->bind_param("s", $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing product
            $row = $result->fetch_assoc();
            $productId = $row['id'];
            $currentStock = (int)$row['stock'];
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Found in products. Current stock: $currentStock\n", FILE_APPEND);
            
            if ($currentStock >= $quantityNeeded) {
                // Enough stock, deduct from products
                $newStock = $currentStock - $quantityNeeded;
                $updateStmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
                $updateStmt->bind_param("ii", $newStock, $productId);
                
                if (!$updateStmt->execute()) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update product stock: " . $conn->error . "\n", FILE_APPEND);
                    return false;
                }
                
                // Record movement
                recordInventoryMovement($conn, $productName, $quantityNeeded, 'deduction', $po_number, 'Order Activated');
                
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully deducted $quantityNeeded units from stock\n", FILE_APPEND);
            } else {
                // Not enough stock, check if we have ingredients to manufacture
                $checkIngredientsStmt = $conn->prepare("SELECT ingredients FROM products WHERE id = ?");
                $checkIngredientsStmt->bind_param("i", $productId);
                $checkIngredientsStmt->execute();
                $ingredientsResult = $checkIngredientsStmt->get_result();
                
                if ($ingredientsResult->num_rows > 0) {
                    $ingredientsData = $ingredientsResult->fetch_assoc();
                    $ingredients = $ingredientsData['ingredients'];
                    
                    // If we have ingredients data, try to manufacture by deducting raw materials
                    if (!empty($ingredients)) {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Not enough stock. Checking ingredients: $ingredients\n", FILE_APPEND);
                        
                        // Deduct what we have from stock first
                        if ($currentStock > 0) {
                            $updateStmt = $conn->prepare("UPDATE products SET stock = 0 WHERE id = ?");
                            $updateStmt->bind_param("i", $productId);
                            $updateStmt->execute();
                            
                            // Record movement for what we used from stock
                            recordInventoryMovement($conn, $productName, $currentStock, 'deduction', $po_number, 'Order Activated (Partial Stock)');
                            
                            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Deducted $currentStock units from available stock\n", FILE_APPEND);
                        }
                        
                        // Calculate remaining quantity needed
                        $remainingNeeded = $quantityNeeded - $currentStock;
                        
                        // Try to deduct from raw materials for the remainder
                        if (!deductRawMaterials($conn, $productName, $remainingNeeded, $ingredients, $po_number)) {
                            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to deduct raw materials\n", FILE_APPEND);
                            return false;
                        }
                        
                        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully deducted raw materials for remaining $remainingNeeded units\n", FILE_APPEND);
                    } else {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . ": No ingredients data available for manufacturing\n", FILE_APPEND);
                        return false; // Can't manufacture without ingredients data
                    }
                } else {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to get ingredients data\n", FILE_APPEND);
                    return false;
                }
            }
        } else {
            // Product not found
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Product not found in database: $productName\n", FILE_APPEND);
            return false;
        }
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Inventory deduction completed successfully\n", FILE_APPEND);
    return true;
}

// Helper function to deduct raw materials for manufacturing
function deductRawMaterials($conn, $productName, $quantity, $ingredientsJson, $po_number) {
    global $log_dir;
    $log_file = $log_dir . "/inventory_log.txt";
    
    // Try to parse ingredients JSON
    $ingredients = json_decode($ingredientsJson, true);
    if (!$ingredients || !is_array($ingredients)) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to parse ingredients JSON: $ingredientsJson\n", FILE_APPEND);
        return false;
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Processing $quantity units with ingredients: " . json_encode($ingredients) . "\n", FILE_APPEND);
    
    // Check if we have enough of each raw material
    foreach ($ingredients as $ingredient) {
        $materialName = $ingredient['name'];
        $amountNeeded = $ingredient['amount'] * $quantity;
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Checking material: $materialName, amount needed: $amountNeeded\n", FILE_APPEND);
        
        // Check current stock of this material
        $stmt = $conn->prepare("SELECT id, quantity FROM raw_materials WHERE material_name = ?");
        $stmt->bind_param("s", $materialName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Material not found: $materialName\n", FILE_APPEND);
            return false;
        }
        
        $row = $result->fetch_assoc();
        $materialId = $row['id'];
        $currentQuantity = (float)$row['quantity'];
        
        if ($currentQuantity < $amountNeeded) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Not enough material. Have: $currentQuantity, Need: $amountNeeded\n", FILE_APPEND);
            return false;
        }
    }
    
    // If we get here, we have enough of all materials, so deduct them
    foreach ($ingredients as $ingredient) {
        $materialName = $ingredient['name'];
        $amountNeeded = $ingredient['amount'] * $quantity;
        
        $stmt = $conn->prepare("UPDATE raw_materials SET quantity = quantity - ? WHERE material_name = ?");
        $stmt->bind_param("ds", $amountNeeded, $materialName);
        
        if (!$stmt->execute()) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update raw material: " . $conn->error . "\n", FILE_APPEND);
            return false;
        }
        
        // Record movement
        recordRawMaterialMovement($conn, $materialName, $amountNeeded, 'deduction', $po_number, "Manufacturing for $productName");
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Deducted $amountNeeded of $materialName\n", FILE_APPEND);
    }
    
    return true;
}

// Function to return materials to inventory when changing from Active to Pending/Rejected
function processInventoryReturn($conn, $po_number, $ordersJson) {
    global $log_dir;
    $log_file = $log_dir . "/inventory_log.txt";
    $log_message = "Starting inventory return for PO: $po_number\n";
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $log_message, FILE_APPEND);
    
    // Get all inventory movements for this PO where type is 'deduction'
    $stmt = $conn->prepare("SELECT id, item_name, quantity, item_type FROM inventory_movements WHERE po_number = ? AND action_type = 'deduction'");
    
    if (!$stmt) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Prepare failed for inventory movements: " . $conn->error . "\n", FILE_APPEND);
        return false;
    }
    
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No inventory movements found, return items based on order data
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": No inventory movements found. Returning based on order data.\n", FILE_APPEND);
        
        $orders = json_decode($ordersJson, true);
        if (!$orders) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to parse orders JSON\n", FILE_APPEND);
            return false;
        }
        
        foreach ($orders as $item) {
            $productName = $item['item_description'];
            $quantity = (int)$item['quantity'];
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Returning product: $productName, quantity: $quantity\n", FILE_APPEND);
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE item_description = ?");
            $stmt->bind_param("is", $quantity, $productName);
            
            if (!$stmt->execute()) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update product stock: " . $conn->error . "\n", FILE_APPEND);
                continue; // Continue with next item even if this one fails
            }
            
            // Record movement
            recordInventoryMovement($conn, $productName, $quantity, 'addition', $po_number, 'Order Status Change Return');
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully returned $quantity units of $productName\n", FILE_APPEND);
        }
        
        return true;
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Found " . $result->num_rows . " inventory movements to reverse\n", FILE_APPEND);
    
    // Process each movement and return the materials
    while ($row = $result->fetch_assoc()) {
        $movementId = $row['id'];
        $itemName = $row['item_name'];
        $quantity = (float)$row['quantity']; // This was deducted, so it's a positive number
        $itemType = $row['item_type']; // 'product' or 'raw_material'
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Returning $quantity of $itemName (type: $itemType)\n", FILE_APPEND);
        
        if ($itemType === 'product') {
            // Return to products stock
            $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE item_description = ?");
            $stmt->bind_param("is", $quantity, $itemName);
            
            if (!$stmt->execute()) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update product stock: " . $conn->error . "\n", FILE_APPEND);
                continue;
            }
            
            // Record movement
            recordInventoryMovement($conn, $itemName, $quantity, 'addition', $po_number, 'Order Status Change Return');
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully returned $quantity units to product stock\n", FILE_APPEND);
        } else if ($itemType === 'raw_material') {
            // Return to raw materials
            $stmt = $conn->prepare("UPDATE raw_materials SET quantity = quantity + ? WHERE material_name = ?");
            $stmt->bind_param("ds", $quantity, $itemName);
            
            if (!$stmt->execute()) {
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Failed to update raw material quantity: " . $conn->error . "\n", FILE_APPEND);
                continue;
            }
            
            // Record movement
            recordRawMaterialMovement($conn, $itemName, $quantity, 'addition', $po_number, 'Order Status Change Return');
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Successfully returned $quantity units to raw material\n", FILE_APPEND);
        }
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Inventory return completed successfully\n", FILE_APPEND);
    return true;
}

// Helper function to record inventory movements for products
function recordInventoryMovement($conn, $itemName, $quantity, $actionType, $po_number, $reason) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system';
    $itemType = 'product';
    
    $stmt = $conn->prepare("INSERT INTO inventory_movements (item_name, item_type, quantity, action_type, po_number, reason, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ssdssss", $itemName, $itemType, $quantity, $actionType, $po_number, $reason, $userId);
    return $stmt->execute();
}

// Helper function to record inventory movements for raw materials
function recordRawMaterialMovement($conn, $materialName, $quantity, $actionType, $po_number, $reason) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system';
    $itemType = 'raw_material';
    
    $stmt = $conn->prepare("INSERT INTO inventory_movements (item_name, item_type, quantity, action_type, po_number, reason, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ssdssss", $materialName, $itemType, $quantity, $actionType, $po_number, $reason, $userId);
    return $stmt->execute();
}
?>