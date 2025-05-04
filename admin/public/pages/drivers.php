<?php
session_start();
// Ensure connection and role check includes are correct relative to this file's location
// Assuming they are two levels up in a 'backend' folder
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_user_id'])) {
    header("Location: ../login.php"); // Adjust path if login is elsewhere
    exit();
}

// Check role permission for Drivers
checkRole('Drivers');

function returnJsonResponse($success, $reload, $message = '', $errors = []) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message, 'errors' => $errors]);
    exit;
}

// --- Process form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (!headers_sent()) { header('Content-Type: application/json'); }

    // ADD DRIVER
    if ($_POST['formType'] == 'add') {
        $name = trim($_POST['name']); $username = trim($_POST['username']); $address = $_POST['address']; $contact_no = trim($_POST['contact_no']); $availability = $_POST['availability']; $area = $_POST['area']; $errors = [];
        if (empty($name)) { $errors['name'] = 'Name is required.'; } elseif (strlen($name) > 40) { $errors['name'] = 'Name cannot exceed 40 characters.'; }
        if (empty($username)) { $errors['username'] = 'Username is required.'; } elseif (strlen($username) > 15) { $errors['username'] = 'Username cannot exceed 15 characters.'; } elseif (str_contains($username, ' ')) { $errors['username'] = 'Username cannot contain spaces.'; }
        else { $checkUserStmt = $conn->prepare("SELECT id FROM drivers WHERE username = ?"); if ($checkUserStmt) { $checkUserStmt->bind_param("s", $username); $checkUserStmt->execute(); $checkUserStmt->store_result(); if ($checkUserStmt->num_rows > 0) { $errors['username'] = 'Username already exists.'; } $checkUserStmt->close(); } else { error_log("Add Driver Username Check Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'DB error checking username.', ['general' => 'DB error checking username.']); } }
        if (empty($contact_no)) { $errors['contact_no'] = 'Contact number is required.'; } elseif (!preg_match('/^\\d{4,12}$/', $contact_no)) { $errors['contact_no'] = 'Contact number must be 4-12 digits.'; } // Adjusted regex and message
        if (empty($address)) { $errors['address'] = 'Address is required.'; }
        if (!in_array($availability, ['Available', 'Not Available'])) { $errors['availability'] = 'Invalid availability status.'; }
        if (!in_array($area, ['North', 'South'])) { $errors['area'] = 'Invalid area.'; }
        if (empty($errors['name'])) { $checkNameStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ?"); if ($checkNameStmt) { $checkNameStmt->bind_param("s", $name); $checkNameStmt->execute(); $checkNameStmt->store_result(); if ($checkNameStmt->num_rows > 0) { $errors['name'] = 'Driver name already exists.'; } $checkNameStmt->close(); } else { error_log("Add Driver Name Check Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'DB error checking name.', ['general' => 'DB error checking name.']); } }
        if (!empty($errors)) { returnJsonResponse(false, false, 'Please correct the errors below.', $errors); }
        $last_four_digits = substr($contact_no, -4); $generated_password = $username . $last_four_digits; $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
        if ($hashed_password === false) { error_log("Password hashing failed for add driver."); returnJsonResponse(false, false, 'Failed to secure password.', ['general' => 'Password hashing failed.']); }
        $stmtAdd = $conn->prepare("INSERT INTO drivers (name, username, password, address, contact_no, availability, area, status, current_deliveries) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 0)");
        if (!$stmtAdd) { error_log("Add Driver Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'Failed to prepare statement.', ['general' => 'Database error.']); }
        $stmtAdd->bind_param("sssssss", $name, $username, $hashed_password, $address, $contact_no, $availability, $area);
        if ($stmtAdd->execute()) { returnJsonResponse(true, true, 'Driver added successfully.'); } else { error_log("Add Driver DB Error: " . $stmtAdd->error); returnJsonResponse(false, false, 'Failed to add driver.', ['general' => 'Database execution error.']); }
        $stmtAdd->close();
    }
    // EDIT DRIVER
    elseif ($_POST['formType'] == 'edit') {
        $id = $_POST['id']; $name = trim($_POST['name']); $username = trim($_POST['username']); $password = $_POST['password']; $address = $_POST['address']; $contact_no = trim($_POST['contact_no']); $availability = $_POST['availability']; $area = $_POST['area']; $status = $_POST['status']; $errors = [];
        if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) { $errors['general'] = 'Invalid Driver ID.'; }
        if (empty($name)) { $errors['name'] = 'Name is required.'; } elseif (strlen($name) > 40) { $errors['name'] = 'Name cannot exceed 40 characters.'; }
        if (empty($username)) { $errors['username'] = 'Username is required.'; } elseif (strlen($username) > 15) { $errors['username'] = 'Username cannot exceed 15 characters.'; } elseif (str_contains($username, ' ')) { $errors['username'] = 'Username cannot contain spaces.'; }
        else { $checkUserStmt = $conn->prepare("SELECT id FROM drivers WHERE username = ? AND id != ?"); if ($checkUserStmt) { $checkUserStmt->bind_param("si", $username, $id); $checkUserStmt->execute(); $checkUserStmt->store_result(); if ($checkUserStmt->num_rows > 0) { $errors['username'] = 'Username already exists.'; } $checkUserStmt->close(); } else { error_log("Edit Driver Username Check Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'DB error checking username.', ['general' => 'DB error checking username.']); } }
        if (!empty($password)) { if (strlen($password) < 6) { $errors['password'] = 'New password must be at least 6 characters long.'; } }
        if (empty($contact_no)) { $errors['contact_no'] = 'Contact number is required.'; } elseif (!preg_match('/^\\d{1,12}$/', $contact_no)) { $errors['contact_no'] = 'Contact number must be only digits (up to 12).'; }
        if (empty($address)) { $errors['address'] = 'Address is required.'; }
        if (!in_array($availability, ['Available', 'Not Available'])) { $errors['availability'] = 'Invalid availability status.'; }
        if (!in_array($area, ['North', 'South'])) { $errors['area'] = 'Invalid area.'; }
        if (!in_array($status, ['Active', 'Archive'])) { $errors['status'] = 'Invalid account status.'; }
        if (empty($errors['name']) && empty($errors['general'])) { $checkNameStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ? AND id != ?"); if ($checkNameStmt) { $checkNameStmt->bind_param("si", $name, $id); $checkNameStmt->execute(); $checkNameStmt->store_result(); if ($checkNameStmt->num_rows > 0) { $errors['name'] = 'Driver name already exists.'; } $checkNameStmt->close(); } else { error_log("Edit Driver Name Check Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'DB error checking name.', ['general' => 'DB error checking name.']); } }
        if (!empty($errors)) { returnJsonResponse(false, false, 'Please correct the errors below.', $errors); }
        $sql_update = "UPDATE drivers SET name = ?, username = ?, address = ?, contact_no = ?, availability = ?, area = ?, status = ?"; $types = "sssssss"; $params_update = [$name, $username, $address, $contact_no, $availability, $area, $status];
        if (!empty($password)) { $hashed_password = password_hash($password, PASSWORD_DEFAULT); if ($hashed_password === false) { error_log("Password hashing failed for edit driver ID: $id"); returnJsonResponse(false, false, 'Failed to secure new password.', ['password' => 'Hashing failed.']); } $sql_update .= ", password = ?"; $types .= "s"; $params_update[] = $hashed_password; }
        $sql_update .= " WHERE id = ?"; $types .= "i"; $params_update[] = $id;
        $stmtEdit = $conn->prepare($sql_update); if (!$stmtEdit) { error_log("Edit Driver Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'Failed to prepare update.', ['general' => 'Database error.']); } $stmtEdit->bind_param($types, ...$params_update);
        if ($stmtEdit->execute()) { returnJsonResponse(true, true, 'Driver updated successfully.'); } else { error_log("Edit Driver DB Error: " . $stmtEdit->error); returnJsonResponse(false, false, 'Failed to update driver.', ['general' => 'Database execution error.']); }
        $stmtEdit->close();
    }
    // CHANGE AVAILABILITY STATUS
    elseif ($_POST['formType'] == 'availability_status') {
        $id = $_POST['id']; $availability_status = $_POST['availability'];
        if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) { returnJsonResponse(false, false, 'Invalid Driver ID.'); } if (!in_array($availability_status, ['Available', 'Not Available'])) { returnJsonResponse(false, false, 'Invalid availability status provided.'); }
        $stmtAvail = $conn->prepare("UPDATE drivers SET availability = ? WHERE id = ?");
        if (!$stmtAvail) { error_log("Change Driver Availability Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'Failed to prepare availability update.'); } $stmtAvail->bind_param("si", $availability_status, $id);
        if ($stmtAvail->execute()) { returnJsonResponse(true, true, 'Availability updated successfully.'); } else { error_log("Change Driver Availability DB Error: " . $stmtAvail->error); returnJsonResponse(false, false, 'Failed to change availability. Database error.'); } $stmtAvail->close();
    }
    // CHANGE ACCOUNT STATUS
    elseif ($_POST['formType'] == 'account_status') {
        $id = $_POST['id']; $account_status = $_POST['status'];
        if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) { returnJsonResponse(false, false, 'Invalid Driver ID.'); } if (!in_array($account_status, ['Active', 'Archive'])) { returnJsonResponse(false, false, 'Invalid account status provided.'); }
        $stmtAccStatus = $conn->prepare("UPDATE drivers SET status = ? WHERE id = ?");
        if (!$stmtAccStatus) { error_log("Change Driver Account Status Prepare Error: " . $conn->error); returnJsonResponse(false, false, 'Failed to prepare account status update.'); } $stmtAccStatus->bind_param("si", $account_status, $id);
        if ($stmtAccStatus->execute()) { returnJsonResponse(true, true, 'Account status updated successfully.'); } else { error_log("Change Driver Account Status DB Error: " . $stmtAccStatus->error); returnJsonResponse(false, false, 'Failed to change account status. Database error.'); } $stmtAccStatus->close();
    }
    exit; // Exit after AJAX processing
}

// --- Handler for fetching driver deliveries (Modal List) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['driver_id']) && isset($_GET['action']) && $_GET['action'] == 'get_deliveries') {
    if (!headers_sent()) { header('Content-Type: application/json'); }
    $driver_id = filter_var($_GET['driver_id'], FILTER_VALIDATE_INT);
    $data = [];
    if ($driver_id === false || $driver_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid Driver ID.']); exit; }
    $stmtModal = $conn->prepare("SELECT da.po_number, o.orders, o.delivery_date, o.delivery_address, o.status, o.username FROM driver_assignments da JOIN orders o ON da.po_number = o.po_number WHERE da.driver_id = ? AND o.status IN ('Active', 'For Delivery', 'In Transit') ORDER BY o.delivery_date ASC");
    if (!$stmtModal) { error_log("[drivers.php get_deliveries] Prepare failed: " . $conn->error); echo json_encode(['success' => false, 'message' => 'DB prepare failed.']); exit; }
    $stmtModal->bind_param("i", $driver_id);
    if (!$stmtModal->execute()) { error_log("[drivers.php get_deliveries] Execute failed: " . $stmtModal->error); echo json_encode(['success' => false, 'message' => 'DB execute failed.']); $stmtModal->close(); exit; }
    $resultModal = $stmtModal->get_result();
    if ($resultModal === false) { error_log("[drivers.php get_deliveries] Get result failed: " . $stmtModal->error); echo json_encode(['success' => false, 'message' => 'DB result failed.']); $stmtModal->close(); exit; }
    if ($resultModal->num_rows > 0) {
        while ($row = $resultModal->fetch_assoc()) {
            $orderItems = json_decode($row['orders'], true);
            if (json_last_error() !== JSON_ERROR_NONE) { error_log("[drivers.php get_deliveries] JSON Decode Error for PO {$row['po_number']}: " . json_last_error_msg() . " | Raw Data: " . $row['orders']); $orderItems = []; } // Handle JSON errors gracefully
            $data[] = [
                'po_number' => $row['po_number'],
                'username' => $row['username'],
                'delivery_date' => $row['delivery_date'],
                'delivery_address' => $row['delivery_address'],
                'status' => $row['status'],
                'items' => $orderItems ?? [] // Use null coalescing operator
            ];
        }
        echo json_encode(['success' => true, 'deliveries' => $data]);
    } else {
        echo json_encode(['success' => true, 'deliveries' => []]); // Return empty array if no deliveries
    }
    $stmtModal->close();
    exit; // Exit after AJAX processing
}

// --- Fetch main list of drivers ---
$status_filter = $_GET['status'] ?? ''; $area_filter = $_GET['area'] ?? ''; $params = []; $types = ""; $main_list_result = null;
$sql = "SELECT d.id, d.name, d.username, d.address, d.contact_no, d.availability, d.area, d.status, d.created_at, COALESCE(COUNT(DISTINCT o.po_number), 0) AS calculated_deliveries FROM drivers d LEFT JOIN driver_assignments da ON d.id = da.driver_id LEFT JOIN orders o ON da.po_number = o.po_number AND o.status IN ('Active', 'For Delivery', 'In Transit') WHERE 1=1";
if (!empty($status_filter)) { $sql .= " AND d.availability = ?"; $params[] = $status_filter; $types .= "s"; }
if (!empty($area_filter)) { $sql .= " AND d.area = ?"; $params[] = $area_filter; $types .= "s"; }
$sql .= " GROUP BY d.id, d.name, d.username, d.address, d.contact_no, d.availability, d.area, d.status, d.created_at ORDER BY d.name ASC";
$stmtMain = $conn->prepare($sql);
if (!$stmtMain) { die("Error preparing driver list: " . $conn->error); }
if (!empty($types)) { if (!$stmtMain->bind_param($types, ...$params)) { $stmtMain->close(); die("Error binding parameters: " . $stmtMain->error); } }
if(!$stmtMain->execute()) { $stmtMain->close(); die("Error executing driver list query: " . $stmtMain->error); }
$main_list_result = $stmtMain->get_result();
if ($main_list_result === false) { $stmtMain->close(); die("Error retrieving driver list results: " . $stmtMain->error); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers List</title>
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/drivers.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        /* Styles exactly as provided by user previously */
        .overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
        .overlay-content { background-color: #fefefe; margin: auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .overlay-content h2 { margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; } /* Added flex properties */
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .account-form label { display: block; margin-bottom: 5px; font-weight: 500; }
        .account-form input[type="text"], .account-form input[type="password"], .account-form select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 5px; }
        .form-buttons { text-align: right; margin-top: 20px; }
        .form-buttons button, .action-buttons button, .add-account-btn, .modal-buttons button, .see-deliveries-btn { /* Combined General Button Styling */
            margin-left: 10px; padding: 8px 15px; border-radius: 40px; border: none; cursor: pointer; font-size: 14px; color: white; transition: background-color 0.3s, box-shadow 0.3s; vertical-align: middle; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; justify-content: center; line-height: 1.5;
        }
        .form-buttons button:first-child, .action-buttons button:first-child { margin-left: 0; } /* Remove margin for first button in group */
        .form-buttons button:hover, .action-buttons button:hover, .add-account-btn:hover, .modal-buttons button:hover, .see-deliveries-btn:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.2); filter: brightness(95%); }
        .form-buttons button:active, .action-buttons button:active, .add-account-btn:active, .modal-buttons button:active, .see-deliveries-btn:active { filter: brightness(90%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); }
        .cancel-btn { background-color: #6c757d; color: white; } /* Keep border-radius if needed, but base style covers it */
        .save-btn { background-color: #28a745; color: white; }
        .edit-btn { background-color: #ffc107; color: #333; }
        .availability-btn { background-color: #17a2b8; color: white;}
        .account-status-btn { background-color: #fd7e14; color: white;}
        .add-account-btn { background-color: #0d6efd; color: white; margin-left: auto; } /* Push to right */
        .see-deliveries-btn { background-color: #6c757d; color: white; padding: 5px 10px; font-size: 13px; } /* Specific smaller style */
        .approve-btn { background-color: #28a745; color: white; }
        .reject-btn { background-color: #dc3545; color: white; }
        .archive-btn { background-color: #ffc107; color: #333; }
        .modal-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
        .modal-buttons.single-button { justify-content: center; }
        .required-asterisk { color: red; margin-left: 3px; }
        .error-message { color: red; font-size: 0.85em; display: block; min-height: 1.2em; margin-top: 0; margin-bottom: 10px; }
        .form-field-error { border: 1px solid red !important; }
        .password-info { font-size: 0.9em; color: #666; margin-bottom: 15px; margin-top: -5px; }
        .accounts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .accounts-header h1 { margin: 0; }
        .filter-section { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: 15px; margin-right: auto; } /* Adjusted margins */
        .filter-section label { margin-bottom: 0; margin-right: 5px; }
        .filter-section select { padding: 6px 10px; font-size: 14px; margin-bottom: 0; margin-right: 10px; } /* Added margin-right */
        .accounts-table-container { overflow-x: auto; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); } /* Added background/shadow */
        .accounts-table { width: 100%; border-collapse: collapse; }
        .accounts-table th, .accounts-table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; text-align: center; vertical-align: middle; }
        .accounts-table th { background-color:rgb(32, 32, 32); font-weight: 600; border-top: 1px solid #dee2e6; }
        .accounts-table tr:last-child td { border-bottom: none; }
        .accounts-table tr:hover { background-color: #f1f3f5; }
        .no-accounts { text-align: center; color: #6c757d; font-style: italic; padding: 15px; }
        .delivery-count { font-weight: bold; padding: 3px 8px; border-radius: 10px; display: inline-block; text-align: center; margin-right: 5px; min-width: 40px; font-size: 0.9em; }
        .delivery-count-low { background-color: #d4edda; color: #155724; }
        .delivery-count-medium { background-color: #fff3cd; color: #856404; }
        .delivery-count-high { background-color: #f8d7da; color: #721c24; }
        .status-available { color: #155724; font-weight: bold; }
        .status-not-available { color: #721c24; font-weight: bold; }
        .account-status-active { color: #198754; font-weight: bold; }
        .account-status-archive { color: #dc3545; font-weight: bold; }
        .action-buttons { white-space: nowrap; text-align: center; }
        .action-buttons button { margin-right: 5px; margin-bottom: 5px; } /* Keep allowing wrap */
        .deliveries-modal-content { max-width: 900px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
        .deliveries-table-container { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        .deliveries-table { width: 100%; border-collapse: collapse; }
        .deliveries-table th, .deliveries-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        .deliveries-table th { background-color: rgb(27, 27, 27); color: white; position: sticky; top: 0; z-index: 1; }
        .no-deliveries { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .delivery-status-badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block; color: #fff; }
        .status-pending { background-color: #ffc107; color: #333;} .status-active { background-color: #0d6efd; } .status-for-delivery { background-color: #17a2b8; } .status-in-transit { background-color: #fd7e14; } .status-completed { background-color: #198754; } .status-rejected { background-color: #dc3545; }
        .po-header { background-color: #f9f9f9; cursor: pointer; transition: background-color 0.2s; } .po-header:hover { background-color: #f0f0f0; }
        .order-items-row { /* Initially hidden by JS */ } .order-items-row.collapsed .order-items { display: none; }
        .order-items { padding: 10px 15px; background-color: #fff; border-left: 3px solid #17a2b8; } .order-items h4 { margin-top: 0; margin-bottom: 10px; font-size: 1em; color: #555; } .order-items table { width: 100%; border-collapse: collapse; font-size: 0.9em; } .order-items th, .order-items td { padding: 6px; text-align: left; border: 1px solid #eee; } .order-items th { background-color:rgb(82, 82, 82); color: white; font-weight: 500; }
        .expand-icon { margin-right: 5px; display: inline-block; transition: transform 0.2s; width: 1em; text-align: center; } .po-header.collapsed .expand-icon { transform: rotate(-90deg); }
        .deliveries-modal-content .modal-buttons { margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; // Adjust path if needed ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Drivers List</h1>
            <div class="filter-section">
                <label for="statusFilter">Availability:</label>
                <select id="statusFilter" onchange="filterDrivers()">
                    <option value="">All</option>
                    <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Not Available" <?= $status_filter == 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                </select>
                <label for="areaFilter">Area:</label>
                <select id="areaFilter" onchange="filterDrivers()">
                    <option value="">All</option>
                    <option value="North" <?= $area_filter == 'North' ? 'selected' : '' ?>>North</option>
                    <option value="South" <?= $area_filter == 'South' ? 'selected' : '' ?>>South</option>
                </select>
            </div>
            <button onclick="openAddDriverForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Driver
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Address</th>
                        <th>Contact No.</th>
                        <th>Area</th>
                        <th>Availability</th>
                        <th>Account Status</th>
                        <th>Active Deliveries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($main_list_result && $main_list_result->num_rows > 0):
                        while ($row = $main_list_result->fetch_assoc()):
                            $active_delivery_count = $row['calculated_deliveries'];
                            $deliveryClass = 'delivery-count-low';
                            if ($active_delivery_count > 15) $deliveryClass = 'delivery-count-high';
                            elseif ($active_delivery_count > 10) $deliveryClass = 'delivery-count-medium'; // Use elseif
                            $availability_class = 'status-' . strtolower(str_replace(' ', '-', $row['availability']));
                            $account_status_class = 'account-status-' . strtolower($row['status']);
                    ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_no']) ?></td>
                                <td><?= htmlspecialchars($row['area']) ?></td>
                                <td class="<?= $availability_class ?>">
                                    <?= htmlspecialchars($row['availability']) ?>
                                </td>
                                <td class="<?= $account_status_class ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </td>
                                <td>
                                    <span class="delivery-count <?= $deliveryClass ?>">
                                         <?= $active_delivery_count ?> / 20
                                    </span>
                                    <button class="see-deliveries-btn" onclick="viewDriverDeliveries(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-list-ul"></i> See List
                                    </button>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditDriverForm(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['username'])) ?>', '<?= htmlspecialchars(addslashes($row['address'])) ?>', '<?= htmlspecialchars($row['contact_no']) ?>', '<?= htmlspecialchars($row['availability']) ?>', '<?= htmlspecialchars($row['area']) ?>', '<?= htmlspecialchars($row['status']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="availability-btn" onclick="openAvailabilityModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-calendar-check"></i> Availability
                                    </button>
                                     <button class="account-status-btn" onclick="openAccountStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-user-cog"></i> Account Status
                                    </button>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="9" class="no-accounts">No drivers found matching criteria.</td>
                        </tr>
                    <?php
                    endif;
                    // Close statement and connection
                    if (isset($stmtMain)) $stmtMain->close();
                    if (isset($conn)) $conn->close(); // Ensure connection is closed if opened
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Driver Modal -->
    <div id="addDriverOverlay" class="overlay" style="display: none;">
         <div class="overlay-content">
            <span class="close-btn" onclick="closeAddDriverForm()">&times;</span>
            <h2><i class="fas fa-user-plus"></i> Add New Driver</h2>
            <div id="addDriverError" class="error-message"></div>
            <form id="addDriverForm" method="POST" class="account-form" action="" novalidate>
                <input type="hidden" name="formType" value="add">
                <label for="add-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-name" name="name" required maxlength="40"> <span id="addNameError" class="error-message"></span>
                <label for="add-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-username" name="username" required maxlength="15"> <span id="addUsernameError" class="error-message"></span>
                <p class="password-info">Password will be automatically generated.</p>
                <label for="add-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-address" name="address" required> <span id="addAddressError" class="error-message"></span>
                <label for="add-contact_no">Contact No.: (4-12 digits)<span class="required-asterisk">*</span></label>
                <input type="text" id="add-contact_no" name="contact_no" required maxlength="12" pattern="^\d{4,12}$" title="Must be 4 to 12 digits"> <span id="addContactError" class="error-message"></span>
                <label for="add-area">Area:<span class="required-asterisk">*</span></label>
                <select id="add-area" name="area" required> <option value="">Select Area</option><option value="North">North</option> <option value="South">South</option> </select> <span id="addAreaError" class="error-message"></span>
                <label for="add-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="add-availability" name="availability" required> <option value="">Select Status</option><option value="Available">Available</option> <option value="Not Available">Not Available</option> </select> <span id="addAvailabilityError" class="error-message"></span>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddDriverForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Driver Modal -->
    <div id="editDriverOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <span class="close-btn" onclick="closeEditDriverForm()">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Driver</h2>
            <div id="editDriverError" class="error-message"></div>
            <form id="editDriverForm" method="POST" class="account-form" action="" novalidate>
                <input type="hidden" name="formType" value="edit"> <input type="hidden" id="edit-id" name="id">
                <label for="edit-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-name" name="name" required maxlength="40"> <span id="editNameError" class="error-message"></span>
                <label for="edit-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-username" name="username" required maxlength="15"> <span id="editUsernameError" class="error-message"></span>
                <label for="edit-password">New Password: (Leave blank to keep current)</label>
                <input type="password" id="edit-password" name="password"> <span id="editPasswordError" class="error-message"></span>
                <label for="edit-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-address" name="address" required> <span id="editAddressError" class="error-message"></span>
                <label for="edit-contact_no">Contact No.: (Up to 12 digits)<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-contact_no" name="contact_no" required maxlength="12" pattern="^\d{1,12}$" title="Must be only digits (up to 12)"> <span id="editContactError" class="error-message"></span>
                <label for="edit-area">Area:<span class="required-asterisk">*</span></label>
                <select id="edit-area" name="area" required> <option value="">Select Area</option><option value="North">North</option> <option value="South">South</option> </select> <span id="editAreaError" class="error-message"></span>
                <label for="edit-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="edit-availability" name="availability" required> <option value="">Select Status</option><option value="Available">Available</option> <option value="Not Available">Not Available</option> </select> <span id="editAvailabilityError" class="error-message"></span>
                <label for="edit-status">Account Status:<span class="required-asterisk">*</span></label>
                <select id="edit-status" name="status" required> <option value="">Select Status</option><option value="Active">Active</option> <option value="Archive">Archive</option> </select> <span id="editStatusError" class="error-message"></span>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditDriverForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change AVAILABILITY Modal -->
    <div id="availabilityModal" class="overlay" style="display: none;">
         <div class="overlay-content">
            <span class="close-btn" onclick="closeAvailabilityModal()">&times;</span>
            <h2><i class="fas fa-calendar-check"></i> Change Driver Availability</h2>
            <p id="availabilityMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeAvailability('Available')">
                    <i class="fas fa-check-circle"></i> Set Available
                </button>
                <button class="reject-btn" onclick="changeAvailability('Not Available')">
                    <i class="fas fa-times-circle"></i> Set Not Available
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeAvailabilityModal()">
                    <i class="fas fa-ban"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Change ACCOUNT STATUS Modal -->
    <div id="accountStatusModal" class="overlay" style="display: none;">
         <div class="overlay-content">
            <span class="close-btn" onclick="closeAccountStatusModal()">&times;</span>
            <h2><i class="fas fa-user-cog"></i> Change Account Status</h2>
            <p id="accountStatusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeAccountStatus('Active')">
                    <i class="fas fa-user-check"></i> Set Active
                </button>
                <button class="archive-btn" onclick="changeAccountStatus('Archive')">
                    <i class="fas fa-user-slash"></i> Set Archive
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeAccountStatusModal()">
                    <i class="fas fa-ban"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Deliveries List Modal (FIXED HTML) -->
    <div id="deliveriesModal" class="overlay" style="display: none;">
         <div class="overlay-content deliveries-modal-content">
             <span class="close-btn" onclick="closeDeliveriesModal()">&times;</span>
            <h2><i class="fas fa-truck-loading"></i> <span id="deliveriesModalTitle">Driver's Deliveries</span></h2>
            <div id="deliveriesTableContainer" class="deliveries-table-container">
                <table class="deliveries-table" id="deliveriesTable">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Delivery Date</th>
                            <th>Delivery Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <!-- THIS TBODY NOW HAS THE CORRECT ID -->
                    <tbody id="deliveriesTableBody">
                        <tr><td colspan="4" class="no-deliveries">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeDeliveriesModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript includes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script> <!-- Assuming toast.js initializes toastr -->

    <!-- COMPLETE JAVASCRIPT BLOCK -->
    <script>
        let currentDriverId = null;

        // --- Form Error Handling ---
        function clearFormErrors(formId) { $(`#${formId} .error-message`).text(''); $(`#${formId} input, #${formId} select`).removeClass('form-field-error'); $(`#${formId}Error`).text(''); }
        function displayFormErrors(formId, errors) { clearFormErrors(formId); if (errors && typeof errors === 'object') { $.each(errors, function(field, message) { const inputElement = $(`#${formId} [name="${field}"]`); const errorElementId = `#${formId.replace('Form', '')}${field.charAt(0).toUpperCase() + field.slice(1)}Error`; const errorElement = $(errorElementId); if (inputElement.length) { inputElement.addClass('form-field-error'); } if (errorElement.length) { errorElement.text(message); } else if (field === 'general') { $(`#${formId.replace('Form', '')}Error`).text(message); } }); } }

        // --- Modal Control ---
        function openAddDriverForm() { clearFormErrors('addDriverForm'); $('#addDriverForm')[0].reset(); $('#addDriverOverlay').css('display', 'flex'); }
        function closeAddDriverForm() { $('#addDriverOverlay').hide(); clearFormErrors('addDriverForm'); }
        function openEditDriverForm(id, name, username, address, contact_no, availability, area, status) { clearFormErrors('editDriverForm'); $('#edit-id').val(id); $('#edit-name').val(name); $('#edit-username').val(username); $('#edit-password').val(''); $('#edit-address').val(address); $('#edit-contact_no').val(contact_no); $('#edit-availability').val(availability); $('#edit-area').val(area); $('#edit-status').val(status); $('#editDriverOverlay').css('display', 'flex'); }
        function closeEditDriverForm() { $('#editDriverOverlay').hide(); clearFormErrors('editDriverForm'); }
        function closeDeliveriesModal() { $('#deliveriesModal').hide(); }
        function openAvailabilityModal(id, name) { currentDriverId = id; $('#availabilityMessage').text(`Change work availability for driver: ${name}`); $('#availabilityModal').css('display', 'flex'); }
        function closeAvailabilityModal() { $('#availabilityModal').hide(); currentDriverId = null; }
        function openAccountStatusModal(id, name) { currentDriverId = id; $('#accountStatusMessage').text(`Change account login status for driver: ${name}`); $('#accountStatusModal').css('display', 'flex'); }
        function closeAccountStatusModal() { $('#accountStatusModal').hide(); currentDriverId = null; }

        // --- Deliveries Modal Logic ---
        function toggleOrderItems(headerRow) {
            const itemsRow = headerRow.nextElementSibling;
            if (itemsRow && itemsRow.classList.contains('order-items-row')) {
                headerRow.classList.toggle('collapsed');
                $(itemsRow).toggle(!headerRow.classList.contains('collapsed'));
            }
        }
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                return isNaN(date.getTime()) ? 'Invalid Date' : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) { console.error("Error formatting date:", dateStr, e); return 'Invalid Date'; }
        }
        function formatCurrency(amount) {
            const num = parseFloat(amount);
            return isNaN(num) ? '₱--.--' : '₱' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        function viewDriverDeliveries(id, name) {
            // Check if elements exist BEFORE trying to use them
            if ($('#deliveriesModal').length === 0) { console.error("Deliveries modal (#deliveriesModal) not found."); if(typeof showToast === 'function') showToast("Error: Delivery details modal is missing.", "error"); return; }
            if ($('#deliveriesTableBody').length === 0) { console.error("Deliveries table body (#deliveriesTableBody) not found."); if(typeof showToast === 'function') showToast("Error: Delivery details table is missing.", "error"); return; }

            $('#deliveriesModalTitle').text(`${name}'s Active/Pending Deliveries`);
            $('#deliveriesModal').css('display', 'flex'); // Show modal
            const tableBody = $('#deliveriesTableBody');
            tableBody.html('<tr><td colspan="4" class="no-deliveries"><i class="fas fa-spinner fa-spin"></i> Loading deliveries...</td></tr>'); // Set loading state

            fetch(`drivers.php?driver_id=${id}&action=get_deliveries`)
                .then(response => {
                    if (!response.ok) {
                        // Try to get more info from non-JSON response
                        return response.text().then(text => {
                            console.error(`Server Error (${response.status}) fetching deliveries for driver ${id}:`, text);
                            throw new Error(`Server error (${response.status}). Check server logs or Network tab response.`);
                        });
                    }
                    return response.json(); // Only parse if response is ok
                })
                .then(data => {
                    if (data.success && data.deliveries) {
                        if (data.deliveries.length > 0) {
                            let html = '';
                            data.deliveries.forEach((delivery) => {
                                let statusClass = 'status-' + (delivery.status ? delivery.status.toLowerCase().replace(/\s+/g, '-') : 'unknown');
                                html += `<tr class="po-header collapsed" onclick="toggleOrderItems(this)">`;
                                html += `<td><span class="expand-icon">▼</span> ${delivery.po_number || 'N/A'}</td>`;
                                html += `<td>${formatDate(delivery.delivery_date)}</td>`;
                                html += `<td>${delivery.delivery_address || 'N/A'}</td>`;
                                html += `<td><span class="delivery-status-badge ${statusClass}">${delivery.status || 'Unknown'}</span></td>`;
                                html += `</tr>`;
                                html += `<tr class="order-items-row" style="display: none;"><td colspan="4" class="order-items">`;
                                html += `<h4>Order Items (${delivery.username || 'N/A'})</h4>`;
                                html += `<table><thead><tr><th>Item</th><th>Packaging</th><th>Quantity</th><th>Price</th></tr></thead><tbody>`;
                                if (delivery.items && Array.isArray(delivery.items) && delivery.items.length > 0) {
                                    delivery.items.forEach(item => { html += `<tr><td>${item.item_description || 'N/A'}</td><td>${item.packaging || 'N/A'}</td><td>${item.quantity || 0}</td><td>${formatCurrency(item.price)}</td></tr>`; });
                                } else { html += `<tr><td colspan="4" style="text-align: center; color: #888;">No item details available.</td></tr>`; }
                                html += `</tbody></table></td></tr>`;
                            });
                            tableBody.html(html); // Populate table body
                        } else {
                            tableBody.html('<tr><td colspan="4" class="no-deliveries">No active or pending deliveries found.</td></tr>');
                        }
                    } else {
                        console.error('API reported failure or missing delivery data:', data.message);
                        tableBody.html(`<tr><td colspan="4" class="no-deliveries">Error loading deliveries: ${data.message || 'Data format error'}</td></tr>`);
                    }
                })
                .catch(error => {
                    console.error('Fetch/Processing Error:', error);
                    tableBody.html(`<tr><td colspan="4" class="no-deliveries">Failed to load delivery details. ${error.message}</td></tr>`);
                });
        }
        // --- END Deliveries Modal Logic ---

        // --- Change AVAILABILITY Status AJAX ---
        function changeAvailability(availability) {
             if (currentDriverId === null) { if(typeof showToast === 'function') showToast('No driver selected.', 'error'); return; }
             $.ajax({ url: 'drivers.php', type: 'POST', data: { ajax: true, formType: 'availability_status', id: currentDriverId, availability: availability }, dataType: 'json',
                 success: function(response) { if (response.success && response.reload) { if(typeof showToast === 'function') showToast(response.message || 'Availability updated', 'success'); setTimeout(() => window.location.reload(), 1500); } else { if(typeof showToast === 'function') showToast(response.message || 'Failed to update availability', 'error'); } closeAvailabilityModal(); },
                 error: function(xhr, status, error) { console.error("Change Availability AJAX Error:", status, error, xhr.responseText); if(typeof showToast === 'function') showToast('An error occurred: ' + error, 'error'); closeAvailabilityModal(); }
             });
        }

         // --- Change ACCOUNT Status AJAX ---
        function changeAccountStatus(status) {
             if (currentDriverId === null) { if(typeof showToast === 'function') showToast('No driver selected.', 'error'); return; }
             $.ajax({ url: 'drivers.php', type: 'POST', data: { ajax: true, formType: 'account_status', id: currentDriverId, status: status }, dataType: 'json',
                 success: function(response) { if (response.success && response.reload) { if(typeof showToast === 'function') showToast(response.message || 'Account status updated', 'success'); setTimeout(() => window.location.reload(), 1500); } else { if(typeof showToast === 'function') showToast(response.message || 'Failed to update account status', 'error'); } closeAccountStatusModal(); },
                 error: function(xhr, status, error) { console.error("Change Account Status AJAX Error:", status, error, xhr.responseText); if(typeof showToast === 'function') showToast('An error occurred: ' + error, 'error'); closeAccountStatusModal(); }
             });
        }

        // --- Filtering ---
        function filterDrivers() { const status = document.getElementById('statusFilter').value; const area = document.getElementById('areaFilter').value; const params = new URLSearchParams(window.location.search); params.set('status', status); params.set('area', area); window.location.href = `drivers.php?${params.toString()}`; }

        // --- Client-Side Validation Functions ---
        function validateContactNumber(contactInput, errorElement) {
            const contactValue = $(contactInput).val().trim();
            const pattern = /^\d{4,12}$/; // Use the 4-12 pattern for add form
            if (!contactValue) { $(errorElement).text('Contact number is required.'); $(contactInput).addClass('form-field-error'); return false; }
            else if (!pattern.test(contactValue) && $(contactInput).closest('form').attr('id') === 'addDriverForm') { $(errorElement).text('Must be 4 to 12 digits.'); $(contactInput).addClass('form-field-error'); return false; }
            else if (!/^\d{1,12}$/.test(contactValue) && $(contactInput).closest('form').attr('id') === 'editDriverForm') { // Use 1-12 for edit
                 $(errorElement).text('Must be only digits (up to 12).'); $(contactInput).addClass('form-field-error'); return false;
            }
            $(errorElement).text(''); $(contactInput).removeClass('form-field-error'); return true;
        }
        function validateRequired(inputElement, errorElement, fieldName) {
            const value = $(inputElement).val().trim();
            if (!value) { $(errorElement).text(`${fieldName} is required.`); $(inputElement).addClass('form-field-error'); return false; }
            $(errorElement).text(''); $(inputElement).removeClass('form-field-error'); return true;
        }
        function validateMaxLength(inputElement, errorElement, maxLength, fieldName) {
             const value = $(inputElement).val().trim();
             if (value.length > maxLength) { $(errorElement).text(`${fieldName} cannot exceed ${maxLength} characters.`); $(inputElement).addClass('form-field-error'); return false; }
             if (!$(errorElement).text().includes('required')) { $(errorElement).text(''); $(inputElement).removeClass('form-field-error'); }
             return true;
        }
        function validateNoSpaces(inputElement, errorElement, fieldName) {
             const value = $(inputElement).val();
             if (value.includes(' ')) { $(errorElement).text(`${fieldName} cannot contain spaces.`); $(inputElement).addClass('form-field-error'); return false; }
             if (!$(errorElement).text().includes('required') && !$(errorElement).text().includes('characters')) { $(errorElement).text(''); $(inputElement).removeClass('form-field-error'); }
             return true;
        }
        function validatePasswordLength(inputElement, errorElement, minLength = 6) {
            const value = $(inputElement).val();
            if (value && value.length < minLength) { $(errorElement).text(`Password must be at least ${minLength} characters.`); $(inputElement).addClass('form-field-error'); return false; }
            $(errorElement).text(''); $(inputElement).removeClass('form-field-error'); return true;
        }
        function validateSelect(selectElement, errorElement, fieldName) {
            const value = $(selectElement).val();
            if (!value) { $(errorElement).text(`${fieldName} is required.`); $(selectElement).addClass('form-field-error'); return false; }
            $(errorElement).text(''); $(selectElement).removeClass('form-field-error'); return true;
        }
        // --- END Client-Side Validation Functions ---

        // --- Document Ready ---
        $(document).ready(function() {
            // Modal closing
            $(document).on('click', '.overlay', function(event) { if (event.target === this) $(this).hide(); });
            $(document).on('click', '.overlay-content', function(event) { event.stopPropagation(); });

            // Live Validation Bindings
            $('#add-name').on('input blur', function() { validateRequired(this, '#addNameError', 'Name') && validateMaxLength(this, '#addNameError', 40, 'Name'); });
            $('#add-username').on('input blur', function() { validateRequired(this, '#addUsernameError', 'Username') && validateMaxLength(this, '#addUsernameError', 15, 'Username') && validateNoSpaces(this, '#addUsernameError', 'Username'); });
            $('#add-address').on('input blur', function() { validateRequired(this, '#addAddressError', 'Address'); });
            $('#add-contact_no').on('input blur', function() { validateContactNumber(this, '#addContactError'); }); // Uses updated validation
            $('#add-area').on('change blur', function() { validateSelect(this, '#addAreaError', 'Area'); });
            $('#add-availability').on('change blur', function() { validateSelect(this, '#addAvailabilityError', 'Availability'); });

            $('#edit-name').on('input blur', function() { validateRequired(this, '#editNameError', 'Name') && validateMaxLength(this, '#editNameError', 40, 'Name'); });
            $('#edit-username').on('input blur', function() { validateRequired(this, '#editUsernameError', 'Username') && validateMaxLength(this, '#editUsernameError', 15, 'Username') && validateNoSpaces(this, '#editUsernameError', 'Username'); });
            $('#edit-address').on('input blur', function() { validateRequired(this, '#editAddressError', 'Address'); });
            $('#edit-contact_no').on('input blur', function() { validateContactNumber(this, '#editContactError'); }); // Uses updated validation
            $('#edit-password').on('input blur', function() { if ($(this).val()) validatePasswordLength(this, '#editPasswordError'); else { $('#editPasswordError').text(''); $(this).removeClass('form-field-error'); } });
            $('#edit-area').on('change blur', function() { validateSelect(this, '#editAreaError', 'Area'); });
            $('#edit-availability').on('change blur', function() { validateSelect(this, '#editAvailabilityError', 'Availability'); });
            $('#edit-status').on('change blur', function() { validateSelect(this, '#editStatusError', 'Account Status'); });

            // Add Form Submit Handler
            $('#addDriverForm').on('submit', function(e) {
                e.preventDefault(); clearFormErrors('addDriverForm'); let isValid = true;
                isValid &= validateRequired('#add-name', '#addNameError', 'Name'); isValid &= validateMaxLength('#add-name', '#addNameError', 40, 'Name');
                isValid &= validateRequired('#add-username', '#addUsernameError', 'Username'); isValid &= validateMaxLength('#add-username', '#addUsernameError', 15, 'Username'); isValid &= validateNoSpaces('#add-username', '#addUsernameError', 'Username');
                isValid &= validateRequired('#add-address', '#addAddressError', 'Address');
                isValid &= validateContactNumber('#add-contact_no', '#addContactError'); // Uses updated validation
                isValid &= validateSelect('#add-area', '#addAreaError', 'Area');
                isValid &= validateSelect('#add-availability', '#addAvailabilityError', 'Availability');
                if (!isValid) { if (typeof showToast === 'function') showToast('Please correct the errors.', 'warning'); return false; }
                $.ajax({ url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) { if (response.success && response.reload) { if (typeof showToast === 'function') showToast(response.message || 'Driver added', 'success'); closeAddDriverForm(); setTimeout(() => window.location.reload(), 1500); } else { displayFormErrors('addDriverForm', response.errors); if (typeof showToast === 'function') showToast(response.message || 'Failed to add driver.', 'error'); } },
                    error: function(xhr, status, error) { console.error("Add AJAX Error:", status, error, xhr.responseText); $('#addDriverError').text('Request error.'); if (typeof showToast === 'function') showToast('Request error: ' + error, 'error'); }
                });
            });

            // Edit Form Submit Handler
            $('#editDriverForm').on('submit', function(e) {
                e.preventDefault(); clearFormErrors('editDriverForm'); let isValid = true;
                isValid &= validateRequired('#edit-name', '#editNameError', 'Name'); isValid &= validateMaxLength('#edit-name', '#editNameError', 40, 'Name');
                isValid &= validateRequired('#edit-username', '#editUsernameError', 'Username'); isValid &= validateMaxLength('#edit-username', '#editUsernameError', 15, 'Username'); isValid &= validateNoSpaces('#edit-username', '#editUsernameError', 'Username');
                isValid &= validateRequired('#edit-address', '#editAddressError', 'Address');
                isValid &= validateContactNumber('#edit-contact_no', '#editContactError'); // Uses updated validation
                if ($('#edit-password').val()) { isValid &= validatePasswordLength('#edit-password', '#editPasswordError'); }
                isValid &= validateSelect('#edit-area', '#editAreaError', 'Area');
                isValid &= validateSelect('#edit-availability', '#editAvailabilityError', 'Availability');
                isValid &= validateSelect('#edit-status', '#editStatusError', 'Account Status');
                if (!isValid) { if (typeof showToast === 'function') showToast('Please correct the errors.', 'warning'); return false; }
                $.ajax({ url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) { if (response.success && response.reload) { if (typeof showToast === 'function') showToast(response.message || 'Driver updated', 'success'); closeEditDriverForm(); setTimeout(() => window.location.reload(), 1500); } else { displayFormErrors('editDriverForm', response.errors); if (typeof showToast === 'function') showToast(response.message || 'Failed to update driver.', 'error'); } },
                    error: function(xhr, status, error) { console.error("Edit AJAX Error:", status, error, xhr.responseText); $('#editDriverError').text('Request error.'); if (typeof showToast === 'function') showToast('Request error: ' + error, 'error'); }
                });
            });
        });
    </script>
</body>
</html>