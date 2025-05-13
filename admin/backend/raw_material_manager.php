<?php

/**
 * Checks raw material availability for an order using the 'ingredients' column from the 'products' table.
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

    $required_raw_materials_map = []; // Keyed by raw_material_id
    $all_sufficient = true;
    $material_details_for_response = []; // Keyed by material name for frontend display

    if (empty($ordered_items)) {
        return ['all_sufficient' => true, 'materials' => [], 'message' => 'No items in the order to check for materials.'];
    }

    // 1. Get all unique product IDs from the order
    $product_ids_in_order = array_unique(array_map(function($item) { return $item['product_id'] ?? null; }, $ordered_items));
    $product_ids_in_order = array_filter($product_ids_in_order, 'is_numeric'); // Ensure product_ids are numeric

    if (empty($product_ids_in_order)) {
        return ['all_sufficient' => true, 'materials' => [], 'message' => 'No valid product IDs in the order.'];
    }

    // 2. Fetch product details (including ingredients JSON) for all products in the order
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
    $product_ingredients_map = []; // Map product_id to its parsed ingredients array
    while ($row = $result_products->fetch_assoc()) {
        if (!empty($row['ingredients'])) {
            $decoded_ingredients = json_decode($row['ingredients'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ingredients)) {
                $product_ingredients_map[$row['product_id']] = $decoded_ingredients;
            } else {
                error_log("RawMaterialManager: Failed to decode ingredients JSON for product_id {$row['product_id']}. JSON: " . $row['ingredients'] . ". Error: " . json_last_error_msg());
                // Optionally, treat this as an error preventing order processing
            }
        }
    }
    $stmt_products->close();

    // 3. Get all raw material names from the parsed ingredients to fetch their details from raw_materials table
    $all_ingredient_names = [];
    foreach ($product_ingredients_map as $product_id => $ingredients_for_product) {
        foreach ($ingredients_for_product as $ingredient_item) {
            if (isset($ingredient_item[0]) && is_string($ingredient_item[0])) {
                $all_ingredient_names[] = $ingredient_item[0];
            }
        }
    }
    $unique_ingredient_names = array_unique($all_ingredient_names);

    $raw_material_db_info = []; // Map material_name to its DB record (id, current_stock)
    if (!empty($unique_ingredient_names)) {
        $name_placeholders = implode(',', array_fill(0, count($unique_ingredient_names), '?'));
        $sql_raw_materials = "SELECT id, material_name, current_stock FROM raw_materials WHERE material_name IN ({$name_placeholders})";
        
        $stmt_raw_materials = $conn->prepare($sql_raw_materials);
        if (!$stmt_raw_materials) {
             error_log("RawMaterialManager: Prepare failed (fetch raw materials by name): " . $conn->error);
             return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error fetching raw material details.'];
        }
        $types_names = str_repeat('s', count($unique_ingredient_names));
        $stmt_raw_materials->bind_param($types_names, ...$unique_ingredient_names);

        if (!$stmt_raw_materials->execute()) {
            error_log("RawMaterialManager: Execute failed (fetch raw materials by name): " . $stmt_raw_materials->error);
            $stmt_raw_materials->close();
            return ['all_sufficient' => false, 'materials' => [], 'message' => 'DB error executing raw material details fetch.'];
        }
        $result_raw_materials = $stmt_raw_materials->get_result();
        while ($rm_row = $result_raw_materials->fetch_assoc()) {
            $raw_material_db_info[$rm_row['material_name']] = $rm_row;
        }
        $stmt_raw_materials->close();
    }
    
    // 4. Calculate total raw materials needed for the order
    foreach ($ordered_items as $order_item) {
        $product_id = $order_item['product_id'] ?? null;
        $order_quantity = intval($order_item['quantity'] ?? 0);

        if (!$product_id || $order_quantity <= 0 || !isset($product_ingredients_map[$product_id])) {
            continue; // Skip if no product_id, zero quantity, or no ingredients found for this product
        }

        $ingredients_for_this_product = $product_ingredients_map[$product_id];
        foreach ($ingredients_for_this_product as $ingredient_components) {
            if (count($ingredient_components) < 2 || !is_string($ingredient_components[0]) || !is_numeric($ingredient_components[1])) {
                error_log("RawMaterialManager: Malformed ingredient component for product_id {$product_id}: " . print_r($ingredient_components, true));
                continue; // Skip malformed ingredient
            }
            $raw_material_name = $ingredient_components[0];
            $qty_per_product_unit = floatval($ingredient_components[1]);
            
            if (!isset($raw_material_db_info[$raw_material_name])) {
                // This raw material name from products.ingredients JSON was not found in raw_materials table
                error_log("RawMaterialManager: Raw material '{$raw_material_name}' (from product_id {$product_id}) not found in raw_materials table. Skipping this ingredient.");
                $all_sufficient = false; // Mark as insufficient as we can't verify or deduct
                // Add to response so user knows which one is missing from DB
                 if (!isset($material_details_for_response[$raw_material_name])) {
                    $material_details_for_response[$raw_material_name] = [
                        'id' => null, // No ID from DB
                        'required' => 0, // Will accumulate below if other products need it and it's found
                        'available' => 0,
                        'sufficient' => false,
                        'shortfall' => 0,
                        'error' => "Raw material '{$raw_material_name}' not found in database."
                    ];
                }
                // Accumulate requirement even if not found, to show total need vs. 0 available
                $material_details_for_response[$raw_material_name]['required'] += ($qty_per_product_unit * $order_quantity);
                continue;
            }

            $rm_db_record = $raw_material_db_info[$raw_material_name];
            $rm_id = $rm_db_record['id'];
            $current_rm_stock = floatval($rm_db_record['current_stock']);
            $total_needed_for_this_ingredient_for_this_item = $qty_per_product_unit * $order_quantity;

            if (!isset($required_raw_materials_map[$rm_id])) {
                $required_raw_materials_map[$rm_id] = [
                    'name' => $raw_material_name,
                    'total_required' => 0,
                    'available' => $current_rm_stock 
                ];
            }
            $required_raw_materials_map[$rm_id]['total_required'] += $total_needed_for_this_ingredient_for_this_item;
        }
    }

    // 5. Check sufficiency and prepare details for response
    foreach ($required_raw_materials_map as $rm_id => $data) {
        $is_material_sufficient = ($data['available'] >= $data['total_required']);
        if (!$is_material_sufficient) {
            $all_sufficient = false;
        }
        // For frontend display, key by material name
        $material_details_for_response[$data['name']] = [
            'id' => $rm_id, 
            'required' => $data['total_required'],
            'available' => $data['available'],
            'sufficient' => $is_material_sufficient,
            'shortfall' => $is_material_sufficient ? 0 : ($data['total_required'] - $data['available'])
        ];
    }
    
    // Add any materials that had errors (like not found in DB) but were required
    foreach ($material_details_for_response as $name => $details) {
        if (isset($details['error']) && $details['required'] > 0) {
             $all_sufficient = false; // Ensure overall status reflects this error
        }
    }
    
    $message = $all_sufficient ? 'All raw materials are sufficient.' : 'One or more raw materials are insufficient or have issues.';
    if (!$all_sufficient) {
        // Construct a more detailed message
        $issues = [];
        foreach ($material_details_for_response as $name => $details) {
            if (isset($details['error'])) {
                $issues[] = $details['error'];
            } elseif (!$details['sufficient']) {
                $issues[] = "{$name} (Needs: {$details['required']}, Has: {$details['available']})";
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
 * This function relies on the output structure of the modified check_raw_materials_for_order.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success.
 * @throws Exception On critical database errors or if deduction leads to negative stock (safeguard).
 */
