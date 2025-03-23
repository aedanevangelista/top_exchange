<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Payment History');

// Ensure the uploads/payments directory exists
$paymentsDir = "../../uploads/payments";
if (!is_dir($paymentsDir)) {
    mkdir($paymentsDir, 0755, true);
}

// Fetch active and inactive users
$sql = "SELECT username, status, balance FROM clients_accounts WHERE status IN ('Active', 'Inactive') ORDER BY username";
$result = $conn->query($sql);
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Current date for reference
$currentDate = date('Y-m-d H:i:s');
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

        .view-button, .status-toggle, .update-payment-btn, .add-balance-btn, .change-status-btn {
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
        
        .payment-status-unpaid {
            color: #dc3545;
            font-weight: 600;
        }
        
        .payment-status-pending {
            color: #ffc107;
            font-weight: 600;
        }

        /* Added blue status color for consistency */
        .payment-status-blue {
            color: #2980b9;
            font-weight: 600;
        }

        .payment-status-unavailable {
            color: #6c757d;
            font-style: italic;
        }
        
        .update-payment-btn {
            background-color: #2980b9;
            color: white;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .update-payment-btn:hover {
            background-color: #2471a3;
        }
        
        .change-status-btn {
            background-color: #6c757d;
            color: white;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .change-status-btn:hover {
            background-color: #5a6268;
        }
        
        .add-balance-btn {
            background-color: #28a745;
            color: white;
            width: 30px;
            height: 30px;
            font-size: 18px;
            font-weight: 600;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
        }
        
        .add-balance-btn:hover {
            background-color: #218838;
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
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            object-fit: cover;
            cursor: pointer;
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
        
        .current-balance {
            color: #28a745;
            font-weight: bold;
        }
        
        .remaining-balance {
            color: #dc3545;
        }
        
        #proofPreview {
            display: none;
            margin-top: 10px;
            text-align: center;
        }
        
        /* Toast Notification - Moved to bottom right */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }

        .toast {
            min-width: 250px;
            margin-bottom: 10px;
            background-color: #333;
            color: white;
            padding: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            animation: slideIn 0.3s, fadeOut 0.5s 2.5s forwards;
            opacity: 0.95;
        }

        .toast-success {
            background-color: #28a745;
            border-left: 5px solid #1e7e34;
        }

        .toast-error {
            background-color: #dc3545;
            border-left: 5px solid #bd2130;
        }

        .toast-info {
            background-color: #17a2b8;
            border-left: 5px solid #117a8b;
        }

        .toast-warning {
            background-color: #ffc107;
            border-left: 5px solid #d39e00;
            color: #333;
        }

        .toast-icon {
            margin-right: 10px;
            font-size: 20px;
        }

        .toast-content {
            flex: 1;
        }

        .toast-close {
            cursor: pointer;
            margin-left: 10px;
        }

        @keyframes slideIn {
            from {transform: translateX(100%); opacity: 0;}
            to {transform: translateX(0); opacity: 0.95;}
        }

        @keyframes fadeOut {
            from {opacity: 0.95;}
            to {opacity: 0; visibility: hidden;}
        }
        
        /* Improved Modal Header Design */
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            position: relative;
            padding: 0 0 15px 0;
        }
        
        /* Single line header with title and balance */
        .header-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-right: 40px; /* Make room for X button */
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .balance-display {
            display: flex;
            align-items: center;
            margin-right: 15px; /* Ensure space between balance and X button */
        }
        
        /* Improved Close Button Design */
        .close {
            position: absolute;
            right: 0;
            top: 0;
            width: 32px;
            height: 32px;
            opacity: 0.6;
            background-color: #f1f1f1;
            border-radius: 50%;
            font-size: 24px;
            line-height: 32px;
            text-align: center;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 10;
        }
        
        .close:hover {
            opacity: 1;
            background-color: #ddd;
        }
        
        .header-balance-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .payments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
        }
        
        .payments-title {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .user-balance {
            font-weight: bold;
        }
        
        .user-balance-amount {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .positive-balance {
            color: #28a745;
        }
        
        .negative-balance {
            color: #dc3545;
        }
        
        .zero-balance {
            color: #000;
        }
        
        .amount-column {
            color: #28a745;
            font-weight: bold;
        }
        
        .balance-column {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Modal Styles - Expanded */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding: 20px;
            box-sizing: border-box;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 0 auto;
            padding: 25px;
            border: 1px solid #888;
            width: 75%; /* Increased to 75% of screen width */
            max-width: 1200px; /* Added max-width for very large screens */
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
            max-height: 90vh; /* Maximum height as 90% of viewport height */
            overflow-y: auto; /* Add scroll for overflow content */
        }
        
        .update-payment-modal .modal-content,
        .add-balance-modal .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Photo Thumbnail Gallery */
        .photo-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .payment-proof-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .payment-proof-thumbnail:hover {
            transform: scale(1.05);
        }
        
        /* Modal for Image Preview */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1200;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }

        .image-modal-content {
            margin: auto;
            display: block;
            max-width: 80%;
            max-height: 80%;
        }

        /* Improved Close Button for Image Modal */
        .close-image-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            width: 40px;
            height: 40px;
            color: #f1f1f1;
            font-size: 30px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }

        .close-image-modal:hover,
        .close-image-modal:focus {
            color: #bbb;
            background-color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            cursor: pointer;
        }
        
        /* Add Balance Modal Styles */
        .add-balance-modal .modal-content {
            max-width: 500px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-height: 80%;
        }
        
        .add-balance-form {
            padding: 20px 0;
        }
        
        .current-balance-display {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            background-color: #f8f8f8;
            text-align: center;
        }
        
        .current-balance-value {
            font-size: 24px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        /* Change Status Modal Styles - Updated with consistent button design */
        .change-status-modal .modal-content {
            max-width: 500px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            text-align: center;
            padding: 25px;
        }
        
        .status-title {
            margin: 0 0 20px 0;
            font-size: 1.5rem;
            color: #333;
        }
        
        .status-message {
            margin-bottom: 25px;
            font-size: 1.1rem;
            color: #555;
        }
        
        .status-options {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 25px 0;
        }
        
        /* Fixed status buttons with proper hover colors */
        .status-btn {
            padding: 8px 16px;
            border-radius: 80px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .status-btn-paid {
            background-color: #28a745;
            color: white;
        }
        
        .status-btn-paid:hover {
            background-color: #218838;
        }
        
        .status-btn-pending {
            background-color: #ffc107;
            color: white; /* Changed to white text */
        }
        
        .status-btn-pending:hover {
            background-color: #e0a800;
        }
        
        .status-btn-unpaid {
            background-color: #dc3545;
            color: white;
        }
        
        .status-btn-unpaid:hover {
            background-color: #c82333;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        /* Table responsiveness for large modals */
        .orders-table-container {
            overflow-x: auto;
        }
        
        .orders-table {
            min-width: 800px; /* Ensure table doesn't get too narrow */
        }
        
        /* Adjust positioning for tables inside modals */
        .modal .orders-table-container {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        
        /* Fix additional header spacing */
        .modal-content h2:not(.modal-title) {
            margin-top: 0;
            margin-bottom: 20px;
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

        <!-- Toast Container for Notifications - Now at bottom right -->
        <div class="toast-container" id="toastContainer"></div>

        <!-- Main Table -->
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Balance</th>
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
                            <td class="<?= ($user['balance'] < 0) ? 'balance-column' : 'amount-column' ?>">
                                PHP <?= number_format($user['balance'], 2) ?>
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
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title">Monthly Payments - <span id="modalUsername"></span></h2>
                        <div class="balance-display">
                            Available Balance: 
                            <span id="userTotalBalance" class="user-balance-amount">PHP 0.00</span>
                            <button class="add-balance-btn" onclick="openAddBalanceModal()" title="Add Balance">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <span class="close" onclick="closeModal('monthlyPaymentsModal')">&times;</span>
                </div>
                
                <!-- Year Tabs -->
                <div class="year-tabs" id="yearTabs">
                    <!-- Tabs will be added dynamically -->
                </div>
                
                <div class="orders-table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Orders</th>
                                <th>Total Amount</th>
                                <th>Remaining Balance</th>
                                <th>Proof of Payment</th>
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
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title">Orders - <span id="modalMonth"></span></h2>
                    </div>
                    <span class="close" onclick="closeModal('monthlyOrdersModal')">&times;</span>
                </div>
                <div class="orders-table-container">
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
        </div>

        <!-- Order Details Modal -->
        <div id="orderDetailsModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title">Order Details</h2>
                    </div>
                    <span class="close" onclick="closeModal('orderDetailsModal')">&times;</span>
                </div>
                <div id="orderDetailsContent"></div>
            </div>
        </div>
        
        <!-- Update Payment Modal -->
        <div id="updatePaymentModal" class="modal update-payment-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title">Update Payment - <span id="paymentModalMonth"></span></h2>
                    </div>
                    <span class="close" onclick="closeModal('updatePaymentModal')">&times;</span>
                </div>
                
                <div class="balance-info">
                    <div>
                        <div class="total-amount">Total Amount: <span id="totalAmountValue">PHP 0.00</span></div>
                    </div>
                    <div>
                        <div class="remaining-balance">Remaining Balance: <span id="remainingBalance">PHP 0.00</span></div>
                        <div class="current-balance">Current Balance: <span id="balanceValue">PHP 0.00</span></div>
                    </div>
                </div>
                
                <form id="paymentUpdateForm" enctype="multipart/form-data" class="payment-form">
                    <input type="hidden" id="payment_username" name="username">
                    <input type="hidden" id="payment_month" name="month">
                    <input type="hidden" id="payment_year" name="year">
                    <input type="hidden" id="payment_total_amount" name="total_amount">
                    <input type="hidden" id="payment_current_balance" name="current_balance">
                    <input type="hidden" id="payment_remaining_balance" name="remaining_balance">
                    
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (PHP)</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required>
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
            </div>
        </div>
        
        <!-- Add Balance Modal -->
        <div id="addBalanceModal" class="modal add-balance-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title">Add Balance - <span id="balanceModalUsername"></span></h2>
                    </div>
                    <span class="close" onclick="closeModal('addBalanceModal')">&times;</span>
                </div>
                
                <div class="current-balance-display">
                    <div>Current Balance</div>
                    <div id="currentBalanceValue" class="current-balance-value">PHP 0.00</div>
                </div>
                
                <form id="addBalanceForm" class="payment-form add-balance-form">
                    <input type="hidden" id="balance_username" name="username">
                    <input type="hidden" id="balance_current" name="current_balance">
                    
                    <div class="form-group">
                        <label for="balance_amount">Amount to Add (PHP)</label>
                        <input type="number" id="balance_amount" name="balance_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="balance_notes">Notes (Optional)</label>
                        <textarea id="balance_notes" name="balance_notes" rows="3"></textarea>
                    </div>
                    
                    <div class="payment-actions">
                        <button type="button" class="cancel-btn" onclick="closeModal('addBalanceModal')">Cancel</button>
                        <button type="submit" class="save-btn">Add Balance</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Status Modal - Updated with consistent button design -->
        <div id="changeStatusModal" class="modal change-status-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="header-content">
                        <h2 class="modal-title status-title">Change Payment Status</h2>
                    </div>
                    <span class="close" onclick="closeModal('changeStatusModal')">&times;</span>
                </div>
                
                <p class="status-message">Select a payment status for <strong><span id="statusMonthYear"></span></strong></p>
                
                <div class="status-options">
                    <button class="status-btn status-btn-paid" onclick="updatePaymentStatus('Paid')">
                        <i class="fas fa-check-circle"></i> Paid
                    </button>
                    <button class="status-btn status-btn-pending" onclick="updatePaymentStatus('Pending')">
                        <i class="fas fa-clock"></i> Pending
                    </button>
                    <button class="status-btn status-btn-unpaid" onclick="updatePaymentStatus('Unpaid')">
                        <i class="fas fa-times-circle"></i> Unpaid
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Image Modal for Previewing Proofs -->
        <div id="imageModal" class="image-modal">
            <span class="close-image-modal" onclick="closeImageModal()">&times;</span>
            <img class="image-modal-content" id="modalImage">
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
    let userBalance = 0;
    let currentStatusMonth = 0;
    let currentStatusYear = 0;
    let currentStatusMonthName = '';
    
    // Toast Notification Function - Now showing at bottom right
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = '';
        switch (type) {
            case 'success':
                icon = '<i class="fas fa-check-circle toast-icon"></i>';
                break;
            case 'error':
                icon = '<i class="fas fa-exclamation-circle toast-icon"></i>';
                break;
            case 'warning':
                icon = '<i class="fas fa-exclamation-triangle toast-icon"></i>';
                break;
            default:
                icon = '<i class="fas fa-info-circle toast-icon"></i>';
        }
        
        toast.innerHTML = `
            ${icon}
            <div class="toast-content">${message}</div>
            <span class="toast-close" onclick="this.parentElement.remove()">&times;</span>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 3000);
    }

    function viewPaymentHistory(username, balance) {
        $('#modalUsername').text(username);
        $('#balanceModalUsername').text(username);
        currentUsername = username;
        userBalance = balance;
        
        // Update the balance display with appropriate color
        updateBalanceDisplay(balance);
        
        // Always clear any cached data when opening the payment history
        availableYears = [];
        
        // Fetch available years for this user
        fetchAvailableYears(username, true);
    }
    
    function updateBalanceDisplay(balance) {
        const balanceElement = $('#userTotalBalance');
        const currentBalanceElement = $('#currentBalanceValue');
        
        balanceElement.text(`PHP ${numberFormat(balance)}`);
        currentBalanceElement.text(`PHP ${numberFormat(balance)}`);
        
        // Set color based on balance
        if (balance > 0) {
            balanceElement.removeClass('negative-balance zero-balance').addClass('positive-balance');
            currentBalanceElement.removeClass('negative-balance zero-balance').addClass('positive-balance');
        } else if (balance < 0) {
            balanceElement.removeClass('positive-balance zero-balance').addClass('negative-balance');
            currentBalanceElement.removeClass('positive-balance zero-balance').addClass('negative-balance');
        } else {
            balanceElement.removeClass('positive-balance negative-balance').addClass('zero-balance');
            currentBalanceElement.removeClass('positive-balance negative-balance').addClass('zero-balance');
        }
    }
    
    function openAddBalanceModal() {
        // Set up form values
        $('#balance_username').val(currentUsername);
        $('#balance_current').val(userBalance);
        $('#balance_amount').val('');
        $('#balance_notes').val('');
        
        // Show the modal
        $('#addBalanceModal').show();
    }
    
    function openChangeStatusModal(month, year, monthName) {
        // Store current month, year for status update
        currentStatusMonth = month;
        currentStatusYear = year;
        currentStatusMonthName = monthName;
        
        // Update modal text
        $('#statusMonthYear').text(`${monthName} ${year}`);
        
        // Show the modal
        $('#changeStatusModal').show();
    }
    
    function updatePaymentStatus(status) {
        $.ajax({
            url: '../../backend/update_payment_status.php',
            method: 'POST',
            data: {
                username: currentUsername,
                month: currentStatusMonth,
                year: currentStatusYear,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal
                    closeModal('changeStatusModal');
                    
                    // Show success toast
                    showToast(`Payment status updated to ${status} successfully!`, 'success');
                    
                    // Refresh data
                    loadYearData(currentYear, true);
                } else {
                    // Show error toast
                    showToast('Failed to update status: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating payment status:', error);
                showToast('Failed to update payment status. Please try again.', 'error');
            }
        });
    }
    
    // Handle balance form submission - Added toast for balance operations
    $('#addBalanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const username = $('#balance_username').val();
        const currentBalance = parseFloat($('#balance_current').val()) || 0;
        const amountToAdd = parseFloat($('#balance_amount').val()) || 0;
        const notes = $('#balance_notes').val() || '';
        
        if (amountToAdd <= 0) {
            showToast('Please enter a valid amount greater than zero.', 'warning');
            return;
        }
        
        // Disable submit button
        $(this).find('.save-btn').prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: '../../backend/add_balance.php',
            method: 'POST',
            data: {
                username: username,
                amount: amountToAdd,
                current_balance: currentBalance,
                notes: notes
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success toast for adding balance
                    showToast(`PHP ${numberFormat(amountToAdd)} added to balance successfully!`, 'success');
                    
                    // Update the user's balance
                    userBalance = response.balance;
                    updateBalanceDisplay(userBalance);
                    
                    // Close the modal
                    closeModal('addBalanceModal');
                    
                    // Also refresh the main table to show updated balance
                    location.reload();
                } else {
                    // Show error toast
                    showToast('Error: ' + (response.message || 'Failed to update balance.'), 'error');
                    $('#addBalanceForm').find('.save-btn').prop('disabled', false).text('Add Balance');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating balance:', error);
                showToast('Failed to update balance. Please try again.', 'error');
                $('#addBalanceForm').find('.save-btn').prop('disabled', false).text('Add Balance');
            }
        });
    });
    
    function fetchAvailableYears(username, refreshData = false) {
        // Show loading state
        $('#yearTabs').html('<div>Loading years...</div>');
        $('#monthlyPaymentsBody').html('<tr><td colspan="7">Please select a year...</td></tr>');
        $('#monthlyPaymentsModal').show();
        
        // Center modal vertically if needed
        centerModals();
        
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
                
                // Current date for comparison 
                const currentDate = new Date();
                const currentYear = currentDate.getFullYear();
                const currentMonth = currentDate.getMonth(); // 0-based index

                // Use the months array defined at the top of the script
                months.forEach((month, index) => {
                    const monthData = payments.find(p => p.month === index + 1) || {
                        total_amount: 0,
                        payment_status: 'Unpaid',
                        proof_of_payment: null,
                        remaining_balance: 0
                    };

                    // Check if the month is in the future
                    const isDisabled = (year > currentYear) || 
                                     (year === currentYear && index > currentMonth);
                    
                    // Determine status class based on payment status
                    let statusClass, statusText;
                    if (isDisabled) {
                        // Changed for unavailable months
                        statusClass = 'payment-status-unavailable';
                        statusText = 'Unavailable';
                    } else {
                        switch(monthData.payment_status) {
                            case 'Paid':
                                statusClass = 'payment-status-paid';
                                statusText = 'Paid';
                                break;
                            case 'Pending':
                                statusClass = 'payment-status-blue';  // Blue color for Pending
                                statusText = 'Pending';
                                break;
                            default:
                                statusClass = 'payment-status-unpaid';
                                statusText = 'Unpaid';
                        }
                    }
                    
                    const buttonClass = isDisabled ? 'disabled' : '';
                    
                    // Handle proof of payment images
                    let proofHtml = '';
                    if (monthData.proof_of_payment) {
                        const proofs = Array.isArray(monthData.proof_of_payment) ? 
                                        monthData.proof_of_payment : 
                                        [monthData.proof_of_payment];
                        
                        proofHtml = '<div class="photo-gallery">';
                        proofs.forEach((proof, idx) => {
                            if (proof && proof.trim() !== '') {
                                proofHtml += `<img src="/top_exchange/uploads/payments/${proof}" class="payment-proof-thumbnail" onclick="openImageModal(this.src)" alt="Payment Proof ${idx+1}">`;
                            }
                        });
                        proofHtml += '</div>';
                    } else {
                        proofHtml = 'No proof uploaded';
                    }
                    
                    const totalAmount = parseFloat(monthData.total_amount) || 0;
                    const remainingBalance = parseFloat(monthData.remaining_balance) || totalAmount;
                    
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
                            <td class="balance-column">PHP ${numberFormat(remainingBalance)}</td>
                            <td>${proofHtml}</td>
                            <td class="${statusClass}">${statusText}</td>
                            <td class="action-buttons">
                                <button class="update-payment-btn ${buttonClass}"
                                        ${isDisabled ? 'disabled' : ''}
                                        onclick="${isDisabled ? '' : `openUpdatePayment('${currentUsername}', ${index + 1}, '${month}', ${year}, ${totalAmount}, ${userBalance}, ${remainingBalance})`}"
                                        title="${isDisabled ? 'Cannot update payment for future months' : 'Update payment information'}">
                                    <i class="fas fa-money-bill-wave"></i>
                                    Pay
                                </button>
                                <button class="change-status-btn ${buttonClass}"
                                        ${isDisabled ? 'disabled' : ''}
                                        onclick="${isDisabled ? '' : `openChangeStatusModal(${index + 1}, ${year}, '${month}')`}"
                                        title="${isDisabled ? 'Cannot change status for future months' : 'Change payment status'}">
                                    <i class="fas fa-exchange-alt"></i>
                                    Status
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
                
                showToast('Failed to load payment history. Please try again.', 'error');
            }
        });
    }

    function viewMonthlyOrders(username, month, monthName, year) {
        $('#modalMonth').text(`${monthName} ${year}`);
        
        // Add cache-busting parameter
        const cacheBuster = `&_=${new Date().getTime()}`;
        
        // Show the modal
        $('#monthlyOrdersModal').show();
        
        // Center modal vertically
        centerModals();
        
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
            },
            error: function(xhr, status, error) {
                console.error('Ajax Error:', error);
                $('#monthlyOrdersBody').html(
                    '<tr><td colspan="5" style="color: red;">Error loading orders. Please try again.</td></tr>'
                );
                
                showToast('Failed to load orders. Please try again.', 'error');
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
                <div class="orders-table-container">
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

            orderDetailsHtml += '</tbody></table></div>';
            $('#orderDetailsContent').html(orderDetailsHtml);
            $('#orderDetailsModal').show();
            
            // Center modal vertically
            centerModals();
        } catch (e) {
            console.error('Error parsing orders JSON:', e);
            showToast('Error displaying order details. Please try again.', 'error');
        }
    }

    function openUpdatePayment(username, month, monthName, year, totalAmount, currentBalance, remainingBalance) {
        // Set modal title
        $('#paymentModalMonth').text(`${monthName} ${year}`);
        
        // Set form values
        $('#payment_username').val(username);
        $('#payment_month').val(month);
        $('#payment_year').val(year);
        $('#payment_total_amount').val(totalAmount);
        $('#payment_current_balance').val(currentBalance);
        $('#payment_remaining_balance').val(remainingBalance);
        
        // Set current values in the display
        $('#totalAmountValue').text(`PHP ${numberFormat(totalAmount)}`);
        $('#balanceValue').text(`PHP ${numberFormat(currentBalance)}`);
        $('#remainingBalance').text(`PHP ${numberFormat(remainingBalance)}`);
        
        // Set default value for payment amount input
        const suggestedPayment = remainingBalance > 0 ? remainingBalance : 0;
        $('#payment_amount').val(suggestedPayment.toFixed(2));
        
        // Clear previous proof preview
        $('#proofPreview').empty().hide();
        $('#payment_notes').val('');
        
        // Show the modal
        $('#updatePaymentModal').show();
        
        // Center modal vertically
        centerModals();
    }
    
    // Function to ensure modals are centered properly and don't go under the screen
    function centerModals() {
        // For modal windows that need vertical centering
        $('.modal-content').each(function() {
            // Make sure they're fully visible
            const $content = $(this);
            const windowHeight = window.innerHeight;
            const contentHeight = $content.outerHeight();
            
            // Ensure it's not taller than the viewport
            if (contentHeight > windowHeight * 0.9) {
                $content.css('height', windowHeight * 0.9);
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
                    preview = `<img src="${e.target.result}" alt="Proof Preview" class="proof-image" onclick="openImageModal('${e.target.result}')">`;
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
        formData.append('payment_status', 'Paid');
        
        // Get payment amount for toast notification
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;
        
        // Show loading message
        $('#proofPreview').html('<div>Saving payment information...</div>').show();
        $('.save-btn').prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: '../../backend/update_payment.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success toast with payment amount
                    showToast(`Payment of PHP ${numberFormat(paymentAmount)} recorded successfully!`, 'success');
                    
                    // Close the modal
                    closeModal('updatePaymentModal');
                    
                    // Update the user's balance in the display
                    userBalance = response.balance;
                    updateBalanceDisplay(userBalance);
                    
                    // Refresh the data
                    loadYearData(currentYear, true);
                } else {
                    // Show error toast
                    showToast('Error: ' + (response.message || 'Failed to update payment.'), 'error');
                    $('.save-btn').prop('disabled', false).text('Save Payment');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error submitting payment update:', error);
                showToast('Failed to update payment. Please try again.', 'error');
                $('.save-btn').prop('disabled', false).text('Save Payment');
            }
        });
    });

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function openImageModal(src) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        modal.style.display = "block";
        modalImg.src = src;
    }
    
    function closeImageModal() {
        document.getElementById("imageModal").style.display = "none";
    }

    function numberFormat(number) {
        return parseFloat(number).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal' || event.target.className === 'image-modal') {
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
    
    // Add resize event listener to ensure modals are properly centered
    window.addEventListener('resize', centerModals);
</script>
</body>
</html>