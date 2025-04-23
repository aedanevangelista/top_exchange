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
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Pending Orders</h1>
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
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <span class="status-badge status-pending"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars($order['orders']) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-orders">No pending orders found.</td>
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

    <style>
        /* Raw Materials table styling */
        .raw-materials-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .raw-materials-container h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        
        .materials-table-container {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .materials-table th, 
        .materials-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .materials-table th {
            background-color: #f1f1f1;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .material-sufficient {
            color: #28a745;
            font-weight: bold;
        }
        
        .material-insufficient {
            color: #dc3545;
            font-weight: bold;
        }
        
        .materials-status {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-sufficient {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-insufficient {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Make modal larger to fit tables */
        #statusModal .modal-content {
            max-width: 700px;
            width: 90%;
        }
    </style>

    <script src="/js/orders.js"></script>
        <script>
    window.openStatusModal = function(poNumber, username, ordersJson) {
        $('#statusMessage').text('Change order status for ' + poNumber);
        $('#statusModal').data('po_number', poNumber).show();
        
        // Clear previous data and show loading state
        $('#rawMaterialsBody').html('<tr><td colspan="4" style="text-align:center;">Loading inventory data...</td></tr>');
        $('#materialsStatus').text('Checking product availability...');
        $('#materialsStatus').removeClass('status-sufficient status-insufficient');
        $('#rawMaterialsContainer').show();
        
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
                            $('#rawMaterialsContainer h3').text('All products are in stock - no manufacturing needed');
                            $('#rawMaterialsBody').html('<tr><td colspan="4" style="text-align:center;">No additional raw materials required</td></tr>');
                        }
                        
                        // Enable or disable the Active button based on overall status
                        updateOrderActionStatus(response);
                    } else {
                        $('#rawMaterialsBody').html(`<tr><td colspan="4" style="text-align:center;color:red;">Error: ${response.message || 'Unknown error'}</td></tr>`);
                        $('#materialsStatus').text('Could not check product availability. Order status can still be changed.');
                        $('#activeStatusBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    $('#rawMaterialsBody').html(`<tr><td colspan="4" style="text-align:center;color:red;">Server error: ${error}</td></tr>`);
                    $('#materialsStatus').text('Error checking inventory. Order status can still be changed.');
                    $('#activeStatusBtn').prop('disabled', false);
                    console.error("AJAX Error:", status, error);
                }
            });
        } catch (e) {
            $('#rawMaterialsBody').html(`<tr><td colspan="4" style="text-align:center;color:red;">Error: ${e.message}</td></tr>`);
            $('#materialsStatus').text('Error processing order data. Order status can still be changed.');
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
        const productsTable = $('<table class="materials-table"></table>');
        const thead = $(`
            <thead>
                <tr>
                    <th>Product</th>
                    <th>In Stock</th>
                    <th>Required</th>
                    <th>Status</th>
                </tr>
            </thead>
        `);
        const tbody = $('<tbody></tbody>');
        
        let allSufficient = true;
        let anyCanManufacture = true;
        
        Object.keys(productsData).forEach(product => {
            const data = productsData[product];
            const available = parseInt(data.available);
            const required = parseInt(data.required);
            const isSufficient = data.sufficient;
            
            if (!isSufficient) {
                allSufficient = false;
                
                // Check if product can be manufactured
                if (data.canManufacture === false) {
                    anyCanManufacture = false;
                }
            }
            
            const row = $(`
                <tr>
                    <td>${product}</td>
                    <td>${available}</td>
                    <td>${required}</td>
                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                        ${isSufficient ? 'In Stock' : 'Need to manufacture ' + data.shortfall + ' more'}
                    </td>
                </tr>
            `);
            tbody.append(row);
        });
        
        productsTable.append(thead).append(tbody);
        
        // Update the HTML container
        $('#rawMaterialsContainer').html('<h3>Finished Products Status</h3>');
        $('#rawMaterialsContainer').append(productsTable);
        
        // If not all products are in stock but can be manufactured
        if (!allSufficient && anyCanManufacture) {
            $('#rawMaterialsContainer').append('<h3>Raw Materials Required for Manufacturing</h3><div class="materials-table-container"><table class="materials-table"><thead><tr><th>Material</th><th>Available</th><th>Required</th><th>Status</th></tr></thead><tbody id="rawMaterialsBody"></tbody></table></div><div id="materialsStatus" class="materials-status"></div>');
        }
    }

    // Function to display raw materials data
    function displayRawMaterials(materialsData) {
        const rawMaterialsBody = $('#rawMaterialsBody');
        rawMaterialsBody.empty();
        
        // If no materials data
        if (!materialsData || Object.keys(materialsData).length === 0) {
            rawMaterialsBody.html('<tr><td colspan="4" style="text-align:center;">No raw materials data available</td></tr>');
            $('#materialsStatus').text('No raw materials information found. Order status can be changed.');
            $('#activeStatusBtn').prop('disabled', false);
            return;
        }
        
        // Process materials data
        let allSufficient = true;
        let insufficientMaterials = [];
        
        // Add each material to the table
        Object.keys(materialsData).forEach(material => {
            const data = materialsData[material];
            const available = parseFloat(data.available);
            const required = parseFloat(data.required);
            const isSufficient = data.sufficient;
            
            if (!isSufficient) {
                allSufficient = false;
                insufficientMaterials.push(material);
            }
            
            const row = $(`
                <tr>
                    <td>${material}</td>
                    <td>${formatWeight(available)}</td>
                    <td>${formatWeight(required)}</td>
                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                        ${isSufficient ? 'Sufficient' : 'Insufficient'}
                    </td>
                </tr>
            `);
            rawMaterialsBody.append(row);
        });
        
        // Update overall status and enable/disable button
        if (allSufficient) {
            $('#materialsStatus').text('All raw materials are sufficient for manufacturing.');
            $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
        } else {
            const message = `Insufficient raw materials: ${insufficientMaterials.join(', ')}`;
            $('#materialsStatus').text(`${message}. The order cannot proceed.`);
            $('#materialsStatus').addClass('status-insufficient').removeClass('status-sufficient');
        }
        
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
        $('#materialsStatus').text(statusMessage);
        
        if (canProceed) {
            $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
        } else {
            $('#materialsStatus').addClass('status-insufficient').removeClass('status-sufficient');
        }
    }

    // Function to change order status
    window.changeStatus = function(status) {
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
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Failed to change status. Please try again.');
            }
        });
    };

    // Helper function to format weight values
    function formatWeight(weightInGrams) {
        if (weightInGrams >= 1000) {
            return (weightInGrams / 1000).toFixed(2) + ' kg';
        } else {
            return weightInGrams.toFixed(2) + ' g';
        }
    }

    // Function to display raw materials data
    function displayRawMaterials(materialsData) {
        const rawMaterialsBody = $('#rawMaterialsBody');
        rawMaterialsBody.empty();
        
        // If no materials data
        if (!materialsData || Object.keys(materialsData).length === 0) {
            rawMaterialsBody.html('<tr><td colspan="4" style="text-align:center;">No raw materials data available</td></tr>');
            $('#materialsStatus').text('No raw materials information found. Order status can be changed.');
            $('#activeStatusBtn').prop('disabled', false);
            return;
        }
        
        // Process materials data
        let allSufficient = true;
        let insufficientMaterials = [];
        
        // Add each material to the table
        Object.keys(materialsData).forEach(material => {
            const data = materialsData[material];
            const available = parseFloat(data.available);
            const required = parseFloat(data.required);
            const isSufficient = available >= required;
            
            if (!isSufficient) {
                allSufficient = false;
                insufficientMaterials.push(material);
            }
            
            const row = `
                <tr>
                    <td>${material}</td>
                    <td>${formatWeight(available)}</td>
                    <td>${formatWeight(required)}</td>
                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                        ${isSufficient ? 'Sufficient' : 'Insufficient'}
                    </td>
                </tr>
            `;
            rawMaterialsBody.append(row);
        });
        
        // Update overall status and enable/disable button
        if (allSufficient) {
            $('#materialsStatus').text('All raw materials are sufficient for this order.');
            $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
            $('#activeStatusBtn').prop('disabled', false);
        } else {
            const message = `Insufficient raw materials: ${insufficientMaterials.join(', ')}`;
            $('#materialsStatus').text(`${message}. The order cannot proceed.`);
            $('#materialsStatus').addClass('status-insufficient').removeClass('status-sufficient');
            $('#activeStatusBtn').prop('disabled', true);
        }
    }

    // Function to change order status
    window.changeStatus = function(status) {
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
                    if (deductMaterials) {
                        message = `Changed status for ${poNumber} to ${status}. Raw materials have been deducted.`;
                    }
                    
                    // Show toast and reload
                    showToast(message, toastType);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Failed to change status: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Failed to change status. Please try again.');
            }
        });
    };
        </script>
    <script>
        <?php include('../../js/order_processing.js'); ?>
    </script> 
</body>
</html>