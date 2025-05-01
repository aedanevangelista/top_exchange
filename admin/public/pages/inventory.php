<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php");
    exit();
}

// Determine which tab is active
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'company';

// Process form submissions for standard product updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['formType']) && $_POST['formType'] == 'edit_product') {
    header('Content-Type: application/json');

    $product_id = $_POST['product_id'];
    $is_walkin = isset($_POST['is_walkin']) && $_POST['is_walkin'] == '1';
    $table = $is_walkin ? 'walkin_products' : 'products';

    $category = $_POST['category'];
    $product_name = $_POST['product_name']; // New field
    $item_description = $_POST['item_description'];
    $packaging = $_POST['packaging'];
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $additional_description = $_POST['additional_description'];

    if ($category === 'new' && isset($_POST['new_category']) && !empty($_POST['new_category'])) {
        $category = $_POST['new_category'];
    }

    if ($product_name === 'new' && isset($_POST['new_product_name']) && !empty($_POST['new_product_name'])) {
        $product_name = $_POST['new_product_name'];
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

    // *** FIX 1: Update upload directory path ***
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/products/';
    if (!file_exists($upload_dir)) {
        // Note: Use 0755 for directories for better security practice
        mkdir($upload_dir, 0755, true);
    }

    // Image upload handling code
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024;
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            // Create proper folder path based on product variant
            $item_folder = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $item_description); // Allow underscores, dots, hyphens
            $item_dir = $upload_dir . $item_folder . '/';

            // Create the directory if it doesn't exist
            if (!file_exists($item_dir)) {
                // Note: Use 0755 for directories
                mkdir($item_dir, 0755, true);
            }

            // Step 1: Handle item description change (if the product variant name changed)
            if ($old_item_description != $item_description && !empty($old_product_image)) {
                $old_item_folder = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $old_item_description);
                $old_item_dir = $upload_dir . $old_item_folder . '/';

                if (file_exists($old_item_dir)) {
                    error_log("Cleaning up old folder due to item description change: " . $old_item_dir);
                    $old_files = array_diff(scandir($old_item_dir), array('.', '..'));
                    foreach ($old_files as $file) {
                        if (file_exists($old_item_dir . $file)) {
                            if (unlink($old_item_dir . $file)) {
                                error_log("Successfully deleted old file: " . $old_item_dir . $file);
                            } else {
                                error_log("Failed to delete old file: " . $old_item_dir . $file);
                            }
                        }
                    }

                    // Try to remove old directory if empty
                    if (count(array_diff(scandir($old_item_dir), array('.', '..'))) == 0) {
                        if (rmdir($old_item_dir)) {
                            error_log("Successfully removed old directory: " . $old_item_dir);
                        } else {
                            error_log("Failed to remove old directory: " . $old_item_dir);
                        }
                    }
                }
            }

            // Step 2: CLEAN UP EXISTING FILES - Make sure this happens BEFORE trying to upload
            if (file_exists($item_dir)) {
                error_log("Cleaning up current folder before new upload: " . $item_dir);
                $existing_files = array_diff(scandir($item_dir), array('.', '..'));

                // Log what we found
                error_log("Found " . count($existing_files) . " existing files to remove");

                // Remove each file with proper error handling
                foreach ($existing_files as $file) {
                    if (file_exists($item_dir . $file)) {
                        if (unlink($item_dir . $file)) {
                            error_log("Successfully deleted file: " . $item_dir . $file);
                        } else {
                            error_log("Failed to delete file: " . $item_dir . $file . " - Error: " . error_get_last()['message']);
                        }
                    }
                }

                // Double-check that files were actually deleted
                $remaining_files = array_diff(scandir($item_dir), array('.', '..'));
                if (count($remaining_files) > 0) {
                    error_log("Warning: Some files could not be deleted. Remaining files: " . implode(", ", $remaining_files));
                } else {
                    error_log("All files successfully deleted from directory");
                }
            }

            // Step 3: Now that cleanup is done, proceed with the upload
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_image.' . $file_extension;
            // *** FIX 2: Update product image path for database ***
            $product_image_path = '/admin/uploads/products/' . $item_folder . '/' . $filename;
            $target_file_path = $item_dir . $filename;

            // Add a small delay to ensure file system operations are complete
            usleep(100000); // 0.1 second delay

            error_log("Attempting to upload new image to: " . $target_file_path);

            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file_path)) {
                error_log("Successfully uploaded new image");
                $product_image = $product_image_path; // Set the new path for database update

                // Verify the file was actually created
                if (file_exists($target_file_path)) {
                    error_log("Verified file exists: " . $target_file_path . " (size: " . filesize($target_file_path) . " bytes)");
                } else {
                    error_log("Warning: File doesn't exist after move_uploaded_file reported success!");
                }
            } else {
                $upload_error = error_get_last();
                error_log("Failed to upload image. Error: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
                error_log("Upload details - tmp_name: " . $_FILES['product_image']['tmp_name'] . ", target: " . $target_file_path);
                echo json_encode(['success' => false, 'message' => 'Failed to upload image. Please try again.']);
                exit;
            }
        } else {
            error_log("Invalid file type or size. Type: $file_type, Size: $file_size bytes");
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
            exit;
        }
    }

    // Update the database with potentially new image path
    $stmt = $conn->prepare("UPDATE $table SET category = ?, product_name = ?, item_description = ?, packaging = ?, price = ?, stock_quantity = ?, additional_description = ?, product_image = ? WHERE product_id = ?");
    $stmt->bind_param("ssssdissi", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image, $product_id);

    if ($stmt->execute()) {
        // If item description changed, update image path for other products with the same new description (if applicable)
        if ($old_item_description != $item_description && !empty($product_image)) {
            $stmt_update_others = $conn->prepare("UPDATE $table SET product_image = ? WHERE item_description = ? AND product_id != ?");
            $stmt_update_others->bind_param("ssi", $product_image, $item_description, $product_id);
            $stmt_update_others->execute();
            $stmt_update_others->close();
        }

        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
    }
    $stmt->close();

    exit;
}

