<?php

/**
 * Checks raw material availability for an order using the 'ingredients' column from the 'products' table
 * and the correct column names from the 'raw_materials' table.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items (product_id, quantity).
 * @return array ['all_sufficient' => bool, 'materials' => [details_array], 'message' => string (optional error message)]
 */
function check_raw_materials_for_order($conn, $orders_json) {
    $ordered_items = json_decode($orders_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($ordered_items)) {
        return ['all_sufficient' => false, 'materials' => [], 'message' => 'Invalid order items format for material check.'];
    }

    $required_raw_materials_map = []; // Keyed by material_id (from raw_materials table)
    $all_sufficient = true;
    $material_details_for_response = []; // Keyed by material name for frontend display

    if (empty($ordered_items)) {
        return ['all_sufficient' => true, 'materials' => [], 'message' => 'No items in the order to check for materials.'];
    }

    $product_ids_in_order = array_unique(array_map(function($item) { return $item['product_id'] ?? null; }, $ordered_items));
    $product_ids_in_order = array_filter($product_ids_in_order, 'is_numeric');

    if (empty($product_ids_in_order)) {
        return ['all_sufficient' => true, 'materials' => [], 'message' => 'No valid product IDs in the order.'];
    }

    $product_id_placeholders = implode(',', array_fill(0, count($product_ids_in_order), '?'));
    $sql_products = "SELECT product_id, ingredients FROM products WHERE product_id IN ({$product_id_placeholders})";
    
    $stmt_products = $conn->prepare($sql_products);
    if (!$stmt_products) {
        error_log("RawMaterialManager: Prepare failed (fetch products for ingredients): " . $conn->error);
        return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error fetching product ingredients.'];
    }
    $types = str_repeat('i', count($product_ids_in_order));
    $stmt_products->bind_param($types, ...$product_ids_in_order);
    
    if (!$stmt_products->execute()) {
        error_log("RawMaterialManager: Execute failed (fetch products for ingredients): " . $stmt_products->error);
        $stmt_products->close();
        return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error executing product ingredients fetch.'];
    }
    
    $result_products = $stmt_products->get_result();
    $product_ingredients_map = [];
    while ($row = $result_products->fetch_assoc()) {
        if (!empty($row['ingredients'])) {
            $decoded_ingredients = json_decode($row['ingredients'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ingredients)) {
                $product_ingredients_map[$row['product_id']] = $decoded_ingredients;
            } else {
                error_log("RawMaterialManager: Failed to decode ingredients JSON for product_id {$row['product_id']}. JSON: " . $row['ingredients'] . ". Error: " . json_last_error_msg());
            }
        }
    }
    $stmt_products->close();

    $all_ingredient_names_from_json = [];
    foreach ($product_ingredients_map as $product_id => $ingredients_for_product) {
        foreach ($ingredients_for_product as $ingredient_item) {
            if (isset($ingredient_item[0]) && is_string($ingredient_item[0])) {
                $all_ingredient_names_from_json[] = $ingredient_item[0];
            }
        }
    }
    $unique_ingredient_names_from_json = array_unique($all_ingredient_names_from_json);

    $raw_material_db_info = []; // Map material name (from JSON) to its DB record (material_id, name, stock_quantity)
    if (!empty($unique_ingredient_names_from_json)) {
        $name_placeholders = implode(',', array_fill(0, count($unique_ingredient_names_from_json), '?'));
        // Corrected column names for raw_materials table
        $sql_raw_materials = "SELECT material_id, name, stock_quantity FROM raw_materials WHERE name IN ({$name_placeholders})";
        
        $stmt_raw_materials = $conn->prepare($sql_raw_materials);
        if (!$stmt_raw_materials) {
             error_log("RawMaterialManager: Prepare failed (fetch raw materials by name): " . $conn->error);
             return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error fetching raw material details.'];
        }
        $types_names = str_repeat('s', count($unique_ingredient_names_from_json));
        $stmt_raw_materials->bind_param($types_names, ...$unique_ingredient_names_from_json);

        if (!$stmt_raw_materials->execute()) {
            error_log("RawMaterialManager: Execute failed (fetch raw materials by name): " . $stmt_raw_materials->error);
            $stmt_raw_materials->close();
            return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error executing raw material details fetch.'];
        }
        $result_raw_materials = $stmt_raw_materials->get_result();
        while ($rm_row = $result_raw_materials->fetch_assoc()) {
            // Key by the 'name' column from raw_materials, which should match the name in products.ingredients JSON
            $raw_material_db_info[$rm_row['name']] = $rm_row; 
        }
        $stmt_raw_materials->close();
    }
    
    foreach ($ordered_items as $order_item) {
        $product_id = $order_item['product_id'] ?? null;
        $order_quantity = intval($order_item['quantity'] ?? 0);

        if (!$product_id || $order_quantity <= 0 || !isset($product_ingredients_map[$product_id])) {
            continue;
        }

        $ingredients_for_this_product = $product_ingredients_map[$product_id];
        foreach ($ingredients_for_this_product as $ingredient_components) {
            if (count($ingredient_components) < 2 || !is_string($ingredient_components[0]) || !is_numeric($ingredient_components[1])) {
                error_log("RawMaterialManager: Malformed ingredient component for product_id {$product_id}: " . print_r($ingredient_components, true));
                continue;
            }
            $raw_material_name_from_json = $ingredient_components[0]; // Name as per products.ingredients
            $qty_per_product_unit = floatval($ingredient_components[1]);
            
            if (!isset($raw_material_db_info[$raw_material_name_from_json])) {
                error_log("RawMaterialManager: Raw material '{$raw_material_name_from_json}' (from product_id {$product_id}) not found in raw_materials table. Skipping this ingredient.");
                $all_sufficient = false; 
                 if (!isset($material_details_for_response[$raw_material_name_from_json])) {
                    $material_details_for_response[$raw_material_name_from_json] = [
                        'id' => null, // material_id from raw_materials table
                        'name_from_db' => null, // Actual name from raw_materials table (if found)
                        'required' => 0,
                        'available' => 0,
                        'sufficient' => false,
                        'shortfall' => 0,
                        'error' => "Raw material '{$raw_material_name_from_json}' not found in database."
                    ];
                }
                $material_details_for_response[$raw_material_name_from_json]['required'] += ($qty_per_product_unit * $order_quantity);
                continue;
            }

            $rm_db_record = $raw_material_db_info[$raw_material_name_from_json];
            $rm_id_from_db = $rm_db_record['material_id']; // Use material_id
            $current_rm_stock_from_db = floatval($rm_db_record['stock_quantity']); // Use stock_quantity
            $total_needed_for_this_ingredient_for_this_item = $qty_per_product_unit * $order_quantity;

            if (!isset($required_raw_materials_map[$rm_id_from_db])) {
                $required_raw_materials_map[$rm_id_from_db] = [
                    'name_from_db' => $rm_db_record['name'], // Store the actual name from raw_materials table
                    'total_required' => 0,
                    'available' => $current_rm_stock_from_db 
                ];
            }
            $required_raw_materials_map[$rm_id_from_db]['total_required'] += $total_needed_for_this_ingredient_for_this_item;
        }
    }

    foreach ($required_raw_materials_map as $rm_id => $data) {
        $is_material_sufficient = ($data['available'] >= $data['total_required']);
        if (!$is_material_sufficient) {
            $all_sufficient = false;
        }
        // For frontend display, key by material name (the one from DB)
        $material_details_for_response[$data['name_from_db']] = [
            'id' => $rm_id, // This is material_id
            'name_from_db' => $data['name_from_db'],
            'required' => $data['total_required'],
            'available' => $data['available'],
            'sufficient' => $is_material_sufficient,
            'shortfall' => $is_material_sufficient ? 0 : ($data['total_required'] - $data['available'])
        ];
    }
    
    foreach ($material_details_for_response as $name => $details) {
        if (isset($details['error']) && $details['required'] > 0) {
             $all_sufficient = false; 
        }
    }
    
    $message = $all_sufficient ? 'All raw materials are sufficient.' : 'One or more raw materials are insufficient or have issues.';
    if (!$all_sufficient) {
        $issues = [];
        foreach ($material_details_for_response as $name_key => $details) { // $name_key here is the name from products.ingredients or raw_materials.name
            if (isset($details['error'])) {
                $issues[] = $details['error'];
            } elseif (!$details['sufficient']) {
                // Use $details['name_from_db'] if available, otherwise $name_key (which might be the one from JSON if DB entry was missing)
                $display_name = $details['name_from_db'] ?? $name_key;
                $issues[] = "{$display_name} (Needs: {$details['required']}, Has: {$details['available']})";
            }
        }
        if (!empty($issues)) {
            $message = "Material issues: " . implode('; ', $issues);
        }
    }

    return ['all_sufficient' => $all_sufficient, 'materials' => $material_details_for_response, 'message' => $message];
}


