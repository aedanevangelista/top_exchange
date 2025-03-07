<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Accounts - Clients'); // Ensure the user has access to the Accounts Clients page

// Disable error reporting to avoid breaking JSON response
error_reporting(0);

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
    $business_proof = [];

    if (validateUnique($conn, $username, $email)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    $user_upload_dir = __DIR__ . '/../../uploads/' . $username . '/';
    if (!file_exists($user_upload_dir)) {
        mkdir($user_upload_dir, 0777, true);
    }

    if (isset($_FILES['business_proof'])) {
        if (count($_FILES['business_proof']['name']) > 3) {
            echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']);
            exit;
        }
        foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['business_proof']['error'][$key] == 0) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 50 * 1024 * 1024; // 50MB
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];

                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                    $business_proof_path = '/top_exchange/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $user_upload_dir . basename($_FILES['business_proof']['name'][$key]))) {
                        $business_proof[] = $business_proof_path;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type or size.']);
                    exit;
                }
            }
        }
    }

    $business_proof_json = json_encode($business_proof);

    $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssssssss", $username, $password, $email, $phone, $region, $city, $company_address, $business_proof_json);

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
    $business_proof = [];

    if (validateUnique($conn, $username, $email, $id)) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    $user_upload_dir = __DIR__ . '/../../uploads/' . $username . '/';
    if (!file_exists($user_upload_dir)) {
        mkdir($user_upload_dir, 0777, true);
    }

    // Collect new files
    if (isset($_FILES['business_proof'])) {
        if (count($_FILES['business_proof']['name']) > 3) {
            echo json_encode(['success' => false, 'message' => 'Maximum of 3 photos allowed.']);
            exit;
        }
        foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['business_proof']['error'][$key] == 0) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 50 * 1024 * 1024; // 50MB
                $file_type = $_FILES['business_proof']['type'][$key];
                $file_size = $_FILES['business_proof']['size'][$key];

                if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                    $business_proof_path = '/top_exchange/uploads/' . $username . '/' . basename($_FILES['business_proof']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $user_upload_dir . basename($_FILES['business_proof']['name'][$key]))) {
                        $business_proof[] = $business_proof_path;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type or size.']);
                    exit;
                }
            }
        }
    }

    // Delete old files that are not part of the new upload
    $existing_business_proof = json_decode($_POST['existing_business_proof'], true) ?? [];
    $existing_files = array_diff(scandir($user_upload_dir), array('.', '..'));
    foreach ($existing_files as $existing_file) {
        $existing_file_path = $user_upload_dir . $existing_file;
        if (!in_array('/top_exchange/uploads/' . $username . '/' . $existing_file, array_merge($existing_business_proof, $business_proof))) {
            if (file_exists($existing_file_path)) {
                unlink($existing_file_path);
            }
        }
    }

    $business_proof_json = json_encode($business_proof);

    $stmt = $conn->prepare("UPDATE clients_accounts SET username = ?, password = ?, email = ?, phone = ?, region = ?, city = ?, company_address = ?, business_proof = ? WHERE id = ?");
    $stmt->bind_param("ssssssssi", $username, $password, $email, $phone, $region, $city, $company_address, $business_proof_json, $id);

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

// Handle status filter
$status_filter = $_GET['status'] ?? '';

// Fetch accounts for display
$sql = "SELECT id, username, email, phone, region, city, company_address, business_proof, status, created_at FROM clients_accounts WHERE status != 'archived'";
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
}
$sql .= " ORDER BY 
    CASE 
        WHEN status = 'Active' THEN 1
        WHEN status = 'Pending' THEN 2
        WHEN status = 'Rejected' THEN 3
        WHEN status = 'Inactive' THEN 4
    END, created_at ASC";
