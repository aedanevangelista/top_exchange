<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Payment History');

// Fetch active and inactive users
$sql = "SELECT username, status FROM clients_accounts WHERE status IN ('Active', 'Inactive') ORDER BY username";
$result = $conn->query($sql);
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    <link rel="stylesheet" href="/top_exchange/public/css/orders.css">
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="/top_exchange/public/css/payment_history.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Payment History</h1>
            <div class="search-section">
                <input type="text" id="searchInput" placeholder="Search by username...">
            </div>
        </div>

        <!-- Main Table -->
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Payment History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['status']) ?></td>
                            <td>
                                <button class="view-button" onclick="viewPaymentHistory('<?= htmlspecialchars($user['username']) ?>')">
                                    View Payments
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Monthly Payments Modal -->
        <div id="monthlyPaymentsModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('monthlyPaymentsModal')">&times;</span>
                <h2>Monthly Payments - <span id="modalUsername"></span></h2>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyPaymentsBody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Monthly Orders Modal -->
        <div id="monthlyOrdersModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('monthlyOrdersModal')">&times;</span>
                <h2>Orders - <span id="modalMonth"></span></h2>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Order Date</th>
                            <th>Delivery Date</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyOrdersBody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Details Modal -->
        <div id="orderDetailsModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('orderDetailsModal')">&times;</span>
                <h2>Order Details</h2>
                <div id="orderDetailsContent"></div>
            </div>
        </div>
    </div>

    <script>
        // Define months array at the top level of your script
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        function viewPaymentHistory(username) {
            $('#modalUsername').text(username);
            const currentYear = new Date().getFullYear();
            
            // Show loading state
            $('#monthlyPaymentsBody').html('<tr><td colspan="5">Loading...</td></tr>');
            $('#monthlyPaymentsModal').show();
            
            console.log('Fetching payments for:', username, currentYear);

            $.ajax({
                url: '../../backend/get_monthly_payments.php',
                method: 'GET',
                data: { username: username, year: currentYear },
                dataType: 'json',
                success: function(response) {
                    let monthlyPaymentsHtml = '';
                    
                    if (!response.success) {
                        $('#monthlyPaymentsBody').html(
                            `<tr><td colspan="5" style="color: red;">${response.message || 'Error loading payment history'}</td></tr>`
                        );
                        return;
                    }

                    const payments = response.data || [];
                    
                    // Use the months array defined at the top of the script
                    months.forEach((month, index) => {
                        const monthData = payments.find(p => p.month === index + 1) || {
                            total_amount: 0,
                            payment_status: 'Unpaid'
                        };
                        
                        monthlyPaymentsHtml += `
                            <tr>
                                <td>${month}</td>
                                <td>
                                    <button class="view-button" onclick="viewMonthlyOrders('${username}', ${index + 1}, '${month}')">
                                        View Orders List
                                    </button>
                                </td>
                                <td>PHP ${numberFormat(monthData.total_amount)}</td>
                                <td class="payment-status-${monthData.payment_status.toLowerCase()}">${monthData.payment_status}</td>
                                <td>
                                    <button class="status-toggle ${monthData.payment_status === 'Paid' ? 'status-paid' : 'status-unpaid'}"
                                            onclick="togglePaymentStatus('${username}', ${index + 1}, this, '${monthData.payment_status}')">
                                        Change Status
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    $('#monthlyPaymentsBody').html(monthlyPaymentsHtml);
                },
                error: function(xhr, status, error) {
                    console.error('Ajax Error:', error);
                    console.error('Response:', xhr.responseText);
                    console.error('Status:', status);
                    $('#monthlyPaymentsBody').html(
                        '<tr><td colspan="5" style="color: red;">Error loading payment history. Please try again.</td></tr>'
                    );
                }
            });
        }

        function viewMonthlyOrders(username, month, monthName) {
            $('#modalMonth').text(monthName);
            
            $.ajax({
                url: '../../backend/get_monthly_orders.php',
                method: 'GET',
                data: { 
                    username: username, 
                    month: month,
                    year: new Date().getFullYear()
                },
                dataType: 'json',
                success: function(response) {
                    let orders = [];
                    try {
                        orders = response.data || response;
                    } catch (e) {
                        console.error('Error processing orders:', e);
                        orders = [];
                    }

                    let ordersHtml = '';
                    if (orders && orders.length > 0) {
                        orders.forEach(order => {
                            ordersHtml += `
                                <tr>
                                    <td>${order.po_number}</td>
                                    <td>${order.order_date}</td>
                                    <td>${order.delivery_date}</td>
                                    <td>
                                        <button class="view-button" onclick="viewOrderDetails('${order.orders}', '${order.po_number}', '${username}')">
                                            View Orders
                                        </button>
                                    </td>
                                    <td>PHP ${numberFormat(order.total_amount)}</td>
                                </tr>
                            `;
                        });
                    } else {
                        ordersHtml = '<tr><td colspan="5">No orders found for this month</td></tr>';
                    }

                    $('#monthlyOrdersBody').html(ordersHtml);
                    $('#monthlyOrdersModal').show();
                },
                error: function(xhr, status, error) {
                    console.error('Ajax Error:', error);
                    $('#monthlyOrdersBody').html(
                        '<tr><td colspan="5" style="color: red;">Error loading orders. Please try again.</td></tr>'
                    );
                }
            });
        }

        function viewOrderDetails(orders, poNumber, username) {
            try {
                const ordersList = JSON.parse(orders);
                let orderDetailsHtml = `
                    <div id="orderDetailsHeader">
                        <p><strong>PO Number:</strong> ${poNumber}</p>
                        <p><strong>Username:</strong> ${username}</p>
                    </div>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Item Description</th>
                                <th>Packaging</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                ordersList.forEach(item => {
                    orderDetailsHtml += `
                        <tr>
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${numberFormat(item.price)}</td>
                            <td>${item.quantity}</td>
                            <td>PHP ${numberFormat(item.price * item.quantity)}</td>
                        </tr>
                    `;
                });

                orderDetailsHtml += '</tbody></table>';
                $('#orderDetailsContent').html(orderDetailsHtml);
                $('#orderDetailsModal').show();
            } catch (e) {
                console.error('Error parsing orders JSON:', e);
                alert('Error displaying order details. Please try again.');
            }
        }

        function togglePaymentStatus(username, month, button, currentStatus) {
            const newStatus = currentStatus === 'Paid' ? 'Unpaid' : 'Paid';

            $.ajax({
                url: '../../backend/update_payment_status.php',
                method: 'POST',
                data: {
                    username: username,
                    month: month,
                    year: new Date().getFullYear(),
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update the button class
                        button.className = `status-toggle status-${newStatus.toLowerCase()}`;
                        
                        // Update the status text in the previous column
                        const statusCell = button.parentElement.previousElementSibling;
                        statusCell.innerHTML = `<span class="status-text-${newStatus.toLowerCase()}">${newStatus}</span>`;
                        
                        // Update the button's stored status
                        $(button).attr('onclick', `togglePaymentStatus('${username}', ${month}, this, '${newStatus}')`);
                    } else {
                        alert('Error updating payment status. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax Error:', error);
                    alert('Error updating payment status. Please try again.');
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function numberFormat(number) {
            return parseFloat(number).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.orders-table tbody tr');
            
            rows.forEach(row => {
                const username = row.cells[0].textContent.toLowerCase();
                row.style.display = username.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html>