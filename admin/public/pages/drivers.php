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

function returnJsonResponse($success, $reload, $message = '') {
    // Ensure JSON header is set ONLY when outputting JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}

// --- Process form submissions (Add, Edit, Status Change) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    // Set header here as this block exclusively outputs JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $name = trim($_POST['name']);
    $address = $_POST['address'];
    $contact_no = $_POST['contact_no'];
    $availability = $_POST['availability'];
    $area = $_POST['area'];

    // Validate contact number length and format (starts with 63, total 12 digits)
    if (!preg_match('/^63\d{10}$/', $contact_no)) {
        returnJsonResponse(false, false, 'Contact number must be 12 digits starting with 63.');
    }

    $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ?");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Driver name already exists.');
    }
    $checkStmt->close();

    $stmt = $conn->prepare("INSERT INTO drivers (name, address, contact_no, availability, area, current_deliveries) VALUES (?, ?, ?, ?, ?, 0)");
    // Use $stmtAdd for clarity to avoid conflict with main list $stmt
    $stmtAdd = $conn->prepare("INSERT INTO drivers (name, address, contact_no, availability, area, current_deliveries) VALUES (?, ?, ?, ?, ?, 0)");
    if (!$stmtAdd) { // Check prepare result
         error_log("Add Driver Prepare Error: " . $conn->error);
         returnJsonResponse(false, false, 'Failed to prepare statement. Database error.');
    }
    $stmtAdd->bind_param("sssss", $name, $address, $contact_no, $availability, $area);

    if ($stmtAdd->execute()) {
        returnJsonResponse(true, true); // Success, trigger reload
    } else {
        // Provide specific error if possible, otherwise generic
        error_log("Add Driver DB Error: " . $stmtAdd->error);
        returnJsonResponse(false, false, 'Failed to add driver. Database error.');
    }
    $stmtAdd->close();
    exit; // Ensure exit after handling POST
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'edit') {
    // Set header here as this block exclusively outputs JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $address = $_POST['address'];
    $contact_no = $_POST['contact_no'];
    $availability = $_POST['availability'];
    $area = $_POST['area'];

    // Validate contact number length and format
    if (!preg_match('/^63\d{10}$/', $contact_no)) {
        returnJsonResponse(false, false, 'Contact number must be 12 digits starting with 63.');
    }

    // Check for existing name (excluding current driver)
    $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ? AND id != ?");
    if (!$checkStmt) {
         error_log("Edit Driver Check Prepare Error: " . $conn->error);
         returnJsonResponse(false, false, 'Database error checking name.');
    }
    $checkStmt->bind_param("si", $name, $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->close(); // Close check statement here
        returnJsonResponse(false, false, 'Driver name already exists.');
    }
    $checkStmt->close(); // Also close if no rows found

    // Prepare update statement
    // Use $stmtEdit for clarity
    $stmtEdit = $conn->prepare("UPDATE drivers SET name = ?, address = ?, contact_no = ?, availability = ?, area = ? WHERE id = ?");
    if (!$stmtEdit) {
        error_log("Edit Driver Prepare Error: " . $conn->error);
        returnJsonResponse(false, false, 'Failed to prepare update. Database error.');
    }
    $stmtEdit->bind_param("sssssi", $name, $address, $contact_no, $availability, $area, $id);

    if ($stmtEdit->execute()) {
        returnJsonResponse(true, true); // Success, trigger reload
    } else {
        error_log("Edit Driver DB Error: " . $stmtEdit->error);
        returnJsonResponse(false, false, 'Failed to update driver. Database error.');
    }
    $stmtEdit->close();
    exit; // Ensure exit after handling POST
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'status') {
    // Set header here as this block exclusively outputs JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $id = $_POST['id'];
    $status = $_POST['status']; // Should be 'Available' or 'Not Available'

    // Basic validation
    if (!in_array($status, ['Available', 'Not Available'])) {
         returnJsonResponse(false, false, 'Invalid status provided.');
    }

    // Use $stmtStatus for clarity
    $stmtStatus = $conn->prepare("UPDATE drivers SET availability = ? WHERE id = ?");
    if (!$stmtStatus) {
         error_log("Change Driver Status Prepare Error: " . $conn->error);
         returnJsonResponse(false, false, 'Failed to prepare status update.');
    }
    $stmtStatus->bind_param("si", $status, $id);

    if ($stmtStatus->execute()) {
        returnJsonResponse(true, true); // Success, trigger reload
    } else {
        error_log("Change Driver Status DB Error: " . $stmtStatus->error);
        returnJsonResponse(false, false, 'Failed to change status. Database error.');
    }

    $stmtStatus->close();
    exit; // Ensure exit after handling POST
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

    // Use $stmtModal for clarity
    $stmtModal = $conn->prepare("
        SELECT da.po_number, o.orders, o.delivery_date, o.delivery_address, o.status, o.username
        FROM driver_assignments da
        JOIN orders o ON da.po_number = o.po_number
        WHERE da.driver_id = ?
          AND o.status IN ('Active', 'For Delivery', 'In Transit') -- Show only active/ongoing deliveries
        ORDER BY o.delivery_date ASC -- Show soonest delivery date first
    ");

    // Check if prepare failed
    if (!$stmtModal) {
        error_log("[drivers.php get_deliveries] Prepare failed: " . $conn->error);
        // Output valid JSON error for the frontend
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed. Check logs.']);
        exit;
    }

    $stmtModal->bind_param("i", $driver_id);

    // Check if execute failed
    if (!$stmtModal->execute()) {
        error_log("[drivers.php get_deliveries] Execute failed: " . $stmtModal->error);
         // Output valid JSON error for the frontend
        echo json_encode(['success' => false, 'message' => 'Database query execution failed. Check logs.']);
        $stmtModal->close();
        exit;
    }

    $resultModal = $stmtModal->get_result(); // Use $resultModal

    // Check if get_result failed
    if ($resultModal === false) {
         error_log("[drivers.php get_deliveries] Get result failed: " . $stmtModal->error);
          // Output valid JSON error for the frontend
         echo json_encode(['success' => false, 'message' => 'Failed to retrieve query results. Check logs.']);
         $stmtModal->close();
         exit;
    }

    if ($resultModal->num_rows > 0) {
        while ($row = $resultModal->fetch_assoc()) {
            // Parse the JSON orders data with error checking
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
                'items' => $orderItems ?? [] // Use null coalescing for safety
            ];
        }
        echo json_encode(['success' => true, 'deliveries' => $data]);
    } else {
        // No rows found is a valid success case, just return empty array
        echo json_encode(['success' => true, 'deliveries' => []]);
    }

    $stmtModal->close();
    exit; // Important to stop script execution here
}


