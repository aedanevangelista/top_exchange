<?php
session_start();
include "../../backend/db_connection.php"; // Establishes $conn
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

// --- Fetch Roles ---
$roles = [];
$roleQuery = "SELECT role_name FROM roles WHERE status = 'active'";
$resultRoles = $conn->query($roleQuery);
if ($resultRoles && $resultRoles->num_rows > 0) {
    while ($row = $resultRoles->fetch_assoc()) {
        $roles[] = $row['role_name'];
    }
}
// Not closing $resultRoles here assuming it's small or implicitly handled

// --- AJAX Handlers ---
function returnJsonResponse($success, $reload, $message = '') {
    // Note: This function calls exit, so connection closing needs careful consideration
    // if database operations happen *after* a potential AJAX call.
    // For now, assuming AJAX handlers are self-contained and main page load continues.
    global $conn; // Make connection available if needed within AJAX, though ideally handled differently
    echo json_encode(['success' => $success, 'reload' => $reload, 'message' => $message]);
    // $conn->close(); // Don't close here if main script continues
    exit;
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    // Important: If AJAX handlers use $conn, ensure it's not closed before they run.
    // The current structure seems okay as AJAX exits, but keep this in mind.
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
            $checkStmt->close(); // Close check statement
            returnJsonResponse(false, false, 'Username already exists.');
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO accounts (username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?)");
        // IMPORTANT: In a real application, hash the password!
        $stmt->bind_param("sssss", $username, $password, $role, $status, $created_at); // Using plain text for now

        if ($stmt->execute()) {
             $stmt->close(); // Close insert statement
            returnJsonResponse(true, true);
        } else {
            error_log("Add account failed: " . $stmt->error);
             $stmt->close(); // Close insert statement even on failure
            returnJsonResponse(false, false, 'Database error adding account.');
        }
        // Exit is handled by returnJsonResponse
    }

    // Edit Account
    elseif ($formType == 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $password = $_POST['password'];
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

        $stmt = null; // Initialize stmt
        if (!empty($password)) {
            // IMPORTANT: Hash the new password!
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $password, $role, $id); // Plain text for now
        } else {
            $stmt = $conn->prepare("UPDATE accounts SET username = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $role, $id);
        }

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true);
        } else {
            error_log("Edit account failed: " . $stmt->error);
            $stmt->close();
            returnJsonResponse(false, false, 'Database error updating account.');
        }
         // Exit is handled by returnJsonResponse
    }

    // Change Status
    elseif ($formType == 'status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $allowed_statuses = ['Active', 'Reject', 'Archived'];
        if (!in_array($status, $allowed_statuses)) {
             returnJsonResponse(false, false, 'Invalid status value.');
        }

        $stmt = $conn->prepare("UPDATE accounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            returnJsonResponse(true, true);
        } else {
            error_log("Change status failed: " . $stmt->error);
            $stmt->close();
            returnJsonResponse(false, false, 'Failed to change status.');
        }
         // Exit is handled by returnJsonResponse
    }
} // End AJAX handling block

// --- Fetch Accounts Data for Page Load ---
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
    // Close connection before dying if prepare fails early
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
    $stmt->close(); // Close the statement after fetching results
} else {
     error_log("Fetch accounts failed: " . $conn->error);
     $stmt->close(); // Close statement even if get_result failed
     // Handle error appropriately, maybe show a message
}

// --- Helper Functions for Sorting ---
// These functions don't need the database connection
function getSortUrl($column, $currentColumn, $currentDirection, $currentStatus) {
    $newDirection = ($column === $currentColumn && strtoupper($currentDirection) === 'ASC') ? 'DESC' : 'ASC';
    $urlParams = [
        'sort' => $column,
        'direction' => $newDirection
    ];
    if (!empty($currentStatus)) {
        $urlParams['status'] = $currentStatus;
    }
    return "?" . http_build_query($urlParams);
}

function getSortIcon($column, $currentColumn, $currentDirection) {
    if ($column !== $currentColumn) {
        return '<i class="fas fa-sort"></i>';
    } elseif (strtoupper($currentDirection) === 'ASC') {
        return '<i class="fas fa-sort-up"></i>';
    } else {
        return '<i class="fas fa-sort-down"></i>';
    }
}

