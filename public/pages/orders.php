<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure user has access to Orders

// Fetch all drivers for the driver assignment dropdown
$drivers = [];
$driverStmt = $conn->prepare("SELECT id, name FROM drivers WHERE availability = 'Available' AND current_deliveries < 20 ORDER BY name");
if ($driverStmt) {
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    while ($row = $driverResult->fetch_assoc()) {
        $drivers[] = $row;
    }
    $driverStmt->close();
}

// Get the current date for filtering
$today = date('Y-m-d');

// Get status filter from query string (default to 'Active')
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'Active';

// Handle sorting
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort parameters
$allowedColumns = ['po_number', 'username', 'order_date', 'delivery_date', 'total_amount', 'status'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'order_date';
}

$allowedOrders = ['ASC', 'DESC'];
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'DESC';
}

// Build the SQL query with status filter - REMOVED payment_method from the SELECT statement
$sql = "SELECT o.po_number, o.username, o.company, o.order_date, o.delivery_date, o.delivery_address, 
        o.orders, o.total_amount, o.status, o.driver_assigned, 
        o.special_instructions, IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name 
        FROM orders o 
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number 
        LEFT JOIN drivers d ON da.driver_id = d.id 
        WHERE o.status = ?
        ORDER BY o.$sortColumn $sortOrder";

