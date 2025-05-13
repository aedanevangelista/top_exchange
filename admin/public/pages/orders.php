<?php
// Based on commit: dfe8bc989ea063004ce0bef65815a6d013e7d9e8
// (Original header comment: // Based on commit: e801e509bf62bd6fdb304d4fb4a20d6b43b2ecb6)
// Modifications: Restricted delivery dates to Mon/Wed/Fri, 5-day min, email notifications, delivery date updates
// ADDED Console logs to viewOrderDetails and viewOrderInfo for debugging

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

// Process delivery date update if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_date']) && isset($_POST['po_number']) && isset($_POST['new_delivery_date'])) {
    $po_number_update = $_POST['po_number']; // Renamed to avoid conflict
    $new_delivery_date_update = $_POST['new_delivery_date']; // Renamed
    
    $day_of_week_update = date('N', strtotime($new_delivery_date_update));
    $is_valid_day_update = ($day_of_week_update == 1 || $day_of_week_update == 3 || $day_of_week_update == 5);
    
    $stmt_order_date = $conn->prepare("SELECT order_date, username FROM orders WHERE po_number = ?");
    $stmt_order_date->bind_param("s", $po_number_update);
    $stmt_order_date->execute();
    $result_order_date = $stmt_order_date->get_result();
    $order_for_date_update = $result_order_date->fetch_assoc(); // Renamed
    $stmt_order_date->close();
    
    $order_date_dt_update = new DateTime($order_for_date_update['order_date']);
    $delivery_date_dt_update = new DateTime($new_delivery_date_update);
    $days_difference_update = $delivery_date_dt_update->diff($order_date_dt_update)->days;
    
    $is_valid_days_gap_update = ($days_difference_update >= 5);
    
    if ($is_valid_day_update && $is_valid_days_gap_update) {
        $stmt_update_delivery = $conn->prepare("UPDATE orders SET delivery_date = ? WHERE po_number = ?");
        $stmt_update_delivery->bind_param("ss", $new_delivery_date_update, $po_number_update);
        if ($stmt_update_delivery->execute()) {
            $username_for_email = $order_for_date_update['username'];
            
            $stmt_user_email = $conn->prepare("SELECT email FROM clients_accounts WHERE username = ?");
            $stmt_user_email->bind_param("s", $username_for_email);
            $stmt_user_email->execute();
            $result_user_email = $stmt_user_email->get_result();
            $user_data_for_email = $result_user_email->fetch_assoc();
            $stmt_user_email->close();
            
            if ($user_data_for_email && !empty($user_data_for_email['email'])) {
                $user_email_address = $user_data_for_email['email'];
                
                $email_subject_update = "Top Exchange Food Corp: Delivery Date Changed";
                $email_message_update = "Dear $username_for_email,\n\n";
                $email_message_update .= "The delivery date for your order (PO: $po_number_update) has been updated.\n";
                $email_message_update .= "New Delivery Date: " . date('F j, Y', strtotime($new_delivery_date_update)) . "\n\n";
                $email_message_update .= "If you have any questions regarding this change, please contact us.\n\n";
                $email_message_update .= "Thank you,\nTop Exchange Food Corp";
                $email_headers_update = "From: no-reply@topexchange.com";
                mail($user_email_address, $email_subject_update, $email_message_update, $email_headers_update);
            }
            
            $_SESSION['message'] = "Delivery date updated successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating delivery date.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_update_delivery->close();
    } else {
        if (!$is_valid_day_update) {
            $_SESSION['message'] = "Delivery date must be Monday, Wednesday, or Friday.";
        } else {
            $_SESSION['message'] = "Delivery date must be at least 5 days after the order date.";
        }
        $_SESSION['message_type'] = "error";
    }
    header("Location: orders.php");
    exit();
}

$clients_arr = [];
$clients_with_company_address_map = [];
$clients_with_company_map = [];
$clients_with_email_map = [];
$stmt_clients_fetch = $conn->prepare("SELECT username, company_address, company, email FROM clients_accounts WHERE status = 'active'");
if ($stmt_clients_fetch === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt_clients_fetch->execute();
$result_clients_fetch = $stmt_clients_fetch->get_result();
while ($row_clients_item = $result_clients_fetch->fetch_assoc()) {
    $clients_arr[] = $row_clients_item['username'];
    $clients_with_company_address_map[$row_clients_item['username']] = $row_clients_item['company_address'];
    $clients_with_company_map[$row_clients_item['username']] = $row_clients_item['company'];
    $clients_with_email_map[$row_clients_item['username']] = $row_clients_item['email'];
}
$stmt_clients_fetch->close();

$orders_list = []; // Renamed
$sql_fetch_orders = "SELECT o.id, o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.progress,
        o.company, o.special_instructions
        FROM orders o
        WHERE o.status IN ('Pending', 'Rejected')
        OR (o.status = 'Active' AND o.progress < 100)"; 

$orderByClause_orders = $sort_column; // Renamed
if (in_array($sort_column, ['id', 'po_number', 'username', 'order_date', 'delivery_date', 'progress', 'total_amount', 'status'])) {
    $orderByClause_orders = 'o.' . $sort_column;
}
$sql_fetch_orders .= " ORDER BY {$orderByClause_orders} {$sort_direction}";

$stmt_all_orders = $conn->prepare($sql_fetch_orders); // Renamed
if ($stmt_all_orders === false) {
     die('Prepare failed after correction: ' . htmlspecialchars($conn->error) . ' - SQL: ' . $sql_fetch_orders);
}
$stmt_all_orders->execute();
$result_all_orders = $stmt_all_orders->get_result();
if ($result_all_orders && $result_all_orders->num_rows > 0) {
    while ($row_order_item = $result_all_orders->fetch_assoc()) {
        $orders_list[] = $row_order_item;
    }
}
$stmt_all_orders->close();

function getSortUrl($column, $currentColumn, $currentDirection) {
    $newDirection = ($column === $currentColumn && $currentDirection === 'ASC') ? 'DESC' : 'ASC';
    return "?sort=" . urlencode($column) . "&direction=" . urlencode($newDirection);
}

function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column === 'id') return '';
    if ($column !== $currentColumn) return '<i class="fas fa-sort"></i>';
    return ($currentDirection === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}

function getNextAvailableDeliveryDatePHPFunc($minDaysAfter = 5) { // Renamed
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
    $startDate->modify("+{$daysToAdd} days");
    return $startDate->format('Y-m-d');
}

function isValidDeliveryDayPHP($date_str) { // Renamed
    $dayOfWeek = date('N', strtotime($date_str));
    return ($dayOfWeek == 1 || $dayOfWeek == 3 || $dayOfWeek == 5);
}

