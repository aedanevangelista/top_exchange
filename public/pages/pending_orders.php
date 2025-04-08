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
        /* Main container styles */
        .main-content {
            padding: 20px;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header-section h1 {
            font-size: 24px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .header-section h1 i {
            margin-right: 10px;
            color: #ff9800;
        }
        
        .timestamp {
            font-size: 14px;
            color: #666;
            text-align: right;
            line-height: 1.5;
        }
        
        /* Status badge styles */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        
        /* Orders container */
        .orders-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Table styles */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th, 
        .orders-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .orders-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .orders-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .orders-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }
        
        .action-btn i {
            margin-right: 5px;
        }
        
        .view-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .view-btn:hover {
            background-color: #0b7dda;
        }
        
        .change-status-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .change-status-btn:hover {
            background-color: #e68a00;
        }
        
        .print-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .print-btn:hover {
            background-color: #45a049;
        }
        
        /* Modal styles */
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
            padding: 25px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .status-modal-content h3 {
            color: #333;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .status-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
        
        .status-btn {
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .status-btn i {
            margin-right: 8px;
        }
        
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        
        .status-active:hover {
            background-color: #45a049;
        }
        
        .status-reject {
            background-color: #f44336;
            color: white;
        }
        
        .status-reject:hover {
            background-color: #d32f2f;
        }
        
        /* Empty state */
        .no-orders {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-orders i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
            display: block;
        }
        
        .no-orders h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .timestamp {
                margin-top: 10px;
                text-align: left;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-btn {
                width: 100%;
            }
            
            .status-modal-content {
                width: 90%;
            }
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
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Client</th>
                            <th>Order Date</th>
                            <th>Delivery Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['po_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['company'] ? $row['company'] : $row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['delivery_date']); ?></td>
                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
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
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-info-circle"></i>
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