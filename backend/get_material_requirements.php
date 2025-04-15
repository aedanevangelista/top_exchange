<?php
include 'db_connection.php';
include 'check_raw_materials.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Get POST data
        $orders = $_POST['orders'];

        // Validate that orders is valid JSON
        $decoded_orders = json_decode($orders, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid order data format');
        }

        // Initialize arrays to track required materials
        $requiredMaterials = [];

        // Calculate total required materials for all products in the order
        foreach ($decoded_orders as $order) {
            $productId = $order['product_id'];
            $quantity = (int)$order['quantity'];
            
            // Get product ingredients
            $stmt = $conn->prepare("SELECT ingredients FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $ingredients = json_decode($row['ingredients'], true);
                
                if ($ingredients) {
                    foreach ($ingredients as $ingredient) {
                        $materialName = $ingredient[0];
                        $materialAmount = $ingredient[1] * $quantity; // Multiply by order quantity
                        
                        if (!isset($requiredMaterials[$materialName])) {
                            $requiredMaterials[$materialName] = ['required' => 0];
                        }
                        $requiredMaterials[$materialName]['required'] += $materialAmount;
                    }
                }
            }
            $stmt->close();
        }

        // Get current raw material stock quantities
        $availableMaterials = [];
        foreach (array_keys($requiredMaterials) as $materialName) {
            $stmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE name = ?");
            $stmt->bind_param("s", $materialName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $availableMaterials[$materialName] = [
                    'available' => $row['stock_quantity']
                ];
            } else {
                $availableMaterials[$materialName] = [
                    'available' => 0
                ];
            }
            $stmt->close();
        }

        echo json_encode([
            'success' => true,
            'materials' => $requiredMaterials,
            'availableMaterials' => $availableMaterials
        ]);
        
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