<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Admin');


$roles = [];
$roleQuery = "SELECT role_name FROM roles WHERE status = 'active'";
$result = $conn->query($roleQuery);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
}


function returnJsonResponse($success, $reload, $message = '') {
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    header('Content-Type: application/json');

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = 'Active'; 
    $created_at = date('Y-m-d H:i:s');

    $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Username already exists.');
    }
    $checkStmt->close();

    $stmt = $conn->prepare("INSERT INTO accounts (username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $role, $status, $created_at);

    if ($stmt->execute()) {
        returnJsonResponse(true, true);
    } else {
        returnJsonResponse(false, false);
    }
    $stmt->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'edit') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
    $checkStmt->bind_param("si", $username, $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        returnJsonResponse(false, false, 'Username already exists.');
    }
    $checkStmt->close();

    $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $password, $role, $id);

    if ($stmt->execute()) {
        returnJsonResponse(true, true);
    } else {
        returnJsonResponse(false, false);
    }
    $stmt->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'status') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE accounts SET status = ? WHERE id = ?");
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

$sql = "SELECT id, username, role, status, created_at FROM accounts";
if (!empty($status_filter)) {
    $sql .= " WHERE status = ?";
}
$sql .= " ORDER BY FIELD(status, 'Active', 'Reject', 'Archived'), FIELD(role, 'Admin') DESC, role ASC, username ASC";

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
    <title>Account Management</title>
    <link rel="stylesheet" href="/css/accounts.css">
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
            <h1>Account Management</h1>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Reject" <?= $status_filter == 'Reject' ? 'selected' : '' ?>>Reject</option>
                    <option value="Archived" <?= $status_filter == 'Archived' ? 'selected' : '' ?>>Archived</option>
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
                        <th>Role</th>
                        <th>Account Age</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $created_at = new DateTime($row['created_at']);
                            $now = new DateTime();
                            $diff = $created_at->diff($now);
                            if ($diff->y > 0) {
                                $account_age = $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
                            } elseif ($diff->m > 0) {
                                $account_age = $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
                            } elseif ($diff->d > 0) {
                                $account_age = $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
                            } else {
                                $account_age = "Just now";
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td>
                                    <?php 
                                    $role = ucfirst($row['role']);
                                    echo "<span class='role-label role-$row[role]'>$role</span>"; 
                                    ?>
                                </td>
                                <td><?= $account_age ?></td>
                                <td class="<?= 'status-' . strtolower($row['status'] ?? 'active') ?>">
                                    <?= htmlspecialchars($row['status'] ?? 'Active') ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditAccountForm(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>', '', '<?= $row['role'] ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Status
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-accounts">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            <div id="addAccountError" class="error-message"></div>
            <form id="addAccountForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" required>
                <label for="role">Role:</label>
                <select id="role" name="role" autocomplete="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>"><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>

                </div>
            </form>
        </div>
    </div>

    <div id="editAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-edit"></i> Edit Account</h2>
            <div id="editAccountError" class="error-message"></div>
            <form id="editAccountForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id"> 
                <label for="edit-username">Username:</label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password:</label>
                <input type="text" id="edit-password" name="password" autocomplete="new-password" required>
                <label for="edit-role">Role:</label>
                <select id="edit-role" name="role" autocomplete="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>"><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()">
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
                <button class="approve-btn" onclick="changeStatus('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <button class="reject-btn" onclick="changeStatus('Reject')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="archive-btn" onclick="changeStatus('Archived')">
                    <i class="fas fa-archive"></i> Archive
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
    <script src="/js/accounts.js"></script>
</body>
</html>