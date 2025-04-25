<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Deliverable Orders'); // Ensure the user has access to the Deliverable Orders page

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'delivery_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'delivery_date', 'progress', 'total_amount', 'company'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'delivery_date'; // Default sort column
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'ASC'; // Default to ascending for delivery dates
}

// Fetch active orders that are ready for delivery (progress = 100%)
$orders = []; 
$sql = "SELECT o.po_number, o.username, o.company, o.delivery_date, o.delivery_address, o.total_amount, 
               o.status, o.progress, o.driver_assigned, da.id as assignment_id, da.driver_id, d.name as driver_name
        FROM orders o
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number
        LEFT JOIN drivers d ON da.driver_id = d.id
        WHERE o.status = 'Active' AND o.progress = 100";

// Add sorting
$sql .= " ORDER BY {$sort_column} {$sort_direction}";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Fetch all available drivers (where availability = 'Available' AND current_deliveries < 20)
$drivers = [];
$sql = "SELECT id, name, area, current_deliveries FROM drivers 
        WHERE availability = 'Available' AND current_deliveries < 20
        ORDER BY name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
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
    <title>Deliverable Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        .driver-select {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            min-width: 200px;
        }
        
        .assign-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .assign-btn:hover {
            background-color: #45a049;
        }
        
        .driver-badge {
            padding: 6px 12px;
            border-radius: 4px;
            background-color: #e3f2fd;
            color: #0d47a1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .driver-badge .area-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            background-color: #bbdefb;
        }
        
        .area-north {
            border-left: 3px solid #2196F3; /* Blue for North */
        }
        
        .area-south {
            border-left: 3px solid #FF9800; /* Orange for South */
        }
        
        .driver-details span {
            display: block;
            font-size: 12px;
            margin-top: 3px;
            color: #666;
        }
        
        .order-details-row {
            background-color: #f9f9f9;
            display: none;
        }
        
        .order-details-content {
            padding: 15px;
        }
        
        .order-details-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .order-details-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        
        .order-details-list li:last-child {
            border-bottom: none;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .view-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .view-btn:hover {
            background-color: #0b7dda;
        }
        
        .view-details-btn {
            background: none;
            border: none;
            color: #2196F3;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            font-size: 14px;
        }
        
        .driver-status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .driver-available {
            background-color: #4CAF50;
        }
        
        .driver-near-limit {
            background-color: #FFC107;
        }
        
        .driver-busy {
            background-color: #F44336;
        }
        
        .delivery-count {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            text-align: center;
        }

        .delivery-count-low {
            background-color: #d4edda;
            color: #155724;
        }

        .delivery-count-medium {
            background-color: #fff3cd;
            color: #856404;
        }

        .delivery-count-high {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .filter-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-select {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .search-container {
            display: flex;
            align-items: center;
            margin-left: auto;
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
        
        .deliverable-orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        /* Assignment Modal */
        .assignment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .assignment-modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
            max-width: 90%;
        }
        
        .assignment-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .assignment-modal-header h3 {
            margin: 0;
        }
        
        .assignment-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
        }
        
        .assignment-modal-body {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .cancel-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .driver-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .driver-option-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .driver-option-details {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="deliverable-orders-header">
            <h1>Deliverable Orders</h1>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
        <div class="filter-controls">
            <select id="areaFilter" class="filter-select">
                <option value="">Filter by Area</option>
                <option value="North">North</option>
                <option value="South">South</option>
            </select>
            
            <select id="assignmentFilter" class="filter-select">
                <option value="">Filter by Assignment</option>
                <option value="assigned">Assigned</option>
                <option value="unassigned">Unassigned</option>
            </select>
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
                                Company <?= getSortIcon('company', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">
                                Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Delivery Address</th>
                        <th>Order Details</th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">
                                Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Driver Assignment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row" data-po="<?= htmlspecialchars($order['po_number']) ?>" data-driver-assigned="<?= $order['driver_assigned'] ? 'yes' : 'no' ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['company'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-details-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-eye"></i> View Items
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if ($order['driver_assigned'] && $order['driver_id']): ?>
                                        <div class="driver-badge area-<?= strtolower($order['area'] ?? 'north') ?>">
                                            <i class="fas fa-user-circle"></i> 
                                            <?= htmlspecialchars($order['driver_name']) ?>
                                            <span class="area-badge"><?= htmlspecialchars($order['area'] ?? 'N/A') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="driver-assignment">
                                            <button class="assign-btn" onclick="openAssignmentModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['delivery_address']) ?>')">
                                                <i class="fas fa-user-plus"></i> Assign Driver
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="action-btn view-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($order['driver_assigned'] && $order['driver_id']): ?>
                                        <button class="action-btn assign-btn" onclick="confirmDelivery('<?= htmlspecialchars($order['po_number']) ?>', '<?= (int)$order['driver_id'] ?>')">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Order Details Row (initially hidden) -->
                            <tr id="order-details-<?= htmlspecialchars($order['po_number']) ?>" class="order-details-row">
                                <td colspan="9">
                                    <div class="order-details-content">
                                        <h3>Order Items</h3>
                                        <div id="order-items-<?= htmlspecialchars($order['po_number']) ?>">
                                            Loading order items...
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-orders">No deliverable orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>
    
    <!-- Driver Assignment Modal -->
    <div id="driverAssignmentModal" class="assignment-modal">
        <div class="assignment-modal-content">
            <div class="assignment-modal-header">
                <h3><i class="fas fa-truck"></i> Assign Driver</h3>
                <button class="assignment-modal-close" onclick="closeAssignmentModal()">&times;</button>
            </div>
            <div class="assignment-modal-body">
                <form id="assignDriverForm">
                    <input type="hidden" id="assignOrderPoNumber" name="po_number">
                    
                    <div class="form-group">
                        <label for="delivery_address_display">Delivery Address:</label>
                        <div id="delivery_address_display" style="padding: 8px; background-color: #f5f5f5; border-radius: 4px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="driverSelect">Select Driver:</label>
                        <select id="driverSelect" name="driver_id" class="driver-select" required>
                            <option value="">-- Select a Driver --</option>
                            <?php foreach ($drivers as $driver): 
                                // Determine class based on delivery count
                                $loadClass = 'driver-available';
                                if ($driver['current_deliveries'] > 15) {
                                    $loadClass = 'driver-busy';
                                } else if ($driver['current_deliveries'] > 10) {
                                    $loadClass = 'driver-near-limit';
                                }
                            ?>
                                <option value="<?= $driver['id'] ?>" data-area="<?= htmlspecialchars($driver['area']) ?>">
                                    <div class="driver-option">
                                        <div class="driver-option-name">
                                            <span class="driver-status-indicator <?= $loadClass ?>"></span>
                                            <?= htmlspecialchars($driver['name']) ?>
                                        </div>
                                        <div class="driver-option-details">
                                            <?= htmlspecialchars($driver['area']) ?> | <?= $driver['current_deliveries'] ?>/20
                                        </div>
                                    </div>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="driverDetails" style="margin-top: 10px; display: none;">
                            <div class="driver-badge" id="selectedDriverBadge">
                                <i class="fas fa-user-circle"></i> 
                                <span id="selectedDriverName"></span>
                                <span class="area-badge" id="selectedDriverArea"></span>
                            </div>
                            <div style="margin-top: 5px;">
                                <span id="selectedDriverDeliveries" class="delivery-count"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="closeAssignmentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-check"></i> Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentPoNumber = '';
        
        function openAssignmentModal(poNumber, deliveryAddress) {
            currentPoNumber = poNumber;
            document.getElementById('assignOrderPoNumber').value = poNumber;
            document.getElementById('delivery_address_display').textContent = deliveryAddress;
            document.getElementById('driverAssignmentModal').style.display = 'flex';
        }
        
        function closeAssignmentModal() {
            document.getElementById('driverAssignmentModal').style.display = 'none';
            document.getElementById('assignDriverForm').reset();
            document.getElementById('driverDetails').style.display = 'none';
        }
        
        function viewOrderDetails(poNumber) {
            const detailsRow = document.getElementById(`order-details-${poNumber}`);
            const isVisible = detailsRow.style.display === 'table-row';
            
            // Toggle visibility
            detailsRow.style.display = isVisible ? 'none' : 'table-row';
            
            if (!isVisible) {
                // Fetch order details only if we're showing the row
                fetchOrderItems(poNumber);
            }
        }
        
        function fetchOrderItems(poNumber) {
            const itemsContainer = document.getElementById(`order-items-${poNumber}`);
            itemsContainer.innerHTML = '<p>Loading order items...</p>';
            
            // Fetch the order items from the server
            fetch(`/backend/get_order_items.php?po_number=${poNumber}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.items) {
                        let html = '<ul class="order-details-list">';
                        
                        data.items.forEach(item => {
                            html += `
                                <li>
                                    <div>
                                        <strong>${item.item_description}</strong>
                                        <div>Packaging: ${item.packaging || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div>Quantity: ${item.quantity}</div>
                                        <div>Price: PHP ${parseFloat(item.price).toFixed(2)}</div>
                                    </div>
                                </li>
                            `;
                        });
                        
                        html += '</ul>';
                        itemsContainer.innerHTML = html;
                    } else {
                        itemsContainer.innerHTML = '<p>No items found for this order</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching order items:', error);
                    itemsContainer.innerHTML = '<p>Error loading order items. Please try again.</p>';
                });
        }
        
        function confirmDelivery(poNumber, driverId) {
            if (confirm('Mark this order as completed? This will update the driver\'s delivery count.')) {
                completeOrder(poNumber, driverId);
            }
        }
        
        function completeOrder(poNumber, driverId) {
            // Send request to complete the order
            fetch('/backend/complete_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `po_number=${poNumber}&driver_id=${driverId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Order completed successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error, 'error');
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Handle driver select change
            document.getElementById('driverSelect').addEventListener('change', function() {
                const driverDetails = document.getElementById('driverDetails');
                const selectedOption = this.options[this.selectedIndex];
                
                if (this.value) {
                    // Show driver details
                    const driverArea = selectedOption.getAttribute('data-area');
                    const driverName = selectedOption.text.trim();
                    const deliveryCount = driverName.match(/(\d+)\/20/)[1];
                    
                    document.getElementById('selectedDriverName').textContent = driverName.replace(/\s*\|\s*\d+\/20$/, '');
                    document.getElementById('selectedDriverArea').textContent = driverArea;
                    
                    const deliveryCountEl = document.getElementById('selectedDriverDeliveries');
                    deliveryCountEl.textContent = `${deliveryCount}/20 Deliveries`;
                    
                    // Set the color class based on delivery count
                    deliveryCountEl.className = 'delivery-count';
                    if (parseInt(deliveryCount) > 15) {
                        deliveryCountEl.classList.add('delivery-count-high');
                    } else if (parseInt(deliveryCount) > 10) {
                        deliveryCountEl.classList.add('delivery-count-medium');
                    } else {
                        deliveryCountEl.classList.add('delivery-count-low');
                    }
                    
                    // Update badge border color based on area
                    document.getElementById('selectedDriverBadge').className = 
                        `driver-badge area-${driverArea.toLowerCase()}`;
                    
                    driverDetails.style.display = 'block';
                } else {
                    driverDetails.style.display = 'none';
                }
            });
            
            // Handle assignment form submission
            document.getElementById('assignDriverForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const poNumber = document.getElementById('assignOrderPoNumber').value;
                const driverId = document.getElementById('driverSelect').value;
                
                if (!driverId) {
                    showToast('Please select a driver', 'error');
                    return;
                }
                
                // Send request to assign driver to order
                fetch('/backend/assign_driver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `po_number=${poNumber}&driver_id=${driverId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Driver assigned successfully!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error: ' + error, 'error');
                });
            });
            
            // Filter functionality
            document.getElementById('areaFilter').addEventListener('change', filterOrders);
            document.getElementById('assignmentFilter').addEventListener('change', filterOrders);
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                filterOrders();
            });
            
            document.querySelector('.search-btn').addEventListener('click', function() {
                filterOrders();
            });
        });
        
        function filterOrders() {
            const areaFilter = document.getElementById('areaFilter').value.toLowerCase();
            const assignmentFilter = document.getElementById('assignmentFilter').value;
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('tr.order-row');
            
            rows.forEach(row => {
                const detailsRow = document.getElementById(`order-details-${row.dataset.po}`);
                let showRow = true;
                
                // Check assignment filter
                if (assignmentFilter) {
                    const isAssigned = row.dataset.driverAssigned === 'yes';
                    if ((assignmentFilter === 'assigned' && !isAssigned) || 
                        (assignmentFilter === 'unassigned' && isAssigned)) {
                        showRow = false;
                    }
                }
                
                // Check area filter (using driver badge info)
                if (areaFilter && showRow) {
                    const driverBadge = row.querySelector('.driver-badge');
                    if (driverBadge) {
                        // Check if the driver badge contains the area class
                        if (!driverBadge.classList.contains(`area-${areaFilter}`)) {
                            showRow = false;
                        }
                    } else {
                        // If no driver assigned yet, hide if filtering by area
                        showRow = false;
                    }
                }
                
                // Check search text
                if (searchText && showRow) {
                    const rowText = row.textContent.toLowerCase();
                    if (!rowText.includes(searchText)) {
                        showRow = false;
                    }
                }
                
                // Show or hide rows
                row.style.display = showRow ? 'table-row' : 'none';
                
                // Always hide details row when filtering
                if (detailsRow) {
                    detailsRow.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>