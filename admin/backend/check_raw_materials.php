<?php
// /backend/check_raw_materials.php
session_start();
include "db_connection.php";

// Basic error handling
header('Content-Type: application/json');

if (!isset($_POST['orders']) || !isset($_POST['po_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data'
    ]);
    exit;
}

try {
    $ordersJsonInput = $_POST['orders'];
    $poNumber = $_POST['po_number'];

    // Decode the input orders JSON
    $orders = json_decode($ordersJsonInput, true);

    // Check if decoding the input orders was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Log the error and the invalid input
        error_log("Failed to decode input orders JSON in check_raw_materials.php. Error: " . json_last_error_msg() . ". Input: " . $ordersJsonInput);
        throw new Exception('Invalid orders data format received.');
    }

    if (!is_array($orders)) {
         throw new Exception('Orders data is not an array.');
    }

    // Initialize arrays to store data
    $requiredMaterials = [];
    $availableMaterials = [];
    $finishedProductsStatus = [];
    $needsManufacturing = false;

    // Process each order item
    foreach ($orders as $order) {
        if (!isset($order['product_id']) || !isset($order['quantity'])) {
            error_log("Skipping order item due to missing product_id or quantity: " . print_r($order, true));
            continue;
        }

        $productId = $order['product_id'];
        $quantity = (int)$order['quantity'];

        // First check if we have enough finished products
        $stmt = $conn->prepare("SELECT product_id, item_description, stock_quantity, ingredients FROM products WHERE product_id = ?");
        if ($stmt === false) {
             throw new Exception("Prepare failed (get product): " . $conn->error);
        }
        $stmt->bind_param("i", $productId);
        if (!$stmt->execute()) {
             throw new Exception("Execute failed (get product): " . $stmt->error);
        }
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $availableQuantity = (int)$row['stock_quantity'];
            $itemDescription = $row['item_description'];

            // --- FIX APPLIED HERE ---
            // Check if ingredients data exists and is a string before decoding
            $ingredientsJson = $row['ingredients'];
            $ingredients = []; // Default to empty array
            if (!empty($ingredientsJson) && is_string($ingredientsJson)) {
                $ingredients = json_decode($ingredientsJson, true);
                // Optionally check for decode errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON Decode Error for product ID " . $row['product_id'] . " ingredients: " . json_last_error_msg() . ". Content: " . $ingredientsJson);
                    $ingredients = []; // Reset to empty on decode error
                    // Optionally add an error message to the product status if needed
                    // $finishedProductsStatus[$itemDescription]['message'] = 'Invalid ingredients format';
                }
            }
            // If $ingredientsJson was null or empty, $ingredients remains []
            // --- END FIX ---


            // Record status of finished product
            $finishedProductsStatus[$itemDescription] = [
                'available' => $availableQuantity,
                'required' => $quantity,
                'sufficient' => $availableQuantity >= $quantity,
                'shortfall' => max(0, $quantity - $availableQuantity)
            ];

            // If not enough finished products, calculate raw materials needed
            if ($availableQuantity < $quantity) {
                $needsManufacturing = true;
                $shortfall = $quantity - $availableQuantity;

                // Make sure there are ingredients defined (check the decoded $ingredients array)
                if (!is_array($ingredients) || count($ingredients) === 0) {
                    // No ingredients defined, can't manufacture
                    $finishedProductsStatus[$itemDescription]['canManufacture'] = false;
                    $finishedProductsStatus[$itemDescription]['message'] = 'No ingredients defined or invalid format';
                     error_log("Product ID {$productId} needs manufacturing but has no valid ingredients defined.");
                    continue; // Skip ingredient calculation for this item
                }

                $finishedProductsStatus[$itemDescription]['canManufacture'] = true;

                // Calculate required raw materials for shortfall
                foreach ($ingredients as $ingredient) {
                    // Check if ingredient format is valid (array with at least 2 elements)
                    if (is_array($ingredient) && count($ingredient) >= 2) {
                        $materialName = $ingredient[0];
                        $materialAmount = (float)$ingredient[1];

                        if (!isset($requiredMaterials[$materialName])) {
                            $requiredMaterials[$materialName] = 0;
                        }

                        $requiredMaterials[$materialName] += $materialAmount * $shortfall;
                    } else {
                         error_log("Invalid ingredient format for product ID {$productId}: " . print_r($ingredient, true));
                    }
                }
            }
        } else {
            error_log("Product ID {$productId} not found in products table.");
            // Handle product not found case if necessary
        }

        $stmt->close();
    }

    // If all finished products are sufficient, no need to check raw materials
    if (!$needsManufacturing) {
        echo json_encode([
            'success' => true,
            'finishedProducts' => $finishedProductsStatus,
            'needsManufacturing' => false,
            'message' => 'All finished products are in stock'
        ]);
        $conn->close(); // Close connection
        exit;
    }

    // If we need manufacturing, get available quantities for each required material
    foreach ($requiredMaterials as $material => $required) {
        $stmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE name = ?");
         if ($stmt === false) {
             throw new Exception("Prepare failed (get material): " . $conn->error);
         }
        $stmt->bind_param("s", $material);
         if (!$stmt->execute()) {
             throw new Exception("Execute failed (get material): " . $stmt->error);
         }
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $availableMaterials[$material] = (float)$row['stock_quantity'];
        } else {
            $availableMaterials[$material] = 0; // Material not found
            error_log("Raw material '{$material}' not found in raw_materials table.");
        }

        $stmt->close();
    }

    // Prepare the response
    $materialsData = [];
    $allMaterialsSufficient = true; // Flag to check overall material sufficiency

    foreach ($requiredMaterials as $material => $required) {
        $available = $availableMaterials[$material] ?? 0; // Use null coalescing operator
        $isSufficient = $available >= $required;
        if (!$isSufficient) {
            $allMaterialsSufficient = false; // Mark as insufficient if any material is short
        }

        $materialsData[$material] = [
            'available' => $available,
            'required' => $required,
            'sufficient' => $isSufficient
        ];
    }

    // Send the final response including material status
    echo json_encode([
        'success' => true,
        'finishedProducts' => $finishedProductsStatus,
        'materials' => $materialsData,
        'needsManufacturing' => true,
        'allMaterialsSufficient' => $allMaterialsSufficient // Add this flag for easier JS checking
    ]);

} catch (Exception $e) {
    error_log("Error in check_raw_materials.php: " . $e->getMessage()); // Log the exception
    // Send a generic error message in production, or detailed in development
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while checking inventory status.' // More generic message
        // 'message' => $e->getMessage() // Use this for debugging only
    ]);
} finally {
    // Ensure connection is closed even if an exception occurred earlier
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}

exit; // Ensure no further output
?>