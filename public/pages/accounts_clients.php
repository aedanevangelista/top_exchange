<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Clients');


error_reporting(0);


function validateUnique($conn, $username, $email, $id = null) {
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE (username = ? OR email = ?)";
    if ($id) {
        $query .= " AND id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($id) {
        $stmt->bind_param("ssi", $username, $email, $id);
    } else {
        $stmt->bind_param("ss", $username, $email);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] > 0;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    header('Content-Type: application/json');

    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $region = $_POST['region'];
    $city = $_POST['city'];
    $company = $_POST['company'] ?? null; 
    $company_address = $_POST['company_address'];
    $bill_to = $_POST['bill_to'] ?? null;
    $bill_to_attn = $_POST['bill_to_attn'] ?? null;
    $ship_to = $_POST['ship_to'] ?? null;
    $ship_to_attn = $_POST['ship_to_attn'] ?? null;
    $business_proof = [];

    if (validateUnique($conn, $username, $email)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $username . '/';
    if (!file_exists($user_upload_dir)) {
        mkdir($user_upload_dir, 0777, true);
    }

    if (isset($_FILES['business_proof'])) {
        if (count($_FILES['business_proof']['name']) > 3) {
            echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']);
            exit;
        }
        foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['business_proof']['error'][$key] == 0) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 20 * 1024 * 1024; 
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];

                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                    $business_proof_path = '/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $user_upload_dir . basename($_FILES['business_proof']['name'][$key]))) {
                        $business_proof[] = $business_proof_path;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
                    exit;
                }
            }
        }
    }

    $business_proof_json = json_encode($business_proof);

    $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, bill_to, bill_to_attn, ship_to, ship_to_attn, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssssssssssss", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to, $bill_to_attn, $ship_to, $ship_to_attn, $business_proof_json);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add account.']);
    }

    $stmt->close();
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'edit') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $region = $_POST['region'];
    $city = $_POST['city'];
    $company = $_POST['company'] ?? null; 
    $company_address = $_POST['company_address'];
    $bill_to = $_POST['bill_to'] ?? null;
    $bill_to_attn = $_POST['bill_to_attn'] ?? null;
    $ship_to = $_POST['ship_to'] ?? null;
    $ship_to_attn = $_POST['ship_to_attn'] ?? null;
    $business_proof = [];

    if (validateUnique($conn, $username, $email, $id)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

   
    $old_username = '';
    $stmt = $conn->prepare("SELECT username FROM clients_accounts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $old_username = $row['username'];
    }
    $stmt->close();

   
    $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $username . '/';
    if (!file_exists($user_upload_dir)) {
        mkdir($user_upload_dir, 0777, true);
    }

  
    $existing_business_proof = json_decode($_POST['existing_business_proof'], true) ?? [];
    $old_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $old_username . '/';

  
    if (isset($_FILES['business_proof']) && !empty($_FILES['business_proof']['name'][0])) {
        $business_proof = [];

      
        if ($old_username !== $username && !file_exists($user_upload_dir)) {
            mkdir($user_upload_dir, 0777, true);
        }


        if (file_exists($old_upload_dir)) {
            $old_files = array_diff(scandir($old_upload_dir), array('.', '..'));
            foreach ($old_files as $file) {
                @unlink($old_upload_dir . $file);
            }
        }


        if (count($_FILES['business_proof']['name']) > 3) {
            echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']);
            exit;
        }

        foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['business_proof']['error'][$key] == 0) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 20 * 1024 * 1024;
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];

                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                    $business_proof_path = '/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $user_upload_dir . basename($_FILES['business_proof']['name'][$key]))) {
                        $business_proof[] = $business_proof_path;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
                    exit;
                }
            }
        }
    } else {
       
        if ($old_username !== $username) {
           
            foreach ($existing_business_proof as $index => $old_path) {
                $old_file = basename($old_path);
                $old_file_path = $old_upload_dir . $old_file;
                $new_file_path = $user_upload_dir . $old_file;
                $new_path = '/uploads/' . $username . '/' . $old_file;

                if (file_exists($old_file_path) && copy($old_file_path, $new_file_path)) {
                    $business_proof[] = $new_path;
                    @unlink($old_file_path); 
                }
            }
            
           
            if (file_exists($old_upload_dir) && count(array_diff(scandir($old_upload_dir), array('.', '..'))) == 0) {
                @rmdir($old_upload_dir);
            }
        } else {
           
            $business_proof = $existing_business_proof;
        }
    }

  
    $business_proof_json = json_encode($business_proof);

    $stmt = $conn->prepare("UPDATE clients_accounts SET username = ?, password = ?, email = ?, phone = ?, region = ?, city = ?, company = ?, company_address = ?, bill_to = ?, bill_to_attn = ?, ship_to = ?, ship_to_attn = ?, business_proof = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssssi", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to, $bill_to_attn, $ship_to, $ship_to_attn, $business_proof_json, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update account.']);
    }

    $stmt->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'status') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE clients_accounts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change status.']);
    }

    $stmt->close();
    exit;
}


