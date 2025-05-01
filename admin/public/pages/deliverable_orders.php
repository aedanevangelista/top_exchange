<?php
// Current Date: 2025-05-01 17:48:25
// Author: aedanevangelista

session_start();
include "../backend/db_connection.php"; // Make sure this path is correct relative to /public/pages/
include "../backend/check_role.php";   // Make sure this path is correct relative to /public/pages/

checkRole('Deliverables'); // Adjust role name if necessary, ensure user has permission

// Fetch orders with status 'For Delivery' and driver assigned
$orders = [];
$sql = "SELECT o.po_number, o.username, o.company, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.special_instructions,
               d.name as driver_name, da.driver_id
        FROM orders o
        JOIN driver_assignments da ON o.po_number = da.po_number
        JOIN drivers d ON da.driver_id = d.id
        WHERE o.status = 'For Delivery'
        ORDER BY o.delivery_date ASC, d.name ASC"; // Sort by delivery date, then driver

$stmt = $conn->prepare($sql);

// Check if prepare() failed
if ($stmt === false) {
    // Log error and display a user-friendly message or die
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    die('Error preparing database query. Please contact support.'); // More user-friendly than raw error
}

// Execute the statement
if (!$stmt->execute()) {
    // Log error and display a user-friendly message or die
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    $stmt->close();
    $conn->close();
    die('Error executing database query. Please contact support.');
}

// Get the result
$result = $stmt->get_result();

if ($result === false) {
    // Log error and display a user-friendly message or die
     error_log("Getting result failed: (" . $stmt->errno . ") " . $stmt->error);
     $stmt->close();
     $conn->close();
    die('Error retrieving database results. Please contact support.');
}

