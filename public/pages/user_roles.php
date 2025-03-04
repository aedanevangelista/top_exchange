<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

checkRole('User Roles'); // Ensure the user has access to the User Roles page

$roles = $conn->query("SELECT * FROM roles ORDER BY (role_name = 'admin') DESC, role_name ASC");
$pages = $conn->query("SELECT * FROM pages ORDER BY page_name");

// Capture error messages only after form submission
$errorMessage = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'duplicate':
            $errorMessage = "Role name already exists. Please choose a different name.";
            break;
        case 'restricted':
            $errorMessage = "The 'admin' role cannot be modified.";
            break;
        default:
            $errorMessage = "An unexpected error occurred.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Management</title>
    <link rel="stylesheet" href="../css/user_roles.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>User Roles Management</h1>
            <button onclick="openAddRoleForm()" class="add-account-btn">
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
                    <?php while ($role = $roles->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($role['role_name']) ?></td>
                            <td><?= htmlspecialchars($role['pages'] ?? 'None') ?></td>
                            <td class="<?= $role['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= htmlspecialchars(ucfirst($role['status'])) ?>
                            </td>
                            <td class="action-buttons">
                                <?php if ($role['role_name'] !== 'admin'): ?>
                                    <button type="button" class="edit-btn"
                                        onclick="openEditRoleForm('<?= $role['role_id'] ?>', '<?= htmlspecialchars($role['role_name']) ?>', '<?= htmlspecialchars($role['pages']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <form method="POST" action="../../backend/roles/manage_roles.php" style="display:inline;">
                                        <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                        <?php if ($role['status'] == 'active'): ?>
                                            <input type="hidden" name="action" value="archive">
                                            <button type="submit" class="archive-btn">
                                                <i class="fas fa-archive"></i> Archive
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="activate-btn">
                                                <i class="fas fa-check"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overlay Form for Adding/Editing Role -->
    <div id="roleOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2 id="roleFormTitle"><i class="fas fa-user-plus"></i> Add Role</h2>
            <p id="roleError" style="color: red; display: <?= $errorMessage ? 'block' : 'none' ?>;">
                <?= $errorMessage ?>
            </p>
            <form id="roleForm" method="POST" action="../../backend/roles/manage_roles.php" class="account-form">
                <input type="hidden" id="actionType" name="action" value="add">
                <input type="hidden" id="roleId" name="role_id">
                <label for="roleName">Role Name:</label>
                <input type="text" id="roleName" name="role_name" required>
                <br/>
                <label>Accessible Pages:</label>
                <div class="checkbox-container">
                    <?php while ($page = $pages->fetch_assoc()): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="page_ids[]" value="<?= $page['page_name'] ?>"> <?= htmlspecialchars($page['page_name']) ?>
                        </label>
                    <?php endwhile; ?>
                </div>
                <br/>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeRoleForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddRoleForm() {
            document.getElementById("roleFormTitle").innerHTML = '<i class="fas fa-user-plus"></i> Add Role';
            document.getElementById("actionType").value = "add";
            document.getElementById("roleId").value = "";
            document.getElementById("roleName").value = "";
            document.getElementById("roleError").style.display = "none";
            document.getElementById("roleOverlay").style.display = "block";
        }

        function openEditRoleForm(roleId, roleName, pages) {
            document.getElementById("roleFormTitle").innerHTML = '<i class="fas fa-edit"></i> Edit Role';
            document.getElementById("actionType").value = "edit";
            document.getElementById("roleId").value = roleId;
            document.getElementById("roleName").value = roleName;
            document.getElementById("roleError").style.display = "none"; // Hide error on opening

            document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
                checkbox.checked = false;
            });

            if (pages) {
                let pageArray = pages.split(", ");
                document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
                    if (pageArray.includes(checkbox.value)) {
                        checkbox.checked = true;
                    }
                });
            }
            document.getElementById("roleOverlay").style.display = "block";
        }

        function closeRoleForm() {
            document.getElementById("roleOverlay").style.display = "none";
        }
    </script>
</body>
</html>
