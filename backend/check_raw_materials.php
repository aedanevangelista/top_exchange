<?php
// /backend/check_raw_materials.php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Ensure user has appropriate access
checkAccess();

header('Content-Type: application/json');

// Check if order data was provided
if (!isset($_POST['orders']) || !isset($_POST['po_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data'
    ]);
    exit;
}

try {
    $orders = json_decode($_POST['orders'], true);
    
    if (!is_array($orders)) {
        throw new Exception('Invalid orders data');
    }
    
    $poNumber = $_POST['po_number'];
    
    // Fetch product information with ingredients data
    $productIngredients = [];
    $productIds = array_map(function($order) {
        return $order['product_id'];
    }, $orders);
    
    if (empty($productIds)) {
        echo json_encode([
            'success' => false,
            'message' => 'No products in order'
        ]);
        exit;
    }
    
    // Create placeholder string for SQL query
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    // Fetch products with ingredients
    $stmt = $conn->prepare("SELECT product_id, item_description, ingredients FROM products WHERE product_id IN ($placeholders)");
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    // Bind product IDs to the query
    $stmt->bind_param(str_repeat('i', count($productIds)), ...$productIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $productIngredients[$row['product_id']] = [
            'name' => $row['item_description'],
            'ingredients' => json_decode($row['ingredients'], true)
        ];
    }
    
    $stmt->close();
    
    // Calculate required materials
    $requiredMaterials = [];
    
    foreach ($orders as $order) {
        $productId = $order['product_id'];
        $quantity = $order['quantity'];
        
        if (isset($productIngredients[$productId]) && is_array($productIngredients[$productId]['ingredients'])) {
            $ingredients = $productIngredients[$productId]['ingredients'];
            
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
    
    // Fetch available materials
    $availableMaterials = [];
    $materialNames = array_keys($requiredMaterials);
    
    if (!empty($materialNames)) {
        $placeholders = str_repeat('?,', count($materialNames) - 1) . '?';
        
        $stmt = $conn->prepare("SELECT name, stock_quantity FROM raw_materials WHERE name IN ($placeholders)");
        
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param(str_repeat('s', count($materialNames)), ...$materialNames);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $availableMaterials[$row['name']] = floatval($row['stock_quantity']);
        }
        
        $stmt->close();
    }
    
    // Prepare response data
    $materialsData = [];
    
    foreach ($requiredMaterials as $material => $requiredAmount) {
        $availableAmount = isset($availableMaterials[$material]) ? $availableMaterials[$material] : 0;
        
        $materialsData[$material] = [
            'available' => $availableAmount,
            'required' => $requiredAmount
        ];
    }
    
    echo json_encode([
        'success' => true,
        'materials' => $materialsData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper function to check access
function checkAccess() {
    // Check if the user is logged in
    if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access'
        ]);
        exit;
    }
}
?>