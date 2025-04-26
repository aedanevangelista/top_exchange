<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure user has access to Orders

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

// Get orders with 'For Delivery' status - Fix the SQL syntax error
$orders = [];
$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, 
        o.orders, o.total_amount, o.status, o.progress, o.driver_assigned, 
        IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name 
        FROM orders o 
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number 
        LEFT JOIN drivers d ON da.driver_id = d.id 
        WHERE o.status = 'For Delivery'
        ORDER BY o.delivery_date ASC";

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
        
        /* Debug section */
        .debug-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            display: none;
        }
        
        .debug-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .debug-info {
            font-family: monospace;
            white-space: pre-wrap;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            max-height: 300px;
            overflow: auto;
        }
        
        /* Form buttons */
        .form-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Username</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Delivery Address</th>
                        <th>Progress</th>
                        <th>Orders</th>
                        <th>Total Amount</th>
                        <th>Driver</th>
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
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                </td>
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
                                    <span class="status-badge status-for-delivery"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <button class="complete-delivery-btn" onclick="completeDelivery('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="no-orders">No orders ready for delivery.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Debug section - Remove in production or toggle with a admin-only flag -->
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
        <div class="debug-section">
            <div class="debug-title">Debug Information</div>
            <div class="debug-info">
                <?php 
                    echo "Total 'For Delivery' orders found: " . count($orders) . "\n";
                    echo "SQL Query: " . $sql . "\n\n";
                    
                    // Check what statuses exist in the database
                    $statusQuery = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
                    $statusResult = $conn->query($statusQuery);
                    
                    echo "Status counts in database:\n";
                    if ($statusResult && $statusResult->num_rows > 0) {
                        while($row = $statusResult->fetch_assoc()) {
                            echo "- " . $row['status'] . ": " . $row['count'] . "\n";
                        }
                    } else {
                        echo "No status data found\n";
                    }
                ?>
            </div>
        </div>
        <?php endif; ?>
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

        // Search functionality
        $(document).ready(function() {
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