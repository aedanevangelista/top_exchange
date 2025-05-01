<?php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-05-01 17:51:05
// Current User's Login: aedanevangelista

session_start();
include "../../backend/db_connection.php"; // Ensure this path is correct
include "../../backend/check_role.php";   // Ensure this path is correct
checkRole('Orders'); // Ensure user has access to Orders (or adjust role if needed)

// Get current date for auto-transit
$current_date = date('Y-m-d');

// Automatically update orders to In Transit if today is delivery day
// **Added error handling for prepare**
$auto_transit_sql = "UPDATE orders SET status = 'In Transit'
                     WHERE status = 'For Delivery'
                     AND delivery_date = ?
                     AND status != 'In Transit'";
$auto_transit_stmt = $conn->prepare($auto_transit_sql);
if ($auto_transit_stmt) {
    $auto_transit_stmt->bind_param("s", $current_date);
    // **Added error handling for execute**
    if ($auto_transit_stmt->execute()) {
        $auto_transit_count = $auto_transit_stmt->affected_rows;
    } else {
        error_log("Auto-transit execute failed: " . $auto_transit_stmt->error);
        $auto_transit_count = -1; // Indicate an error occurred
    }
    $auto_transit_stmt->close();
} else {
     error_log("Auto-transit prepare failed: " . $conn->error);
     $auto_transit_count = -1; // Indicate an error occurred
}


