<?php
// Start the session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    <link rel="icon" href="/LandingPage/images/resized_food_corp_logo.png" type="image/png" />
    <!-- font css -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery (required for cart functions) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Cart Functions -->
    <script src="/LandingPage/cart_functions.js"></script>

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

        /* Cart Modal Styles */
        #cartModal .modal-header {
            background-color: var(--primary-color);
            color: white;
        }

        #cartModal .modal-title {
            font-weight: 600;
        }

        #cartModal .table th {
            border-top: none;
        }

        #cartModal .order-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        #cartModal .input-group {
            width: 120px;
        }

        #cartModal .quantity-input {
            text-align: center;
            -moz-appearance: textfield; /* Firefox */
        }

        /* Remove spinner arrows for Chrome, Safari, Edge, Opera */
        #cartModal .quantity-input::-webkit-outer-spin-button,
        #cartModal .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #cartModal .quantity-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(154, 116, 50, 0.25);
        }

        #cartModal .btn-outline-secondary {
            border-color: #ced4da;
        }

        #cartModal .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
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

        .profile-dropdown-toggle .profile-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: 10px;
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

        .profile-dropdown-header .profile-header-icon {
            font-size: 2.2rem;
            color: var(--primary-color);
            margin-right: 10px;
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

        /* Cart Button Styles */
        .cart-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            background-color: rgba(154, 116, 50, 0.1);
            border: 1px solid rgba(154, 116, 50, 0.2);
            border-radius: 50%;
            width: 42px;
            height: 42px;
        }

        .cart-button:hover {
            background-color: rgba(154, 116, 50, 0.2);
            transform: translateY(-2px);
        }

        .cart-button .cart-icon {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25em 0.4em;
            min-width: 18px;
            height: 18px;
            line-height: 1;
            text-align: center;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .cart-button:hover .cart-count {
            transform: scale(1.1);
        }

        /* Fixed Header Styles */
        .header_section {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Add padding to body to prevent content from being hidden under fixed header */
        body {
            padding-top: 76px; /* Adjust this value based on your header height */
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            body {
                padding-top: 66px; /* Smaller padding for mobile */
            }

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

            .cart-button {
                width: 38px;
                height: 38px;
                margin-right: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header_section">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light py-2">
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
                                <a href="javascript:void(0);" class="cart-button" onclick="$('#cartModal').modal('show');" aria-label="Shopping Cart">
                                    <span class="cart-icon"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="cart-count"><?php
                                        // Safely calculate cart count with error handling
                                        $cartCount = 0;
                                        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                            foreach ($_SESSION['cart'] as $item) {
                                                if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                                                    $cartCount += (int)$item['quantity'];
                                                }
                                            }
                                        }
                                        echo $cartCount;
                                    ?></span>
                                </a>

                                <!-- Enhanced Profile Dropdown -->
                                <div class="profile-dropdown" id="profileDropdown">
                                    <a href="#" class="profile-dropdown-toggle" onclick="event.preventDefault(); document.getElementById('profileDropdown').classList.toggle('active');">
                                        <i class="fas fa-user-circle profile-icon"></i>
                                        <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                        <span class="caret"><i class="fas fa-chevron-down"></i></span>
                                    </a>
                                    <div class="profile-dropdown-menu">
                                        <div class="profile-dropdown-header">
                                            <i class="fas fa-user-circle profile-header-icon"></i>
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
        <!-- Cart Modal is included in footer.php for Bootstrap 4 pages -->
        <!-- For Bootstrap 5 pages (profile.php, orders.php, payments.php, settings.php),
             the cart modal is included directly in those files -->
        <?php
        // Set a flag to prevent including cart modal in footer for Bootstrap 5 pages
        $currentPage = basename($_SERVER['PHP_SELF']);
        $bootstrap5Pages = ['profile.php', 'orders.php', 'payments.php', 'settings.php'];
        if (in_array($currentPage, $bootstrap5Pages)) {
            $_SESSION['skip_cart_modal_in_footer'] = true;
        } else {
            $_SESSION['skip_cart_modal_in_footer'] = false;
        }
        ?>
        <?php endif; ?>

        <!-- Custom Popup Message -->
        <div id="customPopup" class="custom-popup">
            <div class="popup-content">
                <span id="popupMessage"></span>
            </div>
        </div>
    </div>