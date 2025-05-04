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
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    header('Content-Type: application/json');

    $name = trim($_POST['name']);
    $address = $_POST['address'];
    $contact_no = $_POST['contact_no'];
    $availability = $_POST['availability'];
    $area = $_POST['area'];

    // Validate contact number length
    if (strlen($contact_no) !== 12) {
        returnJsonResponse(false, false, 'Contact number must be exactly 12 digits.');
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
    $stmt->bind_param("sssss", $name, $address, $contact_no, $availability, $area);

    if ($stmt->execute()) {
        returnJsonResponse(true, true);
    } else {
        returnJsonResponse(false, false);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'edit') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $address = $_POST['address'];
    $contact_no = $_POST['contact_no'];
    $availability = $_POST['availability'];
    $area = $_POST['area'];

    // Validate contact number length
    if (strlen($contact_no) !== 12) {
        returnJsonResponse(false, false, 'Contact number must be exactly 12 digits.');
    }

    $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ? AND id != ?");
    $checkStmt->bind_param("si", $name, $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Driver name already exists.');
    }
    $checkStmt->close();

    $stmt = $conn->prepare("UPDATE drivers SET name = ?, address = ?, contact_no = ?, availability = ?, area = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $address, $contact_no, $availability, $area, $id);

    if ($stmt->execute()) {
        returnJsonResponse(true, true);
    } else {
        returnJsonResponse(false, false);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['formType'] == 'status') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE drivers SET availability = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        returnJsonResponse(true, true);
    } else {
        returnJsonResponse(false, false, 'Failed to change status.');
    }

    $stmt->close();
    exit;
}

