<?php
session_start();
// --- Error Reporting for Debugging (Turn off display_errors in production) ---
ini_set('display_errors', 1); // Show errors during development
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ini_set('log_errors', 1); // Enable logging
// ini_set('error_log', '/path/to/your/php-error.log'); // Specify log file

include "../../backend/db_connection.php"; // Should handle connection errors
include "../../backend/check_role.php"; // Assumes this handles unauthorized access

// Ensure checkRole exits if not authorized
checkRole('Accounts - Clients');


// --- Function to validate unique username/email ---
function validateUnique($conn, $username, $email, $id = null) {
    $result = ['exists' => false, 'field' => null, 'message' => ''];
    // Use prepared statements consistently
    try {
        // Check username
        $sqlUser = "SELECT id FROM clients_accounts WHERE username = ?";
        $paramsUser = [$username];
        $typesUser = "s";
        if ($id !== null) {
            $sqlUser .= " AND id != ?";
            $paramsUser[] = $id;
            $typesUser .= "i";
        }
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->bind_param($typesUser, ...$paramsUser);
        $stmtUser->execute();
        $stmtUser->store_result();
        if ($stmtUser->num_rows > 0) {
            $stmtUser->close();
            return ['exists' => true, 'field' => 'username', 'message' => 'Username already exists'];
        }
        $stmtUser->close();

        // Check email
        $sqlEmail = "SELECT id FROM clients_accounts WHERE email = ?";
        $paramsEmail = [$email];
        $typesEmail = "s";
        if ($id !== null) {
            $sqlEmail .= " AND id != ?";
            $paramsEmail[] = $id;
            $typesEmail .= "i";
        }
        $stmtEmail = $conn->prepare($sqlEmail);
        $stmtEmail->bind_param($typesEmail, ...$paramsEmail);
        $stmtEmail->execute();
        $stmtEmail->store_result();
        if ($stmtEmail->num_rows > 0) {
            $stmtEmail->close();
            return ['exists' => true, 'field' => 'email', 'message' => 'Email already exists'];
        }
        $stmtEmail->close();

    } catch (Exception $e) {
        error_log("Unique validation error: " . $e->getMessage());
        // Return a generic error or rethrow, depending on desired handling
        return ['exists' => true, 'field' => 'database', 'message' => 'Database error during validation.'];
    }
    return $result; // No conflicts found
}

// --- Function to generate a safe filename ---
function generateSafeFilename($username, $originalFilename) {
    $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)); // Lowercase extension
    // Basic sanitization: replace non-alphanumeric/hyphen/underscore with underscore
    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalFilename, PATHINFO_FILENAME));
    // Trim leading/trailing underscores and limit length
    $safeBaseName = trim(substr($safeBaseName, 0, 50), '_');
    // Prevent empty base names
    if (empty($safeBaseName)) {
        $safeBaseName = 'file';
    }
    // Add username and a more unique ID
    $uniqueId = bin2hex(random_bytes(5)); // Generate 10 hex chars
    return $username . '_' . $safeBaseName . '_' . $uniqueId . '.' . $extension;
}


