<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

// Fetch active clients for the dropdown
$clients = [];
$stmt = $conn->prepare("SELECT username FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$stmt->bind_result($username);
while ($stmt->fetch()) {
    $clients[] = $username;
}
$stmt->close();

// Fetch orders for display in the table
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, orders, total_amount, status FROM orders WHERE status != 'Completed'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management</title>
    <link rel="stylesheet" href="/top_exchange/public/css/orders.css">
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Orders Management</h1>
            <button onclick="openAddOrderForm()" class="add-order-btn">
                <i class="fas fa-plus"></i> Add New Order
            </button>
        </div>
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Username</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Orders</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td><?= htmlspecialchars($order['orders']) ?></td>
                                <td><?= htmlspecialchars($order['total_amount']) ?></td>
                                <td><?= htmlspecialchars($order['status']) ?></td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditOrderForm('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="delete-btn" onclick="openDeleteModal('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-orders">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overlay Form for Adding New Order -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form" action="">
                <div class="left-section">
                    <label for="username">Username:</label>
                    <select id="username" name="username" required>
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client) ?>"><?= htmlspecialchars($client) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="order_date">Order Date:</label>
                    <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label>
                    <input type="text" id="delivery_date" name="delivery_date" required>
                    <button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()">
                        <i class="fas fa-box-open"></i> Select Products
                    </button>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Overlay for Selecting Products -->
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Select Products</h2>
            <input type="text" id="inventorySearch" placeholder="Search products...">
            <select id="inventoryFilter">
                <option value="all">All Categories</option>
                <!-- Populate with categories dynamically -->
            </select>
            <div class="inventory-table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="inventory">
                        <!-- Inventory list will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons">
                <button type="button" class="done-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-check"></i> Done
                </button>
                <button type="button" class="cancel-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Overlay Modal for Delete Confirmation -->
    <div id="deleteModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
            <p id="deleteMessage"></p>
            <div class="modal-buttons">
                <button class="confirm-btn" onclick="confirmDeletion()">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="cancel-btn" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="/top_exchange/public/js/orders.js"></script>
</body>
</html>