$status_filter = $_GET['status'] ?? '';

$sql = "SELECT id, username, email, phone, region, city, company, company_address, bill_to, bill_to_attn, ship_to, ship_to_attn, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'";
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
}
$sql .= " ORDER BY 
    CASE 
        WHEN status = 'Pending' THEN 1
        WHEN status = 'Active' THEN 2
        WHEN status = 'Rejected' THEN 3
        WHEN status = 'Inactive' THEN 4
    END, created_at DESC"; // Changed from ASC to DESC to show newest first
$stmt = $conn->prepare($sql);
if (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();

function truncate($text, $max = 15) {
    return (strlen($text) > $max) ? substr($text, 0, $max) . '...' : $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Accounts</title>
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/accounts_clients.css">
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        #myModal {
            display: none;
            position: fixed;
            z-index: 9999; 
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 80%;
            max-height: 80%;
        }

        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 10px 0;
            height: 150px;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
        }

        .photo-album img {
            cursor: pointer;
            transition: all 0.3s;
        }

        .photo-album img:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        .file-info {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }
        
        /* New styles for the 2-column layout */
        .two-column-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px; /* Reduced gap */
        }
        
        .form-column {
            display: flex;
            flex-direction: column;
        }
        
        .form-divider {
            width: 1px;
            background-color: #ddd;
            margin: 0 auto;
        }
        
        .form-full-width {
            grid-column: 1 / span 2;
        }
        
        .required {
            color: #ff0000;
            font-weight: bold;
        }
        
        .optional {
            color: #666;
            font-style: italic;
            font-size: 0.9em;
        }
        
        .overlay-content {
            max-width: 800px;
            width: 90%;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Make inputs a bit wider as they have more space now */
        .two-column-form input, 
        .two-column-form textarea {
            width: 100%;
        }
        
        /* Enhanced company address textarea */
        textarea#company_address, textarea#edit-company_address,
        textarea#bill_to, textarea#edit-bill_to,
        textarea#ship_to, textarea#edit-ship_to {
            height: 60px; /* Smaller text areas */
            padding: 8px; /* Reduced padding */
            font-size: 14px; /* Increased font size by 1px */
            resize: vertical; /* Allow vertical resizing only */
            min-height: 60px; /* Smaller minimum height */
        }
        
        /* Style consistent input borders */
        input, textarea {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 10px; /* Reduced padding */
            transition: border-color 0.3s;
            outline: none;
            font-size: 14px; /* Increased font size by 1px */
        }
        
        input:focus, textarea:focus {
            border-color: #4a90fe;
            box-shadow: 0 0 5px rgba(77, 144, 254, 0.5);
        }
        
        /* Style placeholder text */
        input::placeholder, textarea::placeholder {
            color: #aaa;
            padding: 4px;
            font-style: italic;
        }

        /* View address button styles */
        .view-address-btn, .view-contact-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .view-address-btn:hover, .view-contact-btn:hover {
            background-color: #357abf;
        }

        /* Improved Info Modal Styles - Unified Design */
        #addressInfoModal, #contactInfoModal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }

        .info-modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 700px;
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .info-modal-header {
            background-color: #4a90e2;
            color: #fff;
            padding: 15px 25px;
            position: relative;
            display: flex;
            align-items: center;
            border-radius: 10px 10px 0 0;
        }

        .info-modal-header h2 {
            margin: 0;
            font-size: 20px;
            flex: 1;
            font-weight: 500;
        }

        .info-modal-header h2 i {
            margin-right: 10px;
        }

        .info-modal-close {
            color: #fff;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            padding: 5px;
            line-height: 1;
        }

        .info-modal-close:hover {
            transform: scale(1.1);
        }

        .info-modal-body {
            padding: 25px;
            max-height: auto; /* No max-height to prevent scroll */
        }

        .info-section {
            margin-bottom: 25px;
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-section-title {
            display: flex;
            align-items: center;
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px; /* Increased font size by 1px */
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-section-title i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Unified table styling */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .info-table th {
            text-align: left;
            background-color: #eef5ff;
            padding: 12px 15px;
            border: 1px solid #d1e1f9;
            width: 30%;
            vertical-align: top;
            color: #3a5d85;
            font-weight: 600;
            font-size: 14px; /* Increased font size by 1px */
        }

        .info-table td {
            padding: 12px 15px;
            border: 1px solid #d1e1f9;
            word-break: break-word;
            vertical-align: top;
            line-height: 1.5;
            color: #333;
            background-color: #fff;
            font-size: 14px; /* Increased font size by 1px */
        }

        /* Contact info styling */
        .contact-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #fff;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #d1e1f9;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .contact-icon {
            width: 45px;
            height: 45px;
            background-color: #eef5ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a90e2;
            font-size: 18px;
            margin-right: 15px;
        }

        .contact-text {
            flex: 1;
        }

        .contact-value {
            font-weight: bold;
            color: #333;
            font-size: 14px; /* Increased font size by 1px */
            word-break: break-all;
        }

        .contact-label {
            font-size: 13px; /* Increased font size by 1px */
            color: #777;
            display: block;
            margin-top: 5px;
        }

        /* Attention styling */
        .attention-info {
            display: flex;
            align-items: center;
            margin-top: 5px;
            font-size: 14px; /* Increased font size by 1px */
        }

        .attention-info i {
            color: #4a90e2;
            margin-right: 8px;
            font-size: 15px; /* Increased font size by 1px */
        }

        .attention-info strong {
            color: #3a5d85;
            margin-right: 5px;
        }

        .empty-notice {
            padding: 20px;
            text-align: center;
            color: #888;
            font-style: italic;
            border: 1px dashed #d1e1f9;
            border-radius: 6px;
            font-size: 14px; /* Increased font size by 1px */
        }

        /* Overlays */
        .overlay {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }

        /* Address groups in forms */
        .address-group {
            border: 1px solid #eee;
            padding: 12px; /* Reduced padding */
            border-radius: 8px;
            margin-bottom: 15px; /* Reduced margin */
            background-color: #fafafa;
        }

        .address-group h3 {
            margin-top: 0;
            color: #4a90e2;
            font-size: 15px; /* Increased font size by 1px */
            margin-bottom: 12px; /* Reduced margin */
            border-bottom: 1px solid #eee;
            padding-bottom: 6px; /* Reduced padding */
        }

        .attention-title {
            display: flex;
            align-items: center;
            margin: 10px 0 5px 0;
            font-size: 14px; /* Increased font size by 1px */
            color: #4a90e2;
        }
        
        .attention-title i {
            margin-right: 5px;
        }
        
        /* Attention field styles */
        .attention-field {
            width: 100%;
            margin-top: 6px; /* Reduced margin */
            text-align: center;
        }
        
        .attention-field input {
            width: 100%;
            text-align: center;
            font-size: 14px; /* Increased font size by 1px */
        }

        /* Fixed header and footer in modal */
        .modal-header {
            background-color: #ffffff;
            padding: 12px; /* Reduced padding */
            text-align: center;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid black;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header h2 {
            margin: 0;
            padding: 0;
            font-size: 18px; /* Reduced font size */
        }

        .modal-footer {
            background-color: #ffffff;
            padding: 12px 12px; /* Reduced padding */
            border-top: 1px solid black;
            text-align: center; /* Center the buttons */
            border-radius: 0 0 8px 8px;
            position: sticky;
            bottom: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: auto; /* Push footer to bottom of content */
        }

        .modal-body {
            padding: 15px; /* Reduced padding */
            overflow-y: auto; /* Add scrollbar to modal body */
            max-height: calc(85vh - 110px); /* Subtracting approximate header/footer height */
            height: auto; /* Let content determine height */
        }

        /* Style for form modals with fixed header and footer */
        .form-modal-content {
            display: flex;
            flex-direction: column;
            max-height: 85vh; /* Changed from fixed height to max-height */
            height: auto; /* Let content determine height */
            width: 80%;
            max-width: 650px;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden; /* Keep this to contain child overflow */
            margin: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* Label styling - normal */
        label {
            font-size: 14px; /* Increased font size by 1px */
            margin-bottom: 4px; /* Less spacing */
        }

        /* Error message styling */
        .error-message {
            color: #ff3333;
            padding: 5px 0;
            font-size: 14px;
        }
        
        /* Form buttons - improved */
        .modal-footer button {
            padding: 8px 16px; /* Better padding */
            font-size: 14px; /* Increased font size by 1px */
            min-width: 100px; /* Minimum width for buttons */
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            margin: 0; /* Remove margins */
        }

        .save-btn {
            background-color: #4a90e2;
            color: white;
        }

        .save-btn:hover {
            background-color: #357abf;
        }

        .cancel-btn {
            background-color: #f1f1f1;
            color: #333;
        }

        .cancel-btn:hover {
            background-color: #e1e1e1;
        }

        /* Attention cell styles */
        .attention-cell {
            display: flex;
            align-items: center;
        }

        .attention-cell i {
            margin-right: 6px;
            color: #4a90e2;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div id="toast-container" class="toast-container"></div>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Client Accounts</h1>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Contact Info</th>
                        <th>Company Name</th>
                        <th>Address Info</th>
                        <th>Business Proof</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars(truncate($row['username'] ?? 'N/A')) ?></td>
                                <td>
                                    <button class="view-contact-btn" 
                                        onclick='showContactInfo(
                                            <?= json_encode($row["email"]) ?>,
                                            <?= json_encode($row["phone"]) ?>
                                        )'>
                                        <i class="fas fa-address-card"></i> View
                                    </button>
                                </td>
                                <td><?= htmlspecialchars(truncate($row['company'] ?? 'N/A')) ?></td>
                                <td>
                                    <button class="view-address-btn" 
                                        onclick='showAddressInfo(
                                            <?= json_encode($row["company_address"]) ?>,
                                            <?= json_encode($row["region"]) ?>,
                                            <?= json_encode($row["city"]) ?>,
                                            <?= json_encode($row["bill_to"]) ?>,
                                            <?= json_encode($row["bill_to_attn"]) ?>,
                                            <?= json_encode($row["ship_to"]) ?>,
                                            <?= json_encode($row["ship_to_attn"]) ?>
                                        )'>
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td class="photo-album">
                                    <?php
                                    $proofs = json_decode($row['business_proof'], true);
                                    if ($proofs) {
                                        foreach ($proofs as $proof) {
                                            echo '<img src="' . htmlspecialchars($proof) . '" alt="Business Proof" width="50" onclick="openModal(this)">';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="<?= 'status-' . strtolower($row['status'] ?? 'pending') ?>"><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                                <td class="action-buttons">
                                <?php
                                    $business_proof_json = htmlspecialchars($row['business_proof'], ENT_QUOTES);
                                ?>
                                <button class="edit-btn"
                                    onclick='openEditAccountForm(
                                        <?= json_encode($row["id"]) ?>,
                                        <?= json_encode($row["username"]) ?>,
                                        <?= json_encode($row["email"]) ?>,
                                        <?= json_encode($row["phone"]) ?>,
                                        <?= json_encode($row["region"]) ?>,
                                        <?= json_encode($row["city"]) ?>,
                                        <?= json_encode($row["company"]) ?>,
                                        <?= json_encode($row["company_address"]) ?>,
                                        <?= $business_proof_json ?>,
                                        <?= json_encode($row["bill_to"]) ?>,
                                        <?= json_encode($row["bill_to_attn"]) ?>,
                                        <?= json_encode($row["ship_to"]) ?>,
                                        <?= json_encode($row["ship_to_attn"]) ?>
                                    )'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['email']) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change Status
                                </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-accounts">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Improved Contact Info Modal with Unified Design -->
    <div id="contactInfoModal" class="overlay">
        <div class="info-modal-content">
            <div class="info-modal-header">
                <h2><i class="fas fa-address-card"></i> Contact Information</h2>
                <span class="info-modal-close" onclick="closeContactInfoModal()">&times;</span>
            </div>
            
            <div class="info-modal-body">
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-user"></i> Contact Details</h3>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-text">
                            <div class="contact-value" id="modalEmail"></div>
                            <div class="contact-label">Email Address</div>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-text">
                            <div class="contact-value" id="modalPhone"></div>
                            <div class="contact-label">Phone Number</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Improved Address Info Modal with Unified Design - No Scrollbar -->
    <div id="addressInfoModal" class="overlay">
        <div class="info-modal-content">
            <div class="info-modal-header">
                <h2><i class="fas fa-map-marker-alt"></i> Address Information</h2>
                <span class="info-modal-close" onclick="closeAddressInfoModal()">&times;</span>
            </div>
            
            <div class="info-modal-body">
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-building"></i> Company Location</h3>
                    <table class="info-table">
                        <tr>
                            <th>Company Address</th>
                            <td id="modalCompanyAddress"></td>
                        </tr>
                        <tr>
                            <th>Region</th>
                            <td id="modalRegion"></td>
                        </tr>
                        <tr>
                            <th>City</th>
                            <td id="modalCity"></td>
                        </tr>
                    </table>
                </div>
                
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-file-invoice"></i> Billing Information</h3>
                    <table class="info-table">
                        <tr>
                            <th>Bill To Address</th>
                            <td id="modalBillTo"></td>
                        </tr>
                        <tr id="billToAttnRow">
                            <th>Attention To</th>
                            <td class="attention-cell">
                                <i class="fas fa-user"></i>
                                <span id="modalBillToAttn"></span>
                            </td>
                        </tr>
                    </table>
                    <div id="noBillingInfo" class="empty-notice" style="display: none;">
                        No billing address information provided.
                    </div>
                </div>
                
                <div class="info-section">
                    <h3 class="info-section-title"><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                    <table class="info-table">
                        <tr>
                            <th>Ship To Address</th>
                            <td id="modalShipTo"></td>
                        </tr>
                        <tr id="shipToAttnRow">
                            <th>Attention To</th>
                            <td class="attention-cell">
                                <i class="fas fa-user"></i>
                                <span id="modalShipToAttn"></span>
                            </td>
                        </tr>
                    </table>
                    <div id="noShippingInfo" class="empty-notice" style="display: none;">
                        No shipping address information provided.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Account Modal with Fixed Header and Footer -->
    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="form-modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
                <div id="addAccountError" class="error-message"></div>
            </div>
            
            <form id="addAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="add">
                
                <div class="modal-body">
                    <div class="two-column-form">
                        <div class="form-column">
                            <label for="username">Username: <span class="required">*</span></label>
                            <input type="text" id="username" name="username" autocomplete="username" required 
                                placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" 
                                title="Username can only contain letters, numbers, and underscores. Maximum 15 characters.">
                            
                            <label for="password">Password: <span class="required">*</span></label>
                            <input type="password" id="password" name="password" autocomplete="new-password" required placeholder="e.g., ********">
                            
                            <label for="email">Email: <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required placeholder="e.g., johndoe@example.com">
                            
                            <label for="phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
                            <input type="tel" id="phone" name="phone" placeholder="e.g., +1234567890" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)">
                        </div>
                        
                        <div class="form-column">
                            <label for="region">Region: <span class="required">*</span></label>
                            <input type="text" id="region" name="region" required placeholder="e.g., Metro Manila">
                            
                            <label for="city">City: <span class="required">*</span></label>
                            <input type="text" id="city" name="city" required placeholder="e.g., Quezon City">
                            
                            <label for="company">Company Name: <span class="optional">(optional)</span></label>
                            <input type="text" id="company" name="company" placeholder="e.g., Top Exchange Food Corp">
                        </div>
                        
                        <div class="form-full-width">
                            <div class="address-group">
                                <h3><i class="fas fa-building"></i> Company Address</h3>
                                <label for="company_address">Company Address: <span class="required">*</span></label>
                                <textarea id="company_address" name="company_address" required placeholder="e.g., 123 Main St, Metro Manila, Quezon City"></textarea>
                            </div>
                            
                            <div class="address-group">
                                <h3><i class="fas fa-file-invoice"></i> Billing Information</h3>
                                <label for="bill_to">Bill To: <span class="optional">(optional)</span></label>
                                <textarea id="bill_to" name="bill_to" placeholder="Billing address if different from company address"></textarea>
                                
                                <div class="attention-title">
                                    <i class="fas fa-user"></i> Attention To <span class="optional">(optional)</span>
                                </div>
                                <div class="attention-field">
                                    <input type="text" id="bill_to_attn" name="bill_to_attn" placeholder="Contact person for billing inquiries">
                                </div>
                            </div>

                            <div class="address-group">
                                <h3><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                                <label for="ship_to">Ship To: <span class="optional">(optional)</span></label>
                                <textarea id="ship_to" name="ship_to" placeholder="Shipping address if different from company address"></textarea>
                                
                                <div class="attention-title">
                                    <i class="fas fa-user"></i> Attention To <span class="optional">(optional)</span>
                                </div>
                                <div class="attention-field">
                                    <input type="text" id="ship_to_attn" name="ship_to_attn" placeholder="Contact person for deliveries">
                                </div>
                            </div>
                            
                            <label for="business_proof">Business Proof: <span class="required">*</span> <span class="file-info">(Max: 20MB per image, JPG/PNG only)</span></label>
                            <input type="file" id="business_proof" name="business_proof[]" required accept="image/jpeg, image/png" multiple title="Maximum file size: 20MB per image">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Account Modal with Fixed Header and Footer -->
    <div id="editAccountOverlay" class="overlay" style="display: none;">
        <div class="form-modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Account</h2>
                <div id="editAccountError" class="error-message"></div>
            </div>
            
            <form id="editAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id">
                <input type="hidden" id="existing-business-proof" name="existing_business_proof">
                
                <div class="modal-body">
                    <div class="two-column-form">
                        <div class="form-column">
                            <label for="edit-username">Username: <span class="required">*</span></label>
                            <input type="text" id="edit-username" name="username" autocomplete="username" required 
                                placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" 
                                title="Username can only contain letters, numbers, and underscores. Maximum 15 characters.">
                            
                            <label for="edit-password">Password: <span class="required">*</span></label>
                            <input type="password" id="edit-password" name="password" autocomplete="new-password" required placeholder="e.g., ********">
                            
                            <label for="edit-email">Email: <span class="required">*</span></label>
                            <input type="email" id="edit-email" name="email" required placeholder="e.g., johndoe@example.com">
                            
                            <label for="edit-phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
                            <input type="tel" id="edit-phone" name="phone" placeholder="e.g., +1234567890" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)">
                        </div>
                        
                        <div class="form-column">
                            <label for="edit-region">Region: <span class="required">*</span></label>
                            <input type="text" id="edit-region" name="region" required placeholder="e.g., North America">
                            
                            <label for="edit-city">City: <span class="required">*</span></label>
                            <input type="text" id="edit-city" name="city" required placeholder="e.g., New York">
                            
                            <label for="edit-company">Company Name: <span class="optional">(optional)</span></label>
                            <input type="text" id="edit-company" name="company" placeholder="e.g., ABC Corp">
                        </div>
                        
                        <div class="form-full-width">
                            <div class="address-group">
                                <h3><i class="fas fa-building"></i> Company Address</h3>
                                <label for="edit-company_address">Company Address: <span class="required">*</span></label>
                                <textarea id="edit-company_address" name="company_address" required placeholder="e.g., 123 Main St, New York, NY 10001"></textarea>
                            </div>
                            
                            <div class="address-group">
                                <h3><i class="fas fa-file-invoice"></i> Billing Information</h3>
                                <label for="edit-bill_to">Bill To: <span class="optional">(optional)</span></label>
                                <textarea id="edit-bill_to" name="bill_to" placeholder="Billing address if different from company address"></textarea>
                                
                                <div class="attention-title">
                                    <i class="fas fa-user"></i> Attention To <span class="optional">(optional)</span>
                                </div>
                                <div class="attention-field">
                                    <input type="text" id="edit-bill_to_attn" name="bill_to_attn" placeholder="Contact person for billing inquiries">
                                </div>
                            </div>

                            <div class="address-group">
                                <h3><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                                <label for="edit-ship_to">Ship To: <span class="optional">(optional)</span></label>
                                <textarea id="edit-ship_to" name="ship_to" placeholder="Shipping address if different from company address"></textarea>
                                
                                <div class="attention-title">
                                    <i class="fas fa-user"></i> Attention To <span class="optional">(optional)</span>
                                </div>
                                <div class="attention-field">
                                    <input type="text" id="edit-ship_to_attn" name="ship_to_attn" placeholder="Contact person for deliveries">
                                </div>
                            </div>
                            
                            <div id="edit-business-proof-container"></div>
                            <label for="edit-business_proof">Business Proof: <span class="optional">(optional)</span> <span class="file-info">(Max: 20MB per image, JPG/PNG only)</span></label>
                            <input type="file" id="edit-business_proof" name="business_proof[]" accept="image/jpeg, image/png" multiple title="Maximum file size: 20MB per image">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="statusModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeStatus('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <button class="reject-btn" onclick="changeStatus('Rejected')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="pending-btn" onclick="changeStatus('Pending')">
                    <i class="fas fa-hourglass-half"></i> Pending
                </button>
                <button class="inactive-btn" onclick="changeStatus('Inactive')">
                    <i class="fas fa-ban"></i> Archive
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <div id="myModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="img01">
        <div id="caption"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script>
    <script src="/js/accounts_clients.js"></script>
    
    <script>
    function openModal(imgElement) {
        var modal = document.getElementById("myModal");
        var modalImg = document.getElementById("img01");
        var captionText = document.getElementById("caption");
        modal.style.display = "block";
        modalImg.src = imgElement.src;
        captionText.innerHTML = imgElement.alt;
    }

    function closeModal() {
        var modal = document.getElementById("myModal");
        modal.style.display = "none";
    }
    
    function showContactInfo(email, phone) {
        document.getElementById("modalEmail").textContent = email || 'N/A';
        document.getElementById("modalPhone").textContent = phone || 'N/A';
        document.getElementById("contactInfoModal").style.display = "block";
    }
    
    function closeContactInfoModal() {
        document.getElementById("contactInfoModal").style.display = "none";
    }

    function showAddressInfo(companyAddress, region, city, billTo, billToAttn, shipTo, shipToAttn) {
        // Company address info
        document.getElementById("modalCompanyAddress").textContent = companyAddress || 'N/A';
        document.getElementById("modalRegion").textContent = region || 'N/A';
        document.getElementById("modalCity").textContent = city || 'N/A';
        
        // Bill To info with conditional display
        document.getElementById("modalBillTo").textContent = billTo || 'N/A';
        document.getElementById("noBillingInfo").style.display = (!billTo && !billToAttn) ? "block" : "none";
        
        // Bill To Attention with conditional display
        if (billToAttn) {
            document.getElementById("modalBillToAttn").textContent = billToAttn;
            document.getElementById("billToAttnRow").style.display = "table-row";
        } else {
            document.getElementById("billToAttnRow").style.display = "none";
        }
        
        // Ship To info with conditional display
        document.getElementById("modalShipTo").textContent = shipTo || 'N/A';
        document.getElementById("noShippingInfo").style.display = (!shipTo && !shipToAttn) ? "block" : "none";
        
        // Ship To Attention with conditional display
        if (shipToAttn) {
            document.getElementById("modalShipToAttn").textContent = shipToAttn;
            document.getElementById("shipToAttnRow").style.display = "table-row";
        } else {
            document.getElementById("shipToAttnRow").style.display = "none";
        }
        
        document.getElementById("addressInfoModal").style.display = "block";
    }

    function closeAddressInfoModal() {
        document.getElementById("addressInfoModal").style.display = "none";
    }
    
    // Client-side validation for username (prevent special characters)
    document.addEventListener('DOMContentLoaded', function() {
        const usernameInputs = document.querySelectorAll('#username, #edit-username');
        
        usernameInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Replace any characters that aren't alphanumeric or underscore
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
                
                // Enforce max length (redundant with maxlength attribute, but good for extra security)
                if (this.value.length > 15) {
                    this.value = this.value.slice(0, 15);
                }
            });
        });
        
        // Phone number validation (digits only)
        const phoneInputs = document.querySelectorAll('#phone, #edit-phone');
        
        phoneInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Replace any non-digit characters
                this.value = this.value.replace(/\D/g, '');
                
                // Enforce max length (12 digits)
                if (this.value.length > 12) {
                    this.value = this.value.slice(0, 12);
                }
            });
        });

        // When the user clicks anywhere outside of the modals, close them
        window.onclick = function(event) {
            var addressModal = document.getElementById('addressInfoModal');
            var contactModal = document.getElementById('contactInfoModal');
            var imageModal = document.getElementById('myModal');
            
            if (event.target == addressModal) {
                addressModal.style.display = "none";
            }
            
            if (event.target == contactModal) {
                contactModal.style.display = "none";
            }
            
            if (event.target == imageModal) {
                imageModal.style.display = "none";
            }
        };
    });

    // Fixed the openEditAccountForm function to properly handle JSON and include the new attention fields
    function openEditAccountForm(id, username, email, phone, region, city, company, company_address, business_proof, bill_to, bill_to_attn, ship_to, ship_to_attn) {
        document.getElementById("edit-id").value = id;
        document.getElementById("edit-username").value = username;
        document.getElementById("edit-email").value = email;
        document.getElementById("edit-phone").value = phone || '';
        document.getElementById("edit-region").value = region;
        document.getElementById("edit-city").value = city;
        document.getElementById("edit-company").value = company || '';
        document.getElementById("edit-company_address").value = company_address;
        document.getElementById("edit-bill_to").value = bill_to || '';
        document.getElementById("edit-bill_to_attn").value = bill_to_attn || '';
        document.getElementById("edit-ship_to").value = ship_to || '';
        document.getElementById("edit-ship_to_attn").value = ship_to_attn || '';
        
        // Parse business_proof if it's a string
        let proofs = business_proof;
        if (typeof business_proof === 'string') {
            try {
                proofs = JSON.parse(business_proof);
            } catch (e) {
                console.error("Error parsing business proof:", e);
                proofs = [];
            }
        }
        
        document.getElementById("existing-business-proof").value = JSON.stringify(proofs);
        
        var proofContainer = document.getElementById("edit-business-proof-container");
        proofContainer.innerHTML = '';
        
        if (proofs && Array.isArray(proofs) && proofs.length > 0) {
            var proofLabel = document.createElement('label');
            proofLabel.innerHTML = 'Current Business Proof:';
            proofContainer.appendChild(proofLabel);
            
            var proofDiv = document.createElement('div');
            proofDiv.className = 'current-proofs';
            proofDiv.style.marginBottom = '15px';
            
            proofs.forEach(function(proof) {
                var img = document.createElement('img');
                img.src = proof;
                img.alt = 'Business Proof';
                img.style.width = '80px';
                img.style.height = 'auto';
                img.style.margin = '5px';
                img.style.cursor = 'pointer';
                img.onclick = function() { openModal(this); };
                proofDiv.appendChild(img);
            });
            
            proofContainer.appendChild(proofDiv);
        }
        
        document.getElementById("editAccountOverlay").style.display = "block";
    }
    </script>
</body>
</html>