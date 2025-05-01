<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Clients');

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production (or 1 for debugging)
// ini_set('log_errors', 1); // Optional: Log errors to a file in production
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Specify error log file

function validateUnique($conn, $username, $email, $id = null) {
    $result = [
        'exists' => false,
        'field' => null,
        'message' => ''
    ];

    // First check username
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE username = ?";
    $params = [$username];
    $types = "s";
    if ($id) {
        $query .= " AND id != ?";
        $params[] = $id;
        $types .= "i";
    }
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
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
    $params = [$email];
    $types = "s";
    if ($id) {
        $query .= " AND id != ?";
        $params[] = $id;
        $types .= "i";
    }
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
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

// --- Helper function to generate a safe filename ---
function generateSafeFilename($username, $originalFilename) {
    $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    // Create a safer base name: remove special chars, limit length
    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalFilename, PATHINFO_FILENAME));
    $safeBaseName = substr($safeBaseName, 0, 50); // Limit length
    // Add a unique part and timestamp to prevent collisions
    $uniquePart = uniqid('', true); // More unique ID
    return $username . '_' . $safeBaseName . '_' . $uniquePart . '.' . $extension;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // --- ADD ACCOUNT LOGIC ---
    if ($_POST['formType'] == 'add') {
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''); // Sanitize phone: numbers only

        // Validate unique username/email
        $uniqueCheck = validateUnique($conn, $username, $email);
        if ($uniqueCheck['exists']) {
            echo json_encode(['success' => false, 'message' => $uniqueCheck['message']]);
            exit;
        }
        
        // Basic validation for required fields
        if (empty($username) || empty($email) || empty($phone) || empty($_POST['region']) || empty($_POST['city']) || empty($_POST['company']) || empty($_POST['company_address']) || empty($_POST['bill_to_address']) || !isset($_FILES['business_proof']) || empty($_FILES['business_proof']['name'][0])) {
             echo json_encode(['success' => false, 'message' => 'Please fill in all required fields and upload business proof.']);
             exit;
        }

        // Auto-generate password
        $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad(substr($phone, 0, 4), 4, '0');
        $autoPassword = $username . $last4digits;
        $password = password_hash($autoPassword, PASSWORD_DEFAULT);
        
        $region = $_POST['region'];
        $city = $_POST['city'];
        $company = $_POST['company'] ?? ''; 
        $company_address = $_POST['company_address'];
        $bill_to_address = $_POST['bill_to_address'] ?? ''; 
        $business_proof_paths = []; // Store URL paths

        // --- Define Base Directory Paths ---
        // Filesystem base path for uploads (ensure trailing slash)
        $uploadBaseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/admin/uploads/';
        // URL base path for accessing uploads (ensure trailing slash)
        $uploadBaseUrl = '/admin/uploads/';

        $userUploadDir = $uploadBaseDir . $username . '/'; // Filesystem path for this user
        $userUploadUrl = $uploadBaseUrl . $username . '/'; // URL path for this user

        // Create user directory if it doesn't exist
        if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0777, true) && !is_dir($userUploadDir)) {
             echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Check permissions.']);
             exit;
        }

        // --- Process Uploaded Files ---
        if (isset($_FILES['business_proof'])) {
            if (count($_FILES['business_proof']['name']) > 3) {
                echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']);
                exit;
            }
            
            foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                // Check for upload errors
                 if ($_FILES['business_proof']['error'][$key] !== UPLOAD_ERR_OK) {
                    // Optionally provide more specific error messages based on the error code
                    if ($_FILES['business_proof']['error'][$key] != UPLOAD_ERR_NO_FILE) { // Ignore "no file" error if multiple input used
                       echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . $_FILES['business_proof']['name'][$key]]);
                       exit;
                    }
                    continue; // Skip if no file was uploaded for this specific input in the array
                 }

                $originalFilename = basename($_FILES['business_proof']['name'][$key]);
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];
                
                // Validation
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 20 * 1024 * 1024; // 20MB
                if (!in_array($file_type, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type for ' . $originalFilename . '. Only JPG and PNG allowed.']);
                    exit;
                }
                if ($file_size > $max_size) {
                     echo json_encode(['success' => false, 'message' => 'File ' . $originalFilename . ' exceeds maximum size of 20MB.']);
                     exit;
                }

                // Generate a safe and unique filename
                $safeFilename = generateSafeFilename($username, $originalFilename);
                $filesystemTargetPath = $userUploadDir . $safeFilename;
                $urlPath = $userUploadUrl . $safeFilename; // Path to store in DB

                // Move the uploaded file
                if (move_uploaded_file($tmp_name, $filesystemTargetPath)) {
                    $business_proof_paths[] = $urlPath; // Store the URL path
                } else {
                    // Provide more context on failure if possible
                    $error = error_get_last();
                    $errorMessage = 'Failed to move uploaded file: ' . $originalFilename;
                    if ($error) {
                        $errorMessage .= ' - PHP Error: ' . $error['message'];
                    }
                     echo json_encode(['success' => false, 'message' => $errorMessage]);
                     exit;
                }
            }
        }
        
        // Check if any proofs were actually uploaded if the field was required
         if (empty($business_proof_paths)) {
             echo json_encode(['success' => false, 'message' => 'Business proof is required. Please upload at least one image.']);
             exit;
         }


        $business_proof_json = json_encode($business_proof_paths); // Encode the array of URL paths

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, bill_to_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        // Ensure correct types: s=string
        $stmt->bind_param("ssssssssss", $username, $password, $email, $phone, $region, $city, $company, $company_address, $bill_to_address, $business_proof_json);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
            // Provide DB error if possible (for debugging, disable in production)
             echo json_encode(['success' => false, 'message' => 'Database error: Failed to add account. ' . $stmt->error]);
            // echo json_encode(['success' => false, 'message' => 'Failed to add account.']); // Production version
        }

        $stmt->close();
        exit;
    }

    // --- EDIT ACCOUNT LOGIC ---
    if ($_POST['formType'] == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $email = $_POST['email'];
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''); // Sanitize phone

        // Basic validation
         if (empty($id) || empty($username) || empty($email) || empty($phone) || empty($_POST['region']) || empty($_POST['city']) || empty($_POST['company']) || empty($_POST['company_address']) || empty($_POST['bill_to_address'])) {
             echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
             exit;
         }

        // Validate unique username/email (excluding current user)
        $uniqueCheck = validateUnique($conn, $username, $email, $id);
        if ($uniqueCheck['exists']) {
            echo json_encode(['success' => false, 'message' => $uniqueCheck['message']]);
            exit;
        }
        
        // Handle password update
        $passwordSqlPart = "";
        $passwordParam = null;
        $passwordType = "";
        if (!empty($_POST['manual_password'])) {
             if (strlen($_POST['manual_password']) < 6) {
                 echo json_encode(['success' => false, 'message' => 'Manual password must be at least 6 characters long.']);
                 exit;
             }
            $password = password_hash($_POST['manual_password'], PASSWORD_DEFAULT);
            $passwordSqlPart = ", password = ?";
            $passwordParam = $password;
            $passwordType = "s";
        } 
        // Only auto-generate if NOT using manual password AND phone/username might have changed
        // (It's generally better NOT to auto-update password on edit unless explicitly requested or needed for recovery)
        // else if (!empty($phone)) { 
        //     $last4digits = (strlen($phone) >= 4) ? substr($phone, -4) : str_pad(substr($phone, 0, 4), 4, '0');
        //     $autoPassword = $username . $last4digits;
        //     $password = password_hash($autoPassword, PASSWORD_DEFAULT);
        //     $passwordSqlPart = ", password = ?";
        //     $passwordParam = $password;
        //     $passwordType = "s";
        // }
        
        $region = $_POST['region'];
        $city = $_POST['city'];
        $company = $_POST['company'] ?? '';
        $company_address = $_POST['company_address'];
        $bill_to_address = $_POST['bill_to_address'] ?? '';
        
        // --- Define Base Directory Paths (consistent with add) ---
        $uploadBaseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/admin/uploads/';
        $uploadBaseUrl = '/admin/uploads/';
        $userUploadDir = $uploadBaseDir . $username . '/'; // Use NEW username for consistency if changed
        $userUploadUrl = $uploadBaseUrl . $username . '/';

        // --- Handle Business Proof Update ---
        $new_business_proof_paths = []; // Store URL paths of NEWLY uploaded files
        $existing_business_proof_json = '[]'; // Default if nothing existing passed

         // Validate and get existing proofs JSON from POST
         if (isset($_POST['existing_business_proof'])) {
             // Attempt to decode to ensure it's valid JSON before using it
             $decoded_existing = json_decode($_POST['existing_business_proof']);
             if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_existing)) {
                 // If valid JSON array, use the original string from POST
                 $existing_business_proof_json = $_POST['existing_business_proof'];
             } else {
                  // Log potentially corrupted data
                  error_log("Invalid existing_business_proof JSON received for ID $id: " . $_POST['existing_business_proof']);
                  // Keep default '[]'
             }
         }
         $final_business_proof_paths = json_decode($existing_business_proof_json, true) ?: []; // Start with existing paths


        // Create user directory if it doesn't exist (might be needed if username changed)
        if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0777, true) && !is_dir($userUploadDir)) {
             echo json_encode(['success' => false, 'message' => 'Failed to create upload directory for edit. Check permissions.']);
             exit;
        }

        // --- Process Newly Uploaded Files (if any) ---
        if (isset($_FILES['business_proof']) && !empty($_FILES['business_proof']['name'][0])) {
             // If new files are uploaded, they REPLACE the old ones.
             $final_business_proof_paths = []; // Reset paths if new files are provided

            if (count($_FILES['business_proof']['name']) > 3) {
                echo json_encode(['success' => false, 'message' => 'Maximum of 3 new photos allowed.']);
                exit;
            }

            foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                 if ($_FILES['business_proof']['error'][$key] !== UPLOAD_ERR_OK) {
                     if ($_FILES['business_proof']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                         echo json_encode(['success' => false, 'message' => 'Error uploading file: ' . $_FILES['business_proof']['name'][$key]]);
                         exit;
                     }
                     continue; 
                 }

                $originalFilename = basename($_FILES['business_proof']['name'][$key]);
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];
                
                // Validation (same as add)
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 20 * 1024 * 1024; 
                if (!in_array($file_type, $allowed_types)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type for ' . $originalFilename . '. Only JPG and PNG allowed.']);
                    exit;
                }
                 if ($file_size > $max_size) {
                     echo json_encode(['success' => false, 'message' => 'File ' . $originalFilename . ' exceeds maximum size of 20MB.']);
                     exit;
                 }

                $safeFilename = generateSafeFilename($username, $originalFilename);
                $filesystemTargetPath = $userUploadDir . $safeFilename;
                $urlPath = $userUploadUrl . $safeFilename; 

                if (move_uploaded_file($tmp_name, $filesystemTargetPath)) {
                    $final_business_proof_paths[] = $urlPath; // Add the URL path of the new file
                } else {
                    $error = error_get_last();
                    $errorMessage = 'Failed to move newly uploaded file: ' . $originalFilename;
                    if ($error) { $errorMessage .= ' - PHP Error: ' . $error['message']; }
                     echo json_encode(['success' => false, 'message' => $errorMessage]);
                     exit;
                }
            }
             // Check if any files were successfully uploaded if new files were intended
             if (empty($final_business_proof_paths)) {
                 echo json_encode(['success' => false, 'message' => 'Failed to process any of the newly uploaded business proofs.']);
                 exit;
             }
        }
        // If no new files uploaded, $final_business_proof_paths remains the decoded existing paths

        // Convert the final array of paths back to JSON for DB storage
        $business_proof_json_to_save = json_encode($final_business_proof_paths);


        // --- Prepare and Execute Update Query ---
        $sql = "UPDATE clients_accounts SET username = ?, email = ?, phone = ?, region = ?, city = ?, company = ?, company_address = ?, bill_to_address = ?, business_proof = ? $passwordSqlPart WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        // Combine types and params dynamically
        $types = "sssssssss" . $passwordType . "i"; // 9 strings, optional password string, 1 integer ID
        $params = [
            $username, $email, $phone, $region, $city, 
            $company, $company_address, $bill_to_address, 
            $business_proof_json_to_save
        ];
        if ($passwordParam !== null) {
            $params[] = $passwordParam;
        }
        $params[] = $id;

        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
             echo json_encode(['success' => false, 'message' => 'Database error: Failed to update account. ' . $stmt->error]);
            // echo json_encode(['success' => false, 'message' => 'Failed to update account.']); // Production
        }

        $stmt->close();
        exit;
    }

    // --- STATUS CHANGE LOGIC ---
    if ($_POST['formType'] == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        // Validate status value if needed
        $allowed_statuses = ['Active', 'Rejected', 'Pending', 'Inactive'];
        if (!in_array($status, $allowed_statuses)) {
             echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
             exit;
        }

        $stmt = $conn->prepare("UPDATE clients_accounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'reload' => true]);
        } else {
             echo json_encode(['success' => false, 'message' => 'Database error: Failed to change status. ' . $stmt->error]);
            // echo json_encode(['success' => false, 'message' => 'Failed to change status.']); // Production
        }

        $stmt->close();
        exit;
    }
}

