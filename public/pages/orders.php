<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

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

// Handle status filter
$status_filter = $_GET['status'] ?? '';

// Modified query to only show Active and Rejected orders
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress FROM orders WHERE status IN ('Active', 'Rejected')";
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
}

// Modified ORDER BY clause to prioritize status (Active, then Rejected) and then delivery_date (ascending)
$sql .= " ORDER BY 
          CASE 
              WHEN status = 'Active' THEN 1 
              WHEN status = 'Rejected' THEN 2 
              ELSE 3 
          END, 
          delivery_date ASC";

$stmt = $conn->prepare($sql);
if (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
}
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
    <title>Orders Management</title>
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
        
        .completed-item {
            background-color: #d4edda !important;
        }
        
        .form-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .save-progress-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .save-progress-btn:hover {
            background-color: #45a049;
        }
        
        .back-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
        }
        
        .item-progress-select {
            width: 100%;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .item-progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 4px;
            height: 8px;
            margin-top: 5px;
            position: relative;
            overflow: hidden;
        }
        
        .item-progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .in-progress {
            background-color: #fff3cd !important;
        }
        
        .not-started {
            background-color: #f8f9fa !important;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Orders Management</h1>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <!-- Removed "Add New Order" button -->
        </div>
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Username</th>
                        <th>Delivery Date</th>
                        <th>Progress</th>
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
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>    
                                        View Order Status
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($order['status']) {
                                        case 'Active':
                                            $statusClass = 'status-active';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'status-rejected';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span>
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
                            <td colspan="8" class="no-orders">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Order Details Modal with Progress Tracking -->
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
                            <th>Status</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="save-progress-btn" onclick="saveProgressChanges()">
                    <i class="fas fa-save"></i> Save Progress
                </button>
            </div>
        </div>
    </div>
    
    <!-- Status Modal -->
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
                <button onclick="changeStatus('Pending')" class="modal-status-btn pending">
                    <i class="fas fa-clock"></i> Pending
                </button>
                <button onclick="changeStatus('Completed')" class="modal-status-btn complete">
                    <i class="fas fa-check-circle"></i> Complete
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closeStatusModal()" class="modal-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPoNumber = '';
        let currentOrderItems = [];
        let itemProgressData = []; // Store progress data for each item

        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            window.location.href = `/public/pages/orders.php${status ? '?status=' + status : ''}`;
        }

        function openStatusModal(poNumber, username) {
            currentPoNumber = poNumber;
            document.getElementById('statusMessage').textContent = `Change status for order ${poNumber} (${username})`;
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function changeStatus(status) {
            // Send AJAX request to update status
            fetch('/backend/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `po_number=${currentPoNumber}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully', 'success');
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error updating status: ' + data.message, 'error');
                }
                closeStatusModal();
            })
            .catch(error => {
                showToast('Error updating status: ' + error, 'error');
                closeStatusModal();
            });
        }

        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            
            // Fetch the order data and completion status
            fetch(`/backend/get_order_details.php?po_number=${poNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentOrderItems = data.orderItems;
                    itemProgressData = data.itemProgressData || [];
                    
                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    
                    currentOrderItems.forEach((item, index) => {
                        // Get completion status (backward compatibility)
                        const isCompleted = data.completedItems && data.completedItems.includes(index);
                        // Get item progress (if available) or set default
                        const itemProgress = itemProgressData[index] ? 
                                            itemProgressData[index].progress : 
                                            (isCompleted ? 100 : 0);
                        const row = document.createElement('tr');
                        
                        // Set row class based on progress
                        if (itemProgress === 100) {
                            row.classList.add('completed-item');
                        } else if (itemProgress > 0) {
                            row.classList.add('in-progress');
                        } else {
                            row.classList.add('not-started');
                        }
                        
                        row.innerHTML = `
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td>
                                <select class="item-progress-select" data-index="${index}" onchange="updateItemProgress(this)">
                                    <option value="0" ${itemProgress === 0 ? 'selected' : ''}>Not Started</option>
                                    <option value="25" ${itemProgress === 25 ? 'selected' : ''}>In Progress (25%)</option>
                                    <option value="50" ${itemProgress === 50 ? 'selected' : ''}>Halfway (50%)</option>
                                    <option value="75" ${itemProgress === 75 ? 'selected' : ''}>Nearly Done (75%)</option>
                                    <option value="100" ${itemProgress === 100 ? 'selected' : ''}>Completed (100%)</option>
                                </select>
                            </td>
                            <td>
                                <div class="item-progress-bar-container">
                                    <div class="item-progress-bar" style="width: ${itemProgress}%"></div>
                                </div>
                            </td>
                        `;
                        orderDetailsBody.appendChild(row);
                    });
                    
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

        function updateItemProgress(selectElement) {
            const progress = parseInt(selectElement.value);
            const index = parseInt(selectElement.getAttribute('data-index'));
            const row = selectElement.closest('tr');
            
            // Update progress bar
            const progressBar = row.querySelector('.item-progress-bar');
            progressBar.style.width = progress + '%';
            
            // Update row style based on progress
            row.classList.remove('completed-item', 'in-progress', 'not-started');
            if (progress === 100) {
                row.classList.add('completed-item');
            } else if (progress > 0) {
                row.classList.add('in-progress');
            } else {
                row.classList.add('not-started');
            }
            
            // Update progress data
            if (!itemProgressData[index]) {
                itemProgressData[index] = {};
            }
            itemProgressData[index].progress = progress;
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function saveProgressChanges() {
            // Calculate overall progress as average of all items
            let totalProgress = 0;
            const completedItems = [];
            
            // Collect all progress data from dropdowns
            const selectElements = document.querySelectorAll('.item-progress-select');
            selectElements.forEach(select => {
                const index = parseInt(select.getAttribute('data-index'));
                const progress = parseInt(select.value);
                
                if (!itemProgressData[index]) {
                    itemProgressData[index] = {};
                }
                itemProgressData[index].progress = progress;
                
                // For backward compatibility
                if (progress === 100) {
                    completedItems.push(index);
                }
                
                totalProgress += progress;
            });
            
            // Calculate overall percentage
            const overallProgress = Math.round(totalProgress / currentOrderItems.length);
            
            // Determine if the order should be completed automatically
            const shouldComplete = overallProgress === 100;
            
            // Send AJAX request to update progress
            fetch('/backend/update_order_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    completed_items: completedItems,  // For backward compatibility
                    item_progress_data: itemProgressData,  // New detailed progress data
                    progress: overallProgress,
                    auto_complete: shouldComplete
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (shouldComplete) {
                        showToast('Order completed successfully!', 'success');
                    } else {
                        showToast('Progress updated successfully', 'success');
                    }
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error updating progress: ' + data.message, 'error');
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
    </script>
</body>
</html>