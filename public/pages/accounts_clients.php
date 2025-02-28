<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole(['admin']); // Only admins can access

// Validate if username or email already exists
function validateUnique($conn, $username, $email, $id = null) {
    $query = "SELECT COUNT(*) as count FROM clients_accounts WHERE (username = ? OR email = ?)";
    if ($id) {
        $query .= " AND id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($id) {
        $stmt->bind_param("ssi", $username, $email, $id);
    } else {
        $stmt->bind_param("ss", $username, $email);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] > 0;
}

// Handle form submission (Add Account)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'add') {
    header('Content-Type: application/json');

    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $region = $_POST['region'];
    $city = $_POST['city'];
    $company_address = $_POST['company_address'];
    $business_proof = null;

    if (validateUnique($conn, $username, $email)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    if (isset($_FILES['business_proof']) && $_FILES['business_proof']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 50 * 1024 * 1024; // 50MB
        $file_type = $_FILES['business_proof']['type'];
        $file_size = $_FILES['business_proof']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $uploadDir = realpath(__DIR__ . '/../../../uploads') . '/';
            $business_proof = $upload_dir . basename($_FILES['business_proof']['name']);
            if (!move_uploaded_file($_FILES['business_proof']['tmp_name'], $business_proof)) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size.']);
            exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssssssss", $username, $password, $email, $phone, $region, $city, $company_address, $business_proof);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add account.']);
    }

    $stmt->close();
    exit;
}

// Handle form submission (Edit Account)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'edit') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $region = $_POST['region'];
    $city = $_POST['city'];
    $company_address = $_POST['company_address'];
    $business_proof = null;

    if (validateUnique($conn, $username, $email, $id)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    if (isset($_FILES['business_proof']) && $_FILES['business_proof']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 50 * 1024 * 1024; // 50MB
        $file_type = $_FILES['business_proof']['type'];
        $file_size = $_FILES['business_proof']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $upload_dir = '../../uploads/';
            $business_proof = $upload_dir . basename($_FILES['business_proof']['name']);
            if (!move_uploaded_file($_FILES['business_proof']['tmp_name'], $business_proof)) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type or size.']);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE clients_accounts SET username = ?, password = ?, email = ?, phone = ?, region = ?, city = ?, company_address = ?, business_proof = ? WHERE id = ?");
    $stmt->bind_param("ssssssssi", $username, $password, $email, $phone, $region, $city, $company_address, $business_proof, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update account.']);
    }

    $stmt->close();
    exit;
}

// Handle form submission (Change Status)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['formType'] == 'status') {
    header('Content-Type: application/json');

    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE clients_accounts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'reload' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change status.']);
    }

    $stmt->close();
    exit;
}

// Fetch accounts for display
$sql = "SELECT id, username, email, phone, region, city, company_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived' ORDER BY created_at ASC";
$result = $conn->query($sql);

function truncate($text, $max = 15) {
    return (strlen($text) > $max) ? substr($text, 0, $max) . '...' : $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Accounts</title>
    <link rel="stylesheet" href="/top_exchange/public/css/accounts.css">
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/top_exchange/public/css/accounts_clients.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Client Accounts</h1>
            <button onclick="openAddAccountForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Account
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Region</th>
                        <th>City</th>
                        <th>Company Address</th>
                        <th>Business Proof</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars(truncate($row['username'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(truncate($row['email'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(truncate($row['phone'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(truncate($row['region'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(truncate($row['city'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars(truncate($row['company_address'] ?? 'N/A')) ?></td>
                                <td><img src="<?= htmlspecialchars($row['business_proof']) ?>" alt="Business Proof" width="50"></td>
                                <td class="<?= 'status-' . strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="openEditAccountForm(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['phone']) ?>', '<?= htmlspecialchars($row['region']) ?>', '<?= htmlspecialchars($row['city']) ?>', '<?= htmlspecialchars($row['company_address']) ?>', '<?= htmlspecialchars($row['business_proof']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="status-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['email']) ?>')">
                                        <i class="fas fa-exchange-alt"></i> Status
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-accounts">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Overlay Form for Adding New Account -->
    <div id="addAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-user-plus"></i> Add New Account</h2>
            <div id="addAccountError" class="error-message"></div>
            <form id="addAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="add">
                <label for="username">Username: <span class="required">*</span></label>
                <input type="text" id="username" name="username" autocomplete="username" required>
                <label for="password">Password: <span class="required">*</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
                <label for="email">Email: <span class="required">*</span></label>
                <input type="email" id="email" name="email" required>
                <label for="phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
                <input type="text" id="phone" name="phone">
                <label for="region">Region: <span class="required">*</span></label>
                <input type="text" id="region" name="region" required>
                <label for="city">City: <span class="required">*</span></label>
                <input type="text" id="city" name="city" required>
                <label for="company_address">Company Address: <span class="required">*</span></label>
                <textarea id="company_address" name="company_address" required></textarea>
                <label for="business_proof">Business Proof: <span class="required">*</span></label>
                <input type="file" id="business_proof" name="business_proof" required accept="image/jpeg, image/png">
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeAddAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Form for Editing Account -->
    <div id="editAccountOverlay" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-edit"></i> Edit Account</h2>
            <div id="editAccountError" class="error-message"></div>
            <form id="editAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
                <input type="hidden" name="formType" value="edit">
                <input type="hidden" id="edit-id" name="id">
                <label for="edit-username">Username: <span class="required">*</span></label>
                <input type="text" id="edit-username" name="username" autocomplete="username" required>
                <label for="edit-password">Password: <span class="required">*</span></label>
                <input type="password" id="edit-password" name="password" autocomplete="new-password" required>
                <label for="edit-email">Email: <span class="required">*</span></label>
                <input type="email" id="edit-email" name="email" required>
                <label for="edit-phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
                <input type="text" id="edit-phone" name="phone">
                <label for="edit-region">Region: <span class="required">*</span></label>
                <input type="text" id="edit-region" name="region" required>
                <label for="edit-city">City: <span class="required">*</span></label>
                <input type="text" id="edit-city" name="city" required>
                <label for="edit-company_address">Company Address: <span class="required">*</span></label>
                <textarea id="edit-company_address" name="company_address" required></textarea>
                <label for="edit-business_proof">Business Proof: <span class="required">*</span></label>
                <input type="file" id="edit-business_proof" name="business_proof" required accept="image/jpeg, image/png">
                <input type="hidden" id="edit-business_proof-current" name="business_proof_current">
                <div class="form-buttons">
                    <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="cancel-btn" onclick="closeEditAccountForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Modal for Status Change -->
    <div id="statusModal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2>Change Status</h2>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="approve-btn" onclick="changeStatus('Approved')">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button class="reject-btn" onclick="changeStatus('Rejected')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="pending-btn" onclick="changeStatus('Pending')">
                    <i class="fas fa-hourglass-half"></i> Pending
                </button>
                <button class="inactive-btn" onclick="changeStatus('Inactive')">
                    <i class="fas fa-ban"></i> Inactive
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
    <script src="/top_exchange/public/js/accounts_clients.js"></script>
</body>
</html>