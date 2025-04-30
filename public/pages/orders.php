<?php
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
$stmt = $conn->prepare("SELECT username, company_address FROM clients_accounts WHERE status = 'active'");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->execute();
$stmt->bind_result($username, $company_address);
while ($stmt->fetch()) {
    $clients[] = $username;
    $clients_with_company_address[$username] = $company_address;
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
        
        .download-pdf-btn {
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
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order['progress'] ?? 0 ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i>    
                                        View
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
                                    <?php if ($order['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order['po_number']) ?>')">
                                            <i class="fas fa-clipboard-list"></i>    
                                            View
                                        </button>
                                    <?php else: ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars($order['po_number']) ?>')">
                                            <i class="fas fa-clipboard-list"></i>    
                                            View
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-active"><?= htmlspecialchars($order['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <button class="status-btn" onclick="openStatusModal('<?= htmlspecialchars($order['po_number']) ?>', '<?= htmlspecialchars($order['username']) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Status
                                    </button>
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
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
                <!-- Overall progress info -->
                <div class="item-progress-info" id="overall-progress-info" style="margin-top: 10px;">
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
                <button type="button" class="save-progress-btn" onclick="saveProgressChanges()">
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
                    <button onclick="changeStatus('For Delivery')" class="modal-status-btn delivery">
                        <i class="fas fa-truck"></i> For Delivery
                        <div class="btn-info">(Requires: 100% Progress and Driver)</div>
                    </button>
                    <button onclick="changeStatus('Pending')" class="modal-status-btn pending">
                        <i class="fas fa-clock"></i> Pending
                        <div class="btn-info">(Disables: Driver and Progress)</div>
                    </button>
                    <button onclick="changeStatus('Rejected')" class="modal-status-btn rejected">
                        <i class="fas fa-times-circle"></i> Reject
                        <div class="btn-info">(Disables: Driver and Progress)</div>
                    </button>
                </div>
                <div class="modal-footer">
                    <button onclick="closeStatusModal()" class="modal-cancel-btn">
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
                <button class="save-btn" onclick="assignDriver()">
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

function openStatusModal(poNumber, username) {
    currentPoNumber = poNumber;
    document.getElementById('statusMessage').textContent = `Change status for order ${poNumber} (${username})`;
    document.getElementById('statusModal').style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function changeStatus(status) {
    // For 'For Delivery' status, check if a driver has been assigned and progress is 100%
    if (status === 'For Delivery') {
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
                document.getElementById('status-loading').remove();
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
                    updateOrderStatus(status);
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
        // For other statuses, proceed directly
        updateOrderStatus(status);
    }
}

function updateOrderStatus(status) {
    // Send AJAX request to update status
    fetch('/backend/update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `po_number=${currentPoNumber}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = 'Status updated successfully';
            if (status === 'For Delivery') {
                message = 'Order marked for delivery successfully';
            } else if (status === 'Rejected') {
                message = 'Order rejected successfully';
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
    })
    .catch(error => {
        showToast('Error updating status: ' + error, 'error');
        closeStatusModal();
    });
}

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

function updateOverallProgressDisplay() {
    const overallProgressBar = document.getElementById('overall-progress-bar');
    const overallProgressText = document.getElementById('overall-progress-text');
    
    // Round to nearest whole number
    const roundedProgress = Math.round(overallProgress);
    
    overallProgressBar.style.width = `${roundedProgress}%`;
    overallProgressText.textContent = `${roundedProgress}%`;
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

// Add missing functions that were causing errors
function closeOrderDetailsModal() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}

function saveProgressChanges() {
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
            auto_delivery: shouldMarkForDelivery, // changed from auto_complete to auto_delivery
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

// Driver Modal Functions
function openDriverModal(poNumber, driverId, driverName) {
    currentPoNumber = poNumber;
    currentDriverId = driverId;
    
    // Update the modal title based on whether we're assigning or changing
    const modalTitle = document.getElementById('driverModalTitle');
    if (driverId > 0) {
        modalTitle.textContent = 'Change Driver Assignment';
        document.getElementById('driverModalMessage').textContent = `Current driver: ${driverName}`;
    } else {
        modalTitle.textContent = 'Assign Driver';
        document.getElementById('driverModalMessage').textContent = `Select a driver for order ${poNumber}:`;
    }
    
    // Set the current driver in the dropdown if one is assigned
    const driverSelect = document.getElementById('driverSelect');
    driverSelect.value = driverId;
    
    // Show the modal
    document.getElementById('driverModal').style.display = 'flex';
}

function closeDriverModal() {
    document.getElementById('driverModal').style.display = 'none';
    currentDriverId = 0;
}

function assignDriver() {
    const driverSelect = document.getElementById('driverSelect');
    const driverId = parseInt(driverSelect.value);
    
    if (driverId === 0 || isNaN(driverId)) {
        showToast('Please select a driver', 'error');
        return;
    }
    
    // Show a loading state
    const saveBtn = document.querySelector('#driverModal .save-btn');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
    saveBtn.disabled = true;
    
    // Debug output
    console.log("Sending request to assign driver:", {
        po_number: currentPoNumber,
        driver_id: driverId
    });
    
    // Create FormData object
    const formData = new FormData();
    formData.append('po_number', currentPoNumber);
    formData.append('driver_id', driverId);
    
    // First try with FormData
    fetch('/backend/assign_driver.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Driver assigned successfully', 'success');
            setTimeout(() => { window.location.reload(); }, 1000);
        } else {
            // If FormData fails, try with JSON
            console.log("FormData method failed, trying JSON");
            return fetch('/backend/assign_driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_number: currentPoNumber,
                    driver_id: driverId
                })
            }).then(response => response.json());
        }
        return null; // Signal that we don't need to continue
    })
    .then(data => {
        if (data === null) return; // Skip if first attempt succeeded
        
        if (data.success) {
            showToast('Driver assigned successfully', 'success');
            setTimeout(() => { window.location.reload(); }, 1000);
        } else {
            showToast('Error assigning driver: ' + (data.message || 'Unknown error'), 'error');
            saveBtn.innerHTML = originalBtnText;
            saveBtn.disabled = false;
        }
        closeDriverModal();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error: Failed to communicate with server. Please try again.', 'error');
        saveBtn.innerHTML = originalBtnText;
        saveBtn.disabled = false;
        closeDriverModal();
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
    
    // Automatically remove the toast after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Document ready function for real-time searching
$(document).ready(function() {
    // Search functionality - exact match to order_history.php
    $("#searchInput").on("input", function() {
        let searchText = $(this).val().toLowerCase().trim();
        console.log("Searching for:", searchText); // Debug line

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

    // Handle clicks outside modals to close them
    $(document).on('click', '.overlay', function(event) {
        if (event.target === this) {
            if (this.id === 'orderDetailsModal') closeOrderDetailsModal();
            else if (this.id === 'driverModal') closeDriverModal();
        }
    });

    // Close special instructions modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('specialInstructionsModal');
        if (event.target === modal) {
            closeSpecialInstructions();
        }
    });
});

function viewOrderInfo(poNumber) {
    // Fetch the order data without progress tracking
    fetch(`/backend/get_order_info.php?po_number=${poNumber}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Display order details in a modal without progress tracking
            const orderItems = JSON.parse(data.order.orders);
            let orderDetails = `
                <h3>Order #${data.order.po_number}</h3>
                <p><strong>Status:</strong> ${data.order.status}</p>
                <p><strong>Order Date:</strong> ${data.order.order_date}</p>
                <p><strong>Delivery Date:</strong> ${data.order.delivery_date}</p>
                <p><strong>Total Amount:</strong> PHP ${parseFloat(data.order.total_amount).toFixed(2)}</p>
                <h4>Items:</h4>
                <ul>
            `;
            
            orderItems.forEach(item => {
                orderDetails += `
                    <li>${item.item_description} - ${item.quantity} x PHP ${parseFloat(item.price).toFixed(2)}</li>
                `;
            });
            
            orderDetails += `</ul>`;
            
            // Use a simple alert or create a custom modal to display this information
            alert(orderDetails);
            // Or use a more sophisticated modal display
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error, 'error');
        console.error('Error fetching order details:', error);
    });
}
    </script>
</body>
</html>