<?php
session_start();
include "../../backend/db_connection.php"; // Establishes $conn
include "../../backend/check_role.php";
checkRole('Accounts - Clients');

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

// --- Sorting ---
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at'; // Default sort: newest first
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'DESC';

// Validate sort column
$allowed_columns = ['username', 'email', 'phone', 'region', 'city', 'company', 'status', 'created_at'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'created_at'; // Default back if invalid
}
// Validate sort direction
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'DESC'; // Default back if invalid
}

// --- Status Filter ---
$status_filter = $_GET['status'] ?? '';

// --- AJAX Handlers (Existing code - no changes needed within this block) ---
function validateUnique($conn, $username, $email, $id = null) {
    $result = ['exists' => false, 'field' => null, 'message' => ''];
    // Check username
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE username = ?";
    if ($id) $query .= " AND id != ?";
    $stmt = $conn->prepare($query);
    if ($id) $stmt->bind_param("si", $username, $id); else $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUsername = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($resultUsername['count'] > 0) return ['exists' => true, 'field' => 'username', 'message' => 'Username already exists'];

    // Check email
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE email = ?";
    if ($id) $query .= " AND id != ?";
    $stmt = $conn->prepare($query);
    if ($id) $stmt->bind_param("si", $email, $id); else $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultEmail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($resultEmail['count'] > 0) return ['exists' => true, 'field' => 'email', 'message' => 'Email already exists'];

    return $result;
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $formType = $_POST['formType'] ?? '';

    // Add Client Account
    if ($formType == 'add') {
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? '';

        $uniqueCheck = validateUnique($conn, $username, $email);
        if ($uniqueCheck['exists']) {
            echo json_encode(['success' => false, 'message' => $uniqueCheck['message']]); exit;
        }

        $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad($phone, 4, '0');
        $autoPassword = $username . $last4digits;
        $password = password_hash($autoPassword, PASSWORD_DEFAULT);

        $region = $_POST['region'];
        $city = $_POST['city'];
        $company = $_POST['company'] ?? '';
        $company_address = $_POST['company_address'];
        $bill_to_address = $_POST['bill_to_address'] ?? '';
        $business_proof = [];
        $status = 'Pending'; // New accounts start as Pending

        $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/' . $username . '/';
        if (!file_exists($user_upload_dir)) mkdir($user_upload_dir, 0777, true);

        if (isset($_FILES['business_proof'])) {
            if (count($_FILES['business_proof']['name']) > 3) { echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']); exit; }
            foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['business_proof']['error'][$key] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png']; $max_size = 20 * 1024 * 1024;
                    $file_type = $_FILES['business_proof']['type'][$key]; $file_size = $_FILES['business_proof']['size'][$key];
                    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                        $filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['business_proof']['name'][$key])); // Sanitize filename
                        $business_proof_path = '/admin/uploads/' . $username . '/' . $filename;
                        if (move_uploaded_file($tmp_name, $user_upload_dir . $filename)) {
                            $business_proof[] = $business_proof_path;
                        } else { echo json_encode(['success' => false, 'message' => 'Failed to upload file: ' . $filename]); exit; }
                    } else { echo json_encode(['success' => false, 'message' => 'Invalid file type or size for: ' . basename($_FILES['business_proof']['name'][$key]) . '. Max 20MB JPG/PNG.']); exit; }
                } else if ($_FILES['business_proof']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                     echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . basename($_FILES['business_proof']['name'][$key])]); exit;
                }
            }
        }
         if (empty($business_proof)) { echo json_encode(['success' => false, 'message' => 'Business proof is required.']); exit; } // Ensure proof is uploaded

        $business_proof_json = json_encode($business_proof);

        $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, bill_to_address, business_proof, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssssss", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json, $status);

        if ($stmt->execute()) { echo json_encode(['success' => true, 'reload' => true]); }
        else { error_log("Add client failed: " . $stmt->error); echo json_encode(['success' => false, 'message' => 'Failed to add account.']); }
        $stmt->close();
        exit;
    }

    // Edit Client Account
    if ($formType == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? '';

        $uniqueCheck = validateUnique($conn, $username, $email, $id);
        if ($uniqueCheck['exists']) { echo json_encode(['success' => false, 'message' => $uniqueCheck['message']]); exit; }

        $password_sql_part = "";
        $bind_types = "ssssssssi"; // Types for non-password fields + id
        $bind_params = [$username, $email, $phone, $_POST['region'], $_POST['city'], $_POST['company'] ?? '', $_POST['company_address'], $_POST['bill_to_address'] ?? ''];

        if (!empty($_POST['manual_password'])) {
            $password = password_hash($_POST['manual_password'], PASSWORD_DEFAULT);
            $password_sql_part = ", password = ?";
            $bind_types .= "s"; // Add type for password
            $bind_params[] = $password; // Add password to params
        } else if (isset($_POST['regenerate_password']) && $_POST['regenerate_password'] == '1' && !empty($phone)) {
            // Regenerate auto-password only if checkbox is checked and phone exists
            $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad($phone, 4, '0');
            $autoPassword = $username . $last4digits;
            $password = password_hash($autoPassword, PASSWORD_DEFAULT);
            $password_sql_part = ", password = ?";
            $bind_types .= "s";
            $bind_params[] = $password;
        }
        // Else: Keep existing password (no password update needed)

        $business_proof = json_decode($_POST['existing_business_proof'] ?? '[]', true); // Start with existing

        $user_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/' . $username . '/';
        if (!file_exists($user_upload_dir)) mkdir($user_upload_dir, 0777, true);

        if (isset($_FILES['business_proof']) && !empty($_FILES['business_proof']['name'][0])) {
             if ((count($business_proof) + count($_FILES['business_proof']['name'])) > 3) { echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed in total.']); exit; }
            foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['business_proof']['error'][$key] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png']; $max_size = 20 * 1024 * 1024;
                    $file_type = $_FILES['business_proof']['type'][$key]; $file_size = $_FILES['business_proof']['size'][$key];
                    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                         $filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['business_proof']['name'][$key]));
                         $business_proof_path = '/admin/uploads/' . $username . '/' . $filename;
                         if (move_uploaded_file($tmp_name, $user_upload_dir . $filename)) {
                             $business_proof[] = $business_proof_path;
                         } else { echo json_encode(['success' => false, 'message' => 'Failed to upload new file: ' . $filename]); exit; }
                     } else { echo json_encode(['success' => false, 'message' => 'Invalid type/size for new file: ' . basename($_FILES['business_proof']['name'][$key])]); exit; }
                 } else if ($_FILES['business_proof']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                      echo json_encode(['success' => false, 'message' => 'Error uploading new file: ' . basename($_FILES['business_proof']['name'][$key])]); exit;
                 }
            }
        }
         if (empty($business_proof)) { echo json_encode(['success' => false, 'message' => 'Business proof cannot be empty.']); exit; } // Must have at least one proof

        $business_proof_json = json_encode(array_values($business_proof)); // Re-index array
        $bind_params[] = $business_proof_json; // Add proof JSON to params
        $bind_types .= "s"; // Add type for proof JSON

        $bind_params[] = $id; // Add ID to the end

        $sql = "UPDATE clients_accounts SET username = ?, email = ?, phone = ?, region = ?, city = ?, company = ?, company_address = ?, bill_to_address = ? {$password_sql_part}, business_proof = ? WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { error_log("Prepare failed (edit client): " . $conn->error); echo json_encode(['success' => false, 'message' => 'Database error preparing update.']); exit; }

        $stmt->bind_param($bind_types, ...$bind_params);

        if ($stmt->execute()) { echo json_encode(['success' => true, 'reload' => true]); }
        else { error_log("Edit client failed: " . $stmt->error); echo json_encode(['success' => false, 'message' => 'Failed to update account.']); }
        $stmt->close();
        exit;
    }

    // Change Client Status
    if ($formType == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $allowed_statuses = ['Active', 'Pending', 'Rejected', 'Inactive']; // Include 'Inactive'
         if (!in_array($status, $allowed_statuses)) {
              echo json_encode(['success' => false, 'message' => 'Invalid status value.']); exit;
         }

        $stmt = $conn->prepare("UPDATE clients_accounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) { echo json_encode(['success' => true, 'reload' => true]); }
        else { error_log("Change client status failed: " . $stmt->error); echo json_encode(['success' => false, 'message' => 'Failed to change status.']); }
        $stmt->close();
        exit;
    }
} // End AJAX handling block

