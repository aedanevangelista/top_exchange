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

// Modified query to only show Active orders
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status, progress FROM orders WHERE status = 'Active'";

// Order by order_date descending (latest orders first)
$sql .= " ORDER BY order_date DESC";

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
    <title>Orders</title>
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
        
        /* Fix for hover color on green rows */
        .completed-item:hover, 
        tr.completed-item:hover td,
        tr.unit-item.completed:hover,
        tr.unit-item.completed:hover td {
            background-color: #c3e6cb !important;
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
        
        .unit-row {
            display: none;
        }
        
        .unit-item {
            background-color: #f8f9fa;
        }
        
        .unit-item.completed {
            background-color: #d4edda !important;
        }
        
        .unit-item td {
            padding-left: 20px; /* Indent to show hierarchy */
            border-top: 1px dashed #dee2e6;
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
        
        .units-divider {
            border-top: 2px solid #6c757d;
            margin-top: -1px;
        }
        
        .unit-number-cell {
            width: 70px;
            font-weight: bold;
            color: #6c757d;
        }
        
        .item-header-row {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #dee2e6;
        }
        
        /* Add scrolling to the order details container */
        .order-details-container {
            max-height: 70vh; /* 70% of viewport height */
            overflow-y: auto;
            margin-bottom: 10px;
            padding-right: 5px;
        }
        
        /* Make sure the header stays visible */
        .order-details-table thead {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 10;
        }
        
        .order-details-table thead th {
            box-shadow: 0 1px 0 0 #dee2e6; /* Add bottom border that doesn't move */
        }
        
        /* Styles for item progress bar */
        .item-progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 4px;
            height: 12px;
            margin-top: 5px;
            position: relative;
            overflow: hidden;
        }
        
        .item-progress-bar {
            height: 100%;
            background-color: #17a2b8;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .item-progress-text {
            display: block;
            font-size: 11px;
            margin-top: 3px;
            color: #6c757d;
            text-align: center;
        }
        
        .status-cell {
            min-width: 120px;
        }
        
        /* Progress contribution text */
        .contribution-text {
            font-size: 11px;
            color: #6c757d;
            margin-top: 3px;
            display: block;
            text-align: center;
        }
        
        /* Progress percentage info */
        .item-progress-info {
            margin-top: 5px;
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 12px;
        }
        
        .progress-info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .progress-info-value {
            color: #28a745;
            font-weight: bold;
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

    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Orders Management</h1>
            <!-- Updated search section to exactly match order_history.php -->
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
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
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
                                    <span class="status-badge status-active"><?= htmlspecialchars($order['status']) ?></span>
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
                            <td colspan="9" class="no-orders">No orders found.</td>
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
                <!-- Overall progress info -->
                <div class="item-progress-info" id="overall-progress-info" style="margin-top: 10px;">
                    <div class="progress-info-label">Overall Order Progress:</div>
                    <div class="progress-bar-container" style="margin-top: 5px;">
                        <div class="progress-bar" id="overall-progress-bar" style="width: 0%"></div>
                        <div class="progress-text" id="overall-progress-text">0%</div>
                    </div>
                </div>
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
        let itemProgressPercentages = {};
        let itemContributions = {}; // How much each item contributes to the total
        let overallProgress = 0;

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
                    itemProgressPercentages = data.itemProgressPercentages || {};
                    
                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    
                    // Calculate item contributions to overall progress
                    const totalItems = currentOrderItems.length;
                    const contributionPerItem = totalItems > 0 ? (100 / totalItems) : 0;
                    
                    // Track overall progress
                    overallProgress = 0;
                    
                    currentOrderItems.forEach((item, index) => {
                        const isCompleted = completedItems.includes(index);
                        const itemQuantity = parseInt(item.quantity) || 0;
                        
                        // Store contribution percentage
                        itemContributions[index] = contributionPerItem;
                        
                        // Calculate item progress percentage based on units
                        let unitCompletedCount = 0;
                        if (quantityProgressData[index]) {
                            for (let i = 0; i < itemQuantity; i++) {
                                if (quantityProgressData[index][i] === true) {
                                    unitCompletedCount++;
                                }
                            }
                        }
                        
                        // Calculate unit progress percentage
                        const unitProgress = itemQuantity > 0 ? (unitCompletedCount / itemQuantity) * 100 : 0;
                        
                        // Calculate contribution to overall progress (what this item adds to overall)
                        const contributionToOverall = (unitProgress / 100) * contributionPerItem;
                        overallProgress += contributionToOverall;
                        
                        // Store item progress
                        itemProgressPercentages[index] = unitProgress;
                        
                        // Create main row for the item
                        const mainRow = document.createElement('tr');
                        mainRow.className = 'item-header-row';
                        if (isCompleted) {
                            mainRow.classList.add('completed-item');
                        }
                        mainRow.dataset.itemIndex = index;
                        
                        mainRow.innerHTML = `
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td class="status-cell">
                                <div style="display: flex; align-items: center; justify-content: space-between">
                                    <input type="checkbox" class="item-status-checkbox" data-index="${index}" 
                                        ${isCompleted ? 'checked' : ''} onchange="updateRowStyle(this)">
                                    <button type="button" class="toggle-item-progress" data-index="${index}" onclick="toggleQuantityProgress(${index})">
                                        <i class="fas fa-list-ol"></i> Units
                                    </button>
                                </div>
                                <div class="item-progress-bar-container">
                                    <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div>
                                </div>
                                <span class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}% Complete</span>
                                <span class="contribution-text" id="contribution-text-${index}">
                                    (${Math.round(contributionToOverall)}% of total)
                                </span>
                            </td>
                        `;
                        orderDetailsBody.appendChild(mainRow);
                        
                        // Add a divider row
                        const dividerRow = document.createElement('tr');
                        dividerRow.className = 'units-divider';
                        dividerRow.id = `units-divider-${index}`;
                        dividerRow.style.display = 'none';
                        dividerRow.innerHTML = `<td colspan="6"></td>`;
                        orderDetailsBody.appendChild(dividerRow);
                        
                        // Create rows for individual units
                        for (let i = 0; i < itemQuantity; i++) {
                            // Check if this unit is completed
                            const isUnitCompleted = quantityProgressData[index] && 
                                                    quantityProgressData[index][i] === true;
                            
                            const unitRow = document.createElement('tr');
                            unitRow.className = `unit-row unit-item unit-for-item-${index}`;
                            unitRow.style.display = 'none';
                            if (isUnitCompleted) {
                                unitRow.classList.add('completed');
                            }
                            
                            unitRow.innerHTML = `
                                <td>${item.category}</td>
                                <td>${item.item_description}</td>
                                <td>${item.packaging}</td>
                                <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                                <td class="unit-number-cell">Unit ${i+1}</td>
                                <td>
                                    <input type="checkbox" class="unit-status-checkbox" 
                                        data-item-index="${index}" 
                                        data-unit-index="${i}" 
                                        ${isUnitCompleted ? 'checked' : ''} 
                                        onchange="updateUnitStatus(this)">
                                </td>
                            `;
                            orderDetailsBody.appendChild(unitRow);
                        }
                        
                        // Add an action row with a "Select All" button
                        if (itemQuantity > 0) {
                            const actionRow = document.createElement('tr');
                            actionRow.className = `unit-row unit-action-row unit-for-item-${index}`;
                            actionRow.style.display = 'none';
                            actionRow.innerHTML = `
                                <td colspan="6" style="text-align: right; padding: 10px;">
                                    <button type="button" class="select-all-units" onclick="selectAllUnits(${index}, ${itemQuantity})">
                                        <i class="fas fa-check-square"></i> Select All Units
                                    </button>
                                </td>
                            `;
                            orderDetailsBody.appendChild(actionRow);
                        }
                    });
                    
                    // Update overall progress display
                    updateOverallProgressDisplay();
                    
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

        function updateOverallProgressDisplay() {
            const overallProgressBar = document.getElementById('overall-progress-bar');
            const overallProgressText = document.getElementById('overall-progress-text');
            
            // Round to nearest whole number
            const roundedProgress = Math.round(overallProgress);
            
            overallProgressBar.style.width = `${roundedProgress}%`;
            overallProgressText.textContent = `${roundedProgress}%`;
        }

        function toggleQuantityProgress(itemIndex) {
            const unitRows = document.querySelectorAll(`.unit-for-item-${itemIndex}`);
            const dividerRow = document.getElementById(`units-divider-${itemIndex}`);
            const isVisible = unitRows[0].style.display !== 'none';
            
            // Toggle divider
            dividerRow.style.display = isVisible ? 'none' : 'table-row';
            
            // Toggle unit rows
            unitRows.forEach(row => {
                row.style.display = isVisible ? 'none' : 'table-row';
            });
        }

        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.getAttribute('data-item-index'));
            const unitIndex = parseInt(checkbox.getAttribute('data-unit-index'));
            const isChecked = checkbox.checked;
            
            // Update unit row style
            const unitRow = checkbox.closest('tr');
            if (isChecked) {
                unitRow.classList.add('completed');
            } else {
                unitRow.classList.remove('completed');
            }
            
            // Initialize the quantityProgressData structure if needed
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
                const itemQuantity = parseInt(currentOrderItems[itemIndex].quantity) || 0;
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[itemIndex].push(false);
                }
            }
            
            // Update the progress data
            quantityProgressData[itemIndex][unitIndex] = isChecked;
            
            // Update item progress and contribution to overall
            updateItemProgress(itemIndex);
            
            // Update overall progress
            updateOverallProgress();
        }

        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex];
            const itemQuantity = parseInt(item.quantity) || 0;
            
            if (itemQuantity === 0) return;
            
            // Count completed units
            let completedUnits = 0;
            for (let i = 0; i < itemQuantity; i++) {
                if (quantityProgressData[itemIndex] && quantityProgressData[itemIndex][i]) {
                    completedUnits++;
                }
            }
            
            // Calculate unit progress percentage
            const unitProgress = (completedUnits / itemQuantity) * 100;
            itemProgressPercentages[itemIndex] = unitProgress;
            
            // Calculate contribution to overall progress
            const contributionToOverall = (unitProgress / 100) * itemContributions[itemIndex];
            
            // Update item progress display
            const progressBar = document.getElementById(`item-progress-bar-${itemIndex}`);
            const progressText = document.getElementById(`item-progress-text-${itemIndex}`);
            const contributionText = document.getElementById(`contribution-text-${itemIndex}`);
            
            progressBar.style.width = `${unitProgress}%`;
            progressText.textContent = `${Math.round(unitProgress)}% Complete`;
            contributionText.textContent = `(${Math.round(contributionToOverall)}% of total)`;
            
            // Check if all units are complete to update item checkbox
            updateItemStatusBasedOnUnits(itemIndex, completedUnits === itemQuantity);
        }

        function updateOverallProgress() {
            // Calculate overall progress from all items
            let newOverallProgress = 0;
            
            Object.keys(itemProgressPercentages).forEach(itemIndex => {
                const itemProgress = itemProgressPercentages[itemIndex];
                const itemContribution = itemContributions[itemIndex];
                newOverallProgress += (itemProgress / 100) * itemContribution;
            });
            
            overallProgress = newOverallProgress;
            updateOverallProgressDisplay();
            
            return Math.round(overallProgress);
        }

        function updateItemStatusBasedOnUnits(itemIndex, allComplete) {
            // Update the main item checkbox based on unit completion
            const mainCheckbox = document.querySelector(`.item-status-checkbox[data-index="${itemIndex}"]`);
            const mainRow = document.querySelector(`tr[data-item-index="${itemIndex}"]`);
            
            if (allComplete) {
                mainCheckbox.checked = true;
                mainRow.classList.add('completed-item');
                if (!completedItems.includes(parseInt(itemIndex))) {
                    completedItems.push(parseInt(itemIndex));
                }
            } else {
                mainCheckbox.checked = false;
                mainRow.classList.remove('completed-item');
                const completedIndex = completedItems.indexOf(parseInt(itemIndex));
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
            }
        }

        function selectAllUnits(itemIndex, quantity) {
            // Get all unit checkboxes for this item
            const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            
            // Check all unit checkboxes
            unitCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
                const unitRow = checkbox.closest('tr');
                unitRow.classList.add('completed');
            });
            
            // Update the progress data
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
            }
            
            for (let i = 0; i < quantity; i++) {
                quantityProgressData[itemIndex][i] = true;
            }
            
            // Update item progress
            updateItemProgress(itemIndex);
            
            // Update overall progress
            updateOverallProgress();
        }

        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.getAttribute('data-index'));
            const row = checkbox.closest('tr');
            const itemQuantity = parseInt(currentOrderItems[index].quantity) || 0;
            
            if (checkbox.checked) {
                row.classList.add('completed-item');
                if (!completedItems.includes(index)) {
                    completedItems.push(index);
                }
                
                // Mark all units as completed
                if (!quantityProgressData[index]) {
                    quantityProgressData[index] = [];
                }
                
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[index][i] = true;
                }
                
                // Update unit checkboxes and row styles
                const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${index}"]`);
                unitCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    const unitRow = checkbox.closest('tr');
                    unitRow.classList.add('completed');
                });
                
                // Set item progress to 100%
                itemProgressPercentages[index] = 100;
                
                // Update item display
                const progressBar = document.getElementById(`item-progress-bar-${index}`);
                const progressText = document.getElementById(`item-progress-text-${index}`);
                const contributionText = document.getElementById(`contribution-text-${index}`);
                
                progressBar.style.width = '100%';
                progressText.textContent = '100% Complete';
                contributionText.textContent = `(${Math.round(itemContributions[index])}% of total)`;
                
            } else {
                row.classList.remove('completed-item');
                const completedIndex = completedItems.indexOf(index);
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
                
                // Mark all units as not completed
                if (!quantityProgressData[index]) {
                    quantityProgressData[index] = [];
                }
                
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[index][i] = false;
                }
                
                // Update unit checkboxes and row styles
                const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${index}"]`);
                unitCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                    const unitRow = checkbox.closest('tr');
                    unitRow.classList.remove('completed');
                });
                
                // Set item progress to 0%
                itemProgressPercentages[index] = 0;
                
                // Update item display
                const progressBar = document.getElementById(`item-progress-bar-${index}`);
                const progressText = document.getElementById(`item-progress-text-${index}`);
                const contributionText = document.getElementById(`contribution-text-${index}`);
                
                progressBar.style.width = '0%';
                progressText.textContent = '0% Complete';
                contributionText.textContent = '(0% of total)';
            }
            
            // Update overall progress
            updateOverallProgress();
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function saveProgressChanges() {
            // Calculate overall progress percentage
            const progressPercentage = updateOverallProgress();
            
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
                    item_progress_percentages: itemProgressPercentages,
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

        // Document ready function for real-time searching
        $(document).ready(function() {
            // Search functionality - exact match to order_history.php
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