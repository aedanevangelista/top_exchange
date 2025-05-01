<?php
// Current Date: 2025-05-01 17:43:19
// Author: aedanevangelista

session_start();
include "../../backend/db_connection.php"; // Ensure this path is correct
include "../../backend/check_role.php"; // Ensure this path is correct

checkRole('Deliverables'); // Adjust role name if necessary

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

if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die('Execute failed: ' . htmlspecialchars($conn->error));
}

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverable Orders</title>
    <link rel="stylesheet" href="/css/deliverables.css"> <!-- Ensure path is correct -->
    <link rel="stylesheet" href="/css/sidebar.css"> <!-- Ensure path is correct -->
    <link rel="stylesheet" href="/css/toast.css"> <!-- Ensure path is correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Existing styles */
        .orders-table-container {
            max-height: 75vh; /* Adjust height as needed */
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            border: 1px solid #ddd;
            padding: 10px 12px; /* Increased padding */
            text-align: left;
            font-size: 14px; /* Slightly larger font */
            vertical-align: middle; /* Align content vertically */
        }

        .orders-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .orders-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .action-buttons button, .view-orders-btn, .instructions-btn {
            padding: 6px 12px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
            display: inline-flex; /* Align icon and text */
            align-items: center; /* Align icon and text */
            gap: 5px; /* Space between icon and text */
        }
        .action-buttons button i, .view-orders-btn i, .instructions-btn i {
             /* margin-right: 5px; Removed, using gap now */
        }


        .complete-btn {
            background-color: #28a745; /* Green */
            color: white;
        }
        .complete-btn:hover { background-color: #218838; }

        .view-orders-btn {
            background-color: #007bff; /* Blue */
            color: white;
        }
        .view-orders-btn:hover { background-color: #0056b3; }

        .instructions-btn {
            background-color: #17a2b8; /* Info Blue */
            color: white;
        }
        .instructions-btn:hover { background-color: #117a8b; }

        .download-btn {
            background-color: #6c757d; /* Secondary Gray */
            color: white;
             padding: 6px 12px;
             margin-right: 5px;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             font-size: 12px;
             transition: background-color 0.2s;
             display: inline-flex;
             align-items: center;
             gap: 5px;
        }
        .download-btn:hover { background-color: #5a6268; }

        .no-orders {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }

        .driver-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #e2e3e5;
            color: #383d41;
            font-size: 12px;
            font-weight: 500;
        }
        .driver-badge i { margin-right: 4px; }

        /* Status Badge */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
         .status-delivery { /* Specific style for 'For Delivery' */
            background-color: #e2e3e5;
            color: #383d41;
        }

        /* Order Details Modal */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow: auto;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
        }

        .overlay-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            width: 80%;
            max-width: 700px; /* Adjusted max-width */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-height: 85vh; /* Limit height */
            overflow-y: auto; /* Add scroll if needed */
            position: relative; /* Needed for absolute positioning of close button */
        }
        .overlay-content h2 { margin-top: 0; color: #333; margin-bottom: 20px; font-size: 20px; }

        .order-details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .order-details-table th, .order-details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        .order-details-table th { background-color: #f8f9fa; }

        .order-details-footer { text-align: right; margin-top: 15px; font-weight: bold; font-size: 15px; padding-top: 10px; border-top: 1px solid #eee; }

        .form-buttons { margin-top: 20px; text-align: right; }
        .form-buttons .back-btn {
             background-color: #6c757d;
             color: white;
             padding: 8px 15px;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             font-size: 14px;
        }
        .form-buttons .back-btn:hover { background-color: #5a6268; }

        /* Special Instructions Modal */
        .instructions-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
        .instructions-modal-content { background-color: #ffffff; margin: 15% auto; padding: 0; border-radius: 8px; width: 60%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: modalFadeIn 0.3s; overflow: hidden; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .instructions-header { background-color: #17a2b8; color: white; padding: 12px 15px; }
        .instructions-header h3 { margin: 0; font-size: 16px; }
        .instructions-po-number { font-size: 12px; margin-top: 3px; opacity: 0.9; }
        .instructions-body { padding: 15px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; border-bottom: 1px solid #eaeaea; max-height: 300px; overflow-y: auto; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 30px 15px; }
        .instructions-footer { padding: 10px 15px; text-align: right; background-color: #f1f1f1; }
        .close-instructions-btn { background-color: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .close-instructions-btn:hover { background-color: #5a6268; }

        /* Confirmation Modal Styles (Copied from orders.php) */
        .confirmation-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); overflow: hidden; }
        .confirmation-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border-radius: 8px; width: 350px; max-width: 90%; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalPopIn 0.3s; }
        @keyframes modalPopIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        .confirmation-title { font-size: 20px; margin-bottom: 15px; color: #333; }
        .confirmation-message { margin-bottom: 20px; color: #555; font-size: 14px; }
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes { background-color: #28a745; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .confirm-yes:hover { background-color: #218838; }
        .confirm-no { background-color: #f1f1f1; color: #333; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .confirm-no:hover { background-color: #e1e1e1; }

        /* ---- FIXED Toast CSS ---- */
        /* Ensure container is positioned */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            width: 300px; /* Adjust width as needed */
        }

        /* Basic toast styling */
        .toast {
            background-color: #333;
            color: #fff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            opacity: 0.9;
            transition: opacity 0.5s ease-in-out;
        }

        /* Style different toast types */
        .toast.success { background-color: #28a745; }
        .toast.error { background-color: #dc3545; }
        .toast.info { background-color: #17a2b8; }

        /* --- Flexbox for Icon and Message Alignment --- */
        .toast-content {
            display: flex; /* Use flexbox */
            align-items: center; /* Vertically center items */
            gap: 10px; /* Space between icon and text */
        }

        .toast-content i {
            font-size: 1.2em; /* Adjust icon size if needed */
            flex-shrink: 0; /* Prevent icon from shrinking */
             /* Removed margin-right, using gap now */
        }

        .toast-content .message {
            flex-grow: 1; /* Allow message to take remaining space */
            margin: 0; /* Reset default margins */
            font-size: 14px;
            line-height: 1.4;
        }
        /* ---- End of Fixed Toast CSS ---- */

         /* PO PDF layout (Copied from orders.php for consistency) */
         .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; }
         .po-header { text-align: center; margin-bottom: 30px; }
         .po-company { font-size: 22px; font-weight: bold; margin-bottom: 10px; }
         .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
         .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; }
         .po-left, .po-right { width: 48%; }
         .po-detail-row { margin-bottom: 10px; }
         .po-detail-label { font-weight: bold; display: inline-block; width: 120px; }
         .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
         .po-table th, .po-table td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; } /* Smaller font for PDF table */
         .po-table th { background-color: #f2f2f2; }
         .po-total { text-align: right; font-weight: bold; font-size: 13px; margin-bottom: 30px; } /* Smaller font */
         #contentToDownload { font-size: 13px; } /* Base font size for PDF */
         #contentToDownload .po-title { font-size: 16px; }
         #contentToDownload .po-company { font-size: 20px; }


    </style>
</head>
<body>
    <?php include 'sidebar.php'; // Ensure this path is correct ?>
    <div class="main-content">
        <h1><i class="fas fa-truck"></i> Deliverable Orders</h1>

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
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($order['driver_name']) ?>
                                    </span>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                <td><span class="status-badge status-delivery"><?= htmlspecialchars($order['status']) ?></span></td>
                                <td>
                                    <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order['orders'])) ?>', '<?= htmlspecialchars($order['po_number']) ?>')">
                                        <i class="fas fa-clipboard-list"></i> View
                                    </button>
                                </td>
                                <td>
                                    <?php if (!empty($order['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order['special_instructions'])) ?>')">
                                            <i class="fas fa-info-circle"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
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
     <div id="orderDetailsModal" class="overlay">
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
     <div id="specialInstructionsModal" class="instructions-modal">
         <div class="instructions-modal-content">
             <div class="instructions-header"><h3>Special Instructions</h3><div class="instructions-po-number" id="instructionsPoNumber"></div></div>
             <div class="instructions-body" id="instructionsContent"></div>
             <div class="instructions-footer"><button type="button" class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button></div>
         </div>
     </div>

     <!-- Confirmation Modal for Completing Order -->
     <div id="completeConfirmationModal" class="confirmation-modal">
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
        let poToComplete = ''; // Variable to store PO number for confirmation

        // --- Toast Function ---
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            // Use the corrected toast-content structure
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i>
                    <div class="message">${message}</div>
                </div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }

        // --- Order Completion Confirmation ---
        function confirmCompleteOrder(poNumber) {
            poToComplete = poNumber; // Store the PO number
            // Update confirmation message if needed
            $('#completeConfirmationModal .confirmation-message').text(`Mark order ${poNumber} as Completed?`);
            $('#completeConfirmationModal').css('display', 'block'); // Show confirmation modal
        }

        function closeCompleteConfirmation() {
            $('#completeConfirmationModal').css('display', 'none');
            poToComplete = ''; // Clear the stored PO number
        }

        // Function called when "Yes" is clicked on confirmation
        function executeCompleteOrder() {
            if (!poToComplete) return; // Safety check

            $('#completeConfirmationModal').css('display', 'none'); // Hide confirmation

            const poNumber = poToComplete; // Get the stored PO number
            poToComplete = ''; // Clear it after use

            // Show immediate feedback (optional)
            showToast(`Processing completion for ${poNumber}...`, 'info');

            const formData = new FormData();
            formData.append('po_number', poNumber);
            formData.append('status', 'Completed'); // Target status
            // No material flags needed for For Delivery -> Completed

            fetch('/backend/update_order_status.php', { // Use the same endpoint as orders.php
                method: 'POST',
                body: formData
            })
            .then(response => response.text().then(text => { // Get text first
                 try {
                     const jsonData = JSON.parse(text);
                     if (!response.ok) throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     return jsonData;
                 } catch (e) { console.error('Invalid JSON:', text); throw new Error('Invalid server response.'); }
            }))
            .then(data => {
                if (data.success) {
                    showToast(`Order ${poNumber} marked as Completed!`, 'success');
                    // Reload the page after a short delay to show updated list
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to update status.');
                }
            })
            .catch(error => {
                console.error("Complete order error:", error);
                showToast(`Error completing order ${poNumber}: ${error.message}`, 'error');
            });
        }

        // --- View Order Details ---
        function viewOrderInfo(ordersJson, poNumber) {
             try {
                 const orderDetails = JSON.parse(ordersJson);
                 const body = $('#orderDetailsBody').empty();
                 let totalAmount = 0;

                 orderDetails.forEach(product => {
                     const price = parseFloat(product.price) || 0;
                     const quantity = parseInt(product.quantity) || 0;
                     const itemTotal = price * quantity;
                     totalAmount += itemTotal;
                     body.append(`
                         <tr>
                             <td>${product.category || ''}</td>
                             <td>${product.item_description || ''}</td>
                             <td>${product.packaging || ''}</td>
                             <td>PHP ${price.toFixed(2)}</td>
                             <td>${quantity}</td>
                         </tr>`);
                 });

                 $('#modalPoNumber').text(poNumber); // Display PO number in modal title
                 $('#orderTotalAmount').text(`PHP ${totalAmount.toFixed(2)}`);
                 $('#orderDetailsModal').css('display', 'flex'); // Use flex to enable centering
             } catch (e) {
                 console.error('Error parsing order details:', e);
                 showToast('Could not display order details.', 'error');
             }
         }

         function closeOrderDetailsModal() {
             $('#orderDetailsModal').css('display', 'none');
         }

         // --- View Special Instructions ---
         function viewSpecialInstructions(poNumber, instructions) {
             $('#instructionsPoNumber').text('PO: ' + poNumber);
             const contentEl = $('#instructionsContent');
             if (instructions && instructions.trim().length > 0) {
                 contentEl.text(instructions).removeClass('empty');
             } else {
                 contentEl.text('No special instructions provided.').addClass('empty');
             }
             $('#specialInstructionsModal').css('display', 'block');
         }

         function closeSpecialInstructions() {
             $('#specialInstructionsModal').css('display', 'none');
         }

         // --- Download PO PDF ---
         // This function prepares the hidden div and triggers the download
         function downloadPO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions) {
             console.log("Preparing PDF for:", poNumber); // Debug log
             try {
                 // Populate hidden div for PDF generation
                 $('#printCompany').text(company || 'N/A');
                 $('#printPoNumber').text(poNumber);
                 $('#printUsername').text(username);
                 $('#printDeliveryAddress').text(deliveryAddress);
                 $('#printOrderDate').text(orderDate);
                 $('#printDeliveryDate').text(deliveryDate);
                 $('#printTotalAmount').text(parseFloat(totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                 const instructionsSection = $('#printInstructionsSection');
                 if (specialInstructions && specialInstructions.trim() !== '') {
                     $('#printSpecialInstructions').text(specialInstructions).css('white-space', 'pre-wrap');
                     instructionsSection.show();
                 } else {
                     instructionsSection.hide();
                 }

                 const orderItems = JSON.parse(ordersJson);
                 const orderItemsBody = $('#printOrderItems').empty(); // Clear previous items

                 orderItems.forEach(item => {
                     const itemPrice = parseFloat(item.price) || 0;
                     const itemQuantity = parseInt(item.quantity) || 0;
                     const itemTotal = itemPrice * itemQuantity;
                     const row = `
                         <tr>
                             <td>${item.category || ''}</td>
                             <td>${item.item_description || ''}</td>
                             <td>${item.packaging || ''}</td>
                             <td>${itemQuantity}</td>
                             <td>PHP ${itemPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                             <td>PHP ${itemTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                         </tr>`;
                     orderItemsBody.append(row);
                 });

                 const element = document.getElementById('contentToDownload');
                 const opt = {
                     margin: [10, 10, 10, 10], // Margins: top, left, bottom, right
                     filename: `PO_${poNumber}.pdf`,
                     image: { type: 'jpeg', quality: 0.98 },
                     html2canvas: { scale: 2, useCORS: true, logging: false }, // Disable logging for cleaner console
                     jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                 };

                 // Generate and save PDF
                 showToast(`Generating PDF for ${poNumber}...`, 'info');
                 html2pdf().set(opt).from(element).save()
                 .then(() => {
                     console.log("PDF Download successful for:", poNumber);
                     // showToast(`PO ${poNumber} downloaded.`, 'success'); // Optional success message
                 })
                 .catch(error => {
                     console.error('Error generating PDF for ' + poNumber + ':', error);
                     showToast('Error generating PDF.', 'error');
                 });

             } catch (e) {
                 console.error('Error preparing PDF data for ' + poNumber + ':', e);
                 showToast('Error preparing PDF data', 'error');
             }
         }


         // Close modals on outside click
         $(document).ready(function() {
             window.addEventListener('click', function(event) {
                 if ($(event.target).hasClass('overlay')) {
                     closeOrderDetailsModal();
                 }
                 if ($(event.target).hasClass('instructions-modal')) {
                     closeSpecialInstructions();
                 }
                 if ($(event.target).hasClass('confirmation-modal')) {
                     $(event.target).hide(); // Hide any confirmation modal
                     poToComplete = ''; // Clear pending action variable
                 }
             });
         });

    </script>

</body>
</html>