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
    <style>
        /* Year Tabs Styling */
        .year-tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .year-tab {
            padding: 8px 16px;
            margin-right: 10px;
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 4px 4px 0 0;
            background-color: #f8f8f8;
        }
        
        .year-tab.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        /* Status Text Styling */
        .status-active {
            color: #28a745;
            font-weight: 600;
        }

        .status-inactive {
            color: gray;
            font-weight: 600;
        }
        
        .far.fa-money-bill-alt {
            margin-right: 5px;
        }

        .view-button, .status-toggle, .update-payment-btn {
            border-radius: 80px;
        }

        .status-toggle.disabled {
            background-color: lightgray !important;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .payment-status-disabled {
            color: lightgray;
            font-style: italic;
        }
        
        .payment-status-paid {
            color: #28a745;
            font-weight: 600;
        }
        
        .payment-status-partial {
            color: #ffc107;
            font-weight: 600;
        }
        
        .payment-status-unpaid {
            color: #dc3545;
            font-weight: 600;
        }
        
        .status-toggle.status-paid {
            background-color: #28a745;
            color: white;
        }
        
        .status-toggle.status-partial {
            background-color: #ffc107;
            color: white;
        }
        
        .status-toggle.status-unpaid {
            background-color: #dc3545;
            color: white;
        }
        
        .update-payment-btn {
            background-color: #2980b9;
            color: white;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .update-payment-btn:hover {
            background-color: #2471a3;
        }
        
        .payment-form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        
        .payment-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .payment-form input, .payment-form select, .payment-form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .payment-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .save-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 80px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .save-btn {
            background-color: #28a745;
            color: white;
        }
        
        .cancel-btn {
            background-color: #6c757d;
            color: white;
        }
        
        .proof-image {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .payment-history-section {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        
        .payment-history-section h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .payment-history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-history-table th, .payment-history-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        
        .payment-history-table th {
            background-color: #f3f3f3;
            font-weight: bold;
        }
        
        .balance-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .total-amount {
            font-size: 1.1em;
        }
        
        .amount-paid {
            color: #28a745;
        }
        
        .remaining-balance {
            color: #dc3545;
        }
        
        #proofPreview {
            display: none;
            margin-top: 10px;
        }
    </style>
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
                            <td>
                                <span class="status-<?= strtolower($user['status']) ?>">
                                    <?= htmlspecialchars($user['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="view-button" onclick="viewPaymentHistory('<?= htmlspecialchars($user['username']) ?>')">
                                    <i class="far fa-money-bill-alt"></i>View Payments
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
                
                <!-- Year Tabs -->
                <div class="year-tabs" id="yearTabs">
                    <!-- Tabs will be added dynamically -->
                </div>
                
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
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
        
        <!-- Update Payment Modal -->
        <div id="updatePaymentModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('updatePaymentModal')">&times;</span>
                <h2>Update Payment - <span id="paymentModalMonth"></span></h2>
                
                <div class="balance-info">
                    <div>
                        <div class="total-amount">Total Amount: <span id="totalAmountValue">PHP 0.00</span></div>
                        <div class="amount-paid">Amount Paid: <span id="amountPaidValue">PHP 0.00</span></div>
                    </div>
                    <div class="remaining-balance">Remaining Balance: <span id="balanceValue">PHP 0.00</span></div>
                </div>
                
                <form id="paymentUpdateForm" enctype="multipart/form-data" class="payment-form">
                    <input type="hidden" id="payment_username" name="username">
                    <input type="hidden" id="payment_month" name="month">
                    <input type="hidden" id="payment_year" name="year">
                    <input type="hidden" id="payment_total_amount" name="total_amount">
                    
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (PHP)</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_status">Payment Status</label>
                        <select id="payment_status" name="payment_status" required>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Partial">Partial Payment</option>
                            <option value="Paid">Fully Paid</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_proof">Proof of Payment (Image/PDF)</label>
                        <input type="file" id="payment_proof" name="payment_proof" accept="image/*, application/pdf">
                        <div id="proofPreview"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_notes">Notes</label>
                        <textarea id="payment_notes" name="payment_notes" rows="3"></textarea>
                    </div>
                    
                    <div class="payment-actions">
                        <button type="button" class="cancel-btn" onclick="closeModal('updatePaymentModal')">Cancel</button>
                        <button type="submit" class="save-btn">Save Payment</button>
                    </div>
                </form>
                
                <div class="payment-history-section" id="paymentHistorySection" style="display: none;">
                    <h3>Previous Payments</h3>
                    <table class="payment-history-table" id="paymentHistoryTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Proof</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="paymentHistoryBody">
                            <!-- Payment history will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<script>
    // Define months array at the top level of your script
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    // Store the current active username and year
    let currentUsername = '';
    let availableYears = [];
    let currentYear = new Date().getFullYear();

    function viewPaymentHistory(username) {
        $('#modalUsername').text(username);
        currentUsername = username;
        
        // Always clear any cached data when opening the payment history
        availableYears = [];
        
        // Fetch available years for this user
        fetchAvailableYears(username, true);
    }
    
    function fetchAvailableYears(username, refreshData = false) {
        // Show loading state
        $('#yearTabs').html('<div>Loading years...</div>');
        $('#monthlyPaymentsBody').html('<tr><td colspan="7">Please select a year...</td></tr>');
        $('#monthlyPaymentsModal').show();
        
        // Add cache-busting parameter to prevent browser caching
        const cacheBuster = refreshData ? `&_=${new Date().getTime()}` : '';
        
        $.ajax({
            url: `../../backend/get_available_years.php?username=${username}${cacheBuster}`,
            method: 'GET',
            data: { username: username },
            dataType: 'json',
            cache: false, // Prevent caching
            success: function(response) {
                if (!response.success || !response.data || response.data.length === 0) {
                    // If no years found, default to current year
                    currentYear = new Date().getFullYear();
                    availableYears = [currentYear];
                } else {
                    availableYears = response.data;
                    // Ensure current year is included if it's not in the response
                    const thisYear = new Date().getFullYear();
                    if (!availableYears.includes(thisYear)) {
                        availableYears.push(thisYear);
                    }
                }
                
                // Generate year tabs
                generateYearTabs();
                
                // Load the most recent year's data by default
                const mostRecentYear = Math.max(...availableYears);
                currentYear = mostRecentYear;
                loadYearData(mostRecentYear, refreshData);
            },
            error: function(xhr, status, error) {
                console.error('Ajax Error:', error);
                currentYear = new Date().getFullYear();
                availableYears = [currentYear, currentYear - 1]; // Default to current year and previous year
                
                // Generate year tabs
                generateYearTabs();
                
                // Load current year's data
                loadYearData(currentYear, refreshData);
            }
        });
    }
    
    function generateYearTabs() {
        let tabsHtml = '';
        
        // Sort years in descending order (most recent first)
        availableYears.sort((a, b) => b - a);
        
        availableYears.forEach((year, index) => {
            const activeClass = year === currentYear ? 'active' : '';
            tabsHtml += `<div class="year-tab ${activeClass}" onclick="loadYearData(${year}, true)">${year}</div>`;
        });
        
        $('#yearTabs').html(tabsHtml);
    }
    
    function loadYearData(year, refreshData = false) {
        // Update active tab
        $('.year-tab').removeClass('active');
        $(`.year-tab:contains(${year})`).addClass('active');
        currentYear = year;
        
        // Show loading state
        $('#monthlyPaymentsBody').html('<tr><td colspan="7">Loading...</td></tr>');
        
        console.log('Fetching payments for:', currentUsername, year);

        // Add cache-busting parameter to prevent browser caching
        const cacheBuster = refreshData ? `&_=${new Date().getTime()}` : '';
        
        $.ajax({
            url: `../../backend/get_monthly_payments.php?username=${currentUsername}&year=${year}${cacheBuster}`,
            method: 'GET',
            data: { username: currentUsername, year: year },
            dataType: 'json',
            cache: false,
            success: function(response) {
                let monthlyPaymentsHtml = '';
                
                if (!response.success) {
                    $('#monthlyPaymentsBody').html(
                        `<tr><td colspan="7" style="color: red;">${response.message || 'Error loading payment history'}</td></tr>`
                    );
                    return;
                }

                const payments = response.data || [];
                
                // Current date for comparison (using March 19, 2025 as fixed date)
                const currentDate = new Date('2025-03-22');
                const currentYear = currentDate.getFullYear();
                const currentMonth = currentDate.getMonth(); // 0-based index

                // Use the months array defined at the top of the script
                months.forEach((month, index) => {
                    const monthData = payments.find(p => p.month === index + 1) || {
                        total_amount: 0,
                        payment_status: 'Unpaid',
                        amount_paid: 0,
                        balance: 0
                    };

                    // Check if the month is in the future
                    const isDisabled = (year > currentYear) || 
                                     (year === currentYear && index > currentMonth);
                    
                    // Determine status class based on payment status
                    let statusClass;
                    if (isDisabled) {
                        statusClass = 'payment-status-disabled';
                    } else {
                        switch(monthData.payment_status) {
                            case 'Paid':
                                statusClass = 'payment-status-paid';
                                break;
                            case 'Partial':
                                statusClass = 'payment-status-partial';
                                break;
                            default:
                                statusClass = 'payment-status-unpaid';
                        }
                    }
                    
                    const buttonClass = isDisabled ? 'update-payment-btn disabled' : 'update-payment-btn';
                    
                    const statusText = isDisabled ? 'Pending' : monthData.payment_status;
                    
                    // Calculate balance
                    const totalAmount = parseFloat(monthData.total_amount) || 0;
                    const amountPaid = parseFloat(monthData.amount_paid) || 0;
                    const balance = totalAmount - amountPaid;
                    
                    monthlyPaymentsHtml += `
                        <tr>
                            <td>${month}</td>
                            <td>
                                <button class="view-button" onclick="viewMonthlyOrders('${currentUsername}', ${index + 1}, '${month}', ${year})">
                                    <i class="fas fa-clipboard-list"></i>
                                    View Orders List
                                </button>
                            </td>
                            <td>PHP ${numberFormat(totalAmount)}</td>
                            <td>PHP ${numberFormat(amountPaid)}</td>
                            <td>PHP ${numberFormat(balance)}</td>
                            <td class="${statusClass}">${statusText}</td>
                            <td>
                                <button class="${buttonClass}"
                                        ${isDisabled ? 'disabled' : ''}
                                        onclick="${isDisabled ? '' : `openUpdatePayment('${currentUsername}', ${index + 1}, '${month}', ${year}, ${totalAmount}, ${amountPaid})`}"
                                        title="${isDisabled ? 'Cannot update payment for future months' : 'Update payment information'}">
                                    <i class="fas fa-money-bill-wave"></i>
                                    Update Payment
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
                    '<tr><td colspan="7" style="color: red;">Error loading payment history. Please try again.</td></tr>'
                );
            }
        });
    }

    function viewMonthlyOrders(username, month, monthName, year) {
        $('#modalMonth').text(`${monthName} ${year}`);
        
        // Add cache-busting parameter
        const cacheBuster = `&_=${new Date().getTime()}`;
        
        $.ajax({
            url: `../../backend/get_monthly_orders.php?username=${username}&month=${month}&year=${year}${cacheBuster}`,
            method: 'GET',
            data: { 
                username: username, 
                month: month,
                year: year
            },
            dataType: 'json',
            cache: false,
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
                                    <button class="view-button" onclick="viewOrderDetails(${JSON.stringify(order.orders).replace(/"/g, '&quot;')}, '${order.po_number}', '${username}')">
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
            // If orders is a string, parse it, otherwise use it as is
            const ordersList = typeof orders === 'string' ? JSON.parse(orders) : orders;
            
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

    function openUpdatePayment(username, month, monthName, year, totalAmount, amountPaid) {
        // Set modal title
        $('#paymentModalMonth').text(`${monthName} ${year}`);
        
        // Set form values
        $('#payment_username').val(username);
        $('#payment_month').val(month);
        $('#payment_year').val(year);
        $('#payment_total_amount').val(totalAmount);
        
        // Set current values in the display
        $('#totalAmountValue').text(`PHP ${numberFormat(totalAmount)}`);
        $('#amountPaidValue').text(`PHP ${numberFormat(amountPaid)}`);
        $('#balanceValue').text(`PHP ${numberFormat(totalAmount - amountPaid)}`);
        
        // Set default value for payment amount input
        const balance = totalAmount - amountPaid;
        $('#payment_amount').val(balance.toFixed(2));
        $('#payment_amount').attr('max', balance.toFixed(2));
        
        // Set default status based on payment amount
        if (amountPaid >= totalAmount) {
            $('#payment_status').val('Paid');
        } else if (amountPaid > 0) {
            $('#payment_status').val('Partial');
        } else {
            $('#payment_status').val('Unpaid');
        }
        
        // Clear previous proof preview
        $('#proofPreview').empty().hide();
        $('#payment_notes').val('');
        
        // Fetch payment history for this month/year
        fetchPaymentHistory(username, month, year);
        
        // Show the modal
        $('#updatePaymentModal').show();
    }
    
    function fetchPaymentHistory(username, month, year) {
        $.ajax({
            url: '../../backend/get_payment_history.php',
            method: 'GET',
            data: {
                username: username,
                month: month,
                year: year
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let historyHtml = '';
                    
                    response.data.forEach(payment => {
                        let proofLink = '';
                        if (payment.proof_of_payment) {
                            proofLink = `<a href="../../uploads/payment_proofs/${payment.proof_of_payment}" target="_blank">View Proof</a>`;
                        } else {
                            proofLink = 'N/A';
                        }
                        
                        historyHtml += `
                            <tr>
                                <td>${payment.payment_date}</td>
                                <td>PHP ${numberFormat(payment.amount_paid)}</td>
                                <td class="payment-status-${payment.payment_status.toLowerCase()}">${payment.payment_status}</td>
                                <td>${proofLink}</td>
                                <td>${payment.payment_notes || 'N/A'}</td>
                            </tr>
                        `;
                    });
                    
                    $('#paymentHistoryBody').html(historyHtml);
                    $('#paymentHistorySection').show();
                } else {
                    $('#paymentHistorySection').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching payment history:', error);
                $('#paymentHistorySection').hide();
            }
        });
    }
    
    // Preview uploaded proof of payment
    $('#payment_proof').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                let preview = '';
                
                if (file.type.startsWith('image/')) {
                    preview = `<img src="${e.target.result}" alt="Proof Preview" class="proof-image">`;
                } else if (file.type === 'application/pdf') {
                    preview = `<div class="pdf-preview">PDF File: ${file.name}</div>`;
                }
                
                $('#proofPreview').html(preview).show();
            }
            
            reader.readAsDataURL(file);
        } else {
            $('#proofPreview').empty().hide();
        }
    });
    
    // Handle payment form submission
    $('#paymentUpdateForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: '../../backend/update_payment.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Payment updated successfully!');
                    
                    // Close the modal
                    closeModal('updatePaymentModal');
                    
                    // Refresh the data
                    loadYearData(currentYear, true);
                } else {
                    alert('Error: ' + (response.message || 'Failed to update payment.'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error submitting payment update:', error);
                alert('Failed to update payment. Please try again.');
            }
        });
    });
    
    // Update remaining balance based on payment amount input
    $('#payment_amount').on('input', function() {
        const totalAmount = parseFloat($('#payment_total_amount').val()) || 0;
        const currentPaid = parseFloat($('#amountPaidValue').text().replace('PHP ', '').replace(',', '')) || 0;
        const newPayment = parseFloat($(this).val()) || 0;
        
        const newTotal = currentPaid + newPayment;
        const newBalance = totalAmount - newTotal;
        
        $('#balanceValue').text(`PHP ${numberFormat(newBalance < 0 ? 0 : newBalance)}`);
        
        // Update status based on the new balance
        if (newTotal >= totalAmount) {
            $('#payment_status').val('Paid');
        } else if (newTotal > 0) {
            $('#payment_status').val('Partial');
        } else {
            $('#payment_status').val('Unpaid');
        }
    });

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