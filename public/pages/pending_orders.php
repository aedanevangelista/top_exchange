<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php");
    exit();
}

// Get current user information
$username = $_SESSION['admin_username'] ?? 'System';
$currentDateTime = date('Y-m-d H:i:s');

// Fetch pending orders
$sql = "SELECT o.*, c.username, c.company FROM orders o 
        LEFT JOIN clients_accounts c ON o.username = c.username 
        WHERE o.status = 'Pending'
        ORDER BY o.order_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders</title>
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .order-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9f9f9;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .order-details {
            margin-bottom: 15px;
        }
        
        .order-items {
            margin-top: 15px;
        }
        
        .item-card {
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            background-color: white;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .status-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        .status-btn {
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        
        .status-reject {
            background-color: #f44336;
            color: white;
        }
        
        .close-modal {
            float: right;
            cursor: pointer;
            font-size: 20px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .view-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .change-status-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .print-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .no-orders {
            text-align: center;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header-section">
            <h1><i class="fas fa-clock"></i> Pending Orders</h1>
            <div class="timestamp">
                Current Date and Time (UTC): <?php echo date('Y-m-d H:i:s'); ?><br>
                Current User's Login: <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </div>
        </div>

        <div class="orders-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $orders = json_decode($row['orders'], true);
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <h3>PO #<?php echo htmlspecialchars($row['po_number']); ?></h3>
                                <span class="status-pending">Pending</span>
                            </div>
                            <div>
                                <button class="action-btn view-btn" onclick="viewOrderDetails('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="action-btn change-status-btn" onclick="openStatusModal('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                <button class="action-btn print-btn" onclick="printOrder('<?php echo $row['id']; ?>')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($row['company'] ? $row['company'] : $row['username']); ?></p>
                            <p><strong>Order Date:</strong> <?php echo htmlspecialchars($row['order_date']); ?></p>
                            <p><strong>Delivery Date:</strong> <?php echo htmlspecialchars($row['delivery_date']); ?></p>
                            <p><strong>Total Amount:</strong> ₱<?php echo number_format($row['total_amount'], 2); ?></p>
                        </div>
                        
                        <div class="order-items">
                            <h4>Order Items:</h4>
                            <?php 
                            if (is_array($orders)) {
                                foreach(array_slice($orders, 0, 2) as $item): 
                            ?>
                                <div class="item-card">
                                    <p><strong><?php echo htmlspecialchars($item['item_description']); ?></strong></p>
                                    <p>Quantity: <?php echo $item['quantity']; ?> x ₱<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                            <?php 
                                endforeach;
                                
                                if (count($orders) > 2) {
                                    echo "<p>+" . (count($orders) - 2) . " more items</p>";
                                }
                            } else {
                                echo "<p>No items found</p>";
                            }
                            ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-info-circle" style="font-size: 48px; color: #ccc;"></i>
                    <h3>No Pending Orders</h3>
                    <p>There are currently no orders with pending status.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Status Change Modal -->
        <div id="statusModal" class="status-modal">
            <div class="status-modal-content">
                <span class="close-modal" onclick="closeStatusModal()">&times;</span>
                <h3>Change Order Status</h3>
                <p>Select a new status for this order:</p>
                <input type="hidden" id="orderId" value="">
                <div class="status-options">
                    <button class="status-btn status-active" onclick="changeStatus('Active')">
                        <i class="fas fa-check-circle"></i> Approve (Active)
                    </button>
                    <button class="status-btn status-reject" onclick="changeStatus('Rejected')">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "positionClass": "toast-bottom-right",
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
        
        // Open order details in a new page
        function viewOrderDetails(orderId) {
            window.location.href = `/public/pages/order_details.php?id=${orderId}`;
        }
        
        // Open status change modal
        function openStatusModal(orderId) {
            document.getElementById('orderId').value = orderId;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        // Close status change modal
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Change order status
        function changeStatus(newStatus) {
            const orderId = document.getElementById('orderId').value;
            
            // Send AJAX request to update status
            $.ajax({
                url: '/backend/update_order_status.php',
                type: 'POST',
                data: {
                    order_id: orderId,
                    status: newStatus,
                    timestamp: '<?php echo $currentDateTime; ?>',
                    username: '<?php echo $username; ?>'
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            toastr.success('Order status updated successfully');
                            // Reload page after short delay
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            toastr.error('Error: ' + data.message);
                        }
                    } catch (e) {
                        toastr.error('Invalid response from server');
                    }
                    closeStatusModal();
                },
                error: function() {
                    toastr.error('Error communicating with server');
                    closeStatusModal();
                }
            });
        }
        
        // Print order function
        function printOrder(orderId) {
            window.open(`/public/pages/print_order.php?id=${orderId}`, '_blank');
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                closeStatusModal();
            }
        }
    </script>
</body>
</html>