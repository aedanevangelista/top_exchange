<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory'); // Or the appropriate role for this page

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php"); // Adjust if your login path is different
    exit();
}

$active_tab = 'company'; // Default active tab

// Handle Edit Product Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['formType']) && $_POST['formType'] == 'edit_product') {
    header('Content-Type: application/json'); // Ensure JSON response for AJAX

    $product_id = $_POST['product_id'];
    // Determine table based on product type if you have walkin_products as well
    // For now, assuming 'products' for company orders as per context
    $table = 'products'; 

    $category = $_POST['category'];
    $product_name = $_POST['product_name'];
    $item_description = $_POST['item_description'];
    $packaging = $_POST['packaging']; // This will be the combined string
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $additional_description = $_POST['additional_description'];

    // --- REFINED Expiration processing for Edit ---
    $expiration_duration_str = trim($_POST['expiration_duration'] ?? '');
    $expiration_unit = trim($_POST['expiration_unit'] ?? ''); 

    $expiration_string_to_save = NULL; 

    if ($expiration_duration_str !== '' && is_numeric($expiration_duration_str) && !empty($expiration_unit)) {
        $duration = intval($expiration_duration_str);
        if ($duration < 0) $duration = 0; 

        $temp_parts = [];

        if ($expiration_unit === 'Week') {
            if ($duration === 0) {
                $expiration_string_to_save = "0 Weeks";
            } else {
                $months = floor($duration / 4); // Assuming 4 weeks per month
                $remaining_weeks = $duration % 4;

                if ($months > 0) {
                    $temp_parts[] = $months . ($months == 1 ? " Month" : " Months");
                }
                if ($remaining_weeks > 0) {
                    $temp_parts[] = $remaining_weeks . ($remaining_weeks == 1 ? " Week" : " Weeks");
                }
                
                if (count($temp_parts) > 0) {
                    $expiration_string_to_save = implode(" ", $temp_parts);
                } else {
                    // This handles cases like duration = 4 (becomes "1 Month"), or duration = 0
                    if ($duration > 0 && $months > 0) { // e.g. 4 weeks -> 1 Month
                         $expiration_string_to_save = $months . ($months == 1 ? " Month" : " Months");
                    } else {
                         $expiration_string_to_save = "0 Weeks"; 
                    }
                }
            }
        } elseif ($expiration_unit === 'Month') {
            $expiration_string_to_save = $duration . ($duration == 1 ? " Month" : " Months");
        }
    }
    // --- END REFINED Expiration processing ---

    if ($price > 5000) {
        echo json_encode(['success' => false, 'message' => 'Price cannot exceed ₱5000.']);
        exit;
    }

    // Handle 'new' category/product name selection
    if ($category === 'new' && isset($_POST['new_category_field']) && !empty(trim($_POST['new_category_field']))) {
        $category = trim($_POST['new_category_field']);
    }

    if ($product_name === 'new' && isset($_POST['new_product_name_field']) && !empty(trim($_POST['new_product_name_field']))) {
        $product_name = trim($_POST['new_product_name_field']);
    }

    // Fetch old item description and image for potential folder renaming/cleanup
    $stmt_old_data = $conn->prepare("SELECT item_description, product_image FROM $table WHERE product_id = ?");
    $stmt_old_data->bind_param("i", $product_id);
    $stmt_old_data->execute();
    $result_old_data = $stmt_old_data->get_result();
    $old_item_data = $result_old_data->fetch_assoc();
    $old_item_description = $old_item_data['item_description'] ?? '';
    $old_product_image = $old_item_data['product_image'] ?? '';
    $stmt_old_data->close();

    $product_image = $old_product_image; // Default to old image

    // Image Upload Handling
    $upload_dir_base = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
    if (!file_exists($upload_dir_base)) {
        mkdir($upload_dir_base, 0777, true);
    }

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024; // 20MB
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            // Sanitize item_description for folder name
            $item_folder_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $item_description);
            $new_item_dir = $upload_dir_base . $item_folder_name . '/';

            // If item_description changed, and there was an old image, attempt to move/cleanup old folder
            if ($old_item_description !== $item_description && !empty($old_product_image)) {
                $old_item_folder_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $old_item_description);
                $old_item_path_full = $_SERVER['DOCUMENT_ROOT'] . $old_product_image; // Construct full old path
                $old_item_dir_path = dirname($old_item_path_full);


                if (file_exists($old_item_dir_path) && is_dir($old_item_dir_path)) {
                    // Create new directory if it doesn't exist
                    if (!file_exists($new_item_dir)) {
                        mkdir($new_item_dir, 0777, true);
                    }
                    // New file name based on new item description folder
                    $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'product_image.' . $file_extension; // Or use a more unique name
                    $new_target_file_path = $new_item_dir . $new_filename;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $new_target_file_path)) {
                        $product_image = '/uploads/products/' . $item_folder_name . '/' . $new_filename;
                        // Cleanup old image and directory if it's empty
                        if (file_exists($old_item_path_full)) {
                            unlink($old_item_path_full);
                        }
                        // Check if old directory is empty and remove it
                        if (is_readable($old_item_dir_path) && (count(scandir($old_item_dir_path)) == 2)) { // . and ..
                            rmdir($old_item_dir_path);
                        }
                    } else {
                         echo json_encode(['success' => false, 'message' => 'Failed to move new image to new item folder.']);
                         exit;
                    }
                } else { // Old directory doesn't exist, or item description didn't change for image path
                     if (!file_exists($new_item_dir)) {
                        mkdir($new_item_dir, 0777, true);
                    }
                    $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'product_image.' . $file_extension;
                    $target_file_path = $new_item_dir . $filename;
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file_path)) {
                        $product_image = '/uploads/products/' . $item_folder_name . '/' . $filename;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload new image.']);
                        exit;
                    }
                }
            } else { // Item description did not change, or no old image, just upload to current/new item folder
                if (!file_exists($new_item_dir)) {
                    mkdir($new_item_dir, 0777, true);
                }
                // Clear existing files in the directory if any to avoid multiple "product_image.ext"
                $existing_files = glob($new_item_dir . 'product_image.*');
                foreach($existing_files as $efile){
                    if(is_file($efile)){
                        unlink($efile);
                    }
                }

                $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $filename = 'product_image.' . $file_extension;
                $target_file_path = $new_item_dir . $filename;

                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file_path)) {
                    $product_image = '/uploads/products/' . $item_folder_name . '/' . $filename;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image. Check permissions.']);
                    exit;
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB. Allowed types: JPG, PNG.']);
            exit;
        }
    }
    // If item description changed but no new image uploaded, update the path of the old image if it exists
    elseif ($old_item_description !== $item_description && !empty($old_product_image)) {
        $old_item_folder_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $old_item_description);
        $new_item_folder_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $item_description);
        
        $old_image_path_full = $_SERVER['DOCUMENT_ROOT'] . $old_product_image;
        $old_image_filename = basename($old_product_image);

        $new_item_dir = $upload_dir_base . $new_item_folder_name . '/';
        $new_image_path_relative = '/uploads/products/' . $new_item_folder_name . '/' . $old_image_filename; // Keep old filename
        $new_image_path_full = $new_item_dir . $old_image_filename;

        if (file_exists($old_image_path_full)) {
            if (!file_exists($new_item_dir)) {
                mkdir($new_item_dir, 0777, true);
            }
            if (rename($old_image_path_full, $new_image_path_full)) {
                $product_image = $new_image_path_relative; // Update to new path
                // Cleanup old directory if empty
                $old_item_dir_check = dirname($old_image_path_full);
                if (is_readable($old_item_dir_check) && (count(scandir($old_item_dir_check)) == 2)) {
                     rmdir($old_item_dir_check);
                }
            } else {
                // Could not move the image, keep old path but this might be an issue
                error_log("Could not rename/move image from $old_image_path_full to $new_image_path_full");
            }
        }
    }


    // Prepare update statement
    $stmt = $conn->prepare("UPDATE $table SET category = ?, product_name = ?, item_description = ?, packaging = ?, price = ?, stock_quantity = ?, additional_description = ?, product_image = ?, expiration = ? WHERE product_id = ?");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . $conn->error]);
        exit;
    }

    // *** THIS IS THE CORRECTED LINE ***
    $stmt->bind_param("ssssdiss_s_i", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image, $expiration_string_to_save, $product_id);

    if ($stmt->execute()) {
        // If item_description changed and image was successfully processed,
        // you might want to update other products sharing the old item_description if they shared an image.
        // This logic can be complex and depends on your exact requirements for shared images.
        // For now, we focus on the current product.
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}


