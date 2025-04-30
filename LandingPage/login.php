<?php
// login.php

session_start();
require 'db_connection.php';

// Email functions
function generateVerificationCode() {
    // Use cryptographically secure random function
    if (function_exists('random_int')) {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } else {
        // Fallback if random_int isn't available (less secure)
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function sendVerificationEmail($email, $code) {
    $subject = "Your Top Food Exchange Corp Login Verification Code";
    
    // HTML email template
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #9a7432; padding: 20px; text-align: center; }
            .header img { max-width: 200px; }
            .content { padding: 30px 20px; background-color: #f9f9f9; }
            .code { 
                font-size: 24px; 
                font-weight: bold; 
                color: #9a7432;
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px dashed #9a7432;
                border-radius: 5px;
                display: inline-block;
            }
            .footer { 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #ddd; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
            }
            .button {
                background-color: #9a7432;
                color: white !important;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                display: inline-block;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.topfoodexchangecorp.com/images/logo.png" alt="Top Food Exchange Corp Logo">
            </div>
            <div class="content">
                <h2>Login Verification Code</h2>
                <p>Hello,</p>
                <p>We received a login attempt to your Top Food Exchange Corp account. Please use the following verification code to complete your login:</p>
                
                <div class="code">'.$code.'</div>
                
                <p>This code will expire in 15 minutes. If you didn\'t request this, please ignore this email or contact our support team immediately.</p>
                
                <p>For security reasons, never share this code with anyone.</p>
                
                <p>Thank you,<br>
                Top Food Exchange Corp Team</p>
            </div>
            <div class="footer">
                <p>&copy; '.date('Y').' Top Food Exchange Corp. All rights reserved.</p>
                <p>This is an automated message, please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Plain text version for non-HTML email clients
    $plain_message = "Login Verification Code\n\n";
    $plain_message .= "We received a login attempt to your Top Food Exchange Corp account.\n";
    $plain_message .= "Your verification code is: $code\n\n";
    $plain_message .= "This code will expire in 15 minutes. If you didn't request this, please ignore this email or contact our support team immediately.\n\n";
    $plain_message .= "For security reasons, never share this code with anyone.\n\n";
    $plain_message .= "Thank you,\nTop Food Exchange Corp Team";
    
    $headers = "From: Top Food Exchange Corp <no-reply@topfoodexchangecorp.com>\r\n";
    $headers .= "Reply-To: no-reply@topfoodexchangecorp.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";
    
    // Email body with both HTML and plain text versions
    $email_body = "--boundary\r\n";
    $email_body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $email_body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $email_body .= $plain_message . "\r\n\r\n";
    
    $email_body .= "--boundary\r\n";
    $email_body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $email_body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $email_body .= $message . "\r\n\r\n";
    $email_body .= "--boundary--";
    
    // Additional headers for email tracking (optional)
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1 (Highest)\r\n";
    $headers .= "X-MSMail-Priority: High\r\n";
    $headers .= "Importance: High\r\n";
    
    return mail($email, $subject, $email_body, $headers);
}

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
                    // Generate and send verification code
                    $verification_code = generateVerificationCode();
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    // Store code in database
                    $stmt = $conn->prepare("UPDATE clients_accounts SET verification_code = ?, code_expires_at = ? WHERE email = ?");
                    $stmt->bind_param("sss", $verification_code, $expires_at, $email);
                    $stmt->execute();
                    
                    // Send email
                    if (sendVerificationEmail($email, $verification_code)) {
                        // Store email in session for verification
                        $_SESSION['verification_email'] = $email;
                        $_SESSION['verification_attempts'] = 0;
                        $_SESSION['last_verification_attempt'] = time();
                        
                        // Redirect to verification page
                        header('Location: verify_code.php');
                        exit();
                    } else {
                        $form_errors['login'] = "Failed to send verification email. Please try again.";
                    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- basic -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- site metas -->
    <title>Login | Top Exchange Food Corp</title> 
    <meta name="keywords" content="login, food supplier, Philippines">
    <meta name="description" content="Login to your Top Exchange Food Corp account">
    <meta name="author" content="Top Food Exchange Corp.">
    <!-- bootstrap css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="/LandingPage/admin/css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <!-- font css -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/admin/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
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
            --border-radius: 8px;
        }

        .login-page {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }

        .login-card {
            width: 100%;
            max-width: 480px;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            background: white;
        }

        .login-header {
            background-color: var(--primary-color);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 24px;
        }

        .login-header p {
            margin: 8px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
            font-size: 14px;
        }

        .form-control {
            height: 48px;
            border-radius: var(--border-radius);
            border: 1px solid #e0e0e0;
            padding: 12px 42px 12px 16px;
            font-size: 15px;
            transition: var(--transition);
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(154, 116, 50, 0.1);
            outline: none;
        }

        .btn-login {
            background-color: var(--primary-color);
            border: none;
            color: white;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border-radius: var(--border-radius);
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: var(--primary-hover);
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            font-size: 16px;
            z-index: 2;
            background: white;
            padding: 0 8px;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .forgot-password {
            text-align: center;
            margin: 16px 0 24px;
        }

        .forgot-password a {
            color: var(--primary-color);
            font-size: 13px;
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: var(--accent-color);
            font-size: 13px;
            margin-top: 6px;
            display: block;
        }

        .has-error .form-control {
            border-color: var(--accent-color);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--accent-color);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            font-size: 14px;
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }

        .login-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Custom Popup Styles */
        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            padding: 15px 25px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            display: none;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 2.5s;
            max-width: 300px;
        }

        .popup-content {
            display: flex;
            align-items: center;
        }

        .custom-popup.error {
            background-color: var(--accent-color);
        }

        @keyframes slideIn {
            from { right: -100%; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body class="login-page">
    <!-- Include external header -->
    <?php include('header.php'); ?>

    <!-- Login Section -->
    <div class="login-container">
        <div class="login-card animate__animated animate__fadeIn">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Please login to your account</p>
            </div>
            <div class="login-body">
                <?php if (!empty($form_errors['login'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $form_errors['login']; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" novalidate>
                    <div class="form-group <?php echo isset($form_errors['login_email']) ? 'has-error' : ''; ?>">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <?php if (isset($form_errors['login_email'])): ?>
                            <span class="error-message"><?php echo $form_errors['login_email']; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?php echo isset($form_errors['login_password']) ? 'has-error' : ''; ?>">
                        <label for="password">Password</label>
                        <div class="password-container">
                            <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword()"><i class="fa fa-eye" id="toggleIcon"></i></span>
                        </div>
                        <?php if (isset($form_errors['login_password'])): ?>
                            <span class="error-message"><?php echo $form_errors['login_password']; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>

                    <button type="submit" name="login" class="btn btn-login">Login</button>
                </form>
            </div>
            <div class="login-footer">
                <p>Need help? <a href="contact.php">Contact support</a></p>
            </div>
        </div>
    </div>

    <!-- Include external footer -->
    <?php include('footer.php'); ?>
    
    <!-- Javascript files-->
    <script src="/LandingPage/js/jquery.min.js"></script>
    <script src="/LandingPage/js/popper.min.js"></script>
    <script src="/LandingPage/js/bootstrap.bundle.min.js"></script>
    <script src="/LandingPage/js/jquery-3.0.0.min.js"></script>
    <script src="/LandingPage/js/plugin.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- sidebar -->
    <script src="/LandingPage/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/js/custom.js"></script>
    
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Function to toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Function to show custom popup message
        function showPopup(message, isError = false) {
            const popup = $('#customPopup');
            const popupMessage = $('#popupMessage');
            
            popupMessage.text(message);
            popup.removeClass('error');
            
            if (isError) {
                popup.addClass('error');
            }
            
            // Reset animation by briefly showing/hiding
            popup.hide().show();
            
            // Automatically hide after 3 seconds
            setTimeout(() => {
                popup.hide();
            }, 3000);
        }
    </script>

    <?php if (isset($_SESSION['username'])): ?>
    <script>
        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            $.ajax({
                url: '/LandingPage/update_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    quantity_change: change
                },
                success: function(response) {
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
                        showPopup("Cart updated successfully");
                    } else {
                        showPopup(response.message || "Error updating quantity", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error updating cart item:", error);
                    showPopup("Error updating cart item.", true);
                }
            });
        }

        // Function to remove cart item
        function removeCartItem(productId) {
            $.ajax({
                url: '/LandingPage/remove_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId
                },
                success: function(response) {
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
                        showPopup("Item removed from cart");
                    } else {
                        showPopup(response.message || "Error removing item", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error removing cart item:", error);
                    showPopup("Error removing cart item.", true);
                }
            });
        }

        // Function to update the cart modal
        function updateCartModal() {
            $.ajax({
                url: '/LandingPage/fetch_cart_items.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if(response && response.cart_items !== undefined) {
                        if(response.cart_items.length === 0) {
                            $('#empty-cart-message').show();
                            $('#cart-items-container').hide();
                        } else {
                            $('#empty-cart-message').hide();
                            $('#cart-items-container').show();
                            
                            let cartItemsHtml = '';
                            let subtotal = 0;
                            
                            response.cart_items.forEach(item => {
                                const price = parseFloat(item.price);
                                const itemSubtotal = price * item.quantity;
                                subtotal += itemSubtotal;
                                
                                cartItemsHtml += `
                                    <tr>
                                        <td>
                                            <img src="${item.image_path || '/LandingPage/images/default-product.jpg'}" 
                                                 alt="${item.name}" 
                                                 style="width: 80px; height: 80px; object-fit: contain;">
                                        </td>
                                        <td>
                                            <h6>${item.name}</h6>
                                            <small class="text-muted">${item.packaging || ''}</small>
                                        </td>
                                        <td>₱${price.toFixed(2)}</td>
                                        <td>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <button class="btn btn-outline-secondary decrease-quantity" 
                                                            type="button" 
                                                            data-product-id="${item.product_id}">
                                                        <i class="fa fa-minus"></i>
                                                    </button>
                                                </div>
                                                <input type="text" 
                                                       class="form-control text-center quantity-input" 
                                                       value="${item.quantity}" 
                                                       readonly>
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary increase-quantity" 
                                                            type="button" 
                                                            data-product-id="${item.product_id}">
                                                        <i class="fa fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱${itemSubtotal.toFixed(2)}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger remove-from-cart" 
                                                    data-product-id="${item.product_id}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });

                            $('#cart-items-list').html(cartItemsHtml);
                            $('#subtotal-amount').text('₱' + subtotal.toFixed(2));
                            
                            // Calculate delivery fee
                            const deliveryFee = subtotal > 500 ? 0 : 50;
                            $('#delivery-fee').text('₱' + deliveryFee.toFixed(2));
                            
                            const totalAmount = subtotal + deliveryFee;
                            $('#total-amount').text('₱' + totalAmount.toFixed(2));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching cart items:", error);
                    showPopup("Error fetching cart items.", true);
                }
            });
        }

        $(document).ready(function() {
            // Quantity adjustment handlers
            $(document).on('click', '.increase-quantity', function() {
                const productId = $(this).data('product-id');
                updateCartItemQuantity(productId, 1);
            });

            $(document).on('click', '.decrease-quantity', function() {
                const productId = $(this).data('product-id');
                updateCartItemQuantity(productId, -1);
            });

            // Remove item handler
            $(document).on('click', '.remove-from-cart', function() {
                const productId = $(this).data('product-id');
                removeCartItem(productId);
            });

            // Checkout button handler
            $(document).on('click', '#checkout-button', function() {
                const specialInstructions = $('#special-instructions').val();
                sessionStorage.setItem('specialInstructions', specialInstructions);
                $('#cartModal').modal('hide');
                window.location.href = '/LandingPage/checkout.php';
            });

            // Update cart modal when it's shown
            $('#cartModal').on('show.bs.modal', function() {
                updateCartModal();
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>