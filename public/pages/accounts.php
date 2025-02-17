<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole(['admin']); // Only admins can access

// Handle form submission (Add Account)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    header('Content-Type: application/json');

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $created_at = date('Y-m-d H:i:s');

    $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo json_encode(['success' => false, 'reload' => false]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    $stmt = $conn->prepare("INSERT INTO accounts (username, password, role, created_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $password, $role, $created_at);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'reload' => false]);
    }
    $stmt->close();
    exit;
}

// Handle form submission (Edit Account)
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
        echo json_encode(['success' => false, 'reload' => false]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $password, $role, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'reload' => false]);
    }
    $stmt->close();
    exit;
}

$sql = "SELECT id, username, role, created_at FROM accounts ORDER BY id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <link rel="stylesheet" href="/top_exchange/public/css/accounts.css">
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Account Management</h1>
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Account Age</th>
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
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td>
                                    <?php 
                                    $role = ucfirst($row['role']);
                                    echo "<span class='role-label role-$row[role]'>$role</span>"; 
                                    ?>
                                </td>
                                <td><?= $account_age ?></td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditAccountForm(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>', '<?= $row['role'] ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="delete-btn" onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>')">
                                        <i class="fas fa-trash"></i> Delete
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

    <!-- Overlay Form for Adding New Account -->
    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            <p id="addAccountError" class="error-message"></p>
            <form id="addAccountForm" method="POST" class="account-form">
                <input type="hidden" name="formType" value="add">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" required>
                <label for="role">Role:</label>
                <select id="role" name="role" autocomplete="role" required>
                    <option value="admin">Admin</option>
                    <option value="secretary">Secretary</option>
                    <option value="client">Client</option>
                </select>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Form for Editing Account -->
    <div id="editAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-edit"></i> Edit Account</h2>
            <p id="editAccountError" class="error-message"></p>
            <form id="editAccountForm" method="POST" class="account-form">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username:</label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password:</label>
                <input type="text" id="edit-password" name="password" autocomplete="new-password" required>
                <label for="edit-role">Role:</label>
                <select id="edit-role" name="role" autocomplete="role" required>
                    <option value="admin">Admin</option>
                    <option value="secretary">Secretary</option>
                    <option value="client">Client</option>
                </select>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Modal for Delete Confirmation -->
    <div id="deleteModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
            <p id="deleteMessage"></p>
            <div class="modal-buttons">
                <button class="confirm-btn" onclick="confirmDeletion()">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="cancel-btn" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="/top_exchange/public/js/accounts.js"></script>
</body>
</html>