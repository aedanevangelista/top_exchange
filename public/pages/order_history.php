<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Order History'); // Updated from 'Transaction History'

// Fetch completed orders for display
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, orders, total_amount, status 
        FROM orders 
        WHERE status = 'Completed'
        ORDER BY order_date DESC";
$result = $conn->query($sql);
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
    <title>Transaction History</title>
    <link rel="stylesheet" href="/top_exchange/public/css/orders.css">
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>|
    <style>
        .main-content {
            padding-top: 0;
        }
        #orderDetailsHeader {
            padding: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 1.1em;
        }

        #orderDetailsHeader strong {
            color: #333;
        }

        .overlay-content h2 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Transaction History</h1>
            <div class="search-section">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username, or Order Date...">
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
                        <th>Orders</th>
                        <th>Total Amount</th>
                        <th>Status</th>
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
                                    <button class="view-orders-btn" 
                                            onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>', '<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>
                                            View Orders
                                    </button>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-orders">No completed transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details</h2>
            <h3 id="orderDetailsHeader" style="margin-bottom: 20px; color: #666;"></h3>
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

    <script>
    // Define viewOrderDetails in global scope
    function viewOrderDetails(orders, poNumber, username) {
    try {
        const orderDetails = JSON.parse(orders);
        const orderDetailsBody = $('#orderDetailsBody');
        const orderDetailsHeader = $('#orderDetailsHeader');
        
        // Set the header with PO number and username
        orderDetailsHeader.html(`Transaction Details for PO: <strong>${poNumber}</strong> | Customer: <strong>${username}</strong>`);
        
        orderDetailsBody.empty();
        
        orderDetails.forEach(product => {
            const row = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.quantity}</td>
                </tr>
            `;
            orderDetailsBody.append(row);
        });
        
        $('#orderDetailsModal').show();
        } catch (e) {
            console.error('Error parsing order details:', e);
            alert('Error displaying order details');
        }
    }

    // Define closeOrderDetailsModal in global scope
    function closeOrderDetailsModal() {
        $('#orderDetailsModal').hide();
    }

    // Document ready function for other functionality
    $(document).ready(function() {
        // Search functionality
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
    });
    </script>
</body>
</html>