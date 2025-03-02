<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole(['admin', 'secretary']); // Only admins and secretaries can access

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

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
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
                            echo "<tr data-category='{$row['category']}'>
                                    <td>{$row['product_id']}</td>
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
                        echo "<tr><td colspan='8'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Edit Stock Modal -->
        <div id="editStockModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <form id="edit-stock-form">
                    <input type="hidden" id="edit_product_id" name="product_id">
                    <label for="edit_stock_quantity">Stock Quantity:</label>
                    <input type="number" id="edit_stock_quantity" name="stock_quantity" required>
                    <div class="form-buttons">
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                        <button type="button" class="cancel-btn" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="../js/inventory.js"></script>
    <script>
        toastr.options = {
            "positionClass": "toast-bottom-right",
            "opacity": 1
        };
    </script>
</body>
</html>