// --- Fetch main list of drivers for the page display ---
// (This part runs only if it's a GET request without action=get_deliveries or a non-AJAX POST)
$status_filter = $_GET['status'] ?? '';
$area_filter = $_GET['area'] ?? '';
$params = [];
$types = "";
$main_list_result = null; // Initialize result variable

// Query to fetch drivers and calculate their active deliveries
$sql = "SELECT
            d.id, d.name, d.address, d.contact_no, d.availability, d.area, d.created_at,
            COALESCE(COUNT(DISTINCT o.po_number), 0) AS calculated_deliveries -- Calculate the count of relevant orders
        FROM
            drivers d
        LEFT JOIN
            driver_assignments da ON d.id = da.driver_id
        LEFT JOIN
            orders o ON da.po_number = o.po_number AND o.status IN ('Active', 'For Delivery', 'In Transit') -- Filter orders *before* counting
        WHERE
            1=1"; // Start WHERE clause

// Add filters for the main drivers table
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

$sql .= " GROUP BY d.id, d.name, d.address, d.contact_no, d.availability, d.area, d.created_at"; // Group by all selected non-aggregated driver columns
$sql .= " ORDER BY d.name ASC";


// Use $stmtMain for the main list query
$stmtMain = $conn->prepare($sql);
if (!$stmtMain) {
    error_log("Prepare failed for main driver list: " . $conn->error);
    die("Error preparing driver list. Please check server logs."); // Simple error for now
}

// Bind parameters if any filters were applied
if (!empty($types)) {
    // Add error check for bind_param
    if (!$stmtMain->bind_param($types, ...$params)) {
         error_log("Bind param failed for main driver list: " . $stmtMain->error);
         $stmtMain->close(); // Close on error
         die("Error binding parameters. Please check server logs.");
    }
}

// Add error check for execute
if(!$stmtMain->execute()) {
    error_log("Execute failed for main driver list: " . $stmtMain->error);
    $stmtMain->close(); // Close on error
    die("Error executing driver list query. Please check server logs.");
}

$main_list_result = $stmtMain->get_result(); // Assign to $main_list_result