function isValidDeliveryGapPHP($orderDate_str, $deliveryDate_str) { // Renamed
    $orderDateTime = new DateTime($orderDate_str);
    $deliveryDateTime = new DateTime($deliveryDate_str);
    $days = $deliveryDateTime->diff($orderDateTime)->days;
    return ($days >= 5);
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
        /* --- Styles exactly as provided in commit dfe8bc9... --- */
        .order-summary { margin-top: 20px; margin-bottom: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table tbody { display: block; max-height: 250px; overflow-y: auto; }
        .summary-table thead, .summary-table tbody tr { display: table; width: 100%; }
        .summary-table thead { width: calc(100% - 17px); }
        .summary-table th, .summary-table td { padding: 8px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid #ddd; }
        .summary-table th:nth-child(1), .summary-table td:nth-child(1) { width: 20%; }
        .summary-table th:nth-child(2), .summary-table td:nth-child(2) { width: 30%; }
        .summary-table th:nth-child(3), .summary-table td:nth-child(3) { width: 15%; }
        .summary-table th:nth-child(4), .summary-table td:nth-child(4) { width: 15%; }
        .summary-table th:nth-child(5), .summary-table td:nth-child(5) { width: 10%; }
        .summary-table th:nth-child(6), .summary-table td:nth-child(6) { width: 10%; text-align: center; }
        .summary-total { margin-top: 10px; text-align: right; font-weight: bold; border-top: 1px solid #ddd; padding-top: 10px; }
        .summary-quantity { width: 80px; max-width: 100%; text-align: center; }
        .download-btn { padding: 6px 12px; background-color: #17a2b8; color: white; border: none; border-radius: 40px; cursor: pointer; font-size: 12px; margin-left: 5px; }
        .download-btn:hover { background-color: #138496; }
        .download-btn i { margin-right: 5px; }
        .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; }
        .po-header { text-align: center; margin-bottom: 30px; }
        .po-company { font-size: 22px; font-weight: bold; margin-bottom: 10px; }
        .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
        .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .po-left, .po-right { width: 48%; }
        .po-detail-row { margin-bottom: 10px; }
        .po-detail-label { font-weight: bold; display: inline-block; width: 120px; }
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .po-table th, .po-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .po-table th { background-color: #f2f2f2; }
        .po-total { text-align: right; font-weight: bold; font-size: 14px; margin-bottom: 30px; }
        .po-signature { display: flex; justify-content: space-between; margin-top: 50px; }
        .po-signature-block { width: 40%; text-align: center; }
        .po-signature-line { border-bottom: 1px solid #000; margin-bottom: 10px; padding-top: 40px; }
        #pdfPreview { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 1000; overflow: auto; }
        .pdf-container { background-color: white; width: 80%; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.5); position: relative; }
        .close-pdf { position: absolute; top: 10px; right: 10px; font-size: 18px; background: none; border: none; cursor: pointer; color: #333; }
        .pdf-actions { text-align: center; margin-top: 20px; }
        .pdf-actions button { padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .instructions-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.7); }
        .instructions-modal-content { background-color: #ffffff; margin: auto; padding: 0; border-radius: 8px; width: 60%; max-width: 600px; position: relative; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalFadeIn 0.3s ease-in-out; max-height: 80vh; display: flex; flex-direction: column; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .instructions-header { background-color: #2980b9; color: white; padding: 15px 20px; position: relative; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .instructions-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
        .instructions-po-number { font-size: 12px; margin-top: 5px; opacity: 0.9; }
        .instructions-body { padding: 20px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; flex-grow: 1; overflow-y: auto; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 40px 20px; }
        .instructions-footer { padding: 15px 20px; text-align: right; background-color: #ffffff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #eee; }
        .close-instructions-btn { background-color: #2980b9; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; }
        .close-instructions-btn:hover { background-color: #2471a3; }
        .instructions-btn { padding: 6px 12px; background-color: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; min-width: 60px; text-align: center; transition: background-color 0.2s; }
        .instructions-btn:hover { background-color: #2471a3; }
        .no-instructions { color: #6c757d; font-style: italic; }
        #contentToDownload { font-size: 14px; }
        #contentToDownload .po-table { font-size: 12px; }
        #contentToDownload .po-title { font-size: 16px; }
        #contentToDownload .po-company { font-size: 20px; }
        #contentToDownload .po-total { font-size: 12px; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; text-align: center; min-width: 80px; }
        .status-active { background-color: #d1e7ff; color: #084298; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-rejected { background-color: #f8d7da; color: #842029; }
        .status-delivery { background-color: #e2e3e5; color: #383d41; }
        .status-completed { background-color: #d1e7dd; color: #0f5132; }
        .btn-info { font-size: 10px; opacity: 0.8; margin-top: 3px; }
        .raw-materials-container { overflow: visible; margin-bottom: 15px; }
        .raw-materials-container h3 { margin-top: 0; margin-bottom: 10px; color: #333; font-size: 16px; }
        .materials-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .materials-table tbody { display: block; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; }
        .materials-table thead, .materials-table tbody tr { display: table; width: 100%; table-layout: fixed; }
        .materials-table th, .materials-table td { padding: 6px; text-align: left; border: 1px solid #ddd; font-size: 13px; }
        .materials-table thead { background-color: #f2f2f2; display: table; width: calc(100% - 17px); table-layout: fixed; }
        .materials-table th { background-color: #f2f2f2; }
        .material-sufficient { color: #28a745; }
        .material-insufficient { color: #dc3545; }
        .materials-status { padding: 8px; border-radius: 4px; font-weight: bold; font-size: 14px; margin-top: 10px; }
        .status-sufficient { background-color: #d4edda; color: #155724; }
        .status-insufficient { background-color: #f8d7da; color: #721c24; }
        .active-progress { background-color: #d1e7ff; color: #084298; }
        .pending-progress, .rejected-progress { background-color: #f8d7da; color: #842029; }
        .order-details-footer { display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; }
        .total-amount { font-weight: bold; font-size: 16px; padding: 5px 10px; background-color: #f8f9fa; border-radius: 4px; }
        .search-container { display: flex; align-items: center; }
        .search-container input { padding: 8px 12px; border-radius: 20px 0 0 20px; border: 1px solid #ddd; font-size: 12px; width: 220px; }
        .search-container .search-btn { background-color: #2980b9; color: white; border: none; border-radius: 0 20px 20px 0; padding: 8px 12px; cursor: pointer; }
        .search-container .search-btn:hover { background-color: #2471a3; }
        .orders-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; width: 100%; }
        .orders-header h1 { flex: 1; }
        .search-container { flex: 1; display: flex; justify-content: center; }
        .add-order-btn { display: inline-flex; align-items: center; justify-content: flex-end; background-color: #4a90e2; color: white; border: none; border-radius: 40px; padding: 8px 16px; cursor: pointer; font-size: 14px; width: auto; white-space: nowrap; margin-left: auto; }
        .add-order-btn:hover { background-color: #357abf; }
        #special_instructions_textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-family: inherit; margin-bottom: 15px; }
        .confirmation-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); overflow: hidden; }
        .confirmation-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border-radius: 8px; width: 350px; max-width: 90%; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalPopIn 0.3s; }
        @keyframes modalPopIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        .confirmation-title { font-size: 20px; margin-bottom: 15px; color: #333; }
        .confirmation-message { margin-bottom: 20px; color: #555; font-size: 14px; }
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes { background-color: #4a90e2; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s; }
        .confirm-yes:hover { background-color: #357abf; }
        .confirm-no { background-color: #f1f1f1; color: #333; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .confirm-no:hover { background-color: #e1e1e1; }
        #toast-container .toast-close-button { display: none; }
        .inventory-table-container { max-height: 400px; overflow-y: auto; margin-top: 15px; }
        .inventory-table { width: 100%; border-collapse: collapse; }
        .inventory-table th, .inventory-table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        .inventory-table th { background-color: #f2f2f2; position: sticky; top: 0; z-index: 10; }
        .inventory-quantity { width: 60px; text-align: center; }
        .add-to-cart-btn { background-color: #4a90e2; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; }
        .add-to-cart-btn:hover { background-color: #357abf; }
        .inventory-filter-section { display: flex; gap: 10px; margin-bottom: 15px; }
        .inventory-filter-section input, .inventory-filter-section select { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .remove-item-btn { background-color: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; margin-left: 5px; }
        .remove-item-btn:hover { background-color: #c82333; }
        .cart-quantity { width: 60px; text-align: center; }
        #addOrderOverlay .overlay-content, #inventoryOverlay .overlay-content, #cartModal .overlay-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-height: 90vh; overflow-y: auto; margin: 0; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 80%; max-width: 800px; }
        #statusModal .modal-content, #pendingStatusModal .modal-content, #rejectedStatusModal .modal-content { max-height: 85vh; overflow-y: auto; }
        .edit-date-btn { padding: 3px 8px; background-color: #4a90e2; color: white; border: none; border-radius: 20px; cursor: pointer; font-size: 10px; margin-left: 5px; }
        .edit-date-btn:hover { background-color: #3573b9; }
        #editDateModal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); }
        .edit-date-modal-content { background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%; text-align: left; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
        .edit-date-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .edit-date-modal-header h3 { margin: 0; font-size: 18px; }
        .edit-date-close { background: none; border: none; font-size: 20px; cursor: pointer; }
        .edit-date-form { display: flex; flex-direction: column; }
        .edit-date-form label { margin-bottom: 5px; font-weight: 600; }
        .edit-date-form input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px; }
        .edit-date-footer { text-align: right; margin-top: 15px; }
        .edit-date-save-btn { background-color: #4a90e2; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .edit-date-save-btn:hover { background-color: #3573b9; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; text-align: center; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .main-content { padding: 20px; margin-left: 250px; transition: margin-left 0.3s; }
        .orders-table-container { width: 100%; overflow-x: auto; }
        .orders-table { width: 100%; min-width: 1200px; border-collapse: collapse; }
        .orders-table th, .orders-table td { padding: 10px 12px; text-align: center; font-size: 13px; vertical-align: middle; white-space: nowrap; }
        .orders-table thead th { background-color:rgb(34, 34, 34); color: white; font-weight: 600; position: sticky; top: 0; z-index: 10; } /* Added color: white to header text */
        .orders-table tbody tr:hover { background-color: #f1f3f5; }
        .orders-table th.sortable a { color: inherit; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
        .orders-table th.sortable a i { margin-left: 5px; color: #adb5bd; }
        .orders-table th.sortable a:hover i { color: #e9ecef; } /* Lighter color for hover on sort icon */
        .action-buttons { display: flex; gap: 5px; justify-content: center; }
        .action-buttons button, .view-orders-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 3px; }
        .view-orders-btn { background-color: #0dcaf0; color: white; }
        .view-orders-btn:hover { background-color: #0aa3bf; }
        .status-btn { background-color: #ffc107; color: white; } /* Default for Manage/Update/Review */
        .status-btn:hover { background-color: #e0a800; }
        .progress-bar-container { width: 100%; background-color: #e9ecef; border-radius: 0.25rem; overflow: hidden; position: relative; height: 20px; }
        .progress-bar { background-color: #0d6efd; height: 100%; line-height: 20px; color: white; text-align: center; white-space: nowrap; transition: width .6s ease; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 20px; color: #000; font-size: 12px; font-weight: bold; }
        .order-details-table { width: 100%; border-collapse: collapse; }
        .order-details-table th, .order-details-table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        .order-details-table thead th { background-color:rgb(29, 29, 29); color: white; } /* Added color: white */
        .overlay { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); }
        .overlay-content { position: relative; margin: 10% auto; padding: 20px; background: #fff; border-radius: 8px; width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .modal { display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-footer { padding-top: 15px; text-align: right; border-top: 1px solid #e5e5e5; margin-top: 15px; }
        .modal-cancel-btn { background-color: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .modal-status-btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 5px; flex-grow: 1; text-align: center; }
        .modal-status-btn.delivery { background-color: #0dcaf0; color: white; }
        .modal-status-btn.pending { background-color: #ffc107; color: white; }
        .modal-status-btn.rejected { background-color: #dc3545; color: white; }
        .modal-status-btn.active { background-color: #198754; color: white; }
        .modal-status-btn:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .status-buttons { display: flex; justify-content: space-around; margin-top: 15px; }
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
        .order-form input[type="text"], .order-form select, .order-form textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .order-form input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .centered-button { text-align: center; margin-bottom: 15px; }
        .open-inventory-btn { background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .open-inventory-btn:hover { background-color: #218838; }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
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
                        <th class="sortable"><a href="<?= getSortUrl('status', $sort_column, $sort_direction) ?>">Status <?= getSortIcon('status', $sort_column, $sort_direction) ?></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders_list) > 0): ?>
                        <?php foreach ($orders_list as $order_item_loop): // Renamed loop variable ?>
                            <tr data-current-status="<?= htmlspecialchars($order_item_loop['status']) ?>">
                                <td><?= htmlspecialchars($order_item_loop['po_number']) ?></td>
                                <td><?= htmlspecialchars($order_item_loop['username']) ?></td>
                                <td><?= htmlspecialchars($order_item_loop['order_date']) ?></td>
                                <td>
                                    <?= htmlspecialchars($order_item_loop['delivery_date']) ?>
                                    <button class="edit-date-btn" onclick="openEditDateModal('<?= htmlspecialchars($order_item_loop['po_number']) ?>', '<?= htmlspecialchars($order_item_loop['delivery_date']) ?>', '<?= htmlspecialchars($order_item_loop['order_date']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                                <td>
                                    <?php 
                                    if ($order_item_loop['status'] === 'Active'): ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order_item_loop['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order_item_loop['progress'] ?? 0 ?>%</div>
                                    </div>
                                    <?php else: ?>
                                        <span class="status-badge <?= strtolower(htmlspecialchars($order_item_loop['status'])) ?>-progress"><?= $order_item_loop['status'] === 'Pending' ? 'Pending' : 'Not Available' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($order_item_loop['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order_item_loop['po_number']) ?>')"><i class="fas fa-clipboard-list"></i> View</button>
                                    <?php else: // For Pending, Rejected, etc. ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order_item_loop['orders'])) ?>', '<?= htmlspecialchars($order_item_loop['status']) ?>')"><i class="fas fa-eye"></i> View</button>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order_item_loop['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order_item_loop['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order_item_loop['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order_item_loop['special_instructions'])) ?>')"><i class="fas fa-comment-alt"></i> View</button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class_display = ''; // Renamed
                                    switch ($order_item_loop['status']) {
                                        case 'Active': $status_class_display = 'status-active'; break;
                                        case 'Pending': $status_class_display = 'status-pending'; break;
                                        case 'Rejected': $status_class_display = 'status-rejected'; break;
                                        case 'For Delivery': $status_class_display = 'status-delivery'; break;
                                        case 'Completed': $status_class_display = 'status-completed'; break;
                                        default: $status_class_display = 'status-' . strtolower(htmlspecialchars($order_item_loop['status'])); 
                                    }
                                    ?>
                                    <span class="status-badge <?= $status_class_display ?>"><?= htmlspecialchars($order_item_loop['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order_item_loop['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order_item_loop['po_number']) ?>', '<?= htmlspecialchars($order_item_loop['username']) ?>', '<?= htmlspecialchars(addslashes($order_item_loop['orders'])) ?>', 'Pending')"><i class="fas fa-cogs"></i> Manage</button>
                                    <?php elseif ($order_item_loop['status'] === 'Active' && ((int)($order_item_loop['progress'] ?? 0)) < 100 ): ?>
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order_item_loop['po_number']) ?>', '<?= htmlspecialchars($order_item_loop['username']) ?>', 'Active')"><i class="fas fa-sync-alt"></i> Update</button>
                                    <?php elseif ($order_item_loop['status'] === 'Rejected'): ?>
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order_item_loop['po_number']) ?>', '<?= htmlspecialchars($order_item_loop['username']) ?>', 'Rejected')"><i class="fas fa-undo"></i> Review</button>
                                    <?php endif; ?>
                                    <button class="download-btn" onclick="confirmDownloadPO('<?= htmlspecialchars($order_item_loop['po_number']) ?>', '<?= htmlspecialchars($order_item_loop['username']) ?>', '<?= htmlspecialchars($order_item_loop['company'] ?? '') ?>', '<?= htmlspecialchars($order_item_loop['order_date']) ?>', '<?= htmlspecialchars($order_item_loop['delivery_date']) ?>', '<?= htmlspecialchars($order_item_loop['delivery_address']) ?>', '<?= htmlspecialchars(addslashes($order_item_loop['orders'])) ?>', '<?= htmlspecialchars($order_item_loop['total_amount']) ?>', '<?= htmlspecialchars(addslashes($order_item_loop['special_instructions'] ?? '')) ?>')"><i class="fas fa-file-pdf"></i> Invoice</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="no-orders">No orders found matching criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <div id="pdfPreview" style="display: none;">
        <div class="pdf-container">
            <button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button>
            <div id="contentToDownload">
                <div class="po-container">
                     <div class="po-header"><div class="po-company" id="printCompany"></div><div class="po-title">Sales Invoice</div></div>
                     <div class="po-details">
                         <div class="po-left"><div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div><div class="po-detail-row"><span class="po-detail-label">Client:</span> <span id="printUsername"></span></div><div class="po-detail-row"><span class="po-detail-label">Address:</span> <span id="printDeliveryAddress"></span></div></div>
                         <div class="po-right"><div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div><div class="po-detail-row"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div></div>
                     </div>
                     <div id="printInstructionsSection" style="margin-bottom: 20px; display: none;"><strong>Special Instructions:</strong><div id="printSpecialInstructions" style="white-space: pre-wrap;word-wrap:break-word;"></div></div>
                     <table class="po-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead><tbody id="printOrderItems"></tbody></table>
                     <div class="po-total">Total Amount: PHP <span id="printTotalAmount"></span></div>
                </div>
            </div>
            <div class="pdf-actions"><button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button></div>
        </div>
    </div>

    <div id="specialInstructionsModal" class="instructions-modal">
        <div class="instructions-modal-content"><div class="instructions-header"><h3>Special Instructions</h3><div class="instructions-po-number" id="instructionsPoNumber"></div></div><div class="instructions-body" id="instructionsContent"></div><div class="instructions-footer"><button class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button></div></div>
    </div>

    <div id="orderDetailsModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-box-open"></i> Order Details (<span id="orderStatus"></span>)</h2>
             <div id="overall-progress-info" style="margin-bottom: 15px; display: none;"><strong>Overall Progress:</strong><div class="progress-bar-container" style="margin-top: 5px;"><div class="progress-bar" id="overall-progress-bar" style="width:0%;"></div><div class="progress-text" id="overall-progress-text">0%</div></div></div>
            <div class="order-details-container"><table class="order-details-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th id="status-header-cell">Status/Progress</th></tr></thead><tbody id="orderDetailsBody"></tbody></table></div>
             <div class="order-details-footer"><div class="total-amount" id="orderTotalAmount">Total: PHP 0.00</div></div>
            <div class="form-buttons"><button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="save-btn save-progress-btn" onclick="confirmSaveProgress()" style="display:none;"><i class="fas fa-save"></i> Save Progress</button></div>
        </div>
    </div>

    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="statusMessage"></p>
            <div class="status-buttons">
                <button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject<div class="btn-info">(Return stock)</div></button>
            </div>
            <div class="modal-footer"><button type="button" onclick="closeStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <div id="rejectedStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="rejectedStatusMessage"></p>
            <div class="status-buttons"><button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Pending<div class="btn-info">(Return to pending)</div></button></div>
            <div class="modal-footer"><button type="button" onclick="closeRejectedStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <div id="pendingStatusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Change Status</h2><p id="pendingStatusMessage"></p>
            <div id="rawMaterialsContainer" class="raw-materials-container"><h3>Loading inventory status...</h3></div>
            <div class="status-buttons">
                <button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active" disabled><i class="fas fa-check"></i> Active<div class="btn-info">(Deduct stock)</div></button>
                <button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Reject</button>
            </div>
            <div class="modal-footer"><button type="button" onclick="closePendingStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div>
        </div>
    </div>

    <div id="editDateModal" class="modal">
        <div class="edit-date-modal-content">
            <div class="edit-date-modal-header">
                <h3>Edit Delivery Date</h3>
                <button class="edit-date-close" onclick="closeEditDateModal()">&times;</button>
            </div>
            <form id="editDateForm" method="POST" class="edit-date-form">
                <input type="hidden" id="edit_po_number" name="po_number">
                <label for="current_delivery_date">Current Delivery Date:</label>
                <input type="text" id="current_delivery_date" readonly>
                <label for="new_delivery_date">New Delivery Date:</label>
                <input type="text" id="new_delivery_date" name="new_delivery_date" autocomplete="off" required>
                <div class="edit-date-note" style="margin-bottom: 15px; font-size: 12px; color: #666;">
                    <i class="fas fa-info-circle"></i> Delivery dates must be Monday, Wednesday, or Friday, and at least 5 days after the order date.
                </div>
                <div class="edit-date-footer">
                    <input type="hidden" name="update_delivery_date" value="1">
                    <button type="submit" class="edit-date-save-btn"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addOrderOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-plus"></i> Add New Order</h2>
            <form id="addOrderForm" method="POST" class="order-form">
                <div class="left-section">
                    <label for="username_select_add_order">Username:</label> <!-- Changed ID -->
                    <select id="username_select_add_order" name="username" required onchange="generatePONumber();">
                        <option value="" disabled selected>Select User</option>
                        <?php foreach ($clients_arr as $client_item_form): ?>
                            <option value="<?= htmlspecialchars($client_item_form) ?>"
                                    data-company-address="<?= htmlspecialchars($clients_with_company_address_map[$client_item_form] ?? '') ?>"
                                    data-company="<?= htmlspecialchars($clients_with_company_map[$client_item_form] ?? '') ?>">
                                <?= htmlspecialchars($client_item_form) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="order_date_add_form">Order Date:</label> <input type="text" id="order_date_add_form" name="order_date" readonly>
                    <label for="delivery_date_add_form">Delivery Date:</label> <input type="text" id="delivery_date_add_form" name="delivery_date" autocomplete="off" required>
                    <label for="delivery_address_type_add_form">Delivery Address:</label><select id="delivery_address_type_add_form" name="delivery_address_type" onchange="toggleDeliveryAddress()"> <option value="company">Company Address</option><option value="custom">Custom Address</option></select>
                    <div id="company_address_container_add_form"><input type="text" id="company_address_add_form" name="company_address" readonly placeholder="Company address"></div>
                    <div id="custom_address_container_add_form" style="display: none;"><textarea id="custom_address_add_form" name="custom_address" rows="3" placeholder="Enter delivery address"></textarea></div>
                    <input type="hidden" name="delivery_address" id="delivery_address_hidden_add_form">
                    <label for="special_instructions_textarea_add_form">Special Instructions:</label> <textarea id="special_instructions_textarea_add_form" name="special_instructions" rows="3" placeholder="Enter special instructions"></textarea>
                    <div class="centered-button"><button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()"><i class="fas fa-box-open"></i> Select Products</button></div>
                    <div class="order-summary"><h3>Order Summary</h3><table class="summary-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody id="summaryBody"></tbody></table><div class="summary-total">Total: <span class="summary-total-amount">PHP 0.00</span></div></div>
                    <input type="hidden" name="po_number" id="po_number_add_form">
                    <input type="hidden" name="orders" id="orders_json_add_form">
                    <input type="hidden" name="total_amount" id="total_amount_add_form">
                    <input type="hidden" name="company_hidden" id="company_hidden_add_form">
                </div>
                <div class="form-buttons"><button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="save-btn" onclick="confirmAddOrder()"><i class="fas fa-save"></i> Add Order</button></div>
            </form>
        </div>
    </div>

    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Add Order</div><div class="confirmation-message">Add this order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No</button><button class="confirm-yes" onclick="submitAddOrder()">Yes</button></div></div></div>
    <div id="saveProgressConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Save Progress</div><div class="confirmation-message">Save progress?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeSaveProgressConfirmation()">No</button><button class="confirm-yes" onclick="saveProgressChanges()">Yes</button></div></div></div>
    <div id="statusConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Status Change</div><div class="confirmation-message" id="statusConfirmationMessage"></div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeStatusConfirmation()">No</button><button class="confirm-yes" onclick="executeStatusChange()">Yes</button></div></div></div>
    <div id="downloadConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Download</div><div class="confirmation-message">Download this PO?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDownloadConfirmation()">No</button><button class="confirm-yes" onclick="downloadPODirectly()">Yes</button></div></div></div>

    <div id="inventoryOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
             <div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-box-open"></i> Select Products</h2><button class="cart-btn" onclick="window.openCartModal()"><i class="fas fa-shopping-cart"></i> View Cart (<span id="cartItemCount">0</span>)</button></div>
             <div class="inventory-filter-section"><input type="text" id="inventorySearch" placeholder="Search..."><select id="inventoryFilter"><option value="all">All Categories</option></select></div>
             <div class="inventory-table-container"><table class="inventory-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="inventory"></tbody></table></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="cancel-btn" onclick="closeInventoryOverlay()"><i class="fas fa-times"></i> Cancel</button><button type="button" class="save-btn" onclick="closeInventoryOverlay()"><i class="fas fa-check"></i> Done</button></div>
        </div>
    </div>

    <div id="cartModal" class="overlay" style="display: none;">
        <div class="overlay-content">
             <h2><i class="fas fa-shopping-cart"></i> Selected Products</h2>
             <div class="cart-table-container"><table class="cart-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead><tbody class="cart"></tbody></table><p class="no-products" style="text-align:center;display:none;">No products selected.</p></div>
             <div class="cart-total" style="text-align: right; margin-bottom: 20px; font-weight: bold; font-size: 1.1em;">Total: <span class="total-amount">PHP 0.00</span></div>
             <div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeCartModal()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="confirm-btn" onclick="saveCartChanges()"><i class="fas fa-check"></i> Confirm</button></div>
        </div>
    </div>

    <script>
        // --- Global Variables ---
        let currentPoNumber = '';
        let currentOrderOriginalStatus = '';
        let currentOrderItems = [];
        let completedItems = []; // Used for items with 0 quantity or overall completion
        let quantityProgressData = {}; // { itemIndex: { unitIndex: boolean } }
        let itemProgressPercentages = {}; // { itemIndex: percentage }
        let itemContributions = {}; // { itemIndex: contributionToOverall }
        let overallProgress = 0;
        let currentPOData = null; // For PDF download
        let selectedStatus = ''; // For status change confirmation
        let poDownloadData = null; // For PDF download confirmation
        let cartItems = []; // For add order form
        let editingOrderDate = ''; 

        // --- Utility Functions ---
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
             if (!toastContainer) { console.error("Toast container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<div class="toast-content"><i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')}"></i><div class="message">${message}</div></div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }
        function formatWeight(weightInGrams) {
            if (weightInGrams >= 1000) return (weightInGrams / 1000).toFixed(2) + ' kg';
            return (weightInGrams ? parseFloat(weightInGrams).toFixed(2) : '0.00') + ' g';
        }

        // --- Status Change Logic ---
        function confirmStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#statusMessage').text(`Change status for order ${poNumber} (${username})`);
            $('#statusModal').css('display', 'flex');
        }
        function confirmRejectedStatusChange(poNumber, username, originalStatus) {
            currentPoNumber = poNumber;
            currentOrderOriginalStatus = originalStatus;
            $('#rejectedStatusModal').data('po_number', poNumber); // Store PO for potential future use
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
                 if (!ordersJson) throw new Error("Order items data missing for inventory check.");
                 // Basic check, more robust parsing/validation might be needed if JSON is complex
                 if (ordersJson.length > 5 && !ordersJson.includes('"product_id"')) { 
                     console.warn("Received ordersJson might be missing product_id for material check:", ordersJson.substring(0, 100) + "...");
                     // Proceed but log a warning, or throw error if product_id is strictly necessary for check_raw_materials.php
                 }
                 JSON.parse(ordersJson); // Validate if it's at least parseable
                $.ajax({
                    url: '/backend/check_raw_materials.php', type: 'POST',
                    data: { orders: ordersJson, po_number: poNumber }, dataType: 'json',
                    success: function(response) {
                        console.log("Inventory Check (Pending):", response);
                        if (response.success) {
                            const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer');
                            if (needsMfg && response.materials) { 
                                displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section'); 
                            } else if (needsMfg) { 
                                $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Information unavailable or not applicable.</p>'); 
                            } else if (!needsMfg && response.finishedProducts) { 
                                materialContainer.append('<p>All required finished products are in stock.</p>'); 
                                $('#rawMaterialsContainer #raw-materials-section').hide(); 
                            } else if (!response.finishedProducts && !response.materials) { 
                                materialContainer.html('<h3>Inventory Status</h3><p>No specific inventory details available for this order.</p>'); 
                            }
                            updatePendingOrderActionStatus(response);
                        } else { 
                            materialContainer.html(`<h3>Inventory Check Error</h3><p style="color:red;">${response.message || 'Unknown error during inventory check.'}</p><p>Status change to Active might be allowed, but inventory could not be verified.</p>`); 
                            $('#activeStatusBtn').prop('disabled', false); // Allow manual override if check fails
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error (Inventory Check):", status, error, xhr.responseText); 
                        let errorMsg = `Could not check inventory: ${error || 'Server error'}`;
                        if (status === 'parsererror') { errorMsg = `Could not check inventory: Invalid response from server. Please check console.`; }
                        materialContainer.html(`<h3>Server Error</h3><p style="color:red;">${errorMsg}</p><p>Status change to Active might be allowed, but inventory could not be verified.</p>`); 
                        $('#activeStatusBtn').prop('disabled', false); // Allow manual override
                    }
                });
            } catch (e) { 
                materialContainer.html(`<h3>Data Error</h3><p style="color:red;">${e.message}</p><p>Status change to Active might be allowed, but inventory data is problematic.</p>`); 
                $('#activeStatusBtn').prop('disabled', false); // Allow manual override
                console.error("Error in confirmPendingStatusChange (JSON parsing or setup):", e); 
            }
        }
        function confirmStatusAction(status) {
            selectedStatus = status;
            let confirmationMsg = `Are you sure you want to change the status to ${selectedStatus}?`;
            if (selectedStatus === 'Active') { confirmationMsg += ' This will deduct required stock from inventory.'; }
            else if (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected')) { confirmationMsg += ' This will attempt to return deducted stock to inventory.'; }
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
        function executeStatusChange() {
            $('#statusConfirmationModal').css('display', 'none');
            let deductMaterials = (selectedStatus === 'Active');
            let returnMaterials = (currentOrderOriginalStatus === 'Active' && (selectedStatus === 'Pending' || selectedStatus === 'Rejected'));
            updateOrderStatus(selectedStatus, deductMaterials, returnMaterials);
        }
        function updateOrderStatus(status, deductMaterials, returnMaterials) {
            const formData = new FormData();
            formData.append('po_number', currentPoNumber);
            formData.append('status', status);
            formData.append('deduct_materials', deductMaterials ? '1' : '0');
            formData.append('return_materials', returnMaterials ? '1' : '0');
            if (status === 'For Delivery') {
                 formData.append('progress', '100');
            }
            console.log("Sending status update:", Object.fromEntries(formData)); 
            fetch('/backend/update_order_status.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => { 
                 try {
                     const jsonData = JSON.parse(text);
                     if (!response.ok) throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status}`);
                     return jsonData;
                 } catch (e) { console.error('Invalid JSON in updateOrderStatus:', text); throw new Error('Invalid server response. Check console for raw text.'); }
            }))
            .then(data => {
                console.log("Status update response:", data);
                if (data.success) {
                    let message = `Status updated to ${status} successfully`;
                    if (deductMaterials && data.material_deduction_message) message += ` ${data.material_deduction_message}`;
                    else if (deductMaterials) message += '. Inventory deduction initiated.';
                    if (returnMaterials && data.material_return_message) message += ` ${data.material_return_message}`;
                    else if (returnMaterials) message += '. Inventory return initiated.';
                    showToast(message, 'success');
                    sendStatusNotificationEmail(currentPoNumber, status);
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else { throw new Error(data.message || 'Unknown error updating status.'); }
            })
            .catch(error => {
                console.error("Update status fetch error:", error);
                showToast('Error updating status: ' + error.message, 'error');
            })
            .finally(() => { closeRelevantStatusModals(); }); 
        }
        
        function sendStatusNotificationEmail(poNumber, newStatus) {
            fetch('/backend/send_status_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
                body: `po_number=${poNumber}&new_status=${newStatus}`
            })
            .then(response => response.json().catch(() => ({}))) // Catch JSON parse error on empty/non-JSON response
            .then(data => { console.log("Email notification attempt response:", data); })
            .catch(error => { console.error("Error sending status email notification:", error); });
        }

        function closeStatusModal() { $('#statusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number');}
        function closePendingStatusModal() { $('#pendingStatusModal').css('display', 'none'); selectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        function displayFinishedProducts(productsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return false;
             let html = `<h3>Finished Products Status</h3>`;
             if (!productsData || Object.keys(productsData).length === 0) { html += '<p>No finished product information available for this order.</p>'; container.html(html).append('<div id="raw-materials-section"></div>'); return false;}
             html += `<table class="materials-table"><thead><tr><th>Product</th><th>In Stock</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(productsData).forEach(product => {
                 const data = productsData[product];
                 const available = parseInt(data.available) || 0; const required = parseInt(data.required) || 0; const isSufficient = data.sufficient; const shortfall = data.shortfall || 0;
                 html += `<tr><td>${product}</td><td>${available}</td><td>${required}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'In Stock' : 'Needs Mfg (' + shortfall + ' short)'}</td></tr>`;
             });
             html += `</tbody></table>`; container.html(html);
             const needsMfg = Object.values(productsData).some(p => !p.sufficient);
             if (needsMfg) container.append('<div id="raw-materials-section" style="margin-top:15px;"><h3>Raw Materials Required</h3><p>Loading...</p></div>');
             return needsMfg;
        }
        function displayRawMaterials(materialsData, containerSelector) {
             const container = $(containerSelector); if (!container.length) return true; // Or false, depending on desired behavior if selector invalid
             let html = '<h3>Raw Materials Required</h3>';
             if (!materialsData || Object.keys(materialsData).length === 0) { container.html(html + '<p>No specific raw materials information available or needed if finished products are in stock.</p>'); return true; } // All sufficient if no data
             let allSufficient = true; let insufficient = [];
             html += `<table class="materials-table"><thead><tr><th>Material</th><th>Available</th><th>Required</th><th>Status</th></tr></thead><tbody>`;
             Object.keys(materialsData).forEach(material => {
                 const data = materialsData[material]; const available = parseFloat(data.available) || 0; const required = parseFloat(data.required) || 0; 
                 const isSufficient = data.sufficient === undefined ? (available >= required) : data.sufficient; // Infer if not provided
                 if (!isSufficient) { allSufficient = false; insufficient.push(material); }
                 html += `<tr><td>${material}</td><td>${formatWeight(available)}</td><td>${formatWeight(required)}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? 'Sufficient' : 'Insufficient'}</td></tr>`;
             });
             html += `</tbody></table>`;
             const msg = allSufficient ? 'All raw materials are sufficient for manufacturing.' : `Insufficient raw materials for manufacturing: ${insufficient.join(', ')}.`;
             const cls = allSufficient ? 'status-sufficient' : 'status-insufficient';
             container.html(html + `<p class="materials-status ${cls}">${msg}</p>`);
             return allSufficient;
        }
        function updatePendingOrderActionStatus(response) {
             let canActivate = true; 
             let msg = 'Ready to activate.'; 
             const cont = $('#rawMaterialsContainer');
             const prods = response.finishedProducts || {};
             const needsMfg = response.needsManufacturing === true || Object.values(prods).some(p => !p.sufficient);

             if (Object.keys(prods).length === 0 && !needsMfg) { // No finished products listed, assume direct material check or not applicable
                 msg = 'No specific product stock info; assuming direct material usage or not applicable. Ready to activate.';
             } else if (needsMfg) { // Manufacturing is needed for one or more finished products
                 msg = 'Manufacturing required. ';
                 const mats = response.materials || {};
                 const allMatsSufficient = Object.values(mats).every(m => m.sufficient === true);
                 if (Object.keys(mats).length === 0 && !response.all_raw_materials_sufficient_for_mfg) { // No materials listed, but backend says not all suff for mfg
                    canActivate = false;
                    msg += 'Cannot activate: Overall raw materials are insufficient for manufacturing.';
                 } else if (!allMatsSufficient) {
                    canActivate = false;
                    msg += 'Cannot activate: Some raw materials are insufficient.';
                 } else {
                    msg += 'All required raw materials appear sufficient. Ready.';
                 }
             } else { // All listed finished products are in stock
                 msg = 'All required finished products are in stock. Ready to activate.';
             }
             
             if (response.all_sufficient === false && canActivate) { // Overall backend flag
                // This case implies finished products might be okay, but overall raw materials (maybe for something not listed as a "finished product" itself) are not.
                // Or, it's a general "not sufficient" flag from a simpler check.
                // Let's ensure canActivate reflects this if not already false.
                // canActivate = false; // Uncomment if this should always override
                // msg = response.message || "Overall check indicates insufficiency."; // Use backend message
             }


             $('#activeStatusBtn').prop('disabled', !canActivate);
             let statEl = cont.children('.materials-status').last(); // Get the last status message if multiple
             const cls = canActivate ? 'status-sufficient' : 'status-insufficient';
             if (statEl.length) statEl.removeClass('status-sufficient status-insufficient').addClass(cls).text(msg);
             else cont.append(`<p class="materials-status ${cls}" style="margin-top:10px;">${msg}</p>`);
        }

        // --- Order Details Modal Functions (WITH DEBUGGING LOGS) ---
        function viewOrderDetails(poNumber) {
            console.log('viewOrderDetails called with PO:', poNumber); // DEBUG
            currentPoNumber = poNumber;
            // The modal title is static in HTML: <h2><i class="fas fa-box-open"></i> Order Details (<span id="orderStatus"></span>)</h2>
            // We will update #orderStatus span. The h2 also contains the PO, if we want to add it there, we'd modify the h2's html.
            // For now, just updating the status span and ensuring the main title is clear.
            $('#orderDetailsModal h2').html(`<i class="fas fa-box-open"></i> Manage Items - PO: ${poNumber} (<span id="orderStatus">Loading...</span>)`);


            fetch(`/backend/get_order_details.php?po_number=${encodeURIComponent(poNumber)}`)
            .then(r => {
                console.log('viewOrderDetails - Fetch response status:', r.status); // DEBUG
                if (!r.ok) {
                    return r.text().then(text => {
                        console.error('viewOrderDetails - Fetch failed response text:', text); // DEBUG
                        throw new Error(`Network response was not ok: ${r.status} ${r.statusText}. Server said: ${text}`);
                    });
                }
                return r.json().catch(jsonError => { 
                    console.error('viewOrderDetails - JSON.parse error:', jsonError, "Raw text was:", r.text()); // DEBUG
                    throw new Error('Failed to parse JSON response from server.');
                });
            })
            .then(d => {
                console.log('viewOrderDetails - Data received:', d); // DEBUG
                if (d.success) {
                    currentOrderItems = d.orderItems || [];
                    completedItems = d.completedItems || []; 
                    quantityProgressData = d.quantity_progress_data || d.quantityProgressData || {}; 
                    itemProgressPercentages = d.item_progress_percentages || d.itemProgressPercentages || {}; 
                    overallProgress = d.overall_progress || d.overallProgress || 0; 
                    
                    $('#orderStatus').text(d.order_status || 'Active'); 
                    const orderDetailsBody = $('#orderDetailsBody').empty();
                    $('#status-header-cell').show(); 
                    
                    const totalItemsCount = currentOrderItems.length; 
                    itemContributions = {}; 
                    let calculatedOverallProgress = 0;

                    if (totalItemsCount === 0) {
                        orderDetailsBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No items found for this order.</td></tr>');
                        $('#overall-progress-info, .save-progress-btn').hide();
                        $('#orderTotalAmount').text('Total: PHP 0.00');
                        console.log('viewOrderDetails - Showing modal (no items).'); // DEBUG
                        $('#orderDetailsModal').css('display', 'flex');
                        return;
                    }

                    currentOrderItems.forEach((item, index) => {
                        const itemQuantity = parseInt(item.quantity) || 0;
                        const contributionPerItem = totalItemsCount > 0 ? (100 / totalItemsCount) : 0; 
                        itemContributions[index] = contributionPerItem;

                        let unitCompletedCount = 0; 
                        if (quantityProgressData[index] && typeof quantityProgressData[index] === 'object') { // Ensure it's an object/array
                            for (let i = 0; i < itemQuantity; i++) {
                                if (quantityProgressData[index][i] === true) unitCompletedCount++; 
                            }
                        }
                        
                        let unitProgress = 0;
                        if (itemQuantity > 0) {
                            unitProgress = (unitCompletedCount / itemQuantity) * 100;
                        } else if (itemProgressPercentages[index] !== undefined) { // Use pre-calculated for 0-qty items if available
                            unitProgress = itemProgressPercentages[index];
                        } else if (completedItems.includes(index)) { 
                             unitProgress = 100;
                        }
                        itemProgressPercentages[index] = unitProgress; // Store/update
                        
                        const contributionToOverall = (unitProgress / 100) * contributionPerItem; 
                        calculatedOverallProgress += contributionToOverall;
                        
                        const mainRow = $('<tr>').addClass('item-header-row').toggleClass('completed-item', unitProgress >= 99.9).attr('data-item-index', index);
                        mainRow.html(`<td>${item.category||'N/A'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:right;">PHP ${(parseFloat(item.price)||0).toFixed(2)}</td><td style="text-align:center;">${itemQuantity}</td>
                            <td class="status-cell">
                                <div style="display: flex; flex-direction:column; align-items:center; gap: 5px;">
                                    ${itemQuantity > 0 ? `<button class="expand-units-btn btn btn-sm btn-outline-secondary py-0 px-1" onclick="toggleQuantityProgress(${index}, this)"><i class="fas fa-chevron-down"></i> Units</button>` : ''}
                                    <div class="item-progress-bar-container" style="width:120px;"> <!-- Ensure this width is desired -->
                                        <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${unitProgress.toFixed(0)}%;"></div>
                                        <div class="item-progress-text" id="item-progress-text-${index}">${unitProgress.toFixed(0)}%</div>
                                    </div>
                                    <div class="item-contribution-text" id="contribution-text-${index}" style="font-size:9px;color:#666;">(Contr. ${contributionPerItem.toFixed(1)}%)</div>
                                    ${itemQuantity === 0 ? `<input type="checkbox" class="item-status-checkbox form-check-input mt-1" data-index="${index}" onchange="updateRowStyle(this)" ${unitProgress === 100 ? 'checked' : ''}>` : ''}
                                </div>
                            </td>`);
                        orderDetailsBody.append(mainRow);
                        
                        if (itemQuantity > 0) {
                             const dividerRow = $('<tr>').addClass('units-divider').attr('id', `units-divider-${index}`).hide().html(`<td colspan="6" style="border: none; padding: 1px 0; background-color: #e0e0e0; height: 1px;"></td>`);
                             orderDetailsBody.append(dividerRow);
                             for (let i = 0; i < itemQuantity; i++) { 
                                 const isUnitCompleted = quantityProgressData[index] && quantityProgressData[index][i] === true; 
                                 const unitRow = $('<tr>').addClass(`unit-row unit-for-item-${index}`).hide().toggleClass('completed',isUnitCompleted)
                                     .html(`<td colspan="5" style="padding-left:30px; font-size:0.9em;">Unit ${i+1}</td><td style="text-align:center;"><input type="checkbox" class="unit-status-checkbox form-check-input" data-item-index="${index}" data-unit-index="${i}" onchange="updateUnitStatus(this)" ${isUnitCompleted?'checked':''}></td>`);
                                 orderDetailsBody.append(unitRow);
                             }
                             const actionRow = $('<tr>').addClass(`unit-row unit-action-row unit-for-item-${index}`).hide()
                                 .html(`<td colspan="6" style="text-align:right; padding:8px;"><button class="btn btn-sm btn-outline-success py-0 px-1 me-1" onclick="selectAllUnits(${index},${itemQuantity})">All Done</button><button class="btn btn-sm btn-outline-warning py-0 px-1" onclick="deselectAllUnits(${index},${itemQuantity})">Reset Units</button></td>`);
                             orderDetailsBody.append(actionRow);
                        }
                    });
                    overallProgress = calculatedOverallProgress; 
                    updateOverallProgressDisplay(); 
                    let totalAmount = currentOrderItems.reduce((sum, item) => sum + (parseFloat(item.price || 0) * parseInt(item.quantity || 0)), 0);
                    $('#orderTotalAmount').text(`Total: PHP ${totalAmount.toFixed(2)}`);
                    $('#overall-progress-info, .save-progress-btn').show();
                    console.log('viewOrderDetails - Showing modal (with items).'); // DEBUG
                    $('#orderDetailsModal').css('display', 'flex'); 
                } else {
                    showToast('Error fetching order details: ' + (d.message || 'Unknown error from backend'), 'error');
                    console.error('viewOrderDetails - Backend success false:', d.message); // DEBUG
                     $('#orderDetailsModal h2').html(`<i class="fas fa-exclamation-triangle"></i> Error Loading Details`); // Update title on error
                }
            })
            .catch(error => {
                showToast('Network or server error fetching order details: ' + error.message, 'error');
                console.error('viewOrderDetails - Fetch catch error:', error); // DEBUG
                $('#orderDetailsModal h2').html(`<i class="fas fa-exclamation-triangle"></i> Error Loading Details`); // Update title on error
            });
        }

        function viewOrderInfo(ordersJsonString, orderStatus) { // This is the original signature from dfe8bc9...
            // PO number is not directly passed here. If needed in title, PHP onclick must be changed or PO extracted from ordersJsonString.
            console.log('viewOrderInfo called with Status:', orderStatus); // DEBUG. 
            // console.log('viewOrderInfo - Raw ordersJsonString:', ordersJsonString); // DEBUG - Be careful if long

            let orderDetailsArray;
            try {
                orderDetailsArray = JSON.parse(ordersJsonString); 
                console.log('viewOrderInfo - Successfully parsed ordersJsonString:', orderDetailsArray); // DEBUG
            } catch (e) {
                showToast('Error displaying order information: Invalid items data format.', 'error');
                console.error('viewOrderInfo - JSON.parse error:', e, "Input was:", ordersJsonString); // DEBUG
                return; 
            }

            const itemsBody = $('#orderDetailsBody').empty();
            $('#status-header-cell').hide(); 
            // The modal title is static in HTML: <h2><i class="fas fa-box-open"></i> Order Details (<span id="orderStatus"></span>)</h2>
            // We update the span
            $('#orderStatus').text(orderStatus); 
            // For consistency, if we want PO in title, we'd need to adjust the h2.
            // Let's try to find PO from first item if possible, or leave it generic.
            let poForTitle = 'N/A';
            if(orderDetailsArray && orderDetailsArray.length > 0 && orderDetailsArray[0].po_number) { // Assuming po_number might be in item details
                poForTitle = orderDetailsArray[0].po_number;
            } else if (currentPoNumber && orderStatus !== 'Active') { // If currentPoNumber is set from another context (less reliable here)
                // This is less ideal as currentPoNumber is for Active orders usually.
                // The PHP should pass PO for this function for clarity.
            }
            // Updating the main H2 title of the modal.
             $('#orderDetailsModal h2').html(`<i class="fas fa-eye"></i> View Order Items (<span id="orderStatus">${orderStatus}</span>)`);
            // If PO number is crucial for the title here, the PHP `onclick` for non-active orders should also pass the PO number.
            // Example: onclick="viewOrderInfo('JSON', 'Status', 'PO_NUMBER_HERE')"
            // Then JS: function viewOrderInfo(ordersJsonString, orderStatus, poNumberForTitle)

            let totalAmountCalc = 0;

            if (orderDetailsArray && orderDetailsArray.length > 0) {
                orderDetailsArray.forEach(p => {
                    totalAmountCalc += (parseFloat(p.price) || 0) * (parseInt(p.quantity) || 0);
                    itemsBody.append(`<tr><td>${p.category||'N/A'}</td><td>${p.item_description||'N/A'}</td><td>${p.packaging||'N/A'}</td><td style="text-align:right;">PHP ${(parseFloat(p.price)||0).toFixed(2)}</td><td style="text-align:center;">${p.quantity||0}</td><td>N/A (Status: ${orderStatus})</td></tr>`);
                });
            } else {
                itemsBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No items found for this order.</td></tr>');
            }
            $('#orderTotalAmount').text(`Total: PHP ${totalAmountCalc.toFixed(2)}`);
            $('#overall-progress-info, .save-progress-btn').hide(); 
            console.log('viewOrderInfo - Showing modal.'); // DEBUG
            $('#orderDetailsModal').css('display', 'flex'); 
        }
        
        function toggleQuantityProgress(itemIndex, buttonElement) { 
            $(`.unit-for-item-${itemIndex}, #units-divider-${itemIndex}`).slideToggle(200); 
            $(buttonElement).find('i').toggleClass('fa-chevron-down fa-chevron-up');
        }
        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.dataset.itemIndex); const unitIndex = parseInt(checkbox.dataset.unitIndex); const isChecked = checkbox.checked; $(checkbox).closest('tr').toggleClass('completed', isChecked);
            if (!quantityProgressData[itemIndex] || typeof quantityProgressData[itemIndex] !== 'object') { quantityProgressData[itemIndex] = {}; } // Initialize if not an object
            quantityProgressData[itemIndex][unitIndex] = isChecked; 
            updateItemProgress(itemIndex); 
            updateOverallProgress();
        }
        function updateItemProgress(itemIndex) {
            const item = currentOrderItems[itemIndex]; 
            const qty = parseInt(item.quantity) || 0; 
            if (qty === 0) { // Handle 0-quantity items based on main checkbox if one exists or pre-set progress
                const progressPercentage = itemProgressPercentages[itemIndex] !== undefined ? itemProgressPercentages[itemIndex] : ($(`.item-status-checkbox[data-index="${itemIndex}"]`).is(':checked') ? 100 : 0);
                itemProgressPercentages[itemIndex] = progressPercentage;
                $(`#item-progress-bar-${itemIndex}`).css('width', `${progressPercentage.toFixed(0)}%`); 
                $(`#item-progress-text-${itemIndex}`).text(`${progressPercentage.toFixed(0)}%`);
                updateItemStatusBasedOnUnits(itemIndex, progressPercentage >= 99.9);
                return;
            }
            let completedUnits = 0;
            if (quantityProgressData[itemIndex] && typeof quantityProgressData[itemIndex] === 'object') {
                for (let i = 0; i < qty; i++) {
                    if (quantityProgressData[itemIndex][i]) completedUnits++;
                }
            }
            const progressPercentage = (completedUnits / qty) * 100; 
            itemProgressPercentages[itemIndex] = progressPercentage; 
            $(`#item-progress-bar-${itemIndex}`).css('width', `${progressPercentage.toFixed(0)}%`); 
            $(`#item-progress-text-${itemIndex}`).text(`${progressPercentage.toFixed(0)}%`); 
            updateItemStatusBasedOnUnits(itemIndex, completedUnits === qty);
        }
        function updateOverallProgressDisplay() { 
            const rounded = Math.round(overallProgress); 
            $('#overall-progress-bar').css('width', `${rounded}%`); 
            $('#overall-progress-text').text(`${rounded}%`); 
        }
        function updateOverallProgress() {
            let newCalculatedProgress = 0; 
            currentOrderItems.forEach((item, index) => { // Iterate through currentOrderItems to ensure all are considered
                const prog = itemProgressPercentages[index] || 0; // Default to 0 if not set
                const contrib = itemContributions[index] || 0; // Default to 0
                newCalculatedProgress += (prog / 100) * contrib;
            });
            overallProgress = newCalculatedProgress; 
            updateOverallProgressDisplay(); 
            return Math.round(overallProgress);
        }
        function updateItemStatusBasedOnUnits(itemIndex, allUnitsComplete) {
            const intIndex = parseInt(itemIndex); 
            $(`tr[data-item-index="${intIndex}"]`).toggleClass('completed-item', allUnitsComplete); 
        }
        function selectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', true); 
            checkboxes.closest('tr').addClass('completed');
            if (!quantityProgressData[itemIndex] || typeof quantityProgressData[itemIndex] !== 'object') quantityProgressData[itemIndex] = {}; 
            for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = true;
            updateItemProgress(itemIndex); updateOverallProgress();
        }
        function deselectAllUnits(itemIndex, quantity) {
            const checkboxes = $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', false); 
            checkboxes.closest('tr').removeClass('completed');
            if (!quantityProgressData[itemIndex] || typeof quantityProgressData[itemIndex] !== 'object') quantityProgressData[itemIndex] = {}; 
            for (let i = 0; i < quantity; i++) quantityProgressData[itemIndex][i] = false;
            updateItemProgress(itemIndex); updateOverallProgress();
        }
        function updateRowStyle(checkbox) { // For items with 0 quantity (main checkbox)
            const index = parseInt(checkbox.dataset.index); 
            const isChecked = checkbox.checked; 
            $(checkbox).closest('tr.item-header-row').toggleClass('completed-item', isChecked);
            itemProgressPercentages[index] = isChecked ? 100 : 0; 
            // Also update the visual progress bar for this 0-quantity item
            $(`#item-progress-bar-${index}`).css('width', `${itemProgressPercentages[index].toFixed(0)}%`); 
            $(`#item-progress-text-${index}`).text(`${itemProgressPercentages[index].toFixed(0)}%`);
            updateOverallProgress();
        }
        function closeOrderDetailsModal() { $('#orderDetailsModal').css('display', 'none'); }
        function confirmSaveProgress() { $('#saveProgressConfirmationModal').css('display', 'block'); }
        function closeSaveProgressConfirmation() { $('#saveProgressConfirmationModal').css('display', 'none'); }

        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').hide();
            const finalProgress = updateOverallProgress(); 

            if (finalProgress >= 99.9) { // Use a threshold for floating point
                showToast('Progress reached 100%. Updating status to For Delivery...', 'info');
                updateOrderStatus('For Delivery', false, false); 
            } else {
                fetch('/backend/update_order_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        po_number: currentPoNumber,
                        quantity_progress: quantityProgressData,
                        item_progress_percentages: itemProgressPercentages, // Send this too
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
                    console.error('Save progress error:', error);
                });
            }
        }

        function openEditDateModal(poNumber, currentDate, orderDate) {
            currentPoNumber = poNumber;
            editingOrderDate = orderDate; // Store the order_date for validation
            $('#edit_po_number').val(poNumber);
            $('#current_delivery_date').val(currentDate);
            $('#new_delivery_date').val(''); // Clear previous new date
            
            if ($.datepicker) {
                $("#new_delivery_date").datepicker("destroy"); // Destroy to re-initialize options
                $("#new_delivery_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: calculateMinDeliveryDate(orderDate), // Calculate minDate based on orderDate
                    beforeShowDay: function(date) {
                        var day = date.getDay(); // 0 (Sun) to 6 (Sat)
                        // Allow Monday (1), Wednesday (3), Friday (5)
                        return [(day == 1 || day == 3 || day == 5), ''];
                    }
                });
            }
            $('#editDateModal').css('display', 'flex');
        }

        function calculateMinDeliveryDate(orderDateStr) {
            const orderDate = new Date(orderDateStr);
            const minDate = new Date(orderDate);
            minDate.setDate(orderDate.getDate() + 5); // At least 5 days after order date
            return minDate;
        }
        
        function closeEditDateModal() {
            $('#editDateModal').css('display', 'none');
        }
        
        // Removed validateDeliveryDate JS function as primary validation is in PHP
        // and client-side datepicker already restricts choices.

        function confirmDownloadPO(...args) {
             poDownloadData = { poNumber: args[0], username: args[1], company: args[2], orderDate: args[3], deliveryDate: args[4], deliveryAddress: args[5], ordersJson: args[6], totalAmount: args[7], specialInstructions: args[8] };
             console.log("Data for PDF:", poDownloadData);
             $('#downloadConfirmationModal .confirmation-message').text(`Download Invoice for PO ${poDownloadData.poNumber}?`);
             $('#downloadConfirmationModal').show();
         }
        function closeDownloadConfirmation() { $('#downloadConfirmationModal').hide(); poDownloadData = null; }
        function downloadPODirectly() {
            $('#downloadConfirmationModal').hide(); if (!poDownloadData) { showToast('No data for PO download', 'error'); return; }
            try {
                currentPOData = poDownloadData;
                $('#printCompany').text(currentPOData.company || 'N/A'); 
                $('#printPoNumber').text(currentPOData.poNumber); 
                $('#printUsername').text(currentPOData.username); 
                $('#printDeliveryAddress').text(currentPOData.deliveryAddress || 'N/A');
                $('#printOrderDate').text(currentPOData.orderDate || 'N/A'); 
                $('#printDeliveryDate').text(currentPOData.deliveryDate || 'N/A');

                const instrSec = $('#printInstructionsSection');
                const instrContent = $('#printSpecialInstructions');
                if (currentPOData.specialInstructions && String(currentPOData.specialInstructions).trim()) { 
                    instrContent.text(String(currentPOData.specialInstructions)); instrSec.show(); 
                } else { 
                    instrSec.hide(); instrContent.text(''); 
                }
                
                const items = JSON.parse(currentPOData.ordersJson); 
                const body = $('#printOrderItems').empty();
                items.forEach(item => { 
                    const total = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0); 
                    body.append(`<tr><td>${item.category||'N/A'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:center;">${item.quantity||0}</td><td style="text-align:right;">${(parseFloat(item.price)||0).toFixed(2)}</td><td style="text-align:right;">${total.toFixed(2)}</td></tr>`);
                });
                
                const element = document.getElementById('contentToDownload'); 
                const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }};
                $('#printTotalAmount').text(parseFloat(currentPOData.totalAmount).toFixed(2));
                html2pdf().set(opt).from(element).save().then(() => { 
                    showToast(`Invoice downloaded.`, 'success'); currentPOData = null; poDownloadData = null; 
                }).catch(e => { 
                    console.error('PDF generation error:', e); showToast('PDF generation error: ' + e.message, 'error'); currentPOData = null; poDownloadData = null; 
                });
            } catch (e) { 
                console.error('PDF preparation error:', e); showToast('PDF data error: ' + e.message, 'error'); currentPOData = null; poDownloadData = null; 
            }
        }
        function downloadPDF() { 
            if (!currentPOData) { showToast('No data available for PDF generation.', 'error'); return; } 
            const element = document.getElementById('contentToDownload'); 
            const opt = { margin: [10,10,10,10], filename: `PO_${currentPOData.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }}; 
            html2pdf().set(opt).from(element).save().then(() => { 
                showToast(`Invoice downloaded.`, 'success'); 
            }).catch(e => { 
                console.error('PDF generation error:', e); showToast('PDF generation error: ' + e.message, 'error'); 
            });
        }
        function closePDFPreview() { $('#pdfPreview').hide(); currentPOData = null; }

        function viewSpecialInstructions(poNumber, instructions) { 
            $('#instructionsPoNumber').text('PO: ' + poNumber); 
            const content = $('#instructionsContent'); 
            const decodedInstructions = $('<textarea />').html(instructions).text(); // Decode HTML entities
            if (decodedInstructions && decodedInstructions.trim()){ 
                content.text(decodedInstructions).removeClass('empty'); 
            } else { 
                content.text('No special instructions provided.').addClass('empty'); 
            } 
            $('#specialInstructionsModal').show(); 
        }
        function closeSpecialInstructions() { $('#specialInstructionsModal').hide(); }

        function initializeDeliveryDatePicker(formPrefix = "") { 
            const deliveryDateInput = $(`#${formPrefix}delivery_date`);
            const orderDateInput = $(`#${formPrefix}order_date`); // Assuming an order_date input exists for add form
            const orderDateValue = orderDateInput.length ? orderDateInput.val() : new Date().toISOString().split('T')[0];


            if ($.datepicker && deliveryDateInput.length) { 
                deliveryDateInput.datepicker("destroy"); 
                deliveryDateInput.datepicker({ 
                    dateFormat: 'yy-mm-dd', 
                    minDate: calculateMinDeliveryDate(orderDateValue),
                    beforeShowDay: function(date) {
                        var day = date.getDay();
                        return [(day == 1 || day == 3 || day == 5), ''];
                    }
                }); 
                if (!deliveryDateInput.val()) { // Set default only if empty
                    var nextDate = getNextAvailableDeliveryDateJS(orderDateValue);
                    deliveryDateInput.datepicker("setDate", nextDate);
                }
            }
        }
        
        function getNextAvailableDeliveryDateJS(baseDateStr) { // Renamed to avoid conflict
            var date = baseDateStr ? new Date(baseDateStr) : new Date();
            date.setDate(date.getDate() + 5); 
            while (true) {
                var day = date.getDay();
                if (day === 1 || day === 3 || day === 5) { break; }
                date.setDate(date.getDate() + 1);
            }
            return date;
        }
        
        function openAddOrderForm() { 
            $('#addOrderForm')[0].reset(); 
            cartItems = []; 
            updateOrderSummary(); 
            updateCartItemCount(); 
            const today = new Date(); 
            const fmtDate = `${today.getFullYear()}-${(today.getMonth()+1).toString().padStart(2,'0')}-${today.getDate().toString().padStart(2,'0')}`;
            $('#order_date_add_form').val(fmtDate); // Use specific ID
            initializeDeliveryDatePicker("delivery_date_add_form", fmtDate); // Pass ID and order date
             // Corrected IDs for add order form elements
            $('#username_select_add_order').val(''); // Reset user select
            $('#company_address_add_form').val('');
            $('#custom_address_add_form').val('');
            $('#delivery_address_hidden_add_form').val('');
            $('#special_instructions_textarea_add_form').val('');
            $('#po_number_add_form').val('');
            $('#orders_json_add_form').val('');
            $('#total_amount_add_form').val('');
            $('#company_hidden_add_form').val('');
            toggleDeliveryAddress(true); // Pass true to indicate it's for the add form
            generatePONumber(); // Call after resetting username
            $('#addOrderOverlay').css('display', 'flex'); 
        }
        
        function closeAddOrderForm() { $('#addOrderOverlay').hide(); }

        // Modified toggleDeliveryAddress to accept a flag for add form
        function toggleDeliveryAddress(isAddForm = false) { 
            const prefix = isAddForm ? "_add_form" : "";
            const type = $(`#delivery_address_type${prefix}`).val(); 
            const isCompany = type === 'company'; 
            $(`#company_address_container${prefix}`).toggle(isCompany); 
            $(`#custom_address_container${prefix}`).toggle(!isCompany); 
            if(isCompany){
                $(`#delivery_address${isAddForm ? '_hidden_add_form' : ''}`).val($(`#company_address${prefix}`).val());
            } else {
                $(`#delivery_address${isAddForm ? '_hidden_add_form' : ''}`).val($(`#custom_address${prefix}`).val());
            }
        }

        // Modified generatePONumber to use correct selector for Add Order form
        function generatePONumber() { 
            const userSelect = $('#username_select_add_order'); // Corrected ID
            const username = userSelect.val(); 
            const companyHiddenInput = $('#company_hidden_add_form'); // Corrected ID
            const companyAddressInput = $('#company_address_add_form'); // Corrected ID
            const selectedOption = userSelect.find('option:selected'); 
            companyHiddenInput.val(selectedOption.data('company') || ''); 
            companyAddressInput.val(selectedOption.data('company-address') || ''); 
            if (username) { 
                const now = new Date(); 
                const po = `PO-${username.substring(0,3).toUpperCase()}${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}${now.getHours().toString().padStart(2,'0')}${now.getMinutes().toString().padStart(2,'0')}`; 
                $('#po_number_add_form').val(po); // Corrected ID
            } else { 
                $('#po_number_add_form').val(''); // Corrected ID
            } 
            toggleDeliveryAddress(true); // Ensure correct address field is updated
        }

        // Modified prepareOrderData for Add Order form
        function prepareOrderData() { 
            toggleDeliveryAddress(true); // Ensure correct address context
            const addr = $('#delivery_address_hidden_add_form').val(); 
            if (!$('#delivery_address_type_add_form').val() === 'company' && !addr && $('#delivery_address_type_add_form').val() === 'custom') { 
                showToast('Custom address cannot be empty if selected.', 'error'); return false; 
            } 
            if (cartItems.length === 0) { showToast('Order summary cannot be empty. Please select products.', 'error'); return false; } 
            $('#orders_json_add_form').val(JSON.stringify(cartItems)); 
            let total = 0; 
            cartItems.forEach(item => total += (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0)); 
            $('#total_amount_add_form').val(total.toFixed(2)); 
            // Company name is already set in company_hidden_add_form by generatePONumber
            return true; 
        }
        
        function confirmAddOrder() { 
            const deliveryDateStr = $('#delivery_date_add_form').val();
            const orderDateStr = $('#order_date_add_form').val();
            
            if (!deliveryDateStr) { showToast('Delivery date is required.', 'error'); return; }
            if (!orderDateStr) { showToast('Order date is required.', 'error'); return; } // Should be auto-filled

            const isValidDate = validateClientSideDeliveryDate(deliveryDateStr, orderDateStr);
            
            if (isValidDate && prepareOrderData()) {
                $('#addConfirmationModal').show();
            }
        }

        function validateClientSideDeliveryDate(deliveryDateStr, orderDateStr) {
            const deliveryDate = new Date(deliveryDateStr);
            const orderDate = new Date(orderDateStr);
            
            // Check if deliveryDate is valid
            if (isNaN(deliveryDate.getTime())) {
                showToast('Invalid delivery date format.', 'error');
                return false;
            }

            // Check for Mon, Wed, Fri
            const dayOfWeek = deliveryDate.getDay(); // 0 (Sun) to 6 (Sat)
            if (!(dayOfWeek === 1 || dayOfWeek === 3 || dayOfWeek === 5)) {
                showToast('Delivery date must be a Monday, Wednesday, or Friday.', 'error');
                return false;
            }

            // Check for 5-day gap
            const minDeliveryDate = new Date(orderDate);
            minDeliveryDate.setDate(orderDate.getDate() + 5);
            // Normalize to compare dates only, ignoring time
            minDeliveryDate.setHours(0,0,0,0);
            deliveryDate.setHours(0,0,0,0);

            if (deliveryDate < minDeliveryDate) {
                showToast('Delivery date must be at least 5 days after the order date.', 'error');
                return false;
            }
            return true;
        }


        function closeAddConfirmation() { $('#addConfirmationModal').hide(); }
        function submitAddOrder() { 
            $('#addConfirmationModal').hide(); 
            const form = document.getElementById('addOrderForm'); 
            const fd = new FormData(form); 
            
            // Ensure the hidden fields populated by JS are included correctly
            fd.set('orders', $('#orders_json_add_form').val());
            fd.set('total_amount', $('#total_amount_add_form').val());
            fd.set('po_number', $('#po_number_add_form').val());
            fd.set('company', $('#company_hidden_add_form').val()); // Use 'company' as key if backend expects that
            fd.set('delivery_address', $('#delivery_address_hidden_add_form').val());


            console.log("Submitting Add Order FormData:", Object.fromEntries(fd));
            
            fetch('/backend/add_order.php', { method: 'POST', body: fd })
            .then(response => response.json().catch(err => {
                console.error("Add order - JSON parse error:", err);
                return response.text().then(text => { throw new Error("Invalid JSON response from server: " + text);});
            }))
            .then(data => {
                if (data.success) {
                    showToast('Order added successfully!', 'success');
                    const usernameVal = $('#username_select_add_order').val(); 
                    const poNumberVal = fd.get('po_number'); // Get from FormData as it's set there
                    sendNewOrderEmail(usernameVal, poNumberVal);
                    setTimeout(() => { window.location.href = 'orders.php'; }, 1500);
                } else {
                    showToast('Error adding order: ' + (data.message || 'Unknown error from backend.'), 'error');
                }
            })
            .catch(error => {
                showToast('Error submitting order: ' + error.message, 'error');
                console.error('Add order submission fetch/network error:', error);
            });
        }
        
        function sendNewOrderEmail(username, poNumber) {
            fetch('/backend/send_new_order_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
                body: `username=${encodeURIComponent(username)}&po_number=${encodeURIComponent(poNumber)}`
            })
            .then(response => response.json().catch(() => ({})))
            .then(data => { console.log("New order email notification attempt response:", data); })
            .catch(error => { console.error("Error sending new order email notification:", error); });
        }

        function openInventoryOverlay() { 
            $('#inventoryOverlay').css('display', 'flex'); 
            const inventoryBody = $('.inventory').html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>'); 
            $.getJSON('/backend/get_inventory.php', function(data) { 
                if (data.success) { 
                    populateInventory(data.inventory); 
                    populateCategories(data.categories); 
                } else { 
                    inventoryBody.html('<tr><td colspan="6" style="text-align:center;color:red;">Error: ' + (data.message || 'Could not load inventory.') + '</td></tr>'); 
                } 
            }).fail(() => { 
                inventoryBody.html('<tr><td colspan="6" style="text-align:center;color:red;">Failed to load inventory. Server error or invalid response.</td></tr>');
            });
        }
        function populateInventory(inventory) {
            const body = $('.inventory').empty(); 
            if (!inventory || inventory.length === 0) { 
                body.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No inventory items found.</td></tr>'); return; 
            }
            inventory.forEach(item => {
                const price = parseFloat(item.price);
                if (isNaN(price) || item.product_id === undefined || item.product_id === null) {
                    console.warn("Skipping inventory item due to invalid price or missing product_id:", item);
                    return;
                }
                body.append(`<tr><td>${item.category||'Uncategorized'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:right;">PHP ${price.toFixed(2)}</td><td><input type="number" class="inventory-quantity form-control form-control-sm" value="1" min="1" max="100" style="width:70px;"></td><td style="text-align:center;"><button class="add-to-cart-btn btn btn-sm btn-primary" onclick="addToCart(this,'${item.product_id}','${item.category||''}','${item.item_description||''}','${item.packaging||''}','${price}')"><i class="fas fa-plus"></i> Add</button></td></tr>`);
             });
        }
        function populateCategories(categories) { 
            const sel = $('#inventoryFilter'); 
            sel.find('option:not(:first-child)').remove(); // Keep "All Categories"
            if (!categories || categories.length === 0) return; 
            categories.forEach(cat => sel.append(`<option value="${cat}">${cat}</option>`));
        }
        function filterInventory() { 
            const cat = $('#inventoryFilter').val(); 
            const search = $('#inventorySearch').val().toLowerCase().trim(); 
            $('.inventory tr').each(function() { 
                const row = $(this); 
                const categoryMatch = (cat === 'all' || row.find('td:first-child').text() === cat);
                const searchMatch = (row.text().toLowerCase().indexOf(search) > -1);
                row.toggle(categoryMatch && searchMatch); 
            });
        }
        $('#inventorySearch, #inventoryFilter').off('input change', filterInventory).on('input change', filterInventory);
        function closeInventoryOverlay() { $('#inventoryOverlay').hide(); }
        
        function addToCart(button, productId, category, itemDesc, packaging, price) {
            const qtyInput = $(button).closest('tr').find('.inventory-quantity');
            let qty = parseInt(qtyInput.val()); 

            if (qty > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                qty = 100; 
                qtyInput.val(100); 
            }
            if (isNaN(qty) || qty < 1) { 
                showToast('Quantity must be at least 1.', 'error');
                qtyInput.val(1); return;
            }

            const existingItemIndex = cartItems.findIndex(i => String(i.product_id) === String(productId) && i.packaging === packaging);

            if (existingItemIndex >= 0) {
                 let newQty = cartItems[existingItemIndex].quantity + qty;
                 if (newQty > 100) {
                     showToast(`Cannot add ${qty}. Total quantity for ${itemDesc} (${packaging}) would exceed 100. Max quantity (100) retained.`, 'warning');
                     cartItems[existingItemIndex].quantity = 100; 
                 } else {
                     cartItems[existingItemIndex].quantity = newQty;
                 }
            } else {
                cartItems.push({ product_id: String(productId), category, item_description: itemDesc, packaging, price: parseFloat(price), quantity: qty });
            }
            showToast(`Added ${qty} x ${itemDesc} (${packaging}) to cart.`, 'success');
            qtyInput.val(1); 
            updateOrderSummary();
            updateCartItemCount();
        }
        function updateOrderSummary() {
            const summaryBody = $('#summaryBody').empty(); 
            let totalOrderAmountVal = 0; // Renamed
            if (cartItems.length === 0) { 
                summaryBody.html('<tr><td colspan="6" style="text-align:center; padding: 10px; color: #6c757d;">No products selected.</td></tr>'); 
            } else { 
                cartItems.forEach((item, index) => {
                    totalOrderAmountVal += (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
                    summaryBody.append(`<tr><td>${item.category||'N/A'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:right;">PHP ${(parseFloat(item.price)||0).toFixed(2)}</td><td><input type="number" class="cart-quantity summary-quantity form-control form-control-sm" value="${item.quantity}" min="1" max="100" data-index="${index}" onchange="updateSummaryItemQuantity(this)" style="width:70px;"></td><td style="text-align:center;"><button type="button" class="remove-item-btn btn btn-sm btn-danger" onclick="removeSummaryItem(${index})"><i class="fas fa-trash-alt"></i></button></td></tr>`);
                }); 
            }
            $('.summary-total-amount').text(`PHP ${totalOrderAmountVal.toFixed(2)}`);
        }
        function updateSummaryItemQuantity(input) {
            const itemIndex = parseInt($(input).data('index')); // Renamed
            let newQuantity = parseInt($(input).val()); // Renamed

            if (newQuantity > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                newQuantity = 100; 
                $(input).val(100); 
            }
            if (isNaN(newQuantity) || newQuantity < 1) { 
                showToast('Quantity must be at least 1.', 'error');
                $(input).val(cartItems[itemIndex].quantity); 
                return;
            }
            cartItems[itemIndex].quantity = newQuantity; 
            updateOrderSummary(); 
            updateCartItemCount();
            updateCartDisplay(); 
        }
        function removeSummaryItem(index) { 
            if (index >= 0 && index < cartItems.length) { 
                const removedItem = cartItems.splice(index, 1)[0]; // Renamed
                showToast(`Removed ${removedItem.item_description}`, 'info'); 
                updateOrderSummary(); updateCartItemCount(); updateCartDisplay(); 
            } 
        }
        function updateCartItemCount() { $('#cartItemCount').text(cartItems.length); }
        window.openCartModal = function() { $('#cartModal').css('display', 'flex'); updateCartDisplay(); }
        function closeCartModal() { $('#cartModal').hide(); }
        function updateCartDisplay() { 
            const cartTableBody = $('.cart').empty(); // Renamed
            const noProductsMsg = $('#cartModal .no-products'); // More specific selector
            const cartTotalAmountEl = $('#cartModal .total-amount'); // More specific selector
            let currentCartTotal = 0; // Renamed
            if (cartItems.length === 0) { 
                if(noProductsMsg.length) noProductsMsg.show(); else cartTableBody.html('<tr><td colspan="6" style="text-align:center;padding:15px;">No products in cart.</td></tr>');
                cartTotalAmountEl.text('PHP 0.00'); 
            } else { 
                if(noProductsMsg.length) noProductsMsg.hide(); 
                cartItems.forEach((item, idx) => { 
                    const itemSubTotal = (parseFloat(item.price)||0) * (parseInt(item.quantity)||0); // Renamed
                    currentCartTotal += itemSubTotal; 
                    cartTableBody.append(`<tr><td>${item.category||'N/A'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:right;">PHP ${(parseFloat(item.price)||0).toFixed(2)}</td><td><input type="number" class="cart-quantity form-control form-control-sm" value="${item.quantity}" min="1" max="100" data-index="${idx}" onchange="updateCartItemQuantity(this)" style="width:70px;"></td><td style="text-align:center;"><button type="button" class="remove-item-btn btn btn-sm btn-danger" onclick="removeCartItem(${idx})"><i class="fas fa-trash-alt"></i></button></td></tr>`);
                }); 
                cartTotalAmountEl.text(`PHP ${currentCartTotal.toFixed(2)}`); 
            } 
        }
        
        function updateCartItemQuantity(input) { // This function is duplicated, ensure the correct one is used or merge. This one is specific to cart modal.
            const itemIndexInCart = parseInt($(input).data('index')); // Renamed
            let newCartQuantity = parseInt($(input).val()); // Renamed

            if (newCartQuantity > 100) {
                showToast('Quantity cannot exceed 100.', 'error');
                newCartQuantity = 100; 
                $(input).val(100); 
            }

            if (isNaN(newCartQuantity) || newCartQuantity < 1) {
                showToast('Quantity must be between 1 and 100.', 'error'); 
                $(input).val(cartItems[itemIndexInCart].quantity); 
                return;
            }
            cartItems[itemIndexInCart].quantity = newCartQuantity;
            updateCartDisplay(); 
            updateOrderSummary(); // Reflect changes in the main form's summary as well
        }
        
        function removeCartItem(indexInCart) { // Renamed
            if (indexInCart >= 0 && indexInCart < cartItems.length) { 
                const removedCartItem = cartItems.splice(indexInCart, 1)[0]; // Renamed
                showToast(`Removed ${removedCartItem.item_description} from cart.`, 'info'); 
                updateCartDisplay(); 
                updateOrderSummary(); 
                updateCartItemCount(); 
            } 
        }
        
        function saveCartChanges() { 
            updateOrderSummary(); // This ensures the main form's hidden 'orders' input is updated
            closeCartModal(); 
            showToast('Order items confirmed from cart.', 'success');
        }

        $(document).ready(function() {
            setTimeout(function() {
                $(".alert").fadeOut("slow");
            }, 3000);
            
            $("#searchInput").on("input", function() { 
                const searchTerm = $(this).val().toLowerCase().trim(); // Renamed
                $(".orders-table tbody tr").each(function() { 
                    $(this).toggle($(this).text().toLowerCase().indexOf(searchTerm) > -1); 
                }); 
            });
            
            $(".search-btn").on("click", () => $("#searchInput").trigger("input")); // No change needed
            
            initializeDeliveryDatePicker("delivery_date_add_form", new Date().toISOString().split('T')[0]); // Initialize for add form
            toggleDeliveryAddress(true); // For add form
            generatePONumber(); // For add form
            
            // More specific event listener for closing overlays/modals
            $(window).on('click', function(event) {
                const target = $(event.target);
                if (target.is('.instructions-modal')) closeSpecialInstructions();
                if (target.is('.confirmation-modal')) { 
                    target.hide(); 
                    if (target.attr('id') === 'statusConfirmationModal') closeStatusConfirmation(); 
                }
                if (target.is('.overlay')) { // General overlay click
                    const overlayId = target.attr('id');
                    if (overlayId === 'addOrderOverlay') closeAddOrderForm(); 
                    else if (overlayId === 'inventoryOverlay') closeInventoryOverlay(); 
                    else if (overlayId === 'cartModal') closeCartModal();
                    else if (overlayId === 'orderDetailsModal') closeOrderDetailsModal();
                }
                // For modals that might not have the .overlay class directly on the backdrop
                if (target.is('.modal') && target.children('.modal-content, .edit-date-modal-content').length > 0 && !target.find('.modal-content, .edit-date-modal-content').has(event.target).length && !$(event.target).closest('.modal-content, .edit-date-modal-content').length) {
                    const modalId = target.attr('id');
                    if (modalId === 'statusModal') closeStatusModal();
                    else if (modalId === 'pendingStatusModal') closePendingStatusModal();
                    else if (modalId === 'rejectedStatusModal') closeRejectedStatusModal();
                    else if (modalId === 'editDateModal') closeEditDateModal();
                }
            });
             // Ensure custom address textarea updates the hidden delivery_address field for the add form
            $('#custom_address_add_form').on('input', function() { 
                if ($('#delivery_address_type_add_form').val() === 'custom') {
                    $('#delivery_address_hidden_add_form').val($(this).val());
                }
            });
        });
    </script>
    
    <script>
        function checkEmailSendingStatus() {
            fetch('/backend/check_email_status.php')
            .then(response => response.json().catch(() => ({ unsent_notifications: 0, message: "Error parsing email status response." })))
            .then(data => {
                if (data.unsent_notifications > 0) {
                    showToast(`${data.unsent_notifications} email notifications are pending to be sent.`, 'info');
                } else if (data.message && data.unsent_notifications === 0 && data.message !== "No pending notifications."){ // Log other messages if any
                    console.log("Email status check message:", data.message);
                }
            })
            .catch(error => {
                console.error("Error checking email status:", error);
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            checkEmailSendingStatus();
            setInterval(checkEmailSendingStatus, 180000); // 3 minutes
        });
    </script>
</body>
</html>