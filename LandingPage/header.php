<?php
// Start the session
session_start();

// Prevent caching of the page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialize the cart in the session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <!-- basic -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- site metas -->
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Top Exchange Food Corp'; ?></title>
    <meta name="keywords" content="Filipino food, siopao, siomai, noodles, sauces, food supplier, Philippines">
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Premium Filipino food products since 1998. Our products, services, and company information.'; ?>">
    <meta name="author" content="Top Food Exchange Corp.">
    <!-- bootstrap css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="/LandingPage/css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <!-- font css -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

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

        /* Enhanced Company logo and name styles */
        .navbar-brand {
            display: flex;
            align-items: center;
            transition: var(--transition);
            padding: 5px 0;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .navbar-brand img {
            height: 50px;
            width: auto;
            transition: var(--transition);
        }

        .company-name {
            display: flex;
            flex-direction: column;
            margin-left: 12px;
            line-height: 1.1;
            font-family: 'Roboto', sans-serif;
        }

        .company-name-main {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: linear-gradient(to right, #9a7432, #c9a158);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            padding-bottom: 3px;
        }

        .company-name-main::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(to right, #9a7432, #c9a158);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover .company-name-main::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .company-name-sub {
            font-size: 0.75rem;
            color: var(--secondary-color);
            margin-top: 2px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 500;
            opacity: 0.8;
        }

        @media (max-width: 992px) {
            .company-name-main {
                font-size: 1.4rem;
            }

            .company-name-sub {
                font-size: 0.7rem;
                letter-spacing: 1px;
            }

            .navbar-brand img {
                height: 45px;
            }
        }

        @media (max-width: 768px) {
            .company-name {
                display: none;
            }
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

        /* Enhanced Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
            margin-left: 10px;
        }

        .profile-dropdown-toggle {
            display: flex;
            align-items: center;
            color: var(--secondary-color);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 30px;
            transition: var(--transition);
            background-color: rgba(154, 116, 50, 0.1);
            border: 1px solid rgba(154, 116, 50, 0.2);
        }

        .profile-dropdown-toggle:hover {
            background-color: rgba(154, 116, 50, 0.2);
            color: var(--primary-color);
        }

        .profile-dropdown-toggle img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .profile-dropdown-toggle .username {
            font-weight: 500;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-dropdown-toggle .caret {
            margin-left: 8px;
            transition: transform 0.2s ease;
        }

        .profile-dropdown.active .profile-dropdown-toggle .caret {
            transform: rotate(180deg);
        }

        .profile-dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            background-color: white;
            min-width: 240px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 10px 0;
            z-index: 1000;
            display: none;
            border: 1px solid rgba(0,0,0,0.05);
            transform-origin: top right;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .profile-dropdown.active .profile-dropdown-menu {
            display: block;
        }

        .profile-dropdown-header {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
        }

        .profile-dropdown-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .profile-dropdown-header .user-info {
            line-height: 1.3;
        }

        .profile-dropdown-header .user-name {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 2px;
        }

        .profile-dropdown-header .user-email {
            font-size: 0.8rem;
            color: #777;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--secondary-color);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .profile-dropdown-item i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .profile-dropdown-item:hover {
            background-color: rgba(154, 116, 50, 0.05);
            color: var(--primary-hover);
        }

        .profile-dropdown-item.active {
            background-color: rgba(154, 116, 50, 0.1);
            color: var(--primary-color);
        }

        .profile-dropdown-divider {
            height: 1px;
            margin: 8px 0;
            background-color: rgba(0,0,0,0.05);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .profile-dropdown-menu {
                position: static;
                box-shadow: none;
                border: 1px solid #eee;
                margin-top: 8px;
                width: 100%;
            }

            .profile-dropdown-toggle {
                padding: 6px 10px;
                border-radius: 4px;
            }

            .profile-dropdown-toggle .username {
                max-width: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="header_section">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php">
                    <img src="/LandingPage/images/resized_food_corp_logo.png" alt="Top Food Exchange Corp. Logo">
                    <div class="company-name">
                        <span class="company-name-main">Top Exchange</span>
                        <span class="company-name-sub">Food Corporation</span>
                    </div>
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <a class="nav-link" href="/LandingPage/index.php">Home</a>
                        </li>
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
                            <a class="nav-link" href="/LandingPage/about.php">About</a>
                        </li>
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ordering.php' ? 'active' : ''; ?>">
                            <a class="nav-link" href="/LandingPage/ordering.php">Products</a>
                        </li>
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">
                            <a class="nav-link" href="/LandingPage/contact.php">Contact Us</a>
                        </li>
                    </ul>
                    <form class="form-inline my-2 my-lg-0">
                        <div class="login_bt">
                            <?php if (isset($_SESSION['username'])): ?>
                                <a href="#" class="cart-button" style="margin-right: 15px; position: relative; display: inline-block; text-decoration: none;">
                                    <span style="color: #222222; font-size: 1.2rem;"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="badge badge-danger" style="position: absolute; top: -8px; right: -8px; font-size: 0.7rem; padding: 0.25em 0.4em; border-radius: 50%;"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
                                </a>

                                <!-- Enhanced Profile Dropdown -->
                                <div class="profile-dropdown" id="profileDropdown">
                                    <a href="#" class="profile-dropdown-toggle" onclick="event.preventDefault(); document.getElementById('profileDropdown').classList.toggle('active');">
                                        <img src="/LandingPage/images/default-profile.jpg" alt="Profile Picture">
                                        <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                        <span class="caret"><i class="fas fa-chevron-down"></i></span>
                                    </a>
                                    <div class="profile-dropdown-menu">
                                        <div class="profile-dropdown-header">
                                            <img src="/LandingPage/images/default-profile.jpg" alt="Profile Picture">
                                            <div class="user-info">
                                                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                                <div class="user-email">
                                                    <?php
                                                    // Fetch the user's email from the database
                                                    if (isset($_SESSION['username'])) {
                                                        require_once 'db_connection.php'; // Include your database connection file

                                                        $username = $_SESSION['username'];
                                                        $query = "SELECT email FROM clients_accounts WHERE username = ?";
                                                        $stmt = $conn->prepare($query);
                                                        $stmt->bind_param("s", $username);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();

                                                        if ($result->num_rows > 0) {
                                                            $user = $result->fetch_assoc();
                                                            echo htmlspecialchars($user['email']);
                                                        } else {
                                                            // If not found in clients_accounts, try the admin accounts table
                                                            $query = "SELECT username as email FROM accounts WHERE username = ?";
                                                            $stmt = $conn->prepare($query);
                                                            $stmt->bind_param("s", $username);
                                                            $stmt->execute();
                                                            $result = $stmt->get_result();

                                                            if ($result->num_rows > 0) {
                                                                $user = $result->fetch_assoc();
                                                                echo htmlspecialchars($user['email'] . "@topexchange.com"); // Default domain for admin accounts
                                                            } else {
                                                                echo "No email found";
                                                            }
                                                        }
                                                        $stmt->close();
                                                        // Don't close the connection here as it might be needed by the including page
                                                        // $conn->close();
                                                    } else {
                                                        echo "Not logged in";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="profile-dropdown-divider"></div>
                                        <a href="/LandingPage/profile.php" class="profile-dropdown-item">
                                            <i class="fas fa-user-circle"></i> My Profile
                                        </a>
                                        <a href="/LandingPage/orders.php" class="profile-dropdown-item">
                                            <i class="fas fa-clipboard-list"></i> Order History
                                        </a>
                                        <a href="/LandingPage/payments.php" class="profile-dropdown-item">
                                            <i class="fas fa-credit-card"></i> Payment History
                                        </a>
                                        <div class="profile-dropdown-divider"></div>
                                        <a href="/LandingPage/settings.php" class="profile-dropdown-item">
                                            <i class="fas fa-cog"></i> Account Settings
                                        </a>
                                        <div class="profile-dropdown-divider"></div>
                                        <a href="/LandingPage/logout.php" class="profile-dropdown-item">
                                            <i class="fas fa-sign-out-alt"></i> Logout
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <a href="/LandingPage/login.php">Login
                                    <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </nav>
        </div>

        <?php if (isset($_SESSION['username'])): ?>
        <!-- Cart Modal -->
        <div class="modal fade" id="cartModal" tabindex="-1" role="dialog" aria-labelledby="cartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cartModalLabel">Your Shopping Cart</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="empty-cart-message" class="text-center py-4" style="display: <?php echo empty($_SESSION['cart']) ? 'block' : 'none'; ?>;">
                            <i class="fa fa-shopping-cart fa-4x mb-3" style="color: #ddd;"></i>
                            <h4>Your cart is empty</h4>
                            <p>Start shopping to add items to your cart</p>
                        </div>
                        <div id="cart-items-container" style="display: <?php echo empty($_SESSION['cart']) ? 'none' : 'block'; ?>;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 120px;">Product</th>
                                            <th>Description</th>
                                            <th style="width: 100px;">Price</th>
                                            <th style="width: 150px;">Quantity</th>
                                            <th style="width: 100px;">Subtotal</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cart-items-list">
                                        <?php if (!empty($_SESSION['cart'])): ?>
                                            <?php
                                            $subtotal = 0;
                                            foreach ($_SESSION['cart'] as $productId => $item):
                                                $itemSubtotal = $item['price'] * $item['quantity'];
                                                $subtotal += $itemSubtotal;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>"
                                                             style="width: 80px; height: 80px; object-fit: contain;">
                                                    </td>
                                                    <td>
                                                        <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($item['packaging'] ?? ''); ?></small>
                                                    </td>
                                                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                    <td>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <button class="btn btn-outline-secondary decrease-quantity"
                                                                        type="button"
                                                                        data-product-id="<?php echo $productId; ?>">
                                                                    <i class="fa fa-minus"></i>
                                                                </button>
                                                            </div>
                                                            <input type="text"
                                                                   class="form-control text-center quantity-input"
                                                                   value="<?php echo $item['quantity']; ?>"
                                                                   readonly>
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-secondary increase-quantity"
                                                                        type="button"
                                                                        data-product-id="<?php echo $productId; ?>">
                                                                    <i class="fa fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>₱<?php echo number_format($itemSubtotal, 2); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger remove-from-cart"
                                                                data-product-id="<?php echo $productId; ?>">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="special-instructions">Special Instructions</label>
                                        <textarea class="form-control" id="special-instructions" rows="3" placeholder="Any special requests or notes for your order..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <div class="order-summary">
                                        <h5>Order Summary</h5>
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="subtotal-amount">₱<?php echo number_format($subtotal ?? 0, 2); ?></span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <strong>Total:</strong>
                                            <strong id="total-amount">₱<?php echo number_format(($subtotal ?? 0), 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Continue Shopping</button>
                        <button type="button" class="btn btn-primary" id="checkout-button">Proceed to Checkout</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Custom Popup Message -->
        <div id="customPopup" class="custom-popup">
            <div class="popup-content">
                <span id="popupMessage"></span>
            </div>
        </div>
    </div>