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

// Check for success message
$successMessage = "";
if (isset($_GET['success'])) {
    $successMessages = [
        'added' => "Role has been added successfully.",
        'edited' => "Role has been updated successfully.",
        'archived' => "Role has been archived successfully.",
        'activated' => "Role has been activated successfully."
    ];
    $successMessage = $successMessages[$_GET['success']] ?? "Operation completed successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles</title>
    <link rel="stylesheet" href="/css/user_roles.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }
        .toast {
            background-color: #333;
            color: #fff;
            padding: 15px 25px;
            border-radius: 5px;
            margin-bottom: 10px;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease-in-out;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .toast.success {
            background-color: #4CAF50;
            border-left: 5px solid #2E7D32;
        }
        .toast.error {
            background-color: #F44336;
            border-left: 5px solid #C62828;
        }
        .toast.info {
            background-color: #2196F3;
            border-left: 5px solid #1565C0;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-icon {
            margin-right: 12px;
            font-size: 1.2em;
        }
        .toast-message {
            flex-grow: 1;
        }
        .toast-close {
            cursor: pointer;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.2em;
            margin-left: 10px;
        }

        /* Fixed overlay positioning */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            display: none; /* Start hidden */
        }
        
        /* Center the modal content */
        .overlay-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .overlay-content h2 {
            color: #333;
            margin-top: 0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .overlay-content h2 i {
            margin-right: 10px;
        }
        
        /* Checkbox container */
        .checkbox-container {
            max-height: 50vh;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background: #f9f9f9;
            width: 100%;
            box-sizing: border-box; /* Important to include padding in width calculation */
        }
        
        /* Module section styling */
        .module-section {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            width: 100%; /* Ensure it fills the container width */
            box-sizing: border-box; /* Include padding in width calculation */
        }
        .module-section:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .module-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            cursor: pointer;
            width: 100%; /* Ensure it fills the container width */
        }
        .module-title {
            margin: 0 0 0 10px;
            font-weight: bold;
            font-size: 1.1em;
            color: #444;
        }
        
        /* Checkbox styling */
        .module-checkbox, .page-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .module-checkbox {
            margin-right: 5px;
        }
        .module-pages {
            margin-left: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            padding-top: 5px;
            border-top: 1px dashed #e0e0e0;
            width: calc(100% - 25px); /* Calculate width minus margin */
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .checkbox-label:hover {
            background: #f0f0f0;
        }
        
        /* Form controls */
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            width: 100%;
        }
        .form-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-weight: bold;
            transition: all 0.2s;
        }
        .form-buttons button i {
            margin-right: 8px;
        }
        .save-btn {
            background: #4CAF50;
            color: white;
        }
        .save-btn:hover {
            background: #45a049;
        }
        .cancel-btn {
            background: #f44336;
            color: white;
        }
        .cancel-btn:hover {
            background: #e53935;
        }
        
        /* Input field styling */
        .account-form {
            width: 100%;
        }
        .account-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        .account-form input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
            box-sizing: border-box;
        }
        .account-form input[type="text"]:focus {
            border-color: #2196F3;
            outline: none;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
        }
        
        /* Module icon */
        .module-icon {
            margin-right: 8px;
            color: #666;
            width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <!-- Toast container -->
    <div id="toast-container"></div>

    <div class="main-content">
        <div class="accounts-header">
            <h1>User Roles</h1>
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
                                    <form method="POST" action="../../backend/roles/manage_roles.php" style="display:inline;" class="status-toggle-form">
                                        <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                        <input type="hidden" name="role_name" value="<?= htmlspecialchars($role['role_name']) ?>">
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
    <div id="roleOverlay" class="overlay">
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
                            <?php 
                                // Determine icon based on module name
                                $icon = 'fa-folder';
                                
                                // Map common module names to specific icons
                                $moduleIcons = [
                                    'Dashboard' => 'fa-home',
                                    'Accounts' => 'fa-user',
                                    'Orders' => 'fa-shopping-cart',
                                    'Inventory' => 'fa-box',
                                    'Payment' => 'fa-money-bill',
                                    'Production' => 'fa-industry',
                                    'Staff' => 'fa-users',
                                    'Forecast' => 'fa-chart-line',
                                    'Reports' => 'fa-chart-bar',
                                    'Settings' => 'fa-cog',
                                    'Unassigned' => 'fa-question-circle'
                                ];
                                
                                // Check if the module name starts with any key in our icon map
                                foreach ($moduleIcons as $keyword => $moduleIcon) {
                                    if (stripos($module_name, $keyword) !== false) {
                                        $icon = $moduleIcon;
                                        break;
                                    }
                                }
                            ?>
                            <div class="module-section">
                                <div class="module-header" onclick="toggleModuleVisibility('<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>')">
                                    <input type="checkbox" class="module-checkbox" id="module_<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>" 
                                           value="<?= htmlspecialchars($module_name) ?>" 
                                           onchange="toggleModulePages('<?= htmlspecialchars(str_replace(' ', '_', $module_name)) ?>')"
                                           onclick="event.stopPropagation()">
                                    <h3 class="module-title">
                                        <i class="fas <?= $icon ?> module-icon"></i>
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
    // Toast notification functions
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        // Create icon based on type
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        
        // Create toast content
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas ${icon}"></i></div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="closeToast(this.parentElement)"><i class="fas fa-times"></i></button>
        `;
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Show with animation
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            closeToast(toast);
        }, 5000);
    }
    
    function closeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

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
        
        // Simply display the overlay
        document.getElementById("roleOverlay").style.display = "block";
    }

    function hideRoleForm() {
        // Simply hide the overlay
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

    // Toggle the visibility of module pages to collapse/expand
    function toggleModuleVisibility(moduleId) {
        const modulePages = document.getElementById(`pages_module_${moduleId}`);
        if (modulePages.style.display === 'none') {
            modulePages.style.display = 'grid';
        } else {
            modulePages.style.display = 'none';
        }
        // Stop the event from bubbling up
        event.stopPropagation();
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

    // Submit the role form with AJAX to prevent page refresh and show toast
    document.addEventListener('DOMContentLoaded', function() {
        // Set up listeners for page checkboxes
        const pageCheckboxes = document.querySelectorAll('.page-checkbox');
        pageCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateModuleCheckboxes();
            });
        });

        // Handle form submissions
        document.getElementById('roleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = formData.get('action');
            const roleId = formData.get('role_id');
            const roleName = formData.get('role_name');
            
            fetch('../../backend/roles/manage_roles.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                // Try to parse as JSON, but handle as text if not valid JSON
                let jsonData;
                try {
                    jsonData = JSON.parse(data);
                } catch (e) {
                    // If not valid JSON, just use the text
                    showToast('Operation completed successfully.', 'success');
                    hideRoleForm();
                    setTimeout(() => { window.location.reload(); }, 1500);
                    return;
                }
                
                // Show success message based on action
                let message = jsonData.message || 'Role changes saved successfully!';
                if (!jsonData.message) {
                    if (action === 'add') {
                        message = `Role "${roleName}" has been added successfully.`;
                    } else if (action === 'edit') {
                        message = `Role "${roleName}" has been updated successfully.`;
                    }
                }
                
                // Show toast and reload after a delay
                showToast(message, jsonData.success ? 'success' : 'error');
                
                if (jsonData.success) {
                    hideRoleForm();
                    // Reload page after toast is shown
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        });
        
        // Handle status toggle forms
        document.querySelectorAll('.status-toggle-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const action = formData.get('action');
                const roleName = formData.get('role_name');
                
                fetch('../../backend/roles/manage_roles.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    // Show success message based on action
                    let message = '';
                    if (action === 'archive') {
                        message = `Role "${roleName}" has been archived.`;
                    } else if (action === 'activate') {
                        message = `Role "${roleName}" has been activated.`;
                    }
                    
                    // Show toast and reload after a delay
                    showToast(message, 'success');
                    
                    // Reload page after toast is shown
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                })
                .catch(error => {
                    showToast('Error: ' + error.message, 'error');
                });
            });
        });
        
        // Show any messages from URL parameters
        <?php if (!empty($errorMessage)): ?>
        showToast('<?= addslashes($errorMessage) ?>', 'error');
        <?php endif; ?>
        
        <?php if (!empty($successMessage)): ?>
        showToast('<?= addslashes($successMessage) ?>', 'success');
        <?php endif; ?>
        
        // Close modal when clicking outside
        document.getElementById('roleOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRoleForm();
            }
        });
    });
    </script>
</body>
</html>