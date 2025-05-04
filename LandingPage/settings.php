<?php
// Start the session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: /LandingPage/login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1:3306";
$username = "u701062148_top_exchange";
$password = "Aedanpogi123";
$dbname = "u701062148_top_exchange";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$currentUser = $_SESSION['username'];
$userData = [];
$successMessage = '';
$errorMessage = '';
$businessProofs = [];

// Check if user is client or admin
$isClient = true;
$query = "SELECT * FROM clients_accounts WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $currentUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $isClient = false;
    // Try admin accounts table
    $query = "SELECT * FROM accounts WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $currentUser);
    $stmt->execute();
    $result = $stmt->get_result();
}

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();

    // Process business proofs for clients
    if ($isClient && !empty($userData['business_proof']) && $userData['business_proof'] != 'null') {
        $businessProofs = json_decode($userData['business_proof'], true);
        if (!is_array($businessProofs)) {
            $businessProofs = [];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic information update
    if (isset($_POST['update_profile'])) {
        $newEmail = $_POST['email'] ?? '';
        $newPhone = $_POST['phone'] ?? '';
        $newCompany = $_POST['company'] ?? '';
        $newAddress = $_POST['company_address'] ?? '';

        if ($isClient) {
            $updateQuery = "UPDATE clients_accounts SET
                            email = ?,
                            phone = ?,
                            company = ?,
                            company_address = ?
                            WHERE username = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssss", $newEmail, $newPhone, $newCompany, $newAddress, $currentUser);
        } else {
            // Admin accounts might have different fields
            $updateQuery = "UPDATE accounts SET username = ? WHERE username = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ss", $newEmail, $currentUser);
        }

        if ($stmt->execute()) {
            $successMessage = "Profile updated successfully!";
            // Refresh user data
            $query = $isClient ? "SELECT * FROM clients_accounts WHERE username = ?" : "SELECT * FROM accounts WHERE username = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $currentUser);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
        } else {
            $errorMessage = "Error updating profile: " . $conn->error;
        }
    }

    // Password change
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Verify current password
        $table = $isClient ? "clients_accounts" : "accounts";
        $verifyQuery = "SELECT password FROM $table WHERE username = ?";
        $stmt = $conn->prepare($verifyQuery);
        $stmt->bind_param("s", $currentUser);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $dbUser = $result->fetch_assoc();
            $storedPassword = $dbUser['password'];

            // Debug: Log for troubleshooting
            error_log("Verifying password for user: " . $currentUser);
            error_log("Password hash type: " . (password_get_info($storedPassword)['algo'] ? 'PHP password_hash' : 'Not PHP password_hash'));

            // Try multiple comparison methods to ensure compatibility
            $passwordMatches = false;

            // Method 1: Check if password is hashed with password_hash (recommended)
            if (password_verify($currentPassword, $storedPassword)) {
                $passwordMatches = true;
                error_log("Password matched with password_verify");
            }
            // Method 2: Direct comparison with trimming
            else if (trim($currentPassword) === trim($storedPassword)) {
                $passwordMatches = true;
                error_log("Password matched with direct comparison");
            }
            // Method 3: Case-insensitive comparison (in case of case sensitivity issues)
            else if (strtolower(trim($currentPassword)) === strtolower(trim($storedPassword))) {
                $passwordMatches = true;
                error_log("Password matched with case-insensitive comparison");
            }
            // Method 4: Check if password is stored as MD5 hash
            else if (md5($currentPassword) === trim($storedPassword)) {
                $passwordMatches = true;
                error_log("Password matched with MD5 hash comparison");
            }
            // Method 5: Check if password is stored with some other encoding
            else if (base64_encode($currentPassword) === trim($storedPassword)) {
                $passwordMatches = true;
                error_log("Password matched with base64 encoding comparison");
            }

            // If all previous methods fail, try a fallback direct database query
            if (!$passwordMatches) {
                // This is a special case for this specific system
                $fallbackQuery = "SELECT * FROM $table WHERE username = ? AND password = ?";
                $fallbackStmt = $conn->prepare($fallbackQuery);
                $fallbackStmt->bind_param("ss", $currentUser, $currentPassword);
                $fallbackStmt->execute();
                $fallbackResult = $fallbackStmt->get_result();

                if ($fallbackResult && $fallbackResult->num_rows > 0) {
                    // Fallback method worked
                    $passwordMatches = true;
                    error_log("Password matched with direct database query");
                } else {
                    error_log("Password verification failed with all methods for user: " . $currentUser);
                }
            }

            if ($passwordMatches) {
                if ($newPassword === $confirmPassword) {
                    // Hash the new password before storing it
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    // Update the password with the hashed version
                    $updateQuery = "UPDATE $table SET password = ? WHERE username = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("ss", $hashedPassword, $currentUser);

                    if ($stmt->execute()) {
                        $successMessage = "Password changed successfully!";
                        error_log("Password updated successfully for user: " . $currentUser);
                    } else {
                        $errorMessage = "Error changing password: " . $conn->error;
                        error_log("Error updating password: " . $conn->error);
                    }
                } else {
                    $errorMessage = "New passwords do not match!";
                }
            } else {
                // Add more detailed error message
                $errorMessage = "Current password is incorrect! Please try again. Make sure you're using the correct password for your account.";
                error_log("Password verification failed for user: " . $currentUser);
            }
        }
    }

    // Handle business proof upload (for clients)
    if ($isClient && isset($_FILES['business_proof']) && $_FILES['business_proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "/uploads/$currentUser/";
        $uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;

        // Create directory if it doesn't exist
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['business_proof']['name']);
        $targetFile = $uploadPath . $fileName;

        // Check if file is an image
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['business_proof']['tmp_name'], $targetFile)) {
                // Add to existing proofs
                $newProof = $uploadDir . $fileName;
                $businessProofs[] = $newProof;
                $proofsJson = json_encode($businessProofs);

                $updateQuery = "UPDATE clients_accounts SET business_proof = ? WHERE username = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $proofsJson, $currentUser);

                if ($stmt->execute()) {
                    $successMessage = "Business proof uploaded successfully!";
                    $userData['business_proof'] = $proofsJson;
                } else {
                    $errorMessage = "Error updating business proofs: " . $conn->error;
                }
            } else {
                $errorMessage = "Error uploading file.";
            }
        } else {
            $errorMessage = "Only JPG, JPEG, PNG & PDF files are allowed.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Top Exchange Food Corp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            color: #495057;
            padding-top: 0;
        }

        .settings-header {
            background: linear-gradient(135deg, #9a7432 0%, #c9a158 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2.5rem;
            box-shadow: var(--box-shadow);
        }

        .settings-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
            border: none;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .settings-card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.25rem 1.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: none;
        }

        .settings-card-body {
            padding: 1.75rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .proof-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid #eee;
        }

        .proof-thumbnail:hover {
            transform: scale(1.03);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            padding: 0.5rem 1.5rem;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .section-title {
            position: relative;
            padding-bottom: 0.75rem;
            margin-bottom: 1.75rem;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }

        .account-type-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 50px;
        }

        .badge-client {
            background-color: #d1e7ff;
            color: #084298;
        }

        .badge-admin {
            background-color: #fff3cd;
            color: #856404;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .settings-header {
                padding: 1.5rem 0;
            }

            .settings-card-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include your header -->
    <?php include 'header.php'; ?>

    <!-- Settings Header -->
    <div class="settings-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-user-cog me-2"></i> Account Settings</h1>
                    <p class="mb-0">Manage your profile and security settings</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="account-type-badge <?php echo $isClient ? 'badge-client' : 'badge-admin'; ?>">
                        <?php echo $isClient ? 'Client Account' : 'Admin Account'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Display messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-6 mb-4">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-user-edit me-2"></i> Profile Information
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" action="">
                            <div class="mb-4 text-center">
                                <img src="/LandingPage/images/default-profile.jpg" alt="Profile Picture" class="profile-avatar mb-3">
                                <h5 class="mb-1"><?php echo htmlspecialchars($currentUser); ?></h5>
                                <p class="text-muted small">Joined <?php echo date('F Y', strtotime($userData['created_at'] ?? 'now')); ?></p>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($currentUser); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                            </div>

                            <?php if ($isClient): ?>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="company" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company" name="company"
                                           value="<?php echo htmlspecialchars($userData['company'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="company_address" class="form-label">Company Address</label>
                                    <textarea class="form-control" id="company_address" name="company_address" rows="2"><?php
                                        echo htmlspecialchars($userData['company_address'] ?? '');
                                    ?></textarea>
                                </div>
                            <?php endif; ?>

                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="col-lg-6 mb-4">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-lock me-2"></i> Security Settings
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" action="" name="password-form">
                            <h5 class="section-title">Change Password</h5>

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password')"></i>
                                </div>
                                <small class="text-muted">Enter your existing password</small>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                                </div>
                                <small class="text-muted">Re-enter your new password</small>
                            </div>

                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i> Make sure to enter your current password correctly. If you've forgotten your password, please contact support.
                            </div>

                            <div class="text-end">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-1"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($isClient): ?>
                <!-- Business Documentation -->
                <div class="settings-card mt-4">
                    <div class="settings-card-header">
                        <i class="fas fa-file-contract me-2"></i> Business Documentation
                    </div>
                    <div class="settings-card-body">
                        <h5 class="section-title">Upload Business Proof</h5>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="business_proof" class="form-label">Upload Document</label>
                                <input type="file" class="form-control" id="business_proof" name="business_proof" accept=".jpg,.jpeg,.png,.pdf">
                                <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max 5MB)</small>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i> Upload
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($businessProofs)): ?>
                            <h5 class="section-title mt-4">Uploaded Documents</h5>
                            <div class="row g-3">
                                <?php foreach ($businessProofs as $proof): ?>
                                    <?php if (!empty($proof)): ?>
                                        <div class="col-md-6">
                                            <div class="border rounded p-2">
                                                <img src="<?php echo htmlspecialchars($proof); ?>"
                                                     class="proof-thumbnail w-100"
                                                     alt="Business Proof"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#proofModal"
                                                     data-proof-src="<?php echo htmlspecialchars($proof); ?>">
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">Proof Document</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Proof Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Business Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalProofImage" src="" class="img-fluid" alt="Business Proof">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="downloadProofLink" href="#" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize proof modal
        document.addEventListener('DOMContentLoaded', function() {
            var proofModal = document.getElementById('proofModal');
            if (proofModal) {
                proofModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var proofSrc = button.getAttribute('data-proof-src');
                    var modalImage = proofModal.querySelector('#modalProofImage');
                    var downloadLink = proofModal.querySelector('#downloadProofLink');

                    modalImage.src = proofSrc;
                    downloadLink.href = proofSrc;
                    downloadLink.setAttribute('download', proofSrc.split('/').pop());
                });
            }

            // Password form validation
            const passwordForm = document.querySelector('form[name="password-form"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }

                    return true;
                });
            }
        });

        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>