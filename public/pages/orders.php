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
        
        /* New styles for quantity tracking */
        .item-progress-container {
            margin-top: 5px;
            display: none; /* Hidden by default */
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
        }
        
        .quantity-progress-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        
        .quantity-progress-table th, 
        .quantity-progress-table td {
            padding: 6px;
            border: 1px solid #dee2e6;
            text-align: center;
        }
        
        .quantity-progress-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .toggle-item-progress {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 12px;
        }
        
        .toggle-item-progress:hover {
            background-color: #0069d9;
        }
        
        .quantity-item-completed {
            background-color: #d4edda;
        }
        
        .quantity-checkbox-cell {
            text-align: center;
        }
        
        .quantity-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .item-progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 4px;
            height: 10px;
            margin-top: 5px;
            position: relative;
        }
        
        .item-progress-bar {
            height: 100%;
            background-color: #17a2b8;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .select-all-units {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .select-all-units:hover {
            background-color: #218838;
        }
        
        .toggle-row {
            background-color: #f8f9fa;
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
        let completedItems = [];
        let quantityProgressData = {};

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
                    completedItems = data.completedItems || [];
                    quantityProgressData = data.quantityProgressData || {};
                    
                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    
                    currentOrderItems.forEach((item, index) => {
                        const isCompleted = completedItems.includes(index);
                        
                        // Create main row for the item
                        const row = document.createElement('tr');
                        if (isCompleted) {
                            row.classList.add('completed-item');
                        }
                        
                        row.innerHTML = `
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td>
                                <div style="display: flex; align-items: center; justify-content: space-between">
                                    <input type="checkbox" class="item-status-checkbox" data-index="${index}" 
                                        ${isCompleted ? 'checked' : ''} onchange="updateRowStyle(this)">
                                    <button type="button" class="toggle-item-progress" data-index="${index}" onclick="toggleQuantityProgress(${index})">
                                        <i class="fas fa-list-ol"></i> Units
                                    </button>
                                </div>
                            </td>
                        `;
                        orderDetailsBody.appendChild(row);

                        // Create collapsible row for quantity tracking
                        const detailRow = document.createElement('tr');
                        detailRow.id = `quantity-details-${index}`;
                        detailRow.className = 'toggle-row';
                        detailRow.style.display = 'none';
                        
                        const itemQuantity = parseInt(item.quantity) || 0;
                        let unitProgressData = [];
                        
                        // Get saved progress data for this item if it exists
                        if (quantityProgressData[index]) {
                            unitProgressData = quantityProgressData[index];
                        } else {
                            // Initialize empty array for all units
                            for (let i = 0; i < itemQuantity; i++) {
                                unitProgressData.push(false);
                            }
                        }
                        
                        // Calculate what percentage of units are complete
                        const completedUnits = unitProgressData.filter(unit => unit === true).length;
                        const unitProgressPercent = itemQuantity > 0 ? Math.round((completedUnits / itemQuantity) * 100) : 0;
                        
                        // Create the quantity progress content
                        const detailCell = document.createElement('td');
                        detailCell.colSpan = 6;
                        detailCell.innerHTML = `
                            <div class="item-progress-container" id="item-progress-${index}">
                                <h4>Progress Tracking for ${item.item_description} <span>(${completedUnits} of ${itemQuantity} units complete)</span></h4>
                                <button type="button" class="select-all-units" onclick="selectAllUnits(${index}, ${itemQuantity})">
                                    Select All Units
                                </button>
                                <div class="item-progress-bar-container">
                                    <div class="item-progress-bar" style="width: ${unitProgressPercent}%"></div>
                                </div>
                                <table class="quantity-progress-table">
                                    <thead>
                                        <tr>
                                            <th>Unit #</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="quantity-progress-body-${index}">
                                        ${generateQuantityProgressRows(index, itemQuantity, unitProgressData)}
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        detailRow.appendChild(detailCell);
                        orderDetailsBody.appendChild(detailRow);
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

        function generateQuantityProgressRows(itemIndex, quantity, progressData) {
            let rows = '';
            for (let i = 0; i < quantity; i++) {
                const isUnitCompleted = progressData[i] === true;
                rows += `
                    <tr class="${isUnitCompleted ? 'quantity-item-completed' : ''}">
                        <td>Unit ${i + 1}</td>
                        <td class="quantity-checkbox-cell">
                            <input type="checkbox" class="quantity-checkbox" 
                                data-item-index="${itemIndex}" 
                                data-unit-index="${i}" 
                                ${isUnitCompleted ? 'checked' : ''}
                                onchange="updateUnitProgress(this)">
                        </td>
                    </tr>
                `;
            }
            return rows;
        }

        function toggleQuantityProgress(index) {
            const detailsRow = document.getElementById(`quantity-details-${index}`);
            detailsRow.style.display = detailsRow.style.display === 'none' ? 'table-row' : 'none';
        }

        function selectAllUnits(itemIndex, quantity) {
            // Select all checkboxes for this item
            const checkboxes = document.querySelectorAll(`.quantity-checkbox[data-item-index="${itemIndex}"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                updateUnitProgress(checkbox);
            });
        }

        function updateUnitProgress(checkbox) {
            const itemIndex = parseInt(checkbox.getAttribute('data-item-index'));
            const unitIndex = parseInt(checkbox.getAttribute('data-unit-index'));
            const isCompleted = checkbox.checked;
            
            // Update row styling
            const row = checkbox.closest('tr');
            if (isCompleted) {
                row.classList.add('quantity-item-completed');
            } else {
                row.classList.remove('quantity-item-completed');
            }
            
            // Initialize the item's progress data if not already present
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
                const quantity = parseInt(currentOrderItems[itemIndex].quantity) || 0;
                for (let i = 0; i < quantity; i++) {
                    quantityProgressData[itemIndex].push(false);
                }
            }
            
            // Update the progress data
            quantityProgressData[itemIndex][unitIndex] = isCompleted;
            
            // Update the progress bar
            updateItemProgressBar(itemIndex);
        }

        function updateItemProgressBar(itemIndex) {
            const itemQuantity = parseInt(currentOrderItems[itemIndex].quantity) || 0;
            if (itemQuantity === 0) return;
            
            // Count completed units
            const completedUnits = quantityProgressData[itemIndex].filter(unit => unit === true).length;
            const progressPercent = Math.round((completedUnits / itemQuantity) * 100);
            
            // Update the progress bar
            const progressBar = document.querySelector(`#item-progress-${itemIndex} .item-progress-bar`);
            progressBar.style.width = `${progressPercent}%`;
            
            // Update the header text
            const progressHeader = document.querySelector(`#item-progress-${itemIndex} h4 span`);
            progressHeader.textContent = `(${completedUnits} of ${itemQuantity} units complete)`;
            
            // If all units are complete, check the main checkbox
            const mainCheckbox = document.querySelector(`.item-status-checkbox[data-index="${itemIndex}"]`);
            if (completedUnits === itemQuantity) {
                mainCheckbox.checked = true;
                mainCheckbox.closest('tr').classList.add('completed-item');
            } else {
                mainCheckbox.checked = false;
                mainCheckbox.closest('tr').classList.remove('completed-item');
                
                // If this item was in the completed items list, remove it
                const completedIndex = completedItems.indexOf(itemIndex);
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
            }
        }

        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.getAttribute('data-index'));
            const row = checkbox.closest('tr');
            
            if (checkbox.checked) {
                row.classList.add('completed-item');
                if (!completedItems.includes(index)) {
                    completedItems.push(index);
                }
                
                // If checked, also mark all quantity items as complete
                const itemQuantity = parseInt(currentOrderItems[index].quantity) || 0;
                if (!quantityProgressData[index]) {
                    quantityProgressData[index] = [];
                }
                
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[index][i] = true;
                }
                
                // Update the quantity checkboxes
                const quantityCheckboxes = document.querySelectorAll(`.quantity-checkbox[data-item-index="${index}"]`);
                quantityCheckboxes.forEach(qCheckbox => {
                    qCheckbox.checked = true;
                    qCheckbox.closest('tr').classList.add('quantity-item-completed');
                });
                
                // Update progress bar
                updateItemProgressBar(index);
            } else {
                row.classList.remove('completed-item');
                const completedIndex = completedItems.indexOf(index);
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
            }
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function saveProgressChanges() {
            // Calculate overall progress percentage
            const progressPercentage = currentOrderItems.length > 0 
                ? Math.round((completedItems.length / currentOrderItems.length) * 100) 
                : 0;
            
            // Determine if the order should be completed automatically
            const shouldComplete = progressPercentage === 100;
            
            // Send AJAX request to update progress
            fetch('/backend/update_order_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    completed_items: completedItems,
                    quantity_progress_data: quantityProgressData,
                    progress: progressPercentage,
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