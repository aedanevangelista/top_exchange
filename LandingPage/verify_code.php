<?php
// verify_code.php
session_start();
require 'db_connection.php';

// Check if email exists in session
if (!isset($_SESSION['verification_email'])) {
    header('Location: login.php');
    exit();
}

$email = $_SESSION['verification_email'];
$error_message = '';
$success_message = '';
$show_resend = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify'])) {
        $code = trim($_POST['code']);
        
        // Rate limiting
        if (isset($_SESSION['last_verification_attempt'])) {
            $time_since_last_attempt = time() - $_SESSION['last_verification_attempt'];
            if ($time_since_last_attempt < 30) {
                $error_message = "Please wait 30 seconds between verification attempts.";
            }
        }
        
        // Attempts limit
        if (($_SESSION['verification_attempts'] ?? 0) >= 5) {
            $error_message = "Too many verification attempts. Please try logging in again.";
            unset($_SESSION['verification_email']);
            unset($_SESSION['verification_attempts']);
            header('Location: login.php');
            exit();
        }
        
        if (empty($error_message)) {
            // Check the code
            $stmt = $conn->prepare("SELECT * FROM clients_accounts WHERE email = ? AND verification_code = ? AND code_expires_at > NOW()");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            $client = $result->fetch_assoc();
            
            if ($client) {
                // Login successful
                $_SESSION['user_id'] = $client['id'];
                $_SESSION['username'] = $client['username'];
                $_SESSION['role'] = 'Client';
                
                // Clear verification code
                $stmt = $conn->prepare("UPDATE clients_accounts SET verification_code = NULL, code_expires_at = NULL WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                
                // Clear session
                unset($_SESSION['verification_email']);
                unset($_SESSION['verification_attempts']);
                unset($_SESSION['last_verification_attempt']);
                
                header('Location: ordering.php');
                exit();
            } else {
                $_SESSION['verification_attempts']++;
                $_SESSION['last_verification_attempt'] = time();
                $error_message = "Invalid verification code or code has expired.";
                
                if ($_SESSION['verification_attempts'] >= 3) {
                    $show_resend = true;
                }
            }
        }
    } elseif (isset($_POST['resend'])) {
        $verification_code = generateVerificationCode();
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Update database
        $stmt = $conn->prepare("UPDATE clients_accounts SET verification_code = ?, code_expires_at = ? WHERE email = ?");
        $stmt->bind_param("sss", $verification_code, $expires_at, $email);
        $stmt->execute();
        
        // Send email
        if (sendVerificationEmail($email, $verification_code)) {
            $success_message = "A new verification code has been sent to your email.";
            $_SESSION['verification_attempts'] = 0;
            $_SESSION['last_verification_attempt'] = time();
            $show_resend = false;
        } else {
            $error_message = "Failed to send verification email. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Your Email | Top Exchange Food Corp</title>
    <link rel="stylesheet" href="/LandingPage/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --accent-color: #dc3545;
            --border-radius: 8px;
        }
        
        .verification-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .verification-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .verification-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .verification-icon {
            font-size: 50px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            height: 48px;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .btn-verify {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            width: 100%;
            border-radius: var(--border-radius);
        }
        
        .btn-verify:hover {
            background-color: var(--primary-hover);
        }
        
        .resend-link {
            color: var(--primary-color);
            cursor: pointer;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: var(--border-radius);
        }
        
        .code-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .code-input {
            width: 15%;
            height: 60px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
        }
        
        .code-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(154, 116, 50, 0.1);
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <div class="container">
        <div class="verification-container">
            <div class="verification-header">
                <div class="verification-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h2>Verify Your Email</h2>
                <p>We've sent a 6-digit verification code to<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="code">Enter Verification Code</label>
                    <input type="text" name="code" id="code" class="form-control" placeholder="123456" required maxlength="6" pattern="\d{6}">
                </div>
                
                <button type="submit" name="verify" class="btn btn-verify">Verify & Login</button>
                
                <?php if ($show_resend): ?>
                    <div class="text-center mt-3">
                        <p>Didn't receive the code? <button type="submit" name="resend" class="btn btn-link p-0 resend-link">Resend Code</button></p>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-muted">Having trouble? <a href="contact.php">Contact support</a></p>
            </div>
        </div>
    </div>

    <script src="/LandingPage/js/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto focus on code input
            $('#code').focus();
            
            // Auto advance between code inputs if using multiple fields
            $('.code-input').keyup(function() {
                if (this.value.length === 1) {
                    $(this).next('.code-input').focus();
                }
            });
        });
    </script>
</body>
</html>