<?php
header('Content-Type: application/json');
include 'db_connection.php'; // Ensure this path is correct

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection error in check_raw_materials.']);
    exit;
}

$orders_json = $_POST['orders'] ?? null;

if (!$orders_json) {
    echo json_encode(['success' => false, 'message' => 'Order items not provided.']);
    exit;
}

$ordered_items = json_decode($orders_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($ordered_items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order items format.']);
    exit;
}

$required_raw_materials_aggregated = [];
$all_raw_materials_sufficient = true;
$raw_material_details_for_response = [];

try {
    $product_ids_in_order = [];
    foreach ($ordered_items as $item) {
        if (isset($item['product_id'])) {
            $product_ids_in_order[] = $conn->real_escape_string(strval($item['product_id']));
        }
    }

    if (empty($product_ids_in_order)) {
        echo json_encode([
            'success' => true,
            'message' => 'No specific products identified for raw material check.',
            'materials' => [],
            'all_sufficient' => true
        ]);
        exit;
    }

    $product_id_list_sql = "'" . implode("','", $product_ids_in_order) . "'";

    // 1. Fetch ingredients JSON for all products in the order from the 'products' table
    $sql_product_ingredients = "SELECT product_id, ingredients FROM products WHERE product_id IN ({$product_id_list_sql})";
    $result_product_ingredients = $conn->query($sql_product_ingredients);

    if (!$result_product_ingredients) {
        throw new Exception("Error fetching product ingredients from 'products' table: " . $conn->error);
    }

    $product_material_requirements = [];
    $all_distinct_material_names = []; // Stores names like "Minced Pork", "Soy Sauce"

    while ($row = $result_product_ingredients->fetch_assoc()) {
        $p_id = $row['product_id'];
        $ingredients_json = $row['ingredients']; // This is from the 'products' table

        if ($ingredients_json) {
            $decoded_ingredients = json_decode($ingredients_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ingredients)) {
                $product_material_requirements[$p_id] = [];
                foreach ($decoded_ingredients as $ingredient_pair) {
                    if (is_array($ingredient_pair) && count($ingredient_pair) >= 2) {
                        $material_name_from_recipe = $ingredient_pair[0];
                        $quantity_required_per_unit = floatval($ingredient_pair[1]);
                        $product_material_requirements[$p_id][] = [
                            'material_name' => $material_name_from_recipe,
                            'quantity_required' => $quantity_required_per_unit
                        ];
                        if (!in_array($material_name_from_recipe, $all_distinct_material_names)) {
                            $all_distinct_material_names[] = $material_name_from_recipe;
                        }
                    }
                }
            } else {
                error_log("Invalid ingredients JSON for product_id {$p_id}: {$ingredients_json}");
            }
        }
    }

    // 2. Fetch current stock levels for all distinct raw materials identified
    //    This queries the 'raw_materials' table.
    $raw_material_stock_levels = [];
    if (!empty($all_distinct_material_names)) {
        $material_names_sql_safe = array_map(function($name) use ($conn) {
            return "'" . $conn->real_escape_string($name) . "'";
        }, $all_distinct_material_names);
        $material_names_list_sql = implode(",", $material_names_sql_safe);

        // --- MODIFIED SQL QUERY ---
        // Assumes 'raw_materials' table has:
        //   - 'material_name' (or similar) for the name of the raw material (e.g., "Minced Pork")
        //   - 'stock_quantity' for its current stock.
        $sql_raw_material_stocks = "SELECT material_name, stock_quantity FROM raw_materials WHERE material_name IN ({$material_names_list_sql})";
        
        $result_raw_material_stocks = $conn->query($sql_raw_material_stocks);

        if (!$result_raw_material_stocks) {
            throw new Exception("Error fetching raw material stocks from 'raw_materials' table: " . $conn->error . ". Query: " . $sql_raw_material_stocks);
        }
        while ($row_stock = $result_raw_material_stocks->fetch_assoc()) {
            // --- ADJUSTED ARRAY KEYS ---
            // Uses 'material_name' from the SELECT to key the stocks, and 'stock_quantity' for the value.
            $raw_material_stock_levels[$row_stock['material_name']] = floatval($row_stock['stock_quantity']);
        }
    }

    // 3. Calculate total raw materials needed
    foreach ($ordered_items as $item) {
        $product_id = $item['product_id'] ?? null;
        $order_quantity = intval($item['quantity'] ?? 0);

        if ($product_id && $order_quantity > 0 && isset($product_material_requirements[$product_id])) {
            foreach ($product_material_requirements[$product_id] as $ingredient_recipe) {
                $material_name = $ingredient_recipe['material_name'];
                $qty_per_product_unit = floatval($ingredient_recipe['quantity_required']);
                $total_needed_for_this_item_and_material = $qty_per_product_unit * $order_quantity;

                $current_stock_for_material = $raw_material_stock_levels[$material_name] ?? 0;

                if (!isset($required_raw_materials_aggregated[$material_name])) {
                    $required_raw_materials_aggregated[$material_name] = [
                        'name' => $material_name,
                        'total_required' => 0,
                        'available' => $current_stock_for_material
                    ];
                }
                $required_raw_materials_aggregated[$material_name]['total_required'] += $total_needed_for_this_item_and_material;
            }
        }
    }

    // 4. Check sufficiency and prepare details for the response
    foreach ($required_raw_materials_aggregated as $material_name => $data) {
        $is_sufficient = ($data['available'] >= $data['total_required']);
        if (!$is_sufficient) {
            $all_raw_materials_sufficient = false;
        }
        $raw_material_details_for_response[$material_name] = [
            'required' => $data['total_required'],
            'available' => $data['available'],
            'sufficient' => $is_sufficient,
            'shortfall' => $is_sufficient ? 0 : ($data['total_required'] - $data['available'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'materials' => $raw_material_details_for_response,
        'all_sufficient' => $all_raw_materials_sufficient,
    ]);

} catch (Exception $e) {
    error_log("Error in check_raw_materials.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error during raw material check. Details: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>