// Fetch categories for dropdowns
$categories_sql = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_result = $conn->query($categories_sql);

// Fetch product names for dropdowns
$product_names_sql = "SELECT DISTINCT product_name FROM products WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name";
$product_names_result = $conn->query($product_names_sql);

// Fetch products for the main table
$products_sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image, expiration FROM products ORDER BY category, product_name, item_description";
$products_data_result = $conn->query($products_sql);

// Fetch raw materials for ingredients modal (if you have that feature)
$raw_materials_list = []; // Placeholder
// Example:
// $raw_materials_sql = "SELECT material_id, name FROM raw_materials ORDER BY name";
// $raw_materials_result_q = $conn->query($raw_materials_sql);
// if ($raw_materials_result_q && $raw_materials_result_q->num_rows > 0) {
//     while ($row_rm = $raw_materials_result_q->fetch_assoc()) {
//         $raw_materials_list[] = $row_rm;
//     }
// }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finished Products Inventory</title>
    <link rel="stylesheet" href="/css/inventory.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* Basic Modal Styles (can be moved to a CSS file) */
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .overlay-content { background-color: white; padding: 20px; border-radius: 5px; width: 600px; max-width: 95vw; box-sizing: border-box; max-height: 90vh; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .save-btn, .cancel-btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .save-btn { background-color: #4CAF50; color: white; }
        .cancel-btn { background-color: #f44336; color: white; }
        .error-message { color: red; margin-bottom: 10px; font-size: 0.9em; }
        #current-image-container img { max-width: 100px; max-height: 100px; margin-bottom: 10px; object-fit: cover; border-radius: 4px; }
        .file-info { font-size: 0.9em; color: #666; font-style: italic; }
        .product-img { width: 50px; height: 50px; object-fit: cover; cursor: pointer; border-radius: 4px; transition: transform 0.2s; }
        .product-img:hover { transform: scale(1.1); }
        .no-image { width: 50px; height: 50px; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: #666; font-size: 12px; }
        .packaging-group, .expiration-group { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; }
        .packaging-item, .expiration-item { display: flex; flex-direction: column; flex: 1; }
        .packaging-item label, .expiration-item label { margin-bottom: 5px; font-size: 0.9em; }
        .packaging-item input[type="number"], .packaging-item select,
        .expiration-item input[type="number"], .expiration-item select { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        /* Image Modal */
        #imageDisplayModal { display: none; position: fixed; z-index: 9999; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
        .image-modal-content { margin: auto; display: block; max-width: 80%; max-height: 80%; }
        .image-modal-close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
        .image-modal-close:hover, .image-modal-close:focus { color: #bbb; text-decoration: none; }

    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="inventory-header">
            <h1>Finished Products</h1>
            <div class="search-filter-container">
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="Search products..." onkeyup="searchProducts()">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
                <div class="filter-section">
                    <label for="category-filter">Filter by Category:</label>
                    <select id="category-filter" onchange="filterByCategory()">
                        <option value="all">All</option>
                        <?php
                        if ($categories_result && $categories_result->num_rows > 0) {
                            mysqli_data_seek($categories_result, 0); // Reset pointer
                            while ($row = $categories_result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['category']) . "'>" . htmlspecialchars($row['category']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <button onclick="openAddProductForm()" class="add-product-btn">
                <i class="fas fa-plus-circle"></i> Add New Product
            </button>
        </div>

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Product Name</th>
                        <th>Product Variant</th>
                        <th>Packaging</th>
                        <th>Expiration</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Image</th>
                        <th>Additional Description</th>
                        <!-- <th>Ingredients</th> -->
                        <th>Adjust Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventory-table-body">
                     <?php
                    if ($products_data_result && $products_data_result->num_rows > 0) {
                        while ($row = $products_data_result->fetch_assoc()) {
                            $data_attributes = "data-category='" . htmlspecialchars($row['category'] ?? '') . "' " .
                                               "data-product-id='" . htmlspecialchars($row['product_id'] ?? '') . "' " .
                                               "data-product-name='" . htmlspecialchars($row['product_name'] ?? '') . "' " .
                                               "data-item-description='" . htmlspecialchars($row['item_description'] ?? '') . "' " .
                                               "data-packaging='" . htmlspecialchars($row['packaging'] ?? '') . "' " .
                                               "data-expiration='" . htmlspecialchars($row['expiration'] ?? '') . "' " .
                                               "data-price='" . htmlspecialchars($row['price'] ?? '') . "' " .
                                               "data-stock-quantity='" . htmlspecialchars($row['stock_quantity'] ?? '') . "' " .
                                               "data-additional-description='" . htmlspecialchars($row['additional_description'] ?? '') . "' " .
                                               "data-product-image='" . htmlspecialchars($row['product_image'] ?? '') . "'";

                            echo "<tr $data_attributes>";
                            echo "<td>" . htmlspecialchars($row['category'] ?? 'N/A') . "</td>";
                            echo "<td class='product-name'>" . htmlspecialchars($row['product_name'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['item_description'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['packaging'] ?? 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['expiration'] ?? 'N/A') . "</td>";
                            echo "<td>₱" . number_format(floatval($row['price'] ?? 0), 2) . "</td>";
                            echo "<td id='stock-{$row['product_id']}'>" . htmlspecialchars($row['stock_quantity'] ?? 0) . "</td>";
                            echo "<td class='product-image-cell'>";
                            if (!empty($row['product_image'])) {
                                echo "<img src='" . htmlspecialchars($row['product_image']) . "?t=" . time() . "' alt='Product Image' class='product-img' onclick='openImageModal(this.src)'>";
                            } else {
                                echo "<div class='no-image'>No image</div>";
                            }
                            echo "</td>";
                            echo "<td class='additional-desc' title='" . htmlspecialchars($row['additional_description'] ?? '') . "'>" . htmlspecialchars($row['additional_description'] ?? 'N/A') . "</td>";
                            // echo "<td><button class='view-ingredients-btn' onclick='viewIngredients({$row['product_id']})'><i class='fas fa-list'></i> View</button></td>";
                            echo "<td class='adjust-stock'>
                                    <button class='add-btn' onclick='updateStock({$row['product_id']}, \"add\", \"company\")'>+</button>
                                    <input type='number' id='adjust-{$row['product_id']}' min='1' value='1' class='stock-adjust-input'>
                                    <button class='remove-btn' onclick='updateStock({$row['product_id']}, \"remove\", \"company\")'>-</button>
                                  </td>";
                            echo "<td>
                                    <button class='edit-btn' onclick='editProduct(this)'><i class='fas fa-edit'></i> Edit</button>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Add Product Modal -->
        <div id="addProductModal" class="overlay">
            <div class="overlay-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <div id="addProductError" class="error-message"></div>
                <form id="add-product-form" method="POST" enctype="multipart/form-data">
                    <label for="category">Category:<span class="required-asterisk">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        if ($categories_result && $categories_result->num_rows > 0) {
                            mysqli_data_seek($categories_result, 0);
                            while ($row = $categories_result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['category']) . "'>" . htmlspecialchars($row['category']) . "</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Category</option>
                    </select>
                    <div id="new-category-container" style="display: none;">
                        <label for="new_category_field">New Category Name:<span class="required-asterisk">*</span></label>
                        <input type="text" id="new_category_field" name="new_category_field" placeholder="Enter new category name">
                    </div>

                    <label for="product_name">Product Name:<span class="required-asterisk">*</span></label>
                    <select id="product_name" name="product_name" required>
                        <option value="">Select Product Name</option>
                         <?php
                        if ($product_names_result && $product_names_result->num_rows > 0) {
                           mysqli_data_seek($product_names_result, 0);
                            while ($row = $product_names_result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['product_name']) . "'>" . htmlspecialchars($row['product_name']) . "</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Product Name</option>
                    </select>
                    <div id="new-product-name-container" style="display: none;">
                        <label for="new_product_name_field">New Product Name:<span class="required-asterisk">*</span></label>
                        <input type="text" id="new_product_name_field" name="new_product_name_field" placeholder="Enter new product name">
                    </div>

                    <label for="item_description">Product Variant (Item Description):<span class="required-asterisk">*</span></label>
                    <input type="text" id="item_description" name="item_description" required placeholder="e.g., Asado Siopao (A Large)">
                    
                    <div class="packaging-group">
                        <div class="packaging-item">
                            <label for="packaging_quantity">Quantity:<span class="required-asterisk">*</span></label>
                            <input type="number" id="packaging_quantity" min="1" required placeholder="e.g., 12">
                        </div>
                        <div class="packaging-item">
                            <label for="packaging_unit">Unit:<span class="required-asterisk">*</span></label>
                            <select id="packaging_unit" required>
                                <option value="">Select Unit</option><option value="COUNT">Piece(s)</option><option value="G">Grams (g)</option><option value="KG">Kilograms (kg)</option>
                            </select>
                        </div>
                        <div class="packaging-item">
                            <label for="packaging_container">Container:<span class="required-asterisk">*</span></label>
                            <select id="packaging_container" required>
                                <option value="">Select Container</option><option value="Pack">Pack</option><option value="Btl">Bottle</option><option value="Cntr">Container</option><option value="None">None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="expiration-group">
                        <div class="expiration-item">
                            <label for="add_expiration_duration">Expiration Duration:</label>
                            <input type="number" id="add_expiration_duration" name="expiration_duration" min="0" placeholder="e.g., 2">
                        </div>
                        <div class="expiration-item">
                            <label for="add_expiration_unit">Expiration Unit:</label>
                            <select id="add_expiration_unit" name="expiration_unit">
                                <option value="">-- Select Unit --</option><option value="Week">Week(s)</option><option value="Month">Month(s)</option>
                            </select>
                        </div>
                    </div>

                    <label for="price">Price (₱):<span class="required-asterisk">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0" max="5000" required placeholder="0.00">

                    <label for="stock_quantity">Initial Stock Quantity:</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" placeholder="0" value="0">


                    <label for="additional_description">Additional Description:</label>
                    <textarea id="additional_description" name="additional_description" placeholder="Add more details about the product"></textarea>

                    <label for="product_image">Product Image: <span class="file-info">(Max: 20MB, JPG/PNG only)</span></label>
                    <input type="file" id="product_image" name="product_image" accept="image/jpeg, image/png">

                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeAddProductModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div id="editProductModal" class="overlay">
            <div class="overlay-content">
                <h2><i class="fas fa-edit"></i> Edit Product</h2>
                <div id="editProductError" class="error-message"></div>
                <form id="edit-product-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="formType" value="edit_product">
                    <input type="hidden" id="edit_product_id" name="product_id">

                    <div class="form-grid">
                        <div>
                            <label for="edit_category">Category:<span class="required-asterisk">*</span></label>
                            <select id="edit_category" name="category" required>
                                <option value="">Select Category</option>
                                <?php
                                if ($categories_result && $categories_result->num_rows > 0) {
                                    mysqli_data_seek($categories_result, 0);
                                    while ($row = $categories_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['category']) . "'>" . htmlspecialchars($row['category']) . "</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Category</option>
                            </select>
                            <div id="edit-new-category-container" style="display: none;">
                                <label for="edit_new_category_field">New Category Name:<span class="required-asterisk">*</span></label>
                                <input type="text" id="edit_new_category_field" name="new_category_field" placeholder="Enter new category name">
                            </div>

                            <label for="edit_product_name">Product Name:<span class="required-asterisk">*</span></label>
                            <select id="edit_product_name" name="product_name" required>
                                <option value="">Select Product Name</option>
                                <?php
                                if ($product_names_result && $product_names_result->num_rows > 0) {
                                    mysqli_data_seek($product_names_result, 0);
                                    while ($row = $product_names_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['product_name']) . "'>" . htmlspecialchars($row['product_name']) . "</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Product Name</option>
                            </select>
                            <div id="edit-new-product-name-container" style="display: none;">
                                <label for="edit_new_product_name_field">New Product Name:<span class="required-asterisk">*</span></label>
                                <input type="text" id="edit_new_product_name_field" name="new_product_name_field" placeholder="Enter new product name">
                            </div>
                        </div>
                        <div>
                            <label for="edit_item_description">Product Variant (Item Description):<span class="required-asterisk">*</span></label>
                            <input type="text" id="edit_item_description" name="item_description" required placeholder="Enter full product description">
                        
                            <label for="edit_price">Price (₱):<span class="required-asterisk">*</span></label>
                            <input type="number" id="edit_price" name="price" step="0.01" min="0" max="5000" required placeholder="0.00">

                            <label for="edit_stock_quantity">Stock Quantity:<span class="required-asterisk">*</span></label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" required placeholder="0">
                        </div>
                    </div>
                     
                    <div class="packaging-group">
                        <div class="packaging-item">
                            <label for="edit_packaging_quantity">Quantity:<span class="required-asterisk">*</span></label>
                            <input type="number" id="edit_packaging_quantity" min="1" required placeholder="e.g., 12">
                        </div>
                        <div class="packaging-item">
                            <label for="edit_packaging_unit">Unit:<span class="required-asterisk">*</span></label>
                            <select id="edit_packaging_unit" required>
                                <option value="">Select Unit</option><option value="COUNT">Piece(s)</option><option value="G">Grams (g)</option><option value="KG">Kilograms (kg)</option>
                            </select>
                        </div>
                        <div class="packaging-item">
                            <label for="edit_packaging_container">Container:<span class="required-asterisk">*</span></label>
                            <select id="edit_packaging_container" required>
                                <option value="">Select Container</option><option value="Pack">Pack</option><option value="Btl">Bottle</option><option value="Cntr">Container</option><option value="None">None</option>
                            </select>
                        </div>
                    </div>

                    <div class="expiration-group">
                        <div class="expiration-item">
                            <label for="edit_expiration_duration">Expiration Duration:</label>
                            <input type="number" id="edit_expiration_duration" name="expiration_duration" min="0" placeholder="e.g., 2">
                        </div>
                        <div class="expiration-item">
                            <label for="edit_expiration_unit">Expiration Unit:</label>
                            <select id="edit_expiration_unit" name="expiration_unit">
                                <option value="">-- Select Unit --</option><option value="Week">Week(s)</option><option value="Month">Month(s)</option>
                            </select>
                        </div>
                    </div>

                     <label for="edit_additional_description">Additional Description:</label>
                     <textarea id="edit_additional_description" name="additional_description" placeholder="Add more details about the product"></textarea>

                    <div id="current-image-container"></div>
                    <label for="edit_product_image">Product Image: <span class="file-info">(Max: 20MB, JPG/PNG only, new image replaces old)</span></label>
                    <input type="file" id="edit_product_image" name="product_image" accept="image/jpeg, image/png">

                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeEditProductModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Image Display Modal -->
        <div id="imageDisplayModal" class="overlay">
            <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
            <img class="image-modal-content" id="modalImageDisplayed">
        </div>


    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // const rawMaterials = <?php //echo json_encode($raw_materials_list); ?>;
        const activeTab = "company"; // Assuming default or based on your logic

        toastr.options = { "positionClass": "toast-bottom-right", "timeOut": 3000, "closeButton": true, "progressBar": true };

        function limitNumberInput(inputElement, maxValue, warningMessage) {
            if (!inputElement) return;
            inputElement.addEventListener('input', function() {
                let value = parseFloat(this.value);
                if (!isNaN(value) && value > maxValue) {
                    this.value = maxValue;
                    if (typeof toastr !== 'undefined') {
                         toastr.warning(warningMessage || `Maximum value allowed is ${maxValue}.`, { timeOut: 2000, preventDuplicates: true });
                    }
                } else if (!isNaN(value) && value < 0) {
                     this.value = 0;
                 }
            });
        }
        
        function openImageModal(src) {
            document.getElementById('modalImageDisplayed').src = src;
            document.getElementById('imageDisplayModal').style.display = 'block';
        }
        function closeImageModal() {
            document.getElementById('imageDisplayModal').style.display = 'none';
        }


        function searchProducts() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#inventory-table-body tr');
            rows.forEach(row => {
                const itemDescription = (row.dataset.itemDescription || '').toLowerCase();
                const productName = (row.dataset.productName || '').toLowerCase();
                const category = (row.dataset.category || '').toLowerCase();
                const packaging = (row.dataset.packaging || '').toLowerCase();
                // const additionalDescription = (row.dataset.additionalDescription || '').toLowerCase(); // If you add this data attribute
                const additionalDescriptionCell = row.querySelector('.additional-desc');
                const additionalDescription = additionalDescriptionCell ? (additionalDescriptionCell.textContent || '').toLowerCase() : '';


                if (itemDescription.includes(searchValue) || productName.includes(searchValue) || category.includes(searchValue) || packaging.includes(searchValue) || additionalDescription.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function setupCategoryDropdown(selectId, newContainerId, newFieldId) {
            const selectElement = document.getElementById(selectId);
            const newContainer = document.getElementById(newContainerId);
            const newField = document.getElementById(newFieldId);
            if (selectElement) {
                selectElement.addEventListener('change', function() {
                    const isNew = this.value === 'new';
                    newContainer.style.display = isNew ? 'block' : 'none';
                    newField.required = isNew;
                    if (isNew) newField.value = ''; 
                });
            }
        }
        
        function setupProductNameDropdown(selectId, newContainerId, newFieldId, itemDescId) {
            const selectElement = document.getElementById(selectId);
            const newContainer = document.getElementById(newContainerId);
            const newField = document.getElementById(newFieldId);
            const itemDescInput = document.getElementById(itemDescId);

            if (selectElement) {
                selectElement.addEventListener('change', function() {
                    const isNew = this.value === 'new';
                    newContainer.style.display = isNew ? 'block' : 'none';
                    newField.required = isNew;
                    if (isNew) newField.value = '';

                    if (!isNew && this.value !== '' && itemDescInput) {
                        // If item description is empty or doesn't already start with the product name, prefill it.
                        if (!itemDescInput.value.trim() || !itemDescInput.value.trim().toLowerCase().startsWith(this.value.toLowerCase())) {
                             itemDescInput.value = this.value + ' '; // Add a space for easier typing
                        }
                    }
                });
            }
        }


        document.addEventListener('DOMContentLoaded', function() {
            setupCategoryDropdown('category', 'new-category-container', 'new_category_field');
            setupProductNameDropdown('product_name', 'new-product-name-container', 'new_product_name_field', 'item_description');
            
            setupCategoryDropdown('edit_category', 'edit-new-category-container', 'edit_new_category_field');
            setupProductNameDropdown('edit_product_name', 'edit-new-product-name-container', 'edit_new_product_name_field', 'edit_item_description');

            limitNumberInput(document.getElementById('price'), 5000, 'Price cannot exceed ₱5000.');
            limitNumberInput(document.getElementById('edit_price'), 5000, 'Price cannot exceed ₱5000.');
        });

        function getPackagingString(qtyId, unitId, containerId) {
            const qtyEl = document.getElementById(qtyId);
            const unitEl = document.getElementById(unitId);
            const containerEl = document.getElementById(containerId);

            if (!qtyEl || !unitEl || !containerEl) return null;

            const qty = qtyEl.value;
            const unitType = unitEl.value;
            const container = containerEl.value;

            if (!qty || !unitType || !container) {
                 return null; 
            }

            let unitString;
            if (unitType === 'COUNT') {
                unitString = parseInt(qty) === 1 ? 'pc' : 'pcs';
            } else { 
                unitString = unitType.toLowerCase(); 
            }

            let packagingString = qty.trim() + unitString;
            if (container.trim().toLowerCase() !== 'none' && container.trim() !== '') {
                packagingString += '/' + container.trim().toLowerCase();
            }
            return packagingString;
        }


        document.getElementById('add-product-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('addProductError');
            errorDiv.textContent = ''; 

            // Basic Validations
            const categorySelect = document.getElementById('category');
            const newCategoryInput = document.getElementById('new_category_field');
            if (categorySelect.value === 'new' && !newCategoryInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new category name.'; newCategoryInput.focus(); return;
            }
            const productNameSelect = document.getElementById('product_name');
            const newProductNameInput = document.getElementById('new_product_name_field');
            if (productNameSelect.value === 'new' && !newProductNameInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new product name.'; newProductNameInput.focus(); return;
            }
            const itemDescriptionInput = document.getElementById('item_description');
            if (!itemDescriptionInput.value.trim()) {
                errorDiv.textContent = 'Product Variant (Item Description) is required.'; itemDescriptionInput.focus(); return;
            }
            const packagingString = getPackagingString('packaging_quantity', 'packaging_unit', 'packaging_container');
             if (!packagingString) {
                 errorDiv.textContent = 'Please fill in all packaging details (Quantity, Unit, Container).';
                 if (!document.getElementById('packaging_quantity').value) document.getElementById('packaging_quantity').focus();
                 else if (!document.getElementById('packaging_unit').value) document.getElementById('packaging_unit').focus();
                 else document.getElementById('packaging_container').focus();
                 return;
            }
            const priceInput = document.getElementById('price');
            if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
                errorDiv.textContent = 'Price must be a positive number.'; priceInput.focus(); return;
            }
            // End Basic Validations


            // --- REFINED Expiration string construction for Add form ---
            const addExpDurationInput = document.getElementById('add_expiration_duration');
            const addExpUnitInput = document.getElementById('add_expiration_unit');
            let expirationString = null; 

            if (addExpDurationInput && addExpUnitInput) {
                const addExpDurationValue = addExpDurationInput.value.trim();
                const addExpUnitValue = addExpUnitInput.value.trim(); 
                
                if (addExpDurationValue !== '' && !isNaN(addExpDurationValue) && addExpUnitValue !== '') {
                    let duration = parseInt(addExpDurationValue);
                    if (duration < 0) duration = 0; 

                    let temp_parts = [];
                    if (addExpUnitValue === 'Week') {
                        if (duration === 0) { expirationString = "0 Weeks"; }
                        else {
                            const months = Math.floor(duration / 4); const remaining_weeks = duration % 4;
                            if (months > 0) temp_parts.push(months + (months === 1 ? " Month" : " Months"));
                            if (remaining_weeks > 0) temp_parts.push(remaining_weeks + (remaining_weeks === 1 ? " Week" : " Weeks"));
                            if (temp_parts.length > 0) expirationString = temp_parts.join(" ");
                            else if (months > 0) expirationString = months + (months === 1 ? " Month" : " Months"); // Only months if weeks is 0
                            else expirationString = "0 Weeks";
                        }
                    } else if (addExpUnitValue === 'Month') {
                        expirationString = duration + (duration === 1 ? " Month" : " Months");
                    }
                }
            }
            // --- END REFINED Expiration ---


            const formData = new FormData(this); 
            // Set category and product name correctly if 'new' was selected
            if (categorySelect.value === 'new') formData.set('category', newCategoryInput.value.trim());
            else formData.set('category', categorySelect.value);

            if (productNameSelect.value === 'new') formData.set('product_name', newProductNameInput.value.trim());
            else formData.set('product_name', productNameSelect.value);
            
            formData.set('packaging', packagingString); // Set the combined packaging string

            if (expirationString) formData.set('expiration', expirationString);
            else formData.delete('expiration'); // Remove if null to avoid sending empty string if not intended

            // Remove individual packaging and expiration fields from FormData as they are combined
            formData.delete('packaging_quantity'); formData.delete('packaging_unit'); formData.delete('packaging_container');
            formData.delete('expiration_duration'); formData.delete('expiration_unit');   
            
            // Ensure stock_quantity is set, default to 0 if not provided or empty
            const stockQuantityInput = document.getElementById('stock_quantity');
            if (!stockQuantityInput.value.trim()) {
                formData.set('stock_quantity', 0);
            }


            fetch("../../backend/add_product.php", { method: "POST", body: formData })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (err) { console.error("Invalid JSON from add_product.php:", text, err); throw new Error("Server returned non-JSON response. Check PHP logs."); } }))
            .then(data => {
                if (data.success) {
                    toastr.success(data.message || 'Product added successfully!');
                    closeAddProductModal();
                    // Consider a more targeted update instead of full reload if possible
                    setTimeout(() => window.location.reload(), 1500); 
                } else {
                    errorDiv.textContent = data.message || 'An unknown error occurred';
                    toastr.error(data.message || 'Failed to add product.');
                }
            })
            .catch(error => {
                toastr.error(error.message || 'An error occurred while adding the product.');
                errorDiv.textContent = error.message;
            });
        });

        document.getElementById('edit-product-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('editProductError');
            errorDiv.textContent = ''; 

            // Basic Validations (similar to add form)
            const categorySelect = document.getElementById('edit_category');
            const newCategoryInput = document.getElementById('edit_new_category_field');
            if (categorySelect.value === 'new' && !newCategoryInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new category name.'; newCategoryInput.focus(); return;
            }
            const productNameSelect = document.getElementById('edit_product_name');
            const newProductNameInput = document.getElementById('edit_new_product_name_field');
            if (productNameSelect.value === 'new' && !newProductNameInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new product name.'; newProductNameInput.focus(); return;
            }
            const itemDescriptionInput = document.getElementById('edit_item_description');
            if (!itemDescriptionInput.value.trim()) {
                errorDiv.textContent = 'Product Variant (Item Description) is required.'; itemDescriptionInput.focus(); return;
            }

            const packagingString = getPackagingString('edit_packaging_quantity', 'edit_packaging_unit', 'edit_packaging_container');
             if (!packagingString) {
                 errorDiv.textContent = 'Please fill in all packaging details (Quantity, Unit, Container).';
                 // Focus logic for packaging...
                 return;
            }
            const priceInput = document.getElementById('edit_price');
            if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
                errorDiv.textContent = 'Price must be a positive number.'; priceInput.focus(); return;
            }
            // End Basic Validations


            const formData = new FormData(this); // 'this' is the form
            // Set category and product name correctly if 'new' was selected
            if (categorySelect.value === 'new') formData.set('category', newCategoryInput.value.trim());
            else formData.set('category', categorySelect.value);

            if (productNameSelect.value === 'new') formData.set('product_name', newProductNameInput.value.trim());
            else formData.set('product_name', productNameSelect.value);

            formData.set('packaging', packagingString); // Set the combined packaging string
            
            // Remove individual packaging fields from FormData as they are combined
            formData.delete('edit_packaging_quantity'); formData.delete('edit_packaging_unit'); formData.delete('edit_packaging_container');
            // Expiration duration and unit are already named correctly for POST in the PHP script ('expiration_duration', 'expiration_unit')

            fetch(window.location.href, { method: "POST", body: formData }) // POST to the same page (inventory.php)
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (err) { console.error("Invalid JSON from edit_product (inventory.php):", text, err); throw new Error("Server returned non-JSON response. Check PHP logs."); } }))
            .then(data => {
                if (data.success) {
                    toastr.success(data.message || 'Product updated successfully!');
                    closeEditProductModal();
                     setTimeout(() => window.location.reload(), 1500);
                } else {
                    errorDiv.textContent = data.message || 'An unknown error occurred';
                    toastr.error(data.message || 'Failed to update product.');
                }
            })
            .catch(error => {
                toastr.error(error.message || 'An error occurred while updating the product.');
                errorDiv.textContent = error.message;
            });
        });

        function openAddProductForm() {
            document.getElementById('addProductModal').style.display = 'flex';
            const form = document.getElementById('add-product-form');
            if (form) form.reset();
            document.getElementById('addProductError').textContent = '';
            // Reset 'new' field visibility
            document.getElementById('new-category-container').style.display = 'none';
            document.getElementById('new_category_field').required = false; 
            document.getElementById('new-product-name-container').style.display = 'none';
            document.getElementById('new_product_name_field').required = false; 
            document.getElementById('add_expiration_duration').value = '';
            document.getElementById('add_expiration_unit').value = '';
        }

        function closeAddProductModal() { document.getElementById('addProductModal').style.display = 'none'; }
        function closeEditProductModal() { document.getElementById('editProductModal').style.display = 'none'; }
        // function closeIngredientsModal() { document.getElementById('ingredientsModal').style.display = 'none'; }

        function editProduct(buttonElement) { 
            const tr = buttonElement.closest('tr');
            if (!tr) {
                toastr.error("Could not find product data for editing.");
                return;
            }

            document.getElementById('edit_product_id').value = tr.dataset.productId;
            
            const categorySelect = document.getElementById('edit_category');
            const newCategoryContainer = document.getElementById('edit-new-category-container');
            const newCategoryInput = document.getElementById('edit_new_category_field');
            categorySelect.value = tr.dataset.category;
            if (categorySelect.value !== tr.dataset.category) { // If category not in list, select 'new'
                 categorySelect.value = 'new';
                 newCategoryContainer.style.display = 'block';
                 newCategoryInput.value = tr.dataset.category;
                 newCategoryInput.required = true;
            } else {
                 newCategoryContainer.style.display = 'none';
                 newCategoryInput.required = false;
            }

            const productNameSelect = document.getElementById('edit_product_name');
            const newProductNameContainer = document.getElementById('edit-new-product-name-container');
            const newProductNameInput = document.getElementById('edit_new_product_name_field');
            productNameSelect.value = tr.dataset.productName;
             if (productNameSelect.value !== tr.dataset.productName) { // If name not in list
                 productNameSelect.value = 'new';
                 newProductNameContainer.style.display = 'block';
                 newProductNameInput.value = tr.dataset.productName;
                 newProductNameInput.required = true;
             } else {
                 newProductNameContainer.style.display = 'none';
                 newProductNameInput.required = false;
             }

            document.getElementById('edit_item_description').value = tr.dataset.itemDescription || '';
            
            // Parse packaging string: "12pcs/pack" or "500g/btl" or "1kg/cntr" or "10pcs"
            const packagingStr = tr.dataset.packaging || '';
            let qtyVal = '', unitTypeVal = '', containerVal = 'None';
            const packagingParts = packagingStr.split('/');
            const qtyUnitPart = packagingParts[0];

            const qtyMatch = qtyUnitPart.match(/^(\d+)/);
            if (qtyMatch) qtyVal = qtyMatch[1];
            
            const unitMatch = qtyUnitPart.match(/([a-zA-Z]+)$/);
            if (unitMatch) {
                const parsedUnit = unitMatch[1].toLowerCase();
                if (parsedUnit === 'pc' || parsedUnit === 'pcs') unitTypeVal = 'COUNT';
                else if (parsedUnit === 'g') unitTypeVal = 'G';
                else if (parsedUnit === 'kg') unitTypeVal = 'KG';
            }

            if (packagingParts.length > 1) {
                const parsedContainer = packagingParts[1].toLowerCase();
                if (parsedContainer === 'pack') containerVal = 'Pack';
                else if (parsedContainer === 'btl') containerVal = 'Btl';
                else if (parsedContainer === 'cntr') containerVal = 'Cntr';
                // 'None' is the default if not pack, btl, or cntr
            }
            
            document.getElementById('edit_packaging_quantity').value = qtyVal;
            document.getElementById('edit_packaging_unit').value = unitTypeVal;
            document.getElementById('edit_packaging_container').value = containerVal;

            document.getElementById('edit_price').value = parseFloat(tr.dataset.price || 0);
            document.getElementById('edit_stock_quantity').value = parseInt(tr.dataset.stockQuantity || 0);
            document.getElementById('edit_additional_description').value = tr.dataset.additionalDescription || '';

            // --- REFINED Parsing expiration for Edit form ---
            const productExpiration = tr.dataset.expiration || ''; 
            let form_exp_duration_val = '';
            let form_exp_unit_val = ''; 

            if (productExpiration) {
                let totalWeeks = 0; let monthsFromDb = 0; let weeksFromDb = 0;
                let hasMonthsInDb = false; let hasWeeksInDb = false;

                const monthMatch = productExpiration.match(/(\d+)\s*Month(s)?/i);
                const weekMatch = productExpiration.match(/(\d+)\s*Week(s)?/i);

                if (monthMatch) { monthsFromDb = parseInt(monthMatch[1]); hasMonthsInDb = true; }
                if (weekMatch) { weeksFromDb = parseInt(weekMatch[1]); hasWeeksInDb = true; }

                if (hasMonthsInDb && !hasWeeksInDb) { // Only "X Months"
                    form_exp_duration_val = monthsFromDb; form_exp_unit_val = 'Month';
                } else if (hasWeeksInDb) { // "X Weeks" or "Y Months Z Weeks"
                    totalWeeks = (monthsFromDb * 4) + weeksFromDb;
                    form_exp_duration_val = totalWeeks; form_exp_unit_val = 'Week';
                } else if (productExpiration.toLowerCase() === "0 weeks") {
                    form_exp_duration_val = 0; form_exp_unit_val = 'Week';
                } else if (productExpiration.toLowerCase() === "0 months") {
                     form_exp_duration_val = 0; form_exp_unit_val = 'Month';
                }
            }
            document.getElementById('edit_expiration_duration').value = form_exp_duration_val;
            document.getElementById('edit_expiration_unit').value = form_exp_unit_val;
            // --- END REFINED Parsing ---

            document.getElementById('current-image-container').innerHTML = '';
            if (tr.dataset.productImage) {
                document.getElementById('current-image-container').innerHTML = `<p>Current Image:</p><img src="${tr.dataset.productImage}?t=${new Date().getTime()}" alt="Current product image">`;
            }
            document.getElementById('edit_product_image').value = ''; // Clear file input
            document.getElementById('editProductModal').style.display = 'flex';
            document.getElementById('editProductError').textContent = '';
        }


        function updateStock(productId, action, productType = "company") { // productType default
            const amountInput = document.getElementById(`adjust-${productId}`);
            const amount = parseInt(amountInput.value);
            if (isNaN(amount) || amount <= 0) {
                 toastr.error("Please enter a valid positive amount to adjust.");
                 amountInput.focus(); return;
            }
            fetch("../pages/api/update_stock.php", { // Adjust path if needed
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ product_id: productId, action: action, amount: amount, product_type: productType })
            })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON from update_stock:", text, e); throw new Error("Server returned invalid response for stock update."); } }))
            .then(data => {
                if (data.success) {
                     toastr.success(data.message || "Stock updated successfully!");
                     const stockCell = document.getElementById(`stock-${productId}`);
                     if (stockCell) stockCell.textContent = data.new_stock;
                     amountInput.value = 1; 
                 } else { toastr.error(data.message || "Error updating stock."); }
            })
            .catch(error => {
                toastr.error(error.message || "Failed to update stock due to a network or server error.");
            });
        }

        function filterByCategory() {
            const filterValue = document.getElementById('category-filter').value;
            const rows = document.querySelectorAll('#inventory-table-body tr');
            rows.forEach(row => {
                if (filterValue === 'all' || row.dataset.category === filterValue) {
                    row.style.display = '';
                } else { row.style.display = 'none'; }
            });
        }


        // Close modals when clicking outside of overlay-content
        window.addEventListener('click', function(e) {
            const overlays = document.querySelectorAll('.overlay');
            overlays.forEach(overlay => {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>