<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_user_id'])) {
    // Redirect to admin login page
    header("Location: ../login.php");
    exit();
}

// Check role permission for Drivers
checkRole('Drivers');

function returnJsonResponse($success, $reload, $message = '', $errors = []) {
    // Ensure JSON header is set ONLY when outputting JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message, 'errors' => $errors]);
    exit;
}

// --- Process form submissions (Add, Edit, Status Change) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Set header here as these blocks exclusively output JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    // --- ADD DRIVER ---
    if ($_POST['formType'] == 'add') {
        $name = trim($_POST['name']);
        $username = trim($_POST['username']); // Added
        $password = $_POST['password']; // Added
        $address = $_POST['address'];
        $contact_no = $_POST['contact_no'];
        $availability = $_POST['availability'];
        $area = $_POST['area'];
        $errors = [];

        // --- Server-side Validations ---
        if (empty($name)) $errors['name'] = 'Name is required.';
        if (empty($username)) {
             $errors['username'] = 'Username is required.';
        } else {
            // Check username uniqueness
            $checkUserStmt = $conn->prepare("SELECT id FROM drivers WHERE username = ?");
            if ($checkUserStmt) {
                $checkUserStmt->bind_param("s", $username);
                $checkUserStmt->execute();
                $checkUserStmt->store_result();
                if ($checkUserStmt->num_rows > 0) {
                    $errors['username'] = 'Username already exists.';
                }
                $checkUserStmt->close();
            } else {
                 error_log("Add Driver Username Check Prepare Error: " . $conn->error);
                 returnJsonResponse(false, false, 'Database error checking username.', ['general' => 'Database error checking username.']);
            }
        }
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) { // Basic complexity: min length
             $errors['password'] = 'Password must be at least 6 characters long.';
        }
        if (empty($address)) $errors['address'] = 'Address is required.';
        if (!preg_match('/^63\\d{10}$/', $contact_no)) {
            $errors['contact_no'] = 'Contact number must be 12 digits starting with 63.';
        }
        if (!in_array($availability, ['Available', 'Not Available'])) $errors['availability'] = 'Invalid availability status.';
        if (!in_array($area, ['North', 'South'])) $errors['area'] = 'Invalid area.';

        // Check name uniqueness (already existed, kept it)
        if (empty($errors['name'])) {
             $checkNameStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ?");
             if ($checkNameStmt) {
                 $checkNameStmt->bind_param("s", $name);
                 $checkNameStmt->execute();
                 $checkNameStmt->store_result();
                 if ($checkNameStmt->num_rows > 0) {
                     $errors['name'] = 'Driver name already exists.';
                 }
                 $checkNameStmt->close();
             } else {
                 error_log("Add Driver Name Check Prepare Error: " . $conn->error);
                 returnJsonResponse(false, false, 'Database error checking name.', ['general' => 'Database error checking name.']);
             }
        }

        // If validation errors, return them
        if (!empty($errors)) {
            returnJsonResponse(false, false, 'Please correct the errors below.', $errors);
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
            error_log("Password hashing failed for add driver.");
            returnJsonResponse(false, false, 'Failed to secure password. Please try again.', ['password' => 'Hashing failed.']);
        }

        // Prepare and execute insert
        $stmtAdd = $conn->prepare("INSERT INTO drivers (name, username, password, address, contact_no, availability, area, current_deliveries) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        if (!$stmtAdd) {
             error_log("Add Driver Prepare Error: " . $conn->error);
             returnJsonResponse(false, false, 'Failed to prepare statement. Database error.', ['general' => 'Database error.']);
        }
        // Note: types changed to 'sssssss'
        $stmtAdd->bind_param("sssssss", $name, $username, $hashed_password, $address, $contact_no, $availability, $area);

        if ($stmtAdd->execute()) {
            returnJsonResponse(true, true, 'Driver added successfully.'); // Success, trigger reload
        } else {
            error_log("Add Driver DB Error: " . $stmtAdd->error);
            returnJsonResponse(false, false, 'Failed to add driver. Database error.', ['general' => 'Database execution error.']);
        }
        $stmtAdd->close();
    }
    // --- EDIT DRIVER ---
    elseif ($_POST['formType'] == 'edit') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $username = trim($_POST['username']); // Added
        $password = $_POST['password']; // Added (new password, optional)
        $address = $_POST['address'];
        $contact_no = $_POST['contact_no'];
        $availability = $_POST['availability'];
        $area = $_POST['area'];
        $errors = [];

        // --- Server-side Validations ---
         if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) $errors['general'] = 'Invalid Driver ID.';
         if (empty($name)) $errors['name'] = 'Name is required.';
         if (empty($username)) {
             $errors['username'] = 'Username is required.';
         } else {
             // Check username uniqueness (excluding current driver)
             $checkUserStmt = $conn->prepare("SELECT id FROM drivers WHERE username = ? AND id != ?");
             if ($checkUserStmt) {
                 $checkUserStmt->bind_param("si", $username, $id);
                 $checkUserStmt->execute();
                 $checkUserStmt->store_result();
                 if ($checkUserStmt->num_rows > 0) {
                     $errors['username'] = 'Username already exists.';
                 }
                 $checkUserStmt->close();
             } else {
                 error_log("Edit Driver Username Check Prepare Error: " . $conn->error);
                 returnJsonResponse(false, false, 'Database error checking username.', ['general' => 'Database error checking username.']);
             }
         }
         // Validate password ONLY if provided
         if (!empty($password)) {
             if (strlen($password) < 6) { // Basic complexity: min length
                 $errors['password'] = 'New password must be at least 6 characters long.';
             }
         }
         if (empty($address)) $errors['address'] = 'Address is required.';
         if (!preg_match('/^63\\d{10}$/', $contact_no)) {
             $errors['contact_no'] = 'Contact number must be 12 digits starting with 63.';
         }
         if (!in_array($availability, ['Available', 'Not Available'])) $errors['availability'] = 'Invalid availability status.';
         if (!in_array($area, ['North', 'South'])) $errors['area'] = 'Invalid area.';

         // Check name uniqueness (excluding current driver)
         if (empty($errors['name']) && empty($errors['general'])) {
             $checkNameStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ? AND id != ?");
             if ($checkNameStmt) {
                 $checkNameStmt->bind_param("si", $name, $id);
                 $checkNameStmt->execute();
                 $checkNameStmt->store_result();
                 if ($checkNameStmt->num_rows > 0) {
                     $errors['name'] = 'Driver name already exists.';
                 }
                 $checkNameStmt->close();
             } else {
                 error_log("Edit Driver Name Check Prepare Error: " . $conn->error);
                 returnJsonResponse(false, false, 'Database error checking name.', ['general' => 'Database error checking name.']);
             }
         }

        // If validation errors, return them
        if (!empty($errors)) {
            returnJsonResponse(false, false, 'Please correct the errors below.', $errors);
        }

        // Prepare update statement
        $sql_update = "UPDATE drivers SET name = ?, username = ?, address = ?, contact_no = ?, availability = ?, area = ?";
        $types = "ssssss"; // Base types
        $params_update = [$name, $username, $address, $contact_no, $availability, $area];

        // Handle optional password update
        $hashed_password = null;
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
             if ($hashed_password === false) {
                 error_log("Password hashing failed for edit driver ID: $id");
                 returnJsonResponse(false, false, 'Failed to secure new password. Please try again.', ['password' => 'Hashing failed.']);
             }
            $sql_update .= ", password = ?";
            $types .= "s";
            $params_update[] = $hashed_password;
        }

        $sql_update .= " WHERE id = ?";
        $types .= "i";
        $params_update[] = $id;

        $stmtEdit = $conn->prepare($sql_update);
        if (!$stmtEdit) {
            error_log("Edit Driver Prepare Error: " . $conn->error);
            returnJsonResponse(false, false, 'Failed to prepare update. Database error.', ['general' => 'Database error.']);
        }
        $stmtEdit->bind_param($types, ...$params_update);

        if ($stmtEdit->execute()) {
            returnJsonResponse(true, true, 'Driver updated successfully.'); // Success, trigger reload
        } else {
            error_log("Edit Driver DB Error: " . $stmtEdit->error);
            returnJsonResponse(false, false, 'Failed to update driver. Database error.', ['general' => 'Database execution error.']);
        }
        $stmtEdit->close();
    }
    // --- CHANGE STATUS ---
    elseif ($_POST['formType'] == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status']; // Should be 'Available' or 'Not Available'

        // Basic validation
        if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) {
             returnJsonResponse(false, false, 'Invalid Driver ID.');
        }
        if (!in_array($status, ['Available', 'Not Available'])) {
             returnJsonResponse(false, false, 'Invalid status provided.');
        }

        $stmtStatus = $conn->prepare("UPDATE drivers SET availability = ? WHERE id = ?");
        if (!$stmtStatus) {
             error_log("Change Driver Status Prepare Error: " . $conn->error);
             returnJsonResponse(false, false, 'Failed to prepare status update.');
        }
        $stmtStatus->bind_param("si", $status, $id);

        if ($stmtStatus->execute()) {
            returnJsonResponse(true, true, 'Status updated successfully.'); // Success, trigger reload
        } else {
            error_log("Change Driver Status DB Error: " . $stmtStatus->error);
            returnJsonResponse(false, false, 'Failed to change status. Database error.');
        }
        $stmtStatus->close();
    }
    exit; // Ensure exit after handling any POST request
}