// Fetch all results
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverable Orders</title>
    <!-- CSS Includes -->
    <link rel="stylesheet" href="/css/deliverables.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="/css/sidebar.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="/css/toast.css"> <!-- Adjust path if needed -->
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Base styles from deliverables.css might be here or included via the link */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f4f7f6;
        }
        .main-content {
            margin-left: 250px; /* Adjust according to sidebar width */
            padding: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table Styles */
        .orders-table-container {
            max-height: 75vh; /* Adjust height as needed */
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            border: 1px solid #e1e1e1; /* Lighter border */
            padding: 10px 12px;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
            white-space: nowrap; /* Prevent wrapping, important for table layout */
        }
        /* Specific column widths */
        .orders-table th:nth-child(1), .orders-table td:nth-child(1) { width: 12%; } /* PO */
        .orders-table th:nth-child(2), .orders-table td:nth-child(2) { width: 10%; } /* User */
        .orders-table th:nth-child(3), .orders-table td:nth-child(3) { width: 12%; overflow: hidden; text-overflow: ellipsis; } /* Company */
        .orders-table th:nth-child(4), .orders-table td:nth-child(4) { width: 10%; } /* Delivery Date */
        .orders-table th:nth-child(5), .orders-table td:nth-child(5) { width: 10%; } /* Driver */
        .orders-table th:nth-child(6), .orders-table td:nth-child(6) { width: 10%; text-align: right;} /* Total */
        .orders-table th:nth-child(7), .orders-table td:nth-child(7) { width: 10%; text-align: center;} /* Status */
        .orders-table th:nth-child(8), .orders-table td:nth-child(8) { width: 6%; text-align: center;} /* Orders */
        .orders-table th:nth-child(9), .orders-table td:nth-child(9) { width: 6%; text-align: center;} /* Instructions */
        .orders-table th:nth-child(10),.orders-table td:nth-child(10){ width: 14%; text-align: center;} /* Actions */


        .orders-table th {
            background-color: #f8f9fa; /* Lighter header */
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600; /* Bolder header text */
            color: #495057;
        }

        .orders-table tbody tr:hover {
            background-color: #f1f1f1; /* Subtle hover */
        }

        /* Button Styles */
        .action-buttons button, .view-orders-btn, .instructions-btn, .download-btn {
            padding: 6px 12px;
            margin: 2px 3px; /* Spacing between buttons */
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            vertical-align: middle; /* Align buttons nicely in the cell */
        }
        .action-buttons button:hover, .view-orders-btn:hover, .instructions-btn:hover, .download-btn:hover {
            transform: translateY(-1px); /* Slight lift on hover */
        }

        .complete-btn { background-color: #28a745; color: white; }
        .complete-btn:hover { background-color: #218838; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }

        .view-orders-btn { background-color: #007bff; color: white; }
        .view-orders-btn:hover { background-color: #0056b3; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }

        .instructions-btn { background-color: #17a2b8; color: white; }
        .instructions-btn:hover { background-color: #117a8b; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }

        .download-btn { background-color: #6c757d; color: white; }
        .download-btn:hover { background-color: #5a6268; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }

        .no-orders { text-align: center; padding: 20px; color: #6c757d; font-style: italic; }
        .no-instructions { font-size: 12px; color: #6c757d; font-style: italic; }

        /* Badges */
        .driver-badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 12px; background-color: #e9ecef; color: #495057; font-size: 12px; font-weight: 500; }
        .driver-badge i { margin-right: 4px; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; text-align: center; min-width: 80px; }
        .status-delivery { background-color: #cfe2ff; color: #0a58ca; border: 1px solid #b6d4fe;} /* Adjusted blue for delivery */

        /* Modal Styles (Generic Overlay) */
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1000; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .overlay-content { background-color: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 700px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); max-height: 90vh; overflow-y: auto; position: relative; animation: modalSlideIn 0.3s ease-out; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .overlay-content h2 { margin-top: 0; color: #333; margin-bottom: 20px; font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .order-details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .order-details-table th, .order-details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        .order-details-table th { background-color: #f8f9fa; font-weight: 600; }
        .order-details-footer { text-align: right; margin-top: 15px; font-weight: bold; font-size: 15px; padding-top: 10px; border-top: 1px solid #eee; }
        .form-buttons { margin-top: 20px; text-align: right; }
        .form-buttons .back-btn { background-color: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; }
        .form-buttons .back-btn:hover { background-color: #5a6268; }

        /* Instructions Modal Specifics */
        .instructions-modal { z-index: 2000; /* Ensure it's above other overlays if needed */ }
        .instructions-modal-content { max-width: 550px; } /* Slightly wider */
        .instructions-header { background-color: #17a2b8; color: white; padding: 12px 15px; }
        .instructions-header h3 { margin: 0; font-size: 16px; }
        .instructions-po-number { font-size: 12px; margin-top: 3px; opacity: 0.9; }
        .instructions-body { padding: 15px 20px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #fdfdfd; border-bottom: 1px solid #eaeaea; max-height: 350px; overflow-y: auto; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 30px 15px; }
        .instructions-footer { padding: 10px 15px; text-align: right; background-color: #f1f1f1; border-top: 1px solid #e1e1e1; }
        .close-instructions-btn { background-color: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .close-instructions-btn:hover { background-color: #5a6268; }

        /* Confirmation Modal Styles */
        .confirmation-modal { display: none; position: fixed; z-index: 1100; /* Below instructions modal? */ left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); overflow: hidden; display: flex; justify-content: center; align-items: center; }
        .confirmation-content { background-color: #fefefe; padding: 25px 30px; border-radius: 8px; width: auto; min-width: 300px; max-width: 90%; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalPopIn 0.3s ease-out; }
        @keyframes modalPopIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        .confirmation-title { font-size: 20px; margin-bottom: 15px; color: #333; font-weight: 600; }
        .confirmation-message { margin-bottom: 25px; color: #555; font-size: 15px; line-height: 1.5; }
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes { background-color: #28a745; color: white; border: none; padding: 9px 22px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; font-size: 14px; }
        .confirm-yes:hover { background-color: #218838; }
        .confirm-no { background-color: #f1f1f1; color: #333; border: 1px solid #ccc; padding: 9px 22px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; font-size: 14px; }
        .confirm-no:hover { background-color: #e1e1e1; }

        /* --- FIXED Toast CSS --- */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; width: 320px; }
        .toast { background-color: #333; color: #fff; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); opacity: 0; transition: opacity 0.4s ease-in-out, transform 0.4s ease-in-out; transform: translateX(100%); display: block; /* Ensure it's block for transform */ }
        .toast.show { opacity: 0.95; transform: translateX(0); } /* Add show class for animation */
        .toast.success { background-color: #28a745; }
        .toast.error { background-color: #dc3545; }
        .toast.info { background-color: #0dcaf0; color: #000; } /* Lighter info blue */
        .toast-content { display: flex; align-items: center; gap: 10px; }
        .toast-content i { font-size: 1.3em; flex-shrink: 0; line-height: 1; /* Better vertical align */ }
        .toast-content .message { flex-grow: 1; margin: 0; font-size: 14px; line-height: 1.4; }
        /* --- End of Fixed Toast CSS --- */

         /* PO PDF layout (Copied from orders.php for consistency) */
         #contentToDownload { position: absolute; left: -9999px; top: auto; width: 800px; /* Ensure it's off-screen */ }
         .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border: 1px solid #ccc; /* Add border for visual structure */ }
         .po-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
         .po-company { font-size: 22px; font-weight: bold; margin-bottom: 5px; color: #333; }
         .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; color: #555; }
         .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 11px; /* Smaller font for details */ line-height: 1.5; }
         .po-left, .po-right { width: 48%; }
         .po-detail-row { margin-bottom: 8px; }
         .po-detail-label { font-weight: bold; display: inline-block; width: 100px; color: #444; } /* Adjusted width */
         .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
         .po-table th, .po-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 10px; } /* Smaller font for PDF table */
         .po-table th { background-color: #f2f2f2; font-weight: bold; }
         .po-table td:nth-child(4), /* Qty */
         .po-table td:nth-child(5), /* Price */
         .po-table td:nth-child(6) { text-align: right; } /* Align numbers right */
         .po-table th:nth-child(4), .po-table th:nth-child(5), .po-table th:nth-child(6) { text-align: right; }
         .po-total { text-align: right; font-weight: bold; font-size: 12px; margin-bottom: 30px; padding-top: 10px; border-top: 1px solid #aaa; } /* Smaller font */
         #contentToDownload { font-size: 11px; } /* Base font size for PDF */
         #contentToDownload .po-title { font-size: 16px; }
         #contentToDownload .po-company { font-size: 20px; }

    </style>
</head>
<body>
    <?php
        // Use a relative path assuming sidebar.php is one level up from /pages/
        // If sidebar.php is in the SAME directory (/pages/), use: include 'sidebar.php';
        // If sidebar.php is in the ROOT directory, use: include '../../sidebar.php'; adjust as needed.
        $sidebarPath = '../sidebar.php';
        if (file_exists($sidebarPath)) {
            include $sidebarPath;
        } else {
            echo "<div style='background:red; color:white; padding:10px;'>Error: Sidebar file not found at $sidebarPath</div>";
        }
    ?>
    <div class="main-content">
        <h1><i class="fas fa-truck-loading"></i> Deliverable Orders</h1>

        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Username</th>
                        <th>Company</th>
                        <th>Delivery Date</th>
                        <th>Driver</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <th>Instructions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['po_number']) ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['company']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                                <td>
                                    <span class="driver-badge">
                                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td style="text-align: center;"><span class="status-badge status-delivery"><?= htmlspecialchars($order['status']) ?></span></td>
                                <td style="text-align: center;">
                                    <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order['orders'])) ?>', '<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-receipt"></i> View
                                    </button>
                                </td>
                                <td style="text-align: center;">
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            <i class="fas fa-info-circle"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons" style="text-align: center;">
                                    <button class="complete-btn" onclick="confirmCompleteOrder('<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                     <button class="download-btn" onclick="downloadPO(
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
                                         <i class="fas fa-file-pdf"></i> PDF
                                     </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-orders">No orders currently out for delivery.</td>
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
             <h2><i class="fas fa-receipt"></i> Order Details (<span id="modalPoNumber"></span>)</h2>
             <div class="order-details-container">
                 <table class="order-details-table">
                     <thead>
                         <tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th></tr>
                     </thead>
                     <tbody id="orderDetailsBody">
                         <!-- Order details populated by JS -->
                     </tbody>
                 </table>
                 <div class="order-details-footer">Total: <span id="orderTotalAmount">PHP 0.00</span></div>
             </div>
             <div class="form-buttons">
                 <button type="button" class="back-btn" onclick="closeOrderDetailsModal()">
                     <i class="fas fa-times"></i> Close
                 </button>
             </div>
         </div>
     </div>

     <!-- Special Instructions Modal -->
     <div id="specialInstructionsModal" class="instructions-modal" onclick="if (event.target === this) closeSpecialInstructions();">
         <div class="instructions-modal-content">
             <div class="instructions-header"><h3>Special Instructions</h3><div class="instructions-po-number" id="instructionsPoNumber"></div></div>
             <div class="instructions-body" id="instructionsContent"></div>
             <div class="instructions-footer"><button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button></div>
         </div>
     </div>

     <!-- Confirmation Modal for Completing Order -->
     <div id="completeConfirmationModal" class="confirmation-modal" onclick="if (event.target === this) closeCompleteConfirmation();">
          <div class="confirmation-content">
             <div class="confirmation-title">Confirm Completion</div>
             <div class="confirmation-message">Mark this order as Completed?</div>
             <div class="confirmation-buttons">
                 <button class="confirm-no" onclick="closeCompleteConfirmation()">No</button>
                 <button class="confirm-yes" onclick="executeCompleteOrder()">Yes</button>
             </div>
         </div>
     </div>

     <!-- Hidden div for PDF generation -->
     <div id="contentToDownload" style="position: absolute; left: -9999px; top: auto; width: 800px;">
         <div class="po-container">
              <div class="po-header"><div class="po-company" id="printCompany"></div><div class="po-title">Purchase Order</div></div>
              <div class="po-details">
                  <div class="po-left"><div class="po-detail-row"><span class="po-detail-label">PO:</span> <span id="printPoNumber"></span></div><div class="po-detail-row"><span class="po-detail-label">User:</span> <span id="printUsername"></span></div><div class="po-detail-row"><span class="po-detail-label">Address:</span> <span id="printDeliveryAddress"></span></div><div class="po-detail-row" id="printInstructionsSection" style="display: none;"><span class="po-detail-label">Instructions:</span> <span id="printSpecialInstructions" style="white-space: pre-wrap;"></span></div></div>
                  <div class="po-right"><div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div><div class="po-detail-row"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div></div>
              </div>
              <table class="po-table"><thead><tr><th>Cat</th><th>Product</th><th>Pack</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody id="printOrderItems"></tbody></table>
              <div class="po-total">Total: PHP <span id="printTotalAmount"></span></div>
         </div>
     </div>


    <script>
        // Global variable to store PO number during confirmation
        let poToComplete = '';

        // --- Toast Function ---
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) {
                console.error("Toast container not found!");
                return;
            }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`; // Base classes
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i>
                    <div class="message">${message}</div>
                </div>`;
            container.appendChild(toast);

            // Trigger the animation
            // Use setTimeout to allow the element to be added to the DOM first
            setTimeout(() => {
                toast.classList.add('show');
            }, 10); // Small delay

            // Automatically remove the toast after ~3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                // Remove the element after the fade-out transition completes
                toast.addEventListener('transitionend', () => {
                    if (toast.parentElement) { // Check if it hasn't already been removed
                       toast.remove();
                    }
                });
                 // Fallback removal if transitionend doesn't fire (e.g., if display:none is used)
                 setTimeout(() => {
                      if (toast.parentElement) {
                          toast.remove();
                      }
                 }, 500); // Should match transition duration

            }, 3000); // Duration toast is visible
        }

        // --- Order Completion Confirmation ---
        function confirmCompleteOrder(poNumber) {
            if (!poNumber) return;
            poToComplete = poNumber; // Store the PO number
            $('#completeConfirmationModal .confirmation-message').text(`Mark order ${poNumber} as Completed?`);
            $('#completeConfirmationModal').css('display', 'flex'); // Use flex to enable centering
        }

        function closeCompleteConfirmation() {
            $('#completeConfirmationModal').css('display', 'none');
            poToComplete = ''; // Clear the stored PO number
        }

        // Function called when "Yes" is clicked on confirmation
        function executeCompleteOrder() {
            if (!poToComplete) {
                console.error("No PO number stored for completion.");
                return; // Safety check
            }

            const poNumber = poToComplete; // Get the stored PO number
            poToComplete = ''; // Clear it immediately after use

            closeCompleteConfirmation(); // Hide confirmation modal
            showToast(`Processing completion for ${poNumber}...`, 'info');

            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('status', 'Completed'); // Target status

            // Use the same status update endpoint
            fetch('/backend/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text().then(text => { // Read response as text first
                 try {
                     const jsonData = JSON.parse(text); // Try to parse as JSON
                     if (!response.ok) { // Check HTTP status code
                         // Throw an error using the message from JSON if available
                         throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     }
                     return jsonData; // Return parsed JSON
                 } catch (e) {
                     // If parsing failed or it wasn't JSON
                     console.error('Invalid JSON response:', text);
                     // Throw a more generic error, including the original text if helpful
                     throw new Error('Received an invalid response from the server.');
                 }
            }))
            .then(data => {
                if (data.success) {
                    showToast(`Order ${poNumber} marked as Completed!`, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500); // Reload to reflect changes
                } else {
                    // Error message was already thrown in the previous .then()
                    // We just need to catch it below
                    throw new Error(data.message || 'Failed to update status.');
                }
            })
            .catch(error => {
                console.error("Complete order fetch error:", error);
                // Display the specific error message caught
                showToast(`Error completing order ${poNumber}: ${error.message}`, 'error');
            });
        }

        // --- View Order Details ---
        function viewOrderInfo(ordersJson, poNumber) {
             try {
                 const orderDetails = JSON.parse(ordersJson);
                 const body = $('#orderDetailsBody').empty(); // Clear previous details
                 let totalAmount = 0;

                 if (!Array.isArray(orderDetails)) {
                    throw new Error("Order data is not in the expected format.");
                 }

                 orderDetails.forEach(product => {
                     const price = parseFloat(product.price) || 0;
                     const quantity = parseInt(product.quantity) || 0;
                     const itemTotal = price * quantity;
                     totalAmount += itemTotal;
                     body.append(`
                         <tr>
                             <td>${product.category || 'N/A'}</td>
                             <td>${product.item_description || 'N/A'}</td>
                             <td>${product.packaging || 'N/A'}</td>
                             <td>PHP ${price.toFixed(2)}</td>
                             <td>${quantity}</td>
                         </tr>`);
                 });

                 $('#modalPoNumber').text(poNumber || 'N/A'); // Display PO number
                 $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);
                 $('#orderDetailsModal').css('display', 'flex'); // Show modal using flex for centering
             } catch (e) {
                 console.error('Error parsing or displaying order details:', e);
                 showToast('Could not display order details. Data might be corrupted.', 'error');
             }
         }

         function closeOrderDetailsModal() {
             $('#orderDetailsModal').css('display', 'none');
         }

         // --- View Special Instructions ---
         function viewSpecialInstructions(poNumber, instructions) {
             $('#instructionsPoNumber').text('PO: ' + (poNumber || 'N/A'));
             const contentEl = $('#instructionsContent');
             if (instructions && instructions.trim().length > 0) {
                 // Sanitize instructions before displaying? Basic text display for now.
                 contentEl.text(instructions).removeClass('empty');
             } else {
                 contentEl.text('No special instructions provided.').addClass('empty');
             }
             $('#specialInstructionsModal').css('display', 'flex'); // Show using flex
         }

         function closeSpecialInstructions() {
             $('#specialInstructionsModal').css('display', 'none');
         }

        // --- Download PO PDF ---
        function downloadPO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
            console.log("Requesting PDF download for:", poNumber);
            try {
                // 1. Populate the hidden div used by html2pdf
                $('#printCompany').text(company || 'N/A');
                $('#printPoNumber').text(poNumber || 'N/A');
                $('#printUsername').text(username || 'N/A');
                $('#printDeliveryAddress').text(deliveryAddress || 'N/A');
                $('#printOrderDate').text(orderDate || 'N/A');
                $('#printDeliveryDate').text(deliveryDate || 'N/A');
                $('#printTotalAmount').text(parseFloat(totalAmount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                const instrSec = $('#printInstructionsSection');
                if (specialInstructions && specialInstructions.trim()) {
                    $('#printSpecialInstructions').text(specialInstructions).css('white-space', 'pre-wrap');
                    instrSec.show();
                } else {
                    instrSec.hide();
                }

                const items = JSON.parse(ordersJson || '[]'); // Default to empty array if invalid JSON
                const body = $('#printOrderItems').empty();
                if (!Array.isArray(items)) {
                    throw new Error("Order items JSON is not an array.");
                }
                items.forEach(item => {
                    const price = parseFloat(item.price) || 0;
                    const qty = parseInt(item.quantity) || 0;
                    const total = price * qty;
                    body.append(`<tr><td>${item.category||''}</td><td>${item.item_description||''}</td><td>${item.packaging||''}</td><td style="text-align: right;">${qty}</td><td style="text-align: right;">PHP ${price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td style="text-align: right;">PHP ${total.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}</td></tr>`);
                });

                // 2. Configure html2pdf
                const element = document.getElementById('contentToDownload');
                const opt = {
                    margin: [10, 10, 10, 10],
                    filename: `PO_${poNumber || 'UnknownPO'}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, logging: false }, // Disable console logging from html2canvas
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                // 3. Generate and save the PDF
                showToast(`Generating PDF for ${poNumber}...`, 'info');
                html2pdf().set(opt).from(element).save()
                .then(() => {
                    console.log("PDF Download successful for:", poNumber);
                    // Optional: Success toast, but download prompt is usually enough
                    // showToast(`PO ${poNumber} download started.`, 'success');
                })
                .catch(error => {
                    console.error(`Error generating PDF for ${poNumber}:`, error);
                    showToast('Error generating PDF.', 'error');
                });

            } catch (e) {
                console.error(`Error preparing PDF data for ${poNumber}:`, e);
                showToast(`Error preparing PDF data: ${e.message}`, 'error');
            }
        }


         // --- Document Ready ---
         $(document).ready(function() {
             // Add click listeners for closing modals by clicking outside the content area
             // Note: This requires the overlay itself to have the click listener in the HTML
             // e.g., <div id="orderDetailsModal" class="overlay" onclick="if (event.target === this) closeOrderDetailsModal();">

             // Optional: If you prefer JS-based closing on overlay click:
             /*
             $('.overlay').on('click', function(event) {
                 if (event.target === this) { // Check if the click was directly on the overlay background
                     const modalId = $(this).attr('id');
                     if (modalId === 'orderDetailsModal') {
                         closeOrderDetailsModal();
                     } else if (modalId === 'specialInstructionsModal') {
                         closeSpecialInstructions();
                     } else if (modalId === 'completeConfirmationModal') {
                          closeCompleteConfirmation();
                     }
                     // Add more else if blocks for other modals if needed
                 }
             });
             */
         });

    </script>

</body>
</html>