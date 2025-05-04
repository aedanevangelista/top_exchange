<?php
// Based on commit: e801e509bf62bd6fdb304d4fb4a20d6b43b2ecb6
// Modifications: Removed driver logic, implemented 100% progress -> For Delivery status change, implemented quantity limit 100.

session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

// --- Default sort by 'id' descending ---
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// --- Allowed columns (Removed driver-related fields) ---
$allowed_columns = ['id', 'po_number', 'username', 'order_date', 'delivery_date', 'progress', 'total_amount', 'status']; // Added 'status'
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'id'; // Default sort column if invalid input
}

// Validate sort direction
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC'; // Default to descending
}

// Fetch active clients for the dropdown
$clients = [];
$clients_with_company_address = [];
$clients_with_company = [];
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

// REMOVED: Fetch all drivers PHP block

// Query to show Pending, Rejected, and Active orders *under* 100% progress (REMOVED driver joins)
$orders = [];
$sql = "SELECT o.id, o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.progress,
        o.company, o.special_instructions
        FROM orders o
        WHERE o.status IN ('Pending', 'Rejected')
        OR (o.status = 'Active' AND o.progress < 100)"; // Only show Active orders NOT yet at 100% progress

// Construct the ORDER BY clause
$orderByClause = $sort_column;
if (in_array($sort_column, ['id', 'po_number', 'username', 'order_date', 'delivery_date', 'progress', 'total_amount', 'status'])) {
    $orderByClause = 'o.' . $sort_column;
}
$sql .= " ORDER BY {$orderByClause} {$sort_direction}";