/**
 * Deducts raw materials for an order. Assumes materials were pre-checked for sufficiency.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success.
 * @throws Exception On critical database errors or if deduction leads to negative stock (safeguard).
 */
function deduct_raw_materials_for_order($conn, $orders_json, $po_number) {
    $material_requirements_check = check_raw_materials_for_order($conn, $orders_json);

    if (!$material_requirements_check['all_sufficient']) {
        $detailed_message = $material_requirements_check['message'] ?? 'Raw materials are insufficient or unavailable.';
        error_log("RawMaterialManager: CRITICAL - Attempted to deduct for PO {$po_number} when materials are insufficient. Details: {$detailed_message}");
        throw new Exception("Deduction aborted for PO {$po_number}: {$detailed_message}");
    }

    if (empty($material_requirements_check['materials'])) {
        error_log("RawMaterialManager: No raw materials identified to deduct for PO {$po_number}.");
        return true;
    }
    
    // Corrected column names for raw_materials table
    $stmt_deduct = $conn->prepare("UPDATE raw_materials SET stock_quantity = stock_quantity - ? WHERE material_id = ? AND stock_quantity >= ?");
    if (!$stmt_deduct) {
        error_log("RawMaterialManager: Prepare statement failed for deducting stock: " . $conn->error);
        throw new Exception("Database error preparing for stock deduction: " . $conn->error);
    }

    foreach ($material_requirements_check['materials'] as $material_name_key => $data) { // $material_name_key is the name from DB or JSON
        if (!isset($data['id']) || is_null($data['id'])) { // 'id' here refers to material_id
            if ($data['required'] > 0) {
                 error_log("RawMaterialManager: Skipping deduction for '{$material_name_key}' (PO: {$po_number}) as its material_id is missing or it had errors during check.");
            }
            continue;
        }

        if ($data['required'] > 0) { 
            $qty_to_deduct = $data['required'];
            $rm_id_to_update = $data['id']; // This is material_id
            $actual_material_name_from_db = $data['name_from_db'] ?? $material_name_key; // Prefer name from DB for logging

            $stmt_deduct->bind_param("did", $qty_to_deduct, $rm_id_to_update, $qty_to_deduct); 
            if (!$stmt_deduct->execute()) {
                $err_msg = $stmt_deduct->error;
                $stmt_deduct->close(); 
                error_log("RawMaterialManager: Failed to deduct {$qty_to_deduct} of {$actual_material_name_from_db} (material_id: {$rm_id_to_update}) for PO {$po_number}. Error: " . $err_msg);
                throw new Exception("Failed to deduct raw material: {$actual_material_name_from_db} for PO {$po_number}. Error: " . $err_msg);
            }
            if ($stmt_deduct->affected_rows === 0) {
                $stmt_deduct->close();
                error_log("RawMaterialManager: Deduction of {$qty_to_deduct} for {$actual_material_name_from_db} (material_id: {$rm_id_to_update}) affected 0 rows for PO {$po_number}. Stock might have changed or ID issue.");
                throw new Exception("Stock level for {$actual_material_name_from_db} became insufficient during deduction for PO {$po_number}. Deduction failed.");
            }
            error_log("RawMaterialManager: Deducted {$qty_to_deduct} of {$actual_material_name_from_db} (material_id: {$rm_id_to_update}) for PO {$po_number}.");
        }
    }
    $stmt_deduct->close();
    return true;
}

