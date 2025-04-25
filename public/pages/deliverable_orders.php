<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Deliverable Orders'); // Ensure the user has access to the Deliverable Orders page

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'delivery_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'delivery_date', 'progress', 'total_amount'];
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
               o.status, o.progress, o.driver_assigned
        FROM orders o
        WHERE o.status = 'Active' AND o.progress = 100";

// Add sorting
$sql .= " ORDER BY {$sort_column} {$sort_direction}";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get driver information if assigned
        $driver_info = null;
        if ($row['driver_assigned']) {
            $driver_query = "SELECT d.id, d.name, d.area, d.current_deliveries, da.status as assignment_status 
                            FROM driver_assignments da 
                            JOIN drivers d ON da.driver_id = d.id 
                            WHERE da.po_number = ?";
            $stmt = $conn->prepare($driver_query);
            $stmt->bind_param("s", $row['po_number']);
            $stmt->execute();
            $driver_result = $stmt->get_result();
            if ($driver_result->num_rows > 0) {
                $driver_info = $driver_result->fetch_assoc();
            }
            $stmt->close();
        }
        
        $row['driver_info'] = $driver_info;
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
        /* Consistent styling with other order pages */
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        /* Search Container Styling (like in order_history.php) */
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
            background-color: rgb(51, 51, 51);
        }

        th.sortable .fa-sort-up,
        th.sortable .fa-sort-down {
            color: rgb(255, 255, 255);
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
        }
        
        .status-active {
            background-color: #2196F3;
        }
        
        .status-pending {
            background-color: #FFC107;
        }
        
        .status-completed {
            background-color: #4CAF50;
        }
        
        .status-rejected {
            background-color: #F44336;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn:hover {
            opacity: 0.9;
        }
        
        .view-btn {
            background-color: #2196F3;
        }
        
        .complete-btn {
            background-color: #4CAF50;
        }
        
        .assign-btn {
            background-color: #FF9800;
        }
        
        /* Driver badge */
        .driver-badge {
            padding: 6px 10px;
            border-radius: 4px;
            background-color: #e3f2fd;
            color: #0d47a1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            font-size: 14px;
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
        
        /* Filter controls */
        .filter-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-select {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        
        .filter-label {
            font-size: 14px;
            margin-right: 5px;
        }
        
        /* Order details row */
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
        
        /* Assignment Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
            max-width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #555;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .form-address {
            background-color: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .modal-cancel-btn {
            background-color: #f1f1f1;
            color: #333;
        }
        
        .modal-submit-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        /* Driver select styling */
        #driverSelect {
            padding: 8px 12px;
            width: 100%;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .driver-info-display {
            margin-top: 10px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            display: none;
        }
        
        .delivery-count {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
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
        
        /* View details button */
        .view-details-btn {
            background: none;
            border: none;
            color: #2196F3;
            text-decoration: underline;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
        }
        
        .view-details-btn:hover {
            color: #0b7dda;
        }
        
        /* Progress bar styling (consistent with orders.php) */
        .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 4px;
            height: 20px;
            position: relative;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 4px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .progress-text {
            color: white;
            font-size: 12px;
            font-weight: bold;
            position: absolute;
            width: 100%;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Deliverable Orders</h1>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
        <div class="filter-controls">
            <span class="filter-label">Filter by:</span>
            <select id="areaFilter" class="filter-select">
                <option value="">All Areas</option>
                <option value="North">North</option>
                <option value="South">South</option>
            </select>
            
            <select id="assignmentFilter" class="filter-select">
                <option value="">All Assignments</option>
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
                        <th>Progress</th>
                        <th>Driver Assignment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php 
                                $driver_info = $order['driver_info']; 
                                $is_assigned = !empty($driver_info);
                            ?>
                            <tr class="order-row" 
                                data-po="<?= htmlspecialchars($order['po_number']) ?>" 
                                data-driver-assigned="<?= $is_assigned ? 'yes' : 'no' ?>" 
                                data-area="<?= htmlspecialchars($driver_info['area'] ?? '') ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($order['delivery_date']))) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-details-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-eye"></i> View Items
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_assigned): ?>
                                        <div class="driver-badge area-<?= strtolower($driver_info['area']) ?>">
                                            <i class="fas fa-user-circle"></i> 
                                            <?= htmlspecialchars($driver_info['name']) ?>
                                            <span class="area-badge"><?= htmlspecialchars($driver_info['area']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <button class="action-btn assign-btn" onclick="openAssignmentModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['delivery_address']) ?>')">
                                            <i class="fas fa-user-plus"></i> Assign Driver
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="action-btn view-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($is_assigned): ?>
                                        <button class="action-btn complete-btn" onclick="confirmDelivery('<?= htmlspecialchars($order['po_number']) ?>', <?= (int)$driver_info['id'] ?>)">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
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
    <div id="driverAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-truck"></i> Assign Driver</h3>
                <span class="close" onclick="closeAssignmentModal()">&times;</span>
            </div>
            <form id="assignDriverForm">
                <input type="hidden" id="assignOrderPoNumber" name="po_number">
                
                <div class="form-group">
                    <label for="delivery_address_display">Delivery Address:</label>
                    <div id="delivery_address_display" class="form-address"></div>
                </div>
                
                <div class="form-group">
                    <label for="driverSelect">Select Driver:</label>
                    <select id="driverSelect" name="driver_id" class="form-control" required>
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
                            <option value="<?= $driver['id'] ?>" 
                                    data-name="<?= htmlspecialchars($driver['name']) ?>"
                                    data-area="<?= htmlspecialchars($driver['area']) ?>"
                                    data-deliveries="<?= $driver['current_deliveries'] ?>">
                                <?= htmlspecialchars($driver['name']) ?> - 
                                <?= htmlspecialchars($driver['area']) ?> 
                                (<?= $driver['current_deliveries'] ?>/20)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="driverDetails" class="driver-info-display">
                        <div class="driver-badge">
                            <i class="fas fa-user-circle"></i> 
                            <span id="selectedDriverName"></span>
                            <span class="area-badge" id="selectedDriverArea"></span>
                        </div>
                        <div style="margin-top: 5px;">
                            Delivery load: <span id="selectedDriverDeliveries" class="delivery-count"></span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-cancel-btn" onclick="closeAssignmentModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn modal-submit-btn">
                        <i class="fas fa-check"></i> Assign Driver
                    </button>
                </div>
            </form>
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
                                        <div>${item.category} / ${item.packaging || 'N/A'}</div>
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
                    const driverName = selectedOption.getAttribute('data-name');
                    const driverArea = selectedOption.getAttribute('data-area');
                    const driverDeliveries = selectedOption.getAttribute('data-deliveries');
                    
                    document.getElementById('selectedDriverName').textContent = driverName;
                    document.getElementById('selectedDriverArea').textContent = driverArea;
                    
                    const deliveryCountEl = document.getElementById('selectedDriverDeliveries');
                    deliveryCountEl.textContent = `${driverDeliveries}/20`;
                    
                    // Set the color class based on delivery count
                    deliveryCountEl.className = 'delivery-count';
                    if (parseInt(driverDeliveries) > 15) {
                        deliveryCountEl.classList.add('delivery-count-high');
                    } else if (parseInt(driverDeliveries) > 10) {
                        deliveryCountEl.classList.add('delivery-count-medium');
                    } else {
                        deliveryCountEl.classList.add('delivery-count-low');
                    }
                    
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
                
                // Check area filter
                if (areaFilter && showRow) {
                    const rowArea = row.dataset.area.toLowerCase();
                    if (rowArea !== areaFilter) {
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