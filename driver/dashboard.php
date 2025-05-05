<?php
session_start();
// Adjust the path based on the final location of db_connection.php relative to driver/dashboard.php
require_once __DIR__ . '/backend/db_connection.php';

// Check if driver is logged in, otherwise redirect to login page
if (!isset($_SESSION['driver_id']) || !isset($_SESSION['driver_logged_in']) || $_SESSION['driver_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$driver_name = $_SESSION['driver_name'] ?? 'Driver'; // Fallback name

// --- Handle Order Status Update (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    if (!headers_sent()) { header('Content-Type: application/json'); }

    $po_number = $_POST['po_number'] ?? null;
    $new_status = $_POST['new_status'] ?? null;
    $allowed_statuses = ['For Delivery', 'In Transit', 'Completed']; // Driver-updatable statuses

    // Basic validation
    if (empty($po_number) || empty($new_status) || !in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        exit;
    }

    // Security Check: Verify this driver is assigned to this PO number
    $checkStmt = $conn->prepare("SELECT da.driver_id FROM driver_assignments da JOIN orders o ON da.po_number = o.po_number WHERE da.driver_id = ? AND da.po_number = ? AND o.status NOT IN ('Completed', 'Cancelled')"); // Ensure order is actionable
    if (!$checkStmt) {
         error_log("Dashboard Status Update - Check Prepare Error: " . $conn->error);
         echo json_encode(['success' => false, 'message' => 'Database error (check).']); exit;
    }
    $checkStmt->bind_param("is", $driver_id, $po_number);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows !== 1) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Order not found or not assigned to you.']);
        exit;
    }
    $checkStmt->close();

    // Begin transaction for order update and potential email sending
    $conn->begin_transaction();

    try {
        // Proceed with update
        $updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
        if (!$updateStmt) {
            throw new Exception("Dashboard Status Update - Update Prepare Error: " . $conn->error);
        }
        $updateStmt->bind_param("ss", $new_status, $po_number);

        if (!$updateStmt->execute()) {
            throw new Exception("Dashboard Status Update - Update Execute Error: " . $updateStmt->error);
        }
        $updateStmt->close();

        // If marking as completed, get customer details for email
        if ($new_status === 'Completed') {
            // Get order details for the email
            $orderQuery = $conn->prepare("
                SELECT o.*, c.email, c.username, c.company
                FROM orders o
                JOIN clients_accounts c ON o.username = c.username
                WHERE o.po_number = ?
            ");

            if (!$orderQuery) {
                throw new Exception("Error preparing order details query: " . $conn->error);
            }

            $orderQuery->bind_param("s", $po_number);
            $orderQuery->execute();
            $orderResult = $orderQuery->get_result();

            if ($orderResult->num_rows === 1) {
                $orderData = $orderResult->fetch_assoc();

                // Send email notification
                $email_sent = sendCompletionEmail($orderData);

                if (!$email_sent) {
                    // Log email failure but don't fail the transaction
                    error_log("Failed to send completion email for order $po_number");
                }
            } else {
                error_log("Order data not found for email notification: $po_number");
            }
            $orderQuery->close();
        }

        // Commit the transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Order $po_number status updated to $new_status."
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Transaction Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()]);
    }

    $conn->close();
    exit; // Important to exit after AJAX handling
}

/**
 * Function to send completion email to customer
 */
function sendCompletionEmail($orderData) {
    if (empty($orderData['email'])) {
        return false;
    }

    $to = $orderData['email'];
    $subject = "Order Completed: {$orderData['po_number']}";

    // Decode the JSON orders
    $orderItems = json_decode($orderData['orders'], true);
    $itemsList = '';

    if (is_array($orderItems)) {
        foreach ($orderItems as $item) {
            $itemsList .= "• {$item['quantity']} x {$item['item_description']} ({$item['packaging']}) - PHP " .
                number_format($item['price'] * $item['quantity'], 2) . "\n";
        }
    }

    $company = !empty($orderData['company']) ? $orderData['company'] : $orderData['username'];

    $message = "Dear {$orderData['username']},\n\n" .
        "Your order has been successfully delivered and marked as completed.\n\n" .
        "Order Details:\n" .
        "------------------------\n" .
        "PO Number: {$orderData['po_number']}\n" .
        "Company: $company\n" .
        "Order Date: {$orderData['order_date']}\n" .
        "Delivery Date: {$orderData['delivery_date']}\n" .
        "Delivery Address: {$orderData['delivery_address']}\n\n" .
        "Items:\n$itemsList\n" .
        "------------------------\n" .
        "Total Amount: PHP " . number_format($orderData['total_amount'], 2) . "\n\n" .
        "If you have any questions about your delivery, please contact our customer service.\n\n" .
        "Thank you for your business!\n\n" .
        "Best regards,\n" .
        "Top Exchange Team";

    $headers = "From: noreply@topexchange.com\r\n" .
        "Reply-To: support@topexchange.com\r\n" .
        "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $message, $headers);
}

