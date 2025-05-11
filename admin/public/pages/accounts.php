<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Admin');

// Sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'username';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC';

$allowed_columns = ['username', 'name', 'email_address', 'contact_number', 'role', 'created_at', 'status'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'username';
}
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'ASC';
}

$status_filter = $_GET['status'] ?? '';

$roles = [];
$roleQuery = "SELECT role_name FROM roles WHERE status = 'active'";
$resultRoles = $conn->query($roleQuery);
if ($resultRoles && $resultRoles->num_rows > 0) {
    while ($row = $resultRoles->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
}

function returnJsonResponse($success, $reload, $message = '') {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $formType = $_POST['formType'] ?? '';

    if ($formType == 'add') {
        $username = trim($_POST['username']);
        $name = trim($_POST['name']);
        $email_address = trim($_POST['email_address']);
        $contact_number = trim($_POST['contact_number']);
        $role = $_POST['role'];
        $status = 'Active';
        $created_at = date('Y-m-d H:i:s');

        // Validation
        if (empty($username) || empty($name) || empty($email_address) || empty($contact_number) || empty($role)) {
            returnJsonResponse(false, false, 'All fields are required.');
        }
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            returnJsonResponse(false, false, 'Invalid email address format.');
        }
        if (!preg_match('/^\d{10,15}$/', $contact_number)) { // Example: 10-15 digits, adjust as needed
            returnJsonResponse(false, false, 'Invalid contact number format (10-15 digits required).');
        }
        if (strlen($contact_number) < 4) {
            returnJsonResponse(false, false, 'Contact number must be at least 4 digits long for password generation.');
        }
        if (strlen($username) > 50 || strlen($name) > 255 || strlen($email_address) > 255 || strlen($contact_number) > 20 || strlen($role) > 255) {
            returnJsonResponse(false, false, 'One or more fields exceed maximum length.');
        }


        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? OR email_address = ?");
        $checkStmt->bind_param("ss", $username, $email_address);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            returnJsonResponse(false, false, 'Username or Email address already exists.');
        }
        $checkStmt->close();

        // Automated password generation
        $last_four_digits = substr($contact_number, -4);
        $generated_password_plain = $username . $last_four_digits;
        $hashed_password = password_hash($generated_password_plain, PASSWORD_DEFAULT);

        if ($hashed_password === false) {
            error_log("Password hashing failed for add account: " . $username);
            returnJsonResponse(false, false, 'Password generation failed. Please try again.');
        }

        // Ensure your 'accounts' table has 'name', 'email_address', 'contact_number' columns
        $stmt = $conn->prepare("INSERT INTO accounts (username, name, email_address, contact_number, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $name, $email_address, $contact_number, $hashed_password, $role, $status, $created_at);

        if ($stmt->execute()) {
            $stmt->close();
            // Provide the plain text password in the success message for the admin
            returnJsonResponse(true, true, 'Account added successfully. Password: ' . $generated_password_plain);
        } else {
            error_log("Add account failed: " . $stmt->error . " for username: " . $username);
            $stmt->close();
            returnJsonResponse(false, false, 'Database error adding account. Check server logs.');
        }

    } elseif ($formType == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $name = trim($_POST['name']);
        $email_address = trim($_POST['email_address']);
        $contact_number = trim($_POST['contact_number']);
        $password_input = $_POST['password']; // New password if provided
        $role = $_POST['role'];

        // Validation
        if (empty($id) || !filter_var($id, FILTER_VALIDATE_INT)) {
            returnJsonResponse(false, false, 'Invalid account ID.');
        }
        if (empty($username) || empty($name) || empty($email_address) || empty($contact_number) || empty($role)) {
            returnJsonResponse(false, false, 'Username, Name, Email, Contact, and Role are required.');
        }
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            returnJsonResponse(false, false, 'Invalid email address format.');
        }
        if (!preg_match('/^\d{10,15}$/', $contact_number)) { // Example: 10-15 digits
            returnJsonResponse(false, false, 'Invalid contact number format (10-15 digits required).');
        }
        if (strlen($username) > 50 || strlen($name) > 255 || strlen($email_address) > 255 || strlen($contact_number) > 20 || strlen($role) > 255) {
            returnJsonResponse(false, false, 'One or more fields exceed maximum length.');
        }
        if (!empty($password_input) && strlen($password_input) < 6) { // Example minimum password length
             returnJsonResponse(false, false, 'New password must be at least 6 characters long.');
        }


        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE (username = ? OR email_address = ?) AND id != ?");
        $checkStmt->bind_param("ssi", $username, $email_address, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            returnJsonResponse(false, false, 'Username or Email address already exists for another account.');
        }
        $checkStmt->close();

        $sql_update_parts = [];
        $params_update = [];
        $types = "";

        $sql_update_parts[] = "username = ?"; $params_update[] = $username; $types .= "s";
        $sql_update_parts[] = "name = ?"; $params_update[] = $name; $types .= "s";
        $sql_update_parts[] = "email_address = ?"; $params_update[] = $email_address; $types .= "s";
        $sql_update_parts[] = "contact_number = ?"; $params_update[] = $contact_number; $types .= "s";
        $sql_update_parts[] = "role = ?"; $params_update[] = $role; $types .= "s";

        if (!empty($password_input)) {
            $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
            if ($hashed_password === false) {
                error_log("Password hashing failed for edit account ID: $id");
                returnJsonResponse(false, false, 'Password update failed. Please try again.');
            }
            $sql_update_parts[] = "password = ?";
            $params_update[] = $hashed_password;
            $types .= "s";
        }

        $params_update[] = $id; $types .= "i";
        $sql = "UPDATE accounts SET " . implode(", ", $sql_update_parts) . " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Edit account prepare failed: " . $conn->error);
            returnJsonResponse(false, false, 'Database error preparing update. Check server logs.');
        }
        $stmt->bind_param($types, ...$params_update);

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true, 'Account updated successfully.');
        } else {
            error_log("Edit account failed: " . $stmt->error . " for ID: " . $id);
            $stmt->close();
            returnJsonResponse(false, false, 'Database error updating account. Check server logs.');
        }

    } elseif ($formType == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
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
            error_log("Change status failed: " . $stmt->error . " for ID: " . $id);
            $stmt->close();
            returnJsonResponse(false, false, 'Failed to change status. Check server logs.');
        }
    }
}