function deduct_raw_materials_for_order($conn, $orders_json, $po_number) {
    // Recalculate requirements to ensure we deduct the correct amounts based on the order items.
    // This also re-validates against current stock just before deduction.
    $material_requirements_check = check_raw_materials_for_order($conn, $orders_json);

    if (!$material_requirements_check['all_sufficient']) {
        $detailed_message = $material_requirements_check['message'] ?? 'Raw materials are insufficient or unavailable.';
        error_log("RawMaterialManager: CRITICAL - Attempted to deduct for PO {$po_number} when materials are insufficient. Details: {$detailed_message}");
        throw new Exception("Deduction aborted for PO {$po_number}: {$detailed_message}");
    }

    if (empty($material_requirements_check['materials'])) {
        error_log("RawMaterialManager: No raw materials identified to deduct for PO {$po_number}.");
        return true; // Nothing to deduct, considered a success.
    }
    
    $stmt_deduct = $conn->prepare("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
    if (!$stmt_deduct) {
        error_log("RawMaterialManager: Prepare statement failed for deducting stock: " . $conn->error);
        throw new Exception("Database error preparing for stock deduction: " . $conn->error);
    }

    foreach ($material_requirements_check['materials'] as $material_name => $data) {
        if (!isset($data['id']) || is_null($data['id'])) {
            // This material was problematic (e.g., not found in DB during check).
            // check_raw_materials_for_order should have made all_sufficient false if this was required.
            // If somehow it's here and required > 0, it's an issue.
            if ($data['required'] > 0) {
                 error_log("RawMaterialManager: Skipping deduction for '{$material_name}' (PO: {$po_number}) as its ID is missing or it had errors during check, but was still marked for deduction.");
                 // This indicates a logic flaw if all_sufficient was true.
            }
            continue;
        }

        if ($data['required'] > 0) { 
            $qty_to_deduct = $data['required'];
            $rm_id = $data['id'];

            // Bind parameters: quantity to deduct, raw material ID, quantity to deduct (for WHERE current_stock >= ?)
            $stmt_deduct->bind_param("did", $qty_to_deduct, $rm_id, $qty_to_deduct); 
            if (!$stmt_deduct->execute()) {
                $err_msg = $stmt_deduct->error;
                $stmt_deduct->close(); 
                error_log("RawMaterialManager: Failed to deduct {$qty_to_deduct} of {$material_name} (ID: {$rm_id}) for PO {$po_number}. Error: " . $err_msg);
                throw new Exception("Failed to deduct raw material: {$material_name} for PO {$po_number}. Error: " . $err_msg);
            }
            if ($stmt_deduct->affected_rows === 0) {
                $stmt_deduct->close();
                error_log("RawMaterialManager: Deduction of {$qty_to_deduct} for {$material_name} (ID: {$rm_id}) affected 0 rows for PO {$po_number}. Stock might have changed or ID issue. This indicates a potential race condition or data inconsistency.");
                // This means current_stock was NOT >= qty_to_deduct at the moment of execution.
                // check_raw_materials_for_order should have caught this.
                throw new Exception("Stock level for {$material_name} became insufficient during deduction for PO {$po_number}. Deduction failed.");
            }
            error_log("RawMaterialManager: Deducted {$qty_to_deduct} of {$material_name} (ID: {$rm_id}) for PO {$po_number}.");
        }
    }
    $stmt_deduct->close();
    return true;
}

/**
 * Returns (adds back) raw materials for an order.
 * This function relies on the output structure of the modified check_raw_materials_for_order.
 *
 * @param mysqli $conn Database connection object.
 * @param string $orders_json JSON string of ordered items from the order.
 * @param string $po_number Purchase Order number for logging.
 * @return bool True on success (even if some individual returns fail but are logged).
 * @throws Exception On critical database errors like prepare statement failure.
 */
function return_raw_materials_for_order($conn, $orders_json, $po_number) {
    // Calculate how much was *supposed* to be used based on the order's items.
    $material_requirements_check = check_raw_materials_for_order($conn, $orders_json);
    // We don't strictly need 'all_sufficient' to be true for a return, just what was calculated as 'required'.

    if (empty($material_requirements_check['materials'])) {
        error_log("RawMaterialManager: No raw materials calculated to return for PO {$po_number}.");
        return true; // Nothing to return
    }

    $stmt_return = $conn->prepare("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?");
    if (!$stmt_return) {
        error_log("RawMaterialManager: Prepare statement failed for returning stock: " . $conn->error);
        throw new Exception("Database error preparing for stock return: " . $conn->error);
    }

    $all_returns_succeeded_log = true;
    foreach ($material_requirements_check['materials'] as $material_name => $data) {
         if (!isset($data['id']) || is_null($data['id'])) {
            // If material ID was missing during check, we can't return it.
            if ($data['required'] > 0) { // If it was considered required but had no ID.
                 error_log("RawMaterialManager: Cannot return '{$material_name}' for PO {$po_number} as its ID is missing or it had errors during check.");
            }
            continue;
        }

        if ($data['required'] > 0) { // Only return if it was calculated as required
            $qty_to_return = $data['required'];
            $rm_id = $data['id'];

            $stmt_return->bind_param("di", $qty_to_return, $rm_id); 
            if (!$stmt_return->execute()) {
                error_log("RawMaterialManager: Failed to return {$qty_to_return} of {$material_name} (ID: {$rm_id}) for PO {$po_number}. Error: " . $stmt_return->error);
                $all_returns_succeeded_log = false; // Log failure but continue trying other materials
            } else {
                error_log("RawMaterialManager: Returned {$qty_to_return} of {$material_name} (ID: {$rm_id}) for PO {$po_number}.");
            }
        }
    }
    $stmt_return->close();
    
    if (!$all_returns_succeeded_log) {
        error_log("RawMaterialManager: One or more raw materials failed to return for PO {$po_number}. Check logs. Manual inventory adjustment may be needed.");
    }
    // For now, returning true means the process attempted all returns.
    // A more robust system might return false if any part fails, to signal issues to the calling script.
    return true; 
}

?>