<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders'); 

$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

$allowed_columns = ['id', 'po_number', 'order_type', 'username', 'order_date', 'delivery_date', 'progress', 'total_amount', 'status']; // Added 'order_type'
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'id';
}

if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'DESC';
}function isValidDeliveryDayPHP($date_str) {
    if (empty($date_str)) return false;
    try {
        $date = new DateTime($date_str);
        $dayOfWeek = $date->format('N'); 
        return ($dayOfWeek == 1 || $dayOfWeek == 3 || $dayOfWeek == 5);
    } catch (Exception $e) {
        return false; 
    }
}
function isValidDeliveryGapPHP($orderDate_str, $deliveryDate_str, $minDays = 5) {
    if (empty($orderDate_str) || empty($deliveryDate_str)) return false;
    try {
        $orderDateTime = new DateTime($orderDate_str);
        $deliveryDateTime = new DateTime($deliveryDate_str);
        
        $minDeliveryDateTime = clone $orderDateTime;
        $minDeliveryDateTime->modify("+{$minDays} days");
        
        // Compare only dates, ignore time part for this check
        $orderDateTime->setTime(0,0,0);
        $deliveryDateTime->setTime(0,0,0);
        $minDeliveryDateTime->setTime(0,0,0);

        return $deliveryDateTime >= $minDeliveryDateTime;
    } catch (Exception $e) {
        return false; 
    }
}


// Process delivery date update if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_date']) && isset($_POST['po_number']) && isset($_POST['new_delivery_date'])) {
    $po_number = $_POST['po_number'];
    $new_delivery_date = $_POST['new_delivery_date'];
    
    // Get order date to validate 5-day minimum
    $stmt_order_details = $conn->prepare("SELECT order_date, username, order_type FROM orders WHERE po_number = ?");
    $stmt_order_details->bind_param("s", $po_number);
    $stmt_order_details->execute();
    $result_order_details = $stmt_order_details->get_result();
    $order = $result_order_details->fetch_assoc();
    $stmt_order_details->close();
    
    if ($order) {
        if ($order['order_type'] === 'Walk In') {
             $_SESSION['message'] = "Delivery date cannot be changed for Walk-In orders.";
             $_SESSION['message_type'] = "warning";
        } elseif (isValidDeliveryDayPHP($new_delivery_date) && isValidDeliveryGapPHP($order['order_date'], $new_delivery_date, 5)) {
            // Update delivery date
            $stmt_update = $conn->prepare("UPDATE orders SET delivery_date = ? WHERE po_number = ?");
            $stmt_update->bind_param("ss", $new_delivery_date, $po_number);
            if ($stmt_update->execute()) {
                // Send email notification about delivery date change
                $username = $order['username'];
                
                $stmt_email = $conn->prepare("SELECT email FROM clients_accounts WHERE username = ?");
                $stmt_email->bind_param("s", $username);
                $stmt_email->execute();
                $result_email = $stmt_email->get_result();
                $user_data = $result_email->fetch_assoc();
                $stmt_email->close();
                
                if ($user_data && !empty($user_data['email'])) {
                    $user_email = $user_data['email'];
                    $subject = "Top Exchange Food Corp: Delivery Date Changed";
                    $message_body = "Dear $username,\\n\\nThe delivery date for your order (PO: $po_number) has been updated.\\n";
                    $message_body .= "New Delivery Date: " . date('F j, Y', strtotime($new_delivery_date)) . "\\n\\n";
                    $message_body .= "If you have any questions regarding this change, please contact us.\\n\\n";
                    $message_body .= "Thank you,\\nTop Exchange Food Corp";
                    $headers = "From: no-reply@topexchange.com"; // Replace with your actual sender email
                    mail($user_email, $subject, $message_body, $headers);
                }
                $_SESSION['message'] = "Delivery date updated successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating delivery date: " . $stmt_update->error;
                $_SESSION['message_type'] = "error";
            }
            $stmt_update->close();
        } else {
            if (!isValidDeliveryDayPHP($new_delivery_date)) {
                $_SESSION['message'] = "Delivery date must be Monday, Wednesday, or Friday.";
            } else {
                $_SESSION['message'] = "Delivery date must be at least 5 days after the order date (" . date("M d, Y", strtotime($order['order_date'])) . ").";
            }
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Order not found for PO: " . htmlspecialchars($po_number);
        $_SESSION['message_type'] = "error";
    }
    header("Location: orders.php" . (isset($_GET['sort']) ? "?sort=".htmlspecialchars($_GET['sort'])."&direction=".htmlspecialchars($_GET['direction']) : ""));
    exit();
}

// Fetch active clients for the dropdown
$clients_list = []; // Renamed from $clients to avoid conflict
$clients_data_map = []; // To store company, address, email by username

$stmt_clients_fetch = $conn->prepare("SELECT username, company_address, company, email FROM clients_accounts WHERE status = 'active' ORDER BY username ASC");
if ($stmt_clients_fetch === false) {
    die('Prepare failed (clients_accounts): ' . htmlspecialchars($conn->error));
}
$stmt_clients_fetch->execute();
$result_clients_fetch = $stmt_clients_fetch->get_result();
while ($row_client = $result_clients_fetch->fetch_assoc()) {
    $clients_list[] = $row_client['username'];
    $clients_data_map[$row_client['username']] = [
        'company_address' => $row_client['company_address'],
        'company' => $row_client['company'],
        'email' => $row_client['email']
    ];
}
$stmt_clients_fetch->close();


// Query to show orders based on status (Pending, Rejected, Active < 100% progress)
// And also Completed, For Delivery for comprehensive view (Can be filtered by JS search if needed)
$orders_data = []; // Renamed from $orders
$sql_orders_fetch = "SELECT o.id, o.po_number, o.order_type, o.username, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.progress,
        o.company, o.special_instructions
        FROM orders o ";

$sql_orders_fetch .= " WHERE o.status NOT IN ('For Delivery', 'In Transit') "; // Added this line to filter statuses

$orderByClause = 'o.' . $conn->real_escape_string($sort_column);
$sql_orders_fetch .= " ORDER BY {$orderByClause} {$conn->real_escape_string($sort_direction)}";


$stmt_orders_fetch = $conn->prepare($sql_orders_fetch);
if ($stmt_orders_fetch === false) {
     die('Prepare failed (orders fetch): ' . htmlspecialchars($conn->error) . ' - SQL: ' . $sql_orders_fetch);
}
$stmt_orders_fetch->execute();
$result_orders_fetch = $stmt_orders_fetch->get_result();
if ($result_orders_fetch && $result_orders_fetch->num_rows > 0) {
    while ($row_order_item = $result_orders_fetch->fetch_assoc()) {
        $orders_data[] = $row_order_item;
    }
}
$stmt_orders_fetch->close();

// Helper function to generate sort URL (using your existing function)
function getSortUrl($column, $currentColumn, $currentDirection) {
    $newDirection = (strtoupper($currentDirection) === 'ASC' && $column === $currentColumn) ? 'DESC' : 'ASC';
    return "?" . http_build_query(['sort' => $column, 'direction' => $newDirection]);
}

