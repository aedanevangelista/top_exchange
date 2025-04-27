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

// Fetch active clients for the dropdown with their address information
$clients = [];
$clients_with_addresses = []; // Array to store clients with their address info
$clients_with_company = []; // Array to store clients with their company names

$stmt = $conn->prepare("SELECT username, company, bill_to, bill_to_attn, ship_to, ship_to_attn FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clients[] = $row['username'];
    $clients_with_addresses[$row['username']] = [
        'bill_to' => $row['bill_to'],
        'bill_to_attn' => $row['bill_to_attn'],
        'ship_to' => $row['ship_to'],
        'ship_to_attn' => $row['ship_to_attn']
    ];
    $clients_with_company[$row['username']] = $row['company'];
}
$stmt->close();

// Fetch only pending orders for display in the table with sorting
$orders = []; // Initialize $orders as an empty array

// Modified query to join with clients_accounts to get the company information
$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.orders, o.total_amount, o.status, 
        o.special_instructions, o.bill_to, o.bill_to_attn, o.ship_to, o.ship_to_attn, COALESCE(o.company, c.company) as company
        FROM orders o
        LEFT JOIN clients_accounts c ON o.username = c.username
        WHERE o.status = 'Pending'";

// Add sorting - Handle special case for company column which comes from a COALESCE
if ($sort_column === 'company') {
    $sql .= " ORDER BY company {$sort_direction}, o.po_number DESC";
} else if ($sort_column === 'order_date' || $sort_column === 'delivery_date') {
    // For date columns, add a secondary sort by po_number to ensure latest added appears first when dates are the same
    $sql .= " ORDER BY o.{$sort_column} {$sort_direction}, o.po_number DESC";
} else {
    $sql .= " ORDER BY o.{$sort_column} {$sort_direction}, o.order_date DESC, o.po_number DESC";
}

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
    <link rel="stylesheet" href="/css/pending_orders.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>    
    <style>
      /* Styles for address display */
      .address-info-container {
          border: 1px solid #ddd;
          border-radius: 5px;
          padding: 10px;
          margin-bottom: 20px;
          background-color: #f9f9f9;
      }
      
      .address-display {
          display: flex;
          flex-wrap: wrap;
          gap: 20px;
      }
      
      .address-display .address-section {
          flex: 1;
          min-width: 250px;
      }
      
      .address-display h4 {
          margin-top: 0;
          border-bottom: 1px solid #ddd;
          padding-bottom: 5px;
          color: #333;
      }
      
      .address-display p {
          margin: 5px 0;
      }
      
      .address-display .no-info {
          color: #888;
          font-style: italic;
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
                        <!-- Modified Company column to be sortable -->
                        <th class="sortable">
                            <a href="<?= getSortUrl('company', $sort_column, $sort_direction) ?>">
                                Company <?= getSortIcon('company', $sort_column, $sort_direction) ?>
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
                        <th>Address Info</th>
                        <th>Orders</th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">
                                Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Special Instructions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['company'] ?: 'No Company') ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td>
                                    <button class="view-address-btn" onclick="viewAddressInfo(
                                        '<?= htmlspecialchars(addslashes($order['bill_to'] ?? 'N/A')) ?>',
                                        '<?= htmlspecialchars(addslashes($order['bill_to_attn'] ?? '')) ?>',
                                        '<?= htmlspecialchars(addslashes($order['ship_to'] ?? 'N/A')) ?>',
                                        '<?= htmlspecialchars(addslashes($order['ship_to_attn'] ?? '')) ?>'
                                    )">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>')">
                                        <i class="fas fa-clipboard-list"></i> Orders
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <!-- Add Special Instructions column with view button -->
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            <i class="fas fa-comment"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars($order['orders']) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                <button class="download-btn" onclick="downloadPODirectly(
                                '<?= htmlspecialchars($order['po_number']) ?>', 
                                '<?= htmlspecialchars($order['username']) ?>', 
                                '<?= htmlspecialchars($order['company']) ?>', 
                                '<?= htmlspecialchars($order['order_date']) ?>', 
                                '<?= htmlspecialchars($order['delivery_date']) ?>', 
                                '<?= htmlspecialchars(addslashes($order['orders'])) ?>', 
                                '<?= htmlspecialchars($order['total_amount']) ?>', 
                                '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>',
                                '<?= htmlspecialchars(addslashes($order['bill_to'] ?? '')) ?>',
                                '<?= htmlspecialchars(addslashes($order['bill_to_attn'] ?? '')) ?>',
                                '<?= htmlspecialchars(addslashes($order['ship_to'] ?? '')) ?>',
                                '<?= htmlspecialchars(addslashes($order['ship_to_attn'] ?? '')) ?>'
                            )">
                                <i class="fas fa-file-pdf"></i> Download PDF
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

    <!-- PO PDF Preview Section -->
    <div id="pdfPreview">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <!-- PDF content here -->
            </div>
            <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>

    <!-- Overlay Form for Adding New Order -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form" action="/backend/add_order.php">
                <div class="left-section">
                    <label for="username">Username:</label>
                    <select id="username" name="username" required onchange="generatePONumber(); loadClientAddressInfo(this.value);">
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client) ?>" 
                                data-company="<?= htmlspecialchars($clients_with_company[$client] ?? '') ?>"
                                data-bill-to="<?= htmlspecialchars($clients_with_addresses[$client]['bill_to'] ?? '') ?>"
                                data-bill-to-attn="<?= htmlspecialchars($clients_with_addresses[$client]['bill_to_attn'] ?? '') ?>"
                                data-ship-to="<?= htmlspecialchars($clients_with_addresses[$client]['ship_to'] ?? '') ?>"
                                data-ship-to-attn="<?= htmlspecialchars($clients_with_addresses[$client]['ship_to_attn'] ?? '') ?>">
                                <?= htmlspecialchars($client) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                                        
                    <label for="order_date">Order Date:</label>
                    <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label>
                    <input type="text" id="delivery_date" name="delivery_date" autocomplete="off" required>
                    
                    <!-- Address information section - Read-only display of client address -->
                    <h3>Address Information</h3>
                    <div id="address-info" class="address-info-container">
                        <p>Client address information will be loaded when a username is selected.</p>
                    </div>
                    
                    <!-- Hidden fields to store address info -->
                    <input type="hidden" name="bill_to" id="bill_to">
                    <input type="hidden" name="bill_to_attn" id="bill_to_attn">
                    <input type="hidden" name="ship_to" id="ship_to">
                    <input type="hidden" name="ship_to_attn" id="ship_to_attn">
                    
                    <input type="hidden" name="special_instructions" id="special_instructions_hidden">
                    <!-- Add special instructions field -->
                    <label for="special_instructions">Special Instructions:</label>
                    <textarea id="special_instructions" name="special_instructions" rows="3" placeholder="Enter any special instructions here..."></textarea>
                    
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

    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content">
            <div class="instructions-header">
                <h3>Special Instructions</h3>
                <div class="instructions-po-number" id="instructionsPoNumber"></div>
            </div>
            <div class="instructions-body" id="instructionsContent">
                <!-- Instructions will be displayed here -->
            </div>
            <div class="instructions-footer">
                <button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button>
            </div>
        </div>
    </div>

    <!-- Address Info Modal - Similar to accounts_clients.php -->
    <div id="addressInfoModal" class="overlay" style="display: none;">
        <div class="info-modal-content">
            <div class="info-modal-header">
                <h2><i class="fas fa-map-marker-alt"></i> Address Information</h2>
                <span class="info-modal-close" onclick="closeAddressInfoModal()">&times;</span>
            </div>
            
            <div class="info-modal-body">
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-file-invoice"></i> Billing Information</h3>
                    <table class="info-table">
                        <tr>
                            <th>Bill To Address</th>
                            <td id="modalBillTo"></td>
                        </tr>
                        <tr id="billToAttnRow">
                            <th>Attention To</th>
                            <td class="attention-cell">
                                <i class="fas fa-user"></i>
                                <span id="modalBillToAttn"></span>
                            </td>
                        </tr>
                    </table>
                    <div id="noBillingInfo" class="empty-notice" style="display: none;">
                        No billing address information provided.
                    </div>
                </div>
                
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                    <table class="info-table">
                        <tr>
                            <th>Ship To Address</th>
                            <td id="modalShipTo"></td>
                        </tr>
                        <tr id="shipToAttnRow">
                            <th>Attention To</th>
                            <td class="attention-cell">
                                <i class="fas fa-user"></i>
                                <span id="modalShipToAttn"></span>
                            </td>
                        </tr>
                    </table>
                    <div id="noShippingInfo" class="empty-notice" style="display: none;">
                        No shipping address information provided.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/orders.js"></script>
    <script src="/js/pending_orders.js"></script>
    <script>
        <?php include('../../js/order_processing.js'); ?>
    
        // Add custom script to handle loading client address info
        function loadClientAddressInfo(username) {
            if (!username) return;
            
            console.log("Loading address info for:", username);
            
            const selectedOption = $(`#username option[value="${username}"]`);
            
            // Get address data from the option data attributes
            const billTo = selectedOption.data('bill-to') || '';
            const billToAttn = selectedOption.data('bill-to-attn') || '';
            const shipTo = selectedOption.data('ship-to') || '';
            const shipToAttn = selectedOption.data('ship-to-attn') || '';
            
            console.log("Address data loaded:", {
                billTo: billTo,
                billToAttn: billToAttn,
                shipTo: shipTo,
                shipToAttn: shipToAttn
            });
            
            // Set hidden field values
            $('#bill_to').val(billTo);
            $('#bill_to_attn').val(billToAttn);
            $('#ship_to').val(shipTo);
            $('#ship_to_attn').val(shipToAttn);
            
            // Display the address info in a readable format
            let addressHTML = '<div class="address-display">';
            
            addressHTML += '<div class="address-section">';
            addressHTML += '<h4><i class="fas fa-file-invoice"></i> Billing Information</h4>';
            if (billTo) {
                addressHTML += `<p><strong>Bill To:</strong> ${billTo}</p>`;
            }
            if (billToAttn) {
                addressHTML += `<p><strong>Attention:</strong> ${billToAttn}</p>`;
            }
            if (!billTo && !billToAttn) {
                addressHTML += '<p class="no-info">No billing information available</p>';
            }
            addressHTML += '</div>';
            
            addressHTML += '<div class="address-section">';
            addressHTML += '<h4><i class="fas fa-shipping-fast"></i> Shipping Information</h4>';
            if (shipTo) {
                addressHTML += `<p><strong>Ship To:</strong> ${shipTo}</p>`;
            }
            if (shipToAttn) {
                addressHTML += `<p><strong>Attention:</strong> ${shipToAttn}</p>`;
            }
            if (!shipTo && !shipToAttn) {
                addressHTML += '<p class="no-info">No shipping information available</p>';
            }
            addressHTML += '</div>';
            
            addressHTML += '</div>';
            
            $('#address-info').html(addressHTML);
        }

        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();

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
            
            // Handle search button click
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

            // Make sure prepareOrderData includes proper fields
            window.prepareOrderData = function() {
                const orderData = JSON.stringify(selectedProducts);
                $('#orders').val(orderData);
                const totalAmount = calculateCartTotal();
                $('#total_amount').val(totalAmount.toFixed(2));
                
                // Include special instructions in form data
                const specialInstructions = $('#special_instructions').val();
                $('#special_instructions_hidden').val(specialInstructions);
                
                // No need to set delivery_address since we're using bill_to and ship_to directly
                console.log("Order data prepared with addresses - bill_to:", $('#bill_to').val(), "ship_to:", $('#ship_to').val());
            };
        });

        // Override the form submission to ensure we're not checking for delivery_address
        $('#addOrderForm').on('submit', function(e) {
            e.preventDefault();
            
            if (selectedProducts.length === 0) {
                alert('Please add products to your order');
                return;
            }

            prepareOrderData();
            
            // Check if we have either billing or shipping info
            const billTo = $('#bill_to').val();
            const shipTo = $('#ship_to').val();
            
            console.log("Submitting form with bill_to:", billTo, "and ship_to:", shipTo);
            
            if ((!billTo || billTo.trim() === '') && (!shipTo || shipTo.trim() === '')) {
                alert('No address information available. Please select a different user with address information.');
                return;
            }
            
            // Validate other required fields
            if (!$('#username').val()) {
                alert('Please select a username');
                return;
            }
            
            if (!$('#po_number').val()) {
                alert('PO number is missing. Please try again.');
                return;
            }
            
            // Disable the save button to prevent multiple submissions
            $('.save-btn').prop('disabled', true);
            
            // Submit the form via AJAX
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Order added successfully!', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.save-btn').prop('disabled', false);
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    $('.save-btn').prop('disabled', false);
                    console.error("Form submission error:", status, error);
                    console.error("Server response:", xhr.responseText);
                    alert('Error submitting order. Please try again. Details: ' + error);
                }
            });
        });
    </script>
</body>
</html>