// --- Fetch Assigned Orders for Display ---
$orders = [];
// Select orders assigned to this driver that are in actionable states
$stmt = $conn->prepare("
    SELECT o.po_number, o.username, o.orders, o.delivery_date, o.delivery_address, o.status
    FROM orders o
    JOIN driver_assignments da ON o.po_number = da.po_number
    WHERE da.driver_id = ? AND o.status IN ('Active', 'For Delivery', 'In Transit')
    ORDER BY FIELD(o.status, 'Active', 'For Delivery', 'In Transit'), o.delivery_date ASC, o.po_number ASC
");

if ($stmt) {
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $order_items = json_decode($row['orders'], true);
        // Add basic error handling for JSON decode
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Driver Dashboard - JSON Decode Error for PO {$row['po_number']}: " . json_last_error_msg());
            $order_items = []; // Set to empty array on error
        }
        $row['items'] = $order_items ?? []; // Ensure 'items' key exists
        $orders[] = $row;
    }
    $stmt->close();
} else {
    error_log("Driver Dashboard - Fetch Orders Prepare Error: " . $conn->error);
    // Handle error display if needed, e.g., set an error flag
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Dashboard - Top Exchange</title>
    <link rel="stylesheet" href="css/driver_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Link to the shared toast CSS if it's outside this folder -->
    <link rel="stylesheet" href="../css/toast.css">
    <!-- Added theme color for mobile browsers -->
    <meta name="theme-color" content="#343a40">
    <style>
        /* Base styles from driver_dashboard.css would go here or be linked */
        body { font-family: sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .dashboard-header { background-color: #343a40; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .dashboard-header h1 { margin: 0; font-size: 1.8em; }
        .driver-info { display: flex; align-items: center; }
        .driver-info span { margin-right: 15px; }
        .logout-button { color: white; background-color: #dc3545; border: none; padding: 10px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background-color 0.2s; }
        .logout-button:hover { background-color: #c82333; cursor: pointer; }
        .dashboard-content { padding: 30px; max-width: 1200px; margin: 20px auto; }
        .dashboard-content h2 { margin-top: 0; color: #343a40; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }
        .orders-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .order-card { background-color: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; transition: box-shadow 0.2s; }
        .order-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .order-header { background-color: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .order-header-text { display: flex; flex-direction: column; }
        .po-number { font-weight: bold; font-size: 1.2em; color: #495057; }
        .order-date { font-size: 0.9em; color: #6c757d; margin-top: 3px; }
        .order-status { padding: 5px 10px; border-radius: 15px; font-size: 0.9em; font-weight: bold; text-transform: uppercase; }
        .status-active { background-color: #e9f5ea; color: #28a745; border: 1px solid #a6d9aa; }
        .status-for-delivery { background-color: #fff3cd; color: #ffc107; border: 1px solid #ffeeba; }
        .status-in-transit { background-color: #d1ecf1; color: #17a2b8; border: 1px solid #bee5eb; }
        .status-completed { background-color: #e2e3e5; color: #6c757d; border: 1px solid #d6d8db; }
        .order-body { padding: 15px; flex-grow: 1; }
        .order-body p { margin: 0 0 10px 0; line-height: 1.5; }
        .order-body strong { color: #495057; }
        .order-items-summary { margin-top: 15px; padding-top: 10px; border-top: 1px dashed #eee; }
        .order-items-summary ul { list-style: none; padding: 0; margin: 5px 0 0 0; }
        .order-items-summary li { background-color: #fbfcfc; padding: 5px 8px; border-radius: 3px; margin-bottom: 4px; font-size: 0.9em; color: #555; border: 1px solid #eee; }
        .order-actions { padding: 15px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; display: flex; align-items: center; gap: 10px; }
        .order-actions label { font-size: 0.9em; font-weight: bold; color: #495057; }
        .status-select { padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; flex-grow: 1; font-size: 0.9em; }
        .update-status-btn { background-color: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .update-status-btn:hover { background-color: #0056b3; }
        .update-status-btn:disabled { background-color: #6c757d; cursor: not-allowed; }
        .no-orders { text-align: center; padding: 40px; background-color: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: #6c757d; }
        .pull-indicator { text-align: center; padding: 10px; background-color: #e9ecef; color: #495057; display: none; /* Hidden initially */ position: fixed; top: 0; left: 0; width: 100%; z-index: 10; font-size: 0.9em; }
        .pull-indicator.active { display: block; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(0,0,0,.1); border-radius: 50%; border-top-color: #343a40; animation: spin 1s ease-in-out infinite; margin-right: 5px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .order-completed-visual { opacity: 0.7; transition: opacity 0.5s ease-out; /* Example visual cue */ }
        .confirmation-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; }
        .confirmation-modal .modal-content { background-color: #fff; border-radius: 8px; padding: 25px 30px; width: 400px; max-width: 90%; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); animation: modalPopIn 0.3s ease-out; }
        @keyframes modalPopIn { from {transform: scale(0.8) translateY(20px); opacity: 0;} to {transform: scale(1) translateY(0); opacity: 1;} }
        .confirmation-modal .modal-header { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #dee2e6; }
        .confirmation-modal .modal-title { font-size: 1.25em; margin: 0; color: #343a40; }
        .confirmation-modal .modal-message { margin-bottom: 25px; color: #495057; font-size: 1em; line-height: 1.5; }
        .confirmation-modal .status-pill { padding: 3px 8px; border-radius: 12px; font-weight: bold; font-size: 0.9em; margin: 0 3px; display: inline-block; }
        .confirmation-modal .modal-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirmation-modal .btn-cancel, .confirmation-modal .btn-confirm { padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.2s; border: none; font-size: 0.95em; }
        .confirmation-modal .btn-confirm { background-color: #007bff; color: white; }
        .confirmation-modal .btn-confirm:hover { background-color: #0056b3; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .confirmation-modal .btn-cancel { background-color: #f1f1f1; color: #333; border: 1px solid #ccc; }
        .confirmation-modal .btn-cancel:hover { background-color: #e1e1e1; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        i.fas { margin-right: 5px; } /* Add space after icons */

        /* --- Responsive Design Adjustments --- */

        /* Common Mobile Styles (e.g., screens smaller than 768px) */
        @media (max-width: 767px) {
            .dashboard-header {
                flex-direction: column; /* Stack header items vertically */
                align-items: flex-start; /* Align items to the start */
                padding: 15px;
            }

            .dashboard-header h1 {
                font-size: 1.5em; /* Slightly smaller title */
                margin-bottom: 10px; /* Add space below title */
            }

            .driver-info {
                flex-direction: column; /* Stack driver info items */
                align-items: flex-start;
                width: 100%; /* Take full width */
            }

            .driver-info span {
                margin-bottom: 8px; /* Space between welcome message and button */
            }

            .logout-button {
                padding: 8px 12px; /* Adjust button padding */
                width: 100%; /* Make logout button full width */
                text-align: center;
                margin-top: 5px;
            }

            .dashboard-content {
                padding: 15px; /* Reduce padding on mobile */
            }

            .dashboard-content h2 {
                font-size: 1.3em; /* Adjust heading size */
            }

            .order-card {
                padding: 15px; /* Adjust card padding */
                /* Remove grid-template-columns for cards on mobile if needed */
                display: block; /* Ensure cards stack if grid isn't wrapping */
                margin-bottom: 15px; /* Add space between stacked cards */
            }

            .orders-list {
                 grid-template-columns: 1fr; /* Force single column */
                 gap: 15px;
            }


            .order-header {
                flex-direction: column; /* Stack PO number/date and status */
                align-items: flex-start;
                margin-bottom: 10px;
            }

             .order-header-text {
                margin-bottom: 5px; /* Space between text and status pill */
            }

            .order-status {
                align-self: flex-start; /* Keep status aligned left */
                font-size: 0.85em;
                padding: 4px 8px;
            }

            .order-body p,
            .order-items-summary p {
                font-size: 0.95em; /* Adjust text size for readability */
            }

            .order-items-summary ul {
                padding-left: 20px; /* Adjust list indentation */
            }
             .order-items-summary li {
                font-size: 0.9em;
             }

            .order-actions {
                flex-direction: column; /* Stack label/select and button */
                align-items: stretch; /* Stretch items to full width */
            }

            .order-actions label {
                margin-bottom: 5px;
            }

            .status-select {
                margin-bottom: 10px; /* Add space between select and button */
            }

            .update-status-btn {
                padding: 12px; /* Larger tap target */
            }

            .no-orders {
                padding: 20px;
            }

             /* Modal adjustments */
            .confirmation-modal .modal-content {
                width: 90%; /* Allow modal to use more width */
                padding: 20px;
            }
             .confirmation-modal .modal-header h3 {
                font-size: 1.1em;
            }
            .confirmation-modal .modal-message {
                font-size: 0.95em;
            }
             .confirmation-modal .modal-buttons {
                flex-direction: column; /* Stack modal buttons */
                gap: 10px;
             }
            .confirmation-modal .modal-buttons button {
                width: 100%; /* Make buttons full width */
                padding: 12px;
            }

        }

        /* Optional: Styles for very small screens (e.g., less than 480px) */
        @media (max-width: 479px) {
            .dashboard-header h1 {
                font-size: 1.3em;
            }
             .dashboard-content h2 {
                font-size: 1.2em;
            }
            .po-number {
                font-size: 1.1em;
            }
             .order-body p,
            .order-items-summary p {
                font-size: 0.9em;
            }
            .order-items-summary li {
                font-size: 0.85em;
             }
        }

        /* Ensure pull indicator doesn't interfere */
        .pull-indicator {
            /* Existing styles */
            z-index: 10; /* Ensure it's below header but above content if needed */
        }

        /* Add subtle transition for layout changes */
        .dashboard-header, .driver-info, .order-card, .order-header, .order-actions {
            transition: all 0.2s ease-in-out;
        }
    </style>
</head>
<body>
    <div id="toast-container"></div>
    <header class="dashboard-header">
        <h1><i class="fas fa-truck"></i> Driver Dashboard</h1>
        <div class="driver-info">
            <span><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($driver_name) ?>!</span>
            <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="pull-indicator" id="pullIndicator">
        <span class="spinner"></span> Pull down to refresh...
    </div>

    <main class="dashboard-content">
        <h2>Your Assigned Deliveries</h2>

        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <i class="fas fa-clipboard-list fa-2x" style="margin-bottom: 15px; opacity: 1;"></i>
                <p>You have no active deliveries assigned.</p>
                <p style="font-size: 0.9rem; margin-top: 10px;">Pull down to refresh the page when new deliveries are assigned.</p>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order):
                    $status_class = 'status-' . strtolower(str_replace(' ', '-', $order['status']));
                ?>
                    <div class="order-card" id="order-<?= htmlspecialchars($order['po_number']) ?>">
                        <div class="order-header">
                            <div class="order-header-text">
                                <span class="po-number"><?= htmlspecialchars($order['po_number']) ?></span>
                                <span class="order-date"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars(date('M d, Y', strtotime($order['delivery_date']))) ?></span>
                            </div>
                            <span class="order-status <?= $status_class ?>"><?= htmlspecialchars($order['status']) ?></span>
                        </div>
                        <div class="order-body">
                            <p><strong><i class="fas fa-user"></i> Customer:</strong> <?= htmlspecialchars($order['username']) ?></p>
                            <p><strong><i class="fas fa-map-marker-alt"></i> Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
                            <div class="order-items-summary">
                                <p><strong><i class="fas fa-box"></i> Items:</strong></p>
                                <ul>
                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <li><?= htmlspecialchars($item['quantity'] ?? 0) ?> x <?= htmlspecialchars($item['item_description'] ?? 'N/A') ?> (<?= htmlspecialchars($item['packaging'] ?? 'N/A') ?>)</li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>Item details not available.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="order-actions">
                            <label for="status-<?= htmlspecialchars($order['po_number']) ?>"><i class="fas fa-tag"></i> Update Status:</label>
                            <select id="status-<?= htmlspecialchars($order['po_number']) ?>" name="new_status" class="status-select" data-po="<?= htmlspecialchars($order['po_number']) ?>">
                                <!-- Always show all three status options -->
                                <option value="For Delivery" <?= $order['status'] == 'For Delivery' ? 'selected' : '' ?>>For Delivery</option>
                                <option value="In Transit" <?= $order['status'] == 'In Transit' ? 'selected' : '' ?>>In Transit</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <button class="update-status-btn" data-po="<?= htmlspecialchars($order['po_number']) ?>">
                                <i class="fas fa-sync-alt"></i> Update
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="confirmation-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-question-circle"></i> Confirm Status Update</h3>
            </div>
            <div class="modal-message">
                Are you sure you want to update order <strong id="modalPoNumber"></strong> status to <span id="modalNewStatus" class="status-pill"></span>?
            </div>
            <div class="modal-buttons">
                <button class="btn-cancel" id="cancelBtn"><i class="fas fa-times"></i> Cancel</button>
                <button class="btn-confirm" id="confirmBtn"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Link to the shared toast JS if it's outside this folder -->
    <script src="../js/toast.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize toastr with bottom-right position
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": false,
                    "progressBar": true,
                    "positionClass": "toast-bottom-right", // Changed to bottom-right
                    "preventDuplicates": false,
                    "onclick": null,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
            } else {
                console.error("Toastr library not loaded correctly.");
            }

            // Setup pull-to-refresh
            let touchStartY = 0;
            let touchEndY = 0;
            const minSwipeDistance = 100; // Minimum distance required for a swipe
            const $pullIndicator = $('#pullIndicator');

            document.addEventListener('touchstart', function(e) {
                // Only track if the touch starts at the top of the scrollable area
                if (window.scrollY === 0) {
                    touchStartY = e.touches[0].clientY;
                } else {
                    touchStartY = null; // Reset if not at top
                }
                touchEndY = 0; // Reset end Y on new touch
            }, { passive: true }); // Use passive true for start

            document.addEventListener('touchmove', function(e) {
                if (touchStartY === null) return; // Don't track if touch didn't start at top

                touchEndY = e.touches[0].clientY;
                const swipeDistance = touchEndY - touchStartY;

                // Show pull indicator if user is pulling down from top of page
                if (window.scrollY === 0 && swipeDistance > 30) {
                    $pullIndicator.addClass('active');
                    // Don't prevent default here to allow some natural scroll feel,
                    // but refresh logic is in touchend
                } else {
                    // If scrolling down or pull isn't significant, hide indicator
                    $pullIndicator.removeClass('active');
                }
            }, { passive: true }); // Use passive true for move as well

            document.addEventListener('touchend', function(e) {
                 if (touchStartY === null || touchEndY === 0) {
                     $pullIndicator.removeClass('active'); // Ensure indicator hides
                     return; // Exit if touch didn't start at top or didn't move
                 }

                // Calculate the vertical distance
                const swipeDistance = touchEndY - touchStartY;

                // If we're at the top of the page and the pull is long enough, refresh
                if (window.scrollY === 0 && swipeDistance > minSwipeDistance) {
                    $pullIndicator.html('<span class="spinner"></span> Refreshing...');
                    $pullIndicator.addClass('active'); // Keep it visible while reloading
                    window.location.reload();
                } else {
                    // Hide indicator if swipe wasn't enough
                    $pullIndicator.removeClass('active');
                }
                // Reset positions for next touch
                touchStartY = null;
                touchEndY = 0;
            }, { passive: true }); // Use passive true for end


            // Variables for modal confirmation
            let currentPoNumber = '';
            let currentNewStatus = '';
            let currentOrderCard = null;
            let currentStatusSpan = null;
            const $confirmationModal = $('#confirmationModal');
            const $modalPoNumber = $('#modalPoNumber');
            const $modalNewStatus = $('#modalNewStatus');
            const $cancelBtn = $('#cancelBtn');
            const $confirmBtn = $('#confirmBtn');

            // Show confirmation modal when update button is clicked
            $('.update-status-btn').on('click', function() {
                const button = $(this);
                const po_number = button.data('po');
                const statusSelect = $(`#status-${po_number}`);
                const new_status = statusSelect.val();
                currentOrderCard = button.closest('.order-card');
                currentStatusSpan = currentOrderCard.find('.order-status');
                const current_status = currentStatusSpan.text().trim();

                // Skip modal if no change in status
                if (new_status === current_status) {
                    if (typeof toastr !== 'undefined') {
                        toastr.info('Status remains unchanged.');
                    }
                    return;
                }

                // Set up modal content
                currentPoNumber = po_number;
                currentNewStatus = new_status;

                $modalPoNumber.text(po_number);
                $modalNewStatus.text(new_status);

                // Add special note if completing the order
                $('.modal-note').remove(); // Clear previous notes first
                if (new_status === 'Completed') {
                    $modalNewStatus.parent().append('<p class="modal-note" style="margin-top:10px;font-size:0.9em;color:#666;"><i class="fas fa-envelope"></i> An email notification will be sent to the customer.</p>');
                }

                // Set appropriate status class for pill
                $modalNewStatus.removeClass().addClass('status-pill ' + new_status.toLowerCase().replace(/\s+/g, '-'));

                // Show the confirmation modal using flex
                 $confirmationModal.css('display', 'flex').hide().fadeIn(200);

            });

            // Handle cancel button click
            $cancelBtn.on('click', function() {
                $confirmationModal.fadeOut(200);
                resetModalVariables();
            });

            // Close modal if clicking outside of it
            $confirmationModal.on('click', function(e) {
                if ($(e.target).is($confirmationModal)) {
                    $confirmationModal.fadeOut(200);
                    resetModalVariables();
                }
            });

            // Handle confirm button click
            $confirmBtn.on('click', function() {
                $confirmationModal.fadeOut(200);
                updateOrderStatus(currentPoNumber, currentNewStatus, currentOrderCard, currentStatusSpan);
            });

            // Reset modal variables
            function resetModalVariables() {
                currentPoNumber = '';
                currentNewStatus = '';
                currentOrderCard = null;
                currentStatusSpan = null;
                $('.modal-note').remove();
            }

            // Function to actually update the order status
            function updateOrderStatus(po_number, new_status, orderCard, statusSpan) {
                const button = $(`.update-status-btn[data-po="${po_number}"]`);

                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...'); // Disable button and show spinner

                $.ajax({
                    url: 'dashboard.php', // Post back to the same file
                    type: 'POST',
                    data: {
                        action: 'update_status',
                        po_number: po_number,
                        new_status: new_status
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                           if(typeof showToast === 'function') {
                                showToast(response.message, 'success');
                           } else if (typeof toastr !== 'undefined') {
                                toastr.success(response.message);
                           } else {
                                alert(response.message); // Fallback alert
                           }

                            // Update status display dynamically
                            statusSpan.text(new_status);
                            // Update status class for color
                            statusSpan.removeClass (function (index, className) {
                                return (className.match (/(^|\s)status-\S+/g) || []).join(' ');
                            }).addClass('status-' + new_status.toLowerCase().replace(/\s+/g, '-'));

                            // If status is 'Completed', maybe hide the card or move it after a short delay
                            if (new_status === 'Completed') {
                                orderCard.addClass('order-completed-visual'); // Add class for styling fade/strike-through

                                // Show special toast message for completed orders with email notification
                                if(typeof toastr !== 'undefined' && response.message.includes("Completed")) { // Check if message confirms completion
                                    setTimeout(() => {
                                        toastr.info('<i class="fas fa-envelope"></i> Email notification sent to customer.', 'Notification Sent');
                                    }, 1000);
                                }

                                setTimeout(() => {
                                    // Remove the card with animation
                                    orderCard.fadeOut(500, function() {
                                        $(this).remove();
                                         // Check if no orders left after removal
                                        if ($('.order-card').length === 0) {
                                            $('.orders-list').html(`
                                                <div class="no-orders">
                                                    <i class="fas fa-check-circle fa-2x" style="margin-bottom: 15px; color: #28a745; opacity: 1;"></i>
                                                    <p>All deliveries completed! Great job!</p>
                                                    <p style="font-size: 0.9rem; margin-top: 10px;">Pull down to refresh when new deliveries are assigned.</p>
                                                </div>
                                            `);
                                        }
                                    });


                                }, 1500); // Delay before removing
                            } else {
                                // Re-enable button for non-completed updates
                                button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Update');
                            }

                        } else {
                            if(typeof showToast === 'function') {
                                showToast(response.message || 'Failed to update status.', 'error');
                            } else if (typeof toastr !== 'undefined') {
                                toastr.error(response.message || 'Failed to update status.');
                            } else {
                                alert(response.message || 'Failed to update status.'); // Fallback alert
                            }
                            button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Update'); // Re-enable button on failure
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error, xhr.responseText);
                        if(typeof showToast === 'function') {
                            showToast('An error occurred while communicating with the server.', 'error');
                        } else if (typeof toastr !== 'undefined') {
                            toastr.error('An error occurred while communicating with the server.');
                        } else {
                            alert('An error occurred. Please check console.'); // Fallback alert
                        }
                        button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Update'); // Re-enable button on error
                    },
                    complete: function() {
                        resetModalVariables(); // Ensure variables are reset after AJAX call
                    }
                });
            }

            // Prevent zooming on iOS (already present, keeping it)
            document.addEventListener('gesturestart', function (e) {
                e.preventDefault();
            });
        });
    </script>
</body>
</html>