// Add error check for get_result
if ($main_list_result === false) {
    error_log("Get result failed for main driver list: " . $stmtMain->error);
    $stmtMain->close(); // Close on error
    die("Error retrieving driver list results. Please check server logs.");
}
// $main_list_result now holds the data for the main table display
// $stmtMain is still open here and will be closed after the loop/else block
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
        .overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center; /* This was likely missing or incomplete in your code */
        }        
        
        .overlay-content { background-color: #fefefe; margin: auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .overlay-content h2 { margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 1.5rem; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .account-form label { display: block; margin-bottom: 5px; font-weight: 500; }
        .account-form input[type="text"], .account-form select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-buttons { text-align: right; margin-top: 20px; }
        .form-buttons button { margin-left: 10px; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .cancel-btn { background-color: #6c757d; color: white; }
        .save-btn, .approve-btn { background-color: #28a745; color: white; }
        .reject-btn { background-color: #dc3545; color: white; }
        .modal-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
        .modal-buttons.single-button { justify-content: flex-end; }
        .required-asterisk { color: red; margin-left: 3px; }
        .error-message { color: red; margin-bottom: 10px; font-size: 0.9em; display: block; min-height: 1em; }
        .form-field-error { border: 1px solid red !important; }

        /* Styles for Delivery Count and List */
        .delivery-count { font-weight: bold; padding: 3px 8px; border-radius: 10px; display: inline-block; text-align: center; margin-right: 5px; min-width: 40px; }
        .delivery-count-low { background-color: #d4edda; color: #155724; }
        .delivery-count-medium { background-color: #fff3cd; color: #856404; }
        .delivery-count-high { background-color: #f8d7da; color: #721c24; }
        .see-deliveries-btn { background-color: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; font-size: 14px; vertical-align: middle; }
        .see-deliveries-btn:hover { background-color: #5a6268; }
        .status-available { color: #155724; font-weight: bold; } /* From your original */
        .status-not-available { color: #721c24; font-weight: bold; } /* From your original */

        /* Deliveries Modal Specific */
        .deliveries-modal-content {
            max-width: 900px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            margin: auto; /* Make sure this is present */
            position: relative; /* Ensure proper positioning */
            top: 0; /* Reset any top positioning */
            left: 0; /* Reset any left positioning */
        }        
        
        .deliveries-table-container { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        .deliveries-table { width: 100%; border-collapse: collapse; }
        .deliveries-table th, .deliveries-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        .deliveries-table th { background-color: #f2f2f2; position: sticky; top: 0; z-index: 1; }
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
        .order-items { padding: 10px 15px; background-color: #fff; border-left: 3px solid #17a2b8; } /* Indent items */
        .order-items h4 { margin-top: 0; margin-bottom: 10px; font-size: 1em; color: #555; }
        .order-items table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .order-items th, .order-items td { padding: 6px; text-align: left; border: 1px solid #eee; }
        .order-items th { background-color: #f7f7f7; font-weight: 500; }
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
                        <th>Address</th>
                        <th>Contact No.</th>
                        <th>Area</th>
                        <th>Availability</th>
                        <th>Active Deliveries</th> <!-- Renamed Header -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Use the result fetched earlier ($main_list_result)
                    if ($main_list_result && $main_list_result->num_rows > 0):
                        while ($row = $main_list_result->fetch_assoc()):
                            // Use calculated count for display
                            $active_delivery_count = $row['calculated_deliveries'];

                            // Determine CSS class for delivery count based on calculated count
                            $deliveryClass = 'delivery-count-low';
                            if ($active_delivery_count > 15) { // Example thresholds
                                $deliveryClass = 'delivery-count-high';
                            } else if ($active_delivery_count > 10) {
                                $deliveryClass = 'delivery-count-medium';
                            }
                            // Determine CSS class for availability status
                            $availability_class = 'status-' . strtolower(str_replace(' ', '-', $row['availability']));
                    ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_no']) ?></td>
                                <td><?= htmlspecialchars($row['area']) ?></td>
                                <td class="<?= $availability_class ?>">
                                    <?= htmlspecialchars($row['availability']) ?>
                                </td>
                                <td>
                                    <!-- Display calculated count -->
                                    <span class="delivery-count <?= $deliveryClass ?>">
                                        <?= $active_delivery_count ?> / 20 <!-- Assuming 20 is the max -->
                                    </span>
                                    <button class="see-deliveries-btn" onclick="viewDriverDeliveries(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-list-ul"></i> See List <!-- Changed Icon/Text -->
                                    </button>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditDriverForm(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['address'])) ?>', '<?= htmlspecialchars(addslashes($row['contact_no'])) ?>', '<?= htmlspecialchars($row['availability']) ?>', '<?= htmlspecialchars($row['area']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-toggle-on"></i> Status <!-- Changed icon -->
                                    </button>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    else: // No rows found
                    ?>
                        <tr>
                            <td colspan="7" class="no-accounts">No drivers found matching criteria.</td>
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
            <div id="addDriverError" class="error-message"></div>
            <form id="addDriverForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <label for="add-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-name" name="name" required>

                <label for="add-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-address" name="address" required>

                <label for="add-contact_no">Contact No.: (e.g., 639171234567)<span class="required-asterisk">*</span></label>
                <input type="text" id="add-contact_no" name="contact_no" required maxlength="12" pattern="^63\d{10}$" title="Must be 12 digits starting with 63">
                <span id="contactError" class="error-message"></span>

                <label for="add-area">Area:<span class="required-asterisk">*</span></label>
                <select id="add-area" name="area" required>
                    <option value="North">North</option>
                    <option value="South">South</option>
                </select>

                <label for="add-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="add-availability" name="availability" required>
                    <option value="Available">Available</option>
                    <option value="Not Available">Not Available</option>
                </select>

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
            <div id="editDriverError" class="error-message"></div>
            <form id="editDriverForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id">

                <label for="edit-name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-name" name="name" required>

                <label for="edit-address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-address" name="address" required>

                <label for="edit-contact_no">Contact No.: (e.g., 639171234567)<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-contact_no" name="contact_no" required maxlength="12" pattern="^63\d{10}$" title="Must be 12 digits starting with 63">
                <span id="editContactError" class="error-message"></span>

                <label for="edit-area">Area:<span class="required-asterisk">*</span></label>
                <select id="edit-area" name="area" required>
                    <option value="North">North</option>
                    <option value="South">South</option>
                </select>

                <label for="edit-availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="edit-availability" name="availability" required>
                    <option value="Available">Available</option>
                    <option value="Not Available">Not Available</option>
                </select>

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
                        <!-- Deliveries loaded here -->
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

        // --- Modal Control Functions ---
        function openAddDriverForm() { $('#addDriverOverlay').css('display', 'flex'); }
        function closeAddDriverForm() { $('#addDriverOverlay').hide(); $('#addDriverForm')[0].reset(); $('#addDriverError, #contactError').text(''); $('#add-contact_no').removeClass('form-field-error'); }
        function openEditDriverForm(id, name, address, contact_no, availability, area) { $('#edit-id').val(id); $('#edit-name').val(name); $('#edit-address').val(address); $('#edit-contact_no').val(contact_no); $('#edit-availability').val(availability); $('#edit-area').val(area); $('#editDriverOverlay').css('display', 'flex'); }
        function closeEditDriverForm() { $('#editDriverOverlay').hide(); $('#editDriverError, #editContactError').text(''); $('#edit-contact_no').removeClass('form-field-error');}
        function openStatusModal(id, name) { selectedDriverId = id; $('#statusMessage').text(`Change availability status for driver: ${name}`); $('#statusModal').css('display', 'flex'); }
        function closeStatusModal() { $('#statusModal').hide(); selectedDriverId = null; }
        function closeDeliveriesModal() { $('#deliveriesModal').hide(); selectedDriverId = null; }

        // --- Deliveries Modal Logic ---
        function toggleOrderItems(headerRow) {
             const itemsRow = headerRow.nextElementSibling;
             if (itemsRow && itemsRow.classList.contains('order-items-row')) {
                  headerRow.classList.toggle('collapsed');
                  // Toggle display using jQuery for consistency
                  $(itemsRow).toggle(!headerRow.classList.contains('collapsed'));
             }
        }
        function formatDate(dateStr) { if (!dateStr) return 'N/A'; try { const date = new Date(dateStr); if (isNaN(date.getTime())) return 'Invalid Date'; return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }); } catch (e) { console.error("Error formatting date:", dateStr, e); return 'Invalid Date'; } }
        function formatCurrency(amount) { const num = parseFloat(amount); if (isNaN(num)) return '₱--.--'; return '₱' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
        function viewDriverDeliveries(id, name) {
            selectedDriverId = id;
            $('#deliveriesModalTitle').text(`${name}'s Active/Pending Deliveries`);
            $('#deliveriesModal').css('display', 'flex');
            const tableBody = $('#deliveriesTableBody');
            tableBody.html('<tr><td colspan="4" class="no-deliveries"><i class="fas fa-spinner fa-spin"></i> Loading deliveries...</td></tr>');

            // Fetch using the GET handler in this same file
            fetch(`drivers.php?driver_id=${id}&action=get_deliveries`)
                .then(response => { if (!response.ok) { return response.text().then(text => { console.error("Server response:", text); throw new Error(`Server error: ${response.status}`); }); } return response.json(); })
                .then(data => {
                    console.log("Received deliveries data:", data);
                    if (data.success && data.deliveries) {
                        if (data.deliveries.length > 0) {
                            let html = '';
                            data.deliveries.forEach((delivery) => {
                                let statusClass = 'status-' + (delivery.status ? delivery.status.toLowerCase().replace(/\s+/g, '-') : 'unknown');
                                // Ensure rows start collapsed and items row is hidden
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
                .catch(error => { console.error('Error fetching deliveries:', error); tableBody.html('<tr><td colspan="4" class="no-deliveries">Error loading deliveries. Check console.</td></tr>'); showToast(`Error loading deliveries: ${error.message}`, 'error'); });
        }

        // --- Status Change AJAX ---
        function changeStatus(status) {
            if (selectedDriverId === null) { showToast('No driver selected.', 'error'); return; }
            $.ajax({
                url: 'drivers.php', type: 'POST',
                data: { ajax: true, formType: 'status', id: selectedDriverId, status: status },
                dataType: 'json',
                success: function(response) { if (response.success && response.reload) { showToast('Status updated successfully', 'success'); setTimeout(() => window.location.reload(), 1500); } else { showToast(response.message || 'Failed to update status', 'error'); } closeStatusModal(); },
                error: function(xhr, status, error) { console.error("Change Status AJAX Error:", status, error, xhr.responseText); showToast('An error occurred: ' + error, 'error'); closeStatusModal(); }
            });
        }

        // --- Filtering ---
        function filterDrivers() {
            const status = document.getElementById('statusFilter').value;
            const area = document.getElementById('areaFilter').value;
            const params = new URLSearchParams(window.location.search);
            params.set('status', status);
            params.set('area', area);
            window.location.href = `drivers.php?${params.toString()}`;
        }

        // --- Validation ---
        function validateContactNumber(contactInput, errorElement) {
             const contactValue = contactInput.value.trim();
             const pattern = /^63\d{10}$/; // Starts with 63, total 12 digits
             if (!pattern.test(contactValue)) {
                 errorElement.textContent = 'Must be 12 digits starting with 63.';
                 contactInput.classList.add('form-field-error');
                 return false;
             }
             errorElement.textContent = '';
             contactInput.classList.remove('form-field-error');
             return true;
         }

        // --- Document Ready ---
        $(document).ready(function() {
            // Close modals on overlay click
            $(document).on('click', '.overlay', function(event) { if (event.target === this) { $(this).hide(); if (this.id === 'statusModal' || this.id === 'deliveriesModal') selectedDriverId = null; } });
            // Prevent closing on content click
            $(document).on('click', '.overlay-content', function(event) { event.stopPropagation(); });

            // Live validation for contact numbers
            $('#add-contact_no').on('input blur', function() { validateContactNumber(this, document.getElementById('contactError')); });
            $('#edit-contact_no').on('input blur', function() { validateContactNumber(this, document.getElementById('editContactError')); });

            // AJAX form submission for Add Driver
            $('#addDriverForm').on('submit', function(e) {
                e.preventDefault();
                const contactInput = document.getElementById('add-contact_no'); // Use specific ID
                if (!validateContactNumber(contactInput, document.getElementById('contactError'))) { showToast('Please correct errors.', 'warning'); return false; }
                $.ajax({
                    url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) { if (response.success && response.reload) { showToast('Driver added', 'success'); closeAddDriverForm(); setTimeout(() => window.location.reload(), 1500); } else { $('#addDriverError').text(response.message || 'Error adding.'); showToast(response.message || 'Error adding.', 'error'); } },
                    error: function(xhr, status, error) { console.error("Add AJAX Error:", status, error, xhr.responseText); $('#addDriverError').text('Request error.'); showToast('Request error: ' + error, 'error'); }
                });
            });

            // AJAX form submission for Edit Driver
            $('#editDriverForm').on('submit', function(e) {
                e.preventDefault();
                const contactInput = document.getElementById('edit-contact_no'); // Use specific ID
                if (!validateContactNumber(contactInput, document.getElementById('editContactError'))) { showToast('Please correct errors.', 'warning'); return false; }
                $.ajax({
                    url: 'drivers.php', type: 'POST', data: $(this).serialize() + '&ajax=true', dataType: 'json',
                    success: function(response) { if (response.success && response.reload) { showToast('Driver updated', 'success'); closeEditDriverForm(); setTimeout(() => window.location.reload(), 1500); } else { $('#editDriverError').text(response.message || 'Error updating.'); showToast(response.message || 'Error updating.', 'error'); } },
                    error: function(xhr, status, error) { console.error("Edit AJAX Error:", status, error, xhr.responseText); $('#editDriverError').text('Request error.'); showToast('Request error: ' + error, 'error'); }
                });
            });
        });
    </script>
</body>
</html>