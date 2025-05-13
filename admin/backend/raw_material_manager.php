<?php

/**
 * Checks raw material availability for an order.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items.
 * @return array ['all_sufficient' => bool, 'materials' => [details_array], 'message' => string (optional error message)]
 */
function check_raw_materials_for_order($conn, $orders_json) {
    $ordered_items = json_decode($orders_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($ordered_items)) {
        return ['all_sufficient' => false, 'materials' => [], 'message' => 'Invalid order items format for material check.'];
    }

    $required_raw_materials_map = []; // Keyed by raw_material_id
    $all_sufficient = true;
    $material_details_for_response = []; // Keyed by material name for frontend

    $product_ids_in_order = array_unique(array_map(function($item) { return $item['product_id'] ?? null; }, $ordered_items));
    $product_ids_in_order = array_filter($product_ids_in_order); // Remove nulls

    if (empty($product_ids_in_order)) {
        return ['all_sufficient' => true, 'materials' => [], 'message' => 'No products with IDs to check for materials.'];
    }

    $product_id_list_sql = "'" . implode("','", array_map([$conn, 'real_escape_string'], $product_ids_in_order)) . "'";
    
    // Fetch recipes and current stock of raw materials
    $sql_recipes = "SELECT pr.product_id, pr.raw_material_id, pr.quantity_required, rm.material_name, rm.current_stock 
                    FROM product_recipes pr
                    JOIN raw_materials rm ON pr.raw_material_id = rm.id
                    WHERE pr.product_id IN ({$product_id_list_sql})";
    
    $result_recipes = $conn->query($sql_recipes);
    if (!$result_recipes) {
        error_log("RawMaterialManager: Error fetching recipes: " . $conn->error);
        return ['all_sufficient' => false, 'materials' => [], 'message' => 'Error fetching product recipes: ' . $conn->error];
    }

    $recipes_by_product = [];
    while ($row = $result_recipes->fetch_assoc()) {
        $recipes_by_product[$row['product_id']][] = $row;
    }

    // Calculate total raw materials needed across all items in the order
    foreach ($ordered_items as $item) {
        $product_id = $item['product_id'] ?? null;
        $order_quantity = intval($item['quantity'] ?? 0);

        if ($product_id && $order_quantity > 0 && isset($recipes_by_product[$product_id])) {
            foreach ($recipes_by_product[$product_id] as $ingredient) {
                $rm_id = $ingredient['raw_material_id'];
                $qty_per_product = floatval($ingredient['quantity_required']);
                $total_needed_for_this_ingredient_for_this_item = $qty_per_product * $order_quantity;

                if (!isset($required_raw_materials_map[$rm_id])) {
                    $required_raw_materials_map[$rm_id] = [
                        'name' => $ingredient['material_name'],
                        'total_required' => 0,
                        'available' => floatval($ingredient['current_stock']) // Stock level from DB
                    ];
                }
                $required_raw_materials_map[$rm_id]['total_required'] += $total_needed_for_this_ingredient_for_this_item;
            }
        }
    }

    // Check sufficiency and prepare details for response
    foreach ($required_raw_materials_map as $rm_id => $data) {
        $is_material_sufficient = ($data['available'] >= $data['total_required']);
        if (!$is_material_sufficient) {
            $all_sufficient = false;
        }
        // For frontend display, key by material name
        $material_details_for_response[$data['name']] = [
            'id' => $rm_id, // Include ID for potential direct use if needed later
            'required' => $data['total_required'],
            'available' => $data['available'],
            'sufficient' => $is_material_sufficient,
            'shortfall' => $is_material_sufficient ? 0 : ($data['total_required'] - $data['available'])
        ];
    }
    
    return ['all_sufficient' => $all_sufficient, 'materials' => $material_details_for_response];
}


/**
 * Deducts raw materials for an order. Assumes materials were pre-checked for sufficiency.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success, false on failure.
 * @throws Exception On critical database errors.
 */
