<?php
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

// Fetch active clients for the dropdown
$clients = [];
$clients_with_company_address = []; // Array to store clients with their company addresses
$clients_with_company = []; // Array to store clients with their company names

$stmt = $conn->prepare("SELECT username, company_address, company FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$stmt->bind_result($username, $company_address, $company);
while ($stmt->fetch()) {
    $clients[] = $username;
    $clients_with_company_address[$username] = $company_address;
    $clients_with_company[$username] = $company;
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
        
        /* Search Container Styling (exactly as in order_history.php) */
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
        
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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

        .modal-content {
            max-height: none;
            overflow-y: visible;
            padding-bottom: 20px;
        }
            <table class="cart-table
        
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
        
        /* Status modal buttons */
        .status-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .modal-status-btn {
            padding: 10px 20px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .modal-status-btn.active {
            background-color: #ffc107;
            color: white;
        }

        .modal-status-btn.active:hover {
            background-color:rgb(202, 154, 10);
            color: white;
        }
        
        .modal-status-btn.reject {
            background-color: #dc3545;
            color: white;
        }

        .modal-status-btn.reject:hover {
            background-color:rgb(138, 23, 35);
            color: white;
        }
        
        .modal-status-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .modal-footer {
            margin-top: 8px;
            text-align: right;
        }
        
        .modal-cancel-btn {
            padding: 8px 15px;
            border-radius: 40px;
            border: 1px solid #ddd;
            background-color:rgb(43, 43, 43);
            cursor: pointer;
            font-weight: bold;
        }
        
        .modal-cancel-btn:hover {
            background-color:rgb(61, 61, 61);
        }
        
        /* Sortable table headers */
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
        
        .download-pdf-btn {
            padding: 10px 20px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

    .instructions-btn {
        padding: 6px 12px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        min-width: 60px;
        text-align: center;
    }

    .instructions-btn:hover {
        background-color: #218838;
    }
    
    .instructions-btn i {
        margin-right: 5px;
    }
    
    .no-instructions {
        color: #6c757d;
        font-style: italic;
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
        max-height: 90vh; /* 90% of the viewport height */
        overflow-y: auto; /* Add scroll if content exceeds max height */
        margin: 2vh auto; /* Center vertically with 5% top margin */
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .close-instructions {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 18px;
        color: white;
        opacity: 0.8;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .close-instructions:hover {
        background-color: rgba(255, 255, 255, 0.2);
        opacity: 1;
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
    
    /* Make button look consistent with other buttons */
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

    /* Style for the special instructions textarea */
    #special_instructions {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical; /* Allow vertical resizing only */
        font-family: inherit;
        margin-bottom: 15px;
    }

    /* Update overlay-content max height */
    .overlay-content {
        max-height: 90vh; /* 90% of the viewport height */
        overflow-y: auto; /* Add scroll if content exceeds max height */
    }

    #contentToDownload {
        font-size: 14px; /* Adjust this value based on the original font size minus 2px */
    }

    #contentToDownload .po-table {
        font-size: 12px; /* Adjust this value based on the original font size minus 2px */
    }

    /* Adjust other elements if needed */
    #contentToDownload .po-title {
        font-size: 16px; /* Original was 18px */
    }

    #contentToDownload .po-company {
        font-size: 20px; /* Original was 22px */
    }

    #contentToDownload .po-total {
        font-size: 12px; /* Original was 14px */
    }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="orders-header">
            <h1>Pending Orders</h1>
            <!-- Added search box matching order_history.php -->
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
                        <!-- Modified Company column to be sortable -->
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
                                Orders</button></td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <!-- Add Special Instructions column with view button -->
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
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                <button class="download-btn" onclick="downloadPODirectly(
                                    '<?= htmlspecialchars($order['po_number']) ?>', 
                                    '<?= htmlspecialchars($order['username']) ?>', 
                                    '<?= htmlspecialchars($order['company']) ?>', 
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
                            <td colspan="10" class="no-orders">No pending orders found.</td>
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

    <!-- Overlay Form for Adding New Order -->
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
                    <button type="submit" class="save-btn" onclick="prepareOrderData()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
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
            <input type="text" id="inventorySearch" placeholder="Search products...">
            <select id="inventoryFilter">
                <option value="all">All Categories</option>
                <!-- Populate with categories dynamically -->
            </select>
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
    
    <!-- Enhanced Status Modal - Including both finished products and raw materials information -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            
            <!-- Enhanced Raw Materials Section -->
            <div id="rawMaterialsContainer" class="raw-materials-container">
                <h3>Loading inventory status...</h3>
                <!-- Content will be populated dynamically -->
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
                    <i class="fas fa-times"></i> Cancel
                </button>
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
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
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
                <!-- Instructions will be displayed here -->
            </div>
            <div class="instructions-footer">
                <button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button>
            </div>
        </div>
    </div>

    <script src="/js/orders.js"></script>
    <script>
    // Variables to store the current PO for PDF generation
    let currentPOData = null;
    
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

    // Function to generate Purchase Order PDF
    // Function to generate Purchase Order PDF
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
    
    window.openStatusModal = function(poNumber, username, ordersJson) {
        $('#statusMessage').text('Change order status for ' + poNumber);
        $('#statusModal').data('po_number', poNumber).show();
        
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
    };

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

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    // Function to change order status
    function changeStatus(status) {
        var poNumber = $('#statusModal').data('po_number');
        
        // Only deduct materials if changing to Active
        const deductMaterials = (status === 'Active');
        
        $.ajax({
            type: 'POST',
            url: '/backend/update_order_status.php',
            data: { 
                po_number: poNumber, 
                status: status,
                deduct_materials: deductMaterials
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Format status type for toast
                    let toastType = status.toLowerCase();
                    if (toastType === 'completed') toastType = 'complete';
                    if (toastType === 'rejected') toastType = 'reject';
                    
                    // Create message
                    let message = `Changed status for ${poNumber} to ${status}.`;
                    if (status === 'Active' && deductMaterials) {
                        message = `Changed status for ${poNumber} to ${status}. Inventory has been updated.`;
                    }
                    
                    // Show toast and reload
                    showToast(message, toastType);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Failed to change status: ' + (response.error || 'Unknown error'));
                }
                closeStatusModal();
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Failed to change status. Please try again.');
                closeStatusModal();
            }
        });
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
        
        // Automatically remove the toast after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
        function viewOrderDetails(ordersJson) {
        try {
            const orderDetails = JSON.parse(ordersJson);
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            
            // Clear previous content
            orderDetailsBody.innerHTML = '';
            
            orderDetails.forEach(product => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${parseFloat(product.price).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}</td>
                    <td>${product.quantity}</td>
                `;
                
                orderDetailsBody.appendChild(row);
            });
            
            // Show modal
            document.getElementById('orderDetailsModal').style.display = 'flex';
        } catch (e) {
            console.error('Error parsing order details:', e);
            alert('Error displaying order details');
        }
    }

    function closeOrderDetailsModal() {
        document.getElementById('orderDetailsModal').style.display = 'none';
    }
    
    // Add function to update company name when username changes

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
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('specialInstructionsModal');
        if (event.target === modal) {
            closeSpecialInstructions();
        }
    });
    </script>
    <script>
        <?php include('../../js/order_processing.js'); ?>
    
        // Search functionality (client-side, same as in order_history.php)
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
            
            // Initialize company field if needed
            $('#username').change(function() {
                updateCompany();
            });
            
            // Make sure prepareOrderData includes company field
            window.originalPrepareOrderData = window.prepareOrderData;
            window.prepareOrderData = function() {
                if (window.originalPrepareOrderData) {
                    window.originalPrepareOrderData();
                }
                
                // Ensure company is included
                const ordersInput = document.getElementById('orders');
                if (ordersInput.value) {
                    try {
                        const ordersData = JSON.parse(ordersInput.value);
                        ordersInput.value = JSON.stringify(ordersData);
                    } catch (e) {
                        console.error("Error preparing order data:", e);
                    }
                }
                
                // Include special instructions in form data
                const specialInstructions = document.getElementById('special_instructions').value;
                document.getElementById('special_instructions_hidden').value = specialInstructions;
                // No need for a hidden field since the textarea already has the name attribute
            };
    </script> 
</body>
</html>