// --- Fetch Client Accounts Data ---
$sql = "SELECT id, username, email, phone, region, city, company, company_address, bill_to_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'"; // Exclude archived
$params = [];
$param_types = "";

// Apply status filter if present
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Apply sorting: Prioritize by status first, then by the selected column
$sql .= " ORDER BY
    CASE status
        WHEN 'Pending' THEN 1
        WHEN 'Active' THEN 2
        WHEN 'Rejected' THEN 3
        WHEN 'Inactive' THEN 4
        ELSE 5
    END, {$sort_column} {$sort_direction}";

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
     if ($conn instanceof mysqli) $conn->close();
     die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$accounts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close(); // Close statement after fetching
} else {
    error_log("Fetch client accounts failed: " . $conn->error);
    $stmt->close(); // Close statement on error too
}

// --- Helper functions ---
function truncate($text, $max = 15) {
    return (strlen($text) > $max) ? htmlspecialchars(substr($text, 0, $max)) . '...' : htmlspecialchars($text);
}

// --- Sorting Helper Functions (Reused from accounts.php) ---
function getSortUrl($column, $currentColumn, $currentDirection, $currentStatus) {
    $newDirection = ($column === $currentColumn && strtoupper($currentDirection) === 'ASC') ? 'DESC' : 'ASC';
    $urlParams = [
        'sort' => $column,
        'direction' => $newDirection
    ];
    if (!empty($currentStatus)) {
        $urlParams['status'] = $currentStatus;
    }
    return "?" . http_build_query($urlParams);
}