// Fetch Accounts Data - Include new columns
$sql = "SELECT id, username, name, email_address, contact_number, role, status, created_at FROM accounts";
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

function getSortUrl($column, $currentColumn, $currentDirection, $currentStatus) {
    $newDirection = ($column === $currentColumn && strtoupper($currentDirection) === 'ASC') ? 'DESC' : 'ASC';
    $urlParams = ['sort' => $column, 'direction' => $newDirection];
    if (!empty($currentStatus)) $urlParams['status'] = $currentStatus;
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
        .role-label.role-super-admin { background-color: #ffeeba; color: #856404; border-color: #ffd32a;}
        .role-label.role-orders { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb;}
        .password-generation-info { font-size: 0.9em; color: #6c757d; margin-bottom: 15px; }
        .required-asterisk { color: red; margin-left: 2px;}
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
                        <th class="sortable <?= $sort_column == 'username' ? 'active' : '' ?>"><a href="<?= getSortUrl('username', $sort_column, $sort_direction, $status_filter) ?>">Username <?= getSortIcon('username', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'name' ? 'active' : '' ?>"><a href="<?= getSortUrl('name', $sort_column, $sort_direction, $status_filter) ?>">Name <?= getSortIcon('name', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'email_address' ? 'active' : '' ?>"><a href="<?= getSortUrl('email_address', $sort_column, $sort_direction, $status_filter) ?>">Email <?= getSortIcon('email_address', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'contact_number' ? 'active' : '' ?>"><a href="<?= getSortUrl('contact_number', $sort_column, $sort_direction, $status_filter) ?>">Contact No. <?= getSortIcon('contact_number', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'role' ? 'active' : '' ?>"><a href="<?= getSortUrl('role', $sort_column, $sort_direction, $status_filter) ?>">Role <?= getSortIcon('role', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'created_at' ? 'active' : '' ?>"><a href="<?= getSortUrl('created_at', $sort_column, $sort_direction, $status_filter) ?>">Account Age <?= getSortIcon('created_at', $sort_column, $sort_direction) ?></a></th>
                        <th class="sortable <?= $sort_column == 'status' ? 'active' : '' ?>"><a href="<?= getSortUrl('status', $sort_column, $sort_direction, $status_filter) ?>">Status <?= getSortIcon('status', $sort_column, $sort_direction) ?></a></th>
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
                                else $account_age = "Just now";
                            } catch (Exception $e) { $account_age = "Invalid date"; }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td><?= htmlspecialchars($account['name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($account['email_address'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($account['contact_number'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                    $roleName = $account['role'] ?? '';
                                    $roleDisplay = !empty($roleName) ? ucfirst(str_replace('_', ' ', $roleName)) : 'Unknown';
                                    $rolesWithoutSpecificStyle = ['accountant', 'secretary'];
                                    $roleClasses = 'role-label';
                                    if (!empty($roleName)) {
                                        $lowerRole = strtolower(str_replace(' ', '-', $roleName));
                                        if (!in_array(strtolower($roleName), $rolesWithoutSpecificStyle)) {
                                            $roleClasses .= ' role-' . $lowerRole;
                                        }
                                    } else { $roleClasses .= ' role-unknown'; }
                                    echo "<span class='$roleClasses'>$roleDisplay</span>";
                                    ?>
                                </td>
                                <td><?= $account_age ?></td>
                                <td class="<?= 'status-' . strtolower($account['status'] ?? 'active') ?>">
                                    <?= htmlspecialchars($account['status'] ?? 'Active') ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditAccountForm(
                                        <?= $account['id'] ?>,
                                        '<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($account['name'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($account['email_address'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($account['contact_number'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($account['role'], ENT_QUOTES) ?>'
                                    )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $account['id'] ?>, '<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                         <tr id="noAccountsFound" style="display: none;">
                              <td colspan="8" class="no-accounts">No accounts found matching your search.</td>
                         </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-accounts">No accounts found<?= !empty($status_filter) ? ' with status: ' . htmlspecialchars($status_filter) : '' ?>.</td>
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
                <input type="hidden" name="formType" value="add"><input type="hidden" name="ajax" value="1">
                <label for="add-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-username" name="username" autocomplete="username" required maxlength="50">
                <label for="add-name">Full Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="add-name" name="name" required maxlength="255">
                <label for="add-email_address">Email Address:<span class="required-asterisk">*</span></label>
                <input type="email" id="add-email_address" name="email_address" required maxlength="255">
                <label for="add-contact_number">Contact Number (10-15 digits):<span class="required-asterisk">*</span></label>
                <input type="text" id="add-contact_number" name="contact_number" required pattern="\d{10,15}" title="Enter 10 to 15 digits" maxlength="15">
                <p class="password-generation-info">Password will be: username + last 4 digits of contact number.</p>
                <label for="add-role">Role:<span class="required-asterisk">*</span></label>
                <select id="add-role" name="role" autocomplete="role" required>
                     <?php if (empty($roles)): ?><option value="" disabled>No roles available</option>
                     <?php else: foreach ($roles as $role): ?>
                         <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                     <?php endforeach; endif; ?>
                </select>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()"><i class="fas fa-times"></i> Cancel</button>
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
                <input type="hidden" name="formType" value="edit"><input type="hidden" name="ajax" value="1"><input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required maxlength="50">
                <label for="edit-name">Full Name:<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-name" name="name" required maxlength="255">
                <label for="edit-email_address">Email Address:<span class="required-asterisk">*</span></label>
                <input type="email" id="edit-email_address" name="email_address" required maxlength="255">
                <label for="edit-contact_number">Contact Number (10-15 digits):<span class="required-asterisk">*</span></label>
                <input type="text" id="edit-contact_number" name="contact_number" required pattern="\d{10,15}" title="Enter 10 to 15 digits" maxlength="15">
                <label for="edit-password">New Password: <small>(Leave blank to keep current)</small></label>
                <input type="password" id="edit-password" name="password" autocomplete="new-password" minlength="6">
                <label for="edit-role">Role:<span class="required-asterisk">*</span></label>
                <select id="edit-role" name="role" autocomplete="role" required>
                     <?php if (empty($roles)): ?><option value="" disabled>No roles available</option>
                     <?php else: foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                     <?php endforeach; endif; ?>
                </select>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <div id="statusModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2>Change Status</h2><p id="statusMessage"></p>
            <input type="hidden" id="status-change-id" name="id"><input type="hidden" id="status-change-value" name="status">
            <input type="hidden" name="formType" value="status"><input type="hidden" name="ajax" value="1">
            <div class="modal-buttons">
                <button class="approve-btn" onclick="confirmStatusChange('Active')"><i class="fas fa-check"></i> Active</button>
                <button class="archive-btn" onclick="confirmStatusChange('Archived')"><i class="fas fa-archive"></i> Archive</button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeStatusModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script>
    <script>
        $(document).ready(function() {
            function performSearch() {
                const searchTerm = $('#searchInput').val().toLowerCase().trim();
                let visibleCount = 0; let totalRows = 0;
                $('.accounts-table tbody tr').each(function() {
                    const row = $(this);
                    if (row.attr('id') === 'noAccountsFound') return;
                    if (row.find('.no-accounts').length > 0) { row.hide(); return; }
                    totalRows++;
                    const rowText = row.text().toLowerCase();
                    if (rowText.includes(searchTerm)) { row.show(); visibleCount++; } else { row.hide(); }
                });
                 if (visibleCount === 0 && totalRows > 0 && searchTerm !== '') $('#noAccountsFound').show();
                 else {
                     $('#noAccountsFound').hide();
                     if (searchTerm === '' && totalRows === 0 && $('.accounts-table tbody .no-accounts').length > 0) {
                          $('.accounts-table tbody .no-accounts').closest('tr').show();
                     }
                 }
            }
            $('#searchInput').on('input', performSearch);
            $('#searchBtn').on('click', performSearch);
            if ($('#searchInput').val()) performSearch();
        });

        function filterByStatus() {
            const selectedStatus = document.getElementById('statusFilter').value;
            const url = new URL(window.location.href);
            const params = {};
            if (selectedStatus) params.status = selectedStatus;
            const currentSort = url.searchParams.get('sort');
            const currentDirection = url.searchParams.get('direction');
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

         function openEditAccountForm(id, username, name, email_address, contact_number, role) {
             $('#edit-id').val(id);
             $('#edit-username').val(username);
             $('#edit-name').val(name || ''); // Handle null values from DB
             $('#edit-email_address').val(email_address || '');
             $('#edit-contact_number').val(contact_number || '');
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
         function closeStatusModal() { $('#statusModal').hide(); currentAccountId = null; }

         function confirmStatusChange(newStatus) {
             $('#status-change-value').val(newStatus);
             submitStatusChange();
         }

         function submitStatusChange() {
             const accountId = $('#status-change-id').val();
             const newStatus = $('#status-change-value').val();
             if (!accountId || !newStatus) { showToast('Error: Missing account ID or status.', 'error'); return; }
             $.ajax({
                 url: window.location.pathname, type: 'POST',
                 data: { ajax: 1, formType: 'status', id: accountId, status: newStatus },
                 dataType: 'json',
                 success: function(response) {
                     if (response.success) {
                         showToast(response.message || 'Status updated successfully!', 'success');
                         if (response.reload) setTimeout(() => { window.location.reload(); }, 1000);
                     } else showToast('Error: ' + (response.message || 'Failed to update status.'), 'error');
                 },
                 error: function(xhr) { console.error("Status change AJAX error:", xhr.responseText); showToast('Error: Could not connect to server.', 'error'); },
                 complete: function() { closeStatusModal(); }
             });
         }

         $(document).ready(function() {
             $('#addAccountForm').on('submit', function(e) {
                 e.preventDefault();
                 const contactNumber = $('#add-contact_number').val();
                 if (!/^\d{10,15}$/.test(contactNumber) || contactNumber.length < 4) {
                     $('#addAccountError').text('Valid contact number (10-15 digits) is required for password generation.').show();
                     showToast('Invalid contact number for password generation.', 'error');
                     return;
                 }
                 $('#addAccountError').text('').hide(); // Clear previous error

                 $.ajax({
                     url: window.location.pathname, type: 'POST', data: $(this).serialize(), dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast(response.message || 'Account added successfully!', 'success');
                             closeAddAccountForm();
                             if (response.reload) setTimeout(() => { window.location.reload(); }, 1500);
                         } else {
                             $('#addAccountError').text(response.message || 'Failed to add account.').show();
                             showToast('Error: ' + (response.message || 'Failed to add account.'), 'error');
                         }
                     },
                     error: function(xhr) { console.error("Add account AJAX error:", xhr.responseText); $('#addAccountError').text('Server error occurred.').show(); showToast('Error: Could not connect to server.', 'error');}
                 });
             });

             $('#editAccountForm').on('submit', function(e) {
                 e.preventDefault();
                  const contactNumber = $('#edit-contact_number').val();
                 if (!/^\d{10,15}$/.test(contactNumber)) {
                     $('#editAccountError').text('Valid contact number (10-15 digits) is required.').show();
                     showToast('Invalid contact number.', 'error');
                     return;
                 }
                 $('#editAccountError').text('').hide(); // Clear previous error
                 $.ajax({
                     url: window.location.pathname, type: 'POST', data: $(this).serialize(), dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast(response.message ||'Account updated successfully!', 'success');
                             closeEditAccountForm();
                             if (response.reload) setTimeout(() => { window.location.reload(); }, 1000);
                         } else {
                             $('#editAccountError').text(response.message || 'Failed to update account.').show();
                             showToast('Error: ' + (response.message || 'Failed to update account.'), 'error');
                         }
                     },
                     error: function(xhr) { console.error("Edit account AJAX error:", xhr.responseText); $('#editAccountError').text('Server error occurred.').show(); showToast('Error: Could not connect to server.', 'error');}
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