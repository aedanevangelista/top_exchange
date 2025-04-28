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
            overflow: hidden; /* Changed from auto to hidden to remove outer scrollbar */
            background-color: rgba(0,0,0,0.7);
        }

        .info-modal-content {
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 700px;
            max-height: 80vh; /* Limit the height */
            animation: modalFadeIn 0.3s;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); /* Center the modal */
            display: flex;
            flex-direction: column;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translate(-50%, -55%);}
            to {opacity: 1; transform: translate(-50%, -50%);}
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
            overflow-y: auto; /* Add scrollbar only to the body */
            max-height: calc(80vh - 65px); /* Account for header height */
            flex: 1;
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
            padding-top: 24px;
            padding: 12px; /* Reduced padding */
            text-align: center;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid rgb(68, 68, 68);
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
            border-top: 1px solid rgb(68, 68, 68);
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

</body>
</html>