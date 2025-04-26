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

// Fetch pages with their module information
$pages_query = "SELECT p.*, m.module_name, m.module_id 
                FROM pages p 
                LEFT JOIN modules m ON p.module_id = m.module_id 
                ORDER BY m.module_name, p.page_name";
$pages_result = $conn->query($pages_query);
if (!$pages_result) {
    die("Error retrieving pages: " . $conn->error);
}

// Fetch modules
$modules_query = "SELECT * FROM modules ORDER BY display_order";
$modules_result = $conn->query($modules_query);
if (!$modules_result) {
    die("Error retrieving modules: " . $conn->error);
}

// Group pages by module for easier handling
$pages_by_module = [];
while ($page = $pages_result->fetch_assoc()) {
    $module_id = $page['module_id'] ?: 0; // Use 0 for unassigned
    if (!isset($pages_by_module[$module_id])) {
        $pages_by_module[$module_id] = [
            'module_name' => $page['module_id'] ? $page['module_name'] : 'Other',
            'pages' => []
        ];
    }
    $pages_by_module[$module_id]['pages'][] = $page;
}

// Reset the pointer of roles_result
$roles_result->data_seek(0);

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
    <style>
        .module-section {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .module-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .module-title {
            margin: 0 0 0 10px;
            font-weight: bold;
        }
        .module-pages {
            margin-left: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 5px;
        }
    </style>
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
                <label>Accessible Modules & Pages:</label>
                <div class="checkbox-container">
                    <?php foreach ($modules_by_module = $conn->query("SELECT * FROM modules ORDER BY display_order") as $module): ?>
                        <div class="module-section">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="module_<?= $module['module_id'] ?>" 
                                       value="<?= $module['module_id'] ?>" 
                                       onchange="toggleModulePages(<?= $module['module_id'] ?>)">
                                <h3 class="module-title">
                                    <?php if (!empty($module['icon'])): ?>
                                        <i class="<?= $module['icon'] ?>"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($module['module_name']) ?>
                                </h3>
                            </div>
                            <div class="module-pages" id="pages_module_<?= $module['module_id'] ?>">
                                <?php
                                // Get pages for this module
                                $module_pages_query = "SELECT * FROM pages WHERE module_id = ? ORDER BY page_name";
                                $module_pages_stmt = $conn->prepare($module_pages_query);
                                $module_pages_stmt->bind_param("i", $module['module_id']);
                                $module_pages_stmt->execute();
                                $module_pages_result = $module_pages_stmt->get_result();
                                
                                while ($page = $module_pages_result->fetch_assoc()):
                                ?>
                                    <label class="checkbox-label module-<?= $module['module_id'] ?>-page">
                                        <input type="checkbox" name="page_ids[]" class="page-checkbox module-<?= $module['module_id'] ?>-checkbox" 
                                               value="<?= $page['page_name'] ?>"
                                               data-module-id="<?= $module['module_id'] ?>"> 
                                        <?= htmlspecialchars($page['page_name']) ?>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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