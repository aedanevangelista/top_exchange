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
    $checkStmt = $conn->prepare("SELECT da.driver_id FROM driver_assignments da JOIN orders o ON da.po_number = o.po_number WHERE da.driver_id = ? AND da.po_number = ? AND o.status NOT IN ('Completed', 'Cancelled')");
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

    // Proceed with update
    $updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
     if (!$updateStmt) {
         error_log("Dashboard Status Update - Update Prepare Error: " . $conn->error);
         echo json_encode(['success' => false, 'message' => 'Database error (update).']); exit;
    }
    $updateStmt->bind_param("ss", $new_status, $po_number);

    if ($updateStmt->execute()) {
        // Optionally: Log the status change
        // log_activity($conn, $driver_id, 'driver', "Updated order $po_number status to $new_status");
        echo json_encode(['success' => true, 'message' => "Order $po_number status updated to $new_status."]);
    } else {
        error_log("Dashboard Status Update - Update Execute Error: " . $updateStmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update order status.']);
    }
    $updateStmt->close();
    $conn->close();
    exit; // Important to exit after AJAX handling
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
                <i class="fas fa-clipboard-list fa-2x" style="margin-bottom: 15px; opacity: 0.5;"></i>
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
                                <!-- Show current status and next logical steps -->
                                <?php if ($order['status'] == 'Active'): ?>
                                     <option value="Active" selected>Active (Pending Pickup)</option>
                                     <option value="For Delivery">For Delivery</option>
                                <?php elseif ($order['status'] == 'For Delivery'): ?>
                                     <option value="For Delivery" selected>For Delivery</option>
                                     <option value="In Transit">In Transit</option>
                                     <option value="Completed">Completed</option>
                                <?php elseif ($order['status'] == 'In Transit'): ?>
                                    <option value="In Transit" selected>In Transit</option>
                                    <option value="Completed">Completed</option>
                                <?php else: ?>
                                    <!-- Fallback for unexpected statuses, might need adjustment -->
                                    <option value="<?= htmlspecialchars($order['status']) ?>" selected><?= htmlspecialchars($order['status']) ?></option>
                                <?php endif; ?>
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
                touchStartY = e.touches[0].clientY;
            }, false);

            document.addEventListener('touchmove', function(e) {
                touchEndY = e.touches[0].clientY;
                const swipeDistance = touchEndY - touchStartY;
                
                // Show pull indicator if user is pulling down from top of page
                if (window.scrollY === 0 && swipeDistance > 30) {
                    $pullIndicator.addClass('active');
                    e.preventDefault(); // Prevent default scroll behavior
                }
            }, { passive: false });

            document.addEventListener('touchend', function(e) {
                $pullIndicator.removeClass('active');
                
                // Calculate the vertical distance
                const swipeDistance = touchEndY - touchStartY;
                
                // If we're at the top of the page and the pull is long enough, refresh
                if (window.scrollY === 0 && swipeDistance > minSwipeDistance) {
                    $pullIndicator.html('<span class="spinner"></span> Refreshing...');
                    $pullIndicator.addClass('active');
                    window.location.reload();
                }
            }, false);

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
                
                // Set appropriate status class for pill
                $modalNewStatus.removeClass().addClass('status-pill ' + new_status.toLowerCase().replace(/\s+/g, '-'));
                
                // Show the confirmation modal
                $confirmationModal.fadeIn(200);
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
                                setTimeout(() => {
                                    // Remove the card with animation
                                    orderCard.fadeOut(500, function() { $(this).remove(); });
                                    
                                    // Check if no orders left
                                    if ($('.order-card:visible').length === 0) {
                                        $('.orders-list').html(`
                                            <div class="no-orders">
                                                <i class="fas fa-check-circle fa-2x" style="margin-bottom: 15px; color: #28a745; opacity: 0.8;"></i>
                                                <p>All deliveries completed! Great job!</p>
                                                <p style="font-size: 0.9rem; margin-top: 10px;">Pull down to refresh when new deliveries are assigned.</p>
                                            </div>
                                        `);
                                    }
                                }, 1500); // Delay before removing
                            } else {
                                // Re-enable button for non-completed updates
                                button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Update');
                                // Refresh the select options based on the new status
                                updateStatusOptions($(`#status-${po_number}`), new_status);
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
                    }
                });
            }

            // Function to update the dropdown options based on the current status
            function updateStatusOptions(selectElement, currentStatus) {
                selectElement.empty(); // Clear existing options

                if (currentStatus === 'Active') {
                    selectElement.append($('<option>', { value: 'Active', text: 'Active (Pending Pickup)', selected: true }));
                    selectElement.append($('<option>', { value: 'For Delivery', text: 'For Delivery' }));
                } else if (currentStatus === 'For Delivery') {
                     selectElement.append($('<option>', { value: 'For Delivery', text: 'For Delivery', selected: true }));
                     selectElement.append($('<option>', { value: 'In Transit', text: 'In Transit' }));
                     selectElement.append($('<option>', { value: 'Completed', text: 'Completed' }));
                } else if (currentStatus === 'In Transit') {
                    selectElement.append($('<option>', { value: 'In Transit', text: 'In Transit', selected: true }));
                    selectElement.append($('<option>', { value: 'Completed', text: 'Completed' }));
                }
            }

            // Initial setup of dropdowns based on current status on page load
            $('.status-select').each(function() {
                const currentStatus = $(this).closest('.order-card').find('.order-status').text().trim();
                updateStatusOptions($(this), currentStatus);
            });

            // Prevent zooming on iOS
            document.addEventListener('gesturestart', function (e) {
                e.preventDefault();
            });
        });
    </script>
</body>
</html>