function deduct_raw_materials_for_order($conn, $orders_json, $po_number) {
    // Recalculate requirements to ensure we deduct the correct amounts based on the order items
    // This is safer than relying on a potentially stale check result passed as a parameter.
    $material_requirements = check_raw_materials_for_order($conn, $orders_json);

    if (!$material_requirements['all_sufficient']) {
        // This case should ideally be caught before calling deduct, but as a safeguard:
        $missing_list = implode(', ', array_keys(array_filter($material_requirements['materials'], fn($m) => !$m['sufficient'])));
        error_log("RawMaterialManager: CRITICAL - Attempted to deduct for PO {$po_number} when materials are insufficient. Missing: {$missing_list}");
        throw new Exception("Deduction aborted: Raw materials are insufficient for PO {$po_number}. Missing: {$missing_list}");
    }

    if (empty($material_requirements['materials'])) {
        error_log("RawMaterialManager: No raw materials to deduct for PO {$po_number}.");
        return true; // Nothing to deduct, considered a success
    }
    
    // Prepare statement for deduction
    $stmt_deduct = $conn->prepare("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
    if (!$stmt_deduct) {
        error_log("RawMaterialManager: Prepare statement failed for deducting stock: " . $conn->error);
        throw new Exception("Database error preparing for stock deduction: " . $conn->error);
    }

    foreach ($material_requirements['materials'] as $material_name => $data) {
        if ($data['required'] > 0) { // Only deduct if actually required
            $qty_to_deduct = $data['required'];
            $rm_id = $data['id'];

            $stmt_deduct->bind_param("did", $qty_to_deduct, $rm_id, $qty_to_deduct); // Use "d" for decimal/float quantities
            if (!$stmt_deduct->execute()) {
                $stmt_deduct->close(); // Close statement before throwing
                error_log("RawMaterialManager: Failed to deduct {$qty_to_deduct} of {$material_name} (ID: {$rm_id}) for PO {$po_number}. Error: " . $conn->error);
                throw new Exception("Failed to deduct raw material: {$material_name} for PO {$po_number}. Error: " . $conn->error);
            }
            if ($stmt_deduct->affected_rows === 0) {
                // This means stock became insufficient between check and deduct (race condition)
                // OR the material ID was somehow incorrect (less likely if check_raw_materials_for_order is robust)
                $stmt_deduct->close();
                error_log("RawMaterialManager: Deduction of {$qty_to_deduct} for {$material_name} (ID: {$rm_id}) affected 0 rows for PO {$po_number}. Stock might have changed or ID issue. This indicates a potential race condition or data inconsistency.");
                throw new Exception("Stock level changed for {$material_name} during deduction for PO {$po_number}. Deduction failed.");
            }
            error_log("RawMaterialManager: Deducted {$qty_to_deduct} of {$material_name} (ID: {$rm_id}) for PO {$po_number}.");
        }
    }
    $stmt_deduct->close();
    return true;
}

/**
 * Returns (adds back) raw materials for an order, e.g., when an Active order is cancelled or rejected.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items from the order.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success, false on failure (though it tries to return all, logs errors).
 * @throws Exception On critical database errors like prepare statement failure.
 */
function return_raw_materials_for_order($conn, $orders_json, $po_number) {
    // Calculate how much was *supposed* to be used based on the order's items
    $material_requirements = check_raw_materials_for_order($conn, $orders_json);
    // We don't care about 'all_sufficient' here, just what was supposed to be used.

    if (empty($material_requirements['materials'])) {
        error_log("RawMaterialManager: No raw materials calculated to return for PO {$po_number}.");
        return true; // Nothing to return
    }

    $stmt_return = $conn->prepare("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?");
    if (!$stmt_return) {
        error_log("RawMaterialManager: Prepare statement failed for returning stock: " . $conn->error);
        throw new Exception("Database error preparing for stock return: " . $conn->error);
    }

    $all_returns_succeeded = true;
    foreach ($material_requirements['materials'] as $material_name => $data) {
        if ($data['required'] > 0) { // Only return if it was supposed to be used
            $qty_to_return = $data['required'];
            $rm_id = $data['id'];

            $stmt_return->bind_param("di", $qty_to_return, $rm_id); // Use "d" for decimal/float quantities
            if (!$stmt_return->execute()) {
                error_log("RawMaterialManager: Failed to return {$qty_to_return} of {$material_name} (ID: {$rm_id}) for PO {$po_number}. Error: " . $conn->error);
                $all_returns_succeeded = false; // Log failure but continue trying other materials
            } else {
                error_log("RawMaterialManager: Returned {$qty_to_return} of {$material_name} (ID: {$rm_id}) for PO {$po_number}.");
            }
        }
    }
    $stmt_return->close();
    
    if (!$all_returns_succeeded) {
        // Optionally, you could throw an exception here if partial return is unacceptable
        // For now, it logs errors and returns based on overall attempt.
        error_log("RawMaterialManager: One or more raw materials failed to return for PO {$po_number}. Check logs.");
    }
    return $all_returns_succeeded; // Or true if partial success is acceptable with logging
}

?>