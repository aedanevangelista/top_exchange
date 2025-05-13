<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Orders');

$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

$allowed_columns = ['id', 'po_number', 'order_type', 'username', 'company', 'order_date', 'delivery_date', 'progress', 'total_amount', 'status'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'id';
}
if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
    $sort_direction = 'DESC';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_date']) && isset($_POST['po_number']) && isset($_POST['new_delivery_date'])) {
    $po_number_update = $_POST['po_number'];
    $new_delivery_date_update = $_POST['new_delivery_date'];
    
    $day_of_week_update = date('N', strtotime($new_delivery_date_update));
    $is_valid_day_update = ($day_of_week_update == 1 || $day_of_week_update == 3 || $day_of_week_update == 5);
    
    $stmt_get_order_for_date = $conn->prepare("SELECT order_date, username FROM orders WHERE po_number = ?");
    $stmt_get_order_for_date->bind_param("s", $po_number_update);
    $stmt_get_order_for_date->execute();
    $result_get_order_for_date = $stmt_get_order_for_date->get_result();
    $order_for_date_update = $result_get_order_for_date->fetch_assoc();
    $stmt_get_order_for_date->close();
    
    $order_date_obj_update = new DateTime($order_for_date_update['order_date']);
    $delivery_date_obj_update = new DateTime($new_delivery_date_update);
    $days_difference_update = $delivery_date_obj_update->diff($order_date_obj_update)->days;
    
    $is_valid_days_gap_update = ($days_difference_update >= 5);
    
    if ($is_valid_day_update && $is_valid_days_gap_update) {
        $stmt_update_date = $conn->prepare("UPDATE orders SET delivery_date = ? WHERE po_number = ?");
        $stmt_update_date->bind_param("ss", $new_delivery_date_update, $po_number_update);
        if ($stmt_update_date->execute()) {
            $username_for_email_update = $order_for_date_update['username'];
            
            $stmt_email_update = $conn->prepare("SELECT email FROM clients_accounts WHERE username = ?");
            $stmt_email_update->bind_param("s", $username_for_email_update);
            $stmt_email_update->execute();
            $result_email_update = $stmt_email_update->get_result();
            $user_data_email_update = $result_email_update->fetch_assoc();
            $stmt_email_update->close();
            
            if ($user_data_email_update && !empty($user_data_email_update['email'])) {
                $user_email_update = $user_data_email_update['email'];
                $subject_update = "Top Exchange Food Corp: Delivery Date Changed";
                $message_update = "Dear $username_for_email_update,\n\n";
                $message_update .= "The delivery date for your order (PO: $po_number_update) has been updated.\n";
                $message_update .= "New Delivery Date: " . date('F j, Y', strtotime($new_delivery_date_update)) . "\n\n";
                $message_update .= "If you have any questions regarding this change, please contact us.\n\n";
                $message_update .= "Thank you,\nTop Exchange Food Corp";
                $headers_update = "From: no-reply@topexchange.com";
                mail($user_email_update, $subject_update, $message_update, $headers_update);
            }
            $_SESSION['message'] = "Delivery date updated successfully for PO: " . htmlspecialchars($po_number_update);
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating delivery date for PO: " . htmlspecialchars($po_number_update);
            $_SESSION['message_type'] = "error";
        }
        $stmt_update_date->close();
    } else {
        if (!$is_valid_day_update) {
            $_SESSION['message'] = "Delivery date must be Monday, Wednesday, or Friday.";
        } else {
            $_SESSION['message'] = "Delivery date must be at least 5 days after the order date.";
        }
        $_SESSION['message_type'] = "error";
    }
    header("Location: orders.php" . (isset($_GET['sort']) ? "?sort=".htmlspecialchars($_GET['sort'])."&direction=".htmlspecialchars($_GET['direction']) : ""));
    exit();
}

$clients_list = [];
$clients_data_map = [];

$stmt_clients = $conn->prepare("SELECT username, company_address, company, email FROM clients_accounts WHERE status = 'active' ORDER BY username ASC");
if ($stmt_clients === false) {
    die('Prepare failed (clients): ' . htmlspecialchars($conn->error));
}
$stmt_clients->execute();
$result_clients = $stmt_clients->get_result();
while ($row_client = $result_clients->fetch_assoc()) {
    $clients_list[] = $row_client['username'];
    $clients_data_map[$row_client['username']] = [
        'company' => $row_client['company'],
        'company_address' => $row_client['company_address'],
        'email' => $row_client['email']
    ];
}
$stmt_clients->close();

$orders_list = [];
$sql_orders = "SELECT o.id, o.po_number, o.order_type, o.username, o.company, o.order_date, o.delivery_date, o.delivery_address, o.orders, o.total_amount, o.status, o.progress,
        o.special_instructions, o.payment_method, o.payment_status
        FROM orders o
        WHERE o.status IN ('Pending', 'Rejected') 
        OR (o.status = 'Active' AND o.progress < 100)
        OR (o.status = 'For Delivery' AND o.progress < 100) ";

$orderByClause = 'o.' . $sort_column;
$sql_orders .= " ORDER BY {$orderByClause} {$sort_direction}";

$stmt_orders = $conn->prepare($sql_orders);
if ($stmt_orders === false) {
     die('Prepare failed (orders): ' . htmlspecialchars($conn->error) . ' - SQL: ' . $sql_orders);
}
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();
if ($result_orders && $result_orders->num_rows > 0) {
    while ($row_order = $result_orders->fetch_assoc()) {
        $orders_list[] = $row_order;
    }
}
$stmt_orders->close();

function getSortUrl($column_name, $current_sort_column, $current_sort_direction) {
    $new_direction = ($column_name === $current_sort_column && $current_sort_direction === 'ASC') ? 'DESC' : 'ASC';
    $query_params = http_build_query(['sort' => $column_name, 'direction' => $new_direction]);
    return "?" . $query_params;
}