// --- Handle AJAX POST Requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json'); // Set content type early

    // --- ADD ACCOUNT ---
    if ($_POST['formType'] == 'add') {
        $response = ['success' => false, 'message' => 'An unexpected error occurred.']; // Default response
        try {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''); // Sanitize phone
            $region = $_POST['region'] ?? '';
            $city = $_POST['city'] ?? '';
            $company = trim($_POST['company'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $bill_to_address = trim($_POST['bill_to_address'] ?? '');

            // Server-side Validation
            if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{1,15}$/', $username)) { throw new Exception("Invalid username format or length."); }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new Exception("Invalid email address."); }
            if (empty($phone) || strlen($phone) < 7 || strlen($phone) > 12) { throw new Exception("Invalid phone number."); } // Basic length check
            if (empty($region)) { throw new Exception("Region is required."); }
            if (empty($city)) { throw new Exception("City is required."); }
            if (empty($company)) { throw new Exception("Company Name is required."); }
            if (empty($company_address)) { throw new Exception("Ship to Address is required."); }
            if (empty($bill_to_address)) { throw new Exception("Bill To Address is required."); }
            if (!isset($_FILES['business_proof']) || empty($_FILES['business_proof']['name'][0])) { throw new Exception("Business proof is required."); }

            // Validate uniqueness
            $uniqueCheck = validateUnique($conn, $username, $email);
            if ($uniqueCheck['exists']) { throw new Exception($uniqueCheck['message']); }

            // Auto-generate password
            $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad(substr($phone, 0, 4), 4, '0');
            $autoPassword = $username . $last4digits;
            $password = password_hash($autoPassword, PASSWORD_DEFAULT);
            
            $business_proof_paths = []; // Store URL paths

            // --- File Paths ---
            // Use DIRECTORY_SEPARATOR for filesystem paths for better portability
            $uploadBaseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            $userUploadDir = $uploadBaseDir . $username . DIRECTORY_SEPARATOR;
            // URL path always uses forward slashes
            $userUploadUrl = '/admin/uploads/' . $username . '/';

            // Create directory
            if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0775, true) && !is_dir($userUploadDir)) { // Use 0775 typically
                 throw new Exception('Failed to create upload directory. Check server permissions.');
            }

            // --- Process Uploads ---
            $uploaded_count = 0;
            if (isset($_FILES['business_proof'])) {
                if (count($_FILES['business_proof']['name']) > 3) { throw new Exception('Maximum of 3 photos allowed.'); }
                
                foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['business_proof']['error'][$key] === UPLOAD_ERR_OK) {
                        $originalFilename = basename($_FILES['business_proof']['name'][$key]);
                        $file_type = mime_content_type($tmp_name); // More reliable type check
                        $file_size = $_FILES['business_proof']['size'][$key];
                        
                        $allowed_types = ['image/jpeg', 'image/png'];
                        $max_size = 20 * 1024 * 1024; // 20MB

                        if (!in_array($file_type, $allowed_types)) { throw new Exception('Invalid file type for ' . htmlspecialchars($originalFilename) . '. Only JPG/PNG allowed.'); }
                        if ($file_size > $max_size) { throw new Exception('File ' . htmlspecialchars($originalFilename) . ' exceeds 20MB limit.'); }

                        $safeFilename = generateSafeFilename($username, $originalFilename);
                        $filesystemTargetPath = $userUploadDir . $safeFilename;
                        $urlPath = $userUploadUrl . $safeFilename; // Path to store in DB

                        if (move_uploaded_file($tmp_name, $filesystemTargetPath)) {
                            $business_proof_paths[] = $urlPath; // Store the URL path
                            $uploaded_count++;
                        } else {
                             throw new Exception('Failed to move uploaded file: ' . htmlspecialchars($originalFilename));
                        }
                    } elseif ($_FILES['business_proof']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                         throw new Exception('Error uploading file: Code ' . $_FILES['business_proof']['error'][$key]);
                    }
                }
            }
            if ($uploaded_count === 0) { throw new Exception("No valid business proof files were uploaded."); }

            // Encode paths and insert into DB
            $business_proof_json = json_encode($business_proof_paths);
            $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, bill_to_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("ssssssssss", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json);

            if ($stmt->execute()) {
                $response = ['success' => true, 'reload' => true];
            } else {
                throw new Exception('Database error: Failed to add account. ' . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            error_log("Add Account Error: " . $e->getMessage()); // Log detailed error
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }

    // --- EDIT ACCOUNT ---
    if ($_POST['formType'] == 'edit') {
         $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
         try {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
            $region = $_POST['region'] ?? '';
            $city = $_POST['city'] ?? '';
            $company = trim($_POST['company'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $bill_to_address = trim($_POST['bill_to_address'] ?? '');
            $manual_password = $_POST['manual_password'] ?? '';
            $existing_business_proof_json = $_POST['existing_business_proof'] ?? '[]'; // Get existing proofs JSON

             // Server-side Validation
             if (empty($id)) { throw new Exception("Invalid account ID."); }
             if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{1,15}$/', $username)) { throw new Exception("Invalid username format or length."); }
             if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new Exception("Invalid email address."); }
             if (empty($phone) || strlen($phone) < 7 || strlen($phone) > 12) { throw new Exception("Invalid phone number."); }
             if (empty($region)) { throw new Exception("Region is required."); }
             if (empty($city)) { throw new Exception("City is required."); }
             if (empty($company)) { throw new Exception("Company Name is required."); }
             if (empty($company_address)) { throw new Exception("Ship to Address is required."); }
             if (empty($bill_to_address)) { throw new Exception("Bill To Address is required."); }
             if (!empty($manual_password) && strlen($manual_password) < 6) { throw new Exception("Manual password must be at least 6 characters."); }

             // Validate uniqueness (excluding current user)
             $uniqueCheck = validateUnique($conn, $username, $email, $id);
             if ($uniqueCheck['exists']) { throw new Exception($uniqueCheck['message']); }

             // Handle password update
             $passwordSqlPart = "";
             $passwordParam = null;
             $passwordType = "";
             if (!empty($manual_password)) {
                 $password = password_hash($manual_password, PASSWORD_DEFAULT);
                 $passwordSqlPart = ", password = ?";
                 $passwordParam = $password;
                 $passwordType = "s";
             }

            // --- File Paths (Consistent with Add) ---
            $uploadBaseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            $userUploadDir = $uploadBaseDir . $username . DIRECTORY_SEPARATOR;
            $userUploadUrl = '/admin/uploads/' . $username . '/';

            // Decode existing proofs safely
            $final_business_proof_paths = json_decode($existing_business_proof_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($final_business_proof_paths)) {
                 error_log("Invalid existing_business_proof JSON received during edit for ID $id: " . $existing_business_proof_json);
                 $final_business_proof_paths = []; // Fallback to empty array
            }

            // --- Process Newly Uploaded Files (if any) ---
            $new_files_uploaded = false;
            if (isset($_FILES['business_proof']) && !empty($_FILES['business_proof']['name'][0])) {
                 $new_files_uploaded = true;
                 $final_business_proof_paths = []; // REPLACE existing if new files are uploaded

                 // Create directory if needed (username might have changed)
                if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0775, true) && !is_dir($userUploadDir)) {
                     throw new Exception('Failed to create upload directory for edit. Check permissions.');
                }

                 if (count($_FILES['business_proof']['name']) > 3) { throw new Exception('Maximum of 3 new photos allowed.'); }

                 $uploaded_count = 0;
                 foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                     if ($_FILES['business_proof']['error'][$key] === UPLOAD_ERR_OK) {
                         $originalFilename = basename($_FILES['business_proof']['name'][$key]);
                         $file_type = mime_content_type($tmp_name);
                         $file_size = $_FILES['business_proof']['size'][$key];
                         $allowed_types = ['image/jpeg', 'image/png'];
                         $max_size = 20 * 1024 * 1024;

                         if (!in_array($file_type, $allowed_types)) { throw new Exception('Invalid file type for ' . htmlspecialchars($originalFilename) . '.'); }
                         if ($file_size > $max_size) { throw new Exception('File ' . htmlspecialchars($originalFilename) . ' exceeds 20MB.'); }

                         $safeFilename = generateSafeFilename($username, $originalFilename);
                         $filesystemTargetPath = $userUploadDir . $safeFilename;
                         $urlPath = $userUploadUrl . $safeFilename;

                         if (move_uploaded_file($tmp_name, $filesystemTargetPath)) {
                             $final_business_proof_paths[] = $urlPath;
                             $uploaded_count++;
                         } else {
                              throw new Exception('Failed to move newly uploaded file: ' . htmlspecialchars($originalFilename));
                         }
                     } elseif ($_FILES['business_proof']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                          throw new Exception('Error uploading new file: Code ' . $_FILES['business_proof']['error'][$key]);
                     }
                 }
                 if ($uploaded_count === 0) { throw new Exception("New files were selected, but none were uploaded successfully."); }
            }
            // If no new files uploaded, $final_business_proof_paths remains the decoded existing paths

            // Ensure business proof is not empty if it was required initially (unless new files failed)
             if (empty($final_business_proof_paths) && !$new_files_uploaded) {
                 // This case should ideally not happen if validation on add was correct,
                 // but as a safeguard, you might want to re-check if proofs are mandatory.
                 // For now, we allow saving without proofs if none existed and none were added.
             }

            // Convert final paths array to JSON
            $business_proof_json_to_save = json_encode($final_business_proof_paths);

            // --- Prepare and Execute Update Query ---
            $sql = "UPDATE clients_accounts SET username = ?, email = ?, phone = ?, region = ?, city = ?, company = ?, company_address = ?, bill_to_address = ?, business_proof = ? $passwordSqlPart WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $types = "sssssssss" . $passwordType . "i";
            $params = [$username, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json_to_save];
            if ($passwordParam !== null) { $params[] = $passwordParam; }
            $params[] = $id;

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $response = ['success' => true, 'reload' => true];
            } else {
                 throw new Exception('Database error: Failed to update account. ' . $stmt->error);
            }
            $stmt->close();

         } catch (Exception $e) {
             error_log("Edit Account Error (ID: $id): " . $e->getMessage());
             $response = ['success' => false, 'message' => $e->getMessage()];
         }
         echo json_encode($response);
         exit;
    }

    // --- STATUS CHANGE ---
    if ($_POST['formType'] == 'status') {
        $response = ['success' => false, 'message' => 'An error occurred changing status.'];
        try {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (empty($id)) { throw new Exception("Invalid account ID."); }

            $allowed_statuses = ['Active', 'Rejected', 'Pending', 'Inactive'];
            if (!in_array($status, $allowed_statuses)) { throw new Exception('Invalid status value provided.'); }

            $stmt = $conn->prepare("UPDATE clients_accounts SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'reload' => true];
            } else {
                throw new Exception('Database error: Failed to change status. ' . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
             error_log("Status Change Error (ID: $id): " . $e->getMessage());
             $response = ['success' => false, 'message' => $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }

     // Fallback for unknown formType
     echo json_encode(['success' => false, 'message' => 'Invalid form type specified.']);
     exit;

} // End POST AJAX Handling


// --- GET ACCOUNTS FOR TABLE DISPLAY ---
$accounts_data = [];
$error_message = null;
try {
    $status_filter = $_GET['status'] ?? '';
    $sql = "SELECT id, username, email, phone, region, city, company, company_address, bill_to_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'";
    $params = [];
    $types = "";

    if (!empty($status_filter)) {
        $allowed_filters = ['Pending', 'Active', 'Rejected', 'Inactive'];
         if (in_array($status_filter, $allowed_filters)) {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
            $types .= "s";
         } else {
             $status_filter = ''; // Ignore invalid filter
         }
    }
    $sql .= " ORDER BY CASE status WHEN 'Pending' THEN 1 WHEN 'Active' THEN 2 WHEN 'Rejected' THEN 3 WHEN 'Inactive' THEN 4 ELSE 5 END, created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $accounts_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
     error_log("Error fetching accounts data: " . $e->getMessage());
     $error_message = "Error fetching account data from database.";
}

// --- Helper function for display ---
function truncate($text, $max = 15) {
     if ($text === null) return 'N/A';
     $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); // Escape text for display
    return (mb_strlen($text) > $max) ? mb_substr($text, 0, $max) . '...' : $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Accounts</title>
    <!-- Links removed for brevity, assume they are correct -->
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/accounts_clients.css">
    <link rel="stylesheet" href="/css/toast.css">
    <!-- Assume other CSS files are linked -->
    <style>
        /* --- Base Modal and Overlay --- */
        .overlay {
            display: none; /* Hidden by default */
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            /* display: flex; */ /* REMOVED - JS will handle showing */
            justify-content: center; /* Keep these for when JS sets display:flex */
            align-items: center;     /* Keep these for when JS sets display:flex */
            backdrop-filter: blur(3px);
            overflow-y: auto; /* Allow scrolling if content overflows viewport */
            padding: 20px 0; /* Add some padding top/bottom */
        }

        /* --- Form Modal Content (Add/Edit) --- */
        .form-modal-content {
            display: flex;
            flex-direction: column;
            max-height: 90vh; /* Adjusted slightly */
            height: auto;
            width: 90%; /* More responsive */
            max-width: 700px; /* Increased max-width slightly */
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden; /* Content scroll handled by modal-body */
            margin: auto; /* Center horizontally */
            position: relative;
            top: auto; left: auto; transform: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* --- Info Modal Content (Contact/Address) --- */
        .info-modal-content {
            background-color: #ffffff;
            margin: 0; /* Centered by overlay's flex */
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            animation: modalFadeIn 0.3s ease-out;
            display: flex;
            flex-direction: column;
        }

        /* --- Status Modal Content --- */
        #statusModal .overlay-content { /* Specific styling for status modal */
            max-width: 450px; /* Narrower */
            width: 90%;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            margin: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 25px;
            text-align: center;
        }

        /* --- Confirmation Modal Content --- */
        .confirmation-content {
            background-color: #fefefe;
            padding: 25px 30px;
            border-radius: 8px;
            width: 380px;
            max-width: 90%;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalPopIn 0.3s ease-out;
            margin: auto; /* Centered by overlay's flex */
        }

        /* --- Image Zoom Modal --- */
        #myModal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 9999;
            padding-top: 60px; /* Adjusted padding */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content { /* Image inside zoom modal */
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 85vh; /* Limit image height */
            animation: zoomIn 0.3s ease-out;
        }
        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 15px 0;
            height: auto; /* Allow dynamic height */
        }
        .close { /* Close button for image zoom */
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
        }

        /* --- Modal Header/Body/Footer --- */
        .modal-header {
            background-color: #ffffff;
            padding: 15px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #ddd;
            position: sticky; /* Keep header visible on scroll */
            top: 0;
            z-index: 10;
        }
        .modal-header h2 {
            margin: 0;
            padding: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .modal-header h2 i {
            margin-right: 8px;
            color: #4a90e2;
        }
        .modal-body {
            padding: 20px;
            overflow-y: auto; /* Enable vertical scroll within body */
            /* Calculate max height based on viewport and header/footer */
            max-height: calc(90vh - 130px); /* Adjust as needed */
            flex-grow: 1; /* Allow body to take available space */
        }
        .modal-footer {
            background-color: #f7f7f7;
            padding: 12px 20px;
            border-top: 1px solid #ddd;
            text-align: right; /* Align buttons right by default */
            border-radius: 0 0 8px 8px;
            position: sticky; /* Keep footer visible */
            bottom: 0;
            z-index: 10;
            display: flex;
            justify-content: flex-end; /* Align buttons right */
            gap: 10px;
        }

        /* --- Form Specific Styles --- */
        .two-column-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Responsive columns */
            gap: 15px 20px; /* Row and column gap */
        }
        .form-column {
            display: flex;
            flex-direction: column;
        }
        .form-full-width {
            grid-column: 1 / -1; /* Span all columns */
        }
        label {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px 12px; /* Slightly more padding */
            transition: border-color 0.3s, box-shadow 0.3s;
            outline: none;
            font-size: 14px;
            margin-bottom: 15px; /* Consistent bottom margin */
            background-color: #fff;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #4a90fe;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2); /* Focus ring */
        }
        input::placeholder, textarea::placeholder {
            color: #aaa;
            font-style: italic;
        }
        input[type="file"] {
            padding: 5px; /* Adjust file input padding */
            border: 1px dashed #ccc; /* Dashed border for file input */
            background-color: #f9f9f9;
        }
        input[readonly].auto-generated {
            background-color: #f8f8f8;
            color: #888;
            cursor: not-allowed;
            border-style: dashed;
        }
        textarea {
            resize: vertical;
            min-height: 80px; /* Increased min-height */
        }
        .required { color: #d9534f; font-weight: bold; margin-left: 2px; }
        .file-info { font-size: 0.85em; color: #666; font-style: italic; display: block; margin-top: -10px; margin-bottom: 10px;}
        .password-note { font-size: 0.8em; color: #666; margin-top: -10px; margin-bottom: 10px; font-style: italic; }
        .address-group {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #fafafa;
        }
        .address-group h3 {
            margin-top: 0;
            color: #4a90e2;
            font-size: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
        }
        .address-group h3 i {
            margin-right: 8px;
        }

        /* --- Password Toggle Switch --- */
        .switch-container { display: flex; align-items: center; margin-top: 8px; margin-bottom: 15px; }
        .switch-label { font-size: 13px; margin-left: 8px; color: #555; cursor: pointer; user-select: none; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: #4a90e2; }
        input:focus + .slider { box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2); }
        input:checked + .slider:before { transform: translateX(26px); }

        /* --- Password Visibility Toggle Icon --- */
        .password-container { position: relative; width: 100%; margin-bottom: 15px; }
        .toggle-password {
            position: absolute;
            right: 1px; top: 1px; bottom: 1px;
            display: flex; align-items: center;
            padding: 0 10px;
            cursor: pointer;
            color: #666;
            background-color: #fff;
            border-left: 1px solid #ccc;
            border-radius: 0 4px 4px 0;
            z-index: 2; /* Ensure it's above input */
        }
        .toggle-password:hover { color: #333; }
        .password-container input[type="password"],
        .password-container input[type="text"] { /* Ensure input padding doesn't overlap icon */
            padding-right: 40px;
        }

        /* --- Business Proof Display in Edit Form --- */
        #edit-business-proof-container {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px dashed #ddd;
            border-radius: 4px;
            min-height: 60px; /* Adjusted height */
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px; /* Spacing between images */
        }
        #edit-business-proof-container h4 {
            width: 100%;
            margin-bottom: 8px; /* Spacing below header */
            font-size: 14px;
            color: #555;
            font-weight: 500;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        #edit-business-proof-container img {
            border: 1px solid #ccc;
            padding: 2px;
            max-width: 65px; /* Slightly larger */
            height: auto;
            background: #fff;
            border-radius: 3px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        #edit-business-proof-container img:hover {
            transform: scale(1.05);
            border-color: #888;
        }
        #edit-business-proof-container p {
            width: 100%;
            font-size: 13px;
            color: #888;
            margin: 5px 0;
            text-align: center;
        }

        /* --- Table Styles --- */
        .accounts-table-container { overflow-x: auto; } /* Allow horizontal scroll on small screens */
        .accounts-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .accounts-table th, .accounts-table td {
            border: 1px solid #ddd;
            padding: 10px 12px; /* Adjusted padding */
            text-align: left;
            vertical-align: middle; /* Align content vertically */
            font-size: 14px;
        }
        .accounts-table th { background-color: #f2f2f2; font-weight: 600; white-space: nowrap; }
        .accounts-table tr:nth-child(even) { background-color: #f9f9f9; }
        .accounts-table tr:hover { background-color: #eef5ff; }
        .no-accounts td { text-align: center; color: #777; padding: 20px; font-style: italic; }
        .action-buttons { white-space: nowrap; text-align: center; } /* Prevent wrapping */
        .action-buttons button { margin: 0 3px; } /* Spacing between action buttons */
        .photo-album { text-align: center; }
        .photo-album img { margin: 2px; border: 1px solid #ddd; padding: 1px; background-color: #fff; vertical-align: middle; }

        /* --- Status Styles --- */
        .status-active { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .status-inactive { color: #6c757d; font-weight: bold; }

        /* --- Button Styles --- */
        .add-account-btn, .edit-btn, .status-btn, .view-address-btn, .view-contact-btn {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s, box-shadow 0.2s;
            vertical-align: middle;
            margin-left: 5px; /* Spacing for header buttons */
        }
        .add-account-btn { background-color: #5cb85c; color: white; }
        .add-account-btn:hover { background-color: #4cae4c; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .edit-btn { background-color: #f0ad4e; color: white; }
        .edit-btn:hover { background-color: #ec971f; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-btn { background-color: #5bc0de; color: white; }
        .status-btn:hover { background-color: #46b8da; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .view-address-btn, .view-contact-btn { background-color: #4a90e2; color: white; font-size: 12px; padding: 5px 10px; }
        .view-address-btn:hover, .view-contact-btn:hover { background-color: #357abf; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .modal-footer button {
            padding: 8px 18px; /* Adjusted padding */
            font-size: 14px;
            min-width: 100px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s, box-shadow 0.2s;
            border: none;
            font-weight: 500;
        }
        .save-btn { background-color: #4a90e2; color: white; }
        .save-btn:hover { background-color: #357abf; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .cancel-btn { background-color: #f1f1f1; color: #333; border: 1px solid #ccc; }
        .cancel-btn:hover { background-color: #e1e1e1; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        /* --- Status Modal Buttons --- */
        #statusModal .modal-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 20px; }
        #statusModal .modal-buttons button { flex-grow: 1; padding: 10px 15px; cursor: pointer; border: none; border-radius: 4px; transition: background-color 0.2s, box-shadow 0.2s; color: white; font-weight: 500; font-size: 14px; min-width: 100px; display: flex; align-items: center; justify-content: center; gap: 5px; }
        #statusModal .approve-btn { background-color: #28a745; } #statusModal .approve-btn:hover { background-color: #218838; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        #statusModal .reject-btn { background-color: #dc3545; } #statusModal .reject-btn:hover { background-color: #c82333; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        #statusModal .pending-btn { background-color: #ffc107; color: #333; } #statusModal .pending-btn:hover { background-color: #e0a800; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        #statusModal .inactive-btn { background-color: #6c757d; } #statusModal .inactive-btn:hover { background-color: #5a6268; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        #statusModal .single-button { text-align: center; margin-top: 10px; }
        #statusModal .single-button button { width: auto; min-width: 120px; justify-content: center; } /* Center cancel button */

        /* --- Confirmation Modal Buttons --- */
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes, .confirm-no { padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s, box-shadow 0.2s; border: none; font-size: 14px; }
        .confirm-yes { background-color: #4a90e2; color: white; }
        .confirm-yes:hover { background-color: #357abf; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .confirm-no { background-color: #f1f1f1; color: #333; border: 1px solid #ccc; }
        .confirm-no:hover { background-color: #e1e1e1; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* --- Header Styles --- */
        .accounts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .accounts-header h1 { margin: 0; font-size: 24px; }
        .filter-section { display: flex; align-items: center; gap: 8px; }
        .filter-section label { margin: 0; font-size: 14px; font-weight: 500; }
        .filter-section select { padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; margin-bottom: 0; /* Remove margin from select */ }

        /* --- Info Modal Specific Styles --- */
        #addressInfoModal, #contactInfoModal {
            /* display: none; */ /* Handled by .overlay */
            /* display: flex; */ /* REMOVED - JS handles */
        }
        .info-modal-header { background-color: #4a90e2; color: #fff; }
        .info-modal-header h2 i { margin-right: 10px; }
        .info-modal-close { color: #fff; }
        .info-section { margin-bottom: 25px; background-color: #f9f9f9; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .info-section-title { color: #4a90e2; border-bottom: 1px solid #e0e0e0; }
        .info-table th { background-color: #eef5ff; border: 1px solid #d1e1f9; color: #3a5d85; }
        .info-table td { border: 1px solid #d1e1f9; background-color: #fff; }
        .contact-item { border: 1px solid #d1e1f9; background-color: #fff; }
        .contact-icon { background-color: #eef5ff; color: #4a90e2; }

        /* --- Error Message --- */
        .error-message {
            color: #D8000C; /* Dark red */
            background-color: #FFD2D2; /* Light red background */
            padding: 10px 15px;
            border-radius: 4px;
            border: 1px solid #FFB8B8; /* Slightly darker red border */
            margin-top: 5px;
            margin-bottom: 15px;
            display: none; /* Hidden by default */
            font-size: 13px;
            line-height: 1.4;
            text-align: left;
        }
        .error-message i { margin-right: 5px; } /* Optional icon */

        /* --- Toastr Customization --- */
        #toast-container .toast-close-button { display: none; } /* Hide default close button */
        /* Add other toastr overrides if needed */

        /* --- Animations --- */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes modalPopIn {
            from { transform: scale(0.8) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        @keyframes zoomIn {
             from { transform: scale(0.5); opacity: 0; }
             to { transform: scale(1); opacity: 1; }
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
                    <option value="Pending" <?= ($status_filter ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Active" <?= ($status_filter ?? '') == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Rejected" <?= ($status_filter ?? '') == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Inactive" <?= ($status_filter ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <!-- Corrected button call -->
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message" style="display: block; margin: 15px;"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

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
                    <?php if (!empty($accounts_data)): ?>
                        <?php foreach ($accounts_data as $row): ?>
                            <tr>
                                <td><?= truncate($row['username']) ?></td>
                                <td>
                                    <button class="view-contact-btn"
                                        onclick='showContactInfo(<?= json_encode($row["email"] ?? "N/A") ?>, <?= json_encode($row["phone"] ?? "N/A") ?>)'>
                                        <i class="fas fa-address-card"></i> View
                                    </button>
                                </td>
                                <td><?= truncate($row['company']) ?></td>
                                <td>
                                    <button class="view-address-btn"
                                        onclick='showAddressInfo(<?= json_encode($row["company_address"] ?? "N/A") ?>, <?= json_encode($row["region"] ?? "N/A") ?>, <?= json_encode($row["city"] ?? "N/A") ?>, <?= json_encode($row["bill_to_address"] ?? "N/A") ?>)'>
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td class="photo-album">
                                    <?php
                                    $proofs = json_decode($row['business_proof'] ?? '[]', true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($proofs) && !empty($proofs)) {
                                        foreach ($proofs as $proof) {
                                            if (is_string($proof) && !empty($proof) && $proof[0] === '/') {
                                                // Ensure path starts with / and escape for HTML context
                                                echo '<img src="' . htmlspecialchars($proof, ENT_QUOTES, 'UTF-8') . '" alt="Business Proof" width="50" onclick="openModal(this)">';
                                            }
                                        }
                                    } // No else needed, just won't show images if invalid/empty
                                    ?>
                                </td>
                                <td class="<?= 'status-' . strtolower(htmlspecialchars($row['status'] ?? 'pending')) ?>"><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                                <td class="action-buttons">
                                    <?php
                                    // Pass the raw (but potentially null) proof string to htmlspecialchars
                                    $business_proof_json_for_js = htmlspecialchars($row['business_proof'] ?? '[]', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button class="edit-btn"
                                        onclick='openEditAccountForm(
                                            <?= intval($row["id"]) ?>,
                                            <?= json_encode($row["username"] ?? "") ?>,
                                            <?= json_encode($row["email"] ?? "") ?>,
                                            <?= json_encode($row["phone"] ?? "") ?>,
                                            <?= json_encode($row["region"] ?? "") ?>,
                                            <?= json_encode($row["city"] ?? "") ?>,
                                            <?= json_encode($row["company"] ?? "") ?>,
                                            <?= json_encode($row["company_address"] ?? "") ?>,
                                            <?= json_encode($row["bill_to_address"] ?? "") ?>,
                                            <?= $business_proof_json_for_js /* Pass HTML-encoded JSON */ ?>
                                        )'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= intval($row['id']) ?>, <?= htmlspecialchars(json_encode($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fas fa-exchange-alt"></i> Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-accounts"><?= $error_message ? 'Error loading data.' : 'No accounts found matching the filter.' ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Include all Modals HTML here (Add, Edit, Confirmations, Info, Status, Image Zoom) -->
    <!-- Contact Info Modal -->
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
                         <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                         <div class="contact-text">
                             <div class="contact-value" id="modalEmail"></div>
                             <div class="contact-label">Email Address</div>
                         </div>
                     </div>
                     <div class="contact-item">
                         <div class="contact-icon"><i class="fas fa-phone"></i></div>
                         <div class="contact-text">
                             <div class="contact-value" id="modalPhone"></div>
                             <div class="contact-label">Phone Number</div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>
     <!-- Address Info Modal -->
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
                         <tr><th>Ship to Address</th><td id="modalCompanyAddress"></td></tr>
                         <tr><th>Bill To Address</th><td id="modalBillToAddress"></td></tr>
                         <tr><th>Region</th><td id="modalRegion"></td></tr>
                         <tr><th>City</th><td id="modalCity"></td></tr>
                     </table>
                 </div>
             </div>
         </div>
     </div>
     <!-- Add Account Modal -->
     <div id="addAccountOverlay" class="overlay">
         <div class="form-modal-content">
             <div class="modal-header"><h2><i class="fas fa-user-plus"></i> Add New Account</h2></div>
             <form id="addAccountForm" method="POST" enctype="multipart/form-data" novalidate>
                 <input type="hidden" name="formType" value="add">
                 <div class="modal-body">
                     <div id="addAccountError" class="error-message"></div>
                     <div class="two-column-form">
                         <div class="form-column">
                             <label for="username">Username: <span class="required">*</span></label>
                             <input type="text" id="username" name="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Use letters, numbers, underscores. Max 15 chars.">
                             <label for="phone">Phone: <span class="required">*</span></label>
                             <input type="tel" id="phone" name="phone" required placeholder="e.g., 09123456789" maxlength="12" pattern="[0-9]+" title="Numbers only, 7-12 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                             <label for="password">Password:</label>
                             <input type="text" id="password" name="password" readonly class="auto-generated" placeholder="Auto-generated">
                             <div class="password-note">Auto: username + last 4 of phone</div>
                             <label for="email">Email: <span class="required">*</span></label>
                             <input type="email" id="email" name="email" required maxlength="40" placeholder="e.g., user@example.com">
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
                                 <textarea id="company_address" name="company_address" required maxlength="100" placeholder="e.g., 123 Main St, Brgy, City"></textarea>
                                 <label for="bill_to_address">Bill To Address: <span class="required">*</span></label>
                                 <textarea id="bill_to_address" name="bill_to_address" required maxlength="100" placeholder="e.g., 456 Billing St, Brgy, City"></textarea>
                             </div>
                             <label for="business_proof">Business Proof: <span class="required">*</span> <span class="file-info">(Max 3 images, 20MB/ea, JPG/PNG)</span></label>
                             <input type="file" id="business_proof" name="business_proof[]" required accept="image/jpeg, image/png" multiple>
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
     <!-- Add Confirmation Modal -->
     <div id="addConfirmationModal" class="confirmation-modal">
         <div class="confirmation-content">
             <div class="confirmation-title">Confirm Add Account</div>
             <div class="confirmation-message">Add this new client account?</div>
             <div class="confirmation-buttons">
                 <button class="confirm-no" onclick="closeAddConfirmation()">No</button>
                 <button class="confirm-yes" onclick="submitAddAccount()">Yes, Add</button>
             </div>
         </div>
     </div>
     <!-- Edit Account Modal -->
     <div id="editAccountOverlay" class="overlay">
         <div class="form-modal-content">
             <div class="modal-header"><h2><i class="fas fa-edit"></i> Edit Account</h2></div>
             <form id="editAccountForm" method="POST" enctype="multipart/form-data" novalidate>
                 <input type="hidden" name="formType" value="edit"><input type="hidden" id="edit-id" name="id">
                 <input type="hidden" id="existing-business-proof" name="existing_business_proof">
                 <input type="hidden" id="edit-original-region"><input type="hidden" id="edit-original-city">
                 <div class="modal-body">
                     <div id="editAccountError" class="error-message"></div>
                     <div class="two-column-form">
                         <div class="form-column">
                             <label for="edit-username">Username: <span class="required">*</span></label>
                             <input type="text" id="edit-username" name="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Use letters, numbers, underscores. Max 15 chars.">
                             <label for="edit-phone">Phone: <span class="required">*</span></label>
                             <input type="tel" id="edit-phone" name="phone" required placeholder="e.g., 09123456789" maxlength="12" pattern="[0-9]+" title="Numbers only, 7-12 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                             <div class="switch-container">
                                 <label class="switch"><input type="checkbox" id="edit-password-toggle"><span class="slider"></span></label>
                                 <label for="edit-password-toggle" class="switch-label">Set Manual Password</label> <!-- Label for checkbox -->
                             </div>
                             <div id="auto-password-container">
                                 <label for="edit-auto-password">Password:</label>
                                 <input type="text" id="edit-auto-password" readonly class="auto-generated" placeholder="Password unchanged">
                                 <div class="password-note">Leave toggle off to keep current password.</div>
                             </div>
                             <div id="manual-password-container" style="display: none;">
                                 <label for="edit-manual-password">New Password: <span class="required">*</span></label>
                                 <div class="password-container">
                                     <input type="password" id="edit-manual-password" name="manual_password" placeholder="Enter new password" minlength="6">
                                     <!-- Corrected onclick call -->
                                     <span class="toggle-password" onclick="togglePasswordVisibility('edit-manual-password')"><i class="fas fa-eye"></i></span>
                                 </div>
                                 <div class="password-note">Min 6 characters.</div>
                             </div>
                             <label for="edit-email">Email: <span class="required">*</span></label>
                             <input type="email" id="edit-email" name="email" required maxlength="40" placeholder="e.g., user@example.com">
                         </div>
                         <div class="form-column">
                             <label for="edit-region">Region: <span class="required">*</span></label>
                             <select id="edit-region" name="region" required><option value="">Select Region</option></select>
                             <label for="edit-city">City: <span class="required">*</span></label>
                             <select id="edit-city" name="city" required><option value="">Select City</option></select>
                             <label for="edit-company">Company Name: <span class="required">*</span></label>
                             <input type="text" id="edit-company" name="company" required maxlength="25" placeholder="e.g., Top Exchange Food Corp">
                         </div>
                         <div class="form-full-width">
                             <div class="address-group">
                                 <h3><i class="fas fa-building"></i> Company Address</h3>
                                 <label for="edit-company_address">Ship to Address: <span class="required">*</span></label>
                                 <textarea id="edit-company_address" name="company_address" required maxlength="100" placeholder="e.g., 123 Main St, Brgy, City"></textarea>
                                 <label for="edit-bill_to_address">Bill To Address: <span class="required">*</span></label>
                                 <textarea id="edit-bill_to_address" name="bill_to_address" required maxlength="100" placeholder="e.g., 456 Billing St, Brgy, City"></textarea>
                             </div>
                             <div id="edit-business-proof-container"></div> <!-- Display current images -->
                             <label for="edit-business_proof">Upload New Business Proof: <span class="file-info">(Optional - Replaces existing)</span></label>
                             <input type="file" id="edit-business_proof" name="business_proof[]" accept="image/jpeg, image/png" multiple title="Max 3 images, 20MB/ea. Replaces existing proofs.">
                         </div>
                     </div>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="cancel-btn" onclick="closeEditAccountForm()"><i class="fas fa-times"></i> Cancel</button>
                     <button type="button" class="save-btn" onclick="confirmEditAccount()"><i class="fas fa-save"></i> Save Changes</button>
                 </div>
             </form>
         </div>
     </div>
     <!-- Edit Confirmation Modal -->
     <div id="editConfirmationModal" class="confirmation-modal">
         <div class="confirmation-content">
             <div class="confirmation-title">Confirm Edit Account</div>
             <div class="confirmation-message">Save these changes?</div>
             <div class="confirmation-buttons">
                 <button class="confirm-no" onclick="closeEditConfirmation()">No</button>
                 <button class="confirm-yes" onclick="submitEditAccount()">Yes, Save</button>
             </div>
         </div>
     </div>
     <!-- Status Change Modal -->
     <div id="statusModal" class="overlay">
         <div class="overlay-content">
             <h2>Change Status</h2>
             <p id="statusMessage"></p>
             <div class="modal-buttons">
                 <button class="approve-btn" onclick="changeStatus('Active')"><i class="fas fa-check"></i> Active</button>
                 <button class="reject-btn" onclick="changeStatus('Rejected')"><i class="fas fa-times"></i> Reject</button>
                 <button class="pending-btn" onclick="changeStatus('Pending')"><i class="fas fa-hourglass-half"></i> Pending</button>
                 <button class="inactive-btn" onclick="changeStatus('Inactive')"><i class="fas fa-ban"></i> Archive</button>
             </div>
             <div class="modal-buttons single-button">
                 <button class="cancel-btn" onclick="closeStatusModal()"><i class="fas fa-times"></i> Cancel</button>
             </div>
         </div>
     </div>
     <!-- Image Zoom Modal -->
     <div id="myModal" class="modal">
         <span class="close" onclick="closeModal()">&times;</span>
         <img class="modal-content" id="img01">
         <div id="caption"></div>
     </div>


    <!-- SCRIPTS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- <script src="/js/toast.js"></script> Assuming this initializes toastr -->
    <script>
        // --- Global Vars ---
        let currentAccountId = 0;
        let regionCityMap = new Map();

        // --- Utility Functions ---
        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        function resetErrors() {
            $('.error-message').hide().text(''); // Hide all error messages using jQuery
        }
        function showToast(message, type = 'info') {
            if (typeof toastr !== 'undefined') {
                toastr.options = { closeButton: false, progressBar: true, positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1000, preventDuplicates: true };
                const toastrFunc = toastr[type] || toastr.info;
                toastrFunc(message);
            } else {
                console.log(`Toast [${type}]: ${message}`);
            }
        }

        // --- Modal Functions ---
        function openModal(imgElement) { /* Unchanged */
            const modal = document.getElementById("myModal");
            const modalImg = document.getElementById("img01");
            const captionText = document.getElementById("caption");
            if (modal && modalImg && captionText) {
                modal.style.display = "block";
                modalImg.src = imgElement.src;
                captionText.innerHTML = imgElement.alt || 'Business Proof';
            }
        }
        function closeModal() { /* Unchanged */
            const modal = document.getElementById("myModal");
            if (modal) modal.style.display = "none";
        }
        function showContactInfo(email, phone) { /* Unchanged */
            document.getElementById("modalEmail").textContent = email || 'N/A';
            document.getElementById("modalPhone").textContent = phone || 'N/A';
            $('#contactInfoModal').css('display', 'flex'); // Use jQuery for consistency
        }
        function closeContactInfoModal() { /* Unchanged */
             $('#contactInfoModal').hide();
        }
        function showAddressInfo(companyAddress, region, city, billToAddress) { /* Unchanged */
            document.getElementById("modalCompanyAddress").textContent = companyAddress || 'N/A';
            document.getElementById("modalBillToAddress").textContent = billToAddress || 'N/A';
            document.getElementById("modalRegion").textContent = region || 'N/A'; // Consider mapping code to name if needed
            document.getElementById("modalCity").textContent = city || 'N/A';
            $('#addressInfoModal').css('display', 'flex');
        }
        function closeAddressInfoModal() { /* Unchanged */
            $('#addressInfoModal').hide();
        }

        // --- Password Toggle ---
        // Now accepts the field ID as an argument
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = passwordField ? passwordField.parentElement.querySelector('.toggle-password i') : null;
            if (passwordField && icon) {
                const isPassword = passwordField.type === "password";
                passwordField.type = isPassword ? "text" : "password";
                icon.classList.toggle("fa-eye", !isPassword);
                icon.classList.toggle("fa-eye-slash", isPassword);
            }
        }

        // --- Region/City Loading ---
        async function loadPhilippinesRegions(selectElementId = 'region', selectedRegionCode = null) {
             const select = document.getElementById(selectElementId);
             if (!select) return;
             // console.log(`Loading regions for ${selectElementId}, selected: ${selectedRegionCode}`);
             try {
                 const response = await fetch('https://psgc.gitlab.io/api/regions/');
                 if (!response.ok) throw new Error(`HTTP ${response.status}`);
                 const regions = await response.json();
                 select.length = 1; // Clear existing options except placeholder
                 regions.sort((a, b) => a.name.localeCompare(b.name));
                 regions.forEach(region => select.add(new Option(region.name, region.code)));

                 // Pre-select and trigger change if needed (typically for edit form)
                 if (selectedRegionCode) {
                     select.value = selectedRegionCode;
                     // Ensure the value was actually set before dispatching change
                     if (select.value === selectedRegionCode) {
                         // Use setTimeout to ensure the event loop processes the value change before the event fires
                         setTimeout(() => select.dispatchEvent(new Event('change')), 0);
                     } else {
                         console.warn(`Region code ${selectedRegionCode} not found in ${selectElementId}.`);
                     }
                 }
             } catch (error) {
                 console.error(`Error loading regions for ${selectElementId}:`, error);
                 showToast('Could not load regions.', 'error');
             }
         }

         async function loadCities(regionCode, citySelectId, selectedCityName = null) {
             const citySelect = document.getElementById(citySelectId);
             if (!citySelect) return;
             // console.log(`Loading cities for ${citySelectId}, region: ${regionCode}, selected: ${selectedCityName}`);

             citySelect.disabled = true;
             citySelect.length = 1; // Clear existing options

             if (!regionCode) return;

             try {
                 let cities;
                 if (regionCityMap.has(regionCode)) {
                     cities = regionCityMap.get(regionCode);
                 } else {
                     const response = await fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/cities-municipalities/`);
                     if (!response.ok) throw new Error(`HTTP ${response.status}`);
                     cities = await response.json();
                     regionCityMap.set(regionCode, cities);
                 }

                 cities.sort((a, b) => a.name.localeCompare(b.name));
                 cities.forEach(city => citySelect.add(new Option(city.name, city.name))); // Use name as value

                 citySelect.disabled = false;
                 if (selectedCityName) {
                     citySelect.value = selectedCityName;
                     if (citySelect.value !== selectedCityName) {
                         console.warn(`City name "${selectedCityName}" not found in ${citySelectId} for region ${regionCode}.`);
                     }
                 }
             } catch (error) {
                 console.error(`Error loading cities for ${citySelectId}:`, error);
                 showToast('Could not load cities.', 'error');
                 citySelect.disabled = true;
             }
         }

        // --- Form Handling ---
        function openAddAccountForm() {
             const overlay = document.getElementById("addAccountOverlay");
             const form = document.getElementById("addAccountForm");
             if (overlay && form) {
                 form.reset();
                 resetErrors();
                 $('#region').val(''); // Reset selects
                 $('#city').empty().append('<option value="">Select City</option>').prop('disabled', true);
                 $(overlay).css('display', 'flex');
                 loadPhilippinesRegions('region'); // Reload regions for add form
             }
        }
        function closeAddAccountForm() { $('#addAccountOverlay').hide(); }
        function confirmAddAccount() { /* Unchanged, uses submitAddAccount */
            resetErrors();
            const form = document.getElementById('addAccountForm');
            if (!form || !form.checkValidity()) {
                form.reportValidity();
                showError('addAccountError', 'Please check the highlighted fields.');
                return;
            }
            $('#addConfirmationModal').css('display', 'flex');
        }
        function closeAddConfirmation() { $('#addConfirmationModal').hide(); }
        function submitAddAccount() { /* Unchanged */
            closeAddConfirmation();
            const form = document.getElementById('addAccountForm');
            const formData = new FormData(form);
            formData.append('ajax', true);
            // showLoadingIndicator(); // Optional
            $.ajax({
                url: window.location.pathname, type: 'POST', data: formData, contentType: false, processData: false, dataType: 'json',
                success: function(response) {
                    // hideLoadingIndicator(); // Optional
                    if (response && response.success) {
                        showToast('Account added successfully!', 'success');
                        closeAddAccountForm();
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showError('addAccountError', response?.message || 'Unknown error adding account.');
                        showToast(response?.message || 'Error adding account.', 'error');
                    }
                },
                error: function(xhr, status, error) { /* Unchanged */
                    // hideLoadingIndicator(); // Optional
                    console.error("Add Account AJAX Error:", status, error, xhr.responseText);
                    showError('addAccountError', 'Server error. Please try again.');
                    showToast('Server error occurred.', 'error');
                }
            });
        }

        // --- EDIT ACCOUNT ---
        function openEditAccountForm(id, username, email, phone, regionCode, cityName, company, companyAddress, billToAddress, businessProofEncoded) {
             const overlay = document.getElementById("editAccountOverlay");
             const form = document.getElementById("editAccountForm");
             if (!overlay || !form) return;

             form.reset();
             resetErrors();
             console.log("Opening edit form for ID:", id, "Region:", regionCode, "City:", cityName); // Debug log

             // Populate basic fields
             $('#edit-id').val(id);
             $('#edit-username').val(username);
             $('#edit-email').val(email);
             $('#edit-phone').val(phone);
             $('#edit-company').val(company);
             $('#edit-company_address').val(companyAddress);
             $('#edit-bill_to_address').val(billToAddress);

             // Reset password fields
             $('#edit-password-toggle').prop('checked', false).trigger('change');
             $('#edit-manual-password').val('');

            // --- Handle Business Proof ---
            const proofContainer = $('#edit-business-proof-container');
            const existingProofInput = $('#existing-business-proof');
            proofContainer.empty().append('<h4>Current Business Proof:</h4>'); // Clear and add header
            existingProofInput.val('[]'); // Default

            let original_json = '[]';
            if (businessProofEncoded) {
                try {
                    const tempTextArea = document.createElement('textarea');
                    tempTextArea.innerHTML = businessProofEncoded; // Decode HTML entities
                    original_json = tempTextArea.value;
                    JSON.parse(original_json); // Validate JSON
                    existingProofInput.val(original_json); // Store valid, original JSON
                    console.log("Storing existing proofs JSON:", original_json); // Debug log
                } catch (e) {
                    console.error("Error decoding/parsing existing proofs:", e, "Input:", businessProofEncoded);
                    proofContainer.append('<p style="color: red;">Error loading current proofs.</p>');
                    original_json = '[]'; // Fallback
                }
            }

            // Display current images from the validated JSON
            try {
                const proofs = JSON.parse(original_json);
                if (Array.isArray(proofs) && proofs.length > 0) {
                    proofs.forEach(proof => {
                        if (typeof proof === 'string' && proof.startsWith('/')) {
                            // Ensure src is correctly escaped for HTML attribute
                            const escapedProof = $('<div>').text(proof).html(); // Basic escaping
                            $('<img>', {
                                src: escapedProof, // Use the path directly
                                alt: 'Business Proof', width: 50,
                                css: { margin: '3px', border: '1px solid #ccc', padding: '1px', cursor: 'pointer', backgroundColor: '#fff', borderRadius: '3px' },
                                click: function() { openModal(this); }
                            }).appendTo(proofContainer);
                        } else {
                             console.warn('Skipping invalid proof item for display:', proof);
                        }
                    });
                } else {
                    proofContainer.append('<p>No business proof images on record.</p>');
                }
            } catch (e) {
                console.error("Error displaying proofs from JSON:", e, "JSON:", original_json);
                proofContainer.append('<p style="color: red;">Error displaying proofs.</p>');
            }

             // Clear the file input
             $('#edit-business_proof').val(null);

             // --- Handle Region/City ---
             // Load regions, then pre-select and trigger city loading via the callback mechanism in loadPhilippinesRegions
             loadPhilippinesRegions('edit-region', regionCode).then(() => {
                 // The change event triggered inside loadPhilippinesRegions should handle city loading
                 // We might still need to explicitly set the city value if the event doesn't fire reliably or fast enough
                 if (cityName && $('#edit-region').val() === regionCode) {
                     // Optionally force load cities again if needed, but the change event should cover it
                     // loadCities(regionCode, 'edit-city', cityName);
                     // console.log("Region loaded, city should load/select:", cityName);
                 }
             });

             $(overlay).css('display', 'flex'); // Show modal
        }

        function closeEditAccountForm() { $('#editAccountOverlay').hide(); }
        function confirmEditAccount() { /* Unchanged, uses submitEditAccount */
            resetErrors();
            const form = document.getElementById('editAccountForm');
            const passwordToggle = document.getElementById('edit-password-toggle');
            const manualPasswordInput = document.getElementById('edit-manual-password');
            if (passwordToggle && manualPasswordInput && passwordToggle.checked && manualPasswordInput.value.length > 0 && manualPasswordInput.value.length < 6) {
                 showError('editAccountError', 'Manual password must be at least 6 characters.');
                 manualPasswordInput.focus();
                 return;
            }
            if (!form || !form.checkValidity()) {
                form.reportValidity();
                showError('editAccountError', 'Please check the highlighted fields.');
                return;
            }
            $('#editConfirmationModal').css('display', 'flex');
        }
        function closeEditConfirmation() { $('#editConfirmationModal').hide(); }
        function submitEditAccount() { /* Unchanged */
            closeEditConfirmation();
            const form = document.getElementById('editAccountForm');
            const formData = new FormData(form);
            formData.append('ajax', true);
            // showLoadingIndicator(); // Optional
            $.ajax({
                url: window.location.pathname, type: 'POST', data: formData, contentType: false, processData: false, dataType: 'json',
                success: function(response) {
                    // hideLoadingIndicator(); // Optional
                    if (response && response.success) {
                        showToast('Account updated successfully!', 'success');
                        closeEditAccountForm();
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showError('editAccountError', response?.message || 'Unknown error updating account.');
                        showToast(response?.message || 'Error updating account.', 'error');
                    }
                },
                error: function(xhr, status, error) { /* Unchanged */
                    // hideLoadingIndicator(); // Optional
                    console.error("Edit Account AJAX Error:", status, error, xhr.responseText);
                    showError('editAccountError', 'Server error. Please try again.');
                    showToast('Server error occurred.', 'error');
                }
            });
        }

        // --- Status Change ---
        function openStatusModal(id, username, email) { /* Unchanged */
            const modal = document.getElementById("statusModal");
            const messageEl = document.getElementById("statusMessage");
            if (modal && messageEl) {
                currentAccountId = id;
                messageEl.textContent = `Change status for ${username} (${email})`;
                $(modal).css('display', 'flex');
            }
        }
        function closeStatusModal() { /* Unchanged */
            $('#statusModal').hide();
            currentAccountId = 0;
        }
        function changeStatus(newStatus) { /* Unchanged */
            if (!currentAccountId) return;
            // showLoadingIndicator(); // Optional
            $.ajax({
                url: window.location.pathname, type: 'POST', data: { ajax: true, formType: 'status', id: currentAccountId, status: newStatus }, dataType: 'json',
                success: function(response) {
                    // hideLoadingIndicator(); // Optional
                    if (response && response.success) {
                        showToast(`Status changed to ${newStatus}!`, 'success');
                        closeStatusModal();
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(response?.message || 'Error changing status.', 'error');
                    }
                },
                error: function(xhr, status, error) { /* Unchanged */
                    // hideLoadingIndicator(); // Optional
                    console.error("Change Status AJAX Error:", status, error, xhr.responseText);
                    showToast('Server error occurred.', 'error');
                    closeStatusModal();
                }
            });
        }

        // --- Table Filtering ---
        function filterByStatus() { /* Unchanged */
            var status = document.getElementById("statusFilter").value;
            const url = new URL(window.location.href);
            if (status) { url.searchParams.set('status', status); }
            else { url.searchParams.delete('status'); }
            window.location.href = url.toString();
        }

        // --- Initial Setup ---
        $(document).ready(function() { // Use jQuery's ready for consistency
             // Initial load of regions for Add form (Edit form regions loaded in openEditAccountForm)
             loadPhilippinesRegions('region');

             // Setup region change listeners
             $('#region').on('change', function() { loadCities(this.value, 'city'); });
             $('#edit-region').on('change', function() { loadCities(this.value, 'edit-city'); });

             // Password toggle setup
             const passwordToggle = document.getElementById('edit-password-toggle');
             const autoPassContainer = document.getElementById('auto-password-container');
             const manualPassContainer = document.getElementById('manual-password-container');
             const manualPassInput = document.getElementById('edit-manual-password');
             if (passwordToggle && autoPassContainer && manualPassContainer && manualPassInput) {
                 $(passwordToggle).on('change', function() {
                     const isManual = this.checked;
                     $(autoPassContainer).toggle(!isManual);
                     $(manualPassContainer).toggle(isManual);
                     manualPassInput.required = isManual;
                     if (!isManual) manualPassInput.value = '';
                 }).trigger('change'); // Initial trigger to set correct state
             }

             // Global click listener for info modals (can refine if needed)
             $(window).on('click', function(event) {
                 if ($(event.target).is('#addressInfoModal')) closeAddressInfoModal();
                 if ($(event.target).is('#contactInfoModal')) closeContactInfoModal();
                 if ($(event.target).is('#myModal')) closeModal();
             });
        });

    </script>

</body>
</html>