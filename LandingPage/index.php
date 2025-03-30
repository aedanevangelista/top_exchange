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
    <title>Home | Top Exchange Food Corp</title> 
    <meta name="keywords" content="Filipino food, siopao, siomai, noodles, sauces, food supplier, Philippines">
    <meta name="description" content="Top Food Exchange Corp. - Premium Filipino food products since 1998. Quality siopao, siomai, noodles, and sauces.">
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
        :root {
            --primary-color:#9a7432;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
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
        
        /* Cart count badge */
        .badge-danger {
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 12px;
            position: relative;
            top: -10px;
            left: -5px;
        }
        
        /* Banner Section */
        .banner_section {
            background: url('images/banner-bg.jpg') no-repeat center center;
            background-size: cover;
            position: relative;
            color: white;
            padding: 150px 0;
        }
        
        .banner_section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }
        
        .banner_section .container {
            position: relative;
            z-index: 1;
        }
        
        .banner_taital {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.5);
        }
        
        .banner_text {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .started_text a {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(173, 133, 59, 0.3);
            position: relative;
            overflow: hidden;
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
        
        .started_text a:hover {
            background-color: #9a7432;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(173, 133, 59, 0.4);
            text-decoration: none;
        }
        
        /* Product Card */
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .product-img {
            height: 200px;
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
            padding: 20px;
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
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        
        .product-btn:hover {
            background-color: #9a7432;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        /* Section Headings */
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
        
        /* Features Section */
        .feature-box {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .feature-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 0;
            background-color: var(--primary-color);
            transition: height 0.3s ease;
        }
        
        .feature-box:hover::before {
            height: 100%;
        }
        
        .feature-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .feature-box:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .feature-text {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* Testimonials */
        .testimonial-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            position: relative;
        }
        
        .testimonial-card::before {
            content: '\201C';
            font-family: Georgia, serif;
            font-size: 4rem;
            color: rgba(173, 133, 59, 0.1);
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
        
        /* Newsletter */
        .newsletter-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, #c99a4d 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .newsletter-section::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .newsletter-section::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .newsletter-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .newsletter-form .form-control {
            height: 50px;
            border-radius: 30px;
            border: none;
            padding-left: 20px;
            box-shadow: none;
        }
        
        .newsletter-form .btn {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 30px;
            padding: 10px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }
        
        .newsletter-form .btn:hover {
            background-color: var(--dark-color);
        }
        
        /* Back to Top Button */
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
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: var(--secondary-color);
            transform: translateY(-5px);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .banner_taital {
                font-size: 2.5rem;
            }
            
            .banner_text {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .newsletter-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header_section">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php"><img src="/LandingPage/images/resized_food_corp_logo.png" alt="Top Food Exchange Corp. Logo"></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item active">
                            <a class="nav-link" href="index.php">Hom123123e</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/about.php">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/ordering.php">Products</a>
                        </li>        
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/contact.php">Contact Us</a>
                        </li>
                    </ul>
                    <form class="form-inline my-2 my-lg-0">
                        <div class="login_bt">
                            <?php if (isset($_SESSION['username'])): ?>
                                <a href="#" class="cart-button" data-toggle="modal" data-target="#cartModal">
                                    <span style="color: #222222;"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="badge badge-danger"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
                                </a>
                                <a href="/LandingPage/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>) 
                                    <span style="color: #222222;"><i class="fa fa-sign-out" aria-hidden="true"></i></span>
                                </a>
                            <?php else: ?>
                                <a href="/LandingPage/login.php">Login 
                                    <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </a>
                                <a href="/public/login.php" style="margin-left: 15px;">Admin Login
                                    <span style="color: #222222;"><i class="fa fa-lock" aria-hidden="true"></i></span>
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
        
        <!-- banner section start --> 
        <div class="banner_section layout_padding">
            <div class="container">
                <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
                    <ol class="carousel-indicators">
                        <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
                        <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
                        <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
                        <li data-target="#carouselExampleIndicators" data-slide-to="3"></li>
                    </ol>
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h1 class="banner_taital" data-aos="fade-down">Premium Siopao</h1>
                                    <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Experience the authentic taste of our handcrafted siopao, made with premium ingredients and traditional recipes perfected over generations.</p>
                                    <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
                                </div>
                                <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                                    <div class="banner_img"><img src="/LandingPage/images/Siopao.png" alt="Premium Siopao"></div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h1 class="banner_taital" data-aos="fade-down">Delicious Siomai</h1>
                                    <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our signature siomai combines premium pork with special seasonings, wrapped in thin, delicate wonton wrappers for an unforgettable flavor experience.</p>
                                    <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
                                </div>
                                <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                                    <div class="banner_img"><img src="/LandingPage/images/Siomai.png" alt="Delicious Siomai"></div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h1 class="banner_taital" data-aos="fade-down">Flavorful Sauces</h1>
                                    <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our special blend of sauces enhances every bite. Made from premium ingredients and secret recipes passed down through generations of master chefs.</p>
                                    <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
                                </div>
                                <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                                    <div class="banner_img"><img src="/LandingPage/images/Sauces.png" alt="Flavorful Sauces"></div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h1 class="banner_taital" data-aos="fade-down">Quality Noodles</h1>
                                    <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Made from the finest ingredients, our noodles maintain perfect texture and absorb flavors beautifully whether stir-fried, boiled, or used in soups.</p>
                                    <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
                                </div>
                                <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                                    <div class="banner_img"><img src="/LandingPage/images/Noodles.png" alt="Quality Noodles"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="sr-only">Previous</span>
                    </a>
                    <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="sr-only">Next</span>
                    </a>
                </div>
            </div>
        </div>
        <!-- banner section end -->
    </div>
    <!-- header section end -->
    
    <!-- Features Section -->
    <div class="about_section" style="padding: 100px 0; background-color: #f8f9fa;">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-12 text-center">
                    <h2 class="section-title" data-aos="fade-up">Why Choose Us</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Quality, tradition, and excellence since 1998</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3 class="feature-title">Premium Quality</h3>
                        <p class="feature-text">We use only the finest ingredients and maintain strict quality control to ensure every product meets our high standards.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="feature-title">25+ Years Experience</h3>
                        <p class="feature-text">With over two decades in the industry, we've perfected our recipes and processes to deliver consistent excellence.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-truck-fast"></i>
                        </div>
                        <h3 class="feature-title">Fast Delivery</h3>
                        <p class="feature-text">We ensure timely delivery of your orders with proper packaging to maintain product freshness and quality.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- About Section -->
    <div class="about_section layout_padding" style="background-color: #fff; padding: 100px 0;">
        <div class="container">
            <div class="row">
                <div class="col-md-6" data-aos="fade-right">
                    <div class="about_img"><img src="/LandingPage/images/about_image_1.png" alt="About Top Food Exchange Corp." class="img-fluid"></div>
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <h1 class="about_taital">Top Exchange Food Corp.</h1>
                    <p class="about_text"><strong>Top Exchange Food Corporation ()</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998 as a single-product supplier, responding to the growing demand for delicious homemade siomai and other dimsum products among Filipinos.</p>
                    <p class="about_text">Today, we continue to meet this need with the help of various partners in our international network of supply resources, providing high-quality Filipino food that represents our commitment to quality, reliability, and superior taste.</p>
                    <div class="read_bt_1" data-aos="fade-up" data-aos-delay="400"><a href="/LandingPage/about.php">Read More</a></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Products Section -->
    <div class="cream_section layout_padding" style="padding: 100px 0; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-12 text-center">
                    <h2 class="section-title" data-aos="fade-up">Our Best Sellers</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Customer favorites that never disappoint</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="images/siomai-product.jpg" alt="Premium Pork Siomai">
                            <span class="product-badge">BESTSELLER</span>
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱280</div>
                            <h5 class="product-title">Premium Pork Siomai</h5>
                            <p class="product-description">1kg pack (approx. 50 pieces) of our signature pork siomai with special seasonings.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <span class="ml-2">(128 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="/LandingPage/images/sharksfin-product.jpg" alt="Special Sharksfin Dumpling">
                            <span class="product-badge">POPULAR</span>
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱260</div>
                            <h5 class="product-title">Special Sharksfin Dumpling</h5>
                            <p class="product-description">1kg pack (approx. 45 pieces) of our premium sharksfin dumplings.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                <span class="ml-2">(96 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="/LandingPage/images/wanton-product.jpg" alt="Wanton Regular">
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱315</div>
                            <h5 class="product-title">Wanton Regular</h5>
                            <p class="product-description">1kg pack (approx. 60 pieces) of our classic wanton dumplings.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                                <span class="ml-2">(87 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="/LandingPage/images/egg-noodles-product.jpg" alt="Dried Egg Noodles">
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱185</div>
                            <h5 class="product-title">Dried Egg Noodles</h5>
                            <p class="product-description">500g pack (serves 4-5 people) of our premium dried egg noodles.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                <span class="ml-2">(112 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="/LandingPage/images/pancit-product.jpg" alt="Pancit Canton">
                            <span class="product-badge">NEW</span>
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱350</div>
                            <h5 class="product-title">Pancit Canton</h5>
                            <p class="product-description">1kg pack (serves 8-10 people) of our premium pancit canton noodles.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <span class="ml-2">(64 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="/LandingPage/images/siopao-product.jpg" alt="Asado Siopao">
                        </div>
                        <div class="product-body">
                            <div class="product-price">₱280</div>
                            <h5 class="product-title">Asado Siopao</h5>
                            <p class="product-description">10 pieces pack (regular size) of our classic asado-filled siopao.</p>
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                                <span class="ml-2">(143 reviews)</span>
                            </div>
                            <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="seemore_bt mt-5" data-aos="fade-up"><a href="/LandingPage/ordering.php">View All Products</a></div>
        </div>
    </div>
    
    <!-- Testimonials Section -->
    <div class="testimonial-section" style="padding: 100px 0; background-color: #f8f9fa;">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-12 text-center">
                    <h2 class="section-title" data-aos="fade-up">What Our Clients Say</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Trusted by restaurants and food businesses nationwide</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"Top Food Exchange Corp. has been our reliable supplier for over 5 years. Their siomai quality is consistently excellent, and our customers love it!"</p>
                        <div class="testimonial-author">Maria Santos</div>
                        <div class="testimonial-position">Owner, Mila's Carinderia</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"The asado siopao from  is our bestseller! The flavor is perfect, and the texture is always consistent. Highly recommended for food businesses."</p>
                        <div class="testimonial-author">Juan Dela Cruz</div>
                        <div class="testimonial-position">Manager, Kainan sa Kanto</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"We switched to 's noodles last year and never looked back. The quality is superior, and their delivery is always on time."</p>
                        <div class="testimonial-author">Liza Tan</div>
                        <div class="testimonial-position">Chef, Lutong Bahay Restaurant</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Newsletter Section -->
    <div class="newsletter-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6" data-aos="fade-right">
                    <h2 class="newsletter-title">Join Our Newsletter</h2>
                    <p class="newsletter-text">Subscribe to get updates on new products, special offers, and industry tips.</p>
                </div>
                <div class="col-md-6" data-aos="fade-left">
                    <form class="newsletter-form">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email address" required>
                            <div class="input-group-append">
                                <button class="btn" type="submit">Subscribe</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Top Button -->
    <a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>
    
    <!-- copyright section start -->
    <div class="copyright_section">
        <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>
    <!-- copyright section end -->
    
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
        // Initialize AOS animation - This should always run
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Back to top button - This should always run
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('.back-to-top').addClass('active');
            } else {
                $('.back-to-top').removeClass('active');
            }
        });
        
        $('.back-to-top').click(function(e) {
            e.preventDefault();
            $('html, body').animate({scrollTop: 0}, '300');
        });
        
        // Newsletter form submission - This should always run
        $('.newsletter-form').submit(function(e) {
            e.preventDefault();
            const email = $(this).find('input[type="email"]').val();
            showPopup("Thank you for subscribing to our newsletter!");
            $(this).find('input[type="email"]').val('');
        });
        
        // Product hover effect - This should always run
        $('.product-card').hover(
            function() {
                $(this).find('.product-btn').css('background-color', '#9a7432');
            },
            function() {
                $(this).find('.product-btn').css('background-color', 'var(--primary-color)');
            }
        );
        
        // Function to show custom popup message - This should always be available
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
        // Only the cart-related JavaScript should be inside this condition
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