// --- GET ACCOUNTS FOR DISPLAY ---
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT id, username, email, phone, region, city, company, company_address, bill_to_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'";
if (!empty($status_filter)) {
    // Validate status filter if needed
    $allowed_filters = ['Pending', 'Active', 'Rejected', 'Inactive'];
     if (in_array($status_filter, $allowed_filters)) {
        $sql .= " AND status = ?";
     } else {
         $status_filter = ''; // Ignore invalid filter
     }
}
$sql .= " ORDER BY 
    CASE 
        WHEN status = 'Pending' THEN 1
        WHEN status = 'Active' THEN 2
        WHEN status = 'Rejected' THEN 3
        WHEN status = 'Inactive' THEN 4
        ELSE 5 
    END, created_at DESC";
    
$stmt = $conn->prepare($sql);
if (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();

function truncate($text, $max = 15) {
     if ($text === null) return 'N/A';
    return (mb_strlen($text) > $max) ? mb_substr($text, 0, $max) . '...' : $text;
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
        /* --- Keep all existing CSS from your original file --- */
        #myModal { display: none; position: fixed; z-index: 9999; padding-top: 100px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; max-width: 80%; max-height: 80%; }
        #caption { margin: auto; display: block; width: 80%; max-width: 700px; text-align: center; color: #ccc; padding: 10px 0; height: 150px; }
        .close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
        .close:hover, .close:focus { color: #bbb; text-decoration: none; }
        .photo-album img { cursor: pointer; transition: all 0.3s; margin: 2px; border: 1px solid #ddd; padding: 1px; background-color: #fff;}
        .photo-album img:hover { opacity: 0.8; transform: scale(1.05); border-color: #aaa; }
        .file-info { font-size: 0.9em; color: #666; font-style: italic; }
        .two-column-form { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-column { display: flex; flex-direction: column; }
        .form-divider { width: 1px; background-color: #ddd; margin: 0 auto; }
        .form-full-width { grid-column: 1 / span 2; }
        .required { color: #ff0000; font-weight: bold; }
        .optional { color: #666; font-style: italic; font-size: 0.9em; }
        .overlay-content { max-width: 800px; width: 90%; max-height: 95vh; display: flex; flex-direction: column; background-color: #fff; border-radius: 8px; overflow: hidden; margin: auto; } /* Adjusted for status modal */
        .two-column-form input, .two-column-form textarea, .two-column-form select { width: 100%; box-sizing: border-box; /* Include padding/border in width */ }
        textarea#company_address, textarea#edit-company_address, textarea#bill_to_address, textarea#edit-bill_to_address { height: 60px; padding: 8px; font-size: 14px; resize: vertical; min-height: 60px; }
        input, textarea, select { border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; transition: border-color 0.3s; outline: none; font-size: 14px; margin-bottom: 10px; /* Add some spacing */ }
        input:focus, textarea:focus, select:focus { border-color: #4a90fe; box-shadow: 0 0 5px rgba(77, 144, 254, 0.5); }
        input::placeholder, textarea::placeholder { color: #aaa; padding: 4px; font-style: italic; }
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
        .contact-icon { width: 45px; height: 45px; background-color: #eef5ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4a90e2; font-size: 18px; margin-right: 15px; }
        .contact-text { flex: 1; }
        .contact-value { font-weight: bold; color: #333; font-size: 14px; word-break: break-all; }
        .contact-label { font-size: 13px; color: #777; display: block; margin-top: 5px; }
        .overlay { display: none; /* Hidden by default */ position: fixed; width: 100%; height: 100%; top: 0; left: 0; background-color: rgba(0, 0, 0, 0.7); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .address-group { border: 1px solid #eee; padding: 12px; border-radius: 8px; margin-bottom: 15px; background-color: #fafafa; }
        .address-group h3 { margin-top: 0; color: #4a90e2; font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
        .modal-header { background-color: #ffffff; padding-top: 24px; padding: 12px; text-align: center; border-radius: 8px 8px 0 0; border-bottom: 1px solid rgb(68, 68, 68); position: sticky; top: 0; z-index: 10; }
        .modal-header h2 { margin: 0; padding: 0; font-size: 18px; }
        .modal-footer { background-color: #ffffff; padding: 12px; border-top: 1px solid rgb(68, 68, 68); text-align: center; border-radius: 0 0 8px 8px; position: sticky; bottom: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; margin-top: auto; }
        .modal-body { padding: 15px; overflow-y: auto; max-height: calc(85vh - 110px); /* Adjust based on header/footer height */ height: auto; }
        .form-modal-content { display: flex; flex-direction: column; max-height: 85vh; height: auto; width: 80%; max-width: 650px; background-color: #fff; border-radius: 8px; overflow: hidden; margin: auto; position: relative; /* Changed from absolute */ top: auto; left: auto; transform: none; /* Reset transform */ }
        label { display: block; /* Ensure labels take full width */ font-size: 14px; margin-bottom: 4px; font-weight: 500; }
        .error-message { color: #ff3333; background-color: rgba(255, 51, 51, 0.1); padding: 10px; border-radius: 4px; margin-top: 5px; margin-bottom: 10px; display: none; font-size: 13px; }
        .modal-footer button { padding: 8px 16px; font-size: 14px; min-width: 100px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; border: none; margin: 0; }
        .save-btn { background-color: #4a90e2; color: white; }
        .save-btn:hover { background-color: #357abf; }
        .cancel-btn { background-color: #f1f1f1; color: #333; }
        .cancel-btn:hover { background-color: #e1e1e1; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .status-inactive { color: #6c757d; font-weight: bold; }
        .password-note { font-size: 12px; color: #666; margin-top: 4px; font-style: italic; }
        .auto-generated { background-color: #f8f8f8; color: #888; cursor: not-allowed; }
        .password-container { position: relative; width: 100%; margin-bottom: 10px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; padding: 5px; }
        .switch-container { display: flex; align-items: center; margin-top: 8px; margin-bottom: 12px; }
        .switch-label { font-size: 13px; margin-left: 8px; color: #555; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #4a90e2; }
        input:focus + .slider { box-shadow: 0 0 1px #4a90e2; }
        input:checked + .slider:before { transform: translateX(26px); }
        .confirmation-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .confirmation-content { background-color: #fefefe; padding: 25px; border-radius: 8px; width: 350px; max-width: 90%; text-align: center; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalPopIn 0.3s; }
        @keyframes modalPopIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
        .confirmation-title { font-size: 20px; margin-bottom: 15px; color: #333; font-weight: 600; }
        .confirmation-message { margin-bottom: 25px; color: #555; font-size: 14px; line-height: 1.5; }
        .confirmation-buttons { display: flex; justify-content: center; gap: 15px; }
        .confirm-yes, .confirm-no { padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background-color 0.2s, box-shadow 0.2s; border: none; font-size: 14px; }
        .confirm-yes { background-color: #4a90e2; color: white; }
        .confirm-yes:hover { background-color: #357abf; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .confirm-no { background-color: #f1f1f1; color: #333; }
        .confirm-no:hover { background-color: #e1e1e1; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        #toast-container .toast-close-button { display: none; }
        #edit-business-proof-container img { margin: 5px; border: 1px solid #ccc; padding: 2px; max-width: 60px; height: auto; background: #fff; } /* Style for current images */
        #edit-business-proof-container h4 { margin-bottom: 5px; font-size: 14px; }
        #edit-business-proof-container p { font-size: 13px; color: #888; }
        /* Status Modal Specific Styles */
        #statusModal .overlay-content { padding: 20px; }
        #statusModal h2 { text-align: center; margin-bottom: 15px; }
        #statusModal p { text-align: center; margin-bottom: 20px; color: #555; }
        #statusModal .modal-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 15px; }
        #statusModal .modal-buttons button { flex-grow: 1; padding: 10px 15px; cursor: pointer; border: none; border-radius: 4px; transition: background-color 0.3s; color: white; font-weight: 500; }
        #statusModal .approve-btn { background-color: #28a745; } #statusModal .approve-btn:hover { background-color: #218838; }
        #statusModal .reject-btn { background-color: #dc3545; } #statusModal .reject-btn:hover { background-color: #c82333; }
        #statusModal .pending-btn { background-color: #ffc107; color: #333; } #statusModal .pending-btn:hover { background-color: #e0a800; }
        #statusModal .inactive-btn { background-color: #6c757d; } #statusModal .inactive-btn:hover { background-color: #5a6268; }
        #statusModal .single-button { text-align: center; margin-top: 10px; }
        #statusModal .single-button button { width: auto; min-width: 120px; } /* Adjust cancel button width */
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
                                            <?= json_encode($row["email"] ?? "N/A") ?>,
                                            <?= json_encode($row["phone"] ?? "N/A") ?>
                                        )'>
                                        <i class="fas fa-address-card"></i> View
                                    </button>
                                </td>
                                <td><?= htmlspecialchars(truncate($row['company'] ?? 'N/A')) ?></td>
                                <td>
                                    <button class="view-address-btn" 
                                        onclick='showAddressInfo(
                                            <?= json_encode($row["company_address"] ?? "N/A") ?>,
                                            <?= json_encode($row["region"] ?? "N/A") ?>,
                                            <?= json_encode($row["city"] ?? "N/A") ?>,
                                            <?= json_encode($row["bill_to_address"] ?? "N/A") ?>
                                        )'>
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td class="photo-album">
                                    <?php
                                    // Attempt to decode the business proof JSON
                                    $proofs = json_decode($row['business_proof'] ?? '[]', true); 
                                    // Check if decoding was successful and resulted in an array
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($proofs) && !empty($proofs)) {
                                        foreach ($proofs as $proof) {
                                            // Basic validation: ensure $proof is a non-empty string starting with '/'
                                            if (is_string($proof) && !empty($proof) && $proof[0] === '/') {
                                                echo '<img src="' . htmlspecialchars($proof, ENT_QUOTES, 'UTF-8') . '" alt="Business Proof" width="50" onclick="openModal(this)">';
                                            } else {
                                                 // Optionally log or display a placeholder for invalid paths
                                                 // error_log("Invalid proof path found in DB for ID {$row['id']}: " . print_r($proof, true));
                                            }
                                        }
                                    } else {
                                        // echo '<small>N/A</small>'; // Optionally show N/A if no valid proofs
                                        if (json_last_error() !== JSON_ERROR_NONE && !empty($row['business_proof'])) {
                                             error_log("Failed to decode business_proof JSON for ID {$row['id']}: " . $row['business_proof']);
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="<?= 'status-' . strtolower(htmlspecialchars($row['status'] ?? 'pending')) ?>"><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                                <td class="action-buttons">
                                    <?php
                                    // Encode the business_proof JSON string from DB for safe JS embedding
                                    // Default to '[]' if null or empty
                                    $business_proof_json_for_js = htmlspecialchars($row['business_proof'] ?? '[]', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button class="edit-btn"
                                        onclick='openEditAccountForm(
                                            <?= $row["id"] ?>,
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
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>')">
                                        <i class="fas fa-exchange-alt"></i> Status
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-accounts">No accounts found matching the filter.</td>
                        </tr>
                    <?php endif; ?>
                     <?php $stmt->close(); $conn->close(); /* Close connection after use */ ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modals (Contact, Address, Add, Edit, Confirmations, Status, Image Zoom) -->
    <!-- Keep all modal HTML structures as they were in your original file -->

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
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            </div>
            <form id="addAccountForm" method="POST" class="account-form" enctype="multipart/form-data" novalidate>
                 <input type="hidden" name="formType" value="add">
                 <div class="modal-body">
                     <div id="addAccountError" class="error-message"></div> <!-- Error message display -->
                     <div class="two-column-form">
                         <div class="form-column">
                             <label for="username">Username: <span class="required">*</span></label>
                             <input type="text" id="username" name="username" autocomplete="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Username can only contain letters, numbers, and underscores. Max 15 characters.">
                             
                             <label for="phone">Phone Number: <span class="required">*</span></label>
                             <input type="tel" id="phone" name="phone" required placeholder="e.g., 09123456789" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                             
                             <label for="password">Password:</label>
                             <input type="text" id="password" name="password" maxlength="20" readonly class="auto-generated" placeholder="Auto-generated">
                             <div class="password-note">Auto: username + last 4 of phone</div>
                             
                             <label for="email">Email: <span class="required">*</span></label>
                             <input type="email" id="email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                         </div>
                         <div class="form-column">
                             <label for="region">Region: <span class="required">*</span></label>
                             <select id="region" name="region" required> <option value="">Select Region</option> </select>

                             <label for="city">City: <span class="required">*</span></label>
                             <select id="city" name="city" required disabled> <option value="">Select City</option> </select>
                             
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
                             <label for="business_proof">Business Proof: <span class="required">*</span> <span class="file-info">(Max 3 images, 20MB/ea, JPG/PNG)</span></label>
                             <input type="file" id="business_proof" name="business_proof[]" required accept="image/jpeg, image/png" multiple title="Max 3 images. Max size: 20MB per image">
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
            <div class="confirmation-message">Are you sure you want to add this new account?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeAddConfirmation()">No</button>
                <button class="confirm-yes" onclick="submitAddAccount()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Edit Account Modal -->
     <div id="editAccountOverlay" class="overlay">
         <div class="form-modal-content">
             <div class="modal-header">
                 <h2><i class="fas fa-edit"></i> Edit Account</h2>
             </div>
             <form id="editAccountForm" method="POST" class="account-form" enctype="multipart/form-data" novalidate>
                 <input type="hidden" name="formType" value="edit">
                 <input type="hidden" id="edit-id" name="id">
                 <input type="hidden" id="existing-business-proof" name="existing_business_proof">
                 <input type="hidden" id="edit-original-region" name="original_region">
                 <input type="hidden" id="edit-original-city" name="original_city">
                 
                 <div class="modal-body">
                      <div id="editAccountError" class="error-message"></div> <!-- Error message display -->
                     <div class="two-column-form">
                         <div class="form-column">
                             <label for="edit-username">Username: <span class="required">*</span></label>
                             <input type="text" id="edit-username" name="username" autocomplete="username" required placeholder="e.g., johndoe" maxlength="15" pattern="^[a-zA-Z0-9_]+$" title="Username can only contain letters, numbers, and underscores. Max 15 characters.">
                             
                             <label for="edit-phone">Phone Number: <span class="required">*</span></label>
                             <input type="tel" id="edit-phone" name="phone" required placeholder="e.g., 09123456789" maxlength="12" pattern="[0-9]+" title="Please enter up to 12 digits (numbers only)" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                             
                             <div class="switch-container">
                                 <label class="switch"><input type="checkbox" id="edit-password-toggle"><span class="slider"></span></label>
                                 <span class="switch-label">Set Manual Password</span>
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
                                     <span class="toggle-password" onclick="togglePasswordVisibility('edit-manual-password')"><i class="fas fa-eye"></i></span>
                                 </div>
                                 <div class="password-note">Min 6 characters.</div>
                             </div>
                             
                             <label for="edit-email">Email: <span class="required">*</span></label>
                             <input type="email" id="edit-email" name="email" required maxlength="40" placeholder="e.g., johndoe@example.com">
                         </div>
                         <div class="form-column">
                             <label for="edit-region">Region: <span class="required">*</span></label>
                             <select id="edit-region" name="region" required> <option value="">Select Region</option> </select>

                             <label for="edit-city">City: <span class="required">*</span></label>
                             <select id="edit-city" name="city" required> <option value="">Select City</option> </select>
                             
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
                             
                             <div id="edit-business-proof-container"></div> <!-- Display current images here -->
                             <label for="edit-business_proof">Upload New Business Proof: <span class="file-info">(Optional - Replaces existing. Max 3 images, 20MB/ea, JPG/PNG)</span></label>
                             <input type="file" id="edit-business_proof" name="business_proof[]" accept="image/jpeg, image/png" multiple title="Max 3 images. Max size: 20MB per image. Uploading new files will replace all existing ones.">
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
            <div class="confirmation-message">Are you sure you want to save these changes?</div>
            <div class="confirmation-buttons">
                <button class="confirm-no" onclick="closeEditConfirmation()">No</button>
                <button class="confirm-yes" onclick="submitEditAccount()">Yes</button>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="overlay">
        <div class="overlay-content"> <!-- Use overlay-content for consistent styling -->
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

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script> <!-- Assuming this is your custom toast init -->
    <script>
    // Global variable for status modal
    let currentAccountId = 0;
    // Cache for region/city data
    let regionCityMap = new Map();

    // --- Image Zoom Modal Functions ---
    function openModal(imgElement) {
        const modal = document.getElementById("myModal");
        const modalImg = document.getElementById("img01");
        const captionText = document.getElementById("caption");
        if (modal && modalImg && captionText) {
            modal.style.display = "block";
            modalImg.src = imgElement.src;
            captionText.innerHTML = imgElement.alt || 'Business Proof';
        }
    }
    function closeModal() {
        const modal = document.getElementById("myModal");
        if (modal) modal.style.display = "none";
    }

    // --- Info Modal Functions ---
    function showContactInfo(email, phone) {
        document.getElementById("modalEmail").textContent = email || 'N/A';
        document.getElementById("modalPhone").textContent = phone || 'N/A';
        const modal = document.getElementById("contactInfoModal");
        if(modal) modal.style.display = "flex"; // Use flex for centering
    }
    function closeContactInfoModal() {
        const modal = document.getElementById("contactInfoModal");
        if(modal) modal.style.display = "none";
    }
    function showAddressInfo(companyAddress, region, city, billToAddress) {
        document.getElementById("modalCompanyAddress").textContent = companyAddress || 'N/A';
        document.getElementById("modalBillToAddress").textContent = billToAddress || 'N/A';
        // Potentially map region code to name here if needed, or display code
        document.getElementById("modalRegion").textContent = region || 'N/A'; 
        document.getElementById("modalCity").textContent = city || 'N/A';
        const modal = document.getElementById("addressInfoModal");
        if(modal) modal.style.display = "flex"; // Use flex for centering
    }
    function closeAddressInfoModal() {
        const modal = document.getElementById("addressInfoModal");
        if(modal) modal.style.display = "none";
    }

    // --- Password Visibility Toggle ---
    function togglePasswordVisibility(fieldId) {
        const passwordField = document.getElementById(fieldId);
        // Find the icon within the same password-container
        const icon = passwordField.parentElement.querySelector('.toggle-password i');
        if (passwordField && icon) {
            if (passwordField.type === "password") {
                passwordField.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    }

    // --- Region/City Loading Functions ---
    async function loadPhilippinesRegions(selectedRegionCode = null) {
        try {
            const response = await fetch('https://psgc.gitlab.io/api/regions/');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const regions = await response.json();
            
            const regionSelect = document.getElementById('region');
            const editRegionSelect = document.getElementById('edit-region');
            
            [regionSelect, editRegionSelect].forEach(select => {
                if (select) {
                    // Clear existing options except the placeholder
                    select.length = 1; 
                    // Sort regions by name
                    regions.sort((a, b) => a.name.localeCompare(b.name));
                    // Populate options
                    regions.forEach(region => {
                        select.add(new Option(region.name, region.code));
                    });
                    // Pre-select if a code is provided (for edit form)
                     if (select === editRegionSelect && selectedRegionCode) {
                         select.value = selectedRegionCode;
                         // Trigger change to load cities if region is pre-selected
                         if (select.value === selectedRegionCode) { // Check if value was actually set
                            select.dispatchEvent(new Event('change'));
                         }
                     }
                }
            });
        } catch (error) {
            console.error('Error loading regions:', error);
            showToast('Could not load regions.', 'error');
        }
    }

    async function loadCities(regionCode, citySelectId, selectedCityName = null) {
        const citySelect = document.getElementById(citySelectId);
        if (!citySelect) return;

        citySelect.disabled = true; // Disable while loading
        citySelect.length = 1; // Clear existing options except placeholder

        if (!regionCode) return; // Do nothing if no region selected

        try {
             let cities;
             // Check cache first
             if (regionCityMap.has(regionCode)) {
                 cities = regionCityMap.get(regionCode);
                 // console.log('Using cached cities for region:', regionCode);
             } else {
                // console.log('Fetching cities for region:', regionCode);
                 const response = await fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/cities-municipalities/`);
                 if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                 cities = await response.json();
                 // Cache the result
                 regionCityMap.set(regionCode, cities);
             }

            // Sort cities by name
            cities.sort((a, b) => a.name.localeCompare(b.name));
            // Populate options
            cities.forEach(city => {
                citySelect.add(new Option(city.name, city.name)); // Use name as value
            });

            citySelect.disabled = false; // Re-enable

            // Pre-select city if provided (for edit form)
            if (selectedCityName) {
                citySelect.value = selectedCityName;
            }

        } catch (error) {
            console.error(`Error loading cities for region ${regionCode}:`, error);
             showToast('Could not load cities.', 'error');
            citySelect.disabled = true; // Keep disabled on error
        }
    }

    // --- DOMContentLoaded ---
    document.addEventListener('DOMContentLoaded', function() {
        // Toastr setup (ensure /js/toast.js does this or do it here)
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: false, progressBar: true, positionClass: "toast-top-right", timeOut: 3000, extendedTimeOut: 1000, preventDuplicates: true
            };
        } else {
            console.warn('Toastr library not found.');
        }
        
        // Load regions on page load
        loadPhilippinesRegions();

        // --- Event Listeners ---
        const regionSelect = document.getElementById('region');
        const editRegionSelect = document.getElementById('edit-region');
        const citySelect = document.getElementById('city');
        const editCitySelect = document.getElementById('edit-city');

        if (regionSelect) {
            regionSelect.addEventListener('change', function() {
                loadCities(this.value, 'city');
            });
        }
        if (editRegionSelect) {
            editRegionSelect.addEventListener('change', function() {
                loadCities(this.value, 'edit-city');
            });
        }

        // Edit form password toggle listener
        const passwordToggle = document.getElementById('edit-password-toggle');
        const autoPassContainer = document.getElementById('auto-password-container');
        const manualPassContainer = document.getElementById('manual-password-container');
        const manualPassInput = document.getElementById('edit-manual-password');
        if (passwordToggle && autoPassContainer && manualPassContainer && manualPassInput) {
            passwordToggle.addEventListener('change', function() {
                const isManual = this.checked;
                autoPassContainer.style.display = isManual ? 'none' : 'block';
                manualPassContainer.style.display = isManual ? 'block' : 'none';
                manualPassInput.required = isManual; // Make required only if shown
                if (!isManual) {
                    manualPassInput.value = ''; // Clear manual password if toggled off
                }
            });
        }

        // Global click listener to close info modals when clicking outside
        window.addEventListener('click', function(event) {
            const addressModal = document.getElementById('addressInfoModal');
            const contactModal = document.getElementById('contactInfoModal');
            const imageModal = document.getElementById('myModal');
            
            if (event.target === addressModal) closeAddressInfoModal();
            if (event.target === contactModal) closeContactInfoModal();
            if (event.target === imageModal) closeModal();
        });
    }); // End DOMContentLoaded

    // --- Utility Functions ---
    function showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }
    function resetErrors() {
        ['addAccountError', 'editAccountError'].forEach(id => {
            const errorElement = document.getElementById(id);
            if (errorElement) {
                errorElement.style.display = 'none';
                errorElement.textContent = '';
            }
        });
    }
     // Function to show toast notifications (using toastr)
     function showToast(message, type = 'info') { // Default to info
         if (typeof toastr !== 'undefined') {
             // Map type to toastr functions
             const toastrFunc = toastr[type] || toastr.info; 
             toastrFunc(message);
         } else {
             // Fallback to console if toastr is not available
             console.log(`Toast [${type}]: ${message}`);
             // alert(message); // Avoid using alert for better UX
         }
     }


    // --- Form Handling Functions ---

    // Add Account
    function openAddAccountForm() {
        const overlay = document.getElementById("addAccountOverlay");
        const form = document.getElementById("addAccountForm");
        if (overlay && form) {
            form.reset(); // Reset form fields
            resetErrors();
            // Reset region/city dropdowns
            const regionSelect = document.getElementById('region');
            const citySelect = document.getElementById('city');
            if(regionSelect) regionSelect.selectedIndex = 0;
            if(citySelect) { citySelect.length = 1; citySelect.disabled = true; }
            overlay.style.display = "flex"; // Use flex for centering
        }
    }
    function closeAddAccountForm() {
        const overlay = document.getElementById("addAccountOverlay");
        if (overlay) overlay.style.display = "none";
    }
    function confirmAddAccount() {
        resetErrors();
        const form = document.getElementById('addAccountForm');
        if (!form || !form.checkValidity()) {
            form.reportValidity(); // Show native validation errors
            showError('addAccountError', 'Please fix the errors above.');
            return;
        }
        const modal = document.getElementById('addConfirmationModal');
        if(modal) modal.style.display = 'flex'; // Use flex for centering
    }
    function closeAddConfirmation() {
        const modal = document.getElementById('addConfirmationModal');
        if(modal) modal.style.display = 'none';
    }
    function submitAddAccount() {
        closeAddConfirmation(); // Close confirmation modal first
        const form = document.getElementById('addAccountForm');
        const formData = new FormData(form);
        formData.append('ajax', true); // Ensure ajax flag is set

        // Optional: Add loading indicator
        // showLoadingIndicator(); 

        $.ajax({
            url: window.location.pathname, // Use current script path
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json', // Expect JSON response
            success: function(response) {
                // hideLoadingIndicator();
                if (response && response.success) {
                    showToast('Account added successfully!', 'success');
                    closeAddAccountForm(); // Close the form modal
                    setTimeout(() => window.location.reload(), 1500); // Reload page after delay
                } else {
                    const message = response?.message || 'An unknown error occurred while adding the account.';
                    showError('addAccountError', message);
                    showToast(message, 'error');
                }
            },
            error: function(xhr, status, error) {
                // hideLoadingIndicator();
                console.error("Add Account AJAX Error:", status, error, xhr.responseText);
                const message = 'A server error occurred. Please try again later.';
                showError('addAccountError', message);
                showToast(message, 'error');
            }
        });
    }

    // Edit Account
    function openEditAccountForm(id, username, email, phone, regionCode, cityName, company, companyAddress, billToAddress, businessProofEncoded) {
        const overlay = document.getElementById("editAccountOverlay");
        const form = document.getElementById("editAccountForm");
        if (!overlay || !form) return;

        form.reset(); // Reset form first
        resetErrors();

        // Populate basic fields
        $('#edit-id').val(id);
        $('#edit-username').val(username);
        $('#edit-email').val(email);
        $('#edit-phone').val(phone);
        $('#edit-company').val(company);
        $('#edit-company_address').val(companyAddress);
        $('#edit-bill_to_address').val(billToAddress);
        $('#edit-original-region').val(regionCode); // Store original code
        $('#edit-original-city').val(cityName); // Store original name

        // Reset password fields
        $('#edit-password-toggle').prop('checked', false).trigger('change'); // Ensure toggle is off and event triggers
        $('#edit-manual-password').val('');

        // --- Handle Region/City Dropdowns ---
        const editRegionSelect = document.getElementById('edit-region');
        const editCitySelect = document.getElementById('edit-city');
        
        // Ensure regions are loaded before setting value
        // If loadPhilippinesRegions is fast, this might work. If slow, need promises or async/await.
        if (editRegionSelect && regionCode) {
             editRegionSelect.value = regionCode;
             // If the value was successfully set, load the cities for that region
             if (editRegionSelect.value === regionCode) {
                 loadCities(regionCode, 'edit-city', cityName);
             } else {
                 console.warn(`Region code ${regionCode} not found in dropdown.`);
                 if(editCitySelect) { editCitySelect.length = 1; editCitySelect.disabled = true; }
             }
        } else {
             if(editCitySelect) { editCitySelect.length = 1; editCitySelect.disabled = true; }
        }
        
        // --- Handle Business Proof ---
        const businessProofContainer = document.getElementById('edit-business-proof-container');
        const existingProofInput = document.getElementById('existing-business-proof');
        businessProofContainer.innerHTML = '<h4>Current Business Proof:</h4>'; // Reset display area
        existingProofInput.value = '[]'; // Default to empty array string

        let original_business_proof_json = '[]';
        if (businessProofEncoded) {
            try {
                // Decode HTML entities first
                const tempTextArea = document.createElement('textarea');
                tempTextArea.innerHTML = businessProofEncoded;
                original_business_proof_json = tempTextArea.value;
                
                // Validate if it's actually JSON before storing
                 JSON.parse(original_business_proof_json); 
                 existingProofInput.value = original_business_proof_json; // Store the clean, original JSON

            } catch(e) {
                 console.error("Error decoding/parsing business_proof_encoded:", e, "Input:", businessProofEncoded);
                 original_business_proof_json = '[]'; // Fallback to empty array on error
                 existingProofInput.value = '[]';
                 businessProofContainer.innerHTML += '<p style="color: red;">Error loading current proofs.</p>';
            }
        }

        // Display current images
         try {
             const proofs = JSON.parse(original_business_proof_json); // Parse the clean JSON
             if (Array.isArray(proofs) && proofs.length > 0) {
                 proofs.forEach(proof => {
                     if (typeof proof === 'string' && proof.startsWith('/')) {
                         const img = $('<img>', {
                             src: proof,
                             alt: 'Business Proof',
                             width: 50,
                             css: { margin: '3px', border: '1px solid #ccc', padding: '1px', cursor: 'pointer', backgroundColor: '#fff' },
                             click: function() { openModal(this); }
                         });
                         $(businessProofContainer).append(img);
                     } else {
                          console.warn('Skipping invalid proof item for display:', proof);
                     }
                 });
             } else {
                 businessProofContainer.innerHTML += '<p>No business proof images on record.</p>';
             }
         } catch (e) {
              console.error("Error displaying proofs from JSON:", e, "JSON:", original_business_proof_json);
              businessProofContainer.innerHTML += '<p style="color: red;">Error displaying current proofs.</p>';
         }


        // Clear the file input field in case browser cached a selection
        $('#edit-business_proof').val(null);

        overlay.style.display = "flex"; // Show the modal
    }

    function closeEditAccountForm() {
        const overlay = document.getElementById("editAccountOverlay");
        if (overlay) overlay.style.display = "none";
    }
    function confirmEditAccount() {
        resetErrors();
        const form = document.getElementById('editAccountForm');
        // Check manual password length if toggle is checked
        const passwordToggle = document.getElementById('edit-password-toggle');
        const manualPasswordInput = document.getElementById('edit-manual-password');
        if (passwordToggle.checked && manualPasswordInput.value.length > 0 && manualPasswordInput.value.length < 6) {
             showError('editAccountError', 'Manual password must be at least 6 characters long.');
             manualPasswordInput.focus();
             return; // Prevent confirmation
        }

        if (!form || !form.checkValidity()) {
            form.reportValidity();
            showError('editAccountError', 'Please fix the errors above.');
            return;
        }
        const modal = document.getElementById('editConfirmationModal');
        if(modal) modal.style.display = 'flex';
    }
    function closeEditConfirmation() {
        const modal = document.getElementById('editConfirmationModal');
        if(modal) modal.style.display = 'none';
    }
    function submitEditAccount() {
        closeEditConfirmation();
        const form = document.getElementById('editAccountForm');
        const formData = new FormData(form);
        formData.append('ajax', true);

        // Optional: Add loading indicator
        // showLoadingIndicator();

        $.ajax({
            url: window.location.pathname, // Use current script path
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                // hideLoadingIndicator();
                if (response && response.success) {
                    showToast('Account updated successfully!', 'success');
                    closeEditAccountForm();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    const message = response?.message || 'An unknown error occurred while updating the account.';
                    showError('editAccountError', message);
                    showToast(message, 'error');
                }
            },
            error: function(xhr, status, error) {
                // hideLoadingIndicator();
                console.error("Edit Account AJAX Error:", status, error, xhr.responseText);
                const message = 'A server error occurred. Please try again later.';
                showError('editAccountError', message);
                showToast(message, 'error');
            }
        });
    }

    // Status Change
    function openStatusModal(id, username, email) {
        const modal = document.getElementById("statusModal");
        const messageEl = document.getElementById("statusMessage");
        if (modal && messageEl) {
            currentAccountId = id; // Store ID globally for changeStatus function
            messageEl.textContent = `Change status for ${username} (${email})`;
            modal.style.display = "flex"; // Use flex for centering
        }
    }
    function closeStatusModal() {
        const modal = document.getElementById("statusModal");
        if (modal) modal.style.display = "none";
        currentAccountId = 0; // Reset stored ID
    }
    function changeStatus(newStatus) {
        if (!currentAccountId) {
            console.error("No account ID selected for status change.");
            return;
        }
        
        // Optional: Add loading indicator
        // showLoadingIndicator();

        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: {
                ajax: true,
                formType: 'status',
                id: currentAccountId,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                // hideLoadingIndicator();
                if (response && response.success) {
                    showToast(`Status changed to ${newStatus} successfully!`, 'success');
                    closeStatusModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    const message = response?.message || 'An error occurred while changing status.';
                    showToast(message, 'error');
                     // Optionally keep modal open on error?
                     // closeStatusModal(); 
                }
            },
            error: function(xhr, status, error) {
                // hideLoadingIndicator();
                console.error("Change Status AJAX Error:", status, error, xhr.responseText);
                showToast('A server error occurred. Please try again.', 'error');
                closeStatusModal(); // Close modal on server error
            }
        });
    }

    // --- Table Filtering ---
    function filterByStatus() {
        var status = document.getElementById("statusFilter").value;
        // Construct URL safely
        const url = new URL(window.location.href);
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status'); // Remove status param if "All" selected
        }
        window.location.href = url.toString();
    }

    </script>
</body>
</html>