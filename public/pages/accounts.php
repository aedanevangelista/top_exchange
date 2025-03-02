<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts'); // Ensure the user has access to the Accounts page

// Handle form submission to add or update account
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_id = $_POST['account_id'] ?? null;
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id'];
    $status = 'active';

    if ($account_id) {
        // Update existing account
        $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role_id = ?, status = ? WHERE account_id = ?");
        $stmt->bind_param("ssisi", $username, $password, $role_id, $status, $account_id);
    } else {
        // Insert new account
        $stmt = $conn->prepare("INSERT INTO accounts (username, password, role_id, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $username, $password, $role_id, $status);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: accounts.php");
    exit();
}

// Handle archiving of account
if (isset($_GET['archive'])) {
    $account_id = $_GET['archive'];
    $stmt = $conn->prepare("UPDATE accounts SET status = 'inactive' WHERE account_id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $stmt->close();
    header("Location: accounts.php");
    exit();
} elseif (isset($_GET['activate'])) {
    $account_id = $_GET['activate'];
    $stmt = $conn->prepare("UPDATE accounts SET status = 'active' WHERE account_id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $stmt->close();
    header("Location: accounts.php");
    exit();
}

// Fetch accounts records for display
$sql = "SELECT a.account_id, a.username, a.status, r.role_name, r.role_id 
        FROM accounts a
        JOIN roles r ON a.role_id = r.role_id
        ORDER BY a.username";
$result = $conn->query($sql);

// Fetch roles for the form
$roles = $conn->query("SELECT * FROM roles ORDER BY role_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Management</title>
    <link rel="stylesheet" href="../css/accounts.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Accounts Management</h1>
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['username']) . "</td>
                                    <td>" . htmlspecialchars($row['role_name']) . "</td>
                                    <td class='" . ($row['status'] == 'active' ? 'status-active' : 'status-inactive') . "'>" . htmlspecialchars($row['status']) . "</td>
                                    <td class='action-buttons'>";
                            if ($row['status'] == 'active') {
                                echo "<button class='edit-btn' data-account-id='" . htmlspecialchars($row['account_id']) . "' data-username='" . htmlspecialchars($row['username']) . "' data-role-id='" . htmlspecialchars($row['role_id']) . "'>
                                        <i class='fas fa-edit'></i> Edit
                                      </button>
                                      <a href='accounts.php?archive=" . htmlspecialchars($row['account_id']) . "' class='archive-btn'>
                                        <i class='fas fa-archive'></i> Archive
                                      </a>";
                            } else {
                                echo "<a href='accounts.php?activate=" . htmlspecialchars($row['account_id']) . "' class='activate-btn'>
                                        <i class='fas fa-check-circle'></i> Activate
                                      </a>";
                            }
                            echo "</td></tr>";
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="no-accounts">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overlay Form for Adding Account -->
    <div id="accountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add Account</h2>
            <form id="accountForm" method="POST" class="account-form">
                <input type="hidden" id="accountId" name="account_id">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                <br/>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <br/>
                <label for="role_id">Role:</label>
                <select id="role_id" name="role_id" required>
                    <?php while ($role = $roles->fetch_assoc()): ?>
                        <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
                <div id="editAccountError" class="error-message"></div>
                <div id="addAccountError" class="error-message"></div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        function openAddAccountForm() {
            document.getElementById('accountForm').reset();
            document.getElementById('accountId').value = '';
            document.querySelector('#accountOverlay h2').innerHTML = '<i class="fas fa-user-plus"></i> Add Account';
            document.getElementById('accountOverlay').style.display = 'flex';
        }

        function openEditAccountForm(account_id, username, role_id) {
            document.getElementById('accountForm').reset();
            document.getElementById('accountId').value = account_id;
            document.getElementById('username').value = username;
            document.getElementById('role_id').value = role_id;
            document.querySelector('#accountOverlay h2').innerHTML = '<i class="fas fa-user-edit"></i> Edit Account';
            document.getElementById('accountOverlay').style.display = 'flex';
        }

        function closeAccountForm() {
            document.getElementById('accountOverlay').style.display = 'none';
        }

        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".edit-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var accountId = btn.getAttribute("data-account-id");
                    var username = btn.getAttribute("data-username");
                    var roleId = btn.getAttribute("data-role-id");
                    openEditAccountForm(accountId, username, roleId);
                });
            });
        });
    </script>
</body>
</html>