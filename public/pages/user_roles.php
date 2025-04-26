<?php
// Include configuration and header files
include("../includes/config.php");
include("../includes/session.php");

// Check if user is logged in
/* if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
} */

// Check if user's role has permission to access this page
$role = $_SESSION['role'];
$query = "SELECT pages FROM roles WHERE role_name = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $pages = explode(',', $row['pages']);
    $has_access = false;
    
    // Trim each page name and check if 'User Roles' is in the list
    foreach ($pages as $page) {
        if (trim($page) == 'User Roles') {
            $has_access = true;
            break;
        }
    }
    
    if (!$has_access) {
        header("Location: ../index.php");
        exit();
    }
} else {
    // Role not found
    header("Location: ../index.php");
    exit();
}

// Now include the header and navigation components after authentication is complete
include("../includes/header.php");
include("../includes/navbar.php");
include("../includes/sidebar.php");

// Process form submission for adding new role
if (isset($_POST['add_role'])) {
    $role_name = $_POST['role_name'];
    
    // Check if role name already exists
    $check_query = "SELECT COUNT(*) as count FROM roles WHERE role_name = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $role_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo "<script>alert('Role name already exists!');</script>";
    } else {
        // Get selected module_ids
        $selected_modules = isset($_POST['modules']) ? $_POST['modules'] : [];
        
        // Get all pages for the selected modules
        $pages = [];
        if (!empty($selected_modules)) {
            $modules_str = implode(',', array_map('intval', $selected_modules));
            $pages_query = "SELECT page_name FROM pages WHERE module_id IN ($modules_str)";
            $pages_result = $conn->query($pages_query);
            
            while ($page = $pages_result->fetch_assoc()) {
                $pages[] = $page['page_name'];
            }
        }
        
        $pages_str = implode(', ', $pages);
        
        // Insert new role
        $insert_query = "INSERT INTO roles (role_name, status, pages) VALUES (?, 'active', ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ss", $role_name, $pages_str);
        
        if ($insert_stmt->execute()) {
            echo "<script>alert('Role added successfully!'); window.location.href='user_roles.php';</script>";
        } else {
            echo "<script>alert('Error adding role: " . $conn->error . "');</script>";
        }
    }
}

// Process form submission for editing role
if (isset($_POST['edit_role'])) {
    $role_id = $_POST['role_id'];
    $role_name = $_POST['role_name'];
    $status = $_POST['status'];
    
    // Get selected module_ids
    $selected_modules = isset($_POST['modules']) ? $_POST['modules'] : [];
    
    // Get all pages for the selected modules
    $pages = [];
    if (!empty($selected_modules)) {
        $modules_str = implode(',', array_map('intval', $selected_modules));
        $pages_query = "SELECT page_name FROM pages WHERE module_id IN ($modules_str)";
        $pages_result = $conn->query($pages_query);
        
        while ($page = $pages_result->fetch_assoc()) {
            $pages[] = $page['page_name'];
        }
    }
    
    $pages_str = implode(', ', $pages);
    
    // Update role
    $update_query = "UPDATE roles SET role_name = ?, status = ?, pages = ? WHERE role_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssi", $role_name, $status, $pages_str, $role_id);
    
    if ($update_stmt->execute()) {
        echo "<script>alert('Role updated successfully!'); window.location.href='user_roles.php';</script>";
    } else {
        echo "<script>alert('Error updating role: " . $conn->error . "');</script>";
    }
}

