<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory'); // Ensure the user has access to the Inventory page

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/top_exchange/public/login.php");
    exit();
}

// Handle image upload for products
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['formType']) && $_POST['formType'] == 'edit_product') {
    header('Content-Type: application/json');
    
    $product_id = $_POST['product_id'];
    $item_description = $_POST['item_description'];
    $additional_description = $_POST['additional_description'];
    $product_image = '';
    
    // Get the existing item description to determine the folder name
    $stmt = $conn->prepare("SELECT item_description FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_item_data = $result->fetch_assoc();
    $old_item_description = $old_item_data['item_description'] ?? '';
    $stmt->close();
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Process image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024; // 20MB
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            // Create folder based on item description (for grouping same products)
            $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
            $item_dir = $upload_dir . $item_folder . '/';
            
            if (!file_exists($item_dir)) {
                mkdir($item_dir, 0777, true);
            }
            
            // Remove old image if item description has changed
            if ($old_item_description != $item_description) {
                $old_item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $old_item_description);
                $old_item_dir = $upload_dir . $old_item_folder . '/';
                
                // Delete all files in old directory
                if (file_exists($old_item_dir)) {
                    $old_files = array_diff(scandir($old_item_dir), array('.', '..'));
                    foreach ($old_files as $file) {
                        @unlink($old_item_dir . $file);
                    }
                    
                    // Try to remove directory if empty
                    if (count(array_diff(scandir($old_item_dir), array('.', '..'))) == 0) {
                        @rmdir($old_item_dir);
                    }
                }
            }
            
            // Save the new image
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_image.' . $file_extension;
            $product_image_path = '/top_exchange/uploads/products/' . $item_folder . '/' . $filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $item_dir . $filename)) {
                // Update the database
                $stmt = $conn->prepare("UPDATE products SET additional_description = ?, product_image = ? WHERE product_id = ?");
                $stmt->bind_param("ssi", $additional_description, $product_image_path, $product_id);
                
                if ($stmt->execute()) {
                    // Update all products with the same name to use the same image
                    $stmt = $conn->prepare("UPDATE products SET product_image = ? WHERE item_description = ? AND product_id != ?");
                    $stmt->bind_param("ssi", $product_image_path, $item_description, $product_id);
                    $stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating product']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
        }
    } else {
        // Just update the additional description without changing the image
        $stmt = $conn->prepare("UPDATE products SET additional_description = ? WHERE product_id = ?");
        $stmt->bind_param("si", $additional_description, $product_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product description updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating product description']);
        }
        $stmt->close();
    }
    
    exit;
}

// Fetch inventory data from `products`
$sql = "SELECT DISTINCT category FROM products ORDER BY category";
$categories = $conn->query($sql);

