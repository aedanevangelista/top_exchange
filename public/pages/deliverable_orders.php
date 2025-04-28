<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure user has access to Orders

// Get current date for auto-transit
$current_date = date('Y-m-d');

// Automatically update orders to In Transit if today is delivery day
$auto_transit_sql = "UPDATE orders SET status = 'In Transit' 
                     WHERE status = 'For Delivery' 
                     AND delivery_date = ? 
                     AND status != 'In Transit'";
$auto_transit_stmt = $conn->prepare($auto_transit_sql);
if ($auto_transit_stmt) {
    $auto_transit_stmt->bind_param("s", $current_date);
    $auto_transit_stmt->execute();
    $auto_transit_count = $auto_transit_stmt->affected_rows;
    $auto_transit_stmt->close();
}

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

// Get status filter from query string
$filterStatus = $_GET['status'] ?? '';

// Handle sorting
$sortColumn = $_GET['sort'] ?? 'delivery_date';
$sortOrder = $_GET['order'] ?? 'ASC';

// Validate sort parameters
$allowedColumns = ['po_number', 'username', 'order_date', 'delivery_date', 'total_amount', 'status'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'delivery_date';
}

$allowedOrders = ['ASC', 'DESC'];
if (!in_array($sortOrder, $allowedOrders)) {
    $sortOrder = 'ASC';
}

// Build the SQL query with or without status filter
$whereClause = "WHERE o.status IN ('For Delivery', 'In Transit')";
if (!empty($filterStatus)) {
    $whereClause = "WHERE o.status = '$filterStatus'";
}

$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, 
        o.orders, o.total_amount, o.status, o.driver_assigned, o.special_instructions, o.company,
        IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name 
        FROM orders o 
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number 
        LEFT JOIN drivers d ON da.driver_id = d.id 
        $whereClause
        ORDER BY o.$sortColumn $sortOrder";

$orders = [];
$orderStmt = $conn->prepare($sql);
if ($orderStmt) {
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    if ($orderResult && $orderResult->num_rows > 0) {
        while ($row = $orderResult->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $orderStmt->close();
} else {
    // For debugging - log the SQL error
    error_log("SQL Error in deliverable_orders.php: " . $conn->error);
}

// Get filter options for status
$statusOptions = ['For Delivery', 'In Transit'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverable Orders</title>
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
            margin-right: 15px;
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
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            text-align: center;
        }

        .status-for-delivery {
            background-color: #fd7e14;
            color: white;
        }
        
        .status-in-transit {
            background-color: #17a2b8;
            color: white;
        }

        /* Driver styles */
        .driver-badge {
            background-color: #17a2b8;
            color: white;
            padding: 5px 8px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .driver-btn {
            background-color: #6610f2;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s;
            margin-top: 5px;
            display: inline-block;
        }

        .driver-btn:hover {
            background-color: #510bc4;
        }

        .complete-delivery-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 5px;
        }

        .complete-delivery-btn:hover {
            background-color: #218838;
        }
        
        .toggle-transit-btn {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            transition: background-color 0.3s;
            margin-bottom: 5px;
        }
        
        .toggle-transit-btn:hover {
            background-color: #138496;
        }

        /* Improve action buttons layout */
        .action-buttons-cell {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 210px;
        }
        
        /* Highlighted delivery date */
        .today-delivery {
            background-color: #fff3cd;
            font-weight: bold;
        }

        .today-tag {
            font-weight: normal;
            font-style: italic;
        }
        
        /* Order details modal */
        .order-details-container {
            max-height: 70vh;
            overflow-y: auto;
            margin-bottom: 10px;
            padding-right: 5px;
        }
        
        /* No orders message */
        .no-orders {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #6c757d;
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
        
        /* Modal styling */
        .overlay-content {
            max-width: 550px;
            padding: 25px;
            border-radius: 8px;
        }
        
        .modal-content h2 {
            color: #333;
            text-align: center;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .modal-message {
            margin: 25px 0;
            font-size: 16px;
            line-height: 1.6;
            text-align: center;
        }
        
        /* Modal buttons */
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 25px;
        }
        
        .btn-no, .btn-yes {
            padding: 10px 25px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 120px;
        }
        
        .btn-no {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-no:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-yes {
            background-color: #28a745;
            color: white;
        }
        
        .btn-yes:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* Status pill in modal */
        .status-pill {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 25px;
            font-weight: bold;
            margin: 0 3px;
            color: white;
        }
        
        .status-pill.for-delivery {
            background-color: #fd7e14;
        }
        
        .status-pill.in-transit {
            background-color: #17a2b8;
        }

        /* Date info styles */
        .date-info {
            margin-left: 15px;
            padding: 5px 10px;
            background-color: #e9f2fa;
            border-radius: 4px;
            color: #2980b9;
            font-size: 14px;
            display: inline-block;
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
            margin-bottom: 5px;
            display: inline-block;
            text-align: center;
        }
        
        .download-btn:hover {
            background-color: #138496;
        }
        
        .download-btn i {
            margin-right: 5px;
        }
        
        /* View orders button styling */
        .view-orders-btn {
            padding: 6px 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-align: center;
        }
        
        .view-orders-btn:hover {
            background-color: #2980b9;
        }
        
        .view-orders-btn i {
            margin-right: 5px;
        }
        
        /* Instructions button styling */
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
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
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

        /* Special Instructions Modal */
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
            max-height: 90vh;
            overflow-y: auto;
            margin: 2vh auto;
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
        
        /* Fix tables on mobile devices */
        @media screen and (max-width: 768px) {
            .orders-table-container {
                overflow-x: auto;
            }
            
            .order-details-container {
                overflow-x: auto;
            }
            
            .action-buttons-cell {
                min-width: 210px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <div class="header-content">
                <h1 class="page-title">Deliverable Orders</h1>
                
                <!-- Filter section in the middle -->
                <div class="filter-section">
                    <div class="filter-group">
                        <span class="filter-label">Status:</span>
                        <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                            <option value="">All</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>>
                                    <?= $status ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="date-info">
                        <i class="fas fa-calendar-day"></i> Today: <?= date('Y-m-d') ?>
                        <?php if (isset($auto_transit_count) && $auto_transit_count > 0): ?>
                            (<?= $auto_transit_count ?> orders auto-updated)
                        <?php endif; ?>
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
                        <th class="sort-header <?= $sortColumn == 'order_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="order_date">Order Date</th>
                        <th class="sort-header <?= $sortColumn == 'delivery_date' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="delivery_date">Delivery Date</th>
                        <th>Delivery Address</th>
                        <th>Orders</th>
                        <th class="sort-header <?= $sortColumn == 'total_amount' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="total_amount">Total Amount</th>
                        <th>Special Instructions</th>
                        <th>Driver</th>
                        <th class="sort-header <?= $sortColumn == 'status' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="status">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): 
                            $isDeliveryDay = ($order['delivery_date'] === $current_date);
                        ?>
                            <tr class="order-row" data-status="<?= htmlspecialchars($order['status']) ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td class="<?= $isDeliveryDay ? 'today-delivery' : '' ?>">
                                    <?= htmlspecialchars($order['delivery_date']) ?>
                                    <?= $isDeliveryDay ? ' <span class="today-tag">(Today)</span>' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>    
                                        View Order Items
                                    </button>
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
                                    <?php if ($order['driver_assigned'] && !empty($order['driver_name'])): ?>
                                        <div class="driver-badge">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                        </div>
                                        <button class="driver-btn" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>', <?= $order['driver_id'] ?>, '<?= htmlspecialchars($order['driver_name']) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change
                                        </button>
                                    <?php else: ?>
                                        <span class="no-driver">No driver assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <span class="status-badge status-for-delivery">For Delivery</span>
                                    <?php else: ?>
                                        <span class="status-badge status-in-transit">In Transit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons-cell">
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
                                    
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <button class="toggle-transit-btn" onclick="openStatusChangeModal('<?= htmlspecialchars($order['po_number']) ?>', 'In Transit')">
                                            <i class="fas fa-truck"></i> Mark In Transit
                                        </button>
                                    <?php else: ?>
                                        <button class="toggle-transit-btn" onclick="openStatusChangeModal('<?= htmlspecialchars($order['po_number']) ?>', 'For Delivery')">
                                            <i class="fas fa-warehouse"></i> Mark For Delivery
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="complete-delivery-btn" onclick="openCompleteModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="no-orders">No orders ready for delivery.</td>
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

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="overlay" style="display: none;">
        <div class="overlay-content driver-modal-content">
            <h2><i class="fas fa-user"></i> <span id="driverModalTitle">Change Driver</span></h2>
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
                    <i class="fas fa-times"></i> No
                </button>
                <button class="save-btn" onclick="assignDriver()">
                    <i class="fas fa-check"></i> Yes
                </button>
            </div>
        </div>
    </div>
    
    <!-- Status Change Confirmation Modal -->
    <div id="statusChangeModal" class="overlay" style="display: none;">
        <div class="overlay-content modal-content">
            <h2><i id="statusIcon" class="fas fa-truck"></i> <span id="statusModalTitle">Change Status</span></h2>
            <div class="modal-message" id="statusModalMessage">
                Are you sure you want to change the status of this order?
            </div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeStatusChangeModal()">
                    <i class="fas fa-times"></i> No
                </button>
                <button id="confirmStatusChange" class="btn-yes">
                    <i class="fas fa-check"></i> Yes
                </button>
            </div>
        </div>
    </div>
    
    <!-- Complete Order Confirmation Modal -->
    <div id="completeOrderModal" class="overlay" style="display: none;">
        <div class="overlay-content modal-content">
            <h2><i class="fas fa-check-circle"></i> Complete Delivery</h2>
            <div class="modal-message" id="completeModalMessage">
                Are you sure you want to mark this delivery as completed?
            </div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeCompleteModal()">
                    <i class="fas fa-times"></i> No
                </button>
                <button id="confirmCompleteOrder" class="btn-yes">
                    <i class="fas fa-check"></i> Yes
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentPoNumber = '';
        let currentDriverId = 0;
        let currentStatusChange = '';
        let currentPOData = null; // For PDF generation
        
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const currentSort = '<?= $sortColumn ?>';
            const currentOrder = '<?= $sortOrder ?>';
            
            let url = '?';
            const params = [];
            
            if (status) {
                params.push(`status=${encodeURIComponent(status)}`);
            }
            
            if (currentSort && currentOrder) {
                params.push(`sort=${currentSort}&order=${currentOrder}`);
            }
            
            url += params.join('&');
            window.location.href = url;
        }
        
        function openStatusChangeModal(poNumber, newStatus) {
            currentPoNumber = poNumber;
            currentStatusChange = newStatus;
            
            // Set modal title and message based on the status change
            const modalTitle = document.getElementById('statusModalTitle');
            const modalMessage = document.getElementById('statusModalMessage');
            const statusIcon = document.getElementById('statusIcon');
            const confirmBtn = document.getElementById('confirmStatusChange');
            
            if (newStatus === 'In Transit') {
                modalTitle.textContent = 'Mark as In Transit';
                modalMessage.innerHTML = `Are you sure you want to mark order <strong>${poNumber}</strong> as <span class="status-pill in-transit">In Transit</span>?`;
                statusIcon.className = 'fas fa-truck';
            } else {
                modalTitle.textContent = 'Mark as For Delivery';
                modalMessage.innerHTML = `Are you sure you want to mark order <strong>${poNumber}</strong> as <span class="status-pill for-delivery">For Delivery</span>?`;
                statusIcon.className = 'fas fa-warehouse';
            }
            
            // Set up confirmation button
            confirmBtn.onclick = function() {
                toggleTransitStatus(poNumber, newStatus);
            };
            
            // Show modal
            document.getElementById('statusChangeModal').style.display = 'flex';
        }
        
        function closeStatusChangeModal() {
            document.getElementById('statusChangeModal').style.display = 'none';
        }
        
        function openCompleteModal(poNumber, username) {
            currentPoNumber = poNumber;
            
            // Set modal message
            const modalMessage = document.getElementById('completeModalMessage');
            modalMessage.innerHTML = `Are you sure you want to mark order <strong>${poNumber}</strong> for <strong>${username}</strong> as completed?`;
            
            // Set up confirmation button
            document.getElementById('confirmCompleteOrder').onclick = function() {
                completeDelivery(poNumber);
            };
            
            // Show modal
            document.getElementById('completeOrderModal').style.display = 'flex';
        }
        
        function closeCompleteModal() {
            document.getElementById('completeOrderModal').style.display = 'none';
        }
        
        function toggleTransitStatus(poNumber, newStatus) {
            // Close modal
            closeStatusChangeModal();
            
            // Show loading toast
            showToast(`Updating order status...`, 'info');
            
            fetch('/backend/toggle_transit_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    po_number: poNumber,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (newStatus === 'In Transit') {
                        showToast('Order marked as In Transit', 'success');
                    } else {
                        showToast('Order marked as For Delivery', 'success');
                    }
                    // Reload the page after a short delay
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error: Failed to communicate with server', 'error');
            });
        }

        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            
            // Show loading indicator
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading order details...</td></tr>';
            document.getElementById('orderDetailsModal').style.display = 'flex';
            
            // Fetch the order items
            fetch(`/backend/get_order_items.php?po_number=${poNumber}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Clear the loading message
                orderDetailsBody.innerHTML = '';
                
                if (data.success && data.orderItems && Array.isArray(data.orderItems)) {
                    const orderItems = data.orderItems;
                    
                    let totalAmount = 0;
                    
                    // Check if there are any items
                    if (orderItems.length === 0) {
                        orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;font-style:italic;">No items found in this order.</td></tr>';
                        return;
                    }
                    
                    orderItems.forEach(item => {
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
                    
                } else {
                    // Handle when the API returns success: false or missing orderItems array
                    let errorMessage = data.message || 'Could not retrieve order details';
                    orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${errorMessage}</td></tr>`;
                    
                    showToast('Error: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: Could not load order details. Please try again.</td></tr>`;
                showToast('Error: ' + error.message, 'error');
            });
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function openDriverModal(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId;
            
            document.getElementById('driverModalTitle').textContent = 'Change Driver';
            document.getElementById('driverModalMessage').textContent = `Current driver: ${driverName}`;
            
            // Set the current driver in the dropdown
            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = driverId;
            
            // Show the modal
            document.getElementById('driverModal').style.display = 'flex';
        }

        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
        }

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
                    showToast('Driver updated successfully', 'success');
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

        function completeDelivery(poNumber) {
            // Close the confirm modal
            closeCompleteModal();
            
            // Show loading toast
            showToast('Processing completion...', 'info');
            
            fetch('/backend/complete_delivery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ po_number: poNumber })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Delivery completed successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error: Failed to communicate with server', 'error');
            });
        }

        // PDF Functions
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
                alert('Error preparing PDF data');
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
                alert('Error preparing PDF data');
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
                let url = `?sort=${column}&order=${order}`;
                
                if (statusFilter) {
                    url += `&status=${encodeURIComponent(statusFilter)}`;
                }
                
                window.location.href = url;
            });
        });

        // Search functionality
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                
                $(".order-row").each(function() {
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
                
                $(".order-row").each(function() {
                    let row = $(this);
                    let text = row.text().toLowerCase();
                    
                    if (text.includes(searchText)) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
            });

            // Handle clicks outside modals
            $(document).on('click', '.overlay', function(event) {
                if (event.target === this) {
                    if (this.id === 'orderDetailsModal') closeOrderDetailsModal();
                    else if (this.id === 'driverModal') closeDriverModal();
                    else if (this.id === 'statusChangeModal') closeStatusChangeModal();
                    else if (this.id === 'completeOrderModal') closeCompleteModal();
                }
            });

            // Close special instructions modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('specialInstructionsModal');
                if (event.target === modal) {
                    closeSpecialInstructions();
                }
            });
            
            // Make tables scroll horizontally on small screens
            if (window.innerWidth < 768) {
                document.querySelector('.orders-table-container').style.overflowX = 'auto';
                document.querySelector('.order-details-container').style.overflowX = 'auto';
            }
        });
    </script>
</body>
</html>