// Helper function to display sort icon (using your existing function)
function getSortIcon($column, $currentColumn, $currentDirection) {
    // if ($column === 'id') return ''; // Your original comment, kept if 'id' isn't a visible column header
    if ($column !== $currentColumn) return '<i class="fas fa-sort"></i>';
    return (strtoupper($currentDirection) === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}

// Helper function to get next available delivery date (using your existing function)
// Note: This PHP version is for initial date setting in Add Order form if needed on server-side. JS version handles client-side.
function getNextAvailableDeliveryDatePHP($minDaysAfter = 5) {
    $startDate = new DateTime();
    $startDate->modify("+{$minDaysAfter} days"); 
    $daysToAdd = 0;
    $dayOfWeek = $startDate->format('N');
    if (!($dayOfWeek == 1 || $dayOfWeek == 3 || $dayOfWeek == 5)) {
        if ($dayOfWeek == 2) $daysToAdd = 1; 
        elseif ($dayOfWeek == 4) $daysToAdd = 1;
        elseif ($dayOfWeek == 6) $daysToAdd = 2; 
        elseif ($dayOfWeek == 7) $daysToAdd = 1; 
    }
    if ($daysToAdd > 0) $startDate->modify("+{$daysToAdd} days");
    return $startDate->format('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Top Exchange</title> <!-- Changed title slightly -->
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* --- Styles exactly as provided in commit dfe8bc989ea063004ce0bef65815a6d013e7d9e8 --- */
        /* --- Including all your specific styles for modals, tables, buttons, etc. --- */
        .order-summary { margin-top: 20px; margin-bottom: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table tbody { display: block; max-height: 250px; overflow-y: auto; border: 1px solid #ddd;}
        .summary-table thead, .summary-table tbody tr { display: table; width: 100%; table-layout: fixed; }
        .summary-table thead { width: calc(100% - 17px)); /* Adjust 17px based on scrollbar width */ }
        .summary-table th, .summary-table td { padding: 8px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid #ddd; }
        .summary-table th:nth-child(1), .summary-table td:nth-child(1) { width: 20%; } /* Category */
        .summary-table th:nth-child(2), .summary-table td:nth-child(2) { width: 30%; } /* Product */
        .summary-table th:nth-child(3), .summary-table td:nth-child(3) { width: 15%; } /* Packaging */
        .summary-table th:nth-child(4), .summary-table td:nth-child(4) { width: 15%; text-align:right; } /* Price */
        .summary-table th:nth-child(5), .summary-table td:nth-child(5) { width: 10%; text-align:center;} /* Quantity */
        .summary-table th:nth-child(6), .summary-table td:nth-child(6) { width: 10%; text-align: center; } /* Action */
        .summary-total { margin-top: 10px; text-align: right; font-weight: bold; border-top: 1px solid #ddd; padding-top: 10px; }
        .summary-quantity { width: 80px; max-width: 100%; text-align: center; padding: 6px; border: 1px solid #ccc; border-radius: 4px;}
        .download-btn { padding: 6px 12px; background-color: #17a2b8; color: white; border: none; border-radius: 40px; cursor: pointer; font-size: 12px; margin-left: 5px; }
        .download-btn:hover { background-color: #138496; } .download-btn i { margin-right: 5px; }
        .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; }
        .po-header { text-align: center; margin-bottom: 30px; } .po-company { font-size: 22px; font-weight: bold; margin-bottom: 10px; }
        .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
        .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 12px;}
        .po-left, .po-right { width: 48%; } .po-detail-row { margin-bottom: 8px; } .po-detail-label { font-weight: bold; display: inline-block; width: 110px; }
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px;}
        .po-table th, .po-table td { border: 1px solid #ddd; padding: 8px; text-align: left; } .po-table th { background-color: #f2f2f2; }
        .po-table td:nth-child(4), .po-table td:nth-child(5), .po-table td:nth-child(6) { text-align: right;} /* Qty, Unit Price, Total */
        .po-total { text-align: right; font-weight: bold; font-size: 13px; margin-bottom: 30px; padding-top:10px; border-top: 1px solid #000;}
        .po-signature { display: flex; justify-content: space-between; margin-top: 50px; } .po-signature-block { width: 40%; text-align: center; font-size:12px;}
        .po-signature-line { border-bottom: 1px solid #000; margin-bottom: 10px; padding-top: 40px; }
        #pdfPreview { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 1070; overflow: auto; }
        .pdf-container { background-color: white; width: 90%; max-width: 850px; margin: 30px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.5); position: relative; }
        .close-pdf { position: absolute; top: 10px; right: 15px; font-size: 20px; background: none; border: none; cursor: pointer; color: #333; }
        .pdf-actions { text-align: center; margin-top: 20px; } .pdf-actions button.download-pdf-btn { padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; }
        .instructions-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .instructions-modal-content { background-color: #fff; margin: 10% auto; padding: 0; border-radius: 8px; width: 90%; max-width: 600px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; flex-direction: column; }
        .instructions-header { background-color: #343a40; color: white; padding: 15px 20px; border-top-left-radius: 8px; border-top-right-radius: 8px; display:flex; justify-content:space-between; align-items:center; }
        .instructions-header h3 { margin: 0; font-size: 1.1em; } .instructions-po-number { font-size: 0.9em; opacity: 0.9; }
        .instructions-body { padding: 20px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; flex-grow: 1; overflow-y: auto; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 40px 20px; }
        .instructions-footer { padding: 15px 20px; text-align: right; background-color: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #eee; }
        .close-instructions-btn { background-color: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9em; transition: background-color 0.2s; }
        .close-instructions-btn:hover { background-color: #5a6268; }
        .instructions-btn { padding: 5px 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; }
        .instructions-btn:hover { background-color: #0056b3; } .no-instructions { color: #6c757d; font-style: italic; font-size: 12px; }
        #contentToDownload { font-size: 14px; } #contentToDownload .po-table { font-size: 11px; } #contentToDownload .po-title { font-size: 16px; } #contentToDownload .po-company { font-size: 20px; }
        .status-badge { padding: 0.3em 0.6em; border-radius: 0.25rem; font-size: 0.85em; font-weight: 600; display: inline-block; text-align: center; min-width: 85px; line-height: 1.2; }
        .status-active { background-color: #d1e7ff; color: #084298; border: 1px solid #a6cfff; } .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; } .status-for_delivery, .status-in_transit { background-color: #e2e3e5; color: #383d41; border: 1px solid #ced4da; }
        .status-completed { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .btn-info { font-size: 0.75em; opacity: 0.8; margin-top: 3px; display: block; }
        .raw-materials-container { overflow: visible; margin-bottom: 15px; } .raw-materials-container h3, .raw-materials-container h4 { margin-top: 0; margin-bottom: 10px; color: #333; font-size: 1.1em; }
        .materials-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size:0.9em; }
        .materials-table tbody { display: block; max-height: 180px; overflow-y: auto; border: 1px solid #ddd; }
        .materials-table thead, .materials-table tbody tr { display: table; width: 100%; table-layout: fixed; }
        .materials-table th, .materials-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .materials-table thead { background-color: #f2f2f2; display: table; width: calc(100% - 17px)); /* scrollbar */ table-layout: fixed; }
        .materials-table th { background-color: #f8f9fa; font-weight:600; }
        .material-sufficient { color: #28a745; font-weight: bold; } .material-insufficient { color: #dc3545; font-weight: bold; }
        .materials-status { padding: 8px; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-top: 10px; text-align: center; }
        .status-sufficient { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; } .status-insufficient { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .active-progress { background-color: #d1e7ff; color: #084298; } .pending-progress, .rejected-progress { background-color: #f8d7da; color: #721c24; }
        .order-details-footer { display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; }
        .total-amount { font-weight: bold; font-size: 1.1em; padding: 5px 10px; background-color: #f8f9fa; border-radius: 4px; }
        .search-container { display: flex; align-items: center; }
        .search-container input { padding: 8px 12px; border-radius: 20px 0 0 20px; border: 1px solid #ddd; font-size: 13px; width: 250px; transition: border-color 0.2s; }
        .search-container input:focus { border-color: #007bff; outline:none; }
        .search-container .search-btn { background-color: #007bff; color: white; border: none; border-radius: 0 20px 20px 0; padding: 8px 12px; cursor: pointer; transition: background-color 0.2s; }
        .search-container .search-btn:hover { background-color: #0056b3; }
        .orders-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; width: 100%; }
        .orders-header h1 { flex-grow: 1; margin:0; font-size: 1.8em; color: #333; }
        .add-order-btn { display: inline-flex; align-items: center; background-color: #28a745; color: white; border: none; border-radius: 20px; padding: 10px 20px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; }
        .add-order-btn:hover { background-color: #218838; } .add-order-btn i { margin-right: 8px; }
        #special_instructions_textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-family: inherit; margin-bottom: 15px; min-height: 60px; }
        .confirmation-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: hidden;}
        .confirmation-content { background-color: #fff; margin: 15% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .confirmation-title { font-size: 1.3em; margin-bottom: 15px; color: #333; font-weight: 600; } .confirmation-message { margin-bottom: 25px; color: #555; font-size: 1em; line-height:1.5; }
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes, .confirm-no { border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.2s, transform 0.1s; font-size:0.95em; }
        .confirm-yes { background-color: #007bff; color: white; } .confirm-yes:hover { background-color: #0056b3; transform: translateY(-1px); }
        .confirm-no { background-color: #6c757d; color: white; } .confirm-no:hover { background-color: #5a6268; transform: translateY(-1px); }
        #toast-container .toast-close-button { display: none; }
        .inventory-table-container { max-height: 350px; overflow-y: auto; margin-top: 15px; border: 1px solid #ddd; border-radius: 4px;}
        .inventory-table { width: 100%; border-collapse: collapse; }
        .inventory-table th, .inventory-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        .inventory-table th { background-color: #f8f9fa; position: sticky; top: 0; z-index: 10; font-weight: 600; }
        .inventory-table td:nth-child(4), .inventory-table td:nth-child(5) { text-align: center; } /* Price, Quantity */
        .inventory-quantity { width: 70px; text-align: center; padding: 6px; border:1px solid #ccc; border-radius:4px; }
        .add-to-cart-btn { background-color: #007bff; color: white; border: none; border-radius: 4px; padding: 6px 10px; cursor: pointer; font-size:0.9em; transition: background-color 0.2s; }
        .add-to-cart-btn:hover { background-color: #0056b3; } .add-to-cart-btn i { margin-right: 5px; }
        .inventory-filter-section { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; }
        .inventory-filter-section input, .inventory-filter-section select { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .remove-item-btn { background-color: #dc3545; color: white; border: none; border-radius: 4px; padding: 6px 10px; cursor: pointer; font-size:0.9em; transition: background-color 0.2s; }
        .remove-item-btn:hover { background-color: #c82333; } .cart-quantity { width: 70px; text-align: center; padding: 6px; border:1px solid #ccc; border-radius:4px; }
        #addOrderOverlay .overlay-content, #inventoryOverlay .overlay-content, #cartModal .overlay-content, #orderDetailsModal .overlay-content { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
            max-height: 90vh; overflow-y: auto; margin: 0; background-color: #fff; 
            padding: 20px 25px; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); 
            width: 90%; max-width: 800px;
        }
        #inventoryOverlay .overlay-content { max-width: 900px; }
        #cartModal .overlay-content { max-width: 700px; }
        #orderDetailsModal .overlay-content { max-width: 950px; }
        #statusModal .modal-content, #pendingStatusModal .modal-content, #rejectedStatusModal .modal-content { max-height: 85vh; overflow-y: auto; }
        .edit-date-btn { padding: 4px 8px; background-color: #ffc107; color: #212529; border: none; border-radius: 15px; cursor: pointer; font-size: 11px; margin-left: 5px; transition: background-color 0.2s; }
        .edit-date-btn:hover { background-color: #e0a800; } .edit-date-btn i { font-size: 10px; }
        #editDateModal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); }
        .edit-date-modal-content { background-color: #fff; margin: 15% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; text-align: left; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .edit-date-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .edit-date-modal-header h3 { margin: 0; font-size: 1.2em; color: #333; }
        .edit-date-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #aaa; transition: color 0.2s; } .edit-date-close:hover { color: #333; }
        .edit-date-form { display: flex; flex-direction: column; } .edit-date-form label { margin-bottom: 8px; font-weight: 600; font-size:0.95em; color:#555; }
        .edit-date-form input[type="text"], .edit-date-form input[type="date"] { padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px; font-size: 1em; }
        .edit-date-form input[readonly] { background-color: #e9ecef; }
        .edit-date-note { margin-bottom: 15px; font-size: 0.85em; color: #666; background-color: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #007bff; }
        .edit-date-footer { text-align: right; margin-top: 10px; }
        .edit-date-save-btn { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size:0.95em; transition: background-color 0.2s; }
        .edit-date-save-btn:hover { background-color: #0056b3; } .edit-date-save-btn i { margin-right: 5px; }
        .alert { padding: 15px 20px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 5px; text-align: center; font-size: 1em; }
        .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .alert-error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .main-content { padding: 25px; margin-left: 260px; transition: margin-left 0.3s; background-color: #f4f6f9; min-height: 100vh;}
        .orders-table-container { width: 100%; overflow-x: auto; background-color: #fff; padding:15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .orders-table { width: 100%; min-width: 1500px; border-collapse: collapse; }
        .orders-table th, .orders-table td { padding: 12px 15px; text-align: left; font-size: 13px; vertical-align: middle; white-space: nowrap; border-bottom: 1px solid #dee2e6; }
        .orders-table td:nth-child(7), .orders-table td:nth-child(9), .orders-table td:nth-child(12) { text-align: center; } /* Progress, Total, Actions */
        .orders-table td:nth-child(9) { font-weight: 500; } /* Total Amount */
        .orders-table thead th { background-color: #343a40; color: white; font-weight: 600; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #23272b; }
        .orders-table tbody tr:hover { background-color: #f8f9fa; }
        .orders-table th.sortable a { color: inherit; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
        .orders-table th.sortable a:hover { color: #ced4da; }
        .orders-table th.sortable a i { margin-left: 8px; color: #adb5bd; } .orders-table th.sortable a:hover i { color: #fff; }
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .action-buttons button, .view-orders-btn { padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px; transition: background-color 0.2s, box-shadow 0.2s; }
        .view-orders-btn { background-color: #17a2b8; color: white; } .view-orders-btn:hover { background-color: #138496; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-btn { background-color: #ffc107; color: #212529; } .status-btn:hover { background-color: #e0a800; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .progress-bar-container { width: 100px; background-color: #e9ecef; border-radius: 0.25rem; overflow: hidden; position: relative; height: 18px; margin: auto; }
        .progress-bar { background-color: #0d6efd; height: 100%; line-height: 18px; color: white; text-align: center; white-space: nowrap; transition: width .6s ease; font-size: 10px; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 18px; color: #000; font-size: 10px; font-weight: bold; }
        .order-details-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .order-details-table th, .order-details-table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        .order-details-table thead th { background-color: #343a40; color:white; }
        .overlay { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0,0,0,0.55); }
        .modal { display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0,0,0,0.55); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: none; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .modal-content h2 { font-size: 1.4em; margin-top:0; margin-bottom:15px; color:#333; }
        .modal-content p { margin-bottom: 20px; line-height: 1.6; color: #555; }
        .modal-footer { padding-top: 20px; text-align: right; border-top: 1px solid #e9ecef; margin-top: 20px; }
        .modal-cancel-btn { background-color: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        .modal-cancel-btn:hover { background-color: #5a6268; }
        .modal-status-btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.95em; margin: 5px; flex-grow: 1; text-align: center; transition: background-color 0.2s, transform 0.1s; }
        .modal-status-btn:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.15); transform: translateY(-1px); }
        .modal-status-btn.delivery { background-color: #0dcaf0; color: white; } .modal-status-btn.pending { background-color: #ffc107; color: #212529; }
        .modal-status-btn.rejected { background-color: #dc3545; color: white; } .modal-status-btn.active { background-color: #198754; color: white; }
        .modal-status-btn:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; box-shadow: none; transform: none; }
        .status-buttons { display: flex; justify-content: space-around; margin-top: 15px; gap:10px; }
        .no-orders td { text-align: center; padding: 30px 20px; color: #6c757d; font-style: italic; font-size: 1.1em; }
        .item-progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 4px; overflow: hidden; height: 16px; margin-top: 5px; position: relative; }
        .item-progress-bar { background-color: #198754; height: 100%; transition: width .3s ease; }
        .item-progress-text { position: absolute; width: 100%; text-align: center; line-height: 16px; color: #000; font-size: 10px; font-weight: bold; }
        .item-contribution-text { font-size: 9px; color: #6c757d; text-align: center; margin-top: 2px; }
        .status-cell { vertical-align: middle; } .completed-item { background-color: #e6ffed !important; } .completed { background-color: #d1e7dd !important; }
        .expand-units-btn { background: none; border: none; cursor: pointer; color: #007bff; padding: 0 5px; font-size:0.9em; }
        .unit-row td { font-size: 0.85em; padding: 6px 10px 6px 35px; background-color: #fdfdfd; border-top: 1px dashed #eee;}
        .unit-action-row td { text-align: right; padding: 10px; background-color: #f8f9fa; }
        .unit-action-row button { font-size: 0.8em; padding: 4px 8px; margin-left: 5px; }
        .units-divider td { border: none; padding: 1px 0; background-color: #e0e0e0; height: 1px; }
        .overlay-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #dee2e6; padding-bottom: 15px; }
        .overlay-title { margin: 0; font-size: 1.5rem; color: #333; }
        .overlay-header .cancel-btn, .overlay-header .cart-btn { font-size: 0.85em; padding: 6px 12px; } 
        .cart-btn { background-color: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .cart-btn i { margin-right: 5px; } .cart-btn:hover { background-color: #5a6268; }
        .cart-table-container { max-height: 350px; overflow-y: auto; margin-bottom: 15px; border: 1px solid #ddd; border-radius:4px; }
        .cart-table { width: 100%; border-collapse: collapse; font-size:0.9em; }
        .cart-table th, .cart-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        .cart-table th { background-color: #f8f9fa; font-weight:600; }
        .cart-table td:nth-child(4), .cart-table td:nth-child(5) { text-align: center; } /* Price, Qty */
        .cart-total { text-align: right; margin-top:15px; margin-bottom: 20px; font-weight: bold; font-size: 1.2em; }
        .form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
        .cancel-btn, .back-btn, .save-btn, .confirm-btn { border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; }
        .cancel-btn, .back-btn { background-color: #6c757d; color: white; } .cancel-btn:hover, .back-btn:hover { background-color: #5a6268; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .save-btn, .confirm-btn { background-color: #007bff; color: white; } .save-btn:hover, .confirm-btn:hover { background-color: #0056b3; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .save-btn i, .confirm-btn i, .cancel-btn i, .back-btn i { margin-right: 6px; }
        .order-form .form-group { margin-bottom: 18px; } /* Your original form-group style */
        .order-form label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #495057; }
        .order-form input[type="text"], .order-form input[type="date"], .order-form select, .order-form textarea { 
            width: 100%; padding: 10px 12px; margin-bottom: 0; /* Your original was 15px, changed to 0 for tighter layout */
            border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 14px;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .order-form input:focus, .order-form select:focus, .order-form textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 .2rem rgba(0,123,255,.25); }
        .order-form input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .centered-button { text-align: center; margin: 20px 0; }
        .open-inventory-btn { background-color: #28a745; color: white; border: none; padding: 10px 18px; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; }
        .open-inventory-btn:hover { background-color: #218838; } .open-inventory-btn i { margin-right: 8px; }
        #onlineSpecificInputs .form-group, #walkInSpecificInputs .form-group { margin-bottom: 18px; }
        #commonOrderFields { margin-top: 15px; }
        /* End of your provided styles */
    </style>
</head>
<body>
    <?php include '../sidebar.php'; // Ensure this path is correct for your structure ?>
    <div class="main-content">
        <!-- Display message if set -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type']); ?>">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
        
        <div class="orders-header">
            <h1>Manage Orders</h1> <!-- Changed from "Orders" to "Manage Orders" for clarity -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search PO, Type, User, Company...">
                <button class="search-btn" aria-label="Search"><i class="fas fa-search"></i></button>
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
                        <th class="sortable"><a href="<?= getSortUrl('order_type', $sort_column, $sort_direction) ?>">Order Type <?= getSortIcon('order_type', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('username', $sort_column, $sort_direction) ?>">Username <?= getSortIcon('username', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('company', $sort_column, $sort_direction) ?>">Company <?= getSortIcon('company', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('order_date', $sort_column, $sort_direction) ?>">Order Date <?= getSortIcon('order_date', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('delivery_date', $sort_column, $sort_direction) ?>">Delivery Date <?= getSortIcon('delivery_date', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable"><a href="<?= getSortUrl('progress', $sort_column, $sort_direction) ?>">Progress <?= getSortIcon('progress', $sort_column, $sort_direction) ?></a></th>
                        <th>Items</th>
                        <th class="sortable"><a href="<?= getSortUrl('total_amount', $sort_column, $sort_direction) ?>">Total Amount <?= getSortIcon('total_amount', $sort_column, $sort_direction) ?></a></th>
                        <th>Instructions</th>
                        <th class="sortable"><a href="<?= getSortUrl('status', $sort_column, $sort_direction) ?>">Status <?= getSortIcon('status', $sort_column, $sort_direction) ?></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders_data) > 0): ?>
                        <?php foreach ($orders_data as $order_item_row): // Renamed from $order to $order_item_row ?>
                            <tr data-current-status="<?= htmlspecialchars($order_item_row['status']) ?>" data-po-number="<?= htmlspecialchars($order_item_row['po_number']) ?>">
                                <td><?= htmlspecialchars($order_item_row['po_number']) ?></td>
                                <td><?= htmlspecialchars($order_item_row['order_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order_item_row['username'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order_item_row['company'] ?? ($clients_data_map[$order_item_row['username']]['company'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(date("M d, Y", strtotime($order_item_row['order_date']))) ?></td>
                                <td>
                                    <?php 
                                    if ($order_item_row['order_type'] === 'Walk In' && $order_item_row['order_date'] === $order_item_row['delivery_date']) { echo 'N/A (Walk-In)'; } 
                                    elseif (empty($order_item_row['delivery_date'])) { echo 'N/A'; } 
                                    else { echo htmlspecialchars(date("M d, Y", strtotime($order_item_row['delivery_date']))); }
                                    ?>
                                    <?php if ($order_item_row['order_type'] !== 'Walk In' && !empty($order_item_row['delivery_date'])): ?>
                                    <button class="edit-date-btn" onclick="openEditDateModal('<?= htmlspecialchars($order_item_row['po_number']) ?>', '<?= htmlspecialchars($order_item_row['delivery_date']) ?>', '<?= htmlspecialchars($order_item_row['order_date']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($order_item_row['status'], ['Active', 'For Delivery', 'Completed', 'In Transit'])): // Added 'In Transit' ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order_item_row['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order_item_row['progress'] ?? 0 ?>%</div>
                                    </div>
                                    <?php else: // For Pending, Rejected ?>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '_', htmlspecialchars($order_item_row['status']))) ?>"><?= htmlspecialchars($order_item_row['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                     <?php if ($order_item_row['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order_item_row['po_number']) ?>')"><i class="fas fa-tasks"></i> Manage Items</button>
                                    <?php else: ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order_item_row['orders'])) ?>', '<?= htmlspecialchars($order_item_row['status']) ?>')"><i class="fas fa-eye"></i> View Items</button>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format((float)$order_item_row['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order_item_row['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order_item_row['po_number'])) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $order_item_row['special_instructions']))) ?>')"><i class="fas fa-info-circle"></i> View</button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '_', htmlspecialchars($order_item_row['status']))) ?>"><?= htmlspecialchars($order_item_row['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order_item_row['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order_item_row['po_number']) ?>', '<?= htmlspecialchars($order_item_row['username']) ?>', '<?= htmlspecialchars(addslashes($order_item_row['orders'])) ?>', '<?= htmlspecialchars($order_item_row['status']) ?>')"><i class="fas fa-edit"></i> Status</button>
                                    <?php elseif ($order_item_row['status'] === 'Active' && ((int)($order_item_row['progress'] ?? 0)) < 100): ?>
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order_item_row['po_number']) ?>', '<?= htmlspecialchars($order_item_row['username']) ?>', '<?= htmlspecialchars($order_item_row['status']) ?>')"><i class="fas fa-edit"></i> Status</button>
                                    <?php elseif ($order_item_row['status'] === 'Rejected'): ?>
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order_item_row['po_number']) ?>', '<?= htmlspecialchars($order_item_row['username']) ?>', '<?= htmlspecialchars($order_item_row['status']) ?>')"><i class="fas fa-edit"></i> Status</button>
                                    <?php endif; ?>
                                    <button class="download-btn" 
                                            onclick="prepareAndShowPOPreview('<?= htmlspecialchars($order_item_row['po_number']) ?>', '<?= htmlspecialchars($order_item_row['username'] ?? 'Walk-In Customer') ?>', '<?= htmlspecialchars($order_item_row['company'] ?? ($clients_data_map[$order_item_row['username']]['company'] ?? 'N/A')) ?>', '<?= htmlspecialchars($order_item_row['order_date']) ?>', '<?= htmlspecialchars($order_item_row['delivery_date']) ?>', '<?= htmlspecialchars(addslashes($order_item_row['delivery_address'])) ?>', '<?= htmlspecialchars(addslashes($order_item_row['orders'])) ?>', '<?= htmlspecialchars($order_item_row['total_amount']) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $order_item_row['special_instructions']))) ?>', '<?= htmlspecialchars($order_item_row['order_type']) ?>')">
                                        <i class="fas fa-file-pdf"></i> Invoice
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="no-orders">No orders found.</td></tr> <!-- Adjusted colspan -->
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <!-- PDF Preview Modal (Using your structure) -->
    <div id="pdfPreview">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <div class="po-container">
                     <div class="po-header"><div class="po-company" id="printCompany"></div><div class="po-title">Sales Invoice</div></div>
                     <div class="po-details">
                         <div class="po-left"><div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div><div class="po-detail-row"><span class="po-detail-label">Client:</span> <span id="printUsername"></span></div><div class="po-detail-row"><span class="po-detail-label">Address:</span> <span id="printDeliveryAddress"></span></div></div>
                         <div class="po-right"><div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div><div class="po-detail-row" id="printDeliveryDateRow"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div></div>
                     </div>
                     <div id="printInstructionsSection" style="margin-bottom:20px;display:none;font-size:12px;"><strong>Special Instructions:</strong><div id="printSpecialInstructions" style="white-space:pre-wrap; word-wrap:break-word; margin-top:5px; padding:8px; background-color:#f9f9f9; border-radius:4px;"></div></div>
                     <table class="po-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Total Price</th></tr></thead><tbody id="printOrderItems"></tbody></table>
                     <div class="po-total">Grand Total: PHP <span id="printTotalAmount"></span></div>
                     <div class="po-signature"><div class="po-signature-block"><div class="po-signature-line"></div>Prepared by</div><div class="po-signature-block"><div class="po-signature-line"></div>Received by</div></div>
                </div>
            </div>
            <div class="pdf-actions"><button class="download-pdf-btn" onclick="downloadCurrentPODataAsPDF()"><i class="fas fa-download"></i> Download PDF</button></div>
        </div>
    </div>

    <!-- Special Instructions Modal (Using your structure) -->
    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content"><div class="instructions-header"><h3>Special Instructions for <span id="instructionsPoNumber"></span></h3><button class="close-instructions-btn" onclick="closeSpecialInstructions()">&times;</button></div><div id="instructionsContent" class="instructions-body"></div><div class="instructions-footer"><button class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button></div></div>
    </div>
    
    <!-- Order Details Modal (Using your structure) -->
    <div id="orderDetailsModal" class="overlay">
        <div class="overlay-content">
            <div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-tasks"></i> Order Item Details (<span id="orderStatusView"></span>)</h2><button type="button" class="cancel-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-times"></i> Close</button></div>
            <div id="overall-progress-info" style="margin-bottom:15px;display:none;"><strong>Overall Order Progress:</strong><div class="progress-bar-container" style="margin-top:5px;height:22px;"><div id="overall-progress-bar" class="progress-bar" style="width:0%;"></div><div id="overall-progress-text" class="progress-text">0%</div></div></div>
            <div class="order-details-container"><table class="order-details-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Price</th><th style="text-align:center;">Quantity</th><th id="status-header-cell">Status/Progress</th></tr></thead><tbody id="orderDetailsBody"></tbody></table></div>
            <div class="order-details-footer"><div class="total-amount" id="orderTotalAmount">Total: PHP 0.00</div></div>
            <div class="form-buttons"><button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="save-btn save-progress-btn" onclick="confirmSaveProgress()"><i class="fas fa-save"></i> Save Progress</button></div>
        </div>
    </div>

    <!-- Status Modal (for Active Orders - Using your structure) -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h2>Change Status</h2><p id="statusMessage"></p>
            <div class="status-buttons">
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject<div class="btn-info">(Return stock)</div></button>
            </div>
            <div class="modal-footer"><button type="button" onclick="closeStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Rejected Status Modal (Using your structure) -->
    <div id="rejectedStatusModal" class="modal">
        <div class="modal-content">
            <h2>Change Status</h2><p id="rejectedStatusMessage"></p>
            <div class="status-buttons"><button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return to pending)</div></button></div>
            <div class="modal-footer"><button type="button" onclick="closeRejectedStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Pending Status Modal (Using your structure, with #rawMaterialsContainer for material info) -->
    <div id="pendingStatusModal" class="modal">
        <div class="modal-content">
            <h2>Change Status</h2><p id="pendingStatusMessage"></p>
            <div id="rawMaterialsContainer" class="raw-materials-container"><h3>Loading inventory status...</h3></div> <!-- Your existing container ID -->
            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active" disabled><i class="fas fa-check"></i> Active<div class="btn-info">(Deduct stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject</button>
            </div>
            <div class="modal-footer"><button type="button" onclick="closePendingStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <!-- Edit Delivery Date Modal (Using your structure) -->
    <div id="editDateModal" class="modal">
        <div class="edit-date-modal-content">
            <div class="edit-date-modal-header">
                <h3>Edit Delivery Date</h3>
                <button class="edit-date-close" onclick="closeEditDateModal()">&times;</button>
            </div>
            <form id="editDateForm" method="POST" class="edit-date-form" onsubmit="return validateEditDateForm(event)"> <!-- Added onsubmit -->
                <input type="hidden" id="edit_po_number" name="po_number">
                <div class="form-group"> <!-- Added form-group for consistency -->
                    <label for="current_delivery_date">Current Delivery Date:</label>
                    <input type="text" id="current_delivery_date" class="form-control" readonly> <!-- Added form-control class -->
                </div>
                <div class="form-group">
                    <label for="new_delivery_date_input_edit">New Delivery Date:</label> <!-- Changed ID to avoid conflict with add form -->
                    <input type="text" id="new_delivery_date_input_edit" name="new_delivery_date" class="form-control" autocomplete="off" required>
                </div>
                <div class="edit-date-note" style="margin-bottom: 15px; font-size: 12px; color: #666;">
                    <i class="fas fa-info-circle"></i> Delivery dates must be Mon, Wed, or Fri, and at least 5 days after the order date (<span id="edit_order_date_display_modal"></span>). <!-- Changed ID -->
                </div>
                <div class="edit-date-footer">
                    <input type="hidden" name="update_delivery_date" value="1">
                    <button type="submit" class="edit-date-save-btn"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add New Order Overlay (Using your structure, added order_type fields) -->
    <div id="addOrderOverlay" class="overlay">
        <div class="overlay-content">
            <div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-cart-plus"></i> Add New Order</h2></div>
            <form id="addOrderForm" method="POST" class="order-form">
                <div class="form-group"> <!-- Added form-group -->
                    <label for="order_type_selection_add">Order Type:</label> <!-- Changed ID -->
                    <select id="order_type_selection_add" onchange="toggleOrderFormFieldsAdd()" class="form-control"> <!-- Changed onchange function name -->
                        <option value="" disabled selected>--- Select Order Type ---</option>
                        <option value="Online">Online Order (Existing Client)</option>
                        <option value="Walk In">Walk-In / New Client</option>
                    </select>
                    <input type="hidden" name="order_type" id="order_type_hidden_for_submit_add"> <!-- Changed ID -->
                </div>

                <div id="onlineSpecificInputsAdd" style="display:none;"> <!-- Changed ID -->
                    <div class="form-group">
                        <label for="username_online_select_add">Client Username:</label> <!-- Changed ID -->
                        <select id="username_online_select_add" name="username_online" onchange="handleOnlineUserChangeAdd()" class="form-control"> <!-- Changed ID and onchange -->
                            <option value="" disabled selected>--- Select Client ---</option>
                            <?php foreach ($clients_list as $client_username_item):?>
                                <option value="<?= htmlspecialchars($client_username_item) ?>" 
                                        data-company="<?= htmlspecialchars($clients_data_map[$client_username_item]['company'] ?? '') ?>" 
                                        data-company-address="<?= htmlspecialchars($clients_data_map[$client_username_item]['company_address'] ?? '') ?>">
                                    <?= htmlspecialchars($client_username_item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="online_company_display_add">Company:</label> <!-- Changed ID -->
                        <input type="text" id="online_company_display_add" class="form-control" readonly>
                    </div>
                </div>

                <div id="walkInSpecificInputsAdd" style="display:none;"> <!-- Changed ID -->
                    <div class="form-group">
                        <label for="walk_in_name_company_input_add">Full Name / Company Name:</label> <!-- Changed ID -->
                        <input type="text" id="walk_in_name_company_input_add" name="walk_in_name_company_input" class="form-control" placeholder="Enter client's full name or company name">
                    </div>
                </div>
                
                <div id="commonOrderFieldsAdd" style="display:none;"> <!-- Changed ID -->
                    <div class="form-group">
                        <label for="order_date_add">Order Date:</label> <!-- Changed ID -->
                        <input type="text" id="order_date_add" name="order_date" class="form-control" readonly>
                    </div>
                    <div class="form-group" id="delivery_date_form_group_add" style="display:none;"> <!-- Changed ID -->
                        <label for="delivery_date_add">Requested Delivery Date:</label> <!-- Changed ID -->
                        <input type="text" id="delivery_date_add" name="delivery_date" class="form-control" autocomplete="off" placeholder="Select a Mon, Wed, or Fri">
                        <small class="form-text text-muted">Deliveries: Mon, Wed, Fri. Min. 5 days from order date.</small>
                    </div>
                    <div class="form-group" id="delivery_address_type_form_group_add" style="display:none;"> <!-- Changed ID -->
                        <label for="delivery_address_type_select_add">Delivery Address Type:</label> <!-- Changed ID -->
                        <select id="delivery_address_type_select_add" onchange="toggleDeliveryAddressOptionsAdd()" class="form-control"> <!-- Changed onchange -->
                            <option value="custom" selected>Enter Custom Address</option>
                            <option value="company" id="company_address_option_for_delivery_add" style="display:none;">Use Client's Registered Address</option> <!-- Changed ID -->
                        </select>
                    </div>
                    <div id="company_address_container_div_add" style="display:none;" class="form-group"> <!-- Changed ID -->
                        <label for="company_address_display_field_add">Registered Company Address:</label> <!-- Changed ID -->
                        <input type="text" id="company_address_display_field_add" class="form-control" readonly>
                    </div>
                    <div id="custom_address_container_div_add" class="form-group" style="display:none;"> <!-- Changed ID -->
                        <label for="custom_address_input_field_add" id="custom_address_label_add">Custom Delivery Address:</label> <!-- Changed ID -->
                        <textarea id="custom_address_input_field_add" name="custom_address" rows="3" class="form-control" placeholder="Enter complete address"></textarea> <!-- Removed direct name, handled by hidden input -->
                    </div>
                    <input type="hidden" name="delivery_address" id="delivery_address_for_submit_add"> <!-- Changed ID -->
                    <div class="form-group">
                        <label for="special_instructions_input_add">Special Instructions / Notes:</label> <!-- Changed ID -->
                        <textarea id="special_instructions_input_add" name="special_instructions" rows="3" class="form-control" placeholder="e.g., Contact person, landmark"></textarea>
                    </div>
                    <div class="centered-button"><button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()"><i class="fas fa-box-open"></i> Select Products</button></div>
                    <div class="order-summary"><h3>Order Summary <span id="orderSummaryItemCountAdd">(0 items)</span></h3><table class="summary-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Price</th><th style="text-align:center;">Quantity</th><th style="text-align:center;">Action</th></tr></thead><tbody id="summaryBodyAdd"></tbody></table><div class="summary-total">Total: <span class="summary-total-amount-add">PHP 0.00</span></div></div>
                </div>
                <input type="hidden" name="po_number" id="po_number_for_submit_add"> <!-- Changed ID -->
                <input type="hidden" name="orders" id="orders_json_for_submit_add"> <!-- Changed ID -->
                <input type="hidden" name="total_amount" id="total_amount_for_submit_add"> <!-- Changed ID -->
                <input type="hidden" name="company_name_final" id="company_name_final_for_submit_add"> <!-- Changed ID -->
                <div class="form-buttons"><button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="save-btn" id="confirmAddOrderBtnAdd" onclick="confirmAddOrder()"><i class="fas fa-check"></i> Confirm Order</button></div>
            </form>
        </div>
    </div>

    <!-- Confirmation modals (Using your structure, removed driver confirmation) -->
    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm New Order</div><div class="confirmation-message">Are you sure you want to submit this new order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No, Review</button><button class="confirm-yes" onclick="submitAddOrder()">Yes, Submit</button></div></div></div>
    <div id="saveProgressConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Save Progress</div><div class="confirmation-message">Are you sure you want to save the current progress for this order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeSaveProgressConfirmation()">Cancel</button><button class="confirm-yes" onclick="saveProgressChanges()">Yes, Save</button></div></div></div>
    <div id="statusConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Status Change</div><div class="confirmation-message" id="statusConfirmationMessage">Are you sure?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeStatusConfirmation()">No</button><button class="confirm-yes" onclick="executeStatusChange()">Yes</button></div></div></div>
    <div id="downloadConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Download</div><div class="confirmation-message">Download PO as PDF?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDownloadConfirmation()">Cancel</button><button class="confirm-yes" onclick="downloadPODirectly()">Yes, Download</button></div></div></div>

    <!-- Inventory Overlay (Using your structure) -->
    <div id="inventoryOverlay" class="overlay">
        <div class="overlay-content">
             <div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2><button class="cart-btn" onclick="window.openCartModal()"><i class="fas fa-shopping-cart"></i> View Cart (<span id="cartItemCountNav">0</span>)</button><button type="button" class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-times"></i> Close</button></div>
             <div class="inventory-filter-section"><input type="text" id="inventorySearch" placeholder="Search products..."><select id="inventoryFilter"><option value="all">All Categories</option></select></div>
             <div class="inventory-table-container"><table class="inventory-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="inventory"></tbody></table></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="confirm-btn" onclick="saveCartChangesAndClose()"><i class="fas fa-check"></i> Confirm Selection</button></div>
        </div>
    </div>

    <!-- Cart Modal (Using your structure) -->
    <div id="cartModal" class="overlay">
        <div class="overlay-content">
             <h2><i class="fas fa-shopping-cart"></i> Selected Products for New Order</h2>
             <div class="cart-table-container"><table class="cart-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="cart"></tbody></table></div>
             <div class="cart-total" style="text-align: right; margin-bottom: 20px; font-weight: bold; font-size: 1.1em;">Total: <span class="total-amount-cart">PHP 0.00</span></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeCartModal()"><i class="fas fa-arrow-left"></i> Back to Inventory</button><button type="button" class="confirm-btn" onclick="saveCartChangesAndClose()"><i class="fas fa-check"></i> Confirm & Close Cart</button></div>
        </div>
    </div>

    <script>
        // --- Global Variables (Using your structure, adapted where needed) ---
        let currentPoNumber = '';
        let currentOrderOriginalStatus = ''; // Used to determine material return logic
        let currentOrderItems = []; // For item progress in viewOrderDetails
        let completedItems = []; // For item progress in viewOrderDetails (deprecated, use quantityProgressData)
        let quantityProgressData = {}; // For item progress in viewOrderDetails
        let itemProgressPercentages = {}; // For item progress in viewOrderDetails
        let itemContributions = {}; // For item progress in viewOrderDetails
        let overallProgress = 0; // For item progress in viewOrderDetails
        let currentPODataForPDF = null; // MODIFIED: Using this for PDF preview/download logic
        let selectedStatus = ''; // For status change confirmation flow
        // let poDownloadData = null; // REMOVED: Replaced by currentPODataForPDF
        let cartItems = []; // For new order form
        let editingOrderDate = ''; // Store order date when editing delivery date (your existing variable)
        const clientsDataFromPHPOrders = <?php echo json_encode($clients_data_map); ?>; // Added suffix to avoid conflict if any other clientsData exists

        // --- Utility Functions (Using your structure) ---
        function showToast(message, type = 'info', duration = 3500) { // Added duration parameter
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`; // Using template literal for class
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-times-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            toast.innerHTML = `<div class="toast-icon"><i class="fas ${iconClass}"></i></div><div class="toast-message">${message}</div>`; // Simplified toast HTML
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration); // Fade out before removing
        }
        function formatWeight(weightInGrams) { // Your existing function
            if (isNaN(parseFloat(weightInGrams))) return '0 g'; // Handle NaN
            weightInGrams = parseFloat(weightInGrams);
            if (weightInGrams >= 1000) return (weightInGrams / 1000).toFixed(weightInGrams % 1000 === 0 ? 0 : 2) + ' kg';
            return weightInGrams.toFixed(weightInGrams % 1 === 0 ? 0 : 2) + ' g';
        }
        function isValidDeliveryDayJS(dateStr){ // Added JS suffix to distinguish from PHP
            if(!dateStr)return false;
            try{ const dt=new Date(dateStr+'T00:00:00'); const day=dt.getDay(); return(day===1||day===3||day===5); /* 0=Sun, 1=Mon */ }
            catch(e){ console.error("isValidDeliveryDayJS error:",e); return false; }
        }
        function isValidDeliveryGapJS(orderDateStr,deliveryDateStr,minDays=5){ // Added JS suffix
            if(!orderDateStr||!deliveryDateStr)return false;
            try{ const odt=new Date(orderDateStr+'T00:00:00'); const ddt=new Date(deliveryDateStr+'T00:00:00'); const mdt=new Date(odt); mdt.setDate(odt.getDate()+minDays); mdt.setHours(0,0,0,0); ddt.setHours(0,0,0,0); return ddt>=mdt; }
            catch(e){ console.error("isValidDeliveryGapJS error:",e); return false; }
        }
        function getNextAvailableDeliveryDateJS(baseDateStr=null,minDaysAfter=5){ // Added JS suffix
            let startDate;
            if(baseDateStr){ startDate=new Date(baseDateStr.includes('T')?baseDateStr:baseDateStr+'T00:00:00'); }
            else{ startDate=new Date(); startDate.setHours(0,0,0,0); }
            startDate.setDate(startDate.getDate()+minDaysAfter);
            while(true){ const dayOfWeek=startDate.getDay(); if(dayOfWeek===1||dayOfWeek===3||dayOfWeek===5){break;} startDate.setDate(startDate.getDate()+1); }
            const year=startDate.getFullYear(); const month=(startDate.getMonth()+1).toString().padStart(2,'0'); const day=startDate.getDate().toString().padStart(2,'0');
            return`${year}-${month}-${day}`;
        }


        // --- PDF Preview and Download Logic (MODIFIED to use currentPODataForPDF and direct download) ---
        function prepareAndShowPOPreview(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJsonString, totalAmount, specialInstructions, orderType) {
            currentPODataForPDF = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJsonString, totalAmount, specialInstructions, orderType }; // Store globally
            try {
                $('#printCompany').text(company || 'N/A');
                $('#printPoNumber').text(poNumber);
                $('#printUsername').text(username + (company && company !== 'N/A' ? ` (${company})` : ''));
                
                let displayAddress = deliveryAddress;
                if (orderType === 'Walk In' && deliveryAddress === company) { // Assuming company name is used as address placeholder for Walk-In
                    displayAddress = 'N/A (Walk-In)';
                } else if (orderType === 'Walk In' && !deliveryAddress) {
                     displayAddress = 'N/A (Walk-In Address Not Set)';
                }
                $('#printDeliveryAddress').text(displayAddress || 'N/A');
                
                $('#printOrderDate').text(orderDate ? new Date(orderDate + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A');

                if (orderType === 'Walk In' || !deliveryDate || orderDate === deliveryDate) {
                    $('#printDeliveryDateRow').hide();
                } else {
                    $('#printDeliveryDate').text(new Date(deliveryDate + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
                    $('#printDeliveryDateRow').show();
                }

                const instrSec = $('#printInstructionsSection');
                const instrContent = $('#printSpecialInstructions');
                if (specialInstructions && specialInstructions.trim()) {
                    instrContent.html(specialInstructions.replace(/\\n/g, '<br>')); // Use html for <br>
                    instrSec.show();
                } else {
                    instrSec.hide();
                }

                const itemsArray = JSON.parse(ordersJsonString);
                const itemsBody = $('#printOrderItems').empty();
                if (itemsArray && itemsArray.length > 0) {
                    itemsArray.forEach(item => {
                        const itemTotal = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
                        itemsBody.append(`<tr><td>${item.category||'N/A'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:right;">${item.quantity||0}</td><td style="text-align:right;">${parseFloat(item.price||0).toFixed(2)}</td><td style="text-align:right;">${itemTotal.toFixed(2)}</td></tr>`);
                    });
                } else {
                    itemsBody.append('<tr><td colspan="6" style="text-align:center;">No items in this order.</td></tr>');
                }
                $('#printTotalAmount').text(parseFloat(totalAmount).toFixed(2));
                
                $('#pdfPreview').show(); // Show the modal with the populated data

            } catch (e) {
                showToast('Error preparing PO preview data: ' + e.message, 'error');
                console.error("Error in prepareAndShowPOPreview:", e, {poNumber, ordersJsonString});
                currentPODataForPDF = null; // Clear data on error
                closePDFPreview();
            }
        }
        function downloadCurrentPODataAsPDF() { // Renamed from downloadPDF
            if (!currentPODataForPDF) {
                showToast('No PO data available to download.', 'error');
                return;
            }
            const pdfElement = document.getElementById('contentToDownload');
            const pdfOptions = {
                margin: [10, 8, 10, 8], // Adjusted margins slightly
                filename: `SalesInvoice_${currentPODataForPDF.poNumber}.pdf`,
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: { scale: 2, useCORS: true, logging: false }, // logging false for cleaner console
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            const downloadButton = $('.download-pdf-btn'); // Target the button inside the modal
            const originalButtonText = downloadButton.html();
            downloadButton.html('<i class="fas fa-spinner fa-spin"></i> Generating...').prop('disabled', true);

            html2pdf().set(pdfOptions).from(pdfElement).save()
                .then(() => {
                    showToast(`Sales Invoice PDF for PO ${currentPODataForPDF.poNumber} downloaded.`, 'success');
                })
                .catch(err => {
                    showToast('Error generating PDF: ' + err.message, 'error');
                    console.error("PDF Generation Error:", err);
                })
                .finally(() => {
                    downloadButton.html(originalButtonText).prop('disabled', false);
                    // Do not close preview automatically, user might want to look at it more.
                    // currentPODataForPDF = null; // Keep data if user wants to download again without re-opening
                });
        }
        function closePDFPreview() { $('#pdfPreview').hide(); /* currentPODataForPDF can be cleared here if preferred */ }
        // REMOVED confirmDownloadPO and downloadPODirectly as they are replaced by the new flow


        // --- Status Change Logic (Using your structure, integrated raw material checks) ---
        function confirmStatusChange(poNumber, username, originalStatus) { // For 'Active' orders
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);
            $('#statusModal').css('display', 'flex');
        }
        function confirmRejectedStatusChange(poNumber, username, originalStatus) { // For 'Rejected' orders
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#rejectedStatusModal').data('po_number', poNumber); // Your original code
            $('#rejectedStatusMessage').text(`Change status for rejected order ${poNumber} (${username})`);
            $('#rejectedStatusModal').css('display', 'flex');
        }

        // MODIFIED confirmPendingStatusChange for raw material check
        function confirmPendingStatusChange(poNumber, username, ordersJson, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus; // Should be 'Pending'
            $('#pendingStatusModal').data('po_number', poNumber); // Your original code
            $('#pendingStatusMessage').text('Change order status for ' + poNumber + '. Reviewing raw materials...');
            
            const materialContainer = $('#rawMaterialsContainer'); // Your existing container ID
            materialContainer.html('<h3><i class="fas fa-spinner fa-spin"></i> Loading inventory status...</h3>'); // Loading state
            $('#activeStatusBtn').prop('disabled', true); // Disable button until check is complete
            $('#pendingStatusModal').css('display', 'flex');
            
            try {
                 if (!ordersJson) throw new Error("Order items data missing for material check.");
                 // Basic check if ordersJson seems valid (not a fool-proof validation)
                 if (ordersJson.length > 5 && !ordersJson.includes('"product_id":') && !ordersJson.includes('"item_description":')) { 
                     console.warn("Received ordersJson might be missing product_id or item_description:", ordersJson.substring(0, 100) + "...");
                     // Depending on your ordersJson structure, this check might need adjustment
                     // If product_id is essential for raw material check, this is important.
                 }
                 JSON.parse(ordersJson); // Validate JSON structure

                $.ajax({
                    url: '/backend/check_raw_materials.php', // This script should ONLY check raw materials now
                    type: 'POST',
                    data: { orders: ordersJson, po_number: poNumber },
                    dataType: 'json',
                    success: function(response) {
                        console.log("Raw Material Check (Pending Modal):", response);
                        if (response && response.success) {
                            // Call a new function to display ONLY raw materials
                            displayRawMaterialsForPendingModal(response.materials, '#rawMaterialsContainer');
                            $('#activeStatusBtn').prop('disabled', !response.all_sufficient); // Enable/disable based on raw material sufficiency
                            if (!response.all_sufficient) {
                                let missingNames = Object.entries(response.materials)
                                    .filter(([name, details]) => !details.sufficient)
                                    .map(([name, details]) => `${name} (short by ${formatWeight(details.shortfall)})`)
                                    .join(', ');
                                if (missingNames) {
                                   showToast(`Cannot activate order: Insufficient raw materials. Missing: ${missingNames}`, 'warning', 7000);
                                } else {
                                   showToast(`Cannot activate order: Raw material check indicates insufficiency.`, 'warning', 7000);
                                }
                            }
                        } else {
                            materialContainer.html(`<h3>Raw Material Check Failed</h3><p style="color:red;">${response.message || 'Could not verify raw materials.'}</p><p>Activation button disabled.</p>`);
                            $('#activeStatusBtn').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error (Raw Material Check):", status, error, xhr.responseText);
                        let errorMsg = `Could not check raw materials: ${error || status}`;
                        if (status === 'parsererror') { errorMsg = `Could not check raw materials: Invalid response from server.`; }
                        materialContainer.html(`<h3>Server Error</h3><p style="color:red;">${errorMsg}</p><p>Activation button disabled.</p>`);
                        $('#activeStatusBtn').prop('disabled', true);
                    }
                });
            } catch (e) {
                 materialContainer.html(`<h3>Data Error</h3><p style="color:red;">${e.message}</p><p>Activation button disabled.</p>`);
                 $('#activeStatusBtn').prop('disabled', true);
                 console.error("Error processing ordersJson for material check:", e);
            }
        }

        // NEW function to display raw materials in the pending modal
        function displayRawMaterialsForPendingModal(materialsData, containerSelector) {
            const container = $(containerSelector);
            if (!container.length) return;
            let html = '<h4>Raw Material Stock Check:</h4>';
            if (!materialsData || Object.keys(materialsData).length === 0) {
                container.html(html + '<p class="text-muted" style="text-align:center;padding:10px;">No specific raw materials required for this order or information unavailable.</p>');
                return;
            }
            html += `<table class="materials-table"><thead><tr><th>Material</th><th style="text-align:right;">Available</th><th style="text-align:right;">Required</th><th>Status</th></tr></thead><tbody>`;
            let allSufficientForTable = true;
            Object.entries(materialsData).forEach(([materialName, data]) => {
                html += `<tr><td>${materialName}</td>
                             <td style="text-align:right;">${formatWeight(data.available)}</td>
                             <td style="text-align:right;">${formatWeight(data.required)}</td>
                             <td class="${data.sufficient ? 'material-sufficient' : 'material-insufficient'}">${data.sufficient ? '<i class="fas fa-check-circle"></i> Sufficient' : '<i class="fas fa-times-circle"></i> Insufficient'}</td></tr>`;
                if (!data.sufficient) allSufficientForTable = false;
            });
            html += `</tbody></table>`;
            const overallMsg = allSufficientForTable ? 'All raw materials are currently sufficient.' : 'Some raw materials are insufficient for this order.';
            html += `<p class="materials-status ${allSufficientForTable ? 'status-sufficient' : 'status-insufficient'}" style="margin-top:10px;">${overallMsg}</p>`;
            container.html(html);
        }


        function confirmStatusAction(status) { // Your existing function
            selectedStatus = status;
            let confirmationMsg = `Are you sure you want to change the status to ${selectedStatus}?`;
            if (selectedStatus === 'Active' && currentOrderOriginalStatus === 'Pending') { confirmationMsg += ' This will deduct required raw materials from inventory.'; }
            else if (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')) { confirmationMsg += ' This will attempt to return deducted raw materials to inventory.'; }
            $('#statusConfirmationMessage').text(confirmationMsg);
            $('#statusConfirmationModal').css('display', 'block');
            $('#statusModal, #pendingStatusModal, #rejectedStatusModal').css('display', 'none');
        }
        function closeStatusConfirmation() { // Your existing function
            $('#statusConfirmationModal').css('display', 'none');
             if (currentOrderOriginalStatus === 'Pending') $('#pendingStatusModal').css('display', 'flex');
             else if (currentOrderOriginalStatus === 'Rejected') $('#rejectedStatusModal').css('display', 'flex');
             else if (currentOrderOriginalStatus === 'Active') $('#statusModal').css('display', 'flex');
             selectedStatus = '';
        }
        function executeStatusChange() { // Your existing function, calls the modified updateOrderStatus
            $('#statusConfirmationModal').css('display', 'none');
            // Flags for material deduction/return are determined here based on transition
            let manageRawMaterialsAction = null;
            if (selectedStatus === 'Active' && currentOrderOriginalStatus === 'Pending') {
                manageRawMaterialsAction = 'deduct';
            } else if ((selectedStatus === 'Pending' || selectedStatus === 'Rejected') && currentOrderOriginalStatus === 'Active') {
                manageRawMaterialsAction = 'return';
            }
            updateOrderStatus(selectedStatus, manageRawMaterialsAction); // Pass the action
        }

        // MODIFIED updateOrderStatus to send manage_raw_materials action
        function updateOrderStatus(status, manageRawMaterialsAction = null) { // manageRawMaterialsAction can be 'deduct' or 'return'
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);

            if (manageRawMaterialsAction) {
                formData.append('manage_raw_materials', manageRawMaterialsAction);
            }
            
            if (status === 'For Delivery' || status === 'Completed') { // Your logic for progress
                 formData.append('progress', '100');
            }

            console.log("Sending status update:", Object.fromEntries(formData)); 

            fetch('/backend/update_order_status.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => {
                 try {
                     const jsonData = JSON.parse(text);
                     if (!response.ok) throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     return jsonData;
                 } catch (e) { console.error('Invalid JSON in updateOrderStatus:', text); throw new Error('Invalid server response during status update.'); }
            }))
            .then(data => {
                console.log("Status update response:", data);
                if (data.success) {
                    let message = `Status updated to ${status} successfully`;
                    if (data.material_message) message += `. ${data.material_message}`; // Append material message from backend
                    showToast(message, 'success');
                    sendStatusNotificationEmail(currentPoNumber, status);
                    setTimeout(() => { window.location.reload(); }, 1800); // Increased timeout slightly
                } else { throw new Error(data.message || 'Unknown error updating status.'); }
            })
            .catch(error => {
                console.error("Update status fetch error:", error);
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => { closeRelevantStatusModals(); });
        }
        
        function sendStatusNotificationEmail(poNumber, newStatus) { // Your existing function
            fetch('/backend/send_status_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
                body: `po_number=${encodeURIComponent(poNumber)}&new_status=${encodeURIComponent(newStatus)}`
            })
            .then(response => response.json())
            .then(data => { console.log(data.success ? "Status notification email sent/queued." : "Status notification email failed: " + data.message); })
            .catch(error => { console.error("Error sending status notification email AJAX:", error); });
        }

        function closeStatusModal() { $('#statusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number'); }
        function closePendingStatusModal() { $('#pendingStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); $('#rawMaterialsContainer').html(''); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        // --- Material Display Helpers (Your existing functions, kept for reference if needed elsewhere) ---
        function displayFinishedProducts(productsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return false;
             let html = `<h3>Finished Products Status</h3>`;
             if (!productsData || Object.keys(productsData).length === 0) { html += '<p>No finished product information available.</p>'; container.html(html).append('<div id="raw-materials-section"></div>'); return false; }
             html += `<table class="materials-table"><thead><tr><th>Product</th><th>In Stock</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(productsData).forEach(product => {
                 const data = productsData[product];
                 const available = parseInt(data.available) || 0; const required = parseInt(data.required) || 0; const isSufficient = data.sufficient; const shortfall = data.shortfall || 0;
                 html += `<tr><td>${product}</td><td>${available}</td><td>${required}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'In Stock' : `Needs ${shortfall} (Mfg. ${data.canManufacture ? 'Possible' : 'Not Possible'})`}</td></tr>`;
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
             else if (Object.keys(prods).length === 0 && !response.needsManufacturing) { msg = 'Inventory details unclear.'; }
             $('#activeStatusBtn').prop('disabled', !canActivate);
             let statEl = cont.children('.materials-status');
             const cls = canActivate ? 'status-sufficient' : 'status-insufficient';
             if (statEl.length) statEl.removeClass('status-sufficient status-insufficient').addClass(cls).text(msg);
             else cont.append(`<p class="materials-status ${cls}">${msg}</p>`);
        }
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
                            <td class="status-cell"><div style="display: flex; align-items: center; justify-content: space-between;"><input type="checkbox" class="item-status-checkbox" data-index="${index}" onchange="updateRowStyle(this)" ${isCompletedByCheckbox ? 'checked' : ''}>${itemQuantity > 0 ? `<button class="expand-units-btn" onclick="toggleQuantityProgress(${index})"><i class="fas fa-chevron-down"></i></button>` : ''}</div>
                            ${itemQuantity > 0 ? `<div class="item-progress-bar-container"><div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress}%"></div><div class="item-progress-text" id="item-progress-text-${index}">${Math.round(unitProgress)}%</div></div><div class="item-contribution-text" id="contribution-text-${index}">Contributes: ${contributionToOverall.toFixed(1)}%</div>` : ''}
                            </td>`);
                        orderDetailsBody.append(mainRow);
                        if (itemQuantity > 0) {
                             const dividerRow = $('<tr>').addClass('units-divider').attr('id', `units-divider-${index}`).hide().html(`<td colspan="6" style="border: none; padding: 2px 0; background-color: #e9ecef;"></td>`);
                             orderDetailsBody.append(dividerRow);
                             for (let i = 0; i < itemQuantity; i++) { const isUnitCompleted = quantityProgressData[index] && quantityProgressData[index][i] === true; const unitRow = $('<tr>').addClass(`unit-row unit-for-item-${index}`).hide().toggleClass('completed', isUnitCompleted).html(`<td></td><td colspan="4">Unit ${i+1}</td><td><input type="checkbox" class="unit-status-checkbox" data-item-index="${index}" data-unit-index="${i}" onchange="updateUnitStatus(this)" ${isUnitCompleted ? 'checked' : ''}></td>`); orderDetailsBody.append(unitRow); }
                             const actionRow = $('<tr>').addClass(`unit-row unit-action-row unit-for-item-${index}`).hide().html(`<td colspan="6" style="text-align: right; padding: 10px;"><button onclick="selectAllUnits(${index}, ${itemQuantity})" class="btn btn-sm btn-outline-success">All</button><button onclick="deselectAllUnits(${index}, ${itemQuantity})" class="btn btn-sm btn-outline-secondary">None</button></td>`);
                             orderDetailsBody.append(actionRow);
                        }
                    });
                    overallProgress = calculatedOverallProgress; updateOverallProgressDisplay();
                    let totalAmount = currentOrderItems.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0); $('#orderTotalAmount').text(`Total: PHP ${totalAmount.toFixed(2)}`);
                    $('#overall-progress-info, .save-progress-btn').show(); $('#orderDetailsModal').css('display', 'flex');
                } else { showToast('Error fetching details: ' + data.message, 'error'); }
            })
            .catch(error => { showToast('Error fetching details: ' + error, 'error'); });
        }
        function viewOrderInfo(ordersJson, orderStatus) {
             try {
                 const orderDetails = JSON.parse(ordersJson); const body = $('#orderDetailsBody').empty(); $('#status-header-cell').hide(); $('#orderStatus').text(orderStatus); let total = 0;
                 orderDetails.forEach(p => { total += parseFloat(p.price) * parseInt(p.quantity); body.append(`<tr><td>${p.category||''}</td><td>${p.item_description}</td><td>${p.packaging||''}</td><td>${parseFloat(p.price).toFixed(2)}</td><td>${p.quantity}</td><td></td></tr>`); });
                 $('#orderTotalAmount').text(`Total: PHP ${total.toFixed(2)}`); $('#overall-progress-info, .save-progress-btn').hide(); $('#orderDetailsModal').css('display', 'flex');
             } catch (e) { showToast('Error displaying info', 'error'); }
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
        function updateOverallProgressDisplay() { const rounded = Math.round(overallProgress); $('#overall-progress-bar').css('width', `${rounded}%`); $('#overall-progress-text').text(`${rounded}%`); }
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
        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').hide();
            const finalProgress = updateOverallProgress();
            if (finalProgress === 100) {
                showToast('Progress reached 100%. Updating status to For Delivery...', 'info');
                updateOrderStatus('For Delivery', false, false);
            } else {
                fetch('/backend/update_order_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        po_number: currentPoNumber,
                        completed_items: completedItems,
                        quantity_progress: quantityProgressData,
                        overall_progress: finalProgress
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Progress updated successfully', 'success');
                        setTimeout(() => { window.location.reload(); }, 1000);
                    } else {
                        showToast('Error saving progress: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error saving progress: ' + error, 'error');
                });
            }
        }

        // --- Edit Delivery Date Functions (Using your structure) ---
        function openEditDateModal(poNumber, currentDate, orderDate) {
            currentPoNumber = poNumber;
            editingOrderDate = orderDate; // Your variable
            
            $('#edit_po_number').val(poNumber);
            $('#current_delivery_date').val(currentDate); // Assuming this ID is in your HTML for this modal
            $('#edit_order_date_display_modal').text(orderDate ? new Date(orderDate+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'N/A');


            if ($.datepicker) {
                $("#new_delivery_date_input_edit").datepicker("destroy"); // Use the corrected ID
                let minDateForPicker = new Date(orderDate+'T00:00:00');
                minDateForPicker.setDate(minDateForPicker.getDate() + 5);
                $("#new_delivery_date_input_edit").datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: minDateForPicker, 
                    beforeShowDay: function(date) {
                        var day = date.getDay(); // 0 (Sun) to 6 (Sat)
                        return [(day == 1 || day == 3 || day == 5), '']; // Monday, Wednesday, Friday
                    }
                    // Removed onSelect to avoid double validation, using onsubmit instead
                });
            }
            $('#editDateModal').css('display', 'flex'); // Your display method
        }
        function closeEditDateModal() { $('#editDateModal').css('display', 'none'); }
        function validateEditDateForm(event) { // Added event parameter
            const newDateStr = $('#new_delivery_date_input_edit').val(); // Use corrected ID
            if (!newDateStr) { showToast('New delivery date cannot be empty.', 'error'); event.preventDefault(); return false; }
            if (!isValidDeliveryDayJS(newDateStr)) { showToast('New delivery date must be a Monday, Wednesday, or Friday.', 'error'); event.preventDefault(); return false; }
            if (!isValidDeliveryGapJS(editingOrderDate, newDateStr, 5)) { showToast('New delivery date must be at least 5 days after the order date.', 'error', 5000); event.preventDefault(); return false; }
            return true; // Allow form submission
        }


        // --- Special Instructions Modal (Using your structure) ---
        function viewSpecialInstructions(poNumber, instructions) { $('#instructionsPoNumber').text('PO: ' + poNumber); const content = $('#instructionsContent'); if (instructions && instructions.trim() !== "") { content.html(instructions.replace(/\\n/g, '<br>')).removeClass('empty'); } else { content.html('No special instructions provided for this order.').addClass('empty'); } $('#specialInstructionsModal').show(); }
        function closeSpecialInstructions() { $('#specialInstructionsModal').hide(); }

        // --- Add New Order Form Functions (Using your structure, with unique IDs for Add Order Form) ---
        function initializeDeliveryDatePickerAdd() { // Renamed
            if ($.datepicker) { 
                $("#delivery_date_add").datepicker("destroy"); 
                const orderDateVal = $("#order_date_add").val();
                const minD = getNextAvailableDeliveryDateJS(orderDateVal, 5); // Use JS helper
                $("#delivery_date_add").datepicker({ 
                    dateFormat: 'yy-mm-dd', 
                    minDate: minD,
                    beforeShowDay: function(date) { var day = date.getDay(); return [(day == 1 || day == 3 || day == 5), '']; }
                }); 
                $("#delivery_date_add").datepicker("setDate", minD); // Set default
            }
        }
        function openAddOrderForm() { 
            $('#addOrderForm')[0].reset(); 
            cartItems = []; // Use global cartItems, not currentCartItems to avoid conflict
            updateOrderSummaryAdd(); // Call new summary update function
            updateCartItemCountNav(); // Call new cart count update
            
            const today = new Date(); 
            const fmtDate = `${today.getFullYear()}-${(today.getMonth()+1).toString().padStart(2,'0')}-${today.getDate().toString().padStart(2,'0')}`;
            $('#order_date_add').val(fmtDate); // Use new ID
            
            $('#order_type_selection_add').val("").trigger('change'); // Reset and trigger change for toggleOrderFormFieldsAdd
            $('#onlineSpecificInputsAdd, #walkInSpecificInputsAdd, #commonOrderFieldsAdd, #confirmAddOrderBtnAdd').hide();
            $('#delivery_date_form_group_add, #delivery_address_type_form_group_add, #company_address_container_div_add, #custom_address_container_div_add').hide();
            $('#custom_address_label_add').text('Custom Delivery Address:'); // Reset label

            $('#addOrderOverlay').css('display', 'flex'); 
        }
        function closeAddOrderForm() { $('#addOrderOverlay').hide(); }
        function toggleOrderFormFieldsAdd(){ // Renamed
            const selectedOrderType=$('#order_type_selection_add').val();
            $('#order_type_hidden_for_submit_add').val(selectedOrderType);
            $('#onlineSpecificInputsAdd, #walkInSpecificInputsAdd, #commonOrderFieldsAdd, #confirmAddOrderBtnAdd').hide();
            $('#delivery_date_form_group_add, #delivery_address_type_form_group_add, #company_address_container_div_add, #custom_address_container_div_add').hide();
            $('#custom_address_label_add').text('Custom Delivery Address:'); 
            $('#username_online_select_add').val(''); $('#online_company_display_add').val(''); $('#walk_in_name_company_input_add').val('');
            $('#delivery_date_add').val(''); $('#custom_address_input_field_add').val(''); $('#delivery_address_for_submit_add').val(''); $('#po_number_for_submit_add').val('');

            if(selectedOrderType==="Online"){$('#onlineSpecificInputsAdd, #commonOrderFieldsAdd, #confirmAddOrderBtnAdd').show();$('#delivery_date_form_group_add, #delivery_address_type_form_group_add').show();$('#company_address_option_for_delivery_add').show();initializeDeliveryDatePickerAdd();}
            else if(selectedOrderType==="Walk In"){$('#walkInSpecificInputsAdd, #commonOrderFieldsAdd, #confirmAddOrderBtnAdd').show();$('#custom_address_label_add').text('Address (for Walk-In):');$('#company_address_option_for_delivery_add').hide();fetchAndSetWalkInPONumberAdd();}
            
            if(selectedOrderType){ const today=new Date(); const formattedToday=`${today.getFullYear()}-${(today.getMonth()+1).toString().padStart(2,'0')}-${today.getDate().toString().padStart(2,'0')}`; $('#order_date_add').val(formattedToday); }
            cartItems=[]; updateOrderSummaryAdd(); updateCartItemCountNav(); toggleDeliveryAddressOptionsAdd();
        }
        function fetchAndSetWalkInPONumberAdd(){ // Renamed
            $('#po_number_for_submit_add').val('Generating...');
            fetch('/backend/get_next_walkin_po.php') // Assuming this backend endpoint exists and is correct
            .then(response=>response.json())
            .then(data=>{ if(data.success&&typeof data.next_sequence_number!=='undefined'){ const po=`WI-${String(data.next_sequence_number).padStart(3,'0')}`; $('#po_number_for_submit_add').val(po); } else { showToast('Could not generate Walk-In PO: '+(data.message||'Unknown error'),'error'); $('#po_number_for_submit_add').val(''); } })
            .catch(error=>{ showToast('Error generating Walk-In PO: '+error.message,'error'); $('#po_number_for_submit_add').val(''); });
        }
        function handleOnlineUserChangeAdd(){ // Renamed
            const selectedUserOption=$('#username_online_select_add option:selected'); const username=selectedUserOption.val();
            if(username){ const companyName=selectedUserOption.data('company')||'N/A'; const companyAddr=selectedUserOption.data('company-address')||'';
                $('#online_company_display_add').val(companyName); $('#company_name_final_for_submit_add').val(companyName);
                $('#company_address_display_field_add').val(companyAddr); // Assuming this ID exists for display
                if($('#delivery_address_type_select_add').val()==='company')$('#delivery_address_for_submit_add').val(companyAddr);
                $('#po_number_for_submit_add').val(generateOnlinePONumberAdd(username)); // Use new PO gen
            } else { $('#online_company_display_add, #company_name_final_for_submit_add, #company_address_display_field_add, #po_number_for_submit_add').val(''); if($('#delivery_address_type_select_add').val()==='company')$('#delivery_address_for_submit_add').val(''); }
        }
        function generateOnlinePONumberAdd(username){ // Renamed
            if(!username)return ''; const d=new Date(); const userPart=username.substring(0,Math.min(username.length,4)).toUpperCase().replace(/[^A-Z0-9]/g,'');
            // Using a simpler random part for PO for now, backend should ensure uniqueness if this is an issue
            const po=`PO-${userPart||'CLNT'}-${d.getFullYear().toString().slice(-2)}${(d.getMonth()+1).toString().padStart(2,'0')}${d.getDate().toString().padStart(2,'0')}-${Math.floor(1000+Math.random()*9000)}`;
            return po;
        }
        function toggleDeliveryAddressOptionsAdd(){ // Renamed
            const deliveryTypeSelected=$('#delivery_address_type_select_add').val(); const currentOrderType=$('#order_type_selection_add').val();
            if(currentOrderType==="Walk In"){$('#company_address_container_div_add').hide();$('#custom_address_container_div_add').show();$('#delivery_address_for_submit_add').val($('#custom_address_input_field_add').val().trim());return;}
            if(deliveryTypeSelected==='company'){$('#company_address_container_div_add').show();$('#custom_address_container_div_add').hide();$('#delivery_address_for_submit_add').val($('#company_address_display_field_add').val().trim());}
            else{$('#company_address_container_div_add').hide();$('#custom_address_container_div_add').show();$('#delivery_address_for_submit_add').val($('#custom_address_input_field_add').val().trim());}
        }
        $('#custom_address_input_field_add').on('input',function(){ // Bind to new ID
            const currentOrderType=$('#order_type_selection_add').val();
            if(currentOrderType==="Walk In"||$('#delivery_address_type_select_add').val()==='custom'){$('#delivery_address_for_submit_add').val($(this).val().trim());}
        });
        function prepareOrderDataForSubmitAdd(){ // Renamed
            const selectedOrderType=$('#order_type_selection_add').val(); $('#order_type_hidden_for_submit_add').val(selectedOrderType);
            if(!selectedOrderType){showToast('Please select an Order Type.','error');return false;}
            let finalUsernameForDB='';let finalCompanyForDB='';
            if(selectedOrderType==="Online"){ finalUsernameForDB=$('#username_online_select_add').val(); if(!finalUsernameForDB){showToast('Please select a Client Username for an Online order.','error');return false;} finalCompanyForDB=$('#online_company_display_add').val(); }
            else if(selectedOrderType==="Walk In"){ finalUsernameForDB='Walk-In Customer'; finalCompanyForDB=$('#walk_in_name_company_input_add').val().trim(); if(!finalCompanyForDB){showToast('Please enter Full Name / Company Name for a Walk-In order.','error');return false;} }
            $('#company_name_final_for_submit_add').val(finalCompanyForDB);
            if(cartItems.length===0){showToast('Order cannot be empty. Please select products.','error');return false;}
            $('#orders_json_for_submit_add').val(JSON.stringify(cartItems));
            let currentTotalAmount=0; cartItems.forEach(item=>{currentTotalAmount+=(parseFloat(item.price)||0)*(parseInt(item.quantity)||0);});
            $('#total_amount_for_submit_add').val(currentTotalAmount.toFixed(2));
            const deliveryAddress=$('#delivery_address_for_submit_add').val().trim(); if(!deliveryAddress){const addressLabel=selectedOrderType==="Walk In"?"Address (for Walk-In)":"Custom Delivery Address";showToast(`Please provide a ${addressLabel}.`,'error');return false;}
            if(!$('#po_number_for_submit_add').val()||($('#po_number_for_submit_add').val()==='Generating...')){showToast('PO Number is not yet generated or is invalid.','error');return false;}
            return true;
        }
        function confirmAddOrder(){ if(prepareOrderDataForSubmitAdd()){ $('#addConfirmationModal .confirmation-message').text('Are you sure you want to submit this order? Please double-check all details.'); $('#addConfirmationModal').show(); } }
        function closeAddConfirmation(){ $('#addConfirmationModal').hide(); }
        // MODIFIED submitAddOrder to handle recommend_pending
        function submitAddOrder(){ 
            $('#addConfirmationModal').hide(); 
            if(!prepareOrderDataForSubmitAdd())return; // Use new validation function
            const formElement=document.getElementById('addOrderForm'); 
            const formData=new FormData(formElement); 
            const orderType=formData.get('order_type'); // This comes from order_type_hidden_for_submit_add
            // Ensure correct fields are sent based on order type
            if(orderType==="Walk In"){ formData.delete('username_online'); formData.delete('delivery_date'); }
            else if (orderType === "Online") { formData.delete('walk_in_name_company_input'); }

            fetch('/backend/add_order.php',{method:'POST',body:formData})
            .then(response=>response.json())
            .then(data=>{
                if(data.success){
                    showToast('Order successfully added! PO Number: '+(data.po_number||formData.get('po_number')),'success',4000);
                    const clientUsernameForEmail=orderType==="Online"?formData.get('username_online'):'Walk-In Client'; // Use correct username field
                    sendNewOrderEmail(clientUsernameForEmail,data.po_number||formData.get('po_number'));
                    
                    if(data.recommend_pending){ // Check for the new flag
                        showToast('Order created as "Pending" due to insufficient raw materials. Please review.','warning',7000);
                        setTimeout(()=>{window.location.href='orders.php';},3500); // Longer delay for warning
                    } else {
                        setTimeout(()=>{window.location.href='orders.php';},2000);
                    }
                }else{
                    showToast('Error adding order: '+(data.message||'An unknown server error occurred.'),'error',5000);
                    console.error("Add order error response:",data);
                }
            })
            .catch(error=>{
                showToast('Network or server error while adding order: '+error.message,'error',5000);
                console.error("Full error details:",error);
            });
        }
        function sendNewOrderEmail(usernameForEmail,poNumberForEmail){ // Your existing function
            fetch('/backend/send_new_order_notification.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded',}, body:`username=${encodeURIComponent(usernameForEmail)}&po_number=${encodeURIComponent(poNumberForEmail)}`})
            .then(response=>response.json())
            .then(data=>{ if(data.success)console.log("New order notification email successfully sent/queued for:",usernameForEmail,poNumberForEmail); else console.warn("Failed to send new order notification email:",data.message); })
            .catch(error=>console.error("Error sending new order notification email (AJAX):",error));
        }
        
        // --- Inventory Overlay and Cart Functions (Using your structure, with unique IDs for Add Order Form) ---
        function openInventoryOverlay(){ $('#inventoryOverlay').css('display', 'flex'); const inventoryBody=$('.inventory').html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory...</td></tr>'); fetch('/backend/get_inventory.php').then(r=>r.json()).then(d=>{if(d.success){populateInventoryTable(d.inventory);populateCategoryFilter(d.categories);}else{showToast('Error: '+d.message,'error');inventoryBody.html('<tr><td colspan="6" style="text-align:center;color:red;padding:20px;">Could not load inventory.</td></tr>');}}).catch(e=>{showToast('Network Error: '+e,'error');inventoryBody.html('<tr><td colspan="6" style="text-align:center;color:red;padding:20px;">Network error loading inventory.</td></tr>');}); }
        function populateInventoryTable(inventoryItems){ const inventoryBody=$('.inventory').empty(); if(!inventoryItems||inventoryItems.length===0){inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;color:#6c757d;">No inventory items found.</td></tr>');return;} inventoryItems.forEach(item=>{const tr=$('<tr>').attr('data-category',item.category.toLowerCase()).attr('data-product-name',item.item_description.toLowerCase());tr.html(`<td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:center;">PHP ${parseFloat(item.price).toFixed(2)}</td><td><input type="number" class="inventory-quantity" value="1" min="1" max="100"></td><td><button class="add-to-cart-btn" onclick="addToCartFromInventory(this,'${item.product_id}','${item.category}','${item.item_description}','${item.packaging}','${item.price}')"><i class="fas fa-cart-plus"></i> Add</button></td>`);inventoryBody.append(tr);}); }
        function populateCategoryFilter(categories){ const categorySelect=$('#inventoryFilter'); categorySelect.find('option:not(:first-child)').remove(); if(!categories||categories.length===0)return; categories.forEach(cat=>categorySelect.append(`<option value="${cat.toLowerCase()}">${cat}</option>`)); }
        function filterInventory(){ const selectedCategory=$('#inventoryFilter').val(); const searchTerm=$('#inventorySearch').val().toLowerCase().trim(); $('.inventory tr').each(function(){const row=$(this);const categoryMatch=selectedCategory==='all'||row.data('category')===selectedCategory;const nameMatch=row.data('product-name').includes(searchTerm);row.toggle(categoryMatch&&nameMatch);}); }
        $('#inventorySearch, #inventoryFilter').off('input change', filterInventory).on('input change', filterInventory);
        function closeInventoryOverlay(){ $('#inventoryOverlay').hide(); }
        function addToCartFromInventory(button,productId,category,itemDesc,packaging,price){ const quantityInput=$(button).closest('tr').find('.inventory-quantity'); let quantity=parseInt(quantityInput.val()); if(isNaN(quantity)||quantity<1){showToast('Please enter a valid quantity (min 1).','error');quantityInput.val(1);return;} if(quantity>100){showToast('Maximum quantity per item is 100.','error');quantity=100;quantityInput.val(100);} const existingItem=cartItems.find(item=>item.product_id===productId&&item.packaging===packaging); if(existingItem){existingItem.quantity+=quantity;if(existingItem.quantity>100){showToast('Total quantity for this item exceeds 100. Adjusted to 100.','warning');existingItem.quantity=100;}}else{cartItems.push({product_id:productId,category:category,item_description:itemDesc,packaging:packaging,price:parseFloat(price),quantity:quantity});} showToast(`${quantity} x ${itemDesc} (${packaging}) added to order.`,'success'); updateCartItemCountNav(); quantityInput.val(1); }
        function updateOrderSummaryAdd(){ // Renamed
            const summaryTableBody=$('#summaryBodyAdd').empty(); let currentOrderTotal=0;
            if(cartItems.length===0){summaryTableBody.html('<tr><td colspan="6" style="text-align:center;padding:15px;color:#6c757d;">No products selected yet. Click "Select Products".</td></tr>');$('#orderSummaryItemCountAdd').text('(0 items)');}
            else{cartItems.forEach((item,index)=>{currentOrderTotal+=(item.price||0)*(item.quantity||0);summaryTableBody.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:right;">PHP ${parseFloat(item.price||0).toFixed(2)}</td><td><input type="number" class="summary-quantity cart-quantity" value="${item.quantity}" min="1" max="100" data-cart-item-index="${index}" onchange="updateSummaryItemQuantityAdd(this)"></td><td style="text-align:center;"><button class="remove-item-btn" onclick="removeSummaryItemAdd(${index})"><i class="fas fa-trash-alt"></i></button></td></tr>`);});$('#orderSummaryItemCountAdd').text(`(${cartItems.length} item${cartItems.length===1?'':'s'})`);}
            $('.summary-total-amount-add').text(`PHP ${currentOrderTotal.toFixed(2)}`);$('#total_amount_for_submit_add').val(currentOrderTotal.toFixed(2));
        }
        function updateSummaryItemQuantityAdd(inputElement){ // Renamed
            const itemIndexInCart=parseInt($(inputElement).data('cart-item-index'));let newQuantity=parseInt($(inputElement).val());
            if(isNaN(newQuantity)||newQuantity<1){showToast('Quantity must be at least 1.','error');$(inputElement).val(cartItems[itemIndexInCart].quantity);return;}
            if(newQuantity>100){showToast('Maximum quantity per item is 100.','error');newQuantity=100;$(inputElement).val(100);}
            cartItems[itemIndexInCart].quantity=newQuantity;updateOrderSummaryAdd();updateCartItemCountNav();updateCartDisplayAdd();
        }
        function removeSummaryItemAdd(itemIndexInCart){ 
            if(itemIndexInCart>=0&&itemIndexInCart<cartItems.length){const removedItem=cartItems.splice(itemIndexInCart,1)[0];showToast(`Removed ${removedItem.item_description} from order.`,'info');updateOrderSummaryAdd();updateCartItemCountNav();updateCartDisplayAdd();}else{showToast('Error removing item. Please try again.','error');}
        }
        function updateCartItemCountNav(){ const count=cartItems.length; $('#cartItemCountNav').text(count); $('#orderSummaryItemCountAdd').text(`(${count} item${count===1?'':'s'})`); if(count===0)$('#orderSummaryItemCountAdd').text('(0 items)');}
        window.openCartModal=function(){ $('#cartModal').css('display', 'flex'); updateCartDisplayAdd(); } // Use new display function
        function closeCartModal(){ $('#cartModal').hide(); }
        function saveCartChangesAndClose(){ updateOrderSummaryAdd(); closeCartModal(); closeInventoryOverlay(); showToast('Selected items confirmed for the order.','success'); }        function updateCartDisplayAdd(){ // Renamed
            const cartTableBody=$('.cart').empty(); const noProductsRow=$('.no-products-in-cart-row'); const cartTotalElement=$('.total-amount-cart'); let currentCartModalTotal=0;
            if(cartItems.length===0){if(noProductsRow.length)noProductsRow.show();else cartTableBody.html('<tr class="no-products-in-cart-row"><td colspan="6" style="text-align:center;padding:20px;color:#6c757d;">Your cart is empty. Add products from the inventory.</td></tr>'); cartTotalElement.text('PHP 0.00');}
            else{if(noProductsRow.length)noProductsRow.hide();cartItems.forEach((item,index)=>{currentCartModalTotal+=(item.price||0)*(item.quantity||0);cartTableBody.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:right;">PHP ${parseFloat(item.price||0).toFixed(2)}</td><td><input type="number" class="cart-quantity" value="${item.quantity}" min="1" max="100" data-cart-item-index="${index}" onchange="updateCartModalItemQuantityAdd(this)"></td><td style="text-align:center;"><button class="remove-item-btn" onclick="removeCartModalItemAdd(${index})"><i class="fas fa-trash-alt"></i></button></td></tr>`);});cartTotalElement.text(`PHP ${currentCartModalTotal.toFixed(2)}`);}
        }
        function updateCartModalItemQuantityAdd(inputElement){ // Renamed
            const itemIndexInCart=parseInt($(inputElement).data('cart-item-index'));let newQuantity=parseInt($(inputElement).val());
            if(isNaN(newQuantity)||newQuantity<1){showToast('Quantity must be at least 1.','error');$(inputElement).val(cartItems[itemIndexInCart].quantity);return;}
            if(newQuantity>100){showToast('Maximum quantity is 100.','error');newQuantity=100;$(inputElement).val(100);}
            cartItems[itemIndexInCart].quantity=newQuantity;updateCartDisplayAdd();updateOrderSummaryAdd();
        }
        function removeCartModalItemAdd(itemIndexInCart){ // Renamed
            if(itemIndexInCart>=0&&itemIndexInCart<cartItems.length){const removedItem=cartItems.splice(itemIndexInCart,1)[0];showToast(`Removed ${removedItem.item_description} from selection. Please confirm changes.`,'info');updateCartDisplayAdd();updateOrderSummaryAdd();}else{showToast('Error removing item.','error');}
        }
        
        // --- Document Ready (Using your structure, adapted for new Add Order Form IDs) ---
        $(document).ready(function() {
            setTimeout(function() { $(".alert").fadeOut("slow"); }, 3500);
            
            $("#searchInput").on("input", function() { 
                const search = $(this).val().toLowerCase().trim(); 
                $(".orders-table tbody tr").each(function() { $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1); }); 
            });
            $(".search-btn").on("click", () => $("#searchInput").trigger("input")); // Your search button
            
            // Initialize Add Order Form (using new function names)
            $('#order_type_selection_add').val("").trigger('change'); // Reset and trigger for toggleOrderFormFieldsAdd
            $('#onlineSpecificInputsAdd,#walkInSpecificInputsAdd,#commonOrderFieldsAdd,#delivery_date_form_group_add,#delivery_address_type_form_group_add,#company_address_container_div_add,#custom_address_container_div_add,#confirmAddOrderBtnAdd').hide();
            
            // Universal modal closer (your existing logic, adapted slightly for clarity)
            $(document).on('click', function(event) {
                const $target = $(event.target);
                if ($target.hasClass('overlay') || $target.hasClass('modal') || $target.hasClass('instructions-modal') || $target.hasClass('confirmation-modal')) {
                    if (event.target === event.currentTarget) { 
                        if ($target.is('#addOrderOverlay:visible')) closeAddOrderForm();
                        else if ($target.is('#inventoryOverlay:visible')) closeInventoryOverlay();
                        else if ($target.is('#cartModal:visible')) closeCartModal();
                        else if ($target.is('#orderDetailsModal:visible')) closeOrderDetailsModal();
                        else if ($target.is('#pdfPreview:visible')) closePDFPreview();
                        else if ($target.is('#statusModal:visible')) closeStatusModal();
                        else if ($target.is('#pendingStatusModal:visible')) closePendingStatusModal();
                        else if ($target.is('#rejectedStatusModal:visible')) closeRejectedStatusModal();
                        else if ($target.is('#editDateModal:visible')) closeEditDateModal();
                        else if ($target.is('#specialInstructionsModal:visible')) closeSpecialInstructions();
                        else if ($target.hasClass('confirmation-modal')) $target.hide();
                    }
                }
            });

            // Initial call to your email status checker (if you keep this functionality)
            // checkEmailSendingStatus(); 
            // setInterval(checkEmailSendingStatus, 180000);
        });

        // --- Your Email Checking Script (kept as is, if you use it) ---
        function checkEmailSendingStatus() {
            fetch('/backend/check_email_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.unsent_notifications > 0) {
                   // showToast(`${data.unsent_notifications} email notifications are pending to be sent.`, 'info');
                }
            })
            .catch(error => {
                console.error("Error checking email status:", error);
            });
        }
        
        // Check email status on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check email status every 3 minutes
            checkEmailSendingStatus();
            setInterval(checkEmailSendingStatus, 180000);
        });
    </script>
</body>
</html>