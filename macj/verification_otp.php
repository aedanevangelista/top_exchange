<?php
require_once("Database/userdb.php");
session_start();
if (!isset($_SESSION['OTP'])) {
    header("Location: SignUp.php");
    exit();
}

$email = $_SESSION["email"];
$firstName = $_SESSION["firstName"];
$lastName = $_SESSION["lastName"];
$contact = $_SESSION["contact"];
$location = $_SESSION["location"];
$placeType = $_SESSION["placeType"];
$password = $_SESSION["password"];

$SEND_OTP = $_SESSION["OTP"];

if(isset($_POST["OTP_BTN"])){
    // Getting the OTPs
    $otp1 = $_POST["OTP1"];
    $otp2 = $_POST["OTP2"];
    $otp3 = $_POST["OTP3"];
    $otp4 = $_POST["OTP4"];
    $otp5 = $_POST["OTP5"];
    $otp6 = $_POST["OTP6"];

    $input_otp = $otp1 . $otp2 . $otp3 . $otp4 . $otp5 . $otp6;

    if($input_otp == $SEND_OTP){
        // Insert user into database
        $user_id = Insert_User($firstName, $firstName, $lastName, $email, $contact, $password, "Customer", $location, $placeType);

        if ($user_id) {
            $_SESSION['client_id'] = $user_id;
            $_SESSION['OTP_SUCCESS'] = true; // Set success flag
            header("Location: verification_otp.php?status=success");
            exit();
        } else {
            $_SESSION['OTP_ERROR'] = "Registration failed. Please try again.";
            header("Location: verification_otp.php?status=error");
            exit();
        }
    } else {
        $_SESSION['OTP_ERROR'] = "Invalid OTP. Please try again.";
        header("Location: verification_otp.php?status=error");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Email Verification - MacJ Pest Control</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f5ff;
            font-family: 'Poppins', sans-serif;
            background-image: linear-gradient(135deg, #f0f5ff 0%, #e6f0ff 100%);
        }
        .container {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 62, 155, 0.1);
            text-align: center;
            max-width: 450px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #0056b3, #007bff);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            max-width: 180px;
            margin-bottom: 30px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        .icon {
            font-size: 40px;
            color: #0056b3;
            margin-bottom: 20px;
            background-color: #e6f0ff;
            width: 90px;
            height: 90px;
            line-height: 90px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.15);
            transition: all 0.3s ease;
        }
        .icon:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 86, 179, 0.2);
        }
        h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #0056b3;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        p {
            font-size: 15px;
            color: #555;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .email-address {
            color: #0056b3;
            font-weight: 600;
            word-break: break-all;
        }
        .note-text {
            font-size: 14px;
            font-style: italic;
            color: #666;
            margin-top: 5px;
            margin-bottom: 25px;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border-left: 3px solid #0056b3;
        }
        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            outline: none;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
            color: #0056b3;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .otp-input:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.2);
            background-color: #fff;
            transform: translateY(-2px);
        }
        .otp-input:not(:placeholder-shown) {
            background-color: #e6f0ff;
            border-color: #0056b3;
        }
        .verify-button {
            background: linear-gradient(90deg, #0056b3, #007bff);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 15px;
            letter-spacing: 0.8px;
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.2);
            position: relative;
            overflow: hidden;
        }
        .verify-button:hover {
            background: linear-gradient(90deg, #004494, #0069d9);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(0, 86, 179, 0.3);
        }
        .verify-button:active {
            transform: translateY(1px);
            box-shadow: 0 3px 10px rgba(0, 86, 179, 0.2);
        }
        .verify-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
        }
        .verify-button:hover::after {
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            100% {
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="Landingpage/assets/img/MACJLOGO.png" alt="MacJ Pest Control" class="logo">
        <div class="icon"><i class="bi bi-envelope-check-fill"></i></div>
        <h2>Email Verification</h2>
        <p class="otp-message">Check your email for a 6-digit code to</p>
        <p><span class="email-address"><?php echo htmlspecialchars($email)?></span></p>
        <p>If you can't find the email, check your spam folder.</p>
        <p class="note-text">Note: You can't proceed on Signing In without verifying your email.</p>
        <form action="verification_otp.php" method="POST">
            <div class="code-inputs">
                <input type="text" name="OTP1" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this); moveToNext(this, 'OTP2')" onkeydown="moveToPrev(event, 'OTP1')" required>
                <input type="text" name="OTP2" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this); moveToNext(this, 'OTP3')" onkeydown="moveToPrev(event, 'OTP1')" required>
                <input type="text" name="OTP3" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this); moveToNext(this, 'OTP4')" onkeydown="moveToPrev(event, 'OTP2')" required>
                <input type="text" name="OTP4" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this); moveToNext(this, 'OTP5')" onkeydown="moveToPrev(event, 'OTP3')" required>
                <input type="text" name="OTP5" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this); moveToNext(this, 'OTP6')" onkeydown="moveToPrev(event, 'OTP4')" required>
                <input type="text" name="OTP6" class="otp-input" maxlength="1" placeholder="-" oninput="validateInput(this);" onkeydown="moveToPrev(event, 'OTP5')" required>
            </div>
            <button class="verify-button" name="OTP_BTN">Verify</button>
        </form>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(90deg, #0056b3, #007bff); color: white;">
                    <h5 class="modal-title" id="successModalLabel"><i class="bi bi-check-circle-fill me-2"></i>Registration Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 3.5rem; color: #0056b3;"></i>
                    </div>
                    <h4 style="color: #0056b3; font-weight: 600;">Welcome to MacJ Pest Control!</h4>
                    <p style="color: #555; margin-top: 15px;">Your account has been created successfully. You can now log in to access our services.</p>
                </div>
                <div class="modal-footer d-flex justify-content-center">
                    <a href="SignIn.php" class="btn px-4 py-2" style="background: linear-gradient(90deg, #0056b3, #007bff); color: white; border-radius: 50px; font-weight: 500; box-shadow: 0 4px 10px rgba(0, 86, 179, 0.2);">Proceed to Login</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true" data-bs-backdrop="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Verification Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 style="font-weight: 600;">Oops! Something went wrong</h5>
                    <p style="color: #555; margin-top: 15px;"><?php echo isset($_SESSION['OTP_ERROR']) ? $_SESSION['OTP_ERROR'] : 'Unknown error. Please try again.'; ?></p>
                </div>
                <div class="modal-footer d-flex justify-content-center">
                    <button type="button" class="btn btn-outline-danger px-4 py-2" style="border-radius: 50px; font-weight: 500;" data-bs-dismiss="modal">Try Again</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript to Trigger Modal -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

    <script>
    function moveToNext(currentInput, nextInputName) {
        if (currentInput.value.length >= 1) {
            document.getElementsByName(nextInputName)[0].focus();
        }
    }

    function moveToPrev(event, prevInputName) {
        if (event.key === "Backspace" && event.target.value.length === 0) {
            const prevInput = document.getElementsByName(prevInputName)[0];
            prevInput.focus();
            prevInput.value = '';
        }
    }

    function validateInput(input) {
        input.value = input.value.replace(/[^0-9]/g, '');
    }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');

        if (status === "success") {
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
                window.location.href = 'SignIn.php'; // Redirect to login page after modal closes
            });
        } else if (status === "error") {
            var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }
    });
    </script>
</body>
</html>