$stmt = $conn->prepare($sql);
if ($stmt === false) {
     die('Prepare failed after correction: ' . htmlspecialchars($conn->error) . ' - SQL: ' . $sql);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();

// Helper function to generate sort URL
function getSortUrl($column, $currentColumn, $currentDirection) {
    $newDirection = ($column === $currentColumn && $currentDirection === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=" . urlencode($column) . "&direction=" . urlencode($newDirection);
}

// Helper function to display sort icon
function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column === 'id') return ''; // Assuming no 'id' header
    if ($column !== $currentColumn) return '<i class="fas fa-sort"></i>';
    return ($currentDirection === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* --- Styles exactly as provided in commit e801e50... --- */
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
            overflow: auto; /* Allow scrolling on the modal background if content is very tall */
            background-color: rgba(0, 0, 0, 0.7);
        }

        .instructions-modal-content {
            background-color: #ffffff;
            margin: auto;
            padding: 0;
            border-radius: 8px;
            width: 60%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-in-out;
            max-height: 80vh; /* Adjusted max-height */
            display: flex; /* Use flexbox for layout */
            flex-direction: column; /* Stack header, body, footer */
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
            border-top-left-radius: 8px; /* Round top corners */
            border-top-right-radius: 8px;
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
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f8f9fa;
            flex-grow: 1; /* Allow body to take up available space */
            overflow-y: auto; /* Add scroll if content exceeds max-height */
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
            border-bottom-left-radius: 8px; /* Round bottom corners */
            border-bottom-right-radius: 8px;
            border-top: 1px solid #eee; /* Add separator line */
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

        .status-delivery { /* Keep style for potential display */
            background-color: #e2e3e5;
            color: #383d41;
        }

        .status-completed { /* Keep style for potential display */
            background-color: #d1e7dd;
            color: #0f5132;
        }

        /* REMOVED driver-badge.driver-not-allowed style */

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
        #cartModal .overlay-content { /* Remove driverModal */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 90vh;
            overflow-y: auto;
            margin: 0;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 80%;
            max-width: 800px;
        }

        /* Adjust Status Modal content max height and scrolling */
        #statusModal .modal-content,
        #pendingStatusModal .modal-content,
        #rejectedStatusModal .modal-content {
             max-height: 85vh;
             overflow-y: auto;
        }

        /* --- Keep remaining styles from commit e801e50... --- */
        .main-content { padding: 20px; margin-left: 250px; /* Adjust if sidebar width changes */ transition: margin-left 0.3s; }
        .orders-table-container { width: 100%; overflow-x: auto; }
        .orders-table { width: 100%; min-width: 1200px; /* Ensure minimum width */ border-collapse: collapse; }
        .orders-table th, .orders-table td { padding: 10px 12px; border: 1px solid #dee2e6; text-align: left; font-size: 13px; vertical-align: middle; white-space: nowrap; }
        .orders-table thead th { background-color:rgb(34, 34, 34); font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .orders-table tbody tr:hover { background-color: #f1f3f5; }
        .orders-table th.sortable a { color: inherit; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
        .orders-table th.sortable a i { margin-left: 5px; color: #adb5bd; }
        .orders-table th.sortable a:hover i { color: #343a40; }
        .action-buttons { display: flex; gap: 5px; justify-content: center; }
        /* REMOVED driver-btn from shared styles */
        .action-buttons button, .view-orders-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 3px; }
        .view-orders-btn { background-color: #0dcaf0; color: white; }
        .view-orders-btn:hover { background-color: #0aa3bf; }
        .status-btn { background-color: #ffc107; color: black; }
        .status-btn:hover { background-color: #e0a800; }
        /* REMOVED driver-btn specific styles */
        /* REMOVED driver-badge styles */
        .progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 0.25rem; overflow: hidden; position: relative; height: 20px; }
        .progress-bar { background-color: #0d6efd; height: 100%; line-height: 20px; color: white; text-align: center; white-space: nowrap; transition: width .6s ease; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 20px; color: #000; font-size: 12px; font-weight: bold; }
        .order-details-table { width: 100%; border-collapse: collapse; }
        .order-details-table th, .order-details-table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        .order-details-table thead th { background-color: #f8f9fa; }
        .overlay { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); }
        .overlay-content { position: relative; margin: 10% auto; padding: 20px; background: #fff; border-radius: 8px; width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .modal { display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; display: flex; flex-direction: column; }
        .modal-footer { padding-top: 15px; text-align: right; border-top: 1px solid #e5e5e5; margin-top: 15px; }
        .modal-cancel-btn { background-color: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .modal-status-btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 5px; flex-grow: 1; text-align: center; }
        .modal-status-btn.delivery { background-color: #0dcaf0; color: white; }
        .modal-status-btn.pending { background-color: #ffc107; color: black; }
        .modal-status-btn.rejected { background-color: #dc3545; color: white; }
        .modal-status-btn.active { background-color: #198754; color: white; }
        .modal-status-btn:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .status-buttons { display: flex; justify-content: space-around; margin-top: 15px; }
        /* REMOVED driver-modal-content styles */
        /* REMOVED driver-selection styles */
        /* REMOVED driver-modal-buttons styles */
        .no-orders td { text-align: center; padding: 20px; color: #6c757d; font-style: italic; }
        .item-progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 4px; overflow: hidden; height: 16px; margin-top: 5px; position: relative; }
        .item-progress-bar { background-color: #198754; height: 100%; transition: width .3s ease; }
        .item-progress-text { position: absolute; width: 100%; text-align: center; line-height: 16px; color: #000; font-size: 10px; font-weight: bold; }
        .item-contribution-text { font-size: 9px; color: #6c757d; text-align: center; margin-top: 2px; }
        .status-cell { vertical-align: middle; }
        .completed-item { background-color: #f0fff0 !important; }
        .completed { background-color: #e0ffe0 !important; }
        .expand-units-btn { background: none; border: none; cursor: pointer; color: #0d6efd; padding: 0 5px; }
        .unit-row td { font-size: 0.9em; padding: 4px 8px 4px 30px; background-color: #fdfdfd; }
        .unit-action-row td { text-align: right; padding: 10px; background-color: #f8f9fa; }
        .unit-action-row button { font-size: 0.8em; padding: 3px 6px; margin-left: 5px; }
        .units-divider td { border: none; padding: 2px 0; background-color: #e9ecef; height: 2px; }
        .overlay-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; }
        .overlay-title { margin: 0; font-size: 1.5rem; }
        .cart-btn { background-color: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .cart-btn i { margin-right: 5px; }
        .cart-table-container { max-height: 400px; overflow-y: auto; margin-bottom: 15px; }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th, .cart-table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        .cart-table th { background-color: #f8f9fa; }
        .form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .cancel-btn, .back-btn { background-color: #6c757d; color: white; }
        .save-btn, .confirm-btn { background-color: #4a90e2; color: white; }
        .cancel-btn, .back-btn, .save-btn, .confirm-btn { border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; }
        .cancel-btn:hover, .back-btn:hover { background-color: #5a6268; }
        .save-btn:hover, .confirm-btn:hover { background-color: #357abf; }
        .order-form .left-section { width: 100%; }
        .order-form label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .order-form input[type="text"], .order-form select, .order-form textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .order-form input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .centered-button { text-align: center; margin-bottom: 15px; }
        .open-inventory-btn { background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .open-inventory-btn:hover { background-color: #218838; }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Orders</h1>
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
                        <th class="sortable"><a href="<?= getSortUrl('po_number', $sort_column, $sort_direction) ?>">PO Number <?= getSortIcon('po_number', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('username', $sort_column, $sort_direction) ?>">Username <?= getSortIcon('username', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('order_date', $sort_column, $sort_direction) ?>">Order Date <?= getSortIcon('order_date', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('progress', $sort_column, $sort_direction) ?>">Progress <?= getSortIcon('progress', $sort_column, $sort_direction) ?></a></th>
                        <th>Orders</th>
                        <th class="sortable"><a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?></a></th>
                        <th>Special Instructions</th>
                        <!-- REMOVED Drivers Column Header -->
                        <th class="sortable"><a href="<?= getSortUrl('status', $sort_column, $sort_direction) ?>">Status <?= getSortIcon('status', $sort_column, $sort_direction) ?></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr data-current-status="<?= htmlspecialchars($order['status']) ?>">
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td>
                                    <?php // Only show progress bar for Active (and now < 100%) orders
                                    if ($order['status'] === 'Active'): ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                    <?php else: ?>
                                        <span class="status-badge <?= strtolower($order['status']) ?>-progress"><?= $order['status'] === 'Pending' ? 'Pending' : 'Not Available' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php // Only allow editing progress for Active orders
                                    if ($order['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')"><i class="fas fa-clipboard-list"></i> View</button>
                                    <?php else: ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order['orders'])) ?>', '<?= htmlspecialchars($order['status']) ?>')"><i class="fas fa-info-circle"></i> Info</button>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')"><i class="fas fa-comment-alt"></i> View</button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <!-- REMOVED Drivers Column Data -->
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($order['status']) {
                                        case 'Active': $statusClass = 'status-active'; break;
                                        case 'Pending': $statusClass = 'status-pending'; break;
                                        case 'Rejected': $statusClass = 'status-rejected'; break;
                                        // Keep other statuses for display if needed
                                        case 'For Delivery': $statusClass = 'status-delivery'; break;
                                        case 'Completed': $statusClass = 'status-completed'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>', 'Pending')"><i class="fas fa-sync-alt"></i> Status</button>
                                    <?php elseif ($order['status'] === 'Active'): // Progress < 100% is handled by SQL query now ?>
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', 'Active')"><i class="fas fa-sync-alt"></i> Status</button>
                                    <?php elseif ($order['status'] === 'Rejected'): ?>
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', 'Rejected')"><i class="fas fa-sync-alt"></i> Status</button>
                                    <?php endif; ?>
                                    <button class="download-btn" onclick="confirmDownloadPO('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>', '<?= htmlspecialchars($order['company'] ?? '') ?>', '<?= htmlspecialchars($order['order_date']) ?>', '<?= htmlspecialchars($order['delivery_date']) ?>', '<?= htmlspecialchars($order['delivery_address']) ?>', '<?= htmlspecialchars(addslashes($order['orders'])) ?>', '<?= htmlspecialchars($order['total_amount']) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'] ?? '')) ?>')"><i class="fas fa-download"></i> PO</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Adjust colspan to account for removed column -->
                        <tr><td colspan="10" class="no-orders">No orders found matching criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- PO PDF Preview Section (Keep as is) -->
    <div id="pdfPreview" style="display: none;">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <div class="po-container">
                     <div class="po-header"><div class="po-company" id="printCompany"></div><div class="po-title">Purchase Order</div></div>
                     <div class="po-details">
                         <div class="po-left"><div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div><div class="po-detail-row"><span class="po-detail-label">Customer:</span> <span id="printUsername"></span></div><div class="po-detail-row"><span class="po-detail-label">Delivery Address:</span> <span id="printDeliveryAddress"></span></div></div>
                         <div class="po-right"><div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div><div class="po-detail-row"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div></div>
                     </div>
                     <div id="printInstructionsSection" style="margin-bottom: 20px; display: none;"><strong>Special Instructions:</strong><div id="printSpecialInstructions" style="white-space: pre-wrap; border: 1px dashed #ccc; padding: 5px;"></div></div>
                     <table class="po-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead><tbody id="printOrderItems"></tbody></table>
                     <div class="po-total">Total Amount: PHP <span id="printTotalAmount"></span></div>
                      <div class="po-signature"><div class="po-signature-block"><div class="po-signature-line"></div>Prepared By</div><div class="po-signature-block"><div class="po-signature-line"></div>Approved By</div></div>
                </div>
            </div>
            <div class="pdf-actions"><button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button></div>
        </div>
    </div>

    <!-- Special Instructions Modal (Keep as is) -->
    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content"><div class="instructions-header"><h3>Special Instructions</h3><div class="instructions-po-number" id="instructionsPoNumber"></div></div><div class="instructions-body" id="instructionsContent"></div><div class="instructions-footer"><button class="close-instructions-btn" onclick="closeSpecialInstructions()"><i class="fas fa-times"></i> Close</button></div></div>
    </div>

    <!-- Order Details Modal (Keep as is) -->
    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details (<span id="orderStatus"></span>)</h2>
             <div id="overall-progress-info" style="margin-bottom: 15px; display: none;"><strong>Overall Progress:</strong><div class="progress-bar-container" style="margin-top: 5px;"><div class="progress-bar" id="overall-progress-bar" style="width: 0%;"></div><div class="progress-text" id="overall-progress-text">0%</div></div></div>
            <div class="order-details-container"><table class="order-details-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th id="status-header-cell">Progress / Status</th></tr></thead><tbody id="orderDetailsBody"></tbody></table></div>
             <div class="order-details-footer"><div class="total-amount" id="orderTotalAmount">Total: PHP 0.00</div></div>
            <div class="form-buttons"><button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="save-progress-btn" onclick="confirmSaveProgress()" style="display: none;"><i class="fas fa-save"></i> Save Progress</button></div>
        </div>
    </div>

    <!-- Status Modal (for Active Orders) - REMOVED 'For Delivery' button -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="statusMessage"></p>
            <div class="status-buttons">
                <!-- Removed 'For Delivery' button -->
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject<div class="btn-info">(Return stock)</div></button>
            </div>
            <div class="modal-footer"><button onclick="closeStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Rejected Status Modal (Keep as is) -->
    <div id="rejectedStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="rejectedStatusMessage"></p>
            <div class="status-buttons"><button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return to pending state)</div></button></div>
            <div class="modal-footer"><button onclick="closeRejectedStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Pending Status Modal (Keep as is) -->
    <div id="pendingStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="pendingStatusMessage"></p>
            <div id="rawMaterialsContainer" class="raw-materials-container"><h3>Loading inventory status...</h3></div>
            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active" disabled><i class="fas fa-check"></i> Active<div class="btn-info">(Deduct stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject</button>
            </div>
            <div class="modal-footer"><button onclick="closePendingStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- REMOVED Driver Assignment Modal HTML -->

    <!-- Add New Order Overlay (Keep as is) -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form">
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
                    <label for="order_date">Order Date:</label> <input type="text" id="order_date" name="order_date" readonly>
                    <label for="delivery_date">Delivery Date:</label> <input type="text" id="delivery_date" name="delivery_date" autocomplete="off" required>
                    <label for="delivery_address_type">Delivery Address:</label><select id="delivery_address_type" name="delivery_address_type" onchange="toggleDeliveryAddress()"> <option value="company" selected>Company Address</option><option value="custom">Custom Address</option></select>
                    <div id="company_address_container"><input type="text" id="company_address" name="company_address" readonly placeholder="Company address"></div>
                    <div id="custom_address_container" style="display: none;"><textarea id="custom_address" name="custom_address" rows="3" placeholder="Enter delivery address"></textarea></div>
                    <input type="hidden" name="delivery_address" id="delivery_address">
                    <label for="special_instructions_textarea">Special Instructions:</label> <textarea id="special_instructions_textarea" name="special_instructions" rows="3" placeholder="Enter any special requests or delivery notes"></textarea>
                    <div class="centered-button"><button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()"><i class="fas fa-box-open"></i> Select Products</button></div>
                    <div class="order-summary"><h3>Order Summary</h3><table class="summary-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody id="summaryBody"><tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products</td></tr></tbody><tfoot><tr><td colspan="6" class="summary-total">Total: <span class="summary-total-amount">PHP 0.00</span></td></tr></tfoot></table></div>
                    <input type="hidden" name="po_number" id="po_number">
                    <input type="hidden" name="orders" id="orders">
                    <input type="hidden" name="total_amount" id="total_amount">
                    <input type="hidden" name="company_hidden" id="company_hidden">
                </div>
                <div class="form-buttons"><button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="save-btn" onclick="confirmAddOrder()"><i class="fas fa-save"></i> Add Order</button></div>
            </form>
        </div>
    </div>

    <!-- Confirmation modals (Keep as is, except driver confirmation) -->
    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Add Order</div><div class="confirmation-message">Add this new order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No</button><button class="confirm-yes" onclick="submitAddOrder()">Yes</button></div></div></div>
    <!-- REMOVED Driver Confirmation Modal HTML -->
    <div id="saveProgressConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Save Progress</div><div class="confirmation-message">Save the current progress for this order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeSaveProgressConfirmation()">No</button><button class="confirm-yes" onclick="saveProgressChanges()">Yes</button></div></div></div>
    <div id="statusConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Status Change</div><div class="confirmation-message" id="statusConfirmationMessage">Are you sure?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeStatusConfirmation()">No</button><button class="confirm-yes" onclick="executeStatusChange()">Yes</button></div></div></div>
    <div id="downloadConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Download</div><div class="confirmation-message">Download the PO PDF?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDownloadConfirmation()">No</button><button class="confirm-yes" onclick="downloadPODirectly()">Yes</button></div></div></div>

    <!-- Inventory Overlay (Keep as is) -->
    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
             <div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2><button class="cart-btn" onclick="window.openCartModal()"><i class="fas fa-shopping-cart"></i> View Cart (<span id="cartItemCount">0</span>)</button></div>
             <div class="inventory-filter-section"><input type="text" id="inventorySearch" placeholder="Search..."><select id="inventoryFilter"><option value="all">All Categories</option></select></div>
             <div class="inventory-table-container"><table class="inventory-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="inventory"></tbody></table></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="back-btn" onclick="closeInventoryOverlay()"><i class="fas fa-check"></i> Done</button></div>
        </div>
    </div>

    <!-- Cart Modal (Keep as is) -->
    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
             <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
             <div class="cart-table-container"><table class="cart-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="cart"><tr class="no-products"><td colspan="6" style="text-align: center; padding: 20px; color: #6c757d;">No products selected</td></tr></tbody></table></div>
             <div class="cart-total" style="text-align: right; margin-bottom: 20px; font-weight: bold; font-size: 1.1em;">Total: <span class="total-amount">PHP 0.00</span></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeCartModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="save-btn" onclick="saveCartChanges()"><i class="fas fa-check"></i> Confirm Selections</button></div>
        </div>
    </div>

    <script>
        // --- Global Variables (Removed currentDriverId) ---\
        let currentPoNumber = '';
        let currentOrderOriginalStatus = '';
        let currentOrderItems = [];
        let completedItems = [];
        let quantityProgressData = {};
        let itemProgressPercentages = {};
        let itemContributions = {};
        let overallProgress = 0;
        let currentPOData = null;
        let selectedStatus = '';
        let poDownloadData = null;
        let cartItems = [];

        // --- Utility Functions (Keep as is) ---\
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<div class="toast-content"><i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i><div class="message"><div class="title">${type.charAt(0).toUpperCase() + type.slice(1)}</div><div>${message}</div></div></div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }
        function formatWeight(weightInGrams) {
            if (weightInGrams >= 1000) return (weightInGrams / 1000).toFixed(2) + ' kg';
            return (weightInGrams ? parseFloat(weightInGrams).toFixed(2) : '0.00') + ' g';
        }

        // --- Status Change Logic (Modified executeStatusChange) ---\
        function confirmStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);
            $('#statusModal').css('display', 'flex');
        }
        function confirmRejectedStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#rejectedStatusModal').data('po_number', poNumber);
            $('#rejectedStatusMessage').text(`Change status for rejected order ${poNumber} (${username})`);
            $('#rejectedStatusModal').css('display', 'flex');
        }
        function confirmPendingStatusChange(poNumber, username, ordersJson, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#pendingStatusModal').data('po_number', poNumber);
            $('#pendingStatusMessage').text('Change order status for ' + poNumber);
            const materialContainer = $('#rawMaterialsContainer');
            materialContainer.html('<h3>Loading inventory status...</h3>');
            $('#pendingStatusModal').css('display', 'flex');
            try {
                 if (!ordersJson) throw new Error("Order items data missing.");
                 if (ordersJson.length > 5 && !ordersJson.includes('"product_id":')) { console.warn("Received ordersJson might be missing product_id:", ordersJson.substring(0, 100) + "..."); throw new Error("Order data seems incomplete or corrupted. It might be missing essential product information. Please try adding the order again. If the problem persists, contact support."); }
                 JSON.parse(ordersJson);
                $.ajax({
                    url: '/backend/check_raw_materials.php', type: 'POST',
                    data: { orders: ordersJson, po_number: poNumber }, dataType: 'json',
                    success: function(response) {
                        console.log("Inventory Check (Pending):", response);
                        if (response.success) {
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer');
                            if (needsMfg && response.materials) { displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section'); }
                            else if (needsMfg) { $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Info unavailable.</p>'); }
                            else if (!needsMfg && response.finishedProducts) { materialContainer.append('<p>All required products in stock.</p>'); $('#rawMaterialsContainer #raw-materials-section').remove(); }
                            else if (!response.finishedProducts && !response.materials) { materialContainer.html('<h3>Inventory Status</h3><p>No details available.</p>'); }
                            updatePendingOrderActionStatus(response);
                        } else { materialContainer.html(`<h3>Inventory Check Error</h3><p style="color:red;">${response.message || 'Unknown error'}</p><p>Status change allowed, but check failed.</p>`); $('#activeStatusBtn').prop('disabled', false); }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText); let errorMsg = `Could not check inventory: ${error}`;
                        if (status === 'parsererror') { errorMsg = `Could not check inventory: Invalid response from server. Check console for details.`; }
                        materialContainer.html(`<h3>Server Error</h3><p style="color:red;">${errorMsg}</p><p>Status change allowed, but check failed.</p>`); $('#activeStatusBtn').prop('disabled', false);
                    }
                });
            } catch (e) { materialContainer.html(`<h3>Data Error</h3><p style="color:red;">${e.message}</p><p>Status change allowed, but check failed.</p>`); $('#activeStatusBtn').prop('disabled', false); console.error("Error processing data for pending status change:", e); }
        }
        function confirmStatusAction(status) {
            selectedStatus = status;
            let confirmationMsg = `Are you sure you want to change the status to ${selectedStatus}?`;
            if (selectedStatus === 'Active') { confirmationMsg += ' This will deduct required stock from inventory.'; }
            else if (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')) { confirmationMsg += ' This will attempt to return deducted stock to inventory.'; }
            // REMOVED 'For Delivery' check here, handled by saveProgressChanges
            $('#statusConfirmationMessage').text(confirmationMsg);
            $('#statusConfirmationModal').css('display', 'block');
            $('#statusModal, #pendingStatusModal, #rejectedStatusModal').css('display', 'none');
        }
        function closeStatusConfirmation() {
            $('#statusConfirmationModal').css('display', 'none');
             if (currentOrderOriginalStatus === 'Pending') $('#pendingStatusModal').css('display', 'flex');
             else if (currentOrderOriginalStatus === 'Rejected') $('#rejectedStatusModal').css('display', 'flex');
             else if (currentOrderOriginalStatus === 'Active') $('#statusModal').css('display', 'flex');
             selectedStatus = '';
        }
        // Modified to remove 'For Delivery' check here
        function executeStatusChange() {
            $('#statusConfirmationModal').css('display', 'none');
            let deductMaterials = (selectedStatus === 'Active');
            let returnMaterials = (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected'));
            // Directly call updateOrderStatus for Pending/Rejected/Active changes
            updateOrderStatus(selectedStatus, deductMaterials, returnMaterials);
        }
        // --- CORRECTED updateOrderStatus (Sends progress=100 for 'For Delivery') ---
        function updateOrderStatus(status, deductMaterials, returnMaterials) {
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);
            formData.append('deduct_materials', deductMaterials ? '1' : '0');
            formData.append('return_materials', returnMaterials ? '1' : '0');

            // If updating TO 'For Delivery', explicitly send progress=100
            // This relies on the backend handling this parameter
            if (status === 'For Delivery') {
                 formData.append('progress', '100');
            }

            console.log("Sending status update:", Object.fromEntries(formData)); // Log form data

            fetch('/backend/update_order_status.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => { // Get text first
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
                    if (deductMaterials) message += '. Inventory deduction initiated.';
                    if (returnMaterials) message += '. Inventory return initiated.';
                    showToast(message, 'success');
                    // Reload page to reflect changes (order might disappear or status changes)
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else { throw new Error(data.message || 'Unknown error updating status.'); }
            })
            .catch(error => {
                console.error("Update status fetch error:", error);
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => { closeRelevantStatusModals(); }); // Close modals and clear state
        }
        // --- END CORRECTED updateOrderStatus ---


        // --- Modal Closing Helpers (Keep as is) ---\
        function closeStatusModal() { $('#statusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number'); }
        function closePendingStatusModal() { $('#pendingStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        // --- Material Display Helpers (Keep as is) ---\
        function displayFinishedProducts(productsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return false;
             let html = `<h3>Finished Products Status</h3>`;
             if (!productsData || Object.keys(productsData).length === 0) { html += '<p>No finished product information available.</p>'; container.html(html).append('<div id="raw-materials-section"></div>'); return true; }
             html += `<table class="materials-table"><thead><tr><th>Product</th><th>In Stock</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(productsData).forEach(product => {
                 const data = productsData[product];
                 const available = parseInt(data.available) || 0; const required = parseInt(data.required) || 0; const isSufficient = data.sufficient; const shortfall = data.shortfall || 0;
                 html += `<tr><td>${product}</td><td>${available}</td><td>${required}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'In Stock' : `Shortfall: ${shortfall}`}</td></tr>`;
             });
             html += `</tbody></table>`; container.html(html);
             const needsMfg = Object.values(productsData).some(p => !p.sufficient);
             if (needsMfg) container.append('<div id="raw-materials-section"><h3>Raw Materials Required</h3><p>Loading...</p></div>');
             return needsMfg;
        }
        function displayRawMaterials(materialsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return true;
             let html = '<h3>Raw Materials Required</h3>';
             if (!materialsData || Object.keys(materialsData).length === 0) { container.html(html + '<p>No raw materials information available.</p>'); return true; }
             let allSufficient = true; let insufficient = [];
             html += `<table class="materials-table"><thead><tr><th>Material</th><th>Available</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(materialsData).forEach(material => {
                 const data = materialsData[material]; const available = parseFloat(data.available) || 0; const required = parseFloat(data.required) || 0; const isSufficient = data.sufficient;
                 if (!isSufficient) { allSufficient = false; insufficient.push(material); }
                 html += `<tr><td>${material}</td><td>${formatWeight(available)}</td><td>${formatWeight(required)}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'Sufficient' : 'Insufficient'}</td></tr>`;
             });
             html += `</tbody></table>`;
             const msg = allSufficient ? 'All raw materials are sufficient.' : `Insufficient raw materials: ${insufficient.join(', ')}.`;
             const cls = allSufficient ? 'status-sufficient' : 'status-insufficient';
             container.html(html + `<p class="materials-status ${cls}">${msg}</p>`);
             return allSufficient;
        }
        function updatePendingOrderActionStatus(response) {
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
             else if (Object.keys(prods).length === 0 && !response.needsManufacturing) { msg = 'Inventory details unclear.'; /* Allow activation attempt */ }

             $('#activeStatusBtn').prop('disabled', !canActivate);
             let statEl = cont.children('.materials-status');
             const cls = canActivate ? 'status-sufficient' : 'status-insufficient';
             if (statEl.length) statEl.removeClass('status-sufficient status-insufficient').addClass(cls).text(msg);
             else cont.append(`<p class="materials-status ${cls}">${msg}</p>`);
        }

        // --- Order Details Modal Functions (Keep as is) ---\
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            fetch(`/backend/get_order_details.php?po_number=${poNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentOrderItems = data.orderItems; completedItems = data.completedItems || []; quantityProgressData = data.quantityProgressData || {}; itemProgressPercentages = data.itemProgressPercentages || {};
                    const orderDetailsBody = $('#orderDetailsBody').empty(); $('#status-header-cell').show(); $('#orderStatus').text('Active');
                    const totalItemsCount = currentOrderItems.length; itemContributions = {}; let calculatedOverallProgress = 0;
                    currentOrderItems.forEach((item, index) => {
                        const isCompletedByCheckbox = completedItems.includes(index); const itemQuantity = parseInt(item.quantity) || 0;
                        const contributionPerItem = totalItemsCount > 0 ? (100 / totalItemsCount) : 0; itemContributions[index] = contributionPerItem;
                        let unitCompletedCount = 0; if (quantityProgressData[index]) { for (let i = 0; i < itemQuantity; i++) if (quantityProgressData[index][i] === true) unitCompletedCount++; }
                        const unitProgress = itemQuantity > 0 ? (unitCompletedCount / itemQuantity) * 100 : (isCompletedByCheckbox ? 100 : 0); itemProgressPercentages[index] = unitProgress;
                        const contributionToOverall = (unitProgress / 100) * contributionPerItem; calculatedOverallProgress += contributionToOverall;
                        const mainRow = $('<tr>').addClass('item-header-row').toggleClass('completed-item', isCompletedByCheckbox || unitProgress === 100).attr('data-item-index', index);
                        mainRow.html(`<td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td>PHP ${parseFloat(item.price).toFixed(2)}</td><td>${item.quantity}</td>
                            <td class="status-cell"><div style="display: flex; align-items: center; justify-content: space-between;"><input type="checkbox" class="item-status-checkbox" data-index="${index}" onchange="updateRowStyle(this)" ${isCompletedByCheckbox ? 'checked' : ''}> <button class="expand-units-btn" onclick="toggleQuantityProgress(${index})" style="${itemQuantity > 0 ? '' : 'display:none;'}"><i class="fas fa-chevron-down"></i></button></div>
                            ${itemQuantity > 0 ? `<div class="item-progress-bar-container"><div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div><div class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}% Complete</div></div><div class="item-contribution-text" id="contribution-text-${index}">Contributes: ${contributionPerItem.toFixed(1)}%</div>` : 'N/A'}
                            </td>`);
                        orderDetailsBody.append(mainRow);
                        if (itemQuantity > 0) {
                             const dividerRow = $('<tr>').addClass('units-divider').attr('id', `units-divider-${index}`).hide().html(`<td colspan="6" style="border: none; padding: 2px 0; background-color: #e9ecef;"></td>`);
                             orderDetailsBody.append(dividerRow);
                             for (let i = 0; i < itemQuantity; i++) { const isUnitCompleted = quantityProgressData[index] && quantityProgressData[index][i] === true; const unitRow = $('<tr>').addClass(`unit-row unit-for-item-${index}`).toggleClass('completed', isUnitCompleted).hide().html(`<td colspan="5" style="padding-left: 30px;">Unit ${i + 1}</td><td style="text-align: center;"><input type="checkbox" class="unit-status-checkbox" data-item-index="${index}" data-unit-index="${i}" onchange="updateUnitStatus(this)" ${isUnitCompleted ? 'checked' : ''}></td>`); orderDetailsBody.append(unitRow); }
                             const actionRow = $('<tr>').addClass(`unit-row unit-action-row unit-for-item-${index}`).hide().html(`<td colspan="6" style="text-align: right; padding: 10px;"><button onclick="selectAllUnits(${index}, ${itemQuantity})">Select All</button> <button onclick="deselectAllUnits(${index}, ${itemQuantity})">Deselect All</button></td>`); orderDetailsBody.append(actionRow);
                        }
                    });
                    overallProgress = calculatedOverallProgress; updateOverallProgressDisplay();
                    let totalAmount = currentOrderItems.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0); $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);
                    $('#overall-progress-info, .save-progress-btn').show(); $('#orderDetailsModal').css('display', 'flex');
                } else { showToast('Error fetching details: ' + data.message, 'error'); }
            })
            .catch(error => { showToast('Error fetching details: ' + error, 'error'); console.error('Fetch order details error:', error); });
        }
        function viewOrderInfo(ordersJson, orderStatus) {
             try {
                 const orderDetails = JSON.parse(ordersJson); const body = $('#orderDetailsBody').empty(); $('#status-header-cell').hide(); $('#orderStatus').text(orderStatus); let total = 0;
                 orderDetails.forEach(p => { total += parseFloat(p.price) * parseInt(p.quantity); body.append(`<tr><td>${p.category||''}</td><td>${p.item_description}</td><td>${p.packaging||''}</td><td>PHP ${parseFloat(p.price).toFixed(2)}</td><td>${p.quantity}</td></tr>`); });
                 $('#orderTotalAmount').text(`PHP ${total.toFixed(2)}`); $('#overall-progress-info, .save-progress-btn').hide(); $('#orderDetailsModal').css('display', 'flex');
             } catch (e) { console.error('Parse error:', e); showToast('Error displaying info', 'error'); }
        }
        function toggleQuantityProgress(itemIndex) { $(`.unit-for-item-${itemIndex}, #units-divider-${itemIndex}`).toggle(); }
        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.dataset.itemIndex); const unitIndex = parseInt(checkbox.dataset.unitIndex); const isChecked = checkbox.checked; $(checkbox).closest('tr').toggleClass('completed', isChecked);
            if (!quantityProgressData[itemIndex]) { quantityProgressData[itemIndex] = []; for (let i = 0; i < (parseInt(currentOrderItems[itemIndex].quantity)||0); i++) quantityProgressData[itemIndex][i] = false; }
            quantityProgressData[itemIndex][unitIndex] = isChecked; updateItemProgress(itemIndex); updateOverallProgress();
        }
        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex]; const qty = parseInt(item.quantity) || 0; if (qty === 0) return; let completed = 0;
            for (let i = 0; i < qty; i++) if (quantityProgressData[itemIndex] && quantityProgressData[itemIndex][i]) completed++;
            const progress = (completed / qty) * 100; itemProgressPercentages[itemIndex] = progress; const contribution = (progress / 100) * itemContributions[itemIndex];
            $(`#item-progress-bar-${itemIndex}`).css('width', `${progress}%`); $(`#item-progress-text-${itemIndex}`).text(`${Math.round(progress)}% Complete`); $(`#contribution-text-${itemIndex}`).text(`Contributes: ${contribution.toFixed(1)}%`);
            updateItemStatusBasedOnUnits(itemIndex, completed === qty);
        }
        function updateOverallProgressDisplay() { const rounded = Math.round(overallProgress); $('#overall-progress-bar').css('width', `${rounded}%`); $('#overall-progress-text').text(`${rounded}%`); console.log("Overall Progress Updated:", overallProgress); }
        function updateOverallProgress() {
            let newProgress = 0; Object.keys(itemProgressPercentages).forEach(idx => { const prog = itemProgressPercentages[idx]; const contrib = itemContributions[idx]; if (prog !== undefined && contrib !== undefined) newProgress += (prog / 100) * contrib; });
            overallProgress = newProgress; updateOverallProgressDisplay(); return Math.round(overallProgress);
        }
        function updateItemStatusBasedOnUnits(itemIndex, allComplete) {
            const intIndex = parseInt(itemIndex); $(`tr[data-item-index="${intIndex}"]`).toggleClass('completed-item', allComplete); $(`.item-status-checkbox[data-index="${intIndex}"]`).prop('checked', allComplete);
            const idxInArray = completedItems.indexOf(intIndex); if (allComplete && idxInArray === -1) completedItems.push(intIndex); else if (!allComplete && idxInArray > -1) completedItems.splice(idxInArray, 1);
        }
        function selectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', true); checkboxes.closest('tr').addClass('completed');
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = []; for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = true;
            updateItemProgress(itemIndex); updateOverallProgress();
        }
        function deselectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', false); checkboxes.closest('tr').removeClass('completed');
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = []; for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = false;
            updateItemProgress(itemIndex); updateOverallProgress();
        }
        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.dataset.index); const isChecked = checkbox.checked; const qty = parseInt(currentOrderItems[index].quantity) || 0; $(checkbox).closest('tr').toggleClass('completed-item', isChecked);
            const intIndex = parseInt(index); const idxInArray = completedItems.indexOf(intIndex); if (isChecked && idxInArray === -1) completedItems.push(intIndex); else if (!isChecked && idxInArray > -1) completedItems.splice(idxInArray, 1);
            const unitCheckboxes = $(`.unit-status-checkbox[data-item-index="${index}"]`).prop('checked', isChecked); unitCheckboxes.closest('tr').toggleClass('completed', isChecked);
            if (!quantityProgressData[index]) quantityProgressData[index] = []; for (let i = 0; i < qty; i++) quantityProgressData[index][i] = isChecked;
            itemProgressPercentages[index] = isChecked ? 100 : 0; const contribution = (itemProgressPercentages[index] / 100) * itemContributions[index];
            $(`#item-progress-bar-${index}`).css('width', `${itemProgressPercentages[index]}%`); $(`#item-progress-text-${index}`).text(`${Math.round(itemProgressPercentages[index])}% Complete`); $(`#contribution-text-${index}`).text(`Contributes: ${contribution.toFixed(1)}%`);
            updateOverallProgress();
        }
        function closeOrderDetailsModal() { $('#orderDetailsModal').css('display', 'none'); }
        function confirmSaveProgress() { $('#saveProgressConfirmationModal').css('display', 'block'); }
        function closeSaveProgressConfirmation() { $('#saveProgressConfirmationModal').css('display', 'none'); }

        // --- CORRECTED saveProgressChanges (Handles 100% progress status change) ---
        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').hide();
            const finalProgress = updateOverallProgress(); // Calculate final progress percentage

            if (finalProgress === 100) {
                // If progress is 100%, trigger status update to 'For Delivery'
                showToast('Progress reached 100%. Updating status to For Delivery...', 'info');
                // Call updateOrderStatus directly - no material changes needed Active -> For Delivery
                // The backend update_order_status.php must also set progress=100
                updateOrderStatus('For Delivery', false, false);
            } else {
                // If progress is less than 100%, just update the progress details via the dedicated endpoint
                fetch('/backend/update_order_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        po_number: currentPoNumber,
                        completed_items: completedItems,
                        quantity_progress: quantityProgressData,
                        overall_progress: finalProgress // Send the calculated progress
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Progress updated successfully', 'success');
                        // Reload to reflect the updated progress bar in the main table
                        setTimeout(() => { window.location.reload(); }, 1000);
                    } else {
                        showToast('Error saving progress: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error saving progress: ' + error, 'error');
                    console.error('Save progress error:', error);
                });
            }
        }
        // --- END CORRECTED saveProgressChanges ---

        // --- REMOVED Driver Assignment Modal Functions ---

        // --- PDF Download Functions (Keep as is) ---\
        function confirmDownloadPO(...args) {
             poDownloadData = { poNumber: args[0], username: args[1], company: args[2], orderDate: args[3], deliveryDate: args[4], deliveryAddress: args[5], ordersJson: args[6], totalAmount: args[7], specialInstructions: args[8] };
             console.log("Data for PDF:", poDownloadData);
             $('#downloadConfirmationModal .confirmation-message').text(`Download PO ${poDownloadData.poNumber}?`);
             $('#downloadConfirmationModal').show();
         }
        function closeDownloadConfirmation() { $('#downloadConfirmationModal').hide(); poDownloadData = null; }
        function downloadPODirectly() {
            $('#downloadConfirmationModal').hide(); if (!poDownloadData) { showToast('No data for PO download', 'error'); return; }
            try {
                currentPOData = poDownloadData;
                $('#printCompany').text(currentPOData.company || 'N/A'); $('#printPoNumber').text(currentPOData.poNumber); $('#printUsername').text(currentPOData.username); $('#printDeliveryAddress').text(currentPOData.deliveryAddress); $('#printOrderDate').text(currentPOData.orderDate); $('#printDeliveryDate').text(currentPOData.deliveryDate); $('#printTotalAmount').text(parseFloat(currentPOData.totalAmount).toFixed(2));
                const instrSec = $('#printInstructionsSection');
                if (currentPOData.specialInstructions && currentPOData.specialInstructions.trim()) { $('#printSpecialInstructions').text(currentPOData.specialInstructions); instrSec.show(); } else { instrSec.hide(); }
                const items = JSON.parse(currentPOData.ordersJson); const body = $('#printOrderItems').empty();
                items.forEach(item => { const total = parseFloat(item.price) * parseInt(item.quantity); if (item.category !== undefined && item.item_description !== undefined && item.packaging !== undefined && item.quantity !== undefined && item.price !== undefined) { body.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td>${item.quantity}</td><td>PHP ${parseFloat(item.price).toFixed(2)}</td><td>PHP ${total.toFixed(2)}</td></tr>`); } else { console.warn("Skipping incomplete item in PDF:", item); } });
                const element = document.getElementById('contentToDownload'); const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
                html2pdf().set(opt).from(element).save().then(() => { showToast(`PO downloaded.`, 'success'); currentPOData = null; poDownloadData = null; }).catch(e => { console.error('PDF generation error:', e); showToast('PDF generation error', 'error'); currentPOData = null; poDownloadData = null; });
            } catch (e) { console.error('PDF preparation error:', e); showToast('PDF data error: ' + e.message, 'error'); currentPOData = null; poDownloadData = null; }
        }
        function downloadPDF() { if (!currentPOData) { showToast('No data', 'error'); return; } const element = document.getElementById('contentToDownload'); const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}_Preview.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }; html2pdf().set(opt).from(element).save(); }
        function closePDFPreview() { $('#pdfPreview').hide(); currentPOData = null; }

        // --- Special Instructions Modal (Keep as is) ---\
        function viewSpecialInstructions(poNumber, instructions) { $('#instructionsPoNumber').text('PO: ' + poNumber); const content = $('#instructionsContent'); if (instructions && instructions.trim()) { content.text(instructions).removeClass('empty'); } else { content.text('No special instructions provided.').addClass('empty'); } $('#specialInstructionsModal').css('display', 'flex'); }
        function closeSpecialInstructions() { $('#specialInstructionsModal').hide(); }

        // --- Add New Order Form Functions (Keep as is, except quantity limits) ---\
        function initializeDeliveryDatePicker() { if ($.datepicker) { $("#delivery_date").datepicker("destroy"); $("#delivery_date").datepicker({ dateFormat: 'yy-mm-dd', minDate: 1, beforeShowDay: function(date) { const day = date.getDay(); const isSelectable = (day === 1 || day === 3 || day === 5); return [isSelectable, isSelectable ? "" : "ui-state-disabled", isSelectable ? "" : "Not available"]; } }); } else { console.error("jQuery UI Datepicker not loaded."); } }
        function openAddOrderForm() { $('#addOrderForm')[0].reset(); cartItems = []; updateOrderSummary(); updateCartItemCount(); const today = new Date(); const fmtDate = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`; $('#order_date').val(fmtDate); $('#delivery_date').val(''); initializeDeliveryDatePicker(); toggleDeliveryAddress(); generatePONumber(); $('#addOrderOverlay').css('display', 'flex'); }
        function closeAddOrderForm() { $('#addOrderOverlay').hide(); }
        function toggleDeliveryAddress() { const type = $('#delivery_address_type').val(); const isCompany = type === 'company'; $('#company_address_container').toggle(isCompany); $('#custom_address_container').toggle(!isCompany); $('#delivery_address').val(isCompany ? $('#company_address').val() : $('#custom_address').val()); }
        $('#custom_address').on('input', function() { if ($('#delivery_address_type').val() === 'custom') $('#delivery_address').val($(this).val()); });
        function generatePONumber() { const userSelect = $('#username'); const username = userSelect.val(); const companyHiddenInput = $('#company_hidden'); const companyAddressInput = $('#company_address'); if (username) { const selectedOption = userSelect.find('option:selected'); const companyAddress = selectedOption.data('company-address') || ''; const companyName = selectedOption.data('company') || ''; companyAddressInput.val(companyAddress); companyHiddenInput.val(companyName); if ($('#delivery_address_type').val() === 'company') { $('#delivery_address').val(companyAddress); } const today = new Date(); const datePart = `${today.getFullYear().toString().substr(-2)}${String(today.getMonth()+1).padStart(2,'0')}${String(today.getDate()).padStart(2,'0')}`; const timePart = `${String(today.getHours()).padStart(2,'0')}${String(today.getMinutes()).padStart(2,'0')}${String(today.getSeconds()).padStart(2,'0')}`; $('#po_number').val(`${datePart}-${timePart}`); console.log("Generated PO, Set Company Hidden:", companyName); } else { companyAddressInput.val(''); companyHiddenInput.val(''); $('#po_number').val(''); if ($('#delivery_address_type').val() === 'company') { $('#delivery_address').val(''); } } }
        function prepareOrderData() { toggleDeliveryAddress(); const addr = $('#delivery_address').val(); const userSelect = $('#username'); const companyName = userSelect.find('option:selected').data('company') || ''; $('#company_hidden').val(companyName); console.log("Company Hidden Value before validation:", $('#company_hidden').val()); if (cartItems.length === 0) { showToast('Please select products.', 'error'); return false; } if (!userSelect.val()) { showToast('Please select a user.', 'error'); return false; } if (!$('#delivery_date').val()) { showToast('Please select a delivery date.', 'error'); return false; } if (!addr) { showToast('Please enter a delivery address.', 'error'); return false; } let total = 0; const orders = cartItems.map(item => { total += item.price * item.quantity; return { product_id: item.product_id, category: item.category, item_description: item.item_description, packaging: item.packaging, price: item.price, quantity: item.quantity }; }); $('#orders').val(JSON.stringify(orders)); $('#total_amount').val(total.toFixed(2)); console.log("Prepared Orders JSON:", $('#orders').val()); console.log("Prepared Company Hidden:", $('#company_hidden').val()); return true; }
        function confirmAddOrder() { if (prepareOrderData()) $('#addConfirmationModal').show(); }
        function closeAddConfirmation() { $('#addConfirmationModal').hide(); }
        function submitAddOrder() { $('#addConfirmationModal').hide(); const form = document.getElementById('addOrderForm'); const fd = new FormData(form); console.log("Submitting FormData - Company Hidden:", fd.get('company_hidden')); console.log("Submitting FormData - Orders:", fd.get('orders')); fetch('/backend/add_order.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) { showToast('Order added!', 'success'); closeAddOrderForm(); setTimeout(() => { window.location.reload(); }, 1500); } else { showToast('Error: ' + (d.message || 'Failed to add order'), 'error'); } }).catch(e => { console.error("Add order error:", e); showToast('Network error adding order.', 'error'); }); }

        // --- Inventory Overlay and Cart Functions (Added Quantity Limits) ---
        function openInventoryOverlay() { $('#inventoryOverlay').css('display', 'flex'); const body = $('.inventory').html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory...</td></tr>'); fetch('/backend/get_inventory.php').then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.text().then(t => { try { return JSON.parse(t); } catch (e) { console.error("Inv JSON Parse Error:", e, "Response:", t); throw new Error("Invalid inventory data format received."); } }); }).then(data => { console.log("Received Inventory data:", data); if (Array.isArray(data)) { const cats = [...new Set(data.map(i => i.category).filter(c => c))]; populateInventory(data); populateCategories(cats); filterInventory(); } else { throw new Error("Unexpected data format"); } }).catch(e => { console.error('Inv fetch:', e); showToast('Inv fetch error: ' + e.message, 'error'); body.html(`<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">Error loading inventory. Check console.</td></tr>`); }); }
        // --- CORRECTED populateInventory (Added max="100") ---
        function populateInventory(inventory) {
            const body = $('.inventory').empty(); if (!inventory || inventory.length === 0) { body.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No items</td></tr>'); return; }
            inventory.forEach(item => {
                const price = parseFloat(item.price);
                if (isNaN(price) || item.product_id === undefined || item.product_id === null) {
                    console.warn("Skipping item due to invalid price or missing product_id:", item);
                    return;
                }
                // Added max="100" to the input field
                body.append(`<tr><td>${item.category||'Uncategorized'}</td><td>${item.item_description}</td><td>${item.packaging||'N/A'}</td><td>PHP ${price.toFixed(2)}</td><td><input type="number" class="inventory-quantity" value="1" min="1" max="100"></td><td><button class="add-to-cart-btn" onclick="addToCart(this, ${item.product_id}, '${item.category||''}', '${item.item_description}', '${item.packaging||''}', ${price})"><i class="fas fa-plus"></i> Add</button></td></tr>`);
             });
        }
        // --- END CORRECTED populateInventory ---
        function populateCategories(categories) { const sel = $('#inventoryFilter'); sel.find('option:not(:first-child)').remove(); if (!categories || categories.length === 0) return; categories.sort().forEach(c => { if (c) sel.append(`<option value="${c}">${c}</option>`); }); sel.off('change', filterInventory).on('change', filterInventory); }
        function filterInventory() { const cat = $('#inventoryFilter').val(); const search = $('#inventorySearch').val().toLowerCase(); $('.inventory tr').each(function() { const row = $(this); if (row.find('th').length > 0 || (row.find('td').length === 1 && row.find('td').attr('colspan') === '6')) return; const cCell = row.find('td:first-child').text(); const text = row.text().toLowerCase(); const catMatch = (cat === 'all' || cCell === cat); const searchMatch = (text.includes(search)); row.toggle(catMatch && searchMatch); }); }
        $('#inventorySearch').off('input', filterInventory).on('input', filterInventory);
        function closeInventoryOverlay() { $('#inventoryOverlay').hide(); }
        // --- CORRECTED addToCart (Enforces 100 limit) ---
        function addToCart(button, productId, category, itemDesc, packaging, price) {
            const qtyInput = $(button).closest('tr').find('.inventory-quantity');
            let qty = parseInt(qtyInput.val()); // Use let to allow modification

            // --- ADDED: Check if quantity exceeds 100 ---
            if (qty > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                qty = 100; // Correct the quantity to 100
                qtyInput.val(100); // Update the input field visually
            }
            // --- END ADDED Check ---

            if (isNaN(qty) || qty < 1) { // Check if qty is valid *after* potential correction
                showToast('Quantity must be at least 1.', 'error');
                qtyInput.val(1); return;
            }

            const idx = cartItems.findIndex(i => i.product_id === productId && i.packaging === packaging);

            if (idx >= 0) {
                 // Item exists, increase quantity, but check combined total doesn't exceed 100
                 let newQty = cartItems[idx].quantity + qty;
                 if (newQty > 100) {
                     showToast(`Cannot add ${qty}. Total quantity for ${itemDesc} would exceed 100. Setting total to 100.`, 'warning');
                     cartItems[idx].quantity = 100; // Cap at 100
                 } else {
                     cartItems[idx].quantity = newQty;
                 }
            } else {
                // Item does not exist, add new item (already capped qty at 100 above)
                cartItems.push({ product_id: productId, category, item_description: itemDesc, packaging, price, quantity: qty });
            }

            console.log("Cart Items after add:", cartItems);
            // Show updated total in cart
            showToast(`Added ${itemDesc} (Total in cart: ${cartItems.find(i => i.product_id === productId && i.packaging === packaging)?.quantity || qty})`, 'success');
            qtyInput.val(1); // Reset quantity input in inventory list
            updateOrderSummary();
            updateCartItemCount();
        }
        // --- END CORRECTED addToCart ---
        // --- CORRECTED updateOrderSummary (Added max="100") ---
        function updateOrderSummary() {
            const body = $('#summaryBody').empty(); let total = 0;
            if (cartItems.length === 0) { body.html('<tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products</td></tr>'); }
            else { cartItems.forEach((item, index) => {
                total += item.price * item.quantity;
                // Added max="100" to the input field
                body.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td>PHP ${item.price.toFixed(2)}</td><td><input type="number" class="cart-quantity summary-quantity" value="${item.quantity}" min="1" max="100" data-index="${index}" onchange="updateSummaryItemQuantity(this)"></td><td><button class="remove-item-btn" onclick="removeSummaryItem(${index})"><i class="fas fa-trash"></i></button></td></tr>`);
             }); }
            $('.summary-total-amount').text(`PHP ${total.toFixed(2)}`);
        }
        // --- CORRECTED updateSummaryItemQuantity (Enforces 100 limit) ---
        function updateSummaryItemQuantity(input) {
            const idx = parseInt($(input).data('index'));
            let qty = parseInt($(input).val()); // Use let

            // --- ADDED: Check if quantity exceeds 100 ---
            if (qty > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                qty = 100; // Correct the quantity
                $(input).val(100); // Update the input field visually
            }
            // --- END ADDED Check ---

            if (isNaN(qty) || qty < 1) { // Check if valid *after* potential correction
                showToast('Quantity must be at least 1.', 'error');
                $(input).val(cartItems[idx].quantity); // Revert to original cart quantity if invalid
                return;
            }
            cartItems[idx].quantity = qty; // Update cart item with corrected quantity
            updateOrderSummary(); // Refresh summary table (will use corrected value)
            updateCartItemCount();
            updateCartDisplay(); // Update cart modal too if open
        }
        // --- END CORRECTED updateSummaryItemQuantity ---
        function removeSummaryItem(index) { if (index >= 0 && index < cartItems.length) { const removed = cartItems.splice(index, 1)[0]; showToast(`Removed ${removed.item_description}`, 'info'); updateOrderSummary(); updateCartItemCount(); updateCartDisplay(); } }
        function updateCartItemCount() { $('#cartItemCount').text(cartItems.length); }
        window.openCartModal = function() { $('#cartModal').css('display', 'flex'); updateCartDisplay(); }
        function closeCartModal() { $('#cartModal').hide(); }
        function updateCartDisplay() { const body = $('.cart').empty(); const msg = $('.no-products'); const totalEl = $('.total-amount'); let total = 0; if (cartItems.length === 0) { msg.show(); totalEl.text('PHP 0.00'); return; } msg.hide(); cartItems.forEach((item, index) => { total += item.price * item.quantity; body.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td>PHP ${item.price.toFixed(2)}</td><td><input type="number" class="cart-quantity" value="${item.quantity}" min="1" max="100" data-index="${index}" onchange="updateCartItemQuantity(this)"></td><td><button class="remove-item-btn" onclick="removeCartItem(${index})"><i class="fas fa-trash"></i></button></td></tr>`); }); totalEl.text(`PHP ${total.toFixed(2)}`); }
        // --- CORRECTED updateCartItemQuantity (Enforces 100 limit in Cart Modal) ---
        function updateCartItemQuantity(input) {
            const idx = parseInt($(input).data('index'));
            let qty = parseInt($(input).val()); // Use let

            if (qty > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                qty = 100; // Correct the quantity
                $(input).val(100); // Update the input field visually
            }

            if (isNaN(qty) || qty < 1) {
                showToast('Quantity must be 1-100.', 'error'); // Corrected message
                $(input).val(cartItems[idx].quantity); // Revert to original cart quantity if invalid
                return;
            }
            cartItems[idx].quantity = qty;
            updateCartDisplay(); // Update total in cart modal
            updateOrderSummary(); // Also update summary in main form
        }
        // --- END CORRECTED updateCartItemQuantity ---
        function removeCartItem(index) { if (index >= 0 && index < cartItems.length) { const removed = cartItems.splice(index, 1)[0]; showToast(`Removed ${removed.item_description}`, 'info'); updateCartDisplay(); updateCartItemCount(); updateOrderSummary();} }
        function saveCartChanges() { updateOrderSummary(); closeCartModal(); }

        // --- Document Ready (Removed driver modal event listener) ---\
        $(document).ready(function() {
            $("#searchInput").on("input", function() { const search = $(this).val().toLowerCase().trim(); $(".orders-table tbody tr").each(function() { $(this).toggle($(this).text().toLowerCase().includes(search)); }); });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input"));
            initializeDeliveryDatePicker();
            toggleDeliveryAddress();
            generatePONumber();
            window.addEventListener('click', function(event) {
                 if ($(event.target).hasClass('instructions-modal')) closeSpecialInstructions();
                 if ($(event.target).hasClass('confirmation-modal')) { $(event.target).hide(); if (event.target.id === 'statusConfirmationModal') closeStatusConfirmation(); /* Removed driver confirmation close */ if (event.target.id === 'saveProgressConfirmationModal') closeSaveProgressConfirmation(); if (event.target.id === 'addConfirmationModal') closeAddConfirmation(); if (event.target.id === 'downloadConfirmationModal') closeDownloadConfirmation(); }
                 if ($(event.target).hasClass('overlay')) {
                     const id = event.target.id;
                     if (id === 'addOrderOverlay') closeAddOrderForm(); else if (id === 'inventoryOverlay') closeInventoryOverlay(); else if (id === 'cartModal') closeCartModal(); /* Removed driverModal close */ else if (id === 'orderDetailsModal') closeOrderDetailsModal();
                 }
                 if ($(event.target).hasClass('modal') && !$(event.target).closest('.modal-content').length) {
                    if (event.target.id === 'statusModal') closeStatusModal();
                    else if (event.target.id === 'pendingStatusModal') closePendingStatusModal();
                    else if (event.target.id === 'rejectedStatusModal') closeRejectedStatusModal();
                 }
            });
        });
    </script>
</body>
</html>