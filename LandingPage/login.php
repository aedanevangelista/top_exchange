<?php
// login.php

session_start();
require 'db_connection.php'; // This file should now have the correct credentials

$error_message = '';
$success_message = '';
$form_errors = [];

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate login inputs
    if (empty($email)) {
        $form_errors['login_email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors['login_email'] = "Invalid email format";
    }

    if (empty($password)) {
        $form_errors['login_password'] = "Password is required";
    }

    if (empty($form_errors)) {
        // Since we're using mysqli in our updated db_connection.php, we need to adapt the queries
        // Check if the user exists in the accounts table (for admins, managers, etc.)
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Login successful for admin/manager accounts
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'Admin') {
                header('Location: dashboard.php');
            } else {
                header('Location: user_dashboard.php');
            }
            exit();
        } else {
            // Check if the user exists in the clients_accounts table (for clients)
            $stmt = $conn->prepare("SELECT * FROM clients_accounts WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $client = $result->fetch_assoc();

            if ($client && password_verify($password, $client['password'])) {
                // Check the account status
                $status = $client['status'];
                if ($status === 'Active') {
                    // Login successful for active clients
                    $_SESSION['user_id'] = $client['id'];
                    $_SESSION['username'] = $client['username'];
                    $_SESSION['role'] = 'Client';
                    header('Location: ordering.php');
                    exit();
                } elseif ($status === 'Pending') {
                    $form_errors['login'] = "Your account is pending approval. Please wait for admin verification.";
                } elseif ($status === 'Rejected') {
                    $form_errors['login'] = "Your account has been rejected. Please contact support.";
                } elseif ($status === 'Archived') {
                    $form_errors['login'] = "Your account has been archived. Please contact support.";
                } else {
                    $form_errors['login'] = "Invalid account status. Please contact support.";
                }
            } else {
                $form_errors['login'] = "Invalid email or password.";
            }
        }
    }
}

// Handle Sign Up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $region = trim($_POST['region']);
    $city = trim($_POST['city']);
    $company = trim($_POST['company'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');

    // Validate username
    if (empty($username)) {
        $form_errors['username'] = "Username is required";
    } elseif (strlen($username) < 4) {
        $form_errors['username'] = "Username must be at least 4 characters";
    }

    // Validate email
    if (empty($email)) {
        $form_errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors['email'] = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM clients_accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $form_errors['email'] = "Email already registered";
        }
    }

    // Validate password
    if (empty($password)) {
        $form_errors['password'] = "Password is required";
    } elseif (strlen($password) < 8) {
        $form_errors['password'] = "Password must be at least 8 characters";
    }

    // Validate phone
    if (empty($phone)) {
        $form_errors['phone'] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $form_errors['phone'] = "Invalid phone number format";
    }

    // Validate required fields
    $required_fields = [
        'region' => 'Region',
        'city' => 'City'
    ];

    foreach ($required_fields as $field => $name) {
        if (empty($_POST[$field])) {
            $form_errors[$field] = "$name is required";
        }
    }

    if (empty($form_errors)) {
        // Handle file uploads for business proof (if provided)
        $business_proof = [];
        $business_proof_json = '[]';
        
        if (!empty($_FILES['business_proof']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $uploadDir = __DIR__ . '/../../uploads/' . $username . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            foreach ($_FILES['business_proof']['tmp_name'] as $key => $tmp_name) {
                $file_type = mime_content_type($tmp_name);
                if (!in_array($file_type, $allowed_types)) {
                    $form_errors['business_proof'] = "Only JPG, PNG, and PDF files are allowed";
                    break;
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['business_proof']['name'][$key]);
                $uploadFilePath = $uploadDir . $fileName;
                if (move_uploaded_file($tmp_name, $uploadFilePath)) {
                    $business_proof[] = '/u701062148_top_exchange/uploads/' . $username . '/' . $fileName;
                }
            }
            
            $business_proof_json = json_encode($business_proof);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $status = 'Pending';

        // Insert new client using mysqli
        $stmt = $conn->prepare("INSERT INTO clients_accounts (username, password, email, phone, region, city, company, company_address, business_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $username, $hashed_password, $email, $phone, $region, $city, $company, $company_address, $business_proof_json, $status);

        if ($stmt->execute()) {
            $success_message = "Sign up successful! Your account is pending approval.";
            // Clear form data after successful submission
            $_POST = [];
        } else {
            $form_errors['signup'] = "Sign up failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-content {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .form-content::-webkit-scrollbar {
            width: 8px;
        }
        .form-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .form-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .form-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .error-message {
            color: #ff3860;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .success-message {
            color: #4CAF50;
            font-size: 14px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
        }
        .has-error input, .has-error input:focus {
            border-color: #ff3860 !important;
        }
        .input-container {
            margin-bottom: 15px;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
        }
        .password-toggle:hover {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-up-container">
            <form action="login.php" method="POST" enctype="multipart/form-data">
                <h1>Create Account</h1>
                <span>or use your email for registration asdasd</span>
                
                <?php if (!empty($success_message)) : ?>
                    <div class="success-message"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($form_errors['signup'])) : ?>
                    <div class="error-message"><?php echo $form_errors['signup']; ?></div>
                <?php endif; ?>
                
                <div class="form-content">
                    <div class="input-container <?php echo (!empty($form_errors['username']) ? 'has-error' : ''); ?>">
                        <input type="text" name="username" placeholder="Username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
                        <?php if (!empty($form_errors['username'])) : ?>
                            <span class="error-message"><?php echo $form_errors['username']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container <?php echo (!empty($form_errors['email']) ? 'has-error' : ''); ?>">
                        <input type="email" name="email" placeholder="Email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                        <?php if (!empty($form_errors['email'])) : ?>
                            <span class="error-message"><?php echo $form_errors['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container <?php echo (!empty($form_errors['password']) ? 'has-error' : ''); ?>">
                        <div class="password-container">
                            <input type="password" name="password" id="signup-password" placeholder="Password" required />
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('signup-password', this)"></i>
                        </div>
                        <?php if (!empty($form_errors['password'])) : ?>
                            <span class="error-message"><?php echo $form_errors['password']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container <?php echo (!empty($form_errors['phone']) ? 'has-error' : ''); ?>">
                        <input type="text" name="phone" placeholder="Phone" required 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" />
                        <?php if (!empty($form_errors['phone'])) : ?>
                            <span class="error-message"><?php echo $form_errors['phone']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container <?php echo (!empty($form_errors['region']) ? 'has-error' : ''); ?>">
                        <input type="text" name="region" placeholder="Region" required 
                               value="<?php echo isset($_POST['region']) ? htmlspecialchars($_POST['region']) : ''; ?>" />
                        <?php if (!empty($form_errors['region'])) : ?>
                            <span class="error-message"><?php echo $form_errors['region']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container <?php echo (!empty($form_errors['city']) ? 'has-error' : ''); ?>">
                        <input type="text" name="city" placeholder="City" required 
                               value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" />
                        <?php if (!empty($form_errors['city'])) : ?>
                            <span class="error-message"><?php echo $form_errors['city']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-container">
                        <input type="text" name="company" placeholder="Company (Optional)" 
                               value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>" />
                    </div>
                    
                    <div class="input-container">
                        <input type="text" name="company_address" placeholder="Company Address (Optional)" 
                               value="<?php echo isset($_POST['company_address']) ? htmlspecialchars($_POST['company_address']) : ''; ?>" />
                    </div>
                    
                    <div class="input-container">
                        <input type="file" name="business_proof[]" multiple />
                        <span class="error-message">Business proof (Optional - JPG, PNG, or PDF)</span>
                    </div>
                </div>
                <button type="submit" name="signup">Sign Up</button>
            </form>
        </div>
        
        <div class="form-container sign-in-container">
            <form action="login.php" method="POST">
                <h1>Sign in</h1>
                <span>or use your account</span>
                
                <?php if (!empty($form_errors['login'])) : ?>
                    <div class="error-message" style="text-align: center; margin-bottom: 10px;"><?php echo $form_errors['login']; ?></div>
                <?php endif; ?>
                
                <div class="input-container <?php echo (!empty($form_errors['login_email']) ? 'has-error' : ''); ?>">
                    <input type="email" name="email" placeholder="Email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                    <?php if (!empty($form_errors['login_email'])) : ?>
                        <span class="error-message"><?php echo $form_errors['login_email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="input-container <?php echo (!empty($form_errors['login_password']) ? 'has-error' : ''); ?>">
                    <div class="password-container">
                        <input type="password" name="password" id="login-password" placeholder="Password" required />
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('login-password', this)"></i>
                    </div>
                    <?php if (!empty($form_errors['login_password'])) : ?>
                        <span class="error-message"><?php echo $form_errors['login_password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <a href="#">Forgot your password?</a>
                <button type="submit" name="login">Sign In</button>
            </form>
        </div>
        
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us please login with your personal info</p>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Hello, Friend!</h1>
                    <p>Enter your personal details and start the journey with us</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
    </footer>

    <script>
        function togglePassword(inputId, icon) {
            const passwordInput = document.getElementById(inputId);
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('container');
            const signUpButton = document.getElementById('signUp');
            const signInButton = document.getElementById('signIn');
            
            signUpButton.addEventListener('click', function() {
                container.classList.add('right-panel-active');
            });
            
            signInButton.addEventListener('click', function() {
                container.classList.remove('right-panel-active');
            });
        });
    </script>
</body>
</html>