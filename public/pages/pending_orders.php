<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Pending Orders'); // Ensure the user has access to the Pending Orders page

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'company', 'order_date', 'delivery_date', 'total_amount'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'order_date'; // Default sort column
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC'; // Default to descending
}

// Fetch active clients for the dropdown
$clients = [];
$clients_with_company_address = []; // Array to store clients with their company addresses
$clients_with_company = []; // Array to store clients with their company names
$stmt = $conn->prepare("SELECT username, company_address, company FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$stmt->bind_result($username, $company_address, $company);
while ($stmt->fetch()) {
    $clients[] = $username;
    $clients_with_company_address[$username] = $company_address;
    $clients_with_company[$username] = $company;
}
$stmt->close();

// Fetch only pending orders for display in the table with sorting
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, company, order_date, delivery_date, delivery_address, orders, total_amount, status FROM orders WHERE status = 'Pending'";

// Add sorting
$sql .= " ORDER BY {$sort_column} {$sort_direction}";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Helper function to generate sort URL
function getSortUrl($column, $currentColumn, $currentDirection) {
    $newDirection = ($column === $currentColumn && $currentDirection === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=" . urlencode($column) . "&direction=" . urlencode($newDirection);
}

// Helper function to display sort icon
function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column !== $currentColumn) {
        return '<i class="fas fa-sort"></i>';
    } else if ($currentDirection === 'ASC') {
        return '<i class="fas fa-sort-up"></i>';
    } else {
        return '<i class="fas fa-sort-down"></i>';
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
    <!-- Include jsPDF for printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        /* Main styles for the Order Summary table */
        .order-summary {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        /* Make the table properly aligned */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        /* Apply proper scrolling to tbody only */
        .summary-table tbody {
            display: block;
            max-height: 250px;
            overflow-y: auto;
        }
        
        /* Make table header and rows consistent */
        .summary-table thead, 
        .summary-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        /* Account for scrollbar width in header */
        .summary-table thead {
            width: calc(100% - 17px);
        }
        
        /* Cell styling for proper alignment and text overflow */
        .summary-table th,
        .summary-table td {
            padding: 8px;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border: 1px solid #ddd;
        }
        
        /* Specify consistent column widths */
        .summary-table th:nth-child(1),
        .summary-table td:nth-child(1) {
            width: 18%;
        }
        
        .summary-table th:nth-child(2),
        .summary-table td:nth-child(2) {
            width: 26%;
        }
        
        .summary-table th:nth-child(3),
        .summary-table td:nth-child(3) {
            width: 18%;
        }
        
        .summary-table th:nth-child(4),
        .summary-table td:nth-child(4) {
            width: 18%;
        }
        
        .summary-table th:nth-child(5),
        .summary-table td:nth-child(5) {
            width: 20%;
        }
        
        /* Additional styles for print button */
        .print-po-btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 80px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 5px;
        }
        
        .print-po-btn:hover {
            background-color: #45a049;
        }
        
        /* Search Container Styling (exactly as in order_history.php) */
        .search-container {
            display: flex;
            align-items: center;
        }

        .search-container input {
            padding: 8px 12px;
            border-radius: 20px 0 0 20px;
            border: 1px solid #ddd;
            font-size: 14px;
            width: 220px;
        }

        .search-container .search-btn {
            background-color: #2980b9;
            color: white;
            border: none;
            border-radius: 0 20px 20px 0;
            padding: 8px 12px;
            cursor: pointer;
        }

        .search-container .search-btn:hover {
            background-color: #2471a3;
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        /* Materials table styling */
        .raw-materials-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .raw-materials-container h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .materials-table th,
        .materials-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .materials-table th {
            background-color: #f2f2f2;
        }
        
        .material-sufficient {
            color: #28a745;
        }
        
        .material-insufficient {
            color: #dc3545;
        }
        
        .materials-status {
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-sufficient {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-insufficient {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Status modal buttons */
        .status-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .modal-status-btn {
            padding: 10px 20px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .modal-status-btn.active {
            background-color: #ffc107;
            color: white;
        }

        .modal-status-btn.active:hover {
            background-color:rgb(202, 154, 10);
            color: white;
        }
        
        .modal-status-btn.reject {
            background-color: #dc3545;
            color: white;
        }

        .modal-status-btn.reject:hover {
            background-color:rgb(138, 23, 35);
            color: white;
        }
        
        .modal-status-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .modal-footer {
            margin-top: 15px;
            text-align: right;
        }
        
        .modal-cancel-btn {
            padding: 8px 15px;
            border-radius: 40px;
            border: 1px solid #ddd;
            background-color:rgb(43, 43, 43);
            cursor: pointer;
            font-weight: bold;
        }
        
        .modal-cancel-btn:hover {
            background-color:rgb(61, 61, 61);
        }
        
        /* Sortable table headers */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px; /* Space for the icon */
            user-select: none;
        }

        th.sortable a {
            color: inherit;
            text-decoration: none;
        }

        th.sortable .fas {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }

        th.sortable:hover {
            background-color:rgb(51, 51, 51);
        }

        th.sortable .fa-sort-up,
        th.sortable .fa-sort-down {
            color:rgb(255, 255, 255);
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Pending Orders</h1>
            <!-- Added search box matching order_history.php -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
            <button onclick="openAddOrderForm()" class="add-order-btn">
                <i class="fas fa-plus"></i> Add New Order
            </button>
        </div>
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th class="sortable">
                            <a href="<?= getSortUrl('po_number', $sort_column, $sort_direction) ?>">
                                PO Number <?= getSortIcon('po_number', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('username', $sort_column, $sort_direction) ?>">
                                Username <?= getSortIcon('username', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('company', $sort_column, $sort_direction) ?>">
                                Company Name <?= getSortIcon('company', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('order_date', $sort_column, $sort_direction) ?>">
                                Order Date <?= getSortIcon('order_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">
                                Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">
                                Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
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
                                <td><?= htmlspecialchars($order['company'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td><button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars(str_replace("'", "\\'", $order['orders'])) ?>')">
                                <i class="fas fa-clipboard-list"></i>    
                                View Orders</button></td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <span class="status-badge status-pending"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(str_replace("'", "\\'", $order['orders'])) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Status
                                    </button>
                                    <button class="print-po-btn" onclick="printPurchaseOrder('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-print"></i> Print PO
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
                    <select id="username" name="username" required onchange="generatePONumber(); updateCompanyInfo();">
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients as $client): ?>
                            <option 
                                value="<?= htmlspecialchars($client) ?>" 
                                data-company-address="<?= htmlspecialchars($clients_with_company_address[$client] ?? '') ?>"
                                data-company-name="<?= htmlspecialchars($clients_with_company[$client] ?? '') ?>"
                            >
                                <?= htmlspecialchars($client) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="company">Company Name:</label>
                    <input type="text" id="company" name="company" readonly>
                    
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
                    <button type="submit" class="save-btn" onclick="prepareOrderData()"><i class="fas fa-save"></i> Save</button>
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
    
    <!-- Enhanced Status Modal - Including both finished products and raw materials information -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            
            <!-- Enhanced Raw Materials Section -->
            <div id="rawMaterialsContainer" class="raw-materials-container">
                <h3>Loading inventory status...</h3>
                <!-- Content will be populated dynamically -->
            </div>
            
            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="changeStatus('Active')" class="modal-status-btn active">
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
    <script>
    window.openStatusModal = function(poNumber, username, ordersJson) {
        $('#statusMessage').text('Change order status for ' + poNumber);
        $('#statusModal').data('po_number', poNumber).show();
        
        // Clear previous data and show loading state
        $('#rawMaterialsContainer').html('<h3>Loading inventory status...</h3>');
        
        // Parse the orders JSON and check materials
        try {
            $.ajax({
                url: '/backend/check_raw_materials.php',
                type: 'POST',
                data: { 
                    orders: ordersJson,
                    po_number: poNumber
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Display finished products status first
                        if (response.finishedProducts) {
                            displayFinishedProducts(response.finishedProducts);
                        }
                        
                        // If manufacturing is needed, display raw materials
                        if (response.needsManufacturing && response.materials) {
                            displayRawMaterials(response.materials);
                        } else {
                            // Hide the raw materials section if no manufacturing needed
                            $('#rawMaterialsContainer').append('<p>All products are in stock - no manufacturing needed</p>');
                        }
                        
                        // Enable or disable the Active button based on overall status
                        updateOrderActionStatus(response);
                    } else {
                        $('#rawMaterialsContainer').html(`
                            <h3>Error Checking Inventory</h3>
                            <p style="color:red;">${response.message || 'Unknown error'}</p>
                            <p>Order status can still be changed.</p>
                        `);
                        $('#activeStatusBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    $('#rawMaterialsContainer').html(`
                        <h3>Server Error</h3>
                        <p style="color:red;">Could not connect to server: ${error}</p>
                        <p>Order status can still be changed.</p>
                    `);
                    $('#activeStatusBtn').prop('disabled', false);
                    console.error("AJAX Error:", status, error);
                }
            });
        } catch (e) {
            $('#rawMaterialsContainer').html(`
                <h3>Error Processing Data</h3>
                <p style="color:red;">${e.message}</p>
                <p>Order status can still be changed.</p>
            `);
            $('#activeStatusBtn').prop('disabled', false);
            console.error("Error:", e);
        }
    };

    // Helper function to format weight values
    function formatWeight(weightInGrams) {
        if (weightInGrams >= 1000) {
            return (weightInGrams / 1000).toFixed(2) + ' kg';
        } else {
            return weightInGrams.toFixed(2) + ' g';
        }
    }

    // Function to display finished products status
    function displayFinishedProducts(productsData) {
        const productsTableHTML = `
            <h3>Finished Products Status</h3>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>In Stock</th>
                        <th>Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.keys(productsData).map(product => {
                        const data = productsData[product];
                        const available = parseInt(data.available);
                        const required = parseInt(data.required);
                        const isSufficient = data.sufficient;
                        
                        return `
                            <tr>
                                <td>${product}</td>
                                <td>${available}</td>
                                <td>${required}</td>
                                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                    ${isSufficient ? 'In Stock' : 'Need to manufacture ' + data.shortfall + ' more'}
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        // Update the HTML container
        $('#rawMaterialsContainer').html(productsTableHTML);
        
        // Check if any products need manufacturing
        const needsManufacturing = Object.values(productsData).some(product => !product.sufficient);
        
        if (needsManufacturing) {
            $('#rawMaterialsContainer').append(`
                <h3>Raw Materials Required for Manufacturing</h3>
                <div id="raw-materials-section">
                    <p>Loading raw materials information...</p>
                </div>
            `);
        }
    }

    // Function to display raw materials data
    function displayRawMaterials(materialsData) {
        if (!materialsData || Object.keys(materialsData).length === 0) {
            $('#raw-materials-section').html('<p>No raw materials information available.</p>');
            return;
        }
        
        // Count sufficient vs insufficient materials
        let allSufficient = true;
        let insufficientMaterials = [];
        
        const materialsTableHTML = `
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Available</th>
                        <th>Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.keys(materialsData).map(material => {
                        const data = materialsData[material];
                        const available = parseFloat(data.available);
                        const required = parseFloat(data.required);
                        const isSufficient = data.sufficient;
                        
                        if (!isSufficient) {
                            allSufficient = false;
                            insufficientMaterials.push(material);
                        }
                        
                        return `
                            <tr>
                                <td>${material}</td>
                                <td>${formatWeight(available)}</td>
                                <td>${formatWeight(required)}</td>
                                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                    ${isSufficient ? 'Sufficient' : 'Insufficient'}
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        // Add status message
        const statusMessage = allSufficient 
            ? 'All raw materials are sufficient for manufacturing.' 
            : `Insufficient raw materials: ${insufficientMaterials.join(', ')}. The order cannot proceed.`;
        
        const statusClass = allSufficient ? 'status-sufficient' : 'status-insufficient';
        
        const fullHTML = `
            ${materialsTableHTML}
            <p class="materials-status ${statusClass}">${statusMessage}</p>
        `;
        
        $('#raw-materials-section').html(fullHTML);
        
        // Enable or disable the Active button
        $('#activeStatusBtn').prop('disabled', !allSufficient);
        
        return allSufficient;
    }

    // Function to update order action status
    function updateOrderActionStatus(response) {
        let canProceed = true;
        let statusMessage = 'All inventory requirements are met. The order can proceed.';
        
        // Check if all finished products are in stock
        const finishedProducts = response.finishedProducts || {};
        const allProductsInStock = Object.values(finishedProducts).every(product => product.sufficient);
        
        // If manufacturing is needed, check raw materials
        if (!allProductsInStock && response.needsManufacturing) {
            // Check if all products can be manufactured
            const canManufactureAll = Object.values(finishedProducts).every(product => 
                product.sufficient || product.canManufacture !== false);
                
            if (!canManufactureAll) {
                canProceed = false;
                statusMessage = 'Some products cannot be manufactured due to missing ingredients.';
            } else {
                // Check if all raw materials are sufficient
                const materials = response.materials || {};
                const allMaterialsSufficient = Object.values(materials).every(material => material.sufficient);
                
                if (!allMaterialsSufficient) {
                    canProceed = false;
                    statusMessage = 'Insufficient raw materials for manufacturing required products.';
                } else {
                    statusMessage = 'Products will be manufactured using raw materials. The order can proceed.';
                }
            }
        }
        
        // Update UI based on status
        $('#activeStatusBtn').prop('disabled', !canProceed);
        
        // Add a summary at the end of the container
        const statusClass = canProceed ? 'status-sufficient' : 'status-insufficient';
        $('#rawMaterialsContainer').append(`
            <p class="materials-status ${statusClass}">${statusMessage}</p>
        `);
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    // Function to change order status
    function changeStatus(status) {
        var poNumber = $('#statusModal').data('po_number');
        
        // Only deduct materials if changing to Active
        const deductMaterials = (status === 'Active');
        
        $.ajax({
            type: 'POST',
            url: '/backend/update_order_status.php',
            data: { 
                po_number: poNumber, 
                status: status,
                deduct_materials: deductMaterials
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Format status type for toast
                    let toastType = status.toLowerCase();
                    if (toastType === 'completed') toastType = 'complete';
                    if (toastType === 'rejected') toastType = 'reject';
                    
                    // Create message
                    let message = `Changed status for ${poNumber} to ${status}.`;
                    if (status === 'Active' && deductMaterials) {
                        message = `Changed status for ${poNumber} to ${status}. Inventory has been updated.`;
                    }
                    
                    // Show toast and reload
                    showToast(message, toastType);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Failed to change status: ' + (response.error || 'Unknown error'));
                }
                closeStatusModal();
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Failed to change status. Please try again.');
                closeStatusModal();
            }
        });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                <div class="message">${message}</div>
            </div>
            <i class="fas fa-times close" onclick="this.parentElement.remove()"></i>
        `;
        document.getElementById('toast-container').appendChild(toast);
        
        // Automatically remove the toast after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    // UPDATED: Enhanced viewOrderDetails function with better JSON handling
    function viewOrderDetails(ordersJson) {
        try {
            console.log("Raw orders data:", ordersJson); // Debug: Log the raw data
            
            // Try to parse the JSON string
            let orderDetails;
            
            try {
                // First attempt to parse as is
                orderDetails = JSON.parse(ordersJson);
            } catch (parseError) {
                console.error("Initial JSON parse error:", parseError);
                
                // Try to fix common JSON issues
                try {
                    // Replace escaped single quotes with regular single quotes
                    const fixedJson = ordersJson.replace(/\\'/g, "'");
                    orderDetails = JSON.parse(fixedJson);
                    console.log("Parsed after fixing escaped quotes");
                } catch (error) {
                    // If that failed, try another approach
                    try {
                        // Sometimes the data might be double-encoded
                        orderDetails = JSON.parse(JSON.parse(ordersJson));
                        console.log("Parsed after double parsing");
                    } catch (doubleError) {
                        throw new Error("Could not parse order details: " + parseError.message);
                    }
                }
            }
            
            // Check if orderDetails is actually an array
            if (!Array.isArray(orderDetails)) {
                console.error("Order details is not an array:", orderDetails);
                throw new Error("Invalid order details format: expected an array");
            }
            
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            
            // Clear previous content
            orderDetailsBody.innerHTML = '';
            
            // Add each product to the table
            orderDetails.forEach(product => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${product.category || 'N/A'}</td>
                    <td>${product.item_description || 'N/A'}</td>
                    <td>${product.packaging || 'N/A'}</td>
                    <td>PHP ${parseFloat(product.price || 0).toFixed(2)}</td>
                    <td>${product.quantity || 0}</td>
                `;
                
                orderDetailsBody.appendChild(row);
            });
            
            // Show modal
            document.getElementById('orderDetailsModal').style.display = 'flex';
        } catch (e) {
            console.error('Error parsing order details:', e);
            showToast('Error displaying order details: ' + e.message, 'error');
        }
    }

    function closeOrderDetailsModal() {
        document.getElementById('orderDetailsModal').style.display = 'none';
    }
    
    // Add the new function to update company information
    function updateCompanyInfo() {
        const usernameSelect = document.getElementById('username');
        const companyInput = document.getElementById('company');
        const companyAddressInput = document.getElementById('company_address');
        
        if (usernameSelect.selectedIndex > 0) {
            const option = usernameSelect.options[usernameSelect.selectedIndex];
            companyInput.value = option.getAttribute('data-company-name') || '';
            companyAddressInput.value = option.getAttribute('data-company-address') || 'No company address available';
        } else {
            companyInput.value = '';
            companyAddressInput.value = '';
        }
    }
    
    // Extend the prepareOrderData function to include company field
    window.prepareOrderData = function() {
        // Get values from the form
        const total = calculateCartTotal();
        document.getElementById('total_amount').value = total.toFixed(2);
        document.getElementById('orders').value = JSON.stringify(selectedProducts);
        
        // Get username and order details for PO number if not already set
        if (document.getElementById('po_number').value === "") {
            generatePONumber();
        }
        
        // Set delivery address based on selection
        const deliveryAddressType = document.getElementById('delivery_address_type').value;
        let deliveryAddressValue;
        
        if (deliveryAddressType === 'company') {
            deliveryAddressValue = document.getElementById('company_address').value;
        } else {
            deliveryAddressValue = document.getElementById('custom_address').value;
        }
        
        document.getElementById('delivery_address').value = deliveryAddressValue;
        
        // Make sure we're properly setting the company value
        if (!document.getElementById('company').value) {
            // If no company name is available, set it to empty string to avoid null issues
            document.getElementById('company').value = '';
        }
        
        // Return true to allow form submission
        return true;
    };
    
    // Add this function to see what's being submitted
    function logFormData() {
        const formData = new FormData(document.getElementById('addOrderForm'));
        console.log("Form data being submitted:");
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
    }
    
    // Add this event listener to your form
    document.getElementById('addOrderForm').addEventListener('submit', function(e) {
        // Don't prevent default - just log the data
        logFormData();
    });
    
    // UPDATED: Enhanced printPurchaseOrder function
    function printPurchaseOrder(poNumber) {
        // Show loading message or spinner
        showToast("Generating Purchase Order...", "info");
        
        // Fetch order details from the server
        fetch(`/backend/get_po_details.php?po_number=${poNumber}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Failed to fetch PO details");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log("Order data received:", data.order); // Debug line
                    console.log("Raw orders field:", data.order.orders); // Debug line
                    generatePDF(data.order);
                } else {
                    showToast("Error: " + data.message, "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Error generating Purchase Order: " + error.message, "error");
            });
    }
    
    // Function to generate the PDF with updated layout
    function generatePDF(order) {
        try {
            // Make sure jsPDF is available
            if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
                showToast("PDF library not loaded. Please refresh the page.", "error");
                return;
            }
            
            // Initialize PDF
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Set up document properties
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 20;
            let yPos = margin;
            
            // Add company header
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            doc.text(order.company || 'N/A', margin, yPos);
            yPos += 10;
            
            // Add PO Number with bold label and normal text value
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.text('PO #:', margin, yPos);
            doc.setFont('helvetica', 'normal');
            doc.text(order.po_number, margin + 25, yPos);
            yPos += 7;
            
            // Add Username with bold label and normal text value
            doc.setFont('helvetica', 'bold');
            doc.text('User:', margin, yPos);
            doc.setFont('helvetica', 'normal');
            doc.text(order.username, margin + 25, yPos);
            yPos += 7;
            
            // Add Delivery Address with bold label and normal text value
            doc.setFont('helvetica', 'bold');
            doc.text('Delivery Address:', margin, yPos);
            
            // Split long address into multiple lines if needed
            const deliveryAddress = doc.splitTextToSize(order.delivery_address, pageWidth - (margin * 2) - 40);
            doc.setFont('helvetica', 'normal');
            doc.text(deliveryAddress, margin + 40, yPos);
            
            // Adjust yPos based on how many lines the address takes
            yPos += (deliveryAddress.length * 7) + 5;
            
            // Add right side information
            doc.setFontSize(12);
            
            // Add Order Date on right side with bold label and normal text value
            doc.setFont('helvetica', 'bold');
            doc.text('Order Date:', pageWidth - margin - 55, margin + 10);
            doc.setFont('helvetica', 'normal');
            doc.text(order.order_date, pageWidth - margin, margin + 10, { align: 'right' });
            
            // Add Delivery Date on right side with bold label and normal text value
            doc.setFont('helvetica', 'bold');
            doc.text('Delivery Date:', pageWidth - margin - 55, margin + 17);
            doc.setFont('helvetica', 'normal');
            doc.text(order.delivery_date, pageWidth - margin, margin + 17, { align: 'right' });
            
            // Add a separator line
            doc.setDrawColor(200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += 10;
            
            // UPDATED: More robust order items handling
            let orderItems = [];
            let tableData = [];
            
            try {
                // Check the type of order.orders and handle accordingly
                if (typeof order.orders === 'string') {
                    try {
                        // First attempt to parse as is
                        orderItems = JSON.parse(order.orders);
                    } catch (parseError) {
                        console.error("Initial JSON parse error:", parseError);
                        
                        // Try to fix common JSON issues
                        try {
                            // Replace escaped single quotes with regular single quotes
                            const fixedJson = order.orders.replace(/\\'/g, "'");
                            orderItems = JSON.parse(fixedJson);
                            console.log("Parsed after fixing escaped quotes");
                        } catch (error) {
                            // If that failed, try another approach
                            try {
                                // Sometimes the data might be double-encoded
                                orderItems = JSON.parse(JSON.parse(order.orders));
                                console.log("Parsed after double parsing");
                            } catch (doubleError) {
                                throw new Error("Could not parse order items: " + parseError.message);
                            }
                        }
                    }
                } else if (Array.isArray(order.orders)) {
                    orderItems = order.orders;
                } else {
                    console.error("Order items is neither a string nor an array:", order.orders);
                    throw new Error("Invalid order items format");
                }
                
                // Make sure orderItems is an array before using map
                if (!Array.isArray(orderItems)) {
                    console.error("Order items is not an array after parsing:", orderItems);
                    throw new Error("Order items is not an array");
                }
                
                // Create table data
                tableData = orderItems.map(item => [
                    item.category || 'N/A',
                    item.item_description || 'N/A',
                    item.packaging || 'N/A',
                    `PHP ${parseFloat(item.price || 0).toFixed(2)}`,
                    item.quantity?.toString() || '0',
                    `PHP ${(parseFloat(item.price || 0) * parseInt(item.quantity || 0)).toFixed(2)}`
                ]);
            } catch (error) {
                console.error("Error processing order items:", error);
                console.log("Order items content:", order.orders);
                
                // Fallback: create a single row with error message
                tableData = [["Error", "Could not process order items", "", "", "", ""]];
                showToast("Warning: Could not process order items properly. PDF may be incomplete.", "warning");
            }
            
            const tableHeaders = [['Category', 'Product', 'Packaging', 'Price', 'Quantity', 'Subtotal']];
            
            // Add the table
            doc.autoTable({
                startY: yPos,
                head: tableHeaders,
                body: tableData,
                theme: 'striped',
                styles: {
                    fontSize: 10,
                    cellPadding: 3
                },
                headStyles: {
                    fillColor: [51, 51, 51],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                columnStyles: {
                    5: { halign: 'right' }  // Align subtotal to right
                }
            });
            
            // Add total amount
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFont('helvetica', 'bold');
            doc.text('Total Amount:', pageWidth - margin - 70, finalY);
            doc.setFont('helvetica', 'normal');
            doc.text(`PHP ${parseFloat(order.total_amount).toFixed(2)}`, pageWidth - margin, finalY, { align: 'right' });
            
            // Add footer
            const footerY = doc.internal.pageSize.getHeight() - 10;
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(100);
            doc.text(`Generated at ${new Date().toLocaleString()}`, pageWidth / 2, footerY, { align: 'center' });
            
            // Save the PDF with a meaningful filename
            doc.save(`PO_${order.po_number}.pdf`);
            showToast("Purchase Order generated successfully!", "success");
        } catch (error) {
            console.error("Error generating PDF:", error);
            showToast("Error generating PDF: " + error.message, "error");
        }
    }
    </script>
    <script>
        <?php include('../../js/order_processing.js'); ?>
    
        // Search functionality (client-side, same as in order_history.php)
        $(document).ready(function() {
            // Initialize date pickers
            $("#order_date").datepicker({
                dateFormat: "yy-mm-dd",
                defaultDate: new Date()
            }).datepicker("setDate", new Date());
            
            $("#delivery_date").datepicker({
                dateFormat: "yy-mm-dd",
                minDate: 0  // Prevent selection of dates before today
            });
            
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                console.log("Searching for:", searchText); // Debug line

                $(".orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });
            
            // Handle search button click (same functionality as typing)
            $(".search-btn").on("click", function() {
                let searchText = $("#searchInput").val().toLowerCase().trim();
                
                $(".orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });
        });
    </script> 
</body>
</html>