function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column !== $currentColumn) {
        return '<i class="fas fa-sort"></i>';
    } elseif (strtoupper($currentDirection) === 'ASC') {
        return '<i class="fas fa-sort-up"></i>';
    } else {
        return '<i class="fas fa-sort-down"></i>';
    }
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
    <link rel="stylesheet" href="/css/accounts_clients.css"> <!-- Keep specific styles -->
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        /* --- Styles from accounts.php for search/sort --- */
        .search-container { display: flex; align-items: center; margin: 0 15px; }
        .search-container input { padding: 8px 12px; border-radius: 20px 0 0 20px; border: 1px solid #ddd; font-size: 12px; width: 200px; border-right: none; }
        .search-container .search-btn { background-color: #2980b9; color: white; border: 1px solid #2980b9; border-radius: 0 20px 20px 0; padding: 8px 12px; cursor: pointer; margin-left: -1px; }
        .search-container .search-btn:hover { background-color: #2471a3; }
        .accounts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .accounts-header h1 { margin-right: auto; }
        .accounts-table th.sortable a { color: inherit; text-decoration: none; display: inline-block; }
        .accounts-table th.sortable a:hover { color: #0056b3; }
        .accounts-table th.sortable i { margin-left: 5px; color: #aaa; }
        .accounts-table th.sortable.active i { color: #333; }
        /* --- End reused styles --- */

        /* Keep existing styles from accounts_clients.css or below */
        #myModal { display: none; position: fixed; z-index: 9999; padding-top: 100px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; max-width: 80%; max-height: 80%; }
        #caption { margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 10px 0; height: 150px; }
        .close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
        .close:hover, .close:focus { color: #bbb; text-decoration: none; }
        .photo-album img { cursor: pointer; transition: all 0.3s; margin: 2px; border: 1px solid #eee; }
        .photo-album img:hover { opacity: 0.8; transform: scale(1.05); }
        .file-info { font-size: 0.9em; color: #666; font-style: italic; }
        .two-column-form { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-column { display: flex; flex-direction: column; }
        .form-full-width { grid-column: 1 / span 2; }
        .required { color: #ff0000; font-weight: bold; }
        .optional { color: #666; font-style: italic; font-size: 0.9em; }
        .overlay-content { max-width: 800px; width: 90%; max-height: 95vh; display: flex; flex-direction: column; }
        .two-column-form input, .two-column-form textarea, .two-column-form select { width: 100%; box-sizing: border-box; } /* Ensure select is also full width */
        textarea#company_address, textarea#edit-company_address, textarea#bill_to_address, textarea#edit-bill_to_address { height: 60px; padding: 8px; font-size: 14px; resize: vertical; min-height: 60px; }
        input, textarea, select { border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; transition: border-color 0.3s; outline: none; font-size: 14px; }
        input:focus, textarea:focus, select:focus { border-color: #4a90fe; box-shadow: 0 0 5px rgba(77, 144, 254, 0.5); }
        input::placeholder, textarea::placeholder { color: #aaa; font-style: italic; }
        .view-address-btn, .view-contact-btn { background-color: #4a90e2; color: white; border: none; border-radius: 4px; padding: 5px 10px; cursor: pointer; font-size: 12px; transition: all 0.3s; }
        .view-address-btn:hover, .view-contact-btn:hover { background-color: #357abf; }
        #addressInfoModal, #contactInfoModal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0,0,0,0.7); }
        .info-modal-content { background-color: #ffffff; margin: 0; padding: 0; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); width: 90%; max-width: 700px; max-height: 80vh; animation: modalFadeIn 0.3s; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; }
        @keyframes modalFadeIn { from {opacity: 0; transform: translate(-50%, -55%);} to {opacity: 1; transform: translate(-50%, -50%);} }
        .info-modal-header { background-color: #4a90e2; color: #fff; padding: 15px 25px; position: relative; display: flex; align-items: center; border-radius: 10px 10px 0 0; }
        .info-modal-header h2 { margin: 0; font-size: 20px; flex: 1; font-weight: 500; }
        .info-modal-header h2 i { margin-right: 10px; }
        .info-modal-close { color: #fff; font-size: 24px; font-weight: bold; cursor: pointer; transition: all 0.2s; padding: 5px; line-height: 1; }
        .info-modal-close:hover { transform: scale(1.1); }
        .info-modal-body { padding: 25px; overflow-y: auto; max-height: calc(80vh - 65px); flex: 1; }
        .info-section { margin-bottom: 25px; background-color: #f9f9f9; border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .info-section:last-child { margin-bottom: 0; }
        .info-section-title { display: flex; align-items: center; color: #4a90e2; margin-top: 0; margin-bottom: 15px; font-size: 16px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
        .info-section-title i { margin-right: 10px; width: 20px; text-align: center; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .info-table th { text-align: left; background-color: #eef5ff; padding: 12px 15px; border: 1px solid #d1e1f9; width: 30%; vertical-align: top; color: #3a5d85; font-weight: 600; font-size: 14px; }
        .info-table td { padding: 12px 15px; border: 1px solid #d1e1f9; word-break: break-word; vertical-align: top; line-height: 1.5; color: #333; background-color: #fff; font-size: 14px; }
        .contact-item { display: flex; align-items: center; padding: 15px; background-color: #fff; border-radius: 6px; margin-bottom: 15px; border: 1px solid #d1e1f9; }
        .contact-item:last-child { margin-bottom: 0; }
        .contact-icon { width: 45px; height: 45px; background-color: #eef5ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4a90e2; font-size: 18px; margin-right: 15px; flex-shrink: 0; }
        .contact-text { flex: 1; min-width: 0; }
        .contact-value { font-weight: bold; color: #333; font-size: 14px; word-break: break-all; }
        .contact-label { font-size: 13px; color: #777; display: block; margin-top: 5px; }
        .overlay { position: fixed; width: 100%; height: 100%; top: 0; left: 0; background-color: rgba(0, 0, 0, 0.7); z-index: 1000; display: flex; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .address-group { border: 1px solid #eee; padding: 12px; border-radius: 8px; margin-bottom: 15px; background-color: #fafafa; }
        .address-group h3 { margin-top: 0; color: #4a90e2; font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
        .modal-header { background-color: #ffffff; padding: 12px; text-align: center; border-radius: 8px 8px 0 0; border-bottom: 1px solid #ccc; position: sticky; top: 0; z-index: 10; }
        .modal-header h2 { margin: 0; padding: 0; font-size: 18px; }
        .modal-footer { background-color: #ffffff; padding: 12px; border-top: 1px solid #ccc; text-align: center; border-radius: 0 0 8px 8px; position: sticky; bottom: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; margin-top: auto; }
        .modal-body { padding: 15px; overflow-y: auto; max-height: calc(85vh - 110px); height: auto; }
        .form-modal-content { display: flex; flex-direction: column; max-height: 85vh; height: auto; width: 80%; max-width: 650px; background-color: #fff; border-radius: 8px; overflow: hidden; margin: auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        label { font-size: 14px; margin-bottom: 4px; display: block; } /* Ensure labels are block */
        .error-message { color: #ff3333; background-color: rgba(255, 51, 51, 0.1); padding: 10px; border-radius: 4px; margin-bottom: 10px; display: none; }
        .modal-footer button { padding: 8px 16px; font-size: 14px; min-width: 100px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; border: none; margin: 0; }
        .save-btn { background-color: #4a90e2; color: white; }
        .save-btn:hover { background-color: #357abf; }
        .cancel-btn { background-color: #f1f1f1; color: #333; }
        .cancel-btn:hover { background-color: #e1e1e1; }
        .status-active { color: #28a745; background-color: #e9f7ef; padding: 4px 8px; border-radius: 4px; border: 1px solid #a6d7b5; display: inline-block; }
        .status-pending { color: #ffc107; background-color: #fff8e1; padding: 4px 8px; border-radius: 4px; border: 1px solid #ffe58d; display: inline-block; }
        .status-rejected { color: #dc3545; background-color: #fdecea; padding: 4px 8px; border-radius: 4px; border: 1px solid #f5c6cb; display: inline-block; }
        .status-inactive { color: #6c757d; background-color: #f8f9fa; padding: 4px 8px; border-radius: 4px; border: 1px solid #dee2e6; display: inline-block; }
        .password-note { font-size: 12px; color: #666; margin-top: 4px; font-style: italic; }
        .auto-generated { background-color: #f8f8f8; color: #888; cursor: not-allowed; }
        .password-container { position: relative; width: 100%; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; }
        .switch-container { display: flex; align-items: center; margin-top: 8px; margin-bottom: 12px; }
        .switch-label { font-size: 13px; margin-left: 8px; color: #555; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #4a90e2; }
        input:focus + .slider { box-shadow: 0 0 1px #4a90e2; }
        input:checked + .slider:before { transform: translateX(26px); }
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
        .proof-thumbnail-container { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
        .proof-thumbnail { position: relative; }
        .proof-thumbnail img { width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 3px; }
        .remove-proof-btn { position: absolute; top: -5px; right: -5px; background-color: rgba(255, 0, 0, 0.7); color: white; border: none; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; line-height: 16px; text-align: center; cursor: pointer; display: flex; align-items: center; justify-content: center; }

    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div id="toast-container" class="toast-container"></div>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Client Accounts</h1>
             <!-- Search Bar -->
             <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search clients...">
                <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
            </div>
            <!-- Status Filter -->
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="" <?= empty($status_filter) ? 'selected' : '' ?>>All (Active)</option>
                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <!-- Add Button -->
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th class="sortable <?= $sort_column == 'username' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('username', $sort_column, $sort_direction, $status_filter) ?>">
                                Username <?= getSortIcon('username', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Contact Info</th>
                        <th class="sortable <?= $sort_column == 'company' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('company', $sort_column, $sort_direction, $status_filter) ?>">
                                Company Name <?= getSortIcon('company', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Address Info</th>
                        <th>Business Proof</th>
                        <th class="sortable <?= $sort_column == 'status' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('status', $sort_column, $sort_direction, $status_filter) ?>">
                                Status <?= getSortIcon('status', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                         <th class="sortable <?= $sort_column == 'created_at' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('created_at', $sort_column, $sort_direction, $status_filter) ?>">
                                Date Added <?= getSortIcon('created_at', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($accounts) > 0): ?>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?= htmlspecialchars($account['username'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="view-contact-btn"
                                        onclick='showContactInfo(<?= json_encode($account["email"]) ?>, <?= json_encode($account["phone"]) ?>)'>
                                        <i class="fas fa-address-card"></i> View
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($account['company'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="view-address-btn"
                                        onclick='showAddressInfo(<?= json_encode($account["company_address"]) ?>, <?= json_encode($account["region"]) ?>, <?= json_encode($account["city"]) ?>, <?= json_encode($account["bill_to_address"]) ?>)'>
                                        <i class="fas fa-map-marker-alt"></i> View
                                    </button>
                                </td>
                                <td class="photo-album">
                                    <?php
                                    $proofs = json_decode($account['business_proof'] ?? '[]', true);
                                    if (is_array($proofs)) {
                                        foreach ($proofs as $proof) {
                                            echo '<img src="' . htmlspecialchars($proof) . '" alt="Business Proof" width="50" onclick="openModal(this)">';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="<?= 'status-' . strtolower($account['status'] ?? 'pending') ?>">
                                    <?= htmlspecialchars($account['status'] ?? 'Pending') ?>
                                </td>
                                <td><?= date('Y-m-d', strtotime($account['created_at'])) ?></td>
                                <td class="action-buttons">
                                    <?php $business_proof_json = htmlspecialchars($account['business_proof'] ?? '[]', ENT_QUOTES); ?>
                                    <button class="edit-btn"
                                        onclick='openEditAccountForm(<?= $account["id"] ?>, <?= json_encode($account["username"]) ?>, <?= json_encode($account["email"]) ?>, <?= json_encode($account["phone"]) ?>, <?= json_encode($account["region"]) ?>, <?= json_encode($account["city"]) ?>, <?= json_encode($account["company"]) ?>, <?= json_encode($account["company_address"]) ?>, <?= json_encode($account["bill_to_address"]) ?>, <?= $business_proof_json ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $account['id'] ?>, <?= htmlspecialchars(json_encode($account['username']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($account['email']), ENT_QUOTES) ?>)">
                                        <i class="fas fa-exchange-alt"></i> Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                         <tr id="noAccountsFound" style="display: none;">
                              <td colspan="8" class="no-accounts">No client accounts found matching your search.</td>
                         </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-accounts">No client accounts found<?= !empty($status_filter) ? ' with status: ' . htmlspecialchars($status_filter) : '' ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modals (Existing: Add, Edit, Status, Image, Contact, Address, Confirmations) -->
    <!-- Add Account Modal -->
    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="form-modal-content">
            <div class="modal-header"><h2><i class="fas fa-user-plus"></i> Add New Account</h2></div>
            <div id="addAccountError" class="error-message"></div>
            <form id="addAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="add"><input type="hidden" name="ajax" value="1">
                <div class="modal-body">
                    <div class="two-column-form">
                        <div class="form-column">
                            <label for="username">Username: <span class="required">*</span></label>
                            <input type="text" id="username" name="username" autocomplete="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Letters, numbers, underscores only. Max 15 chars.">
                            <label for="phone">Phone Number: <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" required placeholder="e.g., 09171234567" maxlength="12" pattern="[0-9]+" title="Numbers only, max 12 digits." oninput="updateAutoPassword()">
                            <label for="password">Password: <span class="required">*</span></label>
                            <input type="text" id="password" name="password" readonly class="auto-generated" placeholder="Auto-generated password">
                            <div class="password-note">Auto: username + last 4 digits of phone</div>
                            <label for="email">Email: <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                        </div>
                        <div class="form-column">
                            <label for="region">Region: <span class="required">*</span></label>
                            <select id="region" name="region" required><option value="">Select Region</option></select>
                            <label for="city">City: <span class="required">*</span></label>
                            <select id="city" name="city" required disabled><option value="">Select City</option></select>
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
                            <label for="business_proof">Business Proof: <span class="required">*</span> <span class="file-info">(Max 3 files, 20MB/ea, JPG/PNG)</span></label>
                            <input type="file" id="business_proof" name="business_proof[]" required accept="image/jpeg, image/png" multiple>
                             <div id="add-proof-preview" class="proof-thumbnail-container" style="margin-top: 5px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="save-btn" onclick="confirmAddAccount()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
    <div id="addConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Add Account</div><div class="confirmation-message">Are you sure?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeAddConfirmation()">No</button><button class="confirm-yes" onclick="submitAddAccount()">Yes</button></div></div></div>

    <!-- Edit Account Modal -->
     <div id="editAccountOverlay" class="overlay" style="display: none;">
        <div class="form-modal-content">
            <div class="modal-header"><h2><i class="fas fa-edit"></i> Edit Account</h2></div>
            <div id="editAccountError" class="error-message"></div>
            <form id="editAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="edit"><input type="hidden" name="ajax" value="1">
                <input type="hidden" id="edit-id" name="id">
                <input type="hidden" id="existing-business-proof" name="existing_business_proof">
                <input type="hidden" id="edit-original-region" name="original_region"><input type="hidden" id="edit-original-city" name="original_city">
                <div class="modal-body">
                    <div class="two-column-form">
                        <div class="form-column">
                            <label for="edit-username">Username: <span class="required">*</span></label>
                            <input type="text" id="edit-username" name="username" autocomplete="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Letters, numbers, underscores only. Max 15 chars." oninput="updateEditAutoPassword()">
                            <label for="edit-phone">Phone Number: <span class="required">*</span></label>
                            <input type="tel" id="edit-phone" name="phone" required placeholder="e.g., 09171234567" maxlength="12" pattern="[0-9]+" title="Numbers only, max 12 digits." oninput="updateEditAutoPassword()">
                            <div class="switch-container">
                                <label class="switch"><input type="checkbox" id="edit-password-toggle"><span class="slider"></span></label><span class="switch-label">Set Manual Password</span>
                            </div>
                             <div class="switch-container" id="regenerate-password-container" style="display: block;"> <!-- Initially show regenerate option -->
                                <label class="switch"><input type="checkbox" id="regenerate_password" name="regenerate_password" value="1"><span class="slider"></span></label><span class="switch-label">Regenerate Auto-Password</span>
                             </div>
                            <div id="auto-password-container">
                                <label for="edit-auto-password">Password: (Auto-generated)</label>
                                <input type="text" id="edit-auto-password" readonly class="auto-generated" placeholder="Auto: username + last 4 phone digits">
                            </div>
                            <div id="manual-password-container" style="display: none;">
                                <label for="edit-manual-password">New Password: <span class="required">*</span></label>
                                <div class="password-container">
                                    <input type="password" id="edit-manual-password" name="manual_password" placeholder="Enter new password" minlength="6">
                                    <span class="toggle-password" onclick="togglePasswordVisibility('edit-manual-password', this)"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                            <label for="edit-email">Email: <span class="required">*</span></label>
                            <input type="email" id="edit-email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                        </div>
                        <div class="form-column">
                            <label for="edit-region">Region: <span class="required">*</span></label>
                            <select id="edit-region" name="region" required><option value="">Select Region</option></select>
                            <label for="edit-city">City: <span class="required">*</span></label>
                            <select id="edit-city" name="city" required disabled><option value="">Select City</option></select>
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
                            <label>Current Business Proof:</label>
                            <div id="edit-business-proof-container" class="proof-thumbnail-container"></div>
                            <label for="edit-business_proof" style="margin-top:10px;">Add New Business Proof: <span class="file-info">(Optional, Max 3 total, 20MB/ea)</span></label>
                            <input type="file" id="edit-business_proof" name="business_proof[]" accept="image/jpeg, image/png" multiple>
                            <div id="edit-new-proof-preview" class="proof-thumbnail-container" style="margin-top: 5px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="save-btn" onclick="confirmEditAccount()"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editConfirmationModal" class="confirmation-modal"><div class="confirmation-content"><div class="confirmation-title">Confirm Edit Account</div><div class="confirmation-message">Save changes?</div><div class="confirmation-buttons"><button class="confirm-no" onclick="closeEditConfirmation()">No</button><button class="confirm-yes" onclick="submitEditAccount()">Yes</button></div></div></div>

    <!-- Status Modal -->
    <div id="statusModal" class="overlay" style="display: none;">
        <div class="form-modal-content" style="max-width: 400px;"> <!-- Smaller modal for status -->
             <div class="modal-header"><h2>Change Status</h2></div>
             <div class="modal-body" style="text-align: center;">
                 <p id="statusMessage" style="margin-bottom: 20px;"></p>
                 <div class="modal-buttons" style="flex-direction: column; gap: 10px;">
                     <button class="approve-btn" onclick="changeStatus('Active')"><i class="fas fa-check"></i> Active</button>
                     <button class="pending-btn" onclick="changeStatus('Pending')"><i class="fas fa-hourglass-half"></i> Pending</button>
                     <button class="reject-btn" onclick="changeStatus('Rejected')"><i class="fas fa-times"></i> Reject</button>
                     <button class="inactive-btn" onclick="changeStatus('Inactive')"><i class="fas fa-ban"></i> Inactive</button>
                 </div>
            </div>
             <div class="modal-footer" style="justify-content: center;">
                 <button class="cancel-btn" onclick="closeStatusModal()"><i class="fas fa-times"></i> Cancel</button>
             </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="myModal" class="modal"><span class="close" onclick="closeModal()">&times;</span><img class="modal-content" id="img01"><div id="caption"></div></div>
    <!-- Contact Info Modal -->
    <div id="contactInfoModal" class="overlay"><div class="info-modal-content"><div class="info-modal-header"><h2><i class="fas fa-address-card"></i> Contact Information</h2><span class="info-modal-close" onclick="closeContactInfoModal()">&times;</span></div><div class="info-modal-body"><div class="info-section"><h3 class="info-section-title"><i class="fas fa-user"></i> Contact Details</h3><div class="contact-item"><div class="contact-icon"><i class="fas fa-envelope"></i></div><div class="contact-text"><div class="contact-value" id="modalEmail"></div><div class="contact-label">Email Address</div></div></div><div class="contact-item"><div class="contact-icon"><i class="fas fa-phone"></i></div><div class="contact-text"><div class="contact-value" id="modalPhone"></div><div class="contact-label">Phone Number</div></div></div></div></div></div></div>
    <!-- Address Info Modal -->
    <div id="addressInfoModal" class="overlay"><div class="info-modal-content"><div class="info-modal-header"><h2><i class="fas fa-map-marker-alt"></i> Address Information</h2><span class="info-modal-close" onclick="closeAddressInfoModal()">&times;</span></div><div class="info-modal-body"><div class="info-section"><h3 class="info-section-title"><i class="fas fa-building"></i> Location Details</h3><table class="info-table"><tr><th>Ship to Address</th><td id="modalCompanyAddress"></td></tr><tr><th>Bill To Address</th><td id="modalBillToAddress"></td></tr><tr><th>Region</th><td id="modalRegion"></td></tr><tr><th>City</th><td id="modalCity"></td></tr></table></div></div></div></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
    // --- Global Vars ---
    let currentAccountId = 0;
    let regionCityMap = new Map();
    let currentEditProofs = []; // To manage proofs during edit

    // --- Client-side Search ---
    $(document).ready(function() {
        function performSearch() {
            const searchTerm = $('#searchInput').val().toLowerCase().trim();
            let visibleCount = 0; let totalRows = 0;
            $('.accounts-table tbody tr').each(function() {
                const row = $(this);
                if (row.attr('id') === 'noAccountsFound') return;
                if (row.find('.no-accounts').length > 0) { row.hide(); return; }
                totalRows++;
                const rowText = row.text().toLowerCase();
                if (rowText.includes(searchTerm)) { row.show(); visibleCount++; } else { row.hide(); }
            });
            if (visibleCount === 0 && totalRows > 0 && searchTerm !== '') { $('#noAccountsFound').show(); }
            else { $('#noAccountsFound').hide(); if (searchTerm === '' && totalRows === 0 && $('.accounts-table tbody .no-accounts').length > 0) { $('.accounts-table tbody .no-accounts').closest('tr').show(); } }
        }
        $('#searchInput').on('input', performSearch);
        $('#searchBtn').on('click', performSearch);
        if ($('#searchInput').val()) performSearch(); // Handle back button case
    });

    // --- Status Filter ---
    function filterByStatus() {
        const selectedStatus = document.getElementById('statusFilter').value;
        const url = new URL(window.location.href);
        const currentSort = url.searchParams.get('sort');
        const currentDirection = url.searchParams.get('direction');
        const params = {};
        if (selectedStatus) params.status = selectedStatus;
        if (currentSort) params.sort = currentSort;
        if (currentDirection) params.direction = currentDirection;
        window.location.search = Object.keys(params).map(key => key + '=' + encodeURIComponent(params[key])).join('&');
    }

    // --- Modal Functions (Image, Contact, Address) ---
    function openModal(imgElement) { $("#img01").attr('src', imgElement.src); $("#caption").text(imgElement.alt); $("#myModal").show(); }
    function closeModal() { $("#myModal").hide(); }
    function showContactInfo(email, phone) { $("#modalEmail").text(email || 'N/A'); $("#modalPhone").text(phone || 'N/A'); $("#contactInfoModal").css('display', 'flex'); }
    function closeContactInfoModal() { $("#contactInfoModal").hide(); }
    function showAddressInfo(companyAddress, region, city, billToAddress) { $("#modalCompanyAddress").text(companyAddress || 'N/A'); $("#modalBillToAddress").text(billToAddress || 'N/A'); $("#modalRegion").text(region || 'N/A'); $("#modalCity").text(city || 'N/A'); $("#addressInfoModal").css('display', 'flex'); }
    function closeAddressInfoModal() { $("#addressInfoModal").hide(); }

    // --- Add/Edit Form Helpers ---
    function showError(elementId, message) { $(`#${elementId}`).text(message).show(); }
    function resetErrors() { $('.error-message').text('').hide(); }
    function updateAutoPassword() { const u = $('#username').val(); const p = $('#phone').val(); $('#password').val(u && p ? u + p.slice(-4).padStart(4,'0') : ''); }
    function updateEditAutoPassword() { const u = $('#edit-username').val(); const p = $('#edit-phone').val(); $('#edit-auto-password').val(u && p ? u + p.slice(-4).padStart(4,'0') : ''); }
    function togglePasswordVisibility(fieldId, iconElement) { const pf = $(`#${fieldId}`); const icon = $(iconElement).find('i'); if (pf.attr('type') === 'password') { pf.attr('type', 'text'); icon.removeClass('fa-eye').addClass('fa-eye-slash'); } else { pf.attr('type', 'password'); icon.removeClass('fa-eye-slash').addClass('fa-eye'); } }
    function previewFiles(input, previewContainerId) {
        const previewContainer = $(`#${previewContainerId}`); previewContainer.empty();
        if (input.files) { $.each(input.files, function(i, file) { if (file.type.startsWith('image/')) { const reader = new FileReader(); reader.onload = function(e) { $('<img>').attr('src', e.target.result).css({width: '50px', height: '50px', objectFit: 'cover', margin: '2px', border: '1px solid #ddd'}).appendTo(previewContainer); }; reader.readAsDataURL(file); } }); }
    }
    $('#business_proof').on('change', function() { previewFiles(this, 'add-proof-preview'); });
    $('#edit-business_proof').on('change', function() { previewFiles(this, 'edit-new-proof-preview'); });

    // --- Region/City Loading ---
    async function loadPhilippinesRegions() { /* ... (same as before) ... */ try { const response = await fetch('https://psgc.gitlab.io/api/regions/'); const regions = await response.json(); const selects = ['#region', '#edit-region']; selects.forEach(selId => { const sel = $(selId); if(sel.length) { sel.html('<option value="">Select Region</option>'); regions.sort((a, b) => a.name.localeCompare(b.name)).forEach(region => sel.append(`<option value="${region.code}">${region.name}</option>`)); } }); } catch (error) { console.error('Error loading regions:', error); } }
    async function loadCities(regionCode, targetId, selectedCity = null) { /* ... (same as before) ... */ if (!regionCode) { $(`#${targetId}`).html('<option value="">Select City</option>').prop('disabled', true); return; } try { let cities = regionCityMap.has(regionCode) ? regionCityMap.get(regionCode) : null; if (!cities) { const response = await fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/cities-municipalities/`); cities = await response.json(); regionCityMap.set(regionCode, cities); } populateCitySelect(cities, targetId, selectedCity); } catch (error) { console.error('Error loading cities:', error); $(`#${targetId}`).html('<option value="">Error loading</option>').prop('disabled', true); } }
    function populateCitySelect(cities, targetId, selectedCity = null) { /* ... (same as before) ... */ const sel = $(`#${targetId}`); if(sel.length) { sel.html('<option value="">Select City</option>'); cities.sort((a, b) => a.name.localeCompare(b.name)).forEach(city => sel.append(`<option value="${city.name}" ${selectedCity === city.name ? 'selected' : ''}>${city.name}</option>`)); sel.prop('disabled', false); if (selectedCity) sel.trigger('change'); } }
    $(document).ready(function() { loadPhilippinesRegions(); $('#region').on('change', function() { loadCities(this.value, 'city'); }); $('#edit-region').on('change', function() { loadCities(this.value, 'edit-city'); }); });

    // --- Add Account Functions ---
    function openAddAccountForm() { resetErrors(); $('#addAccountForm')[0].reset(); $('#city').prop('disabled', true); $('#add-proof-preview').empty(); updateAutoPassword(); $('#addAccountOverlay').css('display', 'flex'); }
    function closeAddAccountForm() { $('#addAccountOverlay').hide(); }
    function confirmAddAccount() { const form = $('#addAccountForm')[0]; if (!form.checkValidity()) { form.reportValidity(); return; } if ($('#business_proof')[0].files.length === 0) { showError('addAccountError', 'Business proof is required.'); showToast('Business proof is required.', 'error'); return; } $('#addConfirmationModal').css('display', 'flex'); }
    function closeAddConfirmation() { $('#addConfirmationModal').hide(); }
    function submitAddAccount() { closeAddConfirmation(); const formData = new FormData($('#addAccountForm')[0]); /* AJAX call (same as before) */ $.ajax({ url: window.location.href, type: 'POST', data: formData, contentType: false, processData: false, dataType: 'json', success: function(response) { if(response.success) { showToast('Account added successfully!', 'success'); setTimeout(() => { window.location.reload(); }, 1500); } else { showError('addAccountError', response.message || 'An error occurred.'); showToast(response.message || 'An error occurred.', 'error'); } }, error: function(xhr) { console.error('AJAX error:', xhr.responseText); showError('addAccountError', 'A server error occurred.'); showToast('A server error occurred.', 'error'); } }); }

    // --- Edit Account Functions ---
    function openEditAccountForm(id, username, email, phone, regionCode, cityName, company, company_address, bill_to_address, business_proof_json) {
        resetErrors(); $('#editAccountForm')[0].reset(); currentEditProofs = []; $('#edit-new-proof-preview').empty();
        $('#edit-id').val(id); $('#edit-username').val(username); $('#edit-email').val(email); $('#edit-phone').val(phone); $('#edit-company').val(company); $('#edit-company_address').val(company_address); $('#edit-bill_to_address').val(bill_to_address);
        // Password setup
        $('#edit-password-toggle').prop('checked', false).trigger('change'); // Start with auto-gen view
        updateEditAutoPassword(); // Calculate initial auto-password display
        $('#regenerate-password-container').show(); // Show regenerate option
        $('#regenerate_password').prop('checked', false); // Uncheck regenerate
        // Region/City setup
        $('#edit-original-region').val(regionCode); $('#edit-original-city').val(cityName);
        const regionSelect = $('#edit-region'); const citySelect = $('#edit-city');
        if (regionCode && regionSelect.find(`option[value="${regionCode}"]`).length > 0) {
             regionSelect.val(regionCode);
             loadCities(regionCode, 'edit-city', cityName); // Load cities and pre-select
        } else { citySelect.prop('disabled', true); }
        // Business Proof setup
        const proofContainer = $('#edit-business-proof-container').empty();
        try { currentEditProofs = JSON.parse(business_proof_json || '[]'); } catch (e) { currentEditProofs = []; console.error("Parsing existing proof error:", e); }
        renderEditProofs();
        $('#existing-business-proof').val(JSON.stringify(currentEditProofs)); // Store current proofs
        $('#editAccountOverlay').css('display', 'flex');
    }
    function renderEditProofs() {
         const proofContainer = $('#edit-business-proof-container').empty();
         if (currentEditProofs.length > 0) {
              currentEditProofs.forEach((proof, index) => {
                   const thumbDiv = $('<div class="proof-thumbnail"></div>');
                   $('<img>').attr('src', proof).attr('alt', 'Proof ' + (index + 1)).appendTo(thumbDiv);
                   $('<button type="button" class="remove-proof-btn" onclick="removeEditProof('+index+')">&times;</button>').appendTo(thumbDiv);
                   proofContainer.append(thumbDiv);
              });
         } else { proofContainer.html('<p>No current proofs.</p>'); }
         $('#existing-business-proof').val(JSON.stringify(currentEditProofs));
    }
    function removeEditProof(index) { if (index >= 0 && index < currentEditProofs.length) { currentEditProofs.splice(index, 1); renderEditProofs(); } }
    function closeEditAccountForm() { $('#editAccountOverlay').hide(); }
    function confirmEditAccount() { const form = $('#editAccountForm')[0]; if (!form.checkValidity()) { form.reportValidity(); return; } if (currentEditProofs.length === 0 && $('#edit-business_proof')[0].files.length === 0) { showError('editAccountError', 'Business proof cannot be empty.'); showToast('Business proof cannot be empty.', 'error'); return; } $('#editConfirmationModal').css('display', 'flex'); }
    function closeEditConfirmation() { $('#editConfirmationModal').hide(); }
    function submitEditAccount() { closeEditConfirmation(); const formData = new FormData($('#editAccountForm')[0]); /* Important: Update existing proofs before sending */ formData.set('existing_business_proof', JSON.stringify(currentEditProofs)); /* AJAX call (same as before) */ $.ajax({ url: window.location.href, type: 'POST', data: formData, contentType: false, processData: false, dataType: 'json', success: function(response) { if(response.success) { showToast('Account updated successfully!', 'success'); setTimeout(() => { window.location.reload(); }, 1500); } else { showError('editAccountError', response.message || 'An error occurred.'); showToast(response.message || 'An error occurred.', 'error'); } }, error: function(xhr) { console.error('AJAX error:', xhr.responseText); showError('editAccountError', 'A server error occurred.'); showToast('A server error occurred.', 'error'); } }); }
    // Edit form password toggle logic
    $('#edit-password-toggle').on('change', function() { const manualVisible = this.checked; $('#auto-password-container').toggle(!manualVisible); $('#manual-password-container').toggle(manualVisible); $('#regenerate-password-container').toggle(!manualVisible); if (manualVisible) { $('#regenerate_password').prop('checked', false); $('#edit-manual-password').prop('required', true); } else { $('#edit-manual-password').prop('required', false); } });


    // --- Status Change Functions ---
    function openStatusModal(id, username, email) { currentAccountId = id; $("#statusMessage").text(`Change status for ${username} (${email})`); $("#statusModal").css('display', 'flex'); }
    function closeStatusModal() { $("#statusModal").hide(); }
    function changeStatus(status) { /* AJAX call (same as before) */ if (!currentAccountId) return; $.ajax({ url: window.location.href, type: 'POST', data: { ajax: true, formType: 'status', id: currentAccountId, status: status }, dataType: 'json', success: function(response) { if(response.success) { showToast('Status changed successfully!', 'success'); setTimeout(() => { window.location.reload(); }, 1500); } else { showToast(response.message || 'An error occurred.', 'error'); } closeStatusModal(); }, error: function(xhr) { console.error('AJAX error:', xhr.responseText); showToast('A server error occurred.', 'error'); closeStatusModal(); } }); }

    // --- Toast Function ---
    function showToast(message, type = 'success') { /* ... (same as before) ... */ if (typeof toastr !== 'undefined') { toastr.options = { closeButton: false, progressBar: true, positionClass: "toast-top-right", timeOut: 3000 }; switch(type) { case 'success': toastr.success(message); break; case 'error': toastr.error(message); break; default: toastr.info(message); } } else { console.log(`${type}: ${message}`); alert(message); } }

    // --- Initial Setup ---
    $(document).ready(function() {
        toastr.options = { closeButton: false, progressBar: true, positionClass: "toast-top-right", timeOut: 3000 };
        // Add event listeners etc.
    });
    </script>
</body>
</html>
<?php
// --- Close Connection AT THE END ---
if ($conn instanceof mysqli) {
    $conn->close();
}
?>