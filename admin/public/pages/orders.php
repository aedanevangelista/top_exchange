<?php
// Current Date: 2025-05-01 17:24:13
// Author: aedanevangelista

session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

// Handle sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_columns = ['po_number', 'username', 'order_date', 'delivery_date', 'progress', 'total_amount'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'order_date'; // Default sort column
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC'; // Default to descending
}

// Fetch active clients for the dropdown
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

// Fetch all drivers for the driver assignment dropdown
$drivers = [];
$stmt = $conn->prepare("SELECT id, name FROM drivers WHERE availability = 'Available' AND current_deliveries < 20 ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $drivers[] = $row;
}
$stmt->close();

// Modified query to show Active, Pending, and Rejected orders with sorting
$orders = []; // Initialize $orders as an empty array
$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.progress, o.driver_assigned,
        o.company, o.special_instructions,
        IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name
        FROM orders o
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number
        LEFT JOIN drivers d ON da.driver_id = d.id
        WHERE o.status IN ('Active', 'Pending', 'Rejected')";

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
    <title>Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
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
            width: calc(100% - 17px); /* Adjust 17px based on scrollbar width */
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
        .summary-table th:nth-child(1), /* Category */
        .summary-table td:nth-child(1) {
            width: 20%;
        }

        .summary-table th:nth-child(2), /* Product */
        .summary-table td:nth-child(2) {
            width: 30%;
        }

        .summary-table th:nth-child(3), /* Packaging */
        .summary-table td:nth-child(3) {
            width: 15%;
        }

        .summary-table th:nth-child(4), /* Price */
        .summary-table td:nth-child(4) {
            width: 15%;
        }

        .summary-table th:nth-child(5), /* Quantity */
        .summary-table td:nth-child(5) {
            width: 10%;
        }
         /* Add width for the new Remove button column */
        .summary-table th:nth-child(6),
        .summary-table td:nth-child(6) {
            width: 10%;
            text-align: center;
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

        /* Existing styles... */

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

        .po-signature {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .po-signature-block {
            width: 40%;
            text-align: center;
        }

        .po-signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 10px;
            padding-top: 40px;
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

        .pdf-actions button {
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
            background-color: #2980b9;
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
            background-color: #2471a3;
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

        /* Status badge styles */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .status-active {
            background-color: #d1e7ff;
            color: #084298;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-delivery {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        /* Driver badge for not allowed status */
        .driver-badge.driver-not-allowed {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .btn-info {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 3px;
        }

        /* Materials table styling */
        .raw-materials-container {
            overflow: visible;
            margin-bottom: 15px; /* Add margin below materials section */
        }

        .raw-materials-container h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 16px; /* Adjust font size */
        }

        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .materials-table tbody {
            display: block;
            max-height: 200px; /* Reduce max height for better fit */
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
            padding: 6px; /* Reduce padding */
            text-align: left;
            border: 1px solid #ddd;
            font-size: 13px; /* Adjust font size */
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
            padding: 8px; /* Reduce padding */
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px; /* Adjust font size */
            margin-top: 10px; /* Add margin above status message */
        }

        .status-sufficient {
            background-color: #d4edda;
            color: #155724;
        }

        .status-insufficient {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Status badges for progress column */
        .active-progress {
            background-color: #d1e7ff;
            color: #084298;
        }

        .pending-progress, .rejected-progress {
            background-color: #f8d7da;
            color: #842029;
        }

        /* Order details footer styling */
        .order-details-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .total-amount {
            font-weight: bold;
            font-size: 16px;
            padding: 5px 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
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

        /* Header styling - Fix button positioning */
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

        .add-order-btn {
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
        #special_instructions_textarea { /* Ensure ID matches HTML */
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical; /* Allow vertical resizing only */
            font-family: inherit;
            margin-bottom: 15px;
        }

        /* Confirmation modal styles */
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

        /* Toast customization - remove X button */
        #toast-container .toast-close-button {
            display: none;
        }

        /* Inventory styling fixes */
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
        #cartModal .overlay-content,
        #driverModal .overlay-content { /* Apply consistent positioning */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 90vh;
            overflow-y: auto;
            margin: 0; /* Remove default margin */
            background-color: #fff; /* Ensure background */
            padding: 20px; /* Ensure padding */
            border-radius: 8px; /* Add border radius */
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); /* Add shadow */
            width: 80%; /* Adjust width as needed */
            max-width: 800px; /* Optional max width */
        }

        /* Adjust Status Modal content max height and scrolling */
        #statusModal .modal-content,
        #pendingStatusModal .modal-content,
        #rejectedStatusModal .modal-content {
             max-height: 85vh; /* Allow slightly more height */
             overflow-y: auto; /* Ensure scroll */
        }

    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Orders</h1>
            <!-- Updated search section to exactly match order_history.php -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
            <!-- Added "Add New Order" button similar to pending_orders.php -->
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
                            <a href="<?= getSortUrl('order_date', $sort_column, $sort_direction) ?>">
                                Order Date <?= getSortIcon('order_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">
                                Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('progress', $sort_column, $sort_direction) ?>">
                                Progress <?= getSortIcon('progress', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Orders</th>
                        <th class="sortable">
                            <a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">
                                Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Special Instructions</th>
                        <th>Drivers</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr data-current-status="<?= htmlspecialchars($order['status']) ?>"> <!-- Store current status -->
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td>
                                    <?php if ($order['status'] === 'Active'): ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                    <?php else: ?>
                                        <span class="status-badge <?= strtolower($order['status']) ?>-progress">
                                            <?= $order['status'] === 'Pending' ? 'Pending' : 'Not Available' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                            <i class="fas fa-clipboard-list"></i>
                                            View
                                        </button>
                                    <?php else: ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order['orders'])) ?>', '<?= htmlspecialchars($order['status']) ?>')">
                                            <i class="fas fa-clipboard-list"></i>
                                            View
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>')">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['status'] === 'Active'): ?>
                                        <?php if ($order['driver_assigned'] && !empty($order['driver_name'])): ?>
                                            <div class="driver-badge">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                            </div>
                                            <button class="driver-btn" onclick="confirmDriverChange('<?= htmlspecialchars($order['po_number']) ?>', <?= $order['driver_id'] ?>, '<?= htmlspecialchars($order['driver_name']) ?>')">
                                                <i class="fas fa-exchange-alt"></i> Change
                                            </button>
                                        <?php else: ?>
                                            <div class="driver-badge driver-not-assigned">
                                                <i class="fas fa-user-slash"></i> Not Assigned
                                            </div>
                                            <button class="driver-btn assign-driver-btn" onclick="confirmDriverAssign('<?= htmlspecialchars($order['po_number']) ?>')">
                                                <i class="fas fa-user-plus"></i> Assign
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="driver-badge driver-not-allowed">
                                            <i class="fas fa-ban"></i> Not Available
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($order['status']) {
                                        case 'Active': $statusClass = 'status-active'; break;
                                        case 'Pending': $statusClass = 'status-pending'; break;
                                        case 'Rejected': $statusClass = 'status-rejected'; break;
                                        case 'For Delivery': $statusClass = 'status-delivery'; break;
                                        case 'Completed': $statusClass = 'status-completed'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>', 'Pending')">
                                            <i class="fas fa-exchange-alt"></i> Status
                                        </button>
                                    <?php elseif ($order['status'] === 'Active'): ?>
                                        <!-- Pass 'Active' as original status -->
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', 'Active')">
                                            <i class="fas fa-exchange-alt"></i> Status
                                        </button>
                                    <?php elseif ($order['status'] === 'Rejected'): ?>
                                         <!-- Pass 'Rejected' as original status -->
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', 'Rejected')">
                                            <i class="fas fa-exchange-alt"></i> Status
                                        </button>
                                    <?php endif; ?>
                                    <button class="download-btn" onclick="confirmDownloadPO(
                                        '<?= htmlspecialchars($order['po_number']) ?>',
                                        '<?= htmlspecialchars($order['username']) ?>',
                                        '<?= htmlspecialchars($order['company'] ?? '') ?>',
                                        '<?= htmlspecialchars($order['order_date']) ?>',
                                        '<?= htmlspecialchars($order['delivery_date']) ?>',
                                        '<?= htmlspecialchars($order['delivery_address']) ?>',
                                        '<?= htmlspecialchars(addslashes($order['orders'])) ?>',
                                        '<?= htmlspecialchars($order['total_amount']) ?>',
                                        '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>'
                                    )">
                                        <i class="fas fa-file-pdf"></i> Download PDF
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="no-orders">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- PO PDF Preview Section -->
    <div id="pdfPreview" style="display: none;"> <!-- Ensure it's hidden initially -->
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <div class="po-container">
                    <!-- ... PO content ... -->
                     <div class="po-header">
                        <div class="po-company" id="printCompany"></div>
                        <div class="po-title">Purchase Order</div>
                    </div>

                    <div class="po-details">
                        <div class="po-left">
                            <div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div>
                            <div class="po-detail-row"><span class="po-detail-label">Username:</span> <span id="printUsername"></span></div>
                            <div class="po-detail-row"><span class="po-detail-label">Delivery Address:</span> <span id="printDeliveryAddress"></span></div>
                            <div class="po-detail-row" id="printInstructionsSection" style="display: none;"><span class="po-detail-label">Special Instructions:</span> <span id="printSpecialInstructions" style="white-space: pre-wrap;"></span></div>
                        </div>
                        <div class="po-right">
                            <div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div>
                            <div class="po-detail-row"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div>
                        </div>
                    </div>

                    <table class="po-table">
                        <thead>
                            <tr><th>Category</th><th>Product</th><th>Packaging</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr>
                        </thead>
                        <tbody id="printOrderItems"></tbody>
                    </table>
                    <div class="po-total">Total Amount: PHP <span id="printTotalAmount"></span></div>
                </div>
            </div>
            <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>

    <!-- Special Instructions Modal -->
    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content">
            <div class="instructions-header">
                <h3>Special Instructions</h3>
                <div class="instructions-po-number" id="instructionsPoNumber"></div>
            </div>
            <div class="instructions-body" id="instructionsContent"></div>
            <div class="instructions-footer">
                <button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal with Progress Tracking -->
    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details (<span id="orderStatus"></span>)</h2>
            <div class="order-details-container">
                <table class="order-details-table">
                    <thead>
                        <tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th id="status-header-cell">Status</th></tr>
                    </thead>
                    <tbody id="orderDetailsBody"></tbody>
                </table>
                <div class="order-details-footer"><div class="total-amount" id="orderTotalAmount">PHP 0.00</div></div>
                <div class="item-progress-info" id="overall-progress-info" style="margin-top: 10px; display: none;">
                    <div class="progress-info-label">Overall Order Progress:</div>
                    <div class="progress-bar-container" style="margin-top: 5px;">
                        <div class="progress-bar" id="overall-progress-bar" style="width: 0%"></div>
                        <div class="progress-text" id="overall-progress-text">0%</div>
                    </div>
                </div>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="save-progress-btn" onclick="confirmSaveProgress()"><i class="fas fa-save"></i> Save Progress</button>
            </div>
        </div>
    </div>

    <!-- Status Modal (for Active Orders) -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <!-- Material container removed as check is not needed here -->
            <div class="status-buttons">
                <button onclick="confirmStatusAction('For Delivery')" class="modal-status-btn delivery"><i class="fas fa-truck"></i> For Delivery<div class="btn-info">(Requires: 100% Progress & Driver)</div></button>
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Will return stock if applicable)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject<div class="btn-info">(Will return stock if applicable)</div></button>
            </div>
            <div class="modal-footer"><button onclick="closeStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Rejected Status Modal -->
    <div id="rejectedStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="rejectedStatusMessage"></p>
            <div class="status-buttons">
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return to pending status)</div></button>
            </div>
            <div class="modal-footer"><button onclick="closeRejectedStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Pending Status Modal (Includes Material Check) -->
    <div id="pendingStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="pendingStatusMessage"></p>
            <div id="rawMaterialsContainer" class="raw-materials-container"><h3>Loading inventory status...</h3></div>
            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active"><i class="fas fa-check"></i> Active<div class="btn-info">(Will deduct stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn reject"><i class="fas fa-ban"></i> Reject</button>
            </div>
            <div class="modal-footer"><button onclick="closePendingStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="overlay" style="display: none;">
        <div class="overlay-content driver-modal-content">
            <h2><i class="fas fa-user"></i> <span id="driverModalTitle">Assign Driver</span></h2>
            <p id="driverModalMessage"></p>
            <div class="driver-selection">
                <label for="driverSelect">Select Driver:</label>
                <select id="driverSelect">
                    <option value="0">-- Select a driver --</option>
                    <?php foreach ($drivers as $driver): ?> <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="driver-modal-buttons">
                <button class="cancel-btn" onclick="closeDriverModal()"><i class="fas fa-times"></i> Cancel</button>
                <button class="save-btn" onclick="confirmDriverAssignment()"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>

    <!-- Add New Order Overlay -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form">
                <div class="left-section">
                    <label for="username">Username:</label>
                    <select id="username" name="username" required onchange="generatePONumber();">
                         <option value="" disabled selected>Select User</option>
                         <?php foreach ($clients as $client): ?> <option value="<?= htmlspecialchars($client) ?>" data-company-address="<?= htmlspecialchars($clients_with_company_address[$client] ?? '') ?>" data-company="<?= htmlspecialchars($clients_with_company[$client] ?? '') ?>"><?= htmlspecialchars($client) ?></option> <?php endforeach; ?>
                    </select>
                    <label for="order_date">Order Date:</label> <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label> <input type="text" id="delivery_date" name="delivery_date" autocomplete="off" required>
                    <label for="delivery_address_type">Delivery Address:</label>
                    <select id="delivery_address_type" name="delivery_address_type" onchange="toggleDeliveryAddress()"> <option value="company">Company Address</option> <option value="custom">Custom Address</option> </select>
                    <div id="company_address_container"><input type="text" id="company_address" name="company_address" readonly placeholder="Company address"></div>
                    <div id="custom_address_container" style="display: none;"><textarea id="custom_address" name="custom_address" rows="3" placeholder="Enter delivery address"></textarea></div>
                    <input type="hidden" name="delivery_address" id="delivery_address">
                    <label for="special_instructions_textarea">Special Instructions:</label> <textarea id="special_instructions_textarea" name="special_instructions" rows="3" placeholder="Enter instructions..."></textarea>
                    <div class="centered-button"><button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()"><i class="fas fa-box-open"></i> Select Products</button></div>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <table class="summary-table">
                           <thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead>
                           <tbody id="summaryBody"></tbody>
                        </table>
                        <div class="summary-total">Total: <span class="summary-total-amount">PHP 0.00</span></div>
                    </div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders">
                    <input type="hidden" name="total_amount" id="total_amount">
                    <input type="hidden" name="company" id="company_hidden">
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="save-btn" onclick="confirmAddOrder()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation modals -->
    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Add Order</div><div class="confirmation-message">Add this new order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No</button><button class="confirm-yes" onclick="submitAddOrder()">Yes</button></div></div></div>
    <div id="driverConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Driver Assignment</div><div class="confirmation-message">Assign this driver?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDriverConfirmation()">No</button><button class="confirm-yes" onclick="assignDriver()">Yes</button></div></div></div>
    <div id="saveProgressConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Save Progress</div><div class="confirmation-message">Save this progress?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeSaveProgressConfirmation()">No</button><button class="confirm-yes" onclick="saveProgressChanges()">Yes</button></div></div></div>
    <div id="statusConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Status Change</div><div class="confirmation-message" id="statusConfirmationMessage">Change status?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeStatusConfirmation()">No</button><button class="confirm-yes" onclick="executeStatusChange()">Yes</button></div></div></div>
    <div id="downloadConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Download</div><div class="confirmation-message">Download PO as PDF?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDownloadConfirmation()">No</button><button class="confirm-yes" onclick="downloadPODirectly()">Yes</button></div></div></div>

    <!-- Inventory Overlay -->
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
             <div class="overlay-header">
                 <h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2>
                 <button class="cart-btn" onclick="window.openCartModal()"><i class="fas fa-shopping-cart"></i> View Cart (<span id="cartItemCount">0</span>)</button>
             </div>
             <div class="inventory-filter-section"><input type="text" id="inventorySearch" placeholder="Search..."><select id="inventoryFilter"><option value="all">All Categories</option></select></div>
             <div class="inventory-table-container"><table class="inventory-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="inventory"></tbody></table></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="done-btn" onclick="closeInventoryOverlay()"><i class="fas fa-check"></i> Done</button></div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
             <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
             <div class="cart-table-container"><table class="cart-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="cart"></tbody></table><div class="no-products" style="display: none; text-align: center; padding: 20px;">Cart is empty.</div></div>
             <div class="cart-total" style="text-align: right; margin-bottom: 20px; font-weight: bold; font-size: 1.1em;">Total: <span class="total-amount">PHP 0.00</span></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeCartModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="save-cart-btn" onclick="saveCartChanges()"><i class="fas fa-save"></i> Save Changes</button></div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPoNumber = '';
        let currentOrderOriginalStatus = ''; // Store the status when modal opens
        let currentOrderItems = []; // For progress tracking
        let completedItems = []; // For progress tracking
        let quantityProgressData = {}; // For progress tracking
        let itemProgressPercentages = {}; // For progress tracking
        let itemContributions = {}; // For progress tracking
        let overallProgress = 0; // For progress tracking
        let currentDriverId = 0; // For driver assignment
        let currentPOData = null; // For PDF generation preview
        let selectedStatus = ''; // For status change confirmation
        let poDownloadData = null; // For direct PDF download data
        let cartItems = []; // Holds items for the new order being created

        // Toast functionality
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<div class="toast-content"><i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i><div class="message">${message}</div></div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }

        // --- Status Change Logic ---

        // Opens modal for ACTIVE orders (NO material check)
        function confirmStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);
            // **NO Material Check Here**
            $('#statusModal').css('display', 'flex');
        }

        // Opens modal for REJECTED orders
        function confirmRejectedStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status
            $('#rejectedStatusModal').data('po_number', poNumber); // Store context for closing confirmation
            $('#rejectedStatusMessage').text(`Change status for rejected order ${poNumber} (${username})`);
            $('#rejectedStatusModal').css('display', 'flex');
        }

        // Opens modal for PENDING orders (WITH material check)
        function confirmPendingStatusChange(poNumber, username, ordersJson, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status
            $('#pendingStatusModal').data('po_number', poNumber); // Store context
            $('#pendingStatusMessage').text('Change order status for ' + poNumber);

            const materialContainer = $('#rawMaterialsContainer');
            materialContainer.html('<h3>Loading inventory status...</h3>');
            $('#pendingStatusModal').css('display', 'flex');

            try {
                 if (!ordersJson) throw new Error("Order items data missing.");
                 JSON.parse(ordersJson); // Validate

                $.ajax({
                    url: '/backend/check_raw_materials.php', type: 'POST',
                    data: { orders: ordersJson, po_number: poNumber }, dataType: 'json',
                    success: function(response) {
                        console.log("Inventory Check (Pending):", response);
                        if (response.success) {
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer');
                            if (needsMfg && response.materials) {
                                displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section');
                            } else if (needsMfg) {
                                $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Info unavailable.</p>');
                            } else if (!needsMfg && response.finishedProducts) {
                                materialContainer.append('<p>All required products in stock.</p>');
                                $('#rawMaterialsContainer #raw-materials-section').remove();
                            } else if (!response.finishedProducts && !response.materials) {
                                 materialContainer.html('<h3>Inventory Status</h3><p>No details available.</p>');
                            }
                            updatePendingOrderActionStatus(response); // Enable/disable 'Active' button
                        } else {
                            materialContainer.html(`<h3>Inventory Check Error</h3><p style="color:red;">${response.message || 'Unknown error'}</p><p>Status change allowed, but check failed.</p>`);
                            $('#activeStatusBtn').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        materialContainer.html(`<h3>Server Error</h3><p style="color:red;">Could not check inventory: ${error}</p><p>Status change allowed, but check failed.</p>`);
                        $('#activeStatusBtn').prop('disabled', false);
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText);
                    }
                });
            } catch (e) {
                materialContainer.html(`<h3>Data Error</h3><p style="color:red;">${e.message}</p><p>Status change allowed, but check failed.</p>`);
                $('#activeStatusBtn').prop('disabled', false);
                console.error("Error parsing ordersJson for pending:", e);
            }
        }

        // Opens the CONFIRMATION modal
        function confirmStatusAction(status) {
            selectedStatus = status; // Store the TARGET status

            let confirmationMsg = `Are you sure you want to change the status to ${selectedStatus}?`;
            // Add specific warnings based on the TARGET status and the ORIGINAL status
            if (selectedStatus === 'Active') { // Moving TO Active (from Pending)
                 confirmationMsg += ' This will deduct required materials/products from inventory.';
            } else if (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')) { // Moving FROM Active
                 confirmationMsg += ' This will attempt to return deducted stock to inventory.';
            } else if (selectedStatus === 'For Delivery') {
                 confirmationMsg += ' Ensure progress is 100% and a driver is assigned.';
            }

            $('#statusConfirmationMessage').text(confirmationMsg);
            $('#statusConfirmationModal').css('display', 'block');

            // Hide the originating status modal
            $('#statusModal, #pendingStatusModal, #rejectedStatusModal').css('display', 'none');
        }

        // Closes the CONFIRMATION modal, reopens the originating status modal
        function closeStatusConfirmation() {
            $('#statusConfirmationModal').css('display', 'none');
            // Reopen the correct modal based on original status
             if (currentOrderOriginalStatus === 'Pending') {
                 $('#pendingStatusModal').css('display', 'flex');
             } else if (currentOrderOriginalStatus === 'Rejected') {
                 $('#rejectedStatusModal').css('display', 'flex');
             } else if (currentOrderOriginalStatus === 'Active') {
                  $('#statusModal').css('display', 'flex');
             }
             // Reset original status tracker? Maybe not needed if it's always set when opening a modal.
             // currentOrderOriginalStatus = '';
        }

        // Called after clicking "Yes" on the confirmation modal
        function executeStatusChange() {
            $('#statusConfirmationModal').css('display', 'none'); // Hide confirmation

            // Determine flags based on TARGET status and ORIGINAL status
            let deductMaterials = (selectedStatus === 'Active'); // Deduct ONLY when target is Active
            let returnMaterials = (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')); // Return ONLY when moving FROM Active to Pending/Rejected

            // Special check for 'For Delivery' requirements
            if (selectedStatus === 'For Delivery') {
                if (currentOrderOriginalStatus !== 'Active') {
                     showToast('Error: Can only mark Active orders for delivery.', 'error');
                     closeRelevantStatusModals();
                     return; // Should not happen based on UI, but good check
                }
                showToast('Checking requirements for delivery...', 'info');
                fetch(`/backend/check_order_driver.php?po_number=${currentPoNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (!data.driver_assigned) { showToast('Error: Assign driver first.', 'error'); closeRelevantStatusModals(); return; }
                            if (data.progress < 100) { showToast('Error: Progress must be 100%.', 'error'); closeRelevantStatusModals(); return; }
                            // Requirements met, proceed (no material adjustment needed Active -> For Delivery)
                            updateOrderStatus(selectedStatus, false, false);
                        } else { showToast('Error checking requirements: ' + data.message, 'error'); closeRelevantStatusModals(); }
                    })
                    .catch(error => { console.error('Delivery check error:', error); showToast('Error checking requirements: ' + error, 'error'); closeRelevantStatusModals(); });
            } else {
                 // Proceed with other status changes
                 updateOrderStatus(selectedStatus, deductMaterials, returnMaterials);
            }
        }

        // Performs the backend AJAX call to update status
        function updateOrderStatus(status, deductMaterials, returnMaterials) {
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);
            formData.append('deduct_materials', deductMaterials ? '1' : '0');
            formData.append('return_materials', returnMaterials ? '1' : '0');

            console.log("Sending status update:", { po_number: currentPoNumber, status: status, deduct: deductMaterials, return: returnMaterials });

            fetch('/backend/update_order_status.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => { // Get text first to handle non-JSON errors
                 try {
                     const jsonData = JSON.parse(text);
                     if (!response.ok) throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     return jsonData;
                 } catch (e) { console.error('Invalid JSON:', text); throw new Error('Invalid server response.'); }
            }))
            .then(data => {
                console.log("Status update response:", data);
                if (data.success) {
                    let message = `Status updated to ${status} successfully`;
                    if (deductMaterials) message += '. Inventory deducted.';
                    if (returnMaterials) message += '. Inventory returned.';
                    showToast(message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else { throw new Error(data.message || 'Unknown error updating status.'); } // Throw error to be caught below
            })
            .catch(error => {
                console.error("Update status fetch error:", error);
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => { closeRelevantStatusModals(); }); // Close modals regardless
        }


        // --- Modal Closing Helpers ---
        function closeStatusModal() { $('#statusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number'); }
        function closePendingStatusModal() { $('#pendingStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); $('#rawMaterialsContainer').html('<h3>Loading...</h3>'); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        // --- Material Display Helpers (used by Pending modal) ---
        function formatWeight(weightInGrams) { /* ... as before ... */
            if (weightInGrams >= 1000) return (weightInGrams / 1000).toFixed(2) + ' kg';
            return (weightInGrams ? parseFloat(weightInGrams).toFixed(2) : '0.00') + ' g';
        }
        function displayFinishedProducts(productsData, containerSelector) { /* ... as before, ensure it returns true/false for needsMfg ... */
             const container = $(containerSelector); if (!container.length) return false;
             let html = `<h3>Finished Products Status</h3>`;
             if (!productsData || Object.keys(productsData).length === 0) { html += '<p>No info available.</p>'; container.html(html).append('<div id="raw-materials-section"></div>'); return true; } // Assume check needed if no product info
             html += `<table class="materials-table"><thead>...</thead><tbody>`; // Add table structure
             Object.keys(productsData).forEach(product => { html += `<tr>... ${productsData[product].sufficient ? 'In Stock' : `Need ${productsData[product].shortfall} more`} ...</tr>`; });
             html += `</tbody></table>`; container.html(html);
             const needsMfg = Object.values(productsData).some(p => !p.sufficient);
             if (needsMfg) container.append('<div id="raw-materials-section"><h3>Raw Materials</h3><p>Loading...</p></div>');
             return needsMfg;
        }
        function displayRawMaterials(materialsData, containerSelector) { /* ... as before, ensure it returns true/false for allSufficient ... */
             const container = $(containerSelector); if (!container.length) return true;
             let html = '<h3>Raw Materials Required</h3>';
             if (!materialsData || Object.keys(materialsData).length === 0) { container.html(html + '<p>No info available.</p>'); return true; }
             let allSufficient = true; let insufficient = [];
             html += `<table class="materials-table"><thead>...</thead><tbody>`;
             Object.keys(materialsData).forEach(material => { if (!materialsData[material].sufficient) { allSufficient = false; insufficient.push(material); } html += `<tr>... ${materialsData[material].sufficient ? 'Sufficient' : 'Insufficient'} ...</tr>`; });
             html += `</tbody></table>`;
             const msg = allSufficient ? 'All raw materials sufficient.' : `Insufficient: ${insufficient.join(', ')}.`;
             const cls = allSufficient ? 'status-sufficient' : 'status-insufficient';
             container.html(html + `<p class="materials-status ${cls}">${msg}</p>`);
             return allSufficient;
        }
        function updatePendingOrderActionStatus(response) { /* ... as before, enables/disables #activeStatusBtn based on checks ... */
             let canActivate = true; let msg = 'Ready to activate.'; const cont = $('#rawMaterialsContainer');
             const prods = response.finishedProducts || {}; const allProdsInStock = Object.keys(prods).length > 0 && Object.values(prods).every(p => p.sufficient);
             if (!allProdsInStock && response.needsManufacturing) {
                 const canMfgAll = Object.values(prods).every(p => p.sufficient || p.canManufacture !== false);
                 if (!canMfgAll) { canActivate = false; msg = 'Cannot activate: Missing ingredients.'; }
                 else {
                     const mats = response.materials || {}; const allMatsSufficient = Object.keys(mats).length > 0 && Object.values(mats).every(m => m.sufficient);
                     if (!allMatsSufficient) { canActivate = false; msg = 'Cannot activate: Insufficient raw materials.'; }
                     else { msg = 'Manufacturing required. Materials sufficient. Ready.'; }
                 }
             } else if (allProdsInStock) { msg = 'All products in stock. Ready.'; }
             else if (Object.keys(prods).length === 0 && !response.needsManufacturing) { msg = 'Inventory details unclear.'; /* canActivate = false; */ }
             $('#activeStatusBtn').prop('disabled', !canActivate);
             const cls = canActivate ? 'status-sufficient' : 'status-insufficient';
             let statEl = cont.children('.materials-status'); if (statEl.length) statEl.removeClass('status-sufficient status-insufficient').addClass(cls).text(msg); else cont.append(`<p class="materials-status ${cls}">${msg}</p>`);
        }

        // --- Order Details Modal Functions (Progress Tracking - Unchanged) ---
        function viewOrderDetails(poNumber){ /* ... */ }
        function viewOrderInfo(ordersJson, orderStatus){ /* ... */ }
        function toggleQuantityProgress(itemIndex){ /* ... */ }
        function updateUnitStatus(checkbox){ /* ... */ }
        function updateItemProgress(itemIndex){ /* ... */ }
        function updateOverallProgressDisplay(){ /* ... */ }
        function updateOverallProgress(){ /* ... */ }
        function updateItemStatusBasedOnUnits(itemIndex, allUnitsComplete){ /* ... */ }
        function selectAllUnits(itemIndex, quantity){ /* ... */ }
        function deselectAllUnits(itemIndex, quantity){ /* ... */ }
        function updateRowStyle(checkbox){ /* ... */ }
        function closeOrderDetailsModal(){ /* ... */ }
        function confirmSaveProgress(){ /* ... */ }
        function closeSaveProgressConfirmation(){ /* ... */ }
        function saveProgressChanges(){ /* ... */ }

        // --- Driver Assignment Modal Functions (Unchanged) ---
        function confirmDriverAssign(poNumber){ /* ... */ }
        function confirmDriverChange(poNumber, driverId, driverName){ /* ... */ }
        function closeDriverModal(){ /* ... */ }
        function confirmDriverAssignment(){ /* ... */ }
        function closeDriverConfirmation(){ /* ... */ }
        function assignDriver(){ /* ... */ }

        // --- PDF Download Functions (Unchanged) ---
        function confirmDownloadPO(...args){ /* ... */ }
        function closeDownloadConfirmation(){ /* ... */ }
        function downloadPODirectly(){ /* ... */ }
        function downloadPDF(){ /* ... */ }
        function closePDFPreview(){ /* ... */ }

        // --- Special Instructions Modal (Unchanged) ---
        function viewSpecialInstructions(poNumber, instructions){ /* ... */ }
        function closeSpecialInstructions(){ /* ... */ }


        // --- Add New Order Form Functions ---

        // Initialize Datepicker with M/W/F and minDate constraint
        function initializeDeliveryDatePicker() {
             if ($.datepicker) {
                 $("#delivery_date").datepicker("destroy");
                 $("#delivery_date").datepicker({
                     dateFormat: 'yy-mm-dd', minDate: 1, // Tomorrow
                     beforeShowDay: function(date) {
                         const day = date.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
                         const isMWF = (day === 1 || day === 3 || day === 5);
                         return [isMWF, isMWF ? "" : "ui-state-disabled", isMWF ? "M/W/F" : "Unavailable"];
                     }
                 });
             }
        }

        function openAddOrderForm() {
            $('#addOrderForm')[0].reset();
            cartItems = [];
            updateOrderSummary();
            updateCartItemCount();
            const today = new Date();
            const formattedDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            $('#order_date').val(formattedDate);
            initializeDeliveryDatePicker();
            toggleDeliveryAddress();
            generatePONumber(); // Use timestamp format now
            $('#addOrderOverlay').css('display', 'block');
        }

        function closeAddOrderForm() { $('#addOrderOverlay').css('display', 'none'); }
        function toggleDeliveryAddress() { /* ... as before ... */
             const type = $('#delivery_address_type').val(); $('#company_address_container').toggle(type === 'company'); $('#custom_address_container').toggle(type === 'custom');
             let addr = ''; if (type === 'company') addr = $('#username option:selected').data('company-address') || ''; else addr = $('#custom_address').val();
             $('#company_address').val(type === 'company' ? addr : ''); $('#delivery_address').val(addr);
        }
         $('#custom_address').on('input', function() { if ($('#delivery_address_type').val() === 'custom') $('#delivery_address').val($(this).val()); });

        // **REVERTED**: Generate PO Number using Timestamp
        function generatePONumber() {
            const usernameSelect = $('#username'); const username = usernameSelect.val();
            const companyHiddenInput = $('#company_hidden');
            if (username) {
                const selectedOption = usernameSelect.find('option:selected');
                const companyAddress = selectedOption.data('company-address') || '';
                const companyName = selectedOption.data('company') || '';
                $('#company_address').val(companyAddress); companyHiddenInput.val(companyName);
                if ($('#delivery_address_type').val() === 'company') $('#delivery_address').val(companyAddress);

                // Timestamp-based PO Number
                const today = new Date();
                const datePart = `${today.getFullYear().toString().substr(-2)}${String(today.getMonth() + 1).padStart(2, '0')}${String(today.getDate()).padStart(2, '0')}`;
                const timePart = Date.now().toString().slice(-6); // Unique time component
                const poNumber = `PO-${datePart}-${username.substring(0, 3).toUpperCase()}-${timePart}`;
                $('#po_number').val(poNumber);
            } else {
                 $('#company_address').val(''); companyHiddenInput.val(''); $('#po_number').val('');
                 if ($('#delivery_address_type').val() === 'company') $('#delivery_address').val('');
            }
        }

        // Prepare data, ensures product_id is included
        function prepareOrderData() {
            toggleDeliveryAddress(); // Update address field
            const deliveryAddress = $('#delivery_address').val();
            // Update hidden company name
            const userSelect = $('#username'); if (userSelect.val()) $('#company_hidden').val(userSelect.find('option:selected').data('company') || ''); else $('#company_hidden').val('');

            // Validations
            if (cartItems.length === 0) { showToast('Select products.', 'error'); return false; }
            if (!$('#username').val()) { showToast('Select user.', 'error'); return false; }
            if (!$('#delivery_date').val()) { showToast('Select delivery date.', 'error'); return false; }
            if (!deliveryAddress || !deliveryAddress.trim()) { showToast('Enter delivery address.', 'error'); return false; }
            if (!$('#po_number').val()) { showToast('PO Number missing.', 'error'); return false; }

            // Collect item data with product_id
            let totalAmount = 0;
            const orders = cartItems.map(item => {
                 totalAmount += item.price * item.quantity;
                 return { product_id: item.id, category: item.category, item_description: item.item_description, packaging: item.packaging, price: item.price, quantity: item.quantity };
            });
            $('#orders').val(JSON.stringify(orders));
            $('#total_amount').val(totalAmount.toFixed(2));
            return true;
        }

        function confirmAddOrder() { if (prepareOrderData()) $('#addConfirmationModal').css('display', 'block'); }
        function closeAddConfirmation() { $('#addConfirmationModal').css('display', 'none'); }
        function submitAddOrder() { /* ... AJAX submission logic as before ... */
             $('#addConfirmationModal').css('display', 'none');
             const form = document.getElementById('addOrderForm'); const formData = new FormData(form);
             fetch('/backend/add_order.php', { method: 'POST', body: formData })
             .then(response => response.json())
             .then(data => { if (data.success) { showToast('Order added!', 'success'); closeAddOrderForm(); setTimeout(() => { window.location.reload(); }, 1500); } else { showToast(data.message || 'Error adding order.', 'error'); } })
             .catch(error => { console.error('Add order error:', error); showToast('Server error adding order.', 'error'); });
        }


        // --- Inventory Overlay and Cart Functions (Unchanged from previous fix) ---
        function openInventoryOverlay(){ /* ... */ }
        function populateInventory(inventory){ /* ... */ }
        function populateCategories(categories){ /* ... */ }
        function filterInventory(){ /* ... */ }
        function closeInventoryOverlay(){ /* ... */ }
        function addToCart(...args){ /* ... */ } // Ensure this adds item.id
        function updateOrderSummary(){ /* ... */ }
        function removeSummaryItem(index){ /* ... */ }
        function updateCartItemCount(){ /* ... */ }
        window.openCartModal = function(){ /* ... */ }
        function closeCartModal(){ /* ... */ }
        function updateCartDisplay(){ /* ... */ }
        function updateCartItemQuantity(input){ /* ... */ }
        function removeCartItem(index){ /* ... */ }
        function saveCartChanges(){ /* ... */ }


        // Document ready
        $(document).ready(function() {
            // Search for main orders table
            $("#searchInput").on("input", function() { /* ... */ });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input"));

            // Initialize Add Order form state
            initializeDeliveryDatePicker();
            toggleDeliveryAddress();
            generatePONumber();

            // Close modals on outside click
            window.addEventListener('click', function(event) { /* ... as before ... */ });
        });
    </script>
</body>
</html>