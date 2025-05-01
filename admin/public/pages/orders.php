<?php
// Current Date: 2025-05-01 17:15:40
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
        // Attempt to decode orders JSON to check format - FOR DEBUGGING IF NEEDED
        // $decoded_orders = json_decode($row['orders'], true);
        // if (json_last_error() !== JSON_ERROR_NONE) {
        //     error_log("Failed to decode orders JSON for PO: " . $row['po_number'] . " - Error: " . json_last_error_msg());
        //     error_log("Original JSON: " . $row['orders']);
        // }
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
        #special_instructions {
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
                            <tr>
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
                                        case 'Active':
                                            $statusClass = 'status-active';
                                            break;
                                        case 'Pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'status-rejected';
                                            break;
                                        case 'For Delivery':
                                            $statusClass = 'status-delivery';
                                            break;
                                        case 'Completed':
                                            $statusClass = 'status-completed';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Status
                                        </button>
                                    <?php elseif ($order['status'] === 'Active'): ?>
                                        <!-- Pass orders JSON to confirmStatusChange -->
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Status
                                        </button>
                                    <?php elseif ($order['status'] === 'Rejected'): ?>
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
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
                            <!-- Items will be populated here -->
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

    <!-- Special Instructions Modal -->
    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content">
            <div class="instructions-header">
                <h3>Special Instructions</h3>
                <div class="instructions-po-number" id="instructionsPoNumber"></div>
            </div>
            <div class="instructions-body" id="instructionsContent">
                <!-- Instructions will be displayed here -->
            </div>
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
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th id="status-header-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
                <div class="order-details-footer">
                    <div class="total-amount" id="orderTotalAmount">PHP 0.00</div>
                </div>

                <!-- Overall progress info (only shown for Active orders with progress tracking) -->
                <div class="item-progress-info" id="overall-progress-info" style="margin-top: 10px; display: none;">
                    <div class="progress-info-label">Overall Order Progress:</div>
                    <div class="progress-bar-container" style="margin-top: 5px;">
                        <div class="progress-bar" id="overall-progress-bar" style="width: 0%"></div>
                        <div class="progress-text" id="overall-progress-text">0%</div>
                    </div>
                </div>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="save-progress-btn" onclick="confirmSaveProgress()">
                    <i class="fas fa-save"></i> Save Progress
                </button>
            </div>
        </div>
    </div>

    <!-- Status Modal (for Active Orders) -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>

            <!-- Container for Material Info -->
            <div id="activeOrderMaterialsContainer" class="raw-materials-container">
                 <h3>Loading inventory status...</h3>
            </div>

            <div class="status-buttons">
                <button onclick="confirmStatusAction('For Delivery')" class="modal-status-btn delivery">
                    <i class="fas fa-truck"></i> For Delivery
                    <div class="btn-info">(Requires: 100% Progress and Driver)</div>
                </button>
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending">
                    <i class="fas fa-clock"></i> Pending
                    <div class="btn-info">(Will disable driver & progress)</div>
                </button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected">
                    <i class="fas fa-times-circle"></i> Reject
                    <div class="btn-info">(Will disable driver & progress)</div>
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closeStatusModal()" class="modal-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Rejected Status Modal -->
    <div id="rejectedStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="rejectedStatusMessage"></p>
            <div class="status-buttons">
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending">
                    <i class="fas fa-clock"></i> Pending
                    <div class="btn-info">(Return to pending status)</div>
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closeRejectedStatusModal()" class="modal-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Pending Status Modal -->
    <div id="pendingStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="pendingStatusMessage"></p>

            <!-- Enhanced Raw Materials Section -->
            <div id="rawMaterialsContainer" class="raw-materials-container">
                <h3>Loading inventory status...</h3>
                <!-- Content will be populated dynamically -->
            </div>

            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active">
                    <i class="fas fa-check"></i> Active
                </button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn reject">
                    <i class="fas fa-ban"></i> Reject
                </button>
            </div>
            <div class="modal-footer">
                <button onclick="closePendingStatusModal()" class="modal-cancel-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
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
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="driver-modal-buttons">
                <button class="cancel-btn" onclick="closeDriverModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="save-btn" onclick="confirmDriverAssignment()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <!-- Add New Order Overlay -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form"> <!-- Removed action attribute -->
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

                    <!-- New Delivery Address selection -->
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
                    <!-- <input type="hidden" name="special_instructions" id="special_instructions_hidden"> No longer needed if using FormData -->
                    <!-- Add special instructions field -->
                    <label for="special_instructions">Special Instructions:</label>
                    <textarea id="special_instructions_textarea" name="special_instructions" rows="3" placeholder="Enter any special instructions here..."></textarea> <!-- Changed id slightly to avoid conflict -->

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
                                    <th>Action</th> <!-- Added Action column header -->
                                </tr>
                            </thead>
                            <tbody id="summaryBody">
                                <!-- Summary will be populated here -->
                            </tbody>
                        </table>
                        <div class="summary-total">
                            Total: <span class="summary-total-amount">PHP 0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders"> <!-- This will hold the JSON -->
                    <input type="hidden" name="total_amount" id="total_amount">
                    <input type="hidden" name="company" id="company_hidden"> <!-- Added hidden field for company -->

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

    <!-- Confirmation modal for adding order -->
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

    <!-- Confirmation modal for driver assignment -->
    <div id="driverConfirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Driver Assignment</div>
            <div class="confirmation-message">Are you sure you want to assign this driver?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeDriverConfirmation()">No</button>
                <button class="confirm-yes" onclick="assignDriver()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Confirmation modal for save progress -->
    <div id="saveProgressConfirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Save Progress</div>
            <div class="confirmation-message">Are you sure you want to save this progress?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeSaveProgressConfirmation()">No</button>
                <button class="confirm-yes" onclick="saveProgressChanges()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Confirmation modal for status change -->
    <div id="statusConfirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Status Change</div>
            <div class="confirmation-message" id="statusConfirmationMessage">Are you sure you want to change the status?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeStatusConfirmation()">No</button>
                <button class="confirm-yes" onclick="executeStatusChange()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Confirmation modal for download -->
    <div id="downloadConfirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Download</div>
            <div class="confirmation-message">Are you sure you want to download this PO as PDF?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeDownloadConfirmation()">No</button>
                <button class="confirm-yes" onclick="downloadPODirectly()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Inventory Overlay for Selecting Products -->
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <div class="overlay-header">
                <h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2>
                <button class="cart-btn" onclick="window.openCartModal()">
                    <i class="fas fa-shopping-cart"></i> View Cart (<span id="cartItemCount">0</span>)
                </button>
            </div>
            <div class="inventory-filter-section">
                <input type="text" id="inventorySearch" placeholder="Search products...">
                <select id="inventoryFilter">
                    <option value="all">All Categories</option>
                    <!-- Populate with categories dynamically -->
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
                        <!-- Inventory list will be populated here -->
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

    <!-- Cart Modal for Viewing Selected Products -->
    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
            <div class="cart-table-container">
                <table class="cart-table"> <!-- Ensure this class exists or add styles -->
                     <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Action</th> <!-- Added Action column -->
                        </tr>
                    </thead>
                    <tbody class="cart">
                        <!-- Selected products list will be populated here -->
                    </tbody>
                </table>
                <div class="no-products" style="display: none; text-align: center; padding: 20px;">No products in the cart.</div>
            </div>
            <div class="cart-total" style="text-align: right; margin-bottom: 20px; font-weight: bold; font-size: 1.1em;">
                Total: <span class="total-amount">PHP 0.00</span>
            </div>
            <div class="form-buttons" style="margin-top: 20px;">
                <button type="button" class="back-btn" onclick="closeCartModal()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="save-cart-btn" onclick="saveCartChanges()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPoNumber = '';
        let currentOrderItems = [];
        let completedItems = [];
        let quantityProgressData = {};
        let itemProgressPercentages = {};
        let itemContributions = {}; // How much each item contributes to the total
        let overallProgress = 0;
        let currentDriverId = 0;
        let currentPOData = null; // For PDF generation
        let selectedStatus = ''; // For status change confirmation

        // Variables for PO download
        let poDownloadData = null;

        // Variables for cart and inventory
        let cartItems = []; // Holds items for the new order being created

        // Toast functionality
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) {
                 console.error("Toast container not found!");
                 return;
             }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i>
                    <div class="message">${message}</div>
                </div>
            `;
            toastContainer.appendChild(toast);

            // Automatically remove the toast after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Status modal functions with confirmations
        // Handles opening the status modal for ACTIVE orders and checking materials
        function confirmStatusChange(poNumber, username, ordersJson) {
            currentPoNumber = poNumber;
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);

            // Clear previous data and show loading state in the specific container
            const materialContainer = $('#activeOrderMaterialsContainer');
            materialContainer.html('<h3>Loading inventory status...</h3>');

            // Show the modal immediately (loading state is visible)
            $('#statusModal').css('display', 'flex');

            // Parse the orders JSON and check materials
            try {
                 if (!ordersJson) {
                     throw new Error("Order items data is missing.");
                 }
                 // Basic check if ordersJson is a valid JSON string before parsing
                 JSON.parse(ordersJson); // This will throw error if invalid

                $.ajax({
                    url: '/backend/check_raw_materials.php',
                    type: 'POST',
                    data: {
                        orders: ordersJson,
                        po_number: poNumber // Send PO number for context if backend needs it
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log("Inventory Check Response (Active Order):", response); // Debugging
                        if (response.success) {
                            // Use existing functions but target the correct container
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#activeOrderMaterialsContainer');

                            if (needsMfg && response.materials) {
                                displayRawMaterials(response.materials, '#activeOrderMaterialsContainer #raw-materials-section');
                            } else if (needsMfg) {
                                 // If manufacturing needed but no materials data provided
                                 $('#activeOrderMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Raw material information not available.</p>');
                            } else if (!needsMfg && response.finishedProducts) {
                                // If finished products were displayed but no manufacturing needed
                                materialContainer.append('<p>All required products are currently in stock.</p>');
                                $('#activeOrderMaterialsContainer #raw-materials-section').remove(); // Clean up placeholder if needed
                            } else {
                                // Neither finished products nor raw materials needed (or error in response structure)
                                // Keep the initial message or provide a generic one
                                if (!response.finishedProducts && !response.materials) {
                                     materialContainer.html('<h3>Inventory Status</h3><p>No specific inventory details available for this order.</p>');
                                }
                            }

                            // Add overall status message (adapted from updateOrderActionStatus logic)
                            let canProceedBasedOnInventory = true; // Assume okay unless proven otherwise
                            let inventoryStatusMessage = 'Inventory requirements appear sufficient.';
                            const finishedProducts = response.finishedProducts || {};
                            const allProductsInStock = Object.keys(finishedProducts).length > 0 && Object.values(finishedProducts).every(product => product.sufficient);

                            if (!allProductsInStock && response.needsManufacturing) {
                                const canManufactureAll = Object.values(finishedProducts).every(product =>
                                    product.sufficient || product.canManufacture !== false); // Check canManufacture flag
                                if (!canManufactureAll) {
                                     // This scenario implies missing ingredients, raw material stock irrelevant
                                     inventoryStatusMessage = 'Some products cannot be manufactured due to missing ingredients.';
                                     // Note: We don't disable buttons here, just inform. Validation is on action.
                                } else {
                                    const materials = response.materials || {};
                                    const allMaterialsSufficient = Object.keys(materials).length > 0 && Object.values(materials).every(material => material.sufficient);
                                    if (!allMaterialsSufficient) {
                                        // canProceedBasedOnInventory = false; // Keep true, let user decide based on info
                                        inventoryStatusMessage = 'Insufficient raw materials for manufacturing required products.';
                                    } else {
                                         inventoryStatusMessage = 'Products need manufacturing, but raw materials appear sufficient.';
                                    }
                                }
                            } else if (allProductsInStock) {
                                inventoryStatusMessage = 'All required finished products are in stock.';
                            } else if (Object.keys(finishedProducts).length === 0 && !response.needsManufacturing) {
                                 inventoryStatusMessage = 'No finished product details available for stock check.';
                            }


                            const statusClass = 'status-sufficient'; // Default to sufficient visually, message clarifies details
                            // Append status message only if it provides useful info
                            if (inventoryStatusMessage !== 'Inventory requirements appear sufficient.') {
                                 materialContainer.append(`<p class="materials-status ${statusClass}">${inventoryStatusMessage}</p>`);
                            }


                        } else {
                            materialContainer.html(`
                                <h3>Error Checking Inventory</h3>
                                <p style="color:red;">${response.message || 'Unknown error during inventory check.'}</p>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        materialContainer.html(`
                            <h3>Server Error</h3>
                            <p style="color:red;">Could not connect to server for inventory check: ${error}</p>
                        `);
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText);
                    }
                });
            } catch (e) {
                materialContainer.html(`
                    <h3>Error Processing Order Data</h3>
                    <p style="color:red;">Could not process order items for inventory check: ${e.message}</p>
                `);
                console.error("Error parsing ordersJson:", e);
            }
        }

        // Opens the confirmation modal before executing status change
        function confirmStatusAction(status) {
            selectedStatus = status;

            // Customize confirmation message
            let confirmationMsg = `Are you sure you want to change the status to ${status}?`;
            if (status === 'Pending' || status === 'Rejected') {
                 confirmationMsg += ' This may return used materials to inventory if applicable.';
            } else if (status === 'For Delivery') {
                 confirmationMsg += ' Ensure progress is 100% and a driver is assigned.';
            } else if (status === 'Active') {
                 confirmationMsg += ' This will deduct required materials/products from inventory.';
            }

            $('#statusConfirmationMessage').text(confirmationMsg);
            $('#statusConfirmationModal').css('display', 'block');

            // Hide the original status modal that triggered this
            $('#statusModal').css('display', 'none');
            $('#pendingStatusModal').css('display', 'none');
            $('#rejectedStatusModal').css('display', 'none');
        }

        // Closes the confirmation modal and potentially reopens the underlying status modal
        function closeStatusConfirmation() {
            $('#statusConfirmationModal').css('display', 'none');

            // Reopen the modal that was originally open based on context
             if ($('#pendingStatusModal').data('po_number')) {
                 $('#pendingStatusModal').css('display', 'flex');
             } else if ($('#rejectedStatusModal').data('po_number')) {
                 $('#rejectedStatusModal').css('display', 'flex');
             } else if ($('#statusModal').css('display') === 'none') { // Check if the active modal was hidden
                  $('#statusModal').css('display', 'flex'); // Reopen the active order status modal
             }
        }

        // Executes the actual status change after confirmation
        function executeStatusChange() {
            $('#statusConfirmationModal').css('display', 'none'); // Hide confirmation

            let deductMaterials = (selectedStatus === 'Active'); // Deduct only when moving TO Active
            let returnMaterials = (selectedStatus === 'Pending' || selectedStatus === 'Rejected'); // Return when moving FROM Active TO Pending/Rejected

            // Special check for 'For Delivery' requirements
            if (selectedStatus === 'For Delivery') {
                showToast('Checking requirements for delivery...', 'info');

                fetch(`/backend/check_order_driver.php?po_number=${currentPoNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (!data.driver_assigned) {
                                showToast('Error: Assign a driver before marking for delivery.', 'error');
                                closeRelevantStatusModals(); // Close any open status modals
                                return;
                            }
                            if (data.progress < 100) {
                                showToast('Error: Order progress must be 100% for delivery.', 'error');
                                closeRelevantStatusModals();
                                return;
                            }
                            // Requirements met, proceed with status change (no material adjustment needed here)
                            updateOrderStatus(selectedStatus, false, false);
                        } else {
                            showToast('Error checking requirements: ' + data.message, 'error');
                            closeRelevantStatusModals();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking delivery requirements:', error);
                        showToast('Error checking requirements: ' + error, 'error');
                        closeRelevantStatusModals();
                    });
            }
             else {
                 // For other status changes, proceed with potential material adjustments
                 updateOrderStatus(selectedStatus, deductMaterials, returnMaterials);
             }
        }

        // Closes the status modal for ACTIVE orders
        function closeStatusModal() {
            $('#statusModal').css('display', 'none');
            selectedStatus = '';
             // Clear the loading/content area for next time
            $('#activeOrderMaterialsContainer').html('<h3>Loading inventory status...</h3>');
        }

        // Opens the status modal for REJECTED orders
        function confirmRejectedStatusChange(poNumber, username) {
            currentPoNumber = poNumber;
            $('#rejectedStatusModal').data('po_number', poNumber); // Store context
            $('#rejectedStatusMessage').text(`Change status for rejected order ${poNumber} (${username})`);
            $('#rejectedStatusModal').css('display', 'flex');
        }

        // Closes the status modal for REJECTED orders
        function closeRejectedStatusModal() {
            $('#rejectedStatusModal').css('display', 'none');
            selectedStatus = '';
            $('#rejectedStatusModal').removeData('po_number'); // Clear context
        }

        // Performs the backend call to update order status
        function updateOrderStatus(status, deductMaterials, returnMaterials) {
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);
            formData.append('deduct_materials', deductMaterials ? '1' : '0');
            formData.append('return_materials', returnMaterials ? '1' : '0');

            console.log("Sending status update:", { // Debug log
                po_number: currentPoNumber,
                status: status,
                deduct_materials: deductMaterials,
                return_materials: returnMaterials
            });

            fetch('/backend/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Improved error handling from response
                 return response.text().then(text => {
                     let jsonData = null;
                     try {
                         jsonData = JSON.parse(text);
                     } catch (e) {
                         console.error('Invalid JSON response:', text);
                         throw new Error('Invalid server response received.');
                     }
                     if (!response.ok) {
                         throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     }
                     return jsonData;
                 });
            })
            .then(data => {
                 console.log("Status update response:", data); // Debug log
                if (data.success) {
                    let message = `Status updated to ${status} successfully`;
                     if (deductMaterials) message += '. Inventory deducted.';
                     if (returnMaterials) message += '. Inventory returned.';

                    showToast(message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    // Error message already thrown/parsed in previous .then()
                    showToast('Error updating status: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error("Update status fetch error:", error); // Debug log
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => {
                 // Ensure all status modals are closed after attempt
                 closeRelevantStatusModals();
            });
        }

        // Opens the status modal for PENDING orders and checks materials
        function confirmPendingStatusChange(poNumber, username, ordersJson) {
            currentPoNumber = poNumber;
            $('#pendingStatusModal').data('po_number', poNumber); // Store context
            $('#pendingStatusMessage').text('Change order status for ' + poNumber);

            const materialContainer = $('#rawMaterialsContainer'); // Target the correct container in this modal
            materialContainer.html('<h3>Loading inventory status...</h3>');

            $('#pendingStatusModal').css('display', 'flex'); // Show modal

            try {
                 if (!ordersJson) {
                     throw new Error("Order items data is missing.");
                 }
                 JSON.parse(ordersJson); // Validate JSON

                $.ajax({
                    url: '/backend/check_raw_materials.php',
                    type: 'POST',
                    data: {
                        orders: ordersJson,
                        po_number: poNumber
                    },
                    dataType: 'json',
                    success: function(response) {
                         console.log("Inventory Check Response (Pending Order):", response); // Debugging
                        if (response.success) {
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer'); // Pass container selector

                            if (needsMfg && response.materials) {
                                displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section'); // Pass container selector
                            } else if (needsMfg) {
                                 $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Raw material information not available.</p>');
                            } else if (!needsMfg && response.finishedProducts) {
                                materialContainer.append('<p>All required products are currently in stock.</p>');
                                $('#rawMaterialsContainer #raw-materials-section').remove();
                            } else {
                                 if (!response.finishedProducts && !response.materials) {
                                     materialContainer.html('<h3>Inventory Status</h3><p>No specific inventory details available for this order.</p>');
                                }
                            }
                            updatePendingOrderActionStatus(response); // Enable/disable 'Active' button based on check
                        } else {
                            materialContainer.html(`
                                <h3>Error Checking Inventory</h3>
                                <p style="color:red;">${response.message || 'Unknown error'}</p>
                                <p>Order status can still be changed, but inventory check failed.</p>
                            `);
                            $('#activeStatusBtn').prop('disabled', false); // Allow activation even if check fails
                        }
                    },
                    error: function(xhr, status, error) {
                        materialContainer.html(`
                            <h3>Server Error</h3>
                            <p style="color:red;">Could not connect for inventory check: ${error}</p>
                            <p>Order status can still be changed, but inventory check failed.</p>
                        `);
                        $('#activeStatusBtn').prop('disabled', false);
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText);
                    }
                });
            } catch (e) {
                materialContainer.html(`
                    <h3>Error Processing Order Data</h3>
                    <p style="color:red;">${e.message}</p>
                     <p>Order status can still be changed, but inventory check failed.</p>
                `);
                 $('#activeStatusBtn').prop('disabled', false);
                console.error("Error parsing ordersJson for pending:", e);
            }
        }

        // Closes the status modal for PENDING orders
        function closePendingStatusModal() {
            $('#pendingStatusModal').css('display', 'none');
            selectedStatus = '';
            $('#pendingStatusModal').removeData('po_number'); // Clear context
            $('#rawMaterialsContainer').html('<h3>Loading inventory status...</h3>'); // Reset content
        }

        // Helper to close all potential status modals
        function closeRelevantStatusModals() {
             closeStatusModal();
             closePendingStatusModal();
             closeRejectedStatusModal();
        }


        // Helper function to format weight values
        function formatWeight(weightInGrams) {
            if (weightInGrams >= 1000) {
                return (weightInGrams / 1000).toFixed(2) + ' kg';
            } else {
                 return (weightInGrams ? parseFloat(weightInGrams).toFixed(2) : '0.00') + ' g';
            }
        }

        // Displays finished product status in the specified container
        function displayFinishedProducts(productsData, containerSelector) {
            const container = $(containerSelector);
            if (!container.length) return false;

             let productsTableHTML = `<h3>Finished Products Status</h3>`;

             if (!productsData || Object.keys(productsData).length === 0) {
                  productsTableHTML += '<p>No finished product information available.</p>';
                  container.html(productsTableHTML);
                  // If no finished product info, assume manufacturing might be needed if raw materials are relevant
                  container.append('<div id="raw-materials-section"></div>'); // Add placeholder
                  return true; // Indicate potential need for manufacturing check
             }

            productsTableHTML += `
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>In Stock</th>
                            <th>Required</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${Object.keys(productsData).map(product => {
                            const data = productsData[product];
                            const available = parseInt(data.available) || 0;
                            const required = parseInt(data.required) || 0;
                            const isSufficient = data.sufficient;
                            const shortfall = data.shortfall || 0;

                            return `
                                <tr>
                                    <td>${product}</td>
                                    <td>${available}</td>
                                    <td>${required}</td>
                                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                        ${isSufficient ? 'In Stock' : `Need to manufacture ${shortfall} more`}
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
            container.html(productsTableHTML); // Replace content

            const needsManufacturing = Object.values(productsData).some(product => !product.sufficient);

            if (needsManufacturing) {
                container.append('<div id="raw-materials-section"><h3>Raw Materials Required</h3><p>Loading raw materials info...</p></div>'); // Add placeholder
                 return true; // Indicate manufacturing is needed
            }
             return false; // Indicate manufacturing is not needed
        }

        // Displays raw material status in the specified container (usually #raw-materials-section)
        function displayRawMaterials(materialsData, containerSelector) {
             const container = $(containerSelector);
             if (!container.length) return true; // Assume okay if container missing

             let materialsTableHTML = '';
             let headerHTML = '<h3>Raw Materials Required for Manufacturing</h3>'; // Add header

            if (!materialsData || Object.keys(materialsData).length === 0) {
                container.html(headerHTML + '<p>No raw materials information available for manufacturing.</p>');
                return true; // Assume sufficient if no raw materials needed/found
            }

            let allSufficient = true;
            let insufficientMaterials = [];

            materialsTableHTML = `
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Available</th>
                            <th>Required</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${Object.keys(materialsData).map(material => {
                            const data = materialsData[material];
                            const available = parseFloat(data.available) || 0;
                            const required = parseFloat(data.required) || 0;
                            const isSufficient = data.sufficient;

                            if (!isSufficient) {
                                allSufficient = false;
                                insufficientMaterials.push(material);
                            }

                            return `
                                <tr>
                                    <td>${material}</td>
                                    <td>${formatWeight(available)}</td>
                                    <td>${formatWeight(required)}</td>
                                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                        ${isSufficient ? 'Sufficient' : 'Insufficient'}
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;

            const statusMessage = allSufficient
                ? 'All raw materials are sufficient for manufacturing.'
                : `Insufficient raw materials: ${insufficientMaterials.join(', ')}.`;

            const statusClass = allSufficient ? 'status-sufficient' : 'status-insufficient';
            container.html(headerHTML + materialsTableHTML + `<p class="materials-status ${statusClass}">${statusMessage}</p>`);

            return allSufficient; // Return sufficiency
        }

        // Updates the 'Active' button state in the PENDING modal based on inventory check
        function updatePendingOrderActionStatus(response) {
            let canProceedToActive = true;
            let statusMessage = 'All inventory requirements met. Ready to activate.';
            const materialContainer = $('#rawMaterialsContainer'); // Target pending modal container

            const finishedProducts = response.finishedProducts || {};
            const allProductsInStock = Object.keys(finishedProducts).length > 0 && Object.values(finishedProducts).every(product => product.sufficient);

            if (!allProductsInStock && response.needsManufacturing) {
                 // Check if backend indicated manufacturing is possible (ingredients exist)
                 const canManufactureAll = Object.values(finishedProducts).every(product =>
                    product.sufficient || product.canManufacture !== false);

                 if (!canManufactureAll) {
                      canProceedToActive = false;
                      statusMessage = 'Cannot activate: Some products lack necessary ingredients for manufacturing.';
                 } else {
                     // Check raw material stock levels
                     const materials = response.materials || {};
                     const allMaterialsSufficient = Object.keys(materials).length > 0 && Object.values(materials).every(material => material.sufficient);

                     if (!allMaterialsSufficient) {
                         canProceedToActive = false;
                         statusMessage = 'Cannot activate: Insufficient raw materials for manufacturing.';
                     } else {
                         statusMessage = 'Manufacturing required. Raw materials sufficient. Ready to activate.';
                     }
                 }
            } else if (allProductsInStock) {
                 statusMessage = 'All required finished products in stock. Ready to activate.';
            } else if (Object.keys(finishedProducts).length === 0 && !response.needsManufacturing) {
                 // If no finished product info and no manufacturing needed -> likely okay? Or error? Be cautious.
                 // Let's assume okay if backend didn't report issues, but add note.
                 statusMessage = 'Inventory details unclear, proceed with caution.';
                 // canProceedToActive = false; // Optionally disable if unsure
            }


            $('#activeStatusBtn').prop('disabled', !canProceedToActive); // Disable/Enable button

            const statusClass = canProceedToActive ? 'status-sufficient' : 'status-insufficient';
            // Append or update the final status message
            let statusElement = materialContainer.children('.materials-status'); // Find existing status message more reliably
            if (statusElement.length) {
                 statusElement.removeClass('status-sufficient status-insufficient').addClass(statusClass).text(statusMessage);
            } else {
                 materialContainer.append(`<p class="materials-status ${statusClass}">${statusMessage}</p>`);
            }
        }


        // --- Order Details Modal Functions (Progress Tracking) ---
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;

            fetch(`/backend/get_order_details.php?po_number=${poNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentOrderItems = data.orderItems;
                    completedItems = data.completedItems || [];
                    quantityProgressData = data.quantityProgressData || {};
                    itemProgressPercentages = data.itemProgressPercentages || {};
                    overallProgress = data.overallProgress || 0; // Load overall progress from backend

                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    document.getElementById('status-header-cell').style.display = '';
                    document.getElementById('orderStatus').textContent = 'Active';

                    const totalItemsCount = currentOrderItems.length; // Use count for contribution calculation
                    itemContributions = {}; // Reset contributions

                    let calculatedOverallProgress = 0; // Recalculate based on loaded data

                    currentOrderItems.forEach((item, index) => {
                        const isCompletedByCheckbox = completedItems.includes(index); // Status based on main checkbox
                        const itemQuantity = parseInt(item.quantity) || 0;

                        // Calculate contribution percentage (more accurate based on quantity)
                        // Let's stick to per-item for simplicity unless quantity weighting is desired
                        const contributionPerItem = totalItemsCount > 0 ? (100 / totalItemsCount) : 0;
                        itemContributions[index] = contributionPerItem;

                        // Calculate unit progress based on loaded data
                        let unitCompletedCount = 0;
                        if (quantityProgressData[index]) {
                            for (let i = 0; i < itemQuantity; i++) {
                                if (quantityProgressData[index][i] === true) {
                                    unitCompletedCount++;
                                }
                            }
                        }
                        const unitProgress = itemQuantity > 0 ? (unitCompletedCount / itemQuantity) * 100 : (isCompletedByCheckbox ? 100 : 0); // If no units, rely on checkbox

                        // Store the calculated unit progress
                        itemProgressPercentages[index] = unitProgress;

                        // Recalculate overall progress contribution
                        const contributionToOverall = (unitProgress / 100) * contributionPerItem;
                        calculatedOverallProgress += contributionToOverall;

                        // Create main row
                        const mainRow = document.createElement('tr');
                        mainRow.className = 'item-header-row';
                        if (isCompletedByCheckbox || unitProgress === 100) { // Mark completed if checkbox OR all units done
                            mainRow.classList.add('completed-item');
                        }
                        mainRow.dataset.itemIndex = index;

                        mainRow.innerHTML = `
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td class="status-cell">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <input type="checkbox" class="item-status-checkbox" data-index="${index}"
                                        ${isCompletedByCheckbox ? 'checked' : ''} onchange="updateRowStyle(this)">
                                    ${itemQuantity > 0 ? // Only show unit button if quantity > 0
                                        `<button type="button" class="toggle-item-progress" data-index="${index}" onclick="toggleQuantityProgress(${index})">
                                            <i class="fas fa-list-ol"></i> Units
                                        </button>` : ''
                                    }
                                </div>
                                ${itemQuantity > 0 ? // Only show progress bar if quantity > 0
                                    `<div class="item-progress-bar-container">
                                        <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div>
                                    </div>
                                    <span class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}% Complete</span>
                                    <span class="contribution-text" id="contribution-text-${index}">(${Math.round(contributionToOverall)}% of total)</span>`
                                    : '<span style="font-size: 0.9em; color: grey;">(N/A)</span>' // Show N/A if no units
                                }
                            </td>
                        `;
                        orderDetailsBody.appendChild(mainRow);

                        // Add unit rows only if quantity > 0
                        if (itemQuantity > 0) {
                             // Divider row
                             const dividerRow = document.createElement('tr');
                             dividerRow.className = 'units-divider';
                             dividerRow.id = `units-divider-${index}`;
                             dividerRow.style.display = 'none';
                             dividerRow.innerHTML = `<td colspan="6" style="border: none; padding: 2px 0; background-color: #eee;"></td>`; // Style divider
                             orderDetailsBody.appendChild(dividerRow);

                            // Unit rows
                            for (let i = 0; i < itemQuantity; i++) {
                                const isUnitCompleted = quantityProgressData[index] && quantityProgressData[index][i] === true;
                                const unitRow = document.createElement('tr');
                                unitRow.className = `unit-row unit-item unit-for-item-${index}`;
                                unitRow.style.display = 'none';
                                if (isUnitCompleted) unitRow.classList.add('completed');

                                unitRow.innerHTML = `
                                    <td style="padding-left: 20px;">${item.category}</td> <!-- Indent slightly -->
                                    <td>${item.item_description}</td>
                                    <td>${item.packaging}</td>
                                    <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                                    <td class="unit-number-cell">Unit ${i+1}</td>
                                    <td>
                                        <input type="checkbox" class="unit-status-checkbox"
                                            data-item-index="${index}" data-unit-index="${i}"
                                            ${isUnitCompleted ? 'checked' : ''} onchange="updateUnitStatus(this)">
                                    </td>
                                `;
                                orderDetailsBody.appendChild(unitRow);
                            }

                             // Action row with "Select All" button
                             const actionRow = document.createElement('tr');
                             actionRow.className = `unit-row unit-action-row unit-for-item-${index}`;
                             actionRow.style.display = 'none';
                             actionRow.innerHTML = `
                                 <td colspan="6" style="text-align: right; padding: 10px;">
                                     <button type="button" class="select-all-units btn-sm btn-outline-secondary" onclick="selectAllUnits(${index}, ${itemQuantity})">
                                         <i class="fas fa-check-square"></i> Mark All Units Complete
                                     </button>
                                      <button type="button" class="deselect-all-units btn-sm btn-outline-secondary" onclick="deselectAllUnits(${index}, ${itemQuantity})" style="margin-left: 5px;">
                                         <i class="far fa-square"></i> Mark All Units Incomplete
                                     </button>
                                 </td>
                             `;
                             orderDetailsBody.appendChild(actionRow);
                        }
                    });

                    // Use the recalculated overall progress
                    overallProgress = calculatedOverallProgress;
                    updateOverallProgressDisplay();

                    let totalAmount = currentOrderItems.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0);
                    $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);

                    $('#overall-progress-info').css('display', 'block');
                    $('.save-progress-btn').css('display', 'block');
                    $('#orderDetailsModal').css('display', 'flex');
                } else {
                    showToast('Error fetching order details: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error fetching order details: ' + error, 'error');
                console.error('Error fetching order details:', error);
            });
        }

        // View basic order info for non-active orders
        function viewOrderInfo(ordersJson, orderStatus) {
            try {
                const orderDetails = JSON.parse(ordersJson);
                const orderDetailsBody = $('#orderDetailsBody');
                orderDetailsBody.html(''); // Clear previous

                $('#status-header-cell').css('display', 'none'); // Hide status column
                $('#orderStatus').text(orderStatus); // Show order status

                let totalAmount = 0;
                orderDetails.forEach(product => {
                    totalAmount += parseFloat(product.price) * parseInt(product.quantity);
                    const row = `
                        <tr>
                            <td>${product.category || ''}</td>
                            <td>${product.item_description}</td>
                            <td>${product.packaging || ''}</td>
                            <td>PHP ${parseFloat(product.price).toFixed(2)}</td>
                            <td>${product.quantity}</td>
                        </tr>`;
                    orderDetailsBody.append(row);
                });

                $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);
                $('#overall-progress-info').css('display', 'none'); // Hide progress elements
                $('.save-progress-btn').css('display', 'none'); // Hide save button
                $('#orderDetailsModal').css('display', 'flex'); // Show modal
            } catch (e) {
                console.error('Error parsing order details for info:', e);
                showToast('Error displaying order details', 'error');
            }
        }

        // Toggle visibility of unit rows for an item
        function toggleQuantityProgress(itemIndex) {
            const unitRows = $(`.unit-for-item-${itemIndex}`);
            const dividerRow = $(`#units-divider-${itemIndex}`);
            const isVisible = unitRows.first().css('display') !== 'none';
            dividerRow.css('display', isVisible ? 'none' : 'table-row');
            unitRows.css('display', isVisible ? 'none' : 'table-row');
        }

        // Update status when a single unit checkbox changes
        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.getAttribute('data-item-index'));
            const unitIndex = parseInt(checkbox.getAttribute('data-unit-index'));
            const isChecked = checkbox.checked;
            const unitRow = checkbox.closest('tr');

            unitRow.classList.toggle('completed', isChecked); // Add/remove class based on check state

            // Ensure progress data structure exists
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
                const itemQuantity = parseInt(currentOrderItems[itemIndex].quantity) || 0;
                for (let i = 0; i < itemQuantity; i++) quantityProgressData[itemIndex].push(false);
            }
            quantityProgressData[itemIndex][unitIndex] = isChecked; // Update data

            updateItemProgress(itemIndex); // Recalculate item progress
            updateOverallProgress(); // Recalculate overall progress
        }

        // Recalculate and update display for a specific item's progress
        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex];
            const itemQuantity = parseInt(item.quantity) || 0;
            if (itemQuantity === 0) return; // No units to track

            let completedUnits = 0;
            for (let i = 0; i < itemQuantity; i++) {
                if (quantityProgressData[itemIndex] && quantityProgressData[itemIndex][i]) {
                    completedUnits++;
                }
            }

            const unitProgress = (completedUnits / itemQuantity) * 100;
            itemProgressPercentages[itemIndex] = unitProgress; // Store percentage

            const contributionToOverall = (unitProgress / 100) * itemContributions[itemIndex];

            // Update display elements
            $(`#item-progress-bar-${itemIndex}`).css('width', `${unitProgress}%`);
            $(`#item-progress-text-${itemIndex}`).text(`${Math.round(unitProgress)}% Complete`);
            $(`#contribution-text-${itemIndex}`).text(`(${Math.round(contributionToOverall)}% of total)`);

            // Update main item checkbox based on unit completion
            updateItemStatusBasedOnUnits(itemIndex, completedUnits === itemQuantity);
        }

        // Update the overall progress bar and text display
        function updateOverallProgressDisplay() {
            const roundedProgress = Math.round(overallProgress);
            $('#overall-progress-bar').css('width', `${roundedProgress}%`);
            $('#overall-progress-text').text(`${roundedProgress}%`);
        }

        // Recalculate the overall progress based on all item percentages and contributions
        function updateOverallProgress() {
            let newOverallProgress = 0;
            Object.keys(itemProgressPercentages).forEach(itemIndex => {
                const itemProgress = itemProgressPercentages[itemIndex];
                const itemContribution = itemContributions[itemIndex];
                if (itemProgress !== undefined && itemContribution !== undefined) { // Ensure values exist
                     newOverallProgress += (itemProgress / 100) * itemContribution;
                }
            });
            overallProgress = newOverallProgress;
            updateOverallProgressDisplay();
            return Math.round(overallProgress); // Return rounded value
        }

        // Update the main item checkbox and row style if all its units are completed/incompleted
        function updateItemStatusBasedOnUnits(itemIndex, allUnitsComplete) {
            const mainCheckbox = $(`.item-status-checkbox[data-index="${itemIndex}"]`);
            const mainRow = $(`tr[data-item-index="${itemIndex}"]`);

            mainCheckbox.prop('checked', allUnitsComplete); // Set checkbox state
            mainRow.toggleClass('completed-item', allUnitsComplete); // Add/remove class

            // Update the completedItems array (used for saving)
            const intIndex = parseInt(itemIndex);
            const completedIndexInArray = completedItems.indexOf(intIndex);
            if (allUnitsComplete && completedIndexInArray === -1) {
                completedItems.push(intIndex);
            } else if (!allUnitsComplete && completedIndexInArray > -1) {
                completedItems.splice(completedIndexInArray, 1);
            }
        }

        // Mark all units for an item as complete
        function selectAllUnits(itemIndex, quantity) {
            const unitCheckboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            unitCheckboxes.prop('checked', true); // Check all boxes
            unitCheckboxes.closest('tr').addClass('completed'); // Style rows

            // Update progress data
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = [];
            for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = true;

            updateItemProgress(itemIndex); // Update item display
            updateOverallProgress(); // Update overall display
        }

         // Mark all units for an item as incomplete
        function deselectAllUnits(itemIndex, quantity) {
            const unitCheckboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            unitCheckboxes.prop('checked', false); // Uncheck all boxes
            unitCheckboxes.closest('tr').removeClass('completed'); // Style rows

            // Update progress data
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = [];
            for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = false;

            updateItemProgress(itemIndex); // Update item display
            updateOverallProgress(); // Update overall display
        }


        // Update item status (and potentially unit statuses) when the main item checkbox changes
        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.getAttribute('data-index'));
            const row = checkbox.closest('tr');
            const isChecked = checkbox.checked;
            const itemQuantity = parseInt(currentOrderItems[index].quantity) || 0;

            row.classList.toggle('completed-item', isChecked); // Style main row

            // Update completedItems array
            const intIndex = parseInt(index);
            const completedIndexInArray = completedItems.indexOf(intIndex);
             if (isChecked && completedIndexInArray === -1) {
                 completedItems.push(intIndex);
             } else if (!isChecked && completedIndexInArray > -1) {
                 completedItems.splice(completedIndexInArray, 1);
             }


            // Update all associated unit checkboxes and data
            const unitCheckboxes = $(`.unit-status-checkbox[data-item-index="${index}"]`);
            unitCheckboxes.prop('checked', isChecked);
            unitCheckboxes.closest('tr').toggleClass('completed', isChecked);

            if (!quantityProgressData[index]) quantityProgressData[index] = [];
            for (let i = 0; i < itemQuantity; i++) {
                quantityProgressData[index][i] = isChecked;
            }

            // Update item progress display (will be 100% or 0%)
            itemProgressPercentages[index] = isChecked ? 100 : 0;
            const contributionToOverall = (itemProgressPercentages[index] / 100) * itemContributions[index];

            $(`#item-progress-bar-${index}`).css('width', `${itemProgressPercentages[index]}%`);
            $(`#item-progress-text-${index}`).text(`${Math.round(itemProgressPercentages[index])}% Complete`);
            $(`#contribution-text-${index}`).text(`(${Math.round(contributionToOverall)}% of total)`);

            updateOverallProgress(); // Update overall progress
        }

        // Close the order details/progress modal
        function closeOrderDetailsModal() {
            $('#orderDetailsModal').css('display', 'none');
        }

        // Show confirmation before saving progress changes
        function confirmSaveProgress() {
            $('#saveProgressConfirmationModal').css('display', 'block');
        }

        // Close progress save confirmation modal
        function closeSaveProgressConfirmation() {
            $('#saveProgressConfirmationModal').css('display', 'none');
        }

        // Save progress changes to the backend
        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').css('display', 'none'); // Hide confirmation

            const finalProgressPercentage = updateOverallProgress(); // Ensure overall progress is current
            // const shouldMarkForDelivery = finalProgressPercentage === 100; // Auto-delivery logic removed, handled by status change

            fetch('/backend/update_order_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    completed_items: completedItems, // Send array of completed item indices
                    quantity_progress_data: quantityProgressData, // Send detailed unit progress
                    item_progress_percentages: itemProgressPercentages, // Send calculated item percentages
                    progress: finalProgressPercentage, // Send overall calculated percentage
                    // auto_delivery: false, // Explicitly disable auto-delivery on progress save
                    // driver_id: currentDriverId // Driver ID not relevant here, only for assignment/status change
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Progress updated successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000); // Reload page
                } else {
                    showToast('Error updating progress: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error saving progress: ' + error, 'error');
                console.error('Error saving progress:', error);
            });
        }


        // --- Driver Assignment Modal Functions ---
        function confirmDriverAssign(poNumber) {
            currentPoNumber = poNumber;
            currentDriverId = 0; // Reset current driver ID
            $('#driverModalTitle').text('Assign Driver');
            $('#driverModalMessage').text(`Select a driver for order ${poNumber}:`);
            $('#driverSelect').val(0); // Reset dropdown
            $('#driverModal').css('display', 'flex'); // Show modal
        }

        function confirmDriverChange(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId; // Set current driver ID
            $('#driverModalTitle').text('Change Driver Assignment');
            $('#driverModalMessage').text(`Current driver: ${driverName}. Select a new driver:`);
            $('#driverSelect').val(driverId); // Set dropdown to current driver
            $('#driverModal').css('display', 'flex'); // Show modal
        }

        function closeDriverModal() {
            $('#driverModal').css('display', 'none');
            currentDriverId = 0; // Reset on close
        }

        // Show confirmation before assigning/changing driver
        function confirmDriverAssignment() {
            const driverId = parseInt($('#driverSelect').val());
            if (driverId === 0 || isNaN(driverId)) {
                showToast('Please select a driver', 'error');
                return;
            }
            // Customize confirmation message based on whether it's a new assignment or change
             const selectedDriverName = $('#driverSelect option:selected').text();
             let msg = `Are you sure you want to assign driver ${selectedDriverName}?`;
             if (currentDriverId > 0 && currentDriverId !== driverId) { // If changing from an existing different driver
                  msg = `Are you sure you want to change the driver to ${selectedDriverName}?`;
             } else if (currentDriverId > 0 && currentDriverId === driverId) {
                  showToast('Selected driver is already assigned.', 'info');
                  closeDriverModal();
                  return; // No change needed
             }

             $('#driverConfirmationModal .confirmation-message').text(msg); // Set message
            $('#driverConfirmationModal').css('display', 'block'); // Show confirmation
            $('#driverModal').css('display', 'none'); // Hide driver selection modal
        }

        // Close driver assignment confirmation
        function closeDriverConfirmation() {
            $('#driverConfirmationModal').css('display', 'none');
            $('#driverModal').css('display', 'flex'); // Re-show driver selection modal
        }

        // Assign driver via backend call after confirmation
        function assignDriver() {
            $('#driverConfirmationModal').css('display', 'none'); // Hide confirmation
            const driverId = parseInt($('#driverSelect').val());

            // Basic validation again
            if (driverId === 0 || isNaN(driverId)) return;

            const saveBtn = $('#driverModal .save-btn'); // Use jQuery selector
            const originalBtnText = saveBtn.html();
            saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Assigning...');
            saveBtn.prop('disabled', true);

            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('driver_id', driverId);

            fetch('/backend/assign_driver.php', { method: 'POST', body: formData })
            .then(response => response.json()) // Assuming backend always returns JSON
            .then(data => {
                if (data.success) {
                    showToast('Driver assigned successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error assigning driver: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error("Assign driver fetch error:", error);
                showToast('Network error occurred while assigning driver', 'error');
            })
            .finally(() => {
                saveBtn.html(originalBtnText); // Restore button text
                saveBtn.prop('disabled', false); // Re-enable button
                closeDriverModal(); // Close the modal regardless of success/failure
            });
        }


        // --- PDF Download Functions ---
        function confirmDownloadPO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            poDownloadData = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions };
            $('#downloadConfirmationModal').css('display', 'block'); // Show confirmation
        }

        function closeDownloadConfirmation() {
            $('#downloadConfirmationModal').css('display', 'none');
            poDownloadData = null; // Clear data
        }

        // Prepares the hidden div and triggers html2pdf download directly
        function downloadPODirectly() {
            $('#downloadConfirmationModal').css('display', 'none'); // Hide confirmation
            if (!poDownloadData) {
                showToast('Error: No data available for download', 'error');
                return;
            }

            try {
                currentPOData = poDownloadData; // Use stored data

                // Populate hidden div
                $('#printCompany').text(currentPOData.company || 'N/A');
                $('#printPoNumber').text(currentPOData.poNumber);
                $('#printUsername').text(currentPOData.username);
                $('#printDeliveryAddress').text(currentPOData.deliveryAddress);
                $('#printOrderDate').text(currentPOData.orderDate);
                $('#printDeliveryDate').text(currentPOData.deliveryDate);
                $('#printTotalAmount').text(parseFloat(currentPOData.totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                const instructionsSection = $('#printInstructionsSection');
                if (currentPOData.specialInstructions && currentPOData.specialInstructions.trim() !== '') {
                    $('#printSpecialInstructions').text(currentPOData.specialInstructions).css('white-space', 'pre-wrap');
                    instructionsSection.show();
                } else {
                    instructionsSection.hide();
                }

                const orderItems = JSON.parse(currentPOData.ordersJson);
                const orderItemsBody = $('#printOrderItems').empty(); // Clear previous items

                orderItems.forEach(item => {
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    const row = `
                        <tr>
                            <td>${item.category || ''}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging || ''}</td>
                            <td>${item.quantity}</td>
                            <td>PHP ${parseFloat(item.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td>PHP ${itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        </tr>`;
                    orderItemsBody.append(row);
                });

                const element = document.getElementById('contentToDownload');
                const opt = {
                    margin: [10, 10, 10, 10],
                    filename: `PO_${currentPOData.poNumber}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                // Generate and save PDF
                html2pdf().set(opt).from(element).save().then(() => {
                    showToast(`PO ${currentPOData.poNumber} downloaded.`, 'success');
                    currentPOData = null; // Clear data after download
                    poDownloadData = null;
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    showToast('Error generating PDF.', 'error');
                    currentPOData = null; // Clear data on error
                    poDownloadData = null;
                });

            } catch (e) {
                console.error('Error preparing PDF data:', e);
                showToast('Error preparing PDF data', 'error');
                currentPOData = null; // Clear data on error
                poDownloadData = null;
            }
        }

        // Trigger download from preview modal (if used, currently direct download is implemented)
        function downloadPDF() {
             if (!currentPOData) { // Check if data is available (might be set by a preview function not shown here)
                 showToast('No PO data loaded for download.', 'error');
                 return;
             }
             // Reuse the logic from downloadPODirectly's html2pdf call
             const element = document.getElementById('contentToDownload');
             const opt = { /* ... options ... */ };
             html2pdf().set(opt).from(element).save().then(() => {
                 showToast(`PO ${currentPOData.poNumber} downloaded.`, 'success');
                 closePDFPreview(); // Close preview after download
             }).catch(error => { /* ... error handling ... */ });
        }

        // Close PDF preview modal (if used)
        function closePDFPreview() {
            $('#pdfPreview').hide();
             currentPOData = null; // Clear data when closing preview
        }


        // --- Special Instructions Modal ---
        function viewSpecialInstructions(poNumber, instructions) {
            $('#instructionsPoNumber').text('PO Number: ' + poNumber);
            const contentEl = $('#instructionsContent');
            if (instructions && instructions.trim().length > 0) {
                contentEl.text(instructions).removeClass('empty');
            } else {
                contentEl.text('No special instructions provided.').addClass('empty');
            }
            $('#specialInstructionsModal').css('display', 'block'); // Show modal
        }

        function closeSpecialInstructions() {
            $('#specialInstructionsModal').css('display', 'none'); // Hide modal
        }


        // --- Add New Order Form Functions ---

        // **FIXED**: Initialize Datepicker with M/W/F and minDate constraint
        function initializeDeliveryDatePicker() {
             if ($.datepicker) {
                 $("#delivery_date").datepicker("destroy"); // Remove previous instance first
                 $("#delivery_date").datepicker({
                     dateFormat: 'yy-mm-dd',
                     minDate: 1, // Tomorrow is the minimum date
                     beforeShowDay: function(date) {
                         const day = date.getDay();
                         // Allow Monday (1), Wednesday (3), Friday (5)
                         const isMWF = (day === 1 || day === 3 || day === 5);
                         return [isMWF, isMWF ? "" : "ui-state-disabled", isMWF ? "Available" : "Unavailable"];
                     }
                 });
             }
        }

        function openAddOrderForm() {
            $('#addOrderForm')[0].reset(); // Reset form fields
            cartItems = []; // Clear the cart array
            updateOrderSummary(); // Update the visual summary table
            updateCartItemCount(); // Update cart count display if exists

            const today = new Date();
            const formattedDate = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');
            $('#order_date').val(formattedDate); // Set order date to today

            initializeDeliveryDatePicker(); // Initialize/Re-initialize datepicker with constraints

            toggleDeliveryAddress(); // Set initial address field visibility
            generatePONumber(); // Attempt to generate PO number (will be blank if no user selected)

            $('#addOrderOverlay').css('display', 'block'); // Show the overlay
        }


        function closeAddOrderForm() {
            $('#addOrderOverlay').css('display', 'none');
            // Optional: Reset cartItems again if needed, though openAddOrderForm should handle it
            // cartItems = [];
            // updateOrderSummary();
        }

        // Toggle delivery address fields
        function toggleDeliveryAddress() {
            const addressType = $('#delivery_address_type').val();
            const companyContainer = $('#company_address_container');
            const customContainer = $('#custom_address_container');
            const usernameSelect = $('#username');
            const companyAddressInput = $('#company_address');
            const deliveryAddressHiddenInput = $('#delivery_address');

            if (addressType === 'company') {
                companyContainer.show();
                customContainer.hide();
                // Update company address based on selected user
                if (usernameSelect.val()) {
                    const selectedOption = usernameSelect.find('option:selected');
                    const addr = selectedOption.data('company-address') || '';
                    companyAddressInput.val(addr);
                    deliveryAddressHiddenInput.val(addr); // Update hidden input
                } else {
                    companyAddressInput.val(''); // Clear if no user
                     deliveryAddressHiddenInput.val('');
                }
            } else { // Custom address
                companyContainer.hide();
                customContainer.show();
                 deliveryAddressHiddenInput.val($('#custom_address').val()); // Update hidden input from textarea
            }
        }

         // Update hidden delivery address when custom textarea changes
         $('#custom_address').on('input', function() {
             if ($('#delivery_address_type').val() === 'custom') {
                  $('#delivery_address').val($(this).val());
             }
         });


        // **FIXED**: Generate PO Number using Timestamp instead of Random
        function generatePONumber() {
            const usernameSelect = $('#username');
            const username = usernameSelect.val();

            // Update company address field and hidden company name field
             const companyHiddenInput = $('#company_hidden');
            if (username) {
                const selectedOption = usernameSelect.find('option:selected');
                const companyAddress = selectedOption.data('company-address') || '';
                 const companyName = selectedOption.data('company') || ''; // Get company name
                $('#company_address').val(companyAddress);
                 companyHiddenInput.val(companyName); // Set hidden company name

                if ($('#delivery_address_type').val() === 'company') {
                    $('#delivery_address').val(companyAddress);
                }

                // Generate PO Number with Timestamp
                const today = new Date();
                const datePart = today.getFullYear().toString().substr(-2) +
                                String(today.getMonth() + 1).padStart(2, '0') +
                                String(today.getDate()).padStart(2, '0');
                const timePart = Date.now().toString().slice(-6); // Use last 6 digits of timestamp
                const poNumber = `PO-${datePart}-${username.substring(0, 3).toUpperCase()}-${timePart}`;
                $('#po_number').val(poNumber);

            } else {
                 $('#company_address').val(''); // Clear address if no user
                 companyHiddenInput.val(''); // Clear hidden company name
                 $('#po_number').val(''); // Clear PO number
                if ($('#delivery_address_type').val() === 'company') {
                    $('#delivery_address').val('');
                }
            }
        }

        // **FIXED**: Prepare data for submission, including product_id
        function prepareOrderData() {
            // Update delivery address one last time
            toggleDeliveryAddress(); // Ensures the hidden field is current
            const deliveryAddress = $('#delivery_address').val();

             // Update hidden company name field
             const usernameSelect = $('#username');
             if (usernameSelect.val()) {
                 $('#company_hidden').val(usernameSelect.find('option:selected').data('company') || '');
             } else {
                  $('#company_hidden').val('');
             }


            // --- Validations ---
            if (cartItems.length === 0) {
                showToast('Please select at least one product.', 'error'); return false;
            }
            if (!$('#username').val()) {
                showToast('Please select a username.', 'error'); return false;
            }
            if (!$('#delivery_date').val()) {
                showToast('Please select a delivery date.', 'error'); return false;
            }
            if (!deliveryAddress || !deliveryAddress.trim()) {
                showToast('Please provide a delivery address.', 'error'); return false;
            }
            if (!$('#po_number').val()) { // Check if PO number generated
                 showToast('Could not generate PO Number. Select a user.', 'error'); return false;
            }
            // --- End Validations ---


            // Collect item data including product_id
            let totalAmount = 0;
            const orders = cartItems.map(item => {
                 totalAmount += item.price * item.quantity;
                 return {
                     product_id: item.id, // *** ADDED product_id ***
                     category: item.category,
                     item_description: item.item_description,
                     packaging: item.packaging,
                     price: item.price,
                     quantity: item.quantity
                 };
            });

            // Set hidden fields for submission
            $('#orders').val(JSON.stringify(orders)); // Set orders JSON
            $('#total_amount').val(totalAmount.toFixed(2)); // Set total amount

             // Special instructions are directly submitted via the textarea's name attribute with FormData

            return true; // Data is ready
        }

        // Show confirmation modal for adding order
        function confirmAddOrder() {
            if (prepareOrderData()) { // Prepare and validate data first
                $('#addConfirmationModal').css('display', 'block');
            }
        }

        // Close add order confirmation
        function closeAddConfirmation() {
            $('#addConfirmationModal').css('display', 'none');
        }

        // Submit the add order form via AJAX
        function submitAddOrder() {
            $('#addConfirmationModal').css('display', 'none'); // Hide confirmation

            const form = document.getElementById('addOrderForm');
            const formData = new FormData(form);

            // Optional: Log FormData contents for debugging
            // for (let [key, value] of formData.entries()) {
            //     console.log(key, value);
            // }

            fetch('/backend/add_order.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Order added successfully!', 'success');
                    closeAddOrderForm(); // Close the form overlay
                    setTimeout(() => { window.location.reload(); }, 1500); // Reload page
                } else {
                    showToast(data.message || 'An error occurred while adding the order.', 'error');
                }
            })
            .catch(error => {
                console.error('Add order AJAX error:', error);
                showToast('A server error occurred while adding the order.', 'error');
            });
        }


        // --- Inventory Overlay and Cart Functions ---

        function openInventoryOverlay() {
            $('#inventoryOverlay').css('display', 'flex');
            const inventoryBody = $('.inventory');
            inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory...</td></tr>');

            fetch('/backend/get_inventory.php')
            .then(response => { /* ... existing fetch logic ... */
                 if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                 return response.text().then(text => {
                     try { return JSON.parse(text); }
                     catch (e) { console.error("Invalid JSON:", text); throw new Error("Invalid JSON response"); }
                 });
             })
            .then(data => {
                console.log("Inventory data:", data); // Debug log
                if (Array.isArray(data)) {
                    const categories = [...new Set(data.map(item => item.category).filter(cat => cat))];
                    populateInventory(data);
                    populateCategories(categories);
                } else if (data && data.success) { // Handle object format {success: true, inventory: [], categories: []}
                     const inventory = data.inventory || [];
                     const categories = data.categories || [...new Set(inventory.map(item => item.category).filter(cat => cat))];
                     populateInventory(inventory);
                     populateCategories(categories);
                }
                 else {
                    throw new Error(data?.message || 'Failed to load inventory data format.');
                }
            })
            .catch(error => {
                console.error('Error fetching inventory:', error);
                showToast('Error fetching inventory: ' + error.message, 'error');
                inventoryBody.html(`<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${error.message}</td></tr>`);
            });
        }

        function populateInventory(inventory) {
            const inventoryBody = $('.inventory').empty(); // Clear previous content
            if (!inventory || inventory.length === 0) {
                inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No inventory items found</td></tr>');
                return;
            }
            inventory.forEach(item => {
                 // Ensure price is a number
                 const price = parseFloat(item.price);
                 if (isNaN(price)) {
                     console.warn("Skipping item with invalid price:", item);
                     return; // Skip items with invalid prices
                 }
                const row = `
                    <tr>
                        <td>${item.category || 'Uncategorized'}</td>
                        <td>${item.item_description || 'N/A'}</td>
                        <td>${item.packaging || ''}</td>
                        <td>PHP ${price.toFixed(2)}</td>
                        <td><input type="number" class="inventory-quantity" min="1" max="1000" value="1"></td>
                        <td>
                            <button class="add-to-cart-btn" onclick="addToCart(this, '${item.product_id}', '${item.category || ''}', '${(item.item_description || '').replace(/'/g, "\\'")}', '${item.packaging || ''}', ${price})">
                                <i class="fas fa-cart-plus"></i> Add
                            </button>
                        </td>
                    </tr>`;
                inventoryBody.append(row);
            });
        }

        function populateCategories(categories) {
            const filterSelect = $('#inventoryFilter');
            // Clear existing options except "All Categories"
            filterSelect.find('option:not(:first-child)').remove();
            if (!categories || categories.length === 0) return;
            categories.sort().forEach(category => {
                if (!category) return;
                filterSelect.append(`<option value="${category}">${category}</option>`);
            });
             // Ensure event listener is attached correctly (using .off().on() is safer)
             filterSelect.off('change', filterInventory).on('change', filterInventory);
        }

        function filterInventory() {
            const category = $('#inventoryFilter').val();
            const searchText = $('#inventorySearch').val().toLowerCase();
            $('.inventory tr').each(function() {
                const row = $(this);
                 // Ignore header row or message row
                 if (row.find('th').length > 0 || (row.find('td').length === 1 && row.find('td').attr('colspan') === '6')) {
                     return;
                 }
                const categoryCell = row.find('td:first-child').text();
                const rowText = row.text().toLowerCase();
                const categoryMatch = category === 'all' || categoryCell === category;
                const searchMatch = !searchText || rowText.includes(searchText);
                row.toggle(categoryMatch && searchMatch); // Use toggle for show/hide
            });
        }
         // Ensure search input also triggers filter
         $('#inventorySearch').off('input', filterInventory).on('input', filterInventory);


        function closeInventoryOverlay() {
            $('#inventoryOverlay').css('display', 'none');
        }

        // Add item to the temporary cart array
        function addToCart(button, itemId, category, itemDescription, packaging, price) {
            const row = $(button).closest('tr');
            const quantityInput = row.find('.inventory-quantity');
            const quantity = parseInt(quantityInput.val());

            if (isNaN(quantity) || quantity < 1 || quantity > 1000) {
                showToast('Please enter a valid quantity (1-1000)', 'error');
                quantityInput.val(1); // Reset
                return;
            }

            // Find existing item (match id AND packaging)
            const existingItemIndex = cartItems.findIndex(item => item.id === itemId && item.packaging === packaging);
            if (existingItemIndex >= 0) {
                cartItems[existingItemIndex].quantity += quantity;
            } else {
                cartItems.push({ id: itemId, category, item_description: itemDescription, packaging, price, quantity });
            }

            showToast(`Added ${quantity} x ${itemDescription} (${packaging || 'N/A'})`, 'success');
            quantityInput.val(1); // Reset input
            updateOrderSummary(); // Update summary on main form
            updateCartItemCount(); // Update cart icon count
        }

        // Update the visual summary table on the main Add Order form
        function updateOrderSummary() {
            const summaryBody = $('#summaryBody').empty(); // Clear previous
            let totalAmount = 0;

            if (cartItems.length === 0) {
                summaryBody.html('<tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products selected</td></tr>');
            } else {
                cartItems.forEach((item, index) => { // Add index for removal
                    const itemTotal = item.price * item.quantity;
                    totalAmount += itemTotal;
                    const row = `
                        <tr>
                            <td>${item.category}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging}</td>
                            <td>PHP ${item.price.toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td>
                                <button type="button" class="remove-item-btn btn-sm" onclick="removeSummaryItem(${index})">
                                     <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    summaryBody.append(row);
                });
            }
            $('.summary-total-amount').text(`PHP ${totalAmount.toFixed(2)}`);
        }

         // Remove item directly from the summary table and cart array
         function removeSummaryItem(index) {
             if (index >= 0 && index < cartItems.length) {
                 const removedItem = cartItems.splice(index, 1)[0]; // Remove item from array
                 showToast(`Removed ${removedItem.item_description}`, 'info');
                 updateOrderSummary(); // Redraw summary table
                 updateCartItemCount(); // Update cart count
             }
         }


        // Update the count display on the cart button
        function updateCartItemCount() {
            $('#cartItemCount').text(cartItems.length);
        }

        // Open the separate cart modal
        window.openCartModal = function() { // Make it globally accessible
            $('#cartModal').css('display', 'flex');
            updateCartDisplay(); // Populate cart modal
        }

        // Close the cart modal
        function closeCartModal() {
            $('#cartModal').css('display', 'none');
        }

        // Populate the cart modal content
        function updateCartDisplay() {
            const cartBody = $('.cart').empty(); // Clear previous
            const noProductsMessage = $('.no-products');
            const totalAmountElement = $('.total-amount');
            let totalAmount = 0;

            if (cartItems.length === 0) {
                noProductsMessage.show();
                totalAmountElement.text('PHP 0.00');
                return;
            }

            noProductsMessage.hide();
            cartItems.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                totalAmount += itemTotal;
                const row = `
                    <tr>
                        <td>${item.category}</td>
                        <td>${item.item_description}</td>
                        <td>${item.packaging}</td>
                        <td>PHP ${item.price.toFixed(2)}</td>
                        <td>
                            <input type="number" class="cart-quantity" min="1" max="1000" value="${item.quantity}"
                                data-index="${index}" onchange="updateCartItemQuantity(this)">
                        </td>
                         <td>
                             <button class="remove-item-btn btn-sm" onclick="removeCartItem(${index})">
                                 <i class="fas fa-trash"></i>
                             </button>
                         </td>
                    </tr>`;
                cartBody.append(row);
            });
            totalAmountElement.text(`PHP ${totalAmount.toFixed(2)}`);
        }

        // Update quantity when changed within the cart modal
        function updateCartItemQuantity(input) {
            const index = parseInt($(input).data('index'));
            const newQuantity = parseInt($(input).val());

            if (isNaN(newQuantity) || newQuantity < 1 || newQuantity > 1000) {
                showToast('Invalid quantity (1-1000)', 'error');
                $(input).val(cartItems[index].quantity); // Revert
                return;
            }
            cartItems[index].quantity = newQuantity;
            updateCartDisplay(); // Recalculate total in modal
        }

        // Remove item when delete button clicked in cart modal
        function removeCartItem(index) {
            if (index >= 0 && index < cartItems.length) {
                 const removedItem = cartItems.splice(index, 1)[0]; // Remove from array
                 showToast(`Removed ${removedItem.item_description} from cart`, 'info');
                 updateCartDisplay(); // Redraw cart modal
                 updateCartItemCount(); // Update cart icon count
            }
        }

        // Save changes from cart modal back to the main order summary
        function saveCartChanges() {
            updateOrderSummary(); // Update the main form's summary table
            closeCartModal();
        }


        // Document ready function
        $(document).ready(function() {
            // Search for main orders table
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                $(".orders-table tbody tr").each(function() {
                    $(this).toggle($(this).text().toLowerCase().includes(searchText));
                });
            });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input")); // Trigger input event on button click

            // Initialize Add Order form state on page load
            initializeDeliveryDatePicker(); // Ensure datepicker is ready
            toggleDeliveryAddress();
            generatePONumber(); // Generate initial PO number if user is pre-selected

            // Close modals on outside click
            window.addEventListener('click', function(event) {
                // Close instruction modal
                const instructionsModal = document.getElementById('specialInstructionsModal');
                if (instructionsModal && event.target === instructionsModal) {
                    closeSpecialInstructions();
                }
                // Close confirmation modals
                $('.confirmation-modal').each(function() {
                     if (event.target === this) {
                         $(this).hide();
                         // Handle reopening underlying modal if status confirmation was closed
                         if (this.id === 'statusConfirmationModal') {
                             closeStatusConfirmation();
                         }
                     }
                });
                 // Close overlay modals (Add Order, Inventory, Cart, Driver)
                 $('.overlay').each(function() {
                     if (event.target === this) {
                          // Decide which close function to call based on ID
                          if (this.id === 'addOrderOverlay') closeAddOrderForm();
                          else if (this.id === 'inventoryOverlay') closeInventoryOverlay();
                          else if (this.id === 'cartModal') closeCartModal();
                          else if (this.id === 'driverModal') closeDriverModal();
                          else if (this.id === 'orderDetailsModal') closeOrderDetailsModal();
                          // Add more specific close functions if needed
                          else $(this).hide(); // Generic hide as fallback
                     }
                 });

            });
        });
    </script>
</body>
</html>