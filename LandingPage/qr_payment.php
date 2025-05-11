<?php
// Start the session
session_start();

// Check if user is logged in - using username instead of loggedin flag
if (!isset($_SESSION['username'])) {
    // Log the session data for debugging
    error_log("QR Payment - Session not authenticated: " . json_encode($_SESSION));
    header("Location: login.php");
    exit;
}

// Log the session data for debugging
error_log("QR Payment - Session authenticated for user: " . $_SESSION['username']);

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$orderId = $_GET['id'];

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
$stmt->bind_param("is", $orderId, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found or doesn't belong to this user
    header("Location: dashboard.php");
    exit;
}

$order = $result->fetch_assoc();

// Check if payment method is QR payment - check both database and session
$isQrPayment = false;

// Check database first
if (isset($order['payment_method']) && $order['payment_method'] === 'qr_payment') {
    $isQrPayment = true;
}

// Also check session as a fallback
if (isset($_SESSION['payment_method']) && $_SESSION['payment_method'] === 'qr_payment') {
    $isQrPayment = true;
}

// Log the payment method check
error_log("QR Payment - Payment method check: DB=" . ($order['payment_method'] ?? 'not set') .
          ", Session=" . ($_SESSION['payment_method'] ?? 'not set') .
          ", isQrPayment=" . ($isQrPayment ? 'true' : 'false'));

if (!$isQrPayment) {
    header("Location: order_confirmation.php?id=" . $orderId);
    exit;
}

// Check if payment is already completed
if (isset($order['payment_status']) && $order['payment_status'] === 'Completed') {
    error_log("QR Payment - Payment already completed for order ID: " . $orderId);
    header("Location: order_confirmation.php?id=" . $orderId);
    exit;
}

// Log that we're proceeding with QR payment
error_log("QR Payment - Proceeding with QR payment for order ID: " . $orderId);

// Include the header
$pageTitle = "QR Payment";
require_once 'header.php';

// Generate a unique payment reference
$paymentReference = 'PAY-' . $order['po_number'] . '-' . time();

// Store the payment reference in the session
$_SESSION['payment_reference'] = $paymentReference;
$_SESSION['order_id'] = $orderId;

// Format the total amount
$totalAmount = number_format($order['total_amount'], 2);
?>

