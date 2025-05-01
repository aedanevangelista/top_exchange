<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Admin'); // Ensure user has access

// --- Sorting ---
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'username'; // Default sort column
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC'; // Default sort direction

// Validate sort column
$allowed_columns = ['username', 'role', 'created_at', 'status']; // 'Account Age' sorts by 'created_at'
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'username'; // Default back if invalid
}

// Validate sort direction
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'ASC'; // Default back if invalid
}

// --- Status Filter ---
$status_filter = $_GET['status'] ?? '';

// --- Fetch Roles (No changes needed here) ---
$roles = [];
$roleQuery = "SELECT role_name FROM roles WHERE status = 'active'";
$resultRoles = $conn->query($roleQuery);
if ($resultRoles && $resultRoles->num_rows > 0) {
    while ($row = $resultRoles->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
}

// --- AJAX Handlers (No changes needed here) ---
function returnJsonResponse($success, $reload, $message = '') {
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    exit;
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $formType = $_POST['formType'] ?? '';

    // Add Account
    if ($formType == 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $status = 'Active';
        $created_at = date('Y-m-d H:i:s');

        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            returnJsonResponse(false, false, 'Username already exists.');
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO accounts (username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?)");
        // IMPORTANT: In a real application, hash the password!
        // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // $stmt->bind_param("sssss", $username, $hashed_password, $role, $status, $created_at);
        $stmt->bind_param("sssss", $username, $password, $role, $status, $created_at); // Using plain text for now as per original

        if ($stmt->execute()) {
            returnJsonResponse(true, true);
        } else {
            error_log("Add account failed: " . $stmt->error);
            returnJsonResponse(false, false, 'Database error adding account.');
        }
        $stmt->close();
        exit;
    }

    // Edit Account
    elseif ($formType == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Consider if password should always be updated
        $role = $_POST['role'];

        $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            returnJsonResponse(false, false, 'Username already exists.');
        }
        $checkStmt->close();

        // Decide whether to update password
        if (!empty($password)) {
            // IMPORTANT: Hash the new password!
            // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
            // $stmt->bind_param("sssi", $username, $hashed_password, $role, $id);
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $password, $role, $id); // Plain text for now
        } else {
            // Don't update password if field is empty
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $role, $id);
        }

        if ($stmt->execute()) {
            returnJsonResponse(true, true);
        } else {
            error_log("Edit account failed: " . $stmt->error);
            returnJsonResponse(false, false, 'Database error updating account.');
        }
        $stmt->close();
        exit;
    }

    // Change Status
    elseif ($formType == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        // Validate status
        $allowed_statuses = ['Active', 'Reject', 'Archived'];
        if (!in_array($status, $allowed_statuses)) {
             returnJsonResponse(false, false, 'Invalid status value.');
        }

        $stmt = $conn->prepare("UPDATE accounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            returnJsonResponse(true, true);
        } else {
            error_log("Change status failed: " . $stmt->error);
            returnJsonResponse(false, false, 'Failed to change status.');
        }
        $stmt->close();
        exit;
    }
}

// --- Fetch Accounts Data ---
$sql = "SELECT id, username, role, status, created_at FROM accounts";
$params = [];
$param_types = "";

// Apply status filter if present
if (!empty($status_filter)) {
    $sql .= " WHERE status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Apply sorting
$sql .= " ORDER BY {$sort_column} {$sort_direction}";

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
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
     // Handle error appropriately, maybe show a message
}

// --- Helper Functions for Sorting ---
function getSortUrl($column, $currentColumn, $currentDirection, $currentStatus) {
    $newDirection = ($column === $currentColumn && strtoupper($currentDirection) === 'ASC') ? 'DESC' : 'ASC';
    $urlParams = [
        'sort' => $column,
        'direction' => $newDirection
    ];
    // Preserve status filter
    if (!empty($currentStatus)) {
        $urlParams['status'] = $currentStatus;
    }
    return "?" . http_build_query($urlParams);
}

function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column !== $currentColumn) {
        return '<i class="fas fa-sort"></i>'; // Neutral icon
    } elseif (strtoupper($currentDirection) === 'ASC') {
        return '<i class="fas fa-sort-up"></i>'; // Ascending icon
    } else {
        return '<i class="fas fa-sort-down"></i>'; // Descending icon
    }
}