function getSortIcon($column_name, $current_sort_column, $current_sort_direction) {
    if ($column_name === $current_sort_column) {
        return ($current_sort_direction === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
    }
    return '<i class="fas fa-sort"></i>';
}

// PHP Helper for Delivery Date (not used by JS datepicker directly, but good for server-side checks if needed)
function getNextAvailableDeliveryDatePHP($baseDateStr = null, $minDaysAfter = 5) {
    $startDate = $baseDateStr ? new DateTime($baseDateStr) : new DateTime();
    $startDate->modify("+{$minDaysAfter} days"); 
    
    while (true) {
        $dayOfWeek = $startDate->format('N');
        if ($dayOfWeek == 1 || $dayOfWeek == 3 || $dayOfWeek == 5) {
            break;
        }
        $startDate->modify("+1 day");
    }
    return $startDate->format('Y-m-d');
}

function isValidDeliveryDay($date_str) {
    if (empty($date_str)) return false;
    $dayOfWeek = date('N', strtotime($date_str));
    return ($dayOfWeek == 1 || $dayOfWeek == 3 || $dayOfWeek == 5);
}

function isValidDeliveryGap($orderDate_str, $deliveryDate_str, $minDays = 5) {
    if (empty($orderDate_str) || empty($deliveryDate_str)) return false;
    try {
        $orderDateTime = new DateTime($orderDate_str);
        $deliveryDateTime = new DateTime($deliveryDate_str);
        $minDeliveryDateTime = clone $orderDateTime;
        $minDeliveryDateTime->modify("+{$minDays} days");
        return $deliveryDateTime >= $minDeliveryDateTime;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Top Exchange</title>
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .order-summary { margin-top: 20px; margin-bottom: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table tbody { display: block; max-height: 250px; overflow-y: auto; border: 1px solid #ddd; }
        .summary-table thead, .summary-table tbody tr { display: table; width: 100%; table-layout: fixed; }
        .summary-table thead { width: calc(100% - 17px)); }
        .summary-table th, .summary-table td { padding: 8px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-bottom: 1px solid #ddd; border-right: 1px solid #ddd; }
        .summary-table th:first-child, .summary-table td:first-child { border-left: 1px solid #ddd; }
        .summary-table th { background-color: #f8f9fa; font-weight: 600; border-top: 1px solid #ddd;}
        .summary-table th:nth-child(1), .summary-table td:nth-child(1) { width: 20%; }
        .summary-table th:nth-child(2), .summary-table td:nth-child(2) { width: 30%; }
        .summary-table th:nth-child(3), .summary-table td:nth-child(3) { width: 15%; }
        .summary-table th:nth-child(4), .summary-table td:nth-child(4) { width: 15%; text-align: right; }
        .summary-table th:nth-child(5), .summary-table td:nth-child(5) { width: 10%; text-align: center; }
        .summary-table th:nth-child(6), .summary-table td:nth-child(6) { width: 10%; text-align: center; }
        .summary-total { margin-top: 10px; text-align: right; font-weight: bold; border-top: 2px solid #ddd; padding-top: 10px; font-size: 1.1em; }
        .summary-quantity { width: 70px; max-width: 100%; text-align: center; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .download-btn { padding: 6px 12px; background-color: #17a2b8; color: white; border: none; border-radius: 20px; cursor: pointer; font-size: 12px; margin-left: 5px; transition: background-color 0.2s; }
        .download-btn:hover { background-color: #138496; }
        .download-btn i { margin-right: 5px; }
        .po-container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; }
        .po-header { text-align: center; margin-bottom: 30px; } .po-company { font-size: 22px; font-weight: bold; margin-bottom: 10px; }
        .po-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
        .po-details { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 12px; }
        .po-left, .po-right { width: 48%; } .po-detail-row { margin-bottom: 8px; } .po-detail-label { font-weight: bold; display: inline-block; width: 110px; }
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; }
        .po-table th, .po-table td { border: 1px solid #ddd; padding: 8px; text-align: left; } .po-table th { background-color: #f2f2f2; }
        .po-table td:nth-child(4), .po-table td:nth-child(5), .po-table td:nth-child(6) { text-align: right;}
        .po-total { text-align: right; font-weight: bold; font-size: 13px; margin-bottom: 30px; padding-top:10px; border-top: 1px solid #000;}
        .po-signature { display: flex; justify-content: space-between; margin-top: 50px; } .po-signature-block { width: 40%; text-align: center; font-size:12px; }
        .po-signature-line { border-bottom: 1px solid #000; margin-bottom: 10px; padding-top: 40px; }
        #pdfPreview { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 1070; overflow: auto; }
        .pdf-container { background-color: white; width: 90%; max-width: 850px; margin: 30px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.5); position: relative; }
        .close-pdf { position: absolute; top: 10px; right: 15px; font-size: 20px; background: none; border: none; cursor: pointer; color: #333; }
        .pdf-actions { text-align: center; margin-top: 20px; } .pdf-actions button { padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .instructions-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .instructions-modal-content { background-color: #fff; margin: 10% auto; padding: 0; border-radius: 8px; width: 90%; max-width: 600px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-height: 80vh; display: flex; flex-direction: column; }
        .instructions-header { background-color: #343a40; color: white; padding: 15px 20px; border-top-left-radius: 8px; border-top-right-radius: 8px; display:flex; justify-content:space-between; align-items:center; }
        .instructions-header h3 { margin: 0; font-size: 1.1em; } .instructions-po-number { font-size: 0.9em; opacity: 0.9; }
        .instructions-body { padding: 20px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa; flex-grow: 1; overflow-y: auto; }
        .instructions-body.empty { color: #6c757d; font-style: italic; text-align: center; padding: 40px 20px; }
        .instructions-footer { padding: 15px 20px; text-align: right; background-color: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #eee; }
        .close-instructions-btn { background-color: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9em; transition: background-color 0.2s; }
        .close-instructions-btn:hover { background-color: #5a6268; }
        .instructions-btn { padding: 5px 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background-color 0.2s; }
        .instructions-btn:hover { background-color: #0056b3; } .no-instructions { color: #6c757d; font-style: italic; font-size: 12px; }
        #contentToDownload { font-size: 14px; } #contentToDownload .po-table { font-size: 11px; } #contentToDownload .po-title { font-size: 16px; } #contentToDownload .po-company { font-size: 20px; } #contentToDownload .po-total { font-size: 12px; }
        .status-badge { padding: 0.3em 0.6em; border-radius: 0.25rem; font-size: 0.85em; font-weight: 600; display: inline-block; text-align: center; min-width: 85px; line-height: 1.2; }
        .status-active { background-color: #d1e7ff; color: #084298; border: 1px solid #a6cfff; } .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; } .status-delivery { background-color: #e2e3e5; color: #383d41; border: 1px solid #ced4da; }
        .status-completed { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .btn-info { font-size: 0.75em; opacity: 0.8; margin-top: 3px; display: block; }
        .raw-materials-container { overflow: visible; margin-bottom: 15px; } .raw-materials-container h3 { margin-top: 0; margin-bottom: 10px; color: #333; font-size: 1em; }
        .materials-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size:0.9em; }
        .materials-table tbody { display: block; max-height: 180px; overflow-y: auto; border: 1px solid #ddd; }
        .materials-table thead, .materials-table tbody tr { display: table; width: 100%; table-layout: fixed; }
        .materials-table th, .materials-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .materials-table thead { background-color: #f2f2f2; display: table; width: calc(100% - 17px)); table-layout: fixed; }
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
        .add-order-btn { display: inline-flex; align-items: center; background-color: #28a745; color: white; border: none; border-radius: 20px; padding: 10px 20px; cursor: pointer; font-size: 14px; transition: background-color 0.2s; white-space: nowrap; }
        .add-order-btn:hover { background-color: #218838; } .add-order-btn i { margin-right: 8px; }
        #special_instructions_textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-family: inherit; margin-bottom: 15px; min-height: 60px; }
        .confirmation-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: hidden;}
        .confirmation-content { background-color: #fff; margin: 15% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
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
        .inventory-table td:nth-child(4), .inventory-table td:nth-child(5) { text-align: center; }
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
        .edit-date-modal-content { background-color: #fff; margin: 15% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; text-align: left; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
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
        .orders-table td:nth-child(7), .orders-table td:nth-child(9), .orders-table td:nth-child(12) { text-align: center; }
        .orders-table td:nth-child(9) { font-weight: 500; }
        .orders-table thead th { background-color: #343a40; color: white; font-weight: 600; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #23272b; }
        .orders-table tbody tr:hover { background-color: #f8f9fa; }
        .orders-table th.sortable a { color: inherit; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
        .orders-table th.sortable a:hover { color: #ced4da; }
        .orders-table th.sortable a i { margin-left: 8px; color: #adb5bd; } .orders-table th.sortable a:hover i { color: #fff; }
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .action-buttons button, .view-orders-btn { padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px; transition: background-color 0.2s, box-shadow 0.2s; white-space: nowrap; }
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
        .modal-status-btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.95em; margin: 5px; flex-grow: 1; text-align: center; transition: background-color 0.2s, box-shadow 0.2s; }
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
        .cart-table td:nth-child(4), .cart-table td:nth-child(5) { text-align: center; }
        .cart-total { text-align: right; margin-top:15px; margin-bottom: 20px; font-weight: bold; font-size: 1.2em; }
        .form-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
        .cancel-btn, .back-btn, .save-btn, .confirm-btn { border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; font-weight:500; transition: background-color 0.2s, box-shadow 0.2s; }
        .cancel-btn, .back-btn { background-color: #6c757d; color: white; } .cancel-btn:hover, .back-btn:hover { background-color: #5a6268; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .save-btn, .confirm-btn { background-color: #007bff; color: white; } .save-btn:hover, .confirm-btn:hover { background-color: #0056b3; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .save-btn i, .confirm-btn i, .cancel-btn i, .back-btn i { margin-right: 6px; }
        .order-form .form-group { margin-bottom: 18px; }
        .order-form label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #495057; }
        .order-form input[type="text"], .order-form input[type="date"], .order-form select, .order-form textarea { 
            width: 100%; padding: 10px 12px; margin-bottom: 0;
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
            <h1>Manage Orders</h1>
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
                    <?php if (count($orders_list) > 0): ?>
                        <?php foreach ($orders_list as $order_item): ?>
                            <tr data-current-status="<?= htmlspecialchars($order_item['status']) ?>" data-po-number="<?= htmlspecialchars($order_item['po_number']) ?>">
                                <td><?= htmlspecialchars($order_item['po_number']) ?></td>
                                <td><?= htmlspecialchars($order_item['order_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order_item['username'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($order_item['company'] ?? ($clients_data_map[$order_item['username']]['company'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(date("M d, Y", strtotime($order_item['order_date']))) ?></td>
                                <td>
                                    <?= ($order_item['order_type'] === 'Walk In' || empty($order_item['delivery_date'])) ? 'N/A (Walk-In)' : htmlspecialchars(date("M d, Y", strtotime($order_item['delivery_date']))) ?>
                                    <?php if ($order_item['order_type'] !== 'Walk In' && !empty($order_item['delivery_date'])): ?>
                                    <button class="edit-date-btn" onclick="openEditDateModal('<?= htmlspecialchars($order_item['po_number']) ?>', '<?= htmlspecialchars($order_item['delivery_date']) ?>', '<?= htmlspecialchars($order_item['order_date']) ?>')" aria-label="Edit Delivery Date">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order_item['status'] === 'Active' || $order_item['status'] === 'For Delivery'): ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $order_item['progress'] ?? 0 ?>%"></div>
                                        <div class="progress-text"><?= $order_item['progress'] ?? 0 ?>%</div>
                                    </div>
                                    <?php else: ?>
                                        <span class="status-badge <?= strtolower(htmlspecialchars($order_item['status'])) ?>-progress"><?= htmlspecialchars($order_item['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order_item['status'] === 'Active'): ?>
                                        <button class="view-orders-btn" onclick="viewOrderDetails('<?= htmlspecialchars($order_item['po_number']) ?>')"><i class="fas fa-tasks"></i> Manage Items</button>
                                    <?php else: ?>
                                        <button class="view-orders-btn" onclick="viewOrderInfo('<?= htmlspecialchars(addslashes($order_item['orders'])) ?>', '<?= htmlspecialchars($order_item['status']) ?>', '<?= htmlspecialchars($order_item['po_number']) ?>')"><i class="fas fa-eye"></i> View Items</button>
                                    <?php endif; ?>
                                </td>
                                <td>PHP <?= htmlspecialchars(number_format($order_item['total_amount'], 2)) ?></td>
                                <td>
                                    <?php if (!empty($order_item['special_instructions'])): ?>
                                        <button class="instructions-btn" onclick="viewSpecialInstructions('<?= htmlspecialchars(addslashes($order_item['po_number'])) ?>', '<?= htmlspecialchars(addslashes($order_item['special_instructions'])) ?>')"><i class="fas fa-comment-alt"></i> View</button>
                                    <?php else: ?>
                                        <span class="no-instructions">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class_name = '';
                                    switch ($order_item['status']) {
                                        case 'Active': $status_class_name = 'status-active'; break;
                                        case 'Pending': $status_class_name = 'status-pending'; break;
                                        case 'Rejected': $status_class_name = 'status-rejected'; break;
                                        case 'For Delivery': $status_class_name = 'status-delivery'; break;
                                        case 'Completed': $status_class_name = 'status-completed'; break;
                                        default: $status_class_name = 'status-default'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $status_class_name ?>"><?= htmlspecialchars($order_item['status']) ?></span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($order_item['status'] === 'Pending'): ?>
                                        <button class="status-btn" onclick="confirmPendingStatusChange('<?= htmlspecialchars($order_item['po_number']) ?>', '<?= htmlspecialchars($order_item['username'] ?? 'Walk-In') ?>', '<?= htmlspecialchars(addslashes($order_item['orders'])) ?>', 'Pending')"><i class="fas fa-cogs"></i> Manage Status</button>
                                    <?php elseif ($order_item['status'] === 'Active' && ($order_item['progress'] ?? 0) < 100): ?>
                                        <button class="status-btn" onclick="confirmStatusChange('<?= htmlspecialchars($order_item['po_number']) ?>', '<?= htmlspecialchars($order_item['username'] ?? 'Walk-In') ?>', 'Active')"><i class="fas fa-sync-alt"></i> Update Status</button>
                                    <?php elseif ($order_item['status'] === 'Rejected'): ?>
                                        <button class="status-btn" onclick="confirmRejectedStatusChange('<?= htmlspecialchars($order_item['po_number']) ?>', '<?= htmlspecialchars($order_item['username'] ?? 'Walk-In') ?>', 'Rejected')"><i class="fas fa-undo"></i> Review Rejected</button>
                                    <?php endif; ?>
                                    <button class="download-btn" onclick="confirmDownloadPO('<?= htmlspecialchars($order_item['po_number']) ?>', '<?= htmlspecialchars($order_item['username'] ?? 'Walk-In') ?>', '<?= htmlspecialchars($order_item['company'] ?? ($clients_data_map[$order_item['username']]['company'] ?? 'N/A')) ?>', '<?= htmlspecialchars($order_item['order_date']) ?>', '<?= htmlspecialchars($order_item['delivery_date'] ?? '') ?>', '<?= htmlspecialchars($order_item['delivery_address']) ?>', '<?= htmlspecialchars(addslashes($order_item['orders'])) ?>', '<?= htmlspecialchars($order_item['total_amount']) ?>', '<?= htmlspecialchars(addslashes($order_item['special_instructions'] ?? '')) ?>', '<?= htmlspecialchars($order_item['order_type']) ?>')"><i class="fas fa-file-pdf"></i> Invoice</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="no-orders">No orders found matching criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <div id="pdfPreview"><div class="pdf-container"><button class="close-pdf" onclick="closePDFPreview()"><i class="fas fa-times"></i></button><div id="contentToDownload"><div class="po-container"><div class="po-header"><div class="po-company" id="printCompany"></div><div class="po-title">Sales Invoice</div></div><div class="po-details"><div class="po-left"><div class="po-detail-row"><span class="po-detail-label">PO Number:</span> <span id="printPoNumber"></span></div><div class="po-detail-row"><span class="po-detail-label">Client/Company:</span> <span id="printUsername"></span></div><div class="po-detail-row"><span class="po-detail-label">Address:</span> <span id="printDeliveryAddress"></span></div></div><div class="po-right"><div class="po-detail-row"><span class="po-detail-label">Order Date:</span> <span id="printOrderDate"></span></div><div class="po-detail-row" id="printDeliveryDateRow"><span class="po-detail-label">Delivery Date:</span> <span id="printDeliveryDate"></span></div></div></div><div id="printInstructionsSection" style="margin-bottom: 20px; display: none; font-size:12px;"><strong>Special Instructions:</strong><div id="printSpecialInstructions" style="white-space: pre-wrap; word-wrap: break-word; padding: 5px; border: 1px solid #eee; margin-top:5px; border-radius:4px;"></div></div><table class="po-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Total</th></tr></thead><tbody id="printOrderItems"></tbody></table><div class="po-total">Grand Total: PHP <span id="printTotalAmount"></span></div><div class="po-signature"><div class="po-signature-block"><div class="po-signature-line"></div>Prepared by</div><div class="po-signature-block"><div class="po-signature-line"></div>Received by / Signature</div></div></div></div><div class="pdf-actions"><button class="download-pdf-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button></div></div></div>

    <div id="specialInstructionsModal" class="instructions-modal"><div class="instructions-modal-content"><div class="instructions-header"><h3>Special Instructions</h3><button class="close-instructions-btn" onclick="closeSpecialInstructions()" style="background:none; border:none; color:white; font-size:1.2em; padding:0;">&times;</button></div><div class="instructions-body" id="instructionsContent"></div><div class="instructions-footer"><button class="close-instructions-btn" onclick="closeSpecialInstructions()">Close</button></div></div></div>

    <div id="orderDetailsModal" class="overlay"><div class="overlay-content"><div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-tasks"></i> Order Item Details (<span id="orderStatusView"></span>)</h2><button type="button" class="cancel-btn" onclick="closeOrderDetailsModal()" style="padding: 8px 15px; font-size: 13px;"><i class="fas fa-times"></i> Close</button></div> <div id="overall-progress-info" style="margin-bottom: 15px; display: none;"><strong>Overall Order Progress:</strong><div class="progress-bar-container" style="margin-top: 5px; height:22px;"><div class="progress-bar" id="overall-progress-bar" style="width: 0%; line-height:22px; font-size:12px;"></div><div class="progress-text" id="overall-progress-text" style="line-height:22px; font-size:12px;">0%</div></div></div><div class="order-details-container"><table class="order-details-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Price</th><th style="text-align:center;">Qty</th><th id="status-header-cell" style="text-align:center;">Item Status/Progress</th></tr></thead><tbody id="orderDetailsBody"></tbody></table></div><div class="order-details-footer"><div class="total-amount" id="orderTotalAmount">Total: PHP 0.00</div></div><div class="form-buttons"><button type="button" class="back-btn" onclick="closeOrderDetailsModal()"><i class="fas fa-arrow-left"></i> Back to Orders</button><button type="button" class="save-btn save-progress-btn" onclick="confirmSaveProgress()" style="display:none;"><i class="fas fa-save"></i> Save Item Progress</button></div></div></div>
    
    <div id="statusModal" class="modal"><div class="modal-content"><h2>Change Order Status</h2><p id="statusMessage"></p><div class="status-buttons"><button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Set to Pending<div class="btn-info">(Returns stock to inventory)</div></button><button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Set to Rejected<div class="btn-info">(Returns stock to inventory)</div></button></div><div class="modal-footer"><button type="button" onclick="closeStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div></div></div>
    <div id="rejectedStatusModal" class="modal"><div class="modal-content"><h2>Change Rejected Order Status</h2><p id="rejectedStatusMessage"></p><div class="status-buttons"><button onclick="confirmStatusAction('Pending')" class="modal-status-btn pending"><i class="fas fa-clock"></i> Set to Pending<div class="btn-info">(Order will be re-evaluated)</div></button></div><div class="modal-footer"><button type="button" onclick="closeRejectedStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div></div></div>
    <div id="pendingStatusModal" class="modal"><div class="modal-content"><h2>Change Pending Order Status</h2><p id="pendingStatusMessage"></p><div id="rawMaterialsContainer" class="raw-materials-container" style="margin-top:15px;"></div><div class="status-buttons"><button id="activeStatusBtn" onclick="confirmStatusAction('Active')" class="modal-status-btn active" disabled><i class="fas fa-check-circle"></i> Set to Active<div class="btn-info">(Deducts stock from inventory)</div></button><button onclick="confirmStatusAction('Rejected')" class="modal-status-btn rejected"><i class="fas fa-times-circle"></i> Set to Rejected</button></div><div class="modal-footer"><button type="button" onclick="closePendingStatusModal()" class="modal-cancel-btn"><i class="fas fa-times"></i> Cancel</button></div></div></div>

    <div id="editDateModal" class="modal"><div class="edit-date-modal-content"><div class="edit-date-modal-header"><h3>Edit Delivery Date</h3><button class="edit-date-close" onclick="closeEditDateModal()">&times;</button></div><form id="editDateForm" method="POST" class="edit-date-form" onsubmit="return validateEditDateForm(event)"><input type="hidden" id="edit_po_number" name="po_number"><div class="form-group"><label for="current_delivery_date">Current Delivery Date:</label><input type="text" id="current_delivery_date" readonly></div><div class="form-group"><label for="new_delivery_date_input">New Delivery Date:</label><input type="text" id="new_delivery_date_input" name="new_delivery_date" autocomplete="off" required></div><div class="edit-date-note"><i class="fas fa-info-circle"></i> Delivery dates must be Monday, Wednesday, or Friday, and at least 5 days after the order date (<span id="edit_order_date_display"></span>).</div><div class="edit-date-footer"><input type="hidden" name="update_delivery_date" value="1"><button type="submit" class="edit-date-save-btn"><i class="fas fa-save"></i> Save Changes</button></div></form></div></div>

    <div id="addOrderOverlay" class="overlay">
        <div class="overlay-content">
            <div class="overlay-header">
                 <h2 class="overlay-title"><i class="fas fa-cart-plus"></i> Create New Order</h2>
                 <button type="button" class="cancel-btn" onclick="closeAddOrderForm()" style="padding: 8px 15px; font-size: 13px;"><i class="fas fa-times"></i> Close</button>
            </div>
            <form id="addOrderForm" method="POST" class="order-form">
                <div class="form-group">
                    <label for="order_type_selection">Order Type:</label>
                    <select id="order_type_selection" name="order_type_selection_display" onchange="toggleOrderFormFields()" class="form-control">
                        <option value="" disabled selected>--- Select Order Type ---</option>
                        <option value="Online">Online Order (Existing Client)</option>
                        <option value="Walk In">Walk-In / New Client</option>
                    </select>
                    <input type="hidden" name="order_type" id="order_type_hidden_for_submit">
                </div>

                <div id="onlineSpecificInputs" style="display:none;">
                    <div class="form-group">
                        <label for="username_online_select">Client Username:</label>
                        <select id="username_online_select" name="username_online" onchange="handleOnlineUserChange()" class="form-control">
                            <option value="" disabled selected>--- Select Client Username ---</option>
                            <?php foreach ($clients_list as $client_username_item): ?>
                                <option value="<?= htmlspecialchars($client_username_item) ?>"
                                        data-company="<?= htmlspecialchars($clients_data_map[$client_username_item]['company'] ?? '') ?>"
                                        data-company-address="<?= htmlspecialchars($clients_data_map[$client_username_item]['company_address'] ?? '') ?>">
                                    <?= htmlspecialchars($client_username_item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="online_company_display">Company (from client profile):</label>
                        <input type="text" id="online_company_display" class="form-control" readonly>
                    </div>
                </div>

                <div id="walkInSpecificInputs" style="display:none;">
                    <div class="form-group">
                        <label for="walk_in_name_company_input">Full Name / Company Name (for Walk-In):</label>
                        <input type="text" id="walk_in_name_company_input" name="walk_in_name_company_input" class="form-control" placeholder="Enter client's full name or company name">
                    </div>
                </div>
                
                <div id="commonOrderFields" style="display:none;">
                    <div class="form-group">
                        <label for="order_date_input">Order Date:</label>
                        <input type="text" id="order_date_input" name="order_date" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group" id="delivery_date_form_group" style="display:none;">
                        <label for="delivery_date_input">Requested Delivery Date:</label>
                        <input type="text" id="delivery_date_input" name="delivery_date" class="form-control" autocomplete="off" placeholder="Select a Mon, Wed, or Fri">
                         <small class="form-text text-muted">Deliveries only on Mon, Wed, Fri. Min. 5 days from order date.</small>
                    </div>

                    <div class="form-group" id="delivery_address_type_form_group" style="display:none;">
                        <label for="delivery_address_type_select">Delivery Address Type:</label>
                        <select id="delivery_address_type_select" name="delivery_address_type_display" onchange="toggleDeliveryAddressOptions()" class="form-control">
                            <option value="custom" selected>Enter Custom Address</option>
                            <option value="company" id="company_address_option_for_delivery" style="display:none;">Use Client's Registered Company Address</option>
                        </select>
                    </div>
                    <div id="company_address_container_div" style="display: none;" class="form-group">
                        <label for="company_address_display_field">Registered Company Address (Auto-filled):</label>
                        <input type="text" id="company_address_display_field" name="company_address_display" class="form-control" readonly>
                    </div>
                    <div id="custom_address_container_div" class="form-group" style="display:none;">
                        <label for="custom_address_input_field" id="custom_address_label">Custom Delivery Address:</label>
                        <textarea id="custom_address_input_field" name="custom_address_input" rows="3" class="form-control" placeholder="Enter complete address"></textarea>
                    </div>
                    <input type="hidden" name="delivery_address" id="delivery_address_for_submit">

                     <div class="form-group">
                        <label for="special_instructions_input">Special Instructions / Notes:</label>
                        <textarea id="special_instructions_input" name="special_instructions" rows="3" class="form-control" placeholder="e.g., Contact person, landmark, preferred time (no guarantee)"></textarea>
                    </div>
                    <div class="centered-button">
                        <button type="button" class="open-inventory-btn" onclick="openInventoryOverlay()"><i class="fas fa-boxes"></i> Select Products to Order</button>
                    </div>
                    <div class="order-summary">
                        <h3>Order Summary <span id="orderSummaryItemCount">(0 items)</span></h3>
                        <table class="summary-table">
                            <thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:right;">Price</th><th style="text-align:center;">Qty</th><th style="text-align:center;">Action</th></tr></thead>
                            <tbody id="summaryBody"><tr><td colspan="6" style="text-align:center; padding: 15px; color: #6c757d;">No products selected yet.</td></tr></tbody>
                        </table>
                        <div class="summary-total">Order Total: <span class="summary-total-amount">PHP 0.00</span></div>
                    </div>
                </div>
                
                <input type="hidden" name="po_number" id="po_number_for_submit">
                <input type="hidden" name="orders" id="orders_json_for_submit">
                <input type="hidden" name="total_amount" id="total_amount_for_submit">
                <input type="hidden" name="company_name_final" id="company_name_final_for_submit">
                
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddOrderForm()"><i class="fas fa-times"></i> Cancel Order Creation</button>
                    <button type="button" class="save-btn" onclick="confirmAddOrder()" id="confirmAddOrderBtn" style="display:none;"><i class="fas fa-check-circle"></i> Review & Confirm Order</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm New Order</div><div class="confirmation-message">Are you sure you want to add this order? Please review all details.</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No, Go Back</button><button class="confirm-yes" onclick="submitAddOrder()">Yes, Add Order</button></div></div></div>
    <div id="saveProgressConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Save Progress</div><div class="confirmation-message">Save current item progress for this order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeSaveProgressConfirmation()">No</button><button class="confirm-yes" onclick="saveProgressChanges()">Yes, Save</button></div></div></div>
    <div id="statusConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Status Change</div><div class="confirmation-message" id="statusConfirmationMessage"></div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeStatusConfirmation()">No</button><button class="confirm-yes" onclick="executeStatusChange()">Yes, Confirm</button></div></div></div>
    <div id="downloadConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Download Invoice</div><div class="confirmation-message">Download Sales Invoice PDF for this order?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeDownloadConfirmation()">No</button><button class="confirm-yes" onclick="downloadPODirectly()">Yes, Download</button></div></div></div>

    <div id="inventoryOverlay" class="overlay"><div class="overlay-content"><div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-store"></i> Select Products from Inventory</h2><button class="cart-btn" onclick="window.openCartModal()" style="font-size: 13px; padding: 8px 12px;"><i class="fas fa-shopping-cart"></i> View Selected Items (<span id="cartItemCountNav">0</span>)</button></div><div class="inventory-filter-section"><input type="text" id="inventorySearch" class="form-control" placeholder="Search products by name or description..."><select id="inventoryFilter" class="form-control"><option value="all">All Categories</option></select></div><div class="inventory-table-container"><table class="inventory-table"><thead><tr><th>Category</th><th>Product Name</th><th>Packaging</th><th style="text-align:center;">Price</th><th style="text-align:center;">Quantity</th><th style="text-align:center;">Action</th></tr></thead><tbody class="inventory"></tbody></table></div><div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeInventoryOverlay()"><i class="fas fa-arrow-left"></i> Back to Order Form</button><button type="button" class="save-btn" onclick="closeInventoryOverlay()"><i class="fas fa-check"></i> Done Selecting Products</button></div></div></div>

    <div id="cartModal" class="overlay"><div class="overlay-content"><div class="overlay-header"><h2 class="overlay-title"><i class="fas fa-shopping-cart"></i> Review Selected Products</h2><button type="button" class="cancel-btn" onclick="closeCartModal()" style="padding: 8px 15px; font-size: 13px;"><i class="fas fa-times"></i> Close</button></div><div class="cart-table-container"><table class="cart-table"><thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th style="text-align:center;">Price</th><th style="text-align:center;">Quantity</th><th style="text-align:center;">Action</th></tr></thead><tbody class="cart"><tr class="no-products-in-cart-row"><td colspan="6" style="text-align:center; padding:20px; color:#6c757d;">No products currently selected.</td></tr></tbody></table></div><div class="cart-total" style="text-align: right; margin-top:15px; margin-bottom: 20px; font-weight: bold; font-size: 1.2em;">Total Amount: <span class="total-amount-cart">PHP 0.00</span></div><div class="form-buttons" style="margin-top: 20px;"><button type="button" class="back-btn" onclick="closeCartModal()"><i class="fas fa-arrow-left"></i> Continue Selecting</button><button type="button" class="save-btn" onclick="saveCartChangesAndClose()"><i class="fas fa-check-double"></i> Confirm These Items</button></div></div></div>

    <script>
        let currentPoNumber = '';
        let currentOrderOriginalStatus = '';
        let currentOrderItemsData = [];
        let completedItemsIndices = [];
        let itemQuantityProgressData = {};
        let itemProgressPercentagesMap = {};
        let itemContributionsToOverall = {};
        let currentOverallProgress = 0;
        let currentPODataForPDF = null;
        let currentSelectedStatus = '';
        let poDownloadDataStore = null;
        let currentCartItems = [];
        let currentEditingOrderDate = '';
        
        const clientsDataFromPHP = <?php echo json_encode($clients_data_map); ?>;

        function showToast(message, type = 'info', duration = 3000) {
            const toastContainer = document.getElementById('toast-container');
            if (!toastContainer) { console.error("Toast container #toast-container not found!"); return; }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-times-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            toast.innerHTML = `<div class="toast-content"><i class="fas ${iconClass}"></i><div class="message">${message}</div></div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
        }

        function formatWeight(weightInGrams) {
            if (isNaN(parseFloat(weightInGrams))) return '0 g';
            if (weightInGrams >= 1000) return (weightInGrams / 1000).toFixed(2).replace(/\.00$/, '') + ' kg';
            return (parseFloat(weightInGrams).toFixed(2).replace(/\.00$/, '')) + ' g';
        }

        // JavaScript helper function for delivery date calculation
        function getNextAvailableDeliveryDate(baseDateStr = null, minDaysAfter = 5) {
            let startDate;
            if (baseDateStr) {
                // Ensure baseDateStr is treated as local date by appending time if not present
                startDate = new Date(baseDateStr.includes('T') ? baseDateStr : baseDateStr + 'T00:00:00');
            } else {
                startDate = new Date();
                startDate.setHours(0, 0, 0, 0); // Normalize to start of today
            }
            
            startDate.setDate(startDate.getDate() + minDaysAfter);

            while (true) {
                const dayOfWeek = startDate.getDay(); // 0 (Sun) to 6 (Sat)
                if (dayOfWeek === 1 || dayOfWeek === 3 || dayOfWeek === 5) { // Monday, Wednesday, or Friday
                    break;
                }
                startDate.setDate(startDate.getDate() + 1);
            }

            const year = startDate.getFullYear();
            const month = (startDate.getMonth() + 1).toString().padStart(2, '0');
            const day = startDate.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        }


        function confirmStatusChange(poNumber, username, originalStatus) { currentPoNumber = poNumber; currentOrderOriginalStatus = originalStatus; $('#statusMessage').text(`Change status for order ${poNumber} (Client: ${username || 'N/A'})? Current: ${originalStatus}.`); $('#statusModal').show(); }
        function confirmRejectedStatusChange(poNumber, username, originalStatus) { currentPoNumber = poNumber; currentOrderOriginalStatus = originalStatus; $('#rejectedStatusModal').data('po_number', poNumber); $('#rejectedStatusMessage').text(`Order ${poNumber} (Client: ${username || 'N/A'}) is currently Rejected. Change status?`); $('#rejectedStatusModal').show(); }
        function confirmPendingStatusChange(poNumber, username, ordersJsonString, originalStatus) {
            currentPoNumber = poNumber; currentOrderOriginalStatus = originalStatus; $('#pendingStatusModal').data('po_number', poNumber); 
            $('#pendingStatusMessage').text(`Order ${poNumber} (Client: ${username || 'N/A'}) is Pending. Review inventory and change status:`);
            const materialContainer = $('#rawMaterialsContainer'); materialContainer.html('<p style="text-align:center; padding:10px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory status...</p>'); 
            $('#activeStatusBtn').prop('disabled', true);
            $('#pendingStatusModal').show();
            try {
                if (!ordersJsonString || ordersJsonString.trim() === "") throw new Error("Order items data is missing or empty.");
                JSON.parse(ordersJsonString);
                $.ajax({
                    url: '/backend/check_raw_materials.php', type: 'POST', data: { orders: ordersJsonString, po_number: poNumber }, dataType: 'json',
                    success: function(response) {
                        if (response && typeof response === 'object') {
                            if (response.success) {
                                const needsMfg = displayFinishedProducts(response.finishedProducts, '#rawMaterialsContainer');
                                if (needsMfg && response.materials) { 
                                    displayRawMaterials(response.materials, '#rawMaterialsContainer #raw-materials-section'); 
                                } else if (needsMfg) { 
                                    $('#rawMaterialsContainer #raw-materials-section').html('<h3>Raw Materials Required</h3><p>Information currently unavailable.</p>'); 
                                } else if (!needsMfg && response.finishedProducts && Object.keys(response.finishedProducts).length > 0) {
                                     materialContainer.append('<p class="materials-status status-sufficient" style="margin-top:10px;">All required finished products are in stock.</p>'); 
                                     $('#rawMaterialsContainer #raw-materials-section').empty();
                                } else if (!response.finishedProducts && !response.materials) { 
                                    materialContainer.html('<h3>Inventory Status</h3><p>No specific inventory details were returned.</p>'); 
                                }
                                updatePendingOrderActionStatus(response);
                            } else { 
                                materialContainer.html(`<h3 style='color:red;'>Inventory Check Failed</h3><p style="color:red;">${response.message || 'Could not verify inventory details.'}</p><p>You can still attempt to change status, but inventory might be affected.</p>`); 
                                $('#activeStatusBtn').prop('disabled', false);
                            }
                        } else {
                             materialContainer.html(`<h3 style='color:red;'>Inventory Check Error</h3><p style="color:red;">Received an invalid response from the server.</p>`);
                             $('#activeStatusBtn').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) { 
                        let errorMsg = `Could not check inventory: ${error || 'Server communication error'}.`; 
                        if (status === 'parsererror') { errorMsg = `Could not check inventory: Invalid data format from server.`; } 
                        materialContainer.html(`<h3 style='color:red;'>Server Error</h3><p style="color:red;">${errorMsg}</p><p>Status change may be attempted, but inventory status is unconfirmed.</p>`); 
                        $('#activeStatusBtn').prop('disabled', false);
                    }
                });
            } catch (e) { 
                materialContainer.html(`<h3 style='color:red;'>Data Error</h3><p style="color:red;">Error processing order items: ${e.message}</p><p>Status change may be attempted, but inventory status is unconfirmed.</p>`); 
                $('#activeStatusBtn').prop('disabled', false);
            }
        }
        function confirmStatusAction(newStatus) {
            currentSelectedStatus = newStatus; 
            let confirmationMsg = `Are you sure you want to change the order status to "${currentSelectedStatus}"?`;
            if (currentSelectedStatus === 'Active') { confirmationMsg += ' This action will attempt to deduct the required stock from inventory.'; } 
            else if (currentOrderOriginalStatus === 'Active' && (currentSelectedStatus === 'Pending' || currentSelectedStatus === 'Rejected')) { confirmationMsg += ' This action will attempt to return any previously deducted stock to inventory.'; }
            $('#statusConfirmationMessage').text(confirmationMsg); 
            $('#statusConfirmationModal').show();
            if ($('#statusModal').is(':visible')) $('#statusModal').hide();
            if ($('#pendingStatusModal').is(':visible')) $('#pendingStatusModal').hide();
            if ($('#rejectedStatusModal').is(':visible')) $('#rejectedStatusModal').hide();
        }
        function closeStatusConfirmation() { 
            $('#statusConfirmationModal').hide(); 
            if (currentOrderOriginalStatus === 'Pending') $('#pendingStatusModal').show(); 
            else if (currentOrderOriginalStatus === 'Rejected') $('#rejectedStatusModal').show(); 
            else if (currentOrderOriginalStatus === 'Active') $('#statusModal').show();
            currentSelectedStatus = '';
        }
        function executeStatusChange() {
            $('#statusConfirmationModal').hide(); 
            let deductMaterialsFlag = (currentSelectedStatus === 'Active'); 
            let returnMaterialsFlag = (currentOrderOriginalStatus === 'Active' && (currentSelectedStatus === 'Pending' || currentSelectedStatus === 'Rejected'));
            updateOrderStatus(currentSelectedStatus, deductMaterialsFlag, returnMaterialsFlag);
        }
        function updateOrderStatus(newStatus, deductMaterials, returnMaterials) {
            const formData = new FormData(); 
            formData.append('po_number', currentPoNumber); 
            formData.append('status', newStatus); 
            formData.append('deduct_materials', deductMaterials ? '1' : '0'); 
            formData.append('return_materials', returnMaterials ? '1' : '0');
            if (newStatus === 'For Delivery') { formData.append('progress', '100'); }

            fetch('/backend/update_order_status.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => { 
                try { 
                    const jsonData = JSON.parse(text); 
                    if (!response.ok) throw new Error(jsonData.message || jsonData.error || `Server error: ${response.status} ${response.statusText}`); 
                    return jsonData; 
                } catch (e) { 
                    throw new Error('Server returned an invalid response format. Please check server logs.'); 
                } 
            }))
            .then(data => { 
                if (data.success) { 
                    let successMessage = `Order status successfully updated to "${newStatus}".`; 
                    if (deductMaterials && data.inventory_adjusted) successMessage += ' Inventory has been adjusted.'; 
                    if (returnMaterials && data.inventory_adjusted) successMessage += ' Inventory has been reverted.';
                    showToast(successMessage, 'success', 4000); 
                    sendStatusNotificationEmail(currentPoNumber, newStatus); 
                    setTimeout(() => { window.location.reload(); }, 1800); 
                } else { 
                    throw new Error(data.message || 'An unknown error occurred while updating status.'); 
                } 
            })
            .catch(error => { 
                showToast(`Error updating status: ${error.message}`, 'error', 5000); 
            })
            .finally(() => { 
                closeRelevantStatusModals(); 
                currentPoNumber = ''; currentOrderOriginalStatus = ''; currentSelectedStatus = '';
            });
        }
        function sendStatusNotificationEmail(poNumber, newStatus) { 
            fetch('/backend/send_status_notification.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', }, 
                body: `po_number=${encodeURIComponent(poNumber)}&new_status=${encodeURIComponent(newStatus)}` 
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) console.log("Status notification email successfully queued/sent for PO:", poNumber, "New Status:", newStatus);
                else console.warn("Failed to send status notification email:", data.message);
            })
            .catch(error => console.error("Error sending status notification email AJAX:", error)); 
        }
        function closeStatusModal() { $('#statusModal').hide(); currentSelectedStatus = ''; currentOrderOriginalStatus = ''; }
        function closeRejectedStatusModal() { $('#rejectedStatusModal').hide(); currentSelectedStatus = ''; currentOrderOriginalStatus = ''; $('#rejectedStatusModal').removeData('po_number'); }
        function closePendingStatusModal() { $('#pendingStatusModal').hide(); currentSelectedStatus = ''; currentOrderOriginalStatus = ''; $('#pendingStatusModal').removeData('po_number'); $('#rawMaterialsContainer').html('<p style="text-align:center; padding:10px;"><i class="fas fa-spinner fa-spin"></i> Loading inventory status...</p>'); $('#activeStatusBtn').prop('disabled', true); }
        function closeRelevantStatusModals() { closeStatusModal(); closePendingStatusModal(); closeRejectedStatusModal(); }

        function displayFinishedProducts(productsData, containerSelector) { 
            const container = $(containerSelector); 
            if (!container.length) { return false; }
            let html = '<h4>Finished Product Stock Check:</h4>'; 
            if (!productsData || Object.keys(productsData).length === 0) { 
                html += '<p class="text-muted">No specific finished product stock information available for this order.</p>'; 
                container.html(html).append('<div id="raw-materials-section" style="margin-top:10px;"></div>'); 
                return false;
            } 
            html += `<table class="materials-table"><thead><tr><th>Product</th><th style="text-align:right;">In Stock</th><th style="text-align:right;">Required</th><th>Status</th></tr></thead><tbody>`; 
            let anyProductInsufficient = false;
            Object.keys(productsData).forEach(productName => { 
                const data = productsData[productName]; 
                const available = parseInt(data.available) || 0; 
                const required = parseInt(data.required) || 0; 
                const isSufficient = data.sufficient; 
                if (!isSufficient) anyProductInsufficient = true;
                html += `<tr><td>${productName}</td><td style="text-align:right;">${available}</td><td style="text-align:right;">${required}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? '<i class="fas fa-check-circle"></i> In Stock' : `<i class="fas fa-times-circle"></i> Short by ${data.shortfall || required}`}</td></tr>`; 
            }); 
            html += `</tbody></table>`; 
            container.html(html); 
            if (anyProductInsufficient) {
                container.append('<div id="raw-materials-section" style="margin-top:15px;"><h4>Raw Materials for Manufacturing (if applicable):</h4><p class="text-muted">Loading details if manufacturing is needed...</p></div>');
            } else {
                 container.append('<div id="raw-materials-section" style="margin-top:15px;"></div>');
            }
            return anyProductInsufficient;
        }
        function displayRawMaterials(materialsData, containerSelector) { 
            const container = $(containerSelector); 
            if (!container.length) { return true; }
            let html = '<h4>Raw Material Stock Check (for items needing manufacturing):</h4>'; 
            if (!materialsData || Object.keys(materialsData).length === 0) { 
                container.html(html + '<p class="text-muted">No specific raw material information available or none needed for manufacturing.</p>'); 
                return true;
            } 
            let allRawMaterialsSufficient = true; 
            let insufficientRawMaterialsList = []; 
            html += `<table class="materials-table"><thead><tr><th>Material</th><th style="text-align:right;">Available</th><th style="text-align:right;">Required</th><th>Status</th></tr></thead><tbody>`; 
            Object.keys(materialsData).forEach(materialName => { 
                const data = materialsData[materialName]; 
                const available = parseFloat(data.available) || 0; 
                const required = parseFloat(data.required) || 0; 
                const isSufficient = data.sufficient; 
                if (!isSufficient) { 
                    allRawMaterialsSufficient = false; 
                    insufficientRawMaterialsList.push(`${materialName} (short by ${formatWeight(data.shortfall || required)})`); 
                } 
                html += `<tr><td>${materialName}</td><td style="text-align:right;">${formatWeight(available)}</td><td style="text-align:right;">${formatWeight(required)}</td><td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">${isSufficient ? '<i class="fas fa-check-circle"></i> Sufficient' : '<i class="fas fa-times-circle"></i> Insufficient'}</td></tr>`; 
            }); 
            html += `</tbody></table>`; 
            const overallRawMaterialMessage = allRawMaterialsSufficient ? 'All required raw materials are currently sufficient for manufacturing.' : `Insufficient raw materials: ${insufficientRawMaterialsList.join('; ')}. Manufacturing may be delayed.`; 
            const overallRawMaterialClass = allRawMaterialsSufficient ? 'status-sufficient' : 'status-insufficient'; 
            container.html(html + `<p class="materials-status ${overallRawMaterialClass}" style="margin-top:10px;">${overallRawMaterialMessage}</p>`); 
            return allRawMaterialsSufficient; 
        }
        function updatePendingOrderActionStatus(inventoryResponse) {
            let canActivateOrder = true; 
            let overallStatusMessage = 'Inventory check complete. Ready to activate order.'; 
            const materialContainer = $('#rawMaterialsContainer');

            const finishedProducts = inventoryResponse.finishedProducts || {};
            const needsManufacturing = inventoryResponse.needsManufacturing || false;
            const materials = inventoryResponse.materials || {};

            let allFinishedProductsInStock = Object.keys(finishedProducts).length > 0 && Object.values(finishedProducts).every(p => p.sufficient);
            
            if (Object.keys(finishedProducts).length === 0 && !needsManufacturing) {
                overallStatusMessage = 'No finished products require stock check for this order. Ready to activate.';
            } else if (!allFinishedProductsInStock) {
                if (needsManufacturing) {
                    let allRawMaterialsSufficientForMfg = Object.keys(materials).length > 0 && Object.values(materials).every(m => m.sufficient);
                     if (Object.keys(materials).length === 0 && Object.values(finishedProducts).some(p => !p.sufficient && p.canManufacture)) {
                        allRawMaterialsSufficientForMfg = false;
                        overallStatusMessage = 'Cannot activate: Manufacturing required for some items, but raw material availability is unknown.';
                    }

                    if (!allRawMaterialsSufficientForMfg) {
                        canActivateOrder = false; 
                        overallStatusMessage = 'Cannot activate: Insufficient raw materials for manufacturing the required finished products.';
                    } else {
                        overallStatusMessage = 'Manufacturing required for some items. Raw materials are sufficient. Ready to activate.';
                    }
                } else {
                    canActivateOrder = false;
                    overallStatusMessage = 'Cannot activate: Some finished products are out of stock, and manufacturing is not indicated as an option or not applicable.';
                }
            } else {
                 overallStatusMessage = 'All required finished products are in stock. Ready to activate.';
            }
            
            $('#activeStatusBtn').prop('disabled', !canActivateOrder); 
            
            let statusMessageElement = materialContainer.children('.overall-inventory-status-message');
            if (!statusMessageElement.length) {
                materialContainer.append(`<p class="materials-status overall-inventory-status-message ${canActivateOrder ? 'status-sufficient' : 'status-insufficient'}">${overallStatusMessage}</p>`);
            } else {
                statusMessageElement.removeClass('status-sufficient status-insufficient').addClass(canActivateOrder ? 'status-sufficient' : 'status-insufficient').html(overallStatusMessage);
            }
        }
        
        function viewOrderDetails(poNumber) {
            currentPoNumber = poNumber;
            $('#orderDetailsModal .overlay-title').html(`<i class="fas fa-tasks"></i> Manage Order Items - PO: ${poNumber}`);
            fetch(`/backend/get_order_details.php?po_number=${encodeURIComponent(poNumber)}`)
            .then(response => {
                if (!response.ok) throw new Error(`Network error: ${response.status} ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    currentOrderItemsData = data.orderItems || [];
                    completedItemsIndices = data.completedItems || [];
                    itemQuantityProgressData = data.quantity_progress_data || {};
                    itemProgressPercentagesMap = data.item_progress_percentages || {};
                    currentOverallProgress = data.overall_progress || 0;

                    $('#orderStatusView').text(data.order_status || 'Active');
                    const orderDetailsBody = $('#orderDetailsBody').empty();
                    $('#status-header-cell').show();

                    const totalOrderItemsCount = currentOrderItemsData.length;
                    itemContributionsToOverall = {}; 
                    let calculatedOverallProgress = 0;

                    if (totalOrderItemsCount === 0) {
                        orderDetailsBody.html('<tr><td colspan="6" style="text-align:center; padding:20px;">No items found in this order.</td></tr>');
                        $('#overall-progress-info, .save-progress-btn').hide();
                        $('#orderTotalAmount').text('Total: PHP 0.00');
                        $('#orderDetailsModal').show();
                        return;
                    }

                    currentOrderItemsData.forEach((item, index) => {
                        const itemQty = parseInt(item.quantity) || 0;
                        const contributionPerFullItem = totalOrderItemsCount > 0 ? (100 / totalOrderItemsCount) : 0;
                        itemContributionsToOverall[index] = contributionPerFullItem;
                        
                        let unitsCompletedCount = 0;
                        if (itemQuantityProgressData[index] && itemQty > 0) {
                            for (let i = 0; i < itemQty; i++) {
                                if (itemQuantityProgressData[index][i] === true) unitsCompletedCount++;
                            }
                        }
                        let currentItemUnitProgress = itemQty > 0 ? (unitsCompletedCount / itemQty) * 100 : (completedItemsIndices.includes(index) ? 100 : 0);
                        itemProgressPercentagesMap[index] = currentItemUnitProgress;

                        calculatedOverallProgress += (currentItemUnitProgress / 100) * contributionPerFullItem;

                        const mainRow = $('<tr>').addClass('item-header-row').toggleClass('completed-item', currentItemUnitProgress === 100).attr('data-item-index', index);
                        mainRow.html(
                           `<td>${item.category || 'N/A'}</td>
                            <td>${item.item_description || 'N/A'}</td>
                            <td>${item.packaging || 'N/A'}</td>
                            <td style="text-align:right;">PHP ${parseFloat(item.price || 0).toFixed(2)}</td>
                            <td style="text-align:center;">${itemQty}</td>
                            <td class="status-cell">
                                <div style="display: flex; flex-direction:column; align-items: center; justify-content: center; gap: 5px;">
                                    ${itemQty > 0 ? `<button class="expand-units-btn btn btn-sm btn-outline-secondary py-0 px-1" onclick="toggleQuantityProgress(${index}, this)"><i class="fas fa-chevron-down"></i> Units</button>` : ''}
                                    <div class="item-progress-bar-container" style="width:120px;">
                                        <div class="item-progress-bar" id="item-progress-bar-${index}" style="width: ${currentItemUnitProgress.toFixed(0)}%;"></div>
                                        <div class="item-progress-text" id="item-progress-text-${index}">${currentItemUnitProgress.toFixed(0)}%</div>
                                    </div>
                                    <div class="item-contribution-text" id="contribution-text-${index}" style="font-size:9px; color:#666;">(Contributes ${contributionPerFullItem.toFixed(1)}% to total)</div>
                                    ${itemQty === 0 ? `<input type="checkbox" class="item-status-checkbox form-check-input mt-1" data-index="${index}" onchange="updateRowStyle(this)" ${completedItemsIndices.includes(index) ? 'checked' : ''} title="Mark as complete">` : ''}
                                </div>
                            </td>`
                        );
                        orderDetailsBody.append(mainRow);

                        if (itemQty > 0) {
                            const dividerRow = $('<tr>').addClass('units-divider').attr('id', `units-divider-${index}`).hide().html(`<td colspan="6"></td>`);
                            orderDetailsBody.append(dividerRow);
                            for (let i = 0; i < itemQty; i++) {
                                const isUnitCompleted = itemQuantityProgressData[index] && itemQuantityProgressData[index][i] === true;
                                const unitRow = $('<tr>').addClass(`unit-row unit-for-item-${index}`).hide().toggleClass('completed', isUnitCompleted)
                                    .html(`<td colspan="5" style="padding-left:40px;">Unit ${i + 1} of ${item.item_description}</td>
                                           <td style="text-align:center;"><input type="checkbox" class="unit-status-checkbox form-check-input" data-item-index="${index}" data-unit-index="${i}" onchange="updateUnitStatus(this)" ${isUnitCompleted ? 'checked' : ''}></td>`);
                                orderDetailsBody.append(unitRow);
                            }
                            const actionRow = $('<tr>').addClass(`unit-row unit-action-row unit-for-item-${index}`).hide()
                                .html(`<td colspan="6"><button class="btn btn-sm btn-outline-success py-0 px-1 me-1" onclick="selectAllUnits(${index}, ${itemQty})">All Units Done</button><button class="btn btn-sm btn-outline-warning py-0 px-1" onclick="deselectAllUnits(${index}, ${itemQty})">Reset Units</button></td>`);
                            orderDetailsBody.append(actionRow);
                        }
                    });
                    currentOverallProgress = calculatedOverallProgress;
                    updateOverallProgressDisplay();
                    let totalOrderAmount = currentOrderItemsData.reduce((sum, item) => sum + (parseFloat(item.price || 0) * parseInt(item.quantity || 0)), 0);
                    $('#orderTotalAmount').text(`Total: PHP ${totalOrderAmount.toFixed(2)}`);
                    $('#overall-progress-info, .save-progress-btn').show();
                    $('#orderDetailsModal').show();
                } else {
                    showToast('Error fetching order details: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                showToast('Network or server error fetching order details: ' + error.message, 'error');
            });
        }
        function viewOrderInfo(ordersJsonString, orderStatus, poNumber) {
            try {
                const orderDetailsArray = JSON.parse(ordersJsonString);
                const itemsBody = $('#orderDetailsBody').empty();
                $('#status-header-cell').hide();
                $('#orderStatusView').text(orderStatus + (poNumber ? ` - PO: ${poNumber}` : ''));
                $('#orderDetailsModal .overlay-title').html(`<i class="fas fa-eye"></i> View Order Items - PO: ${poNumber}`);

                let totalAmountCalc = 0;
                if (orderDetailsArray && orderDetailsArray.length > 0) {
                    orderDetailsArray.forEach(p => {
                        totalAmountCalc += (parseFloat(p.price) || 0) * (parseInt(p.quantity) || 0);
                        itemsBody.append(`<tr><td>${p.category || 'N/A'}</td><td>${p.item_description || 'N/A'}</td><td>${p.packaging || 'N/A'}</td><td style="text-align:right;">PHP ${(parseFloat(p.price) || 0).toFixed(2)}</td><td style="text-align:center;">${p.quantity || 0}</td><td>N/A (Status: ${orderStatus})</td></tr>`);
                    });
                } else {
                    itemsBody.html('<tr><td colspan="6" style="text-align:center; padding:20px;">No items found in this order.</td></tr>');
                }
                $('#orderTotalAmount').text(`Total: PHP ${totalAmountCalc.toFixed(2)}`);
                $('#overall-progress-info, .save-progress-btn').hide();
                $('#orderDetailsModal').show();
            } catch (e) {
                showToast('Error displaying order information. Data might be corrupt.', 'error');
            }
        }
        function toggleQuantityProgress(itemIndex, button) { 
            $(`.unit-for-item-${itemIndex}, #units-divider-${itemIndex}`).slideToggle(200); 
            $(button).find('i').toggleClass('fa-chevron-down fa-chevron-up');
        }
        function updateUnitStatus(checkbox) {
            const itemIndex = parseInt(checkbox.dataset.itemIndex); 
            const unitIndex = parseInt(checkbox.dataset.unitIndex); 
            const isChecked = checkbox.checked; 
            $(checkbox).closest('tr.unit-row').toggleClass('completed', isChecked);
            if (!itemQuantityProgressData[itemIndex]) { 
                itemQuantityProgressData[itemIndex] = {}; 
                for (let i = 0; i < (parseInt(currentOrderItemsData[itemIndex].quantity) || 0); i++) {
                    itemQuantityProgressData[itemIndex][i] = false; 
                }
            }
            itemQuantityProgressData[itemIndex][unitIndex] = isChecked; 
            updateItemProgressUI(itemIndex); 
            updateOverallProgress();
        }
        function updateItemProgressUI(itemIndex) {
            const item = currentOrderItemsData[itemIndex]; 
            const qty = parseInt(item.quantity) || 0; 
            if (qty === 0) {
                itemProgressPercentagesMap[itemIndex] = $(`.item-status-checkbox[data-index="${itemIndex}"]`).is(':checked') ? 100 : 0;
            } else {
                let completedUnits = 0;
                if (itemQuantityProgressData[itemIndex]) {
                    for (let i = 0; i < qty; i++) {
                        if (itemQuantityProgressData[itemIndex][i] === true) completedUnits++;
                    }
                }
                itemProgressPercentagesMap[itemIndex] = qty > 0 ? (completedUnits / qty) * 100 : 0;
            }
            
            const progressPercent = itemProgressPercentagesMap[itemIndex] || 0;
            $(`#item-progress-bar-${itemIndex}`).css('width', `${progressPercent.toFixed(0)}%`); 
            $(`#item-progress-text-${itemIndex}`).text(`${progressPercent.toFixed(0)}%`); 
            
            const isItemNowFullyComplete = progressPercent >= 99.9;
            $(`tr.item-header-row[data-item-index="${itemIndex}"]`).toggleClass('completed-item', isItemNowFullyComplete);
            if (qty === 0) {
                 const mainCheckbox = $(`.item-status-checkbox[data-index="${itemIndex}"]`);
                 if (mainCheckbox.length) {
                    mainCheckbox.prop('checked', isItemNowFullyComplete);
                 }
            }
        }
        function updateOverallProgressDisplay() { 
            const roundedProgress = Math.round(currentOverallProgress); 
            $('#overall-progress-bar').css('width', `${roundedProgress}%`).text(roundedProgress > 5 ? `${roundedProgress}%` : ''); 
            $('#overall-progress-text').text(`${roundedProgress}% Complete`); 
        }
        function updateOverallProgress() {
            let newOverallProgress = 0; 
            if (currentOrderItemsData.length > 0) {
                currentOrderItemsData.forEach((item, index) => {
                    const itemProgress = itemProgressPercentagesMap[index] || 0;
                    const itemContribution = itemContributionsToOverall[index] || (100 / currentOrderItemsData.length);
                    newOverallProgress += (itemProgress / 100) * itemContribution;
                });
            }
            currentOverallProgress = newOverallProgress; 
            updateOverallProgressDisplay(); 
            return Math.round(currentOverallProgress);
        }
        function selectAllUnits(itemIndex, quantity) { 
            if (quantity === 0) return;
            $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', true).closest('tr.unit-row').addClass('completed'); 
            if (!itemQuantityProgressData[itemIndex]) itemQuantityProgressData[itemIndex] = {}; 
            for (let i = 0; i < quantity; i++) itemQuantityProgressData[itemIndex][i] = true; 
            updateItemProgressUI(itemIndex); 
            updateOverallProgress(); 
        }
        function deselectAllUnits(itemIndex, quantity) { 
            if (quantity === 0) return;
            $(`.unit-status-checkbox[data-item-index="${itemIndex}"]`).prop('checked', false).closest('tr.unit-row').removeClass('completed'); 
            if (!itemQuantityProgressData[itemIndex]) itemQuantityProgressData[itemIndex] = {}; 
            for (let i = 0; i < quantity; i++) itemQuantityProgressData[itemIndex][i] = false; 
            updateItemProgressUI(itemIndex); 
            updateOverallProgress(); 
        }
        function updateRowStyle(mainCheckbox) {
            const itemIndex = parseInt(mainCheckbox.dataset.index);
            const isChecked = mainCheckbox.checked;
            $(`tr.item-header-row[data-item-index="${itemIndex}"]`).toggleClass('completed-item', isChecked);
            itemProgressPercentagesMap[itemIndex] = isChecked ? 100 : 0;
            const idxInCompleted = completedItemsIndices.indexOf(itemIndex);
            if (isChecked && idxInCompleted === -1) completedItemsIndices.push(itemIndex);
            else if (!isChecked && idxInCompleted > -1) completedItemsIndices.splice(idxInCompleted, 1);
            updateItemProgressUI(itemIndex);
            updateOverallProgress();
        }
        function closeOrderDetailsModal() { $('#orderDetailsModal').hide(); currentOrderItemsData = []; }
        function confirmSaveProgress() { $('#saveProgressConfirmationModal .confirmation-message').text('Are you sure you want to save the current item completion progress for PO: ' + currentPoNumber + '?'); $('#saveProgressConfirmationModal').show(); }
        function closeSaveProgressConfirmation() { $('#saveProgressConfirmationModal').hide(); }
        function saveProgressChanges() {
            $('#saveProgressConfirmationModal').hide();
            const finalCalculatedOverallProgress = updateOverallProgress();

            const payload = {
                po_number: currentPoNumber,
                completed_items_indices: completedItemsIndices,
                quantity_progress_data: itemQuantityProgressData,
                overall_progress: finalCalculatedOverallProgress 
            };

            if (finalCalculatedOverallProgress >= 100) {
                showToast('Order progress is 100%. Updating status to "For Delivery"...', 'info', 4000);
                updateOrderStatus('For Delivery', false, false);
            } else {
                fetch('/backend/update_order_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Item progress saved successfully for PO: ' + currentPoNumber, 'success');
                        setTimeout(() => { 
                            closeOrderDetailsModal();
                             window.location.reload();
                        }, 1500);
                    } else {
                        showToast('Error saving progress: ' + (data.message || 'Unknown server error'), 'error', 5000);
                    }
                })
                .catch(error => {
                    showToast('Network error while saving progress: ' + error.message, 'error', 5000);
                });
            }
        }

        function openEditDateModal(poNumber, currentDeliveryDate, orderDate) { 
            currentPoNumber = poNumber; 
            currentEditingOrderDate = orderDate;
            $('#edit_po_number').val(poNumber); 
            $('#current_delivery_date').val(currentDeliveryDate ? new Date(currentDeliveryDate).toLocaleDateString('en-CA') : 'N/A');
            $('#edit_order_date_display').text(orderDate ? new Date(orderDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A');
            $('#new_delivery_date_input').val('');

            if ($.datepicker) { 
                $("#new_delivery_date_input").datepicker("destroy"); 
                let minDateForPicker = new Date(orderDate);
                minDateForPicker.setDate(minDateForPicker.getDate() + 5);

                $("#new_delivery_date_input").datepicker({ 
                    dateFormat: 'yy-mm-dd', 
                    minDate: minDateForPicker, 
                    beforeShowDay: function(date) { 
                        var day = date.getDay();
                        return [(day == 1 || day == 3 || day == 5), ''];
                    }
                }); 
            } 
            $('#editDateModal').show(); 
        }
        function closeEditDateModal() { $('#editDateModal').hide(); }
        function validateEditDateForm(event) {
            const newDateStr = $('#new_delivery_date_input').val();
            if (!newDateStr) {
                showToast('New delivery date cannot be empty.', 'error');
                event.preventDefault(); return false;
            }
            if (!isValidDeliveryDay(newDateStr)) {
                showToast('New delivery date must be a Monday, Wednesday, or Friday.', 'error');
                event.preventDefault(); return false;
            }
            if (!isValidDeliveryGap(currentEditingOrderDate, newDateStr, 5)) {
                 showToast('New delivery date must be at least 5 days after the order date ('+ new Date(currentEditingOrderDate).toLocaleDateString() +').', 'error');
                event.preventDefault(); return false;
            }
            return true;
        }
        
        function confirmDownloadPO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJsonString, totalAmount, specialInstructions, orderType) { 
            poDownloadDataStore = { poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJsonString, totalAmount, specialInstructions, orderType }; 
            $('#downloadConfirmationModal .confirmation-message').text(`Download Sales Invoice PDF for PO ${poDownloadDataStore.poNumber}?`); 
            $('#downloadConfirmationModal').show(); 
        }
        function closeDownloadConfirmation() { $('#downloadConfirmationModal').hide(); poDownloadDataStore = null; }
        function downloadPODirectly() { 
            $('#downloadConfirmationModal').hide(); 
            if (!poDownloadDataStore) { showToast('No data available for PO download.', 'error'); return; } 
            try { 
                currentPODataForPDF = poDownloadDataStore;
                $('#printCompany').text(currentPODataForPDF.company || 'N/A'); 
                $('#printPoNumber').text(currentPODataForPDF.poNumber); 
                $('#printUsername').text(currentPODataForPDF.username + (currentPODataForPDF.company ? ` (${currentPODataForPDF.company})` : '')); 
                $('#printDeliveryAddress').text(currentPODataForPDF.deliveryAddress || 'N/A'); 
                $('#printOrderDate').text(currentPODataForPDF.orderDate ? new Date(currentPODataForPDF.orderDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'); 
                
                if (currentPODataForPDF.orderType === 'Walk In' || !currentPODataForPDF.deliveryDate) {
                    $('#printDeliveryDateRow').hide();
                    $('#printDeliveryDate').text('N/A (Walk-In)'); // Or leave blank
                } else {
                    $('#printDeliveryDate').text(new Date(currentPODataForPDF.deliveryDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
                    $('#printDeliveryDateRow').show();
                }
                
                const instrSec = $('#printInstructionsSection'); const instrContent = $('#printSpecialInstructions');
                if (currentPODataForPDF.specialInstructions && currentPODataForPDF.specialInstructions.trim()) { 
                    instrContent.html(currentPODataForPDF.specialInstructions.replace(/\n/g, '<br>'));
                    instrSec.show(); 
                } else { 
                    instrSec.hide(); 
                } 
                
                const itemsArray = JSON.parse(currentPODataForPDF.ordersJsonString); 
                const itemsBody = $('#printOrderItems').empty(); 
                if (itemsArray && itemsArray.length > 0) {
                    itemsArray.forEach(item => { 
                        const itemTotal = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0); 
                        if (item.category !== undefined && item.item_description !== undefined && item.packaging !== undefined && item.quantity !== undefined && item.price !== undefined) { 
                            itemsBody.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:right;">${item.quantity}</td><td style="text-align:right;">${parseFloat(item.price).toFixed(2)}</td><td style="text-align:right;">${itemTotal.toFixed(2)}</td></tr>`); 
                        } 
                    });
                } else {
                    itemsBody.append('<tr><td colspan="6" style="text-align:center;">No items in this order.</td></tr>');
                }
                $('#printTotalAmount').text(parseFloat(currentPODataForPDF.totalAmount).toFixed(2)); 
                
                const pdfElement = document.getElementById('contentToDownload'); 
                const pdfOptions = { 
                    margin: [10, 8, 10, 8], 
                    filename: `SalesInvoice_${currentPODataForPDF.poNumber}.pdf`, 
                    image: { type: 'jpeg', quality: 0.95 }, 
                    html2canvas: { scale: 2, useCORS: true, logging: false }, 
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } 
                }; 
                $('#pdfPreview').show();
                
                html2pdf().set(pdfOptions).from(pdfElement).save()
                .then(() => { 
                    showToast(`Sales Invoice PDF for PO ${currentPODataForPDF.poNumber} downloaded.`, 'success'); 
                })
                .catch(err => { 
                    showToast('Error generating PDF: ' + err.message, 'error'); 
                })
                .finally(() => {
                    currentPODataForPDF = null; 
                    poDownloadDataStore = null;
                });

            } catch (e) { 
                showToast('Error preparing PDF data: ' + e.message, 'error'); 
                currentPODataForPDF = null; poDownloadDataStore = null; 
                closePDFPreview();
            } 
        }
        function downloadPDF() { 
             if (!currentPODataForPDF) { showToast('No active PDF data to download.', 'error'); return; }
             const pdfElement = document.getElementById('contentToDownload'); 
             const pdfOptions = { margin: [10,8,10,8], filename: `SalesInvoice_${currentPODataForPDF.poNumber}.pdf`, image: { type: 'jpeg', quality: 0.95 }, html2canvas: { scale: 2, useCORS:true, logging:false }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
             html2pdf().set(pdfOptions).from(pdfElement).save().then(() => { showToast('PDF Downloaded.', 'success'); }).catch(e => { showToast('PDF download error: '+e.message, 'error');});
        }
        function closePDFPreview() { $('#pdfPreview').hide(); currentPODataForPDF = null; }

        function viewSpecialInstructions(poNumber, instructions) { 
            $('#instructionsPoNumber').text('PO: ' + poNumber); 
            const contentElement = $('#instructionsContent'); 
            if (instructions && instructions.trim()) { 
                contentElement.html(instructions.replace(/\n/g, '<br>')).removeClass('empty');
            } else { 
                contentElement.text('No special instructions provided for this order.').addClass('empty'); 
            } 
            $('#specialInstructionsModal').show(); 
        }
        function closeSpecialInstructions() { $('#specialInstructionsModal').hide(); }

        function initializeDeliveryDatePickerForAddOrder(baseDateStr = null) {
            const deliveryInput = $("#delivery_date_input");
            if ($.datepicker && deliveryInput.length) {
                deliveryInput.datepicker("destroy");

                // Use the JS helper function here
                const minDateForPicker = getNextAvailableDeliveryDate(baseDateStr, 5);


                deliveryInput.datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: minDateForPicker, // Set minDate based on calculation
                    beforeShowDay: function(date) {
                        var day = date.getDay();
                        return [(day == 1 || day == 3 || day == 5), ''];
                    }
                });
                if (!deliveryInput.val()) { // If input is empty, set to the calculated next valid date
                    deliveryInput.datepicker("setDate", minDateForPicker);
                }
            }
        }
        
        function fetchAndSetWalkInPONumber() {
            $('#po_number_for_submit').val('Generating...');
            fetch('/backend/get_next_walkin_po.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok for PO number.');
                return response.json();
            })
            .then(data => {
                if (data.success && typeof data.next_sequence_number !== 'undefined') {
                    const po = `WI-${String(data.next_sequence_number).padStart(3, '0')}`;
                    $('#po_number_for_submit').val(po);
                } else {
                    throw new Error(data.message || 'Invalid data for PO number.');
                }
            })
            .catch(error => {
                showToast('Error generating Walk-In PO: ' + error.message, 'error');
                $('#po_number_for_submit').val('');
            });
        }

        function toggleOrderFormFields() {
            const selectedOrderType = $('#order_type_selection').val();
            $('#order_type_hidden_for_submit').val(selectedOrderType);

            // Hide all conditional sections initially
            $('#onlineSpecificInputs, #walkInSpecificInputs, #commonOrderFields, #confirmAddOrderBtn').hide();
            $('#delivery_date_form_group, #delivery_address_type_form_group, #company_address_container_div, #custom_address_container_div').hide();
            $('#custom_address_label').text('Custom Delivery Address:'); // Reset label

            // Reset common form fields
            $('#username_online_select').val('');
            $('#online_company_display').val('');
            $('#walk_in_name_company_input').val('');
            $('#delivery_date_input').val('');
            $('#custom_address_input_field').val('');
            $('#delivery_address_for_submit').val('');
            $('#po_number_for_submit').val('');


            if (selectedOrderType === "Online") {
                $('#onlineSpecificInputs, #commonOrderFields, #confirmAddOrderBtn').show();
                $('#delivery_date_form_group, #delivery_address_type_form_group').show();
                $('#company_address_option_for_delivery').show();
                $('#delivery_address_type_select').val('company'); // Default for online
                handleOnlineUserChange(); // This will also try to set PO based on username
            } else if (selectedOrderType === "Walk In") {
                $('#walkInSpecificInputs, #commonOrderFields, #confirmAddOrderBtn').show();
                $('#custom_address_label').text('Address (for Walk-In):');
                $('#custom_address_container_div').show(); // Show only custom address input
                $('#delivery_address_type_select').val('custom'); // Internally, it's custom
                fetchAndSetWalkInPONumber();
                $('#company_name_final_for_submit').val('');
            }
            
            if (selectedOrderType) {
                const today = new Date();
                const formattedToday = `${today.getFullYear()}-${(today.getMonth()+1).toString().padStart(2,'0')}-${today.getDate().toString().padStart(2,'0')}`;
                $('#order_date_input').val(formattedToday);
                if (selectedOrderType === "Online") {
                    initializeDeliveryDatePickerForAddOrder(formattedToday);
                }
                toggleDeliveryAddressOptions(); // Call this to set initial address display
            }
            currentCartItems = [];
            updateOrderSummary();
            updateCartItemCount();
        }

        function handleOnlineUserChange() {
            const selectedUserOption = $('#username_online_select option:selected');
            const username = selectedUserOption.val();
            if (username) {
                const companyName = selectedUserOption.data('company') || 'N/A';
                const companyAddr = selectedUserOption.data('company-address') || '';
                $('#online_company_display').val(companyName);
                $('#company_name_final_for_submit').val(companyName);
                $('#company_address_display_field').val(companyAddr); 
                
                if ($('#delivery_address_type_select').val() === 'company') {
                     $('#delivery_address_for_submit').val(companyAddr);
                }
                $('#po_number_for_submit').val(generateOnlinePONumber(username));
            } else {
                $('#online_company_display').val('');
                $('#company_name_final_for_submit').val('');
                $('#company_address_display_field').val('');
                if ($('#delivery_address_type_select').val() === 'company') {
                     $('#delivery_address_for_submit').val('');
                }
                 $('#po_number_for_submit').val('');
            }
        }
        
        function toggleDeliveryAddressOptions() {
            const deliveryTypeSelected = $('#delivery_address_type_select').val();
            const currentOrderType = $('#order_type_selection').val();

            if (currentOrderType === "Walk In") {
                $('#company_address_container_div').hide();
                $('#custom_address_container_div').show();
                $('#delivery_address_for_submit').val($('#custom_address_input_field').val().trim());
                return;
            }

            if (deliveryTypeSelected === 'company') {
                $('#company_address_container_div').show();
                $('#custom_address_container_div').hide();
                $('#delivery_address_for_submit').val($('#company_address_display_field').val()); 
            } else { 
                $('#company_address_container_div').hide();
                $('#custom_address_container_div').show();
                $('#delivery_address_for_submit').val($('#custom_address_input_field').val().trim());
            }
        }
        
        $('#custom_address_input_field').on('input', function() {
            const currentOrderType = $('#order_type_selection').val();
            if (currentOrderType === "Walk In" || $('#delivery_address_type_select').val() === 'custom') {
                $('#delivery_address_for_submit').val($(this).val().trim());
            }
        });

        function openAddOrderForm() {
            $('#addOrderForm')[0].reset();
            currentCartItems = [];
            updateOrderSummary();
            updateCartItemCount();
            $('#order_type_selection').val("").trigger('change'); // This will call toggleOrderFormFields
            $('#onlineSpecificInputs, #walkInSpecificInputs, #commonOrderFields, #confirmAddOrderBtn').hide();
            $('#delivery_date_form_group, #delivery_address_type_form_group, #company_address_container_div, #custom_address_container_div').hide();
            $('#custom_address_label').text('Custom Delivery Address:');
            $('#addOrderOverlay').show();
        }
        function closeAddOrderForm() { $('#addOrderOverlay').hide(); }
        
        function generateOnlinePONumber(username) {
            if (!username) return '';
            const d = new Date();
            const userPart = username.substring(0, Math.min(username.length, 4)).toUpperCase();
            const po = `PO-${userPart}-${d.getFullYear().toString().slice(-2)}${(d.getMonth() + 1).toString().padStart(2, '0')}${d.getDate().toString().padStart(2, '0')}-${d.getHours().toString().padStart(2, '0')}${d.getMinutes().toString().padStart(2, '0')}-${Math.floor(100 + Math.random() * 900)}`;
            return po;
        }

        function prepareOrderDataForSubmit() {
            const selectedOrderType = $('#order_type_selection').val();
            $('#order_type_hidden_for_submit').val(selectedOrderType);

            if (!selectedOrderType) { showToast('Please select an Order Type (Online or Walk-In).', 'error'); return false; }

            let finalUsernameForDB = '';
            let finalCompanyForDB = '';

            if (selectedOrderType === "Online") {
                finalUsernameForDB = $('#username_online_select').val();
                if (!finalUsernameForDB) { showToast('Please select a Client Username for an Online order.', 'error'); return false; }
                finalCompanyForDB = $('#online_company_display').val();
                
                const deliveryDateStr = $('#delivery_date_input').val();
                const orderDateStr = $('#order_date_input').val();
                if (!deliveryDateStr) { showToast('Requested Delivery Date is required for Online orders.', 'error'); return false; }
                if (!isValidDeliveryDay(deliveryDateStr)) { showToast('Delivery date must be a Monday, Wednesday, or Friday.', 'error'); return false; }
                if (!isValidDeliveryGap(orderDateStr, deliveryDateStr, 5)) { showToast(`Delivery date must be at least 5 days after the order date (${new Date(orderDateStr).toLocaleDateString()}).`, 'error', 5000); return false; }

            } else if (selectedOrderType === "Walk In") {
                finalUsernameForDB = 'Walk-In Customer';
                finalCompanyForDB = $('#walk_in_name_company_input').val().trim();
                if (!finalCompanyForDB) { showToast('Please enter the Full Name or Company Name for a Walk-In order.', 'error'); return false;}
            }
            $('#company_name_final_for_submit').val(finalCompanyForDB);

            if (currentCartItems.length === 0) { showToast('Order cannot be empty. Please select products.', 'error'); return false; }
            $('#orders_json_for_submit').val(JSON.stringify(currentCartItems));
            
            let currentTotalAmount = 0;
            currentCartItems.forEach(item => { currentTotalAmount += (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0); });
            $('#total_amount_for_submit').val(currentTotalAmount.toFixed(2));

            const deliveryAddress = $('#delivery_address_for_submit').val().trim();
            if (!deliveryAddress) { showToast('Address is required.', 'error'); return false; }
            
            if (!$('#po_number_for_submit').val() || ($('#po_number_for_submit').val() === 'Generating...')) { 
                showToast('PO Number is not yet generated or is invalid. Please wait or check selections.', 'error'); return false; 
            }

            return true;
        }

        function confirmAddOrder() {
            if (prepareOrderDataForSubmit()) {
                $('#addConfirmationModal .confirmation-message').text('Are you sure you want to submit this order? Please double-check all details.');
                $('#addConfirmationModal').show();
            }
        }
        function closeAddConfirmation() { $('#addConfirmationModal').hide(); }
        function submitAddOrder() {
            $('#addConfirmationModal').hide();
            if (!prepareOrderDataForSubmit()) return;

            const formElement = document.getElementById('addOrderForm');
            const formData = new FormData(formElement);
            
            const orderType = formData.get('order_type');
            if (orderType === "Walk In") {
                formData.delete('username_online'); // Ensure not sent
                // formData.append('username_placeholder_for_walkin', 'Walk-In Customer'); // Backend handles this now
                formData.delete('delivery_date'); 
            }
            
            fetch('/backend/add_order.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Order successfully added! PO Number: ' + (data.po_number || formData.get('po_number')), 'success', 4000);
                    const clientUsernameForEmail = orderType === "Online" ? formData.get('username_online') : 'Walk-In Client';
                    sendNewOrderEmail(clientUsernameForEmail, data.po_number || formData.get('po_number'));
                    setTimeout(() => { window.location.href = 'orders.php'; }, 2000);
                } else {
                    showToast('Error adding order: ' + (data.message || 'An unknown server error occurred.'), 'error', 5000);
                }
            })
            .catch(error => {
                showToast('Network or server error while adding order: ' + error.message, 'error', 5000);
            });
        }
        
        function sendNewOrderEmail(usernameForEmail, poNumberForEmail) { 
            fetch('/backend/send_new_order_notification.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', }, 
                body: `username=${encodeURIComponent(usernameForEmail)}&po_number=${encodeURIComponent(poNumberForEmail)}` 
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) console.log("New order notification email successfully sent/queued for:", usernameForEmail, poNumberForEmail);
                else console.warn("Failed to send new order notification email:", data.message);
            })
            .catch(error => console.error("Error sending new order notification email (AJAX):", error));
        }

        function openInventoryOverlay() { 
            $('#inventoryOverlay').show(); 
            const inventoryBody = $('.inventory').html('<tr><td colspan="6" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading inventory...</p></td></tr>'); 
            fetch('/backend/get_inventory.php')
            .then(response => { 
                if (!response.ok) throw new Error(`Network error fetching inventory: ${response.status} ${response.statusText}`); 
                return response.json(); 
            })
            .then(data => { 
                if (data.success && data.inventory) { 
                    populateInventoryTable(data.inventory);
                    populateCategoryFilter(data.categories || []);
                } else { 
                    inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">Error loading inventory: ' + (data.message || 'No inventory data received.') + '</td></tr>'); 
                    showToast('Error loading inventory: ' + (data.message || 'Unknown error from server.'), 'error'); 
                } 
            })
            .catch(error => { 
                inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;color:red;">Failed to load inventory: ' + error.message + '</td></tr>'); 
                showToast('Failed to load inventory: ' + error.message, 'error'); 
            }); 
        }
        function populateInventoryTable(inventoryItems) {
            const inventoryBody = $('.inventory').empty(); 
            if (!inventoryItems || inventoryItems.length === 0) { 
                inventoryBody.html('<tr><td colspan="6" style="text-align:center;padding:20px; color:#6c757d;">No products found in inventory.</td></tr>'); 
                return; 
            } 
            inventoryItems.forEach(item => { 
                const price = parseFloat(item.price); 
                if (isNaN(price) || item.product_id === undefined || item.product_id === null) { 
                    return; 
                } 
                inventoryBody.append(`<tr><td>${item.category||'Uncategorized'}</td><td>${item.item_description||'N/A'}</td><td>${item.packaging||'N/A'}</td><td style="text-align:center;">PHP ${price.toFixed(2)}</td><td style="text-align:center;"><input type="number" class="inventory-quantity form-control form-control-sm" value="1" min="1" max="100" style="width:70px; margin:auto;"></td><td style="text-align:center;"><button class="add-to-cart-btn btn btn-primary btn-sm" onclick="addToCartFromInventory(this, '${item.product_id}', '${item.category||''}', '${item.item_description||''}', '${item.packaging||''}', ${price})"><i class="fas fa-cart-plus"></i> Add</button></td></tr>`); 
            }); 
        }
        function populateCategoryFilter(categories) {
            const categorySelect = $('#inventoryFilter'); 
            categorySelect.find('option:not(:first-child)').remove();
            if (!categories || categories.length === 0) return; 
            categories.forEach(cat => categorySelect.append(`<option value="${cat}">${cat}</option>`)); 
        }
        function filterInventory() { 
            const selectedCategory = $('#inventoryFilter').val(); 
            const searchTerm = $('#inventorySearch').val().toLowerCase().trim(); 
            $('.inventory tr').each(function() { 
                const row = $(this); 
                const rowCategory = row.find('td:first-child').text(); 
                const rowTextContent = row.text().toLowerCase(); 
                const categoryMatch = (selectedCategory === 'all' || rowCategory === selectedCategory); 
                const searchTermMatch = (searchTerm === '' || rowTextContent.includes(searchTerm)); 
                row.toggle(categoryMatch && searchTermMatch); 
            }); 
        }
        $('#inventorySearch, #inventoryFilter').off('input change', filterInventory).on('input change', filterInventory);
        function closeInventoryOverlay() { $('#inventoryOverlay').hide(); }
        function addToCartFromInventory(button, productId, category, itemDesc, packaging, price) {
            const quantityInput = $(button).closest('tr').find('.inventory-quantity');
            let quantity = parseInt(quantityInput.val()); 

            if (isNaN(quantity) || quantity < 1) { showToast('Quantity must be at least 1.', 'error'); quantityInput.val(1); return; }
            if (quantity > 100) { showToast('Maximum quantity per item is 100.', 'error'); quantity = 100; quantityInput.val(100); }

            const existingCartItemIndex = currentCartItems.findIndex(i => String(i.product_id) === String(productId) && i.packaging === packaging);

            if (existingCartItemIndex >= 0) {
                 let newTotalQuantity = currentCartItems[existingCartItemIndex].quantity + quantity;
                 if (newTotalQuantity > 100) {
                     showToast(`Cannot add ${quantity}. Total for ${itemDesc} would exceed 100. Current in cart: ${currentCartItems[existingCartItemIndex].quantity}. Max is 100.`, 'warning', 4000);
                     return;
                 } else {
                     currentCartItems[existingCartItemIndex].quantity = newTotalQuantity;
                 }
            } else {
                currentCartItems.push({ product_id: productId, category, item_description: itemDesc, packaging, price: parseFloat(price), quantity: quantity });
            }
            showToast(`${quantity} x ${itemDesc} added to your order.`, 'success');
            quantityInput.val(1);
            updateOrderSummary();
            updateCartItemCount();
        }
        function updateOrderSummary() { 
            const summaryTableBody = $('#summaryBody').empty(); 
            let currentOrderTotal = 0; 
            if (currentCartItems.length === 0) { 
                summaryTableBody.html('<tr><td colspan="6" style="text-align:center; padding: 15px; color: #6c757d;">No products selected yet. Click "Select Products".</td></tr>'); 
                $('#orderSummaryItemCount').text('(0 items)');
            } else { 
                currentCartItems.forEach((item, index) => { 
                    currentOrderTotal += (item.price || 0) * (item.quantity || 0); 
                    summaryTableBody.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:right;">PHP ${parseFloat(item.price || 0).toFixed(2)}</td><td style="text-align:center;"><input type="number" class="cart-quantity summary-quantity form-control form-control-sm" value="${item.quantity}" min="1" max="100" data-cart-item-index="${index}" onchange="updateSummaryItemQuantity(this)" style="width:70px; margin:auto;"></td><td style="text-align:center;"><button class="remove-item-btn btn btn-danger btn-sm" onclick="removeSummaryItem(${index})"><i class="fas fa-trash-alt"></i></button></td></tr>`); 
                }); 
                 $('#orderSummaryItemCount').text(`(${currentCartItems.length} item${currentCartItems.length === 1 ? '' : 's'})`);
            } 
            $('.summary-total-amount').text(`PHP ${currentOrderTotal.toFixed(2)}`); 
            $('#total_amount_for_submit').val(currentOrderTotal.toFixed(2));
        }
        function updateSummaryItemQuantity(inputElement) { 
            const itemIndexInCart = parseInt($(inputElement).data('cart-item-index')); 
            let newQuantity = parseInt($(inputElement).val()); 

            if (isNaN(newQuantity) || newQuantity < 1) { showToast('Quantity must be at least 1.', 'error'); $(inputElement).val(currentCartItems[itemIndexInCart].quantity); return; }
            if (newQuantity > 100) { showToast('Maximum quantity per item is 100.', 'error'); newQuantity = 100; $(inputElement).val(100); }
            
            currentCartItems[itemIndexInCart].quantity = newQuantity; 
            updateOrderSummary();
            updateCartItemCount(); 
            updateCartDisplay();
        }
        function removeSummaryItem(itemIndexInCart) { 
            if (itemIndexInCart >= 0 && itemIndexInCart < currentCartItems.length) { 
                const removedItem = currentCartItems.splice(itemIndexInCart, 1)[0]; 
                showToast(`Removed ${removedItem.item_description} from order.`, 'info'); 
                updateOrderSummary(); 
                updateCartItemCount(); 
                updateCartDisplay();
            } 
        }
        function updateCartItemCount() { 
            const count = currentCartItems.length;
            $('#cartItemCount, #cartItemCountNav').text(count);
            $('#orderSummaryItemCount').text(`(${count} item${count === 1 ? '' : 's'})`);
            if (count === 0) $('#orderSummaryItemCount').text('(0 items)');
        }
        window.openCartModal = function() { $('#cartModal').show(); updateCartDisplay(); }
        function closeCartModal() { $('#cartModal').hide(); }
        function saveCartChangesAndClose() {
            updateOrderSummary();
            closeCartModal(); 
            showToast('Selected items confirmed.', 'success');
        }
        function updateCartDisplay() { 
            const cartTableBody = $('.cart').empty();
            const noProductsRow = $('.no-products-in-cart-row');
            const cartTotalElement = $('.total-amount-cart'); 
            let currentCartModalTotal = 0; 

            if (currentCartItems.length === 0) { 
                if(noProductsRow.length) noProductsRow.show();
                else cartTableBody.html('<tr class="no-products-in-cart-row"><td colspan="6" style="text-align:center; padding:20px; color:#6c757d;">No products currently selected.</td></tr>');
                cartTotalElement.text('PHP 0.00'); 
            } else { 
                if(noProductsRow.length) noProductsRow.hide();
                currentCartItems.forEach((item, index) => { 
                    currentCartModalTotal += (item.price || 0) * (item.quantity || 0); 
                    cartTableBody.append(`<tr><td>${item.category}</td><td>${item.item_description}</td><td>${item.packaging}</td><td style="text-align:center;">PHP ${(parseFloat(item.price) || 0).toFixed(2)}</td><td style="text-align:center;"><input type="number" class="cart-quantity form-control form-control-sm" value="${item.quantity}" min="1" max="100" data-cart-item-index="${index}" onchange="updateCartModalItemQuantity(this)" style="width:70px; margin:auto;"></td><td style="text-align:center;"><button class="remove-item-btn btn btn-danger btn-sm" onclick="removeCartModalItem(${index})"><i class="fas fa-trash-alt"></i></button></td></tr>`); 
                }); 
                cartTotalElement.text(`PHP ${currentCartModalTotal.toFixed(2)}`); 
            } 
        }
        function updateCartModalItemQuantity(inputElement) {
            const itemIndexInCart = parseInt($(inputElement).data('cart-item-index')); 
            let newQuantity = parseInt($(inputElement).val()); 
            if (isNaN(newQuantity) || newQuantity < 1) { showToast('Quantity must be at least 1.', 'error'); $(inputElement).val(currentCartItems[itemIndexInCart].quantity); return; }
            if (newQuantity > 100) { showToast('Maximum quantity is 100.', 'error'); newQuantity = 100; $(inputElement).val(100); }
            currentCartItems[itemIndexInCart].quantity = newQuantity; 
            updateCartDisplay();
            updateOrderSummary();
        }
        function removeCartModalItem(itemIndexInCart) {
            if (itemIndexInCart >= 0 && itemIndexInCart < currentCartItems.length) { 
                const removedItem = currentCartItems.splice(itemIndexInCart, 1)[0]; 
                showToast(`Removed ${removedItem.item_description} from selection.`, 'info'); 
                updateCartDisplay(); 
                updateOrderSummary();
                updateCartItemCount(); 
            } 
        }
        
        $(document).ready(function() {
            setTimeout(function() { $(".alert").fadeOut("slow"); }, 3500);
            
            $("#searchInput").on("keyup input", function() { 
                const searchTerm = $(this).val().toLowerCase().trim(); 
                $(".orders-table tbody tr").each(function() { 
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchTerm)); 
                }); 
            });
            
            $('#order_type_selection').val(""); 
            $('#onlineSpecificInputs, #walkInSpecificInputs, #commonOrderFields, #delivery_date_form_group, #delivery_address_type_form_group, #company_address_container_div, #custom_address_container_div').hide();
            $('#custom_address_label').text('Custom Delivery Address:');

            $(document).on('click', function(event) {
                const $target = $(event.target);
                if ($target.hasClass('overlay') || $target.hasClass('modal') || $target.hasClass('instructions-modal') || $target.hasClass('confirmation-modal')) {
                    if (event.target === event.currentTarget) { // Click was directly on the backdrop
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
        });
    </script>
</body>
</html>