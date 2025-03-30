<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Prevent caching of the page (solves the "Back button still logged in" issue)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialize the cart in the session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Connect to MySQL with CORRECT CREDENTIALS
$conn = new mysqli("localhost", "u701062148_top_exchange", "Aedanpogi123", "u701062148_top_exchange");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the page title dynamically
$pageTitle = "About";
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
    <title><?php echo $pageTitle; ?></title>
    <meta name="keywords" content="">
    <meta name="description" content="">
    <meta name="author" content="">
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
    <style>
        /* Custom Popup Styles */
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
        
        /* Cart count badge */
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

        /* New Timeline Styles */
        .timeline {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }
        .timeline::after {
            content: '';
            position: absolute;
            width: 6px;
            background-color: #ad853b;
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -3px;
        }
        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
            box-sizing: border-box;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            width: 25px;
            height: 25px;
            right: -12px;
            background-color: white;
            border: 4px solid #ad853b;
            top: 15px;
            border-radius: 50%;
            z-index: 1;
        }
        .left {
            left: 0;
        }
        .right {
            left: 50%;
        }
        .left::before {
            content: " ";
            height: 0;
            position: absolute;
            top: 22px;
            width: 0;
            z-index: 1;
            right: 30px;
            border: medium solid #f1f1f1;
            border-width: 10px 0 10px 10px;
            border-color: transparent transparent transparent #f1f1f1;
        }
        .right::before {
            content: " ";
            height: 0;
            position: absolute;
            top: 22px;
            width: 0;
            z-index: 1;
            left: 30px;
            border: medium solid #f1f1f1;
            border-width: 10px 10px 10px 0;
            border-color: transparent #f1f1f1 transparent transparent;
        }
        .right::after {
            left: -12px;
        }
        .timeline-content {
            padding: 20px 30px;
            background-color: #f1f1f1;
            position: relative;
            border-radius: 6px;
        }
        .timeline-date {
            color: #ad853b;
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
        }

        /* Achievement Box Styles */
        .achievement-box {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .achievement-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .achievement-icon {
            color: #ad853b;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-title {
            color: #ad853b;
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ordering.php">Products</a>
                    </li>
                    <li class="nav-item">
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
<!-- Cart Modal - Matching the ordering page -->
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

<!-- Custom Popup Message -->
<div id="customPopup" class="custom-popup">
    <div class="popup-content">
        <span id="popupMessage"></span>
    </div>
</div>

<!-- about section start -->
<div class="about_section layout_padding">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="about_img"><img src="images/about_image_1.png" class="img-fluid" alt="About Top Exchange Food Corp"></div>
                <div class="about_img mt-4"><img src="images/about_image_2.png" class="img-fluid" alt="Our Facilities"></div>
            </div>
            <div class="col-md-6">
                <h1 class="about_taital">Top Exchange Food Corporation</h1>
                <p class="about_text"><strong>Top Exchange Food Corporation (TEFC)</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998 with a mission to provide high-quality food products and innovative solutions to businesses throughout the country.</p>
                
                <div class="mission-vision mt-5">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><i class="fa fa-bullseye mr-2"></i>Our Mission</h3>
                                    <p class="card-text">To be the premier food service provider in the Philippines by delivering exceptional quality products, innovative solutions, and unmatched customer service.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><i class="fa fa-eye mr-2"></i>Our Vision</h3>
                                    <p class="card-text">To revolutionize the food service industry by creating a seamless bridge between global food innovations and local culinary traditions, making quality ingredients accessible to all.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="core-values mt-4">
                    <h3><i class="fa fa-star mr-2"></i>Core Values</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Quality:</strong> We source only the finest ingredients</li>
                                <li class="list-group-item"><strong>Integrity:</strong> Honesty in all our dealings</li>
                                <li class="list-group-item"><strong>Innovation:</strong> Continuous improvement in our offerings</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Sustainability:</strong> Environmentally responsible practices</li>
                                <li class="list-group-item"><strong>Customer Focus:</strong> Exceeding expectations</li>
                                <li class="list-group-item"><strong>Teamwork:</strong> Collaborative success</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12">
                <div class="history-section">
                    <h2 class="text-center mb-4"><i class="fa fa-history mr-2"></i>Our History</h2>
                    <div class="timeline">
                        <div class="timeline-item left">
                            <div class="timeline-date">1998</div>
                            <div class="timeline-content">
                                <h4>Foundation</h4>
                                <p>Top Exchange Food Corporation was established with a small warehouse in Metro Manila, serving local restaurants and food businesses.</p>
                            </div>
                        </div>
                        <div class="timeline-item right">
                            <div class="timeline-date">2005</div>
                            <div class="timeline-content">
                                <h4>First Expansion</h4>
                                <p>Opened regional distribution centers in Luzon, Visayas, and Mindanao to better serve clients nationwide.</p>
                            </div>
                        </div>
                        <div class="timeline-item left">
                            <div class="timeline-date">2012</div>
                            <div class="timeline-content">
                                <h4>International Partnerships</h4>
                                <p>Established key partnerships with international suppliers to bring global food products to the Philippine market.</p>
                            </div>
                        </div>
                        <div class="timeline-item right">
                            <div class="timeline-date">2020</div>
                            <div class="timeline-content">
                                <h4>Digital Transformation</h4>
                                <p>Launched e-commerce platform to serve customers more efficiently during the pandemic.</p>
                            </div>
                        </div>
                        <div class="timeline-item left">
                            <div class="timeline-date">Present</div>
                            <div class="timeline-content">
                                <h4>Market Leadership</h4>
                                <p>Recognized as one of the leading food service providers in the country with over 5,000 satisfied clients.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12">
                <div class="team-section">
                    <h2 class="text-center mb-4"><i class="fa fa-users mr-2"></i>Meet Our Leadership Team</h2>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <img src="images/team_ceo.jpg" class="card-img-top" alt="CEO">
                                <div class="card-body">
                                    <h5 class="card-title">Juan Dela Cruz</h5>
                                    <p class="card-subtitle mb-2 text-muted">Founder & CEO</p>
                                    <p class="card-text">With over 25 years in the food industry, Juan established TEFC with a vision to transform food distribution in the Philippines.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <img src="images/team_cfo.jpg" class="card-img-top" alt="CFO">
                                <div class="card-body">
                                    <h5 class="card-title">Maria Santos</h5>
                                    <p class="card-subtitle mb-2 text-muted">Chief Financial Officer</p>
                                    <p class="card-text">Maria brings financial expertise that has guided TEFC's sustainable growth and expansion.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <img src="images/team_coo.jpg" class="card-img-top" alt="COO">
                                <div class="card-body">
                                    <h5 class="card-title">Roberto Garcia</h5>
                                    <p class="card-subtitle mb-2 text-muted">Chief Operations Officer</p>
                                    <p class="card-text">Roberto oversees our nationwide logistics network ensuring timely deliveries to all clients.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12">
                <div class="achievements-section">
                    <h2 class="text-center mb-4"><i class="fa fa-trophy mr-2"></i>Our Achievements</h2>
                    <div class="row text-center">
                        <div class="col-md-3 mb-4">
                            <div class="achievement-box p-3">
                                <div class="achievement-icon mb-3">
                                    <i class="fa fa-building fa-3x"></i>
                                </div>
                                <h3>25+</h3>
                                <p>Warehouses Nationwide</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="achievement-box p-3">
                                <div class="achievement-icon mb-3">
                                    <i class="fa fa-users fa-3x"></i>
                                </div>
                                <h3>5,000+</h3>
                                <p>Satisfied Clients</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="achievement-box p-3">
                                <div class="achievement-icon mb-3">
                                    <i class="fa fa-globe fa-3x"></i>
                                </div>
                                <h3>50+</h3>
                                <p>International Suppliers</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="achievement-box p-3">
                                <div class="achievement-icon mb-3">
                                    <i class="fa fa-certificate fa-3x"></i>
                                </div>
                                <h3>15+</h3>
                                <p>Industry Awards</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12">
                <div class="testimonials-section">
                    <h2 class="text-center mb-4"><i class="fa fa-quote-left mr-2"></i>What Our Clients Say</h2>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fa fa-quote-left text-muted mb-3"></i>
                                        <p class="card-text">Top Exchange Food Corp has been our trusted supplier for over 10 years. Their consistent quality and reliable delivery make them our top choice for all our food service needs.</p>
                                    </div>
                                    <div class="testimonial-author mt-3">
                                        <h6 class="mb-0">- Manuel Reyes</h6>
                                        <small class="text-muted">Owner, Reyes Restaurant Chain</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fa fa-quote-left text-muted mb-3"></i>
                                        <p class="card-text">Their customer service is exceptional. Whenever we have special requests, they go above and beyond to accommodate our needs.</p>
                                    </div>
                                    <div class="testimonial-author mt-3">
                                        <h6 class="mb-0">- Sofia Lim</h6>
                                        <small class="text-muted">Purchasing Manager, Grand Hotel</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fa fa-quote-left text-muted mb-3"></i>
                                        <p class="card-text">The variety of international products they offer has allowed us to expand our menu and attract more customers. Highly recommended!</p>
                                    </div>
                                    <div class="testimonial-author mt-3">
                                        <h6 class="mb-0">- Carlos Tan</h6>
                                        <small class="text-muted">Executive Chef, Fusion Bistro</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- about section end -->

<!-- copyright section start -->
<div class="copyright_section margin_top90">
    <div class="container">
        <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
    </div>
</div>
<!-- copyright section end -->

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
    });
</script>
<?php endif; ?>

<?php $conn->close(); ?>
</body>
</html>