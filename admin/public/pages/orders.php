<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_user_id'])) {
    header("Location: ../login.php"); // Redirect to admin login page
    exit();
}

// Check role permission for Orders
checkRole('Orders');

// Fetch Orders with Filtering
$status_filter = $_GET['status'] ?? 'all';
$search_filter = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT o.*, da.driver_id, d.name as driver_name
        FROM orders o
        LEFT JOIN driver_assignments da ON o.po_number = da.po_number
        LEFT JOIN drivers d ON da.driver_id = d.id
        WHERE 1=1";
$params = [];
$types = "";

if ($status_filter != 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($search_filter)) {
    $sql .= " AND (o.po_number LIKE ? OR o.username LIKE ? OR o.company LIKE ?)";
    $like_search = "%" . $search_filter . "%";
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "sss";
}
if (!empty($date_from)) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY o.order_date DESC, o.id DESC"; // Add secondary sort

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
} else {
    // Handle query error if needed
    error_log("Error fetching orders: " . $stmt->error);
}
$stmt->close();

// Fetch Drivers for assignment modal
$drivers_sql = "SELECT id, name FROM drivers WHERE availability = 'Available' ORDER BY name ASC";
$drivers_result = $conn->query($drivers_sql);
$drivers = [];
if ($drivers_result) {
    while ($driver_row = $drivers_result->fetch_assoc()) {
        $drivers[] = $driver_row;
    }
}