$sql = "SELECT product_id, category, item_description, packaging, price, stock_quantity, additional_description, product_image FROM products ORDER BY category, item_description";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* Modal Styles */
        #myModal {
            display: none;
            position: fixed;
            z-index: 9999; /* High z-index to ensure it's on top of everything */
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

        /* Product image thumbnail */
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .product-img:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        /* Add placeholder for products without images */
        .no-image {
            width: 50px;
            height: 50px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #666;
            font-size: 12px;
        }

        /* Form style for image upload */
        .file-info {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }

        /* Additional Description styling */
        .additional-desc {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="inventory-header">
            <h1>Inventory Management</h1>
            <div class="search-filter-container">
                <!-- Search bar -->
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="Search products..." onkeyup="searchProducts()">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
                <!-- Filter dropdown -->
                <div class="filter-section">
                    <label for="category-filter">Filter by Category:</label>
                    <select id="category-filter" onchange="filterByCategory()">
                        <option value="all">All</option>
                        <?php
                        if ($categories->num_rows > 0) {
                            while ($row = $categories->fetch_assoc()) {
                                echo "<option value='{$row['category']}'>{$row['category']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <!-- Add New Product button -->
            <button onclick="openAddProductForm()" class="add-product-btn">
                <i class="fas fa-plus-circle"></i> Add New Product
            </button>
        </div>

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Item Description</th>
                        <th>Packaging</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Image</th>
                        <th>Additional Description</th>
                        <th>Adjust Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventory-table">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Store additional data attributes for search
                            $data_attributes = "data-category='{$row['category']}' 
                                               data-product-id='{$row['product_id']}' 
                                               data-item-description='{$row['item_description']}'
                                               data-packaging='{$row['packaging']}'
                                               data-additional-description='" . htmlspecialchars($row['additional_description'] ?? '') . "'";
                            
                            echo "<tr $data_attributes>
                                    <td>{$row['category']}</td>
                                    <td>{$row['item_description']}</td>
                                    <td>{$row['packaging']}</td>
                                    <td>₱" . number_format($row['price'], 2) . "</td>
                                    <td id='stock-{$row['product_id']}'>{$row['stock_quantity']}</td>
                                    <td class='product-image-cell'>";
                            
                            // Display product image or placeholder
                            if (!empty($row['product_image'])) {
                                echo "<img src='" . htmlspecialchars($row['product_image']) . "' alt='Product Image' class='product-img' onclick='openModal(this)'>";
                            } else {
                                echo "<div class='no-image'>No image</div>";
                            }
                            
                            echo "</td>
                                    <td class='additional-desc'>" . htmlspecialchars($row['additional_description'] ?? '') . "</td>
                                    <td class='adjust-stock'>
                                        <button class='add-btn' onclick='updateStock({$row['product_id']}, \"add\")'>Add</button>
                                        <input type='number' id='adjust-{$row['product_id']}' min='1' value='1'>
                                        <button class='remove-btn' onclick='updateStock({$row['product_id']}, \"remove\")'>Remove</button>
                                    </td>
                                    <td class='action-buttons'>
                                        <button class='edit-btn' onclick='editStock({$row['product_id']})'>Edit Stock</button>
                                        <button class='edit-btn' onclick='editProduct({$row['product_id']}, \"{$row['item_description']}\", \"" . htmlspecialchars($row['additional_description'] ?? '', ENT_QUOTES) . "\", \"" . htmlspecialchars($row['product_image'] ?? '', ENT_QUOTES) . "\")'>
                                            <i class='fas fa-edit'></i> Edit Details
                                        </button>
                                    </td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Edit Stock Modal -->
        <div id="editStockModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2>Edit Stock Quantity</h2>
                <form id="edit-stock-form">
                    <input type="hidden" id="edit_product_id" name="product_id">
                    <label for="edit_stock_quantity">Stock Quantity:</label>
                    <input type="number" id="edit_stock_quantity" name="stock_quantity" required>
                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add New Product Modal -->
        <div id="addProductModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <div id="addProductError" class="error-message"></div>
                <form id="add-product-form" method="POST">
                    <label for="category">Category:</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        // Reset the categories result pointer
                        $categories->data_seek(0);
                        if ($categories->num_rows > 0) {
                            while ($row = $categories->fetch_assoc()) {
                                echo "<option value='{$row['category']}'>{$row['category']}</option>";
                            }
                        }
                        ?>
                        <option value="new">+ Add New Category</option>
                    </select>
                    
                    <!-- New category input (initially hidden) -->
                    <div id="new-category-container" style="display: none;">
                        <label for="new_category">New Category Name:</label>
                        <input type="text" id="new_category" name="new_category" placeholder="Enter new category name">
                    </div>
                    
                    <label for="item_description">Item Description (Name):</label>
                    <input type="text" id="item_description" name="item_description" required placeholder="Enter product name/description">
                    
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
                <h2><i class="fas fa-edit"></i> Edit Product Details</h2>
                <div id="editProductError" class="error-message"></div>
                <form id="edit-product-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="formType" value="edit_product">
                    <input type="hidden" id="edit_product_id" name="product_id">
                    <input type="hidden" id="edit_item_description" name="item_description">
                    
                    <label for="edit_additional_description">Additional Description:</label>
                    <textarea id="edit_additional_description" name="additional_description" placeholder="Add more details about the product"></textarea>
                    
                    <div id="current-image-container">
                        <!-- This will be populated with the current image if it exists -->
                    </div>
                    
                    <label for="product_image">Product Image: <span class="file-info">(Max: 20MB, JPG/PNG only)</span></label>
                    <input type="file" id="edit_product_image" name="product_image" accept="image/jpeg, image/png">
                    
                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeEditProductModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- The Image Modal -->
        <div id="myModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="img01">
            <div id="caption"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        toastr.options = {
            "positionClass": "toast-bottom-right",
            "opacity": 1
        };

        // Search products functionality
        function searchProducts() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#inventory-table tr');

            rows.forEach(row => {
                const itemDescription = (row.getAttribute('data-item-description') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                const packaging = (row.getAttribute('data-packaging') || '').toLowerCase();
                const additionalDescription = (row.getAttribute('data-additional-description') || '').toLowerCase();
                
                if (itemDescription.includes(searchValue) || 
                    category.includes(searchValue) || 
                    packaging.includes(searchValue) ||
                    additionalDescription.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Show/hide new category input based on selection
        document.getElementById('category').addEventListener('change', function() {
            if (this.value === 'new') {
                document.getElementById('new-category-container').style.display = 'block';
            } else {
                document.getElementById('new-category-container').style.display = 'none';
            }
        });

        // Add Product Form Submission
        document.getElementById('add-product-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const categorySelect = document.getElementById('category');
            let category = categorySelect.value;
            
            if (category === 'new') {
                category = document.getElementById('new_category').value;
                if (!category.trim()) {
                    document.getElementById('addProductError').textContent = 'Please enter a new category name.';
                    return;
                }
            }
            
            const item_description = document.getElementById('item_description').value;
            const packaging = document.getElementById('packaging').value;
            const price = document.getElementById('price').value;
            const additional_description = document.getElementById('additional_description').value;
            
            // Create form data for file upload
            const formData = new FormData();
            formData.append('category', category);
            formData.append('item_description', item_description);
            formData.append('packaging', packaging);
            formData.append('price', price);
            formData.append('additional_description', additional_description);
            formData.append('stock_quantity', 0); // Starting with 0 stock
            
            // Add image file if selected
            const product_image = document.getElementById('product_image').files[0];
            if (product_image) {
                formData.append('product_image', product_image);
            }
            
            // Submit form data
            fetch("../../backend/add_product.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeAddProductModal();
                    fetchInventory(); // Refresh inventory table
                } else {
                    document.getElementById('addProductError').textContent = data.message;
                }
            })
            .catch(error => {
                toastr.error("Error adding product", { timeOut: 3000, closeButton: true });
                console.error("Error:", error);
            });
        });

        // Edit Product Form Submission
        document.getElementById('edit-product-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Create form data for file upload
            const formData = new FormData(this);
            
            // Submit form data
            fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeEditProductModal();
                    // Refresh the page to show updated data
                    window.location.reload();
                } else {
                    document.getElementById('editProductError').textContent = data.message;
                }
            })
            .catch(error => {
                toastr.error("Error updating product", { timeOut: 3000, closeButton: true });
                console.error("Error:", error);
            });
        });

        function openAddProductForm() {
            document.getElementById('addProductModal').style.display = 'flex';
            document.getElementById('add-product-form').reset();
            document.getElementById('addProductError').textContent = '';
            document.getElementById('new-category-container').style.display = 'none';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
        }

        function closeEditModal() {
            document.getElementById('editStockModal').style.display = 'none';
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').style.display = 'none';
        }

        function editProduct(productId, itemDescription, additionalDescription, productImage) {
            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_item_description').value = itemDescription;
            document.getElementById('edit_additional_description').value = additionalDescription || '';
            
            // Clear previous image
            document.getElementById('current-image-container').innerHTML = '';
            
            // Show current image if it exists
            if (productImage) {
                const imgContainer = document.getElementById('current-image-container');
                imgContainer.innerHTML = `
                    <p>Current Image:</p>
                    <img src="${productImage}" alt="Current product image" style="max-width: 200px; max-height: 200px; margin-bottom: 10px;">
                `;
            }
            
            document.getElementById('editProductModal').style.display = 'flex';
            document.getElementById('editProductError').textContent = '';
        }

        function filterByCategory() {
            const filterValue = document.getElementById('category-filter').value;
            const rows = document.querySelectorAll('#inventory-table tr');

            rows.forEach(row => {
                if (filterValue === 'all' || row.getAttribute('data-category') === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Handle image modal
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

        // Handle clicks outside modals to close them
        window.addEventListener('click', function(e) {
            const addProductModal = document.getElementById('addProductModal');
            const editStockModal = document.getElementById('editStockModal');
            const editProductModal = document.getElementById('editProductModal');
            const imgModal = document.getElementById('myModal');
            
            if (e.target === addProductModal) {
                closeAddProductModal();
            }
            
            if (e.target === editStockModal) {
                closeEditModal();
            }
            
            if (e.target === editProductModal) {
                closeEditProductModal();
            }
            
            if (e.target === imgModal) {
                closeModal();
            }
        });
    </script>
    <script src="../js/inventory.js"></script>
</body>
</html>