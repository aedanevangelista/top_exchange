<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Order History'); // Updated from 'Transaction History'

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'order_date', 'delivery_date', 'total_amount'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'order_date'; // Default sort column
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC'; // Default to descending
}

// Fetch completed orders for display with sorting
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount, status 
        FROM orders 
        WHERE status = 'Completed'";

// Add sorting
$sql .= " ORDER BY {$sort_column} {$sort_direction}";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
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
    <title>Order History</title>
    <link rel="stylesheet" href="/admin/css/orders.css">
    <link rel="stylesheet" href="/admin/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>

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
        
        /* Search Container Styling (copied from inventory.css) */
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
            background-color:rgb(51, 51, 51);
        }

        th.sortable .fa-sort-up,
        th.sortable .fa-sort-down {
            color:rgb(255, 255, 255);
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Order History</h1>
            <!-- Search section maintained as-is -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username, or Order Date...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
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
                            <a href="<?= getSortUrl('order_date', $sort_column, $sort_direction) ?>">
                                Order Date <?= getSortIcon('order_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">
                                Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">
                                Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
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
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-orders-btn" 
                                            onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>', '<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars($order['delivery_address']) ?>')">
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
                            <td colspan="8" class="no-orders">No completed transactions found.</td>
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
            <div id="orderDeliveryAddress" style="margin-bottom: 20px; color: #666;"></div>
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
    function viewOrderDetails(orders, poNumber, username, deliveryAddress) {
    try {
        const orderDetails = JSON.parse(orders);
        const orderDetailsBody = $('#orderDetailsBody');
        const orderDetailsHeader = $('#orderDetailsHeader');
        const orderDeliveryAddress = $('#orderDeliveryAddress');
        
        // Set the header with PO number and username
        orderDetailsHeader.html(`Transaction Details for PO: <strong>${poNumber}</strong> | Customer: <strong>${username}</strong>`);
        
        // Set the delivery address
        orderDeliveryAddress.html(`Delivery Address: <strong>${deliveryAddress || 'Not specified'}</strong>`);
        
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