// Process form submission for deleting role
if (isset($_POST['delete_role'])) {
    $role_id = $_POST['role_id'];
    
    // Check if any accounts are using this role
    $check_query = "SELECT COUNT(*) as count FROM accounts WHERE role = (SELECT role_name FROM roles WHERE role_id = ?)";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $role_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo "<script>alert('Cannot delete role because it is assigned to one or more accounts!');</script>";
    } else {
        // Delete role
        $delete_query = "DELETE FROM roles WHERE role_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $role_id);
        
        if ($delete_stmt->execute()) {
            echo "<script>alert('Role deleted successfully!'); window.location.href='user_roles.php';</script>";
        } else {
            echo "<script>alert('Error deleting role: " . $conn->error . "');</script>";
        }
    }
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">User Roles</h4>
                        <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addRoleModal">
                            Add New Role
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="roles-table" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Role Name</th>
                                        <th>Status</th>
                                        <th>Accessible Modules</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT * FROM roles ORDER BY role_name";
                                    $result = $conn->query($query);
                                    
                                    while ($row = $result->fetch_assoc()) {
                                        $role_id = $row['role_id'];
                                        $role_name = $row['role_name'];
                                        $status = $row['status'];
                                        $pages_str = $row['pages'];
                                        
                                        // Get modules for this role based on pages
                                        $pages_array = array_map('trim', explode(',', $pages_str));
                                        $pages_list = "'" . implode("','", $pages_array) . "'";
                                        
                                        $modules_query = "
                                            SELECT DISTINCT m.display_name 
                                            FROM modules m 
                                            INNER JOIN pages p ON m.module_id = p.module_id 
                                            WHERE p.page_name IN ($pages_list)
                                            ORDER BY m.display_order
                                        ";
                                        $modules_result = $conn->query($modules_query);
                                        
                                        $modules = [];
                                        while ($module = $modules_result->fetch_assoc()) {
                                            $modules[] = $module['display_name'];
                                        }
                                        
                                        $modules_display = !empty($modules) ? implode(", ", $modules) : "None";
                                        
                                        echo "<tr>";
                                        echo "<td>$role_name</td>";
                                        echo "<td>" . ucfirst($status) . "</td>";
                                        echo "<td>$modules_display</td>";
                                        echo "<td>
                                                <button class='btn btn-sm btn-info edit-btn' data-toggle='modal' data-target='#editRoleModal' 
                                                    data-id='$role_id' data-name='$role_name' data-status='$status' data-pages='$pages_str'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <button class='btn btn-sm btn-danger delete-btn' data-toggle='modal' data-target='#deleteRoleModal' 
                                                    data-id='$role_id' data-name='$role_name'>
                                                    <i class='fas fa-trash'></i> Delete
                                                </button>
                                            </td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" role="dialog" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">Add New Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">Role Name</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="form-group">
                        <label>Module Access</label>
                        <div class="row">
                            <?php
                            $modules_query = "SELECT * FROM modules ORDER BY display_order";
                            $modules_result = $conn->query($modules_query);
                            
                            while ($module = $modules_result->fetch_assoc()) {
                                echo '<div class="col-md-4 mb-2">';
                                echo '<div class="custom-control custom-checkbox">';
                                echo '<input type="checkbox" class="custom-control-input" id="module_' . $module['module_id'] . '" name="modules[]" value="' . $module['module_id'] . '">';
                                echo '<label class="custom-control-label" for="module_' . $module['module_id'] . '">';
                                echo '<i class="' . $module['icon'] . ' mr-1"></i> ' . $module['display_name'];
                                echo '</label>';
                                echo '</div>';
                                
                                // Get pages for this module
                                $pages_query = "SELECT * FROM pages WHERE module_id = " . $module['module_id'] . " ORDER BY page_name";
                                $pages_result = $conn->query($pages_query);
                                
                                if ($pages_result->num_rows > 0) {
                                    echo '<ul class="list-unstyled pl-4 small text-muted">';
                                    while ($page = $pages_result->fetch_assoc()) {
                                        echo '<li>' . $page['page_name'] . '</li>';
                                    }
                                    echo '</ul>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_role" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoleModalLabel">Edit Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_role_id" name="role_id">
                    <div class="form-group">
                        <label for="edit_role_name">Role Name</label>
                        <input type="text" class="form-control" id="edit_role_name" name="role_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Module Access</label>
                        <div class="row">
                            <?php
                            // Reset result pointer to beginning
                            $modules_result->data_seek(0);
                            
                            while ($module = $modules_result->fetch_assoc()) {
                                echo '<div class="col-md-4 mb-2">';
                                echo '<div class="custom-control custom-checkbox">';
                                echo '<input type="checkbox" class="custom-control-input edit-module" id="edit_module_' . $module['module_id'] . '" name="modules[]" value="' . $module['module_id'] . '">';                                echo '<label class="custom-control-label" for="edit_module_' . $module['module_id'] . '">';
                                echo '<i class="' . $module['icon'] . ' mr-1"></i> ' . $module['display_name'];
                                echo '</label>';
                                echo '</div>';
                                
                                // Get pages for this modules
                                $pages_query = "SELECT * FROM pages WHERE module_id = " . $module['module_id'] . " ORDER BY page_name";
                                $pages_result = $conn->query($pages_query);
                                
                                if ($pages_result->num_rows > 0) {
                                    echo '<ul class="list-unstyled pl-4 small text-muted">';
                                    while ($page = $pages_result->fetch_assoc()) {
                                        echo '<li>' . $page['page_name'] . '</li>';
                                    }
                                    echo '</ul>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="edit_role" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Role Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" role="dialog" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRoleModalLabel">Delete Role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_role_id" name="role_id">
                    <p>Are you sure you want to delete the role <strong id="delete_role_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone, and will fail if any accounts are assigned this role.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_role" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#roles-table').DataTable({
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": 3 }
        ]
    });
    
    // Fill edit modal with role data
    $('.edit-btn').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var status = $(this).data('status');
        var pages = $(this).data('pages');
        
        $('#edit_role_id').val(id);
        $('#edit_role_name').val(name);
        $('#edit_status').val(status);
        
        // Clear all checkboxes
        $('.edit-module').prop('checked', false);
        
        // Get all pages for this role
        if (pages) {
            var pageList = pages.split(',').map(function(item) {
                return item.trim();
            });
            
            // For each page, get its module and check the module checkbox
            if (pageList.length > 0) {
                $.ajax({
                    url: 'get_modules_for_pages.php',
                    type: 'POST',
                    data: {pages: pageList},
                    dataType: 'json',
                    success: function(response) {
                        $.each(response.modules, function(i, moduleId) {
                            $('#edit_module_' + moduleId).prop('checked', true);
                        });
                    }
                });
            }
        }
    });
    
    // Fill delete modal with role data
    $('.delete-btn').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        
        $('#delete_role_id').val(id);
        $('#delete_role_name').text(name);
    });
});
</script>

<?php include("../includes/footer.php"); ?>