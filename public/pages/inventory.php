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

// Fetch inventory data from `products`
$sql = "SELECT DISTINCT category FROM products ORDER BY category";
$categories = $conn->query($sql);

$sql = "SELECT product_id, category, item_description, packaging, price, stock_quantity FROM products ORDER BY category, item_description";
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
                        <!-- Product ID column removed -->
                        <th>Category</th>
                        <th>Item Description</th>
                        <th>Packaging</th>
                        <th>Price</th>
                        <th>Stock Level</th>
                        <th>Adjust Stock</th>
                        <th>Edit Stock</th>
                    </tr>
                </thead>
                <tbody id="inventory-table">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr data-category='{$row['category']}' data-product-id='{$row['product_id']}' data-item-description='{$row['item_description']}' data-packaging='{$row['packaging']}'>
                                    <!-- Product ID column removed -->
                                    <td>{$row['category']}</td>
                                    <td>{$row['item_description']}</td>
                                    <td>{$row['packaging']}</td>
                                    <td>₱" . number_format($row['price'], 2) . "</td>
                                    <td id='stock-{$row['product_id']}'>{$row['stock_quantity']}</td>
                                    <td class='adjust-stock'>
                                        <button class='add-btn' onclick='updateStock({$row['product_id']}, \"add\")'>Add</button>
                                        <input type='number' id='adjust-{$row['product_id']}' min='1' value='1'>
                                        <button class='remove-btn' onclick='updateStock({$row['product_id']}, \"remove\")'>Remove</button>
                                    </td>
                                    <td class='edit-stock'>
                                        <button class='edit-btn' onclick='editStock({$row['product_id']})'>Edit</button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No products found.</td></tr>";
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
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                        <button type="button" class="cancel-btn" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
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
                    
                    <div class="form-buttons">
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                        <button type="button" class="cancel-btn" onclick="closeAddProductModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
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
                
                if (itemDescription.includes(searchValue) || 
                    category.includes(searchValue) || 
                    packaging.includes(searchValue)) {
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
            
            // Changed path to point to the existing backend file
            fetch("../../backend/add_product.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ 
                    category: category,
                    item_description: item_description,
                    packaging: packaging,
                    price: price,
                    stock_quantity: 0 // Starting with 0 stock
                })
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

        // Handle clicks outside modals to close them
        window.addEventListener('click', function(e) {
            const addProductModal = document.getElementById('addProductModal');
            const editStockModal = document.getElementById('editStockModal');
            
            if (e.target === addProductModal) {
                closeAddProductModal();
            }
            
            if (e.target === editStockModal) {
                closeEditModal();
            }
        });
    </script>
    <script src="../js/inventory.js"></script>
</body>
</html>