$orders = [];
$orderStmt = $conn->prepare($sql);
if ($orderStmt) {
    $orderStmt->bind_param("s", $filterStatus);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    if ($orderResult && $orderResult->num_rows > 0) {
        while ($row = $orderResult->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $orderStmt->close();
} else {
    // For debugging
    error_log("SQL Error in orders.php: " . $conn->error);
}

// Get filter options for status
$statusQuery = "SELECT DISTINCT status FROM orders ORDER BY status";
$statusResult = $conn->query($statusQuery);
$statusOptions = [];
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $statusOptions[] = $row['status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Search container styles */
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

        /* Orders header styles */
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .filter-section {
            display: flex;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
        }
        
        .filter-label {
            margin-right: 8px;
            font-weight: bold;
        }
        
        .filter-select {
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            min-width: 150px;
        }

        /* Status badge styles */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background-color: #28a745;
            color: white;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #333;
        }
        
        .status-completed {
            background-color: #6c757d;
            color: white;
        }
        
        .status-rejected {
            background-color: #dc3545;
            color: white;
        }
        
        .status-for-delivery {
            background-color: #fd7e14;
            color: white;
        }
        
        .status-in-transit {
            background-color: #17a2b8;
            color: white;
        }

        /* Driver badge and button styles */
        .driver-badge {
            background-color: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .driver-btn {
            background-color: #6610f2;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
            transition: background-color 0.3s;
        }

        .driver-btn:hover {
            background-color: #510bc4;
        }

        .assign-driver-btn {
            background-color: #fd7e14;
        }

        .assign-driver-btn:hover {
            background-color: #e67211;
        }

        /* Sort indicators and clickable headers */
        .sort-header {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
            transition: background-color 0.2s;
        }
        
        .sort-header:hover {
            background-color:rgb(51, 51, 51);
        }
        
        .sort-header::after {
            content: '\f0dc';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 5px;
            color: #6c757d;
        }
        
        .sort-header.asc::after {
            content: '\f0de';
            color:rgb(255, 255, 255);
        }
        
        .sort-header.desc::after {
            content: '\f0dd';
            color:rgb(255, 255, 255);
        }

        /* Download PDF button */
        .download-btn {
            padding: 6px 12px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .download-btn:hover {
            background-color: #138496;
        }
        
        .download-btn i {
            margin-right: 5px;
        }
        
        /* Special Instructions styles */
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
        
        /* PDF styles for print */
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
        
        .download-pdf-btn {
            padding: 10px 20px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        /* Driver Assignment Modal */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .overlay-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 550px;
            width: 90%;
            position: relative;
        }
        
        .driver-modal-content {
            max-width: 450px;
        }
        
        .driver-selection {
            margin: 20px 0;
        }
        
        .driver-selection label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .driver-selection select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #fff;
            font-size: 16px;
        }
        
        .driver-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 25px;
        }
        
        .cancel-btn, .save-btn {
            padding: 10px 25px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 120px;
        }
        
        .cancel-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .save-btn {
            background-color: #28a745;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* For mobile devices */
        @media (max-width: 768px) {
            .orders-table-container {
                overflow-x: auto;
            }
            
            .orders-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filter-section {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .search-container input {
                width: 100%;
            }
            
            .po-details {
                flex-direction: column;
            }
            
            .po-left, .po-right {
                width: 100%;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <div class="header-content">
                <h1 class="page-title">Order Management</h1>
                
                <div class="filter-section">
                    <div class="filter-group">
                        <span class="filter-label">Status:</span>
                        <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div>
        
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th class="sort-header <?= $sortColumn == 'po_number' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="po_number">PO Number</th>
                        <th class="sort-header <?= $sortColumn == 'username' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="username">Username</th>
                        <th>Company</th>
                        <th class="sort-header <?= $sortColumn == 'order_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="order_date">Order Date</th>
                        <th class="sort-header <?= $sortColumn == 'delivery_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="delivery_date">Delivery Date</th>
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <!-- Removed Payment Method column -->
                        <th>Special Instructions</th>
                        <th class="sort-header <?= $sortColumn == 'total_amount' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="total_amount">Total Amount</th>
                        <th>Driver</th>
                        <th class="sort-header <?= $sortColumn == 'status' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="status">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['company'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>    
                                        View Order Items
                                    </button>
                                </td>
                                <!-- Removed Payment Method column -->
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if ($order['status'] === 'For Delivery' || $order['status'] === 'In Transit'): ?>
                                        <?php if ($order['driver_assigned'] && !empty($order['driver_name'])): ?>
                                            <div class="driver-badge">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                            </div>
                                            <button class="driver-btn" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>', <?= $order['driver_id'] ?>, '<?= htmlspecialchars($order['driver_name']) ?>')">
                                                <i class="fas fa-exchange-alt"></i> Change
                                            </button>
                                        <?php else: ?>
                                            <button class="driver-btn assign-driver-btn" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>')">
                                                <i class="fas fa-user-plus"></i> Assign
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="download-btn" onclick="downloadPODirectly(
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
                            <td colspan="12" style="text-align: center; padding: 20px;">No orders found with status: <?= htmlspecialchars($filterStatus) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Order Details Modal -->
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
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                    <i class="fas fa-arrow-left"></i> Close
                </button>
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

    <!-- PDF Preview Modal -->
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
                    
                    <div class="po-signature">
                        <div class="po-signature-block">
                            <div class="po-signature-line"></div>
                            <div>Client Signature</div>
                        </div>
                        <div class="po-signature-block">
                            <div class="po-signature-line"></div>
                            <div>Authorized Signature</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
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
                <button class="save-btn" onclick="assignDriver()">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPoNumber = '';
        let currentPOData = null;
        let currentDriverId = 0;
        
        // Order filtering functions
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const currentSort = '<?= $sortColumn ?>';
            const currentOrder = '<?= $sortOrder ?>';
            
            let url = '?status=' + encodeURIComponent(status);
            
            if (currentSort && currentOrder) {
                url += '&sort=' + currentSort + '&order=' + currentOrder;
            }
            
            window.location.href = url;
        }
        
        // Function to view order details
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            
            // Show loading indicator
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading order details...</td></tr>';
            document.getElementById('orderDetailsModal').style.display = 'flex';
            
            // Add console log to see what's being sent
            console.log("Fetching order details for PO: " + poNumber);
            
            // Fetch the order items
            fetch(`/backend/get_order_items.php?po_number=${encodeURIComponent(poNumber)}`)
            .then(response => {
                console.log("Response status:", response.status);
                return response.json().catch(err => {
                    console.error("JSON parse error:", err);
                    throw new Error("Failed to parse server response");
                });
            })
            .then(data => {
                // Log the response for debugging
                console.log("Response data:", data);
                
                // Clear the loading message
                orderDetailsBody.innerHTML = '';
                
                if (data && data.success && data.orderItems) {
                    const orderItems = data.orderItems;
                    
                    try {
                        // Parse orders if it's a string (JSON)
                        let parsedItems = orderItems;
                        if (typeof orderItems === 'string') {
                            parsedItems = JSON.parse(orderItems);
                        }
                        
                        // Ensure parsedItems is an array
                        if (!Array.isArray(parsedItems)) {
                            throw new Error("Order items is not an array");
                        }
                        
                        // Check if there are any items
                        if (parsedItems.length === 0) {
                            orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;font-style:italic;">No items found in this order.</td></tr>';
                            return;
                        }
                        
                        let totalAmount = 0;
                        
                        parsedItems.forEach(item => {
                            if (!item) return; // Skip if item is null or undefined
                            
                            const quantity = parseInt(item.quantity) || 0;
                            const price = parseFloat(item.price) || 0;
                            const itemTotal = quantity * price;
                            totalAmount += itemTotal;
                            
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.category || ''}</td>
                                <td>${item.item_description || ''}</td>
                                <td>${item.packaging || ''}</td>
                                <td>PHP ${price.toFixed(2)}</td>
                                <td>${quantity}</td>
                                <td>PHP ${itemTotal.toFixed(2)}</td>
                            `;
                            orderDetailsBody.appendChild(row);
                        });
                        
                        // Add a total row
                        const totalRow = document.createElement('tr');
                        totalRow.style.fontWeight = 'bold';
                        totalRow.innerHTML = `
                            <td colspan="5" style="text-align: right;">Total:</td>
                            <td>PHP ${totalAmount.toFixed(2)}</td>
                        `;
                        orderDetailsBody.appendChild(totalRow);
                    } catch (parseError) {
                        console.error("Error processing order items:", parseError);
                        orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error processing order data: ${parseError.message}</td></tr>`;
                        showToast('Error processing order data: ' + parseError.message, 'error');
                    }
                } else {
                    // Handle when the API returns success: false or missing orderItems array
                    let errorMessage = (data && data.message) ? data.message : 'Could not retrieve order details';
                    orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${errorMessage}</td></tr>`;
                    
                    showToast('Error: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${error.message}</td></tr>`;
                showToast('Error: ' + error.message, 'error');
            });
        }
        
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        // Function to open driver assignment modal
        function openDriverModal(poNumber, driverId = 0, driverName = '') {
            // Store the PO number in a variable for later use
            currentPoNumber = poNumber;
            currentDriverId = driverId;
            
            // Set the modal title
            if (driverId > 0) {
                document.getElementById('driverModalTitle').textContent = 'Change Driver';
                document.getElementById('driverModalMessage').textContent = `Current driver: ${driverName}`;
            } else {
                document.getElementById('driverModalTitle').textContent = 'Assign Driver';
                document.getElementById('driverModalMessage').textContent = 'No driver currently assigned';
            }
            
            // Set the current driver in the dropdown if one is assigned
            const driverSelect = document.getElementById('driverSelect');
            if (driverSelect) {
                driverSelect.value = driverId;
            }
            
            // Show the modal
            const driverModal = document.getElementById('driverModal');
            if (driverModal) {
                driverModal.style.display = 'flex';
            }
        }

        // Function to close the driver modal
        function closeDriverModal() {
            const driverModal = document.getElementById('driverModal');
            if (driverModal) {
                driverModal.style.display = 'none';
            }
        }

        // Function to handle driver assignment
        function assignDriver() {
            const driverId = document.getElementById('driverSelect').value;
            
            if (driverId == 0) {
                showToast('Please select a driver', 'error');
                return;
            }
            
            // Show loading state
            const saveBtn = document.querySelector('#driverModal .save-btn');
            const originalBtnText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            saveBtn.disabled = true;

            // Send request to assign driver
            fetch('/backend/assign_driver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    driver_id: driverId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Driver assigned successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                    saveBtn.innerHTML = originalBtnText;
                    saveBtn.disabled = false;
                }
                closeDriverModal();
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error: Failed to communicate with server', 'error');
                saveBtn.innerHTML = originalBtnText;
                saveBtn.disabled = false;
                closeDriverModal();
            });
        }
        
        // Special instructions functions
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
        
        // PDF Generation functions
        function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            try {
                // Store current PO data
                currentPOData = {
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
                
                // Populate the hidden PDF content silently
                document.getElementById('printCompany').textContent = company || 'No Company Name';
                document.getElementById('printPoNumber').textContent = poNumber;
                document.getElementById('printUsername').textContent = username;
                document.getElementById('printDeliveryAddress').textContent = deliveryAddress;
                document.getElementById('printOrderDate').textContent = orderDate;
                document.getElementById('printDeliveryDate').textContent = deliveryDate;
                
                // Format the total amount
                document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Handle special instructions
                const instructionsSection = document.getElementById('printInstructionsSection');
                if (specialInstructions && specialInstructions.trim() !== '') {
                    document.getElementById('printSpecialInstructions').textContent = specialInstructions;
                    instructionsSection.style.display = 'block';
                } else {
                    instructionsSection.style.display = 'none';
                }
                
                // Parse and populate order items
                const orderItems = JSON.parse(ordersJson);
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
                    filename:     `PO_${poNumber}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                // Generate and download PDF directly
                html2pdf().set(opt).from(element).save().then(() => {
                    showToast(`Purchase Order ${poNumber} has been downloaded.`, 'success');
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    alert('Error generating PDF. Please try again.');
                });
                
            } catch (e) {
                console.error('Error preparing PDF data:', e);
                alert('Error preparing PDF data: ' + e.message);
            }
        }
        
        // Function to show PDF preview
        function generatePO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            try {
                // Store current PO data for later use
                currentPOData = {
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
                
                // Set basic information
                document.getElementById('printCompany').textContent = company || 'No Company Name';
                document.getElementById('printPoNumber').textContent = poNumber;
                document.getElementById('printUsername').textContent = username;
                document.getElementById('printDeliveryAddress').textContent = deliveryAddress;
                document.getElementById('printOrderDate').textContent = orderDate;
                document.getElementById('printDeliveryDate').textContent = deliveryDate;
                
                // Handle special instructions
                const instructionsSection = document.getElementById('printInstructionsSection');
                if (specialInstructions && specialInstructions.trim() !== '') {
                    document.getElementById('printSpecialInstructions').textContent = specialInstructions;
                    instructionsSection.style.display = 'block';
                } else {
                    instructionsSection.style.display = 'none';
                }
                
                // Format the total amount with commas and decimals
                document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Parse and populate order items
                const orderItems = JSON.parse(ordersJson);
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
                
                // Show the PDF preview
                document.getElementById('pdfPreview').style.display = 'block';
                
            } catch (e) {
                console.error('Error preparing PDF data:', e);
                alert('Error preparing PDF data: ' + e.message);
            }
        }
        
        // Function to close PDF preview
        function closePDFPreview() {
            document.getElementById('pdfPreview').style.display = 'none';
        }
        
        // Function to download the PDF
        function downloadPDF() {
            if (!currentPOData) {
                alert('No PO data available for download.');
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
                alert('Error generating PDF. Please try again.');
            });
        }
        
        // Function to display toast messages
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                    <div class="message">${message}</div>
                </div>
                <i class="fas fa-times close" onclick="this.parentElement.remove()"></i>
            `;
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => { toast.remove(); }, 5000);
        }

        // Sorting functionality
        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                let order = 'ASC';
                
                // If already sorted by this column, toggle order
                if (this.classList.contains('asc')) {
                    order = 'DESC';
                }
                
                // Preserve any filter when sorting
                const statusFilter = document.getElementById('statusFilter').value;
                let url = `?status=${encodeURIComponent(statusFilter)}&sort=${column}&order=${order}`;
                
                window.location.href = url;
            });
        });

        // Search functionality
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                
                $("table.orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });
            
            // Handle search button click
            $(".search-btn").on("click", function() {
                let searchText = $("#searchInput").val().toLowerCase().trim();
                
                $("table.orders-table tbody tr").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });

            // Close special instructions modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('specialInstructionsModal');
                if (event.target === modal) {
                    closeSpecialInstructions();
                }
            });
            
            // Close modals with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeOrderDetailsModal();
                    closeSpecialInstructions();
                    closePDFPreview();
                    closeDriverModal();
                }
            });
            
            // Handle clicks outside order details modal
            document.getElementById('orderDetailsModal')?.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeOrderDetailsModal();
                }
            });
            
            // Handle clicks outside driver modal
            document.getElementById('driverModal')?.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeDriverModal();
                }
            });
        });
    </script>
</body>
</html>