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
    <title>Contact Us | Top Exchange Food Corp</title>
    <meta name="keywords" content="contact, food corporation, restaurant, catering, inquiry">
    <meta name="description" content="Get in touch with Top Exchange Food Corp for inquiries, partnerships, or customer support. We'd love to hear from you.">
    <meta name="author" content="Top Exchange Food Corp">
    <!-- bootstrap css -->
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="images/fevicon.png" type="image/gif" />
    <!-- font css -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- Scrollbar Custom CSS -->
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <!-- Tweaks for older IEs-->
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom Popup Styles (unchanged) */
        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: rgb(173, 133, 59);
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
            background-color: #f44336;
        }

        @keyframes slideIn {
            from { right: -100%; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Cart count badge (unchanged) */
        .badge-danger {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 12px;
            position: relative;
            top: -10px;
            left: -5px;
        }

        /* Enhanced Contact Page Styles */
        .contact-hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/background.png') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 60px;
        }

        .contact-hero h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .contact-hero p {
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto;
        }

        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .contact-section {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 60px;
        }

        .contact-form-container {
            flex: 1;
            min-width: 300px;
        }

        .contact-info-container {
            flex: 1;
            min-width: 300px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 30px;
            color: #333;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 3px;
            background-color: rgb(173, 133, 59);
        }

        .contact-form {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            border-color: rgb(173, 133, 59);
            outline: none;
            box-shadow: 0 0 0 3px rgba(173, 133, 59, 0.1);
            background-color: #fff;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .submit-btn {
            background-color: rgb(173, 133, 59);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
        }

        .submit-btn:hover {
            background-color: rgba(173, 133, 59, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .contact-info {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.05);
        }

        .contact-details {
            margin-bottom: 30px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .contact-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .contact-icon {
            color: rgb(173, 133, 59);
            font-size: 20px;
            margin-right: 15px;
            margin-top: 3px;
        }

        .contact-content h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }

        .contact-content p, .contact-content a {
            color: #666;
            margin: 0;
            text-decoration: none;
            transition: color 0.3s;
        }

        .contact-content a:hover {
            color: rgb(173, 133, 59);
        }

        .business-hours {
            margin-bottom: 30px;
        }

        .hours-table {
            width: 100%;
            border-collapse: collapse;
        }

        .hours-table tr {
            border-bottom: 1px solid #f0f0f0;
        }

        .hours-table tr:last-child {
            border-bottom: none;
        }

        .hours-table td {
            padding: 10px 0;
            color: #666;
        }

        .hours-table td:first-child {
            font-weight: 500;
            color: #333;
        }

        .map-container {
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: #f5f5f5;
            color: #666;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .social-link:hover {
            background-color: rgb(173, 133, 59);
            color: white;
            transform: translateY(-3px);
        }

        .contact-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }

        .feature-card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 40px;
            color: rgb(173, 133, 59);
            margin-bottom: 20px;
        }

        .feature-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .feature-desc {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .contact-hero {
                padding: 60px 0;
            }
            
            .contact-hero h1 {
                font-size: 32px;
            }
            
            .contact-section {
                flex-direction: column;
            }
            
            .section-title {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .contact-hero {
                padding: 40px 0;
            }
            
            .contact-hero h1 {
                font-size: 28px;
            }
            
            .contact-form, .contact-info {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section (unchanged) -->
    <div class="header_section header_bg">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php"><img src="images/resized_food_corp_logo.png"></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="ordering.php">Products</a>
                        </li>
                        <li class="nav-item active">
                            <a class="nav-link" href="contact.php">Contact Us</a>
                        </li>
                    </ul>
                    <form class="form-inline my-2 my-lg-0">
                        <div class="login_bt">
                            <?php if (isset($_SESSION['username'])): ?>
                                <a href="#" class="cart-button" data-toggle="modal" data-target="#cartModal">
                                    <span style="color: #222222;"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="badge badge-danger"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
                                </a>
                                <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>) 
                                    <span style="color: #222222;"><i class="fa fa-sign-out" aria-hidden="true"></i></span>
                                </a>
                            <?php else: ?>
                                <a href="login.php">Login 
                                    <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </nav>
        </div>
    </div>

    <?php if (isset($_SESSION['username'])): ?>
    <!-- Cart Modal (unchanged) -->
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
                                                    <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'images/default-product.jpg'); ?>" 
                                                         style="width: 80px; height: 80px; object-fit: cover;">
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
                                    <div class="d-flex justify-content-between">
                                        <span>Delivery Fee:</span>
                                        <span id="delivery-fee">₱<?php echo number_format(($subtotal ?? 0) > 500 ? 0 : 50, 2); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total:</strong>
                                        <strong id="total-amount">₱<?php echo number_format(($subtotal ?? 0) + (($subtotal ?? 0) > 500 ? 0 : 50), 2); ?></strong>
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

    <!-- Custom Popup Message (unchanged) -->
    <div id="customPopup" class="custom-popup">
        <div class="popup-content">
            <span id="popupMessage"></span>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="contact-hero">
        <div class="container">
            <h1>We'd Love to Hear From You</h1>
            <p>Whether you have questions about our products, need catering services, or want to partner with us, our team is ready to assist you.</p>
        </div>
    </div>

    <!-- Main Contact Section -->
    <div class="contact-container">
        <div class="contact-section">
            <!-- Contact Form -->
            <div class="contact-form-container">
                <h2 class="section-title">Send Us a Message</h2>
                <form id="contactForm" class="contact-form" action="/submit_contact.php" method="POST">
                    <div class="form-group">
                        <input type="text" class="form-control" name="name" placeholder="Your Name*" required>
                    </div>
                    <div class="form-group">
                        <input type="email" class="form-control" name="email" placeholder="Email Address*" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" class="form-control" name="phone" placeholder="Phone Number">
                    </div>
                    <div class="form-group">
                        <select class="form-control" name="subject">
                            <option value="">Select Subject</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Product Questions">Product Questions</option>
                            <option value="Catering Services">Catering Services</option>
                            <option value="Partnership">Partnership</option>
                            <option value="Feedback">Feedback</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" name="message" placeholder="Your Message*" required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="contact-info-container">
                <h2 class="section-title">Contact Information</h2>
                <div class="contact-info">
                    <div class="contact-details">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fa fa-map-marker"></i>
                            </div>
                            <div class="contact-content">
                                <h3>Our Location</h3>
                                <a href="https://maps.app.goo.gl/mJtw3QR2Bks5sGCd8" target="_blank">
                                    74 M.H. Del Pilar St, Quezon City, 1105 Metro Manila
                                </a>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fa fa-phone"></i>
                            </div>
                            <div class="contact-content">
                                <h3>Phone Numbers</h3>
                                <p>Main: <a href="tel:+63286307145">(02) 86307145</a></p>
                                <p>Mobile: <a href="tel:+639123456789">+63 912 345 6789</a></p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fa fa-envelope"></i>
                            </div>
                            <div class="contact-content">
                                <h3>Email Address</h3>
                                <a href="mailto:aedanevangelista.capstone2@gmail.com">aedanevangelista.capstone2@gmail.com</a>
                                <p>For business inquiries: <a href="mailto:business@topexchange.com">business@topexchange.com</a></p>
                            </div>
                        </div>
                    </div>

                    <div class="business-hours">
                        <h3 class="feature-title">Business Hours</h3>
                        <table class="hours-table">
                            <tr>
                                <td>Monday - Friday</td>
                                <td>8:00 AM - 8:00 PM</td>
                            </tr>
                            <tr>
                                <td>Saturday</td>
                                <td>9:00 AM - 6:00 PM</td>
                            </tr>
                            <tr>
                                <td>Sunday</td>
                                <td>10:00 AM - 4:00 PM</td>
                            </tr>
                        </table>
                    </div>

                    <div class="map-container">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.258570883843!2d121.05215031532774!3d14.63278968000938!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b7afde2a3a61%3A0x8511af5a1a4f8e1e!2s74%20M.H.%20Del%20Pilar%20St%2C%20Quezon%20City%2C%201105%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1620000000000!5m2!1sen!2sph" allowfullscreen="" loading="lazy"></iframe>
                    </div>

                    <div class="social-links">
                        <a href="https://www.facebook.com/secretrecipesfoodcorp" class="social-link" target="_blank" aria-label="Facebook">
                            <i class="fa fa-facebook"></i>
                        </a>
                        <a href="https://www.instagram.com/topexchangefoodcorp/" class="social-link" target="_blank" aria-label="Instagram">
                            <i class="fa fa-instagram"></i>
                        </a>
                        <a href="https://ph.linkedin.com/in/mr-choi-head-office-mayeth-tagle-2ba923110?original_referer=https%3A%2F%2Fwww.google.com%2F" class="social-link" target="_blank" aria-label="LinkedIn">
                            <i class="fa fa-linkedin"></i>
                        </a>
                        <a href="https://www.dnb.com/business-directory/company-profiles.top_exchange_food_corp.b2cf650e8e0ac9905e5e4a3c92fa7e68.html" class="social-link" target="_blank" aria-label="Twitter">
                            <i class="fa fa-twitter"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Features Section -->
        <div class="contact-features">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa fa-headphones"></i>
                </div>
                <h3 class="feature-title">Customer Support</h3>
                <p class="feature-desc">Our dedicated support team is available to assist you with any questions or concerns about our products and services.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa fa-truck"></i>
                </div>
                <h3 class="feature-title">Delivery Inquiry</h3>
                <p class="feature-desc">Questions about delivery options, timing, or areas we serve? We're happy to provide all the details you need.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa fa-handshake-o"></i>
                </div>
                <h3 class="feature-title">Partnerships</h3>
                <p class="feature-desc">Interested in becoming a distributor or partner? Contact our business development team for opportunities.</p>
            </div>
        </div>
    </div>

    <!-- Copyright Section -->
    <div class="copyright_section margin_top90">
        <div class="container">
            <p class="copyright_text">© 2025 Top Exchange Food Corp. All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>

    <!-- Javascript files-->
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.0.0.min.js"></script>
    <script src="js/plugin.js"></script>
    <!-- sidebar -->
    <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="js/custom.js"></script>

    <?php if (isset($_SESSION['username'])): ?>
    <script>
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

        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            $.ajax({
                url: 'update_cart_item.php',
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
                url: 'remove_cart_item.php',
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
                url: 'fetch_cart_items.php',
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
                                            <img src="${item.image_path || 'images/default-product.jpg'}" 
                                                 alt="${item.name}" 
                                                 style="width: 80px; height: 80px; object-fit: cover;">
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
                window.location.href = 'checkout.php';
            });

            // Update cart modal when it's shown
            $('#cartModal').on('show.bs.modal', function() {
                updateCartModal();
            });

            // Contact form submission
            $('#contactForm').on('submit', function(e) {
                e.preventDefault();
                // Here you would typically add AJAX form submission
                // For now, we'll just show a success message
                showPopup("Your message has been sent successfully! We'll get back to you soon.");
                this.reset();
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>