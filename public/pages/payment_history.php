<?php
// Start session and include necessary files
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Payment History'); // Ensure the user has access to the Payment History page

// Fetch users data for display
$sql = "SELECT id, username, status FROM clients_accounts ORDER BY status DESC, username ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    <link rel="stylesheet" href="../css/payment_history.css">
</head>
<body>
    <div class="sidebar">
        <!-- Include your sidebar navigation here -->
        <?php include '../sidebar.php'; ?>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Payment History</h1>
            <nav>
                <a href="../dashboard.php">Dashboard</a> / Payment History
            </nav>
        </div>
        <div class="filters">
            <button id="show-active">Show Active Users</button>
            <button id="show-inactive">Show Inactive Users</button>
        </div>
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="user_records">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><button onclick="viewTransactionHistory(<?= $row['id'] ?>)">View Transaction History</button></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="no-users">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="transactionModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Transaction History</h2>
                <div class="tabs">
                    <!-- Year tabs will be dynamically generated here -->
                </div>
                <div class="tab-content">
                    <!-- Monthly transaction details will be dynamically generated here -->
                </div>
            </div>
        </div>
    </div>
    <script src="../js/payment_history.js"></script>
</body>
</html>