// Fetch all drivers for the driver assignment dropdown
$drivers = [];
$driverStmt = $conn->prepare("SELECT id, name FROM drivers WHERE availability = 'Available' AND current_deliveries < 20 ORDER BY name");
// **Added error handling**
if ($driverStmt) {
    if ($driverStmt->execute()) {
        $driverResult = $driverStmt->get_result();
        while ($row = $driverResult->fetch_assoc()) {
            $drivers[] = $row;
        }
    } else {
         error_log("Fetching drivers execute failed: " . $driverStmt->error);
    }
    $driverStmt->close();
} else {
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
if (!in_array(strtoupper($sortOrder), $allowedOrders)) { // Use strtoupper for case-insensitivity
    $sortOrder = 'ASC';
}

// Build the SQL query with or without status filter
// **Use prepared statement for filterStatus to prevent SQL injection**
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
        // Invalid status provided, default to showing all
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
        ORDER BY o.`$sortColumn` $sortOrder"; // Use backticks for column names

$orders = [];
$orderStmt = $conn->prepare($sql);
if ($orderStmt) {
    // Bind parameters if filtering by status
    if (!empty($params)) {
        $orderStmt->bind_param($types, ...$params);
    }

    if ($orderStmt->execute()) {
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
    <link rel="stylesheet" href="/css/orders.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="/css/sidebar.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="/css/toast.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Base styles */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f6; }
        .main-content { margin-left: 250px; /* Adjust if sidebar width changes */ padding: 20px; }

        /* Header */
        .orders-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; /* Allow wrapping on smaller screens */ gap: 15px; }
        .header-content { display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: inherit; gap: inherit; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0; color: #333; flex-shrink: 0; }

        /* Filters and Search */
        .filter-section { display: flex; align-items: center; gap: 15px; flex-grow: 1; justify-content: center; }
        .filter-group { display: flex; align-items: center; }
        .filter-label { margin-right: 8px; font-weight: 500; font-size: 14px; color: #555; }
        .filter-select { padding: 8px 10px; border-radius: 4px; border: 1px solid #ccc; background-color: white; min-width: 150px; font-size: 14px; cursor: pointer; }
        .date-info { margin-left: 15px; padding: 6px 12px; background-color: #e9f2fa; border: 1px solid #bde0fe; border-radius: 4px; color: #0a58ca; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .search-container { display: flex; align-items: center; flex-shrink: 0; }
        .search-container input { padding: 8px 12px; border-radius: 20px 0 0 20px; border: 1px solid #ccc; border-right: none; font-size: 14px; width: 220px; }
        .search-container .search-btn { background-color: #0d6efd; color: white; border: 1px solid #0d6efd; border-radius: 0 20px 20px 0; padding: 8px 15px; cursor: pointer; transition: background-color 0.2s; }
        .search-container .search-btn:hover { background-color: #0b5ed7; }

        /* Table Styles */
        .orders-table-container { max-height: 75vh; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background-color: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .orders-table { width: 100%; border-collapse: collapse; }
        .orders-table th, .orders-table td { border: 1px solid #e1e1e1; padding: 10px 12px; text-align: left; font-size: 14px; vertical-align: middle; white-space: nowrap; }
        .orders-table th { background-color: #f8f9fa; position: sticky; top: 0; z-index: 10; font-weight: 600; color: #495057; }
        .orders-table tbody tr:hover { background-color: #f1f1f1; }
        .orders-table td[style*="text-align: right"] { text-align: right; } /* Ensure alignment overrides */
        .orders-table td[style*="text-align: center"] { text-align: center; }

        /* Sorting Headers */
        .sort-header { cursor: pointer; position: relative; padding-right: 20px; transition: background-color 0.2s; user-select: none; }
        .sort-header:hover { background-color: #e9ecef; }
        .sort-header::after { content: '\f0dc'; /* Default sort icon */ font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 0.8em; }
        .sort-header.asc::after { content: '\f0de'; /* Sort up */ color: #0d6efd; }
        .sort-header.desc::after { content: '\f0dd'; /* Sort down */ color: #0d6efd; }

        /* Badges */
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; display: inline-block; text-align: center; border: 1px solid transparent; }
        .status-for-delivery { background-color: #fff3cd; color: #664d03; border-color: #ffecb5; } /* Yellowish */
        .status-in-transit { background-color: #cfe2ff; color: #0a58ca; border-color: #b6d4fe; } /* Blueish */
        .driver-badge { background-color: #e9ecef; color: #495057; padding: 5px 10px; border-radius: 15px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 5px; border: 1px solid #ced4da; }
        .driver-badge i { color: #6c757d; }

        /* Buttons */
        .action-buttons-cell { display: flex; flex-direction: column; gap: 5px; min-width: 150px; /* Adjust as needed */ align-items: center; }
        .driver-btn, .toggle-transit-btn, .complete-delivery-btn, .download-btn, .view-orders-btn, .instructions-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 5px; text-align: center; width: 140px; /* Fixed width for alignment */ }
        .driver-btn:hover, .toggle-transit-btn:hover, .complete-delivery-btn:hover, .download-btn:hover, .view-orders-btn:hover, .instructions-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.15); }

        .driver-btn { background-color: #6f42c1; color: white; } /* Purple */
        .driver-btn:hover { background-color: #5a32a3; }
        .toggle-transit-btn { background-color: #198754; color: white; } /* Green */
        .toggle-transit-btn.mark-for-delivery { background-color: #fd7e14; } /* Orange */
        .toggle-transit-btn:hover { background-color: #157347; }
        .toggle-transit-btn.mark-for-delivery:hover { background-color: #d36a10; }
        .complete-delivery-btn { background-color: #0dcaf0; color: #000; } /* Cyan */
        .complete-delivery-btn:hover { background-color: #31d2f2; }
        .download-btn { background-color: #6c757d; color: white; } /* Gray */
        .download-btn:hover { background-color: #5a6268; }
        .view-orders-btn { background-color: #0d6efd; color: white; width: auto; min-width: 80px; } /* Blue */
        .view-orders-btn:hover { background-color: #0b5ed7; }
        .instructions-btn { background-color: #ffc107; color: #000; width: auto; min-width: 80px; } /* Yellow */
        .instructions-btn:hover { background-color: #ffca2c; }
        .no-instructions { font-size: 12px; color: #6c757d; font-style: italic; }

        /* Highlighting */
        .today-delivery { background-color: #fff3cd !important; /* Light yellow highlight */ font-weight: 500; }
        .today-tag { font-weight: normal; font-style: italic; font-size: 0.9em; color: #664d03; margin-left: 3px; }

        /* Modals (General Overlay) */
        .overlay { display: none; /* Hidden by default */ position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1050; /* High z-index */ display: flex; justify-content: center; align-items: center; padding: 20px; }
        .overlay-content { background-color: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 550px; /* Consistent max width */ box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); max-height: 90vh; overflow-y: auto; position: relative; animation: modalSlideIn 0.3s ease-out; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .overlay-content h2 { margin-top: 0; color: #333; margin-bottom: 20px; font-size: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        /* Order Details Modal Specifics */
        #orderDetailsModal .overlay-content { max-width: 700px; } /* Wider for details table */
        .order-details-container { max-height: 65vh; overflow-y: auto; margin-bottom: 15px; padding-right: 5px; }
        .order-details-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .order-details-table th, .order-details-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; font-size: 14px; }
        .order-details-table th { background-color: #f8f9fa; font-weight: 600; }
        .order-details-table td:nth-child(4), /* Price */
        .order-details-table td:nth-child(5), /* Qty */
        .order-details-table td:nth-child(6) { text-align: right; } /* Total */
        .order-details-table tr:last-child td { border-top: 2px solid #adb5bd; font-weight: bold; } /* Style total row */
        #orderDetailsModal .form-buttons { margin-top: 20px; text-align: right; }
        #orderDetailsModal .back-btn { background-color: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; }
        #orderDetailsModal .back-btn:hover { background-color: #5a6268; }

        /* Driver Modal Specifics */
        #driverModal .overlay-content { max-width: 450px; }
        .driver-selection { margin: 20px 0; }
        .driver-selection label { display: block; margin-bottom: 8px; font-weight: 500; }
        .driver-selection select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .driver-modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        #driverModal .cancel-btn, #driverModal .save-btn { padding: 9px 20px; border-radius: 4px; font-size: 14px; cursor: pointer; transition: all 0.2s; }
        #driverModal .cancel-btn { background-color: #6c757d; color: white; border: none; }
        #driverModal .cancel-btn:hover { background-color: #5a6268; }
        #driverModal .save-btn { background-color: #6f42c1; color: white; border: none; }
        #driverModal .save-btn:hover { background-color: #5a32a3; }
        #driverModal .save-btn:disabled { background-color: #b3a1d1; cursor: not-allowed; }


        /* Confirmation Modals (Status, Complete, Driver) */
        .confirmation-modal .overlay-content { max-width: 400px; padding: 30px; } /* Consistent confirmation size */
        .confirmation-modal h2 { justify-content: center; font-size: 22px; }
        .confirmation-modal .modal-message { margin: 25px 0; font-size: 16px; line-height: 1.6; text-align: center; color: #333; }
        .confirmation-modal .modal-message strong { font-weight: 600; }
        .confirmation-modal .modal-buttons { display: flex; justify-content: center; gap: 20px; margin-top: 30px; }
        .confirmation-modal .btn-no, .confirmation-modal .btn-yes { padding: 10px 25px; border-radius: 25px; border: none; cursor: pointer; font-size: 16px; transition: all 0.3s; min-width: 120px; font-weight: 500; }
        .confirmation-modal .btn-no { background-color: #f8f9fa; color: #333; border: 1px solid #ccc; }
        .confirmation-modal .btn-no:hover { background-color: #e2e6ea; transform: translateY(-1px); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .confirmation-modal .btn-yes { color: white; } /* Base color set by specific modal */
        .confirmation-modal .btn-yes:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        /* Specific YES button colors */
        #statusChangeModal .btn-yes { background-color: #198754; } /* Green for status change */
        #statusChangeModal .btn-yes:hover { background-color: #157347; }
        #completeOrderModal .btn-yes { background-color: #0dcaf0; color: #000; } /* Cyan for complete */
        #completeOrderModal .btn-yes:hover { background-color: #31d2f2; }
        #driverConfirmationModal .btn-yes { background-color: #6f42c1; } /* Purple for driver */
        #driverConfirmationModal .btn-yes:hover { background-color: #5a32a3; }

        /* Status Pill inside confirmation */
        .status-pill { display: inline-block; padding: 3px 10px; border-radius: 12px; font-weight: 600; font-size: 0.95em; margin: 0 3px; color: white; border: 1px solid rgba(0,0,0,0.1); }
        .status-pill.for-delivery { background-color: #fd7e14; } /* Orange */
        .status-pill.in-transit { background-color: #0d6efd; } /* Blue */

        /* Instructions Modal */
        .instructions-modal .overlay-content { max-width: 550px; }
        .instructions-header { background-color: #ffc107; color: #000; padding: 12px 20px; border-bottom: 1px solid #e9ecef; }
        .instructions-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .instructions-po-number { font-size: 13px; margin-top: 5px; opacity: 0.9; }
        .instructions-body { padding: 20px; max-height: 40vh; overflow-y: auto; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #fff; border-bottom: 1px solid #eee; font-size: 14px; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 40px 20px; background-color: #f8f9fa; }
        .instructions-footer { padding: 15px 20px; text-align: right; background-color: #f8f9fa; border-top: 1px solid #eee; }
        .close-instructions-btn { background-color: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; }
        .close-instructions-btn:hover { background-color: #5a6268; }

        /* PDF Preview & Download Styles */
        #pdfPreview { display: none; /* Hidden initially */ position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.75); z-index: 1060; overflow: auto; padding: 30px; }
        .pdf-container { background-color: white; width: 90%; max-width: 850px; /* A bit wider for A4 */ margin: 30px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 15px rgba(0,0,0,0.5); position: relative; }
        .close-pdf { position: absolute; top: 10px; right: 15px; font-size: 24px; background: none; border: none; cursor: pointer; color: #555; padding: 5px; line-height: 1; }
        .close-pdf:hover { color: #000; }
        .pdf-actions { text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; }
        .download-pdf-btn { padding: 10px 25px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 15px; transition: background-color 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .download-pdf-btn:hover { background-color: #0b5ed7; }
        #contentToDownload { position: absolute; left: -9999px; top: auto; width: 800px; /* For html2pdf rendering size */ }
        .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border: 1px solid #ccc; }
        .po-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .po-company { font-size: 22px; font-weight: bold; margin-bottom: 5px; color: #333; }
        .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; color: #555; }
        .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 11px; line-height: 1.5; }
        .po-left, .po-right { width: 48%; }
        .po-detail-row { margin-bottom: 8px; }
        .po-detail-label { font-weight: bold; display: inline-block; width: 100px; color: #444; }
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .po-table th, .po-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 10px; }
        .po-table th { background-color: #f2f2f2; font-weight: bold; }
        .po-table td:nth-child(4), .po-table td:nth-child(5), .po-table td:nth-child(6) { text-align: right; }
        .po-table th:nth-child(4), .po-table th:nth-child(5), .po-table th:nth-child(6) { text-align: right; }
        .po-total { text-align: right; font-weight: bold; font-size: 12px; margin-bottom: 30px; padding-top: 10px; border-top: 1px solid #aaa; }

        /* --- FIXED Toast CSS --- */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; width: 320px; }
        .toast { background-color: #333; color: #fff; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); opacity: 0; transition: opacity 0.4s ease-in-out, transform 0.4s ease-in-out; transform: translateX(100%); display: block; /* Ensure block for transform */ position: relative; /* For close button */ }
        .toast.show { opacity: 0.95; transform: translateX(0); }
        .toast.success { background-color: #198754; } /* Bootstrap success green */
        .toast.error { background-color: #dc3545; } /* Bootstrap danger red */
        .toast.info { background-color: #0dcaf0; color: #000; } /* Bootstrap info cyan */
        .toast-content { display: flex; align-items: center; gap: 12px; /* Increased gap */ }
        .toast-content i { font-size: 1.4em; /* Slightly larger icon */ flex-shrink: 0; line-height: 1; }
        .toast-content .message { flex-grow: 1; margin: 0; font-size: 14px; line-height: 1.4; }
        .toast .close { position: absolute; top: 5px; right: 8px; background: none; border: none; color: rgba(255, 255, 255, 0.7); cursor: pointer; font-size: 1.2em; padding: 0; line-height: 1; }
        .toast .close:hover { color: #fff; }
        /* --- End of Fixed Toast CSS --- */

        /* Responsive adjustments */
        @media screen and (max-width: 1200px) {
            .main-content { margin-left: 0; } /* Adjust if sidebar becomes collapsible */
            .filter-section { justify-content: flex-start; }
        }
        @media screen and (max-width: 992px) {
            .header-content { flex-direction: column; align-items: flex-start; }
            .filter-section { width: 100%; margin-top: 10px; justify-content: space-between; }
            .search-container { margin-top: 10px; width: 100%; justify-content: flex-end; }
            .search-container input { width: calc(100% - 50px); }
        }
        @media screen and (max-width: 768px) {
            .orders-table-container { overflow-x: auto; } /* Enable horizontal scroll on table */
            .action-buttons-cell { min-width: 150px; } /* Ensure buttons have space */
            .overlay-content { width: 95%; padding: 15px; }
            .confirmation-modal .overlay-content { max-width: 90%; padding: 20px; }
            .modal-buttons { gap: 10px; }
            .btn-no, .btn-yes { min-width: 100px; padding: 8px 18px; font-size: 14px; }
        }

    </style>
</head>
<body>
    <?php
        // **Corrected sidebar include path assumption**
        $sidebarPath = '../sidebar.php'; // Assumes sidebar.php is in /public/
        if (file_exists($sidebarPath)) {
            include $sidebarPath;
        } else {
            // Fallback or error message if sidebar is not found
             echo "<div style='position:fixed; top:0; left:0; background:red; color:white; padding:10px; z-index: 10000;'>Error: Sidebar file not found at '$sidebarPath'. Please check the include path in deliverable_orders.php.</div>";
        }
    ?>
    <div class="main-content">
        <div class="orders-header">
            <div class="header-content">
                <h1 class="page-title"><i class="fas fa-truck-loading"></i> Deliverable Orders</h1>

                <div class="filter-section">
                    <div class="filter-group">
                        <label for="statusFilter" class="filter-label">Status:</label>
                        <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                            <option value="">All Deliverables</option>
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
                            (<?= $auto_transit_count ?> auto-updated)
                        <?php elseif (isset($auto_transit_count) && $auto_transit_count < 0): ?>
                            <span style="color: red; margin-left: 5px;">(Error updating)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search PO, User, Driver...">
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
                        <th class="sort-header <?= $sortColumn == 'total_amount' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="total_amount" style="text-align: right;">Total Amount</th>
                        <th>Instructions</th>
                        <th>Driver</th>
                        <th class="sort-header <?= $sortColumn == 'status' ? ($sortOrder == 'ASC' ? 'asc' : 'desc') : '' ?>" data-column="status" style="text-align: center;">Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order):
                            $isDeliveryDay = ($order['delivery_date'] === $current_date);
                            $statusClass = ($order['status'] === 'For Delivery') ? 'status-for-delivery' : 'status-in-transit';
                            $statusText = htmlspecialchars($order['status']);
                        ?>
                            <tr class="order-row" data-status="<?= $statusText ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td class="<?= $isDeliveryDay ? 'today-delivery' : '' ?>">
                                    <?= htmlspecialchars($order['delivery_date']) ?>
                                    <?= $isDeliveryDay ? ' <span class="today-tag">(Today)</span>' : '' ?>
                                </td>
                                <td style="white-space: normal; min-width: 150px;"><?= htmlspecialchars($order['delivery_address']) ?></td>
                                <td style="text-align: center;">
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-receipt"></i> View
                                    </button>
                                </td>
                                <td style="text-align: right;">PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            <i class="fas fa-comment-alt"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['driver_assigned'] && !empty($order['driver_name'])): ?>
                                        <div class="driver-badge">
                                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                        </div>
                                        <button class="driver-btn" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>', <?= intval($order['driver_id']) ?>, '<?= htmlspecialchars(addslashes($order['driver_name'])) ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change
                                        </button>
                                    <?php else: ?>
                                        <span class="no-driver" style="color: #dc3545; font-style: italic; font-size: 12px;">No driver</span>
                                        <!-- Optionally add an Assign button if needed -->
                                        <!-- <button class="driver-btn assign" onclick="openDriverModal('<?= htmlspecialchars($order['po_number']) ?>', 0, '')"><i class="fas fa-user-plus"></i> Assign</button> -->
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td class="action-buttons-cell">
                                    <?php if ($order['status'] === 'For Delivery'): ?>
                                        <button class="toggle-transit-btn" onclick="openStatusChangeModal('<?= htmlspecialchars($order['po_number']) ?>', 'In Transit')">
                                            <i class="fas fa-truck"></i> Mark In Transit
                                        </button>
                                    <?php else: // Status is 'In Transit' ?>
                                        <button class="toggle-transit-btn mark-for-delivery" onclick="openStatusChangeModal('<?= htmlspecialchars($order['po_number']) ?>', 'For Delivery')">
                                            <i class="fas fa-warehouse"></i> Mark For Delivery
                                        </button>
                                    <?php endif; ?>

                                    <button class="complete-delivery-btn" onclick="openCompleteModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars(addslashes($order['username'])) ?>')">
                                        <i class="fas fa-check-circle"></i> Mark Completed
                                    </button>

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
                                        <i class="fas fa-file-pdf"></i> Download PO
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
                                    No orders currently ready for delivery or in transit.
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

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="overlay" onclick="if (event.target === this) closeOrderDetailsModal();">
         <div class="overlay-content">
             <h2><i class="fas fa-receipt"></i> Order Details</h2>
             <div class="order-details-container">
                 <table class="order-details-table">
                     <thead>
                         <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: right;">Quantity</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                     </thead>
                     <tbody id="orderDetailsBody">
                         <!-- Order details populated by JS -->
                     </tbody>
                 </table>
             </div>
             <div class="form-buttons">
                 <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                     <i class="fas fa-times"></i> Close
                 </button>
             </div>
         </div>
     </div>

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="overlay" onclick="if (event.target === this) closeDriverModal();">
        <div class="overlay-content driver-modal-content">
            <h2><i class="fas fa-user-edit"></i> <span id="driverModalTitle">Change Driver</span></h2>
            <p id="driverModalMessage"></p>
            <div class="driver-selection">
                <label for="driverSelect">Select New Driver:</label>
                <select id="driverSelect" name="driver_id"> <!-- Added name attribute -->
                    <option value="0">-- Select a driver --</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="driver-modal-buttons">
                <button type="button" class="cancel-btn" onclick="closeDriverModal()"> <!-- Changed to type="button" -->
                    <i class="fas fa-times"></i> Cancel
                </button>
                <!-- Changed onclick to open confirmation -->
                <button type="button" class="save-btn" onclick="confirmDriverChange()"> <!-- Changed to type="button" -->
                    <i class="fas fa-save"></i> Change Driver
                </button>
            </div>
        </div>
    </div>

    <!-- Driver Change Confirmation Modal -->
    <div id="driverConfirmationModal" class="confirmation-modal overlay" onclick="if (event.target === this) closeDriverConfirmation();">
         <div class="overlay-content">
             <h2><i class="fas fa-user-check"></i> Confirm Driver Change</h2>
             <div class="modal-message" id="driverConfirmMessage">
                 Are you sure you want to change the driver?
             </div>
             <div class="modal-buttons">
                 <button class="btn-no" onclick="closeDriverConfirmation()">
                     <i class="fas fa-times"></i> No
                 </button>
                 <!-- This button will trigger the actual assignment -->
                 <button id="confirmDriverAssignBtn" class="btn-yes">
                     <i class="fas fa-check"></i> Yes, Change
                 </button>
             </div>
         </div>
     </div>

    <!-- Status Change Confirmation Modal -->
    <div id="statusChangeModal" class="confirmation-modal overlay" onclick="if (event.target === this) closeStatusChangeModal();">
        <div class="overlay-content">
            <h2><i id="statusIcon" class="fas fa-question-circle"></i> <span id="statusModalTitle">Confirm Status Change</span></h2>
            <div class="modal-message" id="statusModalMessage">
                Are you sure?
            </div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeStatusChangeModal()">
                    <i class="fas fa-times"></i> No
                </button>
                <!-- This button triggers the actual status toggle -->
                <button id="confirmStatusChangeBtn" class="btn-yes">
                    <i class="fas fa-check"></i> Yes
                </button>
            </div>
        </div>
    </div>

    <!-- Complete Order Confirmation Modal -->
    <div id="completeOrderModal" class="confirmation-modal overlay" onclick="if (event.target === this) closeCompleteModal();">
        <div class="overlay-content">
            <h2><i class="fas fa-check-double"></i> Confirm Completion</h2>
            <div class="modal-message" id="completeModalMessage">
                Are you sure you want to mark this delivery as completed?
            </div>
            <div class="modal-buttons">
                <button class="btn-no" onclick="closeCompleteModal()">
                    <i class="fas fa-times"></i> No
                </button>
                <!-- This button triggers the actual completion -->
                <button id="confirmCompleteOrderBtn" class="btn-yes">
                    <i class="fas fa-check"></i> Yes, Complete
                </button>
            </div>
        </div>
    </div>

    <!-- Special Instructions Modal -->
    <div id="specialInstructionsModal" class="instructions-modal overlay" onclick="if (event.target === this) closeSpecialInstructions();">
         <div class="overlay-content instructions-modal-content">
             <div class="instructions-header">
                 <h3><i class="fas fa-comment-alt"></i> Special Instructions</h3>
                 <div class="instructions-po-number" id="instructionsPoNumber">PO: ...</div>
             </div>
             <div class="instructions-body" id="instructionsContent">
                 Loading...
             </div>
             <div class="instructions-footer">
                 <button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button>
             </div>
         </div>
     </div>

    <!-- PDF Preview Modal -->
    <div id="pdfPreview" class="overlay" onclick="if (event.target === this) closePDFPreview();">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()" aria-label="Close PDF Preview"><i class="fas fa-times"></i></button>
            <div id="pdfRenderArea">
                <!-- Content is copied here before rendering for preview -->
            </div>
            <div class="pdf-actions">
                <button class="download-pdf-btn" onclick="downloadPDF()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
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


    <script>
        // Global variables to store context for confirmation modals
        let currentPoNumber = '';
        let currentDriverId = 0;
        let targetDriverId = 0; // Store selected driver before confirmation
        let targetStatus = ''; // Store target status before confirmation
        let currentPOData = null; // For PDF generation

        // --- Toast Function ---
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i>
                    <div class="message">${message}</div>
                </div>
                <i class="fas fa-times close" onclick="this.parentElement.classList.remove('show'); setTimeout(() => { if(this.parentElement) this.parentElement.remove(); }, 400);"></i>`; // Close button
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('show'); }, 10);
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => { if(toast.parentElement) toast.remove(); });
                setTimeout(() => { if(toast.parentElement) toast.remove(); }, 500); // Fallback removal
            }, 4000); // Show for 4 seconds
        }

        // --- Filtering and Sorting ---
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const url = new URL(window.location.href);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }

        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                const url = new URL(window.location.href);
                const currentOrder = url.searchParams.get('order') || 'ASC'; // Default ASC
                let newOrder = 'ASC';

                if (url.searchParams.get('sort') === column && currentOrder === 'ASC') {
                    newOrder = 'DESC';
                }

                url.searchParams.set('sort', column);
                url.searchParams.set('order', newOrder);
                window.location.href = url.toString();
            });
        });

        // --- Status Change ---
        function openStatusChangeModal(poNumber, newStatus) {
            currentPoNumber = poNumber;
            targetStatus = newStatus; // Store target status

            const modalTitle = document.getElementById('statusModalTitle');
            const modalMessage = document.getElementById('statusModalMessage');
            const statusIcon = document.getElementById('statusIcon');
            const confirmBtn = document.getElementById('confirmStatusChangeBtn'); // Target the correct button

            if (newStatus === 'In Transit') {
                modalTitle.textContent = 'Confirm: Mark as In Transit';
                modalMessage.innerHTML = `Change order <strong>${poNumber}</strong> status to <span class="status-pill in-transit">In Transit</span>?`;
                statusIcon.className = 'fas fa-truck'; // Update icon
            } else { // For Delivery
                modalTitle.textContent = 'Confirm: Mark as For Delivery';
                modalMessage.innerHTML = `Change order <strong>${poNumber}</strong> status to <span class="status-pill for-delivery">For Delivery</span>?`;
                statusIcon.className = 'fas fa-warehouse'; // Update icon
            }

            // Set the action for the "Yes" button in this specific modal
            confirmBtn.onclick = executeToggleTransitStatus; // Call the execution function

            document.getElementById('statusChangeModal').style.display = 'flex';
        }

        function closeStatusChangeModal() {
            document.getElementById('statusChangeModal').style.display = 'none';
            currentPoNumber = '';
            targetStatus = ''; // Clear target status
        }

        // Executes the status toggle AFTER confirmation
        function executeToggleTransitStatus() {
            if (!currentPoNumber || !targetStatus) return;

            const poNumber = currentPoNumber;
            const newStatus = targetStatus;

            closeStatusChangeModal(); // Close the confirmation modal first
            showToast(`Updating status for ${poNumber}...`, 'info');

            // **Assume backend endpoint exists and handles status updates**
            fetch('/backend/update_order_status.php', { // Using the general status update endpoint
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: poNumber,
                    status: newStatus,
                    // No material flags needed for these transitions
                    deduct_materials: '0',
                    return_materials: '0'
                 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Order ${poNumber} status updated to ${newStatus}`, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showToast(`Error updating status: ${data.message || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                console.error('Status toggle error:', error);
                showToast('Network error updating status.', 'error');
            });
        }


        // --- Order Completion ---
        function openCompleteModal(poNumber, username) {
            currentPoNumber = poNumber; // Store PO for confirmation

            const modalMessage = document.getElementById('completeModalMessage');
            modalMessage.innerHTML = `Mark order <strong>${poNumber}</strong> for <strong>${username}</strong> as completed?`;

            // Set the action for the "Yes" button in this specific modal
            document.getElementById('confirmCompleteOrderBtn').onclick = executeCompleteDelivery;

            document.getElementById('completeOrderModal').style.display = 'flex';
        }

        function closeCompleteModal() {
            document.getElementById('completeOrderModal').style.display = 'none';
            currentPoNumber = ''; // Clear PO
        }

        // Executes the completion AFTER confirmation
        function executeCompleteDelivery() {
            if (!currentPoNumber) return;

            const poNumber = currentPoNumber;
            closeCompleteModal(); // Close confirmation modal first
            showToast(`Completing delivery for ${poNumber}...`, 'info');

            // **Assume backend endpoint exists and handles setting status to 'Completed'**
            fetch('/backend/update_order_status.php', { // Using the general status update endpoint
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    po_number: poNumber,
                    status: 'Completed',
                    // No material flags needed for this transition
                    deduct_materials: '0',
                    return_materials: '0'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Order ${poNumber} marked as Completed`, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showToast(`Error completing order: ${data.message || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                console.error('Completion error:', error);
                showToast('Network error completing order.', 'error');
            });
        }

        // --- Driver Change ---
        function openDriverModal(poNumber, driverId, driverName) {
            currentPoNumber = poNumber;
            currentDriverId = driverId; // Store the *current* driver ID

            document.getElementById('driverModalTitle').textContent = driverName ? 'Change Driver' : 'Assign Driver';
            document.getElementById('driverModalMessage').textContent = driverName ? `Current driver: ${driverName}. Select new driver:` : `Assign driver for PO: ${poNumber}`;

            const driverSelect = document.getElementById('driverSelect');
            driverSelect.value = driverId; // Pre-select current driver (or 0 if none)

            // Reset save button state in case it was disabled previously
             const saveBtn = document.querySelector('#driverModal .save-btn');
             saveBtn.innerHTML = '<i class="fas fa-save"></i> Change Driver';
             saveBtn.disabled = false;


            document.getElementById('driverModal').style.display = 'flex';
        }

        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
            currentPoNumber = '';
            currentDriverId = 0;
        }

        // Opens the DRIVER confirmation modal
        function confirmDriverChange() {
            targetDriverId = parseInt(document.getElementById('driverSelect').value); // Get selected driver ID
            const selectedDriverName = $("#driverSelect option:selected").text();

            if (targetDriverId === 0) {
                showToast('Please select a driver first.', 'error');
                return;
            }
            if (targetDriverId === currentDriverId) {
                 showToast('Selected driver is already assigned.', 'info');
                 return;
            }

            // Prepare confirmation message
            const confirmMsg = document.getElementById('driverConfirmMessage');
            confirmMsg.innerHTML = `Are you sure you want to assign driver <strong>${selectedDriverName}</strong> to order <strong>${currentPoNumber}</strong>?`;

            // Set the action for the "Yes" button in the confirmation modal
            document.getElementById('confirmDriverAssignBtn').onclick = executeAssignDriver;

            // Hide the selection modal and show the confirmation modal
            document.getElementById('driverModal').style.display = 'none';
            document.getElementById('driverConfirmationModal').style.display = 'flex';
        }

         function closeDriverConfirmation() {
             document.getElementById('driverConfirmationModal').style.display = 'none';
             // Optionally reopen the selection modal if needed:
             // document.getElementById('driverModal').style.display = 'flex';
             targetDriverId = 0; // Clear target
         }

        // Executes the driver assignment AFTER confirmation
        function executeAssignDriver() {
            if (targetDriverId === 0 || !currentPoNumber) {
                console.error("Missing target driver or PO number for assignment.");
                return;
            }

            const driverIdToAssign = targetDriverId; // Use the stored target ID
            const poNumberForAssign = currentPoNumber;

            closeDriverConfirmation(); // Close the confirmation modal
            showToast(`Assigning driver to ${poNumberForAssign}...`, 'info');

            // Assume backend endpoint exists
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
                console.error('Assign driver error:', error);
                showToast('Network error assigning driver.', 'error');
            });
        }


        // --- View Order Details ---
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            const modal = document.getElementById('orderDetailsModal');
            modal.querySelector('h2').innerHTML = `<i class="fas fa-receipt"></i> Order Details (${poNumber})`; // Update title
            orderDetailsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            modal.style.display = 'flex';

            fetch(`/backend/get_order_items.php?po_number=${encodeURIComponent(poNumber)}`)
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                orderDetailsBody.innerHTML = ''; // Clear loading/previous
                if (data && data.success && data.orderItems) {
                    let parsedItems = data.orderItems;
                    // Check if orderItems is a JSON string and parse it
                    if (typeof parsedItems === 'string') {
                        try { parsedItems = JSON.parse(parsedItems); }
                        catch (e) { throw new Error("Invalid order items JSON format."); }
                    }
                    // Ensure it's an array
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
                    totalRow.innerHTML = `<td colspan="5" style="text-align: right; border-top: 2px solid #adb5bd;">Grand Total:</td><td style="text-align: right; border-top: 2px solid #adb5bd;">PHP ${grandTotal.toFixed(2)}</td>`;
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

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        // --- Special Instructions ---
        function viewSpecialInstructions(poNumber, instructions) {
            document.getElementById('instructionsPoNumber').textContent = 'PO: ' + (poNumber || 'N/A');
            const contentEl = document.getElementById('instructionsContent');
            if (instructions && instructions.trim().length > 0) {
                contentEl.textContent = instructions; // Display raw text
                contentEl.classList.remove('empty');
            } else {
                contentEl.textContent = 'No special instructions provided.';
                contentEl.classList.add('empty');
            }
            document.getElementById('specialInstructionsModal').style.display = 'flex'; // Show using flex
        }

        function closeSpecialInstructions() {
            document.getElementById('specialInstructionsModal').style.display = 'none';
        }

        // --- PDF Functions ---
        function populatePdfData(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
             // 1. Populate the hidden div used by html2pdf
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
             body.innerHTML = ''; // Clear previous items
             if (!Array.isArray(items)) throw new Error("Order items JSON is not an array.");

             items.forEach(item => {
                 const price = parseFloat(item.price) || 0;
                 const qty = parseInt(item.quantity) || 0;
                 const total = price * qty;
                 const row = `<tr><td>${item.category||''}</td><td>${item.item_description||''}</td><td>${item.packaging||''}</td><td style="text-align: right;">${qty}</td><td style="text-align: right;">PHP ${price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td style="text-align: right;">PHP ${total.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td></tr>`;
                 body.innerHTML += row; // Append row HTML
             });
        }

        function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            console.log("Requesting direct PDF download for:", poNumber);
            try {
                populatePdfData(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions);

                const element = document.getElementById('contentToDownload');
                const opt = { margin: [10, 10, 10, 10], filename: `PO_${poNumber || 'UnknownPO'}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true, logging: false }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };

                showToast(`Generating PDF for ${poNumber}...`, 'info');
                html2pdf().set(opt).from(element).save()
                .then(() => { console.log("Direct PDF Download successful for:", poNumber); })
                .catch(error => { console.error(`Error generating direct PDF for ${poNumber}:`, error); showToast('Error generating PDF.', 'error'); });

            } catch (e) {
                console.error(`Error preparing direct PDF data for ${poNumber}:`, e);
                showToast(`Error preparing PDF data: ${e.message}`, 'error');
            }
        }

        // Shows the PDF Preview Modal
        function generatePO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
             console.log("Generating PDF preview for:", poNumber);
             try {
                 // Store data for potential download later
                 currentPOData = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions };
                 // Populate the *hidden* div first
                 populatePdfData(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions);
                 // Copy the *inner* HTML of the hidden content to the visible preview area
                 document.getElementById('pdfRenderArea').innerHTML = document.getElementById('contentToDownload').innerHTML;
                 // Show the preview modal
                 document.getElementById('pdfPreview').style.display = 'flex'; // Use flex
             } catch (e) {
                 console.error('Error preparing PDF preview data:', e);
                 showToast(`Error preparing PDF preview: ${e.message}`, 'error');
             }
         }

         function closePDFPreview() {
             document.getElementById('pdfPreview').style.display = 'none';
             document.getElementById('pdfRenderArea').innerHTML = ''; // Clear preview area
             currentPOData = null; // Clear stored data
         }

         // Downloads PDF from the preview modal's data
         function downloadPDF() {
             if (!currentPOData) {
                 showToast('No PO data available for download.', 'error');
                 return;
             }
             // We need to re-populate the hidden div just before download
             // as the preview area might not be perfectly identical for html2pdf
             try {
                  populatePdfData(
                      currentPOData.poNumber, currentPOData.username, currentPOData.company,
                      currentPOData.orderDate, currentPOData.deliveryDate, currentPOData.deliveryAddress,
                      currentPOData.ordersJson, currentPOData.totalAmount, currentPOData.specialInstructions
                  );

                  const element = document.getElementById('contentToDownload'); // Target the hidden div
                  const opt = { margin: [10, 10, 10, 10], filename: `PO_${currentPOData.poNumber || 'UnknownPO'}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true, logging: false }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };

                  showToast(`Generating PDF for ${currentPOData.poNumber}...`, 'info');
                  html2pdf().set(opt).from(element).save()
                  .then(() => {
                      console.log("PDF Download from preview successful for:", currentPOData.poNumber);
                      closePDFPreview(); // Close preview after download starts
                  })
                  .catch(error => {
                      console.error(`Error generating PDF from preview for ${currentPOData.poNumber}:`, error);
                      showToast('Error generating PDF.', 'error');
                  });
             } catch (e) {
                  console.error(`Error preparing PDF data for download from preview:`, e);
                  showToast(`Error preparing PDF data: ${e.message}`, 'error');
             }
         }


        // --- Document Ready / Event Listeners ---
        $(document).ready(function() {
            // Search functionality
            $("#searchInput").on("input", function() {
                let searchText = $(this).val().toLowerCase().trim();
                $(".order-row").each(function() {
                    $(this).toggle($(this).text().toLowerCase().includes(searchText));
                });
            });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input")); // Trigger input event on button click

             // Make tables scroll horizontally on small screens if needed
             if (window.innerWidth < 768) {
                 const tableContainer = document.querySelector('.orders-table-container');
                 if (tableContainer) tableContainer.style.overflowX = 'auto';
                 // Add for other tables if necessary
             }
        });

    </script>
</body>
</html>