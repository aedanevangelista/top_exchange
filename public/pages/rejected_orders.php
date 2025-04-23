<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Use 'Rejected Orders' as the role permission name
checkRole('Rejected Orders');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php");
    exit();
}

// Fetch only rejected orders for display in the table
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status FROM orders WHERE status = 'Rejected'";

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
    <title>Rejected Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        .status-badge.status-rejected {
            background-color: #dc3545;
            color: white;
        }
        
        /* Raw Materials table styling */
        .raw-materials-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .raw-materials-container h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        
        .materials-table-container {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .materials-table th, 
        .materials-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .materials-table th {
            background-color: #f1f1f1;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .material-sufficient {
            color: #28a745;
            font-weight: bold;
        }
        
        .material-insufficient {
            color: #dc3545;
            font-weight: bold;
        }
        
        .materials-status {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-sufficient {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-insufficient {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Make modal larger to fit tables */
        #statusModal .modal-content {
            max-width: 700px;
            width: 90%;
        }
        
        /* Update the header style to match other ordering pages */
        .orders-header {
            background-color: #435ebe; /* Match the standard blue color */
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .status-btn {
            background-color: #17a2b8;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        
        .status-btn:hover {
            background-color: #138496;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Rejected Orders Management</h1>
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
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>')">
                                        <i class="fas fa-clipboard-list"></i> View Orders
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <span class="status-badge status-rejected"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <button class="status-btn" onclick="restoreOrder('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-undo"></i> Restore to Pending
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-orders">No rejected orders found.</td>
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

    <script src="/js/orders.js"></script>
    <script>
    // Function to view order details - reusing from the original orders.js
    window.viewOrderDetails = function(ordersJson) {
        try {
            const orders = JSON.parse(ordersJson);
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            orderDetailsBody.innerHTML = '';
            
            orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${order.category || 'N/A'}</td>
                    <td>${order.product_name || order.item_description || 'N/A'}</td>
                    <td>${order.packaging || 'N/A'}</td>
                    <td>PHP ${parseFloat(order.price).toFixed(2)}</td>
                    <td>${order.quantity}</td>
                `;
                orderDetailsBody.appendChild(row);
            });
            
            document.getElementById('orderDetailsModal').style.display = 'flex';
        } catch (error) {
            console.error("Error parsing orders JSON:", error);
            alert("Error displaying order details");
        }
    };
    
    // Function to close order details modal
    window.closeOrderDetailsModal = function() {
        document.getElementById('orderDetailsModal').style.display = 'none';
    };
    
    // Function to restore an order to Pending status
    window.restoreOrder = function(poNumber) {
        if (confirm("Are you sure you want to restore this order to Pending status?")) {
            $.ajax({
                type: 'POST',
                url: '/backend/restore_rejected_order.php',
                data: { 
                    po_number: poNumber
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message and reload
                        showToast(`Order ${poNumber} has been restored to Pending status.`, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Failed to restore order: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Failed to restore order. Please try again.');
                }
            });
        }
    };
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = document.createElement('i');
        if (type === 'success') {
            icon.className = 'fas fa-check-circle';
        } else if (type === 'error') {
            icon.className = 'fas fa-times-circle';
        } else if (type === 'info') {
            icon.className = 'fas fa-info-circle';
        }
        
        const text = document.createElement('span');
        text.textContent = message;
        
        toast.appendChild(icon);
        toast.appendChild(text);
        
        const toastContainer = document.getElementById('toast-container');
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toastContainer.removeChild(toast);
            }, 300);
        }, 3000);
    }
    </script>
</body>
</html>