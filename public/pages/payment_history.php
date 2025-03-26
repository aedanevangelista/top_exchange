<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Payment History');

// Fetch active and inactive users with their balance
$sql = "SELECT username, status, balance FROM clients_accounts WHERE status IN ('Active', 'Inactive') ORDER BY username";
$result = $conn->query($sql);
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Update the payment_status enum if needed
$check_status_values = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_status'";
$result = $conn->query($check_status_values);
if ($result && $row = $result->fetch_assoc()) {
    $type = $row['Type'];
    if (strpos($type, 'Fully Paid') === false || strpos($type, 'Partially Paid') === false) {
        // Update the enum to include the new statuses
        $conn->query("ALTER TABLE monthly_payments 
                     MODIFY COLUMN payment_status ENUM('Fully Paid', 'Partially Paid', 'Unpaid', 'For Approval') 
                     NOT NULL DEFAULT 'Unpaid'");
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
            position: sticky;
            top: 65px; /* Adjust based on header height */
            background-color: #fefefe;
            z-index: 4;
            padding: 10px 0;
            margin-bottom: 10px;
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

        .view-button, .status-toggle {
            border-radius: 80px;
            margin: 0 2px;
            min-width: 85px;
        }

        .view-button.disabled, .status-toggle.disabled {
            background-color: lightgray !important;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .payment-status-disabled, .payment-status-pending {
            color: lightgray;
            font-style: italic;
        }

        .payment-status-unpaid {
            color: #dc3545;
            font-weight: 600;
        }

        .payment-status-forapproval {
            color: #ffc107;
            font-weight: 600;
        }

        .payment-status-fullypaid {
            color: #28a745;
            font-weight: 600;
        }
        
        .payment-status-partiallypaid {
            color: #17a2b8;
            font-weight: 600;
        }

        /* Total Balance Styling */
        .total-balance-positive {
            color: #28a745;
            font-weight: 600;
        }

        .total-balance-negative {
            color: #dc3545;
            font-weight: 600;
        }

        .total-balance-zero {
            color: #6c757d;
            font-weight: 600;
        }

        /* Monthly Payment Header with Balance */
        .modal-header-with-balance {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-right: 20px;
        }

        .add-balance-btn {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
        }

        .add-balance-btn:hover {
            background-color: #218838;
        }

        .balance-display {
            display: flex;
            align-items: center;
        }

        /* Payment proof thumbnail */
        .payment-proof-thumbnail {
            max-width: 60px;
            max-height: 60px;
            cursor: pointer;
        }

        /* Payment proof modal */
        #paymentProofModal img {
            max-width: 100%;
            max-height: 80vh;
        }

        /* Search Container Styling */
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

        /* Add Balance Modal */
        #addBalanceModal .modal-content {
            max-width: 400px;
        }

        /* Payment Modal */
        #paymentModal .modal-content {
            max-width: 500px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .submit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Status Modal */
        #changeStatusModal .modal-content {
            max-width: 450px;
            padding: 25px;
        }

        .status-options {
            margin: 20px 0;
        }

        .status-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-option:hover {
            background-color: #f9f9f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .status-option.selected {
            background-color: #e6f7ff;
            border-color: #1890ff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .status-option .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .status-option span {
            font-size: 16px;
            font-weight: 500;
        }

        .status-unpaid {
            background-color: #dc3545;
        }

        .status-forapproval {
            background-color: #ffc107;
        }

        .status-fullypaid {
            background-color: #28a745;
        }
        
        .status-partiallypaid {
            background-color: #17a2b8;
        }

        .status-pending {
            background-color: #6c757d;
        }

        /* Preview image */
        .preview-container {
            display: none;
            margin-top: 10px;
        }

        .preview-container img {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Improved Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            position: relative;
            background-color: #fefefe;
            margin: 0;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 1200px;
            max-height: 80vh;
            animation: modalFade 0.3s ease;
            overflow: hidden;
            
            /* Center the modal in viewport */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
        }

        /* Make monthly payments modal wider */
        #monthlyPaymentsModal .modal-content {
            width: 80%;
            max-width: 1200px;
            max-height: 80vh;
        }

        @keyframes modalFade {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 10;
        }

        .close:hover {
            color: #f44336;
        }

        /* User balance display */
        .user-balance-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .balance-label {
            font-weight: 600;
            color: #495057;
        }

        /* Action buttons spacing - Only for monthly payments table */
        #monthlyPaymentsBody .action-buttons {
            display: flex;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }
        
        /* Table cell styling only for monthly payments table */
        #monthlyPaymentsModal .orders-table th:last-child,
        #monthlyPaymentsModal .orders-table td:last-child {
            width: 180px;
            min-width: 180px;
            max-width: 180px;
            text-align: center;
        }
        
        /* Notes styling */
        .payment-notes {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .payment-notes-tooltip {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .payment-notes-tooltip .notes-tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            white-space: normal;
        }
        
        .payment-notes-tooltip:hover .notes-tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* Delivery Address styling */
        .delivery-address {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .delivery-address:hover {
            white-space: normal;
            overflow: visible;
        }
        
        /* Payment type styling */
        .payment-type {
            font-style: italic;
            color: #495057;
        }
        
        .payment-type-internal {
            color: #17a2b8;
            font-weight: bold;
        }
        
        .payment-type-external {
            color: #fd7e14;
            font-weight: bold;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0 20px 20px 20px;
        }

        .modal-header-with-balance {
            position: sticky;
            top: 0;
            background-color: #fefefe;
            z-index: 5;
            padding: 20px 20px 10px 20px;
            margin-bottom: 0;
            border-bottom: 1px solid #eee;
        }

        /* Responsive styling */
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 15px;
            }
            
            .modal-header-with-balance {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .balance-display {
                margin-top: 10px;
            }
        }

        .orders-table-container {
            max-height: 100%;
            overflow: visible;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table thead {
            position: sticky;
            top: 120px; /* Adjust based on header + tabs height */
            background-color: #f8f9fa;
            z-index: 3;
        }

        .orders-table th {
            background-color: #f8f9fa;
            box-shadow: 0 1px 0 rgba(0,0,0,0.1);
        }

        /* Center small modals */
        #addBalanceModal .modal-content,
        #paymentModal .modal-content,
        #changeStatusModal .modal-content,
        #paymentProofModal .modal-content {
            width: auto;
            max-width: 500px;
        }

        /* Style for the close button */
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 10;
            text-decoration: none;
        }

        .close:hover {
            color: #f44336;
        }

        @keyframes modalFade {
            from {opacity: 0; transform: translate(-50%, -60%);}
            to {opacity: 1; transform: translate(-50%, -50%);}
        }
        
        /* More responsive styling */
        @media (max-width: 992px) {
            .modal-content {
                width: 90%;
                max-height: 85vh;
            }
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
            
            .modal-header-with-balance {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .balance-display {
                margin-top: 10px;
            }
            
            .orders-table thead {
                top: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Payment History</h1>
            <!-- Updated search section to match inventory.php design -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>

        <!-- Main Table -->
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Total Balance</th>
                        <th>Status</th>
                        <th>Payment History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <?php 
                                    $balanceClass = 'total-balance-zero';
                                    if ($user['balance'] > 0) {
                                        $balanceClass = 'total-balance-positive';
                                    } elseif ($user['balance'] < 0) {
                                        $balanceClass = 'total-balance-negative';
                                    }
                                ?>
                                <span class="<?= $balanceClass ?>">
                                    PHP <?= number_format($user['balance'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-<?= strtolower($user['status']) ?>">
                                    <?= htmlspecialchars($user['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="view-button" onclick="viewPaymentHistory('<?= htmlspecialchars($user['username']) ?>', <?= $user['balance'] ?>)">
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
                
                <div class="modal-header-with-balance">
                    <h2>Monthly Payments - <span id="modalUsername"></span></h2>
                    <div class="balance-display">
                        <span>Remaining Balance: <span id="userRemainingBalance" class="total-balance-positive">PHP 0.00</span></span>
                        <button class="add-balance-btn" onclick="openAddBalanceModal()">+</button>
                    </div>
                </div>
                
                <!-- Year Tabs -->
                <div class="year-tabs" id="yearTabs">
                    <!-- Tabs will be added dynamically -->
                </div>
                
                <div class="modal-body">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Orders</th>
                                <th>Total Amount</th>
                                <th>Remaining Balance</th>
                                <th>Proof</th>
                                <th>Notes</th>
                                <th>Payment Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="monthlyPaymentsBody">
                        </tbody>
                    </table>
                </div>
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
                            <th>Delivery Address</th>
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

        <!-- Add Balance Modal -->
        <div id="addBalanceModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('addBalanceModal')">&times;</span>
                <h2>Add Balance</h2>
                <div class="input-group">
                    <label for="amount">Amount (PHP)</label>
                    <input type="number" id="amountToAdd" min="1" step="0.01" placeholder="Enter amount">
                </div>
                <div class="input-group">
                    <label for="notes">Notes (Optional)</label>
                    <input type="text" id="balanceNotes" placeholder="Enter notes">
                </div>
                <div class="button-group">
                    <button class="cancel-btn" onclick="closeModal('addBalanceModal')">Cancel</button>
                    <button class="submit-btn" onclick="addBalance()">Add Balance</button>
                </div>
            </div>
        </div>

        <!-- Make Payment Modal -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('paymentModal')">&times;</span>
                <h2>Make Payment</h2>
                
                <div class="user-balance-info">
                    <span class="balance-label">Available Balance:</span>
                    <span id="availableBalance" class="total-balance-positive">PHP 0.00</span>
                </div>
                
                <div class="input-group">
                    <label for="paymentType">Payment Type</label>
                    <select id="paymentType" onchange="togglePaymentFields()">
                        <option value="Internal">Internal Payment (Use Available Balance)</option>
                        <option value="External">External Payment (Bank Transfer)</option>
                    </select>
                </div>
                
                <div class="input-group">
                    <label for="amountToPay">Amount to Pay (PHP)</label>
                    <input type="number" id="amountToPay" min="1" step="0.01">
                </div>
                
                <div class="input-group" id="proofFileGroup">
                    <label for="paymentProof">Payment Proof</label>
                    <input type="file" id="paymentProof" accept="image/*" onchange="previewImage(this)">
                    <small>Upload proof of payment (image file)</small>
                </div>
                
                <div class="preview-container" id="imagePreview">
                    <img id="previewImg" src="#" alt="Preview">
                </div>
                
                <div class="input-group">
                    <label for="paymentNotes">Notes (Optional)</label>
                    <input type="text" id="paymentNotes" placeholder="Enter notes">
                </div>
                
                <div class="button-group">
                    <button class="cancel-btn" onclick="closeModal('paymentModal')">Cancel</button>
                    <button class="submit-btn" onclick="submitPayment()">Submit Payment</button>
                </div>
                
                <input type="hidden" id="paymentMonth">
                <input type="hidden" id="paymentYear">
            </div>
        </div>

        <!-- Change Status Modal -->
        <div id="changeStatusModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('changeStatusModal')">&times;</span>
                <h2>Change Payment Status</h2>
                <div class="status-options">
                    <div class="status-option" data-status="Unpaid" onclick="selectStatus(this)">
                        <div class="status-icon status-unpaid"></div>
                        <span>Unpaid</span>
                    </div>
                    <div class="status-option" data-status="For Approval" onclick="selectStatus(this)">
                        <div class="status-icon status-forapproval"></div>
                        <span>For Approval</span>
                    </div>
                    <div class="status-option" data-status="Partially Paid" onclick="selectStatus(this)">
                        <div class="status-icon status-partiallypaid"></div>
                        <span>Partially Paid</span>
                    </div>
                    <div class="status-option" data-status="Fully Paid" onclick="selectStatus(this)">
                        <div class="status-icon status-fullypaid"></div>
                        <span>Fully Paid</span>
                    </div>
                </div>
                <div class="button-group">
                    <button class="cancel-btn" onclick="closeModal('changeStatusModal')">Cancel</button>
                    <button class="submit-btn" onclick="updateStatus()">Update Status</button>
                </div>
                <input type="hidden" id="statusUsername">
                <input type="hidden" id="statusMonth">
                <input type="hidden" id="statusYear">
                <input type="hidden" id="selectedStatus">
            </div>
        </div>

        <!-- Payment Proof Modal -->
        <div id="paymentProofModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('paymentProofModal')">&times;</span>
                <h2>Payment Proof</h2>
                <div id="proofImageContainer">
                    <img id="fullProofImage" src="#" alt="Payment Proof">
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
    let currentUserBalance = 0;
    
    // Current date for comparison in UTC (as per the user's timestamp: 2025-03-26 03:54:05)
    const currentDate = new Date('2025-03-26T03:54:05Z');
    const currentYearValue = currentDate.getFullYear();
    const currentMonthValue = currentDate.getMonth(); // 0-based index

    function viewPaymentHistory(username, balance) {
        $('#modalUsername').text(username);
        currentUsername = username;
        currentUserBalance = balance;
        
        // Update balance display
        updateBalanceDisplay();
        
        // Always clear any cached data when opening the payment history
        availableYears = [];
        
        // Fetch available years for this user
        fetchAvailableYears(username, true);
    }
    
    function updateBalanceDisplay() {
        const balanceDisplay = $('#userRemainingBalance');
        balanceDisplay.text(`PHP ${numberFormat(currentUserBalance)}`);
        
        // Set the appropriate class based on balance amount
        if (currentUserBalance > 0) {
            balanceDisplay.attr('class', 'total-balance-positive');
        } else if (currentUserBalance < 0) {
            balanceDisplay.attr('class', 'total-balance-negative');
        } else {
            balanceDisplay.attr('class', 'total-balance-zero');
        }
    }
    
    function openAddBalanceModal() {
        $('#amountToAdd').val('');
        $('#balanceNotes').val('');
        $('#addBalanceModal').show();
    }
    
    function addBalance() {
        const amount = parseFloat($('#amountToAdd').val());
        const notes = $('#balanceNotes').val();
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        $.ajax({
            url: '../../backend/update_client_balance.php',
            method: 'POST',
            data: {
                username: currentUsername,
                amount: amount,
                notes: notes
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentUserBalance = parseFloat(response.new_balance);
                    
                    // Update the balance display in the modal
                    updateBalanceDisplay();
                    
                    // Update the balance in the main table
                    const mainTableRow = $(`.orders-table tbody tr:contains("${currentUsername}")`);
                    if (mainTableRow.length) {
                        const balanceCell = mainTableRow.find('td:nth-child(2) span');
                        balanceCell.text(`PHP ${numberFormat(currentUserBalance)}`);
                        
                        if (currentUserBalance > 0) {
                            balanceCell.attr('class', 'total-balance-positive');
                        } else if (currentUserBalance < 0) {
                            balanceCell.attr('class', 'total-balance-negative');
                        } else {
                            balanceCell.attr('class', 'total-balance-zero');
                        }
                    }
                    
                    // Reload the current year data
                    loadYearData(currentYear, true);
                    
                    closeModal('addBalanceModal');
                } else {
                    alert('Error updating balance: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax Error:', error);
                alert('Error updating balance. Please try again.');
            }
        });
    }
    
    function fetchAvailableYears(username, refreshData = false) {
        // Show loading state
        $('#yearTabs').html('<div>Loading years...</div>');
        $('#monthlyPaymentsBody').html('<tr><td colspan="9">Please select a year...</td></tr>');
        $('#monthlyPaymentsModal').show();
        
        // Add cache-busting parameter to prevent browser caching
        const cacheBuster = refreshData ? `&_=${new Date().getTime()}` : '';
        
        $.ajax({
            url: `../../backend/get_available_years.php?username=${username}${cacheBuster}`,
            method: 'GET',
            dataType: 'json',
            cache: false, // Prevent caching
            success: function(response) {
                if (!response.success || !response.data || response.data.length === 0) {
                    // If no years found, default to current year
                    currentYear = currentYearValue;
                    availableYears = [currentYear];
                } else {
                    availableYears = response.data;
                    // Ensure current year is included if it's not in the response
                    if (!availableYears.includes(currentYearValue)) {
                        availableYears.push(currentYearValue);
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
                currentYear = currentYearValue;
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
        $('#monthlyPaymentsBody').html('<tr><td colspan="9">Loading...</td></tr>');
        
        console.log('Fetching payments for:', currentUsername, year);

        // Add cache-busting parameter to prevent browser caching
        const cacheBuster = refreshData ? `&_=${new Date().getTime()}` : '';
        
        $.ajax({
            url: `../../backend/get_monthly_payments.php?username=${currentUsername}&year=${year}${cacheBuster}`,
            method: 'GET',
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log('Payments response:', response); // Log the response to check if payment_type is included
                
                let monthlyPaymentsHtml = '';
                
                if (!response.success) {
                    $('#monthlyPaymentsBody').html(
                        `<tr><td colspan="9" style="color: red;">${response.message || 'Error loading payment history'}</td></tr>`
                    );
                    return;
                }

                const payments = response.data || [];
                
                // Use the months array defined at the top of the script
                months.forEach((month, index) => {
                    const monthData = payments.find(p => p.month === index + 1) || {
                        total_amount: 0,
                        payment_status: 'Unpaid',
                        remaining_balance: 0,
                        proof_image: null,
                        notes: '',
                        payment_type: null
                    };

                    // Ensure remaining balance is set correctly
                    let remainingBalance = parseFloat(monthData.remaining_balance);
                    if (monthData.payment_status === 'Fully Paid') {
                        remainingBalance = 0;
                    } else if (remainingBalance === 0 && parseFloat(monthData.total_amount) > 0 && 
                              monthData.payment_status !== 'Fully Paid' && monthData.payment_status !== 'Partially Paid') {
                        // If remaining balance is 0 but payment isn't Paid and there's a total amount
                        remainingBalance = parseFloat(monthData.total_amount);
                    }

                    // Check if the month is in the future or current month
                    const isFutureMonth = (year > currentYearValue) || 
                                     (year === currentYearValue && index > currentMonthValue);
                    
                    // Determine the correct status to display
                    let displayStatus = monthData.payment_status;
                    if (isFutureMonth) {
                        displayStatus = 'Pending';
                    }
                    
                    // Convert old 'Paid' status to 'Fully Paid' for display purposes
                    if (displayStatus === 'Paid') {
                        displayStatus = 'Fully Paid';
                    }
                    
                    // Get the correct CSS class for the status
                    let statusClass = isFutureMonth ? 'payment-status-pending' : '';
                    
                    if (!isFutureMonth) {
                        if (displayStatus === 'Unpaid') {
                            statusClass = 'payment-status-unpaid';
                        } else if (displayStatus === 'For Approval') {
                            statusClass = 'payment-status-forapproval';
                        } else if (displayStatus === 'Fully Paid') {
                            statusClass = 'payment-status-fullypaid';
                        } else if (displayStatus === 'Partially Paid') {
                            statusClass = 'payment-status-partiallypaid';
                        }
                    }
                    
                    // Determine button status and classes
                    const viewOrdersButtonDisabled = false; // Allow viewing orders even for future months
                    const payButtonDisabled = isFutureMonth || displayStatus === 'Fully Paid' || parseFloat(monthData.total_amount) === 0;
                    const statusButtonDisabled = isFutureMonth;
                    
                    const viewOrdersBtnClass = viewOrdersButtonDisabled ? 'view-button disabled' : 'view-button';
                    const payBtnClass = payButtonDisabled ? 'view-button disabled' : 'view-button';
                    const statusBtnClass = statusButtonDisabled ? 'status-toggle disabled' : 'status-toggle';

                    // Proof image
                    let proofHtml = 'No proof';
                    if (monthData.proof_image) {
                        proofHtml = `<img src="../../payments/${currentUsername}/${month} - ${year}/${monthData.proof_image}" 
                                    class="payment-proof-thumbnail" 
                                    onclick="viewPaymentProof('${currentUsername}', '${month}', ${year}, '${monthData.proof_image}')"
                                    alt="Payment Proof">`;
                    }
                    
                    // Notes with tooltip for longer notes
                    let notesHtml = 'None';
                    if (monthData.notes && monthData.notes.trim() !== '') {
                        const notesText = monthData.notes.trim();
                        if (notesText.length > 25) {
                            notesHtml = `
                                <div class="payment-notes-tooltip">
                                    <span class="payment-notes">${notesText.substring(0, 25)}...</span>
                                    <span class="notes-tooltip-text">${notesText}</span>
                                </div>`;
                        } else {
                            notesHtml = `<span class="payment-notes">${notesText}</span>`;
                        }
                    }
                    
                    // Payment type display
                    let paymentTypeHtml = 'N/A';
                    if (monthData.payment_type) {
                        const paymentTypeClass = `payment-type-${monthData.payment_type.toLowerCase()}`;
                        paymentTypeHtml = `<span class="payment-type ${paymentTypeClass}">${monthData.payment_type}</span>`;
                    }
                    
                    const tooltip = isFutureMonth ? 'Month has not ended yet' : 
                                   (displayStatus === 'Fully Paid' ? 'Already paid' : 
                                   (parseFloat(monthData.total_amount) === 0 ? 'No orders to pay' : 'Make payment'));
                    
                    monthlyPaymentsHtml += `
                        <tr>
                            <td>${month}</td>
                            <td>
                                <button class="${viewOrdersBtnClass}" onclick="viewMonthlyOrders('${currentUsername}', ${index + 1}, '${month}', ${year})">
                                    <i class="fas fa-clipboard-list"></i>
                                    View Orders
                                </button>
                            </td>
                            <td>PHP ${numberFormat(monthData.total_amount)}</td>
                            <td>PHP ${numberFormat(remainingBalance)}</td>
                            <td>${proofHtml}</td>
                            <td>${notesHtml}</td>
                            <td>${paymentTypeHtml}</td>
                            <td class="${statusClass}">${displayStatus}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="${payBtnClass}" 
                                            ${payButtonDisabled ? 'disabled' : ''}
                                            onclick="${payButtonDisabled ? '' : `openPaymentModal('${currentUsername}', ${index + 1}, ${year}, ${remainingBalance})`}"
                                            title="${tooltip}">
                                        <i class="fas fa-credit-card"></i>
                                        Pay
                                    </button>
                                    <button class="${statusBtnClass}"
                                            ${statusButtonDisabled ? 'disabled' : ''}
                                            onclick="${statusButtonDisabled ? '' : `openChangeStatusModal('${currentUsername}', ${index + 1}, ${year}, '${monthData.payment_status}')`}"
                                            title="${statusButtonDisabled ? 'Month has not ended yet' : 'Click to change status'}">
                                        <i class="fas fa-exchange-alt"></i>
                                        Status
                                    </button>
                                </div>
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
                    '<tr><td colspan="9" style="color: red;">Error loading payment history. Please try again.</td></tr>'
                );
            }
        });
    }

    function openPaymentModal(username, month, year, remainingBalance) {
        // Set the values in the payment modal
        $('#amountToPay').val(remainingBalance);
        $('#availableBalance').text(`PHP ${numberFormat(currentUserBalance)}`);
        
        // Set appropriate color for available balance
        if (currentUserBalance > 0) {
            $('#availableBalance').attr('class', 'total-balance-positive');
        } else if (currentUserBalance < 0) {
            $('#availableBalance').attr('class', 'total-balance-negative');
        } else {
            $('#availableBalance').attr('class', 'total-balance-zero');
        }
        
        // Reset payment type dropdown
        $('#paymentType').val('Internal');
        
        // Update fields based on payment type
        togglePaymentFields();
        
        $('#paymentNotes').val('');
        $('#paymentMonth').val(month);
        $('#paymentYear').val(year);
        
        // Clear any previous file selection and preview
        $('#paymentProof').val('');
        $('#imagePreview').hide();
        
        // Show the modal
        $('#paymentModal').show();
    }
    
    function togglePaymentFields() {
        const paymentType = $('#paymentType').val();
        
        if (paymentType === 'Internal') {
            // For internal payments, no proof image required
            $('#proofFileGroup').hide();
            $('#amountToPay').prop('readonly', false);
        } else {
            // For external payments, proof image required
            $('#proofFileGroup').show();
            $('#amountToPay').prop('readonly', false);
        }
    }

    function submitPayment() {
        const month = $('#paymentMonth').val();
        const year = $('#paymentYear').val();
        const amount = parseFloat($('#amountToPay').val());
        const notes = $('#paymentNotes').val();
        const paymentType = $('#paymentType').val();
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        // Validation for internal payments
        if (paymentType === 'Internal') {
            if (amount > currentUserBalance) {
                alert('Insufficient balance. Please add more funds to your account or select External Payment.');
                return;
            }
        }
        
        // Validation for external payments
        if (paymentType === 'External') {
            const fileInput = document.getElementById('paymentProof');
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please upload proof of payment for external payments');
                return;
            }
        }
        
        // Create FormData object for file upload
        const formData = new FormData();
        formData.append('username', currentUsername);
        formData.append('month', month);
        formData.append('year', year);
        formData.append('amount', amount);
        formData.append('notes', notes);
        formData.append('payment_type', paymentType);
        
        // Only append proof file for External payments
        if (paymentType === 'External') {
            const fileInput = document.getElementById('paymentProof');
            if (fileInput.files && fileInput.files.length > 0) {
                formData.append('proof', fileInput.files[0]);
            }
        }
        
        // Show loading indicator
        $('#paymentModal .submit-btn').prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: '../../backend/process_payment.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(response) {
                console.log('Payment response:', response);
                
                // Reset button
                $('#paymentModal .submit-btn').prop('disabled', false).text('Submit Payment');
                
                if (response.success) {
                    // Update the user balance
                    currentUserBalance = parseFloat(response.new_balance);
                    updateBalanceDisplay();
                    
                    // Update the balance in the main table
                    const mainTableRow = $(`.orders-table tbody tr:contains("${currentUsername}")`);
                    if (mainTableRow.length) {
                        const balanceCell = mainTableRow.find('td:nth-child(2) span');
                        balanceCell.text(`PHP ${numberFormat(currentUserBalance)}`);
                        
                        if (currentUserBalance > 0) {
                            balanceCell.attr('class', 'total-balance-positive');
                        } else if (currentUserBalance < 0) {
                            balanceCell.attr('class', 'total-balance-negative');
                        } else {
                            balanceCell.attr('class', 'total-balance-zero');
                        }
                    }
                    
                    // Reload the current year data
                    loadYearData(currentYear, true);
                    
                    closeModal('paymentModal');
                } else {
                    alert('Error processing payment: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Reset button
                $('#paymentModal .submit-btn').prop('disabled', false).text('Submit Payment');
                
                console.error('Ajax Error:', error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                alert(`Error processing payment. Status: ${xhr.status}. Please check the server logs.`);
            }
        });
    }

    function openChangeStatusModal(username, month, year, currentStatus) {
        // Set the hidden inputs
        $('#statusUsername').val(username);
        $('#statusMonth').val(month);
        $('#statusYear').val(year);
        $('#selectedStatus').val('');
        
        // Reset all status options
        $('.status-option').removeClass('selected');
        
        // Convert old 'Paid' status to 'Fully Paid' if needed
        if (currentStatus === 'Paid') {
            currentStatus = 'Fully Paid';
        }
        
        // Pre-select the current status if it exists
        $(`.status-option[data-status="${currentStatus}"]`).addClass('selected');
        $('#selectedStatus').val(currentStatus);
        
        // Show the modal
        $('#changeStatusModal').show();
    }

    function selectStatus(element) {
        $('.status-option').removeClass('selected');
        $(element).addClass('selected');
        $('#selectedStatus').val($(element).data('status'));
    }

    function updateStatus() {
        const username = $('#statusUsername').val();
        const month = $('#statusMonth').val();
        const year = $('#statusYear').val();
        const newStatus = $('#selectedStatus').val();
        
        if (!newStatus) {
            alert('Please select a status');
            return;
        }
        
        // Show loading indicator
        $('#changeStatusModal .submit-btn').prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: '../../backend/update_payment_status.php',
            method: 'POST',
            data: {
                username: username,
                month: month,
                year: year,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                // Reset button
                $('#changeStatusModal .submit-btn').prop('disabled', false).text('Update Status');
                
                if (response.success) {
                    // Reload the current year data
                    loadYearData(currentYear, true);
                    
                    closeModal('changeStatusModal');
                } else {
                    alert('Error updating status: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Reset button
                $('#changeStatusModal .submit-btn').prop('disabled', false).text('Update Status');
                
                console.error('Ajax Error:', error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                alert('Error updating status. Please try again.');
            }
        });
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                $('#previewImg').attr('src', e.target.result);
                $('#imagePreview').show();
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            $('#imagePreview').hide();
        }
    }

    function viewPaymentProof(username, month, year, filename) {
        const imgUrl = `../../payments/${username}/${month} - ${year}/${filename}`;
        $('#fullProofImage').attr('src', imgUrl);
        $('#paymentProofModal').show();
    }

    function viewMonthlyOrders(username, month, monthName, year) {
        $('#modalMonth').text(`${monthName} ${year}`);
        
        // Add cache-busting parameter
        const cacheBuster = `&_=${new Date().getTime()}`;
        
        $.ajax({
            url: `../../backend/get_monthly_orders.php?username=${username}&month=${month}&year=${year}${cacheBuster}`,
            method: 'GET',
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
                        // Handle delivery address with proper escaping and default value
                        const deliveryAddress = order.delivery_address ? escapeHtml(order.delivery_address) : 'Not specified';
                        
                        ordersHtml += `
                            <tr>
                                <td>${order.po_number}</td>
                                <td>${order.order_date}</td>
                                <td>${order.delivery_date}</td>
                                <td class="delivery-address">${deliveryAddress}</td>
                                <td>
                                    <button class="view-button" onclick="viewOrderDetails(${JSON.stringify(order.orders).replace(/"/g, '&quot;')}, '${order.po_number}', '${username}', '${deliveryAddress}')">
                                        View Orders
                                    </button>
                                </td>
                                <td>PHP ${numberFormat(order.total_amount)}</td>
                            </tr>
                        `;
                    });
                } else {
                    ordersHtml = '<tr><td colspan="6">No orders found for this month</td></tr>';
                }

                $('#monthlyOrdersBody').html(ordersHtml);
                $('#monthlyOrdersModal').show();
            },
            error: function(xhr, status, error) {
                console.error('Ajax Error:', error);
                $('#monthlyOrdersBody').html(
                    '<tr><td colspan="6" style="color: red;">Error loading orders. Please try again.</td></tr>'
                );
            }
        });
    }

    function viewOrderDetails(orders, poNumber, username, deliveryAddress = '') {
        try {
            // If orders is a string, parse it, otherwise use it as is
            const ordersList = typeof orders === 'string' ? JSON.parse(orders) : orders;
            
            let orderDetailsHtml = `
                <div id="orderDetailsHeader">
                    <p><strong>PO Number:</strong> ${poNumber}</p>
                    <p><strong>Username:</strong> ${username}</p>
                    <p><strong>Delivery Address:</strong> ${deliveryAddress || 'Not specified'}</p>
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

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        
        // Clear modal content when closing to avoid stale data
        if (modalId === 'monthlyPaymentsModal') {
            $('#monthlyPaymentsBody').html('');
        } else if (modalId === 'monthlyOrdersModal') {
            $('#monthlyOrdersBody').html('');
        }
    }

    function numberFormat(number) {
        return parseFloat(number || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Helper function to escape HTML special characters
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
    
    // Initialize by hiding the proof file group when page loads
    $(document).ready(function() {
        togglePaymentFields();
    });
</script>
</body>
</html>