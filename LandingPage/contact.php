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
    <title>Contact</title>
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
                        <div class="fa fa-search form-control-feedback"></div>
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

    <!-- contact section start -->
    <div class="contact_section layout_padding">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="contact_main">
                        <h1 class="contact_taital">Contact Us</h1>
                        <form action="/action_page.php">
                            <div class="form-group">
                                <input type="text" class="email-bt" placeholder="Name" name="Name">
                            </div>
                            <div class="form-group">
                                <input type="text" class="email-bt" placeholder="Email" name="Name">
                            </div>
                            <div class="form-group">
                                <input type="text" class="email-bt" placeholder="Phone Number" name="Email">
                            </div>
                            <div class="form-group">
                                <textarea class="massage-bt" placeholder="Massage" rows="5" id="comment" name="Massage"></textarea>
                            </div>
                        </form>
                        <div class="main_bt"><a href="#">SEND</a></div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="location_text">
                        <ul>
                            <li>
                                <a href="https://maps.app.goo.gl/mJtw3QR2Bks5sGCd8">
                                <span class="padding_left_10 active"><i class="fa fa-map-marker" aria-hidden="true"></i></span>74 M.H. Del Pilar St, Quezon City, 1105 Metro Manila</a>
                            </li>
                            <li>
                                <a href="#">
                                <span class="padding_left_10"><i class="fa fa-phone" aria-hidden="true"></i></span>Call : (02) 86307145
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                <span class="padding_left_10"><i class="fa fa-envelope" aria-hidden="true"></i></span>Email : aedanevangelista.capstone2@gmail.com
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="footer_social_icon">
                        <ul>
                            <li><a href="https://www.facebook.com/secretrecipesfoodcorp"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                            <li><a href="https://www.dnb.com/business-directory/company-profiles.top_exchange_food_corp.b2cf650e8e0ac9905e5e4a3c92fa7e68.html"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                            <li><a href="https://ph.linkedin.com/in/mr-choi-head-office-mayeth-tagle-2ba923110?original_referer=https%3A%2F%2Fwww.google.com%2F"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                            <li><a href="https://www.instagram.com/topexchangefoodcorp/"><i class="fa fa-instagram" aria-hidden="true"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- contact section end -->
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
</body>
</html>