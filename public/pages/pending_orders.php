<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

// Get current user information
$username = $_SESSION['admin_username'] ?? 'System';
$currentDateTime = date('Y-m-d H:i:s');

// Fetch only pending orders
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status FROM orders WHERE status = 'Pending' ORDER BY delivery_date ASC";

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
            <div class="timestamp">
                Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo date('Y-m-d H:i:s'); ?><br>
                Current User's Login: <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
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
                                    <span class="status-badge status-pending">Pending</span>
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

    <!-- Modified Status Modal (Only Accept/Reject options) -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="status-buttons">
                <button onclick="changeStatus('Active')" class="modal-status-btn active">
                    <i class="fas fa-check"></i> Accept
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

    <script>
        // Configure toast notifications
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <div class="message">${message}</div>
                </div>
                <i class="fas fa-times close" onclick="this.parentElement.remove()"></i>
            `;
            document.getElementById('toast-container').appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 5000);
        }
        
        // Function to filter orders by status
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            window.location.href = `/public/pages/pending_orders.php${status ? '?status=' + status : ''}`;
        }
        
        // Variables to store current PO number and username
        let currentPO = '';
        let currentUsername = '';
        
        // Function to open status change modal
        function openStatusModal(poNumber, username) {
            currentPO = poNumber;
            currentUsername = username;
            
            document.getElementById('statusMessage').textContent = `Change status for order ${poNumber} (${username})`;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        // Function to close status modal
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Function to change order status
        function changeStatus(newStatus) {
            if (!currentPO) return;
            
            // Send AJAX request to update status
            fetch('/backend/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `po_number=${encodeURIComponent(currentPO)}&status=${encodeURIComponent(newStatus)}&timestamp=${encodeURIComponent('<?php echo $currentDateTime; ?>')}&username=${encodeURIComponent('<?php echo $username; ?>')}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Order status updated to ${newStatus}`, 'success');
                    // Reload page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error updating status', 'error');
                }
                closeStatusModal();
            })
            .catch(error => {
                showToast('Error communicating with server', 'error');
                console.error('Error:', error);
                closeStatusModal();
            });
        }
        
        // Function to view order details
        function viewOrderDetails(ordersJSON) {
            try {
                const orders = JSON.parse(ordersJSON);
                const tbody = document.getElementById('orderDetailsBody');
                tbody.innerHTML = '';
                
                orders.forEach(order => {
                    const row = document.createElement('tr');
                    
                    row.innerHTML = `
                        <td>${order.category || ''}</td>
                        <td>${order.item_description || ''}</td>
                        <td>${order.packaging || ''}</td>
                        <td>PHP ${parseFloat(order.price).toFixed(2)}</td>
                        <td>${order.quantity || '0'}</td>
                    `;
                    
                    tbody.appendChild(row);
                });
                
                document.getElementById('orderDetailsModal').style.display = 'block';
            } catch (e) {
                showToast('Error parsing order details', 'error');
                console.error('Error parsing order details:', e);
            }
        }
        
        // Function to close order details modal
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        // Close modals if user clicks outside
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusModal');
            const orderDetailsModal = document.getElementById('orderDetailsModal');
            
            if (event.target === statusModal) {
                closeStatusModal();
            }
            
            if (event.target === orderDetailsModal) {
                closeOrderDetailsModal();
            }
        };
    </script>
</body>
</html>