// Fetch Clients for "Add Order" form
$clients_sql = "SELECT username, company, company_address FROM clients_accounts WHERE status = 'Active' ORDER BY username ASC";
$clients_result = $conn->query($clients_sql);
$clients = [];
if ($clients_result) {
    while ($client_row = $clients_result->fetch_assoc()) {
        $clients[] = $client_row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="/css/orders.css"> <!-- Adjust path as needed -->
    <link rel="stylesheet" href="/css/sidebar.css"> <!-- Adjust path as needed -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css"> <!-- Adjust path as needed -->
    <!-- Add jQuery UI CSS for Datepicker -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        /* Add specific styles from your previous orders.php if needed, e.g., progress bar, material status */
        .progress-bar-container {
            width: 100%;
            background-color: #e9ecef;
            border-radius: 0.25rem;
            overflow: hidden;
            height: 20px; /* Adjust height */
            position: relative;
        }
        .progress-bar {
            background-color: #198754; /* Green */
            height: 100%;
            width: 0%; /* Default width */
            transition: width 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 8px; position: relative; }
        .modal-content.large { max-width: 90%; width: 1100px; } /* For order details */
        .modal-content.medium { max-width: 600px; } /* For confirmation, driver assign */
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 15px; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal-header { padding-bottom: 10px; border-bottom: 1px solid #eee; margin-bottom: 15px; }
        .modal-header h2 { margin: 0; font-size: 1.5rem; }
        .modal-body { max-height: 65vh; overflow-y: auto; padding-right: 15px; } /* Add padding for scrollbar */
        .modal-footer { padding-top: 15px; border-top: 1px solid #eee; margin-top: 15px; text-align: right; }
        .modal-footer button { margin-left: 10px; }
        .status-btn-group button { margin: 0 5px; }
        .confirmation-modal .modal-content { max-width: 450px; }
        .confirmation-message { margin-bottom: 20px; font-size: 1.1em; text-align: center; }

        /* Styles from your previous orders.php for materials */
        .materials-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.9em; }
        .materials-table th, .materials-table td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        .materials-table th { background-color: #f8f9fa; }
        .material-sufficient { color: #155724; background-color: #d4edda; }
        .material-insufficient { color: #721c24; background-color: #f8d7da; font-weight: bold; }
        .materials-status { padding: 8px; margin-top: 10px; border-radius: 4px; text-align: center; font-weight: bold; }
        .status-sufficient { background-color: #d4edda; color: #155724; }
        .status-insufficient { background-color: #f8d7da; color: #721c24; }
        #raw-materials-section h3 { margin-top: 15px; font-size: 1.1em; }

        /* Styles for order details progress */
        #orderDetailsTable tbody tr:hover { background-color: #f8f9fa; }
        .item-header-row td { vertical-align: middle; }
        .completed-item { background-color: #d1e7dd !important; } /* Light green for completed items */
        .unit-row { background-color: #fdfdfe; font-size: 0.9em; }
        .unit-row.completed td { color: #6c757d; text-decoration: line-through; }
        .item-progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 5px; height: 18px; position: relative; margin-top: 5px; }
        .item-progress-bar { background-color: #0d6efd; height: 100%; border-radius: 5px; transition: width 0.3s ease; }
        .item-progress-text { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: white; font-weight: bold; text-shadow: 1px 1px 1px rgba(0,0,0,0.4); }
        .contribution-text { font-size: 0.7rem; color: #6c757d; text-align: right; }
        #overall-progress-info { margin-top: 15px; }
        #overall-progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 8px; height: 25px; position: relative; margin-top: 5px; }
        #overall-progress-bar { background-color: #198754; height: 100%; border-radius: 8px; }
        #overall-progress-text { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: white; font-weight: bold; text-shadow: 1px 1px 1px rgba(0,0,0,0.5); }
        .toggle-units-btn { background: none; border: none; color: #0d6efd; cursor: pointer; margin-left: 10px; }

        /* Add Order Form Styles */
        #addOrderOverlay { /* Uses .overlay styles from drivers.php */ align-items: flex-start; padding-top: 5vh; }
        #addOrderFormContainer { background-color: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 900px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        #addOrderFormFields { overflow-y: auto; padding-right: 15px; margin-bottom: 15px; }
        #addOrderForm .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        #addOrderForm .form-group { flex: 1; }
        #addOrderForm label { display: block; margin-bottom: 5px; font-weight: 500; }
        #addOrderForm input, #addOrderForm select, #addOrderForm textarea { width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; }
        #addOrderForm textarea { resize: vertical; min-height: 60px; }
        #inventorySection { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        #inventoryControls { display: flex; gap: 15px; margin-bottom: 15px; }
        #inventoryTableContainer { max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; }
        #inventoryTable { width: 100%; font-size: 0.9em; }
        #inventoryTable th, #inventoryTable td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
        #inventoryTable th { background-color: #f8f9fa; position: sticky; top: 0; }
        .inventory-quantity { width: 60px; text-align: center; }
        .add-to-cart-btn { background-color: #28a745; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; }
        .add-to-cart-btn:hover { background-color: #218838; }
        #summarySection { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        #summaryTableContainer { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 10px; }
        #summaryTable { width: 100%; font-size: 0.9em; }
        #summaryTable th, #summaryTable td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
        #summaryTable th { background-color: #f8f9fa; position: sticky; top: 0; }
        .summary-quantity { width: 60px; text-align: center; }
        .remove-item-btn { background-color: #dc3545; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; }
        .remove-item-btn:hover { background-color: #c82333; }
        .summary-total { text-align: right; font-weight: bold; margin-top: 10px; font-size: 1.1em; }
        #addOrderFormButtons { text-align: right; padding-top: 15px; border-top: 1px solid #eee; margin-top: 15px; }

        /* Cart Icon */
        #cartIconContainer { position: fixed; bottom: 30px; right: 30px; z-index: 1050; }
        #cartIcon { background-color: var(--primary-color); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); cursor: pointer; transition: background-color 0.3s; position: relative; }
        #cartIcon:hover { background-color: var(--primary-hover); }
        #cartItemCount { position: absolute; top: -5px; right: -5px; background-color: var(--accent-color); color: white; border-radius: 50%; width: 24px; height: 24px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* Cart Modal */
        #cartModal .modal-content { max-width: 800px; }
        .cart-table-container { max-height: 50vh; overflow-y: auto; margin-bottom: 15px; }
        .cart-table { width: 100%; }
        .cart-table th, .cart-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; }
        .cart-table th { background-color: #f8f9fa; }
        .cart-quantity { width: 65px; }
        .no-products { text-align: center; color: #6c757d; padding: 20px; }
        .cart-total { text-align: right; font-weight: bold; margin-top: 15px; font-size: 1.2em; }

        /* PDF Preview Styles */
        #pdfPreview { display: none; position: fixed; z-index: 1051; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
        #pdfPreviewContent { background-color: #fff; margin: 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 800px; /* A4-like width */ position: relative; }
        #contentToDownload { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; }
        #contentToDownload h2, #contentToDownload h3 { text-align: center; margin-bottom: 15px; color: #000; }
        #contentToDownload .header-info { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ccc; }
        #contentToDownload .header-info div { flex-basis: 48%; }
        #contentToDownload .header-info strong { display: inline-block; width: 120px; }
        #contentToDownload .order-items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        #contentToDownload .order-items-table th, #contentToDownload .order-items-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        #contentToDownload .order-items-table th { background-color: #f2f2f2; }
        #contentToDownload .total-section { text-align: right; margin-top: 20px; font-size: 1.1em; font-weight: bold; }
        #printInstructionsSection { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; }
        #printInstructionsSection h4 { margin-bottom: 5px; }
        #printSpecialInstructions { white-space: pre-wrap; word-wrap: break-word; }

    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Manage Orders</h1>
            <button class="add-btn" onclick="openAddOrderForm()">
                <i class="fas fa-plus"></i> Add New Order
            </button>
        </div>

        <!-- Filters -->
        <div class="filters card">
             <div class="card-header"><i class="fas fa-filter"></i> Filters</div>
             <div class="card-body">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter" name="status">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All</option>
                                <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="For Delivery" <?= $status_filter == 'For Delivery' ? 'selected' : '' ?>>For Delivery</option>
                                <option value="In Transit" <?= $status_filter == 'In Transit' ? 'selected' : '' ?>>In Transit</option>
                                <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                             <label for="date_from">From</label>
                             <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="date_to">To</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="filter-group search-group">
                            <label for="searchInput">Search</label>
                            <input type="text" id="searchInput" name="search" placeholder="PO#, User, Company..." value="<?= htmlspecialchars($search_filter) ?>">
                        </div>
                         <div class="filter-group buttons">
                              <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                              <a href="orders.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                         </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-container card">
             <div class="card-header"><i class="fas fa-list"></i> Orders List (<?= count($orders) ?>)</div>
             <div class="card-body p-0">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Username</th>
                            <th>Company</th>
                            <th>Order Date</th>
                            <th>Delivery Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Driver</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order):
                                $status_lower = strtolower(str_replace(' ', '-', $order['status']));
                                $encoded_orders = htmlspecialchars(json_encode(json_decode($order['orders'])), ENT_QUOTES, 'UTF-8'); // Re-encode safely
                                $encoded_instructions = htmlspecialchars($order['special_instructions'] ?? '', ENT_QUOTES, 'UTF-8');
                                $encoded_username = htmlspecialchars($order['username'], ENT_QUOTES, 'UTF-8');
                                $encoded_company = htmlspecialchars($order['company'] ?? '', ENT_QUOTES, 'UTF-8');
                                $encoded_delivery_address = htmlspecialchars($order['delivery_address'] ?? '', ENT_QUOTES, 'UTF-8');
                                $driver_name_display = $order['driver_name'] ? htmlspecialchars($order['driver_name']) : 'N/A';
                            ?>
                                <tr class="status-<?= $status_lower ?>">
                                    <td><?= htmlspecialchars($order['po_number']) ?></td>
                                    <td><?= htmlspecialchars($order['username']) ?></td>
                                    <td><?= htmlspecialchars($order['company'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($order['delivery_date'])) ?></td>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $status_lower ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $driver_name_display ?></td>
                                    <td>
                                        <?php if ($order['status'] == 'Active' || $order['status'] == 'For Delivery'): ?>
                                            <div class="progress-bar-container" title="<?= $order['progress'] ?? 0 ?>% Complete">
                                                <div class="progress-bar" style="width: <?= $order['progress'] ?? 0 ?>%;">
                                                    <?= $order['progress'] ?? 0 ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="action-btn view-btn" title="View Details" onclick="viewOrderInfo('<?= $encoded_orders ?>', '<?= $order['status'] ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                         <?php if ($order['status'] == 'Active'): ?>
                                            <button class="action-btn progress-btn" title="Track Progress" onclick="viewOrderDetails('<?= $order['po_number'] ?>')">
                                                 <i class="fas fa-tasks"></i>
                                            </button>
                                         <?php endif; ?>

                                        <?php if ($order['status'] == 'Pending'): ?>
                                            <button class="action-btn status-btn-pending" title="Change Status (Pending)" onclick="confirmPendingStatusChange('<?= $order['po_number'] ?>', '<?= $encoded_username ?>', '<?= $encoded_orders ?>', 'Pending')">
                                                 <i class="fas fa-play-circle"></i>
                                            </button>
                                         <?php elseif ($order['status'] == 'Active'): ?>
                                             <button class="action-btn status-btn-active" title="Change Status (Active)" onclick="confirmStatusChange('<?= $order['po_number'] ?>', '<?= $encoded_username ?>', 'Active')">
                                                  <i class="fas fa-exchange-alt"></i>
                                             </button>
                                         <?php elseif ($order['status'] == 'Rejected'): ?>
                                             <button class="action-btn status-btn-rejected" title="Change Status (Rejected)" onclick="confirmRejectedStatusChange('<?= $order['po_number'] ?>', '<?= $encoded_username ?>', 'Rejected')">
                                                  <i class="fas fa-undo"></i>
                                             </button>
                                         <?php endif; ?>

                                        <?php if ($order['status'] == 'Active' || $order['status'] == 'For Delivery' || $order['status'] == 'In Transit'): ?>
                                             <?php if ($order['driver_assigned']): ?>
                                                 <button class="action-btn driver-btn change" title="Change Driver" onclick="confirmDriverChange('<?= $order['po_number'] ?>', <?= $order['driver_id'] ?>, '<?= htmlspecialchars($order['driver_name'], ENT_QUOTES, 'UTF-8') ?>')">
                                                     <i class="fas fa-user-edit"></i>
                                                 </button>
                                             <?php else: ?>
                                                 <button class="action-btn driver-btn assign" title="Assign Driver" onclick="confirmDriverAssign('<?= $order['po_number'] ?>')">
                                                     <i class="fas fa-user-plus"></i>
                                                 </button>
                                             <?php endif; ?>
                                        <?php endif; ?>

                                        <button class="action-btn download-btn" title="Download PO" onclick="confirmDownloadPO('<?= $order['po_number'] ?>', '<?= $encoded_username ?>', '<?= $encoded_company ?>', '<?= date('M d, Y', strtotime($order['order_date'])) ?>', '<?= date('M d, Y', strtotime($order['delivery_date'])) ?>', '<?= $encoded_delivery_address ?>', '<?= $encoded_orders ?>', '<?= $order['total_amount'] ?>', '<?= $encoded_instructions ?>')">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                        <?php if (!empty($order['special_instructions'])): ?>
                                            <button class="action-btn instructions-btn" title="View Instructions" onclick="viewSpecialInstructions('<?= $order['po_number'] ?>', '<?= $encoded_instructions ?>')">
                                                 <i class="fas fa-comment-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-orders">No orders found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Order Details Modal (For Viewing Info & Progress Tracking) -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content large">
            <span class="close-btn" onclick="closeOrderDetailsModal()">&times;</span>
            <div class="modal-header">
                <h2>Order Details - <span id="orderDetailsPoNumber"><?= htmlspecialchars($order['po_number'] ?? '') ?></span> (<span id="orderStatus"></span>)</h2>
            </div>
            <div class="modal-body">
                <table id="orderDetailsTable" class="orders-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Item Description</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th id="status-header-cell">Status / Progress</th> <!-- Hide/show based on context -->
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Items loaded via JS -->
                    </tbody>
                    <tfoot>
                         <tr>
                              <td colspan="4" style="text-align: right; font-weight: bold;">Total:</td>
                              <td id="orderTotalAmount" style="font-weight: bold;"></td>
                              <td></td> <!-- Empty cell for status column -->
                         </tr>
                    </tfoot>
                </table>
                 <div id="overall-progress-info" style="display: none;">
                     <strong>Overall Progress (0%):</strong>
                     <div id="overall-progress-bar-container">
                         <div id="overall-progress-bar" style="width: 0%;">
                             <span id="overall-progress-text">0%</span>
                         </div>
                     </div>
                 </div>
            </div>
            <div class="modal-footer">
                 <button class="cancel-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-times"></i> Close</button>
                 <button class="save-btn save-progress-btn" style="display: none;" onclick="confirmSaveProgress()"><i class="fas fa-save"></i> Save Progress</button>
            </div>
        </div>
    </div>

    <!-- Status Change Modal (for Active orders) -->
    <div id="statusModal" class="modal">
        <div class="modal-content medium">
            <span class="close-btn" onclick="closeStatusModal()">&times;</span>
            <div class="modal-header"><h2>Change Order Status</h2></div>
            <div class="modal-body">
                <p id="statusMessage">Change status for order...</p>
                <div class="status-btn-group" style="text-align: center;">
                    <!-- Note: 'For Delivery' needs progress & driver check -->
                    <button class="action-btn status-btn-delivery" onclick="confirmStatusAction('For Delivery')"><i class="fas fa-truck"></i> For Delivery</button>
                    <!-- Note: Moving from Active to Pending/Rejected should return stock -->
                    <button class="action-btn status-btn-pending" onclick="confirmStatusAction('Pending')"><i class="fas fa-pause-circle"></i> Back to Pending</button>
                    <button class="action-btn status-btn-rejected" onclick="confirmStatusAction('Rejected')"><i class="fas fa-times-circle"></i> Reject Order</button>
                     <!-- 'Completed' might be handled differently, e.g., only after 'In Transit' or 'Delivered' -->
                </div>
            </div>
             <div class="modal-footer">
                 <button type="button" class="cancel-btn" onclick="closeStatusModal()"><i class="fas fa-times"></i> Cancel</button>
             </div>
        </div>
    </div>

    <!-- Status Change Modal (for Pending orders) -->
    <div id="pendingStatusModal" class="modal">
        <div class="modal-content medium">
            <span class="close-btn" onclick="closePendingStatusModal()">&times;</span>
            <div class="modal-header"><h2>Change Order Status</h2></div>
            <div class="modal-body">
                <p id="pendingStatusMessage">Change status for order...</p>
                <div id="rawMaterialsContainer" style="margin-bottom: 15px;">
                    <!-- Material check results loaded here -->
                </div>
                <div class="status-btn-group" style="text-align: center;">
                    <!-- Note: Moving to Active should deduct stock -->
                    <button id="activeStatusBtn" class="action-btn status-btn-active" onclick="confirmStatusAction('Active')" disabled><i class="fas fa-check-circle"></i> Set to Active</button>
                    <button class="action-btn status-btn-rejected" onclick="confirmStatusAction('Rejected')"><i class="fas fa-times-circle"></i> Reject Order</button>
                </div>
            </div>
             <div class="modal-footer">
                 <button type="button" class="cancel-btn" onclick="closePendingStatusModal()"><i class="fas fa-times"></i> Cancel</button>
             </div>
        </div>
    </div>

    <!-- Status Change Modal (for Rejected orders) -->
    <div id="rejectedStatusModal" class="modal">
        <div class="modal-content medium">
            <span class="close-btn" onclick="closeRejectedStatusModal()">&times;</span>
            <div class="modal-header"><h2>Change Order Status</h2></div>
            <div class="modal-body">
                <p id="rejectedStatusMessage">Change status for rejected order...</p>
                <div class="status-btn-group" style="text-align: center;">
                    <!-- Note: Moving to Pending/Active might need review -->
                    <button class="action-btn status-btn-pending" onclick="confirmStatusAction('Pending')"><i class="fas fa-undo"></i> Back to Pending</button>
                    <!-- Optionally allow direct activation if needed, consider stock implications -->
                    <!-- <button class="action-btn status-btn-active" onclick="confirmStatusAction('Active')"><i class="fas fa-play-circle"></i> Activate Order</button> -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeRejectedStatusModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>
    </div>

    <!-- Generic Confirmation Modal for Status Change -->
    <div id="statusConfirmationModal" class="modal confirmation-modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Confirm Status Change</h2></div>
            <div class="modal-body">
                <p id="statusConfirmationMessage" class="confirmation-message">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeStatusConfirmation()"><i class="fas fa-times"></i> No</button>
                <button type="button" class="save-btn" onclick="executeStatusChange()"><i class="fas fa-check"></i> Yes</button>
            </div>
        </div>
    </div>

    <!-- Driver Assignment Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content medium">
             <span class="close-btn" onclick="closeDriverModal()">&times;</span>
             <div class="modal-header"><h2 id="driverModalTitle">Assign Driver</h2></div>
             <div class="modal-body">
                  <p id="driverModalMessage">Select driver for order...</p>
                  <label for="driverSelect">Available Drivers:</label>
                  <select id="driverSelect" name="driver_id" class="form-control">
                       <option value="0">-- Select Driver --</option>
                       <?php foreach ($drivers as $driver): ?>
                           <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                       <?php endforeach; ?>
                  </select>
             </div>
             <div class="modal-footer">
                  <button class="cancel-btn" onclick="closeDriverModal()"><i class="fas fa-times"></i> Cancel</button>
                  <button class="save-btn" onclick="confirmDriverAssignment()"><i class="fas fa-check"></i> Confirm</button>
             </div>
        </div>
    </div>

     <!-- Confirmation Modal for Driver Assignment -->
     <div id="driverConfirmationModal" class="modal confirmation-modal">
         <div class="modal-content">
             <div class="modal-header"><h2>Confirm Driver Assignment</h2></div>
             <div class="modal-body">
                 <p class="confirmation-message">Assign this driver?</p>
             </div>
             <div class="modal-footer">
                 <button type="button" class="cancel-btn" onclick="closeDriverConfirmation()"><i class="fas fa-times"></i> No</button>
                 <button type="button" class="save-btn" onclick="assignDriver()"><i class="fas fa-check"></i> Yes</button>
             </div>
         </div>
     </div>

     <!-- Confirmation Modal for Saving Progress -->
     <div id="saveProgressConfirmationModal" class="modal confirmation-modal">
          <div class="modal-content">
              <div class="modal-header"><h2>Confirm Save Progress</h2></div>
              <div class="modal-body">
                  <p class="confirmation-message">Are you sure you want to save the current progress?</p>
              </div>
              <div class="modal-footer">
                  <button type="button" class="cancel-btn" onclick="closeSaveProgressConfirmation()"><i class="fas fa-times"></i> Cancel</button>
                  <button type="button" class="save-btn" onclick="saveProgressChanges()"><i class="fas fa-check"></i> Save Changes</button>
              </div>
          </div>
      </div>

     <!-- Special Instructions Modal -->
     <div id="specialInstructionsModal" class="modal instructions-modal">
         <div class="modal-content medium">
             <span class="close-btn" onclick="closeSpecialInstructions()">&times;</span>
             <div class="modal-header"><h2>Special Instructions</h2></div>
             <div class="modal-body">
                 <h4 id="instructionsPoNumber">PO: ...</h4>
                 <p id="instructionsContent" style="white-space: pre-wrap;"></p>
             </div>
             <div class="modal-footer">
                 <button class="cancel-btn" onclick="closeSpecialInstructions()"><i class="fas fa-times"></i> Close</button>
             </div>
         </div>
     </div>

     <!-- PDF Download Confirmation Modal -->
    <div id="downloadConfirmationModal" class="modal confirmation-modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Download Purchase Order</h2></div>
            <div class="modal-body">
                <p class="confirmation-message">Download PO?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeDownloadConfirmation()"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" class="save-btn" onclick="downloadPODirectly()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>

    <!-- Hidden Div for PDF Generation Content -->
    <div id="pdfPreview" style="display: none;"> <!-- Keep hidden unless previewing -->
        <div id="pdfPreviewContent">
            <span class="close-btn" onclick="closePDFPreview()">&times;</span>
            <div id="contentToDownload">
                 <h2>Purchase Order</h2>
                 <div class="header-info">
                     <div>
                         <strong>PO Number:</strong> <span id="printPoNumber"></span><br>
                         <strong>Client:</strong> <span id="printUsername"></span><br>
                         <strong>Company:</strong> <span id="printCompany"></span>
                     </div>
                     <div>
                         <strong>Order Date:</strong> <span id="printOrderDate"></span><br>
                         <strong>Delivery Date:</strong> <span id="printDeliveryDate"></span><br>
                         <strong>Delivery Address:</strong> <span id="printDeliveryAddress"></span>
                     </div>
                 </div>

                 <h3>Order Items</h3>
                 <table class="order-items-table">
                     <thead>
                         <tr>
                             <th>Category</th>
                             <th>Item</th>
                             <th>Packaging</th>
                             <th>Qty</th>
                             <th>Unit Price</th>
                             <th>Total</th>
                         </tr>
                     </thead>
                     <tbody id="printOrderItems">
                         <!-- Items will be populated here -->
                     </tbody>
                 </table>

                 <div class="total-section">
                     Total Amount: PHP <span id="printTotalAmount"></span>
                 </div>

                 <div id="printInstructionsSection" style="display: none;">
                      <h4>Special Instructions:</h4>
                      <p id="printSpecialInstructions"></p>
                 </div>
            </div>
            <div class="modal-footer">
                 <button onclick="closePDFPreview()">Close Preview</button>
                 <button onclick="downloadPDF()">Download PDF</button>
            </div>
        </div>
    </div>

    <!-- Add New Order Overlay -->
    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div id="addOrderFormContainer">
            <span class="close-btn" onclick="closeAddOrderForm()" style="position: absolute; top: 15px; right: 20px;">&times;</span>
            <h2 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Add New Order</h2>

            <form id="addOrderForm" method="POST" action="/backend/add_order.php" style="display: flex; flex-direction: column; flex-grow: 1; overflow: hidden;">
                <input type="hidden" name="formType" value="addOrder">
                <input type="hidden" name="orders" id="orders"> <!-- Populated by JS -->
                <input type="hidden" name="total_amount" id="total_amount"> <!-- Populated by JS -->
                <input type="hidden" name="company_hidden" id="company_hidden"> <!-- Populated by JS -->


                <div id="addOrderFormFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Client Username<span class="required-asterisk">*</span></label>
                            <select id="username" name="username" required onchange="generatePONumber()">
                                <option value="">-- Select Client --</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= htmlspecialchars($client['username']) ?>"
                                            data-company="<?= htmlspecialchars($client['company'] ?? '') ?>"
                                            data-company-address="<?= htmlspecialchars($client['company_address'] ?? '') ?>">
                                        <?= htmlspecialchars($client['username']) ?> <?= $client['company'] ? '(' . htmlspecialchars($client['company']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="po_number">PO Number<span class="required-asterisk">*</span></label>
                            <input type="text" id="po_number" name="po_number" required readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="order_date">Order Date<span class="required-asterisk">*</span></label>
                            <input type="date" id="order_date" name="order_date" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="delivery_date">Delivery Date<span class="required-asterisk">*</span></label>
                            <input type="text" id="delivery_date" name="delivery_date" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-row">
                         <div class="form-group">
                              <label for="delivery_address_type">Delivery Address Type<span class="required-asterisk">*</span></label>
                              <select id="delivery_address_type" name="delivery_address_type" onchange="toggleDeliveryAddress()">
                                   <option value="company">Use Company Address</option>
                                   <option value="custom">Enter Custom Address</option>
                              </select>
                              <input type="hidden" id="delivery_address" name="delivery_address"> <!-- Actual address sent -->
                         </div>
                         <div class="form-group" id="company_address_container" style="display: none;">
                              <label for="company_address">Company Address (Readonly)</label>
                              <input type="text" id="company_address" readonly>
                         </div>
                         <div class="form-group" id="custom_address_container" style="display: none;">
                              <label for="custom_address">Custom Address<span class="required-asterisk">*</span></label>
                              <input type="text" id="custom_address">
                         </div>
                    </div>

                    <div class="form-group">
                        <label for="special_instructions">Special Instructions</label>
                        <textarea id="special_instructions" name="special_instructions"></textarea>
                    </div>

                    <!-- Inventory Selection -->
                    <div id="inventorySection">
                        <h4>Select Products</h4>
                        <div id="inventoryControls">
                            <select id="inventoryFilter" class="form-control" style="flex-basis: 30%;">
                                <option value="all">All Categories</option>
                                <!-- Categories populated by JS -->
                            </select>
                            <input type="text" id="inventorySearch" placeholder="Search items..." class="form-control" style="flex-grow: 1;">
                            <button type="button" onclick="openInventoryOverlay()" class="action-btn" style="white-space: nowrap;"><i class="fas fa-search"></i> Browse Full Inventory</button>
                        </div>
                         <!-- Maybe show a small preview table here? Or rely on summary? -->
                    </div>

                    <!-- Order Summary -->
                    <div id="summarySection">
                         <h4>Order Summary (<span id="cartItemCount">0</span> items)</h4>
                         <div id="summaryTableContainer">
                              <table id="summaryTable">
                                   <thead>
                                        <tr>
                                             <th>Category</th>
                                             <th>Item</th>
                                             <th>Packaging</th>
                                             <th>Price</th>
                                             <th>Qty</th>
                                             <th>Action</th>
                                        </tr>
                                   </thead>
                                   <tbody id="summaryBody">
                                        <tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products added</td></tr>
                                   </tbody>
                              </table>
                         </div>
                         <div class="summary-total">Total: <span class="summary-total-amount">PHP 0.00</span></div>
                    </div>
                </div>

                <div id="addOrderFormButtons">
                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="save-btn" onclick="confirmAddOrder()"><i class="fas fa-plus-circle"></i> Add Order</button>
                </div>
            </form>
        </div>
    </div>

     <!-- Inventory Selection Overlay -->
     <div id="inventoryOverlay" class="overlay" style="display: none;">
         <div class="modal-content large">
             <span class="close-btn" onclick="closeInventoryOverlay()">&times;</span>
             <div class="modal-header"><h2>Select Products from Inventory</h2></div>
             <div class="modal-body">
                 <div id="inventoryControls" style="margin-bottom: 15px;">
                      <select id="inventoryFilterOverlay" class="form-control" style="flex-basis: 30%;">
                          <option value="all">All Categories</option>
                      </select>
                      <input type="text" id="inventorySearchOverlay" placeholder="Search items..." class="form-control" style="flex-grow: 1;">
                 </div>
                 <div class="inventory-table-container" style="max-height: 55vh;">
                     <table class="inventory-table">
                         <thead>
                             <tr>
                                 <th>Category</th>
                                 <th>Item Description</th>
                                 <th>Packaging</th>
                                 <th>Price</th>
                                 <th>Quantity</th>
                                 <th>Action</th>
                             </tr>
                         </thead>
                         <tbody class="inventory">
                             <!-- Inventory items loaded via JS -->
                         </tbody>
                     </table>
                 </div>
             </div>
             <div class="modal-footer">
                  <button class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-check"></i> Done Selecting</button>
             </div>
         </div>
     </div>

     <!-- Cart Modal -->
    <div id="cartModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeCartModal()">&times;</span>
            <div class="modal-header"><h2>Order Cart</h2></div>
            <div class="modal-body">
                <div class="cart-table-container">
                    <table class="cart-table">
                        <thead>
                             <tr>
                                 <th>Category</th>
                                 <th>Item</th>
                                 <th>Packaging</th>
                                 <th>Price</th>
                                 <th>Qty</th>
                                 <th>Action</th>
                             </tr>
                        </thead>
                        <tbody class="cart">
                            <!-- Cart items loaded by JS -->
                        </tbody>
                    </table>
                    <p class="no-products" style="display: none;">Your cart is empty.</p>
                </div>
                <div class="cart-total">Total: <span class="total-amount">PHP 0.00</span></div>
            </div>
            <div class="modal-footer">
                 <button class="cancel-btn" onclick="closeCartModal()"><i class="fas fa-times"></i> Close</button>
                 <button class="save-btn" onclick="saveCartChanges()"><i class="fas fa-check"></i> Update Order</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Add Order -->
    <div id="addConfirmationModal" class="modal confirmation-modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Confirm New Order</h2></div>
            <div class="modal-body">
                <p class="confirmation-message">Are you sure you want to add this order?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeAddConfirmation()"><i class="fas fa-times"></i> No</button>
                <button type="button" class="save-btn" onclick="submitAddOrder()"><i class="fas fa-check"></i> Yes, Add Order</button>
            </div>
        </div>
    </div>


    <!-- Cart Icon -->
    <div id="cartIconContainer" onclick="openCartModal()">
         <div id="cartIcon">
              <i class="fas fa-shopping-cart"></i>
              <span id="cartItemCount">0</span>
         </div>
    </div>


    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script> <!-- jQuery UI for Datepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script> <!-- Adjust path as needed -->
    <!-- html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>

    <script>
        // --- Global Variables ---\
        let currentPoNumber = '';
        let currentOrderOriginalStatus = ''; // Store the status when modal opens
        let currentOrderItems = []; // For progress tracking
        let completedItems = []; // For progress tracking
        let quantityProgressData = {}; // For progress tracking
        let itemProgressPercentages = {}; // For progress tracking
        let overallProgress = 0; // For progress tracking
        let itemContributions = {}; // Helper for weighted progress calculation
        let currentDriverId = 0; // For driver assignment
        let currentPOData = null; // For PDF generation preview
        let selectedStatus = ''; // Target status for change confirmation
        let poDownloadData = null; // For direct PDF download data
        let cartItems = []; // Holds items for the new order being created

        // --- Utility Functions ---\
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<div class="toast-content"><i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i><div class="message"><span class="text text-1">${type.charAt(0).toUpperCase() + type.slice(1)}</span><span class="text text-2">${message}</span></div></div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }

        function formatWeight(weightInGrams) {
            if (weightInGrams === null || weightInGrams === undefined) return 'N/A';
            const weightNum = parseFloat(weightInGrams);
            if (isNaN(weightNum)) return 'N/A';
            if (weightNum >= 1000) return (weightNum / 1000).toFixed(2) + ' kg';
            return weightNum.toFixed(2) + ' g';
        }

        // --- Status Change Logic ---\

        // Opens modal for ACTIVE orders (NO material check)
        function confirmStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status ('Active')
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);
            // **NO Material Check performed here**
            $('#statusModal').css('display', 'flex');
        }

        // Opens modal for REJECTED orders
        function confirmRejectedStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status ('Rejected')
            $('#rejectedStatusModal').data('po_number', poNumber); // Store context for closing confirmation
            $('#rejectedStatusMessage').text(`Change status for rejected order ${poNumber} (${username})`);
            $('#rejectedStatusModal').css('display', 'flex');
        }

        // Opens modal for PENDING orders (WITH material check)
        function confirmPendingStatusChange(poNumber, username, ordersJsonString, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Store original status ('Pending')
            $('#pendingStatusModal').data('po_number', poNumber); // Store context
            $('#pendingStatusMessage').text('Change order status for ' + poNumber);

            const materialContainer = $('#rawMaterialsContainer');
            materialContainer.html('<h3>Loading inventory status...</h3>');
            $('#pendingStatusModal').css('display', 'flex');

            try {
                 if (!ordersJsonString) throw new Error("Order items data missing.");
                 // --- Add check for product_id in the FIRST item before parsing ---
                 // Decode HTML entities first
                 const tempDiv = document.createElement('div');
                 tempDiv.innerHTML = ordersJsonString;
                 const decodedJsonString = tempDiv.textContent || tempDiv.innerText || "";

                 if (decodedJsonString.length > 5 && !decodedJsonString.includes('"product_id":')) {
                     console.warn("Received ordersJson might be missing product_id:", decodedJsonString.substring(0, 100) + "...");
                     // Display a more user-friendly error in this case
                     throw new Error("Order data seems incomplete or corrupted. It might be missing essential product information. Please try adding the order again. If the problem persists, contact support.");
                 }
                 // --- End check ---
                 JSON.parse(decodedJsonString); // Validate JSON format AFTER decoding

                $.ajax({
                    url: '../../backend/check_raw_materials.php', // Adjusted path
                    type: 'POST',
                    data: { orders: decodedJsonString, po_number: poNumber }, // Send decoded JSON
                    dataType: 'json',
                    success: function(response) {
                        console.log("Inventory Check (Pending):", response);
                        if (response.success) {
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer');
                            if (needsMfg && response.materials) {
                                displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section');
                            } else if (needsMfg) { // Needs manufacturing but no material data
                                $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Info unavailable.</p>');
                            } else if (!needsMfg && response.finishedProducts) { // No mfg needed, products info available
                                materialContainer.append('<p>All required products in stock.</p>');
                                $('#rawMaterialsContainer #raw-materials-section').remove(); // Remove raw mats section if no mfg needed
                            } else if (!response.finishedProducts && !response.materials) { // No info at all
                                 materialContainer.html('<h3>Inventory Status</h3><p>No details available.</p>');
                            }
                            updatePendingOrderActionStatus(response); // Enable/disable 'Active' button based on checks
                        } else { // AJAX call succeeded but backend reported failure
                            materialContainer.html(`<h3>Inventory Check Error</h3><p style="color:red;">${response.message || 'Unknown error'}</p><p>Status change allowed, but check failed.</p>`);
                            $('#activeStatusBtn').prop('disabled', false); // Allow activation attempt despite check failure
                        }
                    },
                    error: function(xhr, status, error) { // AJAX call itself failed or parsing failed
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText); // Log the raw response
                        let errorMsg = `Could not check inventory: ${error || status}`;
                        if (status === 'parsererror') {
                             errorMsg = `Could not check inventory: Invalid response from server. Check console for details.`;
                        }
                        materialContainer.html(`<h3>Server Error</h3><p style="color:red;">${errorMsg}</p><p>Status change allowed, but check failed.</p>`);
                        $('#activeStatusBtn').prop('disabled', false); // Allow activation attempt
                    }
                });
            } catch (e) { // Error parsing ordersJson or other JS error
                materialContainer.html(`<h3>Data Error</h3><p style="color:red;">${e.message}</p><p>Status change allowed, but check failed.</p>`);
                $('#activeStatusBtn').prop('disabled', false); // Allow activation attempt
                console.error("Error processing data for pending status change:", e);
            }
        }

        // Opens the generic CONFIRMATION modal
        function confirmStatusAction(status) {
            selectedStatus = status; // Store the TARGET status

            let confirmationMsg = `Are you sure you want to change the status to ${selectedStatus}?`;
            // Add specific warnings based on TARGET status and ORIGINAL status
            if (selectedStatus === 'Active') { // Moving TO Active (from Pending or Rejected)
                 confirmationMsg += ' This will deduct required stock from inventory.';
            } else if (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')) { // Moving FROM Active
                 confirmationMsg += ' This will attempt to return deducted stock to inventory.';
            } else if (selectedStatus === 'For Delivery') {
                 confirmationMsg += ' Ensure progress is 100% and a driver is assigned.';
            }

            $('#statusConfirmationMessage').text(confirmationMsg);
            $('#statusConfirmationModal').css('display', 'flex'); // Use flex for centering

            // Hide the originating status modal
            $('#statusModal, #pendingStatusModal, #rejectedStatusModal').css('display', 'none');
        }

        // Closes the CONFIRMATION modal, reopens the originating status modal
        function closeStatusConfirmation() {
            $('#statusConfirmationModal').css('display', 'none');
            // Reopen the correct modal based on the stored original status
             if (currentOrderOriginalStatus === 'Pending') {
                 $('#pendingStatusModal').css('display', 'flex');
             } else if (currentOrderOriginalStatus === 'Rejected') {
                 $('#rejectedStatusModal').css('display', 'flex');
             } else if (currentOrderOriginalStatus === 'Active') {
                  $('#statusModal').css('display', 'flex');
             }
             // Clear the selected target status
             selectedStatus = '';
        }

        // Called after clicking "Yes" on the confirmation modal
        function executeStatusChange() {
            $('#statusConfirmationModal').css('display', 'none'); // Hide confirmation

            // Determine flags based on TARGET status and ORIGINAL status
            let deductMaterials = (selectedStatus === 'Active'); // Deduct ONLY when target is Active (from Pending/Rejected)
            let returnMaterials = (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')); // Return ONLY when moving FROM Active to Pending/Rejected

            // Special check for 'For Delivery' requirements
            if (selectedStatus === 'For Delivery') {
                if (currentOrderOriginalStatus !== 'Active') {
                     showToast('Error: Can only mark Active orders for delivery.', 'error');
                     closeRelevantStatusModals(); // Close any open modals
                     return;
                }
                showToast('Checking requirements for delivery...', 'info');
                fetch(`../../backend/check_order_driver.php?po_number=${currentPoNumber}`) // Adjusted path
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (!data.driver_assigned) { showToast('Error: Assign driver first.', 'error'); closeRelevantStatusModals(); return; }
                            // Use the progress fetched from backend if available, otherwise rely on JS state (less reliable)
                            const progressToCheck = data.progress !== undefined ? data.progress : overallProgress;
                            if (progressToCheck < 100) {
                                 showToast(`Error: Progress must be 100% (currently ${Math.round(progressToCheck)}%).`, 'error');
                                 closeRelevantStatusModals();
                                 return;
                            }
                            // Requirements met, proceed (NO material adjustment needed Active -> For Delivery)
                            updateOrderStatus(selectedStatus, false, false);
                        } else { showToast('Error checking requirements: ' + data.message, 'error'); closeRelevantStatusModals(); }
                    })
                    .catch(error => { console.error('Delivery check error:', error); showToast('Error checking requirements: ' + error, 'error'); closeRelevantStatusModals(); });
            } else {
                 // Proceed with other status changes, sending calculated flags
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

            fetch('../../backend/update_order_status.php', { method: 'POST', body: formData }) // Adjusted path
            .then(response => response.text().then(text => { // Get text first
                 try {
                     const jsonData = JSON.parse(text);
                     // Check HTTP status code AND success flag from JSON
                     if (!response.ok || !jsonData.success) {
                         throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     }
                     return jsonData;
                 } catch (e) {
                     console.error('Invalid JSON or failed response:', text);
                     // Try to provide a more specific error if possible
                     let errorDetail = e.message.includes('Unexpected token') ? 'Invalid server response format.' : e.message;
                     throw new Error(errorDetail);
                 }
            }))
            .then(data => {
                console.log("Status update response:", data);
                // Success is already checked in the previous step
                let message = `Status updated to ${status} successfully`;
                if (deductMaterials && data.deduction_message) message += ` ${data.deduction_message}`; // Use message from backend
                if (returnMaterials && data.return_message) message += ` ${data.return_message}`; // Use message from backend
                showToast(message, 'success');
                setTimeout(() => { window.location.reload(); }, 1500);
            })
            .catch(error => {
                console.error("Update status fetch/processing error:", error);
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => {
                 closeRelevantStatusModals(); // Close relevant status modals
                 currentPoNumber = ''; // Clear current PO context
                 currentOrderOriginalStatus = '';
                 selectedStatus = '';
            });
        }


        // --- Modal Closing Helpers ---\
        function closeStatusModal() { $('#statusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number'); }
        function closePendingStatusModal() { $('#pendingStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        // --- Material Display Helpers (used by Pending modal) ---\
        function displayFinishedProducts(productsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return false;
             let html = `<h3>Finished Products Status</h3>`;
             if (!productsData || Object.keys(productsData).length === 0) { html += '<p>No finished product information available.</p>'; container.html(html).append('<div id="raw-materials-section"></div>'); return false; } // Ensure section div exists even if empty
             html += `<table class="materials-table"><thead><tr><th>Product</th><th>In Stock</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(productsData).forEach(product => {
                 const data = productsData[product];
                 const available = parseInt(data.available) || 0; const required = parseInt(data.required) || 0; const isSufficient = data.sufficient; const shortfall = data.shortfall || 0;
                 html += `<tr><td>${product}</td><td>${available}</td><td>${required}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'In Stock' : `Shortfall: ${shortfall}`}</td></tr>`;
             });
             html += `</tbody></table>`; container.html(html); // Replace container content
             const needsMfg = Object.values(productsData).some(p => !p.sufficient);
             // Append the raw materials section placeholder *after* setting the main HTML
             if (needsMfg) container.append('<div id="raw-materials-section"><h3>Raw Materials Required</h3><p>Loading...</p></div>');
             return needsMfg;
        }

        function displayRawMaterials(materialsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return true; // Container not found, assume okay?
             let html = '<h3>Raw Materials Required</h3>';
             if (!materialsData || Object.keys(materialsData).length === 0) { container.html(html + '<p>No raw materials information available.</p>'); return true; } // No materials needed or info unavailable
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
             container.html(html + `<p class="materials-status ${cls}">${msg}</p>`); // Replace container content
             return allSufficient; // Return true if all materials are sufficient
        }


        // Updates the 'Active' button state in the PENDING modal based on inventory check
        function updatePendingOrderActionStatus(response) {
             let canActivate = true;
             let msg = 'Ready to activate.';
             const cont = $('#rawMaterialsContainer'); // Container for materials display
             const prods = response.finishedProducts || {};
             const hasProductsInfo = Object.keys(prods).length > 0;
             const allProdsInStock = hasProductsInfo && Object.values(prods).every(p => p.sufficient);

             // Scenario 1: Needs manufacturing (some products not sufficient)
             if (response.needsManufacturing && !allProdsInStock) {
                 const canMfgAll = hasProductsInfo && Object.values(prods).every(p => p.sufficient || p.canManufacture === true); // Check if ALL non-sufficient can be manufactured

                 if (!canMfgAll) {
                     // Find which product cannot be manufactured (missing ingredients definition)
                     const cannotMfg = Object.entries(prods).find(([name, data]) => !data.sufficient && data.canManufacture === false);
                     canActivate = false;
                     msg = cannotMfg ? `Cannot activate: Missing ingredients definition for ${cannotMfg[0]}.` : 'Cannot activate: Some products cannot be manufactured (check ingredients).';
                 } else {
                     // Check if raw materials are sufficient
                     const mats = response.materials || {};
                     const hasMatsInfo = Object.keys(mats).length > 0;
                     const allMatsSufficient = hasMatsInfo && Object.values(mats).every(m => m.sufficient);

                     if (!allMatsSufficient) {
                          canActivate = false;
                          msg = 'Cannot activate: Insufficient raw materials.';
                     } else {
                          msg = 'Manufacturing required. Materials sufficient. Ready.';
                     }
                 }
             }
             // Scenario 2: All finished products are in stock
             else if (allProdsInStock) {
                 msg = 'All products in stock. Ready.';
             }
             // Scenario 3: No finished product info OR no manufacturing needed (but maybe product info was missing?)
             else if (!hasProductsInfo && !response.needsManufacturing) {
                 // Allow activation attempt, but warn the user
                 msg = 'Inventory details unclear, proceed with caution.';
             }
             // Scenario 4: Needs manufacturing, but all finished products *were* somehow marked sufficient (edge case)
             else if (response.needsManufacturing && allProdsInStock) {
                  msg = 'Ready to activate (Manufacturing might still occur if stock is borderline).'; // Adjust message if needed
             }


             $('#activeStatusBtn').prop('disabled', !canActivate); // Disable/Enable button

             // Update or append the status message paragraph
             const cls = canActivate ? 'status-sufficient' : 'status-insufficient';
             let statEl = cont.children('.materials-status'); // Find existing status message
             if (statEl.length) {
                 statEl.removeClass('status-sufficient status-insufficient').addClass(cls).text(msg);
             } else {
                 cont.append(`<p class="materials-status ${cls}">${msg}</p>`); // Append if not found
             }
        }


        // --- Order Details Modal Functions (Progress Tracking) ---\
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            $('#orderDetailsPoNumber').text(poNumber); // Set PO in modal title

            // Show loading state
            $('#orderDetailsBody').html('<tr><td colspan="6" style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading details...</td></tr>');
            $('#overall-progress-info, .save-progress-btn').hide(); // Hide progress elements initially
            $('#orderDetailsModal').css('display', 'flex');

            fetch(`../../backend/get_order_details.php?po_number=${poNumber}`) // Adjusted path
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    currentOrderItems = data.orderItems || [];
                    completedItems = data.completedItems || []; // Array of COMPLETED item indices
                    quantityProgressData = data.quantityProgressData || {}; // Object: { itemIndex: [true, false, true...] }
                    itemProgressPercentages = {}; // Reset/Recalculate based on quantityProgressData
                    itemContributions = {}; // Reset/Recalculate
                    overallProgress = 0; // Reset/Recalculate

                    const orderDetailsBody = $('#orderDetailsBody').empty();
                    $('#status-header-cell').show(); // Show status/progress column
                    $('#orderStatus').text(data.orderStatus || 'Active'); // Show current status

                    if (currentOrderItems.length === 0) {
                         orderDetailsBody.html('<tr><td colspan="6" style="text-align:center; padding:20px;">No items found for this order.</td></tr>');
                         $('#orderTotalAmount').text('PHP 0.00');
                         return; // Exit if no items
                    }

                    const totalItemsCount = currentOrderItems.length;
                    let calculatedOverallProgress = 0;

                    currentOrderItems.forEach((item, index) => {
                        const itemQuantity = parseInt(item.quantity) || 0;
                        const contributionPerItem = totalItemsCount > 0 ? (100 / totalItemsCount) : 0;
                        itemContributions[index] = contributionPerItem; // Store contribution weight

                        // Calculate unit progress based ONLY on quantityProgressData
                        let unitCompletedCount = 0;
                        if (itemQuantity > 0 && quantityProgressData[index]) {
                            for (let i = 0; i < itemQuantity; i++) {
                                if (quantityProgressData[index][i] === true) {
                                    unitCompletedCount++;
                                }
                            }
                        }
                        const unitProgress = itemQuantity > 0 ? (unitCompletedCount / itemQuantity) * 100 : 0;
                        itemProgressPercentages[index] = unitProgress; // Store initial percentage

                        const contributionToOverall = (unitProgress / 100) * contributionPerItem;
                        calculatedOverallProgress += contributionToOverall;

                        // Determine if main row checkbox should be checked (only if ALL units are done)
                        const isFullyCompleted = itemQuantity > 0 && unitProgress === 100;
                        // Checkbox is disabled if there are units to track individually
                        const isCheckboxDisabled = itemQuantity > 0;

                        const mainRow = $('<tr>').addClass('item-header-row')
                                        .toggleClass('completed-item', isFullyCompleted)
                                        .attr('data-item-index', index);

                        mainRow.html(`<td>${item.category || ''}</td>
                            <td>${item.item_description}</td>
                            <td>${item.packaging || ''}</td>
                            <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${itemQuantity}</td>
                            <td class="status-cell">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                     <!-- Main Checkbox: Checked if all units done, disabled if units exist -->
                                     <input type="checkbox" class="item-status-checkbox" data-index="${index}"
                                            onchange="updateRowStyle(this)"
                                            ${isFullyCompleted ? 'checked' : ''}
                                            ${isCheckboxDisabled ? 'disabled' : ''}>

                                     <!-- Toggle Button: Only show if units exist -->
                                     ${itemQuantity > 0 ? `<button class="toggle-units-btn" onclick="toggleQuantityProgress(${index})"><i class="fas fa-chevron-down"></i></button>` : ''}
                                </div>
                                <!-- Progress Bar: Only show if units exist -->
                                ${itemQuantity > 0 ? `<div class="item-progress-bar-container">
                                                        <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div>
                                                        <div class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}% Complete</div>
                                                     </div>
                                                     <div class="contribution-text" id="contribution-text-${index}">Contributes ${contributionToOverall.toFixed(1)}% to total</div>` : ''}
                            </td>`);
                        orderDetailsBody.append(mainRow);

                        // Add unit rows if quantity > 0
                        if (itemQuantity > 0) {
                             const dividerRow = $('<tr>').addClass('units-divider').attr('id', `units-divider-${index}`).hide().html(`<td colspan="6" style="border: none; padding: 2px 0; background-color: #eee;"></td>`);
                             orderDetailsBody.append(dividerRow);
                             for (let i = 0; i < itemQuantity; i++) {
                                 const isUnitCompleted = quantityProgressData[index] && quantityProgressData[index][i] === true;
                                 const unitRow = $('<tr>').addClass(`unit-row unit-for-item-${index}`).hide()
                                                 .toggleClass('completed', isUnitCompleted)
                                                 .html(`<td colspan="5" style="padding-left: 30px;">Unit ${i + 1}</td>
                                                        <td><input type="checkbox" class="unit-status-checkbox" data-item-index="${index}" data-unit-index="${i}" onchange="updateUnitStatus(this)" ${isUnitCompleted ? 'checked' : ''}></td>`);
                                 orderDetailsBody.append(unitRow);
                             }
                             const actionRow = $('<tr>').addClass(`unit-row unit-action-row unit-for-item-${index}`).hide()
                                               .html(`<td colspan="6" style="text-align: right; padding: 10px;">
                                                        <button class="action-btn" onclick="selectAllUnits(${index}, ${itemQuantity})">Select All</button>
                                                        <button class="action-btn" onclick="deselectAllUnits(${index}, ${itemQuantity})">Deselect All</button>
                                                     </td>`);
                             orderDetailsBody.append(actionRow);
                        }
                    }); // End forEach item

                    overallProgress = calculatedOverallProgress; // Set global overall progress
                    updateOverallProgressDisplay(); // Update the display

                    let totalAmount = currentOrderItems.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0);
                    $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);

                    $('#overall-progress-info, .save-progress-btn').show(); // Show progress elements
                    $('#orderDetailsModal').css('display', 'flex');
                } else {
                    showToast('Error fetching details: ' + data.message, 'error');
                    // Optionally close modal or show error inside it
                    $('#orderDetailsBody').html(`<tr><td colspan="6" style="text-align:center; padding:20px; color:red;">${data.message || 'Could not load details.'}</td></tr>`);
                     $('#orderDetailsModal').css('display', 'flex'); // Keep modal open to show error
                }
            })
            .catch(error => {
                showToast('Error fetching details: ' + error.message, 'error');
                console.error('Fetch order details error:', error);
                 $('#orderDetailsBody').html(`<tr><td colspan="6" style="text-align:center; padding:20px; color:red;">Error loading details. Check console.</td></tr>`);
                 $('#orderDetailsModal').css('display', 'flex'); // Keep modal open to show error
            });
        }

        // Simplified version for non-Active orders
        function viewOrderInfo(ordersJsonString, orderStatus) {
             try {
                 // Decode HTML entities first
                 const tempDiv = document.createElement('div');
                 tempDiv.innerHTML = ordersJsonString;
                 const decodedJsonString = tempDiv.textContent || tempDiv.innerText || "";
                 const orderDetails = JSON.parse(decodedJsonString);

                 const body = $('#orderDetailsBody').empty();
                 $('#status-header-cell').hide(); // Hide status/progress column
                 $('#orderStatus').text(orderStatus); // Show status
                 $('#orderDetailsPoNumber').text(currentPoNumber); // Set PO in modal title

                 let total = 0;
                 if (orderDetails.length === 0) {
                      body.html('<tr><td colspan="5" style="text-align:center; padding:20px;">No items found for this order.</td></tr>');
                 } else {
                      orderDetails.forEach(p => {
                          const itemTotal = (parseFloat(p.price) || 0) * (parseInt(p.quantity) || 0);
                          total += itemTotal;
                          body.append(`<tr>
                                        <td>${p.category||''}</td>
                                        <td>${p.item_description || 'N/A'}</td>
                                        <td>${p.packaging||''}</td>
                                        <td>PHP ${parseFloat(p.price || 0).toFixed(2)}</td>
                                        <td>${p.quantity || 0}</td>
                                     </tr>`);
                       });
                 }
                 $('#orderTotalAmount').text(`PHP ${total.toFixed(2)}`);
                 $('#overall-progress-info, .save-progress-btn').hide(); // Hide progress elements
                 $('#orderDetailsModal').css('display', 'flex');
             } catch (e) {
                 console.error('Parse error or data issue in viewOrderInfo:', e, ordersJsonString);
                 showToast('Error displaying order info', 'error');
                 // Optionally show error in modal
                 $('#orderDetailsBody').html(`<tr><td colspan="5" style="text-align:center; padding:20px; color:red;">Could not display order items.</td></tr>`);
                 $('#overall-progress-info, .save-progress-btn').hide();
                 $('#orderDetailsModal').css('display', 'flex');
             }
        }

        function toggleQuantityProgress(itemIndex) {
             $(`.unit-for-item-${itemIndex}, #units-divider-${itemIndex}`).toggle();
             // Toggle chevron direction
             const icon = $(`.item-header-row[data-item-index="${itemIndex}"] .toggle-units-btn i`);
             icon.toggleClass('fa-chevron-down fa-chevron-up');
        }

        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.dataset.itemIndex);
            const unitIndex = parseInt(checkbox.dataset.unitIndex);
            const isChecked = checkbox.checked;
            $(checkbox).closest('tr').toggleClass('completed', isChecked);

            // Ensure the progress data structure exists
            if (!quantityProgressData[itemIndex]) {
                 quantityProgressData[itemIndex] = [];
                 // Initialize based on current item quantity if needed, though it should exist from load
                 const itemQty = parseInt(currentOrderItems[itemIndex]?.quantity) || 0;
                 for (let i = 0; i < itemQty; i++) { quantityProgressData[itemIndex][i] = false; }
            }
            // Update the specific unit's status
            quantityProgressData[itemIndex][unitIndex] = isChecked;

            updateItemProgress(itemIndex); // Recalculate and update item progress bar/text
            updateOverallProgress(); // Recalculate and update overall progress bar/text
        }

        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex];
            const qty = parseInt(item.quantity) || 0;
            if (qty === 0) return; // No progress if quantity is zero

            let completedUnits = 0;
            if (quantityProgressData[itemIndex]) {
                for (let i = 0; i < qty; i++) {
                    if (quantityProgressData[itemIndex][i] === true) {
                        completedUnits++;
                    }
                }
            }

            const progress = (completedUnits / qty) * 100;
            itemProgressPercentages[itemIndex] = progress; // Update stored percentage

            const contribution = (progress / 100) * (itemContributions[itemIndex] || 0); // Use stored contribution

            // Update UI elements
            $(`#item-progress-bar-${itemIndex}`).css('width', `${progress}%`);
            $(`#item-progress-text-${itemIndex}`).text(`${Math.round(progress)}% Complete`);
            $(`#contribution-text-${itemIndex}`).text(`Contributes ${contribution.toFixed(1)}% to total`);

            // Update the main row's completed status and checkbox
            updateItemStatusBasedOnUnits(itemIndex, completedUnits === qty);
        }

         function updateOverallProgressDisplay() {
             const rounded = Math.round(overallProgress);
             $('#overall-progress-bar').css('width', `${rounded}%`);
             $('#overall-progress-text').text(`${rounded}%`);
             $('#overall-progress-info strong').text(`Overall Progress (${rounded}%):`);
         }

         function updateOverallProgress() {
             let newProgress = 0;
             // Iterate through all items that *should* have progress calculated
             currentOrderItems.forEach((item, index) => {
                 const prog = itemProgressPercentages[index]; // Use the calculated item percentage
                 const contrib = itemContributions[index]; // Use the stored contribution weight
                 if (prog !== undefined && !isNaN(prog) && contrib !== undefined && !isNaN(contrib)) {
                     newProgress += (prog / 100) * contrib;
                 }
             });
             overallProgress = newProgress; // Update global variable
             updateOverallProgressDisplay(); // Update UI
             return Math.round(overallProgress); // Return rounded value for saving
         }

         function updateItemStatusBasedOnUnits(itemIndex, allUnitsComplete) {
             const intIndex = parseInt(itemIndex);
             const mainRow = $(`tr[data-item-index="${intIndex}"]`);
             const mainCheckbox = $(`.item-status-checkbox[data-index="${intIndex}"]`);

             mainRow.toggleClass('completed-item', allUnitsComplete);
             mainCheckbox.prop('checked', allUnitsComplete);

             // Update the separate completedItems array (if you still need it for backend)
             const idxInArray = completedItems.indexOf(intIndex);
             if (allUnitsComplete && idxInArray === -1) {
                 completedItems.push(intIndex);
             } else if (!allUnitsComplete && idxInArray > -1) {
                 completedItems.splice(idxInArray, 1);
             }
         }


        function selectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            checkboxes.prop('checked', true); // Check all unit checkboxes
            checkboxes.closest('tr').addClass('completed'); // Mark rows visually

            // Update the data structure
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = [];
            for (let i = 0; i < quantity; i++) {
                quantityProgressData[itemIndex][i] = true;
            }

            updateItemProgress(itemIndex); // Update item progress bar/text
            updateOverallProgress(); // Update overall progress
        }

        function deselectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`);
            checkboxes.prop('checked', false); // Uncheck all unit checkboxes
            checkboxes.closest('tr').removeClass('completed'); // Unmark rows visually

            // Update the data structure
            if (!quantityProgressData[itemIndex]) quantityProgressData[itemIndex] = [];
            for (let i = 0; i < quantity; i++) {
                quantityProgressData[itemIndex][i] = false;
            }

            updateItemProgress(itemIndex); // Update item progress bar/text
            updateOverallProgress(); // Update overall progress
        }

        // This function is likely redundant if item checkboxes are disabled when units exist.
        // Kept for potential scenarios where items have 0 quantity or no units.
        function updateRowStyle(checkbox) {
            const index = parseInt(checkbox.dataset.index);
            const isChecked = checkbox.checked;
            const qty = parseInt(currentOrderItems[index]?.quantity) || 0;

            // Only proceed if the checkbox is NOT disabled (meaning qty is likely 0)
            if (!$(checkbox).prop('disabled')) {
                 $(checkbox).closest('tr').toggleClass('completed-item', isChecked);

                 // Update completedItems array
                 const intIndex = parseInt(index);
                 const idxInArray = completedItems.indexOf(intIndex);
                 if (isChecked && idxInArray === -1) completedItems.push(intIndex);
                 else if (!isChecked && idxInArray > -1) completedItems.splice(idxInArray, 1);

                 // Update itemProgressPercentages for this item
                 itemProgressPercentages[index] = isChecked ? 100 : 0;
                 updateOverallProgress(); // Recalculate overall progress
            }
        }

        function closeOrderDetailsModal() {
             $('#orderDetailsModal').css('display', 'none');
             // Clear state if needed
             currentPoNumber = '';
             currentOrderItems = [];
             completedItems = [];
             quantityProgressData = {};
             itemProgressPercentages = {};
             overallProgress = 0;
             itemContributions = {};
        }

        function confirmSaveProgress() { $('#saveProgressConfirmationModal').css('display', 'flex'); } // Use flex
        function closeSaveProgressConfirmation() { $('#saveProgressConfirmationModal').css('display', 'none'); }

        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').hide();
            const finalProgress = updateOverallProgress(); // Ensure calculation is up-to-date

            const payload = {
                 po_number: currentPoNumber,
                 // completed_items: completedItems, // This might be redundant if backend relies on quantity_progress
                 quantity_progress: quantityProgressData,
                 overall_progress: finalProgress
            };
            console.log("Saving Progress Payload:", payload);

            fetch('../../backend/update_order_progress.php', { // Adjusted path
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify(payload)
            })
            .then(response => {
                 if (!response.ok) {
                      return response.json().then(errData => { throw new Error(errData.message || `Server error: ${response.status}`); });
                 }
                 return response.json();
            })
            .then(data => {
                 if (data.success) {
                      showToast('Progress updated successfully', 'success');
                      setTimeout(() => { window.location.reload(); }, 1000);
                 } else {
                      throw new Error(data.message || 'Unknown error saving progress.');
                 }
            })
            .catch(error => {
                 showToast('Error saving progress: ' + error.message, 'error');
                 console.error('Save progress error:', error);
            });
        }


        // --- Driver Assignment Modal Functions ---\
        function confirmDriverAssign(poNumber) { currentPoNumber = poNumber; currentDriverId = 0; $('#driverModalTitle').text('Assign Driver'); $('#driverModalMessage').text(`Select driver for ${poNumber}`); $('#driverSelect').val(0); $('#driverModal').show(); }
        function confirmDriverChange(poNumber, driverId, driverName) { currentPoNumber = poNumber; currentDriverId = driverId; $('#driverModalTitle').text('Change Driver'); $('#driverModalMessage').text(`Current driver for ${poNumber}: ${driverName}`); $('#driverSelect').val(driverId); $('#driverModal').show(); }
        function closeDriverModal() { $('#driverModal').hide(); currentDriverId = 0; /* Clear selected driver? */ $('#driverSelect').val(0); }
        function confirmDriverAssignment() {
            const driverId = parseInt($('#driverSelect').val()); if (driverId === 0 || isNaN(driverId)) { showToast('Please select a driver.', 'error'); return; }
            const name = $('#driverSelect option:selected').text(); let msg = `Assign driver ${name} to order ${currentPoNumber}?`; if (currentDriverId > 0 && currentDriverId !== driverId) msg = `Change driver for order ${currentPoNumber} to ${name}?`; else if (currentDriverId === driverId) { showToast('No change detected.', 'info'); return; }
            $('#driverConfirmationModal .confirmation-message').text(msg);
            $('#driverConfirmationModal').css('display', 'flex'); // Use flex
            $('#driverModal').hide(); // Hide selection modal
        }
        function closeDriverConfirmation() { $('#driverConfirmationModal').hide(); $('#driverModal').show(); } // Re-show selection modal

        function assignDriver() {
            $('#driverConfirmationModal').hide();
            const driverId = parseInt($('#driverSelect').val());
            if (driverId === 0 || isNaN(driverId)) {
                 showToast('Invalid driver selected.', 'error');
                 return;
            }
            // Find the button within the confirmation modal if needed, or use a generic loading state
            // For simplicity, just show toast and proceed
            showToast('Assigning driver...', 'info');

            const fd = new FormData();
            fd.append('po_number', currentPoNumber);
            fd.append('driver_id', driverId);

            console.log("Assigning Driver - PO:", currentPoNumber, "Driver ID:", driverId); // Debug log

            fetch('../../backend/assign_driver.php', { method: 'POST', body: fd }) // Adjusted path
                .then(response => {
                     // Check if response is ok (status 200-299)
                     if (!response.ok) {
                          // Try to parse error message from JSON if possible
                          return response.json().then(errData => {
                               throw new Error(errData.message || `Server error: ${response.status}`);
                          }).catch(() => {
                               // If JSON parsing fails, throw generic error
                               throw new Error(`Server error: ${response.status}`);
                          });
                     }
                     return response.json(); // Parse JSON body for successful responses
                })
                .then(data => {
                     console.log("Assign driver response:", data); // Debug log
                     if (data.success) {
                          showToast('Driver assigned successfully.', 'success');
                          setTimeout(() => { window.location.reload(); }, 1000); // Reload to reflect changes
                     } else {
                          // Error message from backend JSON response
                          showToast('Assignment error: ' + (data.message || 'Unknown error'), 'error');
                     }
                })
                .catch(e => {
                     // Network errors or errors thrown in .then() blocks
                     console.error("Assign driver fetch error:", e);
                     showToast('Network or processing error: ' + e.message, 'error');
                })
                .finally(() => {
                    // No need to manage button state if we reload anyway
                    closeDriverModal(); // Ensure modal is closed
                    currentPoNumber = ''; // Clear context
                    currentDriverId = 0;
                });
        }


        // --- PDF Download Functions ---\
        function confirmDownloadPO(...args) {
             // Arguments: poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions
             try {
                 // Decode potential HTML entities in arguments
                 const decodedArgs = args.map(arg => {
                     if (typeof arg === 'string') {
                         const tempDiv = document.createElement('div');
                         tempDiv.innerHTML = arg;
                         return tempDiv.textContent || tempDiv.innerText || "";
                     }
                     return arg;
                 });

                 poDownloadData = {
                     poNumber: decodedArgs[0], username: decodedArgs[1], company: decodedArgs[2],
                     orderDate: decodedArgs[3], deliveryDate: decodedArgs[4], deliveryAddress: decodedArgs[5],
                     ordersJson: decodedArgs[6], // Already decoded JSON string
                     totalAmount: decodedArgs[7], specialInstructions: decodedArgs[8]
                 };
                 console.log("Data for PDF:", poDownloadData); // Debug: Check company name here
                 $('#downloadConfirmationModal .confirmation-message').text(`Download PO ${poDownloadData.poNumber}?`);
                 $('#downloadConfirmationModal').css('display', 'flex'); // Use flex
             } catch (e) {
                  console.error("Error preparing PDF download data:", e, args);
                  showToast("Error preparing PDF data.", "error");
             }
         }
        function closeDownloadConfirmation() { $('#downloadConfirmationModal').hide(); poDownloadData = null; }
        function downloadPODirectly() {
            $('#downloadConfirmationModal').hide(); if (!poDownloadData) { showToast('No data for PO download', 'error'); return; }
            try {
                currentPOData = poDownloadData; // Use the data prepared in confirmDownloadPO
                // Use the company name passed, default to 'N/A' ONLY if it's truly empty/null
                $('#printCompany').text(currentPOData.company || 'N/A');
                $('#printPoNumber').text(currentPOData.poNumber);
                $('#printUsername').text(currentPOData.username);
                $('#printDeliveryAddress').text(currentPOData.deliveryAddress);
                // Dates are already formatted strings M d, Y
                $('#printOrderDate').text(currentPOData.orderDate);
                $('#printDeliveryDate').text(currentPOData.deliveryDate);
                $('#printTotalAmount').text(parseFloat(currentPOData.totalAmount).toFixed(2));

                const instrSec = $('#printInstructionsSection');
                if (currentPOData.specialInstructions && currentPOData.specialInstructions.trim()) {
                    $('#printSpecialInstructions').text(currentPOData.specialInstructions);
                    instrSec.show();
                } else {
                    instrSec.hide();
                }

                // Parse the already decoded JSON string
                const items = JSON.parse(currentPOData.ordersJson);
                const body = $('#printOrderItems').empty();
                if (items && items.length > 0) {
                    items.forEach(item => {
                        const total = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
                        // Ensure all expected properties exist before adding row
                         if (item.item_description !== undefined && item.quantity !== undefined && item.price !== undefined) {
                             body.append(`<tr>
                                         <td>${item.category || ''}</td>
                                         <td>${item.item_description}</td>
                                         <td>${item.packaging || ''}</td>
                                         <td>${item.quantity}</td>
                                         <td>PHP ${parseFloat(item.price).toFixed(2)}</td>
                                         <td>PHP ${total.toFixed(2)}</td>
                                      </tr>`);
                         } else {
                             console.warn("Skipping incomplete item in PDF:", item);
                         }
                    });
                } else {
                     body.append('<tr><td colspan="6" style="text-align: center;">No items found in order data.</td></tr>');
                }


                const element = document.getElementById('contentToDownload');
                const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
                showToast(`Generating PO PDF...`, 'info'); // Inform user
                html2pdf().set(opt).from(element).save().then(() => {
                    // showToast(`PO downloaded.`, 'success'); // Toast might be hidden by save dialog
                    console.log(`PO ${currentPOData.poNumber} PDF generation initiated.`);
                    currentPOData = null; // Clear data after use
                    poDownloadData = null;
                }).catch(e => {
                    console.error('PDF generation error:', e);
                    showToast('PDF generation error', 'error');
                    currentPOData = null;
                    poDownloadData = null;
                });
            } catch (e) {
                console.error('PDF preparation error:', e);
                showToast('PDF data error: ' + e.message, 'error');
                currentPOData = null;
                poDownloadData = null;
            }
        }
        // This function seems redundant if downloadPODirectly works. Kept for reference.
        function downloadPDF() { if (!currentPOData) { showToast('No data for PDF', 'error'); return; } const element = document.getElementById('contentToDownload'); const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }; html2pdf().set(opt).from(element).save(); }
        function closePDFPreview() { $('#pdfPreview').hide(); currentPOData = null; }

        // --- Special Instructions Modal ---\
        function viewSpecialInstructions(poNumber, instructions) {
             $('#instructionsPoNumber').text('PO: ' + poNumber);
             const content = $('#instructionsContent');
             // Decode HTML entities from instructions
             const tempDiv = document.createElement('div');
             tempDiv.innerHTML = instructions;
             const decodedInstructions = tempDiv.textContent || tempDiv.innerText || "";

             if (decodedInstructions && decodedInstructions.trim()) {
                 content.text(decodedInstructions).removeClass('empty');
             } else {
                 content.text('No special instructions provided.').addClass('empty');
             }
             $('#specialInstructionsModal').css('display', 'flex');
        }
        function closeSpecialInstructions() { $('#specialInstructionsModal').hide(); }

        // --- Add New Order Form Functions ---\
        function initializeDeliveryDatePicker() {
            if ($.datepicker) {
                 try { $("#delivery_date").datepicker("destroy"); } catch(e) {} // Destroy existing instance first, ignore error if not initialized
                 $("#delivery_date").datepicker({
                     dateFormat: 'yy-mm-dd',
                     minDate: 1, // Minimum date is tomorrow
                     beforeShowDay: function(date) {
                         const day = date.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                         // Allow Monday (1), Wednesday (3), Friday (5)
                         const isSelectable = (day === 1 || day === 3 || day === 5);
                         // Return format: [selectable, cssClass, tooltip]
                         return [isSelectable, isSelectable ? "" : "ui-datepicker-unselectable ui-state-disabled", isSelectable ? "" : "Not available"];
                     }
                 });
             } else {
                 console.error("jQuery UI Datepicker not loaded.");
                 // Fallback or alternative date input handling if needed
             }
        }
        function openAddOrderForm() {
             $('#addOrderForm')[0].reset(); // Reset form fields
             cartItems = []; // Clear the cart array
             updateOrderSummary(); // Update the summary table display
             updateCartItemCount(); // Update the cart icon count
             const today = new Date();
             const fmtDate = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
             $('#order_date').val(fmtDate); // Set order date to today
             initializeDeliveryDatePicker(); // Initialize delivery date picker
             $('#delivery_address_type').val('company'); // Default to company address
             toggleDeliveryAddress(); // Show/hide appropriate address field
             generatePONumber(); // Generate initial PO number (might be empty if no user selected)
             $('#addOrderOverlay').css('display', 'flex'); // Show the overlay
        }
        function closeAddOrderForm() { $('#addOrderOverlay').hide(); }

        function toggleDeliveryAddress() {
            const type = $('#delivery_address_type').val();
            const companyAddr = $('#company_address').val(); // Get current company address
            const customAddrInput = $('#custom_address');

            $('#company_address_container').toggle(type === 'company');
            $('#custom_address_container').toggle(type === 'custom');

            if (type === 'company') {
                $('#delivery_address').val(companyAddr); // Set hidden input to company address
                customAddrInput.prop('required', false); // Custom address not required
            } else {
                $('#delivery_address').val(customAddrInput.val()); // Set hidden input to custom address value
                customAddrInput.prop('required', true); // Custom address is required
            }
         }
        // Update hidden delivery address when custom address changes
        $('#custom_address').on('input', function() {
             if ($('#delivery_address_type').val() === 'custom') {
                 $('#delivery_address').val($(this).val());
             }
        });

        function generatePONumber() { // Also sets company address/name
            const userSelect = $('#username');
            const username = userSelect.val();
            const companyHiddenInput = $('#company_hidden'); // Hidden input for company name
            const companyAddressInput = $('#company_address'); // Readonly input for company address display

            if (username) {
                const selectedOption = userSelect.find('option:selected');
                const companyAddress = selectedOption.data('company-address') || '';
                const companyName = selectedOption.data('company') || ''; // Get company name

                companyAddressInput.val(companyAddress); // Display company address
                companyHiddenInput.val(companyName); // Set hidden input for company name

                // Update delivery address if 'company' type is selected
                if ($('#delivery_address_type').val() === 'company') {
                    $('#delivery_address').val(companyAddress);
                }

                // Generate PO Number (Timestamp format)
                const today = new Date();
                const datePart = `${today.getFullYear().toString().substr(-2)}${String(today.getMonth()+1).padStart(2,'0')}${String(today.getDate()).padStart(2,'0')}`;
                const timePart = `${String(today.getHours()).padStart(2,'0')}${String(today.getMinutes()).padStart(2,'0')}${String(today.getSeconds()).padStart(2,'0')}`;
                // Consider adding username prefix if needed PO-USERNAME-TIMESTAMP
                $('#po_number').val(`PO-${datePart}-${timePart}`); // Use timestamp PO for simplicity now
                 console.log("Generated PO, Set Company Hidden:", companyName); // Debug log
            } else {
                // Clear fields if no user is selected
                companyAddressInput.val('');
                companyHiddenInput.val('');
                $('#po_number').val('');
                if ($('#delivery_address_type').val() === 'company') {
                    $('#delivery_address').val('');
                }
            }
        }
        // Prepare data just before confirmation
        function prepareOrderData() {
            toggleDeliveryAddress(); // Ensure correct address is set in the hidden field
            const addr = $('#delivery_address').val();
            const userSelect = $('#username');
            const companyName = $('#company_hidden').val(); // Use the hidden input value

            console.log("Company Hidden Value before validation:", companyName); // Debug log

            // Validation checks
            if (cartItems.length === 0) { showToast('Please add products to the order.', 'error'); return false; }
            if (!userSelect.val()) { showToast('Please select a client.', 'error'); return false; }
            if (!$('#delivery_date').val()) { showToast('Please select a delivery date.', 'error'); return false; }
            if (!addr) { showToast('Please enter or select a delivery address.', 'error'); return false; }
             // Add check for custom address if that type is selected
             if ($('#delivery_address_type').val() === 'custom' && !$('#custom_address').val()) {
                  showToast('Please enter a custom delivery address.', 'error'); return false;
             }

            let total = 0;
            // Map cart items to the required format, INCLUDING product_id
            const orders = cartItems.map(item => { // Iterates through items added to the cart
                total += (item.price || 0) * (item.quantity || 0);
                return {
                    // Reads the 'product_id' property from the 'item' object in the cartItems array
                    product_id: item.product_id, // <<< Use product_id from cart item
                    category: item.category,
                    item_description: item.item_description,
                    packaging: item.packaging,
                    price: item.price,
                    quantity: item.quantity
                };
            });

            // The 'orders' array (now containing objects with 'product_id') is stringified here
            $('#orders').val(JSON.stringify(orders)); // Set hidden orders input
            $('#total_amount').val(total.toFixed(2)); // Set hidden total amount input
            console.log("Prepared Orders JSON:", $('#orders').val());
            console.log("Prepared Company Hidden:", $('#company_hidden').val());
            console.log("Prepared Total Amount:", $('#total_amount').val());
            return true; // Data is prepared and valid
        }
        function confirmAddOrder() { if (prepareOrderData()) $('#addConfirmationModal').css('display', 'flex'); } // Use flex
        function closeAddConfirmation() { $('#addConfirmationModal').hide(); }

        function submitAddOrder() {
             $('#addConfirmationModal').hide();
             const form = document.getElementById('addOrderForm');
             const fd = new FormData(form); // FormData collects from all named inputs

             // Log FormData content for debugging
             console.log("Submitting FormData:");
             for (let [key, value] of fd.entries()) {
                 console.log(`${key}: ${value}`);
             }

             showToast('Adding order...', 'info');

             fetch('../../backend/add_order.php', { method: 'POST', body: fd }) // Adjusted path
                 .then(response => {
                      if (!response.ok) {
                           return response.json().then(errData => { throw new Error(errData.message || `Server error: ${response.status}`); });
                      }
                      return response.json();
                 })
                 .then(d => {
                      if (d.success) {
                           showToast('Order added successfully!', 'success');
                           closeAddOrderForm();
                           setTimeout(() => { window.location.reload(); }, 1000);
                      } else {
                           throw new Error(d.message || 'Unknown error adding order.');
                      }
                 })
                 .catch(e => {
                      console.error('Add order fetch error:', e);
                      showToast('Error adding order: ' + e.message, 'error');
                 });
        }


        // --- Inventory Overlay and Cart Functions (Using product_id) ---
        function openInventoryOverlay() {
            $('#inventoryOverlay').css('display', 'flex');
            const body = $('.inventory').html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
            fetch('../../backend/get_inventory.php') // Adjusted path
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.text().then(t => { try { return JSON.parse(t); } catch (e) { console.error("Inv JSON parse error:", e, "Response:", t); throw new Error('Invalid server response'); } }); })
                .then(data => {
                    console.log("Received Inventory data:", data); // Log received inventory data
                    if (Array.isArray(data)) {
                        const cats = [...new Set(data.map(i => i.category).filter(c => c))];
                        populateInventory(data, '#inventoryOverlay .inventory'); // Target overlay table
                        populateCategories(cats, '#inventoryFilterOverlay'); // Target overlay filter
                        filterInventory('#inventoryFilterOverlay', '#inventorySearchOverlay', '#inventoryOverlay .inventory tr'); // Apply filter to overlay
                    } else { throw new Error("Unexpected data format"); }
                 })
                .catch(e => { console.error('Inv fetch:', e); showToast('Inventory fetch error: ' + e.message, 'error'); body.html(`<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">Error loading inventory</td></tr>`); });
        }
        function populateInventory(inventory, tableBodySelector) {
            const body = $(tableBodySelector).empty();
            if (!inventory || inventory.length === 0) { body.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No items found</td></tr>'); return; }
            inventory.forEach(item => {
                const price = parseFloat(item.price);
                // Ensure product_id exists and price is valid before creating the button/row
                if (isNaN(price) || item.product_id === undefined || item.product_id === null) {
                    console.warn("Skipping item due to invalid price or missing product_id:", item);
                    return; // Skip this item
                }
                // Escape potential quotes in string arguments for JS function call
                const escapedCategory = item.category ? item.category.replace(/'/g, "\\'") : 'Uncategorized';
                const escapedDesc = item.item_description ? item.item_description.replace(/'/g, "\\'") : 'N/A';
                const escapedPkg = item.packaging ? item.packaging.replace(/'/g, "\\'") : 'N/A';

                // Use item.product_id in the onclick handler
                body.append(`<tr>
                             <td>${item.category||'Uncategorized'}</td>
                             <td>${item.item_description}</td>
                             <td>${item.packaging||'N/A'}</td>
                             <td>PHP ${price.toFixed(2)}</td>
                             <td><input type="number" class="inventory-quantity" value="1" min="1" max="1000" style="width: 60px; text-align: center;"></td>
                             <td><button class="add-to-cart-btn" onclick="addToCart(this, ${item.product_id}, '${escapedCategory}', '${escapedDesc}', '${escapedPkg}', ${price})"><i class="fas fa-plus"></i> Add</button></td>
                          </tr>`);
             });
        }
        function populateCategories(categories, filterSelector) {
            const sel = $(filterSelector);
            sel.find('option:not(:first-child)').remove(); // Clear existing options except "All"
            if (!categories || categories.length === 0) return;
            categories.sort().forEach(c => { if (c) sel.append(`<option value="${c}">${c}</option>`); });
            // Unbind previous handler and bind new one to avoid duplicates
            sel.off('change').on('change', () => filterInventory(filterSelector, $(filterSelector).next('input[type="text"]').attr('id'), $(filterSelector).closest('.modal-body, #addOrderFormFields').find('.inventory tr, #inventoryTable tr')));
        }

        function filterInventory(filterSel, searchSel, rowsSel) {
            const categoryFilter = $(filterSel).val();
            const searchFilter = $(searchSel).val().toLowerCase();
            $(rowsSel).each(function() {
                 const row = $(this);
                 // Skip header rows or placeholder rows
                 if (row.find('th').length > 0 || (row.find('td').length === 1 && row.find('td').attr('colspan'))) return;

                 const itemCategory = row.find('td:first-child').text();
                 const rowText = row.text().toLowerCase();

                 const categoryMatch = (categoryFilter === 'all' || itemCategory === categoryFilter);
                 const searchMatch = (rowText.includes(searchFilter));

                 row.toggle(categoryMatch && searchMatch);
            });
        }
        // Initial binding for overlay filters
        $('#inventoryFilterOverlay').on('change', () => filterInventory('#inventoryFilterOverlay', '#inventorySearchOverlay', '#inventoryOverlay .inventory tr'));
        $('#inventorySearchOverlay').on('input', () => filterInventory('#inventoryFilterOverlay', '#inventorySearchOverlay', '#inventoryOverlay .inventory tr'));
        // Initial binding for main form filters (if you add them there)
        // $('#inventoryFilter').on('change', () => filterInventory('#inventoryFilter', '#inventorySearch', '#inventoryTable tr'));
        // $('#inventorySearch').on('input', () => filterInventory('#inventoryFilter', '#inventorySearch', '#inventoryTable tr'));


        function closeInventoryOverlay() { $('#inventoryOverlay').hide(); }

        function addToCart(button, productId, category, itemDesc, packaging, price) {
            const qtyInput = $(button).closest('tr').find('.inventory-quantity');
            const qty = parseInt(qtyInput.val());
            if (isNaN(qty) || qty < 1 || qty > 1000) { showToast('Quantity must be between 1 and 1000.', 'error'); qtyInput.val(1); return; }

            // Find existing item in cart using product_id AND packaging (treat different packaging as distinct items)
            const idx = cartItems.findIndex(i => i.product_id === productId && i.packaging === packaging);

            if (idx >= 0) {
                 // Item exists, increase quantity
                 cartItems[idx].quantity += qty;
            } else {
                // Item does not exist, add new item using product_id
                cartItems.push({ product_id: productId, category, item_description: itemDesc, packaging, price, quantity: qty });
            }

            console.log("Cart Items after add:", cartItems); // Log cart items for debugging
            showToast(`Added ${qty} x ${itemDesc}`, 'success');
            qtyInput.val(1); // Reset quantity input in the inventory table row
            updateOrderSummary(); // Update the summary table in the main form
            updateCartItemCount(); // Update the count on the floating cart icon
            updateCartDisplay(); // Update the cart modal content (if open)
        }
        function updateOrderSummary() {
            const body = $('#summaryBody').empty(); let total = 0;
            if (cartItems.length === 0) { body.html('<tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products added</td></tr>'); }
            else { cartItems.forEach((item, index) => { total += (item.price || 0) * (item.quantity || 0); body.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td>PHP ${parseFloat(item.price || 0).toFixed(2)}</td><td><input type="number" class="summary-quantity" value="${item.quantity}" min="1" max="1000" data-index="${index}" onchange="updateSummaryItemQuantity(this)"></td><td><button class="remove-item-btn" onclick="removeSummaryItem(${index})"><i class="fas fa-trash"></i></button></td></tr>`); }); }
            $('.summary-total-amount').text(`PHP ${total.toFixed(2)}`);
        }
        function updateSummaryItemQuantity(input) {
            const idx = parseInt($(input).data('index')); const qty = parseInt($(input).val());
            if (isNaN(qty) || qty < 1 || qty > 1000) { showToast('Quantity must be 1-1000', 'error'); $(input).val(cartItems[idx].quantity); return; } // Revert to old value on error
            cartItems[idx].quantity = qty;
            updateOrderSummary(); // Update total in summary
            updateCartDisplay(); // Update cart modal if open
        }
        function removeSummaryItem(index) { if (index >= 0 && index < cartItems.length) { const removed = cartItems.splice(index, 1)[0]; showToast(`Removed ${removed.item_description}`, 'info'); updateOrderSummary(); updateCartItemCount(); updateCartDisplay(); } }
        function updateCartItemCount() { $('#cartItemCount').text(cartItems.length); }

        // Cart Modal Functions
        window.openCartModal = function() { $('#cartModal').css('display', 'flex'); updateCartDisplay(); }
        function closeCartModal() { $('#cartModal').hide(); }
        function updateCartDisplay() {
            const body = $('.cart').empty(); // Target cart modal table body
            const msg = $('#cartModal .no-products');
            const totalEl = $('#cartModal .total-amount');
            let total = 0;
            if (cartItems.length === 0) { msg.show(); body.hide(); totalEl.text('PHP 0.00'); return; } // Show message, hide table body

            msg.hide(); body.show(); // Hide message, show table body
            cartItems.forEach((item, index) => {
                 total += (item.price || 0) * (item.quantity || 0);
                 body.append(`<tr>
                              <td>${item.category}</td>
                              <td>${item.item_description}</td>
                              <td>${item.packaging}</td>
                              <td>PHP ${parseFloat(item.price || 0).toFixed(2)}</td>
                              <td><input type="number" class="cart-quantity" value="${item.quantity}" min="1" max="1000" data-index="${index}" onchange="updateCartItemQuantity(this)"></td>
                              <td><button class="remove-item-btn" onclick="removeCartItem(${index})"><i class="fas fa-trash"></i></button></td>
                           </tr>`);
            });
            totalEl.text(`PHP ${total.toFixed(2)}`);
        }
        function updateCartItemQuantity(input) { // Called from cart modal quantity input
            const idx = parseInt($(input).data('index')); const qty = parseInt($(input).val());
            if (isNaN(qty) || qty < 1 || qty > 1000) { showToast('Quantity must be 1-1000', 'error'); $(input).val(cartItems[idx].quantity); return; } // Revert
            cartItems[idx].quantity = qty;
            updateCartDisplay(); // Update total in cart modal
            updateOrderSummary(); // Also update the summary table in the main form
        }
        function removeCartItem(index) { // Called from cart modal remove button
            if (index >= 0 && index < cartItems.length) {
                 const removed = cartItems.splice(index, 1)[0];
                 showToast(`Removed ${removed.item_description}`, 'info');
                 updateCartDisplay(); // Update cart modal
                 updateCartItemCount(); // Update icon count
                 updateOrderSummary(); // Update summary table
            }
        }
        function saveCartChanges() { // Called when clicking "Update Order" in cart modal
             updateOrderSummary(); // Ensure summary table reflects cart changes
             closeCartModal();
        }


        // --- Document Ready ---\
        $(document).ready(function() {
            // Initialize datepicker on load for Add Order form
            initializeDeliveryDatePicker();
            // Set initial state for Add Order form elements
            toggleDeliveryAddress();
            generatePONumber(); // Call on load to set initial state

            // Close modals on outside click (generic handler)
            $(document).on('click', function(event) {
                 const target = $(event.target);
                 // Check if click is directly on an overlay/modal background
                 if (target.hasClass('modal') || target.hasClass('overlay')) {
                     const id = target.attr('id');
                     switch (id) {
                          case 'orderDetailsModal': closeOrderDetailsModal(); break;
                          case 'statusModal': closeStatusModal(); break;
                          case 'pendingStatusModal': closePendingStatusModal(); break;
                          case 'rejectedStatusModal': closeRejectedStatusModal(); break;
                          case 'statusConfirmationModal': closeStatusConfirmation(); break;
                          case 'driverModal': closeDriverModal(); break;
                          case 'driverConfirmationModal': closeDriverConfirmation(); break; // Consider if this should reopen driverModal
                          case 'saveProgressConfirmationModal': closeSaveProgressConfirmation(); break;
                          case 'specialInstructionsModal': closeSpecialInstructions(); break;
                          case 'downloadConfirmationModal': closeDownloadConfirmation(); break;
                          case 'addOrderOverlay': closeAddOrderForm(); break;
                          case 'inventoryOverlay': closeInventoryOverlay(); break;
                          case 'cartModal': closeCartModal(); break;
                          case 'addConfirmationModal': closeAddConfirmation(); break;
                          case 'pdfPreview': closePDFPreview(); break;
                     }
                 }
            });

             // Prevent closing modals when clicking inside the content area
             $(document).on('click', '.modal-content', function(event) {
                 event.stopPropagation();
             });
        });
    </script>
</body>
</html>