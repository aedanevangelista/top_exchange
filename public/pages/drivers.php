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
    
    $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ?");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Driver name already exists.');
    }
    $checkStmt->close();
    
    $stmt = $conn->prepare("INSERT INTO drivers (name, address, contact_no, availability) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $address, $contact_no, $availability);
    
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
    
    $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE name = ? AND id != ?");
    $checkStmt->bind_param("si", $name, $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Driver name already exists.');
    }
    $checkStmt->close();
    
    $stmt = $conn->prepare("UPDATE drivers SET name = ?, address = ?, contact_no = ?, availability = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $address, $contact_no, $availability, $id);
    
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

$status_filter = $_GET['status'] ?? '';

$sql = "SELECT id, name, address, contact_no, availability, created_at FROM drivers";
if (!empty($status_filter)) {
    $sql .= " WHERE availability = ?";
}
$sql .= " ORDER BY name ASC";

if (!empty($status_filter)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status_filter);
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
    <title>Drivers Management</title>
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/drivers.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Drivers Management</h1>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Not Available" <?= $status_filter == 'Not Available' ? 'selected' : '' ?>>Not Available</option>
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
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_no']) ?></td>
                                <td class="<?= 'status-' . strtolower(str_replace(' ', '-', $row['availability'])) ?>">
                                    <?= htmlspecialchars($row['availability']) ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditDriverForm(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['address'])) ?>', '<?= htmlspecialchars(addslashes($row['contact_no'])) ?>', '<?= $row['availability'] ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Status
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-accounts">No drivers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addDriverOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Driver</h2>
            <div id="addDriverError" class="error-message"></div>
            <form id="addDriverForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" required>
                <label for="contact_no">Contact No.:</label>
                <input type="text" id="contact_no" name="contact_no" required>
                <label for="availability">Availability:</label>
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

    <div id="editDriverOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-edit"></i> Edit Driver</h2>
            <div id="editDriverError" class="error-message"></div>
            <form id="editDriverForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id"> 
                <label for="edit-name">Name:</label>
                <input type="text" id="edit-name" name="name" required>
                <label for="edit-address">Address:</label>
                <input type="text" id="edit-address" name="address" required>
                <label for="edit-contact_no">Contact No.:</label>
                <input type="text" id="edit-contact_no" name="contact_no" required>
                <label for="edit-availability">Availability:</label>
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

    <div id="statusModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeStatus('Available')">
                    <i class="fas fa-check"></i> Available
                </button>
                <button class="reject-btn" onclick="changeStatus('Not Available')">
                    <i class="fas fa-times"></i> Not Available
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script>
    <script src="/js/drivers.js"></script>
</body>
</html>