// =====================================================================
// ==== CORRECTED Handler for fetching driver deliveries ====
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['driver_id']) && isset($_GET['action']) && $_GET['action'] == 'get_deliveries') {
    header('Content-Type: application/json');

    $driver_id = filter_var($_GET['driver_id'], FILTER_VALIDATE_INT);
    $data = [];

    if ($driver_id === false || $driver_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Driver ID.']);
        exit;
    }

    // *** CORRECTED QUERY ***
    // Uses 'driver_assignments da' and filters by relevant order statuses
    $stmt = $conn->prepare("
        SELECT da.po_number, o.orders, o.delivery_date, o.delivery_address, o.status, o.username
        FROM driver_assignments da
        JOIN orders o ON da.po_number = o.po_number
        WHERE da.driver_id = ?
          AND o.status IN ('Active', 'For Delivery', 'In Transit') -- Show only active/ongoing deliveries
        ORDER BY o.delivery_date ASC -- Changed to ASC to show soonest first? Or DESC for latest assigned
    ");

    // Check if prepare failed
    if (!$stmt) {
        error_log("[drivers.php get_deliveries] Prepare failed: " . $conn->error);
        // Output valid JSON error for the frontend
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed. Check logs.']);
        exit;
    }

    $stmt->bind_param("i", $driver_id);

    // Check if execute failed
    if (!$stmt->execute()) {
        error_log("[drivers.php get_deliveries] Execute failed: " . $stmt->error);
         // Output valid JSON error for the frontend
        echo json_encode(['success' => false, 'message' => 'Database query execution failed. Check logs.']);
        $stmt->close();
        exit;
    }

    $result = $stmt->get_result();

    // Check if get_result failed (less common)
    if ($result === false) {
         error_log("[drivers.php get_deliveries] Get result failed: " . $stmt->error);
          // Output valid JSON error for the frontend
         echo json_encode(['success' => false, 'message' => 'Failed to retrieve query results. Check logs.']);
         $stmt->close();
         exit;
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
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

    $stmt->close();
    exit; // Important to stop script execution here
}
// =====================================================================
// ==== END CORRECTED Handler ====
// =====================================================================


// --- Fetch main list of drivers for the page display ---
$status_filter = $_GET['status'] ?? '';
$area_filter = $_GET['area'] ?? '';

$sql = "SELECT id, name, address, contact_no, availability, area, current_deliveries, created_at FROM drivers WHERE 1=1";
if (!empty($status_filter)) {
    $sql .= " AND availability = ?";
}
if (!empty($area_filter)) {
    $sql .= " AND area = ?";
}
$sql .= " ORDER BY name ASC";

if (!empty($status_filter) && !empty($area_filter)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $status_filter, $area_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif (!empty($status_filter)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif (!empty($area_filter)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $area_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers List</title>
    <link rel="stylesheet" href="/css/accounts.css"> <!-- Assuming shared styles -->
    <link rel="stylesheet" href="/css/drivers.css"> <!-- Specific driver styles -->
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        .required-asterisk {
            color: red;
            margin-left: 3px;
        }

        .error-message {
            color: red;
            margin-bottom: 10px;
            font-size: 0.9em; /* Smaller error message */
            display: block; /* Ensure it takes space */
            min-height: 1em; /* Prevent layout shift */
        }

        .form-field-error {
            border: 1px solid red !important;
        }

        .delivery-count {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            text-align: center;
            margin-right: 5px; /* Space before button */
        }

        .delivery-count-low {
            background-color: #d4edda; /* Green */
            color: #155724;
        }

        .delivery-count-medium {
            background-color: #fff3cd; /* Yellow */
            color: #856404;
        }

        .delivery-count-high {
            background-color: #f8d7da; /* Red */
            color: #721c24;
        }

        .deliveries-modal-content {
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .deliveries-table-container {
            overflow-y: auto;
            flex-grow: 1; /* Allow table container to take available space */
            margin-bottom: 15px; /* Space before footer */
        }

        .deliveries-table {
            width: 100%;
            border-collapse: collapse;
        }

        .deliveries-table th,
        .deliveries-table td {
            padding: 8px 10px; /* Slightly more padding */
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top; /* Align content to top */
        }

        .deliveries-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .no-deliveries {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .delivery-status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            color: #fff; /* White text for better contrast */
        }

        .status-pending { background-color: #ffc107; color: #333;}
        .status-active { background-color: #0d6efd; }
        .status-for-delivery { background-color: #17a2b8; } /* Teal for 'For Delivery' */
        .status-in-transit { background-color: #fd7e14; } /* Orange for 'In Transit' */
        .status-completed { background-color: #198754; }
        .status-rejected { background-color: #dc3545; }


        /* Button for seeing deliveries */
        .see-deliveries-btn {
            background-color: #6c757d; /* Grey button */
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
            vertical-align: middle; /* Align with count */
        }

        .see-deliveries-btn:hover {
            background-color: #5a6268;
        }

        /* Styles for order items expansion */
        .po-header {
            background-color: #f9f9f9;
            cursor: pointer;
        }

        .po-header:hover {
            background-color: #f0f0f0;
        }

        .order-items-row.collapsed .order-items {
            display: none;
        }

        .order-items {
             padding: 10px 15px; /* Add padding inside the cell */
             background-color: #fff; /* White background for items */
        }

        .order-items h4 {
             margin-top: 0;
             margin-bottom: 10px;
             font-size: 1em;
             color: #555;
        }


        .order-items table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }

        .order-items th,
        .order-items td {
            padding: 6px;
            text-align: left;
            border: 1px solid #eee; /* Lighter border for items table */
        }

        .order-items th {
            background-color: #f7f7f7;
            font-weight: 500;
        }

        .expand-icon {
            margin-right: 5px;
            display: inline-block;
            transition: transform 0.2s;
            width: 1em; /* Ensure icon takes space */
            text-align: center;
        }

        .po-header.collapsed .expand-icon {
            transform: rotate(-90deg);
        }

        /* Ensure modal buttons are at the bottom */
        .deliveries-modal-content .modal-buttons {
            margin-top: auto; /* Push footer to bottom */
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Drivers List</h1>
            <div class="filter-section">
                <label for="statusFilter">Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Not Available" <?= $status_filter == 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                </select>

                <label for="areaFilter">Area:</label>
                <select id="areaFilter" onchange="filterByArea()">
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
                        <th>Status</th>
                        <th>Current Deliveries</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            // Determine CSS class for delivery count
                            $deliveryClass = 'delivery-count-low';
                            if ($row['current_deliveries'] > 15) { // Example thresholds
                                $deliveryClass = 'delivery-count-high';
                            } else if ($row['current_deliveries'] > 10) {
                                $deliveryClass = 'delivery-count-medium';
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_no']) ?></td>
                                <td><?= htmlspecialchars($row['area']) ?></td>
                                <td class="<?= 'status-' . strtolower(str_replace(' ', '-', $row['availability'])) ?>">
                                    <?= htmlspecialchars($row['availability']) ?>
                                </td>
                                <td>
                                    <span class="delivery-count <?= $deliveryClass ?>">
                                        <?= $row['current_deliveries'] ?> / 20 <!-- Assuming 20 is the max -->
                                    </span>
                                    <button class="see-deliveries-btn" onclick="viewDriverDeliveries(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-truck"></i> See List
                                    </button>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditDriverForm(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['address'])) ?>', '<?= htmlspecialchars(addslashes($row['contact_no'])) ?>', '<?= htmlspecialchars($row['availability']) ?>', '<?= htmlspecialchars($row['area']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-sync-alt"></i> Status <!-- Changed icon -->
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-accounts">No drivers found matching criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Driver Modal -->
    <div id="addDriverOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Driver</h2>
            <div id="addDriverError" class="error-message"></div>
            <form id="addDriverForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <label for="name">Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="name" name="name" required>

                <label for="address">Address:<span class="required-asterisk">*</span></label>
                <input type="text" id="address" name="address" required>

                <label for="contact_no">Contact No.: (e.g., 639171234567)<span class="required-asterisk">*</span></label>
                <input type="text" id="contact_no" name="contact_no" required maxlength="12" pattern="\d{12}" title="Contact number must be exactly 12 digits starting with 63">
                <span id="contactError" class="error-message"></span>

                <label for="area">Area:<span class="required-asterisk">*</span></label>
                <select id="area" name="area" required>
                    <option value="North">North</option>
                    <option value="South">South</option>
                </select>

                <label for="availability">Availability:<span class="required-asterisk">*</span></label>
                <select id="availability" name="availability" required>
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
                <input type="text" id="edit-contact_no" name="contact_no" required maxlength="12" pattern="\d{12}" title="Contact number must be exactly 12 digits starting with 63">
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
             <span class="close-btn" onclick="closeDeliveriesModal()" style="position:absolute; top: 10px; right: 15px; font-size: 24px; cursor:pointer;">&times;</span>
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

        function openAddDriverForm() {
            document.getElementById('addDriverOverlay').style.display = 'flex';
        }

        function closeAddDriverForm() {
            document.getElementById('addDriverOverlay').style.display = 'none';
            document.getElementById('addDriverForm').reset();
            document.getElementById('addDriverError').textContent = '';
            document.getElementById('contactError').textContent = '';
            document.getElementById('contact_no').classList.remove('form-field-error');
        }

        function openEditDriverForm(id, name, address, contact_no, availability, area) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-address').value = address;
            document.getElementById('edit-contact_no').value = contact_no;
            document.getElementById('edit-availability').value = availability;
            document.getElementById('edit-area').value = area;
            document.getElementById('editDriverOverlay').style.display = 'flex';
        }

        function closeEditDriverForm() {
            document.getElementById('editDriverOverlay').style.display = 'none';
            document.getElementById('editDriverError').textContent = '';
            document.getElementById('editContactError').textContent = '';
            document.getElementById('edit-contact_no').classList.remove('form-field-error');
        }

        function openStatusModal(id, name) {
            selectedDriverId = id;
            document.getElementById('statusMessage').textContent = `Change availability status for driver: ${name}`;
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            selectedDriverId = null; // Clear selected ID
        }

        function toggleOrderItems(headerRow) {
             // Find the next row which should contain the items
             const itemsRow = headerRow.nextElementSibling;
             if (itemsRow && itemsRow.classList.contains('order-items-row')) {
                  // Toggle the 'collapsed' class on the header row
                  headerRow.classList.toggle('collapsed');
                  // Toggle display of the items row itself
                  itemsRow.style.display = headerRow.classList.contains('collapsed') ? 'none' : 'table-row';
             }
        }


        function viewDriverDeliveries(id, name) {
            selectedDriverId = id;
            document.getElementById('deliveriesModalTitle').textContent = `${name}'s Active/Pending Deliveries`;
            document.getElementById('deliveriesModal').style.display = 'flex';

            const tableBody = document.getElementById('deliveriesTableBody');
            tableBody.innerHTML = '<tr><td colspan="4" class="no-deliveries"><i class="fas fa-spinner fa-spin"></i> Loading deliveries...</td></tr>';

            // Fetch deliveries data using the GET handler within this same file
            fetch(`drivers.php?driver_id=${id}&action=get_deliveries`) // Use relative path
                .then(response => {
                    // Check if response is ok (status 200-299)
                    if (!response.ok) {
                        // Try to get text for more detailed error info
                        return response.text().then(text => {
                             console.error("Server response:", text);
                             throw new Error(`Server error: ${response.status}`);
                        });
                    }
                    // If response is OK, try to parse JSON
                    return response.json();
                })
                .then(data => {
                    console.log("Received deliveries data:", data); // Log received data
                    if (data.success && data.deliveries) {
                        if (data.deliveries.length > 0) {
                            let html = '';
                            data.deliveries.forEach((delivery, index) => {
                                // Determine status class based on delivery.status
                                let statusClass = 'status-' + (delivery.status ? delivery.status.toLowerCase().replace(' ', '-') : 'unknown');

                                // Add the main delivery row (initially collapsed)
                                // Added 'collapsed' class to the header row initially
                                // Added 'display: none;' style to the items row initially
                                html += `
                                    <tr class="po-header collapsed" onclick="toggleOrderItems(this)">
                                        <td><span class="expand-icon">▼</span> ${delivery.po_number}</td>
                                        <td>${formatDate(delivery.delivery_date)}</td>
                                        <td>${delivery.delivery_address || 'N/A'}</td>
                                        <td><span class="delivery-status-badge ${statusClass}">${delivery.status || 'Unknown'}</span></td>
                                    </tr>
                                    <tr class="order-items-row" style="display: none;">
                                        <td colspan="4" class="order-items">
                                            <h4>Order Items (${delivery.username || 'N/A'})</h4>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Packaging</th>
                                                        <th>Quantity</th>
                                                        <th>Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;

                                // Add each order item
                                if (delivery.items && Array.isArray(delivery.items) && delivery.items.length > 0) {
                                    delivery.items.forEach(item => {
                                        html += `
                                            <tr>
                                                <td>${item.item_description || 'N/A'}</td>
                                                <td>${item.packaging || 'N/A'}</td>
                                                <td>${item.quantity || 0}</td>
                                                <td>${formatCurrency(item.price)}</td>
                                            </tr>`;
                                    });
                                } else {
                                    html += `<tr><td colspan="4" style="text-align: center; color: #888;">No items details available or order data is invalid.</td></tr>`;
                                }

                                html += `
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>`;
                            });
                            tableBody.innerHTML = html;
                        } else {
                            tableBody.innerHTML = '<tr><td colspan="4" class="no-deliveries">No active or pending deliveries found for this driver.</td></tr>';
                        }
                    } else {
                         // Handle cases where success is false or deliveries array is missing
                         console.error('API reported failure or missing data:', data.message);
                         tableBody.innerHTML = `<tr><td colspan="4" class="no-deliveries">Error loading deliveries: ${data.message || 'Unknown error'}</td></tr>`;
                    }
                })
                .catch(error => {
                    // Handle fetch errors (network issue, JSON parsing failure)
                    console.error('Error fetching deliveries:', error); // Log the actual error
                    // Display user-friendly message
                    tableBody.innerHTML = '<tr><td colspan="4" class="no-deliveries">Error loading deliveries. Check console or server logs.</td></tr>';
                    // Show a toast message as well
                    showToast(`Error loading deliveries: ${error.message}`, 'error');
                });
        }


        function closeDeliveriesModal() {
            document.getElementById('deliveriesModal').style.display = 'none';
            selectedDriverId = null; // Clear selected ID
        }

        // Helper function to format date (adjust locale and options as needed)
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                 const date = new Date(dateStr);
                 // Check if date is valid
                 if (isNaN(date.getTime())) return 'Invalid Date';
                 return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) {
                 console.error("Error formatting date:", dateStr, e);
                 return 'Invalid Date';
            }
        }


        // Helper function to format currency
        function formatCurrency(amount) {
             const num = parseFloat(amount);
             if (isNaN(num)) return '₱--.--'; // Handle invalid numbers
             // Using Intl.NumberFormat for better localization if needed in future
             // return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(num);
             return '₱' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); // Basic formatting
        }


        function changeStatus(status) {
            if (selectedDriverId === null) {
                showToast('No driver selected.', 'error');
                return;
            }
            $.ajax({
                url: 'drivers.php', // Post back to the same file
                type: 'POST',
                data: {
                    ajax: true,
                    formType: 'status',
                    id: selectedDriverId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Status updated successfully', 'success');
                        setTimeout(function() {
                            window.location.reload(); // Reload page to see changes
                        }, 1500); // Shorter delay
                    } else {
                        showToast(response.message || 'Failed to update status', 'error');
                    }
                    closeStatusModal(); // Close modal regardless of success/failure
                },
                error: function(xhr, status, error) {
                     console.error("Change Status AJAX Error:", status, error, xhr.responseText);
                    showToast('An error occurred: ' + error, 'error');
                    closeStatusModal();
                }
            });
        }

        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const area = document.getElementById('areaFilter').value;
            window.location.href = `drivers.php?status=${status}&area=${area}`; // Ensure filename is correct
        }

        function filterByArea() {
            const status = document.getElementById('statusFilter').value;
            const area = document.getElementById('areaFilter').value;
            window.location.href = `drivers.php?status=${status}&area=${area}`; // Ensure filename is correct
        }

        function validateContactNumber(contactInput, errorElement) {
            const contactValue = contactInput.value.trim();
             // Updated pattern: Starts with 63, followed by 10 digits. Total 12 digits.
             const pattern = /^63\d{10}$/;
            if (!pattern.test(contactValue)) {
                 errorElement.textContent = 'Contact number must be 12 digits starting with 63.';
                 contactInput.classList.add('form-field-error');
                 return false;
            }
            errorElement.textContent = ''; // Clear error
            contactInput.classList.remove('form-field-error');
            return true;
        }


        $(document).ready(function() {
            // Handle click events outside modals to close them
            $(document).on('click', '.overlay', function(event) {
                // Only close if the click is directly on the overlay background
                if (event.target === this) {
                    $(this).hide(); // Hide the clicked overlay
                    // Optional: Clear specific states if needed when closing certain modals
                    if (this.id === 'statusModal' || this.id === 'deliveriesModal') {
                         selectedDriverId = null;
                    }
                }
            });

            // Prevent closing when clicking inside the modal content
            $(document).on('click', '.overlay-content', function(event) {
                 event.stopPropagation();
            });


            // Validate contact number on input/blur for Add form
            $('#contact_no').on('input blur', function() {
                validateContactNumber(this, document.getElementById('contactError'));
            });

            // Validate contact number on input/blur for Edit form
            $('#edit-contact_no').on('input blur', function() {
                validateContactNumber(this, document.getElementById('editContactError'));
            });

            // AJAX form submission for Add Driver
            $('#addDriverForm').on('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Final validation check before submitting
                if (!validateContactNumber(document.getElementById('contact_no'), document.getElementById('contactError'))) {
                    showToast('Please correct the errors before saving.', 'warning');
                    return false; // Stop submission if invalid
                }

                const formData = $(this).serialize() + '&ajax=true'; // Add ajax flag

                $.ajax({
                    url: 'drivers.php', // Post back to the same file
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.reload) {
                            showToast('Driver added successfully', 'success');
                            closeAddDriverForm();
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                             $('#addDriverError').text(response.message || 'Error adding driver. Please try again.');
                             showToast(response.message || 'Error adding driver.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                         console.error("Add Driver AJAX Error:", status, error, xhr.responseText);
                         $('#addDriverError').text('An error occurred. Please check console or try again.');
                         showToast('An error occurred: ' + error, 'error');
                    }
                });
            });

            // AJAX form submission for Edit Driver
            $('#editDriverForm').on('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                 // Final validation check before submitting
                if (!validateContactNumber(document.getElementById('edit-contact_no'), document.getElementById('editContactError'))) {
                     showToast('Please correct the errors before saving.', 'warning');
                    return false; // Stop submission if invalid
                }

                const formData = $(this).serialize() + '&ajax=true'; // Add ajax flag

                $.ajax({
                    url: 'drivers.php', // Post back to the same file
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.reload) {
                            showToast('Driver updated successfully', 'success');
                            closeEditDriverForm();
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $('#editDriverError').text(response.message || 'Error updating driver. Please try again.');
                             showToast(response.message || 'Error updating driver.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                         console.error("Edit Driver AJAX Error:", status, error, xhr.responseText);
                         $('#editDriverError').text('An error occurred. Please check console or try again.');
                         showToast('An error occurred: ' + error, 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>