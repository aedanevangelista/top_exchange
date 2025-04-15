<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Pending Orders'); // Ensure the user has access to the Pending Orders page

// Fetch active clients for the dropdown
$clients = [];
$clients_with_company_address = []; // Array to store clients with their company addresses
$stmt = $conn->prepare("SELECT username, company_address FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$stmt->bind_result($username, $company_address);
while ($stmt->fetch()) {
    $clients[] = $username;
    $clients_with_company_address[$username] = $company_address;
}
$stmt->close();

// Fetch only pending orders for display in the table
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status FROM orders WHERE status = 'Pending'";

// Order by delivery_date ascending
$sql .= " ORDER BY delivery_date ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
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
    <title>Pending Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        /* Additional CSS for the material shortage table */
        .material-shortage-table-container {
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        .material-shortage-table {
            width: 100%;
            border-collapse: collapse;
        }
        .material-shortage-table th, .material-shortage-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .material-shortage-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .shortage {
            color: #ff0000;
            font-weight: bold;
        }
        
        /* CSS for material requirements display */
        .material-requirements-container {
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        .material-requirements-table {
            width: 100%;
            border-collapse: collapse;
        }
        .material-requirements-table th, .material-requirements-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .material-requirements-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .material-available {
            color: #00aa00;
            font-weight: bold;
        }
        .material-unavailable {
            color: #ff0000;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Pending Orders Management</h1>
            <!-- Removed filter section since we only show pending orders -->
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
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <th>Raw Materials</th>
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
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td><button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>')">
                                <i class="fas fa-clipboard-list"></i>    
                                View Orders</button></td>
                                <td><button class="view-materials-btn" onclick="showMaterialRequirements('<?= htmlspecialchars($order['orders']) ?>')">
                                <i class="fas fa-cubes"></i>    
                                View Materials</button></td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <span class="status-badge status-pending"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-orders">No pending orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Overlay Form for Adding New Order -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form" action="/backend/add_order.php">
                <div class="left-section">
                    <label for="username">Username:</label>
                    <select id="username" name="username" required onchange="generatePONumber()">
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client) ?>" data-company-address="<?= htmlspecialchars($clients_with_company_address[$client] ?? '') ?>"><?= htmlspecialchars($client) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="order_date">Order Date:</label>
                    <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label>
                    <input type="text" id="delivery_date" name="delivery_date" autocomplete="off" required>
                    
                    <!-- New Delivery Address selection -->
                    <label for="delivery_address_type">Delivery Address:</label>
                    <select id="delivery_address_type" name="delivery_address_type" onchange="toggleDeliveryAddress()">
                        <option value="company">Company Address</option>
                        <option value="custom">Custom Address</option>
                    </select>
                    
                    <div id="company_address_container">
                        <input type="text" id="company_address" name="company_address" readonly placeholder="Company address will appear here">
                    </div>
                    
                    <div id="custom_address_container" style="display: none;">
                        <textarea id="custom_address" name="custom_address" rows="3" placeholder="Enter delivery address"></textarea>
                    </div>
                    
                    <input type="hidden" name="delivery_address" id="delivery_address">
                    
                    <div class="centered-button">
                        <button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()">
                            <i class="fas fa-box-open"></i> Select Products
                        </button>
                    </div>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Product</th>
                                    <th>Packaging</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="summaryBody">
                                <!-- Summary will be populated here -->
                            </tbody>
                        </table>
                        <div class="summary-total">
                            Total: <span class="summary-total-amount">PHP 0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders">
                    <input type="hidden" name="total_amount" id="total_amount">
                </div>
                <div class="form-buttons">

                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="save-btn" onclick="prepareOrderData()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Overlay for Selecting Products -->
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <div class="overlay-header">
                <h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2>
                <button class="cart-btn" onclick="window.openCartModal()">
                    <i class="fas fa-shopping-cart"></i> View Cart
                </button>
            </div>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="inventory">
                        <!-- Inventory list will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="cancel-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="done-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-check"></i> Done
                </button>
            </div>
        </div>
    </div>

    <!-- Cart Modal for Viewing Selected Products -->
    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
            <div class="cart-table-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="cart">
                        <!-- Selected products list will be populated here -->
                    </tbody>
                </table>
                <div class="no-products" style="display: none;">No products in the cart.</div>
            </div>
            <div class="cart-total" style="text-align: right; margin-bottom: 20px;">
                Total: <span class="total-amount">PHP 0.00</span>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="back-btn" onclick="closeCartModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="save-cart-btn" onclick="saveCartChanges()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modified Status Modal - Removed Pending and Complete buttons -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="status-buttons">
                <button onclick="changeStatus('Active')" class="modal-status-btn active">
                    <i class="fas fa-check"></i> Active
                </button>
                <button onclick="changeStatus('Rejected')" class="modal-status-btn reject">
                    <i class="fas fa-ban"></i> Reject
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closeStatusModal()" class="modal-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details</h2>
            <div class="order-details-container">
                <table class="order-details-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>

    <script src="/js/orders.js"></script>
</body>
</html>