$stmt = $conn->prepare($sql);
if (!empty($status_filter)) {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();

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
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="">All</option>
                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
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
                                <td class="photo-album">
                                    <?php
                                    $proofs = json_decode($row['business_proof'], true);
                                    if ($proofs) {
                                        foreach ($proofs as $proof) {
                                            echo '<img src="' . htmlspecialchars($proof) . '" alt="Business Proof" width="50" onclick="openModal(this)">';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="<?= 'status-' . strtolower($row['status'] ?? 'pending') ?>"><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                                <td class="action-buttons">
                                <?php
                                    $business_proof_json = htmlspecialchars(json_encode($row['business_proof']), ENT_QUOTES);
                                ?>
                                <button class="edit-btn"
                                    onclick='openEditAccountForm(
                                        <?= $row["id"] ?>,
                                        <?= json_encode($row["username"]) ?>,
                                        <?= json_encode($row["email"]) ?>,
                                        <?= json_encode($row["phone"]) ?>,
                                        <?= json_encode($row["region"]) ?>,
                                        <?= json_encode($row["city"]) ?>,
                                        <?= json_encode($row["company_address"]) ?>,
                                        <?= $business_proof_json ?>
                                    )'>
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
                <input type="text" id="username" name="username" autocomplete="username" required placeholder="e.g., johndoe">
                <label for="password">Password: <span class="required">*</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" required placeholder="e.g., ********">
                <label for="email">Email: <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="e.g., johndoe@example.com">
                <label for="phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
                <input type="text" id="phone" name="phone" placeholder="e.g., +1234567890">
                <label for="region">Region: <span class="required">*</span></label>
                <input type="text" id="region" name="region" required placeholder="e.g., North America">
                <label for="city">City: <span class="required">*</span></label>
                <input type="text" id="city" name="city" required placeholder="e.g., New York">
                <label for="company_address">Company Address: <span class="required">*</span></label>
                <textarea id="company_address" name="company_address" required placeholder="e.g., 123 Main St, New York, NY 10001"></textarea>
                <label for="business_proof">Business Proof: <span class="required">*</span></label>
                <input type="file" id="business_proof" name="business_proof[]" required accept="image/jpeg, image/png" multiple>
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
<!-- Overlay Form for Editing Account -->
<div id="editAccountOverlay" class="overlay" style="display: none;">
    <div class="overlay-content">
        <h2><i class="fas fa-edit"></i> Edit Account</h2>
        <div id="editAccountError" class="error-message"></div>
        <form id="editAccountForm" method="POST" class="account-form" enctype="multipart/form-data">
            <input type="hidden" name="formType" value="edit">
            <input type="hidden" id="edit-id" name="id">
            <input type="hidden" id="existing-business-proof" name="existing_business_proof">
            <label for="edit-username">Username: <span class="required">*</span></label>
            <input type="text" id="edit-username" name="username" autocomplete="username" required placeholder="e.g., johndoe">
            <label for="edit-password">Password: <span class="required">*</span></label>
            <input type="password" id="edit-password" name="password" autocomplete="new-password" required placeholder="e.g., ********">
            <label for="edit-email">Email: <span class="required">*</span></label>
            <input type="email" id="edit-email" name="email" required placeholder="e.g., johndoe@example.com">
            <label for="edit-phone">Phone/Telephone Number: <span class="optional">(optional)</span></label>
            <input type="text" id="edit-phone" name="phone" placeholder="e.g., +1234567890">
            <label for="edit-region">Region: <span class="required">*</span></label>
            <input type="text" id="edit-region" name="region" required placeholder="e.g., North America">
            <label for="edit-city">City: <span class="required">*</span></label>
            <input type="text" id="edit-city" name="city" required placeholder="e.g., New York">
            <label for="edit-company_address">Company Address: <span class="required">*</span></label>
            <textarea id="edit-company_address" name="company_address" required placeholder="e.g., 123 Main St, New York, NY 10001"></textarea>
            <div id="edit-business-proof-container"></div>
            <label for="edit-business_proof">Business Proof: <span class="optional">(optional)</span></label>
            <input type="file" id="edit-business_proof" name="business_proof[]" accept="image/jpeg, image/png" multiple>
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
                <button class="approve-btn" onclick="changeStatus('Active')">
                    <i class="fas fa-check"></i> Active
                </button>
                <button class="reject-btn" onclick="changeStatus('Rejected')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="pending-btn" onclick="changeStatus('Pending')">
                    <i class="fas fa-hourglass-half"></i> Pending
                </button>
                <button class="inactive-btn" onclick="changeStatus('Inactive')">
                    <i class="fas fa-ban"></i> Archive
                </button>
            </div>
            <div class="modal-buttons single-button">
                <button class="cancel-btn" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>


    <!-- The Modal -->
        <div id="myModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="img01">
            <div id="caption"></div>
        </div>

        <script>
        function openModal(imgElement) {
            var modal = document.getElementById("myModal");
            var modalImg = document.getElementById("img01");
            var captionText = document.getElementById("caption");
            modal.style.display = "block";
            modalImg.src = imgElement.src;
            captionText.innerHTML = imgElement.alt;
        }

        function closeModal() {
            var modal = document.getElementById("myModal");
            modal.style.display = "none";
        }
        </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/top_exchange/public/js/accounts_clients.js"></script>
</body>
</html>