<?php
header('Content-Type: application/json');
include 'db_connection.php'; // Ensure this path is correct

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection error in check_raw_materials.']);
    exit;
}

$orders_json = $_POST['orders'] ?? null;
// $po_number = $_POST['po_number'] ?? null; // Not strictly needed for just checking

if (!$orders_json) {
    echo json_encode(['success' => false, 'message' => 'Order items not provided.']);
    exit;
}

$ordered_items = json_decode($orders_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($ordered_items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order items format.']);
    exit;
}

$required_raw_materials = [];
$all_raw_materials_sufficient = true;
$raw_material_details = []; // To send back to the frontend

try {
    // Get all product IDs from the order to fetch their recipes
    $product_ids_in_order = [];
    foreach ($ordered_items as $item) {
        if (isset($item['product_id'])) {
            $product_ids_in_order[] = $conn->real_escape_string($item['product_id']);
        }
    }

    if (empty($product_ids_in_order)) {
        // No products that would require raw materials, or product_id missing
        echo json_encode([
            'success' => true, 
            'message' => 'No specific products identified for raw material check.',
            'materials' => [], // No materials to report
            'all_sufficient' => true // Vacuously true
        ]);
        exit;
    }

    $product_id_list_sql = "'" . implode("','", $product_ids_in_order) . "'";

    // Fetch recipes for all products in the order
    $sql_recipes = "SELECT pr.product_id, pr.raw_material_id, pr.quantity_required, rm.material_name, rm.current_stock 
                    FROM product_recipes pr
                    JOIN raw_materials rm ON pr.raw_material_id = rm.id
                    WHERE pr.product_id IN ({$product_id_list_sql})";
    
    $result_recipes = $conn->query($sql_recipes);
    if (!$result_recipes) {
        throw new Exception("Error fetching product recipes: " . $conn->error);
    }

    $recipes_by_product = [];
    while ($row = $result_recipes->fetch_assoc()) {
        $recipes_by_product[$row['product_id']][] = $row;
    }

    // Calculate total raw materials needed
    foreach ($ordered_items as $item) {
        $product_id = $item['product_id'] ?? null;
        $order_quantity = intval($item['quantity'] ?? 0);

        if ($product_id && $order_quantity > 0 && isset($recipes_by_product[$product_id])) {
            foreach ($recipes_by_product[$product_id] as $ingredient) {
                $rm_id = $ingredient['raw_material_id'];
                $qty_per_product = floatval($ingredient['quantity_required']);
                $total_needed_for_item = $qty_per_product * $order_quantity;

                if (!isset($required_raw_materials[$rm_id])) {
                    $required_raw_materials[$rm_id] = [
                        'name' => $ingredient['material_name'],
                        'total_required' => 0,
                        'available' => floatval($ingredient['current_stock']) // Get current stock once per material
                    ];
                }
                $required_raw_materials[$rm_id]['total_required'] += $total_needed_for_item;
            }
        }
    }

    // Check sufficiency and prepare details
    foreach ($required_raw_materials as $rm_id => $data) {
        $is_sufficient = ($data['available'] >= $data['total_required']);
        if (!$is_sufficient) {
            $all_raw_materials_sufficient = false;
        }
        $raw_material_details[$data['name']] = [
            'required' => $data['total_required'],
            'available' => $data['available'],
            'sufficient' => $is_sufficient,
            'shortfall' => $is_sufficient ? 0 : ($data['total_required'] - $data['available'])
        ];
    }
    
    // No need to check finished products here

    echo json_encode([
        'success' => true,
        'materials' => $raw_material_details, // Keyed by material name
        'all_sufficient' => $all_raw_materials_sufficient,
        // 'needsManufacturing' can be inferred by client if 'all_sufficient' is true and order is for products that have recipes
    ]);

} catch (Exception $e) {
    error_log("Error in check_raw_materials.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error during raw material check: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>