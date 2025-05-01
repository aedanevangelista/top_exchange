<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Clients');

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

function validateUnique($conn, $username, $email, $id = null) {
    $result = [
        'exists' => false,
        'field' => null,
        'message' => ''
    ];

    // First check username
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE username = ?";
    if ($id) {
        $query .= " AND id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($id) {
        $stmt->bind_param("si", $username, $id);
    } else {
        $stmt->bind_param("s", $username);
    }
    $stmt->execute();
    $resultUsername = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($resultUsername['count'] > 0) {
        return [
            'exists' => true,
            'field' => 'username',
            'message' => 'Username already exists'
        ];
    }

    // Then check email
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE email = ?";
    if ($id) {
        $query .= " AND id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($id) {
        $stmt->bind_param("si", $email, $id);
    } else {
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $resultEmail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($resultEmail['count'] > 0) {
        return [
            'exists' => true,
            'field' => 'email',
            'message' => 'Email already exists'
        ];
    }

    return $result;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['formType'] == 'add') {
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? ''; // Initialize phone variable
        
        // Validate unique username/email
        $uniqueCheck = validateUnique($conn, $username, $email);
        if ($uniqueCheck['exists']) {
            echo json_encode([
                'success' => false,
                'message' => $uniqueCheck['message']
            ]);
            exit;
        }
        
        // Auto-generate password: username + last 4 digits of phone
        $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad($phone, 4, '0');
        $autoPassword = $username . $last4digits;
        $password = password_hash($autoPassword, PASSWORD_DEFAULT);
        
        $region = $_POST['region'];
        $city = $_POST['city'];
        $company = $_POST['company'] ?? ''; 
        $company_address = $_POST['company_address'];
        $bill_to_address = $_POST['bill_to_address'] ?? ''; 
        $business_proof = [];

        $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/' . $username . '/';
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
                        $business_proof_path = '/admin/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
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

        $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, bill_to_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("ssssssssss", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add account.']);
        }

        $stmt->close();
        exit;
    }

    if ($_POST['formType'] == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? '';
        
        // Validate unique username/email
        $uniqueCheck = validateUnique($conn, $username, $email, $id);
        if ($uniqueCheck['exists']) {
            echo json_encode([
                'success' => false,
                'message' => $uniqueCheck['message']
            ]);
            exit;
        }
        
        // Generate new password only if phone is provided
        if (!empty($_POST['phone'])) {
            $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad($phone, 4, '0');
            $autoPassword = $username . $last4digits;
            $password = password_hash($autoPassword, PASSWORD_DEFAULT);
        }
        
        $region = $_POST['region'];
        $city = $_POST['city'];
        $company = $_POST['company'] ?? '';
        $company_address = $_POST['company_address'];
        $bill_to_address = $_POST['bill_to_address'] ?? '';
        $business_proof = [];

        $old_username = '';
        $stmt = $conn->prepare("SELECT username FROM clients_accounts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $old_username = $row['username'];
        }
        $stmt->close();

        $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/' . $username . '/';
        if (!file_exists($user_upload_dir)) {
            mkdir($user_upload_dir, 0777, true);
        }

        if (isset($_FILES['business_proof']) && !empty($_FILES['business_proof']['name'][0])) {
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
                        $business_proof_path = '/admin/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
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
            
            $business_proof_json = json_encode($business_proof);
        } else {
            $business_proof_json = $_POST['existing_business_proof'];
        }

        // Update query with all fields
        $sql = "UPDATE clients_accounts SET username = ?, email = ?, phone = ?, region = ?, city = ?, company = ?, company_address = ?, bill_to_address = ?, business_proof = ?";
        if (isset($password)) {
            $sql .= ", password = ?";
        }
        $sql .= " WHERE id = ?";

        $stmt = $conn->prepare($sql);
        
        if (isset($password)) {
            $stmt->bind_param("ssssssssssi", $username, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json, $password, $id);
        } else {
            $stmt->bind_param("sssssssssi", $username, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update account.']);
        }

        $stmt->close();
        exit;
    }

    if ($_POST['formType'] == 'status') {
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
}

// Get accounts for display
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT id, username, email, phone, region, city, company, company_address, bill_to_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'";
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
}
$sql .= " ORDER BY 
    CASE 
        WHEN status = 'Pending' THEN 1
        WHEN status = 'Active' THEN 2
        WHEN status = 'Rejected' THEN 3
        WHEN status = 'Inactive' THEN 4
    END, created_at DESC";
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
            gap: 15px;
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
        
        .two-column-form input, 
        .two-column-form textarea {
            width: 100%;
        }
        
        textarea#company_address, textarea#edit-company_address,
        textarea#bill_to_address, textarea#edit-bill_to_address {
            height: 60px;
            padding: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }
        
        input, textarea {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 10px;
            transition: border-color 0.3s;
            outline: none;
            font-size: 14px;
        }
        
        input:focus, textarea:focus {
            border-color: #4a90fe;
            box-shadow: 0 0 5px rgba(77, 144, 254, 0.5);
        }
        
        input::placeholder, textarea::placeholder {
            color: #aaa;
            padding: 4px;
            font-style: italic;
        }

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

        #addressInfoModal, #contactInfoModal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
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
            max-height: 80vh;
            animation: modalFadeIn 0.3s;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
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
            overflow-y: auto;
            max-height: calc(80vh - 65px);
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
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-section-title i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

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
            font-size: 14px;
        }

        .info-table td {
            padding: 12px 15px;
            border: 1px solid #d1e1f9;
            word-break: break-word;
            vertical-align: top;
            line-height: 1.5;
            color: #333;
            background-color: #fff;
            font-size: 14px;
        }

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
            font-size: 14px;
            word-break: break-all;
        }

        .contact-label {
            font-size: 13px;
            color: #777;
            display: block;
            margin-top: 5px;
        }

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

        .address-group {
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: #fafafa;
        }

        .address-group h3 {
            margin-top: 0;
            color: #4a90e2;
            font-size: 15px;
            margin-bottom: 12px;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
        }

        .modal-header {
            background-color: #ffffff;
            padding-top: 24px;
            padding: 12px;
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
            font-size: 18px;
        }

        .modal-footer {
            background-color: #ffffff;
            padding: 12px;
            border-top: 1px solid rgb(68, 68, 68);
            text-align: center;
            border-radius: 0 0 8px 8px;
            position: sticky;
            bottom: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: auto;
        }

        .modal-body {
            padding: 15px;
            overflow-y: auto;
            max-height: calc(85vh - 110px);
            height: auto;
        }

        .form-modal-content {
            display: flex;
            flex-direction: column;
            max-height: 85vh;
            height: auto;
            width: 80%;
            max-width: 650px;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            margin: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        label {
            font-size: 14px;
            margin-bottom: 4px;
        }

        .error-message {
            color: #ff3333;
            background-color: rgba(255, 51, 51, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: none;
        }
        
        .modal-footer button {
            padding: 8px 16px;
            font-size: 14px;
            min-width: 100px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            margin: 0;
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

        .status-active {
            color: #28a745;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-pending {
            color: #ffc107;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-rejected {
            color: #dc3545;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-inactive {
            color: #6c757d;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .password-note {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }
        
        .auto-generated {
            background-color: #f8f8f8;
            color: #888;
            cursor: not-allowed;
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
                                            <?= json_encode($row["bill_to_address"]) ?>
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
                                    // Encode the business_proof JSON string properly
                                    $business_proof_json = htmlspecialchars($row['business_proof'], ENT_QUOTES);
                                    ?>
                                    <button class="edit-btn"
                                        onclick='openEditAccountForm(
                                            <?= $row["id"] ?>,
                                            <?= json_encode($row["username"]) ?>,
                                            <?= json_encode($row["email"]) ?>,
                                            <?= json_encode($row["phone"]) ?>,
                                            <?= json_encode($row["region"]) ?>,
                                            <?= json_encode($row["city"]) ?>,
                                            <?= json_encode($row["company"]) ?>,
                                            <?= json_encode($row["company_address"]) ?>,
                                            <?= json_encode($row["bill_to_address"]) ?>,
                                            <?= $business_proof_json ?>
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

    <!-- Updated Address Info Modal with Bill To Address -->
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
                            <th>Ship to Address</th>
                            <td id="modalCompanyAddress"></td>
                        </tr>
                        <tr>
                            <th>Bill To Address</th>
                            <td id="modalBillToAddress"></td>
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
            </div>
        </div>
    </div>

    <!-- Updated Add Account Modal with Bill To Address Field -->
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
                            
                            <label for="phone">Phone/Telephone Number: <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" required placeholder="e.g., 1234567890" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            
                            <label for="password">Password: <span class="required">*</span></label>
                            <input type="text" id="password" name="password" maxlength="20" readonly class="auto-generated" placeholder="Auto-generated password">
                            <div class="password-note">Password will be auto-generated as username + last 4 digits of phone</div>
                            
                            <label for="email">Email: <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                        </div>
                        
                        <div class="form-column">
                            <label for="region">Region: <span class="required">*</span></label>
                            <select id="region" name="region" required>
                                <option value="">Select Region</option>
                            </select>

                            <label for="city">City: <span class="required">*</span></label>
                            <select id="city" name="city" required disabled>
                                <option value="">Select City</option>
                            </select>
                            
                            <label for="company">Company Name: <span class="required">*</span></label>
                            <input type="text" id="company" name="company" required maxlength="25" placeholder="e.g., Top Exchange Food Corp">
                        </div>
                        
                        <div class="form-full-width">
                            <div class="address-group">
                                <h3><i class="fas fa-building"></i> Company Address</h3>
                                <label for="company_address">Ship to Address: <span class="required">*</span></label>
                                <textarea id="company_address" name="company_address" required maxlength="100" placeholder="e.g., 123 Main St, Metro Manila, Quezon City"></textarea>
                                
                                <label for="bill_to_address">Bill To Address: <span class="required">*</span></label>
                                <textarea id="bill_to_address" name="bill_to_address" required maxlength="100" placeholder="e.g., 456 Billing St, Metro Manila, Quezon City"></textarea>
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

    <!-- Updated Edit Account Modal with Bill To Address Field -->
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
                            
                            <label for="edit-phone">Phone/Telephone Number: <span class="required">*</span></label>
                            <input type="tel" id="edit-phone" name="phone" required placeholder="e.g., 1234567890" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            
                            <label for="edit-password">Password: (Auto-generated)</label>
                            <input type="text" id="edit-password" name="password" readonly class="auto-generated">
                            <div class="password-note">Password will be auto-generated as username + last 4 digits of phone</div>
                            
                            <label for="edit-email">Email: <span class="required">*</span></label>
                            <input type="email" id="edit-email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                        </div>
                        
                        <div class="form-column">
                            <label for="edit-region">Region: <span class="required">*</span></label>
                            <select id="edit-region" name="region" required>
                                <option value="">Select Region</option>
                            </select>

                            <label for="edit-city">City: <span class="required">*</span></label>
                            <select id="edit-city" name="city" required disabled>
                                <option value="">Select City</option>
                            </select>
                            
                            <label for="edit-company">Company Name: <span class="required">*</span></label>
                            <input type="text" id="edit-company" name="company" required maxlength="25" placeholder="e.g., Top Exchange Food Corp">
                        </div>
                        
                        <div class="form-full-width">
                            <div class="address-group">
                                <h3><i class="fas fa-building"></i> Company Address</h3>
                                <label for="edit-company_address">Ship to Address: <span class="required">*</span></label>
                                <textarea id="edit-company_address" name="company_address" required maxlength="100" placeholder="e.g., 123 Main St, Metro Manila, Quezon City"></textarea>
                                
                                <label for="edit-bill_to_address">Bill To Address: <span class="required">*</span></label>
                                <textarea id="edit-bill_to_address" name="bill_to_address" required maxlength="100" placeholder="e.g., 456 Billing St, Metro Manila, Quezon City"></textarea>
                            </div>
                            
                            <div id="edit-business-proof-container"></div>
                            <label for="edit-business_proof">Business Proof: <span class="required">*</span> <span class="file-info">(Max: 20MB per image, JPG/PNG only)</span></label>
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
    <script>
    // Variable to store the current account ID for status changes
    let currentAccountId = 0;

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

    function showAddressInfo(companyAddress, region, city, billToAddress) {
        document.getElementById("modalCompanyAddress").textContent = companyAddress || 'N/A';
        document.getElementById("modalBillToAddress").textContent = billToAddress || 'N/A';
        document.getElementById("modalRegion").textContent = region || 'N/A';
        document.getElementById("modalCity").textContent = city || 'N/A';
        document.getElementById("addressInfoModal").style.display = "block";
    }

    function closeAddressInfoModal() {
        document.getElementById("addressInfoModal").style.display = "none";
    }

    async function loadPhilippinesRegions() {
        try {
            const response = await fetch('https://psgc.gitlab.io/api/regions/');
            const regions = await response.json();
            const regionSelect = document.getElementById('region');
            const editRegionSelect = document.getElementById('edit-region');
            
            [regionSelect, editRegionSelect].forEach(select => {
                if (select) {
                    select.innerHTML = '<option value="">Select Region</option>';
                    regions
                        .sort((a, b) => a.name.localeCompare(b.name))
                        .forEach(region => {
                            select.innerHTML += `<option value="${region.code}">${region.name}</option>`;
                        });
                }
            });
        } catch (error) {
            console.error('Error loading regions:', error);
        }
    }

    async function loadCities(regionCode, targetId) {
        if (!regionCode) return;
        
        try {
            const response = await fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/cities-municipalities`);
            const cities = await response.json();
            const citySelect = document.getElementById(targetId);
            
            if (citySelect) {
                citySelect.innerHTML = '<option value="">Select City</option>';
                cities
                    .sort((a, b) => a.name.localeCompare(b.name))
                    .forEach(city => {
                        citySelect.innerHTML += `<option value="${city.name}">${city.name}</option>`;
                    });
                citySelect.disabled = false;
            }
        } catch (error) {
            console.error('Error loading cities:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadPhilippinesRegions();

        // Region change handlers
        const regionSelect = document.getElementById('region');
        const editRegionSelect = document.getElementById('edit-region');
        const citySelect = document.getElementById('city');
        const editCitySelect = document.getElementById('edit-city');

        // Disable city selects initially
        citySelect.disabled = true;
        editCitySelect.disabled = true;

        if (regionSelect) {
            regionSelect.addEventListener('change', function() {
                citySelect.disabled = !this.value;
                loadCities(this.value, 'city');
            });
        }

        if (editRegionSelect) {
            editRegionSelect.addEventListener('change', function() {
                editCitySelect.disabled = !this.value;
                loadCities(this.value, 'edit-city');
            });
        }

        // When the user clicks anywhere outside of the modals, close them
        window.onclick = function(event) {
            const addressModal = document.getElementById('addressInfoModal');
            const contactModal = document.getElementById('contactInfoModal');
            const imageModal = document.getElementById('myModal');
            
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

    // Show error message in a visible way
    function showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    // Reset error messages
    function resetErrors() {
        document.getElementById('addAccountError').style.display = 'none';
        document.getElementById('editAccountError').style.display = 'none';
    }

    // Handle form submissions with AJAX
    $(document).ready(function() {
        // Add Account Form Submission
        $('#addAccountForm').submit(function(e) {
            e.preventDefault();
            resetErrors();
            
            var formData = new FormData(this);
            formData.append('ajax', true);
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    try {
                        if (typeof response === 'string') {
                            response = JSON.parse(response);
                        }
                        
                        if(response.success) {
                            showToast('Account added successfully!', 'success');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showError('addAccountError', response.message || 'An error occurred.');
                            showToast(response.message || 'An error occurred.', 'error');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showError('addAccountError', 'Invalid server response.');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX error:', xhr.responseText);
                    showError('addAccountError', 'A server error occurred.');
                    showToast('A server error occurred.', 'error');
                }
            });
        });
        
        // Edit Account Form Submission
        $('#editAccountForm').submit(function(e) {
            e.preventDefault();
            resetErrors();
            
            var formData = new FormData(this);
            formData.append('ajax', true);
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    try {
                        if (typeof response === 'string') {
                            response = JSON.parse(response);
                        }
                        
                        if(response.success) {
                            showToast('Account updated successfully!', 'success');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showError('editAccountError', response.message || 'An error occurred.');
                            showToast(response.message || 'An error occurred.', 'error');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showError('editAccountError', 'Invalid server response.');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX error:', xhr.responseText);
                    showError('editAccountError', 'A server error occurred.');
                    showToast('A server error occurred.', 'error');
                }
            });
        });
    });

    // Function to open the add account form
    function openAddAccountForm() {
        document.getElementById("addAccountOverlay").style.display = "block";
        resetErrors();
    }

    // Function to close the add account form
    function closeAddAccountForm() {
        document.getElementById("addAccountOverlay").style.display = "none";
        document.getElementById("addAccountForm").reset();
    }

    // Function to open the edit form
    function openEditAccountForm(id, username, email, phone, region, city, company, company_address, bill_to_address, business_proof) {
        resetErrors();
        
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-username').value = username;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-phone').value = phone;
        document.getElementById('edit-company').value = company;
        document.getElementById('edit-company_address').value = company_address;
        document.getElementById('edit-bill_to_address').value = bill_to_address;
        
        // Get the select fields
        const regionSelect = document.getElementById('edit-region');
        const citySelect = document.getElementById('edit-city');
        
        // Set region
        if (regionSelect && region) {
            regionSelect.value = region;
            // Load cities for this region
            loadCities(region, 'edit-city').then(() => {
                // Set city after cities are loaded
                if (citySelect && city) {
                    citySelect.value = city;
                    citySelect.disabled = false;
                }
            });
        }

        // Handle business proof (images)
        const businessProofContainer = document.getElementById('edit-business-proof-container');
        businessProofContainer.innerHTML = '<h4>Current Business Proof:</h4>';
        
        try {
            // Try to parse business_proof as JSON if it's a string
            const proofs = typeof business_proof === 'string' ? 
                JSON.parse(business_proof) : business_proof;
            
            if (Array.isArray(proofs) && proofs.length > 0) {
                proofs.forEach(proof => {
                    const img = document.createElement('img');
                    img.src = proof;
                    img.alt = 'Business Proof';
                    img.width = 50;
                    img.style.margin = '5px';
                    businessProofContainer.appendChild(img);
                });
            } else {
                businessProofContainer.innerHTML += '<p>No business proof images</p>';
            }
            
            // Set existing business proof in hidden input
            document.getElementById('existing-business-proof').value = JSON.stringify(proofs || []);
            
        } catch (e) {
            console.error('Error parsing business proof:', e);
            businessProofContainer.innerHTML += '<p>Error loading business proof images</p>';
            document.getElementById('existing-business-proof').value = '[]';
        }

        document.getElementById('editAccountOverlay').style.display = 'block';
    }

    // Function to close the edit form
    function closeEditAccountForm() {
        document.getElementById("editAccountOverlay").style.display = "none";
        document.getElementById("editAccountForm").reset();
    }

    // Function to open the status change modal
    function openStatusModal(id, username, email) {
        currentAccountId = id;
        document.getElementById("statusMessage").textContent = "Change status for " + username + " (" + email + ")";
        document.getElementById("statusModal").style.display = "block";
    }

    // Function to close the status modal
    function closeStatusModal() {
        document.getElementById("statusModal").style.display = "none";
    }

    // Function to change account status
    function changeStatus(status) {
        if (!currentAccountId) return;
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                ajax: true,
                formType: 'status',
                id: currentAccountId,
                status: status
            },
            success: function(response) {
                try {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if(response.success) {
                        showToast('Status changed successfully!', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast(response.message || 'An error occurred.', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Invalid server response.', 'error');
                }
                closeStatusModal();
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.responseText);
                showToast('A server error occurred.', 'error');
                closeStatusModal();
            }
        });
    }

    // Function to filter accounts by status
    function filterByStatus() {
        var status = document.getElementById("statusFilter").value;
        window.location.href = '?status=' + status;
    }

    // Function to show toast notifications
    function showToast(message, type = 'success') {
        // Check if toastr is available
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-right",
                timeOut: 3000
            };
            
            switch(type) {
                case 'success':
                    toastr.success(message);
                    break;
                case 'error':
                case 'reject':
                    toastr.error(message);
                    break;
                case 'pending':
                    toastr.warning(message);
                    break;
                case 'active':
                    toastr.success(message);
                    break;
                default:
                    toastr.info(message);
            }
        } else {
            // Fallback to alert if toastr is not available
            console.log(`${type}: ${message}`);
            alert(message);
        }
    }
    </script>
</body>
</html>