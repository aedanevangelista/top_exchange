<?php
// Current Date: 2025-05-02 00:59:38
// Author: Gemini, based on orders.php provided by user

session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Pending Orders'); // Ensure the user has access to the Pending Orders page

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'company', 'order_date', 'delivery_date', 'total_amount'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'order_date'; // Default sort column
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC'; // Default to descending
}

// Fetch active clients for the dropdown (copied from orders.php for consistency)
$clients = [];
$clients_with_company_address = []; // Array to store clients with their company addresses
$clients_with_company = []; // Array to store clients with their company names
$stmt = $conn->prepare("SELECT username, company_address, company FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clients[] = $row['username'];
    $clients_with_company_address[$row['username']] = $row['company_address'];
    $clients_with_company[$row['username']] = $row['company'];
}
$stmt->close();


// Fetch only pending orders for display in the table with sorting
$orders = []; // Initialize $orders as an empty array

// Modified query to join with clients_accounts to get the company information
$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status,
        o.special_instructions, COALESCE(o.company, c.company) as company
        FROM orders o
        LEFT JOIN clients_accounts c ON o.username = c.username
        WHERE o.status = 'Pending'";

// Add sorting - Handle special case for company column which comes from a COALESCE
if ($sort_column === 'company') {
    $sql .= " ORDER BY company {$sort_direction}, o.po_number DESC";
} else if ($sort_column === 'order_date' || $sort_column === 'delivery_date') {
    // For date columns, add a secondary sort by po_number to ensure latest added appears first when dates are the same
    $sql .= " ORDER BY o.{$sort_column} {$sort_direction}, o.po_number DESC";
} else {
    $sql .= " ORDER BY o.{$sort_column} {$sort_direction}, o.order_date DESC, o.po_number DESC";
}

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
    <title>Pending Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* --- Styles copied from orders.php for consistency --- */
        /* Main styles for the Order Summary table */
        .order-summary {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        /* Make the table properly aligned */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Apply proper scrolling to tbody only */
        .summary-table tbody {
            display: block;
            max-height: 250px;
            overflow-y: auto;
        }

        /* Make table header and rows consistent */
        .summary-table thead,
        .summary-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        /* Account for scrollbar width in header */
        .summary-table thead {
            width: calc(100% - 17px);
        }

        /* Cell styling for proper alignment and text overflow */
        .summary-table th,
        .summary-table td {
            padding: 8px;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border: 1px solid #ddd;
        }

        /* Specify consistent column widths */
        .summary-table th:nth-child(1),
        .summary-table td:nth-child(1) {
            width: 18%;
        }

        .summary-table th:nth-child(2),
        .summary-table td:nth-child(2) {
            width: 26%;
        }

        .summary-table th:nth-child(3),
        .summary-table td:nth-child(3) {
            width: 18%;
        }

        .summary-table th:nth-child(4),
        .summary-table td:nth-child(4) {
            width: 18%;
        }

        .summary-table th:nth-child(5),
        .summary-table td:nth-child(5) {
            width: 20%;
        }

        /* Style for the total section */
        .summary-total {
            margin-top: 10px;
            text-align: right;
            font-weight: bold;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        /* Style for quantity input fields */
        .summary-quantity {
            width: 80px;
            max-width: 100%;
            text-align: center;
        }

        /* Download button styles */
        .download-btn {
            padding: 6px 12px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
        }

        .download-btn:hover {
            background-color: #138496;
        }

        .download-btn i {
            margin-right: 5px;
        }

        /* PO PDF layout */
        .po-container {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
        }

        .po-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .po-company {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .po-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .po-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .po-left, .po-right {
            width: 48%;
        }

        .po-detail-row {
            margin-bottom: 10px;
        }

        .po-detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        .po-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .po-table th, .po-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .po-table th {
            background-color: #f2f2f2;
        }

        .po-total {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 30px;
        }

        #pdfPreview {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow: auto;
        }

        .pdf-container {
            background-color: white;
            width: 80%;
            margin: 50px auto;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .close-pdf {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 18px;
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
        }

        .pdf-actions {
            text-align: center;
            margin-top: 20px;
        }

        .pdf-actions button, .download-pdf-btn { /* Apply to both buttons */
            padding: 10px 20px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        /* Special Instructions Modal Styles */
        .instructions-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .instructions-modal-content {
            background-color: #ffffff;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 60%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-in-out;
            overflow: hidden;
            max-height: 90vh; /* 90% of the viewport height */
            overflow-y: auto; /* Add scroll if content exceeds max height */
            margin: 2vh auto; /* Center vertically with 5% top margin */
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .instructions-header {
            background-color: #2980b9;
            color: white;
            padding: 15px 20px;
            position: relative;
        }

        .instructions-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .instructions-po-number {
            font-size: 12px;
            margin-top: 5px;
            opacity: 0.9;
        }

        .instructions-body {
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eaeaea;
        }

        .instructions-body.empty {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 40px 20px;
        }

        .instructions-footer {
            padding: 15px 20px;
            text-align: right;
            background-color: #ffffff;
        }

        .close-instructions-btn {
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }

        .close-instructions-btn:hover {
            background-color: #2471a3;
        }

        /* Instructions button */
        .instructions-btn {
            padding: 6px 12px;
            background-color: #2980b9; /* Changed to match orders.php */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-width: 60px;
            text-align: center;
            transition: background-color 0.2s;
        }

        .instructions-btn:hover {
            background-color: #2471a3; /* Changed to match orders.php */
        }

        .no-instructions {
            color: #6c757d;
            font-style: italic;
        }

        /* Content for PDF */
        #contentToDownload {
            font-size: 14px;
        }

        #contentToDownload .po-table {
            font-size: 12px;
        }

        #contentToDownload .po-title {
            font-size: 16px;
        }

        #contentToDownload .po-company {
            font-size: 20px;
        }

        #contentToDownload .po-total {
            font-size: 12px;
        }

        /* Materials table styling (from orders.php pending modal) */
        .raw-materials-container {
            overflow: visible;
        }

        .raw-materials-container h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }

        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .materials-table tbody {
            display: block;
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }

        .materials-table thead,
        .materials-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .materials-table th,
        .materials-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 14px;
        }

        .materials-table thead {
            background-color: #f2f2f2;
            display: table;
            width: calc(100% - 17px); /* Adjust for scrollbar width */
            table-layout: fixed;
        }

        .materials-table th {
            background-color: #f2f2f2;
        }

        .material-sufficient {
            color: #28a745;
        }

        .material-insufficient {
            color: #dc3545;
        }

        .materials-status {
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        .status-sufficient {
            background-color: #d4edda;
            color: #155724;
            font-size: 16px;
        }

        .status-insufficient {
            background-color: #f8d7da;
            color: #721c24;
            font-size: 16px;
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
            font-size: 12px;
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

        /* Header styling */
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            width: 100%;
        }

        .orders-header h1 {
            flex: 1;
        }

        .search-container {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .add-order-btn { /* Copied from orders.php */
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            width: auto;
            white-space: nowrap;
            margin-left: auto;
        }

        .add-order-btn:hover {
            background-color: #357abf;
        }

        /* Style for special instructions textarea */
        #special_instructions {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical; /* Allow vertical resizing only */
            font-family: inherit;
            margin-bottom: 15px;
        }

        /* Confirmation modal styles (from orders.php) */
        .confirmation-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            overflow: hidden;
        }

        .confirmation-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 350px;
            max-width: 90%;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalPopIn 0.3s;
        }

        @keyframes modalPopIn {
            from {transform: scale(0.8); opacity: 0;}
            to {transform: scale(1); opacity: 1;}
        }

        .confirmation-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .confirmation-message {
            margin-bottom: 20px;
            color: #555;
            font-size: 14px;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .confirm-yes {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        .confirm-yes:hover {
            background-color: #357abf;
        }

        .confirm-no {
            background-color: #f1f1f1;
            color: #333;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .confirm-no:hover {
            background-color: #e1e1e1;
        }

        /* Toast customization */
        #toast-container .toast-close-button {
            display: none;
        }

        /* Inventory styling (from orders.php) */
        .inventory-table-container {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        .inventory-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .inventory-quantity {
            width: 60px;
            text-align: center;
        }

        .add-to-cart-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
        }

        .add-to-cart-btn:hover {
            background-color: #357abf;
        }

        .inventory-filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .inventory-filter-section input,
        .inventory-filter-section select {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .remove-item-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            margin-left: 5px;
        }

        .remove-item-btn:hover {
            background-color: #c82333;
        }

        .cart-quantity {
            width: 60px;
            text-align: center;
        }

        /* Modal positioning fix */
        #addOrderOverlay .overlay-content,
        #inventoryOverlay .overlay-content,
        #cartModal .overlay-content { /* Apply to all relevant modals */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 90vh;
            overflow-y: auto;
            margin: 0; /* Remove default margin */
        }

        /* Adjust status modal for raw materials */
        #statusModal .modal-content {
             max-height: 80vh; /* Allow more height */
             overflow-y: auto; /* Add scroll if needed */
        }

        /* --- End of copied styles --- */

        /* Sortable table headers specific styles */
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

        /* Status modal buttons */
        .status-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: center; /* Center buttons */
        }

        .modal-status-btn {
            padding: 10px 20px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            display: inline-flex; /* Use inline-flex */
            align-items: center;
            gap: 5px;
            flex: 0 1 auto; /* Prevent stretching */
        }

        .modal-status-btn.active {
            background-color: #ffc107; /* Yellow for active */
            color: #333; /* Dark text for yellow */
        }

        .modal-status-btn.active:hover {
            background-color: #e0a800;
        }

        .modal-status-btn.reject {
            background-color: #dc3545; /* Red for reject */
            color: white;
        }

         .modal-status-btn.reject:hover {
            background-color: #c82333;
        }

        .modal-status-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .modal-footer {
            margin-top: 15px; /* Add some space */
            text-align: center; /* Center cancel button */
        }

        .modal-cancel-btn {
            padding: 8px 15px;
            border-radius: 40px;
            border: 1px solid #ccc; /* Lighter border */
            background-color: #f8f9fa; /* Light background */
            color: #333; /* Dark text */
            cursor: pointer;
            font-weight: normal; /* Normal weight */
             transition: background-color 0.2s;
        }

        .modal-cancel-btn:hover {
            background-color: #e2e6ea;
        }

        /* View Orders button style (subtle) */
        .view-orders-btn {
            background: none;
            border: none;
            color: #0d6efd; /* Link color */
            cursor: pointer;
            padding: 0;
            font-size: inherit;
            text-decoration: underline;
        }
         .view-orders-btn:hover {
             color: #0a58ca;
         }
         .view-orders-btn i {
             margin-right: 3px;
         }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Pending Orders</h1>
           
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
           
            <button onclick="openAddOrderForm()" class="add-order-btn">
                <i class="fas fa-plus"></i> Add New Order
            </button>
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
                            <a href="<?= getSortUrl('company', $sort_column, $sort_direction) ?>">
                                Company <?= getSortIcon('company', $sort_column, $sort_direction) ?>
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
                        <th>Special Instructions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['company'] ?: 'No Company') ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td><button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['orders']) ?>')">
                                <i class="fas fa-clipboard-list"></i>
                                View</button></td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Status
                                </button>
                                <button class="download-btn" onclick="downloadPODirectly(
                                    '<?= htmlspecialchars($order['po_number']) ?>',
                                    '<?= htmlspecialchars($order['username']) ?>',
                                    '<?= htmlspecialchars($order['company'] ?? '') ?>', /* Ensure company is passed */
                                    '<?= htmlspecialchars($order['order_date']) ?>',
                                    '<?= htmlspecialchars($order['delivery_date']) ?>',
                                    '<?= htmlspecialchars($order['delivery_address']) ?>',
                                    '<?= htmlspecialchars(addslashes($order['orders'])) ?>',
                                    '<?= htmlspecialchars($order['total_amount']) ?>',
                                    '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>'
                                )">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-orders">No pending orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

   
    <div id="pdfPreview">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <div class="po-container">
                    <div class="po-header">
                        <div class="po-company" id="printCompany"></div>
                        <div class="po-title">Purchase Order</div>
                    </div>
                    <div class="po-details">
                        <div class="po-left">
                            <div class="po-detail-row">
                                <span class="po-detail-label">PO Number:</span>
                                <span id="printPoNumber"></span>
                            </div>
                            <div class="po-detail-row">
                                <span class="po-detail-label">Username:</span>
                                <span id="printUsername"></span>
                            </div>
                             <div class="po-detail-row">
                                <span class="po-detail-label">Delivery Address:</span>
                                <span id="printDeliveryAddress"></span>
                            </div>
                            <div class="po-detail-row" id="printInstructionsSection">
                                <span class="po-detail-label">Special Instructions:</span>
                                <span id="printSpecialInstructions" style="white-space: pre-wrap;"></span>
                            </div>
                        </div>
                        <div class="po-right">
                            <div class="po-detail-row">
                                <span class="po-detail-label">Order Date:</span>
                                <span id="printOrderDate"></span>
                            </div>
                            <div class="po-detail-row">
                                <span class="po-detail-label">Delivery Date:</span>
                                <span id="printDeliveryDate"></span>
                            </div>
                        </div>
                    </div>
                    <table class="po-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Packaging</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="printOrderItems">
                            </tbody>
                    </table>
                    <div class="po-total">
                        Total Amount: PHP <span id="printTotalAmount"></span>
                    </div>
                </div>
            </div>
             <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>

   
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" class="order-form" action="#">
                <div class="left-section">
                    <label for="username">Username:</label>
                    <select id="username" name="username" required onchange="generatePONumber();">
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client) ?>"
                                data-company-address="<?= htmlspecialchars($clients_with_company_address[$client] ?? '') ?>"
                                data-company="<?= htmlspecialchars($clients_with_company[$client] ?? '') ?>">
                                <?= htmlspecialchars($client) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="order_date">Order Date:</label>
                    <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label>
                    <input type="text" id="delivery_date" name="delivery_date" autocomplete="off" required>

                    <label for="delivery_address_type">Delivery Address:</label>
                    <select id="delivery_address_type" name="delivery_address_type" onchange="toggleDeliveryAddress()">
                        <option value="company">Company Address</option>
                        <option value="custom">Custom Address</option>
                    </select>

                    <div id="company_address_container">
                        <input type="text" id="company_address" name="company_address" readonly placeholder="Company address will appear here">
                    </div>

                    <div id="custom_address_container" style="display: none;">
                        <textarea id="custom_address" name="custom_address" rows="3" placeholder="Enter delivery address"></textarea>
                    </div>

                    <input type="hidden" name="delivery_address" id="delivery_address">
                    <label for="special_instructions">Special Instructions:</label>
                    <textarea id="special_instructions_add" name="special_instructions" rows="3" placeholder="Enter any special instructions here..."></textarea>


                    <div class="centered-button">
                        <button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()">
                            <i class="fas fa-box-open"></i> Select Products
                        </button>
                    </div>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Product</th>
                                    <th>Packaging</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="summaryBody">
                                </tbody>
                        </table>
                        <div class="summary-total">
                            Total: <span class="summary-total-amount">PHP 0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders">
                    <input type="hidden" name="total_amount" id="total_amount">
                     <input type="hidden" name="company" id="company_hidden">
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="save-btn" onclick="confirmAddOrder()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

   
    <div id="addConfirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Add Order</div>
            <div class="confirmation-message">Are you sure you want to add this new order?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeAddConfirmation()">No</button>
                <button class="confirm-yes" onclick="submitAddOrder()">Yes</button>
            </div>
        </div>
    </div>

   
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <div class="overlay-header">
                <h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2>
                <button class="cart-btn" onclick="window.openCartModal()">
                    <i class="fas fa-shopping-cart"></i> View Cart
                </button>
            </div>
            <div class="inventory-filter-section">
                <input type="text" id="inventorySearch" placeholder="Search products...">
                <select id="inventoryFilter">
                    <option value="all">All Categories</option>
                    </select>
            </div>
            <div class="inventory-table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="inventory">
                        </tbody>
                </table>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="cancel-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="done-btn" onclick="closeInventoryOverlay()">
                    <i class="fas fa-check"></i> Done
                </button>
            </div>
        </div>
    </div>

   
    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
            <div class="cart-table-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="cart">
                        </tbody>
                </table>
                <div class="no-products" style="display: none;">No products in the cart.</div>
            </div>
            <div class="cart-total" style="text-align: right; margin-bottom: 20px;">
                Total: <span class="total-amount">PHP 0.00</span>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="back-btn" onclick="closeCartModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="save-cart-btn" onclick="saveCartChanges()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>

            <div id="rawMaterialsContainer" class="raw-materials-container">
                <h3>Loading inventory status...</h3>
                </div>

            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="changeStatus('Active')" class="modal-status-btn active">
                    <i class="fas fa-check"></i> Active
                </button>
                <button onclick="changeStatus('Rejected')" class="modal-status-btn reject">
                    <i class="fas fa-ban"></i> Reject
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closeStatusModal()" class="modal-cancel-btn">
                    Cancel </button>
            </div>
        </div>
    </div>

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

    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content">
            <div class="instructions-header">
                <h3>Special Instructions</h3>
                <div class="instructions-po-number" id="instructionsPoNumber"></div>
            </div>
            <div class="instructions-body" id="instructionsContent">
                </div>
            <div class="instructions-footer">
                <button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPoNumber = ''; // For status modal
        let currentPOData = null; // For PDF generation
        let cartItems = []; // For Add Order functionality

        // Toast functionality (copied from orders.php)
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            // Adjusted icon logic slightly for better default
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-exclamation-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';

            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${iconClass}"></i>
                    <div class="message">${message}</div>
                </div>
            `; // Removed close button as per original orders.php style
            document.getElementById('toast-container').appendChild(toast);

            // Automatically remove the toast after 5 seconds
            setTimeout(() => {
                if (toast) toast.remove();
            }, 5000);
        }

        // --- Add Order Functionality (Copied & adapted from orders.php) ---

        function openAddOrderForm() {
            document.getElementById('addOrderOverlay').style.display = 'block'; // Use block instead of flex

            // Initialize order date with current date
            const today = new Date();
            const formattedDate = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');
            document.getElementById('order_date').value = formattedDate;

            // Initialize datepicker for delivery date
            if ($.datepicker) {
                 $("#delivery_date").datepicker("destroy"); // Destroy existing instance first
                 $("#delivery_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0 // Only allow future dates
                });
            } else {
                 console.warn("jQuery UI Datepicker not loaded.");
            }

            // Reset form fields and cart
            document.getElementById('addOrderForm').reset();
            document.getElementById('order_date').value = formattedDate; // Re-set order date after reset
            document.getElementById('company_address').value = ''; // Clear company address field
            document.getElementById('summaryBody').innerHTML = ''; // Clear summary
            document.querySelector('.summary-total-amount').textContent = 'PHP 0.00'; // Reset total
            document.getElementById('custom_address_container').style.display = 'none'; // Hide custom address
            document.getElementById('company_address_container').style.display = 'block'; // Show company address

            cartItems = []; // Reset cart items
        }

        function closeAddOrderForm() {
            document.getElementById('addOrderOverlay').style.display = 'none';
            // Optional: Reset form if needed, handled by openAddOrderForm now
            // document.getElementById('addOrderForm').reset();
        }

        function toggleDeliveryAddress() {
            const addressType = document.getElementById('delivery_address_type').value;
            const companyContainer = document.getElementById('company_address_container');
            const customContainer = document.getElementById('custom_address_container');

            if (addressType === 'company') {
                companyContainer.style.display = 'block';
                customContainer.style.display = 'none';
                document.getElementById('custom_address').value = ''; // Clear custom address if switching
            } else {
                companyContainer.style.display = 'none';
                customContainer.style.display = 'block';
                 // Optionally clear company address display field if needed
                // document.getElementById('company_address').value = '';
            }
            // Update company/PO based on username selection regardless of address type
            updateCompanyAndPONumber();
        }

        function generatePONumber() {
            // This is now primarily handled by updateCompanyAndPONumber to ensure it runs
            // after company info is fetched. Called directly only if username changes.
             updateCompanyAndPONumber();
        }

         function updateCompanyAndPONumber() {
             const usernameSelect = document.getElementById('username');
             const username = usernameSelect.value;
             if (!username) {
                 document.getElementById('po_number').value = '';
                 document.getElementById('company_address').value = '';
                 document.getElementById('company_hidden').value = '';
                 return;
             }

             // Get company address and company name from data attributes
             const selectedOption = usernameSelect.options[usernameSelect.selectedIndex];
             const companyAddress = selectedOption.getAttribute('data-company-address') || '';
             const company = selectedOption.getAttribute('data-company') || '';

             // Set company address display and hidden company name
             document.getElementById('company_address').value = companyAddress;
             document.getElementById('company_hidden').value = company; // Store company name

             // Generate PO number with current date and username
             const today = new Date();
             const datePart = today.getFullYear().toString().substr(-2) +
                             String(today.getMonth() + 1).padStart(2, '0') +
                             String(today.getDate()).padStart(2, '0');
             const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
             const poNumber = `PO-${datePart}-${username.substring(0, 3).toUpperCase()}-${random}`;

             document.getElementById('po_number').value = poNumber;

             // Ensure correct delivery address is selected/displayed based on current dropdown
             toggleDeliveryAddress(); // Re-run this to ensure consistency
         }


        function prepareOrderData() {
            // Update delivery address based on selection
            const addressType = document.getElementById('delivery_address_type').value;
            let deliveryAddress = '';

            if (addressType === 'company') {
                deliveryAddress = document.getElementById('company_address').value;
            } else {
                deliveryAddress = document.getElementById('custom_address').value;
            }

            if (!deliveryAddress || deliveryAddress.trim() === '') {
                 showToast('Please provide a valid delivery address.', 'error');
                 return false; // Stop if no valid address
            }
            document.getElementById('delivery_address').value = deliveryAddress;

            // Update hidden company name field based on selected user
            const usernameSelect = document.getElementById('username');
            const selectedOption = usernameSelect.options[usernameSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                 document.getElementById('company_hidden').value = selectedOption.getAttribute('data-company') || '';
            } else {
                 document.getElementById('company_hidden').value = '';
            }


            // Ensure we have orders data
            if (cartItems.length === 0) {
                showToast('Please select at least one product for this order.', 'error');
                return false;
            }

            // Check if username is selected
            if (!document.getElementById('username').value) {
                showToast('Please select a username for this order.', 'error');
                return false;
            }

            // Check if delivery date is set
            if (!document.getElementById('delivery_date').value) {
                showToast('Please select a delivery date for this order.', 'error');
                return false;
            }

             // Check if PO number was generated
             if (!document.getElementById('po_number').value) {
                 showToast('Could not generate PO Number. Please select a user.', 'error');
                 return false;
             }

            // Collect all item data into a JSON array
            const orders = [];
            let totalAmount = 0;

            cartItems.forEach(item => {
                orders.push({
                    category: item.category,
                    item_description: item.item_description,
                    packaging: item.packaging,
                    price: item.price,
                    quantity: item.quantity
                });
                totalAmount += item.price * item.quantity;
            });

            document.getElementById('orders').value = JSON.stringify(orders);
            document.getElementById('total_amount').value = totalAmount.toFixed(2);

            return true; // Data is prepared and valid
        }

        function confirmAddOrder() {
            // Prepare and validate the order data first
            if (prepareOrderData()) {
                // Show the confirmation modal
                document.getElementById('addConfirmationModal').style.display = 'block'; // Use block
            }
        }

        function closeAddConfirmation() {
            document.getElementById('addConfirmationModal').style.display = 'none';
        }

        function submitAddOrder() {
            document.getElementById('addConfirmationModal').style.display = 'none';

             // Ensure data is prepared again right before submission (safety check)
             if (!prepareOrderData()) {
                  console.error("Data preparation failed before submission.");
                  showToast('Failed to prepare order data before submission.', 'error');
                  return;
             }

            // Submit the form via AJAX
            const formData = new FormData(document.getElementById('addOrderForm'));
            // Append special instructions from the correct textarea
             formData.set('special_instructions', document.getElementById('special_instructions_add').value);


            // Disable button to prevent double submission
            const yesButton = document.querySelector('#addConfirmationModal .confirm-yes');
            const originalButtonText = yesButton ? yesButton.innerHTML : 'Yes';
            if (yesButton) {
                yesButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                yesButton.disabled = true;
            }

            fetch('/backend/add_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text().then(text => ({ ok: response.ok, status: response.status, text })))
            .then(({ ok, status, text }) => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    throw new Error(`Server returned non-JSON response (Status ${status}): ${text.substring(0, 100)}`);
                }

                if (ok && data.success) {
                    showToast('Order added successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || `An error occurred (Status ${status})`);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                showToast(`Error adding order: ${error.message}`, 'error');
                 // Re-enable button on error
                 if (yesButton) {
                     yesButton.innerHTML = originalButtonText;
                     yesButton.disabled = false;
                 }
            });
        }

        // --- Inventory and Cart Management (Copied & adapted from orders.php) ---

        function openInventoryOverlay() {
            document.getElementById('inventoryOverlay').style.display = 'flex';

            // Show loading state
            const inventoryBody = document.querySelector('#inventoryOverlay .inventory');
            inventoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory...</td></tr>';

            // Fetch inventory data
            fetch('/backend/get_inventory.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Invalid JSON response:", text);
                        throw new Error("Invalid JSON response from server");
                    }
                });
            })
             .then(data => {
                console.log("Raw inventory data:", data); // Debug log

                let inventoryItems = [];
                let categories = [];

                 // Handle different possible response structures
                 if (Array.isArray(data)) { // Simple array of items
                      inventoryItems = data;
                      categories = [...new Set(data.map(item => item.category))].filter(Boolean); // Extract categories
                 } else if (data && data.success && Array.isArray(data.inventory)) { // Object with success flag and inventory array
                      inventoryItems = data.inventory;
                      categories = data.categories || [...new Set(data.inventory.map(item => item.category))].filter(Boolean);
                 } else if(data && Array.isArray(data.inventory)) { // Object without success flag but with inventory array
                      inventoryItems = data.inventory;
                      categories = data.categories || [...new Set(data.inventory.map(item => item.category))].filter(Boolean);
                 } else {
                      console.error("Unexpected inventory data format:", data);
                      throw new Error("Unexpected inventory data format received from server.");
                 }

                 populateInventory(inventoryItems);
                 populateCategories(categories);
            })
            .catch(error => {
                console.error('Error fetching inventory:', error);
                showToast('Error fetching inventory: ' + error.message, 'error');
                inventoryBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Failed to load inventory. ${error.message}</td></tr>`;
            });
        }


        function populateInventory(inventory) {
            const inventoryBody = document.querySelector('#inventoryOverlay .inventory');
            inventoryBody.innerHTML = ''; // Clear loading/previous state

            if (!inventory || inventory.length === 0) {
                inventoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No inventory items found</td></tr>';
                return;
            }

            inventory.forEach(item => {
                 // Ensure essential fields exist
                 const productId = item.product_id || `temp_${Math.random().toString(36).substr(2, 9)}`; // Generate temp ID if missing
                 const category = item.category || 'Uncategorized';
                 const description = item.item_description || 'No Description';
                 const packaging = item.packaging || '';
                 const price = parseFloat(item.price) || 0;

                 if (!description || price <= 0) {
                      console.warn("Skipping invalid inventory item:", item);
                      return; // Skip items without description or price
                 }

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${category}</td>
                    <td>${description}</td>
                    <td>${packaging}</td>
                    <td>PHP ${price.toFixed(2)}</td>
                    <td>
                        <input type="number" class="inventory-quantity" min="1" max="9999" value="1" style="width: 60px;"> </td>
                    <td>
                        <button class="add-to-cart-btn" onclick="addToCart(this, '${productId}', '${category.replace(/'/g, "\\'")}', '${description.replace(/'/g, "\\'")}', '${packaging.replace(/'/g, "\\'")}', ${price})">
                            <i class="fas fa-cart-plus"></i> Add
                        </button>
                    </td>
                `;
                inventoryBody.appendChild(row);
            });
        }

        function populateCategories(categories) {
            const filterSelect = document.getElementById('inventoryFilter');

            // Clear existing options except "All Categories"
            while (filterSelect.options.length > 1) {
                filterSelect.remove(1);
            }

            if (!categories || categories.length === 0) {
                return;
            }

            // Add categories to filter dropdown
            categories.forEach(category => {
                if (!category) return; // Skip empty/null categories

                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                filterSelect.appendChild(option);
            });
        }


        function filterInventory() {
            const category = document.getElementById('inventoryFilter').value;
            const searchText = document.getElementById('inventorySearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#inventoryOverlay .inventory tr');

            rows.forEach(row => {
                 // Check if it's the 'no items' row
                 if (row.querySelector('td[colspan="6"]')) {
                     row.style.display = ''; // Always show 'no items' if it's the only row left
                     return;
                 }

                const categoryCell = row.querySelector('td:nth-child(1)')?.textContent || '';
                const productCell = row.querySelector('td:nth-child(2)')?.textContent || '';
                const packagingCell = row.querySelector('td:nth-child(3)')?.textContent || '';

                const categoryMatch = category === 'all' || categoryCell === category;
                const searchMatch = !searchText ||
                                    categoryCell.toLowerCase().includes(searchText) ||
                                    productCell.toLowerCase().includes(searchText) ||
                                    packagingCell.toLowerCase().includes(searchText);

                row.style.display = categoryMatch && searchMatch ? '' : 'none';
            });
        }


        function closeInventoryOverlay() {
            document.getElementById('inventoryOverlay').style.display = 'none';
            // Update the main order summary when closing inventory
             updateOrderSummary();
        }

        function addToCart(button, itemId, category, itemDescription, packaging, price) {
            const row = button.closest('tr');
            const quantityInput = row.querySelector('.inventory-quantity');
            const quantity = parseInt(quantityInput.value);

            if (isNaN(quantity) || quantity < 1) {
                showToast('Please enter a valid quantity (1 or more)', 'error');
                quantityInput.value = 1; // Reset to 1
                return;
            }

            // Find item key (combination of id and packaging for uniqueness)
             const itemKey = `${itemId}-${packaging}`;

            // Check if item already exists in cart
            const existingItemIndex = cartItems.findIndex(item => `${item.id}-${item.packaging}` === itemKey);


            if (existingItemIndex >= 0) {
                // Update quantity of existing item
                cartItems[existingItemIndex].quantity += quantity;
                 showToast(`Updated ${itemDescription} quantity in cart`, 'success');
            } else {
                // Add new item to cart
                cartItems.push({
                    id: itemId, // Use the actual product ID
                    category: category,
                    item_description: itemDescription,
                    packaging: packaging,
                    price: price,
                    quantity: quantity
                });
                 showToast(`Added ${quantity}x ${itemDescription} to cart`, 'success');
            }

            // Reset quantity input in the inventory table
            quantityInput.value = 1;

             // Optionally update cart modal display immediately if it's open
             if (document.getElementById('cartModal').style.display === 'flex') {
                  updateCartDisplay();
             }
             // Always update the main form summary
             updateOrderSummary();
        }

        function updateOrderSummary() {
            const summaryBody = document.getElementById('summaryBody');
            summaryBody.innerHTML = ''; // Clear previous summary

            let totalAmount = 0;

            if (cartItems.length === 0) {
                summaryBody.innerHTML = '<tr><td colspan="5" style="text-align: center; font-style: italic; color: #6c757d;">No products selected. Use "Select Products".</td></tr>';
            } else {
                cartItems.forEach(item => {
                    const row = document.createElement('tr');
                    const itemTotal = item.price * item.quantity;
                    totalAmount += itemTotal;

                    row.innerHTML = `
                        <td>${item.category}</td>
                        <td>${item.item_description}</td>
                        <td>${item.packaging}</td>
                        <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                        <td>${item.quantity}</td>
                    `;
                    summaryBody.appendChild(row);
                });
            }

            // Update total amount display
            document.querySelector('#addOrderOverlay .summary-total-amount').textContent = `PHP ${totalAmount.toFixed(2)}`;
             // Update hidden total amount field for form submission
             document.getElementById('total_amount').value = totalAmount.toFixed(2);
             // Update hidden orders JSON field
              document.getElementById('orders').value = JSON.stringify(cartItems.map(item => ({
                 category: item.category,
                 item_description: item.item_description,
                 packaging: item.packaging,
                 price: item.price,
                 quantity: item.quantity
             })));
        }

        // --- Cart Modal Functions (Copied & adapted from orders.php) ---

        window.openCartModal = function() {
            document.getElementById('cartModal').style.display = 'flex';
            updateCartDisplay(); // Populate cart when opened
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function updateCartDisplay() {
            const cartBody = document.querySelector('#cartModal .cart');
            const noProductsMessage = document.querySelector('#cartModal .no-products');
            const totalAmountElement = document.querySelector('#cartModal .total-amount');

            cartBody.innerHTML = ''; // Clear previous cart content

            if (cartItems.length === 0) {
                cartBody.innerHTML = ''; // Ensure it's empty
                noProductsMessage.style.display = 'block';
                totalAmountElement.textContent = 'PHP 0.00';
            } else {
                noProductsMessage.style.display = 'none';
                let totalAmount = 0;

                cartItems.forEach((item, index) => {
                     // Generate a unique key for the item in the cart context
                     const itemKey = `${item.id}-${item.packaging}`;
                    const row = document.createElement('tr');
                    const itemTotal = item.price * item.quantity;
                    totalAmount += itemTotal;

                    row.innerHTML = `
                        <td>${item.category}</td>
                        <td>${item.item_description}</td>
                        <td>${item.packaging}</td>
                        <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                        <td>
                            <input type="number" class="cart-quantity" min="1" max="9999" value="${item.quantity}"
                                data-item-key="${itemKey}" onchange="updateCartItemQuantity(this)"> <button class="remove-item-btn" onclick="removeCartItem('${itemKey}')"> <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    cartBody.appendChild(row);
                });

                // Update total amount display in cart modal
                totalAmountElement.textContent = `PHP ${totalAmount.toFixed(2)}`;
            }
        }


        function updateCartItemQuantity(input) {
            const itemKey = input.getAttribute('data-item-key');
            const newQuantity = parseInt(input.value);

            if (isNaN(newQuantity) || newQuantity < 1) {
                showToast('Please enter a valid quantity (1 or more)', 'error');
                 // Find the original quantity and reset
                 const itemIndex = cartItems.findIndex(item => `${item.id}-${item.packaging}` === itemKey);
                 if(itemIndex !== -1) input.value = cartItems[itemIndex].quantity;
                return;
            }

             // Find item and update quantity
             const itemIndex = cartItems.findIndex(item => `${item.id}-${item.packaging}` === itemKey);
             if (itemIndex !== -1) {
                 cartItems[itemIndex].quantity = newQuantity;
                 updateCartDisplay(); // Refresh cart modal display
                 updateOrderSummary(); // Also update the main form summary
             } else {
                  console.error("Could not find item in cart to update:", itemKey);
             }
        }

        function removeCartItem(itemKey) {
             const itemIndex = cartItems.findIndex(item => `${item.id}-${item.packaging}` === itemKey);
             if (itemIndex !== -1) {
                 cartItems.splice(itemIndex, 1); // Remove item from array
                 updateCartDisplay(); // Refresh cart modal display
                 updateOrderSummary(); // Also update the main form summary
                 showToast('Item removed from cart', 'info');
             } else {
                  console.error("Could not find item in cart to remove:", itemKey);
             }
        }


        function saveCartChanges() {
            // Simply update the main order summary and close the modal
            updateOrderSummary();
            closeCartModal();
            showToast('Cart changes saved to order summary.', 'success');
        }

        // --- End of Add Order / Inventory / Cart ---


        // --- Status Modal Functionality (Adapted from pending_orders.php original) ---

        function openStatusModal(poNumber, username, ordersJson) {
            currentPoNumber = poNumber; // Store PO number globally for this modal instance
            $('#statusMessage').text('Change status for order ' + poNumber + ' (' + username + ')');


            // Clear previous inventory check results and show loading
            $('#rawMaterialsContainer').html('<h3><i class="fas fa-spinner fa-spin"></i> Checking inventory requirements...</h3>');
            $('#activeStatusBtn').prop('disabled', true); // Disable button while checking

             $('#statusModal').show(); // Show modal immediately

            // Fetch inventory requirements for this specific order
            $.ajax({
                url: '/backend/check_raw_materials.php', // Reuse the check script
                type: 'POST',
                data: {
                    orders: ordersJson,
                    po_number: poNumber // Pass PO number if needed by backend
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayInventoryCheckResults(response); // Display results
                        updateOrderActionStatus(response); // Enable/disable 'Active' button
                    } else {
                        $('#rawMaterialsContainer').html(`
                            <div class="materials-status status-insufficient">
                                Error checking inventory: ${response.message || 'Unknown error'}. Cannot guarantee stock availability.
                            </div>
                        `);
                        $('#activeStatusBtn').prop('disabled', false); // Allow status change despite error
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Inventory Check AJAX Error:", status, error, xhr.responseText);
                    $('#rawMaterialsContainer').html(`
                        <div class="materials-status status-insufficient">
                            Server error checking inventory. Cannot guarantee stock availability.
                        </div>
                    `);
                     $('#activeStatusBtn').prop('disabled', false); // Allow status change despite error
                }
            });
        }


        function displayInventoryCheckResults(response) {
             let htmlContent = '';
             let needsManufacturing = false;

             // Display finished products status first
             if (response.finishedProducts && Object.keys(response.finishedProducts).length > 0) {
                 htmlContent += '<h3>Finished Products Status</h3>';
                 htmlContent += '<table class="materials-table"><thead><tr><th>Product</th><th>In Stock</th><th>Required</th><th>Status</th></tr></thead><tbody>';
                 Object.keys(response.finishedProducts).forEach(product => {
                     const data = response.finishedProducts[product];
                     const isSufficient = data.sufficient;
                     if (!isSufficient) needsManufacturing = true;
                     htmlContent += `
                         <tr>
                             <td>${product}</td>
                             <td>${parseInt(data.available) || 0}</td>
                             <td>${parseInt(data.required) || 0}</td>
                             <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                 ${isSufficient ? 'In Stock' : `Need ${data.shortfall} (Manufacture)`}
                             </td>
                         </tr>
                     `;
                 });
                 htmlContent += '</tbody></table>';
             } else {
                  // If no finished product info, assume manufacturing is needed based on flag
                  needsManufacturing = response.needsManufacturing === true;
             }


             // If manufacturing is needed, display raw materials status
             if (needsManufacturing) {
                  htmlContent += '<h3>Raw Materials for Manufacturing</h3>';
                  if (response.materials && Object.keys(response.materials).length > 0) {
                       htmlContent += '<table class="materials-table"><thead><tr><th>Material</th><th>Available</th><th>Required</th><th>Status</th></tr></thead><tbody>';
                       Object.keys(response.materials).forEach(material => {
                           const data = response.materials[material];
                           const isSufficient = data.sufficient;
                           htmlContent += `
                               <tr>
                                   <td>${material}</td>
                                   <td>${formatWeight(parseFloat(data.available) || 0)}</td>
                                   <td>${formatWeight(parseFloat(data.required) || 0)}</td>
                                   <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                       ${isSufficient ? 'Sufficient' : 'Insufficient'}
                                   </td>
                               </tr>
                           `;
                       });
                       htmlContent += '</tbody></table>';
                  } else {
                       htmlContent += '<p>No raw material information provided or needed.</p>';
                  }
             } else if (response.finishedProducts && Object.keys(response.finishedProducts).length > 0) {
                  // Only show this if finished products were checked and all were sufficient
                  htmlContent += '<p>All required finished products are in stock. No manufacturing needed.</p>';
             } else {
                  // Fallback message if no data is available
                  htmlContent += '<p>Inventory status could not be fully determined.</p>';
             }


             $('#rawMaterialsContainer').html(htmlContent);
        }


        // Helper function to format weight values (grams to kg)
        function formatWeight(weightInGrams) {
            if (isNaN(weightInGrams)) return 'N/A';
            if (weightInGrams >= 1000) {
                return (weightInGrams / 1000).toFixed(2) + ' kg';
            } else {
                return weightInGrams.toFixed(2) + ' g';
            }
        }

        function updateOrderActionStatus(response) {
            let canProceedToActive = true;
            let statusMessage = 'Inventory check complete.';
            const statusMessages = []; // Collect specific messages

            // Check finished products first
             const finishedProducts = response.finishedProducts || {};
             const needsManufacturingList = [];
             Object.keys(finishedProducts).forEach(product => {
                  if (!finishedProducts[product].sufficient) {
                       needsManufacturingList.push(product);
                  }
             });

             const needsManufacturing = needsManufacturingList.length > 0;

             if (!needsManufacturing && Object.keys(finishedProducts).length > 0) {
                   statusMessages.push('All finished products are in stock.');
                   canProceedToActive = true; // Can proceed if all finished goods are available
             } else if (needsManufacturing) {
                  statusMessages.push(`Manufacturing needed for: ${needsManufacturingList.join(', ')}.`);
                  // Now check raw materials ONLY if manufacturing is needed
                  const materials = response.materials || {};
                  const insufficientMaterials = [];
                   Object.keys(materials).forEach(material => {
                        if (!materials[material].sufficient) {
                             insufficientMaterials.push(material);
                        }
                   });

                  if (insufficientMaterials.length > 0) {
                       statusMessages.push(`Insufficient raw materials: ${insufficientMaterials.join(', ')}.`);
                       canProceedToActive = false; // Cannot proceed if raw materials are insufficient
                  } else if (Object.keys(materials).length > 0) {
                       statusMessages.push('Sufficient raw materials available for manufacturing.');
                       canProceedToActive = true; // Can proceed if raw materials are sufficient
                  } else {
                       // No raw materials info, but manufacturing needed - cannot proceed safely
                       statusMessages.push('Raw material stock could not be verified.');
                       canProceedToActive = false;
                  }
             } else {
                   // No finished product info or manufacturing needed flag - assume cannot proceed
                   statusMessages.push('Inventory status unclear.');
                   canProceedToActive = false;
             }


            // Update UI based on status
            $('#activeStatusBtn').prop('disabled', !canProceedToActive);

            // Add a summary status message
            const finalStatusClass = canProceedToActive ? 'status-sufficient' : 'status-insufficient';
             const finalMessage = canProceedToActive
                 ? 'Order can be activated. Inventory requirements met.'
                 : 'Order cannot be activated due to insufficient inventory.';
            statusMessages.push(`<span class="materials-status ${finalStatusClass}" style="display: block; margin-top: 10px; padding: 5px;">${finalMessage}</span>`);

            // Append all messages to the container
            $('#rawMaterialsContainer').append('<div style="margin-top: 15px;">' + statusMessages.join('<br>') + '</div>');
        }


        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            currentPoNumber = ''; // Clear the stored PO number
            // Clear inventory check results
             $('#rawMaterialsContainer').html('');
        }

        function changeStatus(newStatus) {
            if (!currentPoNumber) {
                console.error("No PO number selected for status change.");
                return;
            }

            // Only deduct materials if changing to Active
            const deductMaterials = (newStatus === 'Active');

            // Disable buttons
            const activeBtn = $('#activeStatusBtn');
            const rejectBtn = $('.modal-status-btn.reject');
            const cancelBtn = $('.modal-cancel-btn');
            const originalActiveText = activeBtn.html();
             const originalRejectText = rejectBtn.html();
            activeBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            rejectBtn.prop('disabled', true);
            cancelBtn.prop('disabled', true);


            $.ajax({
                type: 'POST',
                url: '/backend/update_order_status.php',
                data: {
                    po_number: currentPoNumber,
                    status: newStatus,
                    deduct_materials: deductMaterials ? '1' : '0' // Send as '1' or '0'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(`Status for ${currentPoNumber} changed to ${newStatus}.`, 'success');
                        closeStatusModal();
                        setTimeout(() => location.reload(), 1500); // Reload page after success
                    } else {
                        showToast(`Failed to change status: ${response.message || 'Unknown error'}`, 'error');
                        // Re-enable buttons on failure
                        activeBtn.html(originalActiveText).prop('disabled', false); // Restore text and enable
                         rejectBtn.prop('disabled', false);
                         cancelBtn.prop('disabled', false);
                         updateOrderActionStatus(response.inventoryStatus || {}); // Re-check inventory status if provided
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Status Change AJAX Error:', status, error, xhr.responseText);
                    let errorMsg = 'Server error changing status.';
                     try {
                          const errResponse = JSON.parse(xhr.responseText);
                          if (errResponse && errResponse.message) {
                               errorMsg = errResponse.message;
                          }
                     } catch(e) { /* Ignore parsing error */ }
                    showToast(errorMsg, 'error');
                    // Re-enable buttons on error
                     activeBtn.html(originalActiveText).prop('disabled', false);
                     rejectBtn.prop('disabled', false);
                     cancelBtn.prop('disabled', false);
                     // Maybe re-check inventory status here too?
                }
            });
        }

        // --- Order Details Modal ---

        function viewOrderDetails(ordersJson) {
            try {
                const orderDetails = JSON.parse(ordersJson);
                const orderDetailsBody = document.getElementById('orderDetailsBody');
                orderDetailsBody.innerHTML = ''; // Clear previous

                if (!orderDetails || orderDetails.length === 0) {
                    orderDetailsBody.innerHTML = '<tr><td colspan="5">No order items found.</td></tr>';
                     document.getElementById('orderDetailsModal').style.display = 'flex';
                    return;
                }

                orderDetails.forEach(product => {
                    const row = document.createElement('tr');
                    const price = parseFloat(product.price) || 0;
                     const quantity = parseInt(product.quantity) || 0;

                    row.innerHTML = `
                        <td>${product.category || 'N/A'}</td>
                        <td>${product.item_description || 'N/A'}</td>
                        <td>${product.packaging || 'N/A'}</td>
                        <td>PHP ${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td>${quantity}</td>
                    `;
                    orderDetailsBody.appendChild(row);
                });

                document.getElementById('orderDetailsModal').style.display = 'flex';
            } catch (e) {
                console.error('Error parsing order details JSON:', e, ordersJson);
                showToast('Error displaying order details. Invalid data format.', 'error');
                 // Optionally show the modal with an error message
                 const orderDetailsBody = document.getElementById('orderDetailsBody');
                 orderDetailsBody.innerHTML = '<tr><td colspan="5" style="color: red;">Error loading order details.</td></tr>';
                 document.getElementById('orderDetailsModal').style.display = 'flex';
            }
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }


        // --- Special Instructions Modal ---

        function viewSpecialInstructions(poNumber, instructions) {
             document.getElementById('instructionsPoNumber').textContent = 'PO Number: ' + poNumber;
             const contentEl = document.getElementById('instructionsContent');

             if (instructions && instructions.trim().length > 0) {
                 // Replace escaped newlines with actual newlines for display
                 contentEl.textContent = instructions.replace(/\\n/g, '\n');
                 contentEl.classList.remove('empty');
             } else {
                 contentEl.textContent = 'No special instructions provided for this order.';
                 contentEl.classList.add('empty');
             }

             document.getElementById('specialInstructionsModal').style.display = 'block'; // Use block
         }


        function closeSpecialInstructions() {
            document.getElementById('specialInstructionsModal').style.display = 'none';
        }


        // --- PDF Generation/Download Functions (Copied from orders.php) ---

        function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            try {
                 console.log("Preparing PDF for PO:", poNumber); // Debug log
                 // Decode potential HTML entities in JSON string before parsing
                 const decodedOrdersJson = $('<textarea />').html(ordersJson).text();
                 const orderItems = JSON.parse(decodedOrdersJson);

                // Store data for potential use by downloadPDF if preview was used (though not used here)
                currentPOData = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson: decodedOrdersJson, totalAmount, specialInstructions };

                // Populate the hidden PDF content structure silently
                $('#printCompany').text(company || 'No Company Name');
                $('#printPoNumber').text(poNumber);
                $('#printUsername').text(username);
                $('#printDeliveryAddress').text(deliveryAddress);
                $('#printOrderDate').text(orderDate);
                $('#printDeliveryDate').text(deliveryDate);

                // Format total amount
                $('#printTotalAmount').text(parseFloat(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                // Handle special instructions
                const instructionsSection = $('#printInstructionsSection');
                const instructionsContent = $('#printSpecialInstructions');
                if (specialInstructions && specialInstructions.trim() !== '') {
                    instructionsContent.text(specialInstructions.replace(/\\n/g, '\n')).css('white-space', 'pre-wrap');
                    instructionsSection.show();
                } else {
                    instructionsContent.text('');
                    instructionsSection.hide();
                }


                // Populate order items table
                const orderItemsBody = $('#printOrderItems');
                orderItemsBody.empty(); // Clear previous items

                if (orderItems && orderItems.length > 0) {
                    orderItems.forEach(item => {
                        const price = parseFloat(item.price) || 0;
                        const quantity = parseInt(item.quantity) || 0;
                        const itemTotal = price * quantity;

                        const row = $('<tr>').html(`
                            <td>${item.category || ''}</td>
                            <td>${item.item_description || ''}</td>
                            <td>${item.packaging || ''}</td>
                            <td>${quantity}</td>
                            <td>PHP ${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td>PHP ${itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        `);
                        orderItemsBody.append(row);
                    });
                } else {
                     orderItemsBody.append('<tr><td colspan="6">No items found in order data.</td></tr>');
                }


                // Get the element to convert to PDF
                const element = document.getElementById('contentToDownload');

                // Configure html2pdf options
                const opt = {
                    margin: [10, 10, 10, 10], // Margins in mm [top, left, bottom, right]
                    filename: `PO_${poNumber}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, logging: false }, // Reduce logging
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                // Generate and download PDF directly
                console.log("Starting PDF generation..."); // Debug log
                html2pdf().set(opt).from(element).save()
                    .then(() => {
                        console.log("PDF download initiated for:", poNumber); // Debug log
                        showToast(`Purchase Order ${poNumber} download started.`, 'success');
                    })
                    .catch(error => {
                        console.error('Error generating PDF:', error);
                        showToast('Error generating PDF. Check console for details.', 'error');
                    });

            } catch (e) {
                console.error('Error preparing PDF data:', e);
                showToast(`Error preparing PDF data: ${e.message}`, 'error');
            }
        }

        // Function to show PDF preview (optional, kept for potential future use)
        function showPDFPreview() {
             if (!currentPOData) {
                  showToast('No data loaded to preview.', 'warning');
                  return;
             }
             // Logic to populate the preview elements would go here if needed
             // ... (similar to downloadPODirectly's population logic) ...
             document.getElementById('pdfPreview').style.display = 'block'; // Use block
        }


        function closePDFPreview() {
            document.getElementById('pdfPreview').style.display = 'none';
        }

        // Function to download from the preview modal
        function downloadPDF() {
            if (!currentPOData) {
                showToast('No PO data available for download.', 'error');
                return;
            }
            const element = document.getElementById('contentToDownload');
            const opt = {
                margin: [10, 10, 10, 10],
                filename: `PO_${currentPOData.poNumber}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                showToast(`Purchase Order ${currentPOData.poNumber} has been downloaded.`, 'success');
                closePDFPreview();
            }).catch(error => {
                console.error('Error generating PDF from preview:', error);
                showToast('Error generating PDF from preview.', 'error');
            });
        }

        // --- Document Ready and Event Listeners ---

        $(document).ready(function() {
            // Client-side Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                let found = false;
                $(".orders-table tbody tr").each(function() {
                    let row = $(this);
                     // Exclude the 'no orders found' row from hiding
                     if (row.find('.no-orders').length > 0) {
                          return; // Skip this row
                     }
                    let text = row.text().toLowerCase();
                    if (text.includes(searchText)) {
                        row.show();
                        found = true;
                    } else {
                        row.hide();
                    }
                });
                 // Show/hide 'no orders found' row based on search results
                 const noOrdersRow = $(".orders-table tbody .no-orders").closest('tr');
                 if (noOrdersRow.length > 0) {
                      if (found || $(".orders-table tbody tr:visible").not(noOrdersRow).length > 0) {
                           noOrdersRow.hide();
                      } else {
                           // Only show if the table originally had data
                           if ($(".orders-table tbody tr").not(noOrdersRow).length > 0) {
                                noOrdersRow.show();
                           } else {
                                // If table was initially empty, keep it hidden
                                noOrdersRow.hide();
                           }
                      }
                 }
            });

            // Trigger search on button click
            $(".search-btn").on("click", function() {
                $("#searchInput").trigger("input");
            });

             // Inventory search/filter listeners (copied from orders.php)
             $('#inventorySearch').on('input', filterInventory);
             $('#inventoryFilter').on('change', filterInventory);

             // Datepicker initialization for Add Order form
             // Done within openAddOrderForm to ensure it's initialized each time

             // Update company/PO Number when username changes in Add Order form
             $('#username').on('change', generatePONumber);

            // Close modals on clicking outside
             window.addEventListener('click', function(event) {
                 // Status Modal
                 const statusModal = document.getElementById('statusModal');
                 if (event.target == statusModal) {
                     closeStatusModal();
                 }
                  // Order Details Modal
                  const detailsModal = document.getElementById('orderDetailsModal');
                  if (event.target == detailsModal) {
                      closeOrderDetailsModal();
                  }
                  // Special Instructions Modal
                  const instructionsModal = document.getElementById('specialInstructionsModal');
                  if (event.target == instructionsModal) {
                      closeSpecialInstructions();
                  }
                   // PDF Preview Modal
                   const pdfPreviewModal = document.getElementById('pdfPreview');
                   if (event.target == pdfPreviewModal) {
                       closePDFPreview();
                   }
                   // Add Order Overlay
                   const addOrderModal = document.getElementById('addOrderOverlay');
                   if (event.target == addOrderModal) {
                       // Consider if closing on outside click is desired for this form
                       // closeAddOrderForm();
                   }
                    // Inventory Overlay
                    const inventoryModal = document.getElementById('inventoryOverlay');
                    if (event.target == inventoryModal) {
                         // Consider if closing on outside click is desired
                         // closeInventoryOverlay();
                    }
                    // Cart Modal
                    const cartModal = document.getElementById('cartModal');
                    if (event.target == cartModal) {
                         // Consider if closing on outside click is desired
                         // closeCartModal();
                    }

                 // Confirmation Modals (copied from orders.php)
                 const confirmationModals = [
                     'addConfirmationModal'
                     // Add other confirmation modal IDs here if needed later
                 ];
                 confirmationModals.forEach(modalId => {
                     const modal = document.getElementById(modalId);
                     if (modal && event.target === modal) {
                         modal.style.display = 'none';
                     }
                 });
             });
        });
    </script>
</body>
</html>