<style>
    :root {
        --primary-color: #9a7432;
        --primary-hover: #b08a3e;
        --secondary-color: #333;
        --light-color: #f8f9fa;
        --dark-color: #222;
        --accent-color: #dc3545;
        --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
        --border-radius: 8px;
    }

    .payment-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }

    .payment-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .payment-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 10px;
    }

    .payment-subtitle {
        font-size: 1.1rem;
        color: var(--secondary-color);
    }

    .payment-details {
        background-color: #f9f9f9;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 30px;
    }

    .payment-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px dashed #e0e0e0;
    }

    .payment-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .payment-label {
        font-weight: 600;
        color: var(--secondary-color);
    }

    .payment-value {
        font-weight: 500;
    }

    .payment-amount {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    .qr-container {
        text-align: center;
        margin: 30px 0;
    }

    .qr-code {
        max-width: 250px;
        margin: 0 auto;
        padding: 15px;
        background-color: white;
        border: 1px solid #e0e0e0;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }

    .qr-code img {
        width: 100%;
        height: auto;
    }

    .qr-instructions {
        margin-top: 20px;
        font-size: 0.9rem;
        color: #666;
    }

    .payment-actions {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 30px;
    }

    .btn-payment {
        padding: 12px 25px;
        font-weight: 600;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }

    .btn-confirm {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .btn-confirm:hover {
        background-color: var(--primary-hover);
        border-color: var(--primary-hover);
        transform: translateY(-2px);
    }

    .btn-cancel {
        background-color: #f8f9fa;
        border-color: #ddd;
        color: #666;
    }

    .btn-cancel:hover {
        background-color: #e9ecef;
        border-color: #ccc;
    }

    .payment-status {
        text-align: center;
        margin-top: 20px;
        padding: 15px;
        border-radius: var(--border-radius);
        display: none;
    }

    .payment-status.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .payment-status.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .payment-status-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    .payment-status-message {
        font-weight: 600;
    }

    .payment-timer {
        text-align: center;
        margin: 20px 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .timer-value {
        color: var(--accent-color);
    }
</style>

<main class="main-content">
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <h1 class="payment-title">QR Payment</h1>
                <p class="payment-subtitle">Complete your payment by scanning the QR code below</p>
            </div>

            <div class="payment-details">
                <div class="payment-row">
                    <span class="payment-label">Order Number:</span>
                    <span class="payment-value"><?php echo htmlspecialchars($order['po_number']); ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Payment Reference:</span>
                    <span class="payment-value"><?php echo htmlspecialchars($paymentReference); ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Amount:</span>
                    <span class="payment-amount">₱<?php echo htmlspecialchars($totalAmount); ?></span>
                </div>
            </div>

            <div class="payment-timer">
                Time remaining: <span class="timer-value" id="paymentTimer">10:00</span>
            </div>

            <div class="qr-container">
                <div class="qr-code">
                    <!-- QR code image will be generated dynamically -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode('Payment for Order: ' . $order['po_number'] . ' - Amount: ₱' . $totalAmount . ' - Ref: ' . $paymentReference); ?>" alt="Payment QR Code">
                </div>
                <div class="qr-instructions">
                    <p>1. Open your mobile banking or payment app</p>
                    <p>2. Scan the QR code above</p>
                    <p>3. Complete the payment for ₱<?php echo htmlspecialchars($totalAmount); ?></p>
                    <p>4. Click "I've Completed Payment" below after payment</p>
                </div>
            </div>

            <div id="paymentStatus" class="payment-status">
                <div class="payment-status-icon">
                    <i class="fas fa-spinner fa-spin" id="statusIcon"></i>
                </div>
                <div class="payment-status-message" id="statusMessage">
                    Verifying your payment...
                </div>
            </div>

            <div class="payment-actions">
                <button type="button" id="cancelPaymentBtn" class="btn btn-cancel btn-payment">Cancel Payment</button>
                <button type="button" id="confirmPaymentBtn" class="btn btn-confirm btn-payment">I've Completed Payment</button>
            </div>
        </div>
    </div>
</main>

<?php
// Include the footer
require_once 'footer.php';
?>

<script>
    $(document).ready(function() {
        // Payment timer
        let timeLeft = 10 * 60; // 10 minutes in seconds
        const timerElement = $('#paymentTimer');

        const timerInterval = setInterval(function() {
            timeLeft--;

            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;

            timerElement.text(
                minutes.toString().padStart(2, '0') + ':' +
                seconds.toString().padStart(2, '0')
            );

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                // Redirect to order page or show timeout message
                window.location.href = 'dashboard.php?payment_timeout=1';
            }
        }, 1000);

        // Handle cancel payment button
        $('#cancelPaymentBtn').click(function() {
            if (confirm('Are you sure you want to cancel this payment? Your order will not be processed.')) {
                window.location.href = 'dashboard.php?payment_cancelled=1';
            }
        });

        // Handle confirm payment button
        $('#confirmPaymentBtn').click(function() {
            // Show payment status
            $('#paymentStatus').show();
            $('#confirmPaymentBtn').prop('disabled', true);
            $('#cancelPaymentBtn').prop('disabled', true);

            // Simulate payment verification (in a real app, this would be an AJAX call to verify payment)
            setTimeout(function() {
                // Payment successful
                $('#statusIcon').removeClass('fa-spinner fa-spin').addClass('fa-check-circle');
                $('#statusMessage').text('Payment confirmed! Redirecting to your order...');
                $('#paymentStatus').removeClass('error').addClass('success');

                // Redirect to order confirmation page after 2 seconds
                setTimeout(function() {
                    window.location.href = 'verify_payment.php?reference=<?php echo $paymentReference; ?>&order_id=<?php echo $orderId; ?>';
                }, 2000);
            }, 3000);
        });
    });
</script>
