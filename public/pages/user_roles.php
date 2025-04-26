<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

checkRole('User Roles'); // Ensure the user has access to the User Roles page

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch roles and pages
$roles_query = "SELECT * FROM roles ORDER BY (role_name = 'admin') DESC, role_name ASC";
$roles_result = $conn->query($roles_query);
if (!$roles_result) {
    die("Error retrieving roles: " . $conn->error);
}

$pages_query = "SELECT * FROM pages ORDER BY page_name";
$pages_result = $conn->query($pages_query);
if (!$pages_result) {
    die("Error retrieving pages: " . $conn->error);
}

// Capture error messages
$errorMessage = "";
if (isset($_GET['error'])) {
    $errors = [
        'duplicate' => "Role name already exists. Please choose a different name.",
        'restricted' => "The 'admin' role cannot be modified.",
        'default' => "An unexpected error occurred."
    ];
    $errorMessage = $errors[$_GET['error']] ?? $errors['default'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
    <link rel="stylesheet" href="/css/user_roles.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>User Roles Management</h1>
            <button onclick="showRoleForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Role
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Accessible Pages</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($role['role_name']) ?></td>
                            <td><?= htmlspecialchars($role['pages'] ?? 'None') ?></td>
                            <td class="<?= $role['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= htmlspecialchars(ucfirst($role['status'])) ?>
                            </td>
                            <td class="action-buttons">
                                <?php if ($role['role_name'] !== 'admin'): ?>
                                    <button type="button" class="edit-btn"
                                        onclick="showRoleForm('<?= $role['role_id'] ?>', '<?= htmlspecialchars($role['role_name']) ?>', '<?= htmlspecialchars($role['pages']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" action="../../backend/roles/manage_roles.php" style="display:inline;">
                                        <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                        <input type="hidden" name="action" value="<?= $role['status'] == 'active' ? 'archive' : 'activate' ?>">
                                        <button type="submit" class="<?= $role['status'] == 'active' ? 'archive-btn' : 'activate-btn' ?>">
                                            <i class="fas fa-<?= $role['status'] == 'active' ? 'archive' : 'check' ?>"></i> <?= ucfirst($role['status'] == 'active' ? 'Archive' : 'Activate') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="roleOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2 id="roleFormTitle"><i class="fas fa-user-plus"></i> Add Role</h2>
            <p id="roleError" style="color: red; display: <?= $errorMessage ? 'block' : 'none' ?>;"><?= $errorMessage ?></p>
            <form id="roleForm" method="POST" action="../../backend/roles/manage_roles.php" class="account-form">
                <input type="hidden" id="actionType" name="action" value="add">
                <input type="hidden" id="roleId" name="role_id">
                <label for="roleName">Role Name:</label>
                <input type="text" id="roleName" name="role_name" required>
                <br/>
                <label>Accessible Pages:</label>
                <div class="checkbox-container">
                    <?php while ($page = $pages_result->fetch_assoc()): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="page_ids[]" value="<?= $page['page_name'] ?>"> <?= htmlspecialchars($page['page_name']) ?>
                        </label>
                    <?php endwhile; ?>
                </div>
                <br/>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="hideRoleForm()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script src="/js/user_roles.js"></script>
</body>
</html>