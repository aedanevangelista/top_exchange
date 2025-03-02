<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('User Roles'); // Ensure the user has access to the User Roles page

// Handle form submission to add or update role-page access
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role_id = $_POST['role_id'] ?? null;
    $role_name = $_POST['role_name'];
    $page_ids = $_POST['page_ids'] ?? [];

    if ($role_id) {
        // Update existing role
        $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
        $stmt->bind_param("si", $role_name, $role_id);
        $stmt->execute();
        $stmt->close();

        // Delete existing permissions for the role
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new role
        $stmt = $conn->prepare("INSERT INTO roles (role_name, status) VALUES (?, 'active') ON DUPLICATE KEY UPDATE status='active'");
        $stmt->bind_param("s", $role_name);
        $stmt->execute();
        $role_id = $stmt->insert_id;
        $stmt->close();
    }

    // Insert new permissions
    $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_id) VALUES (?, ?)");
    foreach ($page_ids as $page_id) {
        $stmt->bind_param("ii", $role_id, $page_id);
        $stmt->execute();
    }
    $stmt->close();
    header("Location: user_roles.php");
    exit();
}

// Handle archiving or activating of role-page access
if (isset($_GET['archive'])) {
    $role_id = $_GET['archive'];
    // Prevent archiving of admin role
    $stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->bind_result($role_name);
    $stmt->fetch();
    $stmt->close();

    if ($role_name !== 'admin') {
        $stmt = $conn->prepare("UPDATE roles SET status = 'inactive' WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: user_roles.php");
    exit();
} elseif (isset($_GET['activate'])) {
    $role_id = $_GET['activate'];
    $stmt = $conn->prepare("UPDATE roles SET status = 'active' WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->close();
    header("Location: user_roles.php");
    exit();
}

// Fetch role-page access records for display
$sql = "SELECT rp.role_id, r.role_name, r.status, p.page_id, p.page_name 
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.role_id
        JOIN pages p ON rp.page_id = p.page_id
        ORDER BY r.role_name, p.page_name";
$result = $conn->query($sql);

// Fetch roles and pages for the form
$roles = $conn->query("SELECT * FROM roles ORDER BY role_name");
$pages = $conn->query("SELECT * FROM pages ORDER BY page_name");
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
                        <th>Pages</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $current_role = null;
                    $pages_list = [];
                    $last_role_id = null;
                    $last_role_status = null;

                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            if ($current_role !== $row['role_name']):
                                if ($current_role !== null):
                                    echo "<tr>
                                            <td>" . htmlspecialchars($current_role) . "</td>
                                            <td>" . implode(', ', array_map('htmlspecialchars', $pages_list)) . "</td>
                                            <td class='" . ($last_role_status == 'active' ? 'status-active' : 'status-inactive') . "'>" . htmlspecialchars($last_role_status) . "</td>
                                            <td class='action-buttons'>";
                                    if ($last_role_status == 'active') {
                                        echo "<button class='edit-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                                <i class='fas fa-edit'></i> Edit
                                              </button>";
                                        if ($current_role !== 'admin') {
                                            echo "<a href='user_roles.php?archive=" . htmlspecialchars($last_role_id) . "' class='archive-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                                    <i class='fas fa-archive'></i> Archive
                                                  </a>";
                                        }
                                    } else {
                                        echo "<a href='user_roles.php?activate=" . htmlspecialchars($last_role_id) . "' class='activate-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                                <i class='fas fa-check-circle'></i> Activate
                                              </a>";
                                    }
                                    echo "</td></tr>";
                                endif;
                                $current_role = $row['role_name'];
                                $last_role_id = $row['role_id'];
                                $last_role_status = $row['status'];
                                $pages_list = [];
                            endif;
                            $pages_list[] = $row['page_name'];
                        endwhile;

                        if (!is_null($current_role)):
                            echo "<tr>
                                    <td>" . htmlspecialchars($current_role) . "</td>
                                    <td>" . implode(', ', array_map('htmlspecialchars', $pages_list)) . "</td>
                                    <td class='" . ($last_role_status == 'active' ? 'status-active' : 'status-inactive') . "'>" . htmlspecialchars($last_role_status) . "</td>
                                    <td class='action-buttons'>";
                            if ($last_role_status == 'active') {
                                echo "<button class='edit-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                        <i class='fas fa-edit'></i> Edit
                                      </button>";
                                if ($current_role !== 'admin') {
                                    echo "<a href='user_roles.php?archive=" . htmlspecialchars($last_role_id) . "' class='archive-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                            <i class='fas fa-archive'></i> Archive
                                          </a>";
                                }
                            } else {
                                echo "<a href='user_roles.php?activate=" . htmlspecialchars($last_role_id) . "' class='activate-btn' data-role-id='" . htmlspecialchars($last_role_id) . "'>
                                        <i class='fas fa-check-circle'></i> Activate
                                      </a>";
                            }
                            echo "</td></tr>";
                        endif;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="no-accounts">No role-page access found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overlay Form for Adding Role -->
    <div id="roleOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add Role</h2>
            <form id="roleForm" method="POST" class="account-form">
                <input type="hidden" id="roleId" name="role_id">
                <label for="roleName">Role Name:</label>
                <input type="text" id="roleName" name="role_name" required>
                <br/>
                <label>Select Pages:</label>
                <div id="pagesCheckboxes" class="checkbox-group">
                    <?php while ($page = $pages->fetch_assoc()): ?>
                        <div class="checkbox-container">
                            <input type="checkbox" id="page_<?= $page['page_id'] ?>" name="page_ids[]" value="<?= $page['page_id'] ?>">
                            <label for="page_<?= $page['page_id'] ?>"><?= htmlspecialchars($page['page_name']) ?></label>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeRoleForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../js/accounts.js"></script>
    <script>
        function openAddRoleForm() {
            document.getElementById('roleForm').reset();
            document.getElementById('roleId').value = '';
            document.querySelector('#roleOverlay h2').innerHTML = '<i class="fas fa-user-plus"></i> Add Role';
            document.getElementById('roleOverlay').style.display = 'flex';
        }

        function openEditRoleForm(role_id) {
            document.getElementById('roleForm').reset();
            document.getElementById('roleId').value = role_id;
            document.querySelector('#roleOverlay h2').innerHTML = '<i class="fas fa-user-edit"></i> Edit Role';
            $.ajax({
                url: '../../backend/get_role_details.php',
                method: 'GET',
                data: { role_id: role_id },
                success: function(data) {
                    const roleData = JSON.parse(data);
                    $('#roleName').val(roleData.role_name);
                    $('input[type="checkbox"]').prop('checked', false);
                    roleData.pages.forEach(function(page_id) {
                        $('#page_' + page_id).prop('checked', true);
                    });
                    document.getElementById('roleOverlay').style.display = 'flex';
                }
            });
        }

        function closeRoleForm() {
            document.getElementById('roleOverlay').style.display = 'none';
        }

        document.addEventListener("DOMContentLoaded", function() {
            var roleEditButtons = document.querySelectorAll(".edit-btn");
            roleEditButtons.forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var roleId = btn.getAttribute("data-role-id");
                    openEditRoleForm(roleId);
                });
            });
        });
    </script>
</body>
</html>