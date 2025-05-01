<?php
// Establish database connection
include "../../backend/db_connection.php";

try {
    // SQL query to get products with their category (from the category column in products)
    $query = "SELECT product_id, item_description, packaging, price, category FROM products WHERE status = 'active' ORDER BY category, item_description";
    
    $result = $conn->query($query);
    
    $inventory = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventory[] = [
                'product_id' => $row['product_id'],
                'item_description' => $row['item_description'],
                'packaging' => $row['packaging'],
                'price' => $row['price'],
                'category' => $row['category'] ? $row['category'] : 'Uncategorized'
            ];
        }
    }
    
    // Get distinct categories from the products table
    $categories_query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category";
    $categories_result = $conn->query($categories_query);
    
    $categories = [];
    if ($categories_result && $categories_result->num_rows > 0) {
        while ($row = $categories_result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
    
    // Return data in structured format
    $response = [
        'success' => true,
        'inventory' => $inventory,
        'categories' => $categories
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
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
        #addOrderOverlay .overlay-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 90vh;
            overflow-y: auto;
            margin: 0; /* Remove default margin */
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
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars($order['orders']) ?>', '<?= htmlspecialchars($order['status']) ?>')">
                                            <i class="fas fa-clipboard-list"></i>    
                                            View
                                        </button>
                                    <?php endif; ?>
                                </td>
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
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
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
    
    <!-- Status Modal -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
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
            <form id="addOrderForm" method="POST" class="order-form" action="/backend/add_order.php">
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
                    <input type="hidden" name="special_instructions" id="special_instructions_hidden">
                    <!-- Add special instructions field -->
                    <label for="special_instructions">Special Instructions:</label>
                    <textarea id="special_instructions" name="special_instructions" rows="3" placeholder="Enter any special instructions here..."></textarea>
                    
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
                                <!-- Summary will be populated here -->
                            </tbody>
                        </table>
                        <div class="summary-total">
                            Total: <span class="summary-total-amount">PHP 0.00</span>
                        </div>
                    </div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders">
                    <input type="hidden" name="total_amount" id="total_amount">
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
                    <i class="fas fa-shopping-cart"></i> View Cart
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
                        <!-- Selected products list will be populated here -->
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
        let cartItems = [];

        // Toast functionality
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                    <div class="message">${message}</div>
                </div>
            `;
            document.getElementById('toast-container').appendChild(toast);
            
            // Automatically remove the toast after 5 seconds
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Status modal functions with confirmations
        function confirmStatusChange(poNumber, username) {
            currentPoNumber = poNumber;
            document.getElementById('statusMessage').textContent = `Change status for order ${poNumber} (${username})`;
            document.getElementById('statusModal').style.display = 'flex';
        }
        
        function confirmStatusAction(status) {
            selectedStatus = status;
            document.getElementById('statusConfirmationMessage').textContent = `Are you sure you want to change the status to ${status}?`;
            document.getElementById('statusConfirmationModal').style.display = 'block';
            
            // Hide the original modal
            document.getElementById('statusModal').style.display = 'none';
            document.getElementById('pendingStatusModal').style.display = 'none';
            document.getElementById('rejectedStatusModal').style.display = 'none';
        }
        
        function closeStatusConfirmation() {
            document.getElementById('statusConfirmationModal').style.display = 'none';
            
            // Based on which modal was active, reopen it
            if (selectedStatus === 'Active' || selectedStatus === 'Rejected') {
                document.getElementById('pendingStatusModal').style.display = 'flex';
            } else if (selectedStatus === 'Pending') {
                document.getElementById('rejectedStatusModal').style.display = 'flex';
            } else {
                document.getElementById('statusModal').style.display = 'flex';
            }
        }
        
        function executeStatusChange() {
            document.getElementById('statusConfirmationModal').style.display = 'none';
            
            // For 'For Delivery' status, check if a driver has been assigned and progress is 100%
            if (selectedStatus === 'For Delivery') {
                // Show a loading indicator
                const modalContent = document.querySelector('.modal-content');
                const loadingDiv = document.createElement('div');
                loadingDiv.id = 'status-loading';
                loadingDiv.style.textAlign = 'center';
                loadingDiv.style.margin = '10px 0';
                loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking requirements...';
                modalContent.appendChild(loadingDiv);
                
                // Disable all buttons while checking
                const buttons = document.querySelectorAll('.modal-status-btn, .modal-cancel-btn');
                buttons.forEach(btn => btn.disabled = true);
                
                // Fetch the order details to check driver_assigned flag and progress
                fetch(`/backend/check_order_driver.php?po_number=${currentPoNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        // Remove loading indicator
                        if (document.getElementById('status-loading')) {
                            document.getElementById('status-loading').remove();
                        }
                        
                        // Re-enable buttons
                        buttons.forEach(btn => btn.disabled = false);
                        
                        if (data.success) {
                            // Check if driver is assigned
                            if (!data.driver_assigned) {
                                showToast('Error: You must assign a driver to this order before marking it for delivery.', 'error');
                                closeStatusModal();
                                return;
                            }
                            
                            // Check if progress is 100%
                            if (data.progress < 100) {
                                showToast('Error: Order progress must be 100% before marking it for delivery.', 'error');
                                closeStatusModal();
                                return;
                            }
                            
                            // Both requirements are met, proceed with status change
                            updateOrderStatus(selectedStatus, false);
                        } else {
                                                        showToast('Error checking order requirements: ' + data.message, 'error');
                            closeStatusModal();
                        }
                    })
                    .catch(error => {
                        // Remove loading indicator
                        if (document.getElementById('status-loading')) {
                            document.getElementById('status-loading').remove();
                        }
                        
                        // Re-enable buttons
                        buttons.forEach(btn => btn.disabled = false);
                        
                        console.error('Error:', error);
                        showToast('Error checking requirements: ' + error, 'error');
                        closeStatusModal();
                    });
            } else {
                // For going back to Pending or Rejected from Active, we need to return materials to inventory
                const returnMaterials = (selectedStatus === 'Pending' || selectedStatus === 'Rejected');
                
                // For other statuses, proceed directly
                updateOrderStatus(selectedStatus, returnMaterials);
            }
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            selectedStatus = '';
        }

        function confirmRejectedStatusChange(poNumber, username) {
            currentPoNumber = poNumber;
            document.getElementById('rejectedStatusMessage').textContent = `Change status for rejected order ${poNumber} (${username})`;
            document.getElementById('rejectedStatusModal').style.display = 'flex';
        }

        function closeRejectedStatusModal() {
            document.getElementById('rejectedStatusModal').style.display = 'none';
            selectedStatus = '';
        }

        function updateOrderStatus(status, returnMaterials) {
            // Create form data
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);
            formData.append('return_materials', returnMaterials ? '1' : '0');
            
            // Send AJAX request to update status
            fetch('/backend/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    let message = 'Status updated successfully';
                    if (status === 'For Delivery') {
                        message = 'Order marked for delivery successfully';
                    } else if (status === 'Rejected') {
                        message = returnMaterials ? 'Order rejected and materials returned to inventory' : 'Order rejected successfully';
                    } else if (status === 'Pending' && returnMaterials) {
                        message = 'Order set to pending and materials returned to inventory';
                    }
                    showToast(message, 'success');
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error updating status: ' + data.message, 'error');
                }
                closeStatusModal();
                closeRejectedStatusModal();
            })
            .catch(error => {
                showToast('Error updating status: ' + error, 'error');
                closeStatusModal();
                closeRejectedStatusModal();
            });
        }
        
        // Pending status change
        function confirmPendingStatusChange(poNumber, username, ordersJson) {
            currentPoNumber = poNumber;
            $('#pendingStatusMessage').text('Change order status for ' + poNumber);
            $('#pendingStatusModal').data('po_number', poNumber).show();
            
            // Clear previous data and show loading state
            $('#rawMaterialsContainer').html('<h3>Loading inventory status...</h3>');
            
            // Parse the orders JSON and check materials
            try {
                $.ajax({
                    url: '/backend/check_raw_materials.php',
                    type: 'POST',
                    data: { 
                        orders: ordersJson,
                        po_number: poNumber
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Display finished products status first
                            if (response.finishedProducts) {
                                displayFinishedProducts(response.finishedProducts);
                            }
                            
                            // If manufacturing is needed, display raw materials
                            if (response.needsManufacturing && response.materials) {
                                displayRawMaterials(response.materials);
                            } else {
                                // Hide the raw materials section if no manufacturing needed
                                $('#rawMaterialsContainer').append('<p>All products are in stock - no manufacturing needed</p>');
                            }
                            
                            // Enable or disable the Active button based on overall status
                            updateOrderActionStatus(response);
                        } else {
                            $('#rawMaterialsContainer').html(`
                                <h3>Error Checking Inventory</h3>
                                <p style="color:red;">${response.message || 'Unknown error'}</p>
                                <p>Order status can still be changed.</p>
                            `);
                            $('#activeStatusBtn').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#rawMaterialsContainer').html(`
                            <h3>Server Error</h3>
                            <p style="color:red;">Could not connect to server: ${error}</p>
                            <p>Order status can still be changed.</p>
                        `);
                        $('#activeStatusBtn').prop('disabled', false);
                        console.error("AJAX Error:", status, error);
                    }
                });
            } catch (e) {
                $('#rawMaterialsContainer').html(`
                    <h3>Error Processing Data</h3>
                    <p style="color:red;">${e.message}</p>
                    <p>Order status can still be changed.</p>
                `);
                $('#activeStatusBtn').prop('disabled', false);
                console.error("Error:", e);
            }
        }

        function closePendingStatusModal() {
            document.getElementById('pendingStatusModal').style.display = 'none';
            selectedStatus = '';
        }
        
        // Helper function to format weight values
        function formatWeight(weightInGrams) {
            if (weightInGrams >= 1000) {
                return (weightInGrams / 1000).toFixed(2) + ' kg';
            } else {
                return weightInGrams.toFixed(2) + ' g';
            }
        }
        
        // Function to display finished products status
        function displayFinishedProducts(productsData) {
            const productsTableHTML = `
                <h3>Finished Products Status</h3>
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
                            const available = parseInt(data.available);
                            const required = parseInt(data.required);
                            const isSufficient = data.sufficient;
                            
                            return `
                                <tr>
                                    <td>${product}</td>
                                    <td>${available}</td>
                                    <td>${required}</td>
                                    <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                        ${isSufficient ? 'In Stock' : 'Need to manufacture ' + data.shortfall + ' more'}
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
            
            // Update the HTML container
            $('#rawMaterialsContainer').html(productsTableHTML);
            
            // Check if any products need manufacturing
            const needsManufacturing = Object.values(productsData).some(product => !product.sufficient);
            
            if (needsManufacturing) {
                $('#rawMaterialsContainer').append(`
                    <h3>Raw Materials Required for Manufacturing</h3>
                    <div id="raw-materials-section">
                        <p>Loading raw materials information...</p>
                    </div>
                `);
            }
        }
        
        // Function to display raw materials data
        function displayRawMaterials(materialsData) {
            if (!materialsData || Object.keys(materialsData).length === 0) {
                $('#raw-materials-section').html('<p>No raw materials information available.</p>');
                return;
            }
            
            // Count sufficient vs insufficient materials
            let allSufficient = true;
            let insufficientMaterials = [];
            
            const materialsTableHTML = `
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
                            const available = parseFloat(data.available);
                            const required = parseFloat(data.required);
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
            
            // Add status message
            const statusMessage = allSufficient 
                ? 'All raw materials are sufficient for manufacturing.' 
                : `Insufficient raw materials: ${insufficientMaterials.join(', ')}. The order cannot proceed.`;
            
            const statusClass = allSufficient ? 'status-sufficient' : 'status-insufficient';
            
            const fullHTML = `
                ${materialsTableHTML}
                <p class="materials-status ${statusClass}">${statusMessage}</p>
            `;
            
            $('#raw-materials-section').html(fullHTML);
            
            // Enable or disable the Active button
            $('#activeStatusBtn').prop('disabled', !allSufficient);
            
            return allSufficient;
        }

        // Function to update order action status
        function updateOrderActionStatus(response) {
            let canProceed = true;
            let statusMessage = 'All inventory requirements are met. The order can proceed.';
            
            // Check if all finished products are in stock
            const finishedProducts = response.finishedProducts || {};
            const allProductsInStock = Object.values(finishedProducts).every(product => product.sufficient);
            
            // If manufacturing is needed, check raw materials
            if (!allProductsInStock && response.needsManufacturing) {
                // Check if all products can be manufactured
                const canManufactureAll = Object.values(finishedProducts).every(product => 
                    product.sufficient || product.canManufacture !== false);
                    
                if (!canManufactureAll) {
                    canProceed = false;
                    statusMessage = 'Some products cannot be manufactured due to missing ingredients.';
                } else {
                    // Check if all raw materials are sufficient
                    const materials = response.materials || {};
                    const allMaterialsSufficient = Object.values(materials).every(material => material.sufficient);
                    
                    if (!allMaterialsSufficient) {
                        canProceed = false;
                        statusMessage = 'Insufficient raw materials for manufacturing required products.';
                    } else {
                        statusMessage = 'Products will be manufactured using raw materials. The order can proceed.';
                    }
                }
            }
            
            // Update UI based on status
            $('#activeStatusBtn').prop('disabled', !canProceed);
            
            // Add a summary at the end of the container
            const statusClass = canProceed ? 'status-sufficient' : 'status-insufficient';
            $('#rawMaterialsContainer').append(`
                <p class="materials-status ${statusClass}">${statusMessage}</p>
            `);
        }

        // View order details
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            
            // Fetch the order data and completion status
            fetch(`/backend/get_order_details.php?po_number=${poNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentOrderItems = data.orderItems;
                    completedItems = data.completedItems || [];
                    quantityProgressData = data.quantityProgressData || {};
                    itemProgressPercentages = data.itemProgressPercentages || {};
                    
                    const orderDetailsBody = document.getElementById('orderDetailsBody');
                    orderDetailsBody.innerHTML = '';
                    
                    // Make sure the Status header is visible for active orders
                    document.getElementById('status-header-cell').style.display = '';
                    
                    // Update order status in the modal title
                    document.getElementById('orderStatus').textContent = 'Active';
                    
                    // Calculate item contributions to overall progress
                    const totalItems = currentOrderItems.length;
                    const contributionPerItem = totalItems > 0 ? (100 / totalItems) : 0;
                    
                    // Track overall progress
                    overallProgress = 0;
                    
                    currentOrderItems.forEach((item, index) => {
                        const isCompleted = completedItems.includes(index);
                        const itemQuantity = parseInt(item.quantity) || 0;
                        
                        // Store contribution percentage
                        itemContributions[index] = contributionPerItem;
                        
                        // Calculate item progress percentage based on units
                        let unitCompletedCount = 0;
                        if (quantityProgressData[index]) {
                            for (let i = 0; i < itemQuantity; i++) {
                                if (quantityProgressData[index][i] === true) {
                                    unitCompletedCount++;
                                }
                            }
                        }
                        
                        // Calculate unit progress percentage
                        const unitProgress = itemQuantity > 0 ? (unitCompletedCount / itemQuantity) * 100 : 0;
                        
                        // Calculate contribution to overall progress (what this item adds to overall)
                        const contributionToOverall = (unitProgress / 100) * contributionPerItem;
                        overallProgress += contributionToOverall;
                        
                        // Store item progress
                        itemProgressPercentages[index] = unitProgress;
                        
                        // Create main row for the item
                        const mainRow = document.createElement('tr');
                        mainRow.className = 'item-header-row';
                        if (isCompleted) {
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
                                <div style="display: flex; align-items: center; justify-content: space-between">
                                    <input type="checkbox" class="item-status-checkbox" data-index="${index}" 
                                        ${isCompleted ? 'checked' : ''} onchange="updateRowStyle(this)">
                                    <button type="button" class="toggle-item-progress" data-index="${index}" onclick="toggleQuantityProgress(${index})">
                                        <i class="fas fa-list-ol"></i> Units
                                    </button>
                                </div>
                                <div class="item-progress-bar-container">
                                    <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div>
                                </div>
                                <span class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}% Complete</span>
                                <span class="contribution-text" id="contribution-text-${index}">
                                    (${Math.round(contributionToOverall)}% of total)
                                </span>
                            </td>
                        `;
                        orderDetailsBody.appendChild(mainRow);
                        
                        // Add a divider row
                        const dividerRow = document.createElement('tr');
                        dividerRow.className = 'units-divider';
                        dividerRow.id = `units-divider-${index}`;
                        dividerRow.style.display = 'none';
                        dividerRow.innerHTML = `<td colspan="6"></td>`;
                        orderDetailsBody.appendChild(dividerRow);
                        
                        // Create rows for individual units
                        for (let i = 0; i < itemQuantity; i++) {
                            // Check if this unit is completed
                            const isUnitCompleted = quantityProgressData[index] && 
                                                    quantityProgressData[index][i] === true;
                            
                            const unitRow = document.createElement('tr');
                            unitRow.className = `unit-row unit-item unit-for-item-${index}`;
                            unitRow.style.display = 'none';
                            if (isUnitCompleted) {
                                unitRow.classList.add('completed');
                            }
                            
                            unitRow.innerHTML = `
                                <td>${item.category}</td>
                                <td>${item.item_description}</td>
                                <td>${item.packaging}</td>
                                <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                                <td class="unit-number-cell">Unit ${i+1}</td>
                                <td>
                                    <input type="checkbox" class="unit-status-checkbox" 
                                        data-item-index="${index}" 
                                        data-unit-index="${i}" 
                                        ${isUnitCompleted ? 'checked' : ''} 
                                        onchange="updateUnitStatus(this)">
                                </td>
                            `;
                            orderDetailsBody.appendChild(unitRow);
                        }
                        
                        // Add an action row with a "Select All" button
                        if (itemQuantity > 0) {
                            const actionRow = document.createElement('tr');
                            actionRow.className = `unit-row unit-action-row unit-for-item-${index}`;
                            actionRow.style.display = 'none';
                            actionRow.innerHTML = `
                                <td colspan="6" style="text-align: right; padding: 10px;">
                                    <button type="button" class="select-all-units" onclick="selectAllUnits(${index}, ${itemQuantity})">
                                        <i class="fas fa-check-square"></i> Select All Units
                                    </button>
                                </td>
                            `;
                            orderDetailsBody.appendChild(actionRow);
                        }
                    });
                    
                    // Update overall progress display
                    updateOverallProgressDisplay();
                    
                    // Calculate the total amount (needed for display)
                    let totalAmount = 0;
                    currentOrderItems.forEach(item => {
                        const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                        totalAmount += itemTotal;
                    });
                    
                    // Update total amount in the modal
                    document.getElementById('orderTotalAmount').textContent = `PHP ${totalAmount.toFixed(2)}`;
                    
                    // Show progress tracking controls for active orders
                    const overallProgressInfo = document.getElementById('overall-progress-info');
                    if (overallProgressInfo) {
                        overallProgressInfo.style.display = 'block';
                    }
                    
                    // Show save button for active orders
                    const saveButton = document.querySelector('.save-progress-btn');
                    if (saveButton) {
                        saveButton.style.display = 'block';
                    }
                    
                    document.getElementById('orderDetailsModal').style.display = 'flex';
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error, 'error');
                console.error('Error fetching order details:', error);
            });
        }
        
        // Function to view order info for non-active orders
        function viewOrderInfo(ordersJson, orderStatus) {
            try {
                const orderDetails = JSON.parse(ordersJson);
                const orderDetailsBody = document.getElementById('orderDetailsBody');
                
                // Clear previous content
                orderDetailsBody.innerHTML = '';
                
                // Hide the Status header for read-only view
                document.getElementById('status-header-cell').style.display = 'none';
                
                // Update order status in the modal title
                document.getElementById('orderStatus').textContent = orderStatus;
                
                // Calculate total amount
                let totalAmount = 0;
                
                // Populate order details
                orderDetails.forEach(product => {
                    const itemTotal = parseFloat(product.price) * parseInt(product.quantity);
                    totalAmount += itemTotal;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${product.category || ''}</td>
                        <td>${product.item_description}</td>
                        <td>${product.packaging || ''}</td>
                        <td>PHP ${parseFloat(product.price).toFixed(2)}</td>
                        <td>${product.quantity}</td>
                    `;
                    orderDetailsBody.appendChild(row);
                });
                
                // Update total amount in the modal
                document.getElementById('orderTotalAmount').textContent = `PHP ${totalAmount.toFixed(2)}`;
                
                // Hide progress bar elements
                const overallProgressInfo = document.getElementById('overall-progress-info');
                if (overallProgressInfo) {
                    overallProgressInfo.style.display = 'none';
                }
                
                // Hide save button for pending/rejected orders
                const saveButton = document.querySelector('.save-progress-btn');
                if (saveButton) {
                    saveButton.style.display = 'none';
                }
                
                // Show modal
                document.getElementById('orderDetailsModal').style.display = 'flex';
            } catch (e) {
                console.error('Error parsing order details:', e);
                alert('Error displaying order details');
            }
        }
        
        function toggleQuantityProgress(itemIndex) {
            const unitRows = document.querySelectorAll(`.unit-for-item-${itemIndex}`);
            const dividerRow = document.getElementById(`units-divider-${itemIndex}`);
            const isVisible = unitRows[0].style.display !== 'none';
            
            // Toggle divider
            dividerRow.style.display = isVisible ? 'none' : 'table-row';
            
            // Toggle unit rows
            unitRows.forEach(row => {
                row.style.display = isVisible ? 'none' : 'table-row';
            });
        }
        
        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.getAttribute('data-item-index'));
            const unitIndex = parseInt(checkbox.getAttribute('data-unit-index'));
            const isChecked = checkbox.checked;
            
            // Update unit row style
            const unitRow = checkbox.closest('tr');
            if (isChecked) {
                unitRow.classList.add('completed');
            } else {
                unitRow.classList.remove('completed');
            }
            
            // Initialize the quantityProgressData structure if needed
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
                const itemQuantity = parseInt(currentOrderItems[itemIndex].quantity) || 0;
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[itemIndex].push(false);
                }
            }
            
            // Update the progress data
            quantityProgressData[itemIndex][unitIndex] = isChecked;
            
            // Update item progress and contribution to overall
            updateItemProgress(itemIndex);
            
            // Update overall progress
            updateOverallProgress();
        }
        
        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex];
            const itemQuantity = parseInt(item.quantity) || 0;
            
            if (itemQuantity === 0) return;
            
            // Count completed units
            let completedUnits = 0;
            for (let i = 0; i < itemQuantity; i++) {
                if (quantityProgressData[itemIndex] && quantityProgressData[itemIndex][i]) {
                    completedUnits++;
                }
            }
            
            // Calculate unit progress percentage
            const unitProgress = (completedUnits / itemQuantity) * 100;
            itemProgressPercentages[itemIndex] = unitProgress;
            
            // Calculate contribution to overall progress
            const contributionToOverall = (unitProgress / 100) * itemContributions[itemIndex];
            
            // Update item progress display
            const progressBar = document.getElementById(`item-progress-bar-${itemIndex}`);
            const progressText = document.getElementById(`item-progress-text-${itemIndex}`);
            const contributionText = document.getElementById(`contribution-text-${itemIndex}`);
            
            progressBar.style.width = `${unitProgress}%`;
            progressText.textContent = `${Math.round(unitProgress)}% Complete`;
            contributionText.textContent = `(${Math.round(contributionToOverall)}% of total)`;
            
            // Check if all units are complete to update item checkbox
            updateItemStatusBasedOnUnits(itemIndex, completedUnits === itemQuantity);
        }
        
        function updateOverallProgressDisplay() {
            const overallProgressBar = document.getElementById('overall-progress-bar');
            const overallProgressText = document.getElementById('overall-progress-text');
            
            // Round to nearest whole number
            const roundedProgress = Math.round(overallProgress);
            
            overallProgressBar.style.width = `${roundedProgress}%`;
            overallProgressText.textContent = `${roundedProgress}%`;
        }
        
        function updateOverallProgress() {
            // Calculate overall progress from all items
            let newOverallProgress = 0;
            
            Object.keys(itemProgressPercentages).forEach(itemIndex => {
                const itemProgress = itemProgressPercentages[itemIndex];
                const itemContribution = itemContributions[itemIndex];
                newOverallProgress += (itemProgress / 100) * itemContribution;
            });
            
            overallProgress = newOverallProgress;
            updateOverallProgressDisplay();
            
            return Math.round(overallProgress);
        }
        
        function updateItemStatusBasedOnUnits(itemIndex, allComplete) {
            // Update the main item checkbox based on unit completion
            const mainCheckbox = document.querySelector(`.item-status-checkbox[data-index="${itemIndex}"]`);
            const mainRow = document.querySelector(`tr[data-item-index="${itemIndex}"]`);
            
            if (allComplete) {
                mainCheckbox.checked = true;
                mainRow.classList.add('completed-item');
                if (!completedItems.includes(parseInt(itemIndex))) {
                    completedItems.push(parseInt(itemIndex));
                }
            } else {
                mainCheckbox.checked = false;
                mainRow.classList.remove('completed-item');
                const completedIndex = completedItems.indexOf(parseInt(itemIndex));
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
            }
        }
        
        function selectAllUnits(itemIndex, quantity) {
            // Get all unit checkboxes for this item
            const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            
            // Check all unit checkboxes
            unitCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
                const unitRow = checkbox.closest('tr');
                unitRow.classList.add('completed');
            });
            
            // Update the progress data
            if (!quantityProgressData[itemIndex]) {
                quantityProgressData[itemIndex] = [];
            }
            
            for (let i = 0; i < quantity; i++) {
                quantityProgressData[itemIndex][i] = true;
            }
            
            // Update item progress
            updateItemProgress(itemIndex);
            
            // Update overall progress
            updateOverallProgress();
        }
        
        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.getAttribute('data-index'));
            const row = checkbox.closest('tr');
            const itemQuantity = parseInt(currentOrderItems[index].quantity) || 0;
            
            if (checkbox.checked) {
                row.classList.add('completed-item');
                if (!completedItems.includes(index)) {
                    completedItems.push(index);
                }
                
                // Mark all units as completed
                if (!quantityProgressData[index]) {
                    quantityProgressData[index] = [];
                }
                
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[index][i] = true;
                }
                
                // Update unit checkboxes and row styles
                const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${index}"]`);
                unitCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    const unitRow = checkbox.closest('tr');
                    unitRow.classList.add('completed');
                });
                
                // Set item progress to 100%
                itemProgressPercentages[index] = 100;
                
                // Update item display
                const progressBar = document.getElementById(`item-progress-bar-${index}`);
                const progressText = document.getElementById(`item-progress-text-${index}`);
                const contributionText = document.getElementById(`contribution-text-${index}`);
                
                progressBar.style.width = '100%';
                progressText.textContent = '100% Complete';
                contributionText.textContent = `(${Math.round(itemContributions[index])}% of total)`;
                
            } else {
                row.classList.remove('completed-item');
                const completedIndex = completedItems.indexOf(index);
                if (completedIndex > -1) {
                    completedItems.splice(completedIndex, 1);
                }
                
                // Mark all units as not completed
                if (!quantityProgressData[index]) {
                    quantityProgressData[index] = [];
                }
                
                for (let i = 0; i < itemQuantity; i++) {
                    quantityProgressData[index][i] = false;
                }
                
                // Update unit checkboxes and row styles
                const unitCheckboxes = document.querySelectorAll(`.unit-status-checkbox[data-item-index="${index}"]`);
                unitCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                    const unitRow = checkbox.closest('tr');
                    unitRow.classList.remove('completed');
                });
                
                // Set item progress to 0%
                itemProgressPercentages[index] = 0;
                
                // Update item display
                const progressBar = document.getElementById(`item-progress-bar-${index}`);
                const progressText = document.getElementById(`item-progress-text-${index}`);
                const contributionText = document.getElementById(`contribution-text-${index}`);
                
                progressBar.style.width = '0%';
                progressText.textContent = '0% Complete';
                contributionText.textContent = '(0% of total)';
            }
            
            // Update overall progress
            updateOverallProgress();
        }
        
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        // Progress saving with confirmation
        function confirmSaveProgress() {
            document.getElementById('saveProgressConfirmationModal').style.display = 'block';
        }
        
        function closeSaveProgressConfirmation() {
            document.getElementById('saveProgressConfirmationModal').style.display = 'none';
        }
        
        function saveProgressChanges() {
            document.getElementById('saveProgressConfirmationModal').style.display = 'none';
            
            // Calculate overall progress percentage
            const progressPercentage = updateOverallProgress();
            
            // Determine if the order should be marked for delivery automatically when at 100%
            const shouldMarkForDelivery = progressPercentage === 100;
            
            // Send AJAX request to update progress
            fetch('/backend/update_order_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    completed_items: completedItems,
                    quantity_progress_data: quantityProgressData,
                    item_progress_percentages: itemProgressPercentages,
                    progress: progressPercentage,
                    auto_delivery: shouldMarkForDelivery,
                    driver_id: currentDriverId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (shouldMarkForDelivery) {
                        showToast('Order is ready for delivery!', 'success');
                    } else {
                        showToast('Progress updated successfully', 'success');
                    }
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error updating progress: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error, 'error');
            });
        }
        
        // Driver modal functions with confirmations
        function confirmDriverAssign(poNumber) {
            currentPoNumber = poNumber;
            currentDriverId = 0;
            
            // Update the modal title
            const modalTitle = document.getElementById('driverModalTitle');
            modalTitle.textContent = 'Assign Driver';
            document.getElementById('driverModalMessage').textContent = `Select a driver for order ${poNumber}:`;
            
            // Set the current driver in the dropdown
            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = 0;
            
            // Show the modal
            document.getElementById('driverModal').style.display = 'flex';
        }
        
        function confirmDriverChange(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId;
            
            // Update the modal title
            const modalTitle = document.getElementById('driverModalTitle');
            modalTitle.textContent = 'Change Driver Assignment';
            document.getElementById('driverModalMessage').textContent = `Current driver: ${driverName}`;
            
            // Set the current driver in the dropdown
            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = driverId;
            
            // Show the modal
            document.getElementById('driverModal').style.display = 'flex';
        }
        
        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
            currentDriverId = 0;
        }
        
        function confirmDriverAssignment() {
            const driverSelect = document.getElementById('driverSelect');
            const driverId = parseInt(driverSelect.value);
            
            if (driverId === 0 || isNaN(driverId)) {
                showToast('Please select a driver', 'error');
                return;
            }
            
            // Show confirmation modal
            document.getElementById('driverConfirmationModal').style.display = 'block';
            
            // Hide the driver modal
            document.getElementById('driverModal').style.display = 'none';
        }
        
        function closeDriverConfirmation() {
            document.getElementById('driverConfirmationModal').style.display = 'none';
            
            // Show the driver modal again
            document.getElementById('driverModal').style.display = 'flex';
        }
        
        function assignDriver() {
            document.getElementById('driverConfirmationModal').style.display = 'none';
            
            const driverSelect = document.getElementById('driverSelect');
            const driverId = parseInt(driverSelect.value);
            
            // Show a loading state
            const saveBtn = document.querySelector('#driverModal .save-btn');
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
            saveBtn.disabled = true;
            
            // Create FormData object
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('driver_id', driverId);
            
            // Use fetch API for cleaner code
            fetch('/backend/assign_driver.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        console.error("Failed to parse response as JSON:", e);
                        return {success: false, message: "Invalid server response"};
                    }
                });
            })
            .then(data => {
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
                
                if (data.success) {
                    showToast('Driver assigned successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                closeDriverModal();
            });
        }
        
        // PDF Functions with confirmation
        function confirmDownloadPO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            // Store data for download
            poDownloadData = {
                poNumber,
                username,
                company,
                orderDate,
                deliveryDate,
                deliveryAddress,
                ordersJson,
                totalAmount,
                specialInstructions
            };
            
            // Show confirmation modal
            document.getElementById('downloadConfirmationModal').style.display = 'block';
        }
        
        function closeDownloadConfirmation() {
            document.getElementById('downloadConfirmationModal').style.display = 'none';
            poDownloadData = null;
        }
        
        function downloadPODirectly() {
            document.getElementById('downloadConfirmationModal').style.display = 'none';
            
            if (!poDownloadData) {
                showToast('Error: No data available for download', 'error');
                return;
            }
            
            try {
                // Store current PO data
                currentPOData = poDownloadData;
                
                // Populate the hidden PDF content silently
                document.getElementById('printCompany').textContent = currentPOData.company || 'No Company Name';
                document.getElementById('printPoNumber').textContent = currentPOData.poNumber;
                document.getElementById('printUsername').textContent = currentPOData.username;
                document.getElementById('printDeliveryAddress').textContent = currentPOData.deliveryAddress;
                document.getElementById('printOrderDate').textContent = currentPOData.orderDate;
                document.getElementById('printDeliveryDate').textContent = currentPOData.deliveryDate;
                
                // Format the total amount
                document.getElementById('printTotalAmount').textContent = parseFloat(currentPOData.totalAmount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Handle special instructions
                const instructionsSection = document.getElementById('printInstructionsSection');
                if (currentPOData.specialInstructions && currentPOData.specialInstructions.trim() !== '') {
                    document.getElementById('printSpecialInstructions').textContent = currentPOData.specialInstructions;
                    instructionsSection.style.display = 'block';
                } else {
                    instructionsSection.style.display = 'none';
                }
                
                // Parse and populate order items
                const orderItems = JSON.parse(currentPOData.ordersJson);
                const orderItemsBody = document.getElementById('printOrderItems');
                
                // Clear previous content
                orderItemsBody.innerHTML = '';
                
                // Add items to the table
                orderItems.forEach(item => {
                    const row = document.createElement('tr');
                    
                    // Calculate item total
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    
                    row.innerHTML = `
                        <td>${item.category || ''}</td>
                        <td>${item.item_description}</td>
                        <td>${item.packaging || ''}</td>
                        <td>${item.quantity}</td>
                        <td>PHP ${parseFloat(item.price).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}</td>
                        <td>PHP ${itemTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}</td>
                    `;
                    
                    orderItemsBody.appendChild(row);
                });
                
                // Get the element to convert to PDF
                const element = document.getElementById('contentToDownload');
                
                // Configure html2pdf options
                const opt = {
                    margin:       [10, 10, 10, 10],
                    filename:     `PO_${currentPOData.poNumber}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                // Generate and download PDF directly
                html2pdf().set(opt).from(element).save().then(() => {
                    showToast(`Purchase Order ${currentPOData.poNumber} has been downloaded.`, 'success');
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    showToast('Error generating PDF. Please try again.', 'error');
                });
                
            } catch (e) {
                console.error('Error preparing PDF data:', e);
                showToast('Error preparing PDF data', 'error');
            }
        }

        function downloadPDF() {
            if (!currentPOData) {
                showToast('No PO data available for download.', 'error');
                return;
            }
            
            // Get the element to convert to PDF
            const element = document.getElementById('contentToDownload');
            
            // Configure html2pdf options
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     `PO_${currentPOData.poNumber}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Generate and download PDF
            html2pdf().set(opt).from(element).save().then(() => {
                showToast(`Purchase Order ${currentPOData.poNumber} has been downloaded as PDF.`, 'success');
                closePDFPreview();
            }).catch(error => {
                console.error('Error generating PDF:', error);
                showToast('Error generating PDF. Please try again.', 'error');
            });
        }
        
        function closePDFPreview() {
            document.getElementById('pdfPreview').style.display = 'none';
        }
        
        // Special Instructions Modal Functions
        function viewSpecialInstructions(poNumber, instructions) {
            document.getElementById('instructionsPoNumber').textContent = 'PO Number: ' + poNumber;
            const contentEl = document.getElementById('instructionsContent');
            
            if (instructions && instructions.trim().length > 0) {
                contentEl.textContent = instructions;
                contentEl.classList.remove('empty');
            } else {
                contentEl.textContent = 'No special instructions provided for this order.';
                contentEl.classList.add('empty');
            }
            
            document.getElementById('specialInstructionsModal').style.display = 'block';
        }
        
        function closeSpecialInstructions() {
            document.getElementById('specialInstructionsModal').style.display = 'none';
        }
        
        // Order add functionality with confirmation
        function openAddOrderForm() {
            document.getElementById('addOrderOverlay').style.display = 'block';
            
            // Initialize order date with current date
            const today = new Date();
            const formattedDate = today.getFullYear() + '-' + 
                    String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(today.getDate()).padStart(2, '0');
            document.getElementById('order_date').value = formattedDate;
            
            // Initialize datepicker for delivery date
            if ($.datepicker) {
                $("#delivery_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0 // Only allow future dates
                });
            }
            
            // Reset cart items
            cartItems = [];
            updateOrderSummary();
        }
        
        function closeAddOrderForm() {
            document.getElementById('addOrderOverlay').style.display = 'none';
            document.getElementById('addOrderForm').reset();
        }
        
        // Toggle between company address and custom address
        function toggleDeliveryAddress() {
            const addressType = document.getElementById('delivery_address_type').value;
            const companyContainer = document.getElementById('company_address_container');
            const customContainer = document.getElementById('custom_address_container');
            
            if (addressType === 'company') {
                companyContainer.style.display = 'block';
                customContainer.style.display = 'none';
            } else {
                companyContainer.style.display = 'none';
                customContainer.style.display = 'block';
            }
        }
        
        // Generate PO number
        function generatePONumber() {
            const username = document.getElementById('username').value;
            if (!username) return;
            
            // Get company address and company name from data attributes
            const selectedOption = document.querySelector(`#username option[value="${username}"]`);
            const companyAddress = selectedOption.getAttribute('data-company-address') || '';
            const company = selectedOption.getAttribute('data-company') || '';
            
            // Set company address in form
            document.getElementById('company_address').value = companyAddress;
            
            // Generate PO number with current date and username
            const today = new Date();
            const datePart = today.getFullYear().toString().substr(-2) + 
                            String(today.getMonth() + 1).padStart(2, '0') + 
                            String(today.getDate()).padStart(2, '0');
            const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            const poNumber = `PO-${datePart}-${username.substring(0, 3).toUpperCase()}-${random}`;
            
            document.getElementById('po_number').value = poNumber;
        }
        
        // Update delivery address before form submission
        function prepareOrderData() {
            const addressType = document.getElementById('delivery_address_type').value;
            let deliveryAddress = '';
            
            if (addressType === 'company') {
                deliveryAddress = document.getElementById('company_address').value;
            } else {
                deliveryAddress = document.getElementById('custom_address').value;
            }
            
            document.getElementById('delivery_address').value = deliveryAddress;
            
            // Include special instructions
            const specialInstructions = document.getElementById('special_instructions').value;
            document.getElementById('special_instructions_hidden').value = specialInstructions;
            
            // Ensure we have orders data
            const summaryBody = document.getElementById('summaryBody');
            if (summaryBody.children.length === 0) {
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
            
            // Check if we have a delivery address
            if (!deliveryAddress) {
                showToast('Please provide a delivery address for this order.', 'error');
                return false;
            }
            
            // Collect all item data into a JSON array
            const orders = [];
            const rows = summaryBody.querySelectorAll('tr');
            let totalAmount = 0;
            
            rows.forEach(row => {
                const category = row.querySelector('td:nth-child(1)').textContent;
                const item = row.querySelector('td:nth-child(2)').textContent;
                const packaging = row.querySelector('td:nth-child(3)').textContent;
                const price = parseFloat(row.querySelector('td:nth-child(4)').textContent.replace('PHP ', ''));
                const quantity = parseInt(row.querySelector('td:nth-child(5)').textContent);
                
                orders.push({
                    category: category,
                    item_description: item,
                    packaging: packaging,
                    price: price,
                    quantity: quantity
                });
                
                totalAmount += price * quantity;
            });
            
            document.getElementById('orders').value = JSON.stringify(orders);
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
            
            return true;
        }
        
        // Add confirmation modal functionality
        function confirmAddOrder() {
            // Prepare the order data first
            if (prepareOrderData()) {
                // Show the confirmation modal
                document.getElementById('addConfirmationModal').style.display = 'block';
            }
        }
        
        function closeAddConfirmation() {
            document.getElementById('addConfirmationModal').style.display = 'none';
        }
        
        function submitAddOrder() {
            document.getElementById('addConfirmationModal').style.display = 'none';
            
            // Submit the form via AJAX
            const formData = new FormData(document.getElementById('addOrderForm'));
            
            fetch('/backend/add_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Order added successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'An error occurred.', 'error');
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                showToast('A server error occurred.', 'error');
            });
        }
        
        // Inventory Management for Order Creation
       function openInventoryOverlay() {
    document.getElementById('inventoryOverlay').style.display = 'flex';
    
    // Show loading state
    const inventoryBody = document.querySelector('.inventory');
    inventoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory...</td></tr>';
    
    // Fetch inventory data
    fetch('/backend/get_inventory.php')
    .then(response => response.json())
    .then(data => {
        console.log("Raw inventory data:", data);
        
        if (Array.isArray(data)) {
            // Check if the data includes category field
            const hasCategory = data.length > 0 && data[0].hasOwnProperty('category');
            
            // Process the data
            const processedData = hasCategory ? data : data.map(item => {
                // Default to "Uncategorized" if no category exists
                return {...item, category: "Uncategorized"};
            });
            
            // Extract unique categories
            const categories = [...new Set(processedData
                .map(item => item.category || "Uncategorized")
                .filter(cat => cat !== null && cat !== ""))];
            
            // Call populate functions
            populateInventory(processedData);
            populateCategories(categories);
        } else {
            throw new Error("Invalid data format received");
        }
    })
    .catch(error => {
        console.error('Error fetching inventory:', error);
        showToast('Error fetching inventory: ' + error.message, 'error');
        
        const inventoryBody = document.querySelector('.inventory');
        inventoryBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${error.message}</td></tr>`;
    });
}

// The rest of the functions (populateInventory, populateCategories) remain the same

// Keep the rest of your functions mostly the same, but ensure they handle the data format properly
function populateInventory(inventory) {
    const inventoryBody = document.querySelector('.inventory');
    inventoryBody.innerHTML = '';
    
    if (!inventory || inventory.length === 0) {
        inventoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">No inventory items found</td></tr>';
        return;
    }
    
    inventory.forEach(item => {
        const row = document.createElement('tr');
        
        // Extract values, handling the different format
        const itemId = item.id || item.product_id || '';
        const category = item.category || 'Uncategorized';
        const itemDescription = item.item_description || '';
        const packaging = item.packaging || '';
        const price = parseFloat(item.price) || 0;
        
        row.innerHTML = `
            <td>${category}</td>
            <td>${itemDescription}</td>
            <td>${packaging}</td>
            <td>PHP ${price.toFixed(2)}</td>
            <td>
                <input type="number" class="inventory-quantity" min="1" max="100" value="1">
            </td>
            <td>
                <button class="add-to-cart-btn" onclick="addToCart(this, '${itemId}', '${category}', '${itemDescription.replace(/'/g, "\\'")}', '${packaging}', ${price})">
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
    
    // If no categories were provided, just return
    if (!categories || categories.length === 0) {
        return;
    }
    
    // Add categories to filter dropdown
    categories.forEach(category => {
        if (!category) return; // Skip empty categories
        
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        filterSelect.appendChild(option);
    });
    
    // Add event listener for filtering
    filterSelect.addEventListener('change', filterInventory);
}
        
        function filterInventory() {
            const category = document.getElementById('inventoryFilter').value;
            const searchText = document.getElementById('inventorySearch').value.toLowerCase();
            
            const rows = document.querySelectorAll('.inventory tr');
            
            rows.forEach(row => {
                const categoryCell = row.querySelector('td:first-child');
                
                if (!categoryCell) return; // Skip if row doesn't have cells (like headers)
                
                const rowCategory = categoryCell.textContent;
                const rowText = row.textContent.toLowerCase();
                
                const categoryMatch = category === 'all' || rowCategory === category;
                const searchMatch = !searchText || rowText.includes(searchText);
                
                row.style.display = categoryMatch && searchMatch ? '' : 'none';
            });
        }
        
        function closeInventoryOverlay() {
            document.getElementById('inventoryOverlay').style.display = 'none';
        }
        
        function addToCart(button, itemId, category, itemDescription, packaging, price) {
            // Get quantity from the input field in the same row
            const row = button.closest('tr');
            const quantityInput = row.querySelector('.inventory-quantity');
            const quantity = parseInt(quantityInput.value);
            
            if (isNaN(quantity) || quantity < 1) {
                showToast('Please enter a valid quantity', 'error');
                return;
            }
            
            // Check if item already exists in cart
            const existingItemIndex = cartItems.findIndex(item => 
                item.id === itemId && 
                item.packaging === packaging
            );
            
            if (existingItemIndex >= 0) {
                // Update quantity of existing item
                cartItems[existingItemIndex].quantity += quantity;
            } else {
                // Add new item to cart
                cartItems.push({
                    id: itemId,
                    category: category,
                    item_description: itemDescription,
                    packaging: packaging,
                    price: price,
                    quantity: quantity
                });
            }
            
            // Show success message
            showToast(`Added ${quantity} ${itemDescription} to cart`, 'success');
            
            // Reset quantity input
            quantityInput.value = 1;
            
            // Update the summary
            updateOrderSummary();
        }
        
        function updateOrderSummary() {
            const summaryBody = document.getElementById('summaryBody');
            summaryBody.innerHTML = '';
            
            let totalAmount = 0;
            
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
            
            // Update total amount
            document.querySelector('.summary-total-amount').textContent = `PHP ${totalAmount.toFixed(2)}`;
        }
        
        // Cart Modal Functions
        window.openCartModal = function() {
            document.getElementById('cartModal').style.display = 'flex';
            updateCartDisplay();
        }
        
        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }
        
        function updateCartDisplay() {
            const cartBody = document.querySelector('.cart');
            const noProductsMessage = document.querySelector('.no-products');
            const totalAmountElement = document.querySelector('.total-amount');
            
            cartBody.innerHTML = '';
            
            if (cartItems.length === 0) {
                noProductsMessage.style.display = 'block';
                totalAmountElement.textContent = 'PHP 0.00';
                return;
            }
            
            noProductsMessage.style.display = 'none';
            let totalAmount = 0;
            
            cartItems.forEach((item, index) => {
                const row = document.createElement('tr');
                const itemTotal = item.price * item.quantity;
                totalAmount += itemTotal;
                
                row.innerHTML = `
                    <td>${item.category}</td>
                    <td>${item.item_description}</td>
                    <td>${item.packaging}</td>
                    <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                    <td>
                        <input type="number" class="cart-quantity" min="1" max="100" value="${item.quantity}" 
                            data-index="${index}" onchange="updateCartItemQuantity(this)">
                        <button class="remove-item-btn" onclick="removeCartItem(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                cartBody.appendChild(row);
            });
            
            // Update total amount
            totalAmountElement.textContent = `PHP ${totalAmount.toFixed(2)}`;
        }
        
        function updateCartItemQuantity(input) {
            const index = parseInt(input.getAttribute('data-index'));
            const newQuantity = parseInt(input.value);
            
            if (isNaN(newQuantity) || newQuantity < 1) {
                showToast('Please enter a valid quantity', 'error');
                input.value = cartItems[index].quantity;
                return;
            }
            
            cartItems[index].quantity = newQuantity;
            updateCartDisplay();
        }
        
        function removeCartItem(index) {
            cartItems.splice(index, 1);
            updateCartDisplay();
        }
        
        function saveCartChanges() {
            updateOrderSummary();
            closeCartModal();
        }

        // Document ready function
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                
                $(".orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });
            
            // Handle search button click (same functionality as typing)
            $(".search-btn").on("click", function() {
                let searchText = $("#searchInput").val().toLowerCase().trim();
                
                $(".orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });
            
            // Add event listener for search input in inventory
            const searchInput = document.getElementById('inventorySearch');
            if (searchInput) {
                searchInput.addEventListener('input', filterInventory);
            }
            
            // Close special instructions modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('specialInstructionsModal');
                if (event.target === modal) {
                    closeSpecialInstructions();
                }
            });
            
            // Close confirmation modals when clicking outside
            window.addEventListener('click', function(event) {
                const confirmationModals = [
                    'addConfirmationModal',
                    'driverConfirmationModal',
                    'saveProgressConfirmationModal',
                    'statusConfirmationModal',
                    'downloadConfirmationModal'
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