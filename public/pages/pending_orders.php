<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Pending Orders');

// Get all pending orders (status = 'Pending')
$sql = "SELECT o.id, o.customer_name, o.customer_contact, o.delivery_address, o.delivery_date, o.status, o.orders, o.notes, o.created_at, o.updated_at, o.total_price
        FROM orders o
        WHERE o.status = 'Pending'
        ORDER BY o.delivery_date ASC";

$result = $conn->query($sql);

$pendingOrders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['orders'] = json_decode($row['orders'], true);
        $pendingOrders[] = $row;
    }
}

// Get all status options
$statusOptions = array("Pending", "Active", "Completed", "Cancelled");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders</title>
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .orders-header h1 {
            font-size: 24px;
            font-weight: bold;
        }
        
        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-bar input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 80px;
            width: 250px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .orders-table th {
            background-color: black;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
        }
        
        .orders-table td {
            border-bottom: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .view-order-btn {
            background-color: #424242;
            color: white;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 80px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-order-btn:hover {
            background-color: #212121;
        }
        
        .order-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 800px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: black;
            text-decoration: none;
        }
        
        .order-details {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .order-item {
            margin-bottom: 10px;
        }
        
        .order-item strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .order-item span {
            display: block;
            padding: 8px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background-color: black;
            color: white;
            padding: 10px;
            text-align: center;
        }
        
        .items-table td {
            border-bottom: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        
        .status-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
        }
        
        .update-btn {
            background-color: #DAA520;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 80px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
            transition: background-color 0.3s;
            width: 100%;
        }
        
        .update-btn:hover {
            background-color: #B8860B;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 0;
            color: #777;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .empty-state p {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .empty-state span {
            font-size: 14px;
        }
        
        /* Toast styles are in the linked CSS file */
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .orders-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .search-bar input {
                width: 100%;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                padding: 15px;
                margin: 10% auto;
            }
        }
        
        /* Delivery date formatting */
        .delivery-date {
            white-space: nowrap;
        }
        
        /* Add scrollable content to modal */
        .modal-body {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 10px;
        }
        
        /* Scrollbar styling */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1; 
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #888; 
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555; 
        }
        
        /* Notes section with fixed height and scrolling */
        .order-notes {
            margin-bottom: 20px;
        }
        
        .notes-content {
            max-height: 100px;
            overflow-y: auto;
            padding: 8px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        
        /* Fix for Safari overflow issue */
        .modal-content {
            transform: translateZ(0);
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content">
        <div class="orders-header">
            <h1><i class="fas fa-clipboard-list"></i> Pending Orders</h1>
            <div class="search-bar">
                <input type="text" placeholder="Search..." id="orderSearch">
            </div>
        </div>
        
        <?php if (count($pendingOrders) > 0): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Delivery Date</th>
                        <th>Items</th>
                        <th>Total Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php foreach ($pendingOrders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td class="delivery-date"><?= date('M d, Y', strtotime($order['delivery_date'])) ?></td>
                            <td><?= count($order['orders']) ?> items</td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td>
                                <button class="view-order-btn" data-order-id="<?= $order['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No pending orders</p>
                <span>All orders have been processed</span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal for viewing order details -->
    <div id="orderModal" class="order-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="close-btn">&times;</span>
            </div>
            <div class="modal-body">
                <div class="order-details">
                    <div class="order-item">
                        <strong>Order #</strong>
                        <span id="orderNumber"></span>
                    </div>
                    <div class="order-item">
                        <strong>Customer Name</strong>
                        <span id="customerName"></span>
                    </div>
                    <div class="order-item">
                        <strong>Contact Number</strong>
                        <span id="customerContact"></span>
                    </div>
                    <div class="order-item">
                        <strong>Delivery Date</strong>
                        <span id="deliveryDate"></span>
                    </div>
                    <div class="order-item">
                        <strong>Delivery Address</strong>
                        <span id="deliveryAddress"></span>
                    </div>
                    <div class="order-item">
                        <strong>Created On</strong>
                        <span id="createdAt"></span>
                    </div>
                </div>
                
                <div class="order-notes">
                    <strong>Notes</strong>
                    <div class="notes-content" id="orderNotes"></div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="orderItems">
                        <!-- Order items will be populated here -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td><strong id="totalPrice">₱0.00</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="order-actions">
                <form id="updateOrderForm">
                    <input type="hidden" id="orderId" name="order_id">
                    <div class="order-item">
                        <strong>Update Status</strong>
                        <select class="status-select" id="orderStatus" name="status">
                            <?php foreach ($statusOptions as $option): ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="update-btn">Update Order</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Toast for notifications -->
    <div id="toast" class="toast">
        <div class="toast-content">
            <i class="toast-icon"></i>
            <div class="toast-message"></div>
        </div>
        <div class="toast-progress"></div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Store pending orders data in JavaScript
            const pendingOrders = <?= json_encode($pendingOrders) ?>;
            
            // Searching functionality
            $("#orderSearch").on("keyup", function() {
                const value = $(this).val().toLowerCase();
                $("#ordersTableBody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // View order button click event
            $(".view-order-btn").click(function() {
                const orderId = $(this).data("order-id");
                const order = pendingOrders.find(o => o.id == orderId);
                
                if (order) {
                    // Populate order details
                    $("#orderNumber").text(order.id);
                    $("#customerName").text(order.customer_name);
                    $("#customerContact").text(order.customer_contact);
                    $("#deliveryDate").text(formatDate(order.delivery_date));
                    $("#deliveryAddress").text(order.delivery_address);
                    $("#createdAt").text(formatDate(order.created_at));
                    $("#orderId").val(order.id);
                    $("#orderStatus").val(order.status);
                    
                    // Handle notes display
                    const notesContent = order.notes ? order.notes.trim() : '';
                    $("#orderNotes").text(notesContent || 'No notes provided');
                    
                    // Populate order items
                    let itemsHtml = '';
                    if (order.orders && order.orders.length > 0) {
                        order.orders.forEach(item => {
                            const subtotal = item.price * item.quantity;
                            itemsHtml += `
                                <tr>
                                    <td>${item.item_description} (${item.packaging})</td>
                                    <td>${item.quantity}</td>
                                    <td>₱${parseFloat(item.price).toFixed(2)}</td>
                                    <td>₱${subtotal.toFixed(2)}</td>
                                </tr>
                            `;
                        });
                    } else {
                        itemsHtml = '<tr><td colspan="4" style="text-align: center;">No items found</td></tr>';
                    }
                    
                    $("#orderItems").html(itemsHtml);
                    $("#totalPrice").text(`₱${parseFloat(order.total_price).toFixed(2)}`);
                    
                    // Show modal
                    $("#orderModal").show();
                }
            });
            
            // Close modal when X is clicked
            $(".close-btn").click(function() {
                $("#orderModal").hide();
            });
            
            // Close modal when clicked outside the content
            $(window).click(function(event) {
                if (event.target == document.getElementById('orderModal')) {
                    $("#orderModal").hide();
                }
            });
            
            // Handle form submission via AJAX
            $("#updateOrderForm").submit(function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: "/backend/update_order_status.php",
                    method: "POST",
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Show success toast
                            showToast("success", "Order status updated successfully!");
                            
                            // Reload the page after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            // Show error toast
                            showToast("error", response.message || "Failed to update order status");
                        }
                    },
                    error: function() {
                        showToast("error", "An error occurred while processing your request");
                    }
                });
            });
            
            // Helper function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Toast notification function
            function showToast(type, message) {
                const toast = $("#toast");
                const toastIcon = $(".toast-icon");
                const toastMessage = $(".toast-message");
                const toastProgress = $(".toast-progress");
                
                // Set icon based on type
                if (type === "success") {
                    toastIcon.removeClass().addClass("toast-icon fas fa-check-circle");
                    toast.removeClass().addClass("toast success");
                } else {
                    toastIcon.removeClass().addClass("toast-icon fas fa-exclamation-circle");
                    toast.removeClass().addClass("toast error");
                }
                
                // Set message text
                toastMessage.text(message);
                
                // Show toast
                toast.addClass("active");
                
                // Reset and start progress bar
                toastProgress.css("width", "0%");
                let width = 0;
                const progressInterval = setInterval(() => {
                    width += 1;
                    toastProgress.css("width", width + "%");
                    
                    if (width >= 100) {
                        clearInterval(progressInterval);
                        toast.removeClass("active");
                    }
                }, 30);
                
                // Clear toast after timeout
                setTimeout(() => {
                    clearInterval(progressInterval);
                    toast.removeClass("active");
                }, 3000);
            }
        });
    </script>
</body>
</html>