// Fetch raw materials for ingredients dropdown
$raw_materials_sql = "SELECT material_id, name FROM raw_materials ORDER BY name";
$raw_materials = $conn->query($raw_materials_sql);
$raw_materials_list = [];
if ($raw_materials && $raw_materials->num_rows > 0) {
    while ($row = $raw_materials->fetch_assoc()) {
        $raw_materials_list[] = $row;
    }
}

// Fetch categories from both tables (products and walkin_products)
$sql = "SELECT DISTINCT category FROM products
        UNION
        SELECT DISTINCT category FROM walkin_products
        ORDER BY category";
$categories = $conn->query($sql);

// Fetch product names from both tables
$sql = "SELECT DISTINCT product_name FROM products WHERE product_name IS NOT NULL AND product_name != ''
        UNION
        SELECT DISTINCT product_name FROM walkin_products WHERE product_name IS NOT NULL AND product_name != ''
        ORDER BY product_name";
$product_names = $conn->query($sql);

// Fetch products data based on active tab
if ($active_tab === 'company') {
    $sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image FROM products ORDER BY category, product_name, item_description";
} else {
    $sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image FROM walkin_products ORDER BY category, product_name, item_description";
}
$result = $conn->query($sql);
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
        /* Existing styles */
        #myModal {
            display: none;
            position: fixed;
            z-index: 9999;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 80%;
            max-height: 80%;
        }

        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 10px 0;
            height: 150px;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
        }

        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
            vertical-align: middle; /* Align image nicely in cell */
        }

        .product-img:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        .no-image {
            width: 50px;
            height: 50px;
            background-color: #f0f0f0;
            display: inline-flex; /* Use inline-flex to align with text */
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #666;
            font-size: 12px;
            vertical-align: middle; /* Align placeholder nicely */
            text-align: center;
            line-height: 1.2;
        }

        .file-info {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }

        .additional-desc {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .overlay-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn, .cancel-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .save-btn {
            background-color: #4CAF50;
            color: white;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
        }

        .cancel-btn:hover {
            background-color:rgb(155, 18, 8);
            color: white;
        }

        .error-message {
            color: red;
            margin-bottom: 10px;
        }

        #current-image-container img {
            max-width: 50px;
            max-height: 50px;
            margin-bottom: 10px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
        }
        #current-image-container p {
            margin-bottom: 5px;
        }

        .product-name {
            font-weight: bold;
        }

        /* New styles for tabs */
        .inventory-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding: 0;
            list-style: none;
        }

        .tab-item {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            transition: all 0.3s;
            position: relative;
            top: 1px;
        }

        .tab-item.active {
            background-color: #fff;
            border-color: #ddd;
            border-bottom: 1px solid #fff;
            color: #333;
        }

        .tab-item:not(.active) {
            background-color: #f8f8f8;
            color: #777;
        }

        .tab-item:not(.active):hover {
            background-color: #f0f0f0;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .view-ingredients-btn {
            background-color: #555555; /* Dark gray color */
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 80px; /* Circular border radius */
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .view-ingredients-btn i {
            margin-right: 2px;
        }

        .view-ingredients-btn:hover {
            background-color: #333333; /* Darker gray on hover */
        }

        .edit-btn {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            background-color: #ffc107; /* Yellow */
            color: #333;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .edit-btn i {
            margin-right: 5px;
        }
        .edit-btn:hover {
            background-color: #e0a800; /* Darker yellow */
        }

        .ingredients-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .ingredients-table th,
        .ingredients-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .ingredients-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .add-ingredient-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
        }

        .add-ingredient-btn i {
            margin-right: 5px;
        }

        .remove-ingredient-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
        }

        .ingredient-quantity {
            width: 80px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .ingredient-select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .adjust-stock button {
            padding: 2px 6px;
            margin: 0 2px;
            cursor: pointer;
        }
        .adjust-stock input {
            width: 50px;
            padding: 3px;
            text-align: center;
        }
        .product-image-cell {
            text-align: center; /* Center image/placeholder */
        }
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
                        if ($categories && $categories->num_rows > 0) {
                            // Reset pointer for reuse
                            $categories->data_seek(0);
                            while ($row = $categories->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'>" . htmlspecialchars($row['category']) . "</option>";
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

        <!-- Tabs navigation -->
        <ul class="inventory-tabs">
            <li class="tab-item <?php echo ($active_tab === 'company') ? 'active' : ''; ?>" onclick="window.location.href='?tab=company'">
                <i class="fas fa-building"></i> Company Orders
            </li>
            <li class="tab-item <?php echo ($active_tab === 'walkin') ? 'active' : ''; ?>" onclick="window.location.href='?tab=walkin'">
                <i class="fas fa-walking"></i> Walk-in Customers
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
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $data_attributes = "data-category='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'
                                               data-product-id='{$row['product_id']}'
                                               data-product-name='" . htmlspecialchars($row['product_name'] ?? '', ENT_QUOTES) . "'
                                               data-item-description='" . htmlspecialchars($row['item_description'], ENT_QUOTES) . "'
                                               data-packaging='" . htmlspecialchars($row['packaging'], ENT_QUOTES) . "'
                                               data-additional-description='" . htmlspecialchars($row['additional_description'] ?? '', ENT_QUOTES) . "'";

                            echo "<tr $data_attributes>
                                <td>" . htmlspecialchars($row['category']) . "</td>
                                <td class='product-name'>" . htmlspecialchars($row['product_name'] ?? '') . "</td>
                                <td>" . htmlspecialchars($row['item_description']) . "</td>
                                <td>" . htmlspecialchars($row['packaging']) . "</td>
                                <td>₱" . number_format($row['price'], 2) . "</td>
                                <td id='stock-{$row['product_id']}'>{$row['stock_quantity']}</td>";

                            // *** FIX 3: Apply image display logic here ***
                            echo "<td class='product-image-cell'>";
                            $image_path_db = $row['product_image']; // e.g., /admin/uploads/products/.../image.png
                            $image_path_server = $_SERVER['DOCUMENT_ROOT'] . $image_path_db; // e.g., /home/user/.../public_html/admin/uploads/.../image.png

                            // Check if path is not empty AND the file exists on the server
                            if (!empty($image_path_db) && file_exists($image_path_server)) {
                                // Use htmlspecialchars on the DB path for the src attribute
                                echo "<img src='" . htmlspecialchars($image_path_db, ENT_QUOTES, 'UTF-8') . "'
                                           alt='" . htmlspecialchars($row['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') . " Image'
                                           class='product-img'
                                           onclick='openModal(this)'>";
                            } else {
                                // Display placeholder if no image path or file doesn't exist
                                echo "<div class='no-image'>No image</div>";
                                // Optional: Log if the path exists but the file doesn't
                                if (!empty($image_path_db)) {
                                    error_log("Image file not found for product ID {$row['product_id']}: {$image_path_server}");
                                }
                            }
                            echo "</td>";

                            echo "<td class='additional-desc' title='" . htmlspecialchars($row['additional_description'] ?? '', ENT_QUOTES) . "'>" . htmlspecialchars($row['additional_description'] ?? '') . "</td>
                                <td>
                                    <button class='view-ingredients-btn' onclick='viewIngredients({$row['product_id']}, \"" . ($active_tab === 'walkin' ? 'walkin' : 'company') . "\")'>
                                        <i class='fas fa-list'></i> View
                                    </button>
                                </td>
                                <td class='adjust-stock'>
                                    <button class='add-btn' onclick='updateStock({$row['product_id']}, \"add\", \"" . ($active_tab === 'walkin' ? 'walkin' : 'company') . "\")'>+</button>
                                    <input type='number' id='adjust-{$row['product_id']}' min='1' value='1'>
                                    <button class='remove-btn' onclick='updateStock({$row['product_id']}, \"remove\", \"" . ($active_tab === 'walkin' ? 'walkin' : 'company') . "\")'>-</button>
                                </td>
                                <td>
                                    <button class='edit-btn' onclick='editProduct({$row['product_id']}, \"" . ($active_tab === 'walkin' ? 'walkin' : 'company') . "\")'>
                                        <i class='fas fa-edit'></i> Edit
                                    </button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11'>No products found for this view.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Add Product Modal -->
        <div id="addProductModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <div id="addProductError" class="error-message"></div>
                <form id="add-product-form" method="POST">
                    <input type="hidden" id="add_product_type" name="product_type" value="<?php echo $active_tab === 'walkin' ? 'walkin' : 'company'; ?>">

                    <label for="category">Category:</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        if ($categories && $categories->num_rows > 0) {
                            $categories->data_seek(0); // Reset pointer
                            while ($row = $categories->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'>" . htmlspecialchars($row['category']) . "</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Category</option>
                    </select>

                    <div id="new-category-container" style="display: none;">
                        <label for="new_category">New Category Name:</label>
                        <input type="text" id="new_category" name="new_category" placeholder="Enter new category name">
                    </div>

                    <label for="product_name">Product Name:</label>
                    <select id="product_name" name="product_name" required>
                        <option value="">Select Product Name</option>
                        <?php
                        if ($product_names && $product_names->num_rows > 0) {
                            $product_names->data_seek(0); // Reset pointer
                            while ($row = $product_names->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['product_name'], ENT_QUOTES) . "'>" . htmlspecialchars($row['product_name']) . "</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Product Name</option>
                    </select>

                    <div id="new-product-name-container" style="display: none;">
                        <label for="new_product_name">New Product Name:</label>
                        <input type="text" id="new_product_name" name="new_product_name" placeholder="Enter new product name">
                    </div>

                    <label for="item_description">Product Variant:</label>
                    <input type="text" id="item_description" name="item_description" required placeholder="Enter full product description (e.g., Asado Siopao (A Large))">

                    <label for="packaging">Packaging:</label>
                    <input type="text" id="packaging" name="packaging" required placeholder="e.g., Box of 10, 250g pack">

                    <label for="price">Price (₱):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00">

                    <label for="additional_description">Additional Description:</label>
                    <textarea id="additional_description" name="additional_description" placeholder="Add more details about the product"></textarea>

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

        <!-- Edit Product Modal -->
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
                                if ($categories && $categories->num_rows > 0) {
                                    $categories->data_seek(0); // Reset pointer
                                    while ($row = $categories->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'>" . htmlspecialchars($row['category']) . "</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Category</option>
                            </select>

                            <div id="edit-new-category-container" style="display: none;">
                                <label for="edit_new_category">New Category Name:</label>
                                <input type="text" id="edit_new_category" name="new_category" placeholder="Enter new category name">
                            </div>

                            <label for="edit_product_name">Product Name:</label>
                            <select id="edit_product_name" name="product_name" required>
                                <option value="">Select Product Name</option>
                                <?php
                                if ($product_names && $product_names->num_rows > 0) {
                                    $product_names->data_seek(0); // Reset pointer
                                    while ($row = $product_names->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['product_name'], ENT_QUOTES) . "'>" . htmlspecialchars($row['product_name']) . "</option>";
                                    }
                                }
                                ?>
                                <option value="new">+ Add New Product Name</option>
                            </select>

                            <div id="edit-new-product-name-container" style="display: none;">
                                <label for="edit_new_product_name">New Product Name:</label>
                                <input type="text" id="edit_new_product_name" name="new_product_name" placeholder="Enter new product name">
                            </div>

                            <label for="edit_item_description">Product Variant:</label>
                            <input type="text" id="edit_item_description" name="item_description" required placeholder="Enter full product description (e.g., Asado Siopao (A Large))">

                            <label for="edit_packaging">Packaging:</label>
                            <input type="text" id="edit_packaging" name="packaging" required placeholder="e.g., Box of 10, 250g pack">
                        </div>

                        <div>
                            <label for="edit_price">Price (₱):</label>
                            <input type="number" id="edit_price" name="price" step="0.01" min="0" required placeholder="0.00">

                            <label for="edit_stock_quantity">Stock Quantity:</label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" required placeholder="0">

                            <label for="edit_additional_description">Additional Description:</label>
                            <textarea id="edit_additional_description" name="additional_description" placeholder="Add more details about the product"></textarea>
                        </div>
                    </div>

                    <div id="current-image-container">
                        <!-- Current image will be loaded here by JS -->
                    </div>

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

        <!-- Ingredients Modal -->
        <div id="ingredientsModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-list"></i> <span id="ingredients-product-name"></span> - Ingredients</h2>
                <div id="ingredientsError" class="error-message"></div>

                <form id="ingredients-form">
                    <input type="hidden" id="ingredients_product_id">
                    <input type="hidden" id="ingredients_product_type" value="company">

                    <table class="ingredients-table" id="ingredients-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Quantity (grams)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ingredients-tbody">
                            <!-- Rows will be added dynamically -->
                        </tbody>
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

        <!-- Image Zoom Modal -->
        <div id="myModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="img01">
            <div id="caption"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Store raw materials for the ingredients dropdown
        const rawMaterials = <?php echo json_encode($raw_materials_list); ?>;
        // Store current active tab
        const activeTab = "<?php echo $active_tab; ?>";

        toastr.options = {
            "positionClass": "toast-bottom-right",
            "opacity": 1,
            "closeButton": true,
            "progressBar": true,
            "timeOut": 3000
        };

        function searchProducts() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#inventory-table tr');

            rows.forEach(row => {
                const itemDescription = (row.getAttribute('data-item-description') || '').toLowerCase();
                const productName = (row.getAttribute('data-product-name') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                const packaging = (row.getAttribute('data-packaging') || '').toLowerCase();
                const additionalDescription = (row.getAttribute('data-additional-description') || '').toLowerCase();

                if (itemDescription.includes(searchValue) ||
                    productName.includes(searchValue) ||
                    category.includes(searchValue) ||
                    packaging.includes(searchValue) ||
                    additionalDescription.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('category').addEventListener('change', function() {
            document.getElementById('new-category-container').style.display = (this.value === 'new') ? 'block' : 'none';
        });

        document.getElementById('product_name').addEventListener('change', function() {
            document.getElementById('new-product-name-container').style.display = (this.value === 'new') ? 'block' : 'none';

            if (this.value !== '' && this.value !== 'new') {
                const selectedProductName = this.value;
                const itemDescriptionInput = document.getElementById('item_description');

                // Optionally pre-fill or check if item description starts with product name
                // if (!itemDescriptionInput.value.startsWith(selectedProductName)) {
                //     itemDescriptionInput.value = selectedProductName + ' '; // Add space for variant
                // }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('edit_category').addEventListener('change', function() {
                document.getElementById('edit-new-category-container').style.display = (this.value === 'new') ? 'block' : 'none';
            });

            document.getElementById('edit_product_name').addEventListener('change', function() {
                document.getElementById('edit-new-product-name-container').style.display = (this.value === 'new') ? 'block' : 'none';

                if (this.value !== '' && this.value !== 'new') {
                    const selectedProductName = this.value;
                    const itemDescriptionInput = document.getElementById('edit_item_description');
                    // Optionally pre-fill or check if item description starts with product name
                    // if (!itemDescriptionInput.value.startsWith(selectedProductName)) {
                    //     itemDescriptionInput.value = selectedProductName + ' '; // Add space for variant
                    // }
                }
            });
        });

        document.getElementById('add-product-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const categorySelect = document.getElementById('category');
            let category = categorySelect.value;
            if (category === 'new') {
                category = document.getElementById('new_category').value.trim();
                if (!category) {
                    document.getElementById('addProductError').textContent = 'Please enter a new category name.';
                    return;
                }
            }

            const productNameSelect = document.getElementById('product_name');
            let product_name = productNameSelect.value;
            if (product_name === 'new') {
                product_name = document.getElementById('new_product_name').value.trim();
                if (!product_name) {
                    document.getElementById('addProductError').textContent = 'Please enter a new product name.';
                    return;
                }
            }

            const item_description = document.getElementById('item_description').value.trim();
            if (!item_description) {
                document.getElementById('addProductError').textContent = 'Product Variant is required.';
                return;
            }
            const packaging = document.getElementById('packaging').value.trim();
             if (!packaging) {
                document.getElementById('addProductError').textContent = 'Packaging is required.';
                return;
            }
            const price = document.getElementById('price').value;
            if (price === '' || isNaN(parseFloat(price)) || parseFloat(price) < 0) {
                 document.getElementById('addProductError').textContent = 'Valid Price is required.';
                return;
            }
            const additional_description = document.getElementById('additional_description').value;
            const product_type = document.getElementById('add_product_type').value;

            const formData = new FormData();
            formData.append('category', category);
            formData.append('product_name', product_name);
            formData.append('item_description', item_description);
            formData.append('packaging', packaging);
            formData.append('price', price);
            formData.append('additional_description', additional_description);
            formData.append('stock_quantity', 0); // Default stock to 0 for new products
            formData.append('product_type', product_type);

            const product_image = document.getElementById('product_image').files[0];
            if (product_image) {
                formData.append('product_image', product_image);
            }

            document.getElementById('addProductError').textContent = '';

            fetch("../../backend/add_product.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text()) // Get raw text first
            .then(text => {
                try {
                    const data = JSON.parse(text); // Try to parse as JSON
                    if (data.success) {
                        toastr.success(data.message);
                        closeAddProductModal();
                        window.location.reload();
                    } else {
                        document.getElementById('addProductError').textContent = data.message || 'An unknown error occurred';
                    }
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    throw new Error("Server returned invalid response: " + text.substring(0, 100) + "...");
                }
            })
            .catch(error => {
                toastr.error(error.message);
                document.getElementById('addProductError').textContent = error.message;
                console.error("Error:", error);
            });
        });

        // Edit form submission handler
        document.getElementById('edit-product-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const categorySelect = document.getElementById('edit_category');
            if (categorySelect.value === 'new' && !document.getElementById('edit_new_category').value.trim()) {
                document.getElementById('editProductError').textContent = 'Please enter a new category name.';
                return;
            }

            const productNameSelect = document.getElementById('edit_product_name');
            if (productNameSelect.value === 'new' && !document.getElementById('edit_new_product_name').value.trim()) {
                document.getElementById('editProductError').textContent = 'Please enter a new product name.';
                return;
            }

            const formData = new FormData(this);
            document.getElementById('editProductError').textContent = '';

            fetch(window.location.href, { // Post to the same page (inventory.php)
                method: "POST",
                body: formData
            })
            .then(response => response.text()) // Get raw text first
            .then(text => {
                try {
                    const data = JSON.parse(text); // Try to parse as JSON
                    if (data.success) {
                        toastr.success(data.message);
                        closeEditProductModal();
                        window.location.reload();
                    } else {
                        document.getElementById('editProductError').textContent = data.message || 'An unknown error occurred';
                    }
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    throw new Error("Server returned invalid response: " + text.substring(0, 100) + "...");
                }
            })
            .catch(error => {
                toastr.error(error.message);
                document.getElementById('editProductError').textContent = error.message;
                console.error("Error:", error);
            });
        });

        function openAddProductForm() {
            document.getElementById('addProductModal').style.display = 'flex';
            document.getElementById('add-product-form').reset();
            document.getElementById('addProductError').textContent = '';
            document.getElementById('new-category-container').style.display = 'none';
            document.getElementById('new-product-name-container').style.display = 'none';
            document.getElementById('add_product_type').value = activeTab === 'walkin' ? 'walkin' : 'company';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').style.display = 'none';
        }

        function closeIngredientsModal() {
            document.getElementById('ingredientsModal').style.display = 'none';
        }

        // Updated editProduct function to handle both product types
        function editProduct(productId, productType) {
            let apiUrl = `../../backend/get_product.php?id=${productId}`; // Adjusted path
            if (productType === 'walkin') {
                apiUrl += '&type=walkin';
            }

            fetch(apiUrl)
                .then(response => response.text()) // Get raw text first
                .then(text => {
                    try {
                        const product = JSON.parse(text); // Try to parse as JSON
                        if (product.error) {
                            throw new Error(product.error);
                        }

                        // Set form values
                        document.getElementById('edit_product_id').value = product.product_id;
                        document.getElementById('edit_product_type').value = productType === 'walkin' ? '1' : '0';

                        // Set category
                        const categorySelect = document.getElementById('edit_category');
                        categorySelect.value = ''; // Reset first
                        document.getElementById('edit-new-category-container').style.display = 'none';
                        document.getElementById('edit_new_category').value = '';
                        if (product.category) {
                            let categoryExists = Array.from(categorySelect.options).some(opt => opt.value === product.category);
                            if (categoryExists) {
                                categorySelect.value = product.category;
                            } else {
                                categorySelect.value = 'new';
                                document.getElementById('edit-new-category-container').style.display = 'block';
                                document.getElementById('edit_new_category').value = product.category;
                            }
                        }

                        // Set product name
                        const productNameSelect = document.getElementById('edit_product_name');
                        productNameSelect.value = ''; // Reset first
                        document.getElementById('edit-new-product-name-container').style.display = 'none';
                        document.getElementById('edit_new_product_name').value = '';
                        if (product.product_name) {
                            let productNameExists = Array.from(productNameSelect.options).some(opt => opt.value === product.product_name);
                            if (productNameExists) {
                                productNameSelect.value = product.product_name;
                            } else {
                                productNameSelect.value = 'new';
                                document.getElementById('edit-new-product-name-container').style.display = 'block';
                                document.getElementById('edit_new_product_name').value = product.product_name;
                            }
                        }

                        // Set other form fields
                        document.getElementById('edit_item_description').value = product.item_description || '';
                        document.getElementById('edit_packaging').value = product.packaging || '';
                        document.getElementById('edit_price').value = product.price || 0;
                        document.getElementById('edit_stock_quantity').value = product.stock_quantity || 0;
                        document.getElementById('edit_additional_description').value = product.additional_description || '';

                        // Show current image if it exists
                        const imgContainer = document.getElementById('current-image-container');
                        imgContainer.innerHTML = ''; // Clear previous
                        if (product.product_image) {
                            // *** FIX 4: Ensure correct path is used for display in edit modal ***
                            const imgSrc = product.product_image; // Path already includes /admin/
                            imgContainer.innerHTML = `
                                <p>Current Image:</p>
                                <img src="${imgSrc}" alt="Current product image" style="max-width: 100px; max-height: 100px; margin-bottom: 10px; object-fit: cover; border-radius: 4px; vertical-align: middle;">
                            `;
                        } else {
                             imgContainer.innerHTML = `<p>No current image.</p>`;
                        }

                        // Reset file input
                        document.getElementById('edit_product_image').value = '';

                        // Show the modal
                        document.getElementById('editProductModal').style.display = 'flex';
                        document.getElementById('editProductError').textContent = '';

                    } catch (e) {
                        console.error("Error processing product details:", text, e);
                        throw new Error("Server returned invalid response or product not found.");
                    }
                })
                .catch(error => {
                    toastr.error("Error fetching product details: " + error.message);
                    console.error("Error fetching product details:", error);
                });
        }

        // Updated viewIngredients function to handle both product types
        function viewIngredients(productId, productType) {
            let apiUrl = `../../backend/get_product_ingredients.php?id=${productId}`; // Adjusted path
            if (productType === 'walkin') {
                apiUrl += '&type=walkin';
            }

            fetch(apiUrl)
                .then(response => response.text()) // Get raw text first
                .then(text => {
                    try {
                        const product = JSON.parse(text); // Try to parse as JSON
                         if (product.error) {
                            throw new Error(product.error);
                        }

                        // Set product name in the modal title
                        document.getElementById('ingredients-product-name').textContent = product.item_description || 'Product';

                        // Set product ID and type for form submission
                        document.getElementById('ingredients_product_id').value = product.product_id;
                        document.getElementById('ingredients_product_type').value = productType;

                        // Clear previous ingredients table
                        const tbody = document.getElementById('ingredients-tbody');
                        tbody.innerHTML = '';

                        // Add ingredient rows
                        if (product.ingredients && Array.isArray(product.ingredients) && product.ingredients.length > 0) {
                            product.ingredients.forEach(ingredient => {
                                if (Array.isArray(ingredient) && ingredient.length >= 2) {
                                     addIngredientRow(ingredient[0], ingredient[1]);
                                } else {
                                    console.warn("Invalid ingredient format:", ingredient);
                                }
                            });
                        } else {
                            // Add an empty row if no ingredients
                            addIngredientRow();
                        }

                        // Display the ingredients modal
                        document.getElementById('ingredientsModal').style.display = 'flex';
                        document.getElementById('ingredientsError').textContent = '';

                    } catch (e) {
                        console.error("Error processing ingredients:", text, e);
                        throw new Error("Server returned invalid response or ingredients not found.");
                    }
                })
                .catch(error => {
                    toastr.error("Error fetching ingredients: " + error.message);
                    console.error("Error fetching ingredients:", error);
                });
        }

        function addIngredientRow(ingredientName = '', quantity = '') {
            const tbody = document.getElementById('ingredients-tbody');
            const row = document.createElement('tr');

            // Create ingredient select dropdown
            let selectHtml = `<select class="ingredient-select" required>
                                <option value="">-- Select --</option>`;

            rawMaterials.forEach(material => {
                const selected = material.name === ingredientName ? 'selected' : '';
                selectHtml += `<option value="${material.name}" ${selected}>${material.name}</option>`;
            });

            selectHtml += '</select>';

            // Add row content
            row.innerHTML = `
                <td>${selectHtml}</td>
                <td><input type="number" class="ingredient-quantity" min="0" step="any" value="${quantity}" required></td>
                <td><button type="button" class="remove-ingredient-btn" onclick="removeIngredientRow(this)"><i class="fas fa-trash"></i></button></td>
            `;

            tbody.appendChild(row);
        }

        function removeIngredientRow(button) {
            const row = button.closest('tr');
            row.remove();
        }

        // Updated saveIngredients function to handle both product types
        function saveIngredients() {
            const productId = document.getElementById('ingredients_product_id').value;
            const productType = document.getElementById('ingredients_product_type').value;
            const rows = document.querySelectorAll('#ingredients-tbody tr');
            const ingredients = [];
            let hasError = false;

            rows.forEach(row => {
                const ingredientSelect = row.querySelector('.ingredient-select');
                const quantityInput = row.querySelector('.ingredient-quantity');

                const ingredientName = ingredientSelect.value;
                const quantity = parseFloat(quantityInput.value);

                if (ingredientName && !isNaN(quantity) && quantity >= 0) {
                    ingredients.push([ingredientName, quantity]);
                } else if (ingredientName || quantityInput.value.trim() !== '') {
                    // Only show error if either field is filled but invalid
                    toastr.warning(`Invalid data in row: Ingredient "${ingredientName}", Quantity "${quantityInput.value}"`);
                    hasError = true;
                }
            });

            if (hasError) {
                 document.getElementById('ingredientsError').textContent = 'Please correct invalid ingredient rows before saving.';
                 return;
            }
            if (ingredients.length === 0 && rows.length > 0) {
                 document.getElementById('ingredientsError').textContent = 'Please add at least one valid ingredient or remove empty rows.';
                 return;
            }

            document.getElementById('ingredientsError').textContent = ''; // Clear previous error messages

            fetch("../../backend/update_ingredients.php", { // Adjusted path
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    product_id: productId,
                    product_type: productType,
                    ingredients: ingredients
                })
            })
            .then(response => response.text()) // Get raw text first
            .then(text => {
                try {
                    const data = JSON.parse(text); // Try to parse as JSON
                    if (data.success) {
                        toastr.success(data.message);
                        closeIngredientsModal();
                    } else {
                        document.getElementById('ingredientsError').textContent = data.message || 'An unknown error occurred';
                    }
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    throw new Error("Server returned invalid response: " + text.substring(0, 100) + "...");
                }
            })
            .catch(error => {
                toastr.error("Error updating ingredients: " + error.message);
                document.getElementById('ingredientsError').textContent = error.message;
                console.error("Error updating ingredients:", error);
            });
        }

        // Updated updateStock function to handle both product types
        function updateStock(productId, action, productType) {
            const amountInput = document.getElementById(`adjust-${productId}`);
            const amount = parseInt(amountInput.value);

            if (isNaN(amount) || amount <= 0) {
                toastr.warning("Please enter a valid positive amount to adjust.");
                return;
            }

            fetch("../../backend/update_stock.php", { // Adjusted path
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    product_id: productId,
                    action: action,
                    amount: amount,
                    product_type: productType
                })
            })
            .then(response => response.text()) // Get raw text first
            .then(text => {
                try {
                    const data = JSON.parse(text); // Try to parse as JSON
                    if (data.success) {
                         toastr.success(data.message);
                         // Update stock directly in the table
                         const stockCell = document.getElementById(`stock-${productId}`);
                         if (stockCell) {
                             stockCell.textContent = data.new_stock;
                         }
                         // Reset input field
                         amountInput.value = '1';
                    } else {
                         toastr.error(data.message || "Error updating stock");
                    }
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    throw new Error("Server returned invalid response: " + text.substring(0, 100) + "...");
                }
            })
            .catch(error => {
                toastr.error("Error updating stock: " + error.message);
                console.error("Error updating stock:", error);
            });
        }

        function filterByCategory() {
            const filterValue = document.getElementById('category-filter').value;
            const rows = document.querySelectorAll('#inventory-table tr');

            rows.forEach(row => {
                // Check if the row has the data-category attribute
                const categoryAttribute = row.getAttribute('data-category');
                if (categoryAttribute !== null) {
                     if (filterValue === 'all' || categoryAttribute === filterValue) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    // Handle rows without the attribute if necessary, maybe always show or hide
                    // row.style.display = 'none'; // Example: hide rows without category data
                }
            });
        }

        function openModal(imgElement) {
            var modal = document.getElementById("myModal");
            var modalImg = document.getElementById("img01");
            var captionText = document.getElementById("caption");
            modal.style.display = "block";
            modalImg.src = imgElement.src;
            captionText.innerHTML = imgElement.alt;
        }

        function closeModal() {
            var modal = document.getElementById("myModal");
            modal.style.display = "none";
        }

        // Close modals on overlay click
        window.addEventListener('click', function(e) {
            const addProductModal = document.getElementById('addProductModal');
            const editProductModal = document.getElementById('editProductModal');
            const ingredientsModal = document.getElementById('ingredientsModal');
            const imgModal = document.getElementById('myModal');

            if (e.target === addProductModal) {
                closeAddProductModal();
            }
            if (e.target === editProductModal) {
                closeEditProductModal();
            }
            if (e.target === ingredientsModal) {
                closeIngredientsModal();
            }
            if (e.target === imgModal) {
                closeModal();
            }
        });
    </script>
</body>
</html>