// --- Handler for fetching driver deliveries (Modal List) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['driver_id']) && isset($_GET['action']) && $_GET['action'] == 'get_deliveries') {
    // Set header here as this block exclusively outputs JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $driver_id = filter_var($_GET['driver_id'], FILTER_VALIDATE_INT);
    $data = [];

    if ($driver_id === false || $driver_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Driver ID.']);
        exit;
    }

    $stmtModal = $conn->prepare("
        SELECT da.po_number, o.orders, o.delivery_date, o.delivery_address, o.status, o.username
        FROM driver_assignments da
        JOIN orders o ON da.po_number = o.po_number
        WHERE da.driver_id = ?
          AND o.status IN ('Active', 'For Delivery', 'In Transit') -- Show only active/ongoing deliveries
        ORDER BY o.delivery_date ASC -- Show soonest delivery date first
    ");

    if (!$stmtModal) {
        error_log("[drivers.php get_deliveries] Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed. Check logs.']);
        exit;
    }
    $stmtModal->bind_param("i", $driver_id);

    if (!$stmtModal->execute()) {
        error_log("[drivers.php get_deliveries] Execute failed: " . $stmtModal->error);
        echo json_encode(['success' => false, 'message' => 'Database query execution failed. Check logs.']);
        $stmtModal->close();
        exit;
    }
    $resultModal = $stmtModal->get_result();

    if ($resultModal === false) {
         error_log("[drivers.php get_deliveries] Get result failed: " . $stmtModal->error);
         echo json_encode(['success' => false, 'message' => 'Failed to retrieve query results. Check logs.']);
         $stmtModal->close();
         exit;
    }

    if ($resultModal->num_rows > 0) {
        while ($row = $resultModal->fetch_assoc()) {
            $orderItems = json_decode($row['orders'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[drivers.php get_deliveries] JSON Decode Error for PO {$row['po_number']}: " . json_last_error_msg() . " | Raw Data: " . $row['orders']);
                $orderItems = []; // Use empty array on error
            }
            $data[] = [
                'po_number' => $row['po_number'],
                'username' => $row['username'],
                'delivery_date' => $row['delivery_date'],
                'delivery_address' => $row['delivery_address'],
                'status' => $row['status'],
                'items' => $orderItems ?? []
            ];
        }
        echo json_encode(['success' => true, 'deliveries' => $data]);
    } else {
        echo json_encode(['success' => true, 'deliveries' => []]); // No rows found is a valid success case
    }

    $stmtModal->close();
    exit; // Important to stop script execution here
}


// --- Fetch main list of drivers for the page display ---
$status_filter = $_GET['status'] ?? '';
$area_filter = $_GET['area'] ?? '';
$params = [];
$types = "";
$main_list_result = null;

// Query to fetch drivers including username and calculate active deliveries
$sql = "SELECT
            d.id, d.name, d.username, d.address, d.contact_no, d.availability, d.area, d.created_at, -- Added d.username
            COALESCE(COUNT(DISTINCT o.po_number), 0) AS calculated_deliveries
        FROM
            drivers d
        LEFT JOIN
            driver_assignments da ON d.id = da.driver_id
        LEFT JOIN
            orders o ON da.po_number = o.po_number AND o.status IN ('Active', 'For Delivery', 'In Transit')
        WHERE
            1=1";

// Add filters
if (!empty($status_filter)) {
    $sql .= " AND d.availability = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($area_filter)) {
    $sql .= " AND d.area = ?";
    $params[] = $area_filter;
    $types .= "s";
}

// Updated GROUP BY
$sql .= " GROUP BY d.id, d.name, d.username, d.address, d.contact_no, d.availability, d.area, d.created_at";
$sql .= " ORDER BY d.name ASC";


$stmtMain = $conn->prepare($sql);
if (!$stmtMain) {
    error_log("Prepare failed for main driver list: " . $conn->error);
    die("Error preparing driver list. Please check server logs.");
}

if (!empty($types)) {
    if (!$stmtMain->bind_param($types, ...$params)) {
         error_log("Bind param failed for main driver list: " . $stmtMain->error);
         $stmtMain->close();
         die("Error binding parameters. Please check server logs.");
    }
}

if(!$stmtMain->execute()) {
    error_log("Execute failed for main driver list: " . $stmtMain->error);
    $stmtMain->close();
    die("Error executing driver list query. Please check server logs.");
}

$main_list_result = $stmtMain->get_result();

if ($main_list_result === false) {
    error_log("Get result failed for main driver list: " . $stmtMain->error);
    $stmtMain->close();
    die("Error retrieving driver list results. Please check server logs.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers List</title>
    <!-- Link your existing CSS files -->
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/drivers.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        /* --- Minimal Styles Needed for Functionality --- */
        .overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
        .overlay-content { background-color: #fefefe; margin: auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .overlay-content h2 { margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 1.5rem; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .account-form label { display: block; margin-bottom: 5px; font-weight: 500; }
        .account-form input[type="text"], .account-form input[type="password"], .account-form select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-buttons { text-align: right; margin-top: 20px; }
        .form-buttons button { margin-left: 10px; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .cancel-btn { background-color: #6c757d; color: white; }
        .save-btn, .approve-btn { background-color: #28a745; color: white; }
        .reject-btn { background-color: #dc3545; color: white; }
        .modal-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
        .modal-buttons.single-button { justify-content: flex-end; }
        .required-asterisk { color: red; margin-left: 3px; }
        .error-message { color: red; margin-bottom: 10px; font-size: 0.9em; display: block; min-height: 1em; margin-top: -10px; /* Pull up slightly */ }
        .form-field-error { border: 1px solid red !important; }

        /* Styles for Delivery Count and List */
        .delivery-count { font-weight: bold; padding: 3px 8px; border-radius: 10px; display: inline-block; text-align: center; margin-right: 5px; min-width: 40px; }
        .delivery-count-low { background-color: #d4edda; color: #155724; }
        .delivery-count-medium { background-color: #fff3cd; color: #856404; }
        .delivery-count-high { background-color: #f8d7da; color: #721c24; }
        .see-deliveries-btn { background-color: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; font-size: 14px; vertical-align: middle; }
        .see-deliveries-btn:hover { background-color: #5a6268; }
        .status-available { color: #155724; font-weight: bold; }
        .status-not-available { color: #721c24; font-weight: bold; }

        /* Deliveries Modal Specific */
        .deliveries-modal-content { max-width: 900px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
        .deliveries-table-container { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        .deliveries-table { width: 100%; border-collapse: collapse; }
        .deliveries-table th, .deliveries-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        .deliveries-table th { background-color: rgb(27, 27, 27); color: white; position: sticky; top: 0; z-index: 1; }
        .no-deliveries { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .delivery-status-badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block; color: #fff; }
        .status-pending { background-color: #ffc107; color: #333;}
        .status-active { background-color: #0d6efd; }
        .status-for-delivery { background-color: #17a2b8; }
        .status-in-transit { background-color: #fd7e14; }
        .status-completed { background-color: #198754; }
        .status-rejected { background-color: #dc3545; }
        .po-header { background-color: #f9f9f9; cursor: pointer; transition: background-color 0.2s; }
        .po-header:hover { background-color: #f0f0f0; }
        .order-items-row { /* Initially hidden by JS */ }
        .order-items-row.collapsed .order-items { display: none; }
        .order-items { padding: 10px 15px; background-color: #fff; border-left: 3px solid #17a2b8; }
        .order-items h4 { margin-top: 0; margin-bottom: 10px; font-size: 1em; color: #555; }
        .order-items table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .order-items th, .order-items td { padding: 6px; text-align: left; border: 1px solid #eee; }
        .order-items th { background-color:rgb(82, 82, 82); color: white; font-weight: 500; }
        .expand-icon { margin-right: 5px; display: inline-block; transition: transform 0.2s; width: 1em; text-align: center; }
        .po-header.collapsed .expand-icon { transform: rotate(-90deg); }
        .deliveries-modal-content .modal-buttons { margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; }

        /* Add other necessary styles from your original CSS files if needed */

    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; // Make sure this path is correct ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Drivers List</h1>
            <div class="filter-section">
                <label for="statusFilter">Status:</label>
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
                        <th>Username</th> <!-- Added Column -->
                        <th>Address</th>
                        <th>Contact No.</th>
                        <th>Area</th>
                        <th>Availability</th>
                        <th>Active Deliveries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Use the result fetched earlier ($main_list_result)
                    if ($main_list_result && $main_list_result->num_rows > 0):
                        while ($row = $main_list_result->fetch_assoc()):
                            $active_delivery_count = $row['calculated_deliveries'];
                            $deliveryClass = 'delivery-count-low';
                            if ($active_delivery_count > 15) $deliveryClass = 'delivery-count-high';
                            else if ($active_delivery_count > 10) $deliveryClass = 'delivery-count-medium';
                            $availability_class = 'status-' . strtolower(str_replace(' ', '-', $row['availability']));
                    ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td> <!-- Added Data -->
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_no']) ?></td>
                                <td><?= htmlspecialchars($row['area']) ?></td>
                                <td class="<?= $availability_class ?>">
                                    <?= htmlspecialchars($row['availability']) ?>
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
                                    <!-- Pass username to openEditDriverForm -->
                                    <button class="edit-btn" onclick="openEditDriverForm(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['username'])) ?>', '<?= htmlspecialchars(addslashes($row['address'])) ?>', '<?= htmlspecialchars($row['contact_no']) ?>', '<?= htmlspecialchars($row['availability']) ?>', '<?= htmlspecialchars($row['area']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-toggle-on"></i> Status
                                    </button>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    else: // No rows found
                    ?>
                        <tr>
                            <td colspan="8" class="no-accounts">No drivers found matching criteria.</td> <!-- Updated colspan -->
                        </tr>
                    <?php
                    endif;
                    // Close the main statement *ONCE* after the loop/else block
                    if (isset($stmtMain)) $stmtMain->close();
                    // Close the database connection
                    $conn->close();
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
            <div id="addDriverError" class="error-message"></div> <!-- General Error Area -->
            <form id="addDriverForm" method="POST" class="account-form" action="" novalidate> <!-- Added novalidate -->
                <input type="hidden" name="formType" value="add">

                <label for="add-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-name" name="name" required>
                <span id="addNameError" class="error-message"></span>

                <label for="add-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-username" name="username" required>
                <span id="addUsernameError" class="error-message"></span>

                <label for="add-password">Password:<span class="required-asterisk">*</span></label>
                <input type="password" id="add-password" name="password" required>
                <span id="addPasswordError" class="error-message"></span>

                <label for="add-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-address" name="address" required>
                <span id="addAddressError" class="error-message"></span>

                <label for="add-contact_no">Contact No.: (e.g., 639171234567)<span class="required-asterisk">*</span></label>
                <input type="text" id="add-contact_no" name="contact_no" required maxlength="12" pattern="^63\d{10}$" title="Must be 12 digits starting with 63">
                <span id="addContactError" class="error-message"></span>

                <label for="add-area">Area:<span class="required-asterisk">*</span></label>
                <select id="add-area" name="area" required>
                    <option value="North">North</option>
                    <option value="South">South</option>
                </select>
                <span id="addAreaError" class="error-message"></span>

                <label for="add-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="add-availability" name="availability" required>
                    <option value="Available">Available</option>
                    <option value="Not Available">Not Available</option>
                </select>
                <span id="addAvailabilityError" class="error-message"></span>

                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddDriverForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
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
            <div id="editDriverError" class="error-message"></div> <!-- General Error Area -->
            <form id="editDriverForm" method="POST" class="account-form" action="" novalidate> <!-- Added novalidate -->
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id">

                <label for="edit-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-name" name="name" required>
                <span id="editNameError" class="error-message"></span>

                <label for="edit-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-username" name="username" required>
                <span id="editUsernameError" class="error-message"></span>

                <label for="edit-password">New Password: (Leave blank to keep current)</label>
                <input type="password" id="edit-password" name="password"> <!-- Password is optional on edit -->
                <span id="editPasswordError" class="error-message"></span>

                <label for="edit-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-address" name="address" required>
                <span id="editAddressError" class="error-message"></span>

                <label for="edit-contact_no">Contact No.: (e.g., 639171234567)<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-contact_no" name="contact_no" required maxlength="12" pattern="^63\d{10}$" title="Must be 12 digits starting with 63">
                <span id="editContactError" class="error-message"></span>

                <label for="edit-area">Area:<span class="required-asterisk">*</span></label>
                <select id="edit-area" name="area" required>
                    <option value="North">North</option>
                    <option value="South">South</option>
                </select>
                <span id="editAreaError" class="error-message"></span>

                <label for="edit-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="edit-availability" name="availability" required>
                    <option value="Available">Available</option>
                    <option value="Not Available">Not Available</option>
                </select>
                <span id="editAvailabilityError" class="error-message"></span>

                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditDriverForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div id="statusModal" class="overlay" style="display: none;">
         <div class="overlay-content">
            <span class="close-btn" onclick="closeStatusModal()">&times;</span>
            <h2>Change Driver Availability</h2>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeStatus('Available')">
                    <i class="fas fa-check-circle"></i> Set Available
                </button>
                <button class="reject-btn" onclick="changeStatus('Not Available')">
                    <i class="fas fa-times-circle"></i> Set Not Available
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeStatusModal()">
                    <i class="fas fa-ban"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Deliveries List Modal -->
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


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script> <!-- Make sure this path is correct -->
    <script>
        let selectedDriverId = null;

        // --- Clear Form Errors ---
        function clearFormErrors(formId) {
            $(`#${formId} .error-message`).text('');
            $(`#${formId} input, #${formId} select`).removeClass('form-field-error');
            $(`#${formId}Error`).text(''); // Clear general error area
        }

        // --- Display Form Errors ---
        function displayFormErrors(formId, errors) {
             clearFormErrors(formId); // Clear previous errors first
             if (errors && typeof errors === 'object') {
                 $.each(errors, function(field, message) {
                     const inputElement = $(`#${formId} [name="${field}"]`);
                     const errorElement = $(`#${formId.replace('Form', '')}${field.charAt(0).toUpperCase() + field.slice(1)}Error`); // e.g., #addNameError

                     if (inputElement.length) {
                         inputElement.addClass('form-field-error');
                     }
                     if (errorElement.length) {
                         errorElement.text(message);
                     } else if (field === 'general') { // Handle general errors
                          $(`#${formId.replace('Form', '')}Error`).text(message);
                     }
                 });
             }
        }

        // --- Modal Control Functions ---
        function openAddDriverForm() {
            clearFormErrors('addDriverForm');
            $('#addDriverForm')[0].reset();
            $('#addDriverOverlay').css('display', 'flex');
        }
        function closeAddDriverForm() {
            $('#addDriverOverlay').hide();
            clearFormErrors('addDriverForm'); // Clear errors on close
        }
        // Updated to include username
        function openEditDriverForm(id, name, username, address, contact_no, availability, area) {
            clearFormErrors('editDriverForm');
            $('#edit-id').val(id);
            $('#edit-name').val(name);
            $('#edit-username').val(username); // Populate username
            $('#edit-password').val(''); // Clear password field
            $('#edit-address').val(address);
            $('#edit-contact_no').val(contact_no);
            $('#edit-availability').val(availability);
            $('#edit-area').val(area);
            $('#editDriverOverlay').css('display', 'flex');
        }
        function closeEditDriverForm() {
            $('#editDriverOverlay').hide();
            clearFormErrors('editDriverForm'); // Clear errors on close
        }
        function openStatusModal(id, name) {
            selectedDriverId = id;
            $('#statusMessage').text(`Change availability status for driver: ${name}`);
            $('#statusModal').css('display', 'flex');
        }
        function closeStatusModal() {
            $('#statusModal').hide();
            selectedDriverId = null;
        }
        function closeDeliveriesModal() {
            $('#deliveriesModal').hide();
            selectedDriverId = null;
        }

        // --- Deliveries Modal Logic ---
        function toggleOrderItems(headerRow) {
             const itemsRow = headerRow.nextElementSibling;
             if (itemsRow && itemsRow.classList.contains('order-items-row')) {
                  headerRow.classList.toggle('collapsed');
                  $(itemsRow).toggle(!headerRow.classList.contains('collapsed'));
             }
        }
        function formatDate(dateStr) { if (!dateStr) return 'N/A'; try { const date = new Date(dateStr); if (isNaN(date.getTime())) return 'Invalid Date'; return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }); } catch (e) { return 'Invalid Date'; } }
        function formatCurrency(amount) { const num = parseFloat(amount); if (isNaN(num)) return '₱--.--'; return '₱' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
        function viewDriverDeliveries(id, name) {
            selectedDriverId = id;
            $('#deliveriesModalTitle').text(`${name}'s Active/Pending Deliveries`);
            $('#deliveriesModal').css('display', 'flex');
            const tableBody = $('#deliveriesTableBody');
            tableBody.html('<tr><td colspan="4" class="no-deliveries"><i class="fas fa-spinner fa-spin"></i> Loading deliveries...</td></tr>');

            fetch(`drivers.php?driver_id=${id}&action=get_deliveries`)
                .then(response => { if (!response.ok) { return response.text().then(text => { console.error("Server response:", text); throw new Error(`Server error: ${response.status}`); }); } return response.json(); })
                .then(data => {
                    console.log("Received deliveries data:", data);
                    if (data.success && data.deliveries) {
                        if (data.deliveries.length > 0) {
                            let html = '';
                            data.deliveries.forEach((delivery) => {
                                let statusClass = 'status-' + (delivery.status ? delivery.status.toLowerCase().replace(/\s+/g, '-') : 'unknown');
                                html += `<tr class="po-header collapsed" onclick="toggleOrderItems(this)">
                                            <td><span class="expand-icon">▼</span> ${delivery.po_number}</td>
                                            <td>${formatDate(delivery.delivery_date)}</td>
                                            <td>${delivery.delivery_address || 'N/A'}</td>
                                            <td><span class="delivery-status-badge ${statusClass}">${delivery.status || 'Unknown'}</span></td>
                                         </tr>
                                         <tr class="order-items-row" style="display: none;">
                                            <td colspan="4" class="order-items">
                                                <h4>Order Items (${delivery.username || 'N/A'})</h4>
                                                <table><thead><tr><th>Item</th><th>Packaging</th><th>Quantity</th><th>Price</th></tr></thead><tbody>`;
                                if (delivery.items && Array.isArray(delivery.items) && delivery.items.length > 0) {
                                    delivery.items.forEach(item => { html += `<tr><td>${item.item_description || 'N/A'}</td><td>${item.packaging || 'N/A'}</td><td>${item.quantity || 0}</td><td>${formatCurrency(item.price)}</td></tr>`; });
                                } else { html += `<tr><td colspan="4" style="text-align: center; color: #888;">No items details available.</td></tr>`; }
                                html += `</tbody></table></td></tr>`;
                            });
                            tableBody.html(html);
                        } else { tableBody.html('<tr><td colspan="4" class="no-deliveries">No active or pending deliveries found.</td></tr>'); }
                    } else { console.error('API reported failure or missing data:', data.message); tableBody.html(`<tr><td colspan="4" class="no-deliveries">Error loading deliveries: ${data.message || 'Unknown error'}</td></tr>`); }
                })
                .catch(error => { console.error('Error fetching deliveries:', error); tableBody.html('<tr><td colspan="4" class="no-deliveries">Error loading deliveries. Check console.</td></tr>'); });
        }

        // --- Status Change AJAX ---
        function changeStatus(status) {
            if (selectedDriverId === null) { showToast('No driver selected.', 'error'); return; }
            $.ajax({
                url: 'drivers.php', type: 'POST',
                data: { ajax: true, formType: 'status', id: selectedDriverId, status: status },
                dataType: 'json',
                success: function(response) { if (response.success && response.reload) { showToast(response.message || 'Status updated successfully', 'success'); setTimeout(() => window.location.reload(), 1500); } else { showToast(response.message || 'Failed to update status', 'error'); closeStatusModal(); } },
                error: function(xhr, status, error) { console.error("Change Status AJAX Error:", status, error, xhr.responseText); showToast('An error occurred: ' + error, 'error'); closeStatusModal(); }
            });
        }

        // --- Filtering ---\
        function filterDrivers() {
            const status = document.getElementById('statusFilter').value;
            const area = document.getElementById('areaFilter').value;
            const params = new URLSearchParams(window.location.search);
            params.set('status', status);
            params.set('area', area);
            window.location.href = `drivers.php?${params.toString()}`;
        }

        // --- Basic Client-Side Validation (Example) ---
        function validateContactNumber(contactInput, errorElement) {
             const contactValue = contactInput.value.trim();
             const pattern = /^63\d{10}$/;
             if (!pattern.test(contactValue)) {
                 $(errorElement).text('Must be 12 digits starting with 63.');
                 $(contactInput).addClass('form-field-error');
                 return false;
             }
             $(errorElement).text('');
             $(contactInput).removeClass('form-field-error');
             return true;
         }
         function validateRequired(inputElement, errorElement, fieldName) {
            const value = $(inputElement).val().trim();
            if (!value) {
                $(errorElement).text(`${fieldName} is required.`);
                $(inputElement).addClass('form-field-error');
                return false;
            }
            $(errorElement).text('');
            $(inputElement).removeClass('form-field-error');
            return true;
         }
         function validatePasswordLength(inputElement, errorElement, minLength = 6) {
             const value = $(inputElement).val();
             // Only validate if password is being entered (or required)
             if (value && value.length < minLength) {
                 $(errorElement).text(`Password must be at least ${minLength} characters.`);
                 $(inputElement).addClass('form-field-error');
                 return false;
             }
             $(errorElement).text('');
             $(inputElement).removeClass('form-field-error');
             return true;
         }


        // --- Document Ready ---
        $(document).ready(function() {
            // Close modals on overlay click
            $(document).on('click', '.overlay', function(event) { if (event.target === this) { $(this).hide(); if (this.id === 'statusModal' || this.id === 'deliveriesModal') selectedDriverId = null; } });
            // Prevent closing on content click
            $(document).on('click', '.overlay-content', function(event) { event.stopPropagation(); });

            // Live validation for contact numbers
            $('#add-contact_no').on('input blur', function() { validateContactNumber(this, '#addContactError'); });
            $('#edit-contact_no').on('input blur', function() { validateContactNumber(this, '#editContactError'); });
            // Example live validation for required fields
            $('#add-name').on('input blur', function() { validateRequired(this, '#addNameError', 'Name'); });
            $('#add-username').on('input blur', function() { validateRequired(this, '#addUsernameError', 'Username'); });
            $('#add-password').on('input blur', function() { validateRequired(this, '#addPasswordError', 'Password') && validatePasswordLength(this, '#addPasswordError'); });
            $('#edit-name').on('input blur', function() { validateRequired(this, '#editNameError', 'Name'); });
            $('#edit-username').on('input blur', function() { validateRequired(this, '#editUsernameError', 'Username'); });
            // Password validation on edit only if entered
             $('#edit-password').on('input blur', function() { if ($(this).val()) validatePasswordLength(this, '#editPasswordError'); else { $('#editPasswordError').text(''); $(this).removeClass('form-field-error'); } });


            // AJAX form submission for Add Driver
            $('#addDriverForm').on('submit', function(e) {
                e.preventDefault();
                clearFormErrors('addDriverForm'); // Clear previous errors
                let isValid = true;
                isValid &= validateRequired('#add-name', '#addNameError', 'Name');
                isValid &= validateRequired('#add-username', '#addUsernameError', 'Username');
                isValid &= validateRequired('#add-password', '#addPasswordError', 'Password');
                isValid &= validatePasswordLength('#add-password', '#addPasswordError'); // Check length only if required passed
                isValid &= validateRequired('#add-address', '#addAddressError', 'Address');
                isValid &= validateContactNumber('#add-contact_no', '#addContactError');
                // Select validation can be added if needed

                if (!isValid) { showToast('Please correct the errors.', 'warning'); return false; }

                $.ajax({
                    url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) {
                        if (response.success && response.reload) {
                            showToast(response.message || 'Driver added successfully', 'success');
                            closeAddDriverForm();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            // Display server-side errors
                            displayFormErrors('addDriverForm', response.errors);
                            showToast(response.message || 'Failed to add driver. Check errors.', 'error');
                             $('#addDriverError').text(response.errors?.general || ''); // Show general error
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Add AJAX Error:", status, error, xhr.responseText);
                        $('#addDriverError').text('An unexpected error occurred. Please try again.');
                        showToast('Request error: ' + error, 'error');
                    }
                });
            });

            // AJAX form submission for Edit Driver
            $('#editDriverForm').on('submit', function(e) {
                e.preventDefault();
                clearFormErrors('editDriverForm'); // Clear previous errors
                let isValid = true;
                isValid &= validateRequired('#edit-name', '#editNameError', 'Name');
                isValid &= validateRequired('#edit-username', '#editUsernameError', 'Username');
                // Password length validation only if password field is not empty
                if ($('#edit-password').val()) {
                    isValid &= validatePasswordLength('#edit-password', '#editPasswordError');
                }
                isValid &= validateRequired('#edit-address', '#editAddressError', 'Address');
                isValid &= validateContactNumber('#edit-contact_no', '#editContactError');
                 // Select validation can be added if needed

                if (!isValid) { showToast('Please correct the errors.', 'warning'); return false; }

                $.ajax({
                    url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) {
                        if (response.success && response.reload) {
                            showToast(response.message || 'Driver updated successfully', 'success');
                            closeEditDriverForm();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            // Display server-side errors
                            displayFormErrors('editDriverForm', response.errors);
                            showToast(response.message || 'Failed to update driver. Check errors.', 'error');
                             $('#editDriverError').text(response.errors?.general || ''); // Show general error
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Edit AJAX Error:", status, error, xhr.responseText);
                        $('#editDriverError').text('An unexpected error occurred. Please try again.');
                        showToast('Request error: ' + error, 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>