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

// Fetch pages
$pages_query = "SELECT * FROM pages ORDER BY page_name";
$pages_result = $conn->query($pages_query);
if (!$pages_result) {
    die("Error retrieving pages: " . $conn->error);
}

// Get all unique module names
$modules_query = "SELECT DISTINCT module FROM pages WHERE module IS NOT NULL AND module != '' ORDER BY module";
$modules_result = $conn->query($modules_query);
if (!$modules_result) {
    die("Error retrieving modules: " . $conn->error);
}

// Group pages by module
$pages_by_module = [];
$modules_list = [];

// First, collect all modules
while ($module = $modules_result->fetch_assoc()) {
    $module_name = $module['module'];
    $modules_list[$module_name] = [
        'name' => $module_name,
        'pages' => []
    ];
}

// Add 'Unassigned' as a module for pages without a module
$modules_list['Unassigned'] = [
    'name' => 'Unassigned',
    'pages' => []
];

// Reset the pages result
$pages_result->data_seek(0);

// Now group pages by module
while ($page = $pages_result->fetch_assoc()) {
    $module_name = !empty($page['module']) ? $page['module'] : 'Unassigned';
    // Handle case where the module might not be in our list (though it should be)
    if (!isset($modules_list[$module_name])) {
        $modules_list[$module_name] = [
            'name' => $module_name,
            'pages' => []
        ];
    }
    $modules_list[$module_name]['pages'][] = $page;
}

// Reset the roles result
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
        .checkbox-container {
            max-height: 400px;
            overflow-y: auto;
        }
        /* Add icons to modules */
        .module-icon {
            margin-right: 5px;
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
                <label>Accessible Pages by Module:</label>
                <div class="checkbox-container">
                    <?php foreach ($modules_list as $module_name => $module_data): ?>
                        <?php if (!empty($module_data['pages'])): ?>
                            <div class="module-section">
                                <div class="module-header">
                                    <input type="checkbox" class="module-checkbox" id="module_<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>" 
                                           value="<?= htmlspecialchars($module_name) ?>" 
                                           onchange="toggleModulePages('<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>')">
                                    <h3 class="module-title">
                                        <i class="fas fa-folder module-icon"></i>
                                        <?= htmlspecialchars($module_name) ?>
                                    </h3>
                                </div>
                                <div class="module-pages" id="pages_module_<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>">
                                    <?php foreach ($module_data['pages'] as $page): ?>
                                        <label class="checkbox-label module-<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>-page">
                                            <input type="checkbox" name="page_ids[]" class="page-checkbox module-<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>-checkbox" 
                                                   value="<?= htmlspecialchars($page['page_name']) ?>"
                                                   data-module="<?= htmlspecialchars($module_name) ?>"> 
                                            <?= htmlspecialchars($page['page_name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
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
    <script>
    function showRoleForm(roleId = '', roleName = '', pages = '') {
        document.getElementById("roleFormTitle").innerHTML = roleId ? '<i class="fas fa-edit"></i> Edit Role' : '<i class="fas fa-user-plus"></i> Add Role';
        document.getElementById("actionType").value = roleId ? 'edit' : 'add';
        document.getElementById("roleId").value = roleId;
        document.getElementById("roleName").value = roleName;
        document.getElementById("roleError").style.display = "none";
        
        // Uncheck all checkboxes first
        document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
            checkbox.checked = false;
        });
        document.querySelectorAll(".module-checkbox").forEach(checkbox => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
        });
        
        // If editing, check the appropriate boxes
        if (pages) {
            let pageArray = pages.split(", ");
            document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
                if (pageArray.includes(checkbox.value)) {
                    checkbox.checked = true;
                }
            });
            
            // Check if all pages in a module are selected
            updateModuleCheckboxes();
        }
        
        document.getElementById("roleOverlay").style.display = "block";
    }

    function hideRoleForm() {
        document.getElementById("roleOverlay").style.display = "none";
    }

    // Toggle all pages in a module when the module checkbox is clicked
    function toggleModulePages(moduleId) {
        const moduleCheckbox = document.getElementById(`module_${moduleId}`);
        const pageCheckboxes = document.querySelectorAll(`.module-${moduleId}-checkbox`);
        
        pageCheckboxes.forEach(checkbox => {
            checkbox.checked = moduleCheckbox.checked;
        });
    }

    // Update module checkboxes based on page selection
    function updateModuleCheckboxes() {
        document.querySelectorAll('.module-checkbox').forEach(moduleCheckbox => {
            const moduleId = moduleCheckbox.id.replace('module_', '');
            const modulePages = document.querySelectorAll(`.module-${moduleId}-checkbox`);
            const checkedPages = document.querySelectorAll(`.module-${moduleId}-checkbox:checked`);
            
            // If all pages of this module are checked, also check the module checkbox
            if (modulePages.length > 0 && checkedPages.length === modulePages.length) {
                moduleCheckbox.checked = true;
                moduleCheckbox.indeterminate = false;
            }
            // If some pages are checked, but not all, make module checkbox indeterminate
            else if (checkedPages.length > 0) {
                moduleCheckbox.checked = false;
                moduleCheckbox.indeterminate = true;
            }
            // If no pages are checked, uncheck the module checkbox
            else {
                moduleCheckbox.checked = false;
                moduleCheckbox.indeterminate = false;
            }
        });
    }

    // Add event listeners to page checkboxes to update module status
    document.addEventListener('DOMContentLoaded', function() {
        const pageCheckboxes = document.querySelectorAll('.page-checkbox');
        pageCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateModuleCheckboxes();
            });
        });
    });
    </script>
</body>
</html>