$conn->close(); // Close connection after all queries
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
        /* Add styles for search bar */
        .search-container {
            display: flex;
            align-items: center;
            margin: 0 15px; /* Adjust margin as needed */
        }

        .search-container input {
            padding: 8px 12px;
            border-radius: 20px 0 0 20px;
            border: 1px solid #ddd;
            font-size: 12px;
            width: 200px; /* Adjust width */
            border-right: none;
        }

        .search-container .search-btn {
            background-color: #2980b9; /* Match other button styles */
            color: white;
            border: 1px solid #2980b9;
            border-radius: 0 20px 20px 0;
            padding: 8px 12px;
            cursor: pointer;
            margin-left: -1px; /* Overlap border */
        }

        .search-container .search-btn:hover {
            background-color: #2471a3;
        }

        /* Adjust header layout */
        .accounts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .accounts-header h1 {
             margin-right: auto; /* Push other elements to the right */
        }

        /* Style for sortable headers */
        .accounts-table th.sortable a {
            color: inherit;
            text-decoration: none;
            display: inline-block; /* Allows icon placement */
        }
         .accounts-table th.sortable a:hover {
             color: #0056b3; /* Example hover color */
         }
        .accounts-table th.sortable i {
            margin-left: 5px;
            color: #aaa; /* Lighter color for icons */
        }
         .accounts-table th.sortable.active i {
             color: #333; /* Darker color for active sort icon */
         }

    </style>
</head>
<body>
    <div id="toast-container"></div>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Staff Accounts</h1>

            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search accounts...">
                <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
            </div>

            <!-- Status Filter Dropdown -->
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="" <?= empty($status_filter) ? 'selected' : '' ?>>All</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Reject" <?= $status_filter == 'Reject' ? 'selected' : '' ?>>Reject</option>
                    <option value="Archived" <?= $status_filter == 'Archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <!-- Add Account Button -->
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>

        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <!-- Make headers sortable -->
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
                            // Calculate account age (no changes needed here)
                            $created_at = new DateTime($account['created_at']);
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
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td>
                                    <?php
                                    $role = ucfirst($account['role']);
                                    echo "<span class='role-label role-" . strtolower($account['role']) . "'>$role</span>";
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

    <!-- Modals (Add, Edit, Status) - No changes needed here -->
    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            <div id="addAccountError" class="error-message"></div>
            <form id="addAccountForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" required>
                <label for="role">Role:</label>
                <select id="role" name="role" autocomplete="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                    <?php endforeach; ?>
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
                <input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username:</label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password: <small>(Leave blank to keep current)</small></label>
                <input type="text" id="edit-password" name="password" autocomplete="new-password">
                <label for="edit-role">Role:</label>
                <select id="edit-role" name="role" autocomplete="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="save-btn"><i class=\"fas fa-save\"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <div id="statusModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeStatus('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <button class="reject-btn" onclick="changeStatus('Reject')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="archive-btn" onclick="changeStatus('Archived')">
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
        // --- Client-side Search ---
        $(document).ready(function() {
            function performSearch() {
                const searchTerm = $('#searchInput').val().toLowerCase().trim();
                let visibleCount = 0;
                $('.accounts-table tbody tr').each(function() {
                    const row = $(this);
                    // Skip the 'no accounts found' row template
                    if (row.attr('id') === 'noAccountsFound') {
                        return; // continue to next iteration
                    }
                    // Check if the original 'no accounts' row exists
                    if (row.find('.no-accounts').length > 0) {
                         // If the original 'no accounts' row is the ONLY row, hide it during search
                         if ($('.accounts-table tbody tr').length === 1) {
                             row.hide();
                         }
                         return; // continue
                    }

                    const rowText = row.text().toLowerCase();
                    if (rowText.includes(searchTerm)) {
                        row.show();
                        visibleCount++;
                    } else {
                        row.hide();
                    }
                });

                 // Show/hide the 'no accounts found' message row
                 if (visibleCount === 0 && searchTerm !== '') {
                     $('#noAccountsFound').show();
                 } else {
                     $('#noAccountsFound').hide();
                     // If search is cleared and original 'no accounts' row exists, show it
                     if(searchTerm === '' && $('.accounts-table tbody .no-accounts').length > 0) {
                          $('.accounts-table tbody .no-accounts').closest('tr').show();
                     }
                 }
            }

            $('#searchInput').on('input', performSearch);
            $('#searchBtn').on('click', performSearch); // Also trigger search on button click
        });

        // --- Status Filter Function ---
        function filterByStatus() {
            const selectedStatus = document.getElementById('statusFilter').value;
            // Preserve existing sort parameters if any
            const url = new URL(window.location.href);
            const currentSort = url.searchParams.get('sort');
            const currentDirection = url.searchParams.get('direction');

            const params = {};
            if (selectedStatus) {
                params.status = selectedStatus;
            }
            if (currentSort) {
                params.sort = currentSort;
            }
             if (currentDirection) {
                params.direction = currentDirection;
            }

            // Build the new query string
            const queryString = Object.keys(params).map(key => key + '=' + encodeURIComponent(params[key])).join('&');
            window.location.search = queryString; // Reload page with new filter/sort
        }
    </script>
</body>
</html>