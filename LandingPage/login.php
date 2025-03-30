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
        // Check if the user exists in the accounts table (for admins, managers, etc.)
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Login successful for admin/manager accounts - but we'll use client variables
            $_SESSION['user_id'] = $client['id'];
            $_SESSION['username'] = $client['username']; // This is the key change
            $_SESSION['role'] = 'Client';

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
                    // Login successful for active clients - use client specific variables
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
        /* Primary brand colors and variables */
:root {
  --primary-color: #9a7432;
  --primary-hover: #b08a3e;
  --secondary-color: #333;
  --light-color: #f8f9fa;
  --dark-color: #222;
  --accent-color: #dc3545;
  --section-padding: 100px 0;
  --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s ease;
  --border-radius: 10px;
}

/* Global styles refinement */
body {
  font-family: 'Roboto', sans-serif;
  color: #444;
  line-height: 1.7;
  overflow-x: hidden;
}

p {
  margin-bottom: 1rem;
  font-size: 1rem;
  line-height: 1.7;
}

/* Header and Navigation improvements */
.header_section {
  background-image: url('../images/banner-bg.jpg');
  background-size: cover;
  background-position: center;
}

.navbar {
  padding: 15px 0;
  transition: all 0.3s ease;
}

.navbar-brand img {
  max-height: 60px;
  transition: all 0.3s ease;
}

.navbar-light .navbar-nav .nav-link {
  color: #333;
  font-weight: 500;
  padding: 10px 15px;
  transition: all 0.3s ease;
  position: relative;
}

.navbar-light .navbar-nav .nav-link:after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  width: 0;
  height: 2px;
  background-color: var(--primary-color);
  transition: all 0.3s ease;
  transform: translateX(-50%);
}

.navbar-light .navbar-nav .nav-link:hover:after,
.navbar-light .navbar-nav .active > .nav-link:after {
  width: 70%;
}

.navbar-light .navbar-nav .active > .nav-link,
.navbar-light .navbar-nav .nav-link:hover {
  color: var(--primary-color);
}

.login_bt a {
  display: inline-flex;
  align-items: center;
  color: #333;
  font-weight: 500;
  padding: 8px 15px;
  transition: all 0.3s ease;
  margin-left: 10px;
  border-radius: 5px;
}

.login_bt a i {
  margin-left: 8px;
}

.login_bt a:hover {
  color: var(--primary-color);
  background-color: rgba(154, 116, 50, 0.1);
  text-decoration: none;
}

/* Improved Banner Section */
.banner_section {
  padding: 120px 0;
  position: relative;
}

.banner_section .carousel-item {
  min-height: 400px;
}

.banner_taital {
  font-size: 3.5rem;
  font-weight: 700;
  color: #222;
  margin-bottom: 25px;
  width: 100%;
  line-height: 1.2;
}

.banner_text {
  font-size: 1.1rem;
  color: #444;
  margin-bottom: 30px;
  width: 100%;
}

.started_text {
  width: auto;
  margin-top: 20px;
}

.started_text a {
  display: inline-block;
  padding: 12px 30px;
  background-color: var(--primary-color);
  color: white;
  border-radius: 50px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(154, 116, 50, 0.3);
  position: relative;
  overflow: hidden;
}

.started_text a:hover {
  background-color: var(--primary-hover);
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(154, 116, 50, 0.4);
  color: white;
}

.started_text a::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: 0.5s;
}

.started_text a:hover::after {
  left: 100%;
}

.banner_img {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.banner_img img {
  max-width: 90%;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0% { transform: translateY(0px); }
  50% { transform: translateY(-15px); }
  100% { transform: translateY(0px); }
}

.carousel-indicators {
  bottom: -50px;
}

.carousel-indicators li {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin: 0 5px;
  background-color: #ddd;
  cursor: pointer;
}

.carousel-indicators .active {
  background-color: var(--primary-color);
  width: 12px;
  height: 12px;
}

.carousel-control-prev,
.carousel-control-next {
  width: 45px;
  height: 45px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  top: 50%;
  transform: translateY(-50%);
  opacity: 1;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
  width: 20px;
  height: 20px;
}

.carousel-control-prev:hover,
.carousel-control-next:hover {
  background: var(--primary-color);
}

/* Features Section Enhancements */
.feature-box {
  padding: 40px 30px;
  background: white;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  transition: var(--transition);
  height: 100%;
  border-left: 3px solid transparent;
}

.feature-box:hover {
  transform: translateY(-10px);
  box-shadow: 0 15px 30px rgba(0,0,0,0.15);
  border-left: 3px solid var(--primary-color);
}

.feature-icon {
  font-size: 3rem;
  color: var(--primary-color);
  margin-bottom: 25px;
  transition: var(--transition);
}

.feature-box:hover .feature-icon {
  transform: scale(1.1);
}

.feature-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 20px;
  color: var(--secondary-color);
}