/**
 * Returns (adds back) raw materials for an order.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items from the order.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success (even if some individual returns fail but are logged).
 * @throws Exception On critical database errors like prepare statement failure.
 */
function return_raw_materials_for_order($conn, $orders_json, $po_number) {
    $material_requirements_check = check_raw_materials_for_order($conn, $orders_json);

    if (empty($material_requirements_check['materials'])) {
        error_log("RawMaterialManager: No raw materials calculated to return for PO {$po_number}.");
        return true;
    }

    // Corrected column names for raw_materials table
    $stmt_return = $conn->prepare("UPDATE raw_materials SET stock_quantity = stock_quantity + ? WHERE material_id = ?");
    if (!$stmt_return) {
        error_log("RawMaterialManager: Prepare statement failed for returning stock: " . $conn->error);
        throw new Exception("Database error preparing for stock return: " . $conn->error);
    }

    $all_returns_succeeded_log = true;
    foreach ($material_requirements_check['materials'] as $material_name_key => $data) {
         if (!isset($data['id']) || is_null($data['id'])) { // 'id' here refers to material_id
            if ($data['required'] > 0) {
                 error_log("RawMaterialManager: Cannot return '{$material_name_key}' for PO {$po_number} as its material_id is missing or it had errors during check.");
            }
            continue;
        }

        if ($data['required'] > 0) { 
            $qty_to_return = $data['required'];
            $rm_id_to_update = $data['id']; // This is material_id
            $actual_material_name_from_db = $data['name_from_db'] ?? $material_name_key; // Prefer name from DB for logging

            $stmt_return->bind_param("di", $qty_to_return, $rm_id_to_update); 
            if (!$stmt_return->execute()) {
                error_log("RawMaterialManager: Failed to return {$qty_to_return} of {$actual_material_name_from_db} (material_id: {$rm_id_to_update}) for PO {$po_number}. Error: " . $stmt_return->error);
                $all_returns_succeeded_log = false;
            } else {
                error_log("RawMaterialManager: Returned {$qty_to_return} of {$actual_material_name_from_db} (material_id: {$rm_id_to_update}) for PO {$po_number}.");
            }
        }
    }
    $stmt_return->close();
    
    if (!$all_returns_succeeded_log) {
        error_log("RawMaterialManager: One or more raw materials failed to return for PO {$po_number}. Check logs. Manual inventory adjustment may be needed.");
    }
    return true; 
}

?>