// *** DO NOT CLOSE CONNECTION HERE ***
// $conn->close(); // <-- REMOVED FROM HERE

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

    <?php
    // Include the sidebar - $conn should still be open here
    include '../sidebar.php';
    ?>

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
                            // Calculate account age
                            try {
                                $created_at = new DateTime($account['created_at']);
                                $now = new DateTime();
                                $diff = $created_at->diff($now);
                                if ($diff->y > 0) {
                                    $account_age = $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
                                } elseif ($diff->m > 0) {
                                    $account_age = $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
                                } elseif ($diff->d > 0) {
                                    $account_age = $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
                                } elseif ($diff->h > 0 || $diff->i > 0 || $diff->s >= 0) {
                                     $account_age = "Just now";
                                } else {
                                     $account_age = "Unknown"; // Fallback
                                }
                            } catch (Exception $e) {
                                $account_age = "Invalid date"; // Handle potential date parsing errors
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td>
                                    <?php
                                    $role = ucfirst($account['role']);
                                    // Ensure role is not empty before creating class name
                                    $roleClass = !empty($account['role']) ? strtolower($account['role']) : 'unknown';
                                    echo "<span class='role-label role-" . $roleClass . "'>$role</span>";
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

    <!-- Modals (Add, Edit, Status) -->
     <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            <div id="addAccountError" class="error-message"></div>
            <form id="addAccountForm" method="POST" class="account-form" action="">
                <input type="hidden" name="formType" value="add">
                <input type="hidden" name="ajax" value="1"> <!-- Ensure AJAX flag is sent -->
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" required> <!-- Consider type="password" -->
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
                <input type="hidden" name="ajax" value="1"> <!-- Ensure AJAX flag is sent -->
                <input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username:</label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password: <small>(Leave blank to keep current)</small></label>
                <input type="text" id="edit-password" name="password" autocomplete="new-password"> <!-- Consider type="password" -->
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
             <!-- Hidden fields for AJAX submission -->
             <input type="hidden" id="status-change-id" name="id">
             <input type="hidden" id="status-change-value" name="status">
             <input type="hidden" name="formType" value="status">
             <input type="hidden" name="ajax" value="1">

            <div class="modal-buttons">
                <button class="approve-btn" onclick="confirmStatusChange('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <button class="reject-btn" onclick="confirmStatusChange('Reject')">
                    <i class="fas fa-times"></i> Reject
                </button>
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
    <!-- Ensure accounts.js is loaded AFTER the inline script or includes necessary functions -->
    <script src="/js/accounts.js"></script>
    <script>
        // --- Client-side Search ---
        $(document).ready(function() {
            function performSearch() {
                const searchTerm = $('#searchInput').val().toLowerCase().trim();
                let visibleCount = 0;
                let totalRows = 0; // Count actual data rows

                $('.accounts-table tbody tr').each(function() {
                    const row = $(this);
                    // Skip the 'no accounts found' template row
                    if (row.attr('id') === 'noAccountsFound') {
                        return; // continue to next iteration
                    }
                    // Check if it's the original 'no accounts' row
                    if (row.find('.no-accounts').length > 0) {
                        // Hide this row during search
                        row.hide();
                        return; // continue
                    }

                    totalRows++; // This is a data row
                    const rowText = row.text().toLowerCase();
                    if (rowText.includes(searchTerm)) {
                        row.show();
                        visibleCount++;
                    } else {
                        row.hide();
                    }
                });

                 // Show/hide the 'no accounts found' message row template
                 if (visibleCount === 0 && totalRows > 0 && searchTerm !== '') {
                     $('#noAccountsFound').show();
                 } else {
                     $('#noAccountsFound').hide();
                     // If search is cleared and the original 'no accounts' row exists, show it
                     if (searchTerm === '' && totalRows === 0 && $('.accounts-table tbody .no-accounts').length > 0) {
                          $('.accounts-table tbody .no-accounts').closest('tr').show();
                     }
                 }
            }

            $('#searchInput').on('input', performSearch);
            $('#searchBtn').on('click', performSearch); // Also trigger search on button click

             // Ensure search is performed on page load if search input has value (e.g. after back button)
             if ($('#searchInput').val()) {
                 performSearch();
             }
        });

        // --- Status Filter Function ---
        function filterByStatus() {
            const selectedStatus = document.getElementById('statusFilter').value;
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

            const queryString = Object.keys(params).map(key => key + '=' + encodeURIComponent(params[key])).join('&');
            window.location.search = queryString; // Reload page with new filter/sort
        }

         // --- Functions needed by modals/buttons (Ensure these are in accounts.js or here) ---
         let currentAccountId = null; // To store ID for status change

         function openAddAccountForm() {
             $('#addAccountForm')[0].reset();
             $('#addAccountError').text('').hide();
             $('#addAccountOverlay').css('display', 'flex');
         }
         function closeAddAccountForm() { $('#addAccountOverlay').hide(); }

         function openEditAccountForm(id, username, password, role) {
             $('#edit-id').val(id);
             $('#edit-username').val(username);
             $('#edit-password').val(''); // Clear password field for editing
             $('#edit-role').val(role);
             $('#editAccountError').text('').hide();
             $('#editAccountOverlay').css('display', 'flex');
         }
         function closeEditAccountForm() { $('#editAccountOverlay').hide(); }

         function openStatusModal(id, username) {
             currentAccountId = id; // Store the ID
             $('#statusMessage').text(`Change status for account: ${username}`);
             // Clear previous status selection if needed
             $('#status-change-id').val(id); // Set hidden ID field
             $('#statusModal').css('display', 'flex');
         }
         function closeStatusModal() {
             $('#statusModal').hide();
             currentAccountId = null; // Clear stored ID
         }

         // Confirms which status button was clicked, sets hidden value
         function confirmStatusChange(newStatus) {
             $('#status-change-value').val(newStatus);
             // Now call the function that will perform the AJAX submit
             submitStatusChange();
         }

         // Submits the status change via AJAX
         function submitStatusChange() {
             const accountId = $('#status-change-id').val();
             const newStatus = $('#status-change-value').val();

             if (!accountId || !newStatus) {
                 showToast('Error: Missing account ID or status.', 'error');
                 return;
             }

             $.ajax({
                 url: window.location.pathname, // Post back to the same page
                 type: 'POST',
                 data: {
                     ajax: 1,
                     formType: 'status',
                     id: accountId,
                     status: newStatus
                 },
                 dataType: 'json',
                 success: function(response) {
                     if (response.success) {
                         showToast('Status updated successfully!', 'success');
                         if (response.reload) {
                             setTimeout(() => { window.location.reload(); }, 1000);
                         }
                     } else {
                         showToast('Error: ' + (response.message || 'Failed to update status.'), 'error');
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error("Status change AJAX error:", status, error, xhr.responseText);
                     showToast('Error: Could not connect to server.', 'error');
                 },
                 complete: function() {
                     closeStatusModal(); // Close modal regardless of success/failure
                 }
             });
         }

         // --- Form Submissions via AJAX (Add/Edit) ---
         $(document).ready(function() {
             $('#addAccountForm').on('submit', function(e) {
                 e.preventDefault();
                 const form = $(this);
                 $.ajax({
                     url: window.location.pathname, // Post back to same page
                     type: 'POST',
                     data: form.serialize() + '&ajax=1', // Ensure ajax=1 is sent
                     dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast('Account added successfully!', 'success');
                             closeAddAccountForm();
                             if (response.reload) {
                                 setTimeout(() => { window.location.reload(); }, 1000);
                             }
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
                 const form = $(this);
                 $.ajax({
                     url: window.location.pathname, // Post back to same page
                     type: 'POST',
                     data: form.serialize() + '&ajax=1', // Ensure ajax=1 is sent
                     dataType: 'json',
                     success: function(response) {
                         if (response.success) {
                             showToast('Account updated successfully!', 'success');
                             closeEditAccountForm();
                             if (response.reload) {
                                 setTimeout(() => { window.location.reload(); }, 1000);
                             }
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
// --- Close Connection AT THE END ---
if ($conn instanceof mysqli) {
    $conn->close();
}
?>