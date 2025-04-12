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
            <h1>Pending Orders Management</h1>
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
                                <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
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

    <!-- Rest of the HTML content remains unchanged... -->

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
    
    <!-- Modified Status Modal - Removed Pending and Complete buttons -->
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
    
    <!-- Add custom script to restrict delivery date selection -->
    <script>
        $(document).ready(function() {
            // Initialize datepickers
            $("#order_date").datepicker({
                dateFormat: 'yy-mm-dd',
                maxDate: 0 // Can't select future dates for order date
            });
            
            // Set order date to current date
            $("#order_date").datepicker("setDate", new Date());
            
            // Define your delivery days (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
            // Based on your example, deliveries happen on days 2 (Tuesday) and 4 (Thursday)
            const deliveryDays = [2, 4]; // Tuesday and Thursday
            
            // Initialize delivery date picker with custom delivery days
            $("#delivery_date").datepicker({
                dateFormat: 'yy-mm-dd',
                beforeShowDay: function(date) {
                    // Get the day of the week (0-6)
                    const dayOfWeek = date.getDay();
                    
                    // Check if this day is in our delivery days array
                    const isDeliveryDay = deliveryDays.includes(dayOfWeek);
                    
                    // Create a date object for today
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    // For comparing dates only (ignoring time)
                    const dateOnly = new Date(date);
                    dateOnly.setHours(0, 0, 0, 0);
                    
                    // Get the most recent delivery date based on the current date
                    const mostRecentDeliveryDate = getMostRecentDeliveryDate(today);
                    
                    // Get the next scheduled delivery date
                    const nextDeliveryDate = getNextDeliveryDate(mostRecentDeliveryDate);
                    
                    // For the selected date to be valid:
                    // 1. It must be a delivery day
                    // 2. It must be after the most recent delivery date
                    // 3. It must be the next delivery date or later
                    
                    // Compare if the date is at least the next delivery date
                    const isAfterNextDeliveryDate = dateOnly >= nextDeliveryDate;
                    
                    // Return [isSelectable, cssClass, tooltip]
                    return [isDeliveryDay && isAfterNextDeliveryDate, isDeliveryDay ? 'delivery-day' : ''];
                }
            });
            
            // Function to get the most recent delivery date based on today
            function getMostRecentDeliveryDate(today) {
                // Clone the date object to avoid modifying the original
                const date = new Date(today);
                const currentDay = date.getDay();
                
                // Sort delivery days to find the closest previous one
                const sortedDays = [...deliveryDays].sort((a, b) => a - b);
                
                // Find the most recent delivery day
                let recentDeliveryDay = null;
                
                // Check if today is a delivery day
                if (sortedDays.includes(currentDay)) {
                    // If today is a delivery day, it's the most recent
                    recentDeliveryDay = currentDay;
                } else {
                    // Find the most recent delivery day before today
                    for (let i = sortedDays.length - 1; i >= 0; i--) {
                        if (sortedDays[i] < currentDay) {
                            recentDeliveryDay = sortedDays[i];
                            break;
                        }
                    }
                    
                    // If no delivery day was found before today, use the last one in the week (wrap around)
                    if (recentDeliveryDay === null) {
                        recentDeliveryDay = sortedDays[sortedDays.length - 1];
                        // Adjust the date to previous week
                        date.setDate(date.getDate() - (7 - (sortedDays[sortedDays.length - 1] - currentDay)));
                    } else {
                        // Adjust the date to the recent delivery day
                        date.setDate(date.getDate() - (currentDay - recentDeliveryDay));
                    }
                }
                
                return date;
            }
            
            // Function to get the next delivery date after a given date
            function getNextDeliveryDate(date) {
                // Clone the date object to avoid modifying the original
                const nextDate = new Date(date);
                const currentDay = nextDate.getDay();
                
                // Sort delivery days to find the next one
                const sortedDays = [...deliveryDays].sort((a, b) => a - b);
                
                // Find the next delivery day
                let nextDeliveryDay = null;
                
                // Find the next delivery day after the current one
                for (let i = 0; i < sortedDays.length; i++) {
                    if (sortedDays[i] > currentDay) {
                        nextDeliveryDay = sortedDays[i];
                        break;
                    }
                }
                
                // If no delivery day was found after the current day, use the first one in the next week
                if (nextDeliveryDay === null) {
                    nextDeliveryDay = sortedDays[0];
                    // Adjust the date to next week
                    nextDate.setDate(nextDate.getDate() + (7 - currentDay + nextDeliveryDay));
                } else {
                    // Adjust the date to the next delivery day
                    nextDate.setDate(nextDate.getDate() + (nextDeliveryDay - currentDay));
                }
                
                return nextDate;
            }
            
            // Add some custom styling for delivery days
            $("<style>")
                .prop("type", "text/css")
                .html(`
                    .delivery-day a.ui-state-default {
                        background-color: #e6f7ff !important;
                        font-weight: bold !important;
                    }
                    .ui-datepicker-current-day a.ui-state-default {
                        background-color: #4aa3df !important;
                        color: white !important;
                    }
                `)
                .appendTo("head");
            
            // Add validation for form submission
            $("#addOrderForm").on("submit", function(e) {
                const deliveryDate = new Date($("#delivery_date").val());
                
                // Create Date objects for today and tomorrow
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                // Get the most recent delivery date
                const mostRecentDeliveryDate = getMostRecentDeliveryDate(today);
                
                // Get the next scheduled delivery date
                const nextDeliveryDate = getNextDeliveryDate(mostRecentDeliveryDate);
                
                // Check if the selected date is at least the next delivery date
                if (deliveryDate < nextDeliveryDate) {
                    e.preventDefault();
                    
                    const nextDeliveryDateStr = $.datepicker.formatDate('yy-mm-dd', nextDeliveryDate);
                    showToast(`Delivery date must be the next available delivery date (${nextDeliveryDateStr}) or later.`, "error");
                    return false;
                }
                
                // Check if the selected date is a valid delivery day
                const dayOfWeek = deliveryDate.getDay();
                if (!deliveryDays.includes(dayOfWeek)) {
                    e.preventDefault();
                    showToast("Selected date is not a valid delivery day.", "error");
                    return false;
                }
                
                return true;
            });
        });
        
        // Existing functions from the original code
        let currentPoNumber = '';

        function openStatusModal(poNumber, username) {
            currentPoNumber = poNumber;
            document.getElementById('statusMessage').textContent = `Change order-status for ${poNumber} (${username})`;
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function changeStatus(status) {
            // Create form data
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);

            // Send AJAX request to update status
            fetch('/backend/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast(`Changed status for ${currentPoNumber} to ${status}.`, 'success');
                    
                    // Wait a moment for the toast to be visible before reloading
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showToast('Failed to change status: ' + (data.error || 'Unknown error'), 'error');
                }
                closeStatusModal();
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to change status. Please try again.', 'error');
                closeStatusModal();
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

        // Add a toast container if not already present
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('toast-container')) {
                const toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
            }
        });
    </script>
</body>
</html>