<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php");
    exit();
}

$active_tab = 'company'; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['formType']) && $_POST['formType'] == 'edit_product') {
    header('Content-Type: application/json');

    $product_id = $_POST['product_id'];
    $table = 'products'; 

    $category = $_POST['category'];
    $product_name = $_POST['product_name'];
    $item_description = $_POST['item_description'];
    $packaging = $_POST['packaging'];
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $additional_description = $_POST['additional_description'];

    if ($price > 5000) {
        echo json_encode(['success' => false, 'message' => 'Price cannot exceed ₱5000.']);
        exit;
    }

    if ($category === 'new' && isset($_POST['new_category_field']) && !empty($_POST['new_category_field'])) {
        $category = $_POST['new_category_field'];
    }

    if ($product_name === 'new' && isset($_POST['new_product_name_field']) && !empty($_POST['new_product_name_field'])) {
        $product_name = $_POST['new_product_name_field'];
    }

    $stmt = $conn->prepare("SELECT item_description, product_image FROM $table WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_item_data = $result->fetch_assoc();
    $old_item_description = $old_item_data['item_description'] ?? '';
    $old_product_image = $old_item_data['product_image'] ?? '';
    $stmt->close();

    $product_image = $old_product_image;

    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024;
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
            $item_dir = $upload_dir . $item_folder . '/';

            if (!file_exists($item_dir)) {
                mkdir($item_dir, 0777, true);
            }

            if ($old_item_description != $item_description && !empty($old_product_image)) {
                $old_item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $old_item_description);
                $old_item_dir = $upload_dir . $old_item_folder . '/';
                if (file_exists($old_item_dir)) {
                    $old_files = array_diff(scandir($old_item_dir), array('.', '..'));
                    foreach ($old_files as $file) {
                        if (file_exists($old_item_dir . $file)) {
                            unlink($old_item_dir . $file);
                        }
                    }
                    if (count(array_diff(scandir($old_item_dir), array('.', '..'))) == 0) {
                        rmdir($old_item_dir);
                    }
                }
            }

            if (file_exists($item_dir)) {
                $existing_files = array_diff(scandir($item_dir), array('.', '..'));
                foreach ($existing_files as $file) {
                    if (file_exists($item_dir . $file)) {
                        unlink($item_dir . $file);
                    }
                }
            }

            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_image.' . $file_extension;
            $product_image_path = '/uploads/products/' . $item_folder . '/' . $filename;
            $target_file_path = $item_dir . $filename;

            usleep(100000); 

            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file_path)) {
                $product_image = $product_image_path;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image. Please try again.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE $table SET category = ?, product_name = ?, item_description = ?, packaging = ?, price = ?, stock_quantity = ?, additional_description = ?, product_image = ? WHERE product_id = ?");
    $stmt->bind_param("ssssdissi", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image, $product_id);

    if ($stmt->execute()) {
        if ($old_item_description != $item_description && !empty($product_image)) {
            $stmt_update_image = $conn->prepare("UPDATE $table SET product_image = ? WHERE item_description = ? AND product_id != ?");
            $stmt_update_image->bind_param("ssi", $product_image, $item_description, $product_id);
            $stmt_update_image->execute();
            $stmt_update_image->close();
        }
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

$raw_materials_sql = "SELECT material_id, name FROM raw_materials ORDER BY name";
$raw_materials_result = $conn->query($raw_materials_sql);
$raw_materials_list = [];
if ($raw_materials_result && $raw_materials_result->num_rows > 0) {
    while ($row = $raw_materials_result->fetch_assoc()) {
        $raw_materials_list[] = $row;
    }
}

$categories_sql = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_result = $conn->query($categories_sql);

$product_names_sql = "SELECT DISTINCT product_name FROM products WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name";
$product_names_result = $conn->query($product_names_sql);

$products_sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image FROM products ORDER BY category, product_name, item_description";
$products_data_result = $conn->query($products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finished Products</title>
    <link rel="stylesheet" href="/css/inventory.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        #myModal { display: none; position: fixed; z-index: 9999; padding-top: 100px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; max-width: 80%; max-height: 80%; }
        #caption { margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 10px 0; height: 150px; }
        .close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
        .close:hover, .close:focus { color: #bbb; text-decoration: none; }
        .product-img { width: 50px; height: 50px; object-fit: cover; cursor: pointer; border-radius: 4px; transition: all 0.3s; }
        .product-img:hover { opacity: 0.8; transform: scale(1.05); }
        .no-image { width: 50px; height: 50px; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: #666; font-size: 12px; }
        .file-info { font-size: 0.9em; color: #666; font-style: italic; }
        .additional-desc { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); display: flex; justify-content: center; align-items: center; z-index: 1000; }
        .overlay-content { background-color: white; padding: 20px; border-radius: 5px; width: 80%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .save-btn, .cancel-btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .save-btn { background-color: #4CAF50; color: white; }
        .cancel-btn { background-color: #f44336; color: white; }
        .cancel-btn:hover { background-color:rgb(155, 18, 8); color: white; }
        .error-message { color: red; margin-bottom: 10px; }
        #current-image-container img { max-width: 50px; max-height: 50px; margin-bottom: 10px; object-fit: cover; border-radius: 4px; }
        .product-name { font-weight: bold; }
        .inventory-tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding: 0; list-style: none; }
        .tab-item { padding: 10px 20px; cursor: pointer; font-weight: bold; border: 1px solid transparent; border-bottom: none; border-radius: 5px 5px 0 0; margin-right: 5px; transition: all 0.3s; position: relative; top: 1px; }
        .tab-item.active { background-color: #fff; border-color: #ddd; border-bottom: 1px solid #fff; color: #333; }
        .view-ingredients-btn { background-color: #555555; color: white; border: none; padding: 5px 10px; border-radius: 80px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
        .view-ingredients-btn i { margin-right: 2px; }
        .view-ingredients-btn:hover { background-color: #333333; }
        .edit-btn { display: inline-flex; align-items: center; }
        .edit-btn i { margin-right: 5px; }
        .ingredients-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .ingredients-table th, .ingredients-table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .ingredients-table th { background-color: #f2f2f2; font-weight: bold; }
        .add-ingredient-btn { background-color: #4CAF50; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-bottom: 20px; display: inline-flex; align-items: center; }
        .add-ingredient-btn i { margin-right: 5px; }
        .remove-ingredient-btn { background-color: #f44336; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; }
        .ingredient-quantity { width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .ingredient-select { width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        input:required, select:required, textarea:required { border-left: 3px solid #e67e22; }
        input:invalid, select:invalid, textarea:invalid { border-left: 3px solid #e74c3c; }
        .packaging-group { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; }
        .packaging-item { display: flex; flex-direction: column; flex: 1; }
        .packaging-item label { margin-bottom: 5px; font-size: 0.9em; }
        .packaging-item input[type="number"], .packaging-item select { width: 100%; box-sizing: border-box; }
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
                            $categories_result->data_seek(0);
                            while ($row = $categories_result->fetch_assoc()) {
                                echo "<option value='{$row['category']}'>{$row['category']}</option>";
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

        <ul class="inventory-tabs">
            <li class="tab-item active">
                <i class="fas fa-building"></i> Company Orders
            </li>
        </ul>

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Product Name</th>
                        <th>Product Variant</th>
                        <th>Packaging</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Image</th>
                        <th>Additional Description</th>
                        <th>Ingredients</th>
                        <th>Adjust Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventory-table">
                    <?php
                    if ($products_data_result && $products_data_result->num_rows > 0) {
                        while ($row = $products_data_result->fetch_assoc()) {
                            $data_attributes = "data-category='{$row['category']}'
                                               data-product-id='{$row['product_id']}'
                                               data-product-name='" . htmlspecialchars($row['product_name'] ?? '') . "'
                                               data-item-description='" . htmlspecialchars($row['item_description'] ?? '') . "'
                                               data-packaging='" . htmlspecialchars($row['packaging'] ?? '') . "'
                                               data-additional-description='" . htmlspecialchars($row['additional_description'] ?? '') . "'";

                            echo "<tr $data_attributes>
                                <td>{$row['category']}</td>
                                <td class='product-name'>" . htmlspecialchars($row['product_name'] ?? '') . "</td>
                                <td>" . htmlspecialchars($row['item_description'] ?? '') . "</td>
                                <td>" . htmlspecialchars($row['packaging'] ?? '') . "</td>
                                <td>₱" . number_format($row['price'], 2) . "</td>
                                <td id='stock-{$row['product_id']}'>{$row['stock_quantity']}</td>
                                <td class='product-image-cell'>";

                            if (!empty($row['product_image'])) {
                                echo "<img src='" . htmlspecialchars($row['product_image']) . "' alt='Product Image' class='product-img' onclick='openModal(this)'>";
                            } else {
                                echo "<div class='no-image'>No image</div>";
                            }

                            echo "</td>
                                <td class='additional-desc'>" . htmlspecialchars($row['additional_description'] ?? '') . "</td>
                                <td>
                                    <button class='view-ingredients-btn' onclick='viewIngredients({$row['product_id']}, \"company\")'>
                                        <i class='fas fa-list'></i> View
                                    </button>
                                </td>
                                <td class='adjust-stock'>
                                    <button class='add-btn' onclick='updateStock({$row['product_id']}, \"add\", \"company\")'>+</button>
                                    <input type='number' id='adjust-{$row['product_id']}' min='1' value='1'>
                                    <button class='remove-btn' onclick='updateStock({$row['product_id']}, \"remove\", \"company\")'>-</button>
                                </td>
                                <td>
                                    <button class='edit-btn' onclick='editProduct({$row['product_id']}, \"company\")'>
                                        <i class='fas fa-edit'></i> Edit
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="addProductModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <div id="addProductError" class="error-message"></div>
                <form id="add-product-form" method="POST">
                    <input type="hidden" id="add_product_type" name="product_type" value="company">

                    <label for="category">Category:</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        if ($categories_result && $categories_result->num_rows > 0) {
                            $categories_result->data_seek(0);
                            while ($row = $categories_result->fetch_assoc()) {
                                echo "<option value='{$row['category']}'>{$row['category']}</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Category</option>
                    </select>
                    <div id="new-category-container" style="display: none;">
                        <label for="new_category">New Category Name:</label>
                        <input type="text" id="new_category" name="new_category_field" placeholder="Enter new category name">
                    </div>

                    <label for="product_name">Product Name:</label>
                    <select id="product_name" name="product_name" required>
                        <option value="">Select Product Name</option>
                        <?php
                        if ($product_names_result && $product_names_result->num_rows > 0) {
                           $product_names_result->data_seek(0);
                            while ($row = $product_names_result->fetch_assoc()) {
                                echo "<option value='{$row['product_name']}'>{$row['product_name']}</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Product Name</option>
                    </select>
                    <div id="new-product-name-container" style="display: none;">
                        <label for="new_product_name">New Product Name:</label>
                        <input type="text" id="new_product_name" name="new_product_name_field" placeholder="Enter new product name">
                    </div>

                    <label for="item_description">Product Variant:</label>
                    <input type="text" id="item_description" name="item_description" required placeholder="e.g., Asado Siopao (A Large)">
                    
                    <div class="packaging-group">
                        <div class="packaging-item">
                            <label for="packaging_quantity">Quantity:</label>
                            <input type="number" id="packaging_quantity" min="1" required placeholder="e.g., 12">
                        </div>
                        <div class="packaging-item">
                            <label for="packaging_unit">Unit:</label>
                            <select id="packaging_unit" required>
                                <option value="">Select Unit</option>
                                <option value="COUNT">Piece(s)</option>
                                <option value="G">Grams (g)</option>
                                <option value="KG">Kilograms (kg)</option>
                            </select>
                        </div>
                        <div class="packaging-item">
                            <label for="packaging_container">Container:</label>
                            <select id="packaging_container" required>
                                <option value="">Select Container</option>
                                <option value="Pack">Pack</option>
                                <option value="Btl">Bottle</option>
                                <option value="Cntr">Container</option>
                                <option value="None">None</option>
                            </select>
                        </div>
                    </div>

                    <label for="price">Price (₱):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" max="5000" required placeholder="0.00">

                    <label for="additional_description">Additional Description:</label>
                    <textarea id="additional_description" name="additional_description" placeholder="Add more details about the product" required></textarea>

                    <label for="product_image">Product Image: <span class="file-info">(Max: 20MB, JPG/PNG only)</span></label>
                    <input type="file" id="product_image" name="product_image" accept="image/jpeg, image/png">

                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeAddProductModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="editProductModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-edit"></i> Edit Product</h2>
                <div id="editProductError" class="error-message"></div>
                <form id="edit-product-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="formType" value="edit_product">
                    <input type="hidden" id="edit_product_id" name="product_id">
                    <input type="hidden" id="edit_product_type" name="is_walkin" value="0"> 

                    <div class="form-grid">
                        <div>
                            <label for="edit_category">Category:</label>
                            <select id="edit_category" name="category" required>
                                <option value="">Select Category</option>
                                <?php
                                if ($categories_result && $categories_result->num_rows > 0) {
                                    $categories_result->data_seek(0);
                                    while ($row = $categories_result->fetch_assoc()) {
                                        echo "<option value='{$row['category']}'>{$row['category']}</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Category</option>
                            </select>
                            <div id="edit-new-category-container" style="display: none;">
                                <label for="edit_new_category">New Category Name:</label>
                                <input type="text" id="edit_new_category" name="new_category_field" placeholder="Enter new category name">
                            </div>

                            <label for="edit_product_name">Product Name:</label>
                            <select id="edit_product_name" name="product_name" required>
                                <option value="">Select Product Name</option>
                                <?php
                                if ($product_names_result && $product_names_result->num_rows > 0) {
                                    $product_names_result->data_seek(0);
                                    while ($row = $product_names_result->fetch_assoc()) {
                                        echo "<option value='{$row['product_name']}'>{$row['product_name']}</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Product Name</option>
                            </select>
                            <div id="edit-new-product-name-container" style="display: none;">
                                <label for="edit_new_product_name">New Product Name:</label>
                                <input type="text" id="edit_new_product_name" name="new_product_name_field" placeholder="Enter new product name">
                            </div>

                            <label for="edit_item_description">Product Variant:</label>
                            <input type="text" id="edit_item_description" name="item_description" required placeholder="Enter full product description">
                        </div>

                        <div>
                            <label for="edit_price">Price (₱):</label>
                            <input type="number" id="edit_price" name="price" step="0.01" min="0" max="5000" required placeholder="0.00">

                            <label for="edit_stock_quantity">Stock Quantity:</label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" required placeholder="0">
                        </div>
                    </div>
                     
                    <div class="packaging-group">
                        <div class="packaging-item">
                            <label for="edit_packaging_quantity">Quantity:</label>
                            <input type="number" id="edit_packaging_quantity" min="1" required placeholder="e.g., 12">
                        </div>
                        <div class="packaging-item">
                            <label for="edit_packaging_unit">Unit:</label>
                            <select id="edit_packaging_unit" required>
                                <option value="">Select Unit</option>
                                <option value="COUNT">Piece(s)</option>
                                <option value="G">Grams (g)</option>
                                <option value="KG">Kilograms (kg)</option>
                            </select>
                        </div>
                        <div class="packaging-item">
                            <label for="edit_packaging_container">Container:</label>
                            <select id="edit_packaging_container" required>
                                <option value="">Select Container</option>
                                <option value="Pack">Pack</option>
                                <option value="Btl">Bottle</option>
                                <option value="Cntr">Container</option>
                                <option value="None">None</option>
                            </select>
                        </div>
                    </div>

                     <label for="edit_additional_description">Additional Description:</label>
                     <textarea id="edit_additional_description" name="additional_description" placeholder="Add more details about the product" required></textarea>

                    <div id="current-image-container"></div>
                    <label for="edit_product_image">Product Image: <span class="file-info">(Max: 20MB, JPG/PNG only)</span></label>
                    <input type="file" id="edit_product_image" name="product_image" accept="image/jpeg, image/png">

                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeEditProductModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="ingredientsModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-list"></i> <span id="ingredients-product-name"></span> - Ingredients</h2>
                <div id="ingredientsError" class="error-message"></div>
                <form id="ingredients-form">
                    <input type="hidden" id="ingredients_product_id">
                    <input type="hidden" id="ingredients_product_type" value="company">
                    <table class="ingredients-table" id="ingredients-table">
                        <thead><tr><th>Ingredient</th><th>Quantity (grams)</th><th>Action</th></tr></thead>
                        <tbody id="ingredients-tbody"></tbody>
                    </table>
                    <button type="button" class="add-ingredient-btn" onclick="addIngredientRow()">
                        <i class="fas fa-plus"></i> Add Ingredient
                    </button>
                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeIngredientsModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="save-btn" onclick="saveIngredients()">
                            <i class="fas fa-save"></i> Save Ingredients
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="myModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="img01">
            <div id="caption"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        const rawMaterials = <?php echo json_encode($raw_materials_list); ?>;
        const activeTab = "company";

        toastr.options = { "positionClass": "toast-bottom-right", "opacity": 1 };

        function limitNumberInput(inputElement, maxValue) {
            if (!inputElement) return;
            inputElement.addEventListener('input', function() {
                let value = parseFloat(this.value);
                if (!isNaN(value) && value > maxValue) {
                    this.value = maxValue;
                    if (typeof toastr !== 'undefined') {
                         toastr.warning(`Maximum value allowed is ₱${maxValue}.`, { timeOut: 2000, preventDuplicates: true });
                    }
                } else if (!isNaN(value) && value < 0) {
                     this.value = 0;
                 }
            });
        }

        function searchProducts() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#inventory-table tr');
            rows.forEach(row => {
                const itemDescription = (row.getAttribute('data-item-description') || '').toLowerCase();
                const productName = (row.getAttribute('data-product-name') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                const packaging = (row.getAttribute('data-packaging') || '').toLowerCase();
                const additionalDescription = (row.getAttribute('data-additional-description') || '').toLowerCase();
                if (itemDescription.includes(searchValue) || productName.includes(searchValue) || category.includes(searchValue) || packaging.includes(searchValue) || additionalDescription.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('category')?.addEventListener('change', function() {
            const isNew = this.value === 'new';
            document.getElementById('new-category-container').style.display = isNew ? 'block' : 'none';
            document.getElementById('new_category').required = isNew;
        });

        document.getElementById('product_name')?.addEventListener('change', function() {
            const isNew = this.value === 'new';
            document.getElementById('new-product-name-container').style.display = isNew ? 'block' : 'none';
            document.getElementById('new_product_name').required = isNew;
            if (!isNew && this.value !== '') {
                const selectedProductName = this.value;
                const itemDescriptionInput = document.getElementById('item_description');
                if (itemDescriptionInput && (!itemDescriptionInput.value || !itemDescriptionInput.value.startsWith(selectedProductName))) {
                    itemDescriptionInput.value = selectedProductName + ' ';
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('edit_category')?.addEventListener('change', function() {
                 const isNew = this.value === 'new';
                 document.getElementById('edit-new-category-container').style.display = isNew ? 'block' : 'none';
                 document.getElementById('edit_new_category').required = isNew; 
            });
            document.getElementById('edit_product_name')?.addEventListener('change', function() {
                 const isNew = this.value === 'new';
                 document.getElementById('edit-new-product-name-container').style.display = isNew ? 'block' : 'none';
                 document.getElementById('edit_new_product_name').required = isNew; 
                 if (!isNew && this.value !== '') {
                    const selectedProductName = this.value;
                    const itemDescriptionInput = document.getElementById('edit_item_description');
                    if (itemDescriptionInput && (!itemDescriptionInput.value || !itemDescriptionInput.value.startsWith(selectedProductName))) {
                        itemDescriptionInput.value = selectedProductName + ' ';
                    }
                 }
            });
            limitNumberInput(document.getElementById('price'), 5000);
            limitNumberInput(document.getElementById('edit_price'), 5000);
        });

        function getPackagingString(qtyId, unitId, containerId) {
            const qty = document.getElementById(qtyId).value;
            const unitType = document.getElementById(unitId).value;
            const container = document.getElementById(containerId).value;

            if (!qty || !unitType || !container) {
                 return null; 
            }

            let unitString;
            if (unitType === 'COUNT') {
                unitString = parseInt(qty) === 1 ? 'pc' : 'pcs';
            } else { // G or KG
                unitString = unitType.toLowerCase(); 
            }

            let packagingString = qty.trim() + unitString;
            if (container.trim() !== 'None' && container.trim() !== '') {
                packagingString += '/' + container.trim().toLowerCase();
            }
            return packagingString;
        }


        document.getElementById('add-product-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('addProductError');
            errorDiv.textContent = ''; 

            const priceInput = document.getElementById('price');
            const price = parseFloat(priceInput.value);
            if (isNaN(price) || price <= 0 || price > 5000) {
                errorDiv.textContent = 'Price must be a positive number up to ₱5000.';
                priceInput.focus();
                return;
            }

            const categorySelect = document.getElementById('category');
            const newCategoryInput = document.getElementById('new_category');
            if (categorySelect.value === 'new' && !newCategoryInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new category name.';
                newCategoryInput.focus();
                return;
            }

            const productNameSelect = document.getElementById('product_name');
            const newProductNameInput = document.getElementById('new_product_name');
            if (productNameSelect.value === 'new' && !newProductNameInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new product name.';
                newProductNameInput.focus();
                return;
            }
            
            const packagingString = getPackagingString('packaging_quantity', 'packaging_unit', 'packaging_container');
            if (!packagingString) {
                 errorDiv.textContent = 'Please fill in all packaging details (Quantity, Unit, Container).';
                 if (!document.getElementById('packaging_quantity').value) document.getElementById('packaging_quantity').focus();
                 else if (!document.getElementById('packaging_unit').value) document.getElementById('packaging_unit').focus();
                 else document.getElementById('packaging_container').focus();
                 return;
            }

            const requiredFields = this.querySelectorAll('input[required]:not(#new_category):not(#new_product_name), select[required]:not(#new_category):not(#new_product_name), textarea[required]:not(#new_category):not(#new_product_name)');
            let firstEmptyField = null;
            for (const field of requiredFields) {
                if (field.id === 'packaging_quantity' || field.id === 'packaging_unit' || field.id === 'packaging_container') continue; // Already checked
                if (field.offsetParent !== null && !field.value.trim() ) {
                    firstEmptyField = field;
                    break;
                }
            }
            if (firstEmptyField) {
                errorDiv.textContent = `Please fill in the '${firstEmptyField.labels?.[0]?.textContent || firstEmptyField.id || 'required'}' field.`;
                firstEmptyField.focus();
                return;
            }

            const formData = new FormData(this); 
            if (categorySelect.value === 'new') formData.set('category', newCategoryInput.value.trim());
            else formData.set('category', categorySelect.value);

            if (productNameSelect.value === 'new') formData.set('product_name', newProductNameInput.value.trim());
            else formData.set('product_name', productNameSelect.value);
            
            formData.set('packaging', packagingString);

            if (!formData.has('stock_quantity')) {
                 formData.append('stock_quantity', 0);
            }
            formData.set('product_type', 'company');
            // Remove individual packaging fields from FormData if they were added by name attribute
            formData.delete('packaging_quantity');
            formData.delete('packaging_unit');
            formData.delete('packaging_container');


            fetch("../../backend/add_product.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (err) { console.error("Invalid JSON:", text, err); throw new Error("Server returned non-JSON response."); } }))
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeAddProductModal();
                    window.location.reload();
                } else {
                    errorDiv.textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message, { timeOut: 3000, closeButton: true });
                errorDiv.textContent = error.message;
            });
        });

        document.getElementById('edit-product-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('editProductError');
            errorDiv.textContent = ''; 

            const priceInput = document.getElementById('edit_price');
            const price = parseFloat(priceInput.value);
            if (isNaN(price) || price <= 0 || price > 5000) {
                errorDiv.textContent = 'Price must be a positive number up to ₱5000.';
                priceInput.focus();
                return;
            }

            const categorySelect = document.getElementById('edit_category');
            const newCategoryInput = document.getElementById('edit_new_category');
            if (categorySelect.value === 'new' && !newCategoryInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new category name.';
                newCategoryInput.focus();
                return;
            }

            const productNameSelect = document.getElementById('edit_product_name');
            const newProductNameInput = document.getElementById('edit_new_product_name');
            if (productNameSelect.value === 'new' && !newProductNameInput.value.trim()) {
                errorDiv.textContent = 'Please enter the new product name.';
                newProductNameInput.focus();
                return;
            }

            const packagingString = getPackagingString('edit_packaging_quantity', 'edit_packaging_unit', 'edit_packaging_container');
             if (!packagingString) {
                 errorDiv.textContent = 'Please fill in all packaging details (Quantity, Unit, Container).';
                 if (!document.getElementById('edit_packaging_quantity').value) document.getElementById('edit_packaging_quantity').focus();
                 else if (!document.getElementById('edit_packaging_unit').value) document.getElementById('edit_packaging_unit').focus();
                 else document.getElementById('edit_packaging_container').focus();
                 return;
            }
            
            const requiredFields = this.querySelectorAll('input[required]:not(#edit_new_category):not(#edit_new_product_name), select[required]:not(#edit_new_category):not(#edit_new_product_name), textarea[required]:not(#edit_new_category):not(#edit_new_product_name)');
            let firstEmptyField = null;
            for (const field of requiredFields) {
                 if (field.id === 'edit_packaging_quantity' || field.id === 'edit_packaging_unit' || field.id === 'edit_packaging_container') continue; // Already checked
                if (field.offsetParent !== null && !field.value.trim()) {
                    firstEmptyField = field;
                    break;
                }
            }
            if (firstEmptyField) {
                errorDiv.textContent = `Please fill in the '${firstEmptyField.labels?.[0]?.textContent || firstEmptyField.id || 'required'}' field.`;
                firstEmptyField.focus();
                return;
            }

            const formData = new FormData(this);
            if (categorySelect.value === 'new') formData.set('category', newCategoryInput.value.trim());
            else formData.set('category', categorySelect.value);

            if (productNameSelect.value === 'new') formData.set('product_name', newProductNameInput.value.trim());
            else formData.set('product_name', productNameSelect.value);

            formData.set('packaging', packagingString);
            formData.set('is_walkin', '0');
            // Remove individual packaging fields from FormData
            formData.delete('edit_packaging_quantity');
            formData.delete('edit_packaging_unit');
            formData.delete('edit_packaging_container');


            fetch(window.location.href, { 
                method: "POST",
                body: formData
            })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (err) { console.error("Invalid JSON:", text, err); throw new Error("Server returned non-JSON response."); } }))
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeEditProductModal();
                    window.location.reload();
                } else {
                    errorDiv.textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message, { timeOut: 3000, closeButton: true });
                errorDiv.textContent = error.message;
            });
        });

        function openAddProductForm() {
            document.getElementById('addProductModal').style.display = 'flex';
            const form = document.getElementById('add-product-form');
            if (form) form.reset();
            document.getElementById('addProductError').textContent = '';
            document.getElementById('new-category-container').style.display = 'none';
            document.getElementById('new_category').required = false; 
            document.getElementById('new-product-name-container').style.display = 'none';
            document.getElementById('new_product_name').required = false; 
            document.getElementById('add_product_type').value = 'company';
        }

        function closeAddProductModal() { document.getElementById('addProductModal').style.display = 'none'; }
        function closeEditProductModal() { document.getElementById('editProductModal').style.display = 'none'; }
        function closeIngredientsModal() { document.getElementById('ingredientsModal').style.display = 'none'; }

        function editProduct(productId, productType) { 
            let apiUrl = `../pages/api/get_product.php?id=${productId}`;

            fetch(apiUrl)
                .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON response:", text); throw new Error("Server returned invalid response"); } }))
                .then(product => {
                    document.getElementById('edit_product_id').value = product.product_id;
                    document.getElementById('edit_product_type').value = '0'; 

                    const categorySelect = document.getElementById('edit_category');
                    const newCategoryContainer = document.getElementById('edit-new-category-container');
                    const newCategoryInput = document.getElementById('edit_new_category');
                    newCategoryContainer.style.display = 'none'; 
                    newCategoryInput.required = false; 
                    if (product.category) {
                        categorySelect.value = product.category;
                        if (categorySelect.value !== product.category) {
                             categorySelect.value = 'new';
                             newCategoryContainer.style.display = 'block';
                             newCategoryInput.value = product.category;
                             newCategoryInput.required = true;
                        }
                    } else { categorySelect.value = ''; }

                    const productNameSelect = document.getElementById('edit_product_name');
                    const newProductNameContainer = document.getElementById('edit-new-product-name-container');
                    const newProductNameInput = document.getElementById('edit_new_product_name');
                    newProductNameContainer.style.display = 'none'; 
                    newProductNameInput.required = false; 
                    if (product.product_name) {
                        productNameSelect.value = product.product_name;
                         if (productNameSelect.value !== product.product_name) { 
                             productNameSelect.value = 'new';
                             newProductNameContainer.style.display = 'block';
                             newProductNameInput.value = product.product_name;
                             newProductNameInput.required = true;
                         }
                    } else { productNameSelect.value = ''; }

                    document.getElementById('edit_item_description').value = product.item_description || '';
                    
                    const packagingStr = product.packaging || '';
                    let qtyVal = '';
                    let unitTypeVal = ''; 
                    let containerVal = 'None'; 

                    const regex = /^(\d+)([a-zA-Z]+)(?:\/([a-zA-Z]+))?$/;
                    const match = packagingStr.match(regex);

                    if (match) {
                        qtyVal = match[1]; 
                        const parsedUnit = match[2].toLowerCase(); 
                        
                        if (parsedUnit === 'pc' || parsedUnit === 'pcs') {
                            unitTypeVal = 'COUNT';
                        } else if (parsedUnit === 'g') {
                            unitTypeVal = 'G';
                        } else if (parsedUnit === 'kg') {
                            unitTypeVal = 'KG';
                        }

                        if (match[3]) { 
                            const parsedContainer = match[3].toLowerCase();
                            if (parsedContainer === 'pack') containerVal = 'Pack';
                            else if (parsedContainer === 'btl') containerVal = 'Btl';
                            else if (parsedContainer === 'cntr') containerVal = 'Cntr';
                        }
                    }
                    
                    document.getElementById('edit_packaging_quantity').value = qtyVal;
                    document.getElementById('edit_packaging_unit').value = unitTypeVal;
                    document.getElementById('edit_packaging_container').value = containerVal;

                    document.getElementById('edit_price').value = product.price || 0;
                    document.getElementById('edit_stock_quantity').value = product.stock_quantity || 0;
                    document.getElementById('edit_additional_description').value = product.additional_description || '';

                    document.getElementById('current-image-container').innerHTML = '';
                    if (product.product_image) {
                        document.getElementById('current-image-container').innerHTML = `<p>Current Image:</p><img src="${product.product_image}" alt="Current product image" style="max-width: 100px; max-height: 100px; margin-bottom: 10px; object-fit: cover; border-radius: 4px;">`;
                    }
                    document.getElementById('editProductModal').style.display = 'flex';
                    document.getElementById('editProductError').textContent = '';
                })
                .catch(error => {
                    toastr.error("Error fetching product details: " + error.message, { timeOut: 3000, closeButton: true });
                });
        }

        function viewIngredients(productId, productType) {
             let apiUrl = `../pages/api/get_product_ingredients.php?id=${productId}`;
            fetch(apiUrl)
                .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON response:", text); throw new Error("Server returned invalid response"); } }))
                .then(product => {
                    document.getElementById('ingredients-product-name').textContent = product.item_description;
                    document.getElementById('ingredients_product_id').value = product.product_id;
                    document.getElementById('ingredients_product_type').value = productType; 
                    const tbody = document.getElementById('ingredients-tbody');
                    tbody.innerHTML = '';
                    if (product.ingredients && product.ingredients.length > 0) {
                        product.ingredients.forEach(ingredient => addIngredientRow(ingredient[0], ingredient[1]));
                    } else { addIngredientRow(); }
                    document.getElementById('ingredientsModal').style.display = 'flex';
                    document.getElementById('ingredientsError').textContent = '';
                })
                .catch(error => {
                    toastr.error("Error fetching ingredients: " + error.message, { timeOut: 3000, closeButton: true });
                });
        }

        function addIngredientRow(ingredientName = '', quantity = '') {
            const tbody = document.getElementById('ingredients-tbody');
            const row = document.createElement('tr');
            let selectHtml = `<select class="ingredient-select" required><option value="">Select Ingredient</option>`;
            rawMaterials.forEach(material => {
                const selected = material.name === ingredientName ? 'selected' : '';
                selectHtml += `<option value="${material.name}" ${selected}>${material.name}</option>`;
            });
            selectHtml += '</select>';
            row.innerHTML = `<td>${selectHtml}</td><td><input type="number" class="ingredient-quantity" min="1" step="1" value="${quantity}" required></td><td><button type="button" class="remove-ingredient-btn" onclick="removeIngredientRow(this)"><i class="fas fa-trash"></i></button></td>`;
            tbody.appendChild(row);
        }

        function removeIngredientRow(button) { button.closest('tr').remove(); }

        function saveIngredients() {
            const productId = document.getElementById('ingredients_product_id').value;
            const productType = document.getElementById('ingredients_product_type').value;
            const rows = document.querySelectorAll('#ingredients-tbody tr');
            const ingredients = [];
            const errorDiv = document.getElementById('ingredientsError');
            errorDiv.textContent = ''; 
            let isValid = true;
            rows.forEach(row => {
                const ingredientSelect = row.querySelector('.ingredient-select');
                const quantityInput = row.querySelector('.ingredient-quantity');
                const ingredientName = ingredientSelect.value;
                const quantity = parseInt(quantityInput.value);
                if (!ingredientName) { errorDiv.textContent = 'Please select an ingredient for all rows.'; isValid = false; ingredientSelect.focus(); return; }
                if (isNaN(quantity) || quantity <= 0) { errorDiv.textContent = 'Please enter a valid positive quantity for all ingredients.'; isValid = false; quantityInput.focus(); return; }
                ingredients.push([ingredientName, quantity]);
            });
            if (!isValid) return; 
            fetch("../pages/api/update_ingredients.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ product_id: productId, product_type: productType, ingredients: ingredients })
            })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON response:", text); throw new Error("Server returned invalid response"); } }))
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeIngredientsModal();
                } else { errorDiv.textContent = data.message || 'An unknown error occurred'; }
            })
            .catch(error => {
                toastr.error("Error updating ingredients: " + error.message, { timeOut: 3000, closeButton: true });
                errorDiv.textContent = error.message;
            });
        }

        function updateStock(productId, action, productType) {
            const amountInput = document.getElementById(`adjust-${productId}`);
            const amount = parseInt(amountInput.value);
            if (isNaN(amount) || amount <= 0) {
                 toastr.error("Please enter a valid positive amount to adjust.", { timeOut: 3000, closeButton: true });
                 amountInput.focus(); return;
            }
            fetch("../pages/api/update_stock.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ product_id: productId, action: action, amount: amount, product_type: productType })
            })
            .then(response => response.text().then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON response:", text); throw new Error("Server returned invalid response"); } }))
            .then(data => {
                if (data.success) {
                     toastr.success(data.message, { timeOut: 3000, closeButton: true });
                     const stockCell = document.getElementById(`stock-${productId}`);
                     if (stockCell) stockCell.textContent = data.new_stock;
                     amountInput.value = 1; 
                 } else { toastr.error(data.message || "Error updating stock.", { timeOut: 3000, closeButton: true }); }
            })
            .catch(error => {
                toastr.error("Error updating stock: " + error.message, { timeOut: 3000, closeButton: true });
            });
        }

        function filterByCategory() {
            const filterValue = document.getElementById('category-filter').value;
            const rows = document.querySelectorAll('#inventory-table tr');
            rows.forEach(row => {
                if (filterValue === 'all' || row.getAttribute('data-category') === filterValue) {
                    row.style.display = '';
                } else { row.style.display = 'none'; }
            });
        }

        function openModal(imgElement) {
            document.getElementById("myModal").style.display = "block";
            document.getElementById("img01").src = imgElement.src;
            document.getElementById("caption").innerHTML = imgElement.alt;
        }
        function closeModal() { document.getElementById("myModal").style.display = "none"; }

        window.addEventListener('click', function(e) {
            if (e.target === document.getElementById('addProductModal')) closeAddProductModal();
            if (e.target === document.getElementById('editProductModal')) closeEditProductModal();
            if (e.target === document.getElementById('ingredientsModal')) closeIngredientsModal();
            if (e.target === document.getElementById('myModal')) closeModal();
        });
    </script>
</body>
</html>