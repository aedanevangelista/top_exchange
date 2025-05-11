<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Admin');

// Sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'username';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC';

$allowed_columns = ['username', 'role', 'created_at', 'status'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'username';
}
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'ASC';
}

// Status Filter
$status_filter = $_GET['status'] ?? '';

// Fetch Roles
$roles = [];
$roleQuery = "SELECT role_name FROM roles WHERE status = 'active'";
$resultRoles = $conn->query($roleQuery);
if ($resultRoles && $resultRoles->num_rows > 0) {
    while ($row = $resultRoles->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
}

// AJAX Handlers
function returnJsonResponse($success, $reload, $message = '') {
    global $conn;
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $formType = $_POST['formType'] ?? '';

    if ($formType == 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Store passwords securely (e.g., password_hash())
        $role = $_POST['role'];
        $status = 'Active';
        $created_at = date('Y-m-d H:i:s');

        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            returnJsonResponse(false, false, 'Username already exists.');
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO accounts (username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $password, $role, $status, $created_at);

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true, 'Account added successfully.');
        } else {
            error_log("Add account failed: " . $stmt->error);
            $stmt->close();
            returnJsonResponse(false, false, 'Database error adding account.');
        }
    } elseif ($formType == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Store passwords securely
        $role = $_POST['role'];

        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            returnJsonResponse(false, false, 'Username already exists.');
        }
        $checkStmt->close();

        $stmt = null;
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $role, $id);
        }

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true, 'Account updated successfully.');
        } else {
            error_log("Edit account failed: " . $stmt->error);
            $stmt->close();
            returnJsonResponse(false, false, 'Database error updating account.');
        }
    } elseif ($formType == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        // REMOVED 'Reject' from allowed statuses
        $allowed_statuses = ['Active', 'Archived'];
        if (!in_array($status, $allowed_statuses)) {
            returnJsonResponse(false, false, 'Invalid status value.');
        }

        $stmt = $conn->prepare("UPDATE accounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true, 'Status updated successfully.');
        } else {
            error_log("Change status failed: " . $stmt->error);
            $stmt->close();
            returnJsonResponse(false, false, 'Failed to change status.');
        }
    }
}

// Fetch Accounts Data
$sql = "SELECT id, username, role, status, created_at FROM accounts";
$params = [];
$param_types = "";

if (!empty($status_filter)) {
    $sql .= " WHERE status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$sql .= " ORDER BY {$sort_column} {$sort_direction}";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    if ($conn instanceof mysqli) $conn->close();
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$accounts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close();
} else {
    error_log("Fetch accounts failed: " . $conn->error);
    $stmt->close();
}

// Helper Functions for Sorting
function getSortUrl($column, $currentColumn, $currentDirection, $currentStatus) {
    $newDirection = ($column === $currentColumn && strtoupper($currentDirection) === 'ASC') ? 'DESC' : 'ASC';
    $urlParams = ['sort' => $column, 'direction' => $newDirection];
    if (!empty($currentStatus)) {
        $urlParams['status'] = $currentStatus;
    }
    return "?" . http_build_query($urlParams);
}