.feature-text {
  color: #666;
  font-size: 1rem;
  line-height: 1.6;
}

/* About Section Improvements */
.about_section {
  padding: var(--section-padding);
  background-color: var(--light-color);
}

.about_taital {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--secondary-color);
  margin-bottom: 25px;
  position: relative;
  padding-top: 0;
  width: 100%;
}

.about_taital::after {
  content: '';
  position: absolute;
  bottom: -15px;
  left: 0;
  width: 80px;
  height: 4px;
  background-color: var(--primary-color);
  border-radius: 2px;
}

.about_text {
  margin-bottom: 25px;
}

.about_img {
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--box-shadow);
}

.read_bt_1 a {
  display: inline-block;
  padding: 12px 30px;
  background-color: var(--primary-color);
  color: white;
  border-radius: 50px;
  font-weight: 500;
  transition: var(--transition);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.read_bt_1 a:hover {
  background-color: var(--primary-hover);
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(154, 116, 50, 0.2);
}

/* Products Section Improvements */
.cream_section {
  padding: var(--section-padding);
  background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
}

.section-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--secondary-color);
  margin-bottom: 15px;
  position: relative;
  display: inline-block;
}

.section-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 0;
  width: 50px;
  height: 3px;
  background-color: var(--primary-color);
}

.section-subtitle {
  font-size: 1.2rem;
  color: #777;
  margin-bottom: 50px;
}

.product-card {
  background: white;
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--box-shadow);
  transition: var(--transition);
  height: 100%;
  margin-bottom: 30px;
}

.product-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

.product-img {
  height: 220px;
  overflow: hidden;
  position: relative;
}

.product-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.product-card:hover .product-img img {
  transform: scale(1.1);
}

.product-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  background-color: var(--accent-color);
  color: white;
  padding: 5px 10px;
  border-radius: 3px;
  font-size: 0.8rem;
  font-weight: 600;
}

.product-body {
  padding: 25px;
}

.product-price {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-color);
  margin-bottom: 10px;
}

.product-title {
  font-size: 1.3rem;
  font-weight: 600;
  margin-bottom: 10px;
  color: var(--secondary-color);
}

.product-description {
  color: #666;
  margin-bottom: 15px;
  font-size: 0.9rem;
}

.product-rating {
  color: #ffc107;
  margin-bottom: 15px;
}

.product-btn {
  background-color: var(--primary-color);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 30px;
  font-weight: 500;
  transition: var(--transition);
  display: inline-block;
  width: 100%;
  text-align: center;
}

.product-btn:hover {
  background-color: var(--primary-hover);
  color: white;
  text-decoration: none;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(154, 116, 50, 0.3);
}

.seemore_bt {
  margin: 40px auto 0;
}

.seemore_bt a {
  display: inline-block;
  padding: 12px 30px;
  background-color: var(--primary-color);
  color: white;
  border-radius: 50px;
  font-weight: 500;
  transition: var(--transition);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.seemore_bt a:hover {
  background-color: var(--primary-hover);
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(154, 116, 50, 0.2);
}

/* Testimonial Section Improvements */
.testimonial-section {
  padding: var(--section-padding);
  background-color: var(--light-color);
}

.testimonial-card {
  background: white;
  border-radius: var(--border-radius);
  padding: 30px;
  box-shadow: var(--box-shadow);
  margin-bottom: 30px;
  position: relative;
  transition: var(--transition);
  height: 100%;
}

.testimonial-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}

.testimonial-card::before {
  content: '\201C';
  font-family: Georgia, serif;
  font-size: 4rem;
  color: rgba(154, 116, 50, 0.1);
  position: absolute;
  top: 10px;
  left: 10px;
}

.testimonial-text {
  font-style: italic;
  color: #555;
  line-height: 1.8;
  margin-bottom: 20px;
  position: relative;
  z-index: 1;
}

.testimonial-author {
  font-weight: 600;
  color: var(--secondary-color);
}

.testimonial-position {
  color: #777;
  font-size: 0.9rem;
}

