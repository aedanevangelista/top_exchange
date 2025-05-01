<?php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-05-01 17:58:40
// Current User's Login: aedanevangelista
// Applying requested confirmation modals and toast CSS fix ONLY.

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
} else {
    // Log error if prepare failed
    error_log("Auto-transit prepare failed: " . $conn->error);
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
} else {
     // Log error if prepare failed
     error_log("Fetching drivers prepare failed: " . $conn->error);
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
// **Using strtoupper for case-insensitive check**
if (!in_array(strtoupper($sortOrder), $allowedOrders)) {
    $sortOrder = 'ASC';
}

// Build the SQL query with or without status filter
// **Using prepared statement for filterStatus to prevent SQL injection**
$params = [];
$types = "";
$whereClause = "WHERE o.status IN ('For Delivery', 'In Transit')";
if (!empty($filterStatus)) {
    // Validate filterStatus against allowed values
    if (in_array($filterStatus, ['For Delivery', 'In Transit'])) {
        $whereClause = "WHERE o.status = ?";
        $params[] = $filterStatus;
        $types .= "s";
    } else {
        // Invalid status provided, ignore filter
        $filterStatus = ''; // Clear invalid filter
    }
}


$sql = "SELECT o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address,
        o.orders, o.total_amount, o.status, o.driver_assigned, o.special_instructions, o.company,
        IFNULL(da.driver_id, 0) as driver_id, IFNULL(d.name, '') as driver_name
        FROM orders o
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number
        LEFT JOIN drivers d ON da.driver_id = d.id
        $whereClause
        ORDER BY `$sortColumn` $sortOrder"; // Use backticks for column name safety

$orders = [];
$orderStmt = $conn->prepare($sql);
if ($orderStmt) {
    // Bind parameters if filtering by status
    if (!empty($params)) {
        $orderStmt->bind_param($types, ...$params);
    }
    if($orderStmt->execute()){
        $orderResult = $orderStmt->get_result();
        if ($orderResult && $orderResult->num_rows > 0) {
            while ($row = $orderResult->fetch_assoc()) {
                $orders[] = $row;
            }
        }
    } else {
        error_log("Fetching orders execute failed: " . $orderStmt->error);
    }
    $orderStmt->close();
} else {
    // For debugging - log the SQL error
    error_log("SQL Prepare Error in deliverable_orders.php: " . $conn->error);
}

// Get filter options for status
$statusOptions = ['For Delivery', 'In Transit'];

// **Close connection at the end of PHP block**
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverable Orders</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css"> <!-- Original toast CSS link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* --- Start of Original Styles --- */
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
            min-width: 210px; /* Keep original width */
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
            background-color:rgb(51, 51, 51); /* Original hover color */
        }

        .sort-header::after {
            content: '\f0dc';
            font-family: 'Font Awesome 6 Free'; /* Keep using FA 6 */
            font-weight: 900;
            position: absolute;
            right: 5px;
            color: #6c757d;
        }

        .sort-header.asc::after {
            content: '\f0de';
            color:rgb(255, 255, 255); /* Original color */
        }

        .sort-header.desc::after {
            content: '\f0dd';
            color:rgb(255, 255, 255); /* Original color */
        }

        /* Modal styling */
        /* Keeping original overlay content style */
        .overlay { /* Added generic overlay style for background/positioning */
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1050;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .overlay-content {
            max-width: 550px;
            padding: 25px;
            border-radius: 8px;
            background-color: #fff; /* Ensure background */
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); /* Add shadow */
            max-height: 90vh; /* Limit height */
            overflow-y: auto; /* Add scroll */
        }

        /* Keeping original modal content h2 style */
        .modal-content h2 { /* Assuming this class is used in confirmation modals */
            color: #333;
            text-align: center;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        /* Keeping original modal message style */
        .modal-message {
            margin: 25px 0;
            font-size: 16px;
            line-height: 1.6;
            text-align: center;
        }

        /* Keeping original modal buttons style */
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 25px;
        }

        /* Keeping original no/yes button styles */
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

        /* Keeping original status pill style */
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

        /* Keeping original date info style */
        .date-info {
            margin-left: 15px;
            padding: 5px 10px;
            background-color: #e9f2fa;
            border-radius: 4px;
            color: #2980b9;
            font-size: 14px;
            display: inline-block;
        }

        /* Keeping original download button style */
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

        /* Keeping original view orders button style */
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

        /* Keeping original instructions button style */
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

        /* Keeping original PO PDF layout styles */
        .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; }
        .po-header { text-align: center; margin-bottom: 30px; }
        .po-company { font-size: 22px; font-weight: bold; margin-bottom: 10px; }
        .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
        .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .po-left, .po-right { width: 48%; }
        .po-detail-row { margin-bottom: 10px; }
        .po-detail-label { font-weight: bold; display: inline-block; width: 120px; }
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .po-table th, .po-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .po-table th { background-color: #f2f2f2; }
        .po-total { text-align: right; font-weight: bold; font-size: 14px; margin-bottom: 30px; }

        /* Keeping original PDF Preview styles */
        #pdfPreview { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 1000; overflow: auto; }
        .pdf-container { background-color: white; width: 80%; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.5); position: relative; }
        .close-pdf { position: absolute; top: 10px; right: 10px; font-size: 18px; background: none; border: none; cursor: pointer; color: #333; }
        .pdf-actions { text-align: center; margin-top: 20px; }
        .download-pdf-btn { padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }

        /* Keeping original Instructions Modal styles */
        .instructions-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.7); }
        .instructions-modal-content { background-color: #ffffff; margin: 10% auto; padding: 0; border-radius: 8px; width: 60%; max-width: 600px; position: relative; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalFadeIn 0.3s ease-in-out; overflow: hidden; max-height: 90vh; overflow-y: auto; margin: 2vh auto; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .instructions-header { background-color: #2980b9; color: white; padding: 15px 20px; position: relative; }
        .instructions-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
        .instructions-po-number { font-size: 12px; margin-top: 5px; opacity: 0.9; }
        .instructions-body { padding: 20px; max-height: 300px; overflow-y: auto; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; border-bottom: 1px solid #eaeaea; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 40px 20px; }
        .instructions-footer { padding: 15px 20px; text-align: right; background-color: #ffffff; }
        .close-instructions-btn { background-color: #2980b9; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; }
        .close-instructions-btn:hover { background-color: #2471a3; }

        /* Keeping original Content for PDF styles */
        #contentToDownload { font-size: 14px; position: absolute; left: -9999px; top: auto; width: 800px; } /* Added positioning */
        #contentToDownload .po-table { font-size: 12px; }
        #contentToDownload .po-title { font-size: 16px; }
        #contentToDownload .po-company { font-size: 20px; }
        #contentToDownload .po-total { font-size: 12px; }

        /* Keeping original Media Query */
        @media screen and (max-width: 768px) {
            .orders-table-container { overflow-x: auto; }
            .order-details-container { overflow-x: auto; }
            .action-buttons-cell { min-width: 210px; }
        }
        /* --- End of Original Styles --- */

        /* --- ADDED CSS FOR TOAST FIX --- */
        /* Ensure container exists and is positioned */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; width: 320px; }
        /* Basic toast appearance (can be overridden by /css/toast.css) */
        .toast { background-color: #333; color: #fff; padding: 15px; border-radius: 4px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); opacity: 0.9; transition: opacity 0.5s ease-in-out; position: relative; /* For close button positioning */ }
        .toast.success { background-color: #28a745; }
        .toast.error { background-color: #dc3545; }
        .toast.info { background-color: #0dcaf0; color: #000; }
        /* Flexbox for Icon and Message Alignment */
        .toast-content { display: flex; align-items: center; gap: 10px; /* Adjust gap as needed */ }
        .toast-content i { font-size: 1.2em; /* Adjust icon size */ flex-shrink: 0; /* Prevent icon shrinking */ }
        .toast-content .message { flex-grow: 1; /* Allow message to take space */ margin: 0; font-size: 14px; line-height: 1.4; }
        /* Optional: Close button style */
        .toast .close { position: absolute; top: 5px; right: 8px; background: none; border: none; color: rgba(255, 255, 255, 0.7); cursor: pointer; font-size: 1.2em; padding: 0; line-height: 1; }
        .toast .close:hover { color: #fff; }
        /* --- END OF ADDED CSS FOR TOAST FIX --- */

        /* --- ADDED CSS FOR CONFIRMATION MODALS --- */
        /* Using the existing .overlay for background and positioning */
        /* Using existing .overlay-content, .modal-content h2, .modal-message, .modal-buttons, .btn-no, .btn-yes */
        /* Style for the specific confirmation modals if needed, but reusing generic is fine */
        #statusConfirmModal .overlay-content,
        #completeConfirmModal .overlay-content,
        #driverConfirmModal .overlay-content {
            max-width: 450px; /* Adjust width for confirmation dialogs */
        }
        /* --- END OF ADDED CSS FOR CONFIRMATION MODALS --- */

    </style>
</head>
<body>
    <?php include '/admin/public/sidebar.php'; // Assuming sidebar is one level up ?>
    <div class="main-content">
        <div class="orders-header">
            <div class="header-content">
                <h1 class="page-title"><i class="fas fa-truck-loading"></i> Deliverable Orders</h1>

                <div class="filter-section">
                    <div class="filter-group">
                        <label for="statusFilter" class="filter-label">Status:</label>
                        <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                            <option value="">All</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="date-info">
                        <i class="fas fa-calendar-day"></i> Today: <?= htmlspecialchars($current_date) ?>
                        <?php if (isset($auto_transit_count) && $auto_transit_count > 0): ?>
                            (<?= $auto_transit_count ?> orders auto-updated)
                        <?php elseif (isset($auto_transit_count) && $auto_transit_count < 0): ?>
                             <span style="color: red; margin-left: 5px;">(Error updating)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search by PO Number, Username...">
                    <button class="search-btn" aria-label="Search"><i class="fas fa-search"></i></button>
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
                                <td style="white-space: normal; min-width: 150px;"><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>
                                        View Order Items
                                    </button>
                                </td>
                                <td style="text-align: right;">PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
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
                                        <!-- MODIFIED onclick to call confirmation function -->
                                        <button class="driver-btn" onclick="confirmDriverChangeModal('<?= htmlspecialchars($order['po_number']) ?>', <?= intval($order['driver_id']) ?>, '<?= htmlspecialchars(addslashes($order['driver_name'])) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change
                                        </button>
                                    <?php else: ?>
                                        <span class="no-driver" style="color: #dc3545; font-style: italic;">No driver assigned</span>
                                        <!-- Optionally add assign button here if needed -->
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <span class="status-badge status-for-delivery">For Delivery</span>
                                    <?php else: ?>
                                        <span class="status-badge status-in-transit">In Transit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons-cell">
                                    <button class="download-btn" onclick="downloadPODirectly(
                                        '<?= htmlspecialchars($order['po_number']) ?>',
                                        '<?= htmlspecialchars(addslashes($order['username'])) ?>',
                                        '<?= htmlspecialchars(addslashes($order['company'] ?? '')) ?>',
                                        '<?= htmlspecialchars($order['order_date']) ?>',
                                        '<?= htmlspecialchars($order['delivery_date']) ?>',
                                        '<?= htmlspecialchars(addslashes($order['delivery_address'])) ?>',
                                        '<?= htmlspecialchars(addslashes($order['orders'])) ?>',
                                        '<?= htmlspecialchars($order['total_amount']) ?>',
                                        '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>'
                                    )">
                                        <i class="fas fa-file-pdf"></i> Download PDF
                                    </button>

                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <!-- MODIFIED onclick to call confirmation function -->
                                        <button class="toggle-transit-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', 'In Transit')">
                                            <i class="fas fa-truck"></i> Mark In Transit
                                        </button>
                                    <?php else: ?>
                                         <!-- MODIFIED onclick to call confirmation function -->
                                        <button class="toggle-transit-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', 'For Delivery')">
                                            <i class="fas fa-warehouse"></i> Mark For Delivery
                                        </button>
                                    <?php endif; ?>

                                    <!-- MODIFIED onclick to call confirmation function -->
                                    <button class="complete-delivery-btn" onclick="confirmCompleteDelivery('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars(addslashes($order['username'])) ?>')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="no-orders">
                                <?php if (!empty($filterStatus)): ?>
                                    No orders found with status "<?= htmlspecialchars($filterStatus) ?>".
                                <?php else: ?>
                                    No orders ready for delivery or in transit.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- PO PDF Preview Section (Original) -->
    <div id="pdfPreview" class="overlay"> <!-- Added overlay class -->
        <div class="pdf-container overlay-content"> <!-- Added overlay-content -->
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToRender"> <!-- Renamed div for clarity -->
                <!-- Content will be copied here for preview -->
            </div>
            <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>
    <!-- Hidden div for html2pdf generation -->
    <div id="contentToDownload" style="position: absolute; left: -9999px; top: auto; width: 800px;">
         <div class="po-container">
             <div class="po-header">
                 <div class="po-company" id="printCompany"></div>
                 <div class="po-title">Purchase Order</div>
             </div>
             <div class="po-details">
                 <div class="po-left">
                     <div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div>
                     <div class="po-detail-row"><span class="po-detail-label">Username:</span> <span id="printUsername"></span></div>
                     <div class="po-detail-row"><span class="po-detail-label">Delivery Address:</span> <span id="printDeliveryAddress"></span></div>
                     <div class="po-detail-row" id="printInstructionsSection" style="display: none;"><span class="po-detail-label">Instructions:</span> <span id="printSpecialInstructions" style="white-space: pre-wrap;"></span></div>
                 </div>
                 <div class="po-right">
                     <div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div>
                     <div class="po-detail-row"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div>
                 </div>
             </div>
             <table class="po-table">
                 <thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align: right;">Qty</th><th style="text-align: right;">Unit Price</th><th style="text-align: right;">Total</th></tr></thead>
                 <tbody id="printOrderItems"></tbody>
             </table>
             <div class="po-total">Total Amount: PHP <span id="printTotalAmount"></span></div>
         </div>
     </div>

    <!-- Special Instructions Modal (Original) -->
    <div id="specialInstructionsModal" class="instructions-modal overlay"> <!-- Added overlay class -->
        <div class="instructions-modal-content overlay-content"> <!-- Added overlay-content -->
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

    <!-- Order Details Modal (Original) -->
    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details (<span id="modalPoNumberView">PO...</span>)</h2> <!-- Added span for PO -->
            <div class="order-details-container">
                <table class="order-details-table">
                    <thead>
                        <tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align: right;">Price</th><th style="text-align: right;">Quantity</th><th style="text-align: right;">Total</th></tr>
                    </thead>
                    <tbody id="orderDetailsBody"></tbody>
                </table>
            </div>
            <div class="form-buttons">
                <button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>

    <!-- Driver Assignment Modal (Original - will be used for selection) -->
    <div id="driverModal" class="overlay" style="display: none;">
        <div class="overlay-content driver-modal-content"> <!-- Added overlay-content -->
            <h2><i class="fas fa-user-edit"></i> <span id="driverModalTitle">Change Driver</span></h2>
            <p id="driverModalMessage"></p>
            <div class="driver-selection">
                <label for="driverSelect">Select New Driver:</label>
                <select id="driverSelect">
                    <option value="0">-- Select a driver --</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="driver-modal-buttons modal-buttons"> <!-- Reused modal-buttons class -->
                <!-- Changed btn-no style slightly for cancel -->
                <button type="button" class="btn-no" style="background-color: #6c757d;" onclick="closeDriverModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <!-- MODIFIED onclick to call confirmation function -->
                <button type="button" class="btn-yes" style="background-color: #6610f2;" onclick="confirmDriverAssignmentChange()">
                    <i class="fas fa-check"></i> Confirm Selection
                </button>
            </div>
        </div>
    </div>

    <!-- ADDED Status Change Confirmation Modal -->
    <div id="statusConfirmModal" class="overlay confirmation-modal" style="display: none;">
        <div class="overlay-content modal-content">
            <h2><i id="statusConfirmIcon" class="fas fa-question-circle"></i> Confirm Status Change</h2>
            <div class="modal-message" id="statusConfirmMessage">Are you sure?</div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeStatusConfirmation()">No</button>
                <button id="executeStatusChangeBtn" class="btn-yes">Yes</button> <!-- JS will set onclick -->
            </div>
        </div>
    </div>

    <!-- ADDED Complete Order Confirmation Modal -->
    <div id="completeConfirmModal" class="overlay confirmation-modal" style="display: none;">
        <div class="overlay-content modal-content">
            <h2><i class="fas fa-check-double"></i> Confirm Completion</h2>
            <div class="modal-message" id="completeConfirmMessage">Are you sure?</div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeCompleteConfirmation()">No</button>
                <button id="executeCompleteBtn" class="btn-yes" style="background-color: #28a745;">Yes, Complete</button> <!-- JS will set onclick -->
            </div>
        </div>
    </div>

    <!-- ADDED Driver Change Confirmation Modal -->
    <div id="driverConfirmModal" class="overlay confirmation-modal" style="display: none;">
        <div class="overlay-content modal-content">
            <h2><i class="fas fa-user-check"></i> Confirm Driver Change</h2>
            <div class="modal-message" id="driverConfirmMessage">Are you sure?</div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeDriverConfirmation()">No</button>
                <button id="executeDriverChangeBtn" class="btn-yes" style="background-color: #6610f2;">Yes, Change Driver</button> <!-- JS will set onclick -->
            </div>
        </div>
    </div>


    <script>
        // --- Original Variables ---
        let currentPoNumber = '';
        let currentDriverId = 0;
        // let currentStatusChange = ''; // Renamed below
        let currentPOData = null; // For PDF generation

        // --- Added Variables for Confirmation ---
        let targetStatus = '';
        let targetDriverId = 0;
        let poForCompletion = '';
        let userForCompletion = '';

        // --- Original filterByStatus ---
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const url = new URL(window.location.href); // Use URL API for easier param handling
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }

        // --- ADDED Confirmation function for Status Change ---
        function confirmStatusChange(poNumber, newStatus) {
            currentPoNumber = poNumber; // Store PO for execution
            targetStatus = newStatus; // Store target status

            const modalMessage = document.getElementById('statusConfirmMessage');
            const modalIcon = document.getElementById('statusConfirmIcon');
            const modal = document.getElementById('statusConfirmModal');
            const yesButton = document.getElementById('executeStatusChangeBtn');

            if (newStatus === 'In Transit') {
                modal.querySelector('h2').innerHTML = '<i class="fas fa-truck"></i> Confirm: Mark In Transit';
                modalMessage.innerHTML = `Change order <strong>${poNumber}</strong> to <span class="status-pill in-transit">In Transit</span>?`;
                yesButton.textContent = 'Yes, Mark In Transit';
                yesButton.style.backgroundColor = '#17a2b8'; // Match button color
            } else { // For Delivery
                modal.querySelector('h2').innerHTML = '<i class="fas fa-warehouse"></i> Confirm: Mark For Delivery';
                modalMessage.innerHTML = `Change order <strong>${poNumber}</strong> to <span class="status-pill for-delivery">For Delivery</span>?`;
                yesButton.textContent = 'Yes, Mark For Delivery';
                 yesButton.style.backgroundColor = '#fd7e14'; // Match button color
            }

            // Set the action for the YES button
            yesButton.onclick = executeToggleTransitStatus;

            modal.style.display = 'flex';
        }

        // --- ADDED Close function for Status Confirmation ---
        function closeStatusConfirmation() {
            document.getElementById('statusConfirmModal').style.display = 'none';
            currentPoNumber = ''; // Clear context
            targetStatus = '';
        }

        // --- MODIFIED Original toggleTransitStatus to be called AFTER confirmation ---
        function executeToggleTransitStatus() {
            // Use stored context
            const poNumber = currentPoNumber;
            const newStatus = targetStatus;

            if (!poNumber || !newStatus) {
                console.error("Missing context for status change execution.");
                return;
            }

            closeStatusConfirmation(); // Close the confirmation modal
            showToast(`Updating status for ${poNumber}...`, 'info');

            // **Assume original /backend/toggle_transit_status.php OR /backend/update_order_status.php exists and works**
            // Using a general endpoint is often better:
            fetch('/backend/update_order_status.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({
                     po_number: poNumber,
                     status: newStatus,
                     deduct_materials: '0', // Not relevant for these changes
                     return_materials: '0'  // Not relevant for these changes
                 })
             })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Order status updated to ${newStatus}`, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500); // Reload after delay
                } else {
                    showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error toggling status:', error);
                showToast('Error: Failed to communicate with server', 'error');
            });
        }

        // --- ADDED Confirmation function for Completing Delivery ---
        function confirmCompleteDelivery(poNumber, username) {
            poForCompletion = poNumber; // Store context
            userForCompletion = username;

            const modalMessage = document.getElementById('completeConfirmMessage');
            modalMessage.innerHTML = `Mark order <strong>${poNumber}</strong> for <strong>${username}</strong> as completed?`;

            // Set action for YES button
            document.getElementById('executeCompleteBtn').onclick = executeCompleteDeliveryAction;

            document.getElementById('completeConfirmModal').style.display = 'flex';
        }

        // --- ADDED Close function for Complete Confirmation ---
        function closeCompleteConfirmation() {
            document.getElementById('completeConfirmModal').style.display = 'none';
            poForCompletion = ''; // Clear context
            userForCompletion = '';
        }

        // --- MODIFIED Original completeDelivery to be called AFTER confirmation ---
        function executeCompleteDeliveryAction() {
            const poNumber = poForCompletion; // Use stored context
            if (!poNumber) return;

            closeCompleteConfirmation(); // Close the confirmation modal
            showToast(`Processing completion for ${poNumber}...`, 'info');

            // **Assume original /backend/complete_delivery.php OR /backend/update_order_status.php exists and works**
             fetch('/backend/update_order_status.php', { // Using general endpoint
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({
                     po_number: poNumber,
                     status: 'Completed',
                     deduct_materials: '0', // Not relevant
                     return_materials: '0'  // Not relevant
                 })
             })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Delivery completed successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error completing delivery:', error);
                showToast('Error: Failed to communicate with server', 'error');
            });
        }

        // --- MODIFIED Original openDriverModal (Still opens selection modal) ---
        function openDriverModal(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId; // Store the original driver ID

            document.getElementById('driverModalTitle').textContent = driverName ? 'Change Driver' : 'Assign Driver'; // Keep original title logic
            document.getElementById('driverModalMessage').textContent = driverName ? `Current driver: ${driverName}. Select new driver:` : `Assign driver for PO: ${poNumber}`;

            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = driverId; // Pre-select current driver

            document.getElementById('driverModal').style.display = 'flex';
        }

        // --- Original closeDriverModal ---
        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
            // Don't clear currentPoNumber here, might be needed by confirmation
        }

        //--- ADDED function called by "Confirm Selection" in Driver Modal ---
        function confirmDriverAssignmentChange() {
            targetDriverId = parseInt(document.getElementById('driverSelect').value); // Get selected ID
            const selectedDriverName = $("#driverSelect option:selected").text();

             if (targetDriverId === 0) {
                showToast('Please select a driver.', 'error');
                return;
            }
            if (targetDriverId === currentDriverId) {
                 showToast('Selected driver is already assigned.', 'info');
                 return;
            }

             // Prepare confirmation message
            const confirmMsg = document.getElementById('driverConfirmMessage');
            confirmMsg.innerHTML = `Assign driver <strong>${selectedDriverName}</strong> to order <strong>${currentPoNumber}</strong>?`;

            // Set action for YES button in confirmation modal
            document.getElementById('executeDriverChangeBtn').onclick = executeAssignDriverAction;

            // Hide selection modal, show confirmation modal
            document.getElementById('driverModal').style.display = 'none';
            document.getElementById('driverConfirmModal').style.display = 'flex';
        }

        // --- ADDED Close function for Driver Confirmation ---
        function closeDriverConfirmation() {
             document.getElementById('driverConfirmModal').style.display = 'none';
             targetDriverId = 0; // Clear target
             // currentPoNumber = ''; // Clear PO context if no longer needed
             // currentDriverId = 0; // Clear original driver context
        }

        // --- MODIFIED Original assignDriver to be called AFTER confirmation ---
        function executeAssignDriverAction() {
            // Use context stored during confirmation setup
            const driverIdToAssign = targetDriverId;
            const poNumberForAssign = currentPoNumber;

            if (driverIdToAssign === 0 || !poNumberForAssign) {
                 console.error("Missing context for driver assignment execution.");
                 return;
            }

            closeDriverConfirmation(); // Close confirmation modal
            showToast(`Assigning driver for ${poNumberForAssign}...`, 'info');

            // **Assume original /backend/assign_driver.php exists and works**
            fetch('/backend/assign_driver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: poNumberForAssign,
                    driver_id: driverIdToAssign
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Driver updated successfully', 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showToast(`Error assigning driver: ${data.message || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error assigning driver:', error);
                showToast('Error: Failed to communicate with server', 'error');
            })
            .finally(() => {
                 // Clear context after fetch attempt
                 currentPoNumber = '';
                 currentDriverId = 0;
                 targetDriverId = 0;
            });
        }


        // --- Original viewOrderDetails ---
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber; // Store PO just in case, though not strictly needed here
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            const modal = document.getElementById('orderDetailsModal');
            modal.querySelector('#modalPoNumberView').textContent = poNumber; // Update PO in title
            orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            modal.style.display = 'flex';

            fetch(`/backend/get_order_items.php?po_number=${encodeURIComponent(poNumber)}`)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json().catch(() => { throw new Error("Invalid JSON response from server."); }); // Catch JSON parse errors
            })
            .then(data => {
                console.log("Order items response:", data); // Log for debugging
                orderDetailsBody.innerHTML = ''; // Clear loading/previous
                if (data && data.success && data.orderItems) {
                    let parsedItems = data.orderItems;
                    if (typeof parsedItems === 'string') {
                        try { parsedItems = JSON.parse(parsedItems); }
                        catch (e) { throw new Error("Order items JSON string is invalid."); }
                    }
                    if (!Array.isArray(parsedItems)) { throw new Error("Order items data is not an array."); }

                    if (parsedItems.length === 0) {
                        orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;font-style:italic;">No items found.</td></tr>';
                        return;
                    }

                    let grandTotal = 0;
                    parsedItems.forEach(item => {
                        const quantity = parseInt(item.quantity) || 0;
                        const price = parseFloat(item.price) || 0;
                        const itemTotal = quantity * price;
                        grandTotal += itemTotal;
                        const row = document.createElement('tr');
                        // Added text-align right for numeric columns
                        row.innerHTML = `
                            <td>${item.category || '-'}</td>
                            <td>${item.item_description || '-'}</td>
                            <td>${item.packaging || '-'}</td>
                            <td style="text-align: right;">PHP ${price.toFixed(2)}</td>
                            <td style="text-align: right;">${quantity}</td>
                            <td style="text-align: right;">PHP ${itemTotal.toFixed(2)}</td>
                        `;
                        orderDetailsBody.appendChild(row);
                    });
                    // Add total row
                    const totalRow = document.createElement('tr');
                    totalRow.style.fontWeight = 'bold';
                    totalRow.innerHTML = `<td colspan="5" style="text-align: right; border-top: 1px solid #ccc;">Grand Total:</td><td style="text-align: right; border-top: 1px solid #ccc;">PHP ${grandTotal.toFixed(2)}</td>`;
                    orderDetailsBody.appendChild(totalRow);

                } else {
                    throw new Error(data.message || 'Could not retrieve order details.');
                }
            })
            .catch(error => {
                console.error('Error fetching/processing order details:', error);
                orderDetailsBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc3545;">Error: ${error.message}</td></tr>`;
                showToast('Error loading order details.', 'error');
            });
        }

        // --- Original closeOrderDetailsModal ---
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
            document.getElementById('orderDetailsBody').innerHTML = ''; // Clear content on close
        }

        // --- Original PDF Functions (with minor adjustments for consistency) ---
        function populatePdfDataInternal(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            document.getElementById('printCompany').textContent = company || 'N/A';
            document.getElementById('printPoNumber').textContent = poNumber || 'N/A';
            document.getElementById('printUsername').textContent = username || 'N/A';
            document.getElementById('printDeliveryAddress').textContent = deliveryAddress || 'N/A';
            document.getElementById('printOrderDate').textContent = orderDate || 'N/A';
            document.getElementById('printDeliveryDate').textContent = deliveryDate || 'N/A';
            document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const instrSec = document.getElementById('printInstructionsSection');
            if (specialInstructions && specialInstructions.trim()) {
                document.getElementById('printSpecialInstructions').textContent = specialInstructions;
                instrSec.style.display = 'block';
            } else {
                instrSec.style.display = 'none';
            }

            const items = JSON.parse(ordersJson || '[]');
            const body = document.getElementById('printOrderItems');
            body.innerHTML = '';
            if (!Array.isArray(items)) throw new Error("Order items JSON is not an array.");

            items.forEach(item => {
                const price = parseFloat(item.price) || 0;
                const qty = parseInt(item.quantity) || 0;
                const total = price * qty;
                const row = `<tr><td>${item.category||''}</td><td>${item.item_description||''}</td><td>${item.packaging||''}</td><td style="text-align: right;">${qty}</td><td style="text-align: right;">PHP ${price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td style="text-align: right;">PHP ${total.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td></tr>`;
                body.innerHTML += row;
            });
        }

        function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            try {
                populatePdfDataInternal(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions);
                const element = document.getElementById('contentToDownload');
                const opt = { margin: [10, 10, 10, 10], filename: `PO_${poNumber || 'Unknown'}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true, logging: false }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
                showToast(`Generating PDF for ${poNumber}...`, 'info');
                html2pdf().set(opt).from(element).save()
                    .then(() => { console.log("Direct PDF Download success:", poNumber); })
                    .catch(error => { console.error(`Direct PDF Error for ${poNumber}:`, error); showToast('Error generating PDF.', 'error'); });
            } catch (e) {
                console.error('Error preparing direct PDF:', e);
                showToast(`Error preparing PDF data: ${e.message}`, 'error');
            }
        }

        function generatePO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            try {
                currentPOData = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions };
                populatePdfDataInternal(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions);
                // Copy populated content to the preview div
                document.getElementById('contentToRender').innerHTML = document.getElementById('contentToDownload').innerHTML;
                document.getElementById('pdfPreview').style.display = 'flex'; // Show preview modal
            } catch (e) {
                console.error('Error preparing PDF preview:', e);
                showToast(`Error preparing PDF preview: ${e.message}`, 'error');
            }
        }

        function closePDFPreview() {
            document.getElementById('pdfPreview').style.display = 'none';
            document.getElementById('contentToRender').innerHTML = ''; // Clear preview
            currentPOData = null;
        }

        function downloadPDF() { // Called from preview modal
            if (!currentPOData) { showToast('No data to download.', 'error'); return; }
            try {
                // Re-populate hidden div just before download for safety
                populatePdfDataInternal(
                    currentPOData.poNumber, currentPOData.username, currentPOData.company, currentPOData.orderDate,
                    currentPOData.deliveryDate, currentPOData.deliveryAddress, currentPOData.ordersJson,
                    currentPOData.totalAmount, currentPOData.specialInstructions
                );
                const element = document.getElementById('contentToDownload');
                const opt = { margin: [10, 10, 10, 10], filename: `PO_${currentPOData.poNumber || 'Unknown'}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true, logging: false }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
                showToast(`Generating PDF for ${currentPOData.poNumber}...`, 'info');
                html2pdf().set(opt).from(element).save()
                    .then(() => { closePDFPreview(); }) // Close preview after download starts
                    .catch(error => { console.error(`PDF Download Error (Preview) for ${currentPOData.poNumber}:`, error); showToast('Error generating PDF.', 'error'); });
            } catch (e) {
                 console.error('Error preparing PDF for download from preview:', e);
                 showToast(`Error preparing PDF data: ${e.message}`, 'error');
            }
        }

        // --- Original Special Instructions Modal Functions ---
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
            document.getElementById('specialInstructionsModal').style.display = 'flex'; // Use flex
        }

        function closeSpecialInstructions() {
            document.getElementById('specialInstructionsModal').style.display = 'none';
        }

        // --- MODIFIED Original showToast ---
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
             // Added toast-content div and close button structure
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i>
                    <div class="message">${message}</div>
                </div>
                <i class="fas fa-times close" onclick="this.parentElement.remove()"></i>`;
            container.appendChild(toast);
            // Simple fade out, no complex animation needed if CSS handles it
            setTimeout(() => {
                 if(toast.parentElement) { // Check if still exists
                     toast.style.opacity = '0'; // Start fade out
                     setTimeout(() => { if(toast.parentElement) toast.remove(); }, 600); // Remove after fade
                 }
             }, 4000); // Remove after 4 seconds
        }


        // --- Original Sorting functionality ---
        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                const url = new URL(window.location.href);
                const currentOrder = url.searchParams.get('order') || 'ASC';
                let order = 'ASC';
                if (url.searchParams.get('sort') === column && currentOrder === 'ASC') {
                    order = 'DESC';
                }
                url.searchParams.set('sort', column);
                url.searchParams.set('order', order);
                window.location.href = url.toString();
            });
        });

        // --- Original Search functionality ---
        $(document).ready(function() {
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                $(".order-row").each(function() {
                    $(this).toggle($(this).text().toLowerCase().includes(searchText));
                });
            });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input"));

            // --- MODIFIED Original Modal Closing ---
            // Close modals when clicking on the overlay background
            $('.overlay').on('click', function(event) {
                 if (event.target === this) { // Check if the click is directly on the overlay
                     // Use the specific close functions to ensure context is cleared
                     if (this.id === 'orderDetailsModal') closeOrderDetailsModal();
                     else if (this.id === 'driverModal') closeDriverModal(); // Closes selection modal
                     else if (this.id === 'statusConfirmModal') closeStatusConfirmation(); // Closes status confirmation
                     else if (this.id === 'completeConfirmModal') closeCompleteConfirmation(); // Closes complete confirmation
                     else if (this.id === 'driverConfirmModal') closeDriverConfirmation(); // Closes driver confirmation
                     else if (this.id === 'specialInstructionsModal') closeSpecialInstructions();
                     else if (this.id === 'pdfPreview') closePDFPreview();
                     else $(this).hide(); // Generic hide for any others (shouldn't be needed)
                 }
             });

            // Original responsive table scroll
            if (window.innerWidth < 768) {
                const tableContainer = document.querySelector('.orders-table-container');
                if (tableContainer) tableContainer.style.overflowX = 'auto';
                const detailsContainer = document.querySelector('.order-details-container');
                 if (detailsContainer) detailsContainer.style.overflowX = 'auto';
            }
        });
    </script>
</body>
</html>