function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column !== $currentColumn) return '<i class="fas fa-sort"></i>';
    return (strtoupper($currentDirection) === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Accounts</title>
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <style>
        .search-container { display: flex; align-items: center; margin: 0 15px; }
        .search-container input { padding: 8px 12px; border-radius: 20px 0 0 20px; border: 1px solid #ddd; font-size: 12px; width: 200px; border-right: none; }
        .search-container .search-btn { background-color: #2980b9; color: white; border: 1px solid #2980b9; border-radius: 0 20px 20px 0; padding: 8px 12px; cursor: pointer; margin-left: -1px; }
        .search-container .search-btn:hover { background-color: #2471a3; }
        .accounts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .accounts-header h1 { margin-right: auto; }
        .accounts-table th.sortable a { color: inherit; text-decoration: none; display: inline-block; }
        th.sortable:hover { background-color:rgb(71, 71, 71); }
        .accounts-table th.sortable i { margin-left: 5px; color: #aaa; }
        .accounts-table th.sortable.active i { color: white; }
        .role-label { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500; background-color: #e9ecef; color: #495057; border: 1px solid #ced4da; }
        .role-label.role-admin { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;}
        .role-label.role-orders { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb;}
    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Staff Accounts</h1>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search accounts...">
                <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
            </div>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="" <?= empty($status_filter) ? 'selected' : '' ?>>All</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <!-- REMOVED Reject Option -->
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
                        <th class="sortable <?= $sort_column == 'username' ? 'active' : '' ?>">
                            <a href="<?= getSortUrl('username', $sort_column, $sort_direction, $status_filter) ?>">
                                Username <?= getSortIcon('username', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable <?= $sort_column == 'role' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('role', $sort_column, $sort_direction, $status_filter) ?>">
                                Role <?= getSortIcon('role', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th class="sortable <?= $sort_column == 'created_at' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('created_at', $sort_column, $sort_direction, $status_filter) ?>">
                                Account Age <?= getSortIcon('created_at', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                         <th class="sortable <?= $sort_column == 'status' ? 'active' : '' ?>">
                             <a href="<?= getSortUrl('status', $sort_column, $sort_direction, $status_filter) ?>">
                                Status <?= getSortIcon('status', $sort_column, $sort_direction) ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($accounts) > 0): ?>
                        <?php foreach ($accounts as $account):
                            try {
                                $created_at = new DateTime($account['created_at']);
                                $now = new DateTime();
                                $diff = $created_at->diff($now);
                                if ($diff->y > 0) $account_age = $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
                                elseif ($diff->m > 0) $account_age = $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
                                elseif ($diff->d > 0) $account_age = $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
                                elseif ($diff->h > 0 || $diff->i > 0 || $diff->s >= 0) $account_age = "Just now";
                                else $account_age = "Unknown";
                            } catch (Exception $e) {
                                $account_age = "Invalid date";
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td>
                                    <?php
                                    $roleName = $account['role'] ?? '';
                                    $roleDisplay = !empty($roleName) ? ucfirst($roleName) : 'Unknown';
                                    $rolesWithoutSpecificStyle = ['accountant', 'secretary'];
                                    $roleClasses = 'role-label';
                                    if (!empty($roleName)) {
                                        $lowerRole = strtolower($roleName);
                                        if (!in_array($lowerRole, $rolesWithoutSpecificStyle)) {
                                            $roleClasses .= ' role-' . $lowerRole;
                                        }
                                    } else {
                                        $roleClasses .= ' role-unknown';
                                    }
                                    echo "<span class='$roleClasses'>$roleDisplay</span>";
                                    ?>
                                </td>
                                <td><?= $account_age ?></td>
                                <td class="<?= 'status-' . strtolower($account['status'] ?? 'active') ?>">
                                    <?= htmlspecialchars($account['status'] ?? 'Active') ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditAccountForm(<?= $account['id'] ?>, '<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>', '', '<?= htmlspecialchars($account['role'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $account['id'] ?>, '<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                         <tr id="noAccountsFound" style="display: none;">
                              <td colspan="5" class="no-accounts">No accounts found matching your search.</td>
                         </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-accounts">No accounts found<?= !empty($status_filter) ? ' with status: ' . htmlspecialchars($status_filter) : '' ?>.</td>
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
                <input type="hidden" name="ajax" value="1">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" required>
                <label for="role">Role:</label>
                <select id="role" name="role" autocomplete="role" required>
                     <?php if (empty($roles)): ?>
                          <option value="" disabled>No roles available</option>
                     <?php else: ?>
                         <?php foreach ($roles as $role): ?>
                             <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                         <?php endforeach; ?>
                     <?php endif; ?>
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
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username:</label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password: <small>(Leave blank to keep current)</small></label>
                <input type="text" id="edit-password" name="password" autocomplete="new-password">
                <label for="edit-role">Role:</label>
                <select id="edit-role" name="role" autocomplete="role" required>
                     <?php if (empty($roles)): ?>
                          <option value="" disabled>No roles available</option>
                     <?php else: ?>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                        <?php endforeach; ?>
                     <?php endif; ?>
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
             <input type="hidden" id="status-change-id" name="id">
             <input type="hidden" id="status-change-value" name="status">
             <input type="hidden" name="formType" value="status">
             <input type="hidden" name="ajax" value="1">
            <div class="modal-buttons">
                <button class="approve-btn" onclick="confirmStatusChange('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <!-- REMOVED Reject Button -->
                <button class="archive-btn" onclick="confirmStatusChange('Archived')">
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
    <script>
        $(document).ready(function() {
            function performSearch() {
                const searchTerm = $('#searchInput').val().toLowerCase().trim();
                let visibleCount = 0;
                let totalRows = 0;

                $('.accounts-table tbody tr').each(function() {
                    const row = $(this);
                    if (row.attr('id') === 'noAccountsFound') return;
                    if (row.find('.no-accounts').length > 0) {
                        row.hide();
                        return;
                    }
                    totalRows++;
                    const rowText = row.text().toLowerCase();
                    if (rowText.includes(searchTerm)) {
                        row.show();
                        visibleCount++;
                    } else {
                        row.hide();
                    }
                });

                 if (visibleCount === 0 && totalRows > 0 && searchTerm !== '') {
                     $('#noAccountsFound').show();
                 } else {
                     $('#noAccountsFound').hide();
                     if (searchTerm === '' && totalRows === 0 && $('.accounts-table tbody .no-accounts').length > 0) {
                          $('.accounts-table tbody .no-accounts').closest('tr').show();
                     }
                 }
            }
            $('#searchInput').on('input', performSearch);
            $('#searchBtn').on('click', performSearch);
             if ($('#searchInput').val()) {
                 performSearch();
             }
        });

        function filterByStatus() {
            const selectedStatus = document.getElementById('statusFilter').value;
            const url = new URL(window.location.href);
            const currentSort = url.searchParams.get('sort');
            const currentDirection = url.searchParams.get('direction');
            const params = {};
            if (selectedStatus) params.status = selectedStatus;
            if (currentSort) params.sort = currentSort;
            if (currentDirection) params.direction = currentDirection;
            window.location.search = Object.keys(params).map(key => key + '=' + encodeURIComponent(params[key])).join('&');
        }

         let currentAccountId = null;

         function openAddAccountForm() {
             $('#addAccountForm')[0].reset();
             $('#addAccountError').text('').hide();
             $('#addAccountOverlay').css('display', 'flex');
         }
         function closeAddAccountForm() { $('#addAccountOverlay').hide(); }

         function openEditAccountForm(id, username, password, role) {
             $('#edit-id').val(id);
             $('#edit-username').val(username);
             $('#edit-password').val('');
             $('#edit-role').val(role);
             $('#editAccountError').text('').hide();
             $('#editAccountOverlay').css('display', 'flex');
         }
         function closeEditAccountForm() { $('#editAccountOverlay').hide(); }

         function openStatusModal(id, username) {
             currentAccountId = id;
             $('#statusMessage').text(`Change status for account: ${username}`);
             $('#status-change-id').val(id);
             $('#statusModal').css('display', 'flex');
         }
         function closeStatusModal() {
             $('#statusModal').hide();
             currentAccountId = null;
         }

         function confirmStatusChange(newStatus) {
             $('#status-change-value').val(newStatus);
             submitStatusChange();
         }

         function submitStatusChange() {
             const accountId = $('#status-change-id').val();
             const newStatus = $('#status-change-value').val();

             if (!accountId || !newStatus) {
                 showToast('Error: Missing account ID or status.', 'error');
                 return;
             }
             $.ajax({
                 url: window.location.pathname,
                 type: 'POST',
                 data: { ajax: 1, formType: 'status', id: accountId, status: newStatus },
                 dataType: 'json',
                 success: function(response) {
                     if (response.success) {
                         showToast('Status updated successfully!', 'success');
                         if (response.reload) setTimeout(() => { window.location.reload(); }, 1000);
                     } else {
                         showToast('Error: ' + (response.message || 'Failed to update status.'), 'error');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error("Status change AJAX error:", status, error, xhr.responseText);
                     showToast('Error: Could not connect to server.', 'error');
                 },
                 complete: function() { closeStatusModal(); }
             });
         }

         $(document).ready(function() {
             $('#addAccountForm').on('submit', function(e) {
                 e.preventDefault();
                 $.ajax({
                     url: window.location.pathname, type: 'POST', data: $(this).serialize(), dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast('Account added successfully!', 'success');
                             closeAddAccountForm();
                             if (response.reload) setTimeout(() => { window.location.reload(); }, 1000);
                         } else {
                             $('#addAccountError').text(response.message || 'Failed to add account.').show();
                             showToast('Error: ' + (response.message || 'Failed to add account.'), 'error');
                         }
                     },
                     error: function(xhr, status, error) {
                         console.error("Add account AJAX error:", status, error, xhr.responseText);
                         $('#addAccountError').text('Server error occurred.').show();
                         showToast('Error: Could not connect to server.', 'error');
                     }
                 });
             });

             $('#editAccountForm').on('submit', function(e) {
                 e.preventDefault();
                 $.ajax({
                     url: window.location.pathname, type: 'POST', data: $(this).serialize(), dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast('Account updated successfully!', 'success');
                             closeEditAccountForm();
                             if (response.reload) setTimeout(() => { window.location.reload(); }, 1000);
                         } else {
                             $('#editAccountError').text(response.message || 'Failed to update account.').show();
                             showToast('Error: ' + (response.message || 'Failed to update account.'), 'error');
                         }
                     },
                     error: function(xhr, status, error) {
                         console.error("Edit account AJAX error:", status, error, xhr.responseText);
                         $('#editAccountError').text('Server error occurred.').show();
                         showToast('Error: Could not connect to server.', 'error');
                     }
                 });
             });
         });
    </script>
</body>
</html>
<?php
if ($conn instanceof mysqli) {
    $conn->close();
}
?>