/* Newsletter Section Improvements */
.newsletter-section {
  padding: var(--section-padding);
  background: linear-gradient(135deg, var(--primary-color) 0%, #c99a4d 100%);
  color: white;
  position: relative;
  overflow: hidden;
}

.newsletter-section::before,
.newsletter-section::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  background: rgba(255,255,255,0.1);
}

.newsletter-section::before {
  top: -100px;
  right: -100px;
  width: 300px;
  height: 300px;
}

.newsletter-section::after {
  bottom: -150px;
  left: -150px;
  width: 400px;
  height: 400px;
}

.newsletter-title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 20px;
}

.newsletter-form .form-control {
  height: 50px;
  border-radius: 30px 0 0 30px;
  border: none;
  padding-left: 20px;
  box-shadow: none;
}

.newsletter-form .btn {
  background-color: var(--secondary-color);
  color: white;
  border-radius: 0 30px 30px 0;
  padding: 10px 30px;
  font-weight: 500;
  transition: var(--transition);
  border: none;
}

.newsletter-form .btn:hover {
  background-color: var(--dark-color);
}

/* Back to Top Button Enhancement */
.back-to-top {
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 50px;
  height: 50px;
  background-color: var(--primary-color);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  z-index: 99;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.back-to-top.active {
  opacity: 1;
  visibility: visible;
}

.back-to-top:hover {
  background-color: var(--primary-hover);
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

/* Footer Enhancements */
.copyright_section {
  padding: 20px 0;
  background-color: var(--secondary-color);
}

.copyright_text {
  color: white;
  margin: 0;
  text-align: center;
  font-size: 14px;
}

/* Cart Modal Improvements */
.modal-content {
  border-radius: 15px;
  border: none;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.modal-header {
  border-bottom: 1px solid #f1f1f1;
  padding: 20px 30px;
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  border-top: 1px solid #f1f1f1;
  padding: 20px 30px;
}

.table thead th {
  border-bottom: 2px solid #f1f1f1;
  font-weight: 600;
  color: #444;
}

.input-group-prepend .btn,
.input-group-append .btn {
  border-color: #ddd;
  color: #555;
}

.input-group-prepend .btn:hover,
.input-group-append .btn:hover {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.btn-outline-danger:hover {
  background-color: var(--accent-color);
  border-color: var(--accent-color);
}

.order-summary {
  background-color: #f9f9f9;
  padding: 20px;
  border-radius: 10px;
}

.order-summary h5 {
  margin-bottom: 15px;
  color: var(--secondary-color);
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: var(--primary-hover);
  border-color: var(--primary-hover);
}

.btn-outline-secondary {
  color: #555;
  border-color: #ddd;
}

.btn-outline-secondary:hover {
  background-color: #f1f1f1;
  color: #333;
}

/* Mobile responsiveness */
@media (max-width: 992px) {
  .navbar-collapse {
    background-color: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-top: 15px;
  }
  
  .banner_section {
    padding: 80px 0;
  }
  
  .banner_taital {
    font-size: 2.8rem;
  }
  
  .about_taital {
    padding-top: 50px;
  }
}

@media (max-width: 768px) {
  .section-padding {
    padding: 70px 0;
  }
  
  .banner_taital {
    font-size: 2.3rem;
  }
  
  .section-title, 
  .about_taital, 
  .newsletter-title {
    font-size: 2rem;
  }
  
  .product-card, 
  .feature-box, 
  .testimonial-card {
    margin-bottom: 30px;
  }
  
  .newsletter-form .input-group {
    display: block;
  }
  
  .newsletter-form .form-control {
    width: 100%;
    border-radius: 30px;
    margin-bottom: 15px;
  }
  
  .newsletter-form .btn {
    width: 100%;
    border-radius: 30px;
  }
}

@media (max-width: 576px) {
  .banner_taital {
    font-size: 2rem;
  }
  
  .banner_section {
    padding: 60px 0;
  }
  
  .banner_img {
    margin-top: 30px;
  }
  
  .about_img {
    margin-bottom: 30px;
  }
}

        .backHome{
            width: auto;
            padding: 8px;
            text-align: center;
            text-decoration: none;
            font-size: 6px;
        }

        .backHome a {
            color: #000000;
        }
    </style>
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-up-container">
            <form action="login.php" method="POST" enctype="multipart/form-data">
                <h1>Create Account</h1>
                <span>or use your email for registration</span>
                
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
                <div class="backHome"><a href="/LandingPage/index.php">Back to Home</a></div>
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
                <div class="backHome"><a href="/LandingPage/index.php">Back to Home</a></div>
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