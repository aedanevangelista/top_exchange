<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure user has access to Orders

// Get current date for auto-transit
$current_date = date('Y-m-d');

// Automatically update orders to In Transit if today is delivery day
$auto_transit_sql = "UPDATE orders SET status = 'In Transit' 
                     WHERE status = 'For Delivery' 
                     AND delivery_date = ? 
                     AND status != 'In Transit'";
$auto_transit_stmt = $conn->prepare($auto_transit_sql);
if ($auto_transit_stmt) {
    $auto_transit_stmt->bind_param("s", $current_date);
    $auto_transit_stmt->execute();
    $auto_transit_count = $auto_transit_stmt->affected_rows;
    $auto_transit_stmt->close();
}

// Fetch all drivers for the driver assignment dropdown
$drivers = [];
$driverStmt = $conn->prepare("SELECT id, name FROM drivers WHERE availability = 'Available' AND current_deliveries < 20 ORDER BY name");
if ($driverStmt) {
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    while ($row = $driverResult->fetch_assoc()) {
        $drivers[] = $row;
    }
    $driverStmt->close();
}

// Get status filter from query string
$filterStatus = $_GET['status'] ?? '';

// Handle sorting
$sortColumn = $_GET['sort'] ?? 'delivery_date';
$sortOrder = $_GET['order'] ?? 'ASC';

// Validate sort parameters
$allowedColumns = ['po_number', 'username', 'order_date', 'delivery_date', 'total_amount', 'status'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'delivery_date';
}

$allowedOrders = ['ASC', 'DESC'];
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'ASC';
}

// Build the SQL query with or without status filter
$whereClause = "WHERE o.status IN ('For Delivery', 'In Transit')";
if (!empty($filterStatus)) {
    $whereClause = "WHERE o.status = '$filterStatus'";
}

$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, 
        o.orders, o.total_amount, o.status, o.driver_assigned, 
        IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name 
        FROM orders o 
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number 
        LEFT JOIN drivers d ON da.driver_id = d.id 
        $whereClause
        ORDER BY o.$sortColumn $sortOrder";

$orders = [];
$orderStmt = $conn->prepare($sql);
if ($orderStmt) {
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    if ($orderResult && $orderResult->num_rows > 0) {
        while ($row = $orderResult->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $orderStmt->close();
} else {
    // For debugging - log the SQL error
    error_log("SQL Error in deliverable_orders.php: " . $conn->error);
}

// Get filter options for status
$statusOptions = ['For Delivery', 'In Transit'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverable Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
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
        
        .page-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .filter-section {
            display: flex;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
        }
        
        .filter-label {
            margin-right: 8px;
            font-weight: bold;
        }
        
        .filter-select {
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            min-width: 150px;
        }

        /* Status badge styles */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-for-delivery {
            background-color: #fd7e14;
            color: white;
        }
        
        .status-in-transit {
            background-color: #17a2b8;
            color: white;
        }

        /* Driver styles */
        .driver-badge {
            background-color: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .driver-btn {
            background-color: #6610f2;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
            transition: background-color 0.3s;
        }

        .driver-btn:hover {
            background-color: #510bc4;
        }

        .assign-driver-btn {
            background-color: #fd7e14;
        }

        .assign-driver-btn:hover {
            background-color: #e67211;
        }

        .complete-delivery-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .complete-delivery-btn:hover {
            background-color: #218838;
        }
        
        .toggle-transit-btn {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            transition: background-color 0.3s;
        }
        
        .toggle-transit-btn:hover {
            background-color: #138496;
        }
        
        /* Order details modal */
        .order-details-container {
            max-height: 70vh;
            overflow-y: auto;
            margin-bottom: 10px;
            padding-right: 5px;
        }
        
        /* No orders message */
        .no-orders {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #6c757d;
        }
        
        .action-buttons-cell {
            min-width: 210px;
        }
        
        /* Sort indicators and clickable headers - matching other pages */
        .sort-header {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
            color: #2980b9;
            transition: background-color 0.2s;
        }
        
        .sort-header:hover {
            background-color: #e9f2fa;
        }
        
        .sort-header::after {
            content: '\f0dc';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 5px;
            color: #2980b9;
        }
        
        .sort-header.asc::after {
            content: '\f0de';
            color: #2980b9;
        }
        
        .sort-header.desc::after {
            content: '\f0dd';
            color: #2980b9;
        }
        
        /* Highlighted delivery date */
        .today-delivery {
            background-color: #fff3cd;
            font-weight: bold;
        }
        
        /* Driver modal styling */
        .overlay-content.driver-modal-content {
            max-width: 450px;
            padding: 25px;
            border-radius: 8px;
        }
        
        .driver-modal-content h2 {
            color: #333;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .driver-selection {
            margin: 20px 0;
        }
        
        .driver-selection label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .driver-selection select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #fff;
            font-size: 16px;
        }
        
        .driver-modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .cancel-btn, .save-btn {
            padding: 10px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .cancel-btn {
            background-color: #f1f1f1;
            color: #333;
        }
        
        .cancel-btn:hover {
            background-color: #e1e1e1;
        }
        
        .save-btn {
            background-color: #2980b9;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #2471a3;
        }
        
        .date-info {
            margin-bottom: 15px;
            padding: 5px 10px;
            background-color: #e9f2fa;
            border-radius: 4px;
            color: #2980b9;
            font-size: 14px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <div class="header-content">
                <h1 class="page-title">Deliverable Orders</h1>
                
                <!-- Filter section in the middle -->
                <div class="filter-section">
                    <div class="filter-group">
                        <span class="filter-label">Status:</span>
                        <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                            <option value="">All</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="date-info">
                        <i class="fas fa-calendar-day"></i> Today: <?= date('Y-m-d') ?>
                        <?php if (isset($auto_transit_count) && $auto_transit_count > 0): ?>
                            (<?= $auto_transit_count ?> orders auto-updated)
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div>
        
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th class="sort-header <?= $sortColumn == 'po_number' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="po_number">PO Number</th>
                        <th class="sort-header <?= $sortColumn == 'username' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="username">Username</th>
                        <th class="sort-header <?= $sortColumn == 'order_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="order_date">Order Date</th>
                        <th class="sort-header <?= $sortColumn == 'delivery_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="delivery_date">Delivery Date</th>
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <th class="sort-header <?= $sortColumn == 'total_amount' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="total_amount">Total Amount</th>
                        <th>Driver</th>
                        <th class="sort-header <?= $sortColumn == 'status' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="status">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): 
                            $isDeliveryDay = ($order['delivery_date'] === $current_date);
                        ?>
                            <tr class="order-row" data-status="<?= htmlspecialchars($order['status']) ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td class="<?= $isDeliveryDay ? 'today-delivery' : '' ?>">
                                    <?= htmlspecialchars($order['delivery_date']) ?>
                                    <?= $isDeliveryDay ? ' (Today)' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>    
                                        View Order Items
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if ($order['driver_assigned'] && !empty($order['driver_name'])): ?>
                                        <div class="driver-badge">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                        </div>
                                        <button class="driver-btn" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>', <?= $order['driver_id'] ?>, '<?= htmlspecialchars($order['driver_name']) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change
                                        </button>
                                    <?php else: ?>
                                        <div>No driver assigned</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <span class="status-badge status-for-delivery">For Delivery</span>
                                    <?php else: ?>
                                        <span class="status-badge status-in-transit">In Transit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons-cell">
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <button class="toggle-transit-btn" onclick="toggleTransitStatus('<?= htmlspecialchars($order['po_number']) ?>', 'In Transit')">
                                            <i class="fas fa-truck"></i> Mark In Transit
                                        </button>
                                    <?php else: ?>
                                        <button class="toggle-transit-btn" onclick="toggleTransitStatus('<?= htmlspecialchars($order['po_number']) ?>', 'For Delivery')">
                                            <i class="fas fa-warehouse"></i> Mark For Delivery
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="complete-delivery-btn" onclick="completeDelivery('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-orders">No orders ready for delivery.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

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
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                    <i class="fas fa-arrow-left"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="overlay" style="display: none;">
        <div class="overlay-content driver-modal-content">
            <h2><i class="fas fa-user"></i> <span id="driverModalTitle">Change Driver</span></h2>
            <p id="driverModalMessage"></p>
            <div class="driver-selection">
                <label for="driverSelect">Select Driver:</label>
                <select id="driverSelect">
                    <option value="0">-- Select a driver --</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="driver-modal-buttons">
                <button class="cancel-btn" onclick="closeDriverModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="save-btn" onclick="assignDriver()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentPoNumber = '';
        let currentDriverId = 0;
        
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const currentSort = '<?= $sortColumn ?>';
            const currentOrder = '<?= $sortOrder ?>';
            
            let url = '?';
            const params = [];
            
            if (status) {
                params.push(`status=${encodeURIComponent(status)}`);
            }
            
            if (currentSort && currentOrder) {
                params.push(`sort=${currentSort}&order=${currentOrder}`);
            }
            
            url += params.join('&');
            window.location.href = url;
        }
        
        function toggleTransitStatus(poNumber, newStatus) {
            // Show confirmation dialog
            const action = newStatus === 'In Transit' ? 'mark as In Transit' : 'mark as For Delivery';
            if (confirm(`Are you sure you want to ${action} this order?`)) {
                // Show loading toast
                showToast(`Updating order status...`, 'info');
                
                fetch('/backend/toggle_transit_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        po_number: poNumber,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (newStatus === 'In Transit') {
                            showToast('Order marked as In Transit', 'success');
                        } else {
                            showToast('Order marked as For Delivery', 'success');
                        }
                        // Reload the page after a short delay
                        setTimeout(() => { window.location.reload(); }, 1000);
                    } else {
                        showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error: Failed to communicate with server', 'error');
                });
            }
        }

        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            
            // Fetch the order items
            fetch(`/backend/get_order_items.php?po_number=${poNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const orderItems = data.orderItems;
                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    
                    let totalAmount = 0;
                    
                    orderItems.forEach(item => {
                        const quantity = parseInt(item.quantity) || 0;
                        const price = parseFloat(item.price) || 0;
                        const itemTotal = quantity * price;
                        totalAmount += itemTotal;
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.category || ''}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging || ''}</td>
                            <td>PHP ${price.toFixed(2)}</td>
                            <td>${quantity}</td>
                            <td>PHP ${itemTotal.toFixed(2)}</td>
                        `;
                        orderDetailsBody.appendChild(row);
                    });
                    
                    // Add a total row
                    const totalRow = document.createElement('tr');
                    totalRow.style.fontWeight = 'bold';
                    totalRow.innerHTML = `
                        <td colspan="5" style="text-align: right;">Total:</td>
                        <td>PHP ${totalAmount.toFixed(2)}</td>
                    `;
                    orderDetailsBody.appendChild(totalRow);
                    
                    document.getElementById('orderDetailsModal').style.display = 'flex';
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error, 'error');
                console.error('Error fetching order details:', error);
            });
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function openDriverModal(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId;
            
            document.getElementById('driverModalTitle').textContent = 'Change Driver';
            document.getElementById('driverModalMessage').textContent = `Current driver: ${driverName}`;
            
            // Set the current driver in the dropdown
            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = driverId;
            
            // Show the modal
            document.getElementById('driverModal').style.display = 'flex';
        }

        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
        }

        function assignDriver() {
            const driverId = document.getElementById('driverSelect').value;
            
            if (driverId == 0) {
                showToast('Please select a driver', 'error');
                return;
            }
            
            // Show loading state
            const saveBtn = document.querySelector('#driverModal .save-btn');
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            saveBtn.disabled = true;

            // Send request to assign driver
            fetch('/backend/assign_driver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    driver_id: driverId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Driver updated successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                    saveBtn.innerHTML = originalBtnText;
                    saveBtn.disabled = false;
                }
                closeDriverModal();
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error: Failed to communicate with server', 'error');
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
                closeDriverModal();
            });
        }

        function completeDelivery(poNumber, username) {
            if (confirm(`Mark delivery of order ${poNumber} for ${username} as completed?`)) {
                fetch('/backend/complete_delivery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ po_number: poNumber })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Delivery completed successfully', 'success');
                        setTimeout(() => { window.location.reload(); }, 1000);
                    } else {
                        showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error: Failed to communicate with server', 'error');
                });
            }
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
            
            setTimeout(() => { toast.remove(); }, 5000);
        }

        // Sorting functionality
        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                let order = 'ASC';
                
                // If already sorted by this column, toggle order
                if (this.classList.contains('asc')) {
                    order = 'DESC';
                }
                
                // Preserve any filter when sorting
                const statusFilter = document.getElementById('statusFilter').value;
                let url = `?sort=${column}&order=${order}`;
                
                if (statusFilter) {
                    url += `&status=${encodeURIComponent(statusFilter)}`;
                }
                
                window.location.href = url;
            });
        });

        // Search functionality
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                
                $(".order-row").each(function() {
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
                
                $(".order-row").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });

            // Handle clicks outside modals
            $(document).on('click', '.overlay', function(event) {
                if (event.target === this) {
                    if (this.id === 'orderDetailsModal') closeOrderDetailsModal();
                    else if (this.id === 'driverModal') closeDriverModal